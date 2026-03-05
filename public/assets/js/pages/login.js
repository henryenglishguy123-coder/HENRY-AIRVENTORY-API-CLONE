$(function () {
    const $form = $('#login-form');
    const $loginBtn = $('#login-btn');
    const $alert = $('#login-alert');
    function setButtonLoading(isLoading) {
        const defaultText = $loginBtn.data('default-text');
        const loadingText = $loginBtn.data('loading-text');
        if (isLoading) {
            $loginBtn.prop('disabled', true).text(loadingText);
        } else {
            $loginBtn.prop('disabled', false).text(defaultText);
        }
    }
    function clearFieldErrors() {
        $('#username-error').addClass('d-none').text('');
        $('#password-error').addClass('d-none').text('');
    }
    function showAlert(type, message) {
        $alert.removeClass('d-none alert-danger alert-success alert-warning').addClass('alert-' + type).text(message);
    }
    $form.on('submit', function (event) {
        event.preventDefault();
        clearFieldErrors();
        showAlert('warning', messages.pleaseWaitLogin);
        setButtonLoading(true);
        $.ajax({
            url: $form.attr('action'),
            type: 'POST',
            data: $form.serialize(),
            dataType: 'json',
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            },
            success: function (response) {
                console.log(response);
                $.ajax({
                    url: adminMintTokenUrl,
                    type: 'POST',
                    dataType: 'json',
                    headers: {
                        'X-CSRF-TOKEN': response.csrf_token,
                        'Accept': 'application/json'
                    },
                    success: function (res) {
                        // Set JWT token cookie with 12-hour expiration (43200 seconds)
                        const cookieMaxAge = 12 * 60 * 60; // 12 hours
                        document.cookie =
                            "jwt_token=" + res.token +
                            "; path=/" +
                            "; max-age=" + cookieMaxAge +
                            "; SameSite=Lax" +
                            (location.protocol === 'https:' ? "; Secure" : "");
                    },
                    error: function (err) {
                        // Handle error if needed
                    }
                });
                const redirectUrl = response.redirect_url ?? dashboardUrl;
                window.location.href = redirectUrl;
            },
            error: function (xhr) {
                setButtonLoading(false);
                let message = messages.somethingWentWrong;
                if (xhr.status === 422 && xhr.responseJSON && xhr.responseJSON.errors) {
                    const errors = xhr.responseJSON.errors;
                    if (errors.username) {
                        $('#username-error').removeClass('d-none').text(errors.username[0]);
                    }
                    if (errors.password) {
                        $('#password-error').removeClass('d-none').text(errors.password[0]);
                    }
                    message = messages.fixErrors;
                } else if (xhr.status === 401) {
                    message = xhr.responseJSON?.message || messages.invalidCredentials;
                } else if (xhr.responseJSON?.message) {
                    message = xhr.responseJSON.message;
                }
                showAlert('danger', message);
            }
        });
    });
});
