(function ($) {

    const cfg = window.categoryConfig;

    /* Init Select2 */
    $('#parent_id').select2();
    $('#industry_id').select2();

    /* ==================================
     * Load parent categories by Industry
     * ================================== */
    $('#industry_id').on('change', function () {
        let id = $(this).val();

        $('#parent_id').html(`<option value="">${cfg.labels.loading}</option>`);

        if (!id) {
            return $('#parent_id').html(`<option value="">${cfg.labels.none}</option>`);
        }

        $.ajax({
            url: cfg.routes.categoryByIndustry.replace(':id', id),
            type: 'GET',
            success: function (res) {
                $('#parent_id').empty().append(`<option value="">${cfg.labels.none}</option>`);

                if (res.categories?.length) {
                    $('#parent_id').append(buildCategoryTree(res.categories));
                }

                $('#parent_id').trigger('change.select2');
            },
            error: function () {
                toastr.error(cfg.labels.fetchError);
                $('#parent_id').html(`<option value="">${cfg.labels.none}</option>`);
            }
        });
    });

    function buildCategoryTree(cats, prefix = '') {
        return cats.map(c => {
            const label = prefix ? `${prefix} › ${c.meta.name}` : c.meta.name;
            let option = `<option value="${c.id}">${label}</option>`;
            if (c.children?.length) option += buildCategoryTree(c.children, label);
            return option;
        }).join('');
    }

    /* =======================
     * Slug Auto Generate
     * ======================= */
    $('#name').on('input', function () {
        $('#slug').val(generateSlug($(this).val()));
    });

    function generateSlug(text) {
        return text.toLowerCase()
            .replace(/[^\w\s-]/g, '')
            .trim()
            .replace(/\s+/g, '-')
            .replace(/--+/g, '-');
    }

    /* =======================
     * Image Preview
     * ======================= */
    $('#btnUploadImage, #previewImg').on('click', () => $('#image').click());

    $('#image').on('change', e => {
        const file = e.target.files[0];
        if (!file) return;

        if (file.size > 10 * 1024 * 1024) {
            return toastr.error("Max size 10MB");
        }

        const reader = new FileReader();
        reader.onload = e => $('#previewImg').attr('src', e.target.result);
        reader.readAsDataURL(file);
    });

})(jQuery);
