$(document).ready(function () {
    const token = getCookie('jwt_token');
    const selectedIds = new Set();

    const customerTable = $('#customerTable').DataTable({
        responsive: true,
        autoWidth: false,
        processing: true,
        serverSide: true,
        searching: false,
        ajax: {
            url: window.customer_data_url,
            type: 'GET',
            headers: (() => {
                const headers = { 'Accept': 'application/json' };
                if (token) {
                    headers['Authorization'] = `Bearer ${token}`;
                }
                return headers;
            })(),
            data: function (d) {
                return {
                    limit : d.length,
                    searchInput: $('#searchInput').val(),
                    account_status: $('#accountStatusFilter').val(),
                    verified: $('#emailVerificationFilter').val(),
                    ...d
                };
            },
            dataSrc: function (json) {
                if (!json.success) {
                    toastr.error('Failed to load customers');
                    return [];
                }
                return json.data;
            },
            error: function () {
                toastr.error('Failed to load customers');
            }
        },
        order : [[1, 'desc']],
        columns: [
            {
                data: 'id',
                orderable: false,
                render: id =>
                    `<input type="checkbox" class="row-checkbox form-check-input" value="${id}">`
            },
            { data: 'id' },
            {
                data: null,
                render: row => `${row.first_name} ${row.last_name ?? ''}`
            },
            { data: 'email' },
            {
                data: 'account_status',
                render: status => {
                    const map = {
                        0: ['Disabled', 'danger'],
                        1: ['Enabled', 'success'],
                        2: ['Blocked', 'warning'],
                        3: ['Suspended', 'secondary'],
                    };
                    const item = map[status];
                    return item
                        ? `<span class="badge bg-${item[1]}">${item[0]}</span>`
                        : '-';
                }
            },
            {
                data: 'email_verified_at',
                render: val =>
                    val
                        ? `<span class="badge bg-success">Verified</span>`
                        : `<span class="badge bg-warning">Not Verified</span>`
            },
            {
                data: 'created_at',
                render: val => val ? dayjs(val).format('YYYY-MM-DD HH:mm') : '-'
            },
            {
                data: 'last_login',
                render: val => val ? dayjs(val).format('YYYY-MM-DD HH:mm') : 'Never'
            },
            {
                data: 'id',
                orderable: false,
                render: id =>
                    `<a href="${window.customer_show_url.replace(':id', id)}" class="btn btn-sm btn-primary">
                        <i class="mdi mdi-pencil"></i>
                    </a>`
            }
        ],
    });

    // Re-apply checkbox checked state when table is redrawn
    customerTable.on('draw', function () {
        $('.row-checkbox').each(function () {
            const id = $(this).val();
            $(this).prop('checked', selectedIds.has(id));
        });

        // Update the main checkbox status
        const total = $('.row-checkbox').length;
        const checked = $('.row-checkbox:checked').length;
        $('#mainCheckbox').prop('checked', total > 0 && total === checked);
    });

    // Toggle selection when a row checkbox changes
    $('body').on('change', '.row-checkbox', function () {
        const id = $(this).val();
        if ($(this).is(':checked')) {
            selectedIds.add(id);
        } else {
            selectedIds.delete(id);
        }
    });

    // Main checkbox toggles all checkboxes on current page and stores selections
    $('#mainCheckbox').on('click', function () {
        const checked = $(this).is(':checked');
        $('.row-checkbox').each(function () {
            const id = $(this).val();
            $(this).prop('checked', checked);
            if (checked) selectedIds.add(id);
            else selectedIds.delete(id);
        });
    });

    // Filter controls
    $('#filterBtn').on('click', function () {
        customerTable.ajax.reload(null, false);
    });

    $('#searchInput').on('blur', function () {
        customerTable.ajax.reload(null, false);
    });

    $('#resetBtn').on('click', function () {
        $('#searchInput').val('');
        $('#accountStatusFilter').val('');
        $('#emailVerificationFilter').val('');
        customerTable.ajax.reload(null, false);
    });

    // Bulk action
    $('#apply-bulk-action').on('click', function () {
        const action = $('#bulk-action').val();
        const ids = Array.from(selectedIds);

        if (!ids.length) {
            toastr.warning(window.translations.select_at_least_one_customer);
            return;
        }

        let confirmation_title, confirmation_text;

        switch (action) {
            case 'delete':
                confirmation_title = window.translations.are_you_sure;
                confirmation_text = window.translations.cannot_revert;
                break;
            case 'enable':
                confirmation_title = window.translations.are_you_sure;
                confirmation_text = window.translations.enable_selected;
                break;
            case 'disable':
                confirmation_title = window.translations.are_you_sure;
                confirmation_text = window.translations.disable_selected;
                break;
            case 'blocked':
                confirmation_title = window.translations.are_you_sure;
                confirmation_text = window.translations.block_selected;
                break;
            case 'suspended':
                confirmation_title = window.translations.are_you_sure;
                confirmation_text = window.translations.suspend_selected;
                break;
            default:
                toastr.warning(window.translations.select_valid_action);
                return;
        }

        Swal.fire({
            title: confirmation_title,
            text: confirmation_text,
            icon: "warning",
            showCancelButton: true,
            confirmButtonText: window.translations.proceed,
            cancelButtonText: window.translations.cancel,
            reverseButtons: true
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: window.customer_bulk_action_url,
                    type: 'POST',
                    data: {
                        _token: window.csrf_token || $('meta[name="csrf-token"]').attr('content'),
                        ids: ids,
                        action: action
                    },
                    success: function (response) {
                        toastr.success(response.message || window.translations.action_completed);
                        selectedIds.clear();
                        customerTable.ajax.reload(null, false);
                    },
                    error: function (xhr) {
                        let message = window.translations.request_error;
                        if (xhr.status === 422) {
                            const errors = xhr.responseJSON?.errors;
                            message = errors ? Object.values(errors).flat().join('<br>') : (xhr.responseJSON?.message || message);
                        } else {
                            message = xhr.responseJSON.message;
                        }
                        toastr.error(message);
                    }
                });
            }
        });
    });
});