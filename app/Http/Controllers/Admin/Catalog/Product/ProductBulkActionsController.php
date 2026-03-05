<?php

namespace App\Http\Controllers\Admin\Catalog\Product;

use App\Http\Controllers\Controller;
use App\Models\Catalog\Product\CatalogProduct;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

class ProductBulkActionsController extends Controller
{
    /**
     * Handle bulk actions for products.
     */
    public function bulkAction(Request $request)
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => [
                'integer',
                Rule::exists('catalog_products', 'id'),
            ],
            'action' => [
                'required',
                Rule::in(['enable', 'disable', 'delete']),
            ],
        ]);

        $ids = $validated['ids'];
        $action = $validated['action'];

        $affectedRows = 0;

        DB::transaction(function () use ($ids, $action, &$affectedRows) {
            $query = CatalogProduct::whereIn('id', $ids);

            $affectedRows = match ($action) {
                'enable' => $query->update(['status' => true]),
                'disable' => $query->update(['status' => false]),
                'delete' => $query->delete(), // soft delete
            };
        });

        if ($affectedRows === 0) {
            return response()->json([
                'success' => false,
                'message' => __('No products were affected.'),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        $store = Cache::store(config('cache.catalog_store'));
        $store->increment('product_card_version:user');
        $store->increment('product_card_version:admin');

        return response()->json([
            'success' => true,
            'message' => $this->successMessage($action, $affectedRows),
            'count' => $affectedRows,
        ]);
    }

    /**
     * Get success message based on action.
     */
    protected function successMessage(string $action, int $count): string
    {
        return match ($action) {
            'enable' => __(':count product(s) enabled successfully.', ['count' => $count]),
            'disable' => __(':count product(s) disabled successfully.', ['count' => $count]),
            'delete' => __(':count product(s) deleted successfully.', ['count' => $count]),
        };
    }
}
