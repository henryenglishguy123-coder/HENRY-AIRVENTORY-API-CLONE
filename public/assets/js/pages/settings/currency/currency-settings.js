/**
 * Currency Settings JS Handler
 */

$(function () {

    const form = $('#currency-settings-form');
    const btn = $('#currency-settings-btn');
    const fixerStatus = $('#fixer_io_api_status');
    const fixerKeyContainer = $('#fixer_io_api_key_container');

    /**
     * Initialize Select2
     */
    function initSelects() {
        $('#default_currency_id').select2({
            placeholder: defaultSelectLabel,
            allowClear: true,
            width: '100%'
        });

        $('#allowed_currency_ids').select2({
            placeholder: allowedSelectLabel,
            width: '100%'
        });
    }

    /**
     * Toggle Fixer API key input
     */
    function toggleApiKey() {
        fixerStatus.val() === '1'
            ? fixerKeyContainer.slideDown(150)
            : fixerKeyContainer.slideUp(150);
    }

    /**
     * Reset validation UI
     */
    function clearErrors() {
        $('.error').text('');
        form.find('.is-invalid').removeClass('is-invalid');
    }
    function attachListeners() {
        form.on('input change', 'input, select, textarea', function () {
            const id = $(this).attr('id');
            if (!id) return;
            $('#' + id + 'Error').text('');
            $(this).removeClass('is-invalid');
        });
        fixerStatus.on('change', toggleApiKey);
        form.on('submit', function (e) {
            e.preventDefault();
            clearErrors();
            const originalHtml = btn.html();
            btn.prop('disabled', true).html(`<span class="spinner-border spinner-border-sm me-2"></span>${savingLabel}`
            );
            $.ajax({
                url: form.attr('action'),
                type: form.find('input[name="_method"]').val() || form.attr('method'),
                data: form.serialize(),
                dataType: 'json',
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
                    'Accept': 'application/json'
                },
                success: function (response) {
                    if (window.toastr) {
                        toastr.success(response.message || successLabel);
                    } else {
                        alert(response.message || successLabel);
                    }
                },
                error: function (xhr) {
                    if (xhr.status === 422) {
                        const errors = xhr.responseJSON?.errors || {};
                        Object.keys(errors).forEach(function (field) {
                            const messages = errors[field];
                            const baseField = field.split('.')[0];
                            let $input = form.find(`[name="${baseField}"], [name="${baseField}[]"]`);
                            $input.addClass('is-invalid');
                            const $errorSpan = $('#' + baseField + 'Error');
                            if ($errorSpan.length) {
                                $errorSpan.text(messages[0]);
                            }
                        });
                    } else {
                        alert(errorLabel);
                        console.error(xhr);
                    }
                },
                complete: function () {
                    btn.prop('disabled', false).html(originalHtml);
                }
            });
        });
        btn.on('click', function (e) {
            e.preventDefault();
            form.submit();
        });
    }
    initSelects();
    toggleApiKey();
    attachListeners();
});
