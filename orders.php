<?php
include 'header.php'; // Include header for navigation and session checks

// Ensure the user is logged in and is not an admin
if (!isLoggedIn()) {
    redirect('index.php');
}

// Fetch delivery people
$deliveryPeopleQuery = query("SELECT * FROM delivery_people");
$deliveryPeople = $deliveryPeopleQuery->fetchAll(); // Fetch all delivery people
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Orders</title>
    <!-- Include Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Include jQuery for AJAX -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body>
<div class="container mt-5">
    <h2 class="text-center text-primary mb-4">My Orders</h2>
    <div class="card shadow">
        <div class="card-body">
            <!-- Search Bar -->
            <div class="mb-3">
                <input type="text" id="searchOrders" class="form-control" placeholder="Search orders by Invoice No, Details, Phone, Address, or Status">
            </div>
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead class="table-primary">
                        <tr>
                        <th>ID</th>
                            <th>User ID</th>
                            <th>Invoice No</th>
                            <th>Details</th>
                            <th>Phone</th>
                            <th>Address</th>
                            <th>Date</th>
                            <th>Delivered By</th>
                            <th>Status</th>
                            <th>Edit</th>
                            <th>Mark as Delivered</th>
                            <th>Action</th> <!-- New column for WhatsApp action -->
                        </tr>
                    </thead>
                    <tbody id="ordersTable">
                    <?php
                        // Fetch orders for the logged-in user
                        $userId = $_SESSION['user']['id'];
                        $query = query("SELECT * FROM orders WHERE user_id = ?", [$userId]);
                        $orders = $query->fetchAll();

                        if (count($orders) > 0):
                            foreach ($orders as $order): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($order['id']); ?></td>
                                    <td><?php echo htmlspecialchars($order['user_id']); ?></td>
                                    <td><?php echo htmlspecialchars($order['invoice_no']); ?></td>
                                    <td><?php echo htmlspecialchars($order['details']); ?></td>
                                    <td><?php echo htmlspecialchars($order['phone']); ?></td>
                                    <td><?php echo htmlspecialchars($order['address']); ?></td>
                                    <td><?php echo htmlspecialchars($order['date']); ?></td>
                                    <td><?php echo htmlspecialchars($order['delivered_by']); ?></td>
                                    <td><?php echo ucfirst(htmlspecialchars($order['status'])); ?></td>
                                    <td>
                                        <button class="btn btn-secondary btn-sm editOrderBtn" 
                                                data-order-id="<?php echo $order['id']; ?>" 
                                                data-invoice-no="<?php echo htmlspecialchars($order['invoice_no']); ?>"
                                                data-details="<?php echo htmlspecialchars($order['details']); ?>"
                                                data-phone="<?php echo htmlspecialchars($order['phone']); ?>"
                                                data-address="<?php echo htmlspecialchars($order['address']); ?>"
                                                data-date="<?php echo htmlspecialchars($order['date']); ?>"
                                                data-delivered-by="<?php echo htmlspecialchars($order['delivered_by']); ?>">Edit</button>
                                    </td>
                                    <td>
                                        <?php if ($order['status'] !== 'delivered'): ?>
                                            <button class="btn btn-success btn-sm markAsDeliveredBtn" data-order-id="<?php echo $order['id']; ?>">Mark as Delivered</button>
                                        <?php else: ?>
                                            <span class="badge bg-success">Delivered</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                <a 
                    href="https://wa.me/<?php echo htmlspecialchars($order['phone']); ?>?text=<?php echo urlencode("السلام عليكم ورحمة الله وبركاته 
شكرًا لطلبك من [متجر الشاهين للوازم الرحلات والتخييم]!
رقم طلبك هو: # $invoiceNo
يرجى الاحتفاظ بهذا الرقم لإستلام
مكان الاستلام $address
تفاصيل الطلب
$details
لمزيد من المعلومات أو المتابعة، يمكنك مراسلتنا على 
72202722
93211636
تحياتنا،
فريق [ALSHAHEEN ONLINE TEAM]"); ?>" 
                    target="_blank" 
                    class="btn btn-success btn-sm">
                    Send WhatsApp
                </a>
            </td>
                                </tr>
                            <?php endforeach; 
                        else: ?>
                            <tr>
                                <td colspan="11" class="text-center">No orders found.</td>
                            </tr>
                           
                        <?php endif; ?>
                        
                    </tbody>
                </table>
            </div>
        </div>
    </div>

     <!-- Modal for Editing Order -->
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
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal for Mark as Delivered -->
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
                        <button type="submit" class="btn btn-primary">Mark as Delivered</button>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
</div>

<script>
    // Function to load orders dynamically
    function loadOrders(search = "") {
        $.ajax({
            url: "fetch_orders.php",
            type: "GET",
            data: { search: search },
            success: function(data) {
                $("#ordersTable").html(data); // Populate the table with fetched data
            },
            error: function() {
                alert("Failed to fetch orders. Please try again.");
            }
        });
    }

    // Load all orders initially
    loadOrders();

    // Add event listener for live search
    $("#searchOrders").on("input", function() {
        const searchValue = $(this).val();
        loadOrders(searchValue); // Fetch orders based on search input
    });

    // Show modal when clicking the Mark as Delivered button
    $(document).on("click", ".markAsDeliveredBtn", function(event) {
        event.stopPropagation(); // Prevent interference from other event listeners
        const orderId = $(this).data("order-id");
        $("#orderIdInput").val(orderId); // Set the order ID in the modal form
        $("#markAsDeliveredModal").modal("show");
    });

   // Show modal when clicking the Edit button and fetch order details dynamically
   $(document).on("click", ".editOrderBtn", function() {
        const orderId = $(this).data("order-id");

        // Send an AJAX request to fetch the order details
        $.ajax({
            url: "fetch_order_details.php", // Backend script to fetch order details
            type: "GET",
            data: { id: orderId }, // Pass the order ID
            success: function(response) {
                const order = JSON.parse(response);

                if (order.error) {
                    alert(order.error);
                    return;
                }

                // Populate the modal fields with the fetched data
                $("#editOrderId").val(order.id);
                $("#editInvoiceNo").val(order.invoice_no);
                $("#editDetails").val(order.details);
                $("#editPhone").val(order.phone);
                $("#editAddress").val(order.address);

                // Show the modal
                $("#editOrderModal").modal("show");
            },
            error: function() {
                alert("Failed to fetch order details. Please try again.");
            }
        });
    });

    // Handle the form submission for updating the order
    $("#editOrderForm").on("submit", function(event) {
        event.preventDefault(); // Prevent the default form submission

        const formData = $(this).serialize(); // Serialize the form data

        // Send an AJAX request to update the order
        $.ajax({
            url: "update_order.php", // Backend script to handle form submission
            type: "POST",
            data: formData,
            success: function(response) {
                const result = JSON.parse(response);

                if (result.success) {
                    // Close the edit modal
                    $("#editOrderModal").modal("hide");

                    // Show the success modal
                    $("#successMessage").text(result.message); // Update the success message
                    $("#successModal").modal("show");

                    // Reload the orders table
                    loadOrders();
                } else {
                    alert(result.message); // Show an error message if the update fails
                }
            },
            error: function() {
                alert("Failed to update order. Please try again.");
            }
        });
    });
</script>

<!-- Add a Success Modal -->
<div class="modal fade" id="successModal" tabindex="-1" aria-labelledby="successModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title text-success" id="successModalLabel">Success</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p id="successMessage"></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
</script>

<!-- Include Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
<?php
include 'footer.php';
?>
</body>
</html>