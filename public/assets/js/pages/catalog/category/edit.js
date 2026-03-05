(function ($) {

    const cfg = window.editCfg;

    $('#parent_id').select2();

    /* LOAD PARENT CATEGORIES */
    function loadParentCategories() {
        let industryId = $('#industry_id').data('id');

        $('#parent_id').html(`<option value="">${cfg.labels.loading}</option>`);

        $.ajax({
            url: cfg.routes.industryCats.replace(':id', industryId),
            type: 'GET',
            data: { exclude: cfg.categoryId },
            success: function (res) {

                $('#parent_id')
                    .empty()
                    .append(`<option value="">${cfg.labels.none}</option>`);

                if (res.categories) {
                    $('#parent_id').append(buildTree(res.categories));
                }

                $('#parent_id').val(cfg.parentId).trigger('change.select2');
            },
            error: function () {
                toastr.error(cfg.labels.fetchError);
            }
        });
    }

    function buildTree(arr, prefix = '') {
        return arr.map(cat => {
            let name = prefix ? `${prefix} › ${cat.meta.name}` : cat.meta.name;
            let html = `<option value="${cat.id}">${name}</option>`;
            if (cat.children?.length) html += buildTree(cat.children, name);
            return html;
        }).join('');
    }

    loadParentCategories();

    /* IMAGE UPLOAD */
    $('#btnUpload, #previewImg').on('click', () => $('#image').click());

    $('#image').on('change', e => {
        const file = e.target.files[0];
        if (!file) return;
        const allowedTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/jpg'];
        if (!allowedTypes.includes(file.type)) {
            $('#image').val(''); // reset just to be clean
            return toastr.error("Only JPG, JPEG, PNG, WebP files are allowed");
        }

        if (file.size > 10 * 1024 * 1024) {
            return toastr.error("Max size 10MB");
        }

        const r = new FileReader();
        r.onload = e => $('#previewImg').attr('src', e.target.result);
        r.readAsDataURL(file);
    });

    /* VALIDATE + AJAX SUBMIT */
    $('#categoryForm').validate({
        rules: {
            name: { required: true },
        },
        messages: {
            name: { required: "Please enter category name" }
        },
        submitHandler: function (form, event) {
            event.preventDefault();

            let btn = $(form).find('button[type="submit"]');
            btn.prop('disabled', true).html(
                `<i class="spinner-border spinner-border-sm"></i> ${cfg.labels.processing}`
            );

            let formData = new FormData(form);
            formData.append('category_id', cfg.categoryId);

            $.ajax({
                url: cfg.routes.update,
                type: 'POST',
                data: formData,
                contentType: false,
                processData: false,
                success: res => {
                    if (res.success) {
                        toastr.success(res.message);
                        window.location.href = cfg.routes.redirect;
                    }
                },
                error: xhr => {
                    toastr.error("Validation or server error occurred");
                },
                complete: () => {
                    btn.prop('disabled', false).html(`Update Category`);
                }
            });

            return false;
        }
    });

})(jQuery);
