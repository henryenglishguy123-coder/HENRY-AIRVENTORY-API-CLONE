document.addEventListener('DOMContentLoaded', () => {

    /* =======================
     | Boot
     ======================= */
    const App = window.FactorySalesRouting;
    if (!App) return console.error('FactorySalesRouting config missing');

    const { urls, csrfToken } = App;
    const token = getCookie('jwt_token');

    if (!App.actions) {
        App.actions = {};
    }

    App.actions.export = type => {
        if (!type) return;
        const url = urls.routing.exportBase.replace(':type', type);
        window.location.href = url;
    };

    /* =======================
     | DOM
     ======================= */
    const form = document.getElementById('routingForm');
    const importForm = document.getElementById('importForm');
    const importFileInput = document.getElementById('importFile');

    const factorySelectEl = $('#factorySelect');
    const countrySelectEl = $('#countrySelect');
    const priorityInput = form.querySelector('[name="priority"]');

    const btnSave = document.getElementById('btnSave');
    const btnCancel = document.getElementById('btnCancel');
    const btnImportSubmit = document.getElementById('btnImportSubmit');

    const importModal = new bootstrap.Modal(
        document.getElementById('importModal')
    );

    /* =======================
     | State
     ======================= */
    let editFactoryId = null;
    let table = null;
    let factoryAbortController = null;

    /* =======================
     | API Helper
     ======================= */
    const apiRequest = async (url, method = 'GET', data = null, isFormData = false) => {
        const headers = {
            Accept: 'application/json',
            Authorization: `Bearer ${token}`,
            'X-CSRF-TOKEN': csrfToken,
        };

        if (!isFormData) headers['Content-Type'] = 'application/json';

        const res = await fetch(url, {
            method,
            headers,
            body: data ? (isFormData ? data : JSON.stringify(data)) : null,
        });

        const json = await res.json().catch(() => ({}));
        if (!res.ok) throw json;

        return json;
    };

    /* =======================
     | Countries Select2
     ======================= */
    countrySelectEl.select2({
        placeholder: 'Select Countries',
        width: '100%',
        allowClear: true,
    });

    const loadCountries = async () => {
        try {
            const res = await apiRequest(urls.countries);
            const countries = Array.isArray(res.data) ? res.data : [];

            countrySelectEl.empty();

            countries.forEach(c => {
                if (c.is_allowed) {
                    countrySelectEl.append(new Option(c.name, c.id));
                }
            });

            countrySelectEl.val(null).trigger('change');
        } catch {
            toastr.error('Failed to load countries');
        }
    };

    /* =======================
     | Factory Select2 (AJAX)
     ======================= */
    factorySelectEl.select2({
        placeholder: 'Search factory…',
        width: '100%',
        allowClear: true,
        minimumInputLength: 1,

        ajax: {
            delay: 300,

            transport: (params, success, failure) => {
                if (factoryAbortController) {
                    factoryAbortController.abort();
                }

                factoryAbortController = new AbortController();

                const term = params.data.term || '';
                const page = params.data.page || 1;

                fetch(
                    `${urls.routing.factory_list}?search=${encodeURIComponent(term)}&page=${page}`,
                    {
                        headers: {
                            Accept: 'application/json',
                            Authorization: `Bearer ${token}`,
                            'X-CSRF-TOKEN': csrfToken,
                        },
                        signal: factoryAbortController.signal,
                    }
                )
                    .then(res => res.json())
                    .then(success)
                    .catch(err => {
                        if (err.name !== 'AbortError') failure(err);
                    });
            },

            processResults: (res, params) => {
                params.page = params.page || 1;

                const data = Array.isArray(res.data) ? res.data : [];

                return {
                    results: data.map(f => ({
                        id: f.id,
                        text: f.business?.company_name
                            ? `${f.name} (${f.business.company_name})`
                            : f.name,
                    })),
                    pagination: {
                        more: res.meta && res.meta.current_page < res.meta.last_page,
                    },
                };
            },
        },
    });

    /* =======================
     | DataTable
     ======================= */
    const initDataTable = () => {
        table = $('#routingTable').DataTable({
            processing: true,
            serverSide: true,
            searching: false,
            ordering: false,

            ajax: {
                url: urls.routing.list,
                headers: {
                    Authorization: `Bearer ${token}`,
                    'X-CSRF-TOKEN': csrfToken,
                },
            },

            columns: [
                { data: 'factory', render: d => `<strong>${d}</strong>` },
                {
                    data: 'countries',
                    render: d => d.map(c => `<span class="badge bg-secondary me-1">${c}</span>`).join('')
                },
                {
                    data: 'priority',
                    render: d => `<span class="badge bg-warning">#${d}</span>`
                },
                {
                    data: null,
                    orderable: false,
                    render: () => `
                        <button class="btn btn-sm btn-info edit"><i class="fas fa-edit"></i></button>
                        <button class="btn btn-sm btn-danger delete"><i class="fas fa-trash"></i></button>
                    `
                },
            ],
        });

        $('#routingTable tbody')
            .on('click', '.edit', e => handleEdit(table.row($(e.currentTarget).closest('tr')).data()))
            .on('click', '.delete', e => handleDelete(table.row($(e.currentTarget).closest('tr')).data().factory_id));
    };

    /* =======================
     | Delete
     ======================= */
    const handleDelete = async factoryId => {
        if (!factoryId) return;

        const confirmed = window.confirm('Are you sure you want to delete this routing?');
        if (!confirmed) return;

        try {
            const res = await apiRequest(urls.routing.delete(factoryId), 'DELETE');

            toastr.success(res.message || 'Routing deleted successfully');
            if (table) {
                table.ajax.reload(null, false);
            }
        } catch (err) {
            const message = err && err.message ? err.message : 'Failed to delete routing';
            toastr.error(message);
        }
    };

    /* =======================
     | Edit / Reset
     ======================= */
    const handleEdit = data => {
        editFactoryId = data.factory_id;
        factorySelectEl.empty();
        factorySelectEl
            .append(new Option(data.factory, data.factory_id, true, true))
            .trigger('change')
            .prop('disabled', true);

        priorityInput.value = data.priority;
        countrySelectEl.val(data.country_ids).trigger('change');

        btnSave.textContent = 'Update Routing';
        btnSave.classList.replace('btn-primary', 'btn-info');
        btnCancel.classList.remove('d-none');
    };

    const resetForm = () => {
        form.reset();
        editFactoryId = null;

        factorySelectEl.prop('disabled', false).val(null).trigger('change');
        countrySelectEl.val(null).trigger('change');

        btnSave.textContent = 'Save Routing';
        btnSave.classList.replace('btn-info', 'btn-primary');
        btnCancel.classList.add('d-none');
    };

    btnCancel.addEventListener('click', resetForm);

    btnImportSubmit.addEventListener('click', async () => {
        if (!importFileInput || !importFileInput.files.length) {
            toastr.error('Please select a file to upload');
            return;
        }

        const file = importFileInput.files[0];
        const allowedTypes = [
            'text/csv',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
        ];
        const maxSizeBytes = 5 * 1024 * 1024; // 5 MB

        if (file.size > maxSizeBytes) {
            toastr.error('File is too large. Maximum size is 5MB.');
            return;
        }

        if (file.type && !allowedTypes.includes(file.type)) {
            toastr.error('Invalid file type. Please upload a CSV or Excel file.');
            return;
        }

        const formData = new FormData();
        formData.append('file', file);

        try {
            const res = await apiRequest(urls.routing.import, 'POST', formData, true);

            if (res.success) {
                toastr.success(res.message || 'Routing rules imported successfully.');
                importForm.reset();
                importModal.hide();
                if (table) {
                    table.ajax.reload(null, false);
                }
            } else {
                toastr.error(res.message || 'Failed to import routing rules');
            }
        } catch (err) {
            toastr.error(err.message || 'Failed to import routing rules');
        }
    });

    /* =======================
     | Submit
     ======================= */
    form.addEventListener('submit', async e => {
        e.preventDefault();

        const payload = {
            factory_id: factorySelectEl.val(),
            priority: priorityInput.value,
            country_ids: countrySelectEl.val(),
        };

        try {
            await apiRequest(
                editFactoryId ? urls.routing.update(editFactoryId) : urls.routing.store,
                editFactoryId ? 'PUT' : 'POST',
                payload
            );

            toastr.success('Routing saved successfully');
            resetForm();
            table.ajax.reload(null, false);
        } catch (err) {
            toastr.error(err.message || 'Operation failed');
        }
    });

    /* =======================
     | Init
     ======================= */
    loadCountries();
    initDataTable();
});
