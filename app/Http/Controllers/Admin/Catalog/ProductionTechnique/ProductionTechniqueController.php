<?php

namespace App\Http\Controllers\Admin\Catalog\ProductionTechnique;

use App\Http\Controllers\Controller;
use App\Models\PrintingTechnique\PrintingTechnique;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Yajra\DataTables\DataTables;

class ProductionTechniqueController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return view('admin.catalog.production-technique.index');
    }

    /**
     * Fetch datatable data.
     */
    public function getProductionTechniqueData(Request $request)
    {
        $techniques = PrintingTechnique::withTrashed();

        return DataTables::of($techniques)
            ->addColumn('select_id', function ($technique) {
                return $technique->id;
            })
            ->addColumn('actions', function ($technique) {
                if ($technique->trashed()) {
                    $restoreUrl = route('admin.production-techniques.restore', $technique->id);

                    return '
                        <button type="button" class="btn btn-success btn-sm js-restore-technique" data-url="'.e($restoreUrl).'" title="Restore">
                            <i class="mdi mdi-restore"></i> Restore
                        </button>';
                }

                $editUrl = route('admin.catalog.production-techniques.edit', $technique->id);
                $deleteUrl = route('admin.production-techniques.delete', $technique->id);

                // Reusing standard action buttons
                return '
                    <div class="btn-group g-2" role="group">
                        <a href="'.e($editUrl).'" class="btn btn-black btn-sm" title="Edit">
                            <i class="mdi mdi-pencil"></i>
                        </a>
                        <button type="button" class="btn btn-danger btn-sm js-delete-technique" data-url="'.e($deleteUrl).'" title="Delete">
                            <i class="mdi mdi-delete"></i>
                        </button>
                    </div>';
            })
            ->editColumn('status', function ($technique) {
                if ($technique->trashed()) {
                    return '<span class="badge bg-danger">Deleted</span>';
                }
                // Standard catalog toggle UI
                $checked = $technique->status ? 'checked' : '';

                return '
                    <div class="form-check form-switch px-4 ps-5">
                        <input class="form-check-input js-toggle-status" type="checkbox" data-id="'.$technique->id.'" '.$checked.'>
                    </div>
                ';
            })
            ->editColumn('created_at', function ($technique) {
                return formatDateTime($technique->created_at);
            })
            ->rawColumns(['actions', 'status'])
            ->make(true);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('admin.catalog.production-technique.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:printing_techniques,name',
            'status' => ['required', Rule::in([0, 1])],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            PrintingTechnique::create($validator->validated());

            return response()->json([
                'status' => true,
                'message' => __('Production technique created successfully.'),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to create production technique: '.$e->getMessage());

            return response()->json([
                'status' => false,
                'message' => __('Failed to create production technique. Please try again.'),
            ], 500);
        }
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit($id)
    {
        $technique = PrintingTechnique::findOrFail($id);

        return view('admin.catalog.production-technique.edit', compact('technique'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request)
    {

        $validator = Validator::make($request->all(), [
            'id' => 'required|integer|exists:printing_techniques,id',
            'name' => 'required|string|max:255|unique:printing_techniques,name,'.$request->input('id'),
            'status' => ['required', Rule::in([0, 1])],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $technique = PrintingTechnique::findOrFail($request->input('id'));
            $technique->update($validator->validated());

            return response()->json([
                'status' => true,
                'message' => __('Production technique updated successfully.'),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to update production technique: '.$e->getMessage());

            return response()->json([
                'status' => false,
                'message' => __('Failed to update production technique. Please try again.'),
            ], 500);
        }
    }

    /**
     * Toggle the status of a specific technique.
     */
    public function toggleStatus(Request $request, $id)
    {
        try {
            $technique = PrintingTechnique::findOrFail($id);
            $technique->status = $request->input('status', ! $technique->status);
            $technique->save();

            return response()->json([
                'status' => true,
                'message' => __('Status updated successfully.'),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to toggle production technique status: '.$e->getMessage());

            return response()->json([
                'status' => false,
                'message' => __('Failed to update status.'),
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage (Soft Delete).
     */
    public function destroy($id)
    {
        try {
            $technique = PrintingTechnique::findOrFail($id);
            $technique->delete();

            return response()->json([
                'status' => true,
                'message' => __('Production technique deleted successfully.'),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to delete production technique: '.$e->getMessage());

            return response()->json([
                'status' => false,
                'message' => __('Failed to delete production technique.'),
            ], 500);
        }
    }

    /**
     * Handle bulk actions (delete, enable, disable).
     */
    public function bulkAction(Request $request)
    {
        $action = $request->input('action');
        $ids = $request->input('ids');

        if (empty($ids) || ! is_array($ids)) {
            return response()->json([
                'status' => false,
                'message' => __('No items selected.'),
            ], 400);
        }

        try {
            switch ($action) {
                case 'delete':
                    PrintingTechnique::whereIn('id', $ids)->delete();
                    $message = __('Selected techniques deleted successfully.');
                    break;
                case 'enable':
                    PrintingTechnique::whereIn('id', $ids)->update(['status' => 1]);
                    $message = __('Selected techniques enabled successfully.');
                    break;
                case 'disable':
                    PrintingTechnique::whereIn('id', $ids)->update(['status' => 0]);
                    $message = __('Selected techniques disabled successfully.');
                    break;
                default:
                    return response()->json([
                        'status' => false,
                        'message' => __('Invalid action.'),
                    ], 400);
            }

            return response()->json([
                'status' => true,
                'message' => $message,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to perform bulk action on production techniques: '.$e->getMessage());

            return response()->json([
                'status' => false,
                'message' => __('An error occurred while processing your request.'),
            ], 500);
        }
    }

    /**
     * Restore the specified resource from storage (Soft Delete).
     */
    public function restore($id)
    {
        try {
            $technique = PrintingTechnique::withTrashed()->findOrFail($id);
            $technique->restore();

            return response()->json([
                'status' => true,
                'message' => __('Production technique restored successfully.'),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to restore production technique: '.$e->getMessage());

            return response()->json([
                'status' => false,
                'message' => __('Failed to restore production technique.'),
            ], 500);
        }
    }
}
