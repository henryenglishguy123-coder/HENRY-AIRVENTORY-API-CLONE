@extends('admin.layouts.app')

@section('title', __('Factory Business Information'))
@section('content')
    <div class="page-breadcrumb">
        <div class="row">
            <div class="col-12 d-flex no-block align-items-center">
                <h4 class="page-title">{{ __('Factory Business Information') }}</h4>
                <div class="ms-auto text-end">
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">{{ __('Dashboard') }}</a>
                            </li>
                            <li class="breadcrumb-item"><a
                                    href="{{ route('admin.factories.index-web') }}">{{ __('Factories') }}</a>
                            </li>
                            <li class="breadcrumb-item active" aria-current="page">{{ __('Factory Business Information') }}
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
                @include('admin.factory.partials.sidebar', ['active' => 'business', 'id' => $id])
            </div>
            <div class="col-md-9">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="card-title mb-0">{{ __('Account & Business Details') }}</h5>
                        </div>

                        <div class="accordion custom-accordion" id="factoryBusinessAccordion">
                            <div class="accordion-item border-0 mb-3 shadow-sm rounded overflow-hidden">
                                <h2 class="accordion-header" id="headingStatus">
                                    <button class="accordion-button fw-bold py-3 px-4 bg-white" type="button" data-bs-toggle="collapse"
                                        data-bs-target="#collapseStatus" aria-expanded="true"
                                        aria-controls="collapseStatus">
                                        <i class="mdi mdi-shield me-2 text-primary fs-5"></i>{{ __('Status Management') }}
                                    </button>
                                </h2>
                                <div id="collapseStatus" class="accordion-collapse collapse show"
                                    aria-labelledby="headingStatus" data-bs-parent="#factoryBusinessAccordion">
                                    <div class="accordion-body bg-light p-4">
                                        
                                        <!-- Header Row: Title & Action -->
                                        <div class="d-flex flex-column flex-md-row justify-content-between align-items-center mb-4">
                                            <div>
                                                <h6 class="mb-1 text-dark fw-bold">{{ __('Current Factory Status') }}</h6>
                                                <small class="text-muted">{{ __('Manage the account visibility and verification state for this factory.') }}</small>
                                            </div>
                                            <div class="mt-3 mt-md-0">
                                                <button type="button" class="btn btn-primary px-4 py-2 d-inline-flex align-items-center gap-2 rounded-pill shadow-sm hover-shadow-sm transition-all text-white fw-semibold"
                                                    data-bs-toggle="modal" data-bs-target="#statusUpdateModal">
                                                    <i class="mdi mdi-shield-edit-outline fs-5"></i>
                                                    <span>{{ __('Update Factory Status') }}</span>
                                                </button>
                                            </div>
                                        </div>

                                        <!-- Status Pillars Row -->
                                        <div class="row g-4">
                                            <!-- Pillar 1: Account Status -->
                                            <div class="col-md-4">
                                                <div class="status-pillar bg-white shadow-sm border-0">
                                                    <div class="d-flex align-items-center mb-3">
                                                        <div class="avatar-sm bg-soft-primary text-primary me-3">
                                                            <i class="mdi mdi-account-circle fs-4"></i>
                                                        </div>
                                                        <h6 class="mb-0 text-dark fw-semibold uppercase text-xs tracking-wide">{{ __('Account Status') }}</h6>
                                                    </div>
                                                    <div id="currentAccountStatusBadge" class="mt-auto">
                                                        <span class="badge bg-light text-muted px-3 py-2 rounded-pill w-100">--</span>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Pillar 2: Verification -->
                                            <div class="col-md-4">
                                                <div class="status-pillar bg-white shadow-sm border-0">
                                                    <div class="d-flex align-items-center mb-3">
                                                        <div class="avatar-sm bg-soft-success text-success me-3">
                                                            <i class="mdi mdi-shield fs-4"></i>
                                                        </div>
                                                        <h6 class="mb-0 text-dark fw-semibold uppercase text-xs tracking-wide">{{ __('Verification') }}</h6>
                                                    </div>
                                                    <div id="currentVerificationBadge" class="mt-auto">
                                                        <span class="badge bg-light text-muted px-3 py-2 rounded-pill w-100">--</span>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Pillar 3: Profile Completeness -->
                                            <div class="col-md-4">
                                                <div class="status-pillar bg-white shadow-sm border-0 position-relative overflow-hidden">
                                                    <!-- Decorative background element -->
                                                    <div class="position-absolute top-0 end-0 opacity-10 p-3">
                                                        <i class="mdi mdi-progress-check fs-1 text-primary"></i>
                                                    </div>
                                                    
                                                    <div class="d-flex align-items-center mb-3 position-relative z-1">
                                                        <div class="avatar-sm bg-soft-info text-info me-3">
                                                            <i class="mdi mdi-card-account-details-star-outline fs-4"></i>
                                                        </div>
                                                        <h6 class="mb-0 text-dark fw-semibold uppercase text-xs tracking-wide">{{ __('Profile Completeness') }}</h6>
                                                    </div>
                                                    
                                                    <div id="completenessStatus" class="mt-auto position-relative z-1">
                                                        <div class="d-flex align-items-center gap-2 mb-2">
                                                            <div class="spinner-border spinner-border-sm text-primary" role="status"></div>
                                                            <span class="text-muted text-xs fw-semibold">{{ __('Analyzing...') }}</span>
                                                        </div>
                                                        <div class="progress progress-sm w-100">
                                                            <div class="progress-bar progress-bar-striped progress-bar-animated bg-primary" role="progressbar" style="width: 100%"></div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="accordion-item border-0 mb-3 shadow-sm rounded overflow-hidden">
                                <h2 class="accordion-header" id="headingBusiness">
                                    <button class="accordion-button collapsed fw-bold py-3 px-4 bg-white" type="button" data-bs-toggle="collapse"
                                        data-bs-target="#collapseBusiness" aria-expanded="false"
                                        aria-controls="collapseBusiness">
                                        <i class="mdi mdi-factory me-2 text-primary fs-5"></i>{{ __('Business Information') }}
                                    </button>
                                </h2>
                                <div id="collapseBusiness" class="accordion-collapse collapse"
                                    aria-labelledby="headingBusiness" data-bs-parent="#factoryBusinessAccordion">
                                    <div class="accordion-body p-4">
                                        <form id="businessInfoForm" enctype="multipart/form-data">
                                            @csrf
                                            <input type="hidden" name="factory_id" id="factory_id"
                                                value="{{ (int) $id }}">
                                            <div id="businessInfoAlert" class="alert d-none" role="alert"></div>

                                            <div id="businessInfoLoading" class="text-center py-4">
                                                <div class="spinner-border text-primary" role="status">
                                                    <span class="visually-hidden">{{ __('Loading...') }}</span>
                                                </div>
                                            </div>

                                            <div id="businessInfoContent" class="row g-3 d-none">
                                                <div class="col-md-6">
                                                    <label for="company_name"
                                                        class="form-label required">{{ __('Company Name') }}</label>
                                                    <input type="text" id="company_name" name="company_name"
                                                        class="form-control" required>
                                                    <div class="invalid-feedback" data-error-for="company_name"></div>
                                                </div>
                                                <div class="col-md-6">
                                                    <label for="registration_number"
                                                        class="form-label">{{ __('Registration No.') }}</label>
                                                    <input type="text" id="registration_number"
                                                        name="registration_number" class="form-control">
                                                    <div class="invalid-feedback" data-error-for="registration_number">
                                                    </div>
                                                </div>

                                                <div class="col-md-6">
                                                    <label for="tax_vat_number"
                                                        class="form-label">{{ __('Tax/VAT Number') }}</label>
                                                    <input type="text" id="tax_vat_number" name="tax_vat_number"
                                                        class="form-control">
                                                    <div class="invalid-feedback" data-error-for="tax_vat_number"></div>
                                                </div>
                                                <div class="col-md-6">
                                                    <label for="registered_address"
                                                        class="form-label required">{{ __('Registered Address') }}</label>
                                                    <input type="text" id="registered_address" name="registered_address"
                                                        class="form-control" required>
                                                    <div class="invalid-feedback" data-error-for="registered_address"></div>
                                                </div>

                                                <div class="col-md-6">
                                                    <label for="country_id"
                                                        class="form-label required">{{ __('Country') }}</label>
                                                    <select id="country_id" name="country_id" class="form-select"
                                                        required>
                                                        <option value="">{{ __('Select country') }}</option>
                                                    </select>
                                                    <div class="invalid-feedback" data-error-for="country_id"></div>
                                                </div>
                                                <div class="col-md-6">
                                                    <label for="state_id"
                                                        class="form-label">{{ __('Region/State') }}</label>
                                                    <select id="state_id" name="state_id" class="form-select">
                                                        <option value="">{{ __('Select state') }}</option>
                                                    </select>
                                                    <div class="invalid-feedback" data-error-for="state_id"></div>
                                                </div>

                                                <div class="col-md-6">
                                                    <label for="city"
                                                        class="form-label required">{{ __('City') }}</label>
                                                    <input type="text" id="city" name="city"
                                                        class="form-control" required>
                                                    <div class="invalid-feedback" data-error-for="city"></div>
                                                </div>
                                                <div class="col-md-6">
                                                    <label for="postal_code"
                                                        class="form-label required">{{ __('ZIP/Postal Code') }}</label>
                                                    <input type="text" id="postal_code" name="postal_code"
                                                        class="form-control" required>
                                                    <div class="invalid-feedback" data-error-for="postal_code"></div>
                                                </div>

                                                <div class="col-12 mt-3">
                                                    <h6 class="fw-semibold mb-3">{{ __('Certificates') }}</h6>
                                                </div>

                                                <div class="col-md-12">
                                                    <div class="mb-3 certificate-row"
                                                        data-cert="registration_certificate">
                                                        <label
                                                            class="form-label">{{ __('Registration Certificate') }}</label>
                                                        <div
                                                            class="certificate-existing d-flex align-items-center justify-content-between border rounded px-3 py-2 mb-2 d-none">
                                                            <div class="d-flex align-items-center flex-grow-1 me-3">
                                                                <i class="mdi mdi-file-outline text-muted me-2"></i>
                                                                <span id="registration_certificate_name"
                                                                    class="text-truncate"></span>
                                                            </div>
                                                            <div class="d-flex align-items-center gap-2">
                                                                <a id="registration_certificate_download" href="#"
                                                                    class="btn btn-outline-secondary btn-sm"
                                                                    target="_blank" rel="noopener">
                                                                    {{ __('Download') }}
                                                                </a>
                                                                <button type="button"
                                                                    class="btn btn-light btn-sm certificate-change">
                                                                    {{ __('Change') }}
                                                                </button>
                                                            </div>
                                                        </div>
                                                        <div class="certificate-upload">
                                                            <input type="file" id="registration_certificate"
                                                                name="registration_certificate" class="form-control"
                                                                accept=".pdf,.jpg,.jpeg,.png">
                                                            <div class="invalid-feedback"
                                                                data-error-for="registration_certificate"></div>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="col-md-12">
                                                    <div class="mb-3 certificate-row" data-cert="tax_certificate">
                                                        <label class="form-label">{{ __('Tax Certificate') }}</label>
                                                        <div
                                                            class="certificate-existing d-flex align-items-center justify-content-between border rounded px-3 py-2 mb-2 d-none">
                                                            <div class="d-flex align-items-center flex-grow-1 me-3">
                                                                <i class="mdi mdi-file-outline text-muted me-2"></i>
                                                                <span id="tax_certificate_name"
                                                                    class="text-truncate"></span>
                                                            </div>
                                                            <div class="d-flex align-items-center gap-2">
                                                                <a id="tax_certificate_download" href="#"
                                                                    class="btn btn-outline-secondary btn-sm"
                                                                    target="_blank" rel="noopener">
                                                                    {{ __('Download') }}
                                                                </a>
                                                                <button type="button"
                                                                    class="btn btn-light btn-sm certificate-change">
                                                                    {{ __('Change') }}
                                                                </button>
                                                            </div>
                                                        </div>
                                                        <div class="certificate-upload">
                                                            <input type="file" id="tax_certificate"
                                                                name="tax_certificate" class="form-control"
                                                                accept=".pdf,.jpg,.jpeg,.png">
                                                            <div class="invalid-feedback"
                                                                data-error-for="tax_certificate">
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="col-md-12">
                                                    <div class="mb-3 certificate-row"
                                                        data-cert="import_export_certificate">
                                                        <label
                                                            class="form-label">{{ __('Import Export Certificate') }}</label>
                                                        <div
                                                            class="certificate-existing d-flex align-items-center justify-content-between border rounded px-3 py-2 mb-2 d-none">
                                                            <div class="d-flex align-items-center flex-grow-1 me-3">
                                                                <i class="mdi mdi-file-outline text-muted me-2"></i>
                                                                <span id="import_export_certificate_name"
                                                                    class="text-truncate"></span>
                                                            </div>
                                                            <div class="d-flex align-items-center gap-2">
                                                                <a id="import_export_certificate_download" href="#"
                                                                    class="btn btn-outline-secondary btn-sm"
                                                                    target="_blank" rel="noopener">
                                                                    {{ __('Download') }}
                                                                </a>
                                                                <button type="button"
                                                                    class="btn btn-light btn-sm certificate-change">
                                                                    {{ __('Change') }}
                                                                </button>
                                                            </div>
                                                        </div>
                                                        <div class="certificate-upload">
                                                            <input type="file" id="import_export_certificate"
                                                                name="import_export_certificate" class="form-control"
                                                                accept=".pdf,.jpg,.jpeg,.png">
                                                            <div class="invalid-feedback"
                                                                data-error-for="import_export_certificate"></div>
                                                        </div>
                                                    </div>
                                                </div>

                                            </div>

                                            <div class="mt-4 d-flex justify-content-end gap-2">
                                                <button type="button" id="businessInfoReset"
                                                    class="btn btn-outline-secondary">
                                                    {{ __('Reset') }}
                                                </button>
                                                <button type="submit" id="businessInfoSave" class="btn btn-primary">
                                                    <span class="spinner-border spinner-border-sm me-2 d-none"
                                                        role="status" aria-hidden="true"></span>
                                                    <span class="btn-text">{{ __('Save Changes') }}</span>
                                                </button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>

                            <div class="accordion-item border-0 mb-3 shadow-sm rounded overflow-hidden">
                                <h2 class="accordion-header" id="headingLocation">
                                    <button class="accordion-button collapsed fw-bold py-3 px-4 bg-white" type="button" data-bs-toggle="collapse"
                                        data-bs-target="#collapseLocation" aria-expanded="false"
                                        aria-controls="collapseLocation">
                                        <i class="mdi mdi-map-marker-radius me-2 text-primary fs-5"></i>{{ __('Location') }}
                                    </button>
                                </h2>
                                <div id="collapseLocation" class="accordion-collapse collapse"
                                    aria-labelledby="headingLocation" data-bs-parent="#factoryBusinessAccordion">
                                    <div class="accordion-body p-4">
                                        <div id="locationSection">
                                            <div id="addressesLoading" class="text-center py-3 d-none">
                                                <div class="spinner-border text-primary" role="status">
                                                    <span class="visually-hidden">{{ __('Loading...') }}</span>
                                                </div>
                                            </div>

                                            <div id="locationFacilitySection" class="mb-4">
                                                <div class="d-flex justify-content-between align-items-center mb-2">
                                                    <h6 class="mb-0">{{ __('Facility Address') }}</h6>
                                                    <button type="button" id="addFacilityAddress"
                                                        class="btn btn-sm btn-outline-primary">
                                                        {{ __('+ Add More') }}
                                                    </button>
                                                </div>
                                                <div id="facilityAddressList" class="vstack gap-3"></div>
                                            </div>

                                            <div id="locationDistSection">
                                                <div class="d-flex justify-content-between align-items-center mb-2">
                                                    <h6 class="mb-0">{{ __('Distribution Center Address') }}</h6>
                                                    <button type="button" id="addDistAddress"
                                                        class="btn btn-sm btn-outline-primary">
                                                        {{ __('+ Add More') }}
                                                    </button>
                                                </div>
                                                <div id="distAddressList" class="vstack gap-3"></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="accordion-item border-0 mb-3 shadow-sm rounded overflow-hidden">
                                <h2 class="accordion-header" id="headingDeliveryPartner">
                                    <button class="accordion-button collapsed fw-bold py-3 px-4 bg-white" type="button" data-bs-toggle="collapse"
                                        data-bs-target="#collapseDeliveryPartner" aria-expanded="false"
                                        aria-controls="collapseDeliveryPartner">
                                        <i class="mdi mdi-truck-delivery me-2 text-primary fs-5"></i>{{ __('Delivery Partners') }}
                                    </button>
                                </h2>
                                <div id="collapseDeliveryPartner" class="accordion-collapse collapse"
                                    aria-labelledby="headingDeliveryPartner" data-bs-parent="#factoryBusinessAccordion">
                                    <div class="accordion-body p-4">
                                        <form id="deliveryPartnerForm">
                                            @csrf
                                            <input type="hidden" name="factory_id" id="dp_factory_id" value="{{ (int) $id }}">
                                            <div id="deliveryPartnerAlert" class="alert d-none" role="alert"></div>

                                            <div class="row g-3">
                                                <div class="col-md-12">
                                                    <h6 class="fw-semibold mb-3">{{ __('Delivery Partners') }}</h6>
                                                    <div id="shippingPartnersContainer" class="row g-2">
                                                        <div class="col-12">
                                                            <div class="placeholder-glow">
                                                                <span class="placeholder col-12"></span>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="invalid-feedback" data-error-for="shipping_partner_ids"></div>
                                                </div>

                                                <div class="col-md-12 mt-2">
                                                    <div class="d-flex align-items-center justify-content-between mb-2">
                                                        <h6 class="fw-semibold mb-0">{{ __('Myze API Credentials') }}</h6>
                                                        <span class="badge bg-light text-muted text-uppercase">{{ __('Admin Only') }}</span>
                                                    </div>
                                                    <p class="text-muted small mb-3">
                                                        {{ __('These values are stored in factory meta and used when dispatching orders via Myze.') }}
                                                    </p>
                                                    <div class="row g-3">
                                                        <div class="col-md-6">
                                                            <label for="myze_api_url" class="form-label">{{ __('Myze API URL') }}</label>
                                                            <input type="url" id="myze_api_url" name="myze_api_url" class="form-control" placeholder="https://api.myze.com" autocomplete="off">
                                                            <div class="invalid-feedback" data-error-for="myze_api_url"></div>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <label for="myze_api_token" class="form-label">{{ __('Myze API Token') }}</label>
                                                            <input type="password" id="myze_api_token" name="myze_api_token" class="form-control" placeholder="{{ __('Enter token') }}" autocomplete="off">
                                                            <div class="invalid-feedback" data-error-for="myze_api_token"></div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="mt-4 d-flex justify-content-end gap-2">
                                                <button type="submit" id="deliveryPartnerSave" class="btn btn-primary">
                                                    <span class="spinner-border spinner-border-sm me-2 d-none" role="status" aria-hidden="true"></span>
                                                    <span class="btn-text">{{ __('Save Changes') }}</span>
                                                </button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>

                            <div class="accordion-item border-0 mb-3 shadow-sm rounded overflow-hidden">
                                <h2 class="accordion-header" id="headingPrimaryContact">
                                    <button class="accordion-button collapsed fw-bold py-3 px-4 bg-white" type="button" data-bs-toggle="collapse"
                                        data-bs-target="#collapsePrimaryContact" aria-expanded="false"
                                        aria-controls="collapsePrimaryContact">
                                        <i class="mdi mdi-account-star me-2 text-primary fs-5"></i>{{ __('Primary Contact') }}
                                    </button>
                                </h2>
                                <div id="collapsePrimaryContact" class="accordion-collapse collapse"
                                    aria-labelledby="headingPrimaryContact" data-bs-parent="#factoryBusinessAccordion">
                                    <div class="accordion-body p-4">
                                        <form id="primaryContactForm">
                                            @csrf
                                            <input type="hidden" name="factory_id" value="{{ (int) $id }}">
                                            <div id="primaryContactAlert" class="alert d-none" role="alert"></div>
                                            <div class="row g-3">
                                                <div class="col-md-3">
                                                    <label for="primary_first_name"
                                                        class="form-label required">{{ __('First Name') }}</label>
                                                    <input type="text" id="primary_first_name" name="first_name"
                                                        class="form-control" required>
                                                    <div class="invalid-feedback" data-error-for="first_name"></div>
                                                </div>
                                                <div class="col-md-3">
                                                    <label for="primary_last_name"
                                                        class="form-label required">{{ __('Last Name') }}</label>
                                                    <input type="text" id="primary_last_name" name="last_name"
                                                        class="form-control" required>
                                                    <div class="invalid-feedback" data-error-for="last_name"></div>
                                                </div>
                                                <div class="col-md-3">
                                                    <label for="primary_email"
                                                        class="form-label required">{{ __('Email') }}</label>
                                                    <input type="email" id="primary_email" name="email"
                                                        class="form-control" required>
                                                    <div class="invalid-feedback" data-error-for="email"></div>
                                                </div>
                                                <div class="col-md-3">
                                                    <label for="primary_phone_number"
                                                        class="form-label required">{{ __('Phone') }}</label>
                                                    <input type="text" id="primary_phone_number" name="phone_number"
                                                        class="form-control" required>
                                                    <div class="invalid-feedback" data-error-for="phone_number"></div>
                                                </div>
                                            </div>
                                            <div class="mt-3 d-flex justify-content-end">
                                                <button type="submit" id="primaryContactSave"
                                                    class="btn btn-primary btn-sm">
                                                    <span class="spinner-border spinner-border-sm me-2 d-none"
                                                        role="status" aria-hidden="true"></span>
                                                    <span class="btn-text">{{ __('Save Changes') }}</span>
                                                </button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>

                            <div class="accordion-item border-0 mb-3 shadow-sm rounded overflow-hidden">
                                <h2 class="accordion-header" id="headingSecondaryContact">
                                    <button class="accordion-button collapsed fw-bold py-3 px-4 bg-white" type="button" data-bs-toggle="collapse"
                                        data-bs-target="#collapseSecondaryContact" aria-expanded="false"
                                        aria-controls="collapseSecondaryContact">
                                        <i class="mdi mdi-account-multiple me-2 text-primary fs-5"></i>{{ __('Secondary Contact') }}
                                    </button>
                                </h2>
                                <div id="collapseSecondaryContact" class="accordion-collapse collapse"
                                    aria-labelledby="headingSecondaryContact" data-bs-parent="#factoryBusinessAccordion">
                                    <div class="accordion-body p-4">
                                        <form id="secondaryContactForm">
                                            @csrf
                                            <input type="hidden" name="factory_id" value="{{ (int) $id }}">
                                            <div id="secondaryContactAlert" class="alert d-none" role="alert"></div>
                                            <div class="row g-3">
                                                <div class="col-md-3">
                                                    <label for="secondary_first_name"
                                                        class="form-label required">{{ __('First Name') }}</label>
                                                    <input type="text" id="secondary_first_name" name="first_name"
                                                        class="form-control" required>
                                                    <div class="invalid-feedback" data-error-for="first_name"></div>
                                                </div>
                                                <div class="col-md-3">
                                                    <label for="secondary_last_name"
                                                        class="form-label required">{{ __('Last Name') }}</label>
                                                    <input type="text" id="secondary_last_name" name="last_name"
                                                        class="form-control" required>
                                                    <div class="invalid-feedback" data-error-for="last_name"></div>
                                                </div>
                                                <div class="col-md-3">
                                                    <label for="secondary_email"
                                                        class="form-label">{{ __('Email') }}</label>
                                                    <input type="email" id="secondary_email" name="email"
                                                        class="form-control">
                                                    <div class="invalid-feedback" data-error-for="email"></div>
                                                </div>
                                                <div class="col-md-3">
                                                    <label for="secondary_phone_number"
                                                        class="form-label required">{{ __('Phone') }}</label>
                                                    <input type="text" id="secondary_phone_number" name="phone_number"
                                                        class="form-control" required>
                                                    <div class="invalid-feedback" data-error-for="phone_number"></div>
                                                </div>
                                            </div>
                                            <div class="mt-3 d-flex justify-content-end">
                                                <button type="submit" id="secondaryContactSave"
                                                    class="btn btn-primary btn-sm">
                                                    <span class="spinner-border spinner-border-sm me-2 d-none"
                                                        role="status" aria-hidden="true"></span>
                                                    <span class="btn-text">{{ __('Save Changes') }}</span>
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

    <!-- Status Update Modal -->
    @include('admin.factory.modals._status-update-modal', ['factoryId' => $id])

@endsection
@section('js')
    <script src="{{ asset('assets/js/auth-helpers.js') }}"></script>
    <script>
        window.FactoryBusinessConfig = {
            factoryId: @json((int) $id),
            csrfToken: "{{ csrf_token() }}",
            routes: {
                businessInfoShow: "{{ route('factory.business-information.show') }}",
                businessInfoStore: "{{ route('factory.business-information.store') }}",
                adminFactoryShow: "{{ url('/api/v1/admin/factories') }}/:id",
                countries: "{{ route('location.countries.index') }}",
                states: "{{ route('location.states.index', ':country') }}",
                addressesIndex: "{{ route('factory.addresses.index') }}",
                addressesStore: "{{ route('factory.addresses.store') }}",
                addressesUpdate: "{{ route('factory.addresses.update', ':id') }}",
                addressesDestroy: "{{ route('factory.addresses.destroy', ':id') }}",
                accountUpdate: "{{ route('factory.account.update') }}",
                secondaryContactShow: "{{ route('factory.secondary-contact.show') }}",
                secondaryContactStore: "{{ route('factory.secondary-contact.store') }}",
                statusOptions: "{{ url('/api/v1/admin/factories-status/statuses') }}",
                completeness: "{{ url('/api/v1/admin/factories-status/:id/completeness') }}",
                updateStatus: "{{ url('/api/v1/admin/factories-status/:id/update') }}",
                shippingPartners: "{{ url('/api/v1/admin/shipping/partners') }}",
                shippingPartnerUpdate: "{{ route('factory.shipping-partner.update') }}"
            }
        };
    </script>
    <script src="{{ asset('assets/js/pages/factory/business-information.js') }}"></script>
    <script src="{{ asset('assets/js/pages/factory/factory-status-management.js') }}"></script>
@endsection
