document.addEventListener('DOMContentLoaded', function () {
    const tableId = '#factoryTable';
    const modalId = '#createFactoryModal';
    const formId = '#factoryForm';
    let table;
    let cachedIndustries = null;
    let isLoadingIndustries = false;

    // Helper to get email verified badge
    const getEmailVerifiedBadge = (isVerified) => {
        if (isVerified) {
            return '<span class="badge bg-success">Verified</span>';
        }
        return '<span class="badge bg-danger">Unverified</span>';
    };

    // HTML Escape Helper
    const escapeHtml = (text) => {
        if (text === null || text === undefined) return '';
        return String(text)
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    };

    const loadIndustries = (selectedId = null) => {
        const select = $('#industry_id');

        if (!select.length) {
            return;
        }

        const applyOptions = () => {
            const currentValue = select.val();
            const valueToSelect = selectedId !== null ? String(selectedId) : currentValue;

            select.empty();
            select.append('<option value="" disabled>Select Industry</option>');

            (cachedIndustries || []).forEach((industry) => {
                const id = String(industry.id);
                const name = industry.meta && industry.meta.name ? industry.meta.name : '';
                const isSelected = valueToSelect && valueToSelect === id;

                select.append(
                    `<option value="${escapeHtml(id)}"${isSelected ? ' selected' : ''}>${escapeHtml(name)}</option>`
                );
            });

            if (!valueToSelect) {
                select.prop('selectedIndex', 0);
            }
        };

        if (cachedIndustries) {
            applyOptions();
            return;
        }

        if (isLoadingIndustries) {
            return;
        }

        isLoadingIndustries = true;

        $.ajax({
            url: window.FactoryConfig.routes.industries,
            method: 'GET',
            data: { status: 1 },
            success: function (response) {
                cachedIndustries = Array.isArray(response.data) ? response.data : [];
                applyOptions();
            },
            error: function () {
                if (typeof toastr !== 'undefined') {
                    toastr.error('Failed to load industries.');
                }
            },
            complete: function () {
                isLoadingIndustries = false;
            }
        });
    };

    // Initialize Date Range Picker
    if ($('#filter_date_range').length) {
        $('#filter_date_range').daterangepicker({
            autoUpdateInput: false,
            locale: {
                cancelLabel: 'Clear',
                format: 'YYYY-MM-DD'
            },
            ranges: {
                'Today': [moment(), moment()],
                'Yesterday': [moment().subtract(1, 'days'), moment().subtract(1, 'days')],
                'Last 7 Days': [moment().subtract(6, 'days'), moment()],
                'Last 30 Days': [moment().subtract(29, 'days'), moment()],
                'This Month': [moment().startOf('month'), moment().endOf('month')],
                'Last Month': [moment().subtract(1, 'month').startOf('month'), moment().subtract(1, 'month').endOf('month')]
            }
        });

        $('#filter_date_range').on('apply.daterangepicker', function (ev, picker) {
            $(this).val(picker.startDate.format('YYYY-MM-DD') + ' - ' + picker.endDate.format('YYYY-MM-DD'));
        });

        $('#filter_date_range').on('cancel.daterangepicker', function (ev, picker) {
            $(this).val('');
        });
    }

    // Initialize DataTable
    if ($(tableId).length) {
        table = $(tableId).DataTable({
            processing: true,
            serverSide: true,
            ajax: {
                url: window.FactoryConfig.routes.index,
                headers: {
                    'Authorization': 'Bearer ' + getCookie('jwt_token')
                },
                data: function (d) {
                    d.filter_name = $('#filter_name').val();
                    d.filter_business_name = $('#filter_business_name').val();
                    d.filter_email = $('#filter_email').val();
                    d.filter_phone = $('#filter_phone').val();
                    d.filter_account_status = $('#filter_account_status').val();
                    d.filter_email_verified = $('#filter_email_verified').val();
                    d.filter_approval_status = $('#filter_approval_status').val(); // Renamed filter ID input to match
                    d.filter_date_range = $('#filter_date_range').val();
                },
                // Improved error handling
                error: function (xhr, error, code) {
                    let message = 'Failed to load factories.';
                    if (xhr && xhr.responseJSON && xhr.responseJSON.message) {
                        message = xhr.responseJSON.message;
                    }
                    if (typeof toastr !== 'undefined') {
                        toastr.error(message);
                    } else {
                        console.error(message);
                    }
                }
            },
            columns: [
                {
                    data: null,
                    orderable: false,
                    searchable: false,
                    render: function (data, type, row) {
                        return `<div class="form-check"><input class="form-check-input row-checkbox" type="checkbox" value="${escapeHtml(row.id)}"></div>`;
                    }
                },
                { data: 'id', name: 'id' },
                {
                    data: 'business', // Assuming backend returns business object or we use render
                    name: 'contact_name',
                    render: function (data, type, row) {
                        // Safe render: use data-id only, fetch fresh if needed, or stick to simple data attributes.
                        // Ideally avoid JSON.stringify of full row in HTML for security/fragility.
                        // But for now, keeping it simple as requested but handling potential nulls in display.
                        return `<a href="#" class="text-primary fw-bold edit-btn" data-id="${escapeHtml(row.id)}">${escapeHtml(row.first_name)} ${escapeHtml(row.last_name)}</a>`;
                    }
                },
                {
                    data: 'business',
                    name: 'business_name',
                    orderable: false,
                    render: function (data, type, row) {
                        return `
                            <div class="d-flex flex-column">
                                <small class="text-truncate" style="max-width: 200px;"><i class="fas fa-building me-1 text-muted"></i> ${escapeHtml(data?.company_name || 'N/A')}</small>
                                <small class="text-truncate" style="max-width: 200px;"><i class="fas fa-envelope me-1 text-muted"></i> ${escapeHtml(row.email || 'N/A')}</small>
                                <small class="text-truncate" style="max-width: 200px;"><i class="fas fa-phone-alt me-1 text-muted"></i> ${escapeHtml(row.phone_number || 'N/A')}</small>
                            </div>
                        `;
                    }
                },
                {
                    data: 'account_status',
                    name: 'account_status',
                    render: function (data) {
                        if (!data) return '';
                        return `<span class="badge bg-${data.color}">${data.label}</span>`;
                    }
                },
                {
                    data: 'is_email_verified',
                    name: 'email', // Search on email
                    searchable: false, // But usually validation status not searchable via this column unless backend supports
                    render: function (data, type, row) {
                        // Using boolean directly
                        return getEmailVerifiedBadge(data);
                    }
                },
                {
                    data: 'account_verified',
                    name: 'account_verified',
                    render: function (data) {
                        if (!data) return '';
                        return `<span class="badge bg-${data.color}">${data.label}</span>`;
                    }
                },
                { data: 'created_at', name: 'created_at' },
                { data: 'last_active', name: 'last_login' },
                {
                    data: null,
                    name: 'actions',
                    orderable: false,
                    searchable: false,
                    render: function (data, type, row) {
                        return `
                            <div class="text-end pe-2">
                                <a href="${escapeHtml(window.FactoryConfig.routes.businessInformation.replace(':id', row.id))}" class="btn btn-sm btn-soft-primary" title="Edit Business">
                                    <i class="fas fa-pencil-alt"></i>
                                </a>
                            </div>
                        `;
                    }
                }
            ],
            order: [[1, 'desc']], // Order by ID
            columnDefs: [
                { targets: 0, width: '20px' },
                { targets: 1, width: '50px' }
            ]
        });

        // Handle Dynamic Options from API (Appended to response by Controller)
        table.on('xhr', function (e, settings, json, xhr) {
            if (json && json.options && !window.optionsLoaded) {
                // Populate Account Status
                if (json.options.account_statuses) {
                    const statusSelect = $('#filter_account_status');
                    if (statusSelect.length) {
                        const currentVal = statusSelect.val();
                        statusSelect.empty().append('<option value="" selected>All</option>');
                        json.options.account_statuses.forEach(status => {
                            const isSelected = currentVal !== '' && currentVal == status.value;
                            statusSelect.append(`<option value="${escapeHtml(status.value)}" ${isSelected ? 'selected' : ''}>${escapeHtml(status.label)}</option>`);
                        });
                    }
                }

                // Populate Account Verified
                if (json.options.account_verification_statuses) {
                    const verifiedSelect = $('#filter_approval_status');
                    if (verifiedSelect.length) {
                        const currentVal = verifiedSelect.val();
                        verifiedSelect.empty().append('<option value="" selected>All</option>');
                        json.options.account_verification_statuses.forEach(status => {
                            const isSelected = currentVal !== '' && currentVal == status.value;
                            verifiedSelect.append(`<option value="${escapeHtml(status.value)}" ${isSelected ? 'selected' : ''}>${escapeHtml(status.label)}</option>`);
                        });
                    }
                }

                window.optionsLoaded = true;
            }
        });
    }

    // Save Factory (Create/Update)
    $('#saveFactoryBtn').on('click', function () {
        const id = $('#factoryId').val();
        const url = id ? `${window.FactoryConfig.routes.index}/${id}` : window.FactoryConfig.routes.index;
        const method = id ? 'PUT' : 'POST';
        const formData = $(formId).serialize();

        $.ajax({
            url: url,
            method: method,
            data: formData,
            headers: {
                'Authorization': 'Bearer ' + getCookie('jwt_token')
            },
            success: function (response) {
                if (response.success) {
                    $(modalId).modal('hide');
                    if (typeof table !== 'undefined' && table && table.ajax && typeof table.ajax.reload === 'function') {
                        table.ajax.reload();
                    }
                    toastr.success(response.message);
                    $(formId)[0].reset();
                    $('#factoryId').val('');
                } else {
                    toastr.error(response.message);
                }
            },
            error: function (xhr) {
                let errorMessage = 'An error occurred';
                if (xhr && xhr.responseJSON) {
                    errorMessage = xhr.responseJSON.message || errorMessage;
                    if (xhr.responseJSON.errors) {
                        for (let field in xhr.responseJSON.errors) {
                            toastr.error(xhr.responseJSON.errors[field][0]);
                        }
                        return; // Errors handled
                    }
                }
                toastr.error(errorMessage);
            }
        });
    });

    // Edit Button Click
    $(document).on('click', '.edit-btn', function (e) {
        if ($(this).attr('href') === '#') e.preventDefault();

        // Removed fragile data-row JSON parsing. Always fetch fresh data.

        const id = $(this).data('id');

        $.ajax({
            url: `${window.FactoryConfig.routes.index}/${id}`,
            method: 'GET',
            headers: {
                'Authorization': 'Bearer ' + getCookie('jwt_token')
            },
            success: function (response) {
                const factory = response.data;
                $('#factoryId').val(factory.id);
                $('#first_name').val(factory.first_name);
                $('#last_name').val(factory.last_name);
                $('#email').val(factory.email);
                $('#phone_number').val(factory.phone_number);
                $('#password').val('');

                loadIndustries(factory.industry ? factory.industry.id : null);

                $('#createFactoryModalLabel').text('Edit Factory');
                $(modalId).modal('show');
            },
            error: function (xhr) {
                console.error(xhr);
                let message = 'Failed to load factory details.';
                if (xhr && xhr.responseJSON && xhr.responseJSON.message) {
                    message = xhr.responseJSON.message;
                } else if (xhr.statusText) {
                    message += ' (' + xhr.statusText + ')';
                }
                if (typeof toastr !== 'undefined') {
                    toastr.error(message);
                } else {
                    alert(message);
                }
            }
        });
    });

    // Reset Modal on Close
    $(modalId).on('hidden.bs.modal', function () {
        $(formId)[0].reset();
        $('#factoryId').val('');
        $('#createFactoryModalLabel').text('Add Factory');
    });

    $(modalId).on('shown.bs.modal', function () {
        if (!$('#factoryId').val()) {
            loadIndustries();
        }
    });

    // Select All Checkbox
    $('#checkAll').on('click', function () {
        $('.row-checkbox').prop('checked', this.checked);
    });

    // Refresh table function
    window.refreshFactoryTable = function () {
        if (typeof table !== 'undefined' && table && table.ajax && typeof table.ajax.reload === 'function') {
            table.ajax.reload();
        }
    };

    // Filter Toggle
    $('#toggleFilters').on('click', function () {
        $('#filterSection').toggleClass('d-none');
    });

    // Apply Filters
    $('#applyFilters').on('click', function () {
        if (typeof table !== 'undefined' && table && table.ajax && typeof table.ajax.reload === 'function') {
            table.ajax.reload();
        }
    });

    // Reset Filters
    $('#resetFilters').on('click', function () {
        $('#filterForm')[0].reset();
        // Reset DatePicker
        if ($('#filter_date_range').length) {
            $('#filter_date_range').val('');
        }
        if (typeof table !== 'undefined' && table && table.ajax && typeof table.ajax.reload === 'function') {
            table.ajax.reload();
        }
    });

    // Bulk Action (Basic Implementation for Delete)
    $('#applyBulkAction').on('click', function () {
        const action = $('#bulkAction').val();
        const selectedIds = $('.row-checkbox:checked').map(function () { return $(this).val(); }).get();

        if (action === 'Select Action' || selectedIds.length === 0) {
            toastr.warning('Please select an action and at least one item.');
            return;
        }

        if (action === 'delete') {
            if (!confirm('Are you sure you want to delete selected items?')) return;

            let promises = selectedIds.map(id => {
                return $.ajax({
                    url: `${window.FactoryConfig.routes.index}/${id}`,
                    method: 'DELETE',
                    headers: { 'Authorization': 'Bearer ' + getCookie('jwt_token') }
                });
            });

            Promise.allSettled(promises)
                .then((results) => {
                    const failed = results.filter(r => r.status === 'rejected');
                    const succeeded = results.filter(r => r.status === 'fulfilled');

                    if (failed.length === 0) {
                        toastr.success('Selected items deleted.');
                    } else if (succeeded.length === 0) {
                        toastr.error('Failed to delete selected items.');
                    } else {
                        toastr.warning(`${succeeded.length} items deleted, ${failed.length} failed.`);
                    }

                    if (succeeded.length > 0) {
                        table.ajax.reload();
                        $('#checkAll').prop('checked', false);
                    }
                });
        }
    });

    function getCookie(name) {
        let value = "; " + document.cookie;
        let parts = value.split("; " + name + "=");
        if (parts.length === 2) return parts.pop().split(";").shift();
        return undefined;
    }
});
