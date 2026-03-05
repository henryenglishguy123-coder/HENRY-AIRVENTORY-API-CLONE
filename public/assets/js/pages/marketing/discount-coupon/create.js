$(function() {
    const $form = $('#coupon_form');
    const $discountType = $('#discount_type');
    const $amountType = $('#amount_type');
    const csrfToken = $('meta[name="csrf-token"]').attr('content');
    
    // Routes and Data
    const routeCheckCode = $form.data('route-check-code');
    const routeSearch = $form.data('route-search');
    const routeIndex = $form.data('route-index');
    
    // Translations
    const t = {
        percentageValue: $form.data('lang-percentage-value'),
        fixedAmount: $form.data('lang-fixed-amount'),
        minProductPrice: $form.data('lang-min-product-price'),
        minOrderValue: $form.data('lang-min-order-value'),
        codeUppercase: $form.data('lang-code-uppercase'),
        checkingAvailability: $form.data('lang-checking'),
        codeTaken: $form.data('lang-code-taken'),
        codeValidationFailed: $form.data('lang-code-failed'),
        searchSelect: $form.data('lang-search-select'),
        endDateAfterStart: $form.data('lang-end-date-after-start'),
        maxUsesCustomer: $form.data('lang-max-uses-customer'),
        startDateBeforeEnd: $form.data('lang-start-date-before-end'),
        saving: $form.data('lang-saving'),
        successTitle: $form.data('lang-success-title'),
        successText: $form.data('lang-success-text'),
        titleRequired: $form.data('lang-title-required'),
        codeRequired: $form.data('lang-code-required'),
        amountRequired: $form.data('lang-amount-required'),
        valueGreaterThanZero: $form.data('lang-value-greater-zero'),
        percentageMax: $form.data('lang-percentage-max'),
        customerRequired: $form.data('lang-customer-required'),
        productRequired: $form.data('lang-product-required'),
        categoryRequired: $form.data('lang-category-required'),
        supplierRequired: $form.data('lang-supplier-required'),
        numberValid: $form.data('lang-number-valid'),
        maxUsesCustomerTotal: $form.data('lang-max-uses-customer-total'),
        errorGeneric: $form.data('lang-error-generic')
    };

    // Initialize default dates
    const now = new Date();
    const pad = (num) => num.toString().padStart(2, '0');
    const dateStr = `${now.getFullYear()}-${pad(now.getMonth() + 1)}-${pad(now.getDate())}`;
    const timeStr = `${pad(now.getHours())}:${pad(now.getMinutes())}`;
    if (!$('#start_date').val()) $('#start_date').val(dateStr);
    if (!$('#start_time').val()) $('#start_time').val(timeStr);

    function updateAmountField() {
        const type = $amountType.val();
        const $amountValue = $('#amount_value');
        const currencySymbol = '$';

        if (type === 'Percentage') {
            $('#amount_type_label').html(`${t.percentageValue} <span class="text-danger">*</span>`);
            $('#amount_suffix').text('%');
            $amountValue.attr({
                'max': 100,
                'placeholder': 'Max 100'
            }).removeClass('is-invalid');
            $amountValue.closest('.input-group').find('.invalid-feedback.dynamic-error').remove();
        } else {
            $('#amount_type_label').html(`${t.fixedAmount} <span class="text-danger">*</span>`);
            $('#amount_suffix').text(currencySymbol);
            $amountValue.removeAttr('max').attr('placeholder', 'Enter fixed amount').removeClass('is-invalid');
            $amountValue.closest('.input-group').find('.invalid-feedback.dynamic-error').remove();
        }
    }
    $amountType.on('change', updateAmountField);
    updateAmountField();

    function updateMinPurchaseSection() {
        const discountFor = $discountType.val();
        const selectedType = $('input[name="min_requirement_type"]:checked').val();
        const labelText = discountFor === 'Product' ? t.minProductPrice : t.minOrderValue;

        $('#min_value_label, #min_price_input_label').text(labelText);

        $('#min_qty_group').toggle(selectedType === 'quantity');
        $('#min_price_group').toggle(selectedType === 'value');
        // Clear values when hiding
        if (selectedType !== 'quantity') $('#min_qty').val('');
        if (selectedType !== 'value') $('#min_price').val('');
    }
    $('input[name="min_requirement_type"]').on('change', updateMinPurchaseSection);
    updateMinPurchaseSection();

    $('input[name="eligibility"]').on('change', function() {
        const isSpecific = this.value === 'Specific Customers';
        const $specificDiv = $('#specific_customers_div');
        $specificDiv.slideToggle(isSpecific ? 200 : 0);
        if (!isSpecific) $('#customers_select').val(null).trigger('change');
    }).trigger('change');

    function cleanupApplyToRadioValidation() {
        const $radioGroupInputs = $('input[name="apply_to_radio"]');
        $radioGroupInputs.removeClass('is-invalid');
        $radioGroupInputs.each(function() {
            $(this).nextAll('.invalid-feedback.dynamic-error').remove();
            $(this).nextAll('.invalid-feedback').remove();
        });
        $('#apply_to_radio_error_placement').empty().hide();
    }

    $('.apply-toggle').on('change', function() {
        const $this = $(this);
        const targetId = $this.data('target');
        cleanupApplyToRadioValidation();
        $('.apply-toggle').not(this).each(function() {
            const otherTargetId = $(this).data('target');
            $(otherTargetId).slideUp().find('select').val(null).trigger('change');
            $(otherTargetId).find('input[name="apply_to"]').val('');
        });

        if (this.checked) {
            $(targetId).slideDown(200);
            $(targetId).find('input[name="apply_to"]').val($this.val());
        } else {
            $(targetId).slideUp(200);
            $(targetId).find('input[name="apply_to"]').val('');
        }
    }).trigger('change');

    function toggleApplySection() {
        const type = $discountType.val();
        const $applyCatalogSection = $('#apply_catalog_section');
        const isProductDiscount = type === 'Product';
        $applyCatalogSection.toggle(isProductDiscount);
        if(isProductDiscount){
            $('.apply-toggle:first').prop('checked', true).trigger('change');
            $('[id^="apply_"]').find('select').val(null).trigger('change');
            $('[id^="hidden_apply_to_"]').val('');
        }
        if (!isProductDiscount) {
            $('.apply-toggle').prop('checked', false);
            $('[id^="apply_"]').find('select').val(null).trigger('change');
            $('[id^="hidden_apply_to_"]').val('');
        }
        updateMinPurchaseSection();
    }

    function enforceAmountTypeByDiscountType() {
        const isOrder = $discountType.val() === 'Order';
        $('#amount_type option[value="Percentage"]').prop('disabled', false).show();
        $('#amount_type option[value="Fixed"]').prop('disabled', false).show();
        if (isOrder) {
            if ($amountType.val() !== 'Percentage') {
                $amountType.val('Percentage').trigger('change');
            }
            $('#amount_type option[value="Fixed"]').prop('disabled', true).hide();
        }
        updateAmountField();
    }

    $discountType.on('change', function () {
        toggleApplySection();
        enforceAmountTypeByDiscountType();
    });
    enforceAmountTypeByDiscountType();
    toggleApplySection();

    $('.generate_code').on('click', function() {
        const randomCode = 'SAVE' + Math.random().toString(36).substring(2, 8).toUpperCase();
        const $code = $('#code');
        $code.val(randomCode).removeClass('is-valid is-invalid');
        $('#code_feedback').hide().empty();
        $code.trigger('blur');
    });

    let codeCheckTimeout;
    $('#code').on('blur', function() {
        const $input = $(this);
        const codeVal = $input.val().toUpperCase();
        $input.val(codeVal);
        const $feedback = $('#code_feedback');
        clearTimeout(codeCheckTimeout);
        if (!codeVal || codeVal.length < 3) {
            $input.removeClass('is-valid is-invalid');
            $feedback.hide().empty();
            return;
        }
        if (!/^[A-Z0-9]+$/.test(codeVal)) {
            $input.removeClass('is-valid').addClass('is-invalid');
            $feedback.text(t.codeUppercase).show();
            return;
        }
        $input.removeClass('is-valid is-invalid');
        $feedback.text(t.checkingAvailability).show();
        $feedback.removeClass('invalid-feedback').addClass('text-muted');
        codeCheckTimeout = setTimeout(() => {
            $.post(routeCheckCode, {
                    code: codeVal,
                    _token: csrfToken
                })
                .done(res => {
                    $feedback.removeClass('text-muted').addClass('invalid-feedback');
                    if (res.exists) {
                        $input.addClass('is-invalid').removeClass('is-valid');
                        $feedback.text(t.codeTaken).show();
                    } else {
                        $input.addClass('is-valid').removeClass('is-invalid');
                        $feedback.hide().empty();
                    }
                }).fail(() => {
                    $input.removeClass('is-valid is-invalid');
                    $feedback.hide().empty();
                    console.error(t.codeValidationFailed);
                });
        }, 300);
    });

    $('.select2').each(function() {
        const type = $(this).data('type');
        $(this).select2({
            ajax: {
                url: routeSearch,
                dataType: 'json',
                delay: 250,
                data: params => ({
                    q: params.term,
                    type
                }),
                processResults: data => ({
                    results: data
                })
            },
            placeholder: `${t.searchSelect}${type}...`,
            width: '100%',
            minimumInputLength: 1,
            allowClear: true,
            // escapeMarkup: m => m, // Removed to enable default escaping for XSS protection
            templateResult: item => item.text, // Use text only to avoid XSS from item.html
            templateSelection: item => item.text
        }).on('change', function() {
            $form.validate().element(this);
        });
    });

    // Validation Methods
    $.validator.addMethod("laterThanStart", function(value, element) {
        const $endDate = $('#end_date').val();
        const $endTime = $('#end_time').val() || '00:00';
        const $startDate = $('#start_date').val();
        const $startTime = $('#start_time').val() || '00:00';
        if (!$endDate && $('#end_time').val() === '') {
             if (element.id.startsWith('start_')) return true;
             if (element.id.startsWith('end_') && $endDate === '' && $('#end_time').val() === '') return true;
        }

        if (!$startDate) return true; 

        const startDateTime = moment(`${$startDate} ${$startTime}`);
        const endDateTime = moment(`${$endDate} ${$endTime}`);
        
        if (element.id === 'end_date' || element.id === 'end_time') {
            if ($endDate) {
                return endDateTime.isAfter(startDateTime);
            }
        }
        
        if (element.id === 'start_date' || element.id === 'start_time') {
            if ($endDate) { 
                return startDateTime.isBefore(endDateTime);
            }
        }

        return true;
    }, t.endDateAfterStart);

    $.validator.addMethod("notGreaterThanTotal", function(value, element) {
        const maxUses = parseInt($('#max_uses').val(), 10);
        const perCustomer = parseInt(value, 10);
        if (isNaN(maxUses) || isNaN(perCustomer)) {
            return true;
        }
        return perCustomer <= maxUses;
    }, t.maxUsesCustomer);

    $.validator.addMethod("earlierThanEnd", function(value, element) {
        const $endDate = $('#end_date').val();
        const $endTime = $('#end_time').val() || '00:00';
        const $startDate = $('#start_date').val();
        const $startTime = $('#start_time').val() || '00:00';
        
        if (!$endDate) return true;

        const startDateTime = moment(`${$startDate} ${$startTime}`);
        const endDateTime = moment(`${$endDate} ${$endTime}`);
        
        return startDateTime.isBefore(endDateTime);

    }, t.startDateBeforeEnd);

    const revalidateDateFields = () => {
        const $endDate = $('#end_date');
        const $endTime = $('#end_time');
        const $startDate = $('#start_date');
        const $startTime = $('#start_time');
        
        $endDate.valid();
        $endTime.valid();
        $startDate.valid();
        $startTime.valid();
    };

    $('#start_date, #start_time, #end_date, #end_time').on('change', revalidateDateFields);
    
    $.validator.addMethod('regex', (value, element, regex) => regex.test(value));
    
    const showLoader = (btn) => {
        btn.prop('disabled', true).html(`<i class="mdi mdi-loading mdi-spin"></i> ${t.saving}`);
    };
    
    const hideLoader = (btn, originalHtml) => {
        btn.prop('disabled', false).html(originalHtml);
    };
    
    const errorPlacement = (error, element) => {
        error.addClass('invalid-feedback dynamic-error');
        if (element.attr('name') === 'apply_to_radio') {
            error.appendTo($('#apply_to_radio_error_placement').show());
        }
        else if (element.hasClass('select2-hidden-accessible')) {
            error.insertAfter(element.next('.select2-container'));
        } else if (element.parent('.input-group').length) {
            error.insertAfter(element.parent());
        } else {
            error.insertAfter(element);
        }
    };
    
    $form.validate({
        ignore: [],
        errorClass: 'is-invalid',
        validClass: 'is-valid',
        errorElement: 'div',
        errorPlacement: errorPlacement,
        highlight: (el, errorClass) => {
            const $el = $(el);
            $el.addClass(errorClass);
            if ($el.hasClass('select2-hidden-accessible')) {
                $el.next('.select2-container').find('.select2-selection').addClass(errorClass);
            }
        },
        unhighlight: (el, errorClass) => {
            const $el = $(el);
            $el.removeClass(errorClass);
            if ($el.hasClass('select2-hidden-accessible')) {
                $el.next('.select2-container').find('.select2-selection').removeClass(errorClass);
            }
        },

        rules: {
            title: { required: true, maxlength: 100 },
            code: { required: true },
            discount_type: { required: true },
            amount_type: { required: true },
            amount_value: {
                required: true,
                number: true,
                min: 0.01,
                max: () => $('#amount_type').val() === 'Percentage' ? 100 : 999999
            },
            start_date: {
                required: true,
                date: true,
                earlierThanEnd: true 
            },
            start_time: {
                earlierThanEnd: true 
            },
            end_date: {
                laterThanStart: true 
            },
            end_time: {
                laterThanStart: true
            },
            status: { required: true },
            min_qty: {
                required: () => $('input[name="min_requirement_type"]:checked').val() === 'quantity',
                number: true,
                min: 1
            },
            min_price: {
                required: () => $('input[name="min_requirement_type"]:checked').val() === 'value',
                number: true,
                min: 0.01
            },
            'customers[]': {
                required: () => $('input[name="eligibility"]:checked').val() === 'Specific Customers'
            },
            'products[]': {
                required: () => $('#discount_type').val() === 'Product' && $('#apply_products_toggle').is(':checked')
            },
            'categories[]': {
                required: () => $('#discount_type').val() === 'Product' && $('#apply_categories_toggle').is(':checked')
            },
            'suppliers[]': {
                required: () => $('#discount_type').val() === 'Product' && $('#apply_suppliers_toggle').is(':checked')
            },
            max_uses: {
                number: true,
            },
            max_uses_per_customer: {
                number: true,
                notGreaterThanTotal: true
            }
        },
        messages: {
            title: t.titleRequired,
            code: {
                required: t.codeRequired,
            },
            amount_value: {
                required: t.amountRequired,
                min: t.valueGreaterThanZero,
                max: t.percentageMax
            },
            'customers[]': t.customerRequired,
            'products[]': t.productRequired,
            'categories[]': t.categoryRequired,
            'suppliers[]': t.supplierRequired,
            max_uses: {
                number: t.numberValid
            },
            max_uses_per_customer: {
                number: t.numberValid,
                notGreaterThanTotal: t.maxUsesCustomerTotal
            }
        },
        submitHandler: function(form) {
            const $btn = $form.find('button[type="submit"]');
            const originalBtnHtml = $btn.html();
            showLoader($btn);
            
            const $startDateField = $form.find('#start_date');
            const $startTimeField = $form.find('#start_time');
            const $endDateField = $form.find('#end_date');
            const $endTimeField = $form.find('#end_time');

            const originalStartTime = $startTimeField.val();
            const originalEndTime = $endTimeField.val();
            
            // Track whether times were auto-filled
            let startTimeAutoFilled = false;
            let endTimeAutoFilled = false;

            if ($startDateField.val() && !$startTimeField.val()) {
                $startTimeField.val('00:00');
                startTimeAutoFilled = true;
            }
            if ($endDateField.val() && !$endTimeField.val()) {
                $endTimeField.val('00:00');
                endTimeAutoFilled = true;
            } 

            const formData = new FormData(form);
            
            $.ajax({
                url: $form.attr('action'),
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false
            })
            .done(() => {
                Swal.fire({
                    icon: 'success',
                    title: t.successTitle,
                    text: t.successText,
                    showConfirmButton: false,
                    timer: 1500
                });
                setTimeout(() => {
                    window.location.href = routeIndex;
                }, 1500);
            })
            .fail(xhr => {
                hideLoader($btn, originalBtnHtml);
                
                // Only restore times if they were auto-filled
                if (startTimeAutoFilled) {
                    $startTimeField.val(originalStartTime);
                }
                if (endTimeAutoFilled) {
                    $endTimeField.val(originalEndTime);
                }

                if (xhr.status === 422 && xhr.responseJSON && xhr.responseJSON.errors) {
                    const errors = {};
                    $.each(xhr.responseJSON.errors, (key, messages) => {
                        // Handle Laravel array validation syntax (e.g., products.0 -> products[])
                        const fieldName = key.includes('.') ? key.split('.')[0] + '[]' : key;
                        errors[fieldName] = messages[0];
                    });
                    
                    // Use jQuery Validate to show errors
                    $form.validate().showErrors(errors);
                    
                    // Scroll to first error
                    const firstError = $form.find('.is-invalid:first');
                    if (firstError.length) {
                        $('html, body').animate({
                            scrollTop: firstError.offset().top - 100
                        }, 500);
                    }
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: t.errorGeneric || 'Something went wrong',
                    });
                }
            });
        }
    });
});
