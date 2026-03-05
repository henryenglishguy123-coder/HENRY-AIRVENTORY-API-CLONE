class ProductManager {
    constructor(config) {
        this.config = config;
        this.isUploading = false;
        this.skuManuallyEdited = false;
        this.form = document.getElementById('productForm');
        this.init();
    }

    init() {
        this.initPlugins();
        this.initRichEditors();
        this.initSkuLogic();
        this.initInventoryLogic();
        this.initCategories();
        this.initMediaGallery();
        this.initVariantSystem();
        this.initValidation();
        this.initFormSubmit();
    }

    // 1. Plugins
    initPlugins() {
        if (typeof $.fn.select2 !== 'undefined') {
            $('.select2').select2({ placeholder: 'Select...', width: '100%', allowClear: true });
        }
        $(document).on('select2:select', '.select2', function () {
            $(this).valid();
        });
    }

    // 2. Rich Text Editors
    initRichEditors() {
        const opts = { theme: 'snow' };
        this.descEditor = new Quill('#description', opts);
        this.shortEditor = new Quill('#short_description', opts);
        const dInput = document.getElementById('description_input');
        const sInput = document.getElementById('short_description_input');
        if (dInput.value) this.descEditor.root.innerHTML = dInput.value;
        if (sInput.value) this.shortEditor.root.innerHTML = sInput.value;
        this.descEditor.on('text-change', () => this.updateSeoPreview());
        this.shortEditor.on('text-change', () => this.updateSeoPreview());
        const metaDesc = document.getElementById('meta_description');
        if (metaDesc) {
            metaDesc.addEventListener('input', () => {
                metaDesc.dataset.userEdited = 'true';
                this.updateMetaCount();
            });
        }

    }

    syncEditors() {
        document.getElementById('description_input').value = this.descEditor.root.innerHTML;
        document.getElementById('short_description_input').value = this.shortEditor.root.innerHTML;
    }
    initSkuLogic() {
        const nameInput = document.getElementById('name');
        const skuInput = document.getElementById('sku');
        const metaTitle = document.getElementById('meta_title');
        if (skuInput.value.trim() !== '') {
            this.skuManuallyEdited = true;
        }
        skuInput.addEventListener('keydown', () => {
            this.skuManuallyEdited = true;
        });
        skuInput.addEventListener('input', () => {
            if (skuInput.value.trim() === '') {
                this.skuManuallyEdited = false;
            }
        });
        nameInput.addEventListener('input', (e) => {
            const name = e.target.value.trim();
            if (!this.skuManuallyEdited && name.length >= 2) {
                skuInput.value = this.generateSku(name);
            }
            if (metaTitle && metaTitle.dataset.userEdited !== 'true') {
                metaTitle.value = name.substring(0, 60);
                this.updateTitleCount();
            }
        });
        if (metaTitle) {
            if (metaTitle.value.trim() !== '') {
                metaTitle.dataset.userEdited = 'true';
            }
            metaTitle.addEventListener('input', () => {
                metaTitle.dataset.userEdited = 'true';
                this.updateTitleCount();
            });
            this.updateTitleCount();
        }
    }

    updateTitleCount() {
        const metaTitle = document.getElementById('meta_title');
        const counter = document.getElementById('title-count');
        if (!metaTitle || !counter) return;
        counter.textContent = metaTitle.value.length;
    }
    generateSku(name) {
        const cleanName = name.toUpperCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '').replace(/[^A-Z0-9 ]/g, '').trim();
        let prefix = cleanName.split(' ').filter(w => w.length > 1).slice(0, 2).map(w => w.substring(0, 2)).join('');
        prefix = (prefix + 'XXX').substring(0, 3);
        const array = new Uint32Array(1);
        window.crypto.getRandomValues(array);
        const random = (array[0] % 900000 + 100000).toString(); // 6-digit
        return `${prefix}-${random}`;
    }


    initInventoryLogic() {
        const toggle = document.getElementById('manage_inventory');
        const update = () => {
            const active = toggle.checked;
            const inputs = [document.getElementById('quantity'), ...document.querySelectorAll('.variant-stock')];
            inputs.forEach(el => { if (el) el.disabled = !active; });
        };
        toggle.addEventListener('change', update);
        update();
        this.refreshInventoryState = update;
    }

    async initCategories() {
        const select = document.getElementById('category_id');
        try {
            const res = await fetch(this.config.urls.categories);
            const data = await res.json();
            let html = `<option value="" disabled selected>${this.config.i18n.selectCategory}</option>`;
            const build = (cats, prefix = '') => {
                cats.forEach(c => {
                    html += `<option value="${c.id}">${prefix}${c.name}</option>`;
                    if (c.children) build(c.children, prefix + '-- ');
                });
            };
            build(data.data || data);
            select.innerHTML = html;
            if (this.config.oldData.categoryId) select.value = this.config.oldData.categoryId;
        } catch (e) { console.error('Category Load Error', e); }
    }

    initMediaGallery() {
        const self = this;
        if (!document.querySelector('#image_gallery')) return;
        const dz = new Dropzone('#image_gallery', {
            url: this.config.urls.upload,
            acceptedFiles: 'image/*,video/mp4,video/x-m4v,video/quicktime,video/webm,video/avi',
            maxFilesize: 200,
            timeout: 60000,
            headers: { 'X-CSRF-TOKEN': this.config.csrf },
            init: function () {
                this.on("sending", () => {
                    self.isUploading = true;
                    document.getElementById('global-upload-progress').classList.remove('d-none');
                });
                this.on("uploadprogress", (f, p) => {
                    document.getElementById('global-upload-bar').style.width = p + "%";
                });
                this.on("success", (f, res) => {
                    self.isUploading = false;
                    document.getElementById('global-upload-progress').classList.add('d-none');
                    const path = res.path || (res.data ? res.data.path : null);
                    if (path) self.addGalleryItem(path);
                    this.removeFile(f);
                });
                this.on("error", (f, msg) => {
                    self.isUploading = false;
                    let errorMsg = typeof msg === 'object' ? msg.message : msg;
                    if (f.size > this.options.maxFilesize * 1024 * 1024) {
                        errorMsg = self.config.messages.file_large;
                    }
                    Swal.fire(self.config.messages.upload_error, errorMsg, 'error');
                    this.removeFile(f);
                });
            }
        });

        try {
            const old = typeof this.config.oldData.gallery === 'string'
                ? JSON.parse(this.config.oldData.gallery)
                : this.config.oldData.gallery;
            if (old) old.forEach(i => this.addGalleryItem(i.file_path));
        } catch (e) { }
        new Sortable(document.getElementById('sortable-container'), {
            animation: 150, onEnd: () => this.updateGalleryInput()
        });
    }

    isVideoFile(path) {
        return path.match(/\.(mp4|mov|avi|webm|m4v)$/i);
    }

    addGalleryItem(path) {
        const container = document.getElementById('sortable-container');
        const div = document.createElement('div');
        div.className = 'col-6 col-md-3 sortable-item';
        div.dataset.path = path;
        let mediaHtml = '';
        if (this.isVideoFile(path)) {
            mediaHtml = `
                <video src="${path}" class="media-preview" controls muted preload="metadata">
                    Your browser does not support video.
                </video>
                <i class="fas fa-video video-indicator" style="position:absolute; bottom:5px; left:5px; color:white; text-shadow:0 0 3px black;"></i>
            `;
        } else {
            mediaHtml = `<img src="${path}" class="media-preview">`;
        }
        div.innerHTML = `
            <div class="image-container">
                ${mediaHtml}
                <button type="button" class="remove-image-btn"><i class="fas fa-times"></i></button>
            </div>`;
        div.querySelector('.remove-image-btn').addEventListener('click', () => this.removeGalleryItem(div, path));
        container.appendChild(div);
        this.updateGalleryInput();
    }

    async removeGalleryItem(el, path) {
        try {
            await fetch(this.config.urls.delete, {
                method: 'DELETE',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': this.config.csrf },
                body: JSON.stringify({ filename: path })
            });
            el.remove();
            this.updateGalleryInput();
        } catch (e) { alert('Error deleting item'); }
    }

    updateGalleryInput() {
        const items = Array.from(document.querySelectorAll('.sortable-item')).map(el => {
            const path = el.dataset.path;
            return {
                file_path: path,
                type: this.isVideoFile(path) ? 'video' : 'image' // Save Type
            };
        });
        document.getElementById('gallery_input').value = JSON.stringify(items);
    }

    initVariantSystem() {
        const btn = document.getElementById('generate-combinations');
        if (!btn) return;

        btn.addEventListener('click', () => {
            const selects = document.querySelectorAll('.variant-select');
            let attrs = [];
            selects.forEach(s => {
                const opts = Array.from(s.selectedOptions);
                if (opts.length) attrs.push({
                    id: s.dataset.attrId,
                    name: s.dataset.attrName,
                    options: opts.map(o => ({ id: o.value, text: o.innerText.trim() }))
                });
            });
            if (!$('#sku').val()) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Missing SKU',
                    text: 'Base SKU is required to generate variants',
                    confirmButtonText: 'OK'
                }).then(() => {
                    const skuInput = document.getElementById('sku');
                    if (skuInput) {
                        const y = skuInput.getBoundingClientRect().top + window.scrollY - 150;
                        window.scrollTo({ top: y, behavior: 'smooth' });
                        setTimeout(() => skuInput.focus(), 500);
                    }
                });
                return;
            }
            if (!$('#weight').val()) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Missing Weight',
                    text: 'Product weight is required before generating variants',
                    confirmButtonText: 'OK'
                }).then(() => {
                    const weightInput = document.getElementById('weight');
                    if (weightInput) {
                        const y = weightInput.getBoundingClientRect().top + window.scrollY - 150;
                        window.scrollTo({ top: y, behavior: 'smooth' });
                        setTimeout(() => weightInput.focus(), 500);
                    }
                });
                return;
            }
            if (!$('#price').val()) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Missing Price',
                    text: 'Base Price is required to generate variants',
                    confirmButtonText: 'OK'
                }).then(() => {
                    const priceInput = document.getElementById('price');
                    if (priceInput) {
                        const y = priceInput.getBoundingClientRect().top + window.scrollY - 150;
                        window.scrollTo({ top: y, behavior: 'smooth' });
                        setTimeout(() => priceInput.focus(), 500);
                    }
                });
                return;
            }
            if (!attrs.length) {
                Swal.fire({
                    icon: 'warning',
                    title: 'No attributes selected',
                    text: 'Please select at least one variant attribute',
                    confirmButtonText: 'OK'
                }).then(() => {
                    const firstSelect = document.querySelector('.variant-select');
                    if (firstSelect) {
                        const y = firstSelect.getBoundingClientRect().top + window.scrollY - 150;
                        window.scrollTo({ top: y, behavior: 'smooth' });
                        setTimeout(() => firstSelect.focus(), 400);
                    }
                });
                return;
            }
            this.renderVariants(this.cartesian(attrs.map(a => a.options)), attrs);
        });
        document.getElementById('variants-table-body').addEventListener('change', (e) => {
            if (e.target.classList.contains('variant-file-input')) {
                const file = e.target.files[0];
                if (!file) return;
                if (!file.type.startsWith('image/')) {
                    Swal.fire('Invalid File', 'Only image files are allowed.', 'error');
                    e.target.value = '';
                    return;
                }
                const reader = new FileReader();
                reader.onload = (ev) => {
                    e.target.closest('label').querySelector('img').src = ev.target.result;
                };
                reader.readAsDataURL(file);
            }
        });
        document.getElementById('variants-table-body').addEventListener('click', (e) => {
            if (e.target.closest('.remove-variant')) e.target.closest('tr').remove();
        });

        if (this.config.variantAttributes && Object.keys(this.config.variantAttributes).length) {
            const selects = document.querySelectorAll('.variant-select');
            selects.forEach(s => {
                const attrId = s.dataset.attrId;
                const selectedOptions = this.config.variantAttributes[attrId];
                if (selectedOptions && selectedOptions.length) {
                    $(s).val(selectedOptions.map(String)).trigger('change');
                }
            });
            // Only auto-click if we have required basic data (SKU, Price, Weight) to avoid validation errors
            const skuVal = $('#sku').val();
            const priceVal = $('#price').val();
            const weightVal = $('#weight').val();

            if (skuVal && priceVal && weightVal && !document.querySelector('#variants-table-body tr')) {
                btn.click();
            }
        }
    }

    renderVariants(combos, structure) {
        const tbody = document.getElementById('variants-table-body');
        const productName = document.getElementById('name')?.value?.trim() || '';
        const baseSku = document.getElementById('sku').value.trim().toUpperCase();

        tbody.innerHTML = '';
        document.getElementById('variants-section').classList.remove('d-none');

        combos.forEach((combo, idx) => {
            const existing = this.findExistingVariant(structure, combo);
            const { badges, sku, name, hidden } = this.buildVariantData(
                combo,
                structure,
                productName,
                baseSku,
                idx,
                existing
            );

            const imgSrc = existing && existing.image
                ? existing.image
                : this.config.urls.placeholder;

            tbody.insertAdjacentHTML('beforeend', `
            <tr>
                <td class="text-center">${idx + 1}</td>

                <td>
                    <label class="variant-file-label">
                        <img src="${imgSrc}" class="variant-img-preview">
                        <input type="file"
                               name="variants[${idx}][image]"
                               accept="image/*"
                               class="d-none variant-file-input">
                    </label>
                </td>

                <td>
                    <div class="fw-semibold text-dark">${name}</div>
                    <div class="text-muted small">${badges}</div>
                    <input type="hidden" name="variants[${idx}][name]" value="${name}">
                    <input type="hidden" name="variants[${idx}][id]" value="${existing ? existing.id : ''}">
                    ${hidden}
                </td>

                <td>
                    <input type="text"
                           name="variants[${idx}][sku]"
                           class="form-control form-control-sm variant-sku"
                           value="${sku}">
                </td>

                <td>
                    <input type="number"
                           name="variants[${idx}][stock]"
                           class="form-control form-control-sm variant-stock"
                           value="${existing?.stock ?? 0}">
                </td>

                <td class="text-center">
                    <button type="button" class="btn btn-sm text-danger remove-variant">
                        <i class="fas fa-trash"></i>
                    </button>
                </td>
            </tr>
        `);
        });
        this.refreshInventoryState();
    }
    buildVariantData(combo, structure, productName, baseSku, idx, existing) {
        const nameParts = [];
        const skuParts = [];
        let badges = '';
        let hidden = '';

        combo.forEach((opt, i) => {
            nameParts.push(opt.text.trim());
            skuParts.push(opt.text.toUpperCase().replace(/[^A-Z0-9]/g, ''));

            badges += `<span class="variant-badge me-2">${structure[i].name}: ${opt.text}</span>`;
            hidden += `<input type="hidden" name="variants[${idx}][attributes][${structure[i].id}]" value="${opt.id}">`;
        });

        const name = existing && existing.name
            ? existing.name
            : (productName
                ? `${productName} - ${nameParts.join(' - ')}`
                : nameParts.join(' - '));

        return {
            name,
            sku: existing && existing.sku ? existing.sku : `${baseSku}-${skuParts.join('-')}`,
            badges,
            hidden
        };
    }

    findExistingVariant(structure, combo) {
        const attrs = {};
        combo.forEach((opt, i) => {
            const attrId = structure[i].id;
            attrs[attrId] = opt.id;
        });

        // Optimization: Create a signature string for O(1) or O(N) lookup 
        // instead of nested loops if existingList is large.
        // For now, hoisting keys.
        const keys1 = Object.keys(attrs).sort();
        const existingList = this.config.existingVariants || [];

        for (let i = 0; i < existingList.length; i++) {
            const v = existingList[i];
            const vAttrs = v.attributes || {};
            const keys2 = Object.keys(vAttrs).sort();

            if (keys1.length !== keys2.length) continue;

            let match = true;
            for (let k = 0; k < keys1.length; k++) {
                const key = keys1[k];
                // Use loose comparison or conversion to string to ensure matching works
                if (String(attrs[key]) !== String(vAttrs[key])) {
                    match = false;
                    break;
                }
            }
            if (match) return v;
        }
        return null;
    }

    cartesian(args) {
        let r = [], max = args.length - 1;
        function helper(arr, i) {
            for (let j = 0, l = args[i].length; j < l; j++) {
                let a = arr.slice(0);
                a.push(args[i][j]);
                if (i == max) r.push(a); else helper(a, i + 1);
            }
        }
        helper([], 0);
        return r;
    }
    updateSeoPreview() {
        const metaDesc = document.getElementById('meta_description');
        if (metaDesc) {
            metaDesc.addEventListener('input', () => {
                metaDesc.dataset.userEdited = 'true';
                this.updateMetaCount();
            });
        }
        let text = this.shortEditor.getText().trim();
        if (!text) {
            text = this.descEditor.getText().trim();
        }
        text = text.substring(0, 160);
        metaDesc.value = text;
        this.updateMetaCount();
    }
    updateMetaCount() {
        const metaDesc = document.getElementById('meta_description');
        const counter = document.getElementById('desc-count');
        if (!metaDesc || !counter) return;
        counter.textContent = metaDesc.value.length;
    }
    initValidation() {
        $.validator.addMethod("saleLessEqualPrice", function (value, element) {
            const sale = parseFloat(value);
            if (isNaN(sale) || value === "") return true;
            let priceField = null;
            if (element.name === "sale_price" || element.id === "sale_price") {
                priceField = document.getElementById("price");
            }
            if (!priceField && element.closest) {
                const row = element.closest("tr");
                if (row) priceField = row.querySelector(".variant-price");
            }
            const price = parseFloat(priceField?.value || 0);
            return sale < price;
        }, this.config.messages.sale_price_less_equal_price);
        $('#productForm').validate({
            errorClass: "is-invalid",
            validClass: "is-valid",
            ignore: ":hidden:not(.select2-hidden-accessible)",
            rules: {
                name: "required",
                sku: "required",
                price: { required: true, number: true },
                sale_price: {
                    number: true,
                    saleLessEqualPrice: true
                },
                category_id: "required",
                weight: { required: true, number: true }
            },
            messages: {
                name: this.config.messages.req_name,
                sku: this.config.messages.req_sku,
                price: this.config.messages.req_price,
                sale_price: this.config.messages.sale_price_less_equal_price,
                category_id: this.config.messages.req_category
            },
            errorPlacement: function (error, element) {
                if (element.hasClass('select2-hidden-accessible')) {
                    error.insertAfter(element.next('.select2'));
                } else if (element.parent('.input-group').length) {
                    error.insertAfter(element.parent());
                } else {
                    error.insertAfter(element);
                }
            }
        });
        $(document).on('select2:select', '.select2', function () {
            $(this).valid();
        });
        const mainPrice = document.getElementById('price');
        const mainSale = document.getElementById('sale_price');
        if (mainPrice && mainSale) {
            mainPrice.addEventListener('input', () => {
                $(mainSale).valid();
            });
            mainSale.addEventListener('input', () => {
                $(mainSale).valid();
            });
        }
    }

    // 9. AJAX Form Submission
    initFormSubmit() {
        const btn = document.getElementById('product-form-btn');
        btn.addEventListener('click', async (e) => {
            e.preventDefault();

            // A. Checks
            if (this.isUploading) return Swal.fire('Wait', this.config.i18n.wait, 'warning');

            // B. Sync
            this.syncEditors();

            // C. Client Validation
            if (!$(this.form).valid()) {
                $('html, body').animate({ scrollTop: $('.is-invalid').first().offset().top - 100 }, 500);
                return;
            }

            // D. UI State
            const originalText = document.getElementById('btn-text').textContent;
            btn.disabled = true;
            document.getElementById('btn-text').textContent = this.config.i18n.saving;
            document.getElementById('btn-spinner').classList.remove('d-none');

            // E. Send Request
            await this.handleAjaxSubmit(btn, originalText);
        });
    }

    async handleAjaxSubmit(btn, originalText) {
        document.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));
        document.querySelectorAll('.invalid-feedback').forEach(el => el.remove());
        const formData = new FormData(this.form);
        try {
            const response = await fetch(this.form.action, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': this.config.csrf,
                    'Accept': 'application/json'
                },
                body: formData
            });
            const data = await response.json();
            if (!response.ok) {
                if (response.status === 422) {
                    this.handleBackendErrors(data.errors);
                    Swal.fire({
                        icon: 'error',
                        title: this.config.messages.val_error_title,
                        text: this.config.messages.val_error_text
                    });
                } else {
                    throw new Error(data.message || this.config.messages.server_error);
                }
            } else {
                window.ProductConfig.productId = data.product_id;
                window.dispatchEvent(
                    new CustomEvent('product:created', {
                        detail: {
                            productId: data.product_id
                        }
                    })
                );
            }
        } catch (error) {
            console.error(error);
            Swal.fire('Error', error.message || 'An unexpected error occurred.', 'error');
        } finally {
            btn.disabled = false;
            document.getElementById('btn-text').textContent = originalText;
            document.getElementById('btn-spinner').classList.add('d-none');
        }
    }
    handleBackendErrors(errors) {
        document.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));
        document.querySelectorAll('.invalid-feedback').forEach(el => el.remove());
        if (typeof $ !== 'undefined') {
            $('.select2-selection').css('border-color', '');
        }
        const summary = document.getElementById('validation-summary');
        const list = document.getElementById('validation-list');
        if (summary && list) {
            list.innerHTML = '';
            summary.classList.add('d-none');
        }
        let firstErrorElement = null;
        Object.keys(errors).forEach(field => {
            const errorMsg = errors[field][0];
            let inputName = field;
            if (field.includes('.')) {
                const parts = field.split('.');
                inputName = parts.shift() + '[' + parts.join('][') + ']';
            }
            const input = this.form.querySelector(`[name="${inputName}"]`) ||
                document.getElementById(field);
            if (input) {
                input.classList.add('is-invalid');
                if (input.classList.contains('select2-hidden-accessible')) {
                    const $wrapper = $(input).next('.select2');
                    if ($wrapper.length) {
                        $wrapper.find('.select2-selection').css('border-color', '#dc3545');
                    }
                }
                const errorDiv = document.createElement('div');
                errorDiv.className = 'invalid-feedback d-block fw-bold'; // d-block ensures visibility
                errorDiv.innerHTML = `<i class="fas fa-exclamation-circle me-1"></i> ${errorMsg}`;
                const inputGroup = input.closest('.input-group');
                const select2Container = input.classList.contains('select2-hidden-accessible')
                    ? $(input).next('.select2')[0]
                    : null;
                if (select2Container) {
                    select2Container.parentNode.insertBefore(errorDiv, select2Container.nextSibling);
                } else if (inputGroup) {
                    inputGroup.parentNode.insertBefore(errorDiv, inputGroup.nextSibling);
                } else {
                    input.parentNode.insertBefore(errorDiv, input.nextSibling);
                }
                if (summary && list) {
                    const li = document.createElement('li');
                    li.textContent = errorMsg;
                    list.appendChild(li);
                }
                if (!firstErrorElement) firstErrorElement = input;
            }
        });
        if (summary && list && list.children.length > 0) {
            summary.classList.remove('d-none');
            const y = summary.getBoundingClientRect().top + window.scrollY - 100;
            window.scrollTo({ top: y, behavior: 'smooth' });
        }
        else if (firstErrorElement) {
            const y = firstErrorElement.getBoundingClientRect().top + window.scrollY - 150;
            window.scrollTo({ top: y, behavior: 'smooth' });
            if (!firstErrorElement.classList.contains('select2-hidden-accessible')) {
                setTimeout(() => firstErrorElement.focus(), 500);
            }
        }
    }

}

