@extends('admin.layouts.app')

@section('title', __('Factory Branding'))
@section('content')
    <div class="page-breadcrumb">
        <div class="row">
            <div class="col-12 d-flex no-block align-items-center">
                <h4 class="page-title">{{ __('Factory Branding') }}</h4>
                <div class="ms-auto text-end">
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">{{ __('Dashboard') }}</a>
                            </li>
                            <li class="breadcrumb-item"><a
                                    href="{{ route('admin.factories.index-web') }}">{{ __('Factories') }}</a>
                            </li>
                            <li class="breadcrumb-item active" aria-current="page">{{ __('Factory Branding') }}
                            </li>
                        </ol>
                    </nav>
                </div>
            </div>
        </div>
    </div>
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-12 mb-3">
                <div class="card shadow-sm border-0 bg-white">
                    <div class="card-body p-4">
                        <div id="factorySummary">
                            <div class="placeholder-glow w-100">
                                <span class="placeholder col-4 mb-2"></span>
                                <span class="placeholder col-8"></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-3 mb-3 mb-md-0">
                @include('admin.factory.partials.sidebar', ['active' => 'branding', 'id' => $id])
            </div>

            <div class="col-md-9">
                <div class="card shadow-sm border-0">
                    <div class="card-body p-4">
                        <h5 class="card-title mb-4">{{ __('Branding Pricing Requirements') }}</h5>

                        <div class="row g-4">
                            <!-- Packaging Label -->
                            <div class="col-md-6">
                                <div class="card mb-0 border">
                                    <div class="card-header bg-light">
                                        <h6 class="mb-0 fw-bold"><i
                                                class="mdi mdi-label-outline me-2 text-primary"></i>{{ __('Packaging Label') }}
                                        </h6>
                                    </div>
                                    <div class="card-body pb-0">
                                        <form id="packagingLabelForm" method="POST">
                                            @csrf
                                            <input type="hidden" name="factory_id" value="{{ $id }}">
                                            <div class="form-check form-switch mb-3 mt-2">
                                                <input class="form-check-input" type="checkbox" name="is_active"
                                                    id="pl_is_active" value="1" checked>
                                                <label class="form-check-label fw-medium"
                                                    for="pl_is_active">{{ __('Active') }}</label>
                                            </div>
                                            <div id="packagingLabelAlert" class="alert d-none" role="alert"></div>

                                            <div class="mb-3">
                                                <label for="pl_front_price"
                                                    class="form-label required fw-medium">{{ __('Front Price') }}</label>
                                                <div class="input-group">
                                                    <span class="input-group-text">$</span>
                                                    <input type="number" step="0.01" min="0" name="front_price"
                                                        id="pl_front_price" class="form-control" required>
                                                </div>
                                                <div class="invalid-feedback" data-error-for="front_price"></div>
                                            </div>

                                            <div class="mb-4">
                                                <label for="pl_back_price"
                                                    class="form-label required fw-medium">{{ __('Back Price') }}</label>
                                                <div class="input-group">
                                                    <span class="input-group-text">$</span>
                                                    <input type="number" step="0.01" min="0" name="back_price"
                                                        id="pl_back_price" class="form-control" required>
                                                </div>
                                                <div class="invalid-feedback" data-error-for="back_price"></div>
                                            </div>

                                            <div class="d-flex justify-content-end mb-3">
                                                <button type="submit" id="packagingLabelSave"
                                                    class="btn btn-primary btn-sm px-4">
                                                    <span class="spinner-border spinner-border-sm me-2 d-none" role="status"
                                                        aria-hidden="true"></span>
                                                    <span class="btn-text">{{ __('Save Settings') }}</span>
                                                </button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>

                            <!-- Hang Tag -->
                            <div class="col-md-6">
                                <div class="card mb-0 border">
                                    <div class="card-header bg-light">
                                        <h6 class="mb-0 fw-bold"><i
                                                class="mdi mdi-tag-outline me-2 text-primary"></i>{{ __('Hang Tag') }}</h6>
                                    </div>
                                    <div class="card-body pb-0">
                                        <form id="hangTagForm" method="POST">
                                            @csrf
                                            <input type="hidden" name="factory_id" value="{{ $id }}">
                                            <div class="form-check form-switch mb-3 mt-2">
                                                <input class="form-check-input" type="checkbox" name="is_active"
                                                    id="ht_is_active" value="1" checked>
                                                <label class="form-check-label fw-medium"
                                                    for="ht_is_active">{{ __('Active') }}</label>
                                            </div>
                                            <div id="hangTagAlert" class="alert d-none" role="alert"></div>

                                            <div class="mb-3">
                                                <label for="ht_front_price"
                                                    class="form-label required fw-medium">{{ __('Front Price') }}</label>
                                                <div class="input-group">
                                                    <span class="input-group-text">$</span>
                                                    <input type="number" step="0.01" min="0" name="front_price"
                                                        id="ht_front_price" class="form-control" required>
                                                </div>
                                                <div class="invalid-feedback" data-error-for="front_price"></div>
                                            </div>

                                            <div class="mb-4">
                                                <label for="ht_back_price"
                                                    class="form-label required fw-medium">{{ __('Back Price') }}</label>
                                                <div class="input-group">
                                                    <span class="input-group-text">$</span>
                                                    <input type="number" step="0.01" min="0" name="back_price"
                                                        id="ht_back_price" class="form-control" required>
                                                </div>
                                                <div class="invalid-feedback" data-error-for="back_price"></div>
                                            </div>

                                            <div class="d-flex justify-content-end mb-3">
                                                <button type="submit" id="hangTagSave" class="btn btn-primary btn-sm px-4">
                                                    <span class="spinner-border spinner-border-sm me-2 d-none" role="status"
                                                        aria-hidden="true"></span>
                                                    <span class="btn-text">{{ __('Save Settings') }}</span>
                                                </button>
                                            </div>
                                        </form>
                                    </div>
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
        window.FactoryBrandingConfig = {
            factoryId: @json((int) $id),
            csrfToken: "{{ csrf_token() }}",
            currencySymbol: "$",
            routes: {
                adminFactoryShow: "{{ url('/api/v1/admin/factories') }}/:id",
                packagingLabelShow: "{{ route('admin.factories.label-settings.packaging-label.show', ['factory' => $id]) }}",
                packagingLabelUpdate: "{{ route('admin.factories.label-settings.packaging-label.update', ['factory' => $id]) }}",
                hangTagShow: "{{ route('admin.factories.label-settings.hang-tag.show', ['factory' => $id]) }}",
                hangTagUpdate: "{{ route('admin.factories.label-settings.hang-tag.update', ['factory' => $id]) }}",
            }
        };
    </script>
    <script src="{{ asset('assets/js/pages/factory/admin-branding.js') }}"></script>
@endsection