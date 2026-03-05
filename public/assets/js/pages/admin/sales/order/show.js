document.addEventListener('DOMContentLoaded', () => {

    /* =======================
     | Boot
     ======================= */
    const Config = window.OrderDetailConfig;
    if (!Config) return console.error('OrderDetailConfig missing');

    const token = getCookie('jwt_token');

    /* =======================
     | Helpers (early, used by setup)
     ======================= */
    const escapeHtml = (str) => {
        if (str == null) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    };

    /**
     * Clones a button, replaces it in the DOM (removing stale listeners),
     * and returns the fresh clone. Always reassign the result back to els.buttons.*.
     */
    const replaceButton = (btn) => {
        const fresh = btn.cloneNode(true);
        btn.replaceWith(fresh);
        return fresh;
    };

    /* =======================
     | DOM Elements
     ======================= */
    const els = {
        container: document.getElementById('order-content'),
        spinner: document.getElementById('loading-spinner'),

        header: {
            number: document.getElementById('header-order-number'),
            status: document.getElementById('header-order-status'),
            payment: document.getElementById('header-payment-status'),
            date: document.getElementById('header-created-at'),
            source: document.getElementById('header-source'),
        },
        buttons: {
            shipNow: document.getElementById('btn-ship-now'),
            cancelShipment: document.getElementById('btn-cancel-shipment'),
        },

        shippingAddress: document.getElementById('shipping-address-content'),
        billingAddress: document.getElementById('billing-address-content'),
        customerInfo: document.getElementById('customer-info-content'),

        shippingMethod: {
            name: document.getElementById('shipping-method-name'),
            details: document.getElementById('shipping-method-details'),
        },

        breakdown: {
            subtotal: document.getElementById('breakdown-subtotal'),
            discount: document.getElementById('breakdown-discount'),
            shipping: document.getElementById('breakdown-shipping'),
            tax: document.getElementById('breakdown-tax'),
            total: document.getElementById('breakdown-total'),
        },

        itemsBody: document.getElementById('order-items-body'),
        transactionsBody: document.getElementById('order-transactions-body'),
    };

    /* =======================
     | Fetch Data
     ======================= */
    const loadOrder = async () => {
        const overlay = document.getElementById('order-loading-overlay');
        const mainContent = document.getElementById('order-content');

        if (mainContent && mainContent.style.display !== 'none' && overlay) {
            overlay.style.display = 'block';
        }

        try {
            const response = await fetch(Config.urls.get, {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                    'Authorization': `Bearer ${token}`
                }
            });

            if (!response.ok) {
                if (response.status === 401) {
                    throw new Error(Config.translations.unauthorized || 'Unauthorized');
                }
                throw new Error(Config.translations.failed_to_load || 'Failed to load order');
            }

            const res = await response.json();
            if ((res.success || res.status) && res.data) {
                currentOrderData = res.data;
                renderOrder(res.data);
            } else {
                throw new Error('Invalid response format');
            }

        } catch (error) {
            console.error(error);
            toastr.error(error.message || Config.translations.failed_to_load || 'Failed to load order details');
        } finally {
            els.spinner.style.display = 'none';
            els.container.style.display = 'block';
            const overlay = document.getElementById('order-loading-overlay');
            if (overlay) overlay.style.display = 'none';
        }
    };

    /* =======================
     | Render
     ======================= */
    const renderOrder = (order) => {
        // Header
        els.header.number.textContent = order.order_number;
        els.header.date.innerHTML = formatDateWithUtc(order.created_at);
        els.header.status.innerHTML = getStatusBadge(order.status || order.order_status);
        els.header.payment.innerHTML = getPaymentBadge(order.payment_status);

        if (els.header.source && order.source) {
            els.header.source.innerHTML = renderSourceHeader(order.source);
        }

        // Action Buttons Setup
        setupActionButtons(order);

        if (order.shipping_address) {
            els.shippingAddress.innerHTML = formatAddress(order.shipping_address);
        } else {
            els.shippingAddress.innerHTML = `<span class="text-muted">${Config.translations.no_shipping_address || 'No shipping address'}</span>`;
        }

        if (els.billingAddress) {
            if (order.billing_address) {
                els.billingAddress.innerHTML = formatAddress(order.billing_address);
            } else {
                els.billingAddress.innerHTML = `<span class="text-muted">${Config.translations.no_billing_address || 'Same as shipping address'}</span>`;
            }
        }

        if (els.customerInfo) {
            let customerHtml = '';
            if (order.factory) {
                customerHtml += `
                    <div class="mb-4">
                        <span class="sidebar-label">Factory / Business</span>
                        <div class="fw-bold text-primary fs-6">${escapeHtml(order.factory.name)}</div>
                        ${order.factory.id ? `<small class="text-muted">ID: ${escapeHtml(order.factory.id)}</small>` : ''}
                    </div>
                `;
            }
            if (order.customer) {
                customerHtml += `
                    <div class="${order.factory ? 'pt-3 border-top mt-3' : ''}">
                        <span class="sidebar-label">Purchaser</span>
                        <div class="fw-bold">${escapeHtml(order.customer.name)}</div>
                        ${order.customer.email ? `<div class="small text-muted"><i class="far fa-envelope me-1"></i>${escapeHtml(order.customer.email)}</div>` : ''}
                    </div>
                `;
            }
            els.customerInfo.innerHTML = customerHtml || '<span class="text-muted">No details available</span>';
        }

        if (els.shippingMethod.name) {
            els.shippingMethod.name.textContent = order.shipping_method || Config.translations.standard_shipping || 'Standard shipping';
        }
        if (els.shippingMethod.details) {
            els.shippingMethod.details.textContent = Config.translations.shipping_details_placeholder || '6 - 10 business days';
        }

        if (order.breakdown) {
            if (els.breakdown.subtotal) els.breakdown.subtotal.textContent = order.breakdown.subtotal;
            if (els.breakdown.discount) els.breakdown.discount.textContent = order.breakdown.discount;
            if (els.breakdown.shipping) els.breakdown.shipping.textContent = order.breakdown.shipping;
            if (els.breakdown.tax) els.breakdown.tax.textContent = order.breakdown.tax;
            if (els.breakdown.total) els.breakdown.total.textContent = order.breakdown.total;
        }

        renderItems(order.items);
        renderTransactions(order.payments);
        renderRecentShipments(order);
        renderShipments(order);
        renderTimeline(order);
        loadMessages(order.order_number);
    };

    const setupActionButtons = (order) => {
        const orderStatus = (order.status || order.order_status || '').toLowerCase();

        // Hide by default — guard for null when buttons aren't rendered
        if (els.buttons.shipNow) els.buttons.shipNow.classList.add('d-none');
        if (els.buttons.cancelShipment) els.buttons.cancelShipment.classList.add('d-none');

        // Setup Ship Now
        const hasFactory = !!order.factory;
        const shipments = order.shipments || [];

        // Robust check: A shipment is active ONLY if its top status isn't cancelled/failed
        // AND it doesn't have a 'cancelled' event in its tracking logs.
        const hasActiveShipment = shipments.some(s => {
            const topStatus = (s.status || '').toLowerCase();
            if (['cancelled', 'failed'].includes(topStatus)) return false;

            // Check logs for explicit cancellation if top status didn't catch it
            const logs = s.tracking_logs || [];
            const isLoggedAsCancelled = logs.some(log => (log.status || '').toLowerCase() === 'cancelled');

            return !isLoggedAsCancelled;
        });

        if (['confirmed', 'processing'].includes(orderStatus) && hasFactory && !hasActiveShipment && els.buttons.shipNow) {
            els.buttons.shipNow.classList.remove('d-none');
            // Replace to remove old listeners; capture fresh reference via helper
            els.buttons.shipNow = replaceButton(els.buttons.shipNow);

            els.buttons.shipNow.addEventListener('click', async () => {
                if (!confirm("Are you sure you want to ship this full order? This will create a shipment via the designated shipping partner.")) return;

                try {
                    els.buttons.shipNow.disabled = true;
                    els.buttons.shipNow.innerHTML = `<i class="fas fa-spinner fa-spin me-1"></i> Processing...`;

                    const res = await fetch(Config.urls.ship, {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'Content-Type': 'application/json',
                            'Authorization': `Bearer ${token}`
                        }
                    });

                    const data = await res.json();
                    if (!res.ok) {
                        const errorMessage = data.error
                            ? `${data.message ? data.message + ' ' : ''}${data.error}`
                            : (data.message || 'Failed to dispatch shipment');
                        throw new Error(errorMessage);
                    }

                    toastr.success(data.message || 'Shipment dispatch job has been queued.');
                    setTimeout(() => window.location.reload(), 1500);
                } catch (e) {
                    console.error(e);
                    toastr.error(e.message || 'Error occurred while creating shipment');
                    els.buttons.shipNow.disabled = false;
                    els.buttons.shipNow.innerHTML = `<i class="fas fa-shipping-fast me-1"></i> ${Config.translations.ship_now || 'Ship Now'}`;
                }
            });
        }

        // Setup Cancel Shipment
        // Only allow cancelling if the latest shipment is NOT already cancelled
        const latestShipment = shipments.length > 0 ? shipments[shipments.length - 1] : null;
        const canCancelShipment = latestShipment && latestShipment.status && latestShipment.status.toLowerCase() !== 'cancelled';

        if (canCancelShipment && !['cancelled', 'delivered'].includes(orderStatus) && els.buttons.cancelShipment) {
            els.buttons.cancelShipment.classList.remove('d-none');
            // Replace to remove old listeners; capture fresh reference via helper
            els.buttons.cancelShipment = replaceButton(els.buttons.cancelShipment);

            els.buttons.cancelShipment.addEventListener('click', async () => {
                if (!confirm("Are you sure you want to cancel this shipment? This action cannot be easily undone.")) return;

                try {
                    els.buttons.cancelShipment.disabled = true;
                    els.buttons.cancelShipment.innerHTML = `<i class="fas fa-spinner fa-spin me-1"></i> Cancelling...`;

                    const cancelUrl = Config.urls.cancelShipment.replace(':shipment', latestShipment.id);
                    const res = await fetch(cancelUrl, {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'Content-Type': 'application/json',
                            'Authorization': `Bearer ${token}`
                        }
                    });

                    const data = await res.json();
                    if (!res.ok) {
                        if (data.full_payload) {
                            // If there's a payload, we should refresh the timeline to show the "View Details" button
                            // rather than just showing a toastr error.
                            toastr.error(data.message || 'Cancellation failed by carrier');
                            loadOrder(); // Re-render everything to show the new history item
                        } else {
                            throw new Error(data.message || 'Failed to cancel shipment');
                        }
                        return;
                    }

                    toastr.success(data.message || 'Shipment has been cancelled successfully.');
                    setTimeout(() => window.location.reload(), 1500);
                } catch (e) {
                    console.error(e);
                    toastr.error(e.message || 'Error occurred while cancelling shipment');
                    els.buttons.cancelShipment.disabled = false;
                    els.buttons.cancelShipment.innerHTML = `<i class="fas fa-times me-1"></i> ${Config.translations.cancel_shipment || 'Cancel Shipment'}`;
                }
            });
        }
    };

    const renderItems = (items) => {
        if (!items || !items.length) {
            els.itemsBody.innerHTML = `<tr><td colspan="6" class="text-center">${Config.translations.no_items_found || 'No items found'}</td></tr>`;
            return;
        }

        els.itemsBody.innerHTML = items.map(item => {
            const optionsHtml = (item.options || []).map(opt => `
                <div class="mb-1">
                    <span class="text-muted">${escapeHtml(opt.name)}:</span> <span class="fw-bold">${escapeHtml(opt.value)}</span>
                </div>
            `).join('');

            const designsHtml = (item.designs || []).map(d => {
                const cleanUrl = (d.preview_image || '').replace(/[`\s]/g, '');
                return `
                <div class="d-inline-block text-center me-2 mb-2" title="${d.layer_name}">
                    <div class="border rounded bg-light d-flex align-items-center justify-content-center" style="width: 50px; height: 50px; overflow: hidden;">
                        <img src="${cleanUrl}" alt="${d.layer_name}" style="max-width: 100%; max-height: 100%;">
                    </div>
                    <small class="d-block text-muted" style="font-size: 10px;">${d.layer_name}</small>
                </div>
            `}).join('');

            let mainImage = '/assets/images/placeholder.png';
            if (item.designs && item.designs.length > 0) {
                mainImage = (item.designs[0].preview_image || '').replace(/[`\s]/g, '');
            }

            return `
            <tr>
                <td class="ps-4">
                    <div class="d-flex align-items-center">
                        <div class="bg-light rounded me-3 d-flex align-items-center justify-content-center" style="width: 48px; height: 48px; min-width: 48px; overflow: hidden;">
                            <img src="${mainImage}" alt="Product" style="max-width: 100%; max-height: 100%;">
                        </div>
                        <div>
                            <div class="fw-bold text-dark text-truncate" style="max-width: 180px;">${escapeHtml(item.name)}</div>
                            ${item.sku ? `<div class="text-muted" style="font-size: 0.75rem;">SKU: ${escapeHtml(item.sku)}</div>` : ''}
                        </div>
                    </div>
                </td>
                <td class="small">
                    ${optionsHtml || '<span class="text-muted">-</span>'}
                    <div class="mt-2 d-flex flex-wrap gap-1">${designsHtml}</div>
                </td>
                <td class="fw-semibold">${item.price}</td>
                <td class="text-center">
                    <span class="badge bg-light text-dark border fw-normal px-2 py-1">${item.quantity}</span>
                </td>
                <td class="pe-4 text-end fw-bold text-dark">${item.subtotal}</td>
            </tr>
            `;
        }).join('');
    };

    const renderTransactions = (payments) => {
        if (!payments || !payments.length) {
            els.transactionsBody.innerHTML = `<tr><td colspan="5" class="text-center text-muted">${Config.translations.no_transactions_found || 'No transactions found'}</td></tr>`;
            return;
        }

        els.transactionsBody.innerHTML = payments.map(p => {
            return `
                <tr>
                    <td class="ps-4">
                        <div class="fw-bold text-dark">${p.transaction_id ? p.transaction_id.substring(0, 8) + '...' : '-'}</div>
                        <div class="text-muted" style="font-size: 0.7rem;">${p.payment_method}</div>
                    </td>
                    <td>${getPaymentBadge(p.status)}</td>
                    <td class="pe-4 text-end fw-bold">${p.amount}</td>
                </tr>
            `;
        }).join('');
    };

    const renderShipments = (order) => {
        const shipmentAccordion = document.getElementById('shipmentAccordion');
        const shipmentCard = document.getElementById('shipments-card');
        if (!shipmentAccordion || !shipmentCard) return;

        if (!order.shipments || order.shipments.length === 0) {
            shipmentCard.style.display = 'none';
            const tabEl = document.getElementById('shipments-tab');
            if (tabEl) tabEl.innerHTML = `<i class="fas fa-truck me-2"></i>Shipments & Tracking`;
            return;
        }

        shipmentCard.style.display = 'block';
        const tabEl = document.getElementById('shipments-tab');
        if (tabEl) tabEl.innerHTML = `<i class="fas fa-truck me-2"></i>Shipments & Tracking <span class="badge bg-primary ms-1">${order.shipments.length}</span>`;

        shipmentAccordion.innerHTML = order.shipments.map((shipment, idx) => {
            const status = (shipment.status || 'unknown').toLowerCase();
            const orderStatus = (order.status || order.order_status || '').toLowerCase();
            const isCancelled = status === 'cancelled';
            const processedDate = shipment.created_at ? formatDateWithUtc(new Date(shipment.created_at)) : 'N/A';
            const canCancelRow = !isCancelled && status !== 'delivered' && !['cancelled', 'delivered'].includes(orderStatus);
            const isActive = activeShipmentId === String(shipment.id);
            const accordionId = `shipment-item-${shipment.id}`;

            return `
                <div class="accordion-item border-bottom mb-2 rounded shadow-sm border-0">
                    <h2 class="accordion-header" id="heading-${accordionId}">
                        <button class="accordion-button ${isActive ? '' : 'collapsed'} py-3 px-4 ${isActive ? 'bg-primary bg-opacity-10' : 'bg-light bg-opacity-50'}" type="button" 
                                data-bs-toggle="collapse" data-bs-target="#collapse-${accordionId}" 
                                aria-expanded="${isActive ? 'true' : 'false'}" aria-controls="collapse-${accordionId}"
                                onclick="filterTimelineByShipment('${shipment.id}')">
                            <div class="d-flex w-100 justify-content-between align-items-center me-3 flex-wrap flex-md-nowrap gap-3">
                                <div class="flex-grow-1" style="min-width: 200px;">
                                    <div class="fw-bold text-dark mb-1 fs-6">
                                        ${isCancelled ? `<del class="text-muted">${escapeHtml(shipment.sales_shipment_number || 'N/A')}</del>` : escapeHtml(shipment.sales_shipment_number || 'N/A')}
                                        ${isCancelled ? '<span class="badge bg-danger ms-1 fs-6">VOID</span>' : ''}
                                    </div>
                                    <div class="text-muted small font-monospace">${escapeHtml(shipment.tracking_number || 'No Tracking')}</div>
                                </div>
                                <div class="text-center flex-grow-1" style="min-width: 150px;">
                                    <div class="small text-muted mb-1">${escapeHtml(shipment.provider_name || 'N/A')}</div>
                                    ${getStatusBadge(status)}
                                </div>
                                <div class="text-end flex-grow-1 d-none d-md-block" style="min-width: 150px;">
                                    <div class="text-muted small">${processedDate}</div>
                                </div>
                            </div>
                        </button>
                    </h2>
                    <div id="collapse-${accordionId}" class="accordion-collapse collapse ${isActive ? 'show' : ''}" 
                         aria-labelledby="heading-${accordionId}" data-bs-parent="#shipmentAccordion">
                        <div class="accordion-body bg-white p-0">
                            
                            <!-- Action Bar -->
                            <div class="p-3 border-bottom bg-light d-flex justify-content-between align-items-center">
                                <div>
                                    ${canCancelRow ? `
                                        <button class="btn btn-sm btn-outline-danger shadow-sm px-3" 
                                                onclick="event.stopPropagation(); cancelSpecificShipment('${shipment.id}')">
                                            <i class="fas fa-times me-1"></i> Cancel
                                        </button>
                                    ` : `
                                        ${isCancelled ? '<span class="badge bg-secondary px-3 py-2"><i class="fas fa-ban me-1"></i> Cancelled</span>' : ''}
                                        ${status === 'delivered' ? '<span class="badge bg-success px-3 py-2"><i class="fas fa-check-circle me-1"></i> Delivered</span>' : ''}
                                    `}
                                </div>
                                <div>
                                    ${shipment.tracking_url && !isCancelled ? `
                                        <a href="${shipment.tracking_url}" target="_blank" class="btn btn-sm btn-dark shadow-sm px-3">
                                            <i class="fas fa-external-link-alt me-2"></i> Track Live
                                        </a>
                                    ` : ''}
                                </div>
                            </div>

                            <!-- Tracking Logs -->
                            <div class="p-4">
                                <h6 class="small fw-bold text-muted text-uppercase mb-4">Tracking Log History</h6>
                                <div class="tracking-log-container">
                                    ${renderTrackingLogs(shipment.tracking_logs)}
                                </div>
                            </div>

                        </div>
                    </div>
                </div>
            `;
        }).join('');
    };

    const renderTrackingLogs = (logs) => {
        if (!logs || logs.length === 0) {
            return `<div class="text-center py-3 text-muted small">No tracking logs available yet.</div>`;
        }

        // Sort logs newest first
        const sortedLogs = [...logs].sort((a, b) => new Date(b.checkpoint_time) - new Date(a.checkpoint_time));

        return sortedLogs.map((log, idx) => {
            const isLast = idx === sortedLogs.length - 1;
            const dateStr = log.checkpoint_time ? formatDateWithUtc(new Date(log.checkpoint_time)) : 'N/A';
            const logStatus = (log.status || 'Update').toUpperCase();

            return `
                <div class="d-flex mb-3 position-relative">
                    ${!isLast ? '<div class="position-absolute h-100 border-start border-2 ms-2 top-0 mt-3 start-0 z-0"></div>' : ''}
                    <div class="me-3 position-relative z-1">
                        <div class="bg-white rounded-circle border border-2 border-primary d-flex align-items-center justify-content-center lh-1 p-1">
                            <div class="bg-primary rounded-circle" style="min-width: 8px; min-height: 8px;"></div>
                        </div>
                    </div>
                    <div class="flex-grow-1 border-bottom pb-2">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <span class="badge bg-light text-dark border fw-semibold mb-1 fs-6">${escapeHtml(logStatus)}</span>
                                <div class="text-dark fw-bold mb-1 fs-6">${escapeHtml(log.description || 'Status update')}</div>
                            </div>
                            <span class="text-muted small">${dateStr}</span>
                        </div>
                        ${log.location ? `<div class="text-muted small"><i class="fas fa-map-marker-alt me-1"></i>${escapeHtml(log.location)}</div>` : ''}
                    </div>
                </div>
            `;
        }).join('');
    };

    const renderRecentShipments = (order) => {
        const container = document.getElementById('recent-shipments-list');
        const card = document.getElementById('recent-shipments-card');
        if (!container || !card) return;

        if (!order.shipments || !order.shipments.length) {
            card.style.display = 'none';
            return;
        }

        card.style.display = 'block';
        container.innerHTML = order.shipments.slice(0, 3).map(ship => {
            const status = (ship.status || 'unknown').toLowerCase();
            const isActive = activeShipmentId === String(ship.id);
            return `
                <div class="px-3 py-2 border-bottom ${isActive ? 'bg-primary bg-opacity-10' : (status === 'cancelled' ? 'bg-light' : 'bg-white')}" style="cursor: pointer;" onclick="filterTimelineByShipment('${ship.id}')">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <span class="small fw-bold text-dark">${escapeHtml(ship.sales_shipment_number)}</span>
                        ${getStatusBadge(status)}
                    </div>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="text-muted small">${escapeHtml(ship.tracking_number || 'No tracking')}</span>
                        ${ship.tracking_url ? `<a href="${ship.tracking_url}" target="_blank" class="text-primary small" onclick="event.stopPropagation()"><i class="fas fa-external-link-alt"></i></a>` : ''}
                    </div>
                </div>
            `;
        }).join('');
    };

    const renderTimeline = (order) => {
        const timelineContainer = document.getElementById('order-timeline-body');
        if (!timelineContainer) return;

        let events = [];

        // 1. Order Status History Events
        if (order.status_history && order.status_history.length > 0) {
            order.status_history.forEach(history => {
                const toStatus = (history.to_status || 'Unknown').toLowerCase();
                let badgeClass = 'bg-secondary';
                let icon = 'fa-info-circle';

                if (['shipped', 'delivered', 'completed'].includes(toStatus)) {
                    badgeClass = 'bg-success text-white';
                    icon = 'fa-check';
                } else if (['failed', 'cancelled'].includes(toStatus)) {
                    badgeClass = 'bg-danger text-white';
                    icon = 'fa-times';
                } else if (toStatus === 'processing') {
                    badgeClass = 'bg-primary text-white';
                    icon = 'fa-cogs';
                }

                let details = `<div class="mb-2">${getStatusBadge(toStatus)}</div>`;
                if (history.reason) details += `<p class="mb-0 text-muted small">${escapeHtml(history.reason)}</p>`;
                if (history.shipping_partner) {
                    details += `<div class="mt-2 small"><span class="text-muted">Via </span><span class="fw-bold">${escapeHtml(history.shipping_partner.name)}</span></div>`;
                }

                if (history.full_payload) {
                    const payloadId = `payload-${history.id}`;
                    window[payloadId] = history.full_payload;
                    details += `
                        <div class="mt-2">
                            <button class="btn btn-sm btn-outline-danger py-0 px-2 rounded-1" onclick="showErrorDetail('${payloadId}')">
                                <i class="fas fa-bug me-1"></i> Debug Logs
                            </button>
                        </div>
                    `;
                }

                events.push({
                    date: new Date(history.created_at),
                    title: `Order: ${escapeHtml(toStatus.toUpperCase())}`,
                    details: details,
                    icon: icon,
                    type: 'status'
                });
            });
        }

        // 2. Shipment Tracking Events
        if (order.shipments && order.shipments.length > 0) {
            order.shipments.forEach(shipment => {
                // Filter by activeShipmentId if set
                if (activeShipmentId && String(shipment.id) !== activeShipmentId) return;

                if (shipment.tracking_logs && shipment.tracking_logs.length > 0) {
                    shipment.tracking_logs.forEach(log => {
                        const eventDate = new Date(log.checkpoint_time);
                        if (isNaN(eventDate.getTime())) return;

                        let logStatus = (log.status || 'Update').toUpperCase();
                        let details = `<div class="text-dark small fw-bold mb-1">${escapeHtml(log.description || 'Shipment update')}</div>`;
                        if (log.location) details += `<div class="text-muted small"><i class="fas fa-map-marker-alt me-1"></i>${escapeHtml(log.location)}</div>`;

                        details += `<div class="mt-1 small"><span class="badge bg-light text-muted border fw-normal">${escapeHtml(shipment.sales_shipment_number)}</span></div>`;

                        events.push({
                            date: eventDate,
                            title: `Shipment: ${logStatus}`,
                            details: details,
                            icon: logStatus === 'CANCELLED' ? 'fa-times-circle' : 'fa-truck',
                            type: 'tracking'
                        });
                    });
                }
            });
        }

        // Sort events chronologically (newest first)
        events.sort((a, b) => b.date - a.date);

        if (events.length === 0) {
            // Fallback
            events.push({
                date: new Date(order.created_at),
                title: 'Order Placed',
                details: `<span class="status-badge-premium bg-light text-muted border">PENDING</span>`,
                icon: 'fa-shopping-cart',
                type: 'status'
            });
        }

        let html = '';

        if (activeShipmentId) {
            html += `
                <div class="alert alert-info py-2 px-3 mb-4 d-flex justify-content-between align-items-center small">
                    <div>
                        <i class="fas fa-filter me-2"></i>
                        Showing timeline for Shipment: <strong>${escapeHtml(order.shipments.find(s => String(s.id) === activeShipmentId)?.sales_shipment_number || 'N/A')}</strong>
                    </div>
                    <button class="btn btn-sm btn-link text-decoration-none p-0" onclick="filterTimelineByShipment(null)">
                        Clear Filter <i class="fas fa-times-circle ms-1"></i>
                    </button>
                </div>
            `;
        }

        events.forEach((evt, idx) => {
            const dateStr = formatDateWithUtc(evt.date);

            html += `
                <div class="timeline-item mb-3">
                    <div class="d-flex justify-content-between align-items-start mb-1">
                        <h6 class="fw-bold mb-0">
                            <i class="fas ${evt.icon} me-2 text-muted"></i>${evt.title}
                        </h6>
                        <div class="text-end small text-muted">${dateStr}</div>
                    </div>
                    <div class="ps-4 small">${evt.details}</div>
                </div>
            `;
        });

        timelineContainer.innerHTML = html;
    };

    window.showErrorDetail = (payloadId) => {
        const payload = window[payloadId];
        if (!payload) return toastr.error('Payload not found');

        const el = document.getElementById('error-payload-raw');
        if (el) {
            el.textContent = JSON.stringify(payload, null, 2);
            const modal = new bootstrap.Modal(document.getElementById('errorDetailModal'));
            modal.show();
        }
    };

    window.cancelSpecificShipment = async (shipmentId) => {
        if (!confirm("Are you sure you want to cancel this shipment?")) return;

        try {
            const cancelUrl = Config.urls.cancelShipment.replace(':shipment', shipmentId);
            const res = await fetch(cancelUrl, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'Authorization': `Bearer ${token}`
                }
            });

            const data = await res.json();
            if (!res.ok) {
                toastr.error(data.message || 'Failed to cancel shipment');
                if (data.full_payload) loadOrder();
                return;
            }

            toastr.success(data.message || 'Shipment cancelled');
            loadOrder();
            // Re-setup buttons is done via loadOrder -> renderOrder -> setupActionButtons
        } catch (e) {
            console.error(e);
            toastr.error('Error occurred while cancelling shipment');
        }
    };

    /* =======================
     | Helpers
     ======================= */
    const getStatusBadge = (status) => {
        let cls = 'bg-secondary';
        if (['completed', 'shipped', 'delivered'].includes(status)) cls = 'bg-success';
        else if (status === 'processing') cls = 'bg-primary';
        else if (status === 'cancelled' || status === 'failed') cls = 'bg-danger';
        else if (['pending', 'confirmed'].includes(status)) cls = 'bg-warning text-dark';
        return `<span class="badge ${cls}">${(status || 'N/A').toUpperCase()}</span>`;
    };

    const getPaymentBadge = (status) => {
        let cls = 'bg-secondary';
        if (['paid', 'captured'].includes(status)) cls = 'bg-success';
        else if (status === 'pending') cls = 'bg-warning text-dark';
        else if (status === 'failed') cls = 'bg-danger';
        return `<span class="badge ${cls}">${(status || 'N/A').toUpperCase()}</span>`;
    };

    const formatAddress = (addr) => {
        return `
            <strong>${escapeHtml(addr.first_name)} ${escapeHtml(addr.last_name)}</strong><br>
            ${addr.phone ? escapeHtml(addr.phone) + '<br>' : ''}
            ${addr.email ? `<a href="mailto:${escapeHtml(addr.email)}">${escapeHtml(addr.email)}</a><br>` : ''}
            <div class="mt-2">
                ${escapeHtml(addr.address_line_1)}<br>
                ${addr.address_line_2 ? escapeHtml(addr.address_line_2) + '<br>' : ''}
                ${escapeHtml(addr.city)}, ${escapeHtml(addr.state || '')} ${escapeHtml(addr.postal_code)}<br>
                ${escapeHtml(addr.country || '')}
            </div>
        `;
    };

    const formatDateWithUtc = (value) => {
        if (!value) return '-';
        const date = new Date(value);
        if (Number.isNaN(date.getTime())) return value;
        const local = date.toLocaleString('en-US', {
            year: 'numeric', month: 'short', day: 'numeric',
            hour: '2-digit', minute: '2-digit'
        });
        const utc = date.toUTCString().replace('GMT', 'UTC');
        return `${local}<br><span class="text-muted" style="font-size: 0.8em;">${utc}</span>`;
    };

    const renderSourceHeader = (source) => {
        if (!source) return '';
        const logoUrl = (source.logo_url || '').replace(/[`\s]/g, '');
        const platform = (source.platform || '').toUpperCase();
        const store = source.source || '';
        const extNumber = source.source_order_number || '';
        const extId = source.source_order_id || '';
        const createdHtml = source.source_created_at ? formatDateWithUtc(source.source_created_at) : '';

        let metaLine = extNumber && extId ? `Ext #${extNumber} • ID ${extId}` : (extNumber ? `Ext #${extNumber}` : (extId ? `ID ${extId}` : ''));

        return `
            <div class="d-flex flex-column">
                <div class="d-flex align-items-center">
                    <span class="me-2 text-uppercase text-muted">Source</span>
                    ${logoUrl ? `<span class="me-1 d-inline-flex align-items-center justify-content-center bg-white border rounded" style="width:22px;height:22px;"><img src="${logoUrl}" style="height:16px;width:16px;object-fit:contain;"></span>` : ''}
                    <span class="fw-semibold">${store || platform}</span>
                    ${platform ? `<span class="badge bg-light text-muted ms-2 border">${platform}</span>` : ''}
                </div>
                ${metaLine ? `<div class="text-muted small mt-1">${metaLine}</div>` : ''}
                ${createdHtml ? `<div class="mt-1 small">${createdHtml}</div>` : ''}
            </div>
        `;
    };

    /* =======================
     | Message Functions
     ======================= */

    let lastMessageId = null;
    let firstMessageId = null;
    let currentPage = 1;
    let isLastPage = false;
    let messageRefreshInterval = null;
    let isRefreshing = false;
    let loadedMessageIds = new Set();
    let activeShipmentId = null;
    let currentOrderData = null;

    const loadMessages = async (orderNumber, mode = 'refresh') => {
        // modes: 'refresh' (latest), 'more' (older), 'auto' (incremental)
        if (isRefreshing) return;

        const refreshIcon = document.getElementById('refresh-icon');
        const refreshBtn = document.getElementById('refresh-messages-btn');
        const loadingSpinner = document.getElementById('messages-loading');
        const loadMoreSpinner = document.getElementById('load-more-spinner');
        const loadMoreBtn = document.getElementById('load-more-btn');

        try {
            isRefreshing = true;
            let params = new URLSearchParams();

            if (mode === 'auto' && lastMessageId) {
                params.append('after_id', lastMessageId);
            } else if (mode === 'more') {
                if (firstMessageId) params.append('before_id', firstMessageId);
                if (loadMoreSpinner) loadMoreSpinner.style.display = 'inline-block';
                if (loadMoreBtn) loadMoreBtn.style.display = 'none';
            } else {
                if (refreshIcon) refreshIcon.classList.add('fa-spin');
                if (refreshBtn) refreshBtn.disabled = true;
                // Full reload
                currentPage = 1;
                isLastPage = false;
                loadedMessageIds.clear();
                lastMessageId = null;
                firstMessageId = null;
                if (loadingSpinner) loadingSpinner.style.display = 'block';
            }

            const response = await fetch(`/api/v1/orders/${orderNumber}/messages?${params.toString()}`, {
                method: 'GET',
                headers: { 'Accept': 'application/json', 'Authorization': `Bearer ${token}` }
            });

            if (!response.ok) throw new Error('Failed to load messages');
            const res = await response.json();

            if (res.success && res.data) {
                const messagesData = res.data.data || res.data;
                const pagination = res.data.current_page ? res.data : null;

                if (mode === 'more') {
                    if (pagination) {
                        currentPage = pagination.current_page;
                        isLastPage = currentPage >= pagination.last_page;
                    } else {
                        isLastPage = messagesData.length === 0;
                    }
                    renderMessages(messagesData, 'prepend');
                } else if (mode === 'auto') {
                    renderMessages(messagesData, 'append');
                } else {
                    // Initial or manual refresh
                    if (pagination) {
                        currentPage = pagination.current_page;
                        isLastPage = currentPage >= pagination.last_page;
                    }
                    document.getElementById('messages-list').querySelectorAll('.message-item, .date-divider').forEach(el => el.remove());
                    renderMessages(messagesData, 'append');
                }

                // Update pagination UI
                const loadMoreContainer = document.getElementById('load-more-container');
                if (loadMoreContainer) {
                    loadMoreContainer.style.display = isLastPage ? 'none' : 'block';
                }
            }
        } catch (error) {
            console.error('Error loading messages:', error);
        } finally {
            isRefreshing = false;
            if (mode !== 'auto') {
                if (refreshIcon) refreshIcon.classList.remove('fa-spin');
                if (refreshBtn) refreshBtn.disabled = false;
                if (loadingSpinner) loadingSpinner.style.display = 'none';
                if (loadMoreSpinner) loadMoreSpinner.style.display = 'none';
                if (loadMoreBtn) loadMoreBtn.style.display = isLastPage ? 'none' : 'inline-block';
            }
        }
    };

    const renderMessages = (messages, mode = 'append') => {
        const messagesList = document.getElementById('messages-list');
        const chatWrapper = document.getElementById('chat-scroll-area');
        const loadingSpinner = document.getElementById('messages-loading');
        const noMessages = document.getElementById('no-messages');

        if (loadingSpinner) loadingSpinner.style.display = 'none';

        if (!messages || messages.length === 0) {
            if (mode === 'append' && loadedMessageIds.size === 0) {
                if (noMessages) noMessages.style.display = 'block';
                const container = document.getElementById('messages-container');
                if (container) container.style.display = 'none';
            }
            return;
        }

        if (noMessages) noMessages.style.display = 'none';
        const container = document.getElementById('messages-container');
        if (container) container.style.display = 'block';

        // Scroll management
        const oldScrollHeight = chatWrapper ? chatWrapper.scrollHeight : 0;
        const oldScrollTop = chatWrapper ? chatWrapper.scrollTop : 0;
        const isAtBottom = chatWrapper ? (oldScrollHeight - oldScrollTop <= chatWrapper.clientHeight + 100) : false;

        const fragment = document.createDocumentFragment();

        // Sort messages to be safe (API should handle this but let's be sure)
        const sortedMessages = [...messages].sort((a, b) => new Date(a.created_at) - new Date(b.created_at));

        sortedMessages.forEach(msg => {
            if (loadedMessageIds.has(msg.id)) return;
            loadedMessageIds.add(msg.id);

            // Update first/last IDs
            if (!lastMessageId || msg.id > lastMessageId) lastMessageId = msg.id;
            if (!firstMessageId || msg.id < firstMessageId) firstMessageId = msg.id;

            const bubble = createMessageBubble(msg);
            fragment.appendChild(bubble);
        });

        if (mode === 'prepend') {
            const loadMoreContainer = document.getElementById('load-more-container');
            if (loadMoreContainer && loadMoreContainer.nextSibling) {
                messagesList.insertBefore(fragment, loadMoreContainer.nextSibling);
            } else {
                messagesList.prepend(fragment);
            }
            // Maintain scroll position when prepending
            if (chatWrapper) chatWrapper.scrollTop = chatWrapper.scrollHeight - oldScrollHeight + oldScrollTop;
        } else {
            messagesList.appendChild(fragment);
            // Scroll to bottom if was at bottom or it's the first load
            if (chatWrapper && (isAtBottom || oldScrollHeight === 0)) {
                chatWrapper.scrollTop = chatWrapper.scrollHeight;
            }
        }

        insertDateDividers();
    };

    const createMessageBubble = (message) => {
        const isMe = message.sender_role === 'admin';
        const timestamp = formatDateOnlyTime(message.created_at);
        const div = document.createElement('div');
        div.className = `message-item ${isMe ? 'align-self-end ms-auto' : 'align-self-start me-auto'} mb-4`;
        div.style.maxWidth = '80%';
        div.dataset.id = message.id;
        div.dataset.date = new Date(message.created_at).toDateString();

        const nameClass = isMe ? 'text-primary' : (message.sender_role === 'factory' ? 'text-success' : 'text-info');

        let attachmentHtml = '';
        if (message.attachments && Array.isArray(message.attachments) && message.attachments.length > 0) {
            attachmentHtml = `
                <div class="mt-3 pt-2 border-top ${isMe ? 'border-white-50' : 'border-light'}">
                    <div class="d-flex flex-wrap gap-2 mt-1 justify-content-${isMe ? 'end' : 'start'}">
                        ${message.attachments.map(att => {
                let urlStr = (att.url || '#').replace(/[`\s]/g, '');
                const name = escapeHtml(att.name || 'File');
                const ext = name.split('.').pop().toLowerCase();
                let icon = 'fa-file';
                if (['jpg', 'jpeg', 'png', 'gif'].includes(ext)) icon = 'fa-image';
                else if (ext === 'pdf') icon = 'fa-file-pdf';

                return `
                                <a href="${urlStr}" target="_blank" class="badge ${isMe ? 'bg-white text-primary' : 'bg-light text-dark'} p-2 text-decoration-none d-flex align-items-center gap-1 shadow-sm" style="border-radius: 6px;">
                                    <i class="fas ${icon}"></i>
                                    <span style="max-width: 100px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">${name}</span>
                                </a>
                            `;
            }).join('')}
                    </div>
                </div>
            `;
        }

        const getMessageTypeBadge = (type) => {
            if (!type || type === 'text' || type === 'general') return '';
            let badgeClass = 'bg-secondary';
            let icon = 'fa-tag';
            let label = type.replace('_', ' ');
            switch (type) {
                case 'approval': badgeClass = 'bg-success'; icon = 'fa-check-circle'; break;
                case 'revision_request': badgeClass = 'bg-warning text-dark'; icon = 'fa-exclamation-circle'; break;
                case 'feedback': badgeClass = 'bg-info text-white'; icon = 'fa-comment-dots'; break;
                case 'sample_sent': badgeClass = 'bg-primary'; icon = 'fa-box-open'; break;
            }
            return `<span class="badge ${badgeClass} text-uppercase ms-2" style="font-size: 0.65rem; padding: 0.25em 0.5em;"><i class="fas ${icon} me-1"></i>${label}</span>`;
        };

        const typeBadgeHtml = getMessageTypeBadge(message.message_type);

        div.innerHTML = `
            <div class="d-flex flex-column ${isMe ? 'align-items-end' : 'align-items-start'}">
                <div class="small mb-1 mx-2 d-flex align-items-center flex-wrap gap-1" style="opacity: 0.8;">
                    <span class="fw-bold ${nameClass}">${escapeHtml(message.sender_name)}</span>
                    <span class="text-muted" style="font-size: 0.65rem;">• ${timestamp}</span>
                    ${typeBadgeHtml}
                </div>
                <div class="card ${isMe ? 'bg-primary text-white' : 'bg-white text-dark'} border-0 shadow-sm" style="border-radius: 12px; border-${isMe ? 'top-right' : 'top-left'}-radius: 2px;">
                    <div class="card-body p-2 px-3">
                        <p class="mb-0" style="white-space: pre-wrap; font-size: 0.9rem; line-height: 1.5;">${escapeHtml(message.message)}</p>
                        ${attachmentHtml}
                    </div>
                </div>
            </div>
        `;
        return div;
    };

    const formatDateOnlyTime = (value) => {
        if (!value) return '';
        const date = new Date(value);
        return date.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });
    };

    const insertDateDividers = () => {
        const messagesList = document.getElementById('messages-list');

        // Remove all existing date dividers
        messagesList.querySelectorAll('.date-divider').forEach(el => el.remove());

        let currentDate = null;
        messagesList.querySelectorAll('.message-item').forEach(item => {
            const itemDate = item.dataset.date;
            if (itemDate !== currentDate) {
                currentDate = itemDate;

                const divider = document.createElement('div');
                divider.className = 'date-divider text-center my-4 position-relative';
                divider.dataset.date = itemDate;

                const displayDate = getDisplayDate(itemDate);
                divider.innerHTML = `
                    <div style="height: 1px; background: rgba(0,0,0,0.05); width: 100%;"></div>
                    <span class="position-absolute top-50 start-50 translate-middle bg-white px-3 text-muted small fw-bold text-uppercase" style="letter-spacing: 1px; font-size: 0.6rem;">
                        ${displayDate}
                    </span>
                `;
                messagesList.insertBefore(divider, item);
            }
        });
    };

    const getDisplayDate = (dateStr) => {
        const date = new Date(dateStr);
        const today = new Date().toDateString();
        const yesterday = new Date(Date.now() - 86400000).toDateString();

        if (dateStr === today) return 'Today';
        if (dateStr === yesterday) return 'Yesterday';
        return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
    };

    const startAutoRefresh = () => {
        stopAutoRefresh();
        messageRefreshInterval = setInterval(() => loadMessages(Config.orderNumber, 'auto'), 10000);
    };

    const stopAutoRefresh = () => {
        if (messageRefreshInterval) { clearInterval(messageRefreshInterval); messageRefreshInterval = null; }
    };

    const refreshMessagesBtn = document.getElementById('refresh-messages-btn');
    if (refreshMessagesBtn) {
        refreshMessagesBtn.addEventListener('click', () => {
            loadMessages(Config.orderNumber, 'refresh');
        });
    }

    const loadMoreBtn = document.getElementById('load-more-btn');
    if (loadMoreBtn) {
        loadMoreBtn.addEventListener('click', () => {
            loadMessages(Config.orderNumber, 'more');
        });
    }

    const qrSendBtn = document.getElementById('qr-send-btn');
    if (qrSendBtn) {
        qrSendBtn.addEventListener('click', () => submitMessage('qr-message-content', 'qr-message-type', 'attachments'));
    }

    const submitMessage = async (contentId, typeId, attachmentId = null) => {
        console.log('Submitting message...', { contentId, typeId, attachmentId });
        const contentEl = document.getElementById(contentId);
        const messageContent = contentEl.value.trim();
        const messageType = document.getElementById(typeId).value;
        const attachmentsInput = attachmentId ? document.getElementById(attachmentId) : null;
        const indicator = document.getElementById('sending-indicator');

        if (!messageContent) {
            console.warn('Message content empty');
            return toastr.error('Please enter a message');
        }

        const formData = new FormData();
        formData.append('message', messageContent);
        formData.append('message_type', messageType);
        if (attachmentsInput && attachmentsInput.files.length > 0) {
            for (let i = 0; i < attachmentsInput.files.length; i++) formData.append('attachments[]', attachmentsInput.files[i]);
        }

        try {
            if (qrSendBtn) qrSendBtn.disabled = true;
            if (indicator) indicator.style.display = 'block';
            const response = await fetch(`/api/v1/orders/${Config.orderNumber}/messages`, {
                method: 'POST',
                headers: { 'Authorization': `Bearer ${token}` },
                body: formData
            });
            const res = await response.json();
            if (res.success) {
                contentEl.value = '';
                if (attachmentsInput) attachmentsInput.value = '';
                const attStatus = document.getElementById('quick-att-status');
                if (attStatus) attStatus.style.display = 'none';

                await loadMessages(Config.orderNumber, 'auto');
                startAutoRefresh();
            } else toastr.error(res.message || 'Failed to send message');
        } catch (error) { toastr.error('Error sending message'); }
        finally { if (qrSendBtn) qrSendBtn.disabled = false; if (indicator) indicator.style.display = 'none'; }
    };

    const fileInput = document.getElementById('attachments');
    if (fileInput) {
        fileInput.addEventListener('change', () => {
            const status = document.getElementById('quick-att-status');
            if (fileInput.files.length > 0) {
                status.style.display = 'inline-block';
                status.innerHTML = `<i class="fas fa-check-circle"></i> ${fileInput.files.length} file(s)`;
            } else status.style.display = 'none';
        });
    }

    const startConvoBtn = document.getElementById('start-convo-btn');
    if (startConvoBtn) {
        startConvoBtn.addEventListener('click', () => document.getElementById('qr-message-content').focus());
    }

    window.filterTimelineByShipment = (shipmentId) => {
        activeShipmentId = shipmentId ? String(shipmentId) : null;
        if (currentOrderData) {
            renderShipments(currentOrderData);
            renderRecentShipments(currentOrderData);
            renderTimeline(currentOrderData);

            // Switch to Shipments tab if a filter is applied
            if (shipmentId) {
                const tabEl = document.getElementById('shipments-tab');
                if (tabEl) tabEl.click();
            }
        }
    };

    // Init
    loadOrder();
    startAutoRefresh();
});
