$(document).ready(function () {
    initializeDataTable();

    $('#apply-bulk-action').on('click', applyBulkAction);
    $('#mainCheckbox').on('click', toggleAllCheckboxes);

    // Handle delete via SweetAlert
    $(document).on('click', '.js-delete-technique', function () {
        const url = $(this).data('url');
        handleDelete(url);
    });

    // Handle status toggle switch
    $(document).on('change', '.js-toggle-status', function () {
        const id = $(this).data('id');
        const url = `/api/v1/admin/production-techniques/toggle-status/${id}`; // Fixed url
        handleToggleStatus(url, this);
    });

    // Handle restore via SweetAlert
    $(document).on('click', '.js-restore-technique', function () {
        const url = $(this).data('url');
        handleRestore(url);
    });
});

/* ---------------- DataTable ---------------- */

function initializeDataTable() {
    if ($.fn.DataTable.isDataTable('#productionTechniqueTable')) {
        return $('#productionTechniqueTable').DataTable();
    }
    $('#productionTechniqueTable').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: window.productionTechniqueIndexConfig.dataUrl,
            type: "POST",
            headers: { 'Authorization': 'Bearer ' + getCookie('jwt_token') },
            data: {
                _token: window.productionTechniqueIndexConfig.csrfToken
            }
        },
        columns: [
            {
                data: 'select_id',
                orderable: false,
                searchable: false,
                className: 'text-center',
                render: data => {
                    const escapedData = $('<div>').text(data).html();
                    return `<input type="checkbox" class="row-checkbox form-check-input" value="${escapedData}">`;
                }
            },
            { data: 'name', name: 'name' },
            { data: 'created_at', name: 'created_at' },
            {
                data: 'status',
                name: 'status',
                orderable: false,
                searchable: false
            },
            {
                data: 'actions',
                name: 'actions',
                orderable: false,
                searchable: false
            }
        ],
        order: [[1, 'ASC']],
        columnDefs: [{ targets: [0], width: '5%' }],
        lengthMenu: [10, 25, 50, 100],
        responsive: true
    });
}

/* ---------------- Checkboxes ---------------- */

function toggleAllCheckboxes() {
    const checked = $(this).is(':checked');
    $('.row-checkbox').prop('checked', checked);
}

/* ---------------- Bulk Actions ---------------- */

function applyBulkAction() {
    const action = $('#bulk-action').val();
    const selectedIds = $('.row-checkbox:checked')
        .map((_, el) => el.value)
        .get();

    if (!selectedIds.length) {
        toastr.warning(window.productionTechniqueIndexConfig.messages.selectOne);
        return;
    }

    let confirmationText = '';

    switch (action) {
        case 'enable':
            confirmationText = window.productionTechniqueIndexConfig.messages.confirmEnable;
            break;
        case 'disable':
            confirmationText = window.productionTechniqueIndexConfig.messages.confirmDisable;
            break;
        case 'delete':
            confirmationText = window.productionTechniqueIndexConfig.messages.confirmDelete;
            break;
        default:
            toastr.warning(window.productionTechniqueIndexConfig.messages.invalidAction);
            return;
    }

    Swal.fire({
        title: window.productionTechniqueIndexConfig.messages.confirmTitle,
        text: confirmationText,
        icon: "warning",
        showCancelButton: true,
        confirmButtonText: window.productionTechniqueIndexConfig.messages.proceed,
        cancelButtonText: window.productionTechniqueIndexConfig.messages.cancel,
        reverseButtons: true
    }).then((result) => {
        if (result.isConfirmed) {
            sendBulkActionRequest(action, selectedIds);
        }
    });
}

/* ---------------- AJAX ---------------- */

function sendBulkActionRequest(action, ids) {
    $.ajax({
        url: window.productionTechniqueIndexConfig.bulkActionUrl,
        type: 'POST',
        headers: { 'Authorization': 'Bearer ' + getCookie('jwt_token') },
        data: {
            _token: window.productionTechniqueIndexConfig.csrfToken,
            action: action,
            ids: ids
        },
        success: function (response) {
            handleAjaxResponse(response);
            $('#mainCheckbox').prop('checked', false);
        },
        error: function () {
            toastr.error(window.productionTechniqueIndexConfig.messages.error);
        }
    });
}

function handleDelete(url) {
    Swal.fire({
        title: window.productionTechniqueIndexConfig.messages.confirmTitle,
        text: window.productionTechniqueIndexConfig.messages.confirmDelete,
        icon: "warning",
        showCancelButton: true,
        confirmButtonText: window.productionTechniqueIndexConfig.messages.proceed,
        cancelButtonText: window.productionTechniqueIndexConfig.messages.cancel,
        reverseButtons: true
    }).then((result) => {
        if (result.isConfirmed) {
            $.ajax({
                url: url,
                type: 'DELETE',
                headers: { 'Authorization': 'Bearer ' + getCookie('jwt_token') },
                data: {
                    _token: window.productionTechniqueIndexConfig.csrfToken
                },
                success: function (response) {
                    handleAjaxResponse(response);
                },
                error: function () {
                    toastr.error(window.productionTechniqueIndexConfig.messages.error);
                }
            });
        }
    });
}

function handleToggleStatus(url, checkbox) {
    const isChecked = $(checkbox).is(':checked');
    $.ajax({
        url: url,
        type: 'POST',
        headers: { 'Authorization': 'Bearer ' + getCookie('jwt_token') },
        data: {
            _token: window.productionTechniqueIndexConfig.csrfToken,
            status: isChecked ? 1 : 0
        },
        success: function (response) {
            if (response.success) {
                toastr.success(response.message);
            } else {
                $(checkbox).prop('checked', !isChecked); // Revert UI
                toastr.error(response.message || window.productionTechniqueIndexConfig.messages.error);
            }
        },
        error: function () {
            $(checkbox).prop('checked', !isChecked); // Revert UI
            toastr.error(window.productionTechniqueIndexConfig.messages.error);
        }
    });
}

function handleAjaxResponse(response) {
    if (response.success) {
        $('#productionTechniqueTable').DataTable().ajax.reload(null, false);
        toastr.success(response.message || window.productionTechniqueIndexConfig.messages.success);
    } else {
        toastr.error(response.message || window.productionTechniqueIndexConfig.messages.error);
    }
}

function handleRestore(url) {
    Swal.fire({
        title: window.productionTechniqueIndexConfig.messages.confirmTitle,
        text: window.productionTechniqueIndexConfig.messages.confirmRestore || "This will restore the selected technique.",
        icon: "info",
        showCancelButton: true,
        confirmButtonText: window.productionTechniqueIndexConfig.messages.proceed,
        cancelButtonText: window.productionTechniqueIndexConfig.messages.cancel,
        reverseButtons: true
    }).then((result) => {
        if (result.isConfirmed) {
            $.ajax({
                url: url,
                type: 'POST',
                headers: { 'Authorization': 'Bearer ' + getCookie('jwt_token') },
                data: {
                    _token: window.productionTechniqueIndexConfig.csrfToken
                },
                success: function (response) {
                    handleAjaxResponse(response);
                },
                error: function () {
                    toastr.error(window.productionTechniqueIndexConfig.messages.error);
                }
            });
        }
    });
}
