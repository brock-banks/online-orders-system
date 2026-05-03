<?php
include 'header.php';

if (!isLoggedIn()) {
    redirect('index.php');
}

$deliveryPeopleQuery = query("SELECT * FROM delivery_people ORDER BY name ASC");
$deliveryPeople = $deliveryPeopleQuery->fetchAll();

$initialSearchType = $_GET['search_type'] ?? 'invoice_no';
$initialSearchValue = trim($_GET['search_value'] ?? '');

if (!in_array($initialSearchType, ['invoice_no', 'phone', 'address'], true)) {
    $initialSearchType = 'invoice_no';
}
?>
<style>
    .details-cell {
        max-width: 260px;
        white-space: normal;
        word-break: break-word;
    }
</style>
<div class="container page-shell">
    <div class="page-title-row">
        <div>
            <h2 class="page-title text-primary">My Orders</h2>
            <p class="page-subtitle">Search for an order by invoice number, phone, or address.</p>
        </div>
    </div>

    <div class="card app-card section-gap">
        <div class="card-body">
            <div class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label for="searchType" class="form-label">Search By</label>
                    <select id="searchType" class="form-select">
                        <option value="invoice_no" <?php echo $initialSearchType === 'invoice_no' ? 'selected' : ''; ?>>Invoice No</option>
                        <option value="phone" <?php echo $initialSearchType === 'phone' ? 'selected' : ''; ?>>Phone</option>
                        <option value="address" <?php echo $initialSearchType === 'address' ? 'selected' : ''; ?>>Address</option>
                    </select>
                </div>

                <div class="col-md-7">
                    <label for="searchValue" class="form-label">Search Value</label>
                    <input
                        type="text"
                        id="searchValue"
                        class="form-control"
                        placeholder="Enter search value"
                        value="<?php echo htmlspecialchars($initialSearchValue); ?>"
                    >
                </div>

                <div class="col-md-2 d-grid gap-2">
                    <button id="searchBtn" class="btn btn-primary">Search</button>
                    <button id="clearBtn" type="button" class="btn btn-outline-secondary">Clear</button>
                </div>
            </div>
        </div>
    </div>

    <div class="card app-card">
        <div class="card-body">
            <div class="app-table-wrap">
                <div class="table-responsive">
                    <table class="table app-table align-middle">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Invoice</th>
                                <th>Details</th>
                                <th>Phone</th>
                                <th>Address</th>
                                <th>Date</th>
                                <th>Status</th>
                                <th style="min-width: 280px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="ordersTable">
                            <tr>
                                <td colspan="8">
                                    <div class="empty-state text-center my-4">
                                        <div style="font-size:2.5rem;">📦</div>
                                        <div class="empty-state-title fw-bold mt-2">Loading recent orders…</div>
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
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success" id="markDeliveredBtn">Mark as Delivered</button>
                    </div>
                </form>
            </div>
        </div>
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
function loadDefaultOrders() {
    $.ajax({
        url: "fetch_orders.php",
        type: "GET",
        data: { default: 1 },
        success: function(data) {
            $("#ordersTable").html(data);
        },
        error: function() {
            $("#ordersTable").html(`
                <tr><td colspan="8">
                    <div class="empty-state text-center my-4">
                        <div style="font-size:2.5rem;">⚠️</div>
                        <div class="empty-state-title fw-bold mt-2">Failed to load orders</div>
                        <div class="text-muted small">Please refresh the page and try again.</div>
                    </div>
                </td></tr>`);
        }
    });
}

function loadOrders() {
    const searchType = document.getElementById('searchType').value;
    const searchValue = document.getElementById('searchValue').value.trim();

    if (!searchValue) {
        AppUI.showToast('Please enter a search value.', 'warning');
        return;
    }

    const searchBtn = document.getElementById('searchBtn');
    AppUI.setButtonLoading(searchBtn, true, 'Searching...');

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
            AppUI.showToast("Failed to fetch orders. Please try again.", "danger");
        },
        complete: function() {
            AppUI.setButtonLoading(searchBtn, false);
        }
    });
}

document.getElementById('searchBtn').addEventListener('click', loadOrders);

document.getElementById('clearBtn').addEventListener('click', function() {
    document.getElementById('searchType').value = 'invoice_no';
    document.getElementById('searchValue').value = '';
    loadDefaultOrders();
});

document.getElementById('searchValue').addEventListener('keydown', function(e) {
    if (e.key === 'Enter') {
        e.preventDefault();
        loadOrders();
    }
});

document.addEventListener('DOMContentLoaded', function() {
    const initialValue = document.getElementById('searchValue').value.trim();
    if (initialValue !== '') {
        loadOrders();
    } else {
        loadDefaultOrders();
    }
});

$(document).on("click", ".markAsDeliveredBtn", function() {
    const orderId = $(this).data("order-id");
    $("#orderIdInput").val(orderId);
    $("#deliveredBySelect").val('');
    $("#markAsDeliveredModal").modal("show");
});

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