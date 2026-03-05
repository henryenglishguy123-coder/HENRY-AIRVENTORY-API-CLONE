@extends('admin.layouts.app')

@section('css')
    <script src="https://cdnjs.cloudflare.com/ajax/libs/fabric.js/5.3.1/fabric.min.js"></script>
    <link rel="stylesheet" href="{{ asset('assets/css/pages/catalog/design-template/create.css') }}">
@endsection

@section('content')
    <div class="page-breadcrumb">
        <div class="row">
            <div class="col-12 d-flex no-block align-items-center">
                <h4 class="page-title"><i class="mdi mdi-table-edit"></i>{{ __('Design Template') }}</h4>
                <div class="ms-auto text-end">
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">{{ __('Home') }}</a></li>
                            <li class="breadcrumb-item"><a
                                    href="{{ route('admin.catalog.design-template.index') }}">{{ __('Design Templates') }}</a>
                            </li>
                            <li class="breadcrumb-item active" aria-current="page">{{ __('Create') }}</li>
                        </ol>
                    </nav>
                </div>
            </div>
        </div>
    </div>

    <div class="container-fluid">
        <div class="row">
            <div class="col-md-12 card shadow-sm rounded p-0">
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-12">
                            <h3>{{ __('Configure designs') }}</h3>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="template_name" class="form-label">{{ __('Name') }}</label>
                                        <input type="text" name="template_name" class="form-control" id="template_name"
                                            required>
                                        <div class="invalid-feedback">
                                            {{ __('Template name is required.') }}
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="template_status" class="form-label">{{ __('Status') }}</label>
                                        <select class="form-select" id="template_status" required>
                                            <option value="1">{{ __('Enable') }}</option>
                                            <option value="0">{{ __('Disable') }}</option>
                                        </select>
                                        <div class="invalid-feedback">
                                            {{ __('Template status is required.') }}
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-12">
                                    <ul class="nav nav-tabs product-layer-container" id="layerTabs" role="tablist">
                                        <button id="addLayerButton"
                                            class="btn btn-secondary">{{ __('Add New Layer') }}</button>
                                    </ul>
                                    <div class="tab-content" id="layerContents">
                                    </div>
                                </div>
                                <div class="col-md-12 text-center">
                                    <button id="saveProductButton" class="btn btn-primary mt-3">
                                        <span id="saveButtonSpinner" class="spinner-border spinner-border-sm me-2"
                                            role="status" style="display: none;"></span>
                                        <span id="saveButtonText">{{ __('Save') }}</span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('js')
    <script>
        window.DesignTemplateConfig = {
            csrf: '{{ csrf_token() }}',
            routes: {
                upload: "{{ route('admin.catalog.design-template.upload-mockup') }}",
                store: "{{ route('admin.catalog.design-template.store') }}",
                index: "{{ route('admin.catalog.design-template.index') }}"
            },
            messages: @json(__('design_template'))
        };
    </script>
    <script src="{{ asset('assets/js/pages/catalog/design-template/create.js') }}"></script>
@endsection
