$(function () {
    const escapeHtml = (text) => {
        return text
            ? String(text).replace(/[&<>"']/g, function(m) {
                return {
                    '&': '&amp;',
                    '<': '&lt;',
                    '>': '&gt;',
                    '"': '&quot;',
                    "'": '&#039;'
                }[m];
            })
            : '';
    };

    const $table = $('#discountCouponsTable');
    const routeData = $table.data('route-data');
    const routeBulkAction = $table.data('route-bulk-action');
    const csrfToken = $('meta[name="csrf-token"]').attr('content');
    
    // Translations
    const t = {
        searchPlaceholder: $table.data('lang-search'),
        zeroRecords: $table.data('lang-zero'),
        selectOne: $table.data('lang-select-one'),
        selectAction: $table.data('lang-select-action'),
        confirmDelete: $table.data('lang-confirm-delete'),
        confirmDisable: $table.data('lang-confirm-disable'),
        confirmEnable: $table.data('lang-confirm-enable'),
        areYouSure: $table.data('lang-are-you-sure'),
        yesProceed: $table.data('lang-yes-proceed'),
        cancel: $table.data('lang-cancel'),
        errorGeneric: $table.data('lang-error-generic'),
        copied: $table.data('lang-copied'),
        copyCode: $table.data('lang-copy-code'),
        copyFailed: $table.data('lang-copy-failed')
    };

    const table = $table.DataTable({
        processing: true,
        serverSide: true,
        ajax: routeData,
        columns: [
            { data: 'checkbox', name: 'checkbox', orderable: false, searchable: false },
            { data: 'id', name: 'id' },
            { 
                data: 'title_code', 
                name: 'title_code',
                render: function (data, type, row) {
                    const title = row.title ? row.title : '';
                    const code = row.code ? row.code : '';
                    // Note: Str::limit is PHP, so we'll handle truncation in JS or assume backend sends full string.
                    // Ideally backend sends truncated version or we truncate here.
                    const truncatedTitle = title.length > 30 ? title.substring(0, 30) + '...' : title;
                    
                    return `
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <strong title="${escapeHtml(title)}">${escapeHtml(truncatedTitle)}</strong><br>
                                <small class="text-muted">${escapeHtml(code)}</small>
                            </div>
                            <button type="button" class="btn btn-sm btn-light border copy-code-btn" data-code="${escapeHtml(code)}" title="${escapeHtml(t.copyCode)}">
                                <i class="mdi mdi-content-copy"></i>
                            </button>
                        </div>`;
                }
            },
            { data: 'discount_type', name: 'discount_type' },
            { data: 'amount_value', name: 'amount_value' },
            { data: 'start_date', name: 'start_date' },
            { data: 'end_date', name: 'end_date' },
            { data: 'status', name: 'status', orderable: false, searchable: false },
            { data: 'actions', name: 'actions', orderable: false, searchable: false, className: 'text-center' },
        ],
        responsive: true,
        language: {
            searchPlaceholder: t.searchPlaceholder,
            zeroRecords: t.zeroRecords
        }
    });

    // Select all checkboxes
    $(document).on('click', '#select_all', function () {
        $('input[name="ids[]"]').prop('checked', this.checked);
    });

    $(document).on('click', 'input[name="ids[]"]', function () {
        if (!this.checked) $('#select_all').prop('checked', false);
    });

    // Bulk Action Handler
    $(document).on('click', '#apply-bulk-action', function () {
        const action = $('#bulk-action').val();
        const ids = $('input[name="ids[]"]:checked').map(function () {
            return $(this).val();
        }).get();

        if (ids.length === 0) {
            toastr.error(t.selectOne);
            return;
        }

        if (!action) {
            toastr.error(t.selectAction);
            return;
        }

        let confirmText = '';
        let confirmColor = '#3085d6';

        if (action === 'delete') {
            confirmText = t.confirmDelete;
            confirmColor = '#d33';
        } else if (action === 'disable') {
            confirmText = t.confirmDisable;
        } else if (action === 'enable') {
            confirmText = t.confirmEnable;
        }

        Swal.fire({
            title: t.areYouSure,
            text: confirmText,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: confirmColor,
            cancelButtonColor: '#6c757d',
            confirmButtonText: t.yesProceed,
            cancelButtonText: t.cancel
        }).then((result) => {
            if (result.isConfirmed) performBulkAction(action, ids);
        });
    });

    // Perform Bulk Action
    function performBulkAction(action, ids) {
        $.ajax({
            url: routeBulkAction,
            type: "POST",
            data: {
                ids: ids,
                action: action,
                _token: csrfToken
            },
            success: function (response) {
                toastr.success(response.message);
                $('#select_all').prop('checked', false);
                table.ajax.reload(null, false);
            },
            error: function (xhr) {
                toastr.error(xhr.responseJSON?.message || t.errorGeneric);
            },
        });
    }

    // Copy Coupon Code
    $(document).on('click', '.copy-code-btn', function () {
        const code = $(this).data('code');
        navigator.clipboard.writeText(code).then(() => {
            const btn = $(this);
            const icon = btn.find('i');
            icon.removeClass('mdi-content-copy').addClass('mdi-check text-success');
            btn.attr('title', t.copied);
            setTimeout(() => {
                icon.removeClass('mdi-check text-success').addClass('mdi-content-copy');
                btn.attr('title', t.copyCode);
            }, 1500);
        }).catch(() => {
            toastr.error(t.copyFailed);
        });
    });
});
