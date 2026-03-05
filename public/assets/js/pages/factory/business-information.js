document.addEventListener('DOMContentLoaded', function () {
    var config = window.FactoryBusinessConfig || null;
    if (!config) {
        return;
    }

    var factoryId = config.factoryId;
    var routes = config.routes || {};

    var form = document.getElementById('businessInfoForm');
    var alertBox = document.getElementById('businessInfoAlert');
    var loadingEl = document.getElementById('businessInfoLoading');
    var contentEl = document.getElementById('businessInfoContent');
    var saveBtn = document.getElementById('businessInfoSave');
    var resetBtn = document.getElementById('businessInfoReset');
    var deliveryPartnerForm = document.getElementById('deliveryPartnerForm');
    var deliveryPartnerAlert = document.getElementById('deliveryPartnerAlert');
    var deliveryPartnerSaveBtn = document.getElementById('deliveryPartnerSave');
    var deliveryPartnerSaveSpinner = deliveryPartnerSaveBtn ? deliveryPartnerSaveBtn.querySelector('.spinner-border') : null;
    var resetBtn = document.getElementById('businessInfoReset');
    var saveSpinner = saveBtn ? saveBtn.querySelector('.spinner-border') : null;
    var factorySummary = document.getElementById('factorySummary');
    var myzeApiUrlInput = document.getElementById('myze_api_url');
    var myzeApiTokenInput = document.getElementById('myze_api_token');

    var facilityAddressList = document.getElementById('facilityAddressList');
    var distAddressList = document.getElementById('distAddressList');
    var addFacilityAddressBtn = document.getElementById('addFacilityAddress');
    var addDistAddressBtn = document.getElementById('addDistAddress');
    var addressesLoading = document.getElementById('addressesLoading');

    var primaryForm = document.getElementById('primaryContactForm');
    var primaryAlert = document.getElementById('primaryContactAlert');
    var primarySaveBtn = document.getElementById('primaryContactSave');
    var primarySaveSpinner = primarySaveBtn ? primarySaveBtn.querySelector('.spinner-border') : null;

    var secondaryForm = document.getElementById('secondaryContactForm');
    var secondaryAlert = document.getElementById('secondaryContactAlert');
    var secondarySaveBtn = document.getElementById('secondaryContactSave');
    var secondarySaveSpinner = secondarySaveBtn ? secondarySaveBtn.querySelector('.spinner-border') : null;

    var fieldIds = [
        'company_name',
        'registration_number',
        'tax_vat_number',
        'registered_address',
        'city',
        'postal_code'
    ];

    function getCookie(name) {
        var value = '; ' + document.cookie;
        var parts = value.split('; ' + name + '=');
        if (parts.length === 2) return parts.pop().split(';').shift();
        return undefined;
    }

    function showAlert(type, message) {
        showFormAlert(alertBox, type, message);
    }

    function clearAlert() {
        clearFormAlert(alertBox);
    }

    function showFormAlert(target, type, message) {
        if (!target) return;
        target.classList.remove('d-none', 'alert-success', 'alert-danger');
        target.classList.add(type === 'success' ? 'alert-success' : 'alert-danger');
        target.textContent = message || '';
    }

    function clearFormAlert(target) {
        if (!target) return;
        target.classList.add('d-none');
        target.textContent = '';
    }

    function toggleLoading(isLoading) {
        if (loadingEl) {
            loadingEl.classList.toggle('d-none', !isLoading);
        }
        if (contentEl) {
            contentEl.classList.toggle('d-none', isLoading);
        }
        if (saveBtn) {
            saveBtn.disabled = isLoading;
        }
        if (resetBtn) {
            resetBtn.disabled = isLoading;
        }
    }

    function toggleSaveLoading(isLoading) {
        if (!saveBtn) return;
        saveBtn.disabled = isLoading;
        if (saveSpinner) {
            saveSpinner.classList.toggle('d-none', !isLoading);
        }
    }

    function setFieldValue(id, value) {
        var el = document.getElementById(id);
        if (el) {
            el.value = value || '';
        }
    }

    function setFileRow(nameId, linkId, url) {
        var nameEl = document.getElementById(nameId);
        var linkEl = document.getElementById(linkId);

        if (!nameEl || !linkEl) {
            return;
        }

        var group = nameEl.closest('.certificate-row');
        var existingRow = group ? group.querySelector('.certificate-existing') : null;
        var uploadRow = group ? group.querySelector('.certificate-upload') : null;

        if (!url) {
            nameEl.textContent = window.transNotUploaded || 'Not uploaded';
            linkEl.removeAttribute('href');
            linkEl.classList.add('disabled');
            linkEl.setAttribute('aria-disabled', 'true');
            if (existingRow) {
                existingRow.classList.add('d-none');
            }
            if (uploadRow) {
                uploadRow.classList.remove('d-none');
            }
            return;
        }

        var fileName = url;
        try {
            var parts = url.split('/');
            if (parts.length) {
                fileName = parts[parts.length - 1];
            }
        } catch (e) {
        }

        nameEl.textContent = fileName;
        linkEl.href = url;
        linkEl.classList.remove('disabled');
        linkEl.removeAttribute('aria-disabled');
        if (existingRow) {
            existingRow.classList.remove('d-none');
        }
        if (uploadRow) {
            uploadRow.classList.add('d-none');
        }
    }

    function parseJsonResponse(res) {
        return res
            .json()
            .then(function (data) {
                return { status: res.status, body: data };
            })
            .catch(function () {
                return { status: res.status, body: null };
            });
    }

    function collectErrors(body) {
        var messages = [];
        if (!body || !body.errors) {
            return messages;
        }
        Object.keys(body.errors).forEach(function (key) {
            var value = body.errors[key];
            if (Array.isArray(value)) {
                value.forEach(function (msg) {
                    if (msg) {
                        messages.push(msg);
                    }
                });
            } else if (value) {
                messages.push(value);
            }
        });
        return messages;
    }

    function clearFormFieldErrors(form) {
        if (!form) return;
        var invalidInputs = form.querySelectorAll('.is-invalid');
        invalidInputs.forEach(function (el) {
            el.classList.remove('is-invalid');
        });
        var feedbacks = form.querySelectorAll('.invalid-feedback[data-error-for]');
        feedbacks.forEach(function (el) {
            el.textContent = '';
        });
    }

    function showInputError(input, message) {
        if (!input || !input.name) {
            return;
        }
        var form = input.closest('form');
        if (!form) {
            return;
        }
        input.classList.add('is-invalid');
        var feedback = form.querySelector('.invalid-feedback[data-error-for="' + input.name + '"]');
        if (feedback && message) {
            feedback.textContent = message;
        }
    }

    function setFormDisabled(form, disabled) {
        if (!form) return;
        var elements = form.querySelectorAll('input, select, textarea, button');
        elements.forEach(function (el) {
            if (disabled) {
                if (!el.hasAttribute('data-prev-disabled')) {
                    el.setAttribute('data-prev-disabled', el.disabled ? '1' : '0');
                }
                el.disabled = true;
            } else {
                var prev = el.getAttribute('data-prev-disabled');
                if (prev === '0') {
                    el.disabled = false;
                }
                el.removeAttribute('data-prev-disabled');
            }
        });
    }

    function applyFormErrors(form, errors) {
        if (!form || !errors) {
            return;
        }
        Object.keys(errors).forEach(function (key) {
            var messages = errors[key];
            if (!Array.isArray(messages)) {
                messages = [messages];
            }
            var firstMessage = messages[0] || '';
            var inputs = form.querySelectorAll('[name="' + key + '"]');
            inputs.forEach(function (input) {
                input.classList.add('is-invalid');
            });
            var feedback = form.querySelector('.invalid-feedback[data-error-for="' + key + '"]');
            if (feedback && firstMessage) {
                feedback.textContent = firstMessage;
            }
        });
    }

    function escapeHtml(str) {
        if (str === null || str === undefined) {
            return '';
        }
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;')
            .replace(/\//g, '&#x2F;');
    }



    function renderFactorySummary(factory) {
        if (!factorySummary || !factory) {
            return;
        }

        var name = [factory.first_name, factory.last_name].filter(Boolean).join(' ');
        var email = factory.email || '';
        var createdAt = factory.created_at || '';
        var lastActive = factory.last_active || '';
        var business = factory.business || null;
        var companyName = business && business.company_name ? business.company_name : '';

        var html = '';
        html += '<div class="d-flex align-items-center gap-3">';
        html += '<div class="bg-light rounded text-primary d-flex align-items-center justify-content-center px-3 py-2 fs-2"><i class="mdi mdi-domain"></i></div>';
        html += '<div>';
        html += '<h4 class="mb-1 text-dark fw-bold">';
        if (companyName) {
            html += escapeHtml(companyName);
        } else if (name) {
            html += escapeHtml(name);
        } else {
            html += 'Factory ID #' + escapeHtml(factory.id);
        }
        html += '</h4>';

        html += '<div class="d-flex flex-wrap align-items-center gap-3 text-muted small">';
        if (name && companyName) {
            html += '<span class="d-flex align-items-center"><i class="mdi mdi-account-circle me-1 fs-5"></i>' + escapeHtml(name) + '</span>';
        }
        if (email) {
            html += '<span class="d-flex align-items-center"><i class="mdi mdi-email-outline me-1 fs-5"></i>' + escapeHtml(email) + '</span>';
        }
        if (createdAt) {
            html += '<span class="d-flex align-items-center border-start ps-3"><i class="mdi mdi-calendar-blank me-1 fs-5"></i>' + (window.transJoined || 'Joined: ') + escapeHtml(createdAt) + '</span>';
        }
        if (lastActive) {
            html += '<span class="d-flex align-items-center border-start ps-3"><i class="mdi mdi-clock me-1 fs-5"></i>' + (window.transLastActive || 'Last active: ') + escapeHtml(lastActive) + '</span>';
        }
        html += '</div>';
        html += '</div>';
        html += '</div>';

        factorySummary.innerHTML = html;
    }

    var token = getCookie('jwt_token');
    if (!token) {
        toggleLoading(false);
        return;
    }

    var countriesCache = [];

    function fetchCountries() {
        if (countriesCache.length) {
            return Promise.resolve(countriesCache);
        }
        if (!routes.countries) {
            return Promise.resolve([]);
        }
        return fetch(routes.countries, {
            headers: {
                Accept: 'application/json'
            }
        })
            .then(function (res) {
                return res.json();
            })
            .then(function (data) {
                var list = Array.isArray(data.data) ? data.data : [];
                countriesCache = list;
                return list;
            })
            .catch(function () {
                return [];
            });
    }

    function loadCountries(selectedId) {
        var select = document.getElementById('country_id');
        if (!select) {
            return Promise.resolve();
        }
        return fetchCountries().then(function (list) {
            populateCountrySelect(select, list, selectedId);
        });
    }

    function populateCountrySelect(select, list, selectedId) {
        select.innerHTML = '';
        var placeholder = document.createElement('option');
        placeholder.value = '';
        placeholder.textContent = select.getAttribute('data-placeholder') || 'Select country';
        select.appendChild(placeholder);

        list.forEach(function (country) {
            var opt = document.createElement('option');
            opt.value = String(country.id);
            opt.textContent = country.name;
            if (selectedId && String(selectedId) === String(country.id)) {
                opt.selected = true;
            }
            select.appendChild(opt);
        });
    }

    function fetchStates(countryId) {
        if (!routes.states || !countryId) {
            return Promise.resolve([]);
        }
        var url = routes.states.replace(':country', countryId);
        return fetch(url, {
            headers: {
                Accept: 'application/json'
            }
        })
            .then(function (res) {
                return res.json();
            })
            .then(function (data) {
                var list = Array.isArray(data.data) ? data.data : [];
                return list;
            })
            .catch(function () {
                return [];
            });
    }

    function loadStates(countryId, selectedId) {
        var select = document.getElementById('state_id');
        if (!select) {
            return Promise.resolve();
        }
        if (!countryId) {
            select.innerHTML = '';
            var placeholder = document.createElement('option');
            placeholder.value = '';
            placeholder.textContent = select.getAttribute('data-placeholder') || 'Select state';
            select.appendChild(placeholder);
            return Promise.resolve();
        }
        return fetchStates(countryId).then(function (list) {
            select.innerHTML = '';
            var placeholder = document.createElement('option');
            placeholder.value = '';
            placeholder.textContent = select.getAttribute('data-placeholder') || 'Select state';
            select.appendChild(placeholder);
            list.forEach(function (state) {
                var opt = document.createElement('option');
                opt.value = String(state.id);
                opt.textContent = state.name;
                if (selectedId && String(selectedId) === String(state.id)) {
                    opt.selected = true;
                }
                select.appendChild(opt);
            });
        });
    }

    function loadFactory() {
        if (!routes.adminFactoryShow) return;
        var url = routes.adminFactoryShow.replace(':id', factoryId);

        fetch(url, {
            headers: {
                Authorization: 'Bearer ' + token,
                Accept: 'application/json'
            }
        })
            .then(function (res) {
                return res.json();
            })
            .then(function (data) {
                if (data && data.data) {
                    renderFactorySummary(data.data);
                    fillPrimaryContact(data.data);
                }
            })
            .catch(function () { });
    }

    function fillBusinessForm(business, shippingPartnerId) {
        fieldIds.forEach(function (id) {
            setFieldValue(id, business[id]);
        });

        setFileRow('registration_certificate_name', 'registration_certificate_download', business.registration_certificate);
        setFileRow('tax_certificate_name', 'tax_certificate_download', business.tax_certificate);
        setFileRow('import_export_certificate_name', 'import_export_certificate_download', business.import_export_certificate);

        loadCountries(business.country_id).then(function () {
            if (business.country_id) {
                loadStates(business.country_id, business.state_id);
            }
        });

        loadShippingPartners(shippingPartnerId || null);
    }

    function loadBusinessInfo() {
        if (!routes.businessInfoShow) {
            toggleLoading(false);
            return;
        }

        toggleLoading(true);
        clearAlert();

        var url = routes.businessInfoShow + '?factory_id=' + factoryId;

        fetch(url, {
            headers: {
                Authorization: 'Bearer ' + token,
                Accept: 'application/json'
            }
        })
            .then(function (res) {
                return res.json();
            })
            .then(function (data) {
                if (data && data.success && data.data) {
                    if (data.data.business) {
                        fillBusinessForm(data.data.business, data.data.shipping_partner_id || null);
                    } else {
                        loadCountries(null);
                        loadShippingPartners(null);
                    }
                    if (data.data.myze_api_url !== undefined) {
                        setFieldValue('myze_api_url', data.data.myze_api_url);
                    }
                    if (data.data.myze_api_token !== undefined) {
                        setFieldValue('myze_api_token', data.data.myze_api_token);
                    }
                } else {
                    loadCountries(null);
                    loadShippingPartners(null);
                }
            })
            .catch(function () {
                loadCountries(null);
                loadShippingPartners(null);
            })
            .finally(function () {
                toggleLoading(false);
            });
    }

    function loadShippingPartners(assignedIds) {
        var container = document.getElementById('shippingPartnersContainer');
        if (!container || !routes.shippingPartners) return;

        fetch(routes.shippingPartners, {
            headers: {
                Authorization: 'Bearer ' + token,
                Accept: 'application/json'
            }
        })
            .then(function (res) {
                return res.json();
            })
            .then(function (data) {
                var partners = Array.isArray(data.data) ? data.data : [];
                container.innerHTML = '';

                if (partners.length === 0) {
                    container.innerHTML = '<div class="col-12 text-muted small">No shipping partners available.</div>';
                    return;
                }

                partners.forEach(function (partner) {
                    var isChecked = false;
                    if (assignedIds) {
                        if (Array.isArray(assignedIds)) {
                            isChecked = assignedIds.indexOf(partner.id) !== -1;
                        } else {
                            isChecked = String(assignedIds) === String(partner.id);
                        }
                    }
                    var col = document.createElement('div');
                    col.className = 'col-md-4 mb-2';

                    var card = document.createElement('div');
                    card.className = 'border rounded p-2 d-flex align-items-center gap-2 hover-shadow-sm transition-all pointer';
                    card.style.cursor = 'pointer';
                    if (isChecked) {
                        card.classList.add('bg-soft-primary', 'border-primary');
                    }

                    var formCheck = document.createElement('div');
                    formCheck.className = 'form-check mb-0';

                    var input = document.createElement('input');
                    input.type = 'radio';
                    input.className = 'form-check-input';
                    input.name = 'shipping_partner_id';
                    input.value = partner.id;
                    input.id = 'shipping-partner-' + partner.id;
                    if (isChecked) input.checked = true;

                    // Click listener for the whole card
                    card.addEventListener('click', function (e) {
                        if (e.target !== input) {
                            input.checked = true;
                            container.querySelectorAll('.bg-soft-primary').forEach(el => {
                                el.classList.remove('bg-soft-primary', 'border-primary');
                            });
                            card.classList.add('bg-soft-primary', 'border-primary');
                        }
                    });

                    var logoHtml = '';
                    if (partner.logo) {
                        logoHtml = '<img src="' + partner.logo + '" alt="' + partner.name + '" class="rounded" style="width:24px;height:24px;object-fit:contain;">';
                    } else {
                        logoHtml = '<div class="avatar-xs bg-light rounded d-flex align-items-center justify-content-center" style="width:24px;height:24px;"><i class="mdi mdi-truck-delivery-outline text-muted" style="font-size: 14px;"></i></div>';
                    }

                    var label = document.createElement('label');
                    label.className = 'form-check-label d-flex align-items-center gap-2 w-100 pointer mb-0';
                    label.htmlFor = 'shipping-partner-' + partner.id;
                    label.innerHTML = logoHtml + ' <span class="text-truncate" title="' + partner.name + '">' + partner.name + '</span>';

                    formCheck.appendChild(input);
                    formCheck.appendChild(label);
                    card.appendChild(formCheck);
                    col.appendChild(card);
                    container.appendChild(col);
                });

                if (partners.length > 0) {
                    var clearCol = document.createElement('div');
                    clearCol.className = 'col-12 mt-2';
                    var clearBtn = document.createElement('button');
                    clearBtn.type = 'button';
                    clearBtn.className = 'btn btn-link btn-sm text-decoration-none p-0';
                    clearBtn.innerHTML = '<i class="mdi mdi-close-circle-outline me-1"></i>Clear Selection';
                    clearBtn.addEventListener('click', function () {
                        container.querySelectorAll('input[type="radio"]').forEach(i => i.checked = false);
                        container.querySelectorAll('.bg-soft-primary').forEach(el => {
                            el.classList.remove('bg-soft-primary', 'border-primary');
                        });
                    });
                    clearCol.appendChild(clearBtn);
                    container.appendChild(clearCol);
                }
            })
            .catch(function () {
                container.innerHTML = '<div class="col-12 text-danger small">Failed to load shipping partners.</div>';
            });
    }

    function renderAddressCard(type, address) {
        var wrapper = document.createElement('div');
        wrapper.className = 'border rounded p-3';
        wrapper.dataset.type = type;
        if (address && address.id) {
            wrapper.dataset.id = String(address.id);
        }

        var row = document.createElement('div');
        row.className = 'row g-3';
        wrapper.appendChild(row);

        var colAddress = document.createElement('div');
        colAddress.className = 'col-md-12';
        var labelAddress = document.createElement('label');
        labelAddress.className = 'form-label required';
        labelAddress.textContent = 'Address';
        var textarea = document.createElement('textarea');
        textarea.className = 'form-control address-input';
        textarea.rows = 2;
        textarea.value = address && address.address ? address.address : '';
        colAddress.appendChild(labelAddress);
        colAddress.appendChild(textarea);
        row.appendChild(colAddress);

        var colCountry = document.createElement('div');
        colCountry.className = 'col-md-3';
        var labelCountry = document.createElement('label');
        labelCountry.className = 'form-label required';
        labelCountry.textContent = 'Country';
        var selectCountry = document.createElement('select');
        selectCountry.className = 'form-select address-country';
        selectCountry.setAttribute('data-placeholder', 'Select country');
        colCountry.appendChild(labelCountry);
        colCountry.appendChild(selectCountry);
        row.appendChild(colCountry);

        var colState = document.createElement('div');
        colState.className = 'col-md-3';
        var labelState = document.createElement('label');
        labelState.className = 'form-label required';
        labelState.textContent = 'Region/State';
        var selectState = document.createElement('select');
        selectState.className = 'form-select address-state';
        selectState.setAttribute('data-placeholder', 'Select state');
        colState.appendChild(labelState);
        colState.appendChild(selectState);
        row.appendChild(colState);

        var colCity = document.createElement('div');
        colCity.className = 'col-md-3';
        var labelCity = document.createElement('label');
        labelCity.className = 'form-label required';
        labelCity.textContent = 'City';
        var inputCity = document.createElement('input');
        inputCity.type = 'text';
        inputCity.className = 'form-control address-city';
        inputCity.value = address && address.city ? address.city : '';
        colCity.appendChild(labelCity);
        colCity.appendChild(inputCity);
        row.appendChild(colCity);

        var colZip = document.createElement('div');
        colZip.className = 'col-md-3';
        var labelZip = document.createElement('label');
        labelZip.className = 'form-label required';
        labelZip.textContent = 'Zip Code';
        var inputZip = document.createElement('input');
        inputZip.type = 'text';
        inputZip.className = 'form-control address-postal';
        inputZip.value = address && address.postal_code ? address.postal_code : '';
        colZip.appendChild(labelZip);
        colZip.appendChild(inputZip);
        row.appendChild(colZip);

        var actionsRow = document.createElement('div');
        actionsRow.className = 'mt-3 d-flex justify-content-end gap-2';
        var saveButton = document.createElement('button');
        saveButton.type = 'button';
        saveButton.className = 'btn btn-primary btn-sm address-save';
        saveButton.textContent = 'Save';
        var deleteButton = document.createElement('button');
        deleteButton.type = 'button';
        deleteButton.className = 'btn btn-outline-danger btn-sm address-delete';
        deleteButton.textContent = 'Remove';
        actionsRow.appendChild(deleteButton);
        actionsRow.appendChild(saveButton);
        wrapper.appendChild(actionsRow);

        fetchCountries().then(function (list) {
            var selectedCountryId = address && address.country_id ? address.country_id : null;
            populateCountrySelect(selectCountry, list, selectedCountryId);
            if (selectedCountryId) {
                fetchStates(selectedCountryId).then(function (states) {
                    selectState.innerHTML = '';
                    var placeholderState = document.createElement('option');
                    placeholderState.value = '';
                    placeholderState.textContent = selectState.getAttribute('data-placeholder') || 'Select state';
                    selectState.appendChild(placeholderState);
                    states.forEach(function (state) {
                        var opt = document.createElement('option');
                        opt.value = String(state.id);
                        opt.textContent = state.name;
                        if (address && address.state_id && String(address.state_id) === String(state.id)) {
                            opt.selected = true;
                        }
                        selectState.appendChild(opt);
                    });
                });
            } else {
                selectState.innerHTML = '';
                var placeholder = document.createElement('option');
                placeholder.value = '';
                placeholder.textContent = selectState.getAttribute('data-placeholder') || 'Select state';
                selectState.appendChild(placeholder);
            }
        });

        selectCountry.addEventListener('change', function () {
            var countryId = this.value || null;
            fetchStates(countryId).then(function (states) {
                selectState.innerHTML = '';
                var placeholderState = document.createElement('option');
                placeholderState.value = '';
                placeholderState.textContent = selectState.getAttribute('data-placeholder') || 'Select state';
                selectState.appendChild(placeholderState);
                states.forEach(function (state) {
                    var opt = document.createElement('option');
                    opt.value = String(state.id);
                    opt.textContent = state.name;
                    selectState.appendChild(opt);
                });
            });
        });

        saveButton.addEventListener('click', function () {
            var payload = {
                factory_id: factoryId,
                type: type,
                address: textarea.value || '',
                country_id: selectCountry.value || '',
                state_id: selectState.value || '',
                city: inputCity.value || '',
                postal_code: inputZip.value || ''
            };

            var url;
            var method;
            var isUpdate = !!wrapper.dataset.id;
            if (isUpdate) {
                url = routes.addressesUpdate ? routes.addressesUpdate.replace(':id', wrapper.dataset.id) : null;
                method = 'PUT';
            } else {
                url = routes.addressesStore || null;
                method = 'POST';
            }
            if (!url) return;

            saveButton.disabled = true;
            deleteButton.disabled = true;

            fetch(url, {
                method: method,
                headers: {
                    Authorization: 'Bearer ' + token,
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': config.csrfToken
                },
                body: JSON.stringify(payload)
            })
                .then(parseJsonResponse)
                .then(function (result) {
                    var status = result.status;
                    var body = result.body || {};
                    if (status >= 200 && status < 300 && body.success) {
                        if (body.data && body.data.address && body.data.address.id) {
                            wrapper.dataset.id = String(body.data.address.id);
                        }
                        if (window.toastr) {
                            window.toastr.success(body.message || 'Address saved successfully.');
                        }
                    } else {
                        var errors = collectErrors(body);
                        var message = body.message || 'Failed to save address.';
                        if (errors.length) {
                            message += ' ' + errors.join(' ');
                        }
                        if (window.toastr) {
                            if (errors.length) {
                                errors.forEach(function (msg) {
                                    window.toastr.error(msg);
                                });
                            } else {
                                window.toastr.error(message);
                            }
                        }
                    }
                })
                .catch(function () {
                    if (window.toastr) {
                        window.toastr.error('Failed to save address.');
                    }
                })
                .finally(function () {
                    saveButton.disabled = false;
                    deleteButton.disabled = false;
                });
        });

        deleteButton.addEventListener('click', function () {
            if (!wrapper.dataset.id) {
                if (wrapper.parentNode) {
                    wrapper.parentNode.removeChild(wrapper);
                }
                return;
            }
            var url = routes.addressesDestroy ? routes.addressesDestroy.replace(':id', wrapper.dataset.id) : null;
            if (!url) return;
            deleteButton.disabled = true;
            saveButton.disabled = true;

            fetch(url + '?factory_id=' + factoryId, {
                method: 'DELETE',
                headers: {
                    Authorization: 'Bearer ' + token,
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': config.csrfToken
                }
            })
                .then(parseJsonResponse)
                .then(function (result) {
                    var status = result.status;
                    var body = result.body || {};
                    if (status >= 200 && status < 300 && body.success) {
                        if (wrapper.parentNode) {
                            wrapper.parentNode.removeChild(wrapper);
                        }
                        if (window.toastr) {
                            window.toastr.success(body.message || 'Address deleted successfully.');
                        }
                    } else {
                        var errors = collectErrors(body);
                        var message = body.message || 'Failed to delete address.';
                        if (errors.length) {
                            message += ' ' + errors.join(' ');
                        }
                        if (window.toastr) {
                            if (errors.length) {
                                errors.forEach(function (msg) {
                                    window.toastr.error(msg);
                                });
                            } else {
                                window.toastr.error(message);
                            }
                        }
                    }
                })
                .catch(function () {
                    if (window.toastr) {
                        window.toastr.error('Failed to delete address.');
                    }
                })
                .finally(function () {
                    deleteButton.disabled = false;
                    saveButton.disabled = false;
                });
        });

        return wrapper;
    }

    function loadAddresses() {
        if (!routes.addressesIndex || !facilityAddressList || !distAddressList) {
            return;
        }
        if (addressesLoading) {
            addressesLoading.classList.remove('d-none');
        }
        facilityAddressList.innerHTML = '';
        distAddressList.innerHTML = '';

        var url = routes.addressesIndex + '?factory_id=' + factoryId;

        fetch(url, {
            headers: {
                Authorization: 'Bearer ' + token,
                Accept: 'application/json'
            }
        })
            .then(function (res) {
                return res.json();
            })
            .then(function (data) {
                if (!data || !data.success || !data.data || !Array.isArray(data.data.addresses)) {
                    return;
                }
                data.data.addresses.forEach(function (address) {
                    if (address.type === 'facility' && facilityAddressList) {
                        facilityAddressList.appendChild(renderAddressCard('facility', address));
                    } else if (address.type === 'dist_center' && distAddressList) {
                        distAddressList.appendChild(renderAddressCard('dist_center', address));
                    }
                });
            })
            .catch(function () { })
            .finally(function () {
                if (addressesLoading) {
                    addressesLoading.classList.add('d-none');
                }
            });
    }

    function fillPrimaryContact(factory) {
        if (!factory || !primaryForm) {
            return;
        }
        var firstName = document.getElementById('primary_first_name');
        var lastName = document.getElementById('primary_last_name');
        var email = document.getElementById('primary_email');
        var phone = document.getElementById('primary_phone_number');
        if (firstName) firstName.value = factory.first_name || '';
        if (lastName) lastName.value = factory.last_name || '';
        if (email) email.value = factory.email || '';
        if (phone) phone.value = factory.phone_number || '';
    }

    function fillSecondaryContact(contact) {
        if (!secondaryForm || !contact) {
            return;
        }
        var firstName = document.getElementById('secondary_first_name');
        var lastName = document.getElementById('secondary_last_name');
        var email = document.getElementById('secondary_email');
        var phone = document.getElementById('secondary_phone_number');
        if (firstName) firstName.value = contact.first_name || '';
        if (lastName) lastName.value = contact.last_name || '';
        if (email) email.value = contact.email || '';
        if (phone) phone.value = contact.phone_number || '';
    }

    function loadSecondaryContact() {
        if (!routes.secondaryContactShow || !secondaryForm) {
            return;
        }
        var url = routes.secondaryContactShow + '?factory_id=' + factoryId;
        fetch(url, {
            headers: {
                Authorization: 'Bearer ' + token,
                Accept: 'application/json'
            }
        })
            .then(function (res) {
                return res.json();
            })
            .then(function (data) {
                if (data && data.success && data.data && data.data.secondary_contact) {
                    fillSecondaryContact(data.data.secondary_contact);
                }
            })
            .catch(function () { });
    }

    if (form) {
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            if (!routes.businessInfoStore) return;

            clearAlert();
            clearFormFieldErrors(form);
            toggleSaveLoading(true);

            var formData = new FormData(form);
            formData.set('factory_id', factoryId);

            setFormDisabled(form, true);

            fetch(routes.businessInfoStore, {
                method: 'POST',
                headers: {
                    Authorization: 'Bearer ' + token,
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': config.csrfToken
                },
                body: formData
            })
                .then(parseJsonResponse)
                .then(function (result) {
                    var status = result.status;
                    var body = result.body || {};

                    if (status >= 200 && status < 300 && body.success) {
                        showAlert('success', body.message || 'Business information saved successfully.');
                        if (body.data && body.data.business) {
                            fillBusinessForm(body.data.business, document.querySelector('input[name="shipping_partner_id"]:checked') ? document.querySelector('input[name="shipping_partner_id"]:checked').value : null);
                        }
                        if (window.toastr) {
                            window.toastr.success(body.message || 'Business information saved successfully.');
                        }
                    } else {
                        var errors = collectErrors(body);
                        var message = body.message || 'Failed to save business information.';
                        if (errors.length) {
                            message += ' ' + errors.join(' ');
                        }
                        showAlert('error', message);
                        if (body && body.errors) {
                            applyFormErrors(form, body.errors);
                        }
                        if (window.toastr) {
                            if (errors.length) {
                                errors.forEach(function (msg) {
                                    window.toastr.error(msg);
                                });
                            } else {
                                window.toastr.error(message);
                            }
                        }
                    }
                })
                .catch(function () {
                    showAlert('error', 'Failed to save business information.');
                    if (window.toastr) {
                        window.toastr.error('Failed to save business information.');
                    }
                })
                .finally(function () {
                    setFormDisabled(form, false);
                    toggleSaveLoading(false);
                });
        });
    }

    if (deliveryPartnerForm && deliveryPartnerSaveBtn) {
        deliveryPartnerForm.addEventListener('submit', function (e) {
            e.preventDefault();
            if (!routes.shippingPartnerUpdate) return;
            clearFormAlert(deliveryPartnerAlert);
            clearFormFieldErrors(deliveryPartnerForm);
            deliveryPartnerSaveBtn.disabled = true;
            if (deliveryPartnerSaveSpinner) {
                deliveryPartnerSaveSpinner.classList.remove('d-none');
            }

            var formData = new FormData(deliveryPartnerForm);
            formData.set('factory_id', factoryId);
            if (myzeApiUrlInput) {
                formData.set('myze_api_url', (myzeApiUrlInput.value || '').trim());
            }
            if (myzeApiTokenInput) {
                formData.set('myze_api_token', (myzeApiTokenInput.value || '').trim());
            }

            setFormDisabled(deliveryPartnerForm, true);

            fetch(routes.shippingPartnerUpdate, {
                method: 'POST',
                headers: {
                    Authorization: 'Bearer ' + token,
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': config.csrfToken
                },
                body: formData
            })
                .then(parseJsonResponse)
                .then(function (result) {
                    var status = result.status;
                    var body = result.body || {};
                    if (status >= 200 && status < 300 && body.success) {
                        showFormAlert(deliveryPartnerAlert, 'success', body.message || 'Delivery partner saved successfully.');
                        if (window.toastr) {
                            window.toastr.success(body.message || 'Delivery partner saved successfully.');
                        }
                        if (body.data && body.data.shipping_partner_id) {
                            loadShippingPartners(body.data.shipping_partner_id);
                        }
                        if (body.data) {
                            if (body.data.myze_api_url !== undefined && myzeApiUrlInput) {
                                myzeApiUrlInput.value = body.data.myze_api_url || '';
                            }
                            if (body.data.myze_api_token !== undefined && myzeApiTokenInput) {
                                myzeApiTokenInput.value = body.data.myze_api_token || '';
                            }
                        }
                    } else {
                        var errors = collectErrors(body);
                        var message = body.message || 'Failed to save delivery partner.';
                        if (errors.length) {
                            message += ' ' + errors.join(' ');
                        }
                        showFormAlert(deliveryPartnerAlert, 'error', message);
                        if (body && body.errors) {
                            applyFormErrors(deliveryPartnerForm, body.errors);
                        }
                        if (window.toastr) {
                            if (errors.length) {
                                errors.forEach(function (msg) {
                                    window.toastr.error(msg);
                                });
                            } else {
                                window.toastr.error(message);
                            }
                        }
                    }
                })
                .catch(function () {
                    showFormAlert(deliveryPartnerAlert, 'error', 'Failed to save delivery partner.');
                    if (window.toastr) {
                        window.toastr.error('Failed to save delivery partner.');
                    }
                })
                .finally(function () {
                    setFormDisabled(deliveryPartnerForm, false);
                    deliveryPartnerSaveBtn.disabled = false;
                    if (deliveryPartnerSaveSpinner) {
                        deliveryPartnerSaveSpinner.classList.add('d-none');
                    }
                });
        });
    }

    if (primaryForm && primarySaveBtn) {
        primaryForm.addEventListener('submit', function (e) {
            e.preventDefault();
            if (!routes.accountUpdate) return;
            clearFormAlert(primaryAlert);
            clearFormFieldErrors(primaryForm);
            primarySaveBtn.disabled = true;
            if (primarySaveSpinner) {
                primarySaveSpinner.classList.remove('d-none');
            }

            var payload = {
                factory_id: factoryId,
                first_name: document.getElementById('primary_first_name') ? document.getElementById('primary_first_name').value : '',
                last_name: document.getElementById('primary_last_name') ? document.getElementById('primary_last_name').value : '',
                email: document.getElementById('primary_email') ? document.getElementById('primary_email').value : '',
                phone_number: document.getElementById('primary_phone_number') ? document.getElementById('primary_phone_number').value : ''
            };

            setFormDisabled(primaryForm, true);

            fetch(routes.accountUpdate, {
                method: 'PUT',
                headers: {
                    Authorization: 'Bearer ' + token,
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': config.csrfToken
                },
                body: JSON.stringify(payload)
            })
                .then(parseJsonResponse)
                .then(function (result) {
                    var status = result.status;
                    var body = result.body || {};
                    if (status >= 200 && status < 300 && body.success) {
                        showFormAlert(primaryAlert, 'success', body.message || 'Primary contact saved successfully.');
                        if (window.toastr) {
                            window.toastr.success(body.message || 'Primary contact saved successfully.');
                        }
                    } else {
                        var errors = collectErrors(body);
                        var message = body.message || 'Failed to save primary contact.';
                        if (errors.length) {
                            message += ' ' + errors.join(' ');
                        }
                        showFormAlert(primaryAlert, 'error', message);
                        if (body && body.errors) {
                            applyFormErrors(primaryForm, body.errors);
                        }
                        if (window.toastr) {
                            if (errors.length) {
                                errors.forEach(function (msg) {
                                    window.toastr.error(msg);
                                });
                            } else {
                                window.toastr.error(message);
                            }
                        }
                    }
                })
                .catch(function () {
                    showFormAlert(primaryAlert, 'error', 'Failed to save primary contact.');
                    if (window.toastr) {
                        window.toastr.error('Failed to save primary contact.');
                    }
                })
                .finally(function () {
                    setFormDisabled(primaryForm, false);
                    primarySaveBtn.disabled = false;
                    if (primarySaveSpinner) {
                        primarySaveSpinner.classList.add('d-none');
                    }
                });
        });
    }

    if (secondaryForm && secondarySaveBtn) {
        secondaryForm.addEventListener('submit', function (e) {
            e.preventDefault();
            if (!routes.secondaryContactStore) return;
            clearFormAlert(secondaryAlert);
            clearFormFieldErrors(secondaryForm);
            secondarySaveBtn.disabled = true;
            if (secondarySaveSpinner) {
                secondarySaveSpinner.classList.remove('d-none');
            }

            var formData = new FormData(secondaryForm);
            formData.set('factory_id', factoryId);

            setFormDisabled(secondaryForm, true);

            fetch(routes.secondaryContactStore, {
                method: 'POST',
                headers: {
                    Authorization: 'Bearer ' + token,
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': config.csrfToken
                },
                body: formData
            })
                .then(parseJsonResponse)
                .then(function (result) {
                    var status = result.status;
                    var body = result.body || {};
                    if (status >= 200 && status < 300 && body.success) {
                        showFormAlert(secondaryAlert, 'success', body.message || 'Secondary contact saved successfully.');
                        if (body.data && body.data.secondary_contact) {
                            fillSecondaryContact(body.data.secondary_contact);
                        }
                        if (window.toastr) {
                            window.toastr.success(body.message || 'Secondary contact saved successfully.');
                        }
                    } else {
                        var errors = collectErrors(body);
                        var message = body.message || 'Failed to save secondary contact.';
                        if (errors.length) {
                            message += ' ' + errors.join(' ');
                        }
                        showFormAlert(secondaryAlert, 'error', message);
                        if (body && body.errors) {
                            applyFormErrors(secondaryForm, body.errors);
                        }
                        if (window.toastr) {
                            if (errors.length) {
                                errors.forEach(function (msg) {
                                    window.toastr.error(msg);
                                });
                            } else {
                                window.toastr.error(message);
                            }
                        }
                    }
                })
                .catch(function () {
                    showFormAlert(secondaryAlert, 'error', 'Failed to save secondary contact.');
                    if (window.toastr) {
                        window.toastr.error('Failed to save secondary contact.');
                    }
                })
                .finally(function () {
                    setFormDisabled(secondaryForm, false);
                    secondarySaveBtn.disabled = false;
                    if (secondarySaveSpinner) {
                        secondarySaveSpinner.classList.add('d-none');
                    }
                });
        });
    }

    if (resetBtn) {
        resetBtn.addEventListener('click', function () {
            loadBusinessInfo();
        });
    }

    var certificateChangeButtons = document.querySelectorAll('.certificate-row .certificate-change');
    certificateChangeButtons.forEach(function (btn) {
        btn.addEventListener('click', function () {
            var group = btn.closest('.certificate-row');
            if (!group) return;
            var existingRow = group.querySelector('.certificate-existing');
            var uploadRow = group.querySelector('.certificate-upload');
            if (existingRow) {
                existingRow.classList.add('d-none');
            }
            if (uploadRow) {
                uploadRow.classList.remove('d-none');
            }
        });
    });

    var allowedCertificateExtensions = ['pdf', 'jpg', 'jpeg', 'png'];
    function attachCertificateValidator(id) {
        var input = document.getElementById(id);
        if (!input) return;
        input.addEventListener('change', function () {
            if (!input.files || !input.files.length) {
                input.classList.remove('is-invalid');
                var form = input.closest('form');
                if (form) {
                    var fb = form.querySelector('.invalid-feedback[data-error-for="' + input.name + '"]');
                    if (fb) {
                        fb.textContent = '';
                    }
                }
                return;
            }
            var file = input.files[0];
            var name = file.name || '';
            var ext = '';
            var idx = name.lastIndexOf('.');
            if (idx !== -1) {
                ext = name.substring(idx + 1).toLowerCase();
            }
            if (allowedCertificateExtensions.indexOf(ext) === -1) {
                var message = window.transInvalidCertFile || 'Allowed file types: PDF, JPG, JPEG, PNG.';
                input.value = '';
                showInputError(input, message);
                if (window.toastr) {
                    window.toastr.error(message);
                }
            } else {
                input.classList.remove('is-invalid');
                var form = input.closest('form');
                if (form) {
                    var fb2 = form.querySelector('.invalid-feedback[data-error-for="' + input.name + '"]');
                    if (fb2) {
                        fb2.textContent = '';
                    }
                }
            }
        });
    }

    attachCertificateValidator('registration_certificate');
    attachCertificateValidator('tax_certificate');
    attachCertificateValidator('import_export_certificate');

    var countrySelect = document.getElementById('country_id');
    if (countrySelect) {
        countrySelect.addEventListener('change', function () {
            var countryId = this.value || null;
            loadStates(countryId, null);
        });
    }

    if (addFacilityAddressBtn && facilityAddressList) {
        addFacilityAddressBtn.addEventListener('click', function () {
            facilityAddressList.appendChild(renderAddressCard('facility', null));
        });
    }

    if (addDistAddressBtn && distAddressList) {
        addDistAddressBtn.addEventListener('click', function () {
            distAddressList.appendChild(renderAddressCard('dist_center', null));
        });
    }

    loadFactory();
    loadBusinessInfo();
    loadAddresses();
    loadSecondaryContact();
});
