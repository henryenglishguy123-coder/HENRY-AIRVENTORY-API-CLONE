@extends('admin.layouts.app')
@section('title', 'Create Discount Coupon')
@section('content')
    <div class="page-breadcrumb">
        <div class="row align-items-center">
            <div class="col-12 d-flex justify-content-between">
                <h4 class="page-title">{{ __('Create Discount Coupon') }}</h4>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">{{ __('Home') }}</a></li>
                        <li class="breadcrumb-item"><a
                                href="{{ route('admin.marketing.discount-coupons.index') }}">{{ __('Discount Coupons') }}</a></li>
                        <li class="breadcrumb-item active" aria-current="page">{{ __('Create') }}</li>
                    </ol>
                </nav>
            </div>
        </div>
    </div>
    <div class="container-fluid">
        {{-- Assuming x-alerts component displays session messages --}}
        <x-alerts />
        <form action="{{ route('admin.marketing.discount-coupons.store') }}" method="POST" id="coupon_form"
            data-route-check-code="{{ route('admin.marketing.discount-coupons.check-code') }}"
            data-route-search="{{ route('admin.marketing.discount-coupons.api.search') }}"
            data-route-index="{{ route('admin.marketing.discount-coupons.index') }}"
            data-lang-percentage-value="{{ __('Percentage Value') }}"
            data-lang-fixed-amount="{{ __('Fixed Amount') }}"
            data-lang-min-product-price="{{ __('Minimum Product Price (Applicable Items Only)') }}"
            data-lang-min-order-value="{{ __('Minimum Order Value') }}"
            data-lang-code-uppercase="{{ __('Code must be uppercase letters and numbers only.') }}"
            data-lang-checking="{{ __('Checking availability...') }}"
            data-lang-code-taken="{{ __('This coupon code is already taken.') }}"
            data-lang-code-failed="{{ __('Code validation failed on server.') }}"
            data-lang-search-select="{{ __('Search and select ') }}"
            data-lang-end-date-after-start="{{ __('End date and time must be after the start date and time.') }}"
            data-lang-max-uses-customer="{{ __('Max uses per customer cannot be greater than total max uses.') }}"
            data-lang-start-date-before-end="{{ __('Start date and time must be before the end date and time.') }}"
            data-lang-saving="{{ __('Saving...') }}"
            data-lang-success-title="{{ __('Success!') }}"
            data-lang-success-text="{{ __('Discount coupon created successfully.') }}"
            data-lang-title-required="{{ __('Please enter a discount title.') }}"
            data-lang-code-required="{{ __('Please enter a discount code.') }}"
            data-lang-amount-required="{{ __('Please enter a discount value.') }}"
            data-lang-value-greater-zero="{{ __('Value must be greater than 0.') }}"
            data-lang-percentage-max="{{ __('Percentage cannot exceed 100.') }}"
            data-lang-customer-required="{{ __('Please select at least one customer for specific eligibility.') }}"
            data-lang-product-required="{{ __('Please select at least one product.') }}"
            data-lang-category-required="{{ __('Please select at least one category.') }}"
            data-lang-supplier-required="{{ __('Please select at least one supplier.') }}"
            data-lang-number-valid="{{ __('Please enter a valid number.') }}"
            data-lang-max-uses-customer-total="{{ __('Max uses per customer cannot exceed total max uses.') }}"
            data-lang-error-generic="{{ __('Something went wrong.') }}"
        >
            @csrf

            <!-- GENERAL INFO -->
            <div class="card shadow-sm mb-4">
                <div class="card-header">
                    <h5 class="mb-0">{{ __('General Information') }}</h5>
                </div>
                <div class="card-body">
                    <div class="row g-4">

                        <div class="col-md-6">
                            <label class="form-label required-label" for="title">{{ __('Discount Title') }} <span
                                    class="text-danger">*</span></label>
                            <input type="text" name="title" id="title"
                                class="form-control" value="{{ old('title') }}"
                                placeholder="e.g. Summer Sale 20%" required maxlength="100">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label required-label" for="code">{{ __('Discount Code') }} <span
                                    class="text-danger">*</span></label>
                            <div class="input-group">
                                <input type="text" name="code" id="code"
                                    class="form-control text-uppercase "
                                    value="{{ old('code') }}" placeholder="SAVE20" required pattern="[A-Z0-9]+"
                                    title="Code must be uppercase letters and numbers only.">
                                <button type="button" class="btn btn-outline-secondary generate_code"
                                    title="Auto-generate a unique code">
                                    <i class="mdi mdi-repeat"></i> {{ __('Generate') }}
                                </button>
                                
                            </div>
                                <div id="code_feedback" class="invalid-feedback" style="display: none;"></div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label required-label" for="discount_type">{{ __('Discount For') }} <span
                                    class="text-danger">*</span></label>
                            <select name="discount_type" id="discount_type"
                                class="form-select @error('discount_type') is-invalid @enderror" required>
                                <option value="Order" @if (old('discount_type') == 'Order') selected @endif>{{ __('Orders') }}
                                </option>
                            </select>
                            <input type="hidden" id="amount_type" name="amount_type" value="Percentage" />
                            @error('discount_type') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="col-md-6">
                            <label class="form-label required-label" id="amount_type_label" for="amount_value">{{ __('Value') }}
                                <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <input type="number" step="0.01" name="amount_value" id="amount_value"
                                    class="form-control number-only @error('amount_value') is-invalid @enderror"
                                    value="{{ old('amount_value') }}" min="0.01" required
                                    placeholder="Enter discount value">
                                <span class="input-group-text" id="amount_suffix">%</span>
                                @error('amount_value') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>

                    </div>
                </div>
            </div>

            <!-- ELIGIBILITY -->
            <div class="card shadow-sm mb-4">
                <div class="card-header">
                    <h5 class="mb-0">{{ __('Customer Eligibility') }}</h5>
                </div>
                <div class="card-body">
                    <div class="d-flex gap-4 mb-3 flex-wrap">
                        <div class="form-check form-check-inline">
                            <input type="radio" name="eligibility" value="All Customers" class="form-check-input"
                                id="eligibility_all"
                                {{ old('eligibility', 'All Customers') == 'All Customers' ? 'checked' : '' }}>
                            <label class="form-check-label" for="eligibility_all">{{ __('All Customers') }}</label>
                        </div>
                        <div class="form-check form-check-inline">
                            <input type="radio" name="eligibility" value="Specific Customers" class="form-check-input"
                                id="eligibility_specific"
                                {{ old('eligibility') == 'Specific Customers' ? 'checked' : '' }}>
                            <label class="form-check-label"
                                for="eligibility_specific">{{ __('Specific Customers') }}</label>
                        </div>
                    </div>

                    <div id="specific_customers_div" class="mt-3"
                        style="display: {{ old('eligibility') == 'Specific Customers' ? 'block' : 'none' }};">
                        <label class="form-label required-label" for="customers">{{ __('Select Customers') }} <span class="text-danger">*</span></label>
                        <select name="customers[]"
                            class="form-select select2 @error('customers') is-invalid @enderror" id="customers_select"
                            multiple data-type="customer" placeholder="Search customers..."></select>
                        @error('customers') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                </div>
            </div>

            <!-- MIN PURCHASE REQUIREMENTS -->
            <div class="card shadow-sm mb-4">
                <div class="card-header">
                    <h5 class="mb-0">{{ __('Minimum Purchase Requirements') }}</h5>
                </div>
                <div class="card-body">
                    <div class="d-flex gap-4 mb-3 flex-wrap">
                        <div class="form-check">
                            <input type="radio" name="min_requirement_type" value="none" id="min_req_none"
                                class="form-check-input"
                                {{ old('min_requirement_type', 'none') == 'none' ? 'checked' : '' }}>
                            <label class="form-check-label" for="min_req_none">{{ __('No minimum requirements') }}</label>
                        </div>
                        <div class="form-check">
                            <input type="radio" name="min_requirement_type" value="quantity" id="min_req_qty"
                                class="form-check-input"
                                {{ old('min_requirement_type') == 'quantity' ? 'checked' : '' }}>
                            <label class="form-check-label" for="min_req_qty">{{ __('Minimum quantity of items') }}</label>
                        </div>
                        <div class="form-check">
                            <input type="radio" name="min_requirement_type" value="value" id="min_req_value"
                                class="form-check-input"
                                {{ old('min_requirement_type') == 'value' ? 'checked' : '' }}>
                            <label class="form-check-label" id="min_value_label"
                                for="min_req_value">{{ __('Minimum Order Value') }}</label>
                        </div>
                    </div>

                    <div class="row">
                        <div id="min_qty_group" class="mt-3 col-md-4"
                            style="display: {{ old('min_requirement_type') == 'quantity' ? 'block' : 'none' }};">
                            <label class="form-label required-label" for="min_qty">{{ __('Minimum Quantity') }} <span class="text-danger">*</span></label>
                            <input type="number" name="min_qty" id="min_qty"
                                class="form-control @error('min_qty') is-invalid @enderror"
                                value="{{ old('min_qty') }}" min="1" placeholder="Enter minimum quantity">
                            @error('min_qty') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div id="min_price_group" class="mt-3 col-md-4"
                            style="display: {{ old('min_requirement_type') == 'value' ? 'block' : 'none' }};">
                            <label class="form-label required-label" id="min_price_input_label"
                                for="min_price">{{ __('Minimum Order Value') }} <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" name="min_price" id="min_price"
                                class="form-control number-decimal-only @error('min_price') is-invalid @enderror"
                                value="{{ old('min_price') }}" min="0.01" placeholder="Enter minimum price">
                            @error('min_price') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>
                </div>
            </div>

            <!-- SCHEDULE & STATUS -->
            <div class="card shadow-sm mb-4">
                <div class="card-header">
                    <h5 class="mb-0">{{ __('Schedule & Status') }}</h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <!-- Start Date & Time -->
                        <div class="col-md-6">
                            <label for="start_datetime" class="form-label required-label">{{ __('Start Date & Time') }}
                                <span class="text-danger">*</span></label>
                            <div class="row g-2" id="start_datetime">
                                <div class="col-6">
                                    <input type="date" name="start_date" id="start_date"
                                        class="form-control @error('start_date') is-invalid @enderror" required
                                        value="{{ old('start_date') }}">
                                    @error('start_date') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                                <div class="col-6">
                                    <input type="time" name="start_time" id="start_time"
                                        class="form-control @error('start_time') is-invalid @enderror" required
                                        value="{{ old('start_time') }}">
                                    @error('start_time') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                            </div>
                        </div>

                        <!-- End Date & Time -->
                        <div class="col-md-6">
                            <label for="end_datetime" class="form-label">{{ __('End Date & Time (Optional)') }}</label>
                            <div class="row g-2" id="end_datetime">
                                <div class="col-6">
                                    <input type="date" name="end_date" id="end_date"
                                        class="form-control @error('end_date') is-invalid @enderror"
                                        value="{{ old('end_date') }}">
                                    @error('end_date') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                                <div class="col-6">
                                    <input type="time" name="end_time" id="end_time"
                                        class="form-control @error('end_time') is-invalid @enderror"
                                        value="{{ old('end_time') }}">
                                    @error('end_time') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                            </div>
                        </div>
                    </div>

                    <hr class="my-4">

                    <!-- Status -->
                    <div class="row">
                        <div class="col-md-6">
                            <label for="status" class="form-label required-label">{{ __('Status') }} <span
                                    class="text-danger">*</span></label>
                            <select name="status" id="status" class="form-select @error('status') is-invalid @enderror">
                                <option value="Active" {{ old('status', 'Active') == 'Active' ? 'selected' : '' }}>
                                    {{ __('Active') }}
                                </option>
                                <option value="Inactive" {{ old('status') == 'Inactive' ? 'selected' : '' }}>
                                    {{ __('Inactive') }}
                                </option>
                            </select>
                            @error('status')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                </div>

            </div>

            <!-- USAGE LIMITS -->
            <div class="card shadow-sm mb-4">
                <div class="card-header">
                    <h5 class="mb-0">{{ __('Usage Limits (Optional)') }}</h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" for="max_uses">{{ __('Max Uses (Total)') }}</label>
                            <input type="number" name="max_uses" id="max_uses"
                                class="form-control number-only @error('max_uses') is-invalid @enderror" min="1"
                                value="{{ old('max_uses') }}" placeholder="Leave blank for unlimited total uses">
                            @error('max_uses') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="max_uses_per_customer">{{ __('Max Uses per Customer') }}</label>
                            <input type="number" name="max_uses_per_customer" id="max_uses_per_customer"
                                class="form-control number-only @error('max_uses_per_customer') is-invalid @enderror"
                                value="{{ old('max_uses_per_customer') }}" min="1"
                                placeholder="Leave blank for unlimited per customer">
                            @error('max_uses_per_customer') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>
                </div>
            </div>

            <div class="text-end mb-5">
                <button type="submit" class="btn btn-primary">{{ __('Create Coupon') }}
                </button>
            </div>
        </form>
    </div>
@endsection
@section('js')
    <script src="{{ asset('assets/js/pages/marketing/discount-coupon/create.js') }}"></script>
@endsection