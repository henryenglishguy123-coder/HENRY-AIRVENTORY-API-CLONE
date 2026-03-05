@extends('admin.layouts.app')

@section('title', __('Customer Wallet'))

@section('content')
    <div class="page-breadcrumb">
        <div class="row">
            <div class="col-12 d-flex align-items-center">
                <h4 class="page-title">
                    <i class="mdi mdi-wallet me-1"></i>
                    {{ __('Customer Wallet') }}
                </h4>
                <div class="ms-auto text-end">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item">
                            <a href="{{ route('admin.dashboard') }}">{{ __('Home') }}</a>
                        </li>
                        <li class="breadcrumb-item">
                            <a href="{{ route('admin.customer.index') }}">{{ __('Customers') }}</a>
                        </li>
                        <li class="breadcrumb-item">
                            <a href="{{ route('admin.customer.show', $customer->id) }}">
                                {{ $customer->first_name }} {{ $customer->last_name }}
                            </a>
                        </li>
                        <li class="breadcrumb-item active">
                            {{ __('Wallet') }}
                        </li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="container-fluid">
        <div class="row">
            <div class="col-lg-3">
                @include('admin.customer._menu', ['id' => $customer->id])
            </div>
            <div class="col-lg-9">
                <div class="card shadow-sm">
                    <div class="card-body">
                        <x-alerts />

                        <h5 class="card-title">{{ __('Wallet Details') }}</h5>
                        <div class="row">
                            <div class="col-md-4">
                                <p><strong>{{ __('Customer Name:') }}</strong> {{ $customer->first_name }}
                                    {{ $customer->last_name }}
                                </p>
                            </div>
                            <div class="col-md-4">
                                <p><strong>{{ __('Current Balance:') }}</strong><span
                                        id="currentBalance"></span>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card shadow-sm">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="card-title text-primary">
                                <i class="mdi mdi-wallet-outline"></i> {{ __('Wallet Transactions') }}
                            </h5>
                            <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#fundsModal">
                                <i class="mdi mdi-plus-circle"></i> {{ __('Add / Deduct Wallet Funds') }}
                            </button>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-striped table-hover align-middle text-center" id="transactions-table">
                                <thead class="table-light">
                                    <tr>
                                        <th><i class="mdi mdi-pound"></i> {{ __('Transaction ID') }}</th>
                                        <th><i class="mdi mdi-swap-horizontal"></i> {{ __('Type') }}</th>
                                        <th><i class="mdi mdi-cash-multiple"></i> {{ __('Amount') }}</th>
                                        <th><i class="mdi mdi-chart-line"></i> {{ __('Balance After') }}</th>
                                        <th><i class="mdi mdi-credit-card"></i> {{ __('Payment Method') }}</th>
                                        <th><i class="mdi mdi-check-circle"></i> {{ __('Status') }}</th>
                                        <th><i class="mdi mdi-calendar"></i> {{ __('Transaction Date') }}</th>
                                        <th><i class="mdi mdi-cog"></i> {{ __('Action') }}</th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="modal fade" id="transactionModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content shadow">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="mdi mdi-receipt-text-outline me-1"></i>
                        {{ __('Transaction Details') }}
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <strong>{{ __('Transaction ID') }}:</strong>
                            <div id="m_transaction_id"></div>
                        </div>

                        <div class="col-md-6">
                            <strong>{{ __('Status') }}:</strong>
                            <div id="m_status"></div>
                        </div>

                        <div class="col-md-6">
                            <strong>{{ __('Type') }}:</strong>
                            <div id="m_type"></div>
                        </div>

                        <div class="col-md-6">
                            <strong>{{ __('Payment Method') }}:</strong>
                            <div id="m_payment_method"></div>
                        </div>

                        <div class="col-md-6">
                            <strong>{{ __('Amount') }}:</strong>
                            <div id="m_amount"></div>
                        </div>

                        <div class="col-md-6">
                            <strong>{{ __('Balance After') }}:</strong>
                            <div id="m_balance_after"></div>
                        </div>

                        <div class="col-12">
                            <strong>{{ __('Description') }}:</strong>
                            <div id="m_description" class="text-muted"></div>
                        </div>

                        <div class="col-12">
                            <strong>{{ __('Transaction Date') }}:</strong>
                            <div id="m_created_at"></div>
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        {{ __('Close') }}
                    </button>
                </div>
            </div>
        </div>
    </div>
    <div class="modal fade" id="fundsModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-md modal-dialog-centered">
            <div class="modal-content shadow">
                <form id="walletFundForm">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <i class="mdi mdi-wallet-plus-outline me-1"></i>
                            {{ __('Add / Deduct Wallet Funds') }}
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>

                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label required">{{ __('Transaction Type') }}</label>
                            <select class="form-select" name="type" required>
                                <option value="credit">{{ __('Add Funds (Credit)') }}</option>
                                <option value="debit">{{ __('Deduct Funds (Debit)') }}</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label required">{{ __('Amount') }}</label>
                            <input type="number" step="0.01" min="1" class="form-control" name="amount" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label required">{{ __('Description') }}</label>
                            <textarea class="form-control" name="description" rows="2"
                                placeholder="Reason for this transaction"></textarea>
                        </div>
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            {{ __('Cancel') }}
                        </button>
                        <button type="submit" class="btn btn-primary" id="fundSubmitBtn">
                            <span class="spinner-border spinner-border-sm d-none me-2" id="fundSubmitSpinner" role="status"
                                aria-hidden="true"></span>
                            <span id="fundSubmitText">
                                <i class="mdi mdi-check-circle-outline"></i> {{ __('Submit') }}
                            </span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>


@endsection
@section('js')
    <script>
        window.customerWallet = {
            customerId: @json($customer->id),
            transactionsDataUrl: @json(route('customer.wallet.transactions')),
            walletFundApiUrl: @json(route('admin.customers.wallet.fund')),
            customerWalletUrl: @json(route('customer.wallet.index', ['customer_id' => $customer->id])),
            csrfToken: @json(csrf_token()),
        }
    </script>
    <script src="{{ asset('assets/js/pages/customer/wallet.js') }}"></script>
@endsection
