@extends('admin.layouts.app')
@section('title', __('Shipping Rates'))

@section('content')

    {{-- Meta for JS --}}
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <div class="page-breadcrumb mb-4">
        <div class="row align-items-center">
            <div class="col-md-6">
                <h4 class="page-title fw-bold text-dark">{{ __('Shipping Rates Configuration') }}</h4>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">{{ __('Dashboard') }}</a></li>
                        <li class="breadcrumb-item active">{{ __('Shipping Rates') }}</li>
                    </ol>
                </nav>
            </div>
        </div>
    </div>

    <div class="container-fluid">
        <form id="shippingRatesForm" novalidate>
            <div class="card shadow border-0" style="min-height: 500px">

                {{-- Card Header --}}
                <div class="card-header bg-white border-bottom py-3">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div class="d-flex align-items-center gap-3">
                            <div
                                class="rounded-circle bg-primary bg-opacity-10 p-2 d-flex align-items-center justify-content-center">
                                <i class="fas fa-shipping-fast text-white fs-5"></i>
                            </div>
                            <div>
                                <h5 class="mb-0 fw-bold text-dark">Shipping Rates</h5>
                                <small class="text-muted">Configure shipping costs</small>
                            </div>
                        </div>
                        <div>
                            <button type="button" class="btn btn-primary fw-semibold" id="addMoreRates">
                                <i class="mdi mdi-plus-circle-outline me-1"></i>
                                {{ __('Add Rate') }}
                            </button>
                        </div>
                    </div>

                    <div class="row g-2 align-items-center">
                        <div class="col-12 col-xl-5 d-flex gap-2">
                            <div class="flex-grow-1">
                                <select id="shippingFilterFactory" class="form-select form-select-sm"></select>
                            </div>
                            <div class="flex-grow-1">
                                <select id="shippingFilterCountry" class="form-select form-select-sm"></select>
                            </div>
                        </div>

                        <div class="col-12 col-md-4 col-xl-3">
                            <div class="position-relative">
                                <i
                                    class="mdi mdi-magnify position-absolute top-50 start-0 translate-middle-y ms-2 text-muted"></i>
                                <input type="search" id="shippingRatesSearch" class="form-control form-control-sm ps-4"
                                    placeholder="{{ __('Search rates...') }}">
                            </div>
                        </div>

                        <div class="col-12 col-md-8 col-xl-4 d-flex justify-content-md-end gap-2">
                            <div class="input-group input-group-sm w-auto">
                                <select id="shippingPerPage" class="form-select" style="max-width: 70px">
                                    <option value="10">10</option>
                                    <option value="25">25</option>
                                    <option value="50" selected>50</option>
                                    <option value="100">100</option>
                                </select>
                                <select id="shippingSortBy" class="form-select" style="max-width: 100px">
                                    <option value="id">{{ __('ID') }}</option>

                                    <option value="shipping_title">{{ __('Title') }}</option>

                                    <option value="price">{{ __('Price') }}</option>

                                    <option value="min_qty">{{ __('Min Qty') }}</option>

                                    <option value="created_at">{{ __('Created At') }}

                                </select>
                                <select id="shippingSortDir" class="form-select" style="max-width: 70px">
                                    <option value="desc">Desc</option>
                                    <option value="asc">Asc</option>
                                </select>
                            </div>

                            <div class="btn-group btn-group-sm">
                                <button type="button" id="exportRatesBtn" class="btn btn-outline-secondary" title="Export">
                                    <i class="mdi mdi-download"></i> {{ __('Export') }}
                                </button>
                                <button type="button" class="btn btn-outline-secondary" data-bs-toggle="modal"
                                    data-bs-target="#importModal" title="Import">
                                    <i class="mdi mdi-upload"></i> {{ __('Import') }}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>


                {{-- Card Body --}}
                <div class="card-body bg-light">
                    <div id="shippingRatesContainer">
                        <div class="text-center py-5">
                            <div class="spinner-border text-primary" role="status"></div>
                            <p class="mt-2 text-muted">{{ __('Loading rates...') }}</p>
                        </div>
                    </div>

                    <div class="d-flex justify-content-end mt-3">
                        <div id="shippingRatesPagination"></div>
                    </div>
                </div>

                {{-- Sticky Footer --}}
                <div class="card-footer bg-white py-3 sticky-bottom-bar">
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="text-muted small">
                            <i class="fas fa-info-circle me-1"></i>
                            {{ __('Changes are not live until you click Save.') }}
                        </span>
                        <button type="submit" class="btn btn-primary" id="saveRatesBtn">{{ __('Save Changes') }}
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </div>
    <div class="modal fade" id="importModal">
        <div class="modal-dialog">
            <form id="importForm" enctype="multipart/form-data">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Import Shipping Rates</h5>
                    </div>

                    <div class="modal-body">
                        <input type="file" name="file" class="form-control" accept=".csv,.xlsx" required>
                    </div>

                    <div class="modal-footer">
                        <button type="submit" class="btn btn-primary">
                            Upload
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <template id="shippingRateTemplate">
        @include('admin.settings.shipping.partials.rate-row', ['defaultCurrency' => $defaultCurrency])
    </template>

@endsection

@section('js')
    <script>
        window.ShippingRatesConfig = {
            countryApiUrl: "{{ route('location.countries.index') }}",
            factoryApiUrl: "{{ route('admin.factories.index') }}",
            shippingRatesApiUrl: "{{ route('admin.shipping-rates.index') }}",
            deleteShippingRateUrl: "{{ route('admin.shipping-rates.destroy', ':id') }}",
            saveShippingRateUrl: "{{ route('admin.shipping-rates.store') }}",
            importShippingRateUrl: "{{ route('admin.shipping-rates.import') }}",
            exportShippingRateUrl: "{{ route('admin.shipping-rates.export') }}",

            i18n: {
                searchRates: @json(__('Search rates...')),
                searchFactory: @json(__('Search Factory...')),
                selectCountry: @json(__('Select Country')),
                deleteTitle: @json(__('Delete Rate?')),
                deleteText: @json(__('This cannot be undone.')),
                deletedSuccess: @json(__('Deleted!')),
                deleteConfirm: @json(__('Yes, delete it')),
                ratesSavedSuccess: @json(__('Shipping rates saved successfully')),
                loadError: @json(__('Failed to load rates')),
                saveFailed: @json(__('Failed to save rates')),
                processing: @json(__('Processing...')),
                importSuccess: @json(__('Imported!')),
                importFailed: @json(__('Import failed')),
                exportSuccess: @json(__('Exported!')),
                exportFailed: @json(__('Export failed')),
                addMoreRates: @json(__('Add Rate')),
                minOne: @json(__('Please add at least one rate')),
                prevPage: @json(__('Previous')),
                nextPage: @json(__('Next')),
                pageInfo: @json(__('Page :current of :last')),
            }
        };
    </script>

    <script src="{{ asset('assets/js/pages/settings/shipping/index.js') }}"></script>
@endsection