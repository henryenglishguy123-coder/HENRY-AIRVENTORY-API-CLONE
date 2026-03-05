@extends('admin.layouts.app')
@section('title', __('Create Product'))

@section('css')
    <link href="https://cdnjs.cloudflare.com/ajax/libs/dropzone/5.9.3/dropzone.min.css" rel="stylesheet" />
    <link href="{{ asset('assets/css/pages/product/product-form.css') }}" rel="stylesheet" />
@endsection

@section('content')
    <div class="container-fluid py-4">
        <div class="row mb-4 align-items-center">
            <div class="col-6">
                <h4 class="mb-0 fw-bold text-dark">{{ __('Add New Product') }}</h4>
                <p class="text-sm text-muted mb-0">{{ __('Create a new product with variants and automated SKU.') }}</p>
            </div>
            <div class="col-6 text-end">
                <a href="{{ route('admin.catalog.product.index') }}" class="btn btn-outline-secondary btn-sm mb-0">
                    <i class="fas fa-arrow-left me-1"></i> {{ __('Back to List') }}
                </a>
            </div>
        </div>

        <x-alerts />
        <div id="validation-summary" class="alert alert-danger d-none shadow-sm" role="alert">
            <div class="d-flex align-items-center mb-2">
                <i class="fas fa-exclamation-triangle fa-2x me-3"></i>
                <div>
                    <h6 class="mb-0">{{ __('Whoops! There were some problems.') }}</h6>
                    <small>{{ __('Please correct the errors below and try again.') }}</small>
                </div>
            </div>
            <ul class="mb-0 ps-3" id="validation-list"></ul>
        </div>
        <form id="productForm" action="{{ route('admin.catalog.product.store') }}" method="POST"
            enctype="multipart/form-data">
            @csrf

            <div class="row g-4">
                <div class="col-lg-8">
                    @include('admin.catalog.product.partials.basic-info')
                    @include('admin.catalog.product.partials.pricing')
                    @include('admin.catalog.product.partials.variants')
                    @include('admin.catalog.product.partials.specifications')
                </div>
                <div class="col-lg-4">
                    @include('admin.catalog.product.partials.organization')
                    @include('admin.catalog.product.partials.media')
                    @include('admin.catalog.product.partials.seo')
                    <div class="card mt-4">
                        <div class="card-body">
                            <button type="button" class="btn btn-primary w-100 mb-0 shadow-sm" id="product-form-btn">
                                <span id="btn-text">{{ __('Save Product') }}</span>
                                <span id="btn-spinner" class="spinner-border spinner-border-sm ms-2 d-none"></span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
    @include('admin.catalog.product.partials.assign-factory-modal')
@endsection

@section('js')
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/dropzone/5.9.3/min/dropzone.min.js"></script>
    <script>
        if (typeof Dropzone !== 'undefined') {
            Dropzone.autoDiscover = false;
        }
        window.ProductConfig = {
            productId: null,
            urls: {
                upload: "{{ route('admin.catalog.product.upload.media') }}",
                delete: "{{ route('admin.catalog.product.delete.media') }}",
                categories: "{{ route('catalog.category.index') }}",
                placeholder: "{{ getImageUrl('') }}",
                factories: "{{ route('admin.catalog.product.factories') }}",
                productInfo: "{{ route('admin.catalog.product.info', ':id') }}",
                assignFactories: "{{ route('admin.catalog.product.factory.assign') }}"
            },
            csrf: "{{ csrf_token() }}",
            oldData: {
                gallery: @json(old('gallery')),
                categoryId: "{{ old('category_id') }}"
            },
            messages: {
                saved: "{{ __('Product saved successfully.') }}",
                val_error_title: "{{ __('Validation Error') }}",
                val_error_text: "{{ __('Please check the highlighted fields and try again.') }}",
                server_error: "{{ __('An internal server error occurred.') }}",
                upload_error: "{{ __('Upload failed.') }}",
                file_large: "{{ __('File is too large. Max size is 200 MB.') }}", // Passing param
                variant_error: "{{ __('Please select attributes to generate variants.') }}",
                // Validation Rules Messages
                req_name: "{{ __('Product name is required.') }}",
                req_sku: "{{ __('SKU is required.') }}",
                req_price: "{{ __('Price is required.') }}",
                req_category: "{{ __('Please select a category.') }}",
                sale_price_less_equal_price: "{{ __('Sale price cannot be greater than price.') }}",
            },
            i18n: {
                selectCategory: "{{ __('Select Category') }}",
                variantError: "{{ __('Please select attributes to generate variants.') }}",
                wait: "{{ __('Please wait...') }}",
                saving: "{{ __('Saving...') }}",
                selectFactory: "{{ __('Select Factory') }}",
                markup: "{{ __('Markup') }}",
                regularPrice: "{{ __('Regular Price') }}",
                salePrice: "{{ __('Sale Price') }}",
                quantity: "{{ __('Quantity') }}",
                availableQty: "{{ __('Available quantity') }}",
                optional: "{{ __('Optional') }}",
                stockStatus: "{{ __('Stock Status') }}"
            }
        };
    </script>
    <script src="{{ asset('assets/js/pages/catalog/product/product-form.js') }}"></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            if (typeof window.ProductManager === 'function' && window.ProductConfig) {
                window.productManager = new ProductManager(window.ProductConfig);
            } else {
                console.warn('ProductManager or ProductConfig not defined. Skipping instantiation.');
                window.productManager = null;
            }
        });
    </script>
    <script src="{{ asset('assets/js/pages/catalog/product/product-factory.js') }}"></script>

@endsection