<?php

namespace App\Http\Controllers\Api\V1\Admin\Factory;

use App\Enums\AccountStatus;
use App\Enums\AccountVerificationStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\Factory\StoreFactoryRequest;
use App\Http\Requests\Api\V1\Admin\Factory\UpdateFactoryRequest;
use App\Http\Resources\Api\V1\Factory\FactoryResource;
use App\Models\Factory\Factory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FactoryController extends Controller
{
    public function index(Request $request)
    {
        // 1. Resolve Parameters (Support both DataTables and Standard API)
        $draw = (int) $request->get('draw', 0);
        $start = (int) $request->get('start', 0);
        $length = (int) $request->get('length', 10);

        // Search can be from DataTables [search][value] or standard [search]
        $search = $request->input('search.value');
        if (is_null($search) && ! is_array($request->input('search'))) {
            $search = $request->input('search');
        }

        // Base Query
        $query = Factory::query()
            ->with(['business:id,factory_id,company_name'])
            ->select([
                'id',
                'first_name',
                'last_name',
                'email',
                'account_status',
                'account_verified',
                'email_verified_at',
                'phone_number',
                'last_login',
                'created_at',
            ]);

        // 2. Search & Filter
        $query->where(function ($q) use ($request, $search) {
            // General Search
            if ($search) {
                $q->where(function ($subQ) use ($search) {
                    if (is_numeric($search)) {
                        $subQ->orWhere('id', (int) $search);
                    }
                    $subQ->orWhere('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhereHas('business', function ($b) use ($search) {
                            $b->where('company_name', 'like', "%{$search}%");
                        });
                });
            }

            // Specific Filters
            if ($request->filled('filter_name')) {
                $name = $request->filter_name;
                $q->where(function ($sq) use ($name) {
                    $sq->where('first_name', 'like', "%{$name}%")
                        ->orWhere('last_name', 'like', "%{$name}%")
                        ->orWhereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", ["%{$name}%"]);
                });
            }

            if ($request->filled('filter_business_name')) {
                $q->whereHas('business', function ($b) use ($request) {
                    $b->where('company_name', 'like', "%{$request->filter_business_name}%");
                });
            }

            if ($request->filled('filter_email')) {
                $q->where('email', 'like', "%{$request->filter_email}%");
            }

            if ($request->filled('filter_phone')) {
                $q->where('phone_number', 'like', "%{$request->filter_phone}%");
            }

            if ($request->filled('filter_account_status')) {
                $q->where('account_status', (int) $request->filter_account_status);
            }

            if ($request->filled('filter_email_verified')) {
                if ($request->filter_email_verified == '1') {
                    $q->whereNotNull('email_verified_at');
                } else {
                    $q->whereNull('email_verified_at');
                }
            }

            if ($request->filled('filter_approval_status')) {
                $q->where('account_verified', (int) $request->filter_approval_status);
            }

            if ($request->filled('filter_date_range')) {
                // Format: YYYY-MM-DD - YYYY-MM-DD
                $dates = explode(' - ', $request->filter_date_range);
                if (count($dates) == 2) {
                    $q->whereBetween('created_at', [$dates[0].' 00:00:00', $dates[1].' 23:59:59']);
                } elseif (count($dates) == 1) {
                    $q->whereDate('created_at', $dates[0]);
                }
            }
        });

        // 3. Total Records
        $recordsTotal = Factory::count();
        $recordsFiltered = $query->count();

        // 4. Dynamic Sorting
        $order = $request->input('order.0'); // DataTables sends array of orders
        $columns = $request->input('columns');

        $sortBy = $request->input('sort_by', 'id');
        $sortDir = $request->input('sort_dir', 'desc');

        if ($order && $columns) {
            $columnIndex = $order['column'];
            $sortDir = $order['dir'];
            $columnName = $columns[$columnIndex]['name'] ?? $columns[$columnIndex]['data'];

            // Map frontend column names to database columns if needed, or ensure them match
            $sortableColumns = [
                'id' => 'id',
                'contact_name' => 'first_name',
                'email' => 'email',
                'phone_number' => 'phone_number',
                'account_status' => 'account_status',
                'account_verified' => 'account_verified',
                'created_at' => 'created_at',
                'last_login' => 'last_login',
            ];

            if (isset($sortableColumns[$columnName])) {
                $sortBy = $sortableColumns[$columnName];
            }
        }

        // Validate Sort Direction
        $sortDir = strtolower($sortDir) === 'asc' ? 'asc' : 'desc';

        // 5. Pagination & Ordering
        // Handle standard page parameter if start/length aren't meaningfully used
        if ($request->has('page') && ! $request->has('start')) {
            $page = (int) $request->get('page', 1);
            $perPage = (int) $request->get('per_page', $length);
            $start = ($page - 1) * $perPage;
            $length = $perPage;
        }

        $factories = $query
            ->orderBy($sortBy, $sortDir)
            ->skip($start)
            ->take($length)
            ->get();

        // 6. Use Resource Collection
        $data = FactoryResource::collection($factories)->resolve();

        // Dynamic Options for Filters
        $options = [
            'account_statuses' => collect(\App\Enums\AccountStatus::cases())->map(fn ($s) => ['value' => $s->value, 'label' => $s->label()]),
            'account_verification_statuses' => collect(\App\Enums\AccountVerificationStatus::cases())->map(fn ($s) => ['value' => $s->value, 'label' => $s->label()]),
        ];

        $response = [
            'draw' => $draw,
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $data,
            'options' => $options,
        ];

        // For standard API consumption, we might want to include meta
        if ($request->has('page')) {
            $response['meta'] = [
                'current_page' => (int) $request->get('page', 1),
                'per_page' => $length,
                'total' => $recordsFiltered,
                'last_page' => ceil($recordsFiltered / $length),
            ];
        }

        return response()->json($response);
    }

    public function store(StoreFactoryRequest $request)
    {
        try {
            DB::beginTransaction();

            $factory = Factory::create([
                'first_name' => $request->first_name,
                'last_name' => $request->last_name,
                'email' => $request->email,
                'phone_number' => $request->phone_number,
                'password' => $request->password,
                'account_status' => AccountStatus::ENABLED,
                'account_verified' => AccountVerificationStatus::PENDING,
                'source' => 'admin',
            ]);

            if ($request->filled('industry_id')) {
                $industryIds = $request->input('industry_id');
                $industryIds = is_array($industryIds) ? $industryIds : [$industryIds];
                $factory->industries()->sync($industryIds);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => __('Factory created successfully'),
                'data' => new FactoryResource($factory),
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            $safeRequest = $request->except([
                'password',
                'password_confirmation',
                'current_password',
                'token',
            ]);
            \Illuminate\Support\Facades\Log::error('Factory creation failed: '.$e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'request' => $safeRequest,
            ]);

            return response()->json([
                'success' => false,
                'message' => __('Failed to create factory'),
            ], 500);
        }
    }

    public function show(Factory $factory)
    {
        return response()->json([
            'success' => true,
            'data' => new FactoryResource($factory->load(['business', 'industries.meta'])),
        ]);
    }

    public function update(UpdateFactoryRequest $request, Factory $factory)
    {
        try {
            DB::beginTransaction();

            $updateData = [
                'first_name' => $request->first_name,
                'last_name' => $request->last_name,
                'email' => $request->email,
                'phone_number' => $request->phone_number,
            ];

            if ($request->filled('password')) {
                $updateData['password'] = $request->password;
            }

            $factory->update($updateData);

            if ($request->filled('industry_id')) {
                $industryIds = $request->input('industry_id');
                $industryIds = is_array($industryIds) ? $industryIds : [$industryIds];
                $factory->industries()->sync($industryIds);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => __('Factory updated successfully'),
                'data' => new FactoryResource($factory),
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            \Illuminate\Support\Facades\Log::error('Factory update failed: '.$e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'factory_id' => $factory->id,
            ]);

            return response()->json([
                'success' => false,
                'message' => __('Failed to update factory'),
            ], 500);
        }
    }

    public function destroy(Factory $factory)
    {
        try {
            $factory->delete();

            return response()->json([
                'success' => true,
                'message' => __('Factory deleted successfully'),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => __('Failed to delete factory'),
            ], 500);
        }
    }
}
