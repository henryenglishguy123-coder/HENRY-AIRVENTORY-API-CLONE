<?php

namespace App\Http\Controllers\Admin\Settings\Tax;

use App\Http\Controllers\Controller;
use App\Models\Tax\TaxZone;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\Facades\DataTables;

class TaxZoneController extends Controller
{
    /**
     * Get all tax zones.
     */
    public function index(Request $request)
    {
        if ($request->has('dropdown')) {
            return response()->json(TaxZone::select('id', 'name')->get());
        }

        $query = TaxZone::with('country');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        return DataTables::of($query)
            ->addColumn('country_name', function ($zone) {
                return $zone->country ? $zone->country->name : '-';
            })
            ->toJson();
    }

    /**
     * Handle bulk actions.
     */
    public function bulkAction(Request $request)
    {
        $request->validate([
            'action' => 'required|in:enable,disable,delete',
            'ids' => 'required|array',
            'ids.*' => 'exists:tax_zones,id',
        ]);

        $action = $request->input('action');
        $ids = $request->input('ids');

        try {
            DB::beginTransaction();

            switch ($action) {
                case 'enable':
                    TaxZone::whereIn('id', $ids)->update(['status' => 1]);
                    $message = __('Selected zones enabled successfully.');
                    break;
                case 'disable':
                    TaxZone::whereIn('id', $ids)->update(['status' => 0]);
                    $message = __('Selected zones disabled successfully.');
                    break;
                case 'delete':
                    $zonesWithRules = TaxZone::whereIn('id', $ids)->has('rules')->count();
                    if ($zonesWithRules > 0) {
                        return response()->json([
                            'success' => false,
                            'message' => __('Cannot delete some zones because they have associated rules.'),
                        ], 422);
                    }
                    TaxZone::destroy($ids);
                    $message = __('Selected zones deleted successfully.');
                    break;
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => $message,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => __('An error occurred while performing bulk action.'),
            ], 500);
        }
    }

    /**
     * Store a new tax zone.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'country_id' => ['required', 'exists:countries,id'],
            'state_code' => ['nullable', 'string', 'max:10'],
            'postal_code_start' => ['nullable', 'string', 'max:20'],
            'postal_code_end' => ['nullable', 'string', 'max:20'],
            'status' => ['boolean'],
        ]);

        $zone = TaxZone::create($validated);
        $zone->load('country');

        return response()->json([
            'success' => true,
            'message' => __('Tax Zone created successfully.'),
            'data' => $zone,
        ]);
    }

    /**
     * Update the specified tax zone.
     */
    public function update(Request $request, TaxZone $taxZone)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'country_id' => ['required', 'exists:countries,id'],
            'state_code' => ['nullable', 'string', 'max:10'],
            'postal_code_start' => ['nullable', 'string', 'max:20'],
            'postal_code_end' => ['nullable', 'string', 'max:20'],
            'status' => ['boolean'],
        ]);

        $taxZone->update($validated);
        $taxZone->load('country');

        return response()->json([
            'success' => true,
            'message' => __('Tax Zone updated successfully.'),
            'data' => $taxZone,
        ]);
    }

    /**
     * Remove the specified tax zone.
     */
    public function destroy(TaxZone $taxZone)
    {
        if ($taxZone->rules()->exists()) {
            return response()->json([
                'success' => false,
                'message' => __('Cannot delete tax zone because it is used in tax rules.'),
            ], 422);
        }

        $taxZone->delete();

        return response()->json([
            'success' => true,
            'message' => __('Tax Zone deleted successfully.'),
        ]);
    }
}
