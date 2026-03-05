@extends('admin.layouts.app')

@section('title', __('Shipping Partners'))

@section('content')
    <div class="page-breadcrumb mb-3">
        <div class="row align-items-center">
            <div class="col-md-6">
                <h4 class="page-title mb-0">{{ __('Shipping Partners') }}</h4>
                <small class="text-muted">
                    {{ __('Manage external shipping and tracking integrations.') }}
                </small>
            </div>
            <div class="col-md-6 text-end">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb justify-content-end mb-0">
                        <li class="breadcrumb-item">
                            <a href="{{ route('admin.dashboard') }}">{{ __('Home') }}</a>
                        </li>
                        <li class="breadcrumb-item">
                            <a href="{{ route('admin.settings.general.web') }}">{{ __('Settings') }}</a>
                        </li>
                        <li class="breadcrumb-item active">{{ __('Shipping Partners') }}</li>
                    </ol>
                </nav>
            </div>
        </div>
    </div>

    <div class="container-fluid">
        <div class="card">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0">{{ __('Configured Partners') }}</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-striped mb-0">
                        <thead>
                            <tr>
                                <th>{{ __('Logo') }}</th>
                                <th>{{ __('Name') }}</th>
                                <th>{{ __('Code') }}</th>
                                <th>{{ __('Type') }}</th>
                                <th>{{ __('Base URL') }}</th>
                                <th>{{ __('Enabled') }}</th>
                                <th class="text-end">{{ __('Actions') }}</th>
                            </tr>
                        </thead>
                        <tbody id="shipping-partners-body">
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="shipping-partner-modal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="shipping-partner-modal-title">{{ __('Edit Shipping Partner') }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <span id="partner-sync-info" class="text-muted small"></span>
                    </div>
                    <form id="shipping-partner-form">
                        <input type="hidden" id="partner-id">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">{{ __('Name') }}</label>
                                <input type="text" class="form-control" id="partner-name" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">{{ __('Code') }}</label>
                                <input type="text" class="form-control" id="partner-code" required>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">{{ __('Type') }}</label>
                                <select class="form-select" id="partner-type" required>
                                    <option value="shipping">{{ __('Shipping only') }}</option>
                                    <option value="tracking">{{ __('Tracking only') }}</option>
                                    <option value="both">{{ __('Shipping + Tracking') }}</option>
                                </select>
                            </div>
                            <div class="col-md-8 mb-3">
                                <label class="form-label">{{ __('API Base URL') }}</label>
                                <input type="text" class="form-control" id="partner-api-base-url">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">{{ __('App ID / Account ID') }}</label>
                                <input type="text" class="form-control" id="partner-app-id">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">{{ __('API Key') }}</label>
                                <input type="text" class="form-control" id="partner-api-key">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">{{ __('Logo URL / Path') }}</label>
                                <input type="text" class="form-control" id="partner-logo">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">{{ __('API Secret') }}</label>
                                <input type="text" class="form-control" id="partner-api-secret">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">{{ __('Webhook Secret') }}</label>
                                <input type="password" class="form-control" id="partner-webhook-secret" autocomplete="new-password">
                            </div>
                        </div>
                        <hr>
                        <h6>{{ __('Extended Settings') }}</h6>
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">{{ __('Carrier ID') }}</label>
                                <input type="text" class="form-control" id="partner-settings-carrier-id">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">{{ __('Carrier Code') }}</label>
                                <input type="text" class="form-control" id="partner-settings-carrier-code">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">{{ __('Service Code') }}</label>
                                <input type="text" class="form-control" id="partner-settings-service-code">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="partner-enabled">
                                    <label class="form-check-label" for="partner-enabled">{{ __('Enabled') }}</label>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                    <button type="button" class="btn btn-primary"
                        id="shipping-partner-save-btn">{{ __('Save changes') }}</button>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('js')
    <script>
        window.ShippingPartnerConfig = {
            urls: {
                index: "{{ route('admin.settings.shipping-partners') }}",
                api_list: "{{ route('admin.shipping.partners.index') }}",
                api_update: "{{ route('admin.shipping.partners.update', ['partner' => ':id']) }}",
            },
            translations: {
                failed_to_load: "{{ __('Failed to load shipping partners') }}",
                failed_to_update: "{{ __('Failed to update shipping partner') }}",
                updated: "{{ __('Shipping partner updated successfully') }}",
            }
        };
    </script>
    <script src="{{ asset('assets/js/pages/admin/settings/shipping/partners.js') }}"></script>
@endsection