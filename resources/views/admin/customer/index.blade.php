@extends('admin.layouts.app')
@section('title', __('Manage Customers'))

@section('content')
    {{-- Breadcrumb and page header --}}
    <div class="page-breadcrumb">
        <div class="row">
            <div class="col-12 d-flex no-block align-items-center">
                <h4 class="page-title">{{ __('Customers') }}</h4>
                <div class="ms-auto text-end">
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">{{ __('home') }}</a></li>
                            <li class="breadcrumb-item active" aria-current="page">{{ __('Customers') }}</li>
                        </ol>
                    </nav>
                </div>
            </div>
        </div>
    </div>

    {{-- Page content --}}
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">

                        {{-- Bulk action controls --}}
                        <div class="row mb-3">
                            <div class="col-lg-6">
                                <select id="bulk-action" class="form-select d-inline-block" style="width: 180px;">
                                    <option value="">{{ __('Select Action') }}</option>
                                    <option value="enable">{{ __('Enable Selected') }}</option>
                                    <option value="disable">{{ __('Disable Selected') }}</option>
                                    <option value="blocked">{{ __('Block Selected') }}</option>
                                    <option value="suspended">{{ __('Suspend Selected') }}</option>
                                    <option value="delete">{{ __('Delete Selected') }}</option>
                                </select>
                                <button id="apply-bulk-action" class="btn btn-primary ms-2">{{ __('apply') }}</button>
                            </div>

                            <div class="col-lg-6 d-flex justify-content-end">
                                <button class="btn btn-outline-secondary me-2" data-bs-toggle="collapse"
                                    data-bs-target="#filterSection">
                                    <i class="fas fa-filter"></i> {{ __('Filters') }}
                                </button>
                            </div>
                        </div>

                        {{-- Filters --}}
                        <div id="filterSection" class="collapse mb-3">
                            <div class="row g-2">
                                <div class="col-md-3">
                                    <input type="text" id="searchInput" class="form-control" placeholder="{{ __('Search by name or email') }}">
                                </div>
                                <div class="col-md-3">
                                    <select id="accountStatusFilter" class="form-select">
                                        <option value="">{{ __('All Statuses') }}</option>
                                        <option value="1">{{ __('Enabled') }}</option>
                                        <option value="0">{{ __('Disabled') }}</option>
                                        <option value="2">{{ __('Blocked') }}</option>
                                        <option value="3">{{ __('Suspended') }}</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <select id="emailVerificationFilter" class="form-select">
                                        <option value="">{{ __('All Email Status') }}</option>
                                        <option value="1">{{ __('Verified') }}</option>
                                        <option value="0">{{ __('Unverified') }}</option>
                                    </select>
                                </div>
                                <div class="col-md-3 d-flex gap-2">
                                    <button id="filterBtn" class="btn btn-primary w-50">{{ __('Apply Filters') }}</button>
                                    <button id="resetBtn" class="btn btn-secondary w-50">{{ __('Reset') }}</button>
                                </div>
                            </div>
                        </div>

                        {{-- Customers table --}}
                        <div class="table-responsive">
                            <table id="customerTable" class="table table-bordered table-striped">
                                <thead>
                                    <tr>
                                        <th scope="col">
                                            <label class="form-check-label">
                                                <input type="checkbox" class="form-check-input" id="mainCheckbox" />
                                            </label>
                                        </th>
                                        <th>{{ __('ID') }}</th>
                                        <th>{{ __('Full Name') }}</th>
                                        <th>{{ __('Email Address') }}</th>
                                        <th>{{ __('Account Status') }}</th>
                                        <th>{{ __('Email Verification') }}</th>
                                        <th>{{ __('Registration Date') }}</th>
                                        <th>{{ __('Last Login') }}</th>
                                        <th>{{ __('Actions') }}</th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('js')
    <script>
        // Provide endpoints & CSRF token to the JS file
        window.customer_data_url = "{{ route('admin.customers.index') }}";
        window.customer_bulk_action_url = "{{ route('admin.customer.bulk-action') }}";
        window.csrf_token = "{{ csrf_token() }}";
        window.customer_show_url = "{{ route('admin.customer.show', ':id') }}";
        window.translations = {
            select_at_least_one_customer: "{{ __('Please select at least one Customer.') }}",
            are_you_sure: "{{ __('Are you sure?') }}",
            cannot_revert: "{{ __('You would not be able to revert this!') }}",
            enable_selected: "{{ __('This will enable the selected customers.') }}",
            disable_selected: "{{ __('This will disable the selected customers.') }}",
            block_selected: "{{ __('This will block the selected customers.') }}",
            suspend_selected: "{{ __('This will suspend the selected customers.') }}",
            select_valid_action: "{{ __('Please select a valid action.') }}",
            proceed: "{{ __('Proceed') }}",
            cancel: "{{ __('Cancel') }}",
            action_completed: "{{ __('Action completed successfully.') }}",
            request_error: "{{ __('There was a problem processing the request.') }}",
            permission_denied: "{{ __('You do not have permission to perform this action.') }}",
            not_found: "{{ __('The requested resource was not found.') }}",
            server_error: "{{ __('A server error occurred. Please try again later.') }}"
        };
    </script>
    <script src="{{ asset('assets/js/pages/customer/index.js') }}"></script>
@endsection
