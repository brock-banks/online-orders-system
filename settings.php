<?php
require 'config.php';
require 'functions.php';

if (!isLoggedIn() || !isAdmin()) {
    redirect('index.php');
}

// Fetch the current theme from the database
$currentTheme = query("SELECT value FROM settings WHERE key_name = 'theme'")->fetch()['value'] ?? 'light';

// Handle form submission to update the theme
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['theme'])) {
    $selectedTheme = $_POST['theme'];
    $updateTheme = query("UPDATE settings SET value = ? WHERE key_name = 'theme'", [$selectedTheme]);

    if ($updateTheme) {
        $success = "Theme updated successfully!";
        $currentTheme = $selectedTheme; // Update the current theme for displaying
    } else {
        $error = "Failed to update theme. Please try again.";
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
        // Handle logo upload
        $uploadDir = 'uploads/'; // Directory to store uploaded files
        $uploadFile = $uploadDir . basename($_FILES['logo']['name']);
        $fileType = strtolower(pathinfo($uploadFile, PATHINFO_EXTENSION));

        // Validate file type (only allow images)
        if (in_array($fileType, ['jpg', 'jpeg', 'png', 'gif'])) {
            if (move_uploaded_file($_FILES['logo']['tmp_name'], $uploadFile)) {
                // Update logo path in the database
                query("UPDATE settings SET value = ? WHERE key_name = 'header_logo'", [$uploadFile]);
                $success = "Logo uploaded and updated successfully!";
            } else {
                $error = "Failed to upload logo. Please try again.";
            }
        } else {
            $error = "Invalid file type. Only JPG, PNG, and GIF files are allowed.";
        }
    } elseif (isset($_POST['key']) && isset($_POST['value'])) {
        // Handle general settings update
        $key = $_POST['key'];
        $value = $_POST['value'];

        $existingSetting = query("SELECT * FROM settings WHERE key_name = ?", [$key])->fetch();

        if ($existingSetting) {
            query("UPDATE settings SET value = ? WHERE key_name = ?", [$value, $key]);
            $success = "Setting updated successfully!";
        } else {
            query("INSERT INTO settings (key_name, value) VALUES (?, ?)", [$key, $value]);
            $success = "Setting added successfully!";
        }
    } else {
        $error = "Invalid form submission.";
    }
}

// Fetch all settings
$settings = [];
$results = query("SELECT * FROM settings")->fetchAll();
foreach ($results as $setting) {
    $settings[$setting['key_name']] = $setting['value'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings</title>
    <!-- Include Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<?php include 'header.php'; ?> <!-- Include dynamic header -->

<div class="container mt-5">
    <h2 class="text-center text-primary mb-4">Settings</h2>
    <?php if (isset($success)) { echo "<div class='alert alert-success'>$success</div>"; } ?>
    <?php if (isset($error)) { echo "<div class='alert alert-danger'>$error</div>"; } ?>

    <!-- Form for Footer Details -->
    <form method="POST" class="mt-4">
    <h4>Update Footer Details</h4>
    <div class="mb-3">
        <label for="map" class="form-label">Embed Map (HTML or iframe)</label>
        <textarea class="form-control" id="map" name="map" rows="4"><?php echo htmlspecialchars($footerInfo['map'] ?? ''); ?></textarea>
    </div>
    <div class="mb-3">
        <label for="address" class="form-label">Address</label>
        <input type="text" class="form-control" id="address" name="address" value="<?php echo htmlspecialchars($footerInfo['address'] ?? ''); ?>" required>
    </div>
    <div class="mb-3">
        <label for="phone" class="form-label">Phone</label>
        <input type="text" class="form-control" id="phone" name="phone" value="<?php echo htmlspecialchars($footerInfo['phone'] ?? ''); ?>" required>
    </div>
    <div class="mb-3">
        <label for="email" class="form-label">Email</label>
        <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($footerInfo['email'] ?? ''); ?>" required>
    </div>
    <div class="mb-3">
        <label for="created_by" class="form-label">Created By (HTML Allowed)</label>
        <input type="text" class="form-control" id="created_by" name="created_by" value="<?php echo htmlspecialchars($footerInfo['created_by'] ?? ''); ?>">
    </div>
    <button type="submit" class="btn btn-primary">Update Footer</button>
</form>
    
    <!-- Form for Header Logo -->
    <form method="POST" enctype="multipart/form-data" class="mb-5">
        <h4>Update Header Logo</h4>
        <div class="mb-3">
            <label for="logo" class="form-label">Upload Logo (JPG, PNG, GIF)</label>
            <input type="file" class="form-control" id="logo" name="logo" required>
        </div>
        <button type="submit" class="btn btn-primary">Upload Logo</button>
    </form>

    <!-- Form for Header Name -->
    <form method="POST" class="mb-5">
        <h4>Update Header Name</h4>
        <div class="mb-3">
            <label for="header_name" class="form-label">Header Name</label>
            <input type="hidden" name="key" value="header_name">
            <input type="text" class="form-control" id="header_name" name="value" 
                   value="<?php echo isset($settings['header_name']) ? htmlspecialchars($settings['header_name']) : ''; ?>" 
                   placeholder="Enter Header Name" required>
        </div>
        <button type="submit" class="btn btn-primary">Update Header</button>
    </form>
</div>

<!-- Theme Selection Form -->
<form method="POST" class="card p-4 shadow">
        <h3 class="card-title text-center">Theme Options</h3>

        <div class="form-check">
            <input class="form-check-input" type="radio" name="theme" id="themeLight" value="light" <?php echo $currentTheme === 'light' ? 'checked' : ''; ?>>
            <label class="form-check-label" for="themeLight">Light Theme</label>
        </div>
        <div class="form-check">
            <input class="form-check-input" type="radio" name="theme" id="themeDark" value="dark" <?php echo $currentTheme === 'dark' ? 'checked' : ''; ?>>
            <label class="form-check-label" for="themeDark">Dark Theme</label>
        </div>
        <div class="form-check">
            <input class="form-check-input" type="radio" name="theme" id="themeSystem" value="system" <?php echo $currentTheme === 'system' ? 'checked' : ''; ?>>
            <label class="form-check-label" for="themeSystem">System Default</label>
        </div>

        <button type="submit" class="btn btn-primary mt-3">Save Theme</button>
    </form>
</div>



<!-- Include Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
<!-- JavaScript to Dynamically Apply Theme -->
<script>
    const currentTheme = "<?php echo $currentTheme; ?>";
    const themeStylesheet = document.getElementById("themeStylesheet");

    // Apply the system theme if selected
    if (currentTheme === "system") {
        const prefersDarkScheme = window.matchMedia("(prefers-color-scheme: dark)").matches;
        themeStylesheet.setAttribute("href", prefersDarkScheme ? "dark-theme.css" : "light-theme.css");
    }
</script>
<?php include 'footer.php'; ?> <!-- Include dynamic footer -->
</body>
</html>