document.addEventListener('DOMContentLoaded', () => {
    /* =====================================================
     * CONFIG & AUTH
     * ===================================================== */
    const CONFIG = window.showCustomer;
    if (!CONFIG) {
        console.error('showCustomer config missing');
        return;
    }

    const authToken = getCookie('jwt_token');
    if (!authToken) {
        toastr.error('Authentication token missing');
        return;
    }

    /* =====================================================
     * DOM REFERENCES
     * ===================================================== */
    const formEl = document.querySelector('form');
    const countrySelectEl = document.querySelector('.country');
    const stateSelectEl = document.querySelector('.state');

    const saveBtn = document.getElementById('saveBtn');
    const saveSpinner = document.getElementById('saveSpinner');
    const pageLoader = document.getElementById('pageLoader');

    let existingBillingAddress = null;

    /* =====================================================
     * UI HELPERS
     * ===================================================== */
    const showPageLoader = () => pageLoader?.classList.remove('d-none');
    const hidePageLoader = () => pageLoader?.classList.add('d-none');

    const startSaving = () => {
        saveBtn.disabled = true;
        saveSpinner.classList.remove('d-none');
    };

    const stopSaving = () => {
        saveBtn.disabled = false;
        saveSpinner.classList.add('d-none');
    };

    const setValue = (selector, value) => {
        const el = document.querySelector(selector);
        if (el && value !== null && value !== undefined) {
            el.value = value;
        }
    };

    /* =====================================================
     * API HELPERS
     * ===================================================== */
    const apiGet = async (url) => {
        const res = await fetch(url, {
            headers: {
                Accept: 'application/json',
                Authorization: `Bearer ${authToken}`,
            },
        });

        if (!res.ok) {
            throw new Error('Request failed');
        }

        return res.json();
    };

    const apiPost = async (url, payload, method = 'POST') => {
        const res = await fetch(url, {
            method,
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                Authorization: `Bearer ${authToken}`,
            },
            body: JSON.stringify(payload),
        });

        const json = await res.json();

        if (!res.ok || !json.success) {
            throw new Error(json.message || 'Save failed');
        }

        return json;
    };

    const apiPatch = async (url, payload) => {
        const res = await fetch(url, {
            method: 'PATCH',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                Authorization: `Bearer ${authToken}`,
            },
            body: JSON.stringify(payload),
        });

        const json = await res.json();

        if (!res.ok || !json.success) {
            throw new Error(json.message || 'Update failed');
        }

        return json;
    };


    /* =====================================================
     * SELECT2 INIT
     * ===================================================== */
    $(countrySelectEl).select2({
        placeholder: 'Select Country',
        width: '100%',
    });

    $(stateSelectEl).select2({
        placeholder: 'Select State',
        width: '100%',
    });

    /* =====================================================
     * COUNTRY / STATE
     * ===================================================== */
    const resetStateSelect = (label = 'Select State', disabled = true) => {
        stateSelectEl.innerHTML = `<option value="">${label}</option>`;
        stateSelectEl.disabled = disabled;
        $(stateSelectEl).val(null).trigger('change');
    };

    const loadCountries = async () => {
        try {
            countrySelectEl.innerHTML = `<option value="">Select Country</option>`;
            const { data } = await apiGet(CONFIG.countryApiUrl);

            data.forEach(country => {
                if (!country.is_allowed) return;

                const opt = document.createElement('option');
                opt.value = country.id;
                opt.textContent = country.name;
                opt.dataset.hasStates = country.is_state_available;

                if (country.is_default) opt.selected = true;
                countrySelectEl.appendChild(opt);
            });

            $(countrySelectEl).trigger('change');

            const selected = countrySelectEl.selectedOptions[0];
            if (selected?.dataset?.hasStates === '1') {
                await loadStates(selected.value);
            } else {
                resetStateSelect();
            }
        } catch {
            toastr.error('Failed to load countries');
        }
    };

    const loadStates = async (countryId) => {
        try {
            resetStateSelect('Loading states...', true);

            const url = CONFIG.stateApiUrl.replace(':country', countryId);
            const { data } = await apiGet(url);

            if (!Array.isArray(data) || !data.length) {
                resetStateSelect('No states available', true);
                return;
            }

            stateSelectEl.innerHTML = `<option value="">Select State</option>`;
            stateSelectEl.disabled = false;

            data.forEach(state => {
                const opt = document.createElement('option');
                opt.value = state.id;
                opt.textContent = state.name;
                stateSelectEl.appendChild(opt);
            });

            $(stateSelectEl).trigger('change');
        } catch {
            toastr.error('Failed to load states');
            resetStateSelect('Failed to load states', true);
        }
    };

    countrySelectEl.addEventListener('change', (e) => {
        const opt = e.target.selectedOptions[0];
        if (opt?.dataset?.hasStates !== '1') {
            resetStateSelect('No states available', true);
            return;
        }
        loadStates(opt.value);
    });

    /* =====================================================
     * LOAD CUSTOMER DATA
     * ===================================================== */
    const loadCustomerProfile = async () => {
        try {
            const url = new URL(CONFIG.customerApiUrl, window.location.origin);
            url.searchParams.set('customer_id', CONFIG.customerId);

            const { customer } = await apiGet(url);
            if (!customer) return;

            setValue('input[name="primary_first_name"]', customer.first_name);
            setValue('input[name="primary_last_name"]', customer.last_name);
            setValue('input[name="primary_email"]', customer.email);
            setValue('input[name="primary_phone"]', customer.phone);
        } catch {
            toastr.error('Failed to load customer details');
        }
    };

    const loadBillingAddress = async () => {
        try {
            const url = new URL(CONFIG.billingDetailsApiUrl, window.location.origin);
            url.searchParams.set('customer_id', CONFIG.customerId);

            const { data } = await apiGet(url);
            if (!data) return;

            existingBillingAddress = data;

            setValue('input[name="company_name"]', data.company_name);
            setValue('input[name="tax_vat_number"]', data.tax_number);
            setValue('input[name="registered_address"]', data.address);
            setValue('input[name="city"]', data.city);
            setValue('input[name="postal_code"]', data.postal_code);

            setValue('select.country', data.country_id);
            $(countrySelectEl).trigger('change');

            if (data.country_id) {
                await loadStates(data.country_id);
                setValue('select.state', data.state_id);
                $(stateSelectEl).trigger('change');
            }
        } catch {
            toastr.error('Failed to load billing details');
        }
    };

    /* =====================================================
     * SAVE BILLING INFO
     * ===================================================== */
    formEl.addEventListener('submit', async (e) => {
        e.preventDefault();

        const customerPayload = {
            customer_id: CONFIG.customerId,
            first_name: document.querySelector('input[name="primary_first_name"]')?.value || null,
            last_name: document.querySelector('input[name="primary_last_name"]')?.value || null,
            phone: document.querySelector('input[name="primary_phone"]')?.value || null,
        };

        const billingPayload = {
            customer_id: CONFIG.customerId,
            address: document.querySelector('input[name="registered_address"]')?.value || null,
            country_id: countrySelectEl.value || null,
            state_id: stateSelectEl.value || null,
            city: document.querySelector('input[name="city"]')?.value || null,
            postal_code: document.querySelector('input[name="postal_code"]')?.value || null,
            company_name: document.querySelector('input[name="company_name"]')?.value || null,
            tax_number: document.querySelector('input[name="tax_vat_number"]')?.value || null,
            is_default: true,
        };

        try {
            startSaving();
            await apiPatch(CONFIG.saveCustomerApiUrl, customerPayload);
            await apiPost(CONFIG.saveBillingDetailsApiUrl, billingPayload);
            toastr.success('Customer & business information saved successfully');
        } catch (err) {
            toastr.error(err.message || 'Failed to save business information');
        } finally {
            stopSaving();
        }
    });

    /* =====================================================
     * INIT
     * ===================================================== */
    (async () => {
        showPageLoader();

        resetStateSelect();
        await loadCountries();
        await loadCustomerProfile();
        await loadBillingAddress();

        hidePageLoader();
    })();
});
