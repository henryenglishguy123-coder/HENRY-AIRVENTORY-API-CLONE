@extends('admin.layouts.app')

@section('title', __('Discount Coupons'))

@section('content')
<div class="page-breadcrumb">
    <div class="row align-items-center">
        <div class="col-12 d-flex justify-content-between">
            <h4 class="page-title mb-0">{{ __('Discount Coupons') }}</h4>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item">
                        <a href="{{ route('admin.dashboard') }}">{{ __('Home') }}</a>
                    </li>
                    <li class="breadcrumb-item active" aria-current="page">
                        {{ __('Discount Coupons') }}
                    </li>
                </ol>
            </nav>
        </div>
    </div>
</div>

<div class="container-fluid mt-4">
    <div class="card shadow-sm border-0">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <div class="d-flex align-items-center gap-2">
                <select id="bulk-action" class="form-select" style="width: 200px;">
                    <option value="">{{ __('Select Action') }}</option>
                    <option value="enable">{{ __('Enable Selected') }}</option>
                    <option value="disable">{{ __('Disable Selected') }}</option>
                    <option value="delete">{{ __('Delete Selected') }}</option>
                </select>
                <button id="apply-bulk-action" class="btn btn-primary">
                    <i class="mdi mdi-check"></i> {{ __('Apply') }}
                </button>
            </div>
            <a href="{{ route('admin.marketing.discount-coupons.create') }}" class="btn btn-primary">
                <i class="mdi mdi-plus"></i> {{ __('Add New Coupon') }}
            </a>
        </div>

        <div class="card-body">
            <div class="table-responsive">
                <table id="discountCouponsTable" class="table table-bordered table-hover align-middle w-100"
                    data-route-data="{{ route('admin.marketing.discount-coupons.data') }}"
                    data-route-bulk-action="{{ route('admin.marketing.discount-coupons.bulk-action') }}"
                    data-lang-search="{{ __('Search coupons...') }}"
                    data-lang-zero="{{ __('No coupons found') }}"
                    data-lang-select-one="{{ __('Please select at least one coupon.') }}"
                    data-lang-select-action="{{ __('Please select an action.') }}"
                    data-lang-confirm-delete="{{ __('This action will permanently delete the selected coupons.') }}"
                    data-lang-confirm-disable="{{ __('This will disable the selected coupons.') }}"
                    data-lang-confirm-enable="{{ __('This will enable the selected coupons.') }}"
                    data-lang-are-you-sure="{{ __('Are you sure?') }}"
                    data-lang-yes-proceed="{{ __('Yes, proceed!') }}"
                    data-lang-cancel="{{ __('Cancel') }}"
                    data-lang-error-generic="{{ __('Something went wrong.') }}"
                    data-lang-copied="{{ __('Copied!') }}"
                    data-lang-copy-code="{{ __('Copy Code') }}"
                    data-lang-copy-failed="{{ __('Failed to copy code.') }}"
                >
                    <thead class="table-light">
                        <tr>
                            <th><input type="checkbox" id="select_all" aria-label="{{ __('Select all coupons') }}"></th>
                            <th>#</th>
                            <th>{{ __('Title & Code') }}</th>
                            <th>{{ __('Discount Type') }}</th>
                            <th>{{ __('Value') }}</th>
                            <th>{{ __('Start Date') }}</th>
                            <th>{{ __('End Date') }}</th>
                            <th>{{ __('Status') }}</th>
                            <th class="text-center">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection


@section('js')
    <script src="{{ asset('assets/js/pages/marketing/discount-coupon/index.js') }}"></script>
@endsection
