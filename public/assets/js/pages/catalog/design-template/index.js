$(function () {

    const CONFIG = window.DesignTemplateIndexConfig;
    const tableEl = $('#designTemplateTable');

    /* ===============================
     * DATATABLE INIT
     * =============================== */
    const table = tableEl.DataTable({
        processing: true,
        serverSide: true,
        responsive: true,
        ajax: {
            url: CONFIG.routes.data,
            type: 'POST',
            headers: {
                'X-CSRF-TOKEN': CONFIG.csrf
            }
        },
        order: [[1, 'desc']],
        columns: [
            {
                data: 'id',
                orderable: false,
                searchable: false,
                render: id => `
                    <input type="checkbox"
                           class="row-checkbox"
                           value="${id}">
                `
            },
            { data: 'name', name: 'name' },
            {
                data: 'template-layers',
                orderable: false,
                searchable: false
            },
            {
                data: 'actions',
                orderable: false,
                searchable: false
            }
        ]
    });

    /* ===============================
     * SELECT ALL
     * =============================== */
    $('#selectAll').on('change', function () {
        $('.row-checkbox').prop('checked', this.checked);
    });

    tableEl.on('draw.dt', () => {
        $('#selectAll').prop('checked', false);
    });

    /* ===============================
     * BULK ACTION
     * =============================== */
    $('#applyBulkAction').on('click', function () {

        const action = $('#bulkAction').val();
        const ids = $('.row-checkbox:checked')
            .map((_, el) => el.value)
            .get();

        if (!ids.length) {
            toastr.warning(CONFIG.messages.select_one);
            return;
        }

        if (action !== 'delete') {
            toastr.warning(CONFIG.messages.select_one);
            return;
        }

        Swal.fire({
            title: CONFIG.messages.confirm_delete,
            text: CONFIG.messages.confirm_delete_text,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Yes, delete'
        }).then(result => {
            if (!result.isConfirmed) return;

            $.ajax({
                url: CONFIG.routes.bulk,
                type: 'POST',
                data: {
                    _token: CONFIG.csrf,
                    action,
                    ids
                },
                success(res) {
                    if (res.success) {
                        toastr.success(res.message || CONFIG.messages.success);
                        table.ajax.reload(null, false);
                    } else {
                        toastr.error(res.message || CONFIG.messages.error);
                    }
                },
                error() {
                    toastr.error(CONFIG.messages.error);
                }
            });
        });
    });

    /* ===============================
     * SINGLE DELETE
     * =============================== */
    tableEl.on('click', '.delete-btn', function (e) {
        e.preventDefault();

        const form = $(this).closest('form');
        const url = form.attr('action');

        Swal.fire({
            title: CONFIG.messages.confirm_delete,
            text: CONFIG.messages.confirm_delete_text,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Delete'
        }).then(result => {
            if (!result.isConfirmed) return;

            $.ajax({
                url,
                type: 'DELETE',
                data: { _token: CONFIG.csrf },
                success(res) {
                    if (res.status === 'success') {
                        toastr.success(res.message);
                        table.ajax.reload(null, false);
                    } else {
                        toastr.error(res.message);
                    }
                },
                error() {
                    toastr.error(CONFIG.messages.error);
                }
            });
        });
    });

});
