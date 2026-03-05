/**
 * Factory Status Management - Business Information Page Handler
 * Handles status update operations specifically for the business details view
 */

(function () {
    'use strict';

    // ==================== Utility Functions ====================

    /**
     * Safely escape HTML special characters for XSS prevention
     * @param {string} str - String to escape
     * @returns {string} Escaped string safe for HTML insertion
     */
    function escapeHtml(str) {
        if (!str) return '';
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    // Status Options Cache
    let statusOptions = {
        accountStatus: [],
        verificationStatus: []
    };

    // Configuration
    let config = null;
    let isBusinessPage = false;
    let currentFactoryData = null;

    // Field name mapping for human-readable display
    const fieldLabels = {
        'basic_info.first_name': { section: 'Basic Information', label: 'First Name' },
        'basic_info.last_name': { section: 'Basic Information', label: 'Last Name' },
        'basic_info.email': { section: 'Basic Information', label: 'Email Address' },
        'basic_info.phone_number': { section: 'Basic Information', label: 'Phone Number' },
        'basic_info.email_verified': { section: 'Basic Information', label: 'Email Verification' },
        'business_info.not_provided': { section: 'Business Information', label: 'Business Information is Missing' },
        'business_info.company_name': { section: 'Business Information', label: 'Company Name' },
        'business_info.registration_number': { section: 'Business Information', label: 'Registration Number' },
        'business_info.tax_vat_number': { section: 'Business Information', label: 'Tax/VAT Number' },
        'business_info.registered_address': { section: 'Business Information', label: 'Registered Address' },
        'business_info.country_id': { section: 'Business Information', label: 'Country' },
        'business_info.state_id': { section: 'Business Information', label: 'State/Province' },
        'business_info.city': { section: 'Business Information', label: 'City' },
        'business_info.postal_code': { section: 'Business Information', label: 'Postal Code' },
        'location.not_provided': { section: 'Location', label: 'At least one Facility or Distribution Center Address is required' },
        'industries.not_assigned': { section: 'Industries', label: 'At least one industry must be assigned' }
    };

    /**
     * Initialize the module
     */
    function initialize() {
        // Detect config
        if (window.FactoryBusinessConfig) {
            config = window.FactoryBusinessConfig;
            isBusinessPage = true;
        } else {
            console.error('Factory Status Management: No FactoryBusinessConfig found');
            return;
        }

        // Handler to run on DOM ready
        const setupModule = function () {
            attachEventListeners();

            loadStatusOptions()
                .finally(() => {
                    loadCurrentFactoryStatus();
                    checkFactoryCompleteness();
                });
        };

        // Check if DOM is already loaded
        if (document.readyState === 'interactive' || document.readyState === 'complete') {
            setupModule();
        } else {
            // Otherwise wait for DOMContentLoaded
            document.addEventListener('DOMContentLoaded', setupModule);
        }
    }

    /**
     * Load available status options from API
     * Returns a promise so callers can wait before using statusOptions.
     */
    function loadStatusOptions() {
        const url = config.routes.statusOptions;

        return fetch(url, {
            method: 'GET',
            headers: {
                'Authorization': `Bearer ${getJWTToken('jwt_token')}`,
                'Accept': 'application/json'
            }
        })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`Failed to load status options: HTTP ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                if (data.data) {
                    statusOptions = {
                        accountStatus: Array.isArray(data.data.accountStatus) ? data.data.accountStatus :
                            Array.isArray(data.data.account_statuses) ? data.data.account_statuses : [],
                        verificationStatus: Array.isArray(data.data.verificationStatus) ? data.data.verificationStatus :
                            Array.isArray(data.data.verification_statuses) ? data.data.verification_statuses : []
                    };
                    populateStatusSelects();
                }
                return statusOptions;
            })
            .catch(error => {
                console.error('Error loading status options:', error);
                showStatusAlert('Unable to load status options', 'error');
                return statusOptions;
            });
    }

    /**
     * Populate status select dropdowns
     */
    function populateStatusSelects() {
        const accountStatusSelect = document.getElementById('statusAccountStatus');
        const verificationSelect = document.getElementById('statusVerificationStatus');

        // Helper to populate a select with metadata
        const populate = (select, options) => {
            if (!select) return;
            select.innerHTML = '<option value="">-- No Change --</option>';

            if (!options || !Array.isArray(options) || options.length === 0) return;

            // Sort options by value (numeric)
            const sortedOptions = [...options].sort((a, b) => parseInt(a.value) - parseInt(b.value));

            sortedOptions.forEach(status => {
                const option = document.createElement('option');
                option.value = status.value.toString(); // Ensure value is a string
                option.textContent = status.label;
                option.title = status.description || '';
                select.appendChild(option);
            });
        };

        populate(accountStatusSelect, statusOptions.accountStatus);
        populate(verificationSelect, statusOptions.verificationStatus);
    }

    /**
     * Attach event listeners
     */
    function attachEventListeners() {
        // Update Status Button
        const updateStatusBtn = document.getElementById('updateStatusBtn');
        if (updateStatusBtn) {
            updateStatusBtn.addEventListener('click', updateStatus);
        }

        // Check Completeness Checkbox
        const checkCompletenessCheckbox = document.getElementById('checkCompleteness');
        if (checkCompletenessCheckbox) {
            checkCompletenessCheckbox.addEventListener('change', function () {
                if (this.checked) {
                    checkFactoryCompleteness();
                } else {
                    const completenessAlert = document.getElementById('completenessAlert');
                    if (completenessAlert) {
                        completenessAlert.classList.add('d-none');
                    }
                }
            });
        }

        // Modal events
        const modal = document.getElementById('statusUpdateModal');
        if (modal) {
            modal.addEventListener('hidden.bs.modal', function () {
                resetForm();
            });
        }
    }


    function loadCurrentFactoryStatus() {
        const id = config.factoryId;

        if (!id) {
            console.error('Factory ID is missing');
            return;
        }

        const url = config.routes.adminFactoryShow.replace(':id', id);

        fetch(url, {
            method: 'GET',
            headers: {
                'Authorization': `Bearer ${getJWTToken('jwt_token')}`,
                'Accept': 'application/json'
            }
        })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Failed to load factory data');
                }
                return response.json();
            })
            .then(data => {
                if (data.data) {
                    currentFactoryData = data.data;

                    // Display factory email in modal header
                    const emailDisplay = document.getElementById('factoryEmailDisplay');
                    if (emailDisplay && currentFactoryData.email) {
                        emailDisplay.textContent = currentFactoryData.email;
                    }

                    // Extract current status values
                    const accountStatusValue = typeof currentFactoryData.account_status === 'object'
                        ? currentFactoryData.account_status?.value
                        : currentFactoryData.account_status;
                    const verificationStatusValue = typeof currentFactoryData.account_verified === 'object'
                        ? currentFactoryData.account_verified?.value
                        : currentFactoryData.account_verified;

                    // Update current status badges for business page specifically
                    updateStatusBadges(accountStatusValue, verificationStatusValue);

                    // Set the form selects to current values
                    const accountSelect = document.getElementById('statusAccountStatus');
                    const verificationSelect = document.getElementById('statusVerificationStatus');

                    if (accountSelect) {
                        accountSelect.value = (accountStatusValue !== null && accountStatusValue !== undefined) ? accountStatusValue.toString() : '';
                    }
                    if (verificationSelect) {
                        verificationSelect.value = (verificationStatusValue !== null && verificationStatusValue !== undefined) ? verificationStatusValue.toString() : '';
                    }

                    // Set email verification checkbox based on current status
                    const isEmailVerified = currentFactoryData.is_email_verified || (currentFactoryData.email_verified_at !== null);
                    document.getElementById('verifyEmailCheckbox').checked = isEmailVerified;
                }
            })
            .catch(error => {
                console.error('Error loading factory status:', error);
                // Continue anyway - the form can still be used
            });
    }

    /**
     * Update status badges with current values (styled for the Business Page)
     */
    function updateStatusBadges(accountStatusValue, verificationStatusValue) {

        const accountBadgeContainer = document.getElementById('currentAccountStatusBadge');
        const verificationBadgeContainer = document.getElementById('currentVerificationBadge');

        if (accountBadgeContainer && statusOptions.accountStatus) {
            const status = statusOptions.accountStatus.find(s => s.value == accountStatusValue);
            if (status) {
                const colorClass = `bg-soft-${status.color} text-${status.color}`;
                const icon = status.icon || 'mdi-account-check';

                // Build badge via DOM to prevent XSS on status.label
                const span = document.createElement('span');
                span.className = `badge ${colorClass} px-3 py-2 w-100 rounded-pill fs-6 fw-semibold shadow-sm d-flex align-items-center justify-content-center border border-${status.color} border-opacity-25`;

                const iconEl = document.createElement('i');
                iconEl.className = `mdi ${icon} me-2 fs-5`;
                span.appendChild(iconEl);
                span.appendChild(document.createTextNode(status.label));

                accountBadgeContainer.innerHTML = '';
                accountBadgeContainer.appendChild(span);
            }
        }

        if (verificationBadgeContainer && statusOptions.verificationStatus) {
            const status = statusOptions.verificationStatus.find(s => s.value == verificationStatusValue);
            if (status) {
                const colorClass = `bg-soft-${status.color} text-${status.color}`;
                const icon = status.icon || 'mdi-shield-check';

                // Build badge via DOM to prevent XSS on status.label
                const span = document.createElement('span');
                span.className = `badge ${colorClass} px-3 py-2 w-100 rounded-pill fs-6 fw-semibold shadow-sm d-flex align-items-center justify-content-center border border-${status.color} border-opacity-25`;

                const iconEl = document.createElement('i');
                iconEl.className = `mdi ${icon} me-2 fs-5`;
                span.appendChild(iconEl);
                span.appendChild(document.createTextNode(status.label));

                verificationBadgeContainer.innerHTML = '';
                verificationBadgeContainer.appendChild(span);
            }
        }
    }

    function getStatusBadgeClass(value, type) {
        const options = type === 'account' ? statusOptions.accountStatus : statusOptions.verificationStatus;
        const status = options.find(s => s.value == value);
        return status ? `bg-${status.color}` : 'bg-secondary';
    }

    /**
     * Check factory completeness and update both modal and page displays
     */
    function checkFactoryCompleteness() {
        const factoryId = config.factoryId;

        if (!factoryId) return;

        const url = config.routes.completeness.replace(':id', factoryId);
        const completenessStatusDiv = document.getElementById('completenessStatus');

        // Show loading state
        if (completenessStatusDiv) {
            completenessStatusDiv.innerHTML = '<span class="badge bg-light text-dark"><i class="mdi mdi-loading mdi-spin me-1"></i>Loading completeness...</span>';
        }

        fetch(url, {
            method: 'GET',
            headers: {
                'Authorization': `Bearer ${getJWTToken('jwt_token')}`,
                'Accept': 'application/json'
            }
        })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Failed to check completeness');
                }
                return response.json();
            })
            .then(data => {
                if (data.data) {
                    const completenessPayload = data.data.completeness || data.data;
                    const completenessObject = completenessPayload.completeness || completenessPayload;

                    displayCompletenessStatus(completenessPayload);
                    displayPageCompletenessStatus(completenessPayload);

                    window.currentCompletenessData = completenessObject;

                    console.log('Factory Completeness:', data.data);
                } else {
                    const alert = document.getElementById('completenessAlert');
                    if (alert) alert.classList.add('d-none');

                    if (completenessStatusDiv) {
                        completenessStatusDiv.innerHTML = '<span class="badge bg-secondary"><i class="mdi mdi-information-outline me-1"></i>Completeness data unavailable</span>';
                    }
                }
            })
            .catch(error => {
                console.error('Error checking completeness:', error);

                const alert = document.getElementById('completenessAlert');
                if (alert) alert.classList.add('d-none');

                if (completenessStatusDiv) {
                    completenessStatusDiv.innerHTML = `<div class="alert alert-danger mb-0 w-100">
                        <i class="mdi mdi-alert-circle me-2"></i>
                        <strong>Error:</strong> Failed to load completeness. Please refresh the page.
                    </div>`;
                }

                showStatusAlert('Failed to check completeness', 'warning');
            });
    }

    /**
     * Display completeness status with organized sections
     */
    function displayCompletenessStatus(completenessData) {
        const alert = document.getElementById('completenessAlert');
        const fieldsList = document.getElementById('missingFieldsList');

        if (!alert || !fieldsList) return;

        // Extract the completeness object if nested
        const completeness = completenessData.completeness || completenessData;

        if (completeness.is_complete) {
            alert.classList.add('d-none');
            return;
        }

        // Organize missing fields by section
        const fieldsBySection = {};

        if (completeness.missing_fields && Array.isArray(completeness.missing_fields)) {
            completeness.missing_fields.forEach(fieldKey => {
                const fieldInfo = fieldLabels[fieldKey] || { section: 'Other', label: fieldKey };

                if (!fieldsBySection[fieldInfo.section]) {
                    fieldsBySection[fieldInfo.section] = [];
                }
                fieldsBySection[fieldInfo.section].push(fieldInfo.label);
            });
        }

        // Display missing fields organized by section
        fieldsList.innerHTML = '';

        Object.keys(fieldsBySection).sort().forEach(section => {
            // Create section header
            const sectionHeader = document.createElement('li');
            sectionHeader.className = 'fw-semibold text-danger mt-2 mb-1';
            sectionHeader.innerHTML = `<i class="mdi mdi-folder-open me-1"></i>${escapeHtml(section)}`;
            fieldsList.appendChild(sectionHeader);

            // Add fields under the section
            fieldsBySection[section].forEach(label => {
                const li = document.createElement('li');
                li.className = 'ms-4 small';
                li.innerHTML = `<i class="mdi mdi-check-outline me-1 text-dark"></i>${escapeHtml(label)}`;
                fieldsList.appendChild(li);
            });
        });

        alert.classList.remove('d-none');
    }

    function displayPageCompletenessStatus(completenessData) {
        const container = document.getElementById('completenessStatus');
        if (!container) return;

        const completeness = completenessData.completeness || completenessData;
        container.innerHTML = '';

        if (!completeness) {
            container.innerHTML = `
                <div class="d-flex align-items-center gap-2 mb-2">
                    <i class="mdi mdi-help-circle fs-5 text-secondary"></i>
                    <span class="text-muted text-xs fw-semibold">No Data</span>
                </div>
                <div class="progress progress-sm w-100">
                    <div class="progress-bar bg-secondary" role="progressbar" style="width: 0%"></div>
                </div>
            `;
            return;
        }

        const totalFields = Object.keys(fieldLabels).length; // Derive from trackable labels
        const missingCount = completeness.missing_fields ? completeness.missing_fields.length : 0;

        let percentage = 100;
        let pColor = 'bg-success';
        let strokeColor = 'text-success';
        let icon = 'mdi-check-decagram';
        let textLabel = '100% Complete';

        if (!completeness.is_complete && missingCount > 0) {
            // Rough calculation for visual feedback
            percentage = Math.max(10, Math.round(((totalFields - missingCount) / totalFields) * 100));
            pColor = percentage > 50 ? 'bg-warning' : 'bg-danger';
            strokeColor = percentage > 50 ? 'text-warning' : 'text-danger';
            icon = 'mdi-alert-circle';
            textLabel = `${percentage}% Complete`;
        }

        container.innerHTML = `
            <div class="d-flex align-items-center justify-content-between w-100 mb-2">
                <div class="d-flex align-items-center gap-1 ${strokeColor}">
                    <i class="mdi ${icon} fs-5"></i>
                    <span class="text-xs fw-bold">${textLabel}</span>
                </div>
                ${!completeness.is_complete ? `<button class="btn btn-link btn-sm p-0 text-decoration-none text-xs" type="button" data-bs-toggle="collapse" data-bs-target="#missingFieldsListPage">View Missing</button>` : ''}
            </div>
            <div class="progress progress-sm w-100 mb-2">
                <div class="progress-bar ${pColor}" role="progressbar" style="width: ${percentage}%" aria-valuenow="${percentage}" aria-valuemin="0" aria-valuemax="100"></div>
            </div>
            ${!completeness.is_complete ? `
                <div class="collapse w-100 mt-2" id="missingFieldsListPage">
                    <div class="card card-body bg-light border-0 p-2 text-start">
                        <small class="text-muted fw-bold mb-1 border-bottom border-warning pb-1">Missing Fields (${missingCount})</small>
                        ${buildMissingFieldsList(completeness.missing_fields)}
                    </div>
                </div>
            ` : ''}
        `;
    }

    /**
     * Build formatted missing fields list
     */
    function buildMissingFieldsList(missingFields) {
        if (!missingFields || !Array.isArray(missingFields) || missingFields.length === 0) {
            return '<p class="mb-0 text-muted">No missing fields</p>';
        }

        // Organize by section
        const fieldsBySection = {};
        missingFields.forEach(fieldKey => {
            const fieldInfo = fieldLabels[fieldKey] || { section: 'Other', label: escapeHtml(fieldKey) };
            if (!fieldsBySection[fieldInfo.section]) {
                fieldsBySection[fieldInfo.section] = [];
            }
            fieldsBySection[fieldInfo.section].push(fieldInfo.label);
        });

        // Build HTML with escaped content
        let html = '<div class="small">';
        Object.keys(fieldsBySection).sort().forEach(section => {
            html += `
                <div class="mb-2">
                    <strong class="text-danger d-block mb-1">
                        <i class="mdi mdi-folder-open me-1"></i>${escapeHtml(section)}
                    </strong>
                    <ul class="mb-0 ps-4">
            `;
            fieldsBySection[section].forEach(label => {
                html += `<li class="text-muted"><i class="mdi mdi-circle-small me-1"></i>${escapeHtml(label)}</li>`;
            });
            html += `
                    </ul>
                </div>
            `;
        });
        html += '</div>';
        return html;
    }
    /**
     * Update factory status
     */
    function updateStatus() {
        const factoryId = config.factoryId;
        const form = document.getElementById('statusUpdateForm');

        if (!factoryId) {
            showStatusAlert('Factory ID is missing', 'error');
            return;
        }

        // Get form data
        const accountStatus = document.getElementById('statusAccountStatus').value;
        const verificationStatus = document.getElementById('statusVerificationStatus').value;
        const verifyEmail = document.getElementById('verifyEmailCheckbox').checked ? 1 : 0;
        const checkCompleteness = document.getElementById('checkCompleteness').checked;
        const reason = document.getElementById('statusReason').value;

        // Validate at least one status is selected
        if (!accountStatus && !verificationStatus && !verifyEmail) {
            showStatusAlert('Please select at least one action to perform', 'warning');
            return;
        }

        // IMPORTANT VALIDATION: Prevent updating to "verified" status if profile is incomplete
        if (verificationStatus) {
            if (verificationStatus == '1' || verificationStatus == 1) {
                if (window.currentCompletenessData && !window.currentCompletenessData.is_complete) {
                    showStatusAlert(
                        '⚠️ Cannot mark as verified: Factory profile is incomplete. Complete all required fields first.',
                        'error'
                    );
                    const checkBox = document.getElementById('checkCompleteness');
                    if (checkBox) checkBox.checked = true;
                    checkFactoryCompleteness();
                    return;
                }
            }
        }

        // Build request data
        const data = {
            reason: reason
        };

        if (accountStatus) data.account_status = accountStatus;
        if (verificationStatus) data.account_verified = verificationStatus;
        if (verifyEmail) data.verify_email = true;

        submitStatusUpdate(data, factoryId);
    }

    /**
     * Submit status update to API
     */
    function submitStatusUpdate(data, factoryId) {
        const url = config.routes.updateStatus.replace(':id', factoryId);
        const btn = document.getElementById('updateStatusBtn');
        if (!btn) {
            console.error('Update status button not found');
            return;
        }

        const spinner = btn.querySelector('span.spinner-border');
        const icon = btn.querySelector('.btn-icon');

        // Show loading state
        btn.disabled = true;
        if (spinner) {
            spinner.classList.remove('d-none');
        }
        if (icon) {
            icon.classList.add('d-none');
        }

        fetch(url, {
            method: 'PUT',
            headers: {
                'Authorization': `Bearer ${getJWTToken('jwt_token')}`,
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': config.csrfToken
            },
            body: JSON.stringify(data)
        })
            .then(response => {
                if (!response.ok) {
                    return response.json().then(err => {
                        throw err;
                    });
                }
                return response.json();
            })
            .then(result => {
                // Update current factory data
                if (result.data) {
                    currentFactoryData = result.data;
                    updateStatusDisplay();

                    // Update completeness display if applicable
                    if (result.data.completeness) {
                        window.currentCompletenessData = result.data.completeness;
                        displayPageCompletenessStatus(result.data.completeness);
                    }
                }

                // Show success message
                showStatusAlert('✓ Status updated successfully!', 'success');

                // Hide completeness alert
                const completenessAlert = document.getElementById('completenessAlert');
                if (completenessAlert) {
                    completenessAlert.classList.add('d-none');
                }

                // Close modal and refresh data
                setTimeout(() => {
                    try {
                        const modal = bootstrap.Modal.getInstance(document.getElementById('statusUpdateModal'));
                        if (modal) modal.hide();
                    } catch (e) {
                        console.log('Modal close error:', e);
                    }

                    // Refresh data
                    checkFactoryCompleteness();
                }, 1500);
            })
            .catch(error => {
                console.error('Error updating status:', error);

                let errorMessage = 'Failed to update status. Please try again.';
                const errorDetails = [];
                let completenessPayload = null;

                if (error.data) {
                    if (error.data.completeness) {
                        completenessPayload = error.data.completeness;
                    } else if (error.data.basic_info || error.data.business_info || error.data.industries) {
                        completenessPayload = error.data;
                    } else if (error.data.message) {
                        errorMessage = error.data.message;
                    } else if (error.data.errors) {
                        Object.entries(error.data.errors).forEach(([key, messages]) => {
                            if (Array.isArray(messages)) {
                                errorDetails.push(...messages);
                            } else {
                                errorDetails.push(messages);
                            }
                        });
                        if (errorDetails.length > 0) {
                            errorMessage = 'Validation Error: ' + errorDetails.join(', ');
                        }
                    }
                } else if (error.message) {
                    errorMessage = error.message;
                }

                if (completenessPayload) {
                    errorMessage = '⚠️ Cannot update status: Factory profile is incomplete';
                    displayCompletenessStatus(completenessPayload);
                    const completenessObject = completenessPayload.completeness || completenessPayload;
                    if (completenessObject) {
                        window.currentCompletenessData = completenessObject;
                        if (isBusinessPage) {
                            displayPageCompletenessStatus(completenessObject);
                        }
                    }
                }

                showStatusAlert(errorMessage, 'error');

                if (errorMessage.includes('incomplete')) {
                    const checkBox = document.getElementById('checkCompleteness');
                    if (checkBox) {
                        checkBox.checked = true;
                        checkFactoryCompleteness();
                    }
                }
            })
            .finally(() => {
                btn.disabled = false;
                if (spinner) {
                    spinner.classList.add('d-none');
                }
                if (icon) {
                    icon.classList.remove('d-none');
                }
            });
    }

    /**
     * Update status display (for business page)
     */
    function updateStatusDisplay() {
        if (!currentFactoryData) return;

        // Extract account status
        const accountStatusValue = typeof currentFactoryData.account_status === 'object'
            ? currentFactoryData.account_status?.value
            : currentFactoryData.account_status;

        // Extract verification status
        const verificationStatusValue = typeof currentFactoryData.account_verified === 'object'
            ? currentFactoryData.account_verified?.value
            : currentFactoryData.account_verified;

        // Update badges dynamically after the API saves
        updateStatusBadges(accountStatusValue, verificationStatusValue);

        // Pre-select current status in form
        document.getElementById('statusAccountStatus').value = accountStatusValue || '';
        document.getElementById('statusVerificationStatus').value = verificationStatusValue || '';

        // Set email verification checkbox based on current status
        const isEmailVerified = currentFactoryData.is_email_verified || (currentFactoryData.email_verified_at !== null);
        document.getElementById('verifyEmailCheckbox').checked = isEmailVerified;

        // Update email display
        const emailDisplay = document.getElementById('factoryEmailDisplay');
        if (emailDisplay && currentFactoryData.email) {
            emailDisplay.textContent = currentFactoryData.email;
        }
    }

    /**
     * Show status alert message
     */
    function showStatusAlert(message, type = 'info') {
        const alert = document.getElementById('statusAlert');
        if (!alert) return;

        const typeMap = {
            'success': 'success',
            'error': 'danger',
            'warning': 'warning',
            'danger': 'danger',
            'info': 'info'
        };
        const alertType = typeMap[type] || 'info';

        // Icon mapping with MDI icons
        const iconMap = {
            'success': 'mdi-check-circle',
            'danger': 'mdi-alert-circle',
            'warning': 'mdi-alert-circle',
            'info': 'mdi-information-outline'
        };
        const icon = iconMap[alertType] || 'mdi-information-outline';

        // Build alert safely without innerHTML
        alert.className = `alert alert-${alertType} d-block alert-dismissible fade show`;
        alert.innerHTML = '';

        // Create main container
        const container = document.createElement('div');
        container.className = 'd-flex align-items-center';

        // Create icon span
        const iconSpan = document.createElement('i');
        iconSpan.className = `mdi ${icon} me-3 fs-5`;
        container.appendChild(iconSpan);

        // Create message span
        const messageSpan = document.createElement('span');
        messageSpan.className = 'flex-grow-1';
        messageSpan.textContent = message;
        container.appendChild(messageSpan);

        // Create close button
        const closeButton = document.createElement('button');
        closeButton.type = 'button';
        closeButton.className = 'btn-close';
        closeButton.setAttribute('data-bs-dismiss', 'alert');
        closeButton.setAttribute('aria-label', 'Close');
        container.appendChild(closeButton);

        alert.appendChild(container);

        // Auto-dismiss success alerts after 5 seconds
        if (type === 'success') {
            setTimeout(() => {
                if (!alert.classList.contains('d-none')) {
                    const bsAlert = new bootstrap.Alert(alert);
                    bsAlert.close();
                }
            }, 5000);
        }
    }

    /**
     * Reset the status form
     */
    function resetForm() {
        // Reset the form fields
        document.getElementById('statusReason').value = '';
        document.getElementById('checkCompleteness').checked = false;

        // Reset selects to "-- No Change --" (empty value)
        document.getElementById('statusAccountStatus').value = '';
        document.getElementById('statusVerificationStatus').value = '';

        // Reset email verification to current state if we have data
        if (currentFactoryData) {
            const isEmailVerified = currentFactoryData.is_email_verified ||
                (currentFactoryData.email_verified_at !== null);
            document.getElementById('verifyEmailCheckbox').checked = isEmailVerified;
        } else {
            document.getElementById('verifyEmailCheckbox').checked = false;
        }

        document.getElementById('statusAlert').classList.add('d-none');
        document.getElementById('completenessAlert').classList.add('d-none');
    }

    /**
     * Expose functions to global scope
     */
    window.checkFactoryCompleteness = checkFactoryCompleteness;
    window.displayPageCompletenessStatus = displayPageCompletenessStatus;

    // Store completeness data globally for access
    window.currentCompletenessData = null;

    // Initialize the module
    initialize();
})();
