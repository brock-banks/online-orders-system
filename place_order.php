<?php
require_once 'config.php'; // Use require_once
require_once 'functions.php'; // Use require_once

if (!isLoggedIn()) {
    redirect('index.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $details = $_POST['details'];
    $invoiceNo = $_POST['invoice_no'];
    $phone = $_POST['phone'];
    $address = $_POST['address'];
    $price = $_POST['price']; // New field for price
    $selectedUserId = $_POST['selected_user_id']; // Get the selected user ID from the hidden input

    query("INSERT INTO orders (user_id, details, invoice_no, phone, address, price) VALUES (?, ?, ?, ?, ?, ?)", 
        [$selectedUserId, $details, $invoiceNo, $phone, $address, $price]);

    $success = "Order placed successfully!";

    // Prepare the WhatsApp message
    $message = "السلام عليكم ورحمة الله وبركاته 
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
فريق [ALSHAHEEN ONLINE TEAM]";
    $encodedMessage = urlencode($message);

    // Generate the WhatsApp URL
    $whatsappURL = "https://wa.me/$phone/?text=$encodedMessage";

    // Output JavaScript to open WhatsApp in a new tab and show a success message
    echo "<script>
        // Open WhatsApp in a new tab
        window.open('$whatsappURL', '_blank');
        // Display success message
        alert('Order placed successfully and WhatsApp message sent!');
    </script>";
}

// Fetch all users from the users table
$users = query("SELECT id, username FROM users")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Place Order</title>
    <!-- Include Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .user-button {
            transition: background-color 0.3s ease;
        }
        .user-button.selected {
            background-color: #0d6efd !important; /* Highlighted blue color */
            color: white !important;
        }
    </style>
</head>
<body>
<?php include 'header.php'; ?> <!-- Include the header -->
<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card shadow">
                <div class="card-body">
                    <h2 class="card-title text-center text-primary mb-4">Place Order</h2>
                    <?php if (isset($success)): ?>
                        <div class="alert alert-success text-center"><?php echo $success; ?></div>
                    <?php endif; ?>
                    <form method="POST">
                        <div class="mb-3">
                            <label for="details" class="form-label">Order Details</label>
                            <textarea class="form-control" id="details" name="details" rows="3" required></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="invoice_no" class="form-label">Invoice Number</label>
                            <input type="text" class="form-control" id="invoice_no" name="invoice_no" required>
                        </div>
                        <div class="mb-3">
                            <label for="phone" class="form-label">Phone Number</label>
                            <input type="text" class="form-control" id="phone" name="phone" value="+968" required> <!-- Default value set -->
                        </div>
                        <div class="mb-3">
                            <label for="address" class="form-label">Address</label>
                            <textarea class="form-control" id="address" name="address" rows="3" required></textarea>
                        </div>
                        

                        <!-- Buttons to autofill the address -->
                        <div class="mb-3">
                            <button type="button" class="btn btn-outline-primary" onclick="fillAddress('Shop1')">Shop1</button>
                            <button type="button" class="btn btn-outline-primary" onclick="fillAddress('Shop2')">Shop2</button>
                            <button type="button" class="btn btn-outline-primary" onclick="fillAddress('Shop3')">Shop3</button>
                            <button type="button" class="btn btn-outline-primary" onclick="fillAddress('Cargo')">Cargo</button>
                        </div>
                        <div class="mb-3">
                            <label for="price" class="form-label">Price</label> <!-- New field for price -->
                            <input type="number" class="form-control" id="price" name="price" step="0.01" min="0" required>
                        </div>
                        <!-- Buttons to select user -->
                        <div class="mb-3">
                            <label class="form-label">Select User</label>
                            <div>
                                <?php foreach ($users as $user): ?>
                                    <button type="button" class="btn btn-outline-secondary mb-2 user-button" 
                                            onclick="selectUser(this, <?php echo $user['id']; ?>)">
                                        <?php echo htmlspecialchars($user['username']); ?>
                                    </button>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- Hidden input to store selected user ID -->
                        <input type="hidden" id="selected_user_id" name="selected_user_id" value="<?php echo $_SESSION['user']['id']; ?>">

                        <button type="submit" class="btn btn-primary w-100">Place Order</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Include Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // JavaScript function to autofill the address textarea
    function fillAddress(text) {
        document.getElementById('address').value = text;
    }

    // JavaScript function to set the selected user and highlight the button
    function selectUser(button, userId) {
        // Remove 'selected' class from all user buttons
        const buttons = document.querySelectorAll('.user-button');
        buttons.forEach(btn => btn.classList.remove('selected'));

        // Add 'selected' class to the clicked button
        button.classList.add('selected');

        // Set the hidden input value to the selected user ID
        document.getElementById('selected_user_id').value = userId;
    }
</script>
<?php
include 'footer.php';
?>
</body>
</html>