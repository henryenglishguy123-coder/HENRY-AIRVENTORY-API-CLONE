<?php

namespace App\Http\Controllers\Admin\Catalog\Category;

use App\Http\Controllers\Controller;
use App\Models\Catalog\Category\CatalogCategory;
use App\Models\Catalog\Category\CatalogCategoryMeta;
use App\Models\Catalog\Products\CatalogProductCategory;
use Illuminate\Http\Request;

class CategoryActions extends Controller
{
    public function bulkAction(Request $request)
    {
        $request->validate([
            'action' => ['required', 'string', 'in:enable,disable,delete'],
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'exists:catalog_categories,id'],
        ]);
        $action = $request->input('action');
        $ids = $request->input('ids');

        switch ($action) {
            case 'enable':
                CatalogCategoryMeta::whereIn('catalog_category_id', $ids)->update(['status' => 1]);

                return response()->json([
                    'success' => true,
                    'message' => __('Selected categories have been enabled.'),
                ]);

            case 'disable':
                CatalogCategoryMeta::whereIn('catalog_category_id', $ids)->update(['status' => 0]);

                return response()->json([
                    'success' => true,
                    'message' => __('Selected categories have been disabled.'),
                ]);

            case 'delete':
                $usedCount = CatalogProductCategory::whereIn('catalog_category_id', $ids)->count();

                if ($usedCount > 0) {
                    return response()->json([
                        'success' => false,
                        'message' => __('One or more categories are currently in use.'),
                    ]);
                }

                CatalogCategory::whereIn('id', $ids)->delete();

                return response()->json([
                    'success' => true,
                    'message' => __('Selected categories have been deleted.'),
                ]);
        }
    }

    public function getCategories($industryId)
    {
        $excludeId = request()->input('exclude', 0);

        $categories = CatalogCategory::with(['meta', 'children.meta'])
            ->where('catalog_industry_id', $industryId)
            ->where('id', '!=', $excludeId)
            ->whereNull('parent_id')
            ->get();

        $categories->each(fn ($category) => $this->loadChildrenRecursively($category));

        return response()->json([
            'categories' => $categories,
        ]);
    }

    private function loadChildrenRecursively($category)
    {
        $category->children->load(['meta', 'children']);
        $category->children->each(fn ($child) => $this->loadChildrenRecursively($child));
    }
}
