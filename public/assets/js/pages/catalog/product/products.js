class ProductTableManager {
    constructor(config) {
        // Selectors
        this.tableSelector = '#products-table';
        this.mainCheckbox = '#mainCheckbox';
        this.bulkSelect = '#bulk-action';
        this.applyBtn = '#apply-bulk-action';

        this.filterSearch = '#filter-search';
        this.filterStatus = '#filter-status';
        this.filterTemplate = '#filter-template';
        this.applyFilterBtn = '#apply-filter';
        this.resetFilterBtn = '#reset-filter';

        // Config
        this.dataUrl = config.dataUrl;
        this.bulkActionUrl = config.bulkActionUrl;
        this.templateassignUrl = config.templateassignUrl;
        this.editUrl = config.editUrl || '/admin/catalog/product/:id/edit';
        this.csrfToken = config.csrfToken;

        this.table = null;

        this.init();
    }

    /* ===============================
     * INIT
     * =============================== */
    init() {
        this.initDataTable();
        this.bindEvents();
        this.toggleApplyButton();
    }
    /* ===============================
     * DATATABLE
     * =============================== */
    initDataTable() {
        this.table = $(this.tableSelector).DataTable({
            responsive: true,
            autoWidth: false,
            processing: true,
            serverSide: false,
            searching: false,
            ajax: {
                url: this.dataUrl,
                type: 'GET',
                headers: (() => {
                    const token = getCookie('jwt_token');
                    const headers = { 'Accept': 'application/json' };
                    if (token) {
                        headers['Authorization'] = `Bearer ${token}`;
                    }
                    return headers;
                })(),
                data: (d) => {
                    d.limit = d.length;
                    d.search = $(this.filterSearch).val();
                    d.status = $(this.filterStatus).val();
                    d.template = $(this.filterTemplate).val();
                },
                dataSrc: (response) => {
                    if (!response || !response.data || !Array.isArray(response.data.items)) {
                        toastr.error('Failed to load products.  Invalid data format.');
                        return [];
                    }
                    return response.data.items;
                }

            },
            columns: this.getColumns(),
            order: [[1, 'desc']],
            drawCallback: () => {
                this.syncHeaderCheckbox();
                this.toggleApplyButton();
            }
        });
    }

    getColumns() {
        const escapeHtml = (str = '') =>
            String(str)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');

        const renderCheckbox = (id) => `
        <input
            type="checkbox"
            class="form-check-input row-checkbox"
            value="${id}"
            aria-label="Select row ${id}"
        />
    `;

        const renderProductInfo = ({ image, name, sku }) => `
        <div class="d-flex align-items-center gap-3">
            <img
                src="${image || '/images/placeholder.png'}"
                alt="${escapeHtml(name)}"
                width="48"
                height="48"
                class="rounded object-fit-cover"
                loading="lazy"
            />

            <div class="lh-sm">
                <div class="fw-semibold text-dark">
                    ${escapeHtml(name)}
                </div>

                <div class="text-muted small">
                    SKU: ${escapeHtml(sku)}
                </div>
            </div>
        </div>
    `;

        const renderStatus = (status) => {
            const enabled = Number(status) === 1;

            return `
            <span class="badge bg-${enabled ? 'success' : 'secondary'}">
                ${enabled ? 'Enabled' : 'Disabled'}
            </span>
        `;
        };

        const renderTemplateStatus = (row) => {
            const template = row.template || {};
            const isValid = template.is_valid === true;
            const url = this.templateassignUrl.replace(':id', row.id);
            const templateName = template.name || 'Assign Template';
            const templateReason = template.reason || 'Action Required';
            const btnClass = isValid ? 'btn-outline-secondary' : 'btn-outline-danger';
            const iconClass = isValid
                ? 'mdi-check-circle text-success'
                : 'mdi-alert-circle text-danger';
            return `<div class="d-flex flex-column">
            <a href="${url}" class="btn btn-sm ${btnClass} d-flex align-items-center justify-content-between gap-3" style="border-style: dashed; padding: 10px 14px;">
                <div class="d-flex align-items-center gap-2 text-start">
                    <i class="mdi ${iconClass}" style="font-size: 1.25rem;"></i>
                    <div class="lh-sm">
                        <div class="fw-semibold small">
                            ${templateName}
                        </div>
                        <div class="text-muted" style="font-size: 11px;">
                            ${templateReason}
                        </div>
                    </div>
                </div>
                <i class="mdi mdi-chevron-right text-muted"></i>
            </a>
        </div>
    `;
        };


        const renderActions = (row) => {
            const editUrl = this.editUrl.replace(':id', row.id);

            return `
            <div class="d-flex gap-2">
                <a
                    href="${editUrl}"
                    class="btn btn-sm btn-primary"
                >
                    Edit
                </a>
            </div>
        `;
        };

        return [
            {
                data: 'id',
                orderable: false,
                render: renderCheckbox
            },
            {
                data: 'id'
            },
            {
                data: null,
                orderable: false,
                render: renderProductInfo
            },
            {
                data: 'status',
                render: renderStatus
            },
            {
                data: 'from_price'
            },
            {
                data: null,
                orderable: false,
                render: renderTemplateStatus
            },
            {
                data: null,
                orderable: false,
                render: renderActions
            }
        ];
    }



    /* ===============================
     * EVENTS
     * =============================== */
    bindEvents() {
        // Header checkbox (select all)
        $(document).on('change', this.mainCheckbox, () => {
            $('.row-checkbox').prop(
                'checked',
                $(this.mainCheckbox).prop('checked')
            );
            this.toggleApplyButton();
        });

        // Row checkbox
        $(document).on('change', '.row-checkbox', () => {
            this.syncHeaderCheckbox();
            this.toggleApplyButton();
        });

        // Apply bulk action
        $(document).on('click', this.applyBtn, () => {
            this.handleBulkAction();
        });

        $(document).on('click', this.applyFilterBtn, () => {
            this.applyFilters();
        });

        // 🔥 RESET FILTER
        $(document).on('click', this.resetFilterBtn, () => {
            this.resetFilters();
        });
    }
    applyFilters() {
        this.table.ajax.reload(null, true); // true = reset to page 1
    }
    resetFilters() {
        $(this.filterSearch).val('');
        $(this.filterStatus).val('');
        $(this.filterTemplate).val('');

        this.table.ajax.reload(null, true);
    }



    /* ===============================
     * BULK ACTIONS
     * =============================== */
    handleBulkAction() {
        const action = $(this.bulkSelect).val();
        const ids = this.getSelectedIds();

        if (!action) {
            toastr.warning(this.trans('datatable.select_one'));
            return;
        }

        if (!ids.length) {
            toastr.warning(this.trans('datatable.select_one'));
            return;
        }

        this.confirmBulkAction(action, ids);
    }

    confirmBulkAction(action, ids) {
        Swal.fire({
            title: this.trans('bulk.confirm_title'),
            text: this.trans(`bulk.confirm_${action}`),
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: this.trans('bulk.confirm_btn'),
            cancelButtonText: this.trans('bulk.cancel_btn'),
        }).then(result => {
            if (!result.isConfirmed) return;
            this.performBulkAction(action, ids);
        });
    }

    performBulkAction(action, ids) {
        $.ajax({
            url: this.bulkActionUrl,
            type: 'POST',
            headers: {
                'X-CSRF-TOKEN': this.csrfToken
            },
            data: { action, ids },
            beforeSend: () => $(this.applyBtn).prop('disabled', true),
            success: res => {
                if (res.success) {
                    toastr.success(res.message || this.trans('bulk.success'));
                    this.table.ajax.reload(null, false); // 🔥 pagination safe
                    this.resetBulkUI();
                } else {
                    toastr.error(res.message || this.trans('bulk.error'));
                }
            },
            error: () => {
                toastr.error(this.trans('bulk.error'));
            },
            complete: () => $(this.applyBtn).prop('disabled', false)
        });
    }

    /* ===============================
     * SINGLE DELETE
     * =============================== */
    confirmSingleDelete(url) {
        Swal.fire({
            title: this.trans('bulk.confirm_title'),
            text: this.trans('bulk.confirm_delete'),
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: this.trans('bulk.confirm_btn'),
            cancelButtonText: this.trans('bulk.cancel_btn'),
        }).then(result => {
            if (!result.isConfirmed) return;
            this.performSingleDelete(url);
        });
    }

    performSingleDelete(url) {
        $.ajax({
            url,
            type: 'DELETE',
            data: { _token: this.csrfToken },
            success: res => {
                if (res.success || res.status === 'success') {
                    toastr.success(res.message || this.trans('bulk.success'));
                    this.table.ajax.reload(null, false);
                } else {
                    toastr.error(res.message || this.trans('bulk.error'));
                }
            },
            error: () => {
                toastr.error(this.trans('bulk.error'));
            }
        });
    }

    /* ===============================
     * HELPERS
     * =============================== */
    getSelectedIds() {
        return $('.row-checkbox:checked')
            .map((_, el) => el.value)
            .get();
    }

    syncHeaderCheckbox() {
        const total = $('.row-checkbox').length;
        const checked = $('.row-checkbox:checked').length;

        $(this.mainCheckbox).prop(
            'checked',
            total > 0 && total === checked
        );
    }

    toggleApplyButton() {
        $(this.applyBtn).prop(
            'disabled',
            this.getSelectedIds().length === 0
        );
    }

    resetBulkUI() {
        $(this.mainCheckbox).prop('checked', false);
        $('.row-checkbox').prop('checked', false);
        $(this.bulkSelect).val('');
        this.toggleApplyButton();
    }

    trans(key) {
        if (key.includes('.')) {
            return window.trans?.[key.split('.')[0]]?.[key.split('.')[1]] ?? key;
        }
        return window.trans?.[key] ?? key;
    }
}
