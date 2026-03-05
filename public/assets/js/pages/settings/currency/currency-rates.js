$(function () {
    const form = $('#settings-form');
    const saveBtn = $('#setting-form-btn');
    const updateBtn = $('#update-rates-btn');
    if (form.length && saveBtn.length) {
        form.on('submit', function () {
            const originalHtml = saveBtn.html();
            const loadingText = saveBtn.data('loading-text') || 'Saving...';
            saveBtn
                .data('original-html', originalHtml)
                .prop('disabled', true)
                .html('<span class="spinner-border spinner-border-sm me-1"></span>' + loadingText);
        });
    }
    if (updateBtn.length) {
        updateBtn.on('click', function (e) {
            e.preventDefault();
            const btn = $(this);
            const url = btn.data('url');
            const loadingText    = btn.data('loading-text') || 'Updating...';
            const urlErrorText   = btn.data('url-error-text') || 'Update URL is not configured.';
            const successText    = btn.data('success-text') || 'Rates updated from API successfully.';
            const errorText      = btn.data('error-text') || 'Failed to update rates from API.';
            if (!url) {
                console.error('Missing data-url attribute on #update-rates-btn');
                if (window.toastr) {
                    toastr.error(urlErrorText);
                } else {
                    alert(urlErrorText);
                }

                return;
            }
            const originalHtml = btn.html();
            btn.prop('disabled', true)
                .html('<span class="spinner-border spinner-border-sm me-1"></span>' + loadingText);

            $.ajax({
                url: url,
                type: 'post',
                dataType: 'json',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                success: function (response) {
                    if (response.rates) {
                        Object.keys(response.rates).forEach(function (currencyId) {
                            const input = $('#currency_rate_' + currencyId);
                            if (input.length) {
                                input.val(response.rates[currencyId]);
                            }
                        });
                    }
                    const msg = response.message || successText;
                    if (window.toastr) {
                        toastr.success(msg);
                    } else {
                        alert(msg);
                    }
                },
                error: function (xhr) {
                    let msg = errorText;
                    if (xhr.responseJSON && xhr.responseJSON.message) {
                        msg = xhr.responseJSON.message;
                    }
                    console.error('Currency rate update failed:', xhr);
                    if (window.toastr) {
                        toastr.error(msg);
                    } else {
                        alert(msg);
                    }
                },
                complete: function () {
                    btn.prop('disabled', false).html(originalHtml);
                }
            });
        });
    }
});
