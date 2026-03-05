$(function () {

    /** ------------------------------------------------
     *  GLOBAL CONFIGS
     * ------------------------------------------------*/
    const select2Config = {
        placeholder: "Select",
        allowClear: true,
        language: "en"
    };

    const $defaultCountry = $('#default_country_id');
    const $allowedCountry = $('#allowed_country_id');
    const $settingsForm = $('#settings-form');

    /** ------------------------------------------------
     *  IMAGE UPLOAD PREVIEW
     * ------------------------------------------------*/
    $('#icon-preview, #favicon-preview').on('click', function () {
        $(this).siblings('input[type="file"]').trigger('click');
    });

    $('#icon, #favicon').on('change', function () {
        const file = this.files?.[0];
        if (!file) return;
        const reader = new FileReader();
        reader.onload = e => $(this).siblings('img').attr('src', e.target.result);
        reader.readAsDataURL(file);
    });

    /** ------------------------------------------------
     *  INIT SELECT2 (EMPTY FIRST)
     * ------------------------------------------------*/
    $defaultCountry.select2(select2Config);
    $allowedCountry.select2(select2Config);

    /** ------------------------------------------------
     *  LOAD COUNTRIES VIA API
     * ------------------------------------------------*/
    async function loadCountries() {
        try {
            const response = await $.get(getCountriesUrl);
            if (!response.success || !Array.isArray(response.data)) {
                console.error("Invalid response from countries API");
                return;
            }
            const options = response.data.map(country => ({
                id: country.id,
                text: `${country.name} (${country.iso2})`,
                is_default: Number(country.is_default),
                is_allowed: Number(country.is_allowed)
            }));
            $defaultCountry.empty().select2({ ...select2Config, data: options });
            $allowedCountry.empty().select2({ ...select2Config, data: options });
            const defaultCountry = options.find(c => c.is_default === 1);
            if (defaultCountry) {
                $defaultCountry.val(defaultCountry.id).trigger('change');
            }
            const allowedCountries = options.filter(c => c.is_allowed === 1).map(c => c.id);
            if (allowedCountries.length) {
                $allowedCountry.val(allowedCountries).trigger('change');
            }
        } catch (error) {
            console.error("Failed to load countries:", error);
        }
    }
    loadCountries();

    /** ------------------------------------------------
     *  FORM SUBMIT (AJAX + LARAVEL VALIDATION)
     * ------------------------------------------------*/
    $settingsForm.on('submit', function (event) {
        event.preventDefault();
        const formData = new FormData(this);
        const submitBtn = $(this).find('button[type="submit"]');
        submitBtn.prop('disabled', true).text(ajaxErrorTranslations.processing);
        $('.error-text').text('');
        $.ajax({
            url: uodateWebSettingsUrl,
            type: 'POST',
            data: formData,
            contentType: false,
            processData: false,
            success: function (response) {
                toastr.success(response.message);
                setTimeout(() => {
                    window.location.reload();
                }, 800);
            },
            error: function (xhr) {
                if (xhr.status === 422) {
                    const errors = xhr.responseJSON.errors;
                    $.each(errors, function (key, value) {
                        $(`#${key}Error`).text(value[0]);
                    });
                    toastr.error(xhr.responseJSON.message);
                } else {
                    toastr.error(ajaxErrorTranslations.server_error_text);
                }
            },
            complete: function () {
                submitBtn.prop('disabled', false).text(ajaxErrorTranslations.save_changes);
            }
        });
    });
    /** ------------------------------------------------
 *  LIVE ERROR CLEARING
 * ------------------------------------------------*/
    $(document).on('input change', 'input, select, textarea', function () {
        const field = $(this).attr('name');
        if (field) {
            $(`#${field}Error`).text('');
        }
    });

});