document.addEventListener('DOMContentLoaded', () => {
    if (window.ShippingRatesConfig) {
        window.shippingManager = new ShippingManager(window.ShippingRatesConfig);
    }
});

class ShippingManager {
    constructor(config) {
        this.config = config;

        this.dom = {
            container: document.getElementById('shippingRatesContainer'),
            template: document.getElementById('shippingRateTemplate'),
            addBtn: document.getElementById('addMoreRates'),
            form: document.getElementById('shippingRatesForm'),
            submitBtn: document.getElementById('saveRatesBtn'),
            importForm: document.getElementById('importForm'),
            exportBtn: document.getElementById('exportRatesBtn'),
            searchInput: document.getElementById('shippingRatesSearch'),
            pagination: document.getElementById('shippingRatesPagination'),
            filterFactory: document.getElementById('shippingFilterFactory'),
            filterCountry: document.getElementById('shippingFilterCountry'),
            perPageSelect: document.getElementById('shippingPerPage'),
            sortBySelect: document.getElementById('shippingSortBy'),
            sortDirSelect: document.getElementById('shippingSortDir'),
        };

        this.cachedCountries = null;
        this.pagination = null;
        this.state = {
            page: 1,
            search: '',
            factoryId: null,
            countryCode: '',
            perPage: 50,
            sortBy: '',
            sortDir: 'desc',
        };
        this.isSubmitting = false;
        this.loadRatesDebounced = this.debounce(this.loadRates, 300);
        this.init();
    }

    async init() {
        this.bindEvents();
        this.initFilterControls();
        await this.loadRates();
    }

    /* ==========================================
     | Events
     ========================================== */

    bindEvents() {
        this.dom.addBtn?.addEventListener('click', () => this.addRateRow());
        this.dom.form?.addEventListener('submit', (e) => this.handleSubmit(e));
        this.dom.importForm?.addEventListener('submit', (e) => this.handleImport(e));
        this.dom.exportBtn?.addEventListener('click', (e) => this.handleExport(e));

        this.dom.searchInput?.addEventListener('input', (e) => {
            var value = e.target.value || '';
            this.loadRatesDebounced({
                page: 1,
                search: value.trim(),
            });
        });

        this.dom.pagination?.addEventListener('click', (e) => {
            if (e.target.closest('.page-prev')) {
                if (this.pagination && this.pagination.current_page > 1) {
                    this.loadRates({
                        page: this.pagination.current_page - 1,
                    });
                }
            }

            if (e.target.closest('.page-next')) {
                if (this.pagination && this.pagination.current_page < this.pagination.last_page) {
                    this.loadRates({
                        page: this.pagination.current_page + 1,
                    });
                }
            }
        });

        this.dom.container?.addEventListener('click', (e) => {
            if (e.target.closest('.remove-rate')) {
                this.handleDelete(e.target.closest('.remove-rate'));
            }
        });
    }

    /* ==========================================
     | API Helper (JWT SAFE)
     ========================================== */

    async fetchJson(url, options = {}) {
        const headers = {
            Accept: 'application/json',
            Authorization: `Bearer ${this.getCookie('jwt_token')}`,
            ...options.headers,
        };

        const res = await fetch(url, {
            credentials: 'same-origin',
            ...options,
            headers,
        });

        if (!res.ok) {
            const error = new Error(res.statusText);
            error.status = res.status;
            try { error.data = await res.json(); } catch { }
            throw error;
        }

        return res.json();
    }

    /**
     * Debounce a function so it runs only after the delay has passed
     * without any new calls.
     *
     * @template TArgs
     * @param {(...args: TArgs[]) => void | Promise<void>} fn
     * @param {number} [delay=300]
     * @returns {((...args: TArgs[]) => void) & { cancel: () => void }}
     */
    debounce(fn, delay) {
        var wait = typeof delay === 'number' ? delay : 300;
        /** @type {number | null} */
        var timeoutId = null;

        /**
         * @param {...TArgs[]} args
         */
        function debounced() {
            var context = this;
            var args = arguments;

            if (timeoutId !== null) {
                clearTimeout(timeoutId);
                timeoutId = null;
            }

            timeoutId = window.setTimeout(function () {
                timeoutId = null;
                try {
                    fn.apply(context, args);
                } catch (error) {
                    console.error('[ShippingManager] Debounced function error', error);
                }
            }, wait);
        }

        debounced.cancel = function () {
            if (timeoutId !== null) {
                clearTimeout(timeoutId);
                timeoutId = null;
            }
        };

        return debounced;
    }

    /* ==========================================
     | Load Data
     ========================================== */

    initFilterControls() {
        // 1. FACTORY FILTER
        if (this.dom.filterFactory) {
            this.initFilterFactorySelect(this.dom.filterFactory);

            // FIX: Use jQuery .on('change') instead of .addEventListener
            $(this.dom.filterFactory).on('change', (e) => {
                var value = $(e.target).val() || ''; // Get value via jQuery
                var id = parseInt(value, 10);
                var factoryId = Number.isFinite(id) && id > 0 ? id : null;

                this.loadRates({
                    page: 1,
                    factoryId: factoryId,
                });
            });
        }

        // 2. COUNTRY FILTER
        if (this.dom.filterCountry) {
            this.initFilterCountrySelect(this.dom.filterCountry);

            // FIX: Use jQuery .on('change')
            $(this.dom.filterCountry).on('change', (e) => {
                var value = ($(e.target).val() || '').toString().trim().toUpperCase();
                var countryCode = value.length === 2 ? value : '';

                this.loadRates({
                    page: 1,
                    countryCode: countryCode,
                });
            });
        }

        // Keep the rest (Per Page, Sort) as standard listeners since they are native selects
        this.dom.perPageSelect?.addEventListener('change', (e) => {
            var value = parseInt(e.target.value || '', 10);
            var allowed = [10, 25, 50, 100];
            var perPage = allowed.indexOf(value) !== -1 ? value : 50;
            this.loadRates({ page: 1, perPage: perPage });
        });

        this.dom.sortBySelect?.addEventListener('change', (e) => {
            this.loadRates({ page: 1, sortBy: e.target.value });
        });

        this.dom.sortDirSelect?.addEventListener('change', (e) => {
            this.loadRates({ page: 1, sortDir: e.target.value });
        });
    }

    async loadRates(params = {}) {
        this.toggleLoading(true);

        if (params && typeof params === 'object') {
            this.state = {
                ...this.state,
                ...params,
            };
        }

        const searchParams = new URLSearchParams();

        if (this.state.search) {
            searchParams.set('search', this.state.search);
        }

        if (this.state.page && this.state.page > 1) {
            searchParams.set('page', String(this.state.page));
        }

        if (this.state.factoryId) {
            searchParams.set('factory_id', String(this.state.factoryId));
        }

        if (this.state.countryCode) {
            searchParams.set('country_code', this.state.countryCode);
        }

        if (this.state.perPage) {
            searchParams.set('per_page', String(this.state.perPage));
        }

        if (this.state.sortBy) {
            searchParams.set('sort_by', this.state.sortBy);
        }

        if (this.state.sortDir) {
            searchParams.set('sort_dir', this.state.sortDir);
        }

        const query = searchParams.toString();
        const url = query ? `${this.config.shippingRatesApiUrl}?${query}` : this.config.shippingRatesApiUrl;

        try {
            const res = await this.fetchJson(url);
            this.dom.container.innerHTML = '';

            const rates = res.data || [];
            this.pagination = res.meta || null;

            if (!rates.length) {
                await this.addRateRow();
            } else {
                for (const rate of rates) {
                    await this.addRateRow(rate);
                }
            }

            this.renderPagination();
        } catch (e) {
            console.error(e);
            this.showToast(this.config.i18n.loadError, 'error');
            await this.addRateRow();
        } finally {
            this.toggleLoading(false);
        }
    }

    async getCountries() {
        if (this.cachedCountries) return this.cachedCountries;

        try {
            const res = await this.fetchJson(this.config.countryApiUrl);
            this.cachedCountries = (res.data || []).map(c => ({
                id: c.iso2,
                text: c.name,
            }));
            return this.cachedCountries;
        } catch {
            return [];
        }
    }

    /* ==========================================
     | DOM
     ========================================== */

    async addRateRow(data = null) {
        const clone = this.dom.template.content.cloneNode(true);
        const row = clone.querySelector('.shipping-rate');

        if (data) {
            row.dataset.rateId = data.id;
            row.querySelector('[name="rate_id[]"]').value = data.id;
            row.querySelector('[name="shipping_title[]"]').value = data.shipping_title;
            row.querySelector('[name="min_qty[]"]').value = data.min_qty;
            row.querySelector('[name="price[]"]').value = data.price;
        }

        const factorySelect = row.querySelector('.factory-select');
        const countrySelect = row.querySelector('.country-select');

        this.dom.container.appendChild(row);

        this.initFactorySelect(factorySelect, data?.factory);
        await this.initCountrySelect(countrySelect, data?.country_code);

        this.updateUIState();
    }

    updateUIState() {
        const rows = this.dom.container.querySelectorAll('.shipping-rate');
        this.dom.container
            .querySelectorAll('.remove-rate')
            .forEach(btn => btn.disabled = rows.length <= 1);
    }

    renderPagination() {
        if (!this.dom.pagination) return;

        const meta = this.pagination;

        if (!meta || !meta.last_page || meta.last_page <= 1) {
            this.dom.pagination.innerHTML = '';
            return;
        }

        const prevDisabled = meta.current_page <= 1 ? 'disabled' : '';
        const nextDisabled = meta.current_page >= meta.last_page ? 'disabled' : '';

        const labelTemplate = this.config.i18n.pageInfo || '';
        const label = labelTemplate
            .replace(':current', meta.current_page)
            .replace(':last', meta.last_page);

        this.dom.pagination.innerHTML = `
            <div class="d-flex align-items-center gap-2">
                <button type="button" class="btn btn-outline-secondary btn-sm page-prev" ${prevDisabled}>
                    ${this.config.i18n.prevPage}
                </button>
                <span class="small text-muted">${label}</span>
                <button type="button" class="btn btn-outline-secondary btn-sm page-next" ${nextDisabled}>
                    ${this.config.i18n.nextPage}
                </button>
            </div>
        `;
    }

    toggleLoading(state) {
        this.dom.container.classList.toggle('opacity-50', state);
        this.dom.container.classList.toggle('pe-none', state);
    }

    /* ==========================================
     | Select2
     ========================================== */

    initFactorySelect(el, preselected = null) {
        if (typeof $ === 'undefined' || !$.fn || !$.fn.select2) {
            return;
        }
        const $el = $(el);

        if (preselected) {
            const text = `${preselected.first_name ?? ''} ${preselected.last_name ?? ''}`.trim();
            const company = preselected.business?.company_name;
            $el.append(new Option(company ? `${text} (${company})` : text, preselected.id, true, true));
        }

        $el.select2({
            width: '100%',
            placeholder: this.config.i18n.searchFactory,
            ajax: {
                url: this.config.factoryApiUrl,
                delay: 300,
                headers: { Authorization: `Bearer ${this.getCookie('jwt_token')}` },
                data: p => ({ search: p.term, page: p.page || 1 }),
                processResults: res => ({
                    results: res.data.map(f => ({
                        id: f.id,
                        text: f.name,
                        company: f.business?.company_name,
                    })),
                    pagination: { more: res.meta?.current_page < res.meta?.last_page },
                }),
            },
            templateResult: r => r.loading ? r.text : $(`
                <div>
                    <strong>${r.text}</strong>
                    <div class="text-muted small">${r.company ?? 'No Company'}</div>
                </div>
            `),
            templateSelection: r => r.company ? `${r.text} (${r.company})` : r.text,
        });
    }

    initFilterFactorySelect(el) {
        if (typeof $ === 'undefined' || !$.fn || !$.fn.select2) {
            return;
        }
        const $el = $(el);
        if ($el.find('option[value=""]').length === 0) {
            $el.prepend('<option value=""></option>');
        }
        $el.select2({
            width: '100%',
            placeholder: $(el).data('placeholder') || this.config.i18n.searchFactory,
            allowClear: true,
            ajax: {
                url: this.config.factoryApiUrl,
                delay: 300,
                headers: { Authorization: `Bearer ${this.getCookie('jwt_token')}` },
                data: p => ({ search: p.term, page: p.page || 1 }),
                processResults: res => ({
                    results: (res.data || []).map(f => ({
                        id: f.id,
                        text: f.name,
                        company: f.business?.company_name,
                    })),
                    pagination: { more: res.meta?.current_page < res.meta?.last_page },
                }),
            },
            allowClear: true,
            templateResult: r => r.loading ? r.text : $(`
                <div>
                    <strong>${r.text}</strong>
                    <div class="text-muted small">${r.company ?? 'No Company'}</div>
                </div>
            `),
            templateSelection: r => r.company ? `${r.text} (${r.company})` : r.text,
        });
    }

    async initCountrySelect(el, selected) {
        if (typeof $ === 'undefined' || !$.fn || !$.fn.select2) {
            return;
        }
        const $el = $(el);
        $el.select2({
            width: '100%',
            placeholder: this.config.i18n.selectCountry,
            data: await this.getCountries(),
        });
        if (selected) $el.val(selected).trigger('change');
    }

    async initFilterCountrySelect(el) {
        if (typeof $ === 'undefined' || !$.fn || !$.fn.select2) {
            return;
        }
        const countries = await this.getCountries();
        const dataWithPlaceholder = [
        { id: '', text: '' }, 
        ...countries
    ];
        const $el = $(el);
        $el.select2({
            width: '100%',
            placeholder: $(el).data('placeholder') || this.config.i18n.selectCountry,
            allowClear: true,
            data: dataWithPlaceholder,
        });
    }

    /* ==========================================
     | Save / Delete
     ========================================== */

    async handleSubmit(e) {
        e.preventDefault();
        if (!this.dom.form.checkValidity()) return this.dom.form.reportValidity();

        if (this.isSubmitting) {
            return;
        }

        this.isSubmitting = true;
        this.setButtonLoading(this.dom.submitBtn, true);

        try {
            await this.fetchJson(this.config.saveShippingRateUrl, {
                method: 'POST',
                body: new FormData(this.dom.form),
            });
            this.showToast(this.config.i18n.ratesSavedSuccess);
            await this.loadRates();
        } catch (error) {
            console.error('[ShippingManager] Save request failed', error);
            this.showToast(this.config.i18n.saveFailed, 'error');
        } finally {
            this.isSubmitting = false;
            this.setButtonLoading(this.dom.submitBtn, false);
        }
    }

    async handleDelete(btn) {
        const row = btn.closest('.shipping-rate');
        const id = row.dataset.rateId;

        if (this.dom.container.querySelectorAll('.shipping-rate').length <= 1) {
            return this.showToast(this.config.i18n.minOne, 'warning');
        }

        const confirm = await Swal.fire({
            title: this.config.i18n.deleteTitle,
            text: this.config.i18n.deleteText,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
        });

        if (!confirm.isConfirmed) return;

        if (!id) return row.remove();

        await this.fetchJson(this.config.deleteShippingRateUrl.replace(':id', id), { method: 'DELETE' });
        row.remove();
        this.showToast(this.config.i18n.deletedSuccess);
    }

    /* ==========================================
     | Import / Export
     ========================================== */

    async handleImport(e) {
        e.preventDefault();
        const btn = this.dom.importForm.querySelector('button[type="submit"]');

        btn.disabled = true;
        btn.innerHTML = `<span class="spinner-border spinner-border-sm me-2"></span> Uploading...`;

        try {
            await fetch(this.config.importShippingRateUrl, {
                method: 'POST',
                headers: { Authorization: `Bearer ${this.getCookie('jwt_token')}` },
                body: new FormData(this.dom.importForm),
            });

            Swal.fire('Imported!', 'Shipping rates imported successfully', 'success');
            bootstrap.Modal.getInstance(document.getElementById('importModal')).hide();
            this.dom.importForm.reset();
            await this.loadRates();
        } catch {
            Swal.fire('Error', 'Import failed', 'error');
        } finally {
            btn.disabled = false;
            btn.innerHTML = 'Upload';
        }
    }

    async handleExport(e) {
        e.preventDefault();

        const res = await fetch(this.config.exportShippingRateUrl, {
            headers: { Authorization: `Bearer ${this.getCookie('jwt_token')}` },
        });

        const blob = await res.blob();
        const url = URL.createObjectURL(blob);

        const a = document.createElement('a');
        a.href = url;
        a.download = 'factory_shipping_rates.xlsx';
        a.click();

        URL.revokeObjectURL(url);
    }

    /* ==========================================
     | Helpers
     ========================================== */

    setButtonLoading(btn, state) {
        if (!btn) return;

        if (state) {
            if (!btn.dataset.originalText) {
                btn.dataset.originalText = btn.innerHTML;
            }
            btn.disabled = true;
            btn.innerHTML = `<span class="spinner-border spinner-border-sm me-2"></span> ${this.config.i18n.processing}`;
        } else {
            btn.disabled = false;
            if (btn.dataset.originalText) {
                btn.innerHTML = btn.dataset.originalText;
            }
        }
    }

    showToast(title, icon = 'success') {
        Swal.fire({ toast: true, position: 'top-end', icon, title, timer: 3000, showConfirmButton: false });
    }

    getCookie(name) {
        const v = `; ${document.cookie}`;
        const p = v.split(`; ${name}=`);
        return p.length === 2 ? p.pop().split(';').shift() : '';
    }
}
