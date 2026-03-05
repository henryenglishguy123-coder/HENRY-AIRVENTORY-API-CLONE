@extends('admin.layouts.app')

@section('title', __('Factory Sales Routing'))

@section('content')

    {{-- ================= Page Header ================= --}}
    <div class="page-breadcrumb mb-3">
        <div class="row align-items-center">
            <div class="col-md-6">
                <h4 class="page-title mb-0">{{ __('Factory Sales Routing') }}</h4>
                <small class="text-muted">
                    Define country-wise routing priority for factories
                </small>
            </div>
            <div class="col-md-6 text-end">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb justify-content-end mb-0">
                        <li class="breadcrumb-item">
                            <a href="{{ route('admin.dashboard') }}">Home</a>
                        </li>
                        <li class="breadcrumb-item active">Sales Routing</li>
                    </ol>
                </nav>
            </div>
        </div>
    </div>

    {{-- ================= Page Content ================= --}}
    <div class="container-fluid">

        {{-- ================= Action Toolbar ================= --}}
        <div class="d-flex justify-content-end mb-3">
            <div class="btn-group">
                <button
                    type="button"
                    class="btn btn-success"
                    data-bs-toggle="modal"
                    data-bs-target="#importModal"
                >
                    <i class="fas fa-file-upload me-1"></i>
                    Import
                </button>

                <button
                    type="button"
                    class="btn btn-outline-secondary dropdown-toggle"
                    data-bs-toggle="dropdown"
                >
                    <i class="fas fa-download me-1"></i>
                    Export
                </button>

                <ul class="dropdown-menu dropdown-menu-end">
                    <li>
                        <a
                            class="dropdown-item"
                            href="javascript:void(0)"
                            onclick="FactorySalesRouting.actions.export('xlsx')"
                        >
                            Excel (.xlsx)
                        </a>
                    </li>
                    <li>
                        <a
                            class="dropdown-item"
                            href="javascript:void(0)"
                            onclick="FactorySalesRouting.actions.export('csv')"
                        >
                            CSV (.csv)
                        </a>
                    </li>
                </ul>
            </div>
        </div>

        <div class="row">
            {{-- ================= Add / Edit Routing ================= --}}
            <div class="col-lg-4">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title mb-3">
                            {{ __('Add / Update Routing Rule') }}
                        </h5>

                        <form id="routingForm">
                            @csrf

                            {{-- Factory --}}
                            <div class="mb-3">
                                <label class="form-label fw-semibold">
                                    {{ __('Factory') }}
                                </label>
                                <select
    name="factory_id"
    id="factorySelect"
    class="form-select"
    required
>
    <option value="">
        {{ __('Select Factory') }}
    </option>
</select>

                                <small class="text-muted">
                                    Factory cannot be changed once saved
                                </small>
                            </div>

                            {{-- Countries --}}
                            <div class="mb-3">
                                <label class="form-label fw-semibold">
                                    {{ __('Countries') }}
                                </label>
                                <select
                                    name="country_ids[]"
                                    id="countrySelect"
                                    class="form-select"
                                    multiple
                                    required
                                ></select>
                                <small class="text-muted">
                                    Multiple countries allowed
                                </small>
                            </div>

                            {{-- Priority --}}
                            <div class="mb-4">
                                <label class="form-label fw-semibold">
                                    {{ __('Priority') }}
                                </label>
                                <input
                                    type="number"
                                    name="priority"
                                    class="form-control"
                                    min="1"
                                    placeholder="1 = Highest Priority"
                                    required
                                >
                            </div>

                            <button
                                type="submit"
                                class="btn btn-primary w-100"
                                id="btnSave"
                            >
                                {{ __('Save Routing') }}
                            </button>

                            <button
                                type="button"
                                class="btn btn-outline-secondary w-100 mt-2 d-none"
                                id="btnCancel"
                            >
                                {{ __('Cancel Edit') }}
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            {{-- ================= Routing List ================= --}}
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="card-title mb-0">
                                {{ __('Routing List') }}
                            </h5>
                        </div>

                        <table
                            id="routingTable"
                            class="table table-bordered table-striped align-middle w-100"
                        >
                            <thead class="table-light">
                                <tr>
                                    <th>{{ __('Factory') }}</th>
                                    <th>{{ __('Countries') }}</th>
                                    <th class="text-center">{{ __('Priority') }}</th>
                                    <th class="text-center">{{ __('Actions') }}</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ================= Import Modal ================= --}}
    <div
        class="modal fade"
        id="importModal"
        tabindex="-1"
        aria-hidden="true"
    >
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        Import Routing Rules
                    </h5>
                    <button
                        type="button"
                        class="btn-close"
                        data-bs-dismiss="modal"
                    ></button>
                </div>

                <div class="modal-body">
                    <form id="importForm">
                        <div class="mb-3">
                            <label class="form-label fw-semibold">
                                Upload File
                            </label>
                            <input
                                type="file"
                                class="form-control"
                                id="importFile"
                                name="file"
                                accept=".csv,.xlsx,.xls"
                                required
                            >
                            <small class="text-muted">
                                Supported formats: CSV, XLS, XLSX
                            </small>
                        </div>

                        <div class="alert alert-info py-2 mb-0">
                            <small>
                                Required headers:
                                <code>factory_id</code>,
                                <code>country_codes</code>,
                                <code>priority</code>
                            </small>
                        </div>
                    </form>
                </div>

                <div class="modal-footer">
                    <button
                        type="button"
                        class="btn btn-outline-secondary"
                        data-bs-dismiss="modal"
                    >
                        Close
                    </button>
                    <button
                        type="button"
                        class="btn btn-primary"
                        id="btnImportSubmit"
                    >
                        Upload & Import
                    </button>
                </div>
            </div>
        </div>
    </div>

@endsection

@section('js')
    <script>
        window.FactorySalesRouting = {
            csrfToken: "{{ csrf_token() }}",
            urls: {
                countries: @json(route('location.countries.index')),
                routing: {
                    list: @json(route('admin.sales-routing-api.index')),
                    store: @json(route('admin.sales-routing-api.store')),
                    import: @json(route('admin.sales-routing.import')),
                    exportBase: "{{ route('admin.sales-routing.export', ['type' => ':type']) }}",
                    update: id => '{{ route('admin.sales-routing-api.update', ':id') }}'.replace(':id', id),
                    delete: id => '{{ route('admin.sales-routing-api.destroy', ':id') }}'.replace(':id', id),
                    factory_list : @json(route('admin.factories.index')),
                }
            }
        };
    </script>

    <script src="{{ asset('assets/js/pages/factory/sales-routing/index.js') }}"></script>
@endsection
