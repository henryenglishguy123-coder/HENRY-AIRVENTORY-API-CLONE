document.addEventListener('DOMContentLoaded', () => {

    /* =======================
     | Boot
     ======================= */
    const Config = window.OrderListConfig;
    if (!Config) return console.error('OrderListConfig missing');

    const token = getCookie('jwt_token');

    /* =======================
     | DOM Elements
     ======================= */
    const tableEl = $('#ordersTable');
    const filterForm = document.getElementById('filterForm');
    const btnReset = document.getElementById('btnReset');
    const dateRangePicker = $('#dateRangePicker');
    const startDateInput = $('#startDate');
    const endDateInput = $('#endDate');

    // Error card
    const errorCard = document.getElementById('ordersErrorCard');
    const errorTitle = document.getElementById('ordersErrorTitle');
    const errorMessage = document.getElementById('ordersErrorMessage');
    const errorCode = document.getElementById('ordersErrorCode');
    const retryBtn = document.getElementById('ordersRetryBtn');
    const errorCloseBtn = document.getElementById('ordersErrorClose');

    /* =======================
     | Error Card Helpers
     ======================= */
    function showError(title, message, code) {
        if (!errorCard) return;
        if (errorTitle) errorTitle.textContent = title || 'Failed to load orders';
        if (errorMessage) errorMessage.textContent = message || 'An unexpected error occurred. Please try again.';

        if (errorCode) {
            if (code) {
                errorCode.textContent = `HTTP ${code}`;
                errorCode.classList.remove('d-none');
            } else {
                errorCode.textContent = '';
                errorCode.classList.add('d-none');
            }
        }

        errorCard.classList.remove('d-none');
    }

    function hideError() {
        if (errorCard) errorCard.classList.add('d-none');
    }

    /* =======================
     | Retry / Close buttons
     ======================= */
    if (retryBtn) {
        retryBtn.addEventListener('click', () => {
            hideError();
            if (table) table.draw();
        });
    }

    if (errorCloseBtn) {
        errorCloseBtn.addEventListener('click', hideError);
    }

    /* =======================
     | Date Range Picker
     ======================= */
    if (dateRangePicker.length) {
        dateRangePicker.daterangepicker({
            autoUpdateInput: false,
            timePicker: true,
            timePicker24Hour: true,
            timePickerSeconds: true,
            maxDate: moment().endOf('day'),
            ranges: {
                'Today': [moment().startOf('day'), moment().endOf('day')],
                'Yesterday': [moment().subtract(1, 'days').startOf('day'), moment().subtract(1, 'days').endOf('day')],
                'Last 7 Days': [moment().subtract(6, 'days').startOf('day'), moment().endOf('day')],
                'Last 30 Days': [moment().subtract(29, 'days').startOf('day'), moment().endOf('day')],
                'This Month': [moment().startOf('month'), moment().endOf('month')],
                'Last Month': [moment().subtract(1, 'month').startOf('month'), moment().subtract(1, 'month').endOf('month')]
            },
            locale: {
                cancelLabel: 'Clear',
                format: 'YYYY-MM-DD HH:mm:ss',
                applyLabel: 'Apply'
            },
            opens: 'left'
        });

        dateRangePicker.on('apply.daterangepicker', function (ev, picker) {
            const format = 'YYYY-MM-DD HH:mm:ss';
            $(this).val(picker.startDate.format(format) + ' - ' + picker.endDate.format(format));
            startDateInput.val(picker.startDate.format(format));
            endDateInput.val(picker.endDate.format(format));
        });

        dateRangePicker.on('cancel.daterangepicker', function () {
            $(this).val('');
            startDateInput.val('');
            endDateInput.val('');
        });
    }

    /* =======================
     | Datatable
     ======================= */
    let table = null;

    if (tableEl.length) {
        table = tableEl.DataTable({
            processing: true,
            serverSide: true,
            ajax: function (data, callback) {
                // Clear any previous error before fetching
                hideError();

                // Map DataTables parameters to API parameters
                const page = (data.start / data.length) + 1;
                const per_page = data.length;
                const search = data.search.value;

                // Sorting
                let sort_by = 'created_at';
                let sort_dir = 'desc';

                if (data.order && data.order.length > 0) {
                    const columnIndex = data.order[0].column;
                    const columnName = data.columns[columnIndex].name;
                    if (columnName) {
                        sort_by = columnName;
                        sort_dir = data.order[0].dir;
                    }
                }

                // Filters
                const formData = new FormData(filterForm);
                const params = new URLSearchParams({
                    page,
                    per_page,
                    sort_by,
                    sort_dir
                });

                if (search) params.append('search', search);
                if (formData.get('status')) params.append('status', formData.get('status'));
                if (formData.get('payment_status')) params.append('payment_status', formData.get('payment_status'));
                if (formData.get('start_date')) params.append('start_date', formData.get('start_date'));
                if (formData.get('end_date')) params.append('end_date', formData.get('end_date'));

                // Fetch
                fetch(`${Config.urls.list}?${params.toString()}`, {
                    method: 'GET',
                    headers: {
                        'Accept': 'application/json',
                        'Authorization': `Bearer ${token}`
                    }
                })
                    .then(response => {
                        // For error responses, try to parse JSON for the error message
                        if (!response.ok) {
                            return response.json().catch(() => null).then(body => {
                                const msg = body && body.message ? body.message : null;

                                if (response.status === 401) {
                                    showError(
                                        'Session Expired',
                                        msg || (Config.translations.session_expired || 'Your session has expired. Please log in again.'),
                                        401
                                    );
                                } else if (response.status === 422) {
                                    // Build a readable list of validation errors if present
                                    let detail = msg || 'One or more inputs are invalid.';
                                    if (body && body.errors) {
                                        const lines = [];
                                        Object.values(body.errors).forEach(msgs => {
                                            (Array.isArray(msgs) ? msgs : [msgs]).forEach(m => lines.push(m));
                                        });
                                        if (lines.length) detail = lines.join(' ');
                                    }
                                    showError('Validation Error', detail, 422);
                                } else {
                                    showError(
                                        'Server Error',
                                        msg || `The server returned an error (HTTP ${response.status}). Please try again later.`,
                                        response.status
                                    );
                                }

                                callback({ draw: data.draw, recordsTotal: 0, recordsFiltered: 0, data: [] });
                            });
                        }

                        return response.json();
                    })
                    .then(res => {
                        if (!res) return; // Already handled above

                        if (res.status) {
                            callback({
                                draw: data.draw,
                                recordsTotal: res.pagination.total,
                                recordsFiltered: res.pagination.total,
                                data: res.data
                            });
                        } else {
                            showError(
                                Config.translations.failed_to_load || 'Failed to load orders',
                                res.message || 'The server returned an unsuccessful response. Please try again.'
                            );
                            callback({ draw: data.draw, recordsTotal: 0, recordsFiltered: 0, data: [] });
                        }
                    })
                    .catch(error => {
                        console.error('Orders fetch error:', error);
                        showError(
                            'Network Error',
                            'Could not reach the server. Please check your connection and try again.'
                        );
                        callback({ draw: data.draw, recordsTotal: 0, recordsFiltered: 0, data: [] });
                    });
            },
            columns: [
                {
                    data: 'order_number',
                    name: 'order_number',
                    render: function (data, type, row) {
                        const orderUrl = Config.urls.show(row.id);
                        const orderLink = `<a href="${orderUrl}" class="fw-bold text-primary text-decoration-none">${data}</a>`;

                        const source = row.source || null;

                        if (!source) {
                            return `
                                <div class="d-flex flex-column">
                                    <div>${orderLink}</div>
                                </div>
                            `;
                        }

                        const logoUrl = (source.logo_url || '').replace(/[`\s]/g, '');
                        const logoImg = logoUrl
                            ? `<img src="${logoUrl}" alt="${source.platform || ''}" style="height:16px;width:16px;object-fit:contain;">`
                            : '';
                        const label = source.source || (source.platform || '').toUpperCase();
                        const extNumber = source.source_order_number || '';
                        const extId = source.source_order_id || '';

                        let metaLine = '';
                        if (extNumber && extId) {
                            metaLine = `Ext #${extNumber} • ID ${extId}`;
                        } else if (extNumber) {
                            metaLine = `Ext #${extNumber}`;
                        } else if (extId) {
                            metaLine = `ID ${extId}`;
                        }

                        return `
                            <div class="d-flex flex-column">
                                <div>${orderLink}</div>
                                <div class="d-flex align-items-center mt-1">
                                    ${logoImg ? `<span class="me-1 d-inline-flex align-items-center justify-content-center bg-white border rounded" style="width:22px;height:22px;">${logoImg}</span>` : ''}
                                    <span class="text-muted small">${label}</span>
                                </div>
                                ${metaLine ? `<div class="text-muted small mt-1">${metaLine}</div>` : ''}
                            </div>
                        `;
                    },
                    orderable: false
                },
                {
                    data: 'customer',
                    name: 'customer',
                    render: function (data) {
                        if (!data) return `<span class="text-muted">${Config.translations.guest || 'Guest'}</span>`;
                        const name = data.name || Config.translations.guest || 'Guest';
                        const email = data.email || '';
                        const phone = data.phone || '';

                        let html = `<div class="fw-semibold">${name}</div>`;
                        if (email) {
                            html += `<div class="text-muted small">${email}</div>`;
                        }
                        if (phone) {
                            html += `<div class="text-muted small">${phone}</div>`;
                        }

                        return html;
                    },
                    orderable: false
                },
                {
                    data: 'factory',
                    name: 'factory',
                    render: function (data) {
                        if (!data) return '<span class="text-muted">-</span>';
                        return `<div class="fw-semibold">${data.name}</div>`;
                    },
                    orderable: false
                },
                {
                    data: 'order_status',
                    name: 'order_status',
                    render: function (data) {
                        let badgeClass = 'bg-secondary';
                        if (data === 'delivered' || data === 'shipped') badgeClass = 'bg-success';
                        else if (data === 'processing' || data === 'confirmed' || data === 'ready_to_ship') badgeClass = 'bg-primary';
                        else if (data === 'cancelled') badgeClass = 'bg-danger';
                        else if (data === 'pending') badgeClass = 'bg-warning text-dark';

                        return `<span class="badge ${badgeClass}">${(data || '').toUpperCase().replace(/_/g, ' ')}</span>`;
                    },
                    orderable: true
                },
                {
                    data: 'payment_status',
                    name: 'payment_status',
                    render: function (data) {
                        let badgeClass = 'bg-secondary';
                        if (data === 'paid') badgeClass = 'bg-success';
                        else if (data === 'pending') badgeClass = 'bg-warning text-dark';
                        else if (data === 'failed') badgeClass = 'bg-danger';

                        return `<span class="badge ${badgeClass}">${(data || '').toUpperCase()}</span>`;
                    },
                    orderable: false
                },
                {
                    data: 'price',
                    name: 'grand_total_inc_margin',
                    orderable: true
                },
                {
                    data: 'created_at',
                    name: 'created_at',
                    render: function (data) {
                        if (!data) return '-';
                        const date = new Date(data);
                        const localDate = date.toLocaleDateString('en-US', {
                            year: 'numeric', month: 'short', day: 'numeric',
                            hour: '2-digit', minute: '2-digit'
                        });
                        const utcDate = date.toUTCString().replace('GMT', 'UTC');
                        return `${localDate}<br><small class="text-muted" style="font-size: 0.8em;">${utcDate}</small>`;
                    },
                    orderable: true
                },
                {
                    data: 'id',
                    orderable: false,
                    render: function (data) {
                        return `
                            <a href="${Config.urls.show(data)}" class="btn btn-sm btn-info text-white">
                                <i class="fas fa-eye"></i> ${Config.translations.view || 'View'}
                            </a>
                        `;
                    }
                }
            ],
            order: [[6, 'desc']] // Sort by created_at desc by default
        });
    }

    /* =======================
     | Filter Events
     ======================= */
    if (filterForm) {
        filterForm.addEventListener('submit', (e) => {
            e.preventDefault();
            table.draw();
        });
    }

    if (btnReset) {
        btnReset.addEventListener('click', () => {
            filterForm.reset();
            if (dateRangePicker.length) {
                dateRangePicker.val('');
                startDateInput.val('');
                endDateInput.val('');
            }
            table.draw();
        });
    }

    /* =======================
     | Helpers
     ======================= */
    function getCookie(name) {
        const value = `; ${document.cookie}`;
        const parts = value.split(`; ${name}=`);
        if (parts.length === 2) return parts.pop().split(';').shift();
        return undefined;
    }
});
