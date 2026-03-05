@extends('admin.layouts.app')

@section('title', __('Orders'))

@section('content')

    {{-- ================= Page Header ================= --}}
    <div class="page-breadcrumb mb-3">
        <div class="row align-items-center">
            <div class="col-md-6">
                <h4 class="page-title mb-0">{{ __('Orders') }}</h4>
                <small class="text-muted">
                    {{ __('Manage and view all sales orders') }}
                </small>
            </div>
            <div class="col-md-6 text-end">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb justify-content-end mb-0">
                        <li class="breadcrumb-item">
                            <a href="{{ route('admin.dashboard') }}">{{ __('Home') }}</a>
                        </li>
                        <li class="breadcrumb-item active">{{ __('Orders') }}</li>
                    </ol>
                </nav>
            </div>
        </div>
    </div>

    {{-- ================= Page Content ================= --}}
    <div class="container-fluid">

        {{-- Filters --}}
        <div class="card mb-3">
            <div class="card-body">
                <form id="filterForm">
                    <div class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label">{{ __('Order Status') }}</label>
                            <select name="status" class="form-select">
                                <option value="">{{ __('All Statuses') }}</option>
                                <option value="pending">{{ __('Pending') }}</option>
                                <option value="confirmed">{{ __('Confirmed') }}</option>
                                <option value="processing">{{ __('Processing') }}</option>
                                <option value="ready_to_ship">{{ __('Ready To Ship') }}</option>
                                <option value="shipped">{{ __('Shipped') }}</option>
                                <option value="delivered">{{ __('Delivered') }}</option>
                                <option value="cancelled">{{ __('Cancelled') }}</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">{{ __('Payment Status') }}</label>
                            <select name="payment_status" class="form-select">
                                <option value="">{{ __('All Payments') }}</option>
                                <option value="pending">{{ __('Pending') }}</option>
                                <option value="paid">{{ __('Paid') }}</option>
                                <option value="failed">{{ __('Failed') }}</option>
                                <option value="refunded">{{ __('Refunded') }}</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">{{ __('Date Range') }}</label>
                            <div class="input-group">
                                <input type="text" id="dateRangePicker" class="form-control" placeholder="{{ __('Select Date Range') }}">
                                <input type="hidden" name="start_date" id="startDate">
                                <input type="hidden" name="end_date" id="endDate">
                                <span class="input-group-text"><i class="fas fa-calendar"></i></span>
                            </div>
                        </div>
                        <div class="col-md-3 d-flex align-items-end">
                            <div class="d-grid gap-2 d-md-block w-100">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-filter me-1"></i> {{ __('Filter') }}
                                </button>
                                <button type="button" id="btnReset" class="btn btn-light border">
                                    {{ __('Reset') }}
                                </button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        {{-- API Error Card (hidden by default, shown on fetch failure) --}}
        <div id="ordersErrorCard" class="card border-danger d-none mb-3" role="alert">
            <div class="card-body d-flex align-items-start gap-3">
                <i class="mdi mdi-alert-circle-outline text-danger fs-2 flex-shrink-0 mt-1"></i>
                <div class="flex-grow-1">
                    <h6 class="mb-1 text-danger fw-bold" id="ordersErrorTitle">{{ __('Failed to load orders') }}</h6>
                    <p class="mb-2 text-muted small mb-0" id="ordersErrorMessage"></p>
                    <div class="d-flex align-items-center gap-2 mt-2">
                        <span id="ordersErrorCode" class="badge bg-danger d-none"></span>
                        <button type="button" class="btn btn-sm btn-outline-danger" id="ordersRetryBtn">
                            <i class="mdi mdi-refresh me-1"></i>{{ __('Retry') }}
                        </button>
                    </div>
                </div>
                <button type="button" class="btn-close flex-shrink-0" id="ordersErrorClose" aria-label="{{ __('Close') }}"></button>
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table id="ordersTable" class="table table-striped border">
                        <thead>
                            <tr>
                                <th>{{ __('Order #') }}</th>
                                <th>{{ __('Customer Information') }}</th>
                                <th>{{ __('Factory') }}</th>
                                <th>{{ __('Status') }}</th>
                                <th>{{ __('Payment') }}</th>
                                <th>{{ __('Total') }}</th>
                                <th>{{ __('Date') }}</th>
                                <th>{{ __('Action') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            {{-- Data via JS --}}
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>

@endsection

@section('js')
    {{-- Date Range Picker --}}
    <script type="text/javascript" src="https://cdn.jsdelivr.net/momentjs/latest/moment.min.js"></script>
    <script type="text/javascript" src="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.min.js"></script>
    <link rel="stylesheet" type="text/css" href="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.css" />

    <script>
        window.OrderListConfig = {
            urls: {
                list: "{{ route('admin.orders.index') }}", // Uses api.php route name
                show: id => "{{ route('admin.sales.orders.show', ':id') }}".replace(':id', id) // Uses web.php route name for UI
            },
            translations: {
                guest: "{{ __('Guest') }}",
                view: "{{ __('View') }}",
                session_expired: "{{ __('Session expired. Please login again.') }}",
                failed_to_load: "{{ __('Failed to load orders.') }}"
            }
        };
    </script>
    <script src="{{ asset('assets/js/pages/admin/sales/order/index.js') }}"></script>
@endsection
