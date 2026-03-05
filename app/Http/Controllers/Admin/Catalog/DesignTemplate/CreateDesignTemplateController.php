<?php

namespace App\Http\Controllers\Admin\Catalog\DesignTemplate;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Catalog\DesignTemplate\StoreDesignTemplateRequest;
use App\Models\Catalog\DesignTemplate\CatalogDesignTemplate;
use App\Models\Catalog\DesignTemplate\CatalogDesignTemplateLayer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class CreateDesignTemplateController extends Controller
{
    /* ---------------------------------------------
     | Show Create Page
     |----------------------------------------------*/
    public function create()
    {
        return view('admin.catalog.design-template.create');
    }

    /* ---------------------------------------------
     | Store Template
     |----------------------------------------------*/
    public function store(StoreDesignTemplateRequest $request)
    {
        DB::beginTransaction();
        try {
            $template = $this->createTemplate($request);
            $this->storeLayers($template->id, $request->layers);
            DB::commit();

            return response()->json([
                'status' => true,
                'message' => __('Design template created successfully.'),
            ], Response::HTTP_CREATED);
        } catch (\Throwable $e) {
            DB::rollBack();
            report($e);

            return response()->json([
                'status' => false,
                'message' => __('Something went wrong while creating the template.'),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /* ---------------------------------------------
     | Create Template
     |----------------------------------------------*/
    protected function createTemplate(StoreDesignTemplateRequest $request): CatalogDesignTemplate
    {
        return CatalogDesignTemplate::create([
            'name' => $request->templateName,
            'status' => $request->templateStatus,
        ]);
    }

    /* ---------------------------------------------
     | Store Layers
     |----------------------------------------------*/
    protected function storeLayers(int $templateId, array $layers): void
    {
        foreach ($layers as $layer) {
            CatalogDesignTemplateLayer::create([
                'catalog_design_template_id' => $templateId,
                'layer_name' => $layer['layerName'],
                'coordinates' => $layer['coordinates'],
                'image' => $this->sanitizeImagePath($layer['image'] ?? null),
                'is_neck_layer' => $layer['is_neck_layer'] ?? false,
            ]);
        }
    }

    /* ---------------------------------------------
     | Image Path Cleanup
     |----------------------------------------------*/
    protected function sanitizeImagePath(?string $image): ?string
    {
        if (empty($image)) {
            return null;
        }
        $image = str_replace('\\', '/', $image);
        $image = preg_replace('#^https?://[^/]+/#', '', $image);
        $storagePrefix = trim(Storage::url('/'), '/');

        return ltrim(str_replace($storagePrefix, '', $image), '/');
    }
}
