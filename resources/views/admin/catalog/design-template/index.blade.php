@extends('admin.layouts.app')

@section('content')
    <div class="page-breadcrumb">
        <div class="row">
            <div class="col-12 d-flex align-items-center">
                <h4 class="page-title">
                    <i class="mdi mdi-table-edit"></i> {{ __('Design Templates') }}
                </h4>
                <div class="ms-auto text-end">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item">
                            <a href="{{ route('admin.dashboard') }}">{{ __('Dashboard') }}</a>
                        </li>
                        <li class="breadcrumb-item active">{{ __('Design Templates') }}</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="container-fluid">
        <div class="card">
            <div class="card-body">

                {{-- Toolbar --}}
                <div class="d-flex justify-content-between mb-3">
                    <div>
                        <select id="bulkAction" class="form-select d-inline-block w-auto">
                            <option value="">{{ __('Select Action') }}</option>
                            <option value="delete">{{ __('Delete Selected') }}</option>
                        </select>
                        <button id="applyBulkAction" class="btn btn-primary ms-2">
                            {{ __('Apply') }}
                        </button>
                    </div>

                    <a href="{{ route('admin.catalog.design-template.create') }}" class="btn btn-primary">
                        <i class="fas fa-plus-circle"></i> {{ __('Create') }}
                    </a>
                </div>

                {{-- Table --}}
                <div class="table-responsive">
                    <table id="designTemplateTable" class="table table-bordered table-striped w-100">
                        <thead>
                            <tr>
                                <th>
                                    <input type="checkbox" id="selectAll">
                                </th>
                                <th>{{ __('Template Name') }}</th>
                                <th>{{ __('Template Layers') }}</th>
                                <th>{{ __('Actions') }}</th>
                            </tr>
                        </thead>
                    </table>
                </div>

            </div>
        </div>
    </div>
@endsection

@section('js')
    <script>
        window.DesignTemplateIndexConfig = {
            routes: {
                data: "{{ route('admin.catalog.design-template.data') }}",
                bulk: "{{ route('admin.catalog.design-template.bulk-action') }}"
            },
            csrf: "{{ csrf_token() }}",
            messages: {
                select_one: "{{ __('Please select at least one template.') }}",
                confirm_delete: "{{ __('Are you sure?') }}",
                confirm_delete_text: "{{ __('This action cannot be undone.') }}",
                success: "{{ __('Action completed successfully.') }}",
                error: "{{ __('Something went wrong. Please try again.') }}"
            }
        };
    </script>

    <script src="{{ asset('assets/js/pages/catalog/design-template/index.js') }}"></script>
@endsection
