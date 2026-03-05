<!-- Status Update Modal - Reusable Partial -->
<div class="modal fade" id="statusUpdateModal" tabindex="-1" aria-labelledby="statusUpdateModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content shadow-lg">
            <div class="modal-header bg-light border-bottom py-3">
                <div class="flex-grow-1">
                    <h5 class="modal-title fw-bold" id="statusUpdateModalLabel">
                        <i class="mdi mdi-shield-edit-outline me-2 text-primary"></i>{{ __('Update Factory Status') }}
                    </h5>
                    <small class="text-muted d-flex align-items-center mt-1">
                        <i class="mdi mdi-email-outline me-1"></i><span id="factoryEmailDisplay"></span>
                    </small>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <!-- Main Alert -->
                <div id="statusAlert" class="alert d-none" role="alert"></div>

                <!-- Completeness Warning Alert -->
                <div id="completenessAlert" class="alert alert-warning d-none border-start border-5 border-warning"
                    role="alert">
                    <div class="d-flex align-items-start">
                        <i class="mdi mdi-alert-circle me-3 fs-5 text-warning flex-shrink-0 mt-1"></i>
                        <div class="flex-grow-1">
                            <strong class="d-block mb-2">{{ __('Profile Incomplete') }}</strong>
                            <p class="mb-0 text-muted">
                                {{ __('Factory profile is incomplete. Please complete all required fields before updating status.') }}
                            </p>
                            <ul id="missingFieldsList" class="mb-0 mt-2 ps-0" style="list-style: none;"></ul>
                        </div>
                    </div>
                </div>

                <form id="statusUpdateForm">
                    @if (isset($factoryId))
                        <input type="hidden" id="statusFactoryId" name="factory_id" value="{{ (int) $factoryId }}">
                    @else
                        <input type="hidden" id="statusFactoryId" name="factory_id">
                    @endif

                    <!-- Account & Verification Status Section -->
                    <div class="card mb-3 border-0 shadow-sm">
                        <div class="card-header bg-light border-bottom border-secondary">
                            <h6 class="mb-0">
                                <i
                                    class="mdi mdi-account-settings me-2 text-primary"></i>{{ __('Account & Verification Status') }}
                            </h6>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="statusAccountStatus" class="form-label fw-semibold">
                                        <i class="mdi mdi-account me-1 text-info"></i>{{ __('Account Status') }}
                                    </label>
                                    <select class="form-select" id="statusAccountStatus"
                                        name="account_status">
                                        <option value="">{{ __('-- No Change --') }}</option>
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="statusVerificationStatus" class="form-label fw-semibold">
                                        <i
                                            class="mdi mdi-shield-check me-1 text-success"></i>{{ __('Verification Status') }}
                                    </label>
                                    <select class="form-select" id="statusVerificationStatus"
                                        name="account_verified">
                                        <option value="">{{ __('-- No Change --') }}</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Email Verification Section -->
                    <div class="card mb-3 border-0 shadow-sm">
                        <div class="card-header bg-light border-bottom border-secondary">
                            <h6 class="mb-0">
                                <i class="mdi mdi-email-check me-2 text-warning"></i>{{ __('Email Verification') }}
                            </h6>
                        </div>
                        <div class="card-body">
                            <div class="form-check form-switch">
                                <input class="form-check-input form-check-input-lg" type="checkbox"
                                    id="verifyEmailCheckbox" name="verify_email">
                                <label class="form-check-label ps-2" for="verifyEmailCheckbox">
                                    <span class="fw-semibold">{{ __('Mark email as verified') }}</span>
                                    <small class="d-block text-muted mt-1">
                                        {{ __('Admin verification of factory email address') }}
                                    </small>
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- Validation Section -->
                    <div class="card mb-3 border-0 shadow-sm">
                        <div class="card-header bg-light border-bottom border-secondary">
                            <h6 class="mb-0">
                                <i class="mdi mdi-clipboard-check me-2 text-danger"></i>{{ __('Validation ') }}
                            </h6>
                        </div>
                        <div class="card-body">
                            <div class="form-check form-switch">
                                <input class="form-check-input form-check-input-lg" type="checkbox"
                                    id="checkCompleteness" name="check_completeness">
                                <label class="form-check-label ps-2" for="checkCompleteness">
                                    <span class="fw-semibold">{{ __('Require complete profile') }}</span>
                                    <small class="d-block text-muted mt-1">
                                        {{ __('Profile must have all required information before status update') }}
                                    </small>
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- Reason Section -->
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-light border-bottom border-secondary">
                            <h6 class="mb-0">
                                <i
                                    class="mdi mdi-note-text me-2 text-secondary"></i>{{ __('Additional Information') }}
                            </h6>
                        </div>
                        <div class="card-body">
                            <label for="statusReason"
                                class="form-label fw-semibold">{{ __('Reason for Change') }}</label>
                            <textarea class="form-control" id="statusReason" name="reason" rows="3"
                                placeholder="{{ __('Optional: Enter reason for status change') }}"></textarea>
                            <small class="text-muted d-block mt-1">
                                <i class="mdi mdi-information-outline me-1"></i>{{ __('Max 500 characters') }}
                            </small>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer bg-light border-top py-3">
                <button type="button" class="btn btn-outline-secondary d-flex align-items-center gap-2" data-bs-dismiss="modal">
                    <i class="mdi mdi-close"></i>{{ __('Cancel') }}
                </button>
                <button type="button" class="btn btn-primary d-flex align-items-center gap-2" id="updateStatusBtn">
                    <span class="spinner-border spinner-border-sm d-none" role="status"
                        aria-hidden="true"></span>
                    <i class="mdi mdi-check-circle-outline btn-icon"></i><span class="btn-text">{{ __('Update Status') }}</span>
                </button>
            </div>
        </div>
    </div>
</div>
