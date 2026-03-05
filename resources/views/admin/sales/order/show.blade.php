@extends('admin.layouts.app')

@section('title', __('Order Details'))

@section('content')

    {{-- ================= Page Header ================= --}}
    <div class="page-breadcrumb mb-3">
        <div class="row align-items-center">
            <div class="col-md-6">
                <h4 class="page-title mb-0">{{ __('Order Details') }}</h4>
                <small class="text-muted">
                    {{ __('View order information') }}
                </small>
            </div>
            <div class="col-md-6 text-end">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb justify-content-end mb-0">
                        <li class="breadcrumb-item">
                            <a href="{{ route('admin.dashboard') }}">{{ __('Home') }}</a>
                        </li>
                        <li class="breadcrumb-item">
                            <a href="{{ route('admin.sales.orders.index') }}">{{ __('Orders') }}</a>
                        </li>
                        <li class="breadcrumb-item active">{{ __('Details') }}</li>
                    </ol>
                </nav>
            </div>
        </div>
    </div>

    {{-- ================= Page Content ================= --}}
    <div class="container-fluid" id="order-container">

        <div class="text-center py-5" id="loading-spinner">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">{{ __('Loading...') }}</span>
            </div>
            <p class="mt-2">{{ __('Loading order details...') }}</p>
        </div>

        <div id="order-content" style="display: none; position: relative;">
            {{-- Loading Overlay --}}
            <div id="order-loading-overlay"
                class="position-absolute top-0 start-0 w-100 h-100 bg-white opacity-75 z-2 d-none">
                <div class="position-absolute top-50 start-50 translate-middle text-center">
                    <div class="spinner-border text-primary" role="status"></div>
                    <p class="mt-2 fw-bold text-dark">{{ __('Refreshing...') }}</p>
                </div>
            </div>


            {{-- Order Header Info --}}
            <div class="card shadow-sm mb-4">
                <div class="card-body d-flex flex-wrap justify-content-between align-items-center">
                    <div>
                        <h3 class="mb-0 fw-bold" id="header-order-number">...</h3>
                        <div class="d-flex align-items-center mt-1">
                            <span class="text-muted small me-2" id="header-created-at">...</span>
                            <div id="header-source"></div>
                        </div>
                    </div>
                    <div class="d-flex align-items-center gap-3">
                        <div class="d-flex gap-2">
                            <span id="header-order-status"></span>
                            <span id="header-payment-status"></span>
                        </div>
                        <div class="d-flex gap-2">
                            <button id="btn-ship-now" class="btn btn-primary d-none">
                                <i class="fas fa-shipping-fast me-1"></i> {{ __('Ship Now') }}
                            </button>
                            <button id="btn-cancel-shipment" class="btn btn-outline-danger d-none">
                                <i class="fas fa-times me-1"></i> {{ __('Cancel Shipment') }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Tabs Navigation --}}
            <ul class="nav nav-tabs mb-4" id="orderTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="overview-tab" data-bs-toggle="tab" data-bs-target="#overview"
                        type="button" role="tab">{{ __('Overview') }}</button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="shipments-tab" data-bs-toggle="tab" data-bs-target="#shipments"
                        type="button" role="tab">{{ __('Shipments & Tracking') }}</button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="communication-tab" data-bs-toggle="tab" data-bs-target="#communication"
                        type="button" role="tab">{{ __('Communication') }}</button>
                </li>
            </ul>

            {{-- Tabs Content --}}
            <div class="tab-content" id="orderTabsContent">

                {{-- Overview Tab --}}
                <div class="tab-pane fade show active" id="overview" role="tabpanel">
                    <div class="row">
                        <div class="col-lg-8">
                            <div class="card shadow-sm mb-4">
                                <div class="card-header bg-white py-3">
                                    <h5 class="mb-0 fw-bold">{{ __('Order Items') }}</h5>
                                </div>
                                <div class="card-body p-0">
                                    <div class="table-responsive">
                                        <table class="table align-middle mb-0">
                                            <thead class="bg-light">
                                                <tr>
                                                    <th class="ps-3">{{ __('Product') }}</th>
                                                    <th>{{ __('Price') }}</th>
                                                    <th class="text-center">{{ __('Qty') }}</th>
                                                    <th class="text-end pe-3">{{ __('Total') }}</th>
                                                </tr>
                                            </thead>
                                            <tbody id="order-items-body">
                                                {{-- JS --}}
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-4">
                            {{-- Recent Shipments Summary --}}
                            <div class="card shadow-sm mb-4" id="recent-shipments-card" style="display: none;">
                                <div class="card-header bg-white py-3">
                                    <h5 class="mb-0 fw-bold text-primary small text-uppercase"
                                        style="letter-spacing: 0.5px;">{{ __('Recent Shipments') }}</h5>
                                </div>
                                <div class="card-body p-0">
                                    <div id="recent-shipments-list">
                                        {{-- JS --}}
                                    </div>
                                    <div class="p-2 border-top text-center">
                                        <button class="btn btn-link btn-sm text-decoration-none p-0"
                                            onclick="document.getElementById('shipments-tab').click()">
                                            View All Shipments <i class="fas fa-arrow-right ms-1"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>

                            {{-- Order Summary --}}
                            <div class="card shadow-sm mb-4">
                                <div class="card-header bg-white py-3">
                                    <h5 class="mb-0 fw-bold">{{ __('Order Summary') }}</h5>
                                </div>
                                <div class="card-body">
                                    <div class="d-flex justify-content-between mb-2">
                                        <span class="text-muted">{{ __('Subtotal') }}</span>
                                        <span id="breakdown-subtotal">...</span>
                                    </div>
                                    <div class="d-flex justify-content-between mb-2">
                                        <span class="text-muted">{{ __('Shipping') }}</span>
                                        <span id="breakdown-shipping">...</span>
                                    </div>
                                    <div class="d-flex justify-content-between mb-2">
                                        <span class="text-muted">{{ __('Discount') }}</span>
                                        <span id="breakdown-discount" class="text-danger">...</span>
                                    </div>
                                    <div class="d-flex justify-content-between mb-2">
                                        <span class="text-muted">{{ __('Tax') }}</span>
                                        <span id="breakdown-tax">...</span>
                                    </div>
                                    <hr>
                                    <div class="d-flex justify-content-between fw-bold fs-5">
                                        <span>{{ __('Total Amount') }}</span>
                                        <span id="breakdown-total">...</span>
                                    </div>
                                </div>
                            </div>

                            {{-- Stakeholders --}}
                            <div class="card shadow-sm mb-4">
                                <div class="card-header bg-white py-3">
                                    <h5 class="mb-0 fw-bold">{{ __('Stakeholders') }}</h5>
                                </div>
                                <div class="card-body" id="customer-info-content">
                                    {{-- JS --}}
                                </div>
                            </div>

                            {{-- Shipping & Addresses --}}
                            <div class="card shadow-sm mb-4">
                                <div class="card-header bg-white py-3">
                                    <h5 class="mb-0 fw-bold">{{ __('Shipping & Addresses') }}</h5>
                                </div>
                                <div class="card-body">
                                    <div class="mb-3">
                                        <small
                                            class="text-uppercase fw-bold text-muted d-block mb-1">{{ __('Shipping Method') }}</small>
                                        <div class="d-flex align-items-center mb-3">
                                            <div class="bg-light p-2 rounded me-3 text-primary">
                                                <i class="fas fa-shipping-fast"></i>
                                            </div>
                                            <div>
                                                <div class="fw-bold" id="shipping-method-name">Standard</div>
                                                <div class="text-muted small" id="shipping-method-details">6-10 days</div>
                                            </div>
                                        </div>
                                    </div>
                                    <hr>
                                    <div class="mb-3">
                                        <small
                                            class="text-uppercase fw-bold text-muted d-block mb-1">{{ __('Shipping Address') }}</small>
                                        <div id="shipping-address-content" class="small"></div>
                                    </div>
                                    <hr>
                                    <div>
                                        <small
                                            class="text-uppercase fw-bold text-muted d-block mb-1">{{ __('Billing Address') }}</small>
                                        <div id="billing-address-content" class="small"></div>
                                    </div>
                                </div>
                            </div>

                            {{-- Transactions --}}
                            <div class="card shadow-sm mb-4">
                                <div class="card-header bg-white py-3">
                                    <h5 class="mb-0 fw-bold">{{ __('Payment Logs') }}</h5>
                                </div>
                                <div class="card-body p-0">
                                    <div class="table-responsive">
                                        <table class="table table-sm align-middle mb-0">
                                            <tbody id="order-transactions-body">
                                                {{-- JS --}}
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Shipments Tab --}}
                <div class="tab-pane fade" id="shipments" role="tabpanel">
                    <div class="row">
                        <div class="col-lg-12">
                            <div class="card shadow-sm mb-4" id="shipments-card" style="display: none;">
                                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                                    <h5 class="mb-0 fw-bold">{{ __('Shipment Logs') }}</h5>
                                    <button class="btn btn-sm btn-outline-secondary" onclick="loadOrder()">
                                        <i class="fas fa-sync-alt"></i>
                                    </button>
                                </div>
                                <div class="card-body p-0">
                                    <div class="accordion accordion-flush" id="shipmentAccordion">
                                        {{-- JS will render accordion items here --}}
                                        <div class="p-4 text-center text-muted">
                                            <div class="spinner-border spinner-border-sm text-primary me-2" role="status">
                                            </div>
                                            {{ __('Loading shipments...') }}
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="card shadow-sm mb-4">
                                <div class="card-header bg-white py-3">
                                    <h5 class="mb-0 fw-bold">{{ __('Activity Timeline') }}</h5>
                                </div>
                                <div class="card-body">
                                    <div id="order-timeline-body">
                                        {{-- JS --}}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Communication Tab --}}
                <div class="tab-pane fade" id="communication" role="tabpanel">
                    <div class="card shadow-sm mb-4">
                        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                            <h5 class="mb-0 fw-bold">{{ __('Communication') }}</h5>
                            <button type="button" class="btn btn-sm btn-outline-secondary" id="refresh-messages-btn">
                                <i class="fas fa-sync-alt" id="refresh-icon"></i>
                            </button>
                        </div>
                        <div class="card-body p-0">
                            <div id="chat-scroll-area" class="overflow-y-auto p-4 bg-light" style="height: 500px;">
                                <div id="messages-loading" class="text-center py-4 d-none">
                                    <div class="spinner-border text-primary spinner-border-sm" role="status"></div>
                                    <p class="small text-muted mt-2">{{ __('Loading messages...') }}</p>
                                </div>
                                <div id="messages-list" class="d-flex flex-column">
                                    {{-- JS --}}
                                </div>
                            </div>
                            <div id="no-messages" class="text-center py-5 d-none">
                                <p class="text-muted">{{ __('No messages found.') }}</p>
                            </div>
                            <div class="p-3 border-top bg-light">
                                <form id="quick-reply-form">
                                    <div class="row g-2 mb-2">
                                        <div class="col-sm-4">
                                            <select class="form-select form-select-sm" name="message_type"
                                                id="qr-message-type">
                                                <option value="text">{{ __('General') }}</option>
                                                <option value="sample_sent">{{ __('Sample Sent') }}</option>
                                                <option value="feedback">{{ __('Feedback') }}</option>
                                                <option value="approval">{{ __('Approval') }}</option>
                                            </select>
                                        </div>
                                        <div class="col-sm-8 text-end">
                                            <button type="button" class="btn btn-sm btn-link text-decoration-none"
                                                onclick="document.getElementById('attachments').click()">
                                                <i class="fas fa-paperclip me-1"></i> {{ __('Attach Files') }}
                                            </button>
                                            <div id="quick-att-status"
                                                class="small text-success fw-bold d-none d-inline-block ms-2">
                                                <i class="fas fa-check-circle"></i>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="input-group">
                                        <textarea class="form-control" id="qr-message-content" name="message" rows="2"
                                            placeholder="{{ __('Type your message...') }}"></textarea>
                                        <button class="btn btn-primary px-4" type="button" id="qr-send-btn">
                                            <i class="fas fa-paper-plane"></i>
                                        </button>
                                    </div>
                                    <input type="file" id="attachments" name="attachments[]" multiple class="d-none">
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <!-- Error Detail Modal -->
    <div class="modal fade" id="errorDetailModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">{{ __('Error Details') }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="error-payload-container" class="bg-light p-3 border rounded"
                        style="max-height: 400px; overflow-y: auto;">
                        <pre id="error-payload-raw" class="small mb-0"></pre>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('Close') }}</button>
                </div>
            </div>
        </div>
    </div>

@endsection

@section('js')
    <script>
        window.OrderDetailConfig = {
            orderId: "{{ $id }}",
            orderNumber: "{{ $orderNumber }}",
            urls: {
                get: "{{ route('admin.orders.show', ':id') }}".replace(':id', "{{ $id }}"),
                ship: "{{ route('admin.orders.ship', ':id') }}".replace(':id', "{{ $id }}"),
                cancelShipment: "{{ route('admin.shipments.cancel', ':shipment') }}",
            },
            translations: {
                no_shipping_address: "{{ __('No shipping address') }}",
                standard_shipping: "{{ __('Standard shipping') }}",
                shipping_details_placeholder: "{{ __('6 - 10 business days') }}",
                no_items_found: "{{ __('No items found') }}",
                no_transactions_found: "{{ __('No transactions found') }}",
                failed_to_load: "{{ __('Failed to load order details') }}",
                unauthorized: "{{ __('Unauthorized') }}",
                loading_messages: "{{ __('Loading messages...') }}",
                no_messages_found: "{{ __('No messages found for this order') }}"
            }
        };
    </script>
    <script src="{{ asset('assets/js/pages/admin/sales/order/show.js') }}"></script>
    <style>
        /* Essential Bootstrap Overrides for cleaner look */
        .nav-tabs .nav-link {
            border: none;
            color: #6c757d;
            font-weight: 600;
            padding: 12px 20px;
        }

        .nav-tabs .nav-link.active {
            border-bottom: 3px solid var(--bs-primary);
            color: var(--bs-primary);
            background: transparent;
        }

        .bg-light-soft {
            background-color: #f8fafc;
        }

        .timeline-item {
            position: relative;
            padding-bottom: 1.5rem;
            border-left: 2px solid #dee2e6;
            margin-left: 1rem;
            padding-left: 1.5rem;
        }

        .timeline-item:last-child {
            border-left: none;
        }

        .timeline-item::before {
            content: '';
            position: absolute;
            left: -9px;
            top: 0;
            width: 16px;
            height: 16px;
            border-radius: 50%;
            background: #fff;
            border: 2px solid #007bff;
        }

        #chat-scroll-area::-webkit-scrollbar {
            width: 5px;
        }

        #chat-scroll-area::-webkit-scrollbar-thumb {
            background: #cbd5e0;
            border-radius: 10px;
        }
    </style>
@endsection