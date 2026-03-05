document.addEventListener('DOMContentLoaded', () => {
    const Config = window.ShippingPartnerConfig;
    if (!Config) return;

    const token = getCookie('jwt_token');

    const els = {
        tbody: document.getElementById('shipping-partners-body'),
        modal: document.getElementById('shipping-partner-modal'),
        form: document.getElementById('shipping-partner-form'),
        title: document.getElementById('shipping-partner-modal-title'),
        saveBtn: document.getElementById('shipping-partner-save-btn'),
        syncInfo: document.getElementById('partner-sync-info'),
        id: document.getElementById('partner-id'),
        name: document.getElementById('partner-name'),
        logo: document.getElementById('partner-logo'),
        code: document.getElementById('partner-code'),
        type: document.getElementById('partner-type'),
        apiBaseUrl: document.getElementById('partner-api-base-url'),
        appId: document.getElementById('partner-app-id'),
        apiKey: document.getElementById('partner-api-key'),
        apiSecret: document.getElementById('partner-api-secret'),
        webhookSecret: document.getElementById('partner-webhook-secret'),
        enabled: document.getElementById('partner-enabled'),
        carrierId: document.getElementById('partner-settings-carrier-id'),
        carrierCode: document.getElementById('partner-settings-carrier-code'),
        serviceCode: document.getElementById('partner-settings-service-code'),
    };

    let partnersCache = [];
    let modalInstance = null;

    const ensureModal = () => {
        if (!els.modal || !window.bootstrap) return null;
        if (!modalInstance) {
            modalInstance = new window.bootstrap.Modal(els.modal);
        }
        return modalInstance;
    };

    const loadPartners = async () => {
        if (!els.tbody) return;

        try {
            const response = await fetch(Config.urls.api_list, {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                    'Authorization': `Bearer ${token}`,
                },
            });

            if (!response.ok) {
                throw new Error(Config.translations.failed_to_load || 'Failed to load partners');
            }

            const res = await response.json();
            const data = res.data || [];

            partnersCache = data;
            renderTable(data);
        } catch (error) {
            console.error(error);
            toastr.error(error.message || Config.translations.failed_to_load || 'Failed to load partners');
        }
    };

    const renderTable = (rows) => {
        if (!rows.length) {
            els.tbody.innerHTML = `
                <tr>
                    <td colspan="7" class="text-center text-muted">
                        ${Config.translations.no_partners_found || 'No partners found'}
                    </td>
                </tr>
            `;
            return;
        }

        // Clear existing rows
        els.tbody.innerHTML = '';

        rows.forEach((row) => {
            const tr = document.createElement('tr');
            if (row.id !== undefined && row.id !== null) {
                tr.setAttribute('data-partner-id', row.id);
            }

            // Logo column
            const tdLogo = document.createElement('td');
            if (row.logo) {
                const img = document.createElement('img');
                img.src = row.logo;
                img.alt = row.name || '';
                img.classList.add('rounded');
                img.style.width = '32px';
                img.style.height = '32px';
                img.style.objectFit = 'contain';
                tdLogo.appendChild(img);
            } else {
                const span = document.createElement('span');
                span.classList.add('text-muted');
                span.textContent = '—';
                tdLogo.appendChild(span);
            }
            tr.appendChild(tdLogo);

            // Name and last sync column
            const tdName = document.createElement('td');
            const nameText = document.createTextNode(row.name || '');
            tdName.appendChild(nameText);

            const syncDiv = document.createElement('div');
            syncDiv.classList.add('small', 'text-muted');

            const statusText = row.last_sync_status || '—';
            const statusNode = document.createTextNode(statusText);
            syncDiv.appendChild(statusNode);

            if (row.last_sync_at) {
                const separatorNode = document.createTextNode(' • ' + row.last_sync_at);
                syncDiv.appendChild(separatorNode);
            }

            tdName.appendChild(syncDiv);
            tr.appendChild(tdName);

            // Code column
            const tdCode = document.createElement('td');
            const codeEl = document.createElement('code');
            if (row.code !== undefined && row.code !== null) {
                codeEl.textContent = row.code;
            }
            tdCode.appendChild(codeEl);
            tr.appendChild(tdCode);

            // Type column
            const tdType = document.createElement('td');
            tdType.textContent = row.type || '-';
            tr.appendChild(tdType);

            // API base URL column
            const tdApi = document.createElement('td');
            tdApi.textContent = row.api_base_url || '-';
            tr.appendChild(tdApi);

            // Enabled badge column
            const tdEnabled = document.createElement('td');
            const badgeSpan = document.createElement('span');
            if (row.is_enabled) {
                badgeSpan.classList.add('badge', 'bg-success');
                badgeSpan.textContent = 'ON';
            } else {
                badgeSpan.classList.add('badge', 'bg-secondary');
                badgeSpan.textContent = 'OFF';
            }
            tdEnabled.appendChild(badgeSpan);
            tr.appendChild(tdEnabled);

            // Actions column
            const tdActions = document.createElement('td');
            tdActions.classList.add('text-end');

            const editBtn = document.createElement('button');
            editBtn.type = 'button';
            editBtn.classList.add('btn', 'btn-sm', 'btn-outline-primary', 'btn-edit-partner');
            editBtn.textContent = 'Edit';

            tdActions.appendChild(editBtn);
            tr.appendChild(tdActions);

            els.tbody.appendChild(tr);
        });
        bindRowEvents();
    };

    const bindRowEvents = () => {
        const buttons = els.tbody.querySelectorAll('.btn-edit-partner');
        buttons.forEach(btn => {
            btn.addEventListener('click', () => {
                const row = btn.closest('tr');
                const id = parseInt(row.getAttribute('data-partner-id'), 10);
                const partner = partnersCache.find(p => p.id === id);
                if (!partner) return;
                openEditModal(partner);
            });
        });
    };

    const openEditModal = (partner) => {
        const modal = ensureModal();
        if (!modal) return;

        els.id.value = partner.id;
        els.name.value = partner.name || '';
        els.logo.value = partner.logo || '';
        els.code.value = partner.code || '';
        els.type.value = partner.type || 'both';
        els.apiBaseUrl.value = partner.api_base_url || '';
        els.appId.value = partner.app_id || '';
        els.apiKey.value = partner.api_key || '';
        els.apiSecret.value = partner.api_secret || '';
        els.webhookSecret.value = partner.webhook_secret || '';
        els.enabled.checked = !!partner.is_enabled;

        const settings = partner.settings || {};
        els.carrierId.value = settings.carrier_id || '';
        els.carrierCode.value = settings.carrier_code || '';
        els.serviceCode.value = settings.service_code || '';

        if (els.syncInfo) {
            const status = partner.last_sync_status || '—';
            const when = partner.last_sync_at || '';
            els.syncInfo.textContent = when ? status + ' • ' + when : status;
        }

        modal.show();
    };

    const savePartner = async () => {
        const id = parseInt(els.id.value, 10);
        if (!Number.isFinite(id)) return;

        const payload = {
            name: els.name.value,
            logo: els.logo.value || null,
            code: els.code.value,
            type: els.type.value,
            api_base_url: els.apiBaseUrl.value || null,
            app_id: els.appId.value || null,
            is_enabled: els.enabled.checked,
            settings: {
                carrier_id: els.carrierId.value || null,
                carrier_code: els.carrierCode.value || null,
                service_code: els.serviceCode.value || null,
            }
        };

        if (els.apiKey.value !== '') {
            payload.api_key = els.apiKey.value;
        }
        if (els.apiSecret.value !== '') {
            payload.api_secret = els.apiSecret.value;
        }
        if (els.webhookSecret.value !== '') {
            payload.webhook_secret = els.webhookSecret.value;
        }

        try {
            els.saveBtn.disabled = true;

            const url = Config.urls.api_update.replace(':id', String(id));

            const response = await fetch(url, {
                method: 'PUT',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'Authorization': `Bearer ${token}`,
                },
                body: JSON.stringify(payload),
            });

            if (!response.ok) {
                throw new Error(Config.translations.failed_to_update || 'Failed to update shipping partner');
            }

            toastr.success(Config.translations.updated || 'Shipping partner updated');

            if (modalInstance) {
                modalInstance.hide();
            }

            await loadPartners();
        } catch (error) {
            console.error(error);
            toastr.error(error.message || Config.translations.failed_to_update || 'Failed to update shipping partner');
        } finally {
            els.saveBtn.disabled = false;
        }
    };

    if (els.saveBtn) {
        els.saveBtn.addEventListener('click', savePartner);
    }

    loadPartners();
});
