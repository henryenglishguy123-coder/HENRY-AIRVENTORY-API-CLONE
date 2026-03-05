$(document).ready(function () {
    const config = window.FactoryBrandingConfig;
    if (!config) {
        console.error('FactoryBrandingConfig is not defined. Halting initialization.');
        return;
    }

    // Load factory summary
    loadFactorySummary();

    // Load pricing data
    loadPackagingLabel();
    loadHangTag();

    // Setup form submissions
    setupForms();

    function loadFactorySummary() {
        $.ajax({
            url: config.routes.adminFactoryShow.replace(':id', config.factoryId),
            method: 'GET',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'Authorization': `Bearer ${getCookie('jwt_token')}`
            },
            success: function (response) {
                if (response.success && response.data) {
                    const factory = response.data;
                    const name = [factory.first_name, factory.last_name].filter(Boolean).join(' ');
                    const email = factory.email || '';
                    const business = factory.business || null;
                    const companyName = business && business.company_name ? business.company_name : '';

                    let displayName = '';
                    if (companyName) {
                        displayName = companyName;
                    } else if (name) {
                        displayName = name;
                    } else {
                        displayName = 'Factory ID #' + factory.id;
                    }

                    const createdAt = factory.created_at || '';
                    const lastActive = factory.last_active || '';

                    const safeName = escapeHtml(name);
                    const safeEmail = escapeHtml(email);
                    const safeCompanyName = escapeHtml(companyName);
                    const safeDisplayName = escapeHtml(displayName);
                    const safeCreatedAt = escapeHtml(createdAt);
                    const safeLastActive = escapeHtml(lastActive);

                    const html = `
                        <div class="d-flex align-items-center gap-3">
                            <div class="bg-light rounded text-primary d-flex align-items-center justify-content-center px-3 py-2 fs-2"><i class="mdi mdi-domain"></i></div>
                            <div>
                                <h4 class="mb-1 text-dark fw-bold">${safeDisplayName}</h4>
                                <div class="d-flex flex-wrap align-items-center gap-3 text-muted small">
                                    ${safeName && safeCompanyName ? `<span class="d-flex align-items-center"><i class="mdi mdi-account-circle me-1 fs-5"></i>${safeName}</span>` : ''}
                                    ${safeEmail ? `<span class="d-flex align-items-center"><i class="mdi mdi-email-outline me-1 fs-5"></i>${safeEmail}</span>` : ''}
                                    ${safeCreatedAt ? `<span class="d-flex align-items-center border-start ps-3"><i class="mdi mdi-calendar-blank me-1 fs-5"></i>Joined: ${safeCreatedAt}</span>` : ''}
                                    ${safeLastActive ? `<span class="d-flex align-items-center border-start ps-3"><i class="mdi mdi-clock me-1 fs-5"></i>Last active: ${safeLastActive}</span>` : ''}
                                </div>
                            </div>
                        </div>
                    `;
                    $('#factorySummary').html(html);
                }
            },
            error: function () {
                $('#factorySummary').html('<div class="text-danger">Failed to load factory details</div>');
            }
        });
    }

    function loadPackagingLabel() {
        $.ajax({
            url: config.routes.packagingLabelShow,
            method: 'GET',
            data: { factory_id: config.factoryId },
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'Authorization': `Bearer ${getCookie('jwt_token')}`
            },
            success: function (response) {
                if (response.success && response.data) {
                    $('#pl_front_price').val(response.data.front_price || 0);
                    $('#pl_back_price').val(response.data.back_price || 0);
                    $('#pl_is_active').prop('checked', !!response.data.is_active);
                }
            },
            error: function (xhr, status, err) {
                console.error("Failed to load packaging label pricing", err);
                showAlert('packagingLabelAlert', 'danger', 'Failed to load existing pricing.');
            }
        });
    }

    function loadHangTag() {
        $.ajax({
            url: config.routes.hangTagShow,
            method: 'GET',
            data: { factory_id: config.factoryId },
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'Authorization': `Bearer ${getCookie('jwt_token')}`
            },
            success: function (response) {
                if (response.success && response.data) {
                    $('#ht_front_price').val(response.data.front_price || 0);
                    $('#ht_back_price').val(response.data.back_price || 0);
                    $('#ht_is_active').prop('checked', !!response.data.is_active);
                }
            },
            error: function (xhr, status, err) {
                console.error("Failed to load hang tag pricing", err);
                showAlert('hangTagAlert', 'danger', 'Failed to load existing pricing.');
            }
        });
    }

    function bindJsonForm(formSelector, buttonSelector, alertId, url) {
        $(formSelector).on('submit', function (e) {
            e.preventDefault();
            const form = $(this);
            const btn = $(buttonSelector);

            clearErrors(form);
            setLoading(btn, true);

            const formData = {};
            $.each(form.serializeArray(), function () {
                formData[this.name] = this.value;
            });
            form.find('input[type="checkbox"]').each(function () {
                formData[this.name] = $(this).is(':checked') ? 1 : 0;
            });

            $.ajax({
                url: url,
                method: 'PUT',
                data: JSON.stringify(formData),
                contentType: 'application/json',
                headers: {
                    'Accept': 'application/json',
                    'Authorization': `Bearer ${getCookie('jwt_token')}`
                },
                success: function (response) {
                    if (response.success) {
                        showAlert(alertId, 'success', response.message);
                    }
                },
                error: function (xhr) {
                    handleErrors(xhr, form, alertId);
                },
                complete: function () {
                    setLoading(btn, false);
                }
            });
        });
    }

    function setupForms() {
        bindJsonForm('#packagingLabelForm', '#packagingLabelSave', 'packagingLabelAlert', config.routes.packagingLabelUpdate);
        bindJsonForm('#hangTagForm', '#hangTagSave', 'hangTagAlert', config.routes.hangTagUpdate);
    }

    // Helpers
    function escapeHtml(unsafe) {
        if (!unsafe) return '';
        return unsafe.toString().replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;");
    }

    function clearErrors(form) {
        form.find('.is-invalid').removeClass('is-invalid');
        form.find('.invalid-feedback').text('');
        form.find('.alert').addClass('d-none').removeClass('alert-danger alert-success').text('');
    }

    function setLoading(btn, isLoading) {
        if (isLoading) {
            btn.prop('disabled', true);
            btn.find('.spinner-border').removeClass('d-none');
        } else {
            btn.prop('disabled', false);
            btn.find('.spinner-border').addClass('d-none');
        }
    }

    function showAlert(id, type, message) {
        $(`#${id}`)
            .removeClass('d-none alert-danger alert-success')
            .addClass(`alert-${type}`)
            .text(message);

        setTimeout(() => {
            $(`#${id}`).addClass('d-none');
        }, 5000);
    }

    function handleErrors(xhr, form, alertId) {
        if (xhr.status === 422 && xhr.responseJSON && xhr.responseJSON.errors) {
            const errors = xhr.responseJSON.errors;
            Object.keys(errors).forEach(key => {
                const escapedKey = CSS.escape(key);
                const input = form.find(`[name="${escapedKey}"]`);
                if (input.length) {
                    input.addClass('is-invalid');
                    form.find(`[data-error-for="${escapedKey}"]`).text(errors[key][0]);
                }
            });
            showAlert(alertId, 'danger', 'Please correct the errors before submitting.');
        } else {
            const msg = xhr.responseJSON?.message || 'An error occurred. Please try again.';
            showAlert(alertId, 'danger', msg);
        }
    }
});
