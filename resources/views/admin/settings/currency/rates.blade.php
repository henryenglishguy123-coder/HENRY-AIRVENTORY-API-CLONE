@extends('admin.layouts.app')

@section('title', __('Currency Rate Setting'))

@section('content')
    {{-- Breadcrumb --}}
    <div class="page-breadcrumb">
        <div class="row">
            <div class="col-12 d-flex no-block align-items-center">
                <h4 class="page-title">{{ __('Currency Rate Setting') }}</h4>
                <div class="ms-auto text-end">
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb mb-0">
                            <li class="breadcrumb-item">
                                <a href="{{ route('admin.dashboard') }}">{{ __('Dashboard') }}</a>
                            </li>
                            <li class="breadcrumb-item active" aria-current="page">
                                {{ __('Currency Rate Setting') }}
                            </li>
                        </ol>
                    </nav>
                </div>
            </div>
        </div>
    </div>

    <div class="container-fluid">

        <div class="row">
            <div class="col-12">
                <div class="card">

                    {{-- Card header --}}
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="mb-1">{{ __('Currency Rate Setting') }}</h5>
                            <small class="text-muted">
                                @if ($defaultCurrency)
                                    {{ __('Set conversion rates relative to the default currency') }}
                                    <strong>{{ $defaultCurrency->code }}</strong>.
                                @else
                                    {{ __('No default currency is configured yet. Please set a default currency first.') }}
                                @endif
                            </small>
                        </div>
                    </div>

                    <div class="card-body">

                        @if (! $defaultCurrency)
                            <div class="alert alert-warning mb-0">
                                {{ __('Please configure a default currency before managing rates.') }}
                            </div>
                        @else
                            <form class="forms-sample" id="settings-form" method="POST"
                                  action="{{ route('admin.settings.currency.rates.save') }}">
                                @csrf

                                <div class="table-responsive">
                                    <table class="table table-bordered align-middle mb-0" id="currency_rates">
                                        <thead class="table-light">
                                            <tr>
                                                <th style="width: 35%">{{ __('Currency') }}</th>
                                                <th style="width: 25%">{{ __('Code') }}</th>
                                                <th style="width: 25%">
                                                    {{ __('Rate') }}
                                                    <small class="text-muted d-block">
                                                        ({{ __('relativeTo') }}: {{ $defaultCurrency->code }})
                                                    </small>
                                                </th>
                                                <th style="width: 15%">{{ __('Status') }}</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {{-- Default currency row --}}
                                            <tr>
                                                <td>
                                                    <span class="fw-semibold">
                                                        {{ $defaultCurrency->currency ?? $defaultCurrency->code }}
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="badge bg-secondary">
                                                        {{ $defaultCurrency->code }}
                                                    </span>
                                                </td>
                                                <td>
                                                    <input type="number" class="form-control"
                                                           id="currency_rate_{{ $defaultCurrency->id }}"
                                                           name="currency_rates[{{ $defaultCurrency->id }}]"
                                                           value="1"
                                                           step="0.0001"
                                                           min="0"
                                                           readonly
                                                           required>
                                                    <small class="text-muted">
                                                        {{ __('Base currency is always 1.0000') }}
                                                    </small>
                                                    <div id="currency_rate_{{ $defaultCurrency->id }}Error"
                                                         class="text-danger error"></div>
                                                </td>
                                                <td>
                                                    <span class="badge bg-success">
                                                        {{ __('Default') }}
                                                    </span>
                                                </td>
                                            </tr>

                                            {{-- Allowed currencies (excluding default if present in list) --}}
                                            @foreach ($allowedCurrencies as $currency)
                                                @if ($defaultCurrency && $currency->id === $defaultCurrency->id)
                                                    @continue
                                                @endif
                                                <tr>
                                                    <td>
                                                        <span class="fw-semibold">
                                                            {{ $currency->currency ?? $currency->code }}
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-primary">
                                                            {{ $currency->code }}
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <input type="number" class="form-control"
                                                               id="currency_rate_{{ $currency->id }}"
                                                               name="currency_rates[{{ $currency->id }}]"
                                                               value="{{ $currency->rate }}"
                                                               placeholder="{{ __('Enter Rate') }}"
                                                               step="0.0001"
                                                               min="0.0001"
                                                               required>
                                                        <div id="currency_rate_{{ $currency->id }}Error"
                                                             class="text-danger error"></div>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-primary">
                                                            {{ __('Allowed') }}
                                                        </span>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>

                                {{-- Actions --}}
                                <div class="d-flex justify-content-end mt-3">
                                    <button type="button"
                                            class="btn btn-outline-info me-2"
                                            id="update-rates-btn"
                                            data-url="{{ route('admin.settings.currency.rates.update') }}"
                                            data-loading-text="{{ __('Updating...') }}"
                                            data-url-error-text="{{ __('Update URL is not configured.') }}"
                                            data-success-text="{{ __('Rates updated from API successfully.') }}"
                                            data-error-text="{{ __('Failed to update rates from API.') }}">
                                        <i class="mdi mdi-cloud-download-outline me-1"></i>
                                        {{ __('Update Rates From API') }}
                                    </button>

                                    <button type="submit"
                                            class="btn btn-primary"
                                            id="setting-form-btn"
                                            data-loading-text="{{ __('Saving...') }}">
                                        <i class="mdi mdi-content-save me-1"></i>
                                        {{ __('Save Changes') }}
                                    </button>
                                </div>
                            </form>
                        @endif
                    </div> {{-- card-body --}}
                </div>
            </div>
        </div>
    </div>
@endsection

@section('js')
    <script src="{{ asset('assets/js/pages/settings/currency/currency-rates.js') }}"></script>
@endsection
