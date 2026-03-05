<?php

namespace App\Http\Controllers\Admin\Catalog\DesignTemplate;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Catalog\DesignTemplate\UpdateDesignTemplateRequest;
use App\Models\Catalog\DesignTemplate\CatalogDesignTemplate;
use App\Models\Catalog\DesignTemplate\CatalogDesignTemplateLayer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class EditDesignTemplateController extends Controller
{
    public function edit(Request $request, CatalogDesignTemplate $designTemplate)
    {
        $designTemplate->load('layers')->layers->map(function ($layer) {
            $layer->image = getImageUrl($layer->image);
        });

        return view('admin.catalog.design-template.edit', compact('designTemplate'));
    }

    public function update(UpdateDesignTemplateRequest $request, CatalogDesignTemplate $designTemplate)
    {
        DB::beginTransaction();
        try {
            $designTemplate = CatalogDesignTemplate::where('id', $designTemplate->id)
                ->lockForUpdate()
                ->first();
            $designTemplate->update([
                'name' => $request->templateName,
                'status' => $request->templateStatus,
            ]);
            $incomingLayerIds = collect($request->layers)->pluck('id')->filter()->values()->toArray();
            $designTemplate->layers()->whereNotIn('id', $incomingLayerIds)->delete();
            foreach ($request->layers as $layer) {
                $path = parse_url($layer['image'], PHP_URL_PATH) ?? $layer['image'];
                $image = ltrim($path, '/');
                $payload = [
                    'layer_name' => $layer['layerName'],
                    'coordinates' => $layer['coordinates'],
                    'image' => $image,
                    'is_neck_layer' => $layer['is_neck_layer'] ?? false,
                ];
                if (! empty($layer['id']) && is_numeric($layer['id'])) {
                    $designTemplate->layers()
                        ->where('id', $layer['id'])
                        ->update($payload);
                } else {
                    $designTemplate->layers()->create($payload);
                }
            }
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => __('Design template updated successfully.'),
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            report($e);

            return response()->json([
                'success' => false,
                'message' => __('Failed to update design template.'),
            ], 500);
        }
    }

    public function destroy(CatalogDesignTemplateLayer $layer): JsonResponse
    {
        DB::beginTransaction();
        try {
            if ($layer->products()->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => __('This layer is already used by one or more products and cannot be deleted.'),
                ], 422);
            }
            $layer_image = $layer->image;
            $layer->delete();
            DB::commit();
            if ($layer_image) {
                Storage::delete($layer_image);
            }

            return response()->json([
                'success' => true,
                'message' => __('Layer removed successfully.'),
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            report($e);

            return response()->json([
                'success' => false,
                'message' => __('Failed to remove layer.'),
            ], 500);
        }
    }
}
