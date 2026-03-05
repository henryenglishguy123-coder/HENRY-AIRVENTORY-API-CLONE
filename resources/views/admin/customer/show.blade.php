@extends('admin.layouts.app')

@section('title', __('Customer Basic Information'))

@section('content')
    <div class="page-breadcrumb">
        <div class="row">
            <div class="col-12 d-flex align-items-center">
                <h4 class="page-title">{{ __('Customer Basic Information') }}</h4>
                <div class="ms-auto text-end">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item">
                            <a href="{{ route('admin.dashboard') }}">{{ __('Home') }}</a>
                        </li>
                        <li class="breadcrumb-item">
                            <a href="{{ route('admin.customer.index') }}">{{ __('Customers') }}</a>
                        </li>
                        <li class="breadcrumb-item active">
                            {{ __('Basic Information') }}
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
                        <div id="pageLoader" class="text-center my-3 d-none">
                            <div class="spinner-border text-primary" role="status"></div>
                        </div>
                        <x-alerts />
                        <form method="POST" action="" enctype="multipart/form-data">
                            @csrf
                            <input type="hidden" name="vendor_id" value="{{ $customer->id }}">

                            {{-- ================= BUSINESS INFO ================= --}}
                            <h5 class="bg-primary text-white p-2 rounded">
                                {{ __('Business Information') }}
                            </h5>

                            <div class="row g-3 mt-2">
                                <div class="col-md-6">
                                    <label class="form-label">{{ __('Company Name') }}</label>
                                    <input type="text" name="company_name"
                                        class="form-control @error('company_name') is-invalid @enderror"
                                        value="{{ old('company_name') }}">
                                    @error('company_name')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label">{{ __('Tax / VAT Number') }}</label>
                                    <input type="text" name="tax_vat_number"
                                        class="form-control @error('tax_vat_number') is-invalid @enderror"
                                        value="{{ old('tax_vat_number') }}">
                                    @error('tax_vat_number')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label required">{{ __('Registered Address') }}</label>
                                    <input type="text" name="registered_address"
                                        class="form-control @error('registered_address') is-invalid @enderror"
                                        value="{{ old('registered_address') }}">
                                    @error('registered_address')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label required">{{ __('Country') }}</label>
                                    <select name="country"
                                        class="form-select country @error('country') is-invalid @enderror">
                                        <option value="" disabled selected>{{ __('Select Country') }}</option>
                                    </select>
                                    @error('country')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label required">{{ __('State') }}</label>
                                    <select name="state" class="form-select state @error('state') is-invalid @enderror">
                                        <option value="" disabled selected>{{ __('Select State') }}</option>
                                    </select>
                                    @error('state')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label required">{{ __('City') }}</label>
                                    <input type="text" name="city"
                                        class="form-control @error('city') is-invalid @enderror"
                                        value="{{ old('city') }}">
                                    @error('city')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label required">{{ __('Postal Code') }}</label>
                                    <input type="text" name="postal_code"
                                        class="form-control @error('postal_code') is-invalid @enderror"
                                        value="{{ old('postal_code') }}">
                                    @error('postal_code')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <hr class="my-4">

                            {{-- ================= CONTACT INFO ================= --}}
                            <h5 class="bg-primary text-white p-2 rounded">
                                {{ __('Contact Information') }}
                            </h5>

                            <div class="row g-3 mt-2">
                                <div class="col-md-6">
                                    <label class="form-label">{{ __('First Name') }}</label>
                                    <input type="text" name="primary_first_name" class="form-control"
                                        value="{{ old('primary_first_name') }}">
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label">{{ __('Last Name') }}</label>
                                    <input type="text" name="primary_last_name" class="form-control"
                                        value="{{ old('primary_last_name') }}">
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label">{{ __('Email') }}</label>
                                    <input type="email" name="primary_email" disabled
                                        class="form-control @error('primary_email') is-invalid @enderror"
                                        value="{{ old('primary_email') }}">
                                    @error('primary_email')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label">{{ __('Phone Number') }}</label>
                                    <input type="text" name="primary_phone" class="form-control"
                                        value="{{ old('primary_phone') }}">
                                </div>
                            </div>

                            {{-- ================= ACTION ================= --}}
                            <div class="mt-4 text-end">
                                <button type="submit" class="btn btn-primary px-4" id="saveBtn">
                                    <span class="spinner-border spinner-border-sm d-none me-2" id="saveSpinner"></span>
                                    {{ __('Save Information') }}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

            </div>
        </div>
    </div>
@endsection
@section('js')
    <script>
        window.showCustomer = {
            customerId: "{{ $customer->id }}",
            countryApiUrl: "{{ route('location.countries.index') }}",
            stateApiUrl: "{{ route('location.states.index', ':country') }}",
            customerApiUrl: "{{ route('customer.account.show') }}",
            saveCustomerApiUrl: "{{ route('customer.account.update') }}",
            billingDetailsApiUrl: "{{ route('customer.address.billing.show') }}",
            saveBillingDetailsApiUrl: "{{ route('customer.address.billing.add') }}",
        }
    </script>
    <script src="{{ asset('assets/js/pages/customer/show.js') }}"></script>
@endsection
