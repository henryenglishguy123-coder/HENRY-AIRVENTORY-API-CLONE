$(function () {

    const dataTable = initializeDataTable();

    $('#apply-bulk-action').on('click', applyBulkAction);
    $('#mainCheckbox').on('click', toggleAllCheckboxes);
});

function initializeDataTable() {
    return $('#categoriesTable').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: categoryRoutes.dataUrl,
            type: "POST",
            data: {
                _token: categoryRoutes.csrfToken
            }
        },
        columns: [
            {
                data: 'select_id',
                orderable: false,
                searchable: false,
                render: data => {
                    const input = document.createElement('input');
                    input.type = 'checkbox';
                    input.className = 'row-checkbox form-check-input';
                    input.value = data;
                    return input.outerHTML;
                }
            },
            {
                data: null,
                render: (_, __, ___, meta) => meta.row + 1
            },
            { data: "categoryName" },
            { data: "industry_name" },
            { data: "image" },
            { data: "status" },
            { data: "created_at" },
            { data: "action", orderable: false, searchable: false }
        ],
        columnDefs: [
            { targets: [0, 1, 3, 4, 5, 6, 7], className: 'text-center' }
        ],
        language: datatableLang,
        paging: true,
        ordering: true,
        searching: true,
        responsive: true,
        order: [[6, 'desc']],
        lengthMenu: [10, 25, 50, 100],
    });
}

function toggleAllCheckboxes() {
    $('.row-checkbox').prop('checked', $(this).is(':checked'));
}

function applyBulkAction() {
    const action = $('#bulk-action').val();
    const selectedIds = $('.row-checkbox:checked').map((_, el) => el.value).get();

    if (!selectedIds.length) {
        toastr.warning(translations.select_one);
        return;
    }

    let msg = {
        delete: translations.delete_confirm,
        enable: translations.enable_confirm,
        disable: translations.disable_confirm,
    };

    if (!msg[action]) {
        toastr.warning(translations.valid_action);
        return;
    }

    swal.fire({
        title: translations.sure,
        text: msg[action],
        icon: "warning",
        showCancelButton: true,
        confirmButtonText: translations.proceed,
        cancelButtonText: translations.cancel,
        reverseButtons: true
    }).then(res => {
        if (res.isConfirmed) {
            $.post(categoryRoutes.bulkActionUrl, {
                _token: categoryRoutes.csrfToken,
                ids: selectedIds,
                action
            })
                .done(response => handleAjaxResponse(response))
                .fail(() => toastr.error(translations.error));
        }
    });
}

function handleAjaxResponse(response) {
    if (response.success) {
        $('#categoriesTable').DataTable().ajax.reload(null, false);
        toastr.success(response.message || translations.success);
    } else {
        toastr.error(response.message || translations.error);
    }
}
