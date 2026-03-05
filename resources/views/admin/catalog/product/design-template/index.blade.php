@extends('admin.layouts.app')

@section('title', __('Assign Design Template'))

@section('css')
    <style>
        .animation-fade-in { animation: fadeIn 0.4s ease-in-out; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
        .price-toggle { cursor: pointer; border-radius: 2em !important; }
        .is-invalid + .invalid-feedback { display: block; }
    </style>
@endsection

@section('content')
    <div class="page-breadcrumb mb-4">
        {{-- Breadcrumb HTML (Same as before) --}}
        <div class="d-flex justify-content-between align-items-center">
            <h4 class="page-title mb-1">
                {{ __('Assign Design Template') }} <small class="text-muted">– {{ $product->sku }}</small>
            </h4>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0 small">
                    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">{{ __('Home') }}</a></li>
                    <li class="breadcrumb-item">{{ __('Products') }}</li>
                    <li class="breadcrumb-item active">{{ __('Assign Design Template') }}</li>
                </ol>
            </nav>
        </div>
    </div>

    <div class="container-fluid">
        <div id="ajax-alert-container"></div>
        <x-alerts />

        <form id="templateConfigForm" method="POST" action="{{ route('admin.catalog.product.design-template.store', $product->id) }}">
            @csrf
            
            {{-- 1. Template Select --}}
            <div class="card shadow-sm mb-4">
                <div class="card-body">
                    <div class="row align-items-end">
                        <div class="col-md-6">
                            <label for="design_template_id" class="form-label fw-semibold">{{ __('Select Design Template') }}</label>
                            <select name="design_template_id" id="design_template_id" class="form-select @error('design_template_id') is-invalid @enderror" required>
                                <option value="">{{ __('— Choose Template —') }}</option>
                                @foreach ($templates as $template)
                                    <option value="{{ $template->id }}" {{ (old('design_template_id') ?? $assignedTemplate?->id) == $template->id ? 'selected' : '' }}>
                                        {{ $template->name }}
                                    </option>
                                @endforeach
                            </select>
                            <div class="invalid-feedback">{{ __('Please select a template.') }}</div>
                        </div>
                        <div class="col-md-6 text-end">
                            <button type="submit" class="btn btn-primary" id="saveBtn" disabled>
                                <span class="spinner-border spinner-border-sm d-none me-1" role="status" aria-hidden="true"></span>
                                <i class="fas fa-save me-1" id="saveIcon"></i> 
                                <span id="saveText">{{ __('Save Configuration') }}</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            {{-- 2. Layers Container --}}
            <div id="layers-loading" class="text-center py-5 d-none">
                <div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div>
                <p class="mt-2 text-muted">{{ __('Loading layers and configuration...') }}</p>
            </div>

            <div id="configuration-container">
                <div class="alert alert-light text-center border dashed p-5" id="empty-state">
                    <i class="fas fa-layer-group fa-3x text-muted mb-3"></i>
                    <h5>{{ __('Select a template above to configure layers') }}</h5>
                </div>
            </div>
        </form>
    </div>
@endsection

@section('js')
    <script src="{{ asset('assets/js/pages/catalog/product/design-template/assign.js') }}"></script>
    <script>
        $(document).ready(function() {
            const config = {
                urls: {
                    fetchLayers: "{{ route('admin.catalog.product.design-template.layers', ':id') }}",
                    redirect: "{{ route('admin.catalog.product.design-template.assign-image', $product->id) }}",
                },
                data: {
                    currentProductId: {{ $product->id }},
                    factories: @json($assignedFactories),
                    techniques: @json($printingTechniques),
                    initialPrices: @json($savedPrices ?? [])
                },
                messages: {
                    layerName: "{{ __('Layer Name') }}",
                    save: "{{ __('Save Configuration') }}",
                    saving: "{{ __('Saving...') }}",
                    errorLoadLayers: "{{ __('Failed to load layers.') }}",
                    errorSystem: "{{ __('System error while loading layers.') }}",
                    noLayers: "{{ __('This template has no layers defined.') }}",
                    errorValidation: "{{ __('Validation Error') }}",
                    errorUnknown: "{{ __('Unknown error') }}",
                },
                settings: {
                    currencySymbol: @json($defaultCurrency->symbol),
                }
            };
            new ProductDesignTemplateManager(config);
        });
    </script>
@endsection