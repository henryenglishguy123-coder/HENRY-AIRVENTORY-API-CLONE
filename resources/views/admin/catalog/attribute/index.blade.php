@extends('admin.layouts.app')
@section('title', __('Manage Attributes'))
@section('content')
    <div class="page-breadcrumb">
        <div class="row">
            <div class="col-12 d-flex no-block align-items-center">
                <h4 class="page-title">{{ __('Catalog Attributes') }}</h4>
                <div class="ms-auto text-end">
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">{{ __('Home') }}</a></li>
                            <li class="breadcrumb-item active" aria-current="page">{{ __('Catalog Attributes') }}</li>
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
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <select id="bulk-action" class="form-select d-inline-block" style="width: 180px;">
                                    <option value="">{{ __('Select Action') }}</option>
                                    <option value="enable">{{ __('Enable Selected') }}</option>
                                    <option value="disable">{{ __('Disable Selected') }}</option>
                                    {{-- <option value="delete">{{ __('Delete Selected') }}</option> --}}
                                </select>
                                <button id="apply-bulk-action" class="btn btn-primary ms-2">{{ __('Apply') }}</button>
                            </div>
                            <a href="{{ route('admin.catalog.attributes.create') }}" class="btn btn-primary">
                                <i class="mdi mdi-plus"></i> {{ __('Add') }}
                            </a>
                        </div>

                        <div class="table-responsive">
                            <table id="attributesTable" class="table table-striped table-bordered">
                                <thead class="">
                                    <tr>
                                        <th scope="col">
                                            <input type="checkbox" class="form-check-input" id="mainCheckbox" />
                                        </th>
                                        <th scope="col">{{ __('Attribute Code') }}</th>
                                        <th scope="col">{{ __('Field Type') }}</th>
                                        <th scope="col">{{ __('Status') }}</th>
                                        <th scope="col">{{ __('Actions') }}</th>
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
        window.attributesIndexConfig = {
            dataUrl: "{{ route('admin.catalog.attributes.data') }}",
            bulkActionUrl: "{{ route('admin.catalog.attributes.bulk-action') }}",
            csrfToken: "{{ csrf_token() }}",

            messages: {
                selectOne: "{{ __('Please select at least one attribute.') }}",
                invalidAction: "{{ __('Please select a valid action.') }}",
                success: "{{ __('Attributes updated successfully.') }}",
                error: "{{ __('An error occurred. Please try again.') }}",
                confirmTitle: "{{ __('Are you sure?') }}",
                confirmDelete: "{{ __('You would not be able to revert this!') }}",
                confirmEnable: "{{ __('This will enable the selected attributes.') }}",
                confirmDisable: "{{ __('This will disable the selected attributes.') }}",
                proceed: "{{ __('Proceed') }}",
                cancel: "{{ __('Cancel') }}"
            }
        };
    </script>

    <script src="{{ asset('assets/js/pages/catalog/attribute/index.js') }}"></script>

@endsection
