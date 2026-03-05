@extends('admin.layouts.app')

@section('title', __('Factories'))

@section('content')
    <div class="page-breadcrumb mb-4">
        <div class="row align-items-center">
            <div class="col-md-6">
                <h4 class="page-title fw-bold">{{ __('Factories') }}</h4>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">{{ __('Dashboard') }}</a></li>
                        <li class="breadcrumb-item active" aria-current="page">{{ __('Factories') }}</li>
                    </ol>
                </nav>
            </div>
        </div>
    </div>

    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                    <div class="row mb-4 align-items-center">
                        <div class="col-12 col-md-6">
                            <div class="d-flex align-items-center gap-2">
                                <div class="input-group input-group-sm w-auto">
                                    <select class="form-select" id="bulkAction">
                                        <option selected disabled>Bulk Actions</option>
                                        <option value="delete">Delete Selected</option>
                                    </select>
                                    <button class="btn btn-outline-secondary" id="applyBulkAction">Apply</button>
                                </div>
                            </div>
                        </div>
                        <div class="col-12 col-md-6 text-md-end">
                            <div class="d-flex justify-content-md-end gap-2 flex-wrap">
                                <button type="button" class="btn btn-primary d-flex align-items-center gap-2" data-bs-toggle="modal"
                                    data-bs-target="#createFactoryModal">
                                    <i class="mdi mdi-plus-circle-outline fs-5"></i>{{ __('Add Factory') }}
                                </button>
                                <button type="button" class="btn btn-outline-primary d-flex align-items-center gap-2" id="toggleFilters">
                                    <i class="mdi mdi-filter-variant fs-5"></i>{{ __('Filters') }}
                                </button>
                            </div>
                        </div>
                    </div>

                        <!-- Filter Section -->
                        <div class="card bg-white border shadow-sm mb-4 d-none" id="filterSection">
                            <div class="card-header border-bottom-0 pb-0">
                                <h6 class="mb-0 fw-bold"><i class="mdi mdi-filter-outline me-2"></i>{{ __('Advanced Filters') }}</h6>
                            </div>
                            <div class="card-body">
                                <form id="filterForm">
                                    <div class="row g-3">
                                        <div class="col-md-3">
                                            <label for="filter_name" class="form-label">{{ __('Name') }}</label>
                                            <input type="text" class="form-control" id="filter_name"
                                                placeholder="Search by name">
                                        </div>
                                        <div class="col-md-3">
                                            <label for="filter_business_name"
                                                class="form-label">{{ __('Business Name') }}</label>
                                            <input type="text" class="form-control" id="filter_business_name"
                                                placeholder="Search business">
                                        </div>
                                        <div class="col-md-3">
                                            <label for="filter_email" class="form-label">{{ __('Email') }}</label>
                                            <input type="text" class="form-control" id="filter_email"
                                                placeholder="Search email">
                                        </div>
                                        <div class="col-md-3">
                                            <label for="filter_phone" class="form-label">{{ __('Phone Number') }}</label>
                                            <input type="text" class="form-control" id="filter_phone"
                                                placeholder="Search phone">
                                        </div>

                                        <div class="col-md-3">
                                            <label for="filter_account_status"
                                                class="form-label">{{ __('Account Status') }}</label>
                                            <select class="form-select" id="filter_account_status">
                                                <option value="" selected>{{ __('All Statuses') }}</option>
                                            </select>
                                        </div>
                                        <div class="col-md-3">
                                            <label for="filter_email_verified"
                                                class="form-label">{{ __('Email Verification') }}</label>
                                            <select class="form-select" id="filter_email_verified">
                                                <option value="" selected>{{ __('All') }}</option>
                                                <option value="1">{{ __('Verified') }}</option>
                                                <option value="0">{{ __('Unverified') }}</option>
                                            </select>
                                        </div>
                                        <div class="col-md-3">
                                            <label for="filter_approval_status"
                                                class="form-label">{{ __('Verification Status') }}</label>
                                            <select class="form-select" id="filter_approval_status">
                                                <option value="" selected>{{ __('All') }}</option>
                                            </select>
                                        </div>
                                        <div class="col-md-3">
                                            <label for="filter_date_range"
                                                class="form-label">{{ __('Registration Date') }}</label>
                                            <input type="text" class="form-control" id="filter_date_range"
                                                placeholder="Select date range">
                                        </div>

                                        <div class="col-12 mt-4 d-flex justify-content-end gap-2">
                                            <button type="button" class="btn btn-outline-secondary" id="resetFilters">
                                                {{ __('Reset') }}
                                            </button>
                                            <button type="button" class="btn btn-primary" id="applyFilters">
                                                <i class="mdi mdi-filter-check me-1"></i>{{ __('Apply Filters') }}
                                            </button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <div class="table-responsive">
                            <table id="factoryTable" class="table table-hover align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th style="width: 40px;">
                                            <div class="form-check mb-0">
                                                <input class="form-check-input" type="checkbox" id="checkAll">
                                            </div>
                                        </th>
                                        <th style="width: 50px;">{{ __('#') }}</th>
                                        <th class="ps-3">{{ __('Name') }}</th>
                                        <th>{{ __('Factory Info') }}</th>
                                        <th>{{ __('Status') }}</th>
                                        <th>{{ __('Email Status') }}</th>
                                        <th>{{ __('Verification') }}</th>
                                        <th>{{ __('Registered') }}</th>
                                        <th>{{ __('Last Active') }}</th>
                                        <th class="text-end pe-3">{{ __('Actions') }}</th>
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

    <!-- Create/Edit Modal -->
    <div class="modal fade" id="createFactoryModal" tabindex="-1" aria-labelledby="createFactoryModalLabel"
        aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="createFactoryModalLabel">{{ __('Add Factory') }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="factoryForm">
                        <input type="hidden" id="factoryId" name="id">
                        <div class="mb-3">
                            <label for="first_name" class="form-label">{{ __('First Name') }}</label>
                            <input type="text" class="form-control" id="first_name" name="first_name" required>
                        </div>
                        <div class="mb-3">
                            <label for="last_name" class="form-label">{{ __('Last Name') }}</label>
                            <input type="text" class="form-control" id="last_name" name="last_name" required>
                        </div>
                        <div class="mb-3">
                            <label for="email" class="form-label">{{ __('Email') }}</label>
                            <input type="email" class="form-control" id="email" name="email" required>
                        </div>
                        <div class="mb-3">
                            <label for="phone_number" class="form-label">{{ __('Phone Number') }}</label>
                            <input type="text" class="form-control" id="phone_number" name="phone_number">
                        </div>
                        <div class="mb-3">
                            <label for="industry_id" class="form-label">{{ __('Industry') }}</label>
                            <select class="form-select" id="industry_id" name="industry_id" required>
                                <option value="" selected disabled>{{ __('Select Industry') }}</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="password" class="form-label">{{ __('Password') }}</label>
                            <input type="password" class="form-control" id="password" name="password">
                            <small class="text-muted"
                                id="passwordHelp">{{ __('Leave blank to keep current password on update.') }}</small>
                        </div>
                    </form>
                </div>
                <div class="modal-footer border-top-0 pt-0">
                    <button type="button" class="btn btn-outline-secondary px-4" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                    <button type="button" class="btn btn-primary px-4" id="saveFactoryBtn">{{ __('Save Changes') }}</button>
                </div>
            </div>
        </div>
    </div>

@endsection

@section('js')
    <script src="{{ asset('assets/js/auth-helpers.js') }}"></script>
    <script>
        window.FactoryConfig = {
            routes: {
                index: "{{ url('/api/v1/admin/factories') }}",
                industries: "{{ route('catalog.industries.index') }}",
                businessInformation: "{{ route('admin.factories.business-information', ':id') }}",
                statusOptions: "{{ url('/api/v1/admin/factories-status/statuses') }}",
                completeness: "{{ url('/api/v1/admin/factories-status/:id/completeness') }}",
                updateStatus: "{{ url('/api/v1/admin/factories-status/:id/update') }}"
            },
            csrfToken: "{{ csrf_token() }}"
        };
    </script>
    <script src="{{ asset('assets/js/pages/factory/index.js') }}"></script>
@endsection
