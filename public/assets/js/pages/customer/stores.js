document.addEventListener('DOMContentLoaded', () => {
    const CONFIG = window.customerStore;
    if (!CONFIG) {
        console.error('customerStore config missing');
        return;
    }
    const tableEl = $('#storesTable');
    if (!tableEl.length) return;
    const authToken = getCookie('jwt_token');
    if (!authToken) {
        toastr.error('Authentication token missing');
        return;
    }
    const table = tableEl.DataTable({
        responsive: true,
        autoWidth: false,
        processing: true,
        serverSide: true,
        searching: false,
        ordering: false,
        lengthChange: true,
        pageLength: 10,
        language: {
            processing: 'Loading stores...',
            emptyTable: 'No stores connected yet',
        },
        ajax: {
            url: CONFIG.customerStoreApiUrl,
            type: 'GET',
            headers: {
                Authorization: `Bearer ${authToken}`,
                Accept: 'application/json',
            },
            data: function (d) {
                const page = Math.floor(d.start / d.length) + 1;
                return {
                    customer_id: CONFIG.customer_id,
                    page: page,
                    limit: d.length,
                };
            },
            dataSrc: function (json) {
                if (!json || !json.success) {
                    toastr.error('Failed to load stores');
                    return [];
                }
                json.recordsTotal = json.meta.total;
                json.recordsFiltered = json.meta.total;
                return json.data;
            },
            error: function () {
                toastr.error('Failed to load stores');
            },
        },
        columns: [
            {
                data: 'domain',
                render: (domain, _, row) => `
                    <div class="d-flex align-items-center gap-2">
                        <img src="${row.channel_logo}" width="28" height="28">
                        <div>
                            <div class="fw-semibold">${domain}</div>
                            <small class="text-muted">${row.store_identifier}</small>
                        </div>
                    </div>
                `,
            },
            {
                data: 'channel',
                render: channel => `
                    <span class="badge bg-light text-dark border text-capitalize">
                        ${channel}
                    </span>
                `,
            },
            {
                data: 'status',
                render: (status, _, row) => {
                    const map = {
                        connected: { cls: 'success', icon: 'mdi-check-circle' },
                        disconnected: { cls: 'secondary', icon: 'mdi-minus-circle' },
                        error: { cls: 'danger', icon: 'mdi-alert-circle' },
                    };
                    const s = map[status] ?? map.disconnected;
                    return `
                        <span class="badge bg-${s.cls}">
                            <i class="mdi ${s.icon} me-1"></i>
                            ${row.status_label}
                        </span>
                    `;
                },
            },
            {
                data: 'last_synced_at',
                render: date =>
                    date
                        ? dayjs(date).format('YYYY-MM-DD HH:mm')
                        : `<span class="text-muted">Never</span>`,
            },
            {
                data: 'error_message',
                render: error =>
                    error
                        ? `
                            <span class="text-danger"
                                  data-bs-toggle="tooltip"
                                  title="${error}">
                                <i class="mdi mdi-alert-outline"></i>
                                View
                            </span>
                        `
                        : `<span class="text-muted">—</span>`,
            },
            {
                data: 'connected_at',
                render: date =>
                    date
                        ? dayjs(date).format('YYYY-MM-DD HH:mm')
                        : '—',
            },
            {
                data: null,
                orderable: false,
                render: (_, __, row) => `
                    <div class="d-flex gap-2">
                        <a href="${row.link}"
                           target="_blank"
                           class="btn btn-sm btn-outline-secondary">
                            <i class="mdi mdi-open-in-new"></i>
                        </a>
                    </div>`,
            },
        ],

        drawCallback: function () {
            $('[data-bs-toggle="tooltip"]').tooltip();
        },
    });
});
