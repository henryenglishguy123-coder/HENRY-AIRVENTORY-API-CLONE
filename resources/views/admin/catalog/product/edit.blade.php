@extends('admin.layouts.app')
@section('title', __('Edit Product'))

@section('css')
    <link href="https://cdnjs.cloudflare.com/ajax/libs/dropzone/5.9.3/dropzone.min.css" rel="stylesheet" />
    <link href="{{ asset('assets/css/pages/product/product-form.css') }}" rel="stylesheet" />
@endsection

@section('content')
    <div class="container-fluid py-4" id="product-edit-root" data-loaded="0">
        <div class="row mb-4 align-items-center">
            <div class="col-6">
                <h4 class="mb-0 fw-bold text-dark">{{ __('Edit Product') }}</h4>
                <p class="text-sm text-muted mb-0">{{ __('Update product information, pricing and media.') }}</p>
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
        <form id="productForm" action="{{ route('admin.catalog.product.update', $product->id) }}" method="POST"
            enctype="multipart/form-data" style="opacity:0; pointer-events:none;">
            @csrf

            <div class="row g-4">
                <div class="col-lg-6">
                    @include('admin.catalog.product.partials.basic-info')
                    @include('admin.catalog.product.partials.pricing')
                    @include('admin.catalog.product.partials.specifications')
                    @include('admin.catalog.product.partials.organization')
                    @include('admin.catalog.product.partials.media')
                    @include('admin.catalog.product.partials.seo')
                    <div class="card mt-4">
                        <div class="card-body">
                            <button type="button" class="btn btn-primary w-100 mb-0 shadow-sm" id="product-form-btn">
                                <span id="btn-text">{{ __('Update Product') }}</span>
                                <span id="btn-spinner" class="spinner-border spinner-border-sm ms-2 d-none"></span>
                            </button>
                            <button type="button" class="btn btn-outline-primary w-100 mb-0 mt-3 shadow-sm"
                                id="openAssignFactoryModal">
                                <i class="fas fa-industry me-1"></i> {{ __('Assign Factory') }}
                            </button>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6">
                    @include('admin.catalog.product.partials.variants-edit')
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
            productId: {{ $product->id }},
            urls: {
                upload: "{{ route('admin.catalog.product.upload.media') }}",
                delete: "{{ route('admin.catalog.product.delete.media') }}",
                categories: "{{ route('catalog.category.index') }}",
                placeholder: "{{ getImageUrl('') }}",
                factories: "{{ route('admin.catalog.product.factories') }}",
                productInfo: "{{ route('admin.catalog.product.info', ':id') }}",
                assignFactories: "{{ route('admin.catalog.product.factory.assign') }}",
                update: "{{ route('admin.catalog.product.update', $product->id) }}",
                variantImage: "{{ route('admin.catalog.product.variant.image') }}",
                variantDelete: "{{ route('admin.catalog.product.variant.delete', ':id') }}"
            },
            csrf: "{{ csrf_token() }}",
            oldData: {
                gallery: @json(old('gallery', $initialGallery)),
                categoryId: @json(old('category_id', $primaryCategoryId)),
            },
            existingVariants: @json($existingVariants ?? []),
            variantAttributes: @json($variantAttributesSelections ?? []),
            messages: {
                saved: "{{ __('Product updated successfully.') }}",
                val_error_title: "{{ __('Validation Error') }}",
                val_error_text: "{{ __('Please check the highlighted fields and try again.') }}",
                server_error: "{{ __('An internal server error occurred.') }}",
                upload_error: "{{ __('Upload failed.') }}",
                file_large: "{{ __('File is too large. Max size is 200 MB.') }}",
                variant_error: "{{ __('Please select attributes to generate variants.') }}",
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
    <script src="{{ asset('assets/js/pages/catalog/product/product-form-edit.js') }}"></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const root = document.getElementById('product-edit-root');
            if (root) {
                root.setAttribute('data-loaded', '1');
            }
            const form = document.getElementById('productForm');
            if (form) {
                form.style.opacity = '1';
                form.style.pointerEvents = 'auto';
            }
            window.productManager = new ProductEditManager(window.ProductConfig);
        });
    </script>
    <script src="{{ asset('assets/js/pages/catalog/product/product-factory.js') }}"></script>
@endsection