@extends('admin.layouts.app')
@section('title', __('Currency Settings'))
@section('content')
    {{-- Breadcrumb --}}
    <div class="page-breadcrumb">
        <div class="row">
            <div class="col-12 d-flex no-block align-items-center">
                <h4 class="page-title">{{ __('Currency Settings') }}</h4>
                <div class="ms-auto text-end">
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb mb-0">
                            <li class="breadcrumb-item">
                                <a href="{{ route('admin.dashboard') }}">{{ __('Dashboard') }}</a>
                            </li>
                            <li class="breadcrumb-item active" aria-current="page">
                                {{ __('Currency Settings') }}
                            </li>
                        </ol>
                    </nav>
                </div>
            </div>
        </div>
    </div>

    <div class="container-fluid">

        {{-- SECTION 1: Currency Settings --}}
        <div class="row">
            <div class="col-12">
                <div class="card mb-4">
                    <div class="card-body">
                        <form id="currency-settings-form" method="POST"
                            action="{{ route('admin.settings.currency.update') }}">
                            @csrf
                            @method('PUT')
                            <div class="row">
                                {{-- Default currency --}}
                                <div class="col-lg-6">
                                    <div class="form-group mb-3">
                                        <label for="default_currency_id" class="form-label">
                                            {{ __('Default Currency') }}
                                        </label>
                                        <select name="default_currency_id"
                                            class="form-select @error('default_currency_id') is-invalid @enderror"
                                            id="default_currency_id" required>
                                            <option value="">{{ __('Select') }}</option>
                                            @foreach ($currencies as $currency)
                                                <option value="{{ $currency->id }}"
                                                    data-currency-name="{{ $currency->currency }}"
                                                    {{ old('default_currency_id') == $currency->id || $currency->is_default == 1 ? 'selected' : '' }}>
                                                    {{ $currency->code }} [{{ strtoupper($currency->symbol) }}]
                                                </option>
                                            @endforeach
                                        </select>
                                        <span id="default_currency_idError" class="text-danger error"></span>
                                        @error('default_currency_id')
                                            <div class="invalid-feedback d-block">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>

                                {{-- Allowed currencies --}}
                                <div class="col-lg-6">
                                    <div class="form-group mb-3">
                                        <label for="allowed_currency_ids" class="form-label">
                                            {{ __('Allowed Currencies') }}
                                        </label>
                                        <select name="allowed_currency_ids[]" multiple
                                            class="form-select @error('allowed_currency_ids') is-invalid @enderror"
                                            id="allowed_currency_ids" style="height: calc(1.5em * 8);" required>
                                            @foreach ($currencies as $currency)
                                                <option value="{{ $currency->id }}"
                                                    data-currency-name="{{ $currency->code }}"
                                                    data-currency-rate="{{ $currency->rate }}"
                                                    @if (is_array(old('allowed_currency_ids')) && in_array($currency->id, old('allowed_currency_ids'))) selected
                                                    @elseif ($currency->is_allowed == 1)
                                                        selected @endif>
                                                    {{ $currency->code }} [{{ strtoupper($currency->symbol) }}]
                                                </option>
                                            @endforeach
                                        </select>
                                        <span id="allowed_currency_idsError" class="text-danger error"></span>
                                        @error('allowed_currency_ids')
                                            <div class="invalid-feedback d-block">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>

                                {{-- Fixer status --}}
                                <div class="col-lg-6">
                                    <div class="form-group mb-3">
                                        <label for="fixer_io_api_status" class="form-label">
                                            {{ __('Fixer API Status') }}
                                        </label>
                                        <select class="form-select @error('fixer_io_api_status') is-invalid @enderror"
                                            id="fixer_io_api_status" name="fixer_io_api_status" required>
                                            @php
                                                $fixerStatus = old(
                                                    'fixer_io_api_status',
                                                    $setting->fixer_io_api_status ?? 0,
                                                );
                                            @endphp
                                            <option value="1" {{ (int) $fixerStatus === 1 ? 'selected' : '' }}>
                                                {{ __('Enable') }}
                                            </option>
                                            <option value="0" {{ (int) $fixerStatus === 0 ? 'selected' : '' }}>
                                                {{ __('Disable') }}
                                            </option>
                                        </select>
                                        @error('fixer_io_api_status')
                                            <div class="invalid-feedback d-block">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>

                                {{-- Fixer API key --}}
                                <div class="col-lg-6">
                                    @php
                                        $showFixerKey = (int) $fixerStatus === 1;
                                    @endphp
                                    <div class="form-group mb-3" id="fixer_io_api_key_container"
                                        style="{{ $showFixerKey ? '' : 'display: none;' }}">
                                        <label for="fixer_io_api_key" class="form-label">
                                            {{ __('Fixer API Key') }}
                                        </label>
                                        <input type="text"
                                            class="form-control @error('fixer_io_api_key') is-invalid @enderror"
                                            id="fixer_io_api_key" name="fixer_io_api_key"
                                            value="{{ old('fixer_io_api_key', $setting->fixer_io_api_key ?? '') }}"
                                            placeholder="{{ __('Enter API Key') }}" autocomplete="off">
                                        <span id="fixer_io_api_keyError" class="text-danger error"></span>
                                        @error('fixer_io_api_key')
                                            <div class="invalid-feedback d-block">{{ $message }}</div>
                                        @enderror
                                        <small class="form-text text-muted">
                                            {{ __('Used to fetch live exchange rates using Fixer.io.') }}
                                        </small>
                                    </div>
                                </div>

                                <div class="col-12 d-flex justify-content-end mt-2">
                                    <button type="submit" class="btn btn-primary mt-3" id="currency-settings-btn">
                                        {{ __('Save Changes') }}
                                    </button>
                                </div>
                            </div> {{-- row --}}
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div> {{-- container-fluid --}}
@endsection

@section('js')
    <script>
        const defaultSelectLabel = "{{ __('Select') }}";
        const allowedSelectLabel = "{{ __('Select allowed currencies') }}";
        const savingLabel = "{{ __('Saving...') }}";
        const successLabel = "{{ __('Currency settings updated successfully.') }}";
        const errorLabel = "{{ __('Something went wrong. Please try again.') }}";
    </script>
<script src="{{ asset('assets/js/pages/settings/currency/currency-settings.js') }}"></script>
@endsection
