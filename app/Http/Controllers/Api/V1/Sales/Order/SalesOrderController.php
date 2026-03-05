<?php

namespace App\Http\Controllers\Api\V1\Sales\Order;

use App\Enums\Order\OrderStatus;
use App\Enums\Order\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Sales\Order\OrderListResource;
use App\Http\Resources\Api\V1\Sales\Order\OrderResource;
use App\Models\Sales\Order\SalesOrder;
use App\Models\Sales\Order\SalesOrderSource;
use App\Services\Customer\Cart\CartService;
use App\Services\Customer\CustomerResolverService;
use App\Services\Sales\Order\CartToOrderService;
use Exception;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class SalesOrderController extends Controller
{
    public function __construct(
        protected CartService $cartService,
        protected CartToOrderService $cartToOrderService,
        protected CustomerResolverService $customerResolverService
    ) {}

    /**
     * List orders with filtering and caching.
     * Supports both Customer (their orders) and Admin (all orders or specific customer).
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $customer = null;
            $isAdmin = Auth::guard('admin_api')->check();
            $factoryGuard = Auth::guard('factory');
            $isFactory = $factoryGuard->check();
            $factoryId = $isFactory ? $factoryGuard->id() : null;
            $query = null;

            if ($isAdmin && ! $request->has('customer_id')) {
                // Admin viewing all orders
                $query = SalesOrder::query();
            } elseif ($isFactory) {
                // Factory viewing their orders (Note: factory auth intentionally overrides any customer_id parameter)
                $query = SalesOrder::where('factory_id', $factoryId);
            } else {
                // Customer or Admin viewing specific customer (via customerResolverService->resolve)
                $customer = $this->customerResolverService->resolve($request);

                if (! $customer) {
                    return response()->json([
                        'status' => false,
                        'message' => 'Unauthorized',
                    ], 401);
                }
                $query = $customer->orders();
            }

            // Validation with explicit rules (strict)
            $validator = Validator::make($request->all(), [
                'per_page' => 'integer|min:1|max:100',
                'page' => 'integer|min:1',
                'order_number' => 'nullable|string',
                'search' => 'nullable|string',
                'source' => 'nullable|string|in:airventory,manual,shopify,woocommerce',
                'source_name' => 'nullable|string|max:255',
                'status' => 'nullable|string|in:'.implode(',', OrderStatus::values()),
                'payment_status' => 'nullable|string|in:'.implode(',', PaymentStatus::values()),
                'start_date' => 'nullable|date',
                'end_date' => 'nullable|date|after_or_equal:start_date',
                'sort_by' => 'nullable|string|in:created_at,grand_total_inc_margin,grand_total,order_status,id',
                'sort_dir' => 'nullable|string|in:asc,desc',
            ]);
            if ($validator->fails()) {
                return response()->json([
                    'status' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }
            $validated = $validator->validated();

            // Factories can only view paid orders; remove source filters for them
            if ($isFactory) {
                $validated['payment_status'] = PaymentStatus::Paid->value;
                unset($validated['source'], $validated['source_name']);
            }

            $perPage = $validated['per_page'] ?? 20;
            $page = $validated['page'] ?? 1;

            // Generate a deterministic cache key based on validated parameters
            if ($isFactory) {
                $cacheContext = "factory_{$factoryId}";
                $cacheVersion = $this->getFactoryOrderCacheVersion($factoryId);
            } elseif ($customer) {
                $cacheContext = "customer_{$customer->id}";
                $cacheVersion = $this->getOrderCacheVersion($customer->id);
            } else {
                $cacheContext = 'admin_global';
                $cacheVersion = $this->getGlobalOrderCacheVersion();
            }

            // Sort keys to ensure same params order produces same key
            ksort($validated);
            $cacheKey = "orders:{$cacheContext}:v{$cacheVersion}:".md5(json_encode($validated));
            $cacheDuration = 60 * 5; // 5 minutes cache

            $cachedData = Cache::remember($cacheKey, $cacheDuration, function () use ($query, $validated, $perPage, $page, $isAdmin, $isFactory) {
                $query->select([
                    'id',
                    'customer_id',
                    'factory_id',
                    'order_number',
                    'created_at',
                    'grand_total',
                    'grand_total_inc_margin',
                    'order_status',
                    'payment_status',
                ])
                    ->with([
                        'sourceInfo' => function ($q) {
                            $q->select([
                                'id',
                                'order_id',
                                'platform',
                                'source',
                                'source_order_id',
                                'source_order_number',
                                'source_created_at',
                            ])->with(['channel' => function ($q) {
                                $q->select(['code', 'logo']);
                            }]);
                        },
                        'addresses' => function ($q) {
                            $q->select(['id', 'order_id', 'first_name', 'last_name', 'email', 'phone', 'address_type']);
                        },
                    ]);

                // For admins and factories, also load customer details to know who placed the order
                if ($isAdmin || $isFactory) {
                    $query->with('customer:id,first_name,last_name,email');
                }
                if ($isAdmin) {
                    $query->with('factory.business');
                }

                // Apply Filters
                if (! empty($validated['order_number'])) {
                    $query->where('order_number', 'like', '%'.$validated['order_number'].'%');
                }

                if (! empty($validated['search'])) {
                    $term = trim((string) $validated['search']);
                    $lower = strtolower($term);
                    $query->where(function ($q) use ($term, $lower) {
                        $q->where('order_number', 'like', "%{$term}%")
                            ->orWhereHas('shippingAddress', function ($subQ) use ($term, $lower) {
                                $subQ->where('first_name', 'like', "%{$term}%")
                                    ->orWhere('last_name', 'like', "%{$term}%")
                                    ->orWhereRaw("LOWER(TRIM(CONCAT(COALESCE(first_name,''),' ',COALESCE(last_name,'')))) LIKE ?", ['%'.$lower.'%'])
                                    ->orWhere('email', 'like', "%{$term}%")
                                    ->orWhere('phone', 'like', "%{$term}%");
                            })
                            ->orWhereHas('billingAddress', function ($subQ) use ($term, $lower) {
                                $subQ->where('first_name', 'like', "%{$term}%")
                                    ->orWhere('last_name', 'like', "%{$term}%")
                                    ->orWhereRaw("LOWER(TRIM(CONCAT(COALESCE(first_name,''),' ',COALESCE(last_name,'')))) LIKE ?", ['%'.$lower.'%'])
                                    ->orWhere('email', 'like', "%{$term}%")
                                    ->orWhere('phone', 'like', "%{$term}%");
                            });
                    });
                }

                if (! empty($validated['status'])) {
                    $query->where('order_status', $validated['status']);
                }

                if (! empty($validated['payment_status'])) {
                    $query->where('payment_status', $validated['payment_status']);
                }

                // Filter by source/platform
                if (! empty($validated['source'])) {
                    $source = strtolower($validated['source']);
                    if (in_array($source, ['airventory', 'manual'], true)) {
                        $query->whereDoesntHave('sourceInfo');
                    } else {
                        // External platform orders
                        $query->whereHas('sourceInfo', function ($q) use ($source) {
                            $q->where('platform', $source);
                        });
                    }
                }

                if (! empty($validated['start_date'])) {
                    $query->whereDate('created_at', '>=', $validated['start_date']);
                }

                if (! empty($validated['end_date'])) {
                    $query->whereDate('created_at', '<=', $validated['end_date']);
                }

                // Build dynamic list of available store names (sources) in the current filtered context
                $availableSourceOptions = [];
                if (! $isFactory) {
                    $baseOrderIds = (clone $query)->select('id')->pluck('id');
                    $availableSources = SalesOrderSource::query()
                        ->whereIn('order_id', $baseOrderIds)
                        ->whereNotNull('source')
                        ->distinct()
                        ->orderBy('source', 'asc')
                        ->pluck('source')
                        ->toArray();
                    $hasAirventory = (clone $query)->whereDoesntHave('sourceInfo')->exists();

                    if ($hasAirventory) {
                        $availableSourceOptions[] = ['label' => 'Airventory Order', 'value' => 'airventory'];
                    }

                    foreach ($availableSources as $src) {
                        $availableSourceOptions[] = ['label' => $src, 'value' => $src];
                    }
                }

                // Apply filter by specific store name (source_name)
                if (! empty($validated['source_name'])) {
                    $sourceName = strtolower($validated['source_name']);
                    if (in_array($sourceName, ['airventory', 'manual'], true)) {
                        $query->whereDoesntHave('sourceInfo');
                    } else {
                        $query->whereHas('sourceInfo', function ($q) use ($sourceName) {
                            $q->where('source', $sourceName);
                        });
                    }
                }

                // Sorting
                $sortField = $validated['sort_by'] ?? 'created_at';
                $sortDirection = $validated['sort_dir'] ?? 'desc';
                if ($sortField === 'grand_total_inc_margin' || $sortField === 'grand_total') {
                    $directionSql = $sortDirection === 'asc' ? 'ASC' : 'DESC';
                    $driver = DB::getDriverName();
                    if ($driver === 'sqlite') {
                        $query->orderByRaw("CAST({$sortField} AS REAL) {$directionSql}");
                    } else {
                        $query->orderByRaw("CAST({$sortField} AS DECIMAL(10,4)) {$directionSql}");
                    }
                } else {
                    $query->orderBy($sortField, $sortDirection);
                }

                // Execute query without pagination to avoid serializing paginator
                $total = $query->count();
                $items = $query->forPage($page, $perPage)->get();

                return [
                    'items' => $items,
                    'total' => $total,
                    'available_sources' => $availableSourceOptions,
                ];
            });

            // Reconstruct paginator from cached data
            $orders = new LengthAwarePaginator(
                $cachedData['items'],
                $cachedData['total'],
                $perPage,
                $page,
                ['path' => $request->url(), 'query' => $request->query()]
            );

            $filters = [
                'search' => [
                    'key' => 'order_number',
                    'label' => __('Find orders using Order Number, Name, Phone, or Email'),
                    'type' => 'text',
                ],
                'statuses' => [
                    'key' => 'status',
                    'label' => 'Order Status',
                    'type' => 'select',
                    'options' => OrderStatus::options(),
                ],
                'date_range' => [
                    'start_key' => 'start_date',
                    'end_key' => 'end_date',
                    'label' => 'Date Range',
                    'type' => 'date_range',
                ],
            ];

            if (! $isFactory) {
                $filters['source_name'] = [
                    'key' => 'source_name',
                    'label' => 'Store',
                    'type' => 'select',
                    'options' => $cachedData['available_sources'] ?? [],
                ];
                $filters['payment_status'] = [
                    'key' => 'payment_status',
                    'label' => 'Payment Status',
                    'type' => 'select',
                    'options' => PaymentStatus::options(),
                ];
            }

            return response()->json([
                'status' => true,
                'message' => 'Orders retrieved successfully',
                'data' => OrderListResource::collection($orders)->resolve(),
                'pagination' => [
                    'total' => $orders->total(),
                    'count' => $orders->count(),
                    'per_page' => $orders->perPage(),
                    'current_page' => $orders->currentPage(),
                    'total_pages' => $orders->lastPage(),
                ],
                'filters' => $filters,
                'sorting' => [
                    'key' => 'sort_by',
                    'direction_key' => 'sort_dir',
                    'options' => [
                        ['label' => 'Date', 'value' => 'created_at'],
                        ['label' => 'Total Amount', 'value' => 'grand_total_inc_margin'],
                        ['label' => 'Grand Total (Base)', 'value' => 'grand_total'],
                        ['label' => 'Order Status', 'value' => 'order_status'],
                        ['label' => 'Order ID', 'value' => 'id'],
                    ],
                    'default' => [
                        'sort_by' => 'created_at',
                        'sort_dir' => 'desc',
                    ],
                ],
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (QueryException $e) {
            Log::error('Failed to list orders (query)', [
                'error' => $e->getMessage(),
                'customer_id' => $request->user('customer')?->id ?? $request->input('customer_id'),
            ]);

            return response()->json([
                'status' => false,
                'message' => 'Database query failed',
                'error' => config('app.debug') ? $e->getMessage() : 'An unexpected database error occurred',
            ], 500);
        } catch (Exception $e) {
            Log::error('Failed to list orders', [
                'error' => $e->getMessage(),
                'customer_id' => $request->user('customer')?->id ?? $request->input('customer_id'),
            ]);

            return response()->json([
                'status' => false,
                'message' => 'Failed to retrieve orders',
                'error' => config('app.debug') ? $e->getMessage() : 'An unexpected error occurred',
            ], 500);
        }
    }

    /**
     * Convert active cart into order(s)
     */
    public function store(Request $request): JsonResponse
    {
        try {
            // Resolve logged-in customer
            $customer = $this->customerResolverService->resolve($request);

            if (! $customer) {
                return response()->json([
                    'status' => false,
                    'message' => 'Unauthorized',
                ], 401);
            }

            $response = DB::transaction(function () use ($customer) {
                // Get active cart with lock
                $cart = $this->cartService->getActiveCartForUpdate($customer->id);

                if (! $cart || $cart->items->isEmpty()) {
                    return response()->json([
                        'status' => false,
                        'message' => 'Cart is empty',
                    ], 422);
                }

                // Convert cart to orders
                $orders = $this->cartToOrderService->convert($cart);

                // Load relationships for the response
                $orders->each(function ($order) {
                    $order->load(['billingAddress', 'shippingAddress', 'items']);
                });

                $paymentAmount = $orders->sum('grand_total_inc_margin');

                return response()->json([
                    'status' => true,
                    'message' => 'Order placed successfully',
                    'data' => [
                        'orders' => OrderResource::collection($orders),
                        'payment_amount' => [
                            'raw_price' => $paymentAmount,
                            'formatted' => format_price($paymentAmount),
                        ],
                    ],
                ], 201);
            });

            return $response;

        } catch (Exception $e) {
            Log::error('Failed to place order', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'customer_id' => $request->user('customer')?->id ?? $request->input('customer_id'),
            ]);

            return response()->json([
                'status' => false,
                'message' => 'Failed to place order',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Get the current cache version for customer orders.
     */
    private function getOrderCacheVersion(int $customerId): string
    {
        return (string) Cache::rememberForever("orders_version:customer_{$customerId}", function () {
            return time();
        });
    }

    /**
     * Get the current cache version for global admin orders.
     */
    private function getGlobalOrderCacheVersion(): string
    {
        return (string) Cache::rememberForever('orders_version:admin_global', function () {
            return time();
        });
    }

    /**
     * Get the current cache version for factory orders.
     */
    private function getFactoryOrderCacheVersion(int $factoryId): string
    {
        return (string) Cache::rememberForever("orders_version:factory_{$factoryId}", function () {
            return time();
        });
    }
}
