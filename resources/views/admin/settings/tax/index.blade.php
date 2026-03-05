@extends('admin.layouts.app')

@section('title', __('Tax Settings'))

@section('content')
<div class="page-breadcrumb">
    <div class="row">
        <div class="col-12 d-flex align-items-center justify-content-between">
            <h4 class="page-title">{{ __('Tax Settings') }}</h4>
            <div class="ms-auto">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">{{ __('Home') }}</a></li>
                        <li class="breadcrumb-item active" aria-current="page">{{ __('Tax Settings') }}</li>
                    </ol>
                </nav>
            </div>
        </div>
    </div>
</div>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <x-alerts />
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-bottom">
                    <ul class="nav nav-tabs card-header-tabs" id="taxTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="taxes-tab" data-bs-toggle="tab" data-bs-target="#taxes" type="button" role="tab">{{ __('Taxes') }}</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="zones-tab" data-bs-toggle="tab" data-bs-target="#zones" type="button" role="tab">{{ __('Zones') }}</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="rules-tab" data-bs-toggle="tab" data-bs-target="#rules" type="button" role="tab">{{ __('Rules') }}</button>
                        </li>
                    </ul>
                </div>
                <div class="card-body">
                    <div class="tab-content" id="taxTabsContent">
                        
                        <!-- ================== TAXES TAB ================== -->
                        <div class="tab-pane fade show active" id="taxes" role="tabpanel">
                            <!-- Filters & Actions -->
                            <div class="row mb-3">
                                <div class="col-lg-3">
                                    <input type="text" id="tax-search" class="form-control" placeholder="{{ __('Search by name or code') }}">
                                </div>
                                <div class="col-lg-3">
                                    <select id="tax-status-filter" class="form-select">
                                        <option value="">{{ __('All Status') }}</option>
                                        <option value="1">{{ __('Active') }}</option>
                                        <option value="0">{{ __('Inactive') }}</option>
                                    </select>
                                </div>
                                <div class="col-lg-3 d-flex gap-2">
                                    <button id="tax-apply-filter" class="btn btn-primary w-100">
                                        {{ __('Apply') }}
                                    </button>
                                    <button id="tax-reset-filter" class="btn btn-outline-secondary w-100">
                                        {{ __('Reset') }}
                                    </button>
                                </div>
                                <div class="col-lg-3 d-flex justify-content-end align-items-center">
                                    <button class="btn btn-primary" onclick="window.taxManager.openTaxModal()">
                                        <i class="fas fa-plus"></i> {{ __('Add Tax') }}
                                    </button>
                                </div>
                            </div>
                            
                            <!-- Bulk Actions -->
                            <div class="row mb-3">
                                <div class="col-lg-6 d-flex align-items-center">
                                    <select id="tax-bulk-action" class="form-select me-2" style="width: 200px;">
                                        <option value="">{{ __('Bulk Actions') }}</option>
                                        <option value="enable">{{ __('Enable') }}</option>
                                        <option value="disable">{{ __('Disable') }}</option>
                                        <option value="delete">{{ __('Delete') }}</option>
                                    </select>
                                    <button id="tax-apply-bulk" class="btn btn-primary" disabled>
                                        {{ __('Apply') }}
                                    </button>
                                </div>
                            </div>

                            <div class="table-responsive">
                                <table class="table table-bordered table-hover" id="taxesTable" style="width:100%">
                                    <thead class="table-light">
                                        <tr>
                                            <th class="text-center" style="width: 40px;">
                                                <input type="checkbox" class="form-check-input" id="tax-check-all">
                                            </th>
                                            <th>{{ __('ID') }}</th>
                                            <th>{{ __('Name') }}</th>
                                            <th>{{ __('Code') }}</th>
                                            <th>{{ __('Status') }}</th>
                                            <th style="width: 150px;">{{ __('Actions') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody></tbody>
                                </table>
                            </div>
                        </div>

                        <!-- ================== ZONES TAB ================== -->
                        <div class="tab-pane fade" id="zones" role="tabpanel">
                            <div class="row mb-3">
                                <div class="col-lg-3">
                                    <input type="text" id="zone-search" class="form-control" placeholder="{{ __('Search zones') }}">
                                </div>
                                <div class="col-lg-3">
                                    <select id="zone-status-filter" class="form-select">
                                        <option value="">{{ __('All Status') }}</option>
                                        <option value="1">{{ __('Active') }}</option>
                                        <option value="0">{{ __('Inactive') }}</option>
                                    </select>
                                </div>
                                <div class="col-lg-3 d-flex gap-2">
                                    <button id="zone-apply-filter" class="btn btn-primary w-100">
                                        {{ __('Apply') }}
                                    </button>
                                    <button id="zone-reset-filter" class="btn btn-outline-secondary w-100">
                                        {{ __('Reset') }}
                                    </button>
                                </div>
                                <div class="col-lg-3 d-flex justify-content-end align-items-center">
                                    <button class="btn btn-primary" onclick="window.taxManager.openZoneModal()">
                                        <i class="fas fa-plus"></i> {{ __('Add Zone') }}
                                    </button>
                                </div>
                            </div>

                             <div class="row mb-3">
                                <div class="col-lg-6 d-flex align-items-center">
                                    <select id="zone-bulk-action" class="form-select me-2" style="width: 200px;">
                                        <option value="">{{ __('Bulk Actions') }}</option>
                                        <option value="enable">{{ __('Enable') }}</option>
                                        <option value="disable">{{ __('Disable') }}</option>
                                        <option value="delete">{{ __('Delete') }}</option>
                                    </select>
                                    <button id="zone-apply-bulk" class="btn btn-primary" disabled>
                                        {{ __('Apply') }}
                                    </button>
                                </div>
                            </div>

                            <div class="table-responsive">
                                <table class="table table-bordered table-hover" id="zonesTable" style="width:100%">
                                    <thead class="table-light">
                                        <tr>
                                            <th class="text-center" style="width: 40px;">
                                                <input type="checkbox" class="form-check-input" id="zone-check-all">
                                            </th>
                                            <th>{{ __('ID') }}</th>
                                            <th>{{ __('Name') }}</th>
                                            <th>{{ __('Country') }}</th>
                                            <th>{{ __('State Code') }}</th>
                                            <th>{{ __('Zip Range') }}</th>
                                            <th>{{ __('Status') }}</th>
                                            <th style="width: 150px;">{{ __('Actions') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody></tbody>
                                </table>
                            </div>
                        </div>

                        <!-- ================== RULES TAB ================== -->
                        <div class="tab-pane fade" id="rules" role="tabpanel">
                            <div class="row mb-3">
                                <div class="col-lg-3">
                                    <input type="text" id="rule-search" class="form-control" placeholder="{{ __('Search rules') }}">
                                </div>
                                <div class="col-lg-3">
                                    <select id="rule-status-filter" class="form-select">
                                        <option value="">{{ __('All Status') }}</option>
                                        <option value="1">{{ __('Active') }}</option>
                                        <option value="0">{{ __('Inactive') }}</option>
                                    </select>
                                </div>
                                <div class="col-lg-3 d-flex gap-2">
                                    <button id="rule-apply-filter" class="btn btn-primary w-100">
                                        {{ __('Apply') }}
                                    </button>
                                    <button id="rule-reset-filter" class="btn btn-outline-secondary w-100">
                                        {{ __('Reset') }}
                                    </button>
                                </div>
                                <div class="col-lg-3 d-flex justify-content-end align-items-center">
                                    <button class="btn btn-primary" onclick="window.taxManager.openRuleModal()">
                                        <i class="fas fa-plus"></i> {{ __('Add Rule') }}
                                    </button>
                                </div>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-lg-6 d-flex align-items-center">
                                    <select id="rule-bulk-action" class="form-select me-2" style="width: 200px;">
                                        <option value="">{{ __('Bulk Actions') }}</option>
                                        <option value="enable">{{ __('Enable') }}</option>
                                        <option value="disable">{{ __('Disable') }}</option>
                                        <option value="delete">{{ __('Delete') }}</option>
                                    </select>
                                    <button id="rule-apply-bulk" class="btn btn-primary" disabled>
                                        {{ __('Apply') }}
                                    </button>
                                </div>
                            </div>

                            <div class="table-responsive">
                                <table class="table table-bordered table-hover" id="rulesTable" style="width:100%">
                                    <thead class="table-light">
                                        <tr>
                                            <th class="text-center" style="width: 40px;">
                                                <input type="checkbox" class="form-check-input" id="rule-check-all">
                                            </th>
                                            <th>{{ __('ID') }}</th>
                                            <th>{{ __('Tax') }}</th>
                                            <th>{{ __('Zone') }}</th>
                                            <th>{{ __('Rate (%)') }}</th>
                                            <th>{{ __('Priority') }}</th>
                                            <th>{{ __('Status') }}</th>
                                            <th style="width: 150px;">{{ __('Actions') }}</th>
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
    </div>
</div>

<!-- ================== MODALS ================== -->

<!-- Tax Modal -->
<div class="modal fade" id="taxModal" tabindex="-1">
    <div class="modal-dialog">
        <form id="taxForm" onsubmit="return false">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add/Edit Tax</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="tax_id" name="id">
                    <div class="mb-3">
                        <label class="form-label">Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="name" id="tax_name" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Code <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="code" id="tax_code" required placeholder="e.g. VAT, GST">
                    </div>
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" name="status" id="tax_status" value="1" checked>
                        <label class="form-check-label" for="tax_status">Active</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary" id="btnSaveTax">Save</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Zone Modal -->
<div class="modal fade" id="zoneModal" tabindex="-1">
    <div class="modal-dialog">
        <form id="zoneForm" onsubmit="return false">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add/Edit Zone</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="zone_id" name="id">
                    <div class="mb-3">
                        <label class="form-label">Zone Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="name" id="zone_name" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Country <span class="text-danger">*</span></label>
                        <select class="form-select" name="country_id" id="zone_country_id" required>
                            <option value="">-- Select Country --</option>
                            <!-- Populated via JS -->
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">State (Optional)</label>
                        <select class="form-select" name="state_code" id="zone_state_code">
                            <option value="">-- Select State --</option>
                            <!-- Populated via JS -->
                        </select>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Zip Start (Optional)</label>
                            <input type="text" class="form-control" name="postal_code_start" id="zone_zip_start">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Zip End (Optional)</label>
                            <input type="text" class="form-control" name="postal_code_end" id="zone_zip_end">
                        </div>
                    </div>
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" name="status" id="zone_status" value="1" checked>
                        <label class="form-check-label" for="zone_status">Active</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary" id="btnSaveZone">Save</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Rule Modal -->
<div class="modal fade" id="ruleModal" tabindex="-1">
    <div class="modal-dialog">
        <form id="ruleForm" onsubmit="return false">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add/Edit Rule</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="rule_id" name="id">
                    
                    <div class="mb-3">
                        <label class="form-label">Tax <span class="text-danger">*</span></label>
                        <select class="form-select" name="tax_id" id="rule_tax_id" required>
                            <option value="">-- Select Tax --</option>
                            <!-- Populated via JS -->
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Zone <span class="text-danger">*</span></label>
                        <select class="form-select" name="tax_zone_id" id="rule_zone_id" required>
                            <option value="">-- Select Zone --</option>
                             <!-- Populated via JS -->
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Rate (%) <span class="text-danger">*</span></label>
                        <input type="number" step="0.01" class="form-control" name="rate" id="rule_rate" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Priority <span class="text-danger">*</span></label>
                        <input type="number" class="form-control" name="priority" id="rule_priority" value="1" required>
                        <small class="text-muted">Higher number = higher priority</small>
                    </div>

                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" name="status" id="rule_status" value="1" checked>
                        <label class="form-check-label" for="rule_status">Active</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary" id="btnSaveRule">Save</button>
                </div>
            </div>
        </form>
    </div>
</div>

@endsection

@section('js')
@push('scripts')
<script src="{{ asset('assets/js/pages/settings/tax/index.js') }}"></script>
<script>
    $(document).ready(function() {
        window.taxManager = new TaxManager({
            csrfToken: $('meta[name="csrf-token"]').attr('content'),
            routes: {
                taxes: {
                    data: "{{ route('admin.settings.tax.data') }}",
                    store: "{{ route('admin.settings.tax.store') }}",
                    bulk: "{{ route('admin.settings.tax.bulk-action') }}",
                    update: "{{ route('admin.settings.tax.update', ':id') }}",
                    delete: "{{ route('admin.settings.tax.delete', ':id') }}"
                },
                zones: {
                    index: "{{ route('admin.settings.tax.zones.index') }}",
                    store: "{{ route('admin.settings.tax.zones.store') }}",
                    bulk: "{{ route('admin.settings.tax.zones.bulk-action') }}",
                    update: "{{ route('admin.settings.tax.zones.update', ':id') }}",
                    delete: "{{ route('admin.settings.tax.zones.delete', ':id') }}"
                },
                rules: {
                    index: "{{ route('admin.settings.tax.rules.index') }}",
                    store: "{{ route('admin.settings.tax.rules.store') }}",
                    bulk: "{{ route('admin.settings.tax.rules.bulk-action') }}",
                    update: "{{ route('admin.settings.tax.rules.update', ':id') }}",
                    delete: "{{ route('admin.settings.tax.rules.delete', ':id') }}"
                },
                location: {
                    countries: "/api/v1/location/countries",
                    states: "/api/v1/location/countries/:id/states"
                }
            },
            translations: {
                select_action: "{{ __('Please select an action') }}",
                select_item: "{{ __('Please select at least one item') }}",
                are_you_sure: "{{ __('Are you sure?') }}",
                bulk_action_confirm: "{{ __('This action will affect selected items.') }}",
                yes_proceed: "{{ __('Yes, proceed!') }}",
                cannot_revert: "{{ __('You won\'t be able to revert this!') }}",
                yes_delete: "{{ __('Yes, delete it!') }}",
                bulk_not_configured: "{{ __('Bulk action not configured.') }}",
                validation_error: "{{ __('Validation Error') }}",
                error: "{{ __('Error') }}",
                generic_error: "{{ __('An error occurred.') }}",
                select_tax: "{{ __('-- Select Tax --') }}",
                select_zone: "{{ __('-- Select Zone --') }}",
                select_country: "{{ __('-- Select Country --') }}",
                loading: "{{ __('Loading...') }}",
                select_state: "{{ __('-- Select State --') }}"
            }
        });
    });
</script>
@endsection
