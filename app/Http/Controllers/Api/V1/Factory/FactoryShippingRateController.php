<?php

namespace App\Http\Controllers\Api\V1\Factory;

use App\Exports\FactoryShippingRateExport;
use App\Http\Controllers\Controller;
use App\Imports\FactoryShippingRateImport;
use App\Models\Factory\FactoryShippingRate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;

class FactoryShippingRateController extends Controller
{
    /**
     * List factory shipping rates with filtering, sorting and pagination.
     *
     * Available query parameters:
     * - search: optional string query for title, price, factory name or company
     * - factory_id: optional integer factory user id (filters by factory)
     * - country_code: optional 2-letter ISO2 country code
     * - per_page: optional integer items per page (1-100, default 50)
     * - page: optional integer page number (>=1)
     * - sort_by: optional field name (id, shipping_title, price, min_qty, created_at)
     * - sort_dir: optional direction (asc, desc)
     */
    public function index(Request $request)
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'factory_id' => ['nullable', 'integer', 'exists:factory_users,id'],
            'country_code' => ['nullable', 'string', 'size:2'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
            'sort_by' => ['nullable', 'string', 'in:id,shipping_title,price,min_qty,created_at'],
            'sort_dir' => ['nullable', 'string', 'in:asc,desc'],
        ]);

        $query = FactoryShippingRate::query()
            ->with([
                'factory:id,first_name,last_name',
                'factory.business:id,factory_id,company_name',
                'country:iso2,name',
            ]);

        if (! empty($validated['factory_id'])) {
            $query->where('factory_id', $validated['factory_id']);
        }

        if (! empty($validated['country_code'])) {
            $query->where('country_code', strtoupper($validated['country_code']));
        }

        /* =======================
         |  Search
         ======================= */
        if (! empty($validated['search'])) {
            $search = $validated['search'];

            $query->where(function ($q) use ($search) {
                if (is_numeric($search)) {
                    $q->orWhere('id', (int) $search);
                }

                $q->orWhere('shipping_title', 'like', "%{$search}%")
                    ->orWhere('price', 'like', "%{$search}%");

                $q->orWhereHas('factory', function ($f) use ($search) {
                    $f->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%");
                });

                $q->orWhereHas('factory.business', function ($b) use ($search) {
                    $b->where('company_name', 'like', "%{$search}%");
                });
            });
        }

        $sortBy = $validated['sort_by'] ?? 'id';
        $sortDir = $validated['sort_dir'] ?? 'desc';

        if (! in_array($sortBy, ['id', 'shipping_title', 'price', 'min_qty', 'created_at'], true)) {
            $sortBy = 'id';
        }

        if (! in_array($sortDir, ['asc', 'desc'], true)) {
            $sortDir = 'desc';
        }

        $query->orderBy($sortBy, $sortDir);

        $perPage = $validated['per_page'] ?? 50;

        $shippingRates = $query->paginate($perPage);

        return response()->json([
            'data' => $shippingRates->items(),
            'meta' => [
                'current_page' => $shippingRates->currentPage(),
                'last_page' => $shippingRates->lastPage(),
                'per_page' => $shippingRates->perPage(),
                'total' => $shippingRates->total(),
            ],
        ]);
    }

    public function destroy($id)
    {
        $rate = FactoryShippingRate::findOrFail($id);

        $admin = auth('admin_api')->user();

        $rate->delete();

        Log::info('Shipping rate deleted', [
            'rate_id' => $rate->id,
            'admin_id' => $admin?->id,
        ]);

        return response()->json([
            'message' => __('Shipping rate deleted successfully'),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'shipping_title' => ['required', 'array'],
            'shipping_title.*' => ['required', 'string'],
            'factory_id' => ['required', 'array'],
            'factory_id.*' => ['required', 'integer', 'exists:factory_users,id'],
            'country_code' => ['required', 'array'],
            'country_code.*' => ['required', 'string'],
            'min_qty' => ['required', 'array'],
            'min_qty.*' => ['required', 'integer', 'min:0'],
            'price' => ['required', 'array'],
            'price.*' => ['required', 'numeric', 'min:0'],
            'rate_id' => ['nullable', 'array'],
            'rate_id.*' => ['nullable', 'integer', 'exists:factory_shipping_rates,id'],
        ]);

        $shippingCount = count($validated['shipping_title']);
        $factoryCount = count($validated['factory_id']);
        $countryCount = count($validated['country_code']);
        $minQtyCount = count($validated['min_qty']);
        $priceCount = count($validated['price']);

        if (
            $shippingCount !== $factoryCount ||
            $shippingCount !== $countryCount ||
            $shippingCount !== $minQtyCount ||
            $shippingCount !== $priceCount
        ) {
            return response()->json([
                'message' => 'Invalid shipping rate payload',
                'errors' => [
                    'fields' => ['All shipping rate arrays must have the same length.'],
                ],
            ], 422);
        }

        $count = $shippingCount;

        DB::transaction(function () use ($validated, $count) {
            for ($i = 0; $i < $count; $i++) {
                FactoryShippingRate::updateOrCreate(
                    [
                        'id' => $validated['rate_id'][$i] ?? null,
                    ],
                    [
                        'shipping_title' => $validated['shipping_title'][$i],
                        'factory_id' => $validated['factory_id'][$i],
                        'country_code' => $validated['country_code'][$i],
                        'min_qty' => $validated['min_qty'][$i],
                        'price' => $validated['price'][$i],
                    ]
                );
            }
        });

        $admin = auth('admin_api')->user();

        Log::info('Shipping rates saved', [
            'admin_id' => $admin?->id,
            'count' => $count,
            'factory_ids' => $validated['factory_id'],
            'country_codes' => $validated['country_code'],
        ]);

        return response()->json([
            'message' => 'Shipping rates saved successfully',
        ]);
    }

    public function export()
    {
        return Excel::download(
            new FactoryShippingRateExport,
            'factory_shipping_rates.xlsx'
        );
    }

    public function import(Request $request)
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,xlsx,xls'],
        ]);

        Excel::import(new FactoryShippingRateImport, $request->file('file'));

        return response()->json([
            'message' => __('Shipping rates imported successfully'),
        ]);
    }
}
