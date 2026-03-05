$(document).ready(function () {
    $('#technique-form').validate({
        rules: {
            name: {
                required: true,
                maxlength: 255
            },
            status: {
                required: true
            }
        },
        messages: {
            name: {
                required: "Please enter the technique name."
            },
            status: {
                required: "Please select a status."
            }
        },
        errorElement: 'div',
        errorPlacement: function (error, element) {
            error.addClass('invalid-feedback');
            var name = element.attr("name");
            var errorContainer = element.siblings(`.error-${name}`);
            if (errorContainer.length) {
                errorContainer.append(error);
            } else {
                error.insertAfter(element);
            }
        },
        highlight: function (element, errorClass, validClass) {
            $(element).addClass('is-invalid').removeClass('is-valid');
        },
        unhighlight: function (element, errorClass, validClass) {
            $(element).removeClass('is-invalid').addClass('is-valid');
        },
        submitHandler: function (form, event) {
            event.preventDefault();

            var formData = new FormData(form);
            const $submitButton = $('#submitBtn');
            const $spinner = $submitButton.find('.spinner-border');

            // Show loading state
            $submitButton.prop('disabled', true);
            $spinner.removeClass('d-none');

            // Clear previous server-side errors
            $('.is-invalid').removeClass('is-invalid');
            $('.invalid-feedback').empty();

            $.ajax({
                url: window.productionTechniqueConfig.storeUrl,
                type: 'POST',
                headers: { 'Authorization': 'Bearer ' + getCookie('jwt_token') },
                data: formData,
                contentType: false,
                processData: false,
                success: function (response) {
                    if (response.success) {
                        toastr.success(response.message);
                        $(form)[0].reset();
                        setTimeout(function () {
                            window.location.href = window.productionTechniqueConfig.redirectUrl;
                        }, 1000); // 1s delay
                    } else {
                        // Restore button state if success flag is false but didn't throw 422
                        $submitButton.prop('disabled', false);
                        $spinner.addClass('d-none');
                        toastr.error(response.message || "Something went wrong.");
                    }
                },
                error: function (xhr) {
                    // Restore button state
                    $submitButton.prop('disabled', false);
                    $spinner.addClass('d-none');

                    if (xhr.status === 422) {
                        let errors = xhr.responseJSON.errors;
                        $.each(errors, function (field, messages) {
                            let inputField = $(`[name="${field}"]`);
                            if (inputField.length) {
                                inputField.addClass('is-invalid');
                                let errorContainer = inputField.siblings(`.error-${field}`);
                                if (errorContainer.length) {
                                    errorContainer.html(`<div>${messages[0]}</div>`);
                                } else {
                                    inputField.after(`<div class="invalid-feedback d-block">${messages[0]}</div>`);
                                }
                            }
                        });
                        toastr.error("Please correct the highlighted errors.");
                    } else {
                        toastr.error("An error occurred. Please try again.");
                    }
                }
            });
            return false;
        }
    });
});
