@extends('admin.layouts.app')
@section('title', __('Manage Products'))
@section('content')
    <div class="page-breadcrumb">
        <div class="row">
            <div class="col-12 d-flex align-items-center justify-content-between">
                <h4 class="page-title">{{ __('Products') }}</h4>
                <div class="ms-auto">
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">{{ __('Home') }}</a></li>
                            <li class="breadcrumb-item active" aria-current="page">{{ __('Products') }}</li>
                        </ol>
                    </nav>
                </div>
            </div>
        </div>
    </div>

    <div class="container-fluid">
        <!-- Products Table -->
        <div class="row">
            <div class="col-12">
                <x-alerts />
                <div class="card border-0">
                    <div class="card-body">
                        <div class="row mb-3">
                            <div class="col-lg-3">
                                <input type="text" id="filter-search" class="form-control"
                                    placeholder="{{ __('Search by name or SKU') }}">
                            </div>

                            <div class="col-lg-3">
                                <select id="filter-status" class="form-select">
                                    <option value="">{{ __('All Status') }}</option>
                                    <option value="1">{{ __('Enabled') }}</option>
                                    <option value="0">{{ __('Disabled') }}</option>
                                </select>
                            </div>

                            <div class="col-lg-3">
                                <select id="filter-template" class="form-select">
                                    <option value="">{{ __('All Templates') }}</option>
                                    <option value="valid">{{ __('Template Assigned') }}</option>
                                    <option value="invalid">{{ __('Template Missing') }}</option>
                                </select>
                            </div>

                            <div class="col-lg-3 d-flex gap-2">
                                <button id="apply-filter" class="btn btn-primary w-100">
                                    {{ __('Apply') }}
                                </button>
                                <button id="reset-filter" class="btn btn-outline-secondary w-100">
                                    {{ __('Reset') }}
                                </button>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-lg-6 d-flex justify-content-between align-items-center">
                                <div class="d-flex align-items-center" style="width: 350px;">
                                    <select id="bulk-action" class="form-select me-2">
                                        <option value="">{{ __('Bulk Actions') }}</option>
                                        <option value="enable">{{ __('Enable') }}</option>
                                        <option value="disable">{{ __('Disable') }}</option>
                                        <option value="delete">{{ __('Delete') }}</option>
                                    </select>

                                    <button id="apply-bulk-action" class="btn btn-primary" disabled>
                                        {{ __('Apply') }}
                                    </button>

                                </div>
                            </div>
                            <div class="col-lg-6 d-flex justify-content-end align-items-center">
                                <a href="{{ route('admin.catalog.product.add') }}" class="btn btn-primary">
                                    <i class="fas fa-plus"></i> {{ __('Add Product') }}
                                </a>
                            </div>
                        </div>
                        <div class="table-responsive">
                            <table id="products-table" class="table table-bordered">
                                <thead class="">
                                    <tr>
                                        <th class="text-center">
                                            <input type="checkbox" id="mainCheckbox" class="form-check-input">
                                        </th>
                                        <th scope="col">{{ __('#') }}</th>
                                        <th scope="col">{{ __('Product Info') }}</th>
                                        <th scope="col">{{ __('Status') }}</th>
                                        <th scope="col">{{ __('Price') }}</th>
                                        <th scope="col">{{ __('Template Status')}}</th>
                                        <th scope="col">{{ __('Actions') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <!-- Data will be populated dynamically -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('js')
    <script src="{{ asset('assets/js/pages/catalog/product/products.js') }}"></script>
    <script>
        window.trans = @json(__('admin'));
        new ProductTableManager({
            dataUrl: @json(route('catalog.products.index')),
            templateassignUrl: @json(route('admin.catalog.product.design-template', ':id')),
            bulkActionUrl: @json(route('admin.catalog.product.bulk-action')),
            csrfToken: "{{ csrf_token() }}"
        });
    </script>

@endsection