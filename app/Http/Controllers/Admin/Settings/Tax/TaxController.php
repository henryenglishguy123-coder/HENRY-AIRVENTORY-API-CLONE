<?php

namespace App\Http\Controllers\Admin\Settings\Tax;

use App\Http\Controllers\Controller;
use App\Models\Tax\Tax;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Yajra\DataTables\Facades\DataTables;

class TaxController extends Controller
{
    /**
     * Display the tax settings page.
     */
    public function index()
    {
        return view('admin.settings.tax.index');
    }

    /**
     * Get all taxes for API/DataTable.
     */
    public function data(Request $request)
    {
        if ($request->has('dropdown')) {
            return response()->json(Tax::select('id', 'name', 'code')->get());
        }

        $query = Tax::query()->withCount('rules');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        return DataTables::of($query)
            ->editColumn('status', function ($tax) {
                return $tax->status;
            })
            ->toJson();
    }

    /**
     * Store a new tax.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:50', 'unique:taxes,code'],
            'status' => ['boolean'],
        ]);

        $tax = Tax::create($validated);

        return response()->json([
            'success' => true,
            'message' => __('Tax created successfully.'),
            'data' => $tax,
        ]);
    }

    /**
     * Update the specified tax.
     */
    public function update(Request $request, Tax $tax)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:50', Rule::unique('taxes')->ignore($tax->id)],
            'status' => ['boolean'],
        ]);

        $tax->update($validated);

        return response()->json([
            'success' => true,
            'message' => __('Tax updated successfully.'),
            'data' => $tax,
        ]);
    }

    /**
     * Remove the specified tax.
     */
    public function destroy(Tax $tax)
    {
        // Check if used in rules
        if ($tax->rules()->exists()) {
            return response()->json([
                'success' => false,
                'message' => __('Cannot delete tax because it has associated rules. Please delete the rules first.'),
            ], 422);
        }

        $tax->delete();

        return response()->json([
            'success' => true,
            'message' => __('Tax deleted successfully.'),
        ]);
    }

    /**
     * Handle bulk actions.
     */
    public function bulkAction(Request $request)
    {
        $request->validate([
            'action' => 'required|in:enable,disable,delete',
            'ids' => 'required|array',
            'ids.*' => 'exists:taxes,id',
        ]);

        $action = $request->input('action');
        $ids = $request->input('ids');

        try {
            DB::beginTransaction();

            switch ($action) {
                case 'enable':
                    Tax::whereIn('id', $ids)->update(['status' => 1]);
                    $message = __('Selected taxes enabled successfully.');
                    break;
                case 'disable':
                    Tax::whereIn('id', $ids)->update(['status' => 0]);
                    $message = __('Selected taxes disabled successfully.');
                    break;
                case 'delete':
                    // Check for dependencies before deleting
                    $taxesWithRules = Tax::whereIn('id', $ids)->has('rules')->count();
                    if ($taxesWithRules > 0) {
                        return response()->json([
                            'success' => false,
                            'message' => __('Cannot delete some taxes because they have associated rules.'),
                        ], 422);
                    }
                    Tax::destroy($ids);
                    $message = __('Selected taxes deleted successfully.');
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
}
