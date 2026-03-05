<?php

namespace App\Http\Controllers\Admin\Customer;

use App\Http\Controllers\Controller;
use App\Mail\Customer\CustomerStatusChanged;
use App\Models\Customer\Vendor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class CustomerBulkActionsController extends Controller
{
    public function bulkAction(Request $request)
    {
        $data = $request->validate([
            'action' => 'required|string|in:enable,disable,blocked,suspended,delete',
            'ids' => 'required|array',
            'ids.*' => 'integer|min:1|distinct|exists:vendors,id',
        ]);
        $action = $data['action'];
        $ids = $data['ids'];
        $loginUrl = rtrim(config('admin.customer_login_url'));

        $statusMessages = [
            'enable' => ['status' => 1, 'message' => __('Selected Customers have been enabled.')],
            'disable' => ['status' => 0, 'message' => __('Selected Customers have been disabled.')],
            'blocked' => ['status' => 2, 'message' => __('Selected Customers have been blocked.')],
            'suspended' => ['status' => 3, 'message' => __('Selected Customers have been suspended.')],
        ];
        if (isset($statusMessages[$action])) {
            Vendor::whereIn('id', $ids)->update(['account_status' => $statusMessages[$action]['status']]);
            Vendor::whereIn('id', $ids)
                ->get(['id', 'first_name', 'email'])
                ->each(function ($customer) use ($action, $loginUrl) {
                    if (! empty($customer->email)) {
                        Mail::to($customer->email)->queue(new CustomerStatusChanged($customer->toArray(), $action, $loginUrl));
                    }
                });

            return response()->json(['success' => true, 'message' => $statusMessages[$action]['message']], 200);
        }
        if ($action === 'delete') {
            DB::beginTransaction();
            try {
                $customers = Vendor::whereIn('id', $ids)->get(['id', 'first_name', 'email']);
                $snapshots = $customers->map(function ($c) {
                    return [
                        'id' => $c->id,
                        'first_name' => $c->first_name,
                        'email' => $c->email,
                    ];
                })->all();
                Vendor::whereIn('id', $ids)->delete();
                DB::commit();
                foreach ($snapshots as $snap) {
                    if (! empty($snap['email'])) {
                        Mail::to($snap['email'])->queue(new CustomerStatusChanged($snap, 'deleted', $loginUrl));
                    }
                }

                return response()->json([
                    'success' => true,
                    'message' => __('Selected Customers have been deleted successfully.'),
                ], 200);
            } catch (\Throwable $e) {
                DB::rollBack();
                report($e);

                return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
            }
        }

        return response()->json(['success' => false, 'message' => __('Invalid action.')], 500);
    }
}
