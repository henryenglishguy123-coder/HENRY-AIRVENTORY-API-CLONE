window.TaxManager = class TaxManager {
    constructor(config) {
        this.routes = config.routes;
        this.csrfToken = config.csrfToken;
        this.translations = config.translations || {};
        this.tables = {
            taxes: null,
            zones: null,
            rules: null
        };
        this.init();
    }

    init() {
        this.initTaxesTable();
        this.initZonesTable();
        this.initRulesTable();
        
        this.loadLocationCountries();
        this.preloadDataForDropdowns();

        this.bindEvents();
    }

    bindEvents() {
        // Forms
        $('#taxForm').on('submit', (e) => { e.preventDefault(); this.saveTax(); });
        $('#zoneForm').on('submit', (e) => { e.preventDefault(); this.saveZone(); });
        $('#ruleForm').on('submit', (e) => { e.preventDefault(); this.saveRule(); });

        // Country Change
        $('#zone_country_id').on('change', (e) => {
            this.loadLocationStates($(e.target).val());
        });
        
        // Tab Switch
        $('button[data-bs-toggle="tab"]').on('shown.bs.tab', (e) => {
            $.fn.dataTable.tables({ visible: true, api: true }).columns.adjust();
        });

        // Filters & Actions
        this.bindFilters('tax', 'taxes');
        this.bindFilters('zone', 'zones');
        this.bindFilters('rule', 'rules');

        // Bulk Actions
        $('#tax-apply-bulk').on('click', () => this.handleBulkAction('tax'));
        $('#zone-apply-bulk').on('click', () => this.handleBulkAction('zone'));
        $('#rule-apply-bulk').on('click', () => this.handleBulkAction('rule'));
    }

    bindFilters(prefix, tableKey) {
        // Search Keyup
        $(`#${prefix}-search`).on('keyup', (e) => {
            this.tables[tableKey].search(e.target.value).draw();
        });

        // Status Filter
        $(`#${prefix}-status-filter`).on('change', () => {
            this.tables[tableKey].ajax.reload();
        });

        // Apply/Reset Buttons
        $(`#${prefix}-apply-filter`).on('click', () => {
            this.tables[tableKey].ajax.reload();
        });
        $(`#${prefix}-reset-filter`).on('click', () => {
            $(`#${prefix}-search`).val('');
            $(`#${prefix}-status-filter`).val('');
            this.tables[tableKey].search('').ajax.reload();
        });

        // Select All Checkbox
        $(`#${prefix}-check-all`).on('change', (e) => {
            const checked = e.target.checked;
            $(`#${tableKey}Table tbody input.row-checkbox`).prop('checked', checked);
            this.toggleBulkButton(prefix);
        });

        // Individual Checkbox
        $(`#${tableKey}Table tbody`).on('change', 'input.row-checkbox', () => {
            this.syncHeaderCheckbox(prefix);
            this.toggleBulkButton(prefix);
        });
    }

    syncHeaderCheckbox(prefix) {
        const tableKey = prefix === 'tax' ? 'taxes' : prefix + 's';
        const $rows = $(`#${tableKey}Table tbody input.row-checkbox`);
        const $checked = $(`#${tableKey}Table tbody input.row-checkbox:checked`);
        
        $(`#${prefix}-check-all`).prop('checked', $rows.length > 0 && $rows.length === $checked.length);
    }

    toggleBulkButton(prefix) {
        const tableKey = prefix === 'tax' ? 'taxes' : prefix + 's';
        const checkedCount = $(`#${tableKey}Table tbody input.row-checkbox:checked`).length;
        $(`#${prefix}-apply-bulk`).prop('disabled', checkedCount === 0);
    }

    // ================== DATATABLE CONFIG ==================
    getCommonDataTableConfig(url, columns, prefix) {
        return {
            responsive: true,
            autoWidth: false,
            processing: true,
            serverSide: true, // Enable Server Side
            dom: 'rtip',
            ajax: {
                url: url,
                data: (d) => {
                    d.status = $(`#${prefix}-status-filter`).val();
                }
            },
            columns: columns,
            drawCallback: () => {
                this.syncHeaderCheckbox(prefix);
                this.toggleBulkButton(prefix);
            },
            order: [[1, 'desc']] // Default sort by ID desc
        };
    }

    renderCheckbox(data, type, row) {
        return `<input type="checkbox" class="form-check-input row-checkbox" value="${row.id}">`;
    }

    renderStatus(status) {
        const enabled = (status == 1 || status === true);
        return `<span class="badge bg-${enabled ? 'success' : 'secondary'}">${enabled ? 'Active' : 'Inactive'}</span>`;
    }

    renderActions(row, type) {
        // We use encodeURIComponent to safely pass JSON
        const rowStr = encodeURIComponent(JSON.stringify(row));
        const editFn = type === 'Tax' ? 'editTax' : (type === 'Zone' ? 'editZone' : 'editRule');
        const deleteFn = type === 'Tax' ? 'deleteTax' : (type === 'Zone' ? 'deleteZone' : 'deleteRule');
        
        return `
            <div class="d-flex gap-2">
                <button class="btn btn-sm btn-info text-white" onclick="window.taxManager.${editFn}(decodeURIComponent('${rowStr}'))" title="Edit">
                    <i class="fas fa-edit"></i>
                </button>
                <button class="btn btn-sm btn-danger" onclick="window.taxManager.${deleteFn}(${row.id})" title="Delete">
                    <i class="fas fa-trash"></i>
                </button>
            </div>
        `;
    }

    // ================== TAXES ==================
    initTaxesTable() {
        const columns = [
            { data: 'id', orderable: false, className: 'text-center', render: (d, t, r) => this.renderCheckbox(d, t, r) },
            { data: 'id', name: 'id' },
            { data: 'name', name: 'name' },
            { data: 'code', name: 'code' },
            { data: 'status', name: 'status', render: this.renderStatus },
            { data: 'id', orderable: false, render: (d, t, r) => this.renderActions(r, 'Tax') }
        ];

        this.tables.taxes = $('#taxesTable').DataTable(
            this.getCommonDataTableConfig(this.routes.taxes.data, columns, 'tax')
        );
    }

    openTaxModal() {
        $('#taxForm')[0].reset();
        $('#tax_id').val('');
        $('#taxModal').modal('show');
    }

    editTax(rowStr) {
        const tax = JSON.parse(rowStr);
        $('#tax_id').val(tax.id);
        $('#tax_name').val(tax.name);
        $('#tax_code').val(tax.code);
        $('#tax_status').prop('checked', tax.status == 1);
        $('#taxModal').modal('show');
    }

    saveTax() {
        const id = $('#tax_id').val();
        const url = id ? this.routes.taxes.update.replace(':id', id) : this.routes.taxes.store;
        const data = {
            name: $('#tax_name').val(),
            code: $('#tax_code').val(),
            status: $('#tax_status').is(':checked') ? 1 : 0,
            _token: this.csrfToken
        };

        $.post(url, data)
            .done((res) => {
                toastr.success(res.message);
                $('#taxModal').modal('hide');
                this.tables.taxes.ajax.reload();
                this.preloadDataForDropdowns();
            })
            .fail(this.handleError);
    }

    deleteTax(id) {
        this.confirmDelete(this.routes.taxes.delete.replace(':id', id), () => {
            this.tables.taxes.ajax.reload();
            this.preloadDataForDropdowns();
        });
    }

    // ================== ZONES ==================
    initZonesTable() {
        const columns = [
            { data: 'id', orderable: false, className: 'text-center', render: (d, t, r) => this.renderCheckbox(d, t, r) },
            { data: 'id', name: 'id' },
            { data: 'name', name: 'name' },
            { data: 'country.name', name: 'country.name', defaultContent: '-' },
            { data: 'state_code', name: 'state_code', defaultContent: 'All' },
            { 
                data: 'postal_code_start', 
                name: 'postal_code_start',
                render: (data, type, row) => {
                    if(row.postal_code_start && row.postal_code_end) return `${row.postal_code_start} - ${row.postal_code_end}`;
                    if(row.postal_code_start) return `${row.postal_code_start}*`;
                    return 'All';
                }
            },
            { data: 'status', name: 'status', render: this.renderStatus },
            { data: 'id', orderable: false, render: (d, t, r) => this.renderActions(r, 'Zone') }
        ];

        this.tables.zones = $('#zonesTable').DataTable(
            this.getCommonDataTableConfig(this.routes.zones.index, columns, 'zone')
        );
    }

    openZoneModal() {
        $('#zoneForm')[0].reset();
        $('#zone_id').val('');
        $('#zone_state_code').html('<option value="">-- Select State --</option>');
        $('#zoneModal').modal('show');
    }

    editZone(rowStr) {
        const zone = JSON.parse(rowStr);
        $('#zone_id').val(zone.id);
        $('#zone_name').val(zone.name);
        $('#zone_country_id').val(zone.country_id);
        
        this.loadLocationStates(zone.country_id).then(() => {
            if(zone.state_code) $('#zone_state_code').val(zone.state_code);
        });

        $('#zone_zip_start').val(zone.postal_code_start);
        $('#zone_zip_end').val(zone.postal_code_end);
        $('#zone_status').prop('checked', zone.status == 1);
        $('#zoneModal').modal('show');
    }

    saveZone() {
        const id = $('#zone_id').val();
        const url = id ? this.routes.zones.update.replace(':id', id) : this.routes.zones.store;
        const data = {
            name: $('#zone_name').val(),
            country_id: $('#zone_country_id').val(),
            state_code: $('#zone_state_code').val(),
            postal_code_start: $('#zone_zip_start').val(),
            postal_code_end: $('#zone_zip_end').val(),
            status: $('#zone_status').is(':checked') ? 1 : 0,
            _token: this.csrfToken
        };

        $.post(url, data)
            .done((res) => {
                toastr.success(res.message);
                $('#zoneModal').modal('hide');
                this.tables.zones.ajax.reload();
                this.preloadDataForDropdowns();
            })
            .fail(this.handleError);
    }

    deleteZone(id) {
        this.confirmDelete(this.routes.zones.delete.replace(':id', id), () => {
            this.tables.zones.ajax.reload();
            this.preloadDataForDropdowns();
        });
    }

    // ================== RULES ==================
    initRulesTable() {
        const columns = [
            { data: 'id', orderable: false, className: 'text-center', render: (d, t, r) => this.renderCheckbox(d, t, r) },
            { data: 'id', name: 'id' },
            { data: 'tax.name', name: 'tax.name', defaultContent: '-' },
            { data: 'zone.name', name: 'zone.name', defaultContent: '-' },
            { data: 'rate', name: 'rate', render: (d) => d + '%' },
            { data: 'priority', name: 'priority' },
            { data: 'status', name: 'status', render: this.renderStatus },
            { data: 'id', orderable: false, render: (d, t, r) => this.renderActions(r, 'Rule') }
        ];

        this.tables.rules = $('#rulesTable').DataTable(
            this.getCommonDataTableConfig(this.routes.rules.index, columns, 'rule')
        );
    }

    openRuleModal() {
        $('#ruleForm')[0].reset();
        $('#rule_id').val('');
        $('#ruleModal').modal('show');
    }

    editRule(rowStr) {
        const rule = JSON.parse(rowStr);
        $('#rule_id').val(rule.id);
        $('#rule_tax_id').val(rule.tax_id);
        $('#rule_zone_id').val(rule.tax_zone_id);
        $('#rule_rate').val(rule.rate);
        $('#rule_priority').val(rule.priority);
        $('#rule_status').prop('checked', rule.status == 1);
        $('#ruleModal').modal('show');
    }

    saveRule() {
        const id = $('#rule_id').val();
        const url = id ? this.routes.rules.update.replace(':id', id) : this.routes.rules.store;
        const data = {
            tax_id: $('#rule_tax_id').val(),
            tax_zone_id: $('#rule_zone_id').val(),
            rate: $('#rule_rate').val(),
            priority: $('#rule_priority').val(),
            status: $('#rule_status').is(':checked') ? 1 : 0,
            _token: this.csrfToken
        };

        $.post(url, data)
            .done((res) => {
                toastr.success(res.message);
                $('#ruleModal').modal('hide');
                this.tables.rules.ajax.reload();
            })
            .fail(this.handleError);
    }

    deleteRule(id) {
        this.confirmDelete(this.routes.rules.delete.replace(':id', id), () => {
            this.tables.rules.ajax.reload();
        });
    }

    // ================== COMMON HELPERS ==================
    confirmDelete(url, onSuccess) {
        Swal.fire({
            title: this.translations.are_you_sure,
            text: this.translations.cannot_revert,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            confirmButtonText: this.translations.yes_delete
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: url,
                    type: 'DELETE',
                    headers: {'X-CSRF-TOKEN': this.csrfToken},
                    success: (res) => {
                        toastr.success(res.message);
                        if(onSuccess) onSuccess();
                    },
                    error: this.handleError
                });
            }
        });
    }

    handleBulkAction(prefix) {
        const tableKey = prefix === 'tax' ? 'taxes' : prefix + 's';
        const action = $(`#${prefix}-bulk-action`).val();
        const ids = [];
        
        $(`#${tableKey}Table tbody input.row-checkbox:checked`).each(function() {
            ids.push($(this).val());
        });

        if (!action) {
            toastr.warning(this.translations.select_action);
            return;
        }

        if (ids.length === 0) {
            toastr.warning(this.translations.select_item);
            return;
        }

        Swal.fire({
            title: this.translations.are_you_sure,
            text: this.translations.bulk_action_confirm,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: this.translations.yes_proceed
        }).then((result) => {
            if (result.isConfirmed) {
                this.performBulkAction(prefix, action, ids);
            }
        });
    }

    performBulkAction(prefix, action, ids) {
        let url = '';
        if(prefix === 'tax') url = this.routes.taxes.bulk;
        if(prefix === 'zone') url = this.routes.zones.bulk;
        if(prefix === 'rule') url = this.routes.rules.bulk;

        if(!url) {
            toastr.error(this.translations.bulk_not_configured);
            return;
        }

        $.ajax({
            url: url,
            type: 'POST',
            data: {
                action: action,
                ids: ids,
                _token: this.csrfToken
            },
            success: (res) => {
                toastr.success(res.message);
                if(prefix === 'tax') this.tables.taxes.ajax.reload();
                if(prefix === 'zone') this.tables.zones.ajax.reload();
                if(prefix === 'rule') this.tables.rules.ajax.reload();
                
                $(`#${prefix}-check-all`).prop('checked', false);
                this.toggleBulkButton(prefix);
                
                // Refresh dropdowns if tax or zone changed
                if(prefix !== 'rule') this.preloadDataForDropdowns();
            },
            error: this.handleError
        });
    }

    preloadDataForDropdowns() {
         // We might need separate non-paginated endpoints for dropdowns or use the paginated ones with limit=-1
         // For now, let's assume the data endpoint can return all if we don't pass DataTables params, 
         // BUT since we switched to server-side DataTables, the existing endpoints might expect params.
         // Better to have dedicated "list" endpoints for dropdowns.
         // OR check if we can get a simple list. 
         // Let's use a trick: pass length=-1 to data endpoints if they support it, or rely on a new endpoint.
         // Actually, let's keep it simple: The current endpoints return ALL data if not called by DataTables 
         // (checking for draw/start/length). We'll verify this in the controller.
         
         $.get(this.routes.taxes.data, { dropdown: 1 }, (data) => this.populateTaxDropdown(data));
         $.get(this.routes.zones.index, { dropdown: 1 }, (data) => this.populateZoneDropdown(data));
    }
    
    populateTaxDropdown(data) {
         // Handle both array (direct) and DataTables response (data.data)
         const items = Array.isArray(data) ? data : (data.data || []);
         let options = `<option value="">${this.translations.select_tax}</option>`;
         items.forEach(t => options += `<option value="${t.id}">${t.name} (${t.code})</option>`);
         $('#rule_tax_id').html(options);
    }
    
    populateZoneDropdown(data) {
         const items = Array.isArray(data) ? data : (data.data || []);
         let options = `<option value="">${this.translations.select_zone}</option>`;
         items.forEach(z => options += `<option value="${z.id}">${z.name}</option>`);
         $('#rule_zone_id').html(options);
    }

    loadLocationCountries() {
         $.get(this.routes.location.countries, (res) => {
             let options = `<option value="">${this.translations.select_country}</option>`;
             if(res.data && Array.isArray(res.data)) {
                 res.data.forEach(c => {
                     options += `<option value="${c.id}">${c.name}</option>`;
                 });
             }
             $('#zone_country_id').html(options);
         });
    }

    loadLocationStates(countryId) {
        const $stateSelect = $('#zone_state_code');
        $stateSelect.html(`<option value="">${this.translations.loading}</option>`);
        
        if(!countryId) {
            $stateSelect.html(`<option value="">${this.translations.select_state}</option>`);
            return Promise.resolve();
        }

        const url = this.routes.location.states.replace(':id', countryId);
        return $.get(url, (res) => {
             let options = `<option value="">${this.translations.select_state}</option>`;
             if(res.data && Array.isArray(res.data)) {
                 res.data.forEach(s => {
                     options += `<option value="${s.iso2}">${s.name}</option>`;
                 });
             }
             $stateSelect.html(options);
        }).fail(() => {
            $stateSelect.html(`<option value="">${this.translations.select_state}</option>`);
        });
    }

    handleError(xhr) {
        if (xhr.status === 422) {
            const errors = xhr.responseJSON.errors;
            let message = '';
            for (const key in errors) {
                message += errors[key][0] + '<br>';
            }
            toastr.error(message, this.translations.validation_error);
        } else {
            toastr.error(xhr.responseJSON?.message || this.translations.generic_error, this.translations.error);
        }
    }
}
