$(document).ready(function () {

    $.ajaxSetup({
        headers: {
            'X-CSRF-TOKEN': window.attributeEditConfig.csrfToken
        }
    });

    const selectedType = $('#attribute_type').val();

    $('#label').on('input', function () {
        const label = $(this).val();
        $('#attribute_code').val(label);

        $(this).next('.error-message').remove();
        $('#attribute_code').next('.error-message').remove();
    });

    // Counter for generating unique indices for new option rows
    window.optionIndexCounter = window.optionIndexCounter || 0;
    
    $.validator.addMethod("checkColorOrImage", function (value, element) {
        var row = $(element).closest('.key-value-row');
        var optionIndex = row.data('option-index');
        var colorInput = row.find('input[name="options[' + optionIndex + '][value_color]"]').val();
        var fileInputElement = row.find('input[name="options[' + optionIndex + '][value_file]"]')[0];
        var hasFile = fileInputElement && fileInputElement.files && fileInputElement.files.length > 0;
        var hasExplicitColor = colorInput !== '' && colorInput !== '#000000';
        return hasExplicitColor || hasFile;
    });

    $('#attribute-form').validate({
        rules: {
            label: { required: true },
            attribute_type: { required: true },
            industry_id: { required: true }
        },
        messages: {
            label: { required: "Please enter a label" },
            attribute_code: { required: "Please enter a key" },
            attribute_type: { required: "Please select an attribute type" },
            industry_id: { required: "Please select an industry" }
        },
        errorPlacement: function (error, element) {
            var name = element.attr("name");
            var row = element.closest('.key-value-row');

            if (name && name.match(/^options\[.+\]\[key\]$/)) {
                error.appendTo(row.find('span.error-key'));
            } else if (name && (name.match(/^options\[.+\]\[value_color\]$/) || name.match(/^options\[.+\]\[value_text\]$/))) {
                error.appendTo(row.find('span.error-value'));
            } else {
                error.insertAfter(element);
            }
        },
        submitHandler: function (form, event) {
            event.preventDefault();

            var formData = new FormData(form);
            formData.append('attribute_id', window.attributeEditConfig.attributeId);

            const $submitButton = $(form).find('button[type="submit"]');
            $submitButton.prop('disabled', true).html('<i class="spinner-border spinner-border-sm"></i> processing');

            $.ajax({
                url: $(form).attr('action'),
                type: 'POST',
                data: formData,
                contentType: false,
                processData: false,
                success: function (response) {
                    if (response.success) {
                        $('#success-message').removeClass('d-none').text(response.message);
                        $(form)[0].reset();
                        setTimeout(function () {
                            window.location.href = window.attributeEditConfig.redirectUrl;
                        }, 1500);
                    }
                },
                error: function (xhr) {
                    if (xhr.status === 422) {
                        $('.error-message').remove();
                        let errors = xhr.responseJSON.errors;
                        $.each(errors, function (field, messages) {
                            let inputField = $('[name="' + field + '"]');
                            if (inputField.length) {
                                inputField.after('<span class="error-message text-danger">' + messages[0] + '</span>');
                            }
                        });
                    } else {
                        $('#error-message').removeClass('d-none').text("An error occurred. Please try again.");
                    }
                },
                complete: function () {
                    $submitButton.prop('disabled', false).html('update');
                }
            });

            return false;
        }
    });

    $(document).on('change', '#attribute_type', function () {
        var selectedType = $(this).val();

        if (selectedType === 'visual_swatch' || selectedType === 'text_swatch' || selectedType === 'multiple_select' || selectedType === 'select') {
            $('#key-value-container').html(`
                    <div class="mb-3">
                        <label for="options" class="form-label">Options</label>
                        <div id="key-value-fields">
                            ${addKeyValueInput(selectedType)}
                        </div>
                        <button type="button" class="btn btn-outline-primary" id="add-more-btn"><i class="mdi mdi-plus"></i> Add More</button>
                    </div>
                `);
        } else {
            $('#key-value-container').html('');
        }
    });

    $(document).on('click', '#add-more-btn', function () {
        var selectedType = $('#attribute_type').val();
        $('#key-value-fields').append(addKeyValueInput(selectedType));

        validateRow($('#key-value-fields .key-value-row:last'), selectedType);
    });

    $(document).on('click', '.remove-key-value-btn', function () {
        const $btn = $(this);
        const $row = $btn.closest('.key-value-row');
        const optionId = $btn.data('id');
        $row.find('.error-message').html('');
        if (!optionId) {
            $row.remove();
            return;
        }
        Swal.fire({
            title: window.attributeEditConfig.texts.confirmTitle,
            text: window.attributeEditConfig.texts.confirmText,
            icon: "warning",
            showCancelButton: true,
            confirmButtonText: window.attributeEditConfig.texts.proceed,
            cancelButtonText: window.attributeEditConfig.texts.cancel,
            reverseButtons: true
        }).then((result) => {
            if (result.isConfirmed) {
                $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span>');
                $.ajax({
                    url: window.attributeEditConfig.deleteOptionUrl,
                    type: 'POST',
                    data: { option_id: optionId },
                    success: function (response) {
                        if (response.success) {
                            $row.remove();
                        } else {
                            $row.find('.error-message').html(response.message);
                            restoreButton();
                        }
                    },
                    error: function (xhr) {
                        const msg = xhr.responseJSON ? xhr.responseJSON.message : null;
                        toastr.error(msg || window.attributeEditConfig.texts.error);
                        restoreButton();
                    }
                });
                function restoreButton() {
                    $btn.prop('disabled', false).html('<i class="mdi mdi-minus"></i>');
                }

            }
        });
    });
});

function validateRow(row, selectedType) {
    row.find('.error').removeClass('error');
    row.find('.error-message').text('');
    var optionIndex = row.data('option-index');
    if (!optionIndex) {
        var inputEl = row.find('input[name^="options["]').first();
        var nameAttr = inputEl.attr('name');
        if (nameAttr) {
            var match = nameAttr.match(/\[(.+?)\]/);
            if (match) {
                optionIndex = match[1];
            }
        }
        if (!optionIndex) {
            console.warn('Could not determine option index for row');
            return false;
        }
    }
    const keyInput = row.find('input[name="options[' + optionIndex + '][key]"]');
    keyInput.rules("add", {
        required: true,
        messages: {
            required: "Key is required"
        }
    });
    let rowIsValid = true;
    if (!keyInput.valid()) {
        rowIsValid = false;
    }
    if (selectedType === 'visual_swatch') {
        const colorInput = row.find('input[name="options[' + optionIndex + '][value_color]"]');
        const fileInput = row.find('input[name="options[' + optionIndex + '][value_file]"]');
        colorInput.rules("add", {
            checkColorOrImage: true,
            messages: {
                checkColorOrImage: "Either color or image is required."
            }
        });
        fileInput.rules("add", {
            checkColorOrImage: true,
            messages: {
                checkColorOrImage: "Either color or image is required."
            }
        });
        if (!colorInput.valid() || !fileInput.valid()) {
            rowIsValid = false;
        }
    }
    return rowIsValid;
}

function addKeyValueInput(selectedType) {
    // Generate a unique index for new option rows
    window.optionIndexCounter = (window.optionIndexCounter || 0) + 1;
    var optionIndex = 'new_' + Date.now() + '_' + window.optionIndexCounter;
    
    var valueField;

    if (selectedType === 'visual_swatch') {
        valueField = `
                <div class="row mb-2 key-value-row" data-option-index="${optionIndex}">
                    <div class="col-md-5">
                        <input type="text" class="form-control" name="options[${optionIndex}][key]" placeholder="value">
                        <input type="hidden" class="form-control" name="options[${optionIndex}][id]" value="" placeholder="Key">
                        <span class="text-danger error-message error-key"></span>
                    </div>
                    <div class="col-md-5">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group" style="margin-bottom: 0rem !important;">
                                    <div class="input-group">
                                        <input type="color" class="form-control color-input" name="options[${optionIndex}][value_color]" onchange="updateColorInput(this)" style="padding: 0.4rem 0.4rem !important;max-width: 3rem;">
                                        <input type="text" class="form-control" name="options[${optionIndex}][color_code]" placeholder="Hex Code" readonly>
                                    </div>
                                    <span class="text-danger error-message error-value"></span>
                                </div>
                            </div>
                            <div class="col-md-2 d-flex align-items-center">
                                <span style="font-size: small;color: #d88aff;">OR</span>
                            </div>
                            <div class="col-md-4">
                                <div class="image-preview" style="width: 70px; height: 37px; border: 1px solid #c7c8cb; display: flex; align-items: center; justify-content: center; background-color: #fff; cursor: pointer; padding: 4px; background-size: cover;background-position: center;background-repeat: no-repeat;" onclick="this.nextElementSibling.click();">
                                    <span class="text-center" style="padding: 4px;background: #f6f3f3;font-size: 12px;">No Image</span>
                                </div>
                                <input type="file" class="form-control mt-2" name="options[${optionIndex}][value_file]" accept="image/*" onchange="previewImage(this)" style="display: none;">
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2 d-flex align-items-center">
                        <button type="button" class="btn btn-danger remove-key-value-btn" data-id=""><i class="mdi mdi-minus"></i></button>
                    </div>
                </div>
            `;
    } else {
        valueField = `
                <div class="row mb-2 key-value-row" data-option-index="${optionIndex}">
                    <div class="col-md-5">
                        <input type="text" class="form-control" name="options[${optionIndex}][key]" placeholder="value">
                        <input type="hidden" class="form-control" name="options[${optionIndex}][id]" value="" placeholder="Key">
                        <span class="text-danger error-message error-key"></span>
                    </div>
                    <div class="col-md-5">
                        <input type="text" class="form-control" name="options[${optionIndex}][value_text]" placeholder="value">
                        <span class="text-danger error-message error-value"></span>
                    </div>
                    <div class="col-md-2 d-flex align-items-center">
                        <button type="button" class="btn btn-danger remove-key-value-btn" data-id=""><i class="mdi mdi-minus"></i></button>
                    </div>
                </div>
            `;
    }

    return valueField;
}

function updateColorInput(input) {
    var colorValue = input.value;
    var row = $(input).closest('.key-value-row');
    var optionIndex = row.data('option-index');
    if (!optionIndex) {
        var name = $(input).attr('name');
        var match = name.match(/\[(.+?)\]/);
        if (match) optionIndex = match[1];
    }
    $(input).siblings('input[name="options[' + optionIndex + '][color_code]"]').val(colorValue);
}

function previewImage(input) {
    if (!input.files || !input.files[0]) {
        return;
    }
    var reader = new FileReader();
    reader.onload = function (e) {
        var imagePreview = $(input).siblings('.image-preview');
        imagePreview.css('background-image', 'url(' + e.target.result + ')');
        imagePreview.find('span').hide();
    };
    reader.readAsDataURL(input.files[0]);
}