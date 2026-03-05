@extends('admin.layouts.app')
@section('title', __('Manage Industries'))
@section('content')
    <div class="page-breadcrumb">
        <div class="row">
            <div class="col-12 d-flex no-block align-items-center">
                <h4 class="page-title">{{ __('Industries') }}</h4>
                <div class="ms-auto text-end">
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">{{ __('Home') }}</a></li>
                            <li class="breadcrumb-item active" aria-current="page">{{ __('Industries') }}</li>
                        </ol>
                    </nav>
                </div>
            </div>
        </div>
    </div>
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <div class="row mb-3">
                            <div class="col-lg-6 d-flex align-items-center gap-2">
                                <select id="bulk-action" class="form-select" style="width: 200px;">
                                    <option value="">{{ __('Select Action') }}</option>
                                    <option value="enable">{{ __('Enable Selected') }}</option>
                                    <option value="disable">{{ __('Disable Selected') }}</option>
                                    <option value="delete">{{ __('Delete Selected') }}</option>
                                </select>
                                <button id="apply-bulk-action" class="btn btn-primary"><i
                                        class="mdi mdi-check"></i>{{ __('Apply') }}</button>
                            </div>

                            <div class="col-lg-6 d-md-flex justify-content-end mt-2 mt-md-0">
                                <button id="add-industry-btn" class="btn btn-primary">
                                    <i class="mdi mdi-plus"></i> {{ __('Create Industry') }}
                                </button>
                            </div>
                        </div>

                        <div class="table-responsive">
                            <table id="industry-table" class="table table-striped table-hover align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th scope="col">
                                            <input type="checkbox" class="form-check-input" id="mainCheckbox">
                                        </th>
                                        <th scope="col">#</th>
                                        <th scope="col">{{ __('Industry Name') }}</th>
                                        <th scope="col">{{ __('Categories') }}</th>
                                        <th scope="col">{{ __('Status') }}</th>
                                        <th scope="col">{{ __('Action') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <!-- Dynamic data will be loaded here -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Blade: Industry Modal -->
    <div class="modal fade" id="industryModal" tabindex="-1" aria-labelledby="industryModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content shadow">
                <div class="modal-header">
                    <h5 class="modal-title" id="industryModalLabel">{{ __('Add Industry') }}</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="industry-form" data-action="add">
                        @csrf
                        <input type="hidden" id="industry-id" name="id">
                        <div class="mb-3">
                            <label for="name" class="form-label">{{ __('Industry Name') }}</label>
                            <input type="text" class="form-control" id="name" name="name"
                                placeholder="{{ __('Enter industry name') }}">
                            <span class="text-danger error-message" id="error-name"></span>
                        </div>

                        <div class="mb-3">
                            <label for="status" class="form-label">{{ __('Status') }}</label>
                            <select class="form-select" id="status" name="status">
                                <option value="1">{{ __('Enable') }}</option>
                                <option value="0">{{ __('Disable') }}</option>
                            </select>
                            <span class="text-danger error-message" id="error-status"></span>
                        </div>

                        <div class="text-end">
                            <button type="button" class="btn btn-secondary"
                                data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                            <button type="submit" class="btn btn-primary">{{ __('Save') }}</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
@section('js')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            window.initIndustry({
                storeUrl: "{{ route('admin.catalog.industries.store') }}",
                bulkActionUrl: "{{ route('admin.catalog.industries.bulkAction') }}",
                csrfToken: "{{ csrf_token() }}",
                getIndustriesUrl: "{{ route('catalog.industries.index') }}",
                getIndustryUrl: "{{ route('catalog.industries.show', ':id') }}",
                translations: {
                    saving: "{{ __('Saving...') }}",
                    save: "{{ __('Save') }}",
                    addSuccess: "{{ __('Industry added successfully!') }}",
                    updateSuccess: "{{ __('Industry updated successfully!') }}",
                    unexpectedError: "{{ __('An unexpected error occurred.') }}",
                    pleaseSelectRecord: "{{ __('Please select at least one record.') }}",
                    pleaseSelectAction: "{{ __('Please select an action.') }}",
                    cannotBeModified: "{{ __('Selected industries cannot be modified as they have categories.') }}"
                }
            });
        });
    </script>
    <script src="{{ asset('assets/js/pages/industry/industry.js') }}"></script>

@endsection
