@extends('admin.layouts.app')

@section('title', __('Order Communications'))

@section('content')

    {{-- ================= Page Header ================= --}}
    <div class="page-breadcrumb mb-3">
        <div class="row align-items-center">
            <div class="col-md-6">
                <h4 class="page-title mb-0">{{ __('Order Communications') }}</h4>
                <small class="text-muted">
                    {{ __('Manage all order-related communications between customers and factories') }}
                </small>
            </div>
            <div class="col-md-6 text-end">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb justify-content-end mb-0">
                        <li class="breadcrumb-item">
                            <a href="{{ route('admin.dashboard') }}">{{ __('Home') }}</a>
                        </li>
                        <li class="breadcrumb-item active">{{ __('Communications') }}</li>
                    </ol>
                </nav>
                <button class="btn btn-primary mt-2" data-bs-toggle="modal" data-bs-target="#newMessageModal">
                    <i class="fas fa-plus me-1"></i> {{ __('New Message') }}
                </button>
            </div>
        </div>
    </div>

    {{-- ================= Page Content ================= --}}
    <div class="container-fluid" id="communications-container">

        <div class="row mb-4">
            {{-- Stats Cards --}}
            <div class="col-md-3">
                <div class="card text-center bg-primary text-white">
                    <div class="card-body">
                        <i class="fas fa-comments fa-2x mb-2"></i>
                        <h4 class="mb-0" id="total-messages">0</h4>
                        <small>{{ __('Total Messages') }}</small>
                    </div>
                </div>
            </div>

            <div class="col-md-3">
                <div class="card text-center bg-success text-white">
                    <div class="card-body">
                        <i class="fas fa-users fa-2x mb-2"></i>
                        <h4 class="mb-0" id="total-orders">0</h4>
                        <small>{{ __('Orders with Messages') }}</small>
                    </div>
                </div>
            </div>

            <div class="col-md-3">
                <div class="card text-center bg-info text-white">
                    <div class="card-body">
                        <i class="fas fa-user fa-2x mb-2"></i>
                        <h4 class="mb-0" id="total-customers">0</h4>
                        <small>{{ __('Customers') }}</small>
                    </div>
                </div>
            </div>

            <div class="col-md-3">
                <div class="card text-center bg-warning text-white">
                    <div class="card-body">
                        <i class="fas fa-industry fa-2x mb-2"></i>
                        <h4 class="mb-0" id="total-factories">0</h4>
                        <small>{{ __('Factories') }}</small>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0">{{ __('All Communications') }}</h5>

                <div class="d-flex gap-2">
                    <div class="input-group" style="width: 300px;">
                        <input type="text" class="form-control" id="search-input"
                            placeholder="{{ __('Search messages...') }}">
                        <button class="btn btn-outline-secondary" type="button" id="search-button">
                            <i class="fas fa-search"></i>
                        </button>
                    </div>

                    <select class="form-select" id="filter-sender" style="width: 150px;">
                        <option value="">{{ __('All Roles') }}</option>
                        <option value="customer">{{ __('Customer') }}</option>
                        <option value="factory">{{ __('Factory') }}</option>
                        <option value="admin">{{ __('Admin') }}</option>
                    </select>

                    <select class="form-select" id="filter-type" style="width: 180px;">
                        <option value="">{{ __('All Types') }}</option>
                        <option value="text">{{ __('General') }}</option>
                        <option value="sample_sent">{{ __('Sample Sent') }}</option>
                        <option value="feedback">{{ __('Feedback') }}</option>
                        <option value="revision_request">{{ __('Revision Request') }}</option>
                        <option value="approval">{{ __('Approval') }}</option>
                    </select>

                    <button class="btn btn-outline-secondary" type="button" id="refresh-button">
                        <i class="fas fa-sync-alt"></i>
                    </button>
                </div>
            </div>

            <div class="card-body">
                <div class="text-center py-5" id="loading-spinner">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">{{ __('Loading...') }}</span>
                    </div>
                    <p class="mt-2">{{ __('Loading communications...') }}</p>
                </div>

                <div id="communications-content" style="display: none;">
                    <div class="table-responsive">
                        <table class="table table-hover" id="communications-table">
                            <thead class="table-light">
                                <tr>
                                    <th>{{ __('Order') }}</th>
                                    <th>{{ __('Sender') }}</th>
                                    <th>{{ __('Message') }}</th>
                                    <th>{{ __('Type') }}</th>
                                    <th>{{ __('Files') }}</th>
                                    <th>{{ __('Date') }}</th>
                                    <th>{{ __('Actions') }}</th>
                                </tr>
                            </thead>
                            <tbody id="communications-tbody">
                                <!-- Communications will be loaded here -->
                            </tbody>
                        </table>
                    </div>

                    <nav aria-label="Communications pagination" class="mt-4">
                        <ul class="pagination justify-content-center" id="pagination-container">
                            <!-- Pagination will be loaded here -->
                        </ul>
                    </nav>
                </div>

                <div id="no-communications" class="text-center py-5" style="display: none;">
                    <i class="fas fa-comments fa-3x text-muted mb-3"></i>
                    <h5 class="text-muted">{{ __('No communications found') }}</h5>
                    <p class="text-muted">{{ __('There are no communications to display') }}</p>
                </div>
            </div>
        </div>

    </div>

    <!-- New Message Modal -->
    <div class="modal fade" id="newMessageModal" tabindex="-1" aria-labelledby="newMessageModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form id="newMessageForm" enctype="multipart/form-data">
                    <div class="modal-header">
                        <h5 class="modal-title" id="newMessageModalLabel">{{ __('Send New Message') }}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"
                            aria-label="{{ __('Close') }}"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="order-search" class="form-label">{{ __('Search Order Number') }}</label>
                            <div class="input-group">
                                <input type="text" class="form-control" id="order-search" placeholder="e.g. ORD-123456"
                                    required>
                                <button class="btn btn-outline-secondary" type="button" id="verify-order-btn">
                                    <i class="fas fa-search"></i>
                                </button>
                            </div>
                            <div id="order-verify-result" class="mt-1 small"></div>
                        </div>

                        <div id="hidden-inputs-section" style="display: none;">
                            <input type="hidden" name="order_number" id="form-order-number">

                            <div class="mb-3">
                                <label for="form-message-type" class="form-label">{{ __('Message Type') }}</label>
                                <select class="form-select" name="message_type" id="form-message-type">
                                    <option value="text">{{ __('General') }}</option>
                                    <option value="sample_sent">{{ __('Sample Sent') }}</option>
                                    <option value="feedback">{{ __('Feedback') }}</option>
                                    <option value="revision_request">{{ __('Revision Request') }}</option>
                                    <option value="approval">{{ __('Approval') }}</option>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label for="form-message" class="form-label">{{ __('Message') }}</label>
                                <textarea class="form-control" name="message" id="form-message" rows="4" required
                                    placeholder="{{ __('Type your message here...') }}"></textarea>
                            </div>

                            <div class="mb-3">
                                <label for="form-attachments" class="form-label">{{ __('Attachments') }}
                                    ({{ __('Max 5 files') }})</label>
                                <input type="file" class="form-control" name="attachments[]" id="form-attachments" multiple>
                                <div class="form-text">{{ __('Supported: Images, PDF, Word, Excel') }}</div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                        <button type="submit" class="btn btn-primary" id="submit-message-btn" disabled>
                            <i class="fas fa-paper-plane me-1"></i> {{ __('Send Message') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- View Message Modal -->
    <div class="modal fade" id="viewMessageModal" tabindex="-1" aria-labelledby="viewMessageModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="viewMessageModalLabel">{{ __('View Message') }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h6>{{ __('Order Information') }}</h6>
                            <table class="table table-sm">
                                <tr>
                                    <td><strong>{{ __('Order Number') }}:</strong></td>
                                    <td id="modal-order-number"></td>
                                </tr>
                                <tr>
                                    <td><strong>{{ __('Created At') }}:</strong></td>
                                    <td id="modal-message-date"></td>
                                </tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <h6>{{ __('Sender Information') }}</h6>
                            <table class="table table-sm">
                                <tr>
                                    <td><strong>{{ __('Name') }}:</strong></td>
                                    <td id="modal-sender-name"></td>
                                </tr>
                                <tr>
                                    <td><strong>{{ __('Role') }}:</strong></td>
                                    <td id="modal-sender-role"></td>
                                </tr>
                            </table>
                        </div>
                    </div>

                    <h6>{{ __('Message Content') }}</h6>
                    <div class="alert alert-light">
                        <p id="modal-message-content"></p>
                    </div>

                    <h6>{{ __('Message Type') }}</h6>
                    <span class="badge bg-info" id="modal-message-type"></span>

                    <div id="modal-attachments-section" style="display: none;">
                        <h6 class="mt-3">{{ __('Attachments') }}</h6>
                        <div id="modal-attachments-list"></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('Close') }}</button>
                </div>
            </div>
        </div>
    </div>

@endsection

@section('js')
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const Config = {
                urls: {
                    index: "/api/v1/admin/communications", // API endpoint for communications
                    stats: "/api/v1/admin/communications/stats", // API endpoint for stats
                    search: "/api/v1/admin/communications/search", // API endpoint for search
                    byOrder: "/api/v1/admin/communications/order/:order_number", // API endpoint for order-specific messages
                    orders: "/api/v1/admin/orders", // API endpoint to verify orders
                    sendMessage: "/api/v1/admin/communications" // POST endpoint
                },
                translations: {
                    loading: "{{ __('Loading...') }}",
                    loading_comms: "{{ __('Loading communications...') }}",
                    no_comms_found: "{{ __('No communications found') }}",
                    no_comms_desc: "{{ __('There are no communications to display') }}",
                    unauthorized: "{{ __('Unauthorized') }}",
                    failed_to_load: "{{ __('Failed to load communications') }}",
                    close: "{{ __('Close') }}",
                    view_message: "{{ __('View Message') }}"
                }
            };

            const token = getCookie('jwt_token');

            let currentPage = 1;
            let totalPages = 1;
            let currentFilters = {};

            // DOM Elements
            const els = {
                loadingSpinner: document.getElementById('loading-spinner'),
                communicationsContent: document.getElementById('communications-content'),
                communicationsTbody: document.getElementById('communications-tbody'),
                noCommunications: document.getElementById('no-communications'),
                paginationContainer: document.getElementById('pagination-container'),
                searchInput: document.getElementById('search-input'),
                searchButton: document.getElementById('search-button'),
                filterSender: document.getElementById('filter-sender'),
                filterType: document.getElementById('filter-type'),
                refreshButton: document.getElementById('refresh-button'),
                totalMessages: document.getElementById('total-messages'),
                totalOrders: document.getElementById('total-orders'),
                totalCustomers: document.getElementById('total-customers'),
                totalFactories: document.getElementById('total-factories'),
                newMessageForm: document.getElementById('newMessageForm'),
                orderSearch: document.getElementById('order-search'),
                verifyOrderBtn: document.getElementById('verify-order-btn'),
                orderVerifyResult: document.getElementById('order-verify-result'),
                hiddenInputsSection: document.getElementById('hidden-inputs-section'),
                formOrderNumber: document.getElementById('form-order-number'),
                submitMessageBtn: document.getElementById('submit-message-btn')
            };


            // Event Listeners
            els.searchButton.addEventListener('click', () => {
                currentFilters.query = els.searchInput.value.trim();
                currentPage = 1;
                loadCommunications();
            });

            els.searchInput.addEventListener('keypress', (e) => {
                if (e.key === 'Enter') {
                    els.searchButton.click();
                }
            });

            els.filterSender.addEventListener('change', () => {
                currentFilters.sender_role = els.filterSender.value || undefined;
                currentPage = 1;
                loadCommunications();
            });

            els.filterType.addEventListener('change', () => {
                currentFilters.message_type = els.filterType.value || undefined;
                currentPage = 1;
                loadCommunications();
            });

            els.refreshButton.addEventListener('click', () => {
                currentPage = 1;
                loadCommunications();
                loadStats();
                toastr.info('Refreshing communications...');
            });

            els.verifyOrderBtn.addEventListener('click', async () => {
                const orderNumber = els.orderSearch.value.trim();
                if (!orderNumber) return;

                els.verifyOrderBtn.disabled = true;
                els.orderVerifyResult.innerHTML = `<span class="text-muted"><i class="fas fa-spinner fa-spin"></i> Verifying...</span>`;

                try {
                    const response = await fetch(`${Config.urls.orders}?order_number=${orderNumber}`, {
                        headers: {
                            'Authorization': `Bearer ${token}`,
                            'Accept': 'application/json'
                        }
                    });
                    const res = await response.json();

                    // Handle inconsistent API responses (status vs success, flat vs nested data)
                    const isSuccess = res.success || res.status;
                    const orders = (res.data && res.data.data) ? res.data.data : res.data;

                    if (isSuccess && Array.isArray(orders) && orders.length > 0) {
                        const order = orders[0];
                        els.orderVerifyResult.innerHTML = `<span class="text-success"><i class="fas fa-check-circle"></i> Order Found: #${order.order_number}</span>`;
                        els.formOrderNumber.value = order.order_number;
                        els.hiddenInputsSection.style.display = 'block';
                        els.submitMessageBtn.disabled = false;
                    } else {
                        els.orderVerifyResult.innerHTML = `<span class="text-danger"><i class="fas fa-times-circle"></i> Order not found</span>`;
                        els.hiddenInputsSection.style.display = 'none';
                        els.submitMessageBtn.disabled = true;
                    }
                } catch (error) {
                    els.orderVerifyResult.innerHTML = `<span class="text-danger">Error verifying order</span>`;
                } finally {
                    els.verifyOrderBtn.disabled = false;
                }
            });

            els.newMessageForm.addEventListener('submit', async (e) => {
                e.preventDefault();
                els.submitMessageBtn.disabled = true;
                els.submitMessageBtn.innerHTML = `<i class="fas fa-spinner fa-spin me-1"></i> Sending...`;

                const formData = new FormData(els.newMessageForm);

                try {
                    const response = await fetch(Config.urls.sendMessage, {
                        method: 'POST',
                        headers: {
                            'Authorization': `Bearer ${token}`,
                            'Accept': 'application/json'
                        },
                        body: formData
                    });

                    const res = await response.json();
                    if (res.success) {
                        toastr.success('Message sent successfully');
                        const modalEl = document.getElementById('newMessageModal');
                        const modal = bootstrap.Modal.getInstance(modalEl);
                        if (modal) modal.hide();

                        els.newMessageForm.reset();
                        els.hiddenInputsSection.style.display = 'none';
                        els.orderVerifyResult.innerHTML = '';
                        loadCommunications();
                    } else {
                        toastr.error(res.message || 'Failed to send message');
                    }
                } catch (error) {
                    toastr.error('Error sending message');
                } finally {
                    els.submitMessageBtn.disabled = false;
                    els.submitMessageBtn.innerHTML = `<i class="fas fa-paper-plane me-1"></i> Send Message`;
                }
            });

            // Functions
            const loadStats = async () => {
                try {
                    const response = await fetch(Config.urls.stats, {
                        method: 'GET',
                        headers: {
                            'Accept': 'application/json',
                            'Authorization': `Bearer ${token}`
                        }
                    });

                    if (!response.ok) {
                        throw new Error('Failed to load stats');
                    }

                    const res = await response.json();
                    if (res.success && res.data) {
                        els.totalMessages.textContent = res.data.total_messages;
                        els.totalOrders.textContent = res.data.total_orders || 0;

                        // Count unique from stats
                        let customerCount = 0;
                        let factoryCount = 0;
                        if (res.data.messages_by_role) {
                            res.data.messages_by_role.forEach(role => {
                                if (role.sender_role === 'customer') customerCount = role.count;
                                if (role.sender_role === 'factory') factoryCount = role.count;
                            });
                        }

                        els.totalCustomers.textContent = customerCount;
                        els.totalFactories.textContent = factoryCount;
                    }
                } catch (error) {
                    console.error('Error loading stats:', error);
                }
            };

            const loadCommunications = async (page = 1) => {
                try {
                    showLoading();

                    // Build query params
                    const params = new URLSearchParams({
                        page: page,
                        ...currentFilters
                    });

                    const url = `${Config.urls.index}?${params.toString()}`;

                    const response = await fetch(url, {
                        method: 'GET',
                        headers: {
                            'Accept': 'application/json',
                            'Authorization': `Bearer ${token}`
                        }
                    });

                    if (!response.ok) {
                        if (response.status === 401) {
                            throw new Error(Config.translations.unauthorized);
                        }
                        throw new Error(Config.translations.failed_to_load);
                    }

                    const res = await response.json();
                    if (res.success && res.data) {
                        renderCommunications(res.data);
                    } else {
                        throw new Error('Invalid response format');
                    }
                } catch (error) {
                    console.error('Error loading communications:', error);
                    toastr.error(error.message || Config.translations.failed_to_load);
                    hideLoading();
                }
            };

            const renderCommunications = (data) => {
                hideLoading();

                if (!data.data || data.data.length === 0) {
                    els.communicationsContent.style.display = 'none';
                    els.noCommunications.style.display = 'block';
                    els.paginationContainer.innerHTML = '';
                    return;
                }

                els.noCommunications.style.display = 'none';
                els.communicationsContent.style.display = 'block';

                els.communicationsTbody.innerHTML = data.data.map(comm => {
                    const hasAttachments = comm.attachments && Array.isArray(comm.attachments) && comm
                        .attachments.length > 0;

                    const safeMessage = escapeHtml(comm.message || '');
                    const safeSenderName = escapeHtml(comm.sender_name || 'Unknown');
                    const safeOrderNumber = escapeHtml(comm.order?.order_number || 'N/A');

                    return `
                                    <tr>
                                        <td>
                                            <a href="${comm.order ? `/admin/orders/${comm.order.id}` : '#'}" class="text-decoration-none fw-bold">
                                                ${safeOrderNumber}
                                            </a>
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div>
                                                    <div class="fw-bold text-dark">${safeSenderName}</div>
                                                    <div class="small text-muted font-monospace" style="font-size: 0.75rem;">${escapeHtml(comm.sender_role || 'Unknown').toUpperCase()}</div>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="message-preview text-muted" style="max-width: 250px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="${safeMessage}">
                                                ${safeMessage}
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge ${getMessageTypeClass(comm.message_type)}">${(comm.message_type || 'text').replace('_', ' ').toUpperCase()}</span>
                                        </td>
                                        <td>
                                            ${hasAttachments ?
                            `<span class="badge bg-light text-primary border"><i class="fas fa-paperclip me-1"></i>${comm.attachments.length}</span>` :
                            '-'
                        }
                                        </td>
                                        <td>
                                            <small class="text-muted">${formatDateWithUtc(comm.created_at)}</small>
                                        </td>
                                        <td class="text-nowrap">
                                            <button class="btn btn-sm btn-light border text-primary view-message-btn me-1"
                                                data-comm-id="${comm.id}">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <button class="btn btn-sm btn-primary reply-message-btn"
                                                data-order-number="${safeOrderNumber}">
                                                <i class="fas fa-reply"></i>
                                            </button>
                                        </td>
                                    </tr>
                                `;
                }).join('');

                // Store current messages in a variable for lookup
                window.currentCommunications = data.data;

                // Add event listeners to view buttons
                document.querySelectorAll('.view-message-btn').forEach(btn => {
                    btn.addEventListener('click', function () {
                        const commId = this.getAttribute('data-comm-id');
                        const message = window.currentCommunications.find(c => c.id == commId);
                        if (message) showMessageDetails(message);
                    });
                });

                // Add event listeners to reply buttons
                document.querySelectorAll('.reply-message-btn').forEach(btn => {
                    btn.addEventListener('click', function () {
                        const orderNumber = this.getAttribute('data-order-number');
                        if (orderNumber && orderNumber !== 'N/A') {
                            els.orderSearch.value = orderNumber;
                            // Trigger verification automatically
                            if (!els.verifyOrderBtn.disabled) {
                                els.verifyOrderBtn.click();
                            }
                            const modalEl = document.getElementById('newMessageModal');
                            const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
                            modal.show();
                        }
                    });
                });

                // Render pagination
                renderPagination(data);
            };

            const renderPagination = (data) => {
                totalPages = data.last_page;
                currentPage = data.current_page;

                let paginationHTML = '';

                // Previous button
                paginationHTML += `
                                <li class="page-item ${currentPage <= 1 ? 'disabled' : ''}">
                                    <a class="page-link" href="#" data-page="${currentPage - 1}" aria-label="Previous">
                                        <span aria-hidden="true">&laquo;</span>
                                    </a>
                                </li>
                            `;

                // Pages
                const startPage = Math.max(1, currentPage - 2);
                const endPage = Math.min(totalPages, currentPage + 2);

                for (let i = startPage; i <= endPage; i++) {
                    paginationHTML += `
                                    <li class="page-item ${i === currentPage ? 'active' : ''}">
                                        <a class="page-link" href="#" data-page="${i}">${i}</a>
                                    </li>
                                `;
                }

                // Next button
                paginationHTML += `
                                <li class="page-item ${currentPage >= totalPages ? 'disabled' : ''}">
                                    <a class="page-link" href="#" data-page="${currentPage + 1}" aria-label="Next">
                                        <span aria-hidden="true">&raquo;</span>
                                    </a>
                                </li>
                            `;

                els.paginationContainer.innerHTML = paginationHTML;

                // Add event listeners to pagination links
                document.querySelectorAll('#pagination-container .page-link').forEach(link => {
                    link.addEventListener('click', function (e) {
                        e.preventDefault();
                        const page = parseInt(this.getAttribute('data-page'));
                        if (page >= 1 && page <= totalPages) {
                            loadCommunications(page);
                        }
                    });
                });
            };

            const showMessageDetails = (message) => {
                document.getElementById('modal-order-number').textContent = message.order?.order_number || 'N/A';
                document.getElementById('modal-message-date').textContent = formatDateWithUtc(message.created_at);
                document.getElementById('modal-sender-name').textContent = message.sender_name || 'Unknown';
                document.getElementById('modal-sender-role').textContent = (message.sender_role || 'Unknown').toUpperCase();
                document.getElementById('modal-message-content').textContent = message.message;
                document.getElementById('modal-message-type').textContent = (message.message_type || 'text').replace('_', ' ').toUpperCase();

                // Handle attachments
                const attachmentsSection = document.getElementById('modal-attachments-section');
                const attachmentsList = document.getElementById('modal-attachments-list');

                if (message.attachments && Array.isArray(message.attachments) && message.attachments.length > 0) {
                    attachmentsList.innerHTML = message.attachments.map(att => `
                                    <div class="alert alert-light d-flex justify-content-between align-items-center mb-2">
                                        <div>
                                            <i class="fas fa-paperclip me-2"></i>
                                            <strong>${att.name || 'File'}</strong>
                                            <small class="text-muted d-block">${att.mime_type || 'N/A'} - ${formatFileSize(att.size || 0)}</small>
                                        </div>
                                        ${att.url ?
                            `<a href="${(att.url || '#').replace(/[`\s]/g, '')}" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-primary">
                                                 <i class="fas fa-download"></i> Download
                                             </a>` :
                            '<span class="badge bg-warning">Not available</span>'
                        }
                                    </div>
                                `).join('');
                    attachmentsSection.style.display = 'block';
                } else {
                    attachmentsSection.style.display = 'none';
                }

                // Show modal
                const modalEl = document.getElementById('viewMessageModal');
                const modal = new bootstrap.Modal(modalEl);
                modal.show();
            };

            const getMessageTypeClass = (type) => {
                const classes = {
                    'text': 'bg-secondary',
                    'sample_sent': 'bg-success',
                    'feedback': 'bg-info',
                    'revision_request': 'bg-warning',
                    'approval': 'bg-primary'
                };
                return classes[type] || 'bg-secondary';
            };

            const formatFileSize = (bytes) => {
                const b = parseFloat(bytes);
                if (isNaN(b) || b === 0) return '0 Bytes';
                const k = 1024;
                const sizes = ['Bytes', 'KB', 'MB', 'GB'];
                const i = Math.floor(Math.log(b) / Math.log(k));
                return parseFloat((b / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
            };

            const escapeHtml = (unsafe) => {
                return unsafe
                    ? String(unsafe)
                        .replace(/&/g, "&amp;")
                        .replace(/</g, "&lt;")
                        .replace(/>/g, "&gt;")
                        .replace(/"/g, "&quot;")
                        .replace(/'/g, "&#039;")
                    : '';
            };

            const formatDateWithUtc = (value) => {
                if (!value) return '-';
                const date = new Date(value);
                if (isNaN(date.getTime())) return value;

                return date.toLocaleString('en-US', {
                    year: 'numeric',
                    month: 'short',
                    day: 'numeric',
                    hour: '2-digit',
                    minute: '2-digit'
                });
            };

            const showLoading = () => {
                els.loadingSpinner.style.display = 'block';
                els.communicationsContent.style.display = 'none';
                els.noCommunications.style.display = 'none';
            };

            const hideLoading = () => {
                els.loadingSpinner.style.display = 'none';
                els.communicationsContent.style.display = 'block';
            };

            // Initialize
            loadStats();
            loadCommunications();
        });
    </script>
@endsection