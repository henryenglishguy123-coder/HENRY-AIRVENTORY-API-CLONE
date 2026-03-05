<?php

namespace App\Http\Controllers\Admin\Catalog\Attributes;

use App\Http\Controllers\Controller;
use App\Models\Catalog\Attribute\CatalogAttribute;
use App\Models\Catalog\Attribute\CatalogAttributeOption;
use App\Models\Catalog\Products\CatalogProductAttribute;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AttributeActions extends Controller
{
    public function show(CatalogAttribute $catalogAttribute)
    {
        return view('admin.catalog.attribute.show', compact('catalogAttribute'));
    }

    public function deleteOptionValue(Request $request): JsonResponse
    {
        $optionId = $request->input('option_id');

        if (! $optionId) {
            return $this->jsonFail(__('Invalid option details provided.'));
        }

        $option = CatalogAttributeOption::where('option_id', $optionId)->first();

        if (! $option) {
            return $this->jsonFail(__('The specified option could not be found.'));
        }
        $isUsed = CatalogProductAttribute::where('attribute_value', $optionId)->exists();
        if ($isUsed) {
            return $this->jsonFail(__('This option is currently assigned to products and cannot be deleted.'));
        }
        try {
            return DB::transaction(function () use ($option) {
                $option->delete();

                return $this->jsonSuccess(__('The option has been deleted successfully.'));
            });
        } catch (\Exception $e) {
            // Transaction will automatically rollback on exception
            return $this->jsonFail(__('An error occurred while deleting the option. Please try again.'), 500);
        }
    }

    public function bulkAction(Request $request): JsonResponse
    {
        $data = $request->validate([
            'action' => 'required|string|in:enable,disable,delete',
            'ids' => 'required|array',
        ]);

        $attributes = CatalogAttribute::whereIn('attribute_id', $data['ids']);

        if (! $attributes->exists()) {
            return $this->jsonFail(__('No valid attributes were found for the requested action.'));
        }

        return DB::transaction(function () use ($attributes, $data) {
            switch ($data['action']) {
                case 'enable':
                    $attributes->update(['status' => 1]);

                    return $this->jsonSuccess(__('Selected attributes have been enabled successfully.'));

                case 'disable':
                    $attributes->update(['status' => 0]);

                    return $this->jsonSuccess(__('Selected attributes have been disabled successfully.'));

                case 'delete':
                    $inUse = CatalogProductAttribute::whereIn('catalog_attribute_id', $data['ids'])->exists();

                    if ($inUse) {
                        return $this->jsonFail(__('These attributes are currently used by products and cannot be deleted.'));
                    }

                    $hasGlobal = (clone $attributes)->where('is_global', 1)->exists();

                    if ($hasGlobal) {
                        return $this->jsonFail(__('Some attributes are marked as global and cannot be removed.'));
                    }

                    $attributes->delete();

                    return $this->jsonSuccess(__('Selected attributes have been deleted successfully.'));
            }
        });
    }

    /**
     * Helper: success response
     */
    private function jsonSuccess(string $message, int $code = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
        ], $code);
    }

    /**
     * Helper: fail response
     */
    private function jsonFail(string $message, int $code = 422): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
        ], $code);
    }
}
