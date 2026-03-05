@extends('admin.layouts.app')

@section('title', __('Category Management'))

@section('content')
    <div class="page-breadcrumb">
        <div class="row">
            <div class="col-12 d-flex align-items-center justify-content-between">
                <h4 class="page-title">{{ __('Category Management') }}</h4>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb bg-transparent mb-0">
                        <li class="breadcrumb-item">
                            <a href="{{ route('admin.dashboard') }}" class="text-decoration-none text-dark">
                                <i class="mdi mdi-home"></i> {{ __('Home') }}
                            </a>
                        </li>
                        <li class="breadcrumb-item active" aria-current="page">{{ __('Category') }}</li>
                    </ol>
                </nav>
            </div>
        </div>
    </div>

    <div class="container-fluid">

        <x-alerts />

        <div class="card">
            <div class="card-body">

                <div class="row mb-3">
                    <div class="col-lg-6 d-flex align-items-center">
                        <select id="bulk-action" class="form-select me-2" style="width:200px;">
                            <option value="">{{ __('Select Action') }}</option>
                            <option value="enable">{{ __('Enable Selected') }}</option>
                            <option value="disable">{{ __('Disable Selected') }}</option>
                            <option value="delete">{{ __('Delete Selected') }}</option>
                        </select>
                        <button id="apply-bulk-action" class="btn btn-primary">
                            <i class="mdi mdi-check"></i> {{ __('Apply') }}
                        </button>
                    </div>

                    <div class="col-lg-6 d-md-flex justify-content-end mt-2 mt-md-0">
                        <a href="{{ route('admin.catalog.categories.create') }}" class="btn btn-primary">
                            <i class="mdi mdi-plus"></i> {{ __('Add New Category') }}
                        </a>
                    </div>
                </div>

                <div class="table-responsive rounded-3">
                    <table id="categoriesTable" class="table table-bordered table-hover align-middle">
                        <thead class="text-dark">
                            <tr>
                                <th class="text-center"><input type="checkbox" id="mainCheckbox" class="form-check-input"></th>
                                <th>#</th>
                                <th>{{ __('Category Name') }}</th>
                                <th>{{ __('Industry Name') }}</th>
                                <th>{{ __('Image') }}</th>
                                <th>{{ __('Status') }}</th>
                                <th>{{ __('Created Date') }}</th>
                                <th class="text-center">{{ __('Actions') }}</th>
                            </tr>
                        </thead>
                        <tbody class="customtable"></tbody>
                    </table>
                </div>

            </div>
        </div>

    </div>
@endsection


@section('js')
<script>
    const categoryRoutes = {
        dataUrl: "{{ route('admin.catalog.categories.data') }}",
        bulkActionUrl: "{{ route('admin.catalog.categories.bulk-action') }}",
        csrfToken: "{{ csrf_token() }}"
    };

    const translations = {
        select_one: "{{ __('Please select at least one category.') }}",
        delete_confirm: "{{ __('You would not be able to revert this!') }}",
        enable_confirm: "{{ __('This will enable the selected categories.') }}",
        disable_confirm: "{{ __('This will disable the selected categories.') }}",
        valid_action: "{{ __('Please select a valid action.') }}",
        sure: "{{ __('Are you sure?') }}",
        proceed: "{{ __('Proceed') }}",
        cancel: "{{ __('Cancel') }}",
        success: "{{ __('Categories updated successfully.') }}",
        error: "{{ __('An error occurred. Please try again.') }}"
    };

    const datatableLang = {
        "processing": "{{ __('Processing...') }}",
        "lengthMenu": "{{ __('Show _MENU_ entries') }}",
        "zeroRecords": "{{ __('No matching records found') }}",
        "info": "{{ __('Showing _START_ to _END_ of _TOTAL_ entries') }}",
        "infoEmpty": "{{ __('Showing 0 to 0 of 0 entries') }}",
        "infoFiltered": "{{ __('(filtered from _MAX_ total entries)') }}",
        "search": "{{ __('Search:') }}"
    };
</script>

<script src="{{ asset('assets/js/pages/catalog/category/index.js') }}"></script>
@endsection
