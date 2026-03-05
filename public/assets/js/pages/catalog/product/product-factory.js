class FactoryAssignmentManager {
    constructor(config) {
        this.config = config;

        this.modal = $('#assignFactoryModal');
        this.select = $('#factory_ids');
        this.preview = document.getElementById('factory-assignment-preview');

        this.targetVariantId = null;

        this.init();
    }

    escapeHtml(value) {
        const div = document.createElement('div');
        div.innerText = value == null ? '' : String(value);
        return div.innerHTML;
    }

    /* -----------------------------------------
     * INIT
     * ----------------------------------------- */
    init() {
        this.bindAutoOpen();
        this.bindVariantAssignClick();
        this.initPreviewEvents();
        this.bindSyncEvents();
        this.bindFactoryCollapse();
        this.bindBulkActions();
        this.bindSubmit();
        this.initValidation();

    }

    /* -----------------------------------------
     * MODAL OPEN + PRODUCT FETCH
     * ----------------------------------------- */
    bindAutoOpen() {
        window.addEventListener('product:created', async (e) => {
            const productId = e.detail.productId;
            if (!productId) return;
            this.openModal(productId);
        });

        // Manual global open binding
        $('#openAssignFactoryModal').on('click', async () => {
            const productId = this.config.productId;
            if (!productId) return;
            this.setAssignButtonLoading(true);
            try {
                await this.openModal(productId);
            } finally {
                this.setAssignButtonLoading(false);
            }
        });

        this.modal.on('hidden.bs.modal', () => {
            this.select.val(null).trigger('change');
            this.preview.innerHTML = '';
            this.targetVariantId = null; // Reset target variant
            $('#assignSku').text(this.productData?.product?.sku || ''); // Reset title
        });
    }

    async openModal(productId) {
        this.config.productId = productId;
        const success = await this.fetchProductInfo(productId);
        if (!success) return;

        $('#assignSku').text(this.productData.product.sku);
        this.initSelect2();
        this.prepopulateFactories();
        this.modal.modal('show');
    }

    bindVariantAssignClick() {
        $(document).on('click', '.assign-variant-factory', async (e) => {
            const btn = $(e.currentTarget);
            const variantId = btn.data('variant-id');
            const variantSku = btn.data('variant-sku');

            // Ensure product info is loaded if not already
            await this.fetchProductInfo(this.config.productId);

            if (!this.productData) return;

            this.openForVariant(variantId, variantSku);
        });
    }

    openForVariant(variantId, variantSku) {
        this.targetVariantId = variantId;
        $('#assignSku').text(variantSku); // Update title to show Variant SKU
        this.initSelect2();
        this.select.val(null).trigger('change');
        this.preview.innerHTML = '';
        this.prepopulateFactories();
        this.modal.modal('show');
    }

    setSubmitLoading(isLoading) {
        const $btn = $('#assignFactorySubmit');
        const $spinner = $btn.find('.spinner-border');
        const $text = $btn.find('.btn-text');
        if (isLoading) {
            $btn.prop('disabled', true);
            $spinner.removeClass('d-none');
            $text.addClass('opacity-75');
        } else {
            $btn.prop('disabled', false);
            $spinner.addClass('d-none');
            $text.removeClass('opacity-75');
        }
    }

    setAssignButtonLoading(isLoading) {
        const btn = document.getElementById('openAssignFactoryModal');
        if (!btn) return;

        btn.disabled = !!isLoading;

        let spinner = btn.querySelector('.assign-factory-spinner');
        if (!spinner) {
            spinner = document.createElement('span');
            spinner.className = 'spinner-border spinner-border-sm ms-2 assign-factory-spinner';
            spinner.setAttribute('role', 'status');
            spinner.setAttribute('aria-hidden', 'true');
            btn.appendChild(spinner);
        }

        if (isLoading) {
            spinner.classList.remove('d-none');
        } else {
            spinner.classList.add('d-none');
        }
    }


    async fetchProductInfo(productId) {
        try {
            const url = this.config.urls.productInfo.replace(':id', productId);
            const res = await fetch(url, { headers: { Accept: 'application/json' } });

            if (!res.ok) throw new Error();
            this.productData = await res.json();
            return true;
        } catch (e) {
            Swal.fire('Error', 'Unable to load product information', 'error');
            return false;
        }
    }

    /* -----------------------------------------
     * SELECT2 FACTORY SEARCH
     * ----------------------------------------- */
    initSelect2() {
        if (this.selectInitialized) return;

        this.select.select2({
            dropdownParent: this.modal,
            width: '100%',
            placeholder: this.config.i18n.selectFactory,
            allowClear: true,
            minimumInputLength: 1,

            ajax: {
                url: this.config.urls.factories,
                dataType: 'json',
                delay: 300,
                cache: true,
                data: params => ({
                    q: params.term || '',
                    page: params.page || 1
                }),
                processResults: data => ({
                    results: data.results,
                    pagination: { more: data.pagination.more }
                })
            },

            escapeMarkup: m => m,
            templateResult: item =>
                item.loading ? item.text : `<div class="fw-semibold">${item.text}</div>`,

            templateSelection: item => item.text || item.id
        });

        this.selectInitialized = true;
    }

    /* -----------------------------------------
     * FACTORY SELECT / UNSELECT
     * ----------------------------------------- */
    initPreviewEvents() {
        this.select.on('select2:select', e => {
            this.renderFactoryCard(e.params.data);
        });

        this.select.on('select2:unselect', e => {
            this.removeFactoryCard(e.params.data.id);
        });
    }

    /* -----------------------------------------
     * FACTORY CARD RENDER
     * ----------------------------------------- */
    renderFactoryCard(factory) {
        if (!factory || document.getElementById(`factory-${factory.id}`)) return;

        const i18n = this.config.i18n;
        const manageInventory = this.productData.manage_inventory;

        const html = `
        <div class="card shadow-sm mb-4 factory-card" id="factory-${factory.id}">
            <div class="card-header bg-light d-flex justify-content-between align-items-center factory-header"
                 data-factory-id="${factory.id}">
                <span class="fw-bold text-primary">
                    <i class="fas fa-industry me-1"></i>${this.escapeHtml(factory.text)}
                </span>

                <div class="d-flex align-items-center gap-2">
                    <input type="number"
                           step="0.01"
                           name="factories[${factory.id}][markup]"
                           class="form-control form-control-sm text-end"
                           style="max-width:120px"
                           placeholder="${i18n.markup}">
                    <button type="button"
                            class="btn btn-sm btn-light factory-toggle"
                            data-factory-id="${factory.id}"
                            aria-label="Toggle factory details">
                        <i class="fas fa-chevron-up"></i>
                    </button>
                </div>
            </div>

            <div class="card-body factory-card-body">
                <input type="hidden"
                       name="factories[${factory.id}][factory_id]"
                       value="${factory.id}">

                <div class="d-flex flex-wrap justify-content-end align-items-center mb-2 gap-2 factory-bulk-actions"
                     data-factory-id="${factory.id}">
                    <input type="number"
                           step="0.01"
                           class="form-control form-control-sm bulk-regular-price"
                           style="max-width:110px"
                           placeholder="${i18n.regularPrice}">
                    <input type="number"
                           step="0.01"
                           class="form-control form-control-sm bulk-sale-price"
                           style="max-width:110px"
                           placeholder="${i18n.salePrice}">
                    ${manageInventory ? `
                    <input type="number"
                           class="form-control form-control-sm bulk-quantity"
                           style="max-width:110px"
                           placeholder="${i18n.quantity}">
                    ` : ``}
                    <select class="form-select form-select-sm bulk-stock-status"
                            style="max-width:130px">
                        <option value="">${i18n.stockStatus}</option>
                        <option value="1">In Stock</option>
                        <option value="0">Out of Stock</option>
                    </select>
                    <button type="button"
                            class="btn btn-sm btn-outline-primary apply-bulk"
                            data-factory-id="${factory.id}">
                        Apply to selected
                    </button>
                </div>

                <div class="table-responsive">
                    <table class="table table-sm align-middle">
                        <thead class="table-light">
                            <tr>
                                <th style="width:60px">
                                    <input type="checkbox"
                                           class="form-check-input factory-sync-toggle"
                                           data-factory-id="${factory.id}">
                                </th>
                                <th>Variant</th>
                                <th class="text-truncate" style="width:120px">${i18n.regularPrice}</th>
                                <th class="text-truncate" style="width:120px">${i18n.salePrice}</th>
                                ${manageInventory ? `<th style="width:120px">${i18n.quantity}</th>` : ``}
                                <th class="text-truncate" style="width:140px">${i18n.stockStatus}</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${this.renderVariantRows(factory.id)}
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        `;

        this.preview.insertAdjacentHTML('beforeend', html);
        this.applyValidationRules(factory.id);
        this.applyExistingMarkup(factory.id);
        this.syncFactoryToggleState(factory.id);
    }
    applyValidationRules(factoryId) {
        const manageInventory = this.productData.manage_inventory;

        document
            .querySelectorAll(`#factory-${factoryId} tbody tr`)
            .forEach(row => {
                const sync = row.querySelector('.variant-sync');
                const inputs = row.querySelectorAll('input, select');
                inputs.forEach(input => {
                    if (input.name.includes('[regular_price]')) {
                        $(input).rules('add', {
                            required: () => sync.checked,
                            positiveNumber: true,
                            messages: {
                                required: 'Regular price is required',
                            }
                        });
                    }
                    if (input.name.includes('[sale_price]')) {
                        $(input).rules('add', {
                            saleLessThanPrice: true,
                            messages: {
                                saleLessThanPrice: 'Sale price must be < regular price'
                            }
                        });
                    }
                    if (manageInventory && input.name.includes('[quantity]')) {
                        $(input).rules('add', {
                            required: () => sync.checked,
                            digits: true,
                            messages: {
                                required: 'Quantity is required'
                            }
                        });
                    }
                    if (input.name.includes('[stock_status]')) {
                        $(input).rules('add', {
                            required: () => sync.checked
                        });
                    }
                });
            });
    }

    prepopulateFactories() {
        const assignments = this.productData.factory_assignments || {};
        const ids = Object.keys(assignments);
        if (!ids.length) return;

        ids.forEach(id => {
            const fa = assignments[id];
            if (!fa) return;
            // Add option if it doesn't exist
            if (this.select.find(`option[value="${id}"]`).length === 0) {
                const option = new Option(fa.name, id, true, true);
                this.select.append(option);
            }
        });

        // Ensure all assigned factories are selected in Select2
        this.select.val(ids).trigger('change');

        ids.forEach(id => {
            const fa = assignments[id];
            this.renderFactoryCard({ id, text: fa.name });
        });
    }

    applyExistingMarkup(factoryId) {
        const assignments = this.productData.factory_assignments || {};
        const fa = assignments[factoryId];
        if (!fa) return;
        const card = document.getElementById(`factory-${factoryId}`);
        if (!card) return;
        const input = card.querySelector(
            `input[name="factories[${factoryId}][markup]"]`
        );
        if (input && typeof fa.markup !== 'undefined' && fa.markup !== null) {
            input.value = fa.markup;
        }
    }

    /* -----------------------------------------
     * VARIANT ROWS
     * ----------------------------------------- */
    renderVariantRows(factoryId) {
        let variants = this.productData.variants || [];

        // Filter if we are targeting a specific variant
        if (this.targetVariantId) {
            variants = variants.filter(v => String(v.id) === String(this.targetVariantId));
        }

        const manageInventory = this.productData.manage_inventory;
        const base_price = this.productData.base_price;
        const assignments =
            (this.productData.factory_assignments &&
                this.productData.factory_assignments[factoryId] &&
                this.productData.factory_assignments[factoryId].variants) ||
            {};

        return variants.map(v => `
            <tr>
                ${(() => {
                const assigned = assignments[v.id] || {};
                const getNum = (val, fb) => {
                    const n = Number(val);
                    return !isNaN(n) ? n : fb;
                };
                const pickPrice = (...candidates) => {
                    for (let i = 0; i < candidates.length; i++) {
                        const val = candidates[i];
                        if (val === null || typeof val === 'undefined' || val === '') {
                            continue;
                        }
                        const n = Number(val);
                        if (!isNaN(n)) {
                            return n;
                        }
                    }
                    return '';
                };

                const regularPrice = pickPrice(assigned.regular_price, v.regular_price, base_price.regular_price);
                const salePrice = pickPrice(assigned.sale_price, v.sale_price, base_price.sale_price);

                const quantity = manageInventory
                    ? getNum(assigned.quantity, getNum(v.quantity, ''))
                    : null;

                const stockStatus = typeof assigned.stock_status !== 'undefined' && assigned.stock_status !== null
                    ? assigned.stock_status
                    : v.stock_status;

                return `
                <td class="text-center">
                    <input type="checkbox"
                           class="form-check-input variant-sync"
                           checked
                           data-target="v-${factoryId}-${v.id}"
                           data-factory-id="${factoryId}">
                </td>

                <td>
                    ${this.escapeHtml(v.attributes_text || v.name)}
                </td>

                <td>
                    <input type="number"
                           step="0.01"
                           name="factories[${factoryId}][variants][${v.id}][regular_price]"
                           class="form-control form-control-sm v-${factoryId}-${v.id}"
                           value="${regularPrice}">
                </td>

                <td>
                    <input type="number"
                           step="0.01"
                           name="factories[${factoryId}][variants][${v.id}][sale_price]"
                           class="form-control form-control-sm v-${factoryId}-${v.id}"
                           value="${salePrice}">
                </td>

                ${manageInventory
                        ? `<td>
                            <input type="number"
                                   name="factories[${factoryId}][variants][${v.id}][quantity]"
                                   class="form-control form-control-sm v-${factoryId}-${v.id}"
                                   value="${quantity}">
                           </td>`
                        : ``
                    }

                <td>
    <select name="factories[${factoryId}][variants][${v.id}][stock_status]"
        class="form-select form-select-sm v-${factoryId}-${v.id}">
        <option value="1" ${String(stockStatus) === '1' ? 'selected' : ''}>
            In Stock
        </option>
        <option value="0" ${String(stockStatus) === '0' ? 'selected' : ''}>
            Out of Stock
        </option>
    </select>
</td>`;
            })()}
            </tr>
        `).join('');
    }

    bindSyncEvents() {
        const self = this;
        $(document).on('change', '.variant-sync', function () {
            const target = $(this).data('target');
            const enabled = this.checked;
            $(`.${target}`).prop('disabled', !enabled);
            const factoryId = $(this).data('factory-id');
            if (factoryId) {
                self.syncFactoryToggleState(factoryId);
            }
        });
        $(document).on('change', '.factory-sync-toggle', function () {
            const factoryId = $(this).data('factory-id');
            const checked = this.checked;
            const card = document.getElementById(`factory-${factoryId}`);
            if (!card) return;
            const checkboxes = card.querySelectorAll('.variant-sync');
            checkboxes.forEach(cb => {
                cb.checked = checked;
                $(cb).trigger('change');
            });
            this.indeterminate = false;
        });
    }

    syncFactoryToggleState(factoryId) {
        const card = document.getElementById(`factory-${factoryId}`);
        if (!card) return;
        const header = card.querySelector('.factory-sync-toggle');
        if (!header) return;
        const checkboxes = card.querySelectorAll('.variant-sync');
        const total = checkboxes.length;
        let checkedCount = 0;
        checkboxes.forEach(cb => {
            if (cb.checked) checkedCount++;
        });
        header.checked = total > 0 && checkedCount === total;
        header.indeterminate = checkedCount > 0 && checkedCount < total;
    }

    bindFactoryCollapse() {
        $(document).on('click', '.factory-toggle', e => {
            const btn = e.currentTarget;
            const factoryId = $(btn).data('factory-id');
            const card = document.getElementById(`factory-${factoryId}`);
            if (!card) return;
            const body = card.querySelector('.factory-card-body');
            if (!body) return;
            const isHidden = body.classList.contains('factory-body-collapsed');
            this.setFactoryBodyVisible(card, isHidden);
        });

        $(document).on('click', '.factory-header', e => {
            if ($(e.target).closest('input, button, .factory-bulk-actions').length) {
                return;
            }
            const header = e.currentTarget;
            const factoryId = $(header).data('factory-id');
            const card = document.getElementById(`factory-${factoryId}`);
            if (!card) return;
            const body = card.querySelector('.factory-card-body');
            if (!body) return;
            const isHidden = body.classList.contains('factory-body-collapsed');
            this.setFactoryBodyVisible(card, isHidden);
        });

        $(document).on('click', '#factoryExpandAll', () => {
            document.querySelectorAll('.factory-card').forEach(card => {
                this.setFactoryBodyVisible(card, true);
            });
        });

        $(document).on('click', '#factoryCollapseAll', () => {
            document.querySelectorAll('.factory-card').forEach(card => {
                this.setFactoryBodyVisible(card, false);
            });
        });
    }

    setFactoryBodyVisible(card, visible) {
        const body = card.querySelector('.factory-card-body');
        if (!body) return;
        const toggle = card.querySelector('.factory-toggle');
        const icon = toggle ? toggle.querySelector('i') : null;

        if (visible) {
            body.classList.remove('factory-body-collapsed');
            if (icon) {
                icon.classList.remove('fa-chevron-down');
                icon.classList.add('fa-chevron-up');
            }
            if (typeof body.scrollIntoView === 'function') {
                card.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        } else {
            body.classList.add('factory-body-collapsed');
            if (icon) {
                icon.classList.remove('fa-chevron-up');
                icon.classList.add('fa-chevron-down');
            }
        }
    }

    bindBulkActions() {
        $(document).on('click', '.factory-bulk-actions .apply-bulk', function () {
            const factoryId = $(this).data('factory-id');
            const container = document.querySelector(`.factory-bulk-actions[data-factory-id="${factoryId}"]`);
            if (!container) return;

            const regularValue = container.querySelector('.bulk-regular-price')?.value ?? '';
            const saleValue = container.querySelector('.bulk-sale-price')?.value ?? '';
            const quantityValue = container.querySelector('.bulk-quantity')?.value ?? '';
            const stockValue = container.querySelector('.bulk-stock-status')?.value ?? '';

            const card = document.getElementById(`factory-${factoryId}`);
            if (!card) return;

            const rows = card.querySelectorAll('tbody tr');
            rows.forEach(row => {
                const checkbox = row.querySelector('.variant-sync');
                if (!checkbox || !checkbox.checked) return;

                const targetClass = checkbox.dataset.target;
                const inputs = row.querySelectorAll(`.${targetClass}`);

                inputs.forEach(input => {
                    if (regularValue !== '' && input.name.includes('[regular_price]')) {
                        input.value = regularValue;
                    }
                    if (saleValue !== '' && input.name.includes('[sale_price]')) {
                        input.value = saleValue;
                    }
                    if (quantityValue !== '' && input.name.includes('[quantity]')) {
                        input.value = quantityValue;
                    }
                    if (stockValue !== '' && input.name.includes('[stock_status]')) {
                        input.value = stockValue;
                    }
                });
            });
        });
    }

    /* -----------------------------------------
     * REMOVE FACTORY CARD
     * ----------------------------------------- */
    removeFactoryCard(factoryId) {
        const el = document.getElementById(`factory-${factoryId}`);
        if (el) el.remove();
    }
    bindSubmit() {
        $('#assignFactorySubmit').on('click', async () => {

            const $form = $('#assignFactoryForm');
            if (!$form.valid()) {
                Swal.fire(
                    'Validation Error',
                    'Please fix highlighted fields',
                    'error'
                );
                return;
            }
            this.setSubmitLoading(true);
            const payload = {
                product_id: this.productData.product.id,
                factories: {}
            };
            document.querySelectorAll('.factory-card').forEach(card => {
                const factoryId = card.id.replace('factory-', '');
                const factoryData = {
                    factory_id: factoryId,
                    markup: (() => {
                        const val = card.querySelector(`input[name="factories[${factoryId}][markup]"]`)?.value;
                        return val === '' ? null : val;
                    })(),
                    variants: {}
                };
                card.querySelectorAll('tbody tr').forEach(row => {
                    const checkbox = row.querySelector('.variant-sync');
                    if (!checkbox || !checkbox.checked) return;

                    const targetClass = checkbox.dataset.target;
                    const inputs = row.querySelectorAll(`.${targetClass}`);

                    const variantId =
                        inputs[0].name.match(/\[variants]\[(\d+)]/)[1];

                    const variantData = {};

                    inputs.forEach(input => {
                        if (input.name.includes('[regular_price]')) {
                            variantData.regular_price = input.value;
                        }
                        if (input.name.includes('[sale_price]')) {
                            variantData.sale_price = input.value || null;
                        }
                        if (input.name.includes('[quantity]')) {
                            variantData.quantity = input.value || null;
                        }
                        if (input.name.includes('[stock_status]')) {
                            variantData.stock_status = input.value;
                        }
                    });

                    factoryData.variants[variantId] = variantData;
                });

                payload.factories[factoryId] = factoryData;
            });

            await this.submitAssignment(payload);
        });
    }

    async submitAssignment(payload) {
        try {
            const res = await fetch(this.config.urls.assignFactories, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': this.config.csrf,
                    'Accept': 'application/json'
                },
                body: JSON.stringify(payload)
            });
            const data = await res.json();
            if (!res.ok) {
                if (res.status === 422) {
                    Swal.fire(
                        'Validation Error',
                        'Please check entered values',
                        'error'
                    );
                } else {
                    throw new Error(data.message || 'Something went wrong');
                }
                return;
            } if (data.redirect_url) {
                window.location.assign(data.redirect_url);
                return;
            }
            this.modal.modal('hide');
        } catch (err) {
            console.error(err);
            Swal.fire('Error', err.message || 'Server error', 'error');
        } finally {
            this.setSubmitLoading(false);
        }
    }
    initValidation() {
        const self = this;
        $('#assignFactoryForm').validate({
            ignore: [],
            errorClass: 'is-invalid',
            validClass: 'is-valid',
            errorElement: 'div',
            errorPlacement: function (error, element) {
                error.addClass('invalid-feedback');

                if (element.closest('.input-group').length) {
                    error.insertAfter(element.closest('.input-group'));
                } else {
                    error.insertAfter(element);
                }
            },

            highlight: function (element) {
                $(element).addClass('is-invalid');
            },

            unhighlight: function (element) {
                $(element).removeClass('is-invalid');
            }
        });

        /**
         * Custom rules
         */
        $.validator.addMethod('positiveNumber', value =>
            value === '' || parseFloat(value) > 0
        );

        $.validator.addMethod('saleLessThanPrice', function (value, element) {
            const row = element.closest('tr');
            const price = row.querySelector('[name*="[regular_price]"]')?.value;
            if (!value || !price) return true;
            return parseFloat(value) < parseFloat(price);
        });
    }

}

/* -----------------------------------------
 * INIT
 * ----------------------------------------- */
document.addEventListener('DOMContentLoaded', () => {
    window.factoryAssignmentManager =
        new FactoryAssignmentManager(window.ProductConfig);
});
