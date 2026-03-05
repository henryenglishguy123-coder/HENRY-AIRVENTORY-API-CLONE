<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Customer\SearchResultResource;
use App\Services\Customer\CustomerResolverService;
use App\Services\Customer\SearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SearchController extends Controller
{
    public function __construct(
        protected SearchService $searchService,
        protected CustomerResolverService $customerResolverService
    ) {}

    /**
     * Unified search across customer resources
     */
    public function search(Request $request): JsonResponse
    {
        $customer = null;

        try {
            // Resolve customer (works for both customer and admin)
            $customer = $this->customerResolverService->resolve($request);

            if (! $customer) {
                return response()->json([
                    'status' => false,
                    'message' => 'Unauthorized',
                ], 401);
            }

            // Validation
            $validator = Validator::make($request->all(), [
                'q' => ['required', 'string', 'min:1', 'max:255', 'regex:/\S/'],
                'type' => 'nullable|string|in:all,orders,templates,stores,catalog',
                'page' => 'nullable|integer|min:1',
                'per_page' => 'nullable|integer|min:1|max:50',
                'min_price' => 'nullable|numeric|min:0',
                'max_price' => 'nullable|numeric|min:0',
                'category' => 'nullable',
                'brand' => 'nullable',
                'available' => 'nullable|boolean',
                'start_date' => 'nullable|date',
                'end_date' => 'nullable|date',
                'status' => 'nullable|string',
                'payment_status' => 'nullable|string',
                'platform' => 'nullable|string|in:shopify,woocommerce',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $validated = $validator->validated();
            $query = $validated['q'];
            $type = $validated['type'] ?? 'all';
            $page = (int) ($validated['page'] ?? 1);
            $perPage = (int) ($validated['per_page'] ?? 10);

            $filters = [
                'min_price' => $validated['min_price'] ?? null,
                'max_price' => $validated['max_price'] ?? null,
                'category' => $validated['category'] ?? null,
                'brand' => $validated['brand'] ?? null,
                'available' => array_key_exists('available', $validated) ? (bool) $validated['available'] : null,
                'start_date' => $validated['start_date'] ?? null,
                'end_date' => $validated['end_date'] ?? null,
                'status' => $validated['status'] ?? null,
                'payment_status' => $validated['payment_status'] ?? null,
                'platform' => $validated['platform'] ?? null,
            ];

            // Perform search
            $results = $this->searchService->search($customer, $query, $type, $perPage, $page, $filters);

            return response()->json([
                'status' => true,
                'data' => [
                    'query' => $query,
                    'type' => $type,
                    'results' => SearchResultResource::make($results)->resolve(),
                ],
            ]);

        } catch (\Exception $e) {
            \Log::error('Customer search failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'customer_id' => $customer?->id ?? $request->user('customer')?->id,
            ]);

            return response()->json([
                'status' => false,
                'message' => 'Search failed',
                'error' => config('app.debug') ? $e->getMessage() : 'An unexpected error occurred',
            ], 500);
        }
    }
}
