<?php

namespace App\Http\Controllers\Admin\Factory;

use App\Exports\FactorySalesRoutingExport;
use App\Http\Controllers\Controller;
use App\Imports\FactorySalesRoutingImport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;

class FactorySalesRoutingController extends Controller
{
    public function index(Request $request)
    {
        return view('admin.factory.sales-routing.index');
    }

    public function export($type)
    {
        if (! in_array($type, ['csv', 'xlsx'])) {
            abort(404);
        }

        $filename = 'factory_sales_routing_'.now()->format('Ymd_His');

        return Excel::download(
            new FactorySalesRoutingExport,
            $filename.'.'.$type
        );
    }

    public function import(Request $request)
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,xlsx,xls'],
        ]);

        try {
            Excel::import(
                new FactorySalesRoutingImport,
                $request->file('file')
            );

            return response()->json([
                'success' => true,
                'message' => __('Routing rules imported successfully.'),
            ]);

        } catch (\Maatwebsite\Excel\Validators\ValidationException $e) {
            $failures = $e->failures();

            // Handle specific row errors if needed
            return response()->json([
                'success' => false,
                'message' => __('Validation failed on specific rows.'),
                'errors' => $failures,
            ], 422);
        } catch (\Exception $e) {
            Log::error($e);

            return response()->json([
                'success' => false,
                'message' => __('Error importing file: ').$e->getMessage(),
            ], 500);
        }
    }
}
