<?php
include 'header.php';

if (!isLoggedIn()) {
    redirect('index.php');
}

ensureAssignedToColumn();

$deliveryPeopleQuery = query("SELECT * FROM delivery_people ORDER BY name ASC");
$deliveryPeople = $deliveryPeopleQuery->fetchAll();

$initialSearchType = $_GET['search_type'] ?? 'invoice_no';
$initialSearchValue = trim($_GET['search_value'] ?? '');

if (!in_array($initialSearchType, ['invoice_no', 'phone', 'address'], true)) {
    $initialSearchType = 'invoice_no';
}
?>
<style>
    /* ---- Page polish: orders.php ---- */
    :root {
        --orders-border: rgba(0, 0, 0, 0.08);
        --orders-muted: #6b7280;
        --orders-surface-2: rgba(86, 143, 197, 0.06);
    }

    .page-shell {
        padding-top: 1.5rem;
        padding-bottom: 3rem;
    }

    .page-title-row {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 1rem;
        margin-bottom: 1.5rem;
    }

    .page-title {
        font-size: 1.75rem;
        font-weight: 700;
        letter-spacing: -0.01em;
        margin: 0;
        line-height: 1.2;
    }

    .page-subtitle {
        color: var(--orders-muted);
        font-size: 0.9375rem;
        margin: 0.35rem 0 0;
    }

    .section-gap { margin-bottom: 1.25rem; }

    .app-card {
        border-radius: 12px;
        border: 1px solid var(--orders-border);
        box-shadow: 0 1px 2px rgba(0, 0, 0, 0.03);
    }

    .app-card > .card-body { padding: 1.25rem; }

    /* ---- Search bar ---- */
    .search-bar .input-group > .form-select,
    .search-bar .input-group > .form-control {
        height: 48px;
    }
    .search-bar .input-group > .form-select {
        flex: 0 0 170px;
        background-color: var(--orders-surface-2);
        border-right: 0;
        font-weight: 500;
        color: var(--text-color);
    }
    .search-bar .input-group > .form-control {
        border-left: 0;
    }
    .search-bar .input-group > .form-control:focus,
    .search-bar .input-group > .form-select:focus {
        box-shadow: none;
        border-color: var(--primary);
        z-index: 3;
    }
    .search-bar .btn-search {
        height: 48px;
        min-width: 120px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
        font-weight: 600;
    }
    .search-bar .btn-search svg { width: 16px; height: 16px; }

    @media (max-width: 575.98px) {
        .search-bar .input-group { flex-wrap: wrap; }
        .search-bar .input-group > .form-select {
            flex: 1 1 100%;
            border-right: 1px solid var(--orders-border);
            border-radius: 0.5rem !important;
            margin-bottom: 0.5rem;
        }
        .search-bar .input-group > .form-control {
            flex: 1 1 auto;
            border-left: 1px solid var(--orders-border);
            border-radius: 0.5rem 0 0 0.5rem !important;
        }
        .search-bar .btn-search {
            border-radius: 0 0.5rem 0.5rem 0 !important;
        }
    }

    /* ---- Table ---- */
    .app-table { margin-bottom: 0; }
    .app-table thead th {
        font-size: 0.72rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: var(--orders-muted);
        font-weight: 600;
        border-bottom: 1px solid var(--orders-border);
        background: transparent;
        padding: 0.85rem 0.75rem;
        white-space: nowrap;
    }
    .app-table tbody td {
        border-color: var(--orders-border);
        vertical-align: middle;
        padding: 0.85rem 0.75rem;
    }
    .app-table tbody tr {
        transition: background-color 0.12s ease;
    }
    .app-table tbody tr:hover {
        background: var(--orders-surface-2);
    }

    .details-cell {
        max-width: 260px;
        white-space: normal;
        word-break: break-word;
        line-height: 1.45;
    }

    /* ---- Status badges ---- */
    .badge-status {
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
        padding: 0.3rem 0.65rem;
        border-radius: 999px;
        font-size: 0.72rem;
        font-weight: 600;
        line-height: 1;
        text-transform: uppercase;
        letter-spacing: 0.04em;
    }
    .badge-status svg {
        width: 14px;
        height: 14px;
        flex: 0 0 14px;
    }
    .badge-delivered { background: rgba(25, 135, 84, 0.12); color: #146c43; }
    .badge-pending   { background: rgba(217, 119, 6, 0.14); color: #92400e; }

    /* ---- Action group ---- */
    .table-actions {
        display: flex;
        flex-wrap: wrap;
        gap: 0.4rem;
        align-items: center;
    }
    .table-actions .btn { white-space: nowrap; font-weight: 500; }

    /* ---- Empty state ---- */
    .empty-state { padding: 2.5rem 1rem; }
    .empty-state-icon {
        width: 56px;
        height: 56px;
        border-radius: 50%;
        background: var(--orders-surface-2);
        color: var(--primary);
        display: inline-flex;
        align-items: center;
        justify-content: center;
        margin-bottom: 0.85rem;
    }
    .empty-state-icon svg { width: 26px; height: 26px; }
    .empty-state-title {
        font-weight: 600;
        color: var(--text-color);
        font-size: 1rem;
    }
    .empty-state-text {
        color: var(--orders-muted);
        font-size: 0.875rem;
        margin-top: 0.25rem;
    }

    /* ---- Modals ---- */
    .modal-content {
        border-radius: 14px;
        border: 1px solid var(--orders-border);
    }
    .modal-header, .modal-footer {
        border-color: var(--orders-border);
    }
    .modal-footer { gap: 0.5rem; }

    @media (max-width: 575.98px) {
        .page-title { font-size: 1.4rem; }
        .table-actions .btn { font-size: 0.8125rem; }
    }

    /* ---- Assignment indicator ---- */
    .assigned-line {
        display: inline-flex;
        align-items: center;
        gap: 0.3rem;
        font-size: 0.75rem;
        line-height: 1.1;
    }
    .assigned-line svg { width: 12px; height: 12px; flex: 0 0 12px; opacity: 0.75; }
    .assigned-line.assigned-empty { opacity: 0.7; font-style: italic; }

    /* ---- Bulk select ---- */
    .select-col, .select-cell {
        width: 36px;
        text-align: center;
        padding-right: 0 !important;
        padding-left: 0.75rem !important;
    }
    .row-select { cursor: pointer; }
    .row-select:disabled { cursor: not-allowed; opacity: 0.35; }

    .bulk-bar {
        position: fixed;
        left: 50%;
        bottom: 1.5rem;
        transform: translate(-50%, 200%);
        background: var(--text-color, #1f2937);
        color: #fff;
        padding: 0.65rem 1rem 0.65rem 1.25rem;
        border-radius: 999px;
        box-shadow: 0 10px 30px rgba(0,0,0,0.22), 0 2px 8px rgba(0,0,0,0.12);
        display: flex;
        align-items: center;
        gap: 0.85rem;
        z-index: 1050;
        transition: transform 0.22s ease;
        max-width: calc(100vw - 2rem);
    }
    .bulk-bar.visible { transform: translate(-50%, 0); }
    .bulk-bar .bulk-count {
        font-weight: 600;
        font-size: 0.9rem;
        white-space: nowrap;
    }
    .bulk-bar .form-check { color: #fff; margin: 0; }
    .bulk-bar .form-check-label { font-size: 0.85rem; user-select: none; }
    .bulk-bar .btn { white-space: nowrap; font-weight: 600; }
    .bulk-bar .btn-link {
        color: #fff;
        opacity: 0.7;
        text-decoration: none;
        padding: 0.2rem 0.4rem;
    }
    .bulk-bar .btn-link:hover { opacity: 1; }
</style>
<div class="container page-shell">
    <div class="page-title-row">
        <div>
            <h2 class="page-title text-primary">My Orders</h2>
            <p class="page-subtitle">Find an order by invoice number, phone, or address.</p>
        </div>
    </div>

    <div class="card app-card section-gap search-bar">
        <div class="card-body">
            <form id="searchForm">
                <div class="input-group">
                    <select id="searchType" class="form-select" aria-label="Search by">
                        <option value="invoice_no" <?php echo $initialSearchType === 'invoice_no' ? 'selected' : ''; ?>>Invoice No</option>
                        <option value="phone" <?php echo $initialSearchType === 'phone' ? 'selected' : ''; ?>>Phone</option>
                        <option value="address" <?php echo $initialSearchType === 'address' ? 'selected' : ''; ?>>Address</option>
                    </select>
                    <input
                        type="text"
                        id="searchValue"
                        class="form-control"
                        placeholder="Enter invoice number"
                        autocomplete="off"
                        autofocus
                        value="<?php echo htmlspecialchars($initialSearchValue); ?>"
                        aria-label="Search value"
                    >
                    <button id="searchBtn" type="submit" class="btn btn-primary btn-search">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <circle cx="11" cy="11" r="7"/>
                            <line x1="21" y1="21" x2="16.65" y2="16.65"/>
                        </svg>
                        <span>Search</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="card app-card">
        <div class="card-body">
            <div class="app-table-wrap">
                <div class="table-responsive">
                    <table class="table app-table align-middle">
                        <thead>
                            <tr>
                                <th class="select-col">
                                    <input type="checkbox" id="selectAll" class="form-check-input" aria-label="Select all pending assigned orders">
                                </th>
                                <th>ID</th>
                                <th>Invoice</th>
                                <th>Details</th>
                                <th>Phone</th>
                                <th>Address</th>
                                <th>Date</th>
                                <th>Status</th>
                                <th style="min-width: 260px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="ordersTable">
                            <tr>
                                <td colspan="9">
                                    <div class="empty-state text-center">
                                        <div class="empty-state-icon">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                                <circle cx="11" cy="11" r="7"/>
                                                <line x1="21" y1="21" x2="16.65" y2="16.65"/>
                                            </svg>
                                        </div>
                                        <div class="empty-state-title">Search to view orders</div>
                                        <div class="empty-state-text">Pick a search type, enter a value, then press Search.</div>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Order Modal -->
    <div class="modal fade" id="editOrderModal" tabindex="-1" aria-labelledby="editOrderModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form id="editOrderForm" method="POST" action="update_order.php">
                    <div class="modal-header">
                        <h5 class="modal-title" id="editOrderModalLabel">Edit Order</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>

                    <div class="modal-body">
                        <input type="hidden" name="order_id" id="editOrderId">

                        <div class="mb-3">
                            <label for="editInvoiceNo" class="form-label">Invoice No</label>
                            <input type="text" class="form-control" id="editInvoiceNo" name="invoice_no" readonly>
                        </div>

                        <div class="mb-3">
                            <label for="editDetails" class="form-label">Details</label>
                            <textarea class="form-control" id="editDetails" name="details" rows="3" required></textarea>
                        </div>

                        <div class="mb-3">
                            <label for="editPhone" class="form-label">Phone</label>
                            <input type="text" class="form-control" id="editPhone" name="phone" required>
                        </div>

                        <div class="mb-3">
                            <label for="editAddress" class="form-label">Address</label>
                            <textarea class="form-control" id="editAddress" name="address" rows="2" required></textarea>
                        </div>
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary" id="saveOrderBtn">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Mark as Delivered Modal -->
    <div class="modal fade" id="markAsDeliveredModal" tabindex="-1" aria-labelledby="markAsDeliveredModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form id="markAsDeliveredForm" method="POST" action="mark_as_delivered.php">
                    <div class="modal-header">
                        <h5 class="modal-title" id="markAsDeliveredModalLabel">Mark as Delivered</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>

                    <div class="modal-body">
                        <input type="hidden" name="order_id" id="orderIdInput">

                        <div class="mb-3">
                            <label for="deliveredBySelect" class="form-label">Delivered By</label>
                            <select name="delivered_by" id="deliveredBySelect" class="form-select" required>
                                <option value="" selected disabled>Select Delivery Person</option>
                                <?php foreach ($deliveryPeople as $person): ?>
                                    <option value="<?php echo htmlspecialchars($person['name']); ?>">
                                        <?php echo htmlspecialchars($person['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-1">
                            <label class="form-label">WhatsApp Message</label>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="template_key" id="tplDelivered" value="template_delivered" checked>
                                <label class="form-check-label" for="tplDelivered">Delivered notification</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="template_key" id="tplPlaceOrder" value="template_place_order">
                                <label class="form-check-label" for="tplPlaceOrder">Order confirmation</label>
                            </div>
                            <div class="form-text">Order is marked delivered either way. <a href="settings.php#templates-pane" target="_blank">Edit templates</a>.</div>
                        </div>
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success" id="markDeliveredBtn">Mark Delivered &amp; Send</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Sticky bulk action bar -->
    <div id="bulkBar" class="bulk-bar" role="region" aria-label="Bulk actions" aria-hidden="true">
        <span class="bulk-count"><span id="bulkCount">0</span> selected</span>
        <div class="form-check">
            <input class="form-check-input" type="checkbox" id="bulkSendWhatsapp">
            <label class="form-check-label" for="bulkSendWhatsapp">Also open WhatsApp</label>
        </div>
        <button type="button" id="bulkMarkBtn" class="btn btn-success btn-sm">Mark delivered</button>
        <button type="button" id="bulkClearBtn" class="btn btn-link btn-sm" aria-label="Clear selection">Clear</button>
    </div>

    <!-- Success Modal -->
    <div class="modal fade" id="successModal" tabindex="-1" aria-labelledby="successModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title text-success" id="successModalLabel">Success</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p id="successMessage" class="mb-0"></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<script>
const PLACEHOLDERS = {
    invoice_no: 'Enter invoice number',
    phone: 'Enter phone number',
    address: 'Enter street, area, or city'
};

const UI = {
    showToast(msg, type) {
        if (window.AppUI && typeof window.AppUI.showToast === 'function') {
            window.AppUI.showToast(msg, type);
        } else {
            alert(msg);
        }
    },
    setButtonLoading(btn, loading, loadingText) {
        if (window.AppUI && typeof window.AppUI.setButtonLoading === 'function') {
            window.AppUI.setButtonLoading(btn, loading, loadingText);
            return;
        }
        if (!btn) return;
        if (loading) {
            btn.dataset.originalText = btn.innerHTML;
            btn.disabled = true;
            if (loadingText) btn.innerHTML = loadingText;
        } else {
            btn.disabled = false;
            if (btn.dataset.originalText) {
                btn.innerHTML = btn.dataset.originalText;
                delete btn.dataset.originalText;
            }
        }
    }
};

function updatePlaceholder() {
    const type = document.getElementById('searchType').value;
    document.getElementById('searchValue').placeholder = PLACEHOLDERS[type] || 'Enter search value';
}

function loadOrders() {
    const searchType = document.getElementById('searchType').value;
    const searchValue = document.getElementById('searchValue').value.trim();

    if (!searchValue) {
        UI.showToast('Please enter a search value.', 'warning');
        document.getElementById('searchValue').focus();
        return;
    }

    const searchBtn = document.getElementById('searchBtn');
    UI.setButtonLoading(searchBtn, true, 'Searching...');

    $.ajax({
        url: "fetch_orders.php",
        type: "GET",
        data: {
            search_type: searchType,
            search_value: searchValue
        },
        success: function(data) {
            $("#ordersTable").html(data);
        },
        error: function() {
            UI.showToast("Failed to fetch orders. Please try again.", "danger");
        },
        complete: function() {
            UI.setButtonLoading(searchBtn, false);
        }
    });
}

document.getElementById('searchForm').addEventListener('submit', function(e) {
    e.preventDefault();
    loadOrders();
});

document.getElementById('searchType').addEventListener('change', updatePlaceholder);

document.addEventListener('DOMContentLoaded', function() {
    updatePlaceholder();
    const initialValue = document.getElementById('searchValue').value.trim();
    if (initialValue !== '') {
        loadOrders();
    }
});

$(document).on("click", ".markAsDeliveredBtn", function() {
    const orderId = $(this).data("order-id");
    $("#orderIdInput").val(orderId);
    $("#deliveredBySelect").val('');
    $("#tplDelivered").prop("checked", true);
    $("#markAsDeliveredModal").modal("show");
});

// One-click delivery for assigned orders
$(document).on("click", ".quickDeliverBtn", function() {
    const btn = $(this);
    const orderId = btn.data("order-id");
    const assignedTo = btn.data("assigned-to");
    if (!confirm(`Mark this order delivered by ${assignedTo}?`)) return;

    UI.setButtonLoading(btn[0], true, 'Marking…');
    $.ajax({
        url: "mark_as_delivered.php",
        type: "POST",
        data: { order_id: orderId, use_assignment: 1, template_key: 'template_delivered' },
        headers: { "X-Requested-With": "XMLHttpRequest", "Accept": "application/json" },
        success: function(response) {
            let result = response;
            if (typeof response === "string") { try { result = JSON.parse(response); } catch (e) {} }
            if (result && result.success) {
                UI.showToast(result.message || 'Marked delivered.', "success");
                if (confirm('Open WhatsApp to send the delivered message now?') && result.whatsapp_url) {
                    window.open(result.whatsapp_url, "_blank");
                }
                const search = document.getElementById("searchValue").value.trim();
                if (search !== "") loadOrders();
            } else {
                UI.showToast((result && result.message) || "Failed to mark delivered.", "danger");
            }
        },
        error: function(xhr) {
            let msg = "Failed to mark delivered.";
            try { const r = JSON.parse(xhr.responseText); if (r.message) msg = r.message; } catch (e) {}
            UI.showToast(msg, "danger");
        },
        complete: function() {
            UI.setButtonLoading(btn[0], false);
        }
    });
});

// Assign / reassign / clear assignment from the row dropdown
$(document).on("click", ".assignOrderItem", function(e) {
    e.preventDefault();
    const item = $(this);
    const orderId = item.data("order-id");
    const assignTo = item.data("assign-to") || '';
    $.ajax({
        url: "assign_order.php",
        type: "POST",
        data: { order_id: orderId, assigned_to: assignTo },
        success: function(response) {
            let result = response;
            if (typeof response === "string") { try { result = JSON.parse(response); } catch (e) {} }
            if (result && result.success) {
                UI.showToast(result.message || 'Assignment updated.', "success");
                const search = document.getElementById("searchValue").value.trim();
                if (search !== "") loadOrders();
            } else {
                UI.showToast((result && result.message) || "Failed to update assignment.", "danger");
            }
        },
        error: function(xhr) {
            let msg = "Failed to update assignment.";
            try { const r = JSON.parse(xhr.responseText); if (r.message) msg = r.message; } catch (e) {}
            UI.showToast(msg, "danger");
        }
    });
});

// ---- Bulk select handling ----
function getSelectedRowIds() {
    return $('#ordersTable .row-select:checked').map(function() { return this.value; }).get();
}

function refreshBulkBar() {
    const ids = getSelectedRowIds();
    const bar = document.getElementById('bulkBar');
    const count = document.getElementById('bulkCount');
    count.textContent = ids.length;
    if (ids.length > 0) {
        bar.classList.add('visible');
        bar.setAttribute('aria-hidden', 'false');
    } else {
        bar.classList.remove('visible');
        bar.setAttribute('aria-hidden', 'true');
    }
    // Sync header "select all" indeterminate state
    const all = $('#ordersTable .row-select:not(:disabled)');
    const selectAll = document.getElementById('selectAll');
    if (selectAll) {
        if (all.length === 0) {
            selectAll.checked = false;
            selectAll.indeterminate = false;
        } else if (ids.length === all.length) {
            selectAll.checked = true;
            selectAll.indeterminate = false;
        } else if (ids.length === 0) {
            selectAll.checked = false;
            selectAll.indeterminate = false;
        } else {
            selectAll.checked = false;
            selectAll.indeterminate = true;
        }
    }
}

$(document).on('change', '.row-select', refreshBulkBar);

$(document).on('change', '#selectAll', function() {
    const check = this.checked;
    $('#ordersTable .row-select:not(:disabled)').prop('checked', check);
    refreshBulkBar();
});

document.getElementById('bulkClearBtn').addEventListener('click', function() {
    $('#ordersTable .row-select').prop('checked', false);
    refreshBulkBar();
});

document.getElementById('bulkMarkBtn').addEventListener('click', function() {
    const ids = getSelectedRowIds();
    if (ids.length === 0) return;
    const sendMsgs = document.getElementById('bulkSendWhatsapp').checked;
    const msg = sendMsgs
        ? `Mark ${ids.length} orders delivered AND open WhatsApp for each? This will open ${ids.length} browser tabs.`
        : `Mark ${ids.length} orders delivered?`;
    if (!confirm(msg)) return;

    const btn = this;
    UI.setButtonLoading(btn, true, 'Working…');
    $.ajax({
        url: 'bulk_mark_delivered.php',
        type: 'POST',
        traditional: true,
        data: {
            'order_ids[]': ids,
            send_messages: sendMsgs ? 1 : 0,
            template_key: 'template_delivered'
        },
        success: function(response) {
            let result = response;
            if (typeof response === 'string') { try { result = JSON.parse(response); } catch (e) {} }
            if (!result || !result.success) {
                UI.showToast((result && result.message) || 'Bulk action failed.', 'danger');
                return;
            }
            UI.showToast(result.message || 'Done.', 'success');
            if (sendMsgs && Array.isArray(result.processed)) {
                result.processed.forEach(function(p, i) {
                    if (p.whatsapp_url) {
                        setTimeout(function() { window.open(p.whatsapp_url, '_blank'); }, i * 250);
                    }
                });
            }
            if (Array.isArray(result.skipped) && result.skipped.length > 0) {
                const lines = result.skipped.map(s => `• #${s.invoice_no || s.id}: ${s.reason}`).join('\n');
                setTimeout(function() {
                    alert(`Skipped ${result.skipped.length} order(s):\n\n${lines}`);
                }, 100);
            }
            const search = document.getElementById('searchValue').value.trim();
            if (search !== '') loadOrders();
            $('#ordersTable .row-select').prop('checked', false);
            refreshBulkBar();
        },
        error: function(xhr) {
            let msg = 'Bulk action failed.';
            try { const r = JSON.parse(xhr.responseText); if (r.message) msg = r.message; } catch (e) {}
            UI.showToast(msg, 'danger');
        },
        complete: function() {
            UI.setButtonLoading(btn, false);
        }
    });
});

// Re-sync bulk bar when search results re-render
const ordersTableEl = document.getElementById('ordersTable');
if (ordersTableEl && window.MutationObserver) {
    new MutationObserver(refreshBulkBar).observe(ordersTableEl, { childList: true });
}

$(document).on("click", ".editOrderBtn", function() {
    const orderId = $(this).data("order-id");

    $.ajax({
        url: "fetch_order_details.php",
        type: "GET",
        dataType: "json",
        data: { id: orderId },
        success: function(order) {
            if (order.error) {
                AppUI.showToast(order.error, "danger");
                return;
            }

            $("#editOrderId").val(order.id);
            $("#editInvoiceNo").val(order.invoice_no);
            $("#editDetails").val(order.details);
            $("#editPhone").val(order.phone);
            $("#editAddress").val(order.address);

            $("#editOrderModal").modal("show");
        },
        error: function(xhr) {
            let msg = "Failed to fetch order details. Please try again.";
            try {
                const res = JSON.parse(xhr.responseText);
                if (res.error) msg = res.error;
            } catch (e) {}
            AppUI.showToast(msg, "danger");
        }
    });
});

$("#editOrderForm").on("submit", function(event) {
    event.preventDefault();

    const saveBtn = document.getElementById("saveOrderBtn");
    AppUI.setButtonLoading(saveBtn, true, "Saving...");

    $.ajax({
        url: "update_order.php",
        type: "POST",
        data: $(this).serialize(),
        success: function(response) {
            let result = response;
            if (typeof response === "string") {
                result = JSON.parse(response);
            }

            if (result.success) {
                $("#editOrderModal").modal("hide");
                $("#successMessage").text(result.message || 'Order updated successfully.');
                $("#successModal").modal("show");
                AppUI.showToast(result.message || 'Order updated successfully.', "success");

                const currentSearch = document.getElementById("searchValue").value.trim();
                if (currentSearch !== "") {
                    loadOrders();
                }
            } else {
                AppUI.showToast(result.message || "Failed to update order.", "danger");
            }
        },
        error: function(xhr) {
            let msg = "Failed to update order. Please try again.";
            try {
                const res = JSON.parse(xhr.responseText);
                if (res.message) msg = res.message;
            } catch (e) {}
            AppUI.showToast(msg, "danger");
        },
        complete: function() {
            AppUI.setButtonLoading(saveBtn, false);
        }
    });
});

$("#markAsDeliveredForm").on("submit", function(event) {
    event.preventDefault();

    const btn = document.getElementById("markDeliveredBtn");
    AppUI.setButtonLoading(btn, true, "Updating...");

    $.ajax({
        url: "mark_as_delivered.php",
        type: "POST",
        data: $(this).serialize(),
        headers: {
            "X-Requested-With": "XMLHttpRequest",
            "Accept": "application/json"
        },
        success: function(response) {
            let result = response;
            if (typeof response === "string") {
                result = JSON.parse(response);
            }

            if (result.success) {
                $("#markAsDeliveredModal").modal("hide");
                AppUI.showToast(result.message || "Order marked as delivered successfully.", "success");

                if (result.whatsapp_url) {
                    window.open(result.whatsapp_url, "_blank");
                }

                const currentSearch = document.getElementById("searchValue").value.trim();
                if (currentSearch !== "") {
                    loadOrders();
                }
            } else {
                AppUI.showToast(result.message || "Failed to update order.", "danger");
            }
        },
        error: function(xhr) {
            let msg = "Failed to mark order as delivered.";
            try {
                const res = JSON.parse(xhr.responseText);
                if (res.message) msg = res.message;
            } catch (e) {}
            AppUI.showToast(msg, "danger");
        },
        complete: function() {
            AppUI.setButtonLoading(btn, false);
        }
    });
});
</script>

<?php include 'footer.php'; ?>
</body>
</html>