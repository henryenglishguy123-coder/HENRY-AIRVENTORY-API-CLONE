document.addEventListener('DOMContentLoaded', () => {
    const CONFIG = window.customerWallet;
    if (!CONFIG) return;

    const authToken = getCookie('jwt_token');
    if (!authToken) {
        toastr.error('Authentication token missing');
        return;
    }

    const tableEl = $('#transactions-table');
    if (!tableEl.length) return;

    tableEl.DataTable({
        responsive: true,
        autoWidth: false,
        processing: true,
        serverSide: false,
        searching: false,
        ordering: true,
        pageLength: 20,
        lengthChange: false,
        language: {
            emptyTable: 'No transactions found',
        },

        ajax: {
            url: CONFIG.transactionsDataUrl,
            type: 'GET',
            headers: {
                Accept: 'application/json',
                Authorization: `Bearer ${authToken}`,
            },
            data: () => ({
                customer_id: CONFIG.customerId,
            }),
            dataSrc: json => {
                if (!json?.success) {
                    toastr.error('Failed to load transactions');
                    return [];
                }
                return json.data.data ?? [];
            },
        },

        order: [[6, 'desc']],

        columns: [
            {
                data: 'transaction_id',
                render: id => `<strong>${id}</strong>`
            },

            {
                data: 'type',
                render: type => {
                    const map = {
                        credit: 'success',
                        debit: 'danger',
                    };
                    return `<span class="badge bg-${map[type] ?? 'secondary'} text-capitalize">${type}</span>`;
                }
            },

            {
                data: 'amount',
            },

            {
                data: 'balance_after',
                render: balance =>
                    `<span class="fw-semibold text-dark">${balance}</span>`
            },

            {
                data: 'payment_method',
                render: method =>
                    method
                        ? `<span class="text-muted text-capitalize">${method}</span>`
                        : '-'
            },
            {
                data: 'status',
                render: status => {
                    const map = {
                        completed: 'success',
                        pending: 'warning',
                        failed: 'danger',
                        refunded: 'info',
                    };
                    return `<span class="badge bg-${map[status] ?? 'secondary'} text-capitalize">
                        ${status}
                    </span>`;
                }
            },
            {
                data: 'created_at',
                render: date =>
                    `<span title="${date}">
                        ${dayjs(date).format('DD MMM YYYY, hh:mm A')}
                    </span>`
            },

            {
                data: null,
                orderable: false,
                render: row => `
                    <button
                        class="btn btn-sm btn-outline-primary view-transaction"
                        title="${row.description ?? ''}">
                        <i class="mdi mdi-eye"></i>
                    </button>
                `
            }
        ],
    });

    /* 🔍 Optional action */
    const modalEl = document.getElementById('transactionModal');
    const modal = new bootstrap.Modal(modalEl);

    $('#transactions-table').on('click', '.view-transaction', function () {
        const table = $('#transactions-table').DataTable();
        const rowData = table.row($(this).closest('tr')).data();

        if (!rowData) return;

        // Status badge
        const statusMap = {
            completed: 'success',
            pending: 'warning',
            failed: 'danger',
            refunded: 'info',
        };

        document.getElementById('m_transaction_id').innerHTML =
            `<code>${rowData.transaction_id}</code>`;

        document.getElementById('m_status').innerHTML =
            `<span class="badge bg-${statusMap[rowData.status] ?? 'secondary'}">
            ${rowData.status}
        </span>`;

        document.getElementById('m_type').innerHTML =
            `<span class="badge bg-${rowData.type === 'credit' ? 'success' : 'danger'}">
            ${rowData.type}
        </span>`;

        document.getElementById('m_payment_method').textContent =
            rowData.payment_method ?? '-';

        document.getElementById('m_amount').innerHTML =
            `<strong class="${rowData.type === 'credit' ? 'text-success' : 'text-danger'}">
            ${rowData.amount}
        </strong>`;

        document.getElementById('m_balance_after').textContent =
            rowData.balance_after;

        document.getElementById('m_description').textContent =
            rowData.description ?? '—';

        document.getElementById('m_created_at').textContent =
            dayjs(rowData.created_at).format('DD MMM YYYY, hh:mm A');

        modal.show();
    });
    const fundForm = document.getElementById('walletFundForm');
    const fundModal = new bootstrap.Modal(document.getElementById('fundsModal'));
    const fundSubmitBtn = document.getElementById('fundSubmitBtn');
    const fundSubmitSpinner = document.getElementById('fundSubmitSpinner');
    const fundSubmitText = document.getElementById('fundSubmitText');
    const startFundLoading = () => {
        fundSubmitBtn.disabled = true;
        fundSubmitSpinner.classList.remove('d-none');
    };

    const stopFundLoading = () => {
        fundSubmitBtn.disabled = false;
        fundSubmitSpinner.classList.add('d-none');
    };
    if (fundForm) {
        fundForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            startFundLoading();
            const formData = new FormData(fundForm);
            const payload = {
                customer_id: CONFIG.customerId,
                type: formData.get('type'),
                amount: formData.get('amount'),
                description: formData.get('description'),
            };
            try {
                const res = await fetch(CONFIG.walletFundApiUrl, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': CONFIG.csrfToken,
                        'Authorization': `Bearer ${authToken}`,
                    },
                    body: JSON.stringify(payload),
                });
                const json = await res.json();
                if (!res.ok || !json.success) {
                    throw new Error(json.message || 'Transaction failed');
                }
                toastr.success(json.message || 'Wallet updated successfully');
                if (json.balance) {
                    document.getElementById('currentBalance').innerText = json.balance;
                }
                $('#transactions-table').DataTable().ajax.reload(null, false);
                fundForm.reset();
                fundModal.hide();
            } catch (err) {
                toastr.error(err.message);
            } finally {
                stopFundLoading();
            }
        });
    }
    const loadWalletBalance = async () => {
        try {
            const res = await fetch(CONFIG.customerWalletUrl, {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                    'Authorization': `Bearer ${authToken}`,
                },
            });
            const json = await res.json();
            if (!res.ok || !json.success) {
                throw new Error(json.message || 'Failed to load wallet balance');
            }
            const { balance, currency } = json.data;
            document.getElementById('currentBalance').innerHTML = `
            <span class="fw-bold text-success fs-5">
                ${balance}
            </span>
            <small class="text-muted ms-1">${currency}</small>
        `;
        } catch (err) {
            console.error(err);
            toastr.error('Unable to fetch wallet balance');
        }
    };
    loadWalletBalance();
});
