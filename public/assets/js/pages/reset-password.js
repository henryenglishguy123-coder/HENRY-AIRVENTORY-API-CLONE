$(document).ready(function () {
    $.ajaxSetup({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
            'Accept': 'application/json'
        }
    });
    $('#reset-password-form').on('submit', function (event) {
        event.preventDefault();
        const $form = $(this);
        const $submitButton = $('#reset-password-btn');
        const $submitSpinner = $('#resetSpinner');
        const $submitText = $('#resetBtnText');
        const $globalAlert = $('#globalAlert');
        const $successMessage = $('#successMessage');
        const $passwordInput = $('#password');
        const $passwordConfirmationInput = $('#password_confirmation');
        const $passwordError = $('#passwordError');
        const $passwordConfirmationError = $('#passwordConfirmationError');
        $globalAlert.removeClass('alert-danger alert-success').addClass('d-none').text('');
        $successMessage.addClass('d-none').text('');
        $passwordInput.removeClass('is-invalid');
        $passwordConfirmationInput.removeClass('is-invalid');
        $passwordError.text('');
        $passwordConfirmationError.text('');
        $submitButton.prop('disabled', true);
        $submitSpinner.removeClass('d-none');
        $submitText.text(messages.pleaseWait);
        $.ajax({
            url: resetPasswordUrl,
            type: 'POST',
            data: $form.serialize(),

            success: function (response) {
                $submitButton.prop('disabled', false);
                $submitSpinner.addClass('d-none');
                $submitText.text(messages.resetPassword);

                $successMessage
                    .removeClass('d-none')
                    .text(messages.successReset);

                setTimeout(() => {
                    window.location.href = loginUrl;
                }, 2000);
            },

            error: function (xhr) {
                $submitButton.prop('disabled', false);
                $submitSpinner.addClass('d-none');
                $submitText.text(messages.resetPassword);

                // Validation errors
                if (xhr.status === 422 && xhr.responseJSON?.errors) {
                    const errors = xhr.responseJSON.errors;
                    let firstField = null;
                    let errorList = '<ul class="mb-0">';

                    $.each(errors, function (field, msgs) {
                        errorList += `<li>${msgs[0]}</li>`;

                        if (field === 'password') {
                            $passwordInput.addClass('is-invalid');
                            $passwordError.text(msgs[0]);
                            firstField ||= '#password';
                        }

                        if (field === 'password_confirmation') {
                            $passwordConfirmationInput.addClass('is-invalid');
                            $passwordConfirmationError.text(msgs[0]);
                            firstField ||= '#password_confirmation';
                        }
                    });

                    errorList += '</ul>';

                    $globalAlert
                        .removeClass('d-none alert-success')
                        .addClass('alert-danger')
                        .html(errorList);

                    if (firstField) $(firstField).focus();
                    return;
                }

                // Other errors
                $globalAlert
                    .removeClass('d-none alert-success')
                    .addClass('alert-danger')
                    .text(messages.unexpectedError);
            }
        });
    });
});
