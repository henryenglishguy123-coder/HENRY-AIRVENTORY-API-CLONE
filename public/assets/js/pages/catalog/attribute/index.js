$(document).ready(function () {
    initializeDataTable();

    $('#apply-bulk-action').on('click', applyBulkAction);
    $('#mainCheckbox').on('click', toggleAllCheckboxes);
});

/* ---------------- DataTable ---------------- */

function initializeDataTable() {
    if ($.fn.DataTable.isDataTable('#attributesTable')) {
        return $('#attributesTable').DataTable();
    }
    $('#attributesTable').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: window.attributesIndexConfig.dataUrl,
            type: "POST",
            data: {
                _token: window.attributesIndexConfig.csrfToken
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
            { data: 'attribute_code', name: 'attribute_code' },
            { data: 'field_type', name: 'field_type' },
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
        toastr.warning(window.attributesIndexConfig.messages.selectOne);
        return;
    }

    let confirmationText = '';

    switch (action) {
        case 'enable':
            confirmationText = window.attributesIndexConfig.messages.confirmEnable;
            break;
        case 'disable':
            confirmationText = window.attributesIndexConfig.messages.confirmDisable;
            break;
        case 'delete':
            confirmationText = window.attributesIndexConfig.messages.confirmDelete;
            break;
        default:
            toastr.warning(window.attributesIndexConfig.messages.invalidAction);
            return;
    }

    Swal.fire({
        title: window.attributesIndexConfig.messages.confirmTitle,
        text: confirmationText,
        icon: "warning",
        showCancelButton: true,
        confirmButtonText: window.attributesIndexConfig.messages.proceed,
        cancelButtonText: window.attributesIndexConfig.messages.cancel,
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
        url: window.attributesIndexConfig.bulkActionUrl,
        type: 'POST',
        data: {
            _token: window.attributesIndexConfig.csrfToken,
            action: action,
            ids: ids
        },
        success: function (response) {
            handleAjaxResponse(response);
        },
        error: function () {
            toastr.error(window.attributesIndexConfig.messages.error);
        }
    });
}

function handleAjaxResponse(response) {
    if (response.success) {
        $('#attributesTable').DataTable().ajax.reload(null, false);
        toastr.success(response.message || window.attributesIndexConfig.messages.success);
    } else {
        toastr.error(response.message || window.attributesIndexConfig.messages.error);
    }
}
