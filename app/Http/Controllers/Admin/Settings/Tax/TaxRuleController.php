<?php

namespace App\Http\Controllers\Admin\Settings\Tax;

use App\Http\Controllers\Controller;
use App\Models\Tax\TaxRule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\Facades\DataTables;

class TaxRuleController extends Controller
{
    /**
     * Get all tax rules.
     */
    public function index(Request $request)
    {
        $query = TaxRule::with(['tax', 'zone.country']);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        return DataTables::of($query)
            ->editColumn('rate', function ($rule) {
                return $rule->rate;
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
            'ids.*' => 'exists:tax_rules,id',
        ]);

        $action = $request->input('action');
        $ids = $request->input('ids');

        try {
            DB::beginTransaction();

            switch ($action) {
                case 'enable':
                    TaxRule::whereIn('id', $ids)->update(['status' => 1]);
                    $message = __('Selected rules enabled successfully.');
                    break;
                case 'disable':
                    TaxRule::whereIn('id', $ids)->update(['status' => 0]);
                    $message = __('Selected rules disabled successfully.');
                    break;
                case 'delete':
                    TaxRule::destroy($ids);
                    $message = __('Selected rules deleted successfully.');
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
     * Store a new tax rule.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'tax_id' => ['required', 'exists:taxes,id'],
            'tax_zone_id' => ['required', 'exists:tax_zones,id'],
            'rate' => ['required', 'numeric', 'min:0'],
            'priority' => ['required', 'integer', 'min:0'],
            'status' => ['boolean'],
        ]);

        $rule = TaxRule::create($validated);
        $rule->load(['tax', 'zone.country']);

        return response()->json([
            'success' => true,
            'message' => __('Tax Rule created successfully.'),
            'data' => $rule,
        ]);
    }

    /**
     * Update the specified tax rule.
     */
    public function update(Request $request, TaxRule $taxRule)
    {
        $validated = $request->validate([
            'tax_id' => ['required', 'exists:taxes,id'],
            'tax_zone_id' => ['required', 'exists:tax_zones,id'],
            'rate' => ['required', 'numeric', 'min:0'],
            'priority' => ['required', 'integer', 'min:0'],
            'status' => ['boolean'],
        ]);

        $taxRule->update($validated);
        $taxRule->load(['tax', 'zone.country']);

        return response()->json([
            'success' => true,
            'message' => __('Tax Rule updated successfully.'),
            'data' => $taxRule,
        ]);
    }

    /**
     * Remove the specified tax rule.
     */
    public function destroy(TaxRule $taxRule)
    {
        $taxRule->delete();

        return response()->json([
            'success' => true,
            'message' => __('Tax Rule deleted successfully.'),
        ]);
    }
}
