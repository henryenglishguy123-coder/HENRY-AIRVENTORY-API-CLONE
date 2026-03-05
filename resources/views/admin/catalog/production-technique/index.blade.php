@extends('admin.layouts.app')
@section('title', __('Manage Production Techniques'))
@section('content')
    <div class="page-breadcrumb">
        <div class="row">
            <div class="col-12 d-flex no-block align-items-center">
                <h4 class="page-title">{{ __('Production Techniques') }}</h4>
                <div class="ms-auto text-end">
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">{{ __('Home') }}</a></li>
                            <li class="breadcrumb-item active" aria-current="page">{{ __('Production Techniques') }}</li>
                        </ol>
                    </nav>
                </div>
            </div>
        </div>
    </div>

    <div class="container-fluid">
        <x-alerts />
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div>
                                <select id="bulk-action" class="form-select d-inline-block" style="width: 180px;">
                                    <option value="">{{ __('Select Action') }}</option>
                                    <option value="enable">{{ __('Enable Selected') }}</option>
                                    <option value="disable">{{ __('Disable Selected') }}</option>
                                    <option value="delete">{{ __('Delete Selected') }}</option>
                                </select>
                                <button id="apply-bulk-action" class="btn btn-primary ms-2">{{ __('Apply') }}</button>
                            </div>
                            <a href="{{ route('admin.catalog.production-techniques.create') }}" class="btn btn-primary">
                                <i class="mdi mdi-plus"></i> {{ __('Add') }}
                            </a>
                        </div>

                        <div class="table-responsive">
                            <table id="productionTechniqueTable" class="table table-striped table-bordered">
                                <thead class="">
                                    <tr>
                                        <th scope="col" style="width: 50px;">
                                            <input type="checkbox" class="form-check-input" id="mainCheckbox" />
                                        </th>
                                        <th scope="col">{{ __('Name') }}</th>
                                        <th scope="col">{{ __('Created At') }}</th>
                                        <th scope="col" style="width: 100px;">{{ __('Status') }}</th>
                                        <th scope="col" style="width: 150px;">{{ __('Actions') }}</th>
                                    </tr>
                                </thead>
                                <tbody class="customtable"></tbody>
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
        window.productionTechniqueIndexConfig = {
            dataUrl: "{{ route('admin.production-techniques.data') }}",
            bulkActionUrl: "{{ route('admin.production-techniques.bulk-action') }}",
            csrfToken: "{{ csrf_token() }}",

            messages: {
                selectOne: "{{ __('Please select at least one technique.') }}",
                invalidAction: "{{ __('Please select a valid action.') }}",
                success: "{{ __('Techniques updated successfully.') }}",
                error: "{{ __('An error occurred. Please try again.') }}",
                confirmTitle: "{{ __('Are you sure?') }}",
                confirmDelete: "{{ __('You would not be able to revert this!') }}",
                confirmEnable: "{{ __('This will enable the selected techniques.') }}",
                confirmDisable: "{{ __('This will disable the selected techniques.') }}",
                proceed: "{{ __('Proceed') }}",
                cancel: "{{ __('Cancel') }}"
            }
        };
    </script>

    <script src="{{ asset('assets/js/pages/catalog/production-technique/index.js') }}"></script>
@endsection