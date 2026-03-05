@extends('admin.layouts.app')

@section('title', __('Assign Layer Images'))
@section('css')
    <link rel="stylesheet" href="{{ asset('assets/css/pages/catalog/product/design-template/page.css') }}">
@endsection

@section('content')
    <div class="page-breadcrumb mb-4">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h4 class="page-title mb-1">
                    {{ __('Assign Layer Images') }} <small class="text-muted">– {{ $product->sku }}</small>
                </h4>
                <p class="text-muted small mb-0">
                    {{ __('Upload images for each color variation below. Changes are saved automatically.') }}
                </p>
            </div>
        </div>
    </div>

    <div class="container-fluid">
        <x-alerts />

        <div id="layer-loading" class="text-center py-5">
            <div class="spinner-border text-primary mb-3" role="status"></div>
            <div class="text-muted">{{ __('Loading product configuration...') }}</div>
        </div>

        <div id="layers-wrapper" class="d-none">
            @foreach($layers as $layer)
                @php
                    $layerImages = $product->layerImages->where('catalog_design_template_layer_id', $layer->id);
                @endphp

                <div class="layer-section layer-block" data-layer-id="{{ $layer->id }}" data-existing='@json($layerImages)'>

                    <div class="layer-header">
                        <h5 class="layer-title m-0">{{ $layer->layer_name }}
                        </h5>
                    </div>

                    <div class="card-body">
                        <div class="row g-3 layer-options" data-layer-id="{{ $layer->id }}">
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
@endsection

@section('js')
    <script>
        window.productVariationUrl = "{{ route('catalog.products.design-template.colors', $product->slug) }}";
        window.uploadLayerImageUrl = "{{ route('admin.catalog.product.upload-layer-image', $product->id) }}";
        window.baseImageUrl = "{{ Storage::url('/') }}";
        window.csrfToken = "{{ csrf_token() }}";
    </script>
    {{-- Include the JS file below --}}
    <script src="{{ asset('assets/js/pages/catalog/product/design-template/assign-image.js') }}"></script>
@endsection