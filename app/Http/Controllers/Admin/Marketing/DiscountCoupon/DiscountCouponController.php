<?php

namespace App\Http\Controllers\Admin\Marketing\DiscountCoupon;

use App\Http\Controllers\Controller;
use App\Models\Catalog\Category\CatalogCategory;
use App\Models\Catalog\Product\CatalogProduct;
use App\Models\Customer\Vendor as VendorUser;
use App\Models\Factory\Factory;
use App\Models\Marketing\Discount\DiscountCoupon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class DiscountCouponController extends Controller
{
    public function index()
    {
        return view('admin.marketing.discount-coupon.index');
    }

    public function data()
    {
        $discountCoupons = DiscountCoupon::select(
            'id',
            'code',
            'title',
            'amount_type',
            'amount_value',
            'discount_type',
            'status',
            'start_date',
            'end_date'
        );
        $discountCoupons->orderBy('id', 'desc');

        return datatables($discountCoupons)
            ->addColumn('checkbox', function ($discountCoupon) {
                return '<input type="checkbox" name="ids[]" value="'.$discountCoupon->id.'">';
            })
            ->addColumn('title_code', function ($discountCoupon) {
                $code = e($discountCoupon->code);
                $title = e($discountCoupon->title);

                return '
        <div class="d-flex align-items-center justify-content-between">
            <div>
                <div class="fw-bold text-dark">'.$title.'</div>
                <div class="text-muted small">
                    <span class="badge bg-primary">'.$code.'</span>
                </div>
            </div>
            <button type="button" 
                class="btn btn-outline-secondary btn-sm ms-2 copy-code-btn" 
                data-code="'.$code.'" 
                title="Copy Code">
                <i class="mdi mdi-content-copy"></i>
            </button>
        </div>
    ';
            })
            ->editColumn('amount_value', function ($row) {
                return $row->amount_type === 'Percentage'
                    ? $row->amount_value.'%'
                    : format_price($row->amount_value);
            })
            ->editColumn('discount_type', function ($row) {
                return ucfirst($row->discount_type);
            })
            ->editColumn('status', function ($row) {
                $statusClass = $row->status == 'Active' ? 'success' : 'danger';

                return '<span class="badge bg-'.$statusClass.'">'.$row->status.'</span>';
            })
            ->editColumn('start_date', function ($row) {
                return $row->start_date ? $row->start_date->format(config('admin.datetime_format', 'Y-m-d H:i:s')) : '-';
            })
            ->editColumn('end_date', function ($row) {
                return $row->end_date ? $row->end_date->format(config('admin.datetime_format', 'Y-m-d H:i:s')) : '-';
            })
            ->addColumn('actions', function ($row) {
                $editUrl = route('admin.marketing.discount-coupons.edit', $row->id);
                $deleteUrl = '#';

                return '<a href="'.$editUrl.'" class="btn btn-sm btn-primary">
                        <i class="fas fa-edit"></i>
                    </a>';
            })
            ->rawColumns(['status', 'actions', 'title_code', 'checkbox'])
            ->make(true);
    }

    public function generateCode()
    {
        $code = DiscountCoupon::generateUniqueCode();

        return response()->json(['code' => $code]);
    }

    public function checkCode(Request $request)
    {
        $request->validate([
            'code' => 'required|string|max:255',
            'id' => 'nullable|integer|exists:discount_coupons,id',
        ]);
        $query = DiscountCoupon::where('code', strtoupper($request->code));
        if ($request->has('id') && $request->id) {
            $query->where('id', '!=', $request->id);
        }
        $exists = $query->exists();

        return response()->json([
            'exists' => $exists,
        ]);
    }

    public function search(Request $request)
    {
        // Validate input
        $allowedTypes = ['product', 'category', 'supplier', 'customer'];
        $validated = $request->validate([
            'type' => ['required', 'string', Rule::in($allowedTypes)],
            'q' => ['nullable', 'string', 'max:255'],
        ]);

        $type = $validated['type'];
        $q = $validated['q'] ?? '';

        switch ($type) {

            case 'product':
                $results = CatalogProduct::query()
                    ->whereHas('info', function ($query) use ($q) {
                        $query->where('name', 'like', "%{$q}%");
                    })
                    ->whereNotIn('catalog_products.id', function ($subQuery) {
                        $subQuery->select('catalog_product_id')->from('catalog_product_parents');
                    })
                    ->with('info:id,catalog_product_id,name')
                    ->whereHas('layerimages')
                    ->whereHas('assignlayers')
                    ->limit(20)
                    ->get()
                    ->map(function ($product) {
                        $name = $product->info->name ?? '(Unnamed Product)';

                        return [
                            'id' => $product->id,
                            'text' => $name,
                            'html' => '<div>'.htmlspecialchars($name).' <br><small>('.$product->sku.')</small></div>',
                        ];
                    });
                break;

            case 'category':
                $results = CatalogCategory::whereHas('meta', function ($query) use ($q) {
                    $query->where('name', 'like', "%{$q}%");
                })
                    ->limit(20)
                    ->get()
                    ->map(function ($cat) {
                        $name = $cat->meta->name ?? '(Unnamed Category)';

                        return [
                            'id' => $cat->id,
                            'text' => $name,
                            'html' => '<div>'.htmlspecialchars($name).'</div>',
                        ];
                    });
                break;

            case 'supplier':
                $results = Factory::with('business')
                    ->where(function ($query) use ($q) {
                        $query->where('first_name', 'like', "%{$q}%")
                            ->orWhere('last_name', 'like', "%{$q}%")
                            ->orWhere('email', 'like', "%{$q}%")
                            ->orWhere('phone_number', 'like', "%{$q}%")
                            ->orWhereHas('business', function ($q2) use ($q) {
                                $q2->where('company_name', 'like', "%{$q}%");
                            });
                    })
                    ->limit(20)
                    ->get()
                    ->map(function ($supp) {
                        $name = trim($supp->first_name.' '.$supp->last_name) ?: '(Unnamed Supplier)';
                        $company = $supp->business->company_name ?? '';

                        return [
                            'id' => $supp->id,
                            'text' => $company,
                            'html' => '<div><strong>'.htmlspecialchars($name).'</strong>'
                                .($company ? ' - '.htmlspecialchars($company) : '')
                                .'</div>',
                        ];
                    });
                break;

            case 'customer':
                $results = VendorUser::where('account_status', 1)
                    ->where(function ($query) use ($q) {
                        $query->where('first_name', 'like', "%{$q}%")
                            ->orWhere('last_name', 'like', "%{$q}%")
                            ->orWhere('email', 'like', "%{$q}%")
                            ->orWhere('mobile', 'like', "%{$q}%");
                    })
                    ->limit(20)
                    ->get()
                    ->map(function ($user) {
                        $name = trim("{$user->first_name} {$user->last_name}");
                        $email = $user->email ?? '';
                        $mobile = $user->mobile ?? '';
                        $displayName = $name ?: ($email ?: $mobile);

                        $html = '<div><strong>'.htmlspecialchars($displayName).'</strong>';
                        if ($email || $mobile) {
                            $html .= '<br><small class="">'
                                .($email ? htmlspecialchars($email) : '')
                                .($email && $mobile ? ' | ' : '')
                                .($mobile ? htmlspecialchars($mobile) : '')
                                .'</small>';
                        }
                        $html .= '</div>';

                        return [
                            'id' => $user->id,
                            'text' => $displayName,
                            'html' => $html,
                        ];
                    });
                break;

            default:
                $results = collect();
                break;
        }

        return response()->json($results);
    }

    public function bulkAction(Request $request)
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'action' => ['required', 'string', Rule::in(['enable', 'disable', 'delete'])],
        ]);

        try {
            $ids = $validated['ids'];
            $action = $validated['action'];

            $query = DiscountCoupon::whereIn('id', $ids);
            $updatedCount = 0;

            switch ($action) {
                case 'enable':
                    $updatedCount = $query->update(['status' => 'Active']);
                    $message = __('Selected coupons have been enabled successfully.');
                    break;

                case 'disable':
                    $updatedCount = $query->update(['status' => 'Inactive']);
                    $message = __('Selected coupons have been disabled successfully.');
                    break;

                case 'delete':
                    $updatedCount = $query->delete();
                    $message = __('Selected coupons have been deleted successfully.');
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
                'count' => $updatedCount,
            ]);

        } catch (\Throwable $e) {
            Log::error('Bulk action failed: '.$e->getMessage(), [
                'action' => $request->action,
                'ids' => $request->ids,
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'status' => false,
                'message' => __('Something went wrong while performing bulk action. Please try again.'),
            ], 500);
        }
    }
}
