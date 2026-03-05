$(document).ready(function () {
    $.ajaxSetup({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
            'Accept': 'application/json'
        }
    });
    $('#password-reset-form').on('submit', function (event) {
        event.preventDefault();
        const $form = $(this);
        const $resetButton = $('#reset-button');
        const $resetSpinner = $('#reset-spinner');
        const $resetText = $('#reset-text');
        const $emailInput = $('#email');
        const $responseBox = $('#response-message');
        $responseBox.html('');
        $emailInput.removeClass('is-invalid');
        $.ajax({
            url: $form.attr('action'),
            type: 'POST',
            data: $form.serialize(),
            beforeSend: function () {
                $resetButton.prop('disabled', true);
                $resetSpinner.removeClass('d-none');
                $resetText.text(messages.pleaseWait);
            },
            success: function (response) {
                $resetButton.prop('disabled', false);
                $resetSpinner.addClass('d-none');
                $resetText.text(messages.recover);
                $responseBox.html(`<div class="alert alert-success mb-0">${response.message ?? messages.passwordResetLinkSent}</div>`);
            },
            error: function (xhr) {
                $resetButton.prop('disabled', false);
                $resetSpinner.addClass('d-none');
                $resetText.text(messages.recover);
                if (xhr.status === 422 && xhr.responseJSON && xhr.responseJSON.errors) {
                    const errors = xhr.responseJSON.errors;
                    let errorHtml = '<div class="alert alert-danger mb-0"><ul class="mb-0">';
                    $.each(errors, function (field, msgs) {
                        errorHtml += '<li>' + msgs[0] + '</li>';
                        if (field === 'email') {
                            $emailInput.addClass('is-invalid');
                        }
                    });
                    errorHtml += '</ul></div>';
                    $responseBox.html(errorHtml);
                    return;
                }
                if (xhr.status === 429) {
                    $responseBox.html(`<div class="alert alert-warning mb-0">${messages.tooManyAttempts}</div>`);
                    return;
                }
                $responseBox.html(`<div class="alert alert-danger mb-0">${messages.errorOccurred}</div>`);
            }
        });
    });
});