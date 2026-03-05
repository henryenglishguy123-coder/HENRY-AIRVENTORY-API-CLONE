document.addEventListener('DOMContentLoaded', () => {
    const CONFIG = window.customerTemplate;
    if (!CONFIG) {
        console.error('customerTemplate config missing');
        return;
    }

    const authToken = getCookie('jwt_token');
    if (!authToken) {
        toastr.error('Authentication token missing');
        return;
    }

    const tableEl = $('#templatesTable');
    if (!tableEl.length) return;

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
            processing: 'Loading templates...',
            emptyTable: 'No templates found',
        },

        ajax: {
            url: CONFIG.customerTemplateApiUrl,
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
                    per_page: d.length,
                };
            },

            dataSrc: function (json) {
                if (!json || !json.success) {
                    toastr.error('Failed to load templates');
                    return [];
                }

                json.recordsTotal = json.meta.total;
                json.recordsFiltered = json.meta.total;

                return json.data;
            },

            error: function () {
                toastr.error('Failed to load templates');
            },
        },

        columns: [
            {
                data: 'product_name',
                render: name => `
                    <div class="fw-semibold">${name}</div>
                `,
            },
            {
                data: 'designs',
                render: designs =>
                    designs.map(d => `
                        <span class="badge bg-light text-dark border me-1">
                            ${d}
                        </span>
                    `).join(''),
            }
        ],
    });
});
