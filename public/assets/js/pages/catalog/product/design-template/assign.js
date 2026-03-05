/**
 * ProductDesignTemplateManager
 * Handles the logic for assigning design templates and pricing layers.
 */
class ProductDesignTemplateManager {
    constructor(config) {
        // 1. Configuration & Data Assignment
        this.selectors = {
            form: '#templateConfigForm',
            templateSelect: '#design_template_id',
            container: '#configuration-container',
            loader: '#layers-loading',
            emptyState: '#empty-state',
            saveBtn: '#saveBtn',
            saveIcon: '#saveIcon',
            saveText: '#saveText',
            alertContainer: '#ajax-alert-container'
        };

        this.urls = config.urls;
        this.data = config.data; // factories, techniques, initialPrices, etc.
        this.messages = config.messages; // Translations
        this.settings = config.settings; // currency symbol, etc.

        // 2. Initialize
        this.init();
    }

    init() {
        this.cacheDom();
        this.bindEvents();
        this.setupValidation();

        // Check if template is already selected (e.g. on validation error return or edit)
        if (this.dom.templateSelect.val()) {
            this.fetchLayers(this.dom.templateSelect.val(), this.data.initialPrices);
        }
    }

    cacheDom() {
        this.dom = {
            form: $(this.selectors.form),
            templateSelect: $(this.selectors.templateSelect),
            container: $(this.selectors.container),
            loader: $(this.selectors.loader),
            emptyState: $(this.selectors.emptyState),
            saveBtn: $(this.selectors.saveBtn),
            saveIcon: $(this.selectors.saveIcon),
            saveText: $(this.selectors.saveText),
            alertContainer: $(this.selectors.alertContainer)
        };
    }

    bindEvents() {
        // Handle Template Change
        this.dom.templateSelect.on('change', (e) => {
            const templateId = $(e.target).val();
            if (templateId) {
                // Pass null for prices to force fetch new ones or empty
                this.fetchLayers(templateId, null);
            } else {
                this.dom.container.html('').append(this.dom.emptyState);
                this.dom.saveBtn.prop('disabled', true);
            }
        });

        // Handle Toggle Switches (Event Delegation)
        this.dom.container.on('change', '.price-toggle', (e) => {
            this.handleToggleChange($(e.currentTarget));
        });
    }

    setupValidation() {
        this.dom.form.validate({
            ignore: ":disabled",
            errorElement: 'div',
            errorClass: 'invalid-feedback',
            highlight: (element) => $(element).addClass('is-invalid'),
            unhighlight: (element) => $(element).removeClass('is-invalid'),
            errorPlacement: (error, element) => {
                if (element.parent('.input-group').length) {
                    error.insertAfter(element.parent());
                } else {
                    error.insertAfter(element);
                }
            },
            submitHandler: () => {
                this.submitFormViaAjax();
                return false;
            }
        });
    }

    fetchLayers(templateId, preloadedPrices) {
        this.toggleLoadingState(true);

        // Construct URL: replace :id placeholder and add product_id param
        const url = this.urls.fetchLayers.replace(':id', templateId) +
            `?product_id=${this.data.currentProductId}`;

        $.ajax({
            url: url,
            method: 'GET',
            success: (response) => {
                if (response.success) {
                    const pricesToRender = preloadedPrices || response.prices || {};
                    this.renderConfiguration(response.layers, pricesToRender);
                } else {
                    this.showAlert('danger', this.messages.errorLoadLayers);
                }
            },
            error: (err) => {
                console.error(err);
                this.showAlert('danger', this.messages.errorSystem);
            },
            complete: () => {
                this.toggleLoadingState(false);
            }
        });
    }

    renderConfiguration(layers, savedPrices) {
        this.dom.container.empty();

        if (layers.length === 0) {
            this.dom.container.html(`<div class="alert alert-warning">${this.messages.noLayers}</div>`);
            return;
        }
        const allowAllTechniques = true;

        this.data.factories.forEach(factory => {
            let currentFactoryTechniques = this.data.techniques;

            if (!allowAllTechniques) {
                let allowedTechIds = factory.metas?.production_techniques || [];
                if (typeof allowedTechIds === 'string') {
                    try { allowedTechIds = JSON.parse(allowedTechIds); } catch { allowedTechIds = []; }
                }

                currentFactoryTechniques = this.data.techniques.filter(tech =>
                    allowedTechIds.includes(String(tech.id)) || allowedTechIds.includes(tech.id)
                );
            }

            if (currentFactoryTechniques.length === 0) return;

            const $card = this.buildCardHtml(factory, currentFactoryTechniques, layers, savedPrices);
            this.dom.container.append($card);
        });
    }
    escapeHtml(str) {
        if (str == null) return '';
        return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
    }
    buildCardHtml(factory, techniques, layers, savedPrices) {
        // 1. Create Wrapper
        const factoryTitle = this.escapeHtml(factory.business?.company_name) || 'Factory';
        const cardHtml = `
            <div class="card shadow-sm mb-4 animation-fade-in">
                <div class="card-header fw-semibold bg-light d-flex justify-content-between align-items-center">
                    <span><i class="fas fa-industry me-2 text-secondary"></i> ${factoryTitle}</span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover align-middle mb-0">
                            <thead class="table-light"><tr></tr></thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
            </div>
        `;
        const $card = $(cardHtml);

        // 2. Build Header
        const $theadRow = $card.find('thead tr');
        $theadRow.append(`<th class="text-start" style="width: 20%;">${this.messages.layerName}</th>`);
        techniques.forEach(tech => $theadRow.append(`<th class="text-center">${tech.name}</th>`));

        // 3. Build Body
        const $tbody = $card.find('tbody');
        layers.forEach(layer => {
            const $tr = $('<tr>');
            $tr.append(`<td class="fw-medium bg-light">${layer.layer_name}</td>`);

            techniques.forEach(tech => {
                // Generate IDs
                const fieldPrefix = `layer_printing[${layer.id}][${factory.id}][${tech.id}]`;
                const checkboxId = `cb_${layer.id}_${factory.id}_${tech.id}`;
                const inputId = `input_${layer.id}_${factory.id}_${tech.id}`;

                // Check saved values
                let savedPriceValue = null;
                if (savedPrices && savedPrices[layer.id] && savedPrices[layer.id][factory.id]) {
                    savedPriceValue = savedPrices[layer.id][factory.id][tech.id];
                }
                const isChecked = (savedPriceValue !== null && savedPriceValue !== undefined);
                const inputValue = isChecked ? savedPriceValue : '';

                const tdContent = `
                    <td class="text-center p-2">
                        <div class="d-flex flex-column align-items-center gap-2">
                            <div class="form-check form-switch">
                                <input class="form-check-input price-toggle" type="checkbox" 
                                    id="${checkboxId}"
                                    data-target="${inputId}"
                                    name="${fieldPrefix}[enabled]" 
                                    value="1"
                                    ${isChecked ? 'checked' : ''}>
                            </div>
                            <div class="input-group input-group-sm" style="width: 120px;">
                                <span class="input-group-text">${this.settings.currencySymbol}</span>
                                <input type="number" 
                                    id="${inputId}"
                                    class="form-control text-end price-input" 
                                    name="${fieldPrefix}[price]" 
                                    placeholder="0.00" min="0" step="0.01" 
                                    value="${inputValue}"
                                    ${!isChecked ? 'disabled' : ''} required>
                            </div>
                        </div>
                    </td>
                `;
                $tr.append(tdContent);
            });
            $tbody.append($tr);
        });

        return $card;
    }

    handleToggleChange($toggle) {
        const targetId = $toggle.data('target');
        const $input = $('#' + targetId);

        if ($toggle.is(':checked')) {
            if ($input.val() === '' || $input.val() === null) {
                $input.val(0);
            }
            $input.prop('disabled', false).focus();
        } else {
            $input.prop('disabled', true).val('').removeClass('is-invalid');
            $input.next('.invalid-feedback').remove();
        }
    }

    submitFormViaAjax() {
        this.setSavingState(true);
        const formData = new FormData(this.dom.form[0]);

        $.ajax({
            url: this.dom.form.attr('action'),
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: (response) => {
                this.showAlert(response.success ? 'success' : 'warning', response.message);
                if (response.success) {
                    window.location.href = this.urls.redirect;
                }
            },
            error: (xhr) => {
                let msg = this.messages.errorUnknown;
                if (xhr.status === 422) {
                    const errors = Object.values(xhr.responseJSON.errors).map(e => `<li>${this.escapeHtml(e[0])}</li>`).join('');
                    msg = `<ul>${errors}</ul>`;
                    this.showAlert('danger', `${this.messages.errorValidation}: ${msg}`);
                } else {
                    this.showAlert('danger', this.escapeHtml(xhr.responseJSON?.message) || msg);
                }
            },
            complete: () => {
                this.setSavingState(false);
            }
        });
    }

    toggleLoadingState(isLoading) {
        if (isLoading) {
            this.dom.loader.removeClass('d-none');
            this.dom.container.addClass('d-none');
            this.dom.saveBtn.prop('disabled', true);
        } else {
            this.dom.loader.addClass('d-none');
            this.dom.container.removeClass('d-none');
            this.dom.saveBtn.prop('disabled', false);
        }
    }

    setSavingState(isSaving) {
        const $spinner = this.dom.saveBtn.find('.spinner-border');
        if (isSaving) {
            this.dom.saveBtn.prop('disabled', true);
            this.dom.saveIcon.addClass('d-none');
            $spinner.removeClass('d-none');
            this.dom.saveText.text(this.messages.saving);
        } else {
            this.dom.saveBtn.prop('disabled', false);
            this.dom.saveIcon.removeClass('d-none');
            $spinner.addClass('d-none');
            this.dom.saveText.text(this.messages.save);
        }
    }

    showAlert(type, message) {
        const html = `
            <div class="alert alert-${type} alert-dismissible fade show animation-fade-in">
                ${message} <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        `;
        this.dom.alertContainer.stop(true, true).html(html).show();
        $('html, body').animate({ scrollTop: 0 }, 'fast');

        if (type === 'success') {
            setTimeout(() => this.dom.alertContainer.find('.alert').fadeOut(), 5000);
        }
    }
}