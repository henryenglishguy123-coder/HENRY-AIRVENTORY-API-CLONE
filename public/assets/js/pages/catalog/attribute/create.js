function generateKeyFromLabel(label) {
    const sanitized = label.trim().toLowerCase().replace(/\s+/g, '-').replace(/[^\w-]+/g, '');
    return sanitized || label.trim().toLowerCase().substring(0, 10) || 'attribute';
}

$(document).ready(function () {
    $('#label').on('input', function () {
        const label = $(this).val();
        const key = generateKeyFromLabel(label);
        $('#attribute_code').val(key);


        $(this).next('.error-message').remove();
        $('#attribute_code').next('.error-message').remove();
    });

    $.validator.addMethod("checkColorOrImage", function (value, element) {
        var row = $(element).closest('.key-value-row');
        var colorInput = row.find('input[name="options[value_color][]"]').val();
        var fileInputElement = row.find('input[name="options[value_file][]"]')[0];
        var hasFile = fileInputElement && fileInputElement.files && fileInputElement.files.length > 0;

        return colorInput !== '' || hasFile;
    });


    $('#attribute-form').validate({
        rules: {
            label: {
                required: true
            },
            attribute_type: {
                required: true
            },
            attribute_code: {
                required: true
            },
            industry_id: {
                required: true
            }
        },
        messages: {
            label: {
                required: "Please enter a label"
            },
            attribute_code: {
                required: "Please enter a key"
            },
            attribute_type: {
                required: "Please select an attribute type"
            },
            industry_id: {
                required: "Please select an industry"
            }
        },
        errorPlacement: function (error, element) {
            var name = element.attr("name");
            var row = element.closest('.key-value-row');

            if (name === "options[key][]") {
                error.appendTo(row.find('span.error-key'));
            } else if (name === "options[value_color][]") {
                error.appendTo(row.find('span#error-value'));
            } else if (name === "options[value_text][]") {
                error.appendTo(row.find('span.error-value'));
            } else {
                error.insertAfter(element);
            }
        },
        submitHandler: function (form, event) {

            const selectedType = $('#attribute_type').val();
            const optionRequiredTypes = ['visual_swatch', 'text_swatch', 'multiple_select', 'select'];
            let isValid = true;

            // Clear any existing error messages
            $('#key-value-container .alert.alert-danger.error-message').remove();

            // Validate each row and collect results
            $('#key-value-fields .key-value-row').each(function () {
                const rowIsValid = validateRow($(this), selectedType);
                if (!rowIsValid) {
                    isValid = false;
                }
            });

            // Check if option-based attribute types have at least one option row
            if (optionRequiredTypes.includes(selectedType)) {
                const rowCount = $('#key-value-fields .key-value-row').length;
                if (rowCount === 0) {
                    isValid = false;
                    // Show inline error message
                    $('#key-value-container').prepend('<div class="alert alert-danger error-message" role="alert">At least one option is required for this attribute type.</div>');
                }
            }

            // Prevent form submission if validation failed
            if (!isValid) {
                event.preventDefault();
                return false;
            }

            var formData = new FormData(form);

            event.preventDefault();

            const $submitButton = $(form).find('button[type="submit"]');
            $submitButton.prop('disabled', true).html('<i class="spinner-border spinner-border-sm"></i> processing');

            $('#key-value-fields .key-value-row').each(function () {
                var key = $(this).find('input[name="options[key][]"]').val();
                var colorValue = $(this).find('input[name="options[value_color][]"]').val();
                var fileInput = $(this).find('input[name="options[value_file][]"]')[0];
                var file = fileInput ? fileInput.files[0] : null;
                var textValue = $(this).find('input[name="options[value_text][]"]').val();

                if (key) {
                    if (colorValue && file) {
                        formData.append('options[' + key + ']', file);
                    } else if (colorValue && !file) {
                        formData.append('options[' + key + ']', colorValue);
                    } else if (file && !colorValue) {
                        formData.append('options[' + key + ']', file);
                    } else if (textValue) {
                        formData.append('options[' + key + ']', textValue);
                    }
                }
            });

            $('.error-message').remove();

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
                        // Keep submit button disabled to prevent duplicate submissions
                        $submitButton.prop('disabled', true);
                        // Delay redirect to allow users to read the success message
                        setTimeout(function () {
                            window.location.href = window.attributeConfig.redirectUrl;
                        }, 1750); // 1.75 seconds delay
                    }
                },
                error: function (xhr) {
                    if (xhr.status === 422) {
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
                    // Only re-enable button on error (success handler keeps it disabled until redirect)
                    if ($('#success-message').hasClass('d-none')) {
                        $submitButton.prop('disabled', false).html(window.attributeConfig.submitText);
                    }
                }
            });
            return false;
        }
    });

});

function validateRow(row, selectedType) {
    row.find('.error').removeClass('error');
    row.find('.error-message').text('');

    const keyInput = row.find('input[name="options[key][]"]');
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
        const colorInput = row.find('input[name="options[value_color][]"]');
        const fileInput = row.find('input[name="options[value_file][]"]');
        colorInput.rules("add", {
            checkColorOrImage: true,
            messages: {
                checkColorOrImage: "Either color or image is required."
            }
        });
        if (!colorInput.valid()) {
            rowIsValid = false;
        }
    }
    return rowIsValid;
}

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

        validateRow($('#key-value-fields .key-value-row'), selectedType);
        updateRemoveButtonsState();
    } else {
        $('#key-value-container').html('');
    }
});

$(document).on('click', '#add-more-btn', function () {
    var selectedType = $('#attribute_type').val();
    $('#key-value-fields').append(addKeyValueInput(selectedType));

    validateRow($('#key-value-fields .key-value-row:last'), selectedType);
    updateRemoveButtonsState();
});

$(document).on('click', '.remove-key-value-btn', function () {
    var selectedType = $('#attribute_type').val();
    var optionRequiredTypes = ['visual_swatch', 'text_swatch', 'multiple_select', 'select'];
    var currentRowCount = $('#key-value-fields .key-value-row').length;

    // Check if this attribute type requires options
    if (optionRequiredTypes.includes(selectedType)) {
        // Prevent removal if only one row remains
        if (currentRowCount <= 1) {
            alert('At least one option is required for this attribute type.');
            return;
        }
    }

    $(this).closest('.key-value-row').remove();
    updateRemoveButtonsState();
});

function updateRemoveButtonsState() {
    var selectedType = $('#attribute_type').val();
    var optionRequiredTypes = ['visual_swatch', 'text_swatch', 'multiple_select', 'select'];
    var currentRowCount = $('#key-value-fields .key-value-row').length;

    // Only update state if this attribute type requires options
    if (optionRequiredTypes.includes(selectedType)) {
        $('.remove-key-value-btn').each(function () {
            if (currentRowCount <= 1) {
                $(this).prop('disabled', true).attr('title', 'At least one option is required');
            } else {
                $(this).prop('disabled', false).removeAttr('title');
            }
        });
    } else {
        // Enable all remove buttons if attribute type doesn't require options
        $('.remove-key-value-btn').prop('disabled', false).removeAttr('title');
    }
}

function addKeyValueInput(selectedType) {
    var valueField;

    if (selectedType === 'visual_swatch') {
        valueField = `
                <div class="row mb-2 key-value-row">
                    <div class="col-md-5">
                        <input type="text" class="form-control" name="options[key][]" placeholder="value">
                        <span class="text-danger error-message error-key"></span>
                    </div>
                    <div class="col-md-5">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group" style="margin-bottom: 0rem !important;">
                                    <div class="input-group">
                                        <input type="color" class="form-control color-input" name="options[value_color][]" onchange="updateColorInput(this)" style="padding: 0.4rem 0.4rem !important;max-width: 3rem;">
                                        <input type="text" class="form-control" name="color_code[]" placeholder="Hex Code" readonly>
                                    </div>
                                    <span class="text-danger error-message" id="error-value"></span>
                                </div>
                            </div>
                            <div class="col-md-2 d-flex align-items-center">
                                <span style="font-size: small;color: #d88aff;">OR</span>
                            </div>
                            <div class="col-md-4">
                                <div class="image-preview" style="width: 70px; height: 37px; border: 1px solid #c7c8cb; display: flex; align-items: center; justify-content: center; background-color: #fff; cursor: pointer; padding: 4px; background-size: cover;background-position: center;background-repeat: no-repeat;" onclick="this.nextElementSibling.click();">
                                    <span class="text-center" style="padding: 4px;background: #f6f3f3;font-size: 12px;">No Image</span>
                                </div>
                                <input type="file" class="form-control mt-2" name="options[value_file][]" accept="image/*" onchange="previewImage(this)" style="display: none;">
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2 d-flex align-items-center">
                        <button type="button" class="btn btn-danger remove-key-value-btn"><i class="mdi mdi-minus"></i></button>
                    </div>
                </div>
            `;
    } else {
        valueField = `
                <div class="row mb-2 key-value-row">
                    <div class="col-md-5">
                        <input type="text" class="form-control" name="options[key][]" placeholder="value">
                        <span class="text-danger error-message error-key"></span>
                    </div>
                    <div class="col-md-5">
                        <input type="text" class="form-control" name="options[value_text][]" placeholder="value">
                        <span class="text-danger error-message error-value"></span>
                    </div>
                    <div class="col-md-2 d-flex align-items-center">
                        <button type="button" class="btn btn-danger remove-key-value-btn"><i class="mdi mdi-minus"></i></button>
                    </div>
                </div>
            `;
    }

    return valueField;
}

function updateColorInput(input) {
    var colorValue = input.value;
    $(input).siblings('input[name="color_code[]"]').val(colorValue);
}

function previewImage(input) {
    // Check if files exist and at least one file is selected
    if (!input.files || input.files.length === 0) {
        // Clear preview if no file was selected
        var imagePreview = $(input).siblings('.image-preview');
        imagePreview.css('background-image', '');
        imagePreview.find('span').show();
        return;
    }

    var reader = new FileReader();

    // Handle successful file read
    reader.onload = function (e) {
        var imagePreview = $(input).siblings('.image-preview');
        imagePreview.css('background-image', 'url(' + e.target.result + ')');
        imagePreview.find('span').hide();
    };

    // Handle file read errors
    reader.onerror = function (e) {
        console.error('Error reading file:', e);
        // Show user-friendly message (you can customize this)
        alert('Failed to load image. Please try selecting a different file.');
        // Reset/hide preview on error
        var imagePreview = $(input).siblings('.image-preview');
        imagePreview.css('background-image', '');
        imagePreview.find('span').show();
        // Clear the file input
        $(input).val('');
    };

    reader.readAsDataURL(input.files[0]);
}