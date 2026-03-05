<?php

namespace App\Http\Controllers\Admin\Catalog\Product;

use App\Http\Controllers\Controller;
use App\Models\Catalog\DesignTemplate\CatalogDesignTemplate;
use App\Models\Catalog\Product\CatalogProduct;
use App\Models\Catalog\Product\CatalogProductPrintingPrice;
use App\Models\Currency\Currency;
use App\Models\Factory\Factory;
use App\Models\PrintingTechnique\PrintingTechnique;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;

class ProductDesignTemplateController extends Controller
{
    private const CACHE_TTL = 3600;

    public function index(Request $request, int $productId)
    {
        $product = CatalogProduct::select(['id', 'sku', 'type'])
            ->where('id', $productId)
            ->where('type', 'configurable')
            ->with(['designTemplate.catalogDesignTemplate:id,name'])
            ->firstOrFail();
        $assignedFactoryIds = $this->getAssignedFactoryIds($productId);
        $assignedFactories = Factory::query()
            ->whereIn('id', $assignedFactoryIds)
            ->with(['business:id,factory_id,company_name', 'metas' => fn ($q) => $q->where('key', 'production_techniques')])
            ->get()
            ->map(fn ($factory) => [
                'id' => $factory->id,
                'business' => $factory->business,
                'metas' => $factory->metas->pluck('value', 'key')->all(),
            ]);
        $templates = Cache::remember('catalog_active_templates', self::CACHE_TTL, function () {
            return CatalogDesignTemplate::select('id', 'name')->where('status', 1)->get();
        });

        $printingTechniques = Cache::remember('catalog_active_techniques', self::CACHE_TTL, function () {
            return PrintingTechnique::select('id', 'name')->active()->get();
        });
        $assignedTemplate = $product->designTemplate?->catalogDesignTemplate;
        $savedPrices = $assignedTemplate
            ? $this->getFormattedPrices($product->id, $assignedTemplate->id)
            : [];
        $defaultCurrency = Currency::getDefaultCurrency();

        return view('admin.catalog.product.design-template.index', compact(
            'product', 'templates', 'assignedTemplate', 'printingTechniques',
            'assignedFactories', 'defaultCurrency', 'savedPrices'
        ));
    }

    public function storeConfiguration(Request $request, int $productId)
    {
        try {
            $validator = $this->validateConfiguration($request);
            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => __('Validation failed. Please check the inputs.'),
                    'errors' => $validator->errors(),
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
            $product = CatalogProduct::findOrFail($productId);
            DB::transaction(function () use ($request, $product) {
                $assignment = $product->designTemplate()->updateOrCreate(
                    ['catalog_product_id' => $product->id],
                    ['catalog_design_template_id' => $request->design_template_id]
                );
                $this->syncPrices($assignment, $product->id, $request->input('layer_printing', []));
            });

            return response()->json([
                'success' => true,
                'message' => __('Configuration saved successfully!'),
            ], Response::HTTP_OK);

        } catch (\Exception $e) {
            // Log::error("Design Template Save Error: " . $e->getMessage()); // Recommended
            return response()->json([
                'success' => false,
                'message' => __('An error occurred while saving: ').$e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function layers(Request $request, int $templateId)
    {
        $layers = Cache::remember("template_layers_{$templateId}", self::CACHE_TTL, function () use ($templateId) {
            return CatalogDesignTemplate::findOrFail($templateId)
                ->layers()
                ->get();
        });
        $productId = $request->input('product_id');
        $prices = $productId ? $this->getFormattedPrices($productId, $templateId) : [];

        return response()->json([
            'success' => true,
            'layers' => $layers,
            'prices' => $prices,
        ]);
    }

    private function validateConfiguration(Request $request): \Illuminate\Contracts\Validation\Validator
    {
        $rules = [
            'design_template_id' => ['required', 'exists:catalog_design_template,id'],
            'layer_printing' => ['required', 'array'],
            'layer_printing.*.*.*.enabled' => ['nullable'],
            'layer_printing.*.*.*.price' => ['required_if:layer_printing.*.*.*.enabled,1', 'nullable', 'numeric', 'min:0'],
        ];
        $validator = Validator::make($request->all(), $rules);
        $validator->after(function ($validator) use ($request) {
            $data = $request->input('layer_printing', []);
            if (empty($data)) {
                return;
            }
        });

        return $validator;
    }

    /**
     * Efficiently fetches factory IDs from child products using a direct query.
     */
    private function getAssignedFactoryIds(int $productId): array
    {
        $childIds = DB::table('catalog_product_parents')
            ->where('parent_id', $productId)
            ->pluck('catalog_product_id');
        if ($childIds->isEmpty()) {
            return [];
        }

        return DB::table('catalog_product_prices')->whereIn('catalog_product_id', $childIds)->distinct()->pluck('factory_id')->toArray();
    }

    private function syncPrices($assignment, int $productId, array $layerData): void
    {
        $upsertData = [];
        $activeKeys = [];
        $now = now();
        foreach ($layerData as $layerId => $factories) {
            if (! is_array($factories)) {
                continue;
            }
            foreach ($factories as $factoryId => $techniques) {
                if (! is_array($techniques)) {
                    continue;
                }
                foreach ($techniques as $techniqueId => $data) {
                    $isEnabled = isset($data['enabled']) && ($data['enabled'] == '1' || $data['enabled'] == 'on');
                    if ($isEnabled) {
                        $upsertData[] = [
                            'catalog_product_id' => $productId,
                            'catalog_product_design_template_id' => $assignment->id,
                            'layer_id' => $layerId,
                            'factory_id' => $factoryId,
                            'printing_technique_id' => $techniqueId,
                            'price' => $data['price'],
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];
                        $activeKeys[] = "{$layerId}_{$factoryId}_{$techniqueId}";
                    }
                }
            }
        }
        if (! empty($upsertData)) {
            CatalogProductPrintingPrice::upsert(
                $upsertData,
                ['catalog_product_design_template_id', 'layer_id', 'factory_id', 'printing_technique_id'],
                ['price', 'updated_at']
            );
        }
        if (! empty($activeKeys)) {
            $existingRecords = CatalogProductPrintingPrice::where('catalog_product_design_template_id', $assignment->id)->get(['id', 'layer_id', 'factory_id', 'printing_technique_id']);
            $idsToDelete = [];
            foreach ($existingRecords as $rec) {
                $key = "{$rec->layer_id}_{$rec->factory_id}_{$rec->printing_technique_id}";
                if (! in_array($key, $activeKeys)) {
                    $idsToDelete[] = $rec->id;
                }
            }
            if (! empty($idsToDelete)) {
                CatalogProductPrintingPrice::whereIn('id', $idsToDelete)->delete();
            }
        } else {
            CatalogProductPrintingPrice::where('catalog_product_design_template_id', $assignment->id)->delete();
        }
    }

    private function getFormattedPrices(int $productId, int $templateId): array
    {
        $prices = CatalogProductPrintingPrice::query()
            ->select(['layer_id', 'factory_id', 'printing_technique_id', 'price'])
            ->where('catalog_product_id', $productId)
            ->whereHas('designTemplateAssignment', function ($q) use ($templateId) {
                $q->where('catalog_design_template_id', $templateId);
            })
            ->get();
        $formatted = [];
        foreach ($prices as $p) {
            $formatted[$p->layer_id][$p->factory_id][$p->printing_technique_id] = $p->price;
        }

        return $formatted;
    }
}
