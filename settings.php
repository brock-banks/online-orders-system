<?php
require_once 'config.php';
require_once 'functions.php';

if (!isLoggedIn() || !isAdmin()) {
    redirect('index.php');
}

$uploadDir = __DIR__ . '/uploads';
if (!is_dir($uploadDir)) {
    @mkdir($uploadDir, 0755, true);
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(24));
}
$csrfToken = $_SESSION['csrf_token'];

function flash($type, $msg) {
    $_SESSION['flash'] = ['type' => $type, 'text' => $msg];
}

function saveSetting($key, $value) {
    $exists = query("SELECT id FROM settings WHERE key_name = ? LIMIT 1", [$key])->fetch();
    if ($exists) {
        query("UPDATE settings SET value = ? WHERE key_name = ?", [$value, $key]);
    } else {
        query("INSERT INTO settings (key_name, value) VALUES (?, ?)", [$key, $value]);
    }
}

$settingsRows = query("SELECT key_name, value FROM settings")->fetchAll();
$settings = [];
foreach ($settingsRows as $r) {
    $settings[$r['key_name']] = $r['value'];
}

$footerInfo = query("SELECT * FROM footer_info LIMIT 1")->fetch();
$footerInfo = $footerInfo ?: [];

$hasAdminRoleColumn = false;
try {
    $col = query("SHOW COLUMNS FROM `users` LIKE 'admin_role'")->fetch();
    if ($col) $hasAdminRoleColumn = true;
} catch (Exception $e) {
    $hasAdminRoleColumn = false;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $posted = $_POST['csrf_token'] ?? '';
    if (!hash_equals($csrfToken, (string)$posted)) {
        flash('danger', 'Invalid CSRF token. Please refresh and try again.');
        header('Location: settings.php');
        exit;
    }

    try {
        if (isset($_POST['theme_submit'])) {
            $theme = in_array($_POST['theme'] ?? '', ['light', 'dark', 'system']) ? $_POST['theme'] : 'light';
            saveSetting('theme', $theme);
            flash('success', 'Theme updated successfully.');
            header('Location: settings.php');
            exit;
        }

        if (isset($_POST['header_name_submit'])) {
            $headerName = trim($_POST['header_name'] ?? '');
            $primaryColor = trim($_POST['primary_color'] ?? '');

            if ($headerName === '') {
                throw new RuntimeException('Header name cannot be empty.');
            }

            if ($primaryColor !== '' && !preg_match('/^#?[0-9a-fA-F]{6}$/', $primaryColor)) {
                throw new RuntimeException('Primary color must be a hex color like #1fa2b3.');
            }

            saveSetting('header_name', $headerName);

            if ($primaryColor !== '') {
                saveSetting('primary_color', strpos($primaryColor, '#') === 0 ? $primaryColor : '#' . $primaryColor);
            } else {
                saveSetting('primary_color', '');
            }

            flash('success', 'Branding settings updated.');
            header('Location: settings.php');
            exit;
        }

        if (isset($_POST['upload_logo'])) {
            if (!isset($_FILES['logo']) || $_FILES['logo']['error'] !== UPLOAD_ERR_OK) {
                throw new RuntimeException('No file uploaded or upload error.');
            }

            $file = $_FILES['logo'];
            $maxBytes = 2 * 1024 * 1024;

            if ($file['size'] > $maxBytes) {
                throw new RuntimeException('Logo exceeds 2MB size limit.');
            }

            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);

            $allowed = [
                'image/png' => 'png',
                'image/jpeg' => 'jpg',
                'image/webp' => 'webp',
                'image/svg+xml' => 'svg'
            ];

            if (!isset($allowed[$mime])) {
                throw new RuntimeException('Unsupported file type. Allowed: PNG, JPG, WEBP, SVG.');
            }

            $ext = $allowed[$mime];
            $safeName = 'header_logo_' . time() . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
            $dest = $uploadDir . '/' . $safeName;

            if (!move_uploaded_file($file['tmp_name'], $dest)) {
                throw new RuntimeException('Failed to move uploaded file.');
            }

            @chmod($dest, 0644);

            $prev = $settings['header_logo'] ?? '';
            if ($prev && !preg_match('#^https?://#i', $prev)) {
                $prevPath = __DIR__ . '/' . ltrim($prev, '/');
                if (is_file($prevPath)) {
                    @unlink($prevPath);
                }
            }

            $webPath = 'uploads/' . $safeName;
            saveSetting('header_logo', $webPath);

            flash('success', 'Header logo uploaded successfully.');
            header('Location: settings.php');
            exit;
        }

        if (isset($_POST['remove_logo'])) {
            $prev = $settings['header_logo'] ?? '';
            if ($prev && !preg_match('#^https?://#i', $prev)) {
                $prevPath = __DIR__ . '/' . ltrim($prev, '/');
                if (is_file($prevPath)) {
                    @unlink($prevPath);
                }
            }
            saveSetting('header_logo', '');
            flash('success', 'Header logo removed.');
            header('Location: settings.php');
            exit;
        }

        if (isset($_POST['footer_submit'])) {
            $map = $_POST['map'] ?? '';
            $address = trim($_POST['address'] ?? '');
            $phone = trim($_POST['phone'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $createdBy = $_POST['created_by'] ?? '';

            if ($address === '' || $phone === '' || $email === '') {
                throw new RuntimeException('Address, phone and email are required for footer.');
            }

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new RuntimeException('Invalid email address.');
            }

            $exists = query("SELECT id FROM footer_info LIMIT 1")->fetch();
            if ($exists) {
                query(
                    "UPDATE footer_info SET map = ?, address = ?, phone = ?, email = ?, created_by = ? WHERE id = ?",
                    [$map, $address, $phone, $email, $createdBy, $exists['id']]
                );
            } else {
                query(
                    "INSERT INTO footer_info (map, address, phone, email, created_by) VALUES (?, ?, ?, ?, ?)",
                    [$map, $address, $phone, $email, $createdBy]
                );
            }

            flash('success', 'Footer information updated.');
            header('Location: settings.php');
            exit;
        }

        if (isset($_POST['templates_submit'])) {
            $placeOrderTpl = (string)($_POST['template_place_order'] ?? '');
            $deliveredTpl = (string)($_POST['template_delivered'] ?? '');

            saveSetting('template_place_order', $placeOrderTpl);
            saveSetting('template_delivered', $deliveredTpl);

            flash('success', 'Message templates updated.');
            header('Location: settings.php#templates-pane');
            exit;
        }

        if (isset($_POST['export_settings'])) {
            $rows = query("SELECT key_name, value FROM settings")->fetchAll();
            $export = [];
            foreach ($rows as $r) {
                $export[$r['key_name']] = $r['value'];
            }

            header('Content-Type: application/json; charset=utf-8');
            header('Content-Disposition: attachment; filename=settings_export_' . date('Ymd_His') . '.json');
            echo json_encode($export, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            exit;
        }

        if (isset($_POST['import_settings'])) {
            if (!isset($_FILES['settings_file']) || $_FILES['settings_file']['error'] !== UPLOAD_ERR_OK) {
                throw new RuntimeException('No file uploaded for import.');
            }

            $content = file_get_contents($_FILES['settings_file']['tmp_name']);
            $decoded = json_decode($content, true);

            if (!is_array($decoded)) {
                throw new RuntimeException('Uploaded file is not valid JSON.');
            }

            foreach ($decoded as $k => $v) {
                if (!preg_match('/^[a-z0-9_\-]+$/i', $k)) {
                    continue;
                }
                saveSetting($k, (string)$v);
            }

            flash('success', 'Settings imported successfully.');
            header('Location: settings.php');
            exit;
        }

        if (isset($_POST['kv_submit'])) {
            $key = trim($_POST['key'] ?? '');
            $value = trim($_POST['value'] ?? '');

            if ($key === '') {
                throw new RuntimeException('Setting key cannot be empty.');
            }

            if (!preg_match('/^[a-z0-9_\-]+$/i', $key)) {
                throw new RuntimeException('Key may only contain letters, numbers, dash and underscore.');
            }

            saveSetting($key, $value);

            flash('success', 'Setting saved.');
            header('Location: settings.php');
            exit;
        }

        if (isset($_POST['add_delivery_person'])) {
            $name = trim($_POST['dp_name'] ?? '');
            if ($name === '') {
                throw new RuntimeException('Delivery person name cannot be empty.');
            }
            if (mb_strlen($name) > 100) {
                throw new RuntimeException('Name must be 100 characters or fewer.');
            }
            $dup = query("SELECT id FROM delivery_people WHERE name = ? LIMIT 1", [$name])->fetch();
            if ($dup) {
                throw new RuntimeException("A delivery person named '{$name}' already exists.");
            }
            query("INSERT INTO delivery_people (name) VALUES (?)", [$name]);
            flash('success', "Delivery person '{$name}' added.");
            header('Location: settings.php#delivery-people-pane');
            exit;
        }

        if (isset($_POST['rename_delivery_person'])) {
            $id = (int)($_POST['dp_id'] ?? 0);
            $newName = trim($_POST['dp_name'] ?? '');
            if ($id <= 0) {
                throw new RuntimeException('Invalid delivery person.');
            }
            if ($newName === '') {
                throw new RuntimeException('Delivery person name cannot be empty.');
            }
            if (mb_strlen($newName) > 100) {
                throw new RuntimeException('Name must be 100 characters or fewer.');
            }
            $row = query("SELECT name FROM delivery_people WHERE id = ? LIMIT 1", [$id])->fetch();
            if (!$row) {
                throw new RuntimeException('Delivery person not found.');
            }
            $oldName = $row['name'];
            if ($oldName === $newName) {
                flash('info', 'No changes — name is the same.');
                header('Location: settings.php#delivery-people-pane');
                exit;
            }
            $dup = query("SELECT id FROM delivery_people WHERE name = ? AND id <> ? LIMIT 1", [$newName, $id])->fetch();
            if ($dup) {
                throw new RuntimeException("A delivery person named '{$newName}' already exists.");
            }
            query("UPDATE delivery_people SET name = ? WHERE id = ?", [$newName, $id]);
            // Cascade rename to historical orders so reports stay consistent.
            query("UPDATE orders SET delivered_by = ? WHERE delivered_by = ?", [$newName, $oldName]);
            try {
                query("UPDATE archived_orders SET delivered_by = ? WHERE delivered_by = ?", [$newName, $oldName]);
            } catch (Exception $e) {
                // archived_orders table may not exist; ignore.
            }
            flash('success', "Renamed '{$oldName}' to '{$newName}'.");
            header('Location: settings.php#delivery-people-pane');
            exit;
        }

        if (isset($_POST['delete_delivery_person'])) {
            $id = (int)($_POST['dp_id'] ?? 0);
            if ($id <= 0) {
                throw new RuntimeException('Invalid delivery person.');
            }
            $row = query("SELECT name FROM delivery_people WHERE id = ? LIMIT 1", [$id])->fetch();
            if (!$row) {
                throw new RuntimeException('Delivery person not found.');
            }
            query("DELETE FROM delivery_people WHERE id = ?", [$id]);
            flash('success', "Removed '{$row['name']}' from the delivery people list.");
            header('Location: settings.php#delivery-people-pane');
            exit;
        }

        if (isset($_POST['create_user'])) {
            $username = trim($_POST['new_username'] ?? '');
            $password = $_POST['new_password'] ?? '';
            $role = ($_POST['new_role'] ?? 'user') === 'admin' ? 'admin' : 'user';
            $adminRole = trim($_POST['new_admin_role'] ?? '');

            if ($username === '' || $password === '') {
                throw new RuntimeException('Username and password are required.');
            }

            if (!preg_match('/^[A-Za-z0-9._-]{3,60}$/', $username)) {
                throw new RuntimeException('Username must be 3-60 characters and may contain letters, numbers, dot, underscore or dash.');
            }

            if (strlen($password) < 6) {
                throw new RuntimeException('Password must be at least 6 characters.');
            }

            $exists = query("SELECT id FROM users WHERE username = ? LIMIT 1", [$username])->fetch();
            if ($exists) {
                throw new RuntimeException('Username already exists.');
            }

            $hash = password_hash($password, PASSWORD_DEFAULT);
            query("INSERT INTO users (username, password, role) VALUES (?, ?, ?)", [$username, $hash, $role]);
            $newId = $pdo->lastInsertId();

            if ($hasAdminRoleColumn && $adminRole !== '') {
                $adminRoleVal = substr($adminRole, 0, 50);
                query("UPDATE users SET admin_role = ? WHERE id = ?", [$adminRoleVal, $newId]);
            }

            flash('success', "User '{$username}' created successfully.");
            header('Location: settings.php');
            exit;
        }

    } catch (Exception $e) {
        error_log('Settings error: ' . $e->getMessage());
        flash('danger', $e->getMessage());
        header('Location: settings.php');
        exit;
    }
}

$settingsRows = query("SELECT key_name, value FROM settings")->fetchAll();
$settings = [];
foreach ($settingsRows as $r) {
    $settings[$r['key_name']] = $r['value'];
}
$footerInfo = query("SELECT * FROM footer_info LIMIT 1")->fetch() ?: [];

try {
    $deliveryPeople = query("SELECT id, name FROM delivery_people ORDER BY name ASC")->fetchAll();
} catch (Exception $e) {
    error_log('Failed to load delivery_people: ' . $e->getMessage());
    $deliveryPeople = [];
}

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Settings</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
    .preview-logo {
        max-height: 72px;
        object-fit: contain;
    }
    .settings-nav .nav-link {
        border-radius: 10px;
        font-weight: 600;
        color: #495057;
    }
    .settings-nav .nav-link.active {
        background: #0d6efd;
        color: #fff;
    }
    .settings-card {
        border-radius: 16px;
    }
    .settings-muted {
        font-size: 0.92rem;
        color: #6c757d;
    }
    .settings-table td:first-child {
        font-family: monospace;
        font-weight: 600;
    }
    .logo-preview-box {
        min-height: 96px;
        border: 1px dashed #d0d7de;
        border-radius: 12px;
        background: #f8f9fa;
    }
</style>
</head>
<body>
<?php include 'header.php'; ?>

<div class="container page-shell">
    <div class="page-title-row">
        <div>
            <h2 class="page-title text-primary">Settings</h2>
            <p class="page-subtitle">Manage branding, theme, footer content, users, and application configuration.</p>
        </div>
    </div>

    <?php if ($flash): ?>
        <div class="alert alert-<?php echo htmlspecialchars($flash['type'] ?? 'info'); ?>">
            <?php echo htmlspecialchars($flash['text'] ?? ''); ?>
        </div>
    <?php endif; ?>

    <div class="row g-4">
        <div class="col-lg-3">
            <div class="card app-card settings-card">
                <div class="card-body">
                    <div class="nav flex-column nav-pills settings-nav" id="settingsTab" role="tablist" aria-orientation="vertical">
                        <button class="nav-link active mb-2" id="branding-tab" data-bs-toggle="pill" data-bs-target="#branding-pane" type="button" role="tab">Branding</button>
                        <button class="nav-link mb-2" id="theme-tab" data-bs-toggle="pill" data-bs-target="#theme-pane" type="button" role="tab">Theme</button>
                        <button class="nav-link mb-2" id="footer-tab" data-bs-toggle="pill" data-bs-target="#footer-pane" type="button" role="tab">Footer</button>
                        <button class="nav-link mb-2" id="templates-tab" data-bs-toggle="pill" data-bs-target="#templates-pane" type="button" role="tab">Message Templates</button>
                        <button class="nav-link mb-2" id="delivery-people-tab" data-bs-toggle="pill" data-bs-target="#delivery-people-pane" type="button" role="tab">Delivery People</button>
                        <button class="nav-link mb-2" id="users-tab" data-bs-toggle="pill" data-bs-target="#users-pane" type="button" role="tab">User Management</button>
                        <button class="nav-link mb-2" id="import-export-tab" data-bs-toggle="pill" data-bs-target="#import-export-pane" type="button" role="tab">Import / Export</button>
                        <button class="nav-link" id="advanced-tab" data-bs-toggle="pill" data-bs-target="#advanced-pane" type="button" role="tab">Advanced</button>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-9">
            <div class="tab-content" id="settingsTabContent">

                <div class="tab-pane fade show active" id="branding-pane" role="tabpanel">
                    <div class="card app-card settings-card section-gap">
                        <div class="card-header">Branding</div>
                        <div class="card-body">
                            <p class="settings-muted">Update the system name, primary color, and brand logo shown in the header.</p>

                            <form method="POST" class="row g-3">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">

                                <div class="col-md-8">
                                    <label class="form-label">Header Name</label>
                                    <input type="text" name="header_name" value="<?php echo htmlspecialchars($settings['header_name'] ?? ''); ?>" class="form-control" required>
                                </div>

                                <div class="col-md-4">
                                    <label class="form-label">Primary Color</label>
                                    <input type="text" name="primary_color" value="<?php echo htmlspecialchars($settings['primary_color'] ?? ''); ?>" class="form-control" placeholder="#1fa2b3">
                                </div>

                                <div class="col-12">
                                    <button type="submit" name="header_name_submit" class="btn btn-primary">Save Branding</button>
                                </div>
                            </form>

                            <hr class="my-4">

                            <div class="mb-3">
                                <label class="form-label">Current Logo</label>
                                <div class="logo-preview-box d-flex align-items-center justify-content-center p-3 mb-3">
                                    <?php if (!empty($settings['header_logo'])): ?>
                                        <img src="<?php echo htmlspecialchars($settings['header_logo']); ?>" alt="logo" class="preview-logo">
                                    <?php else: ?>
                                        <div class="text-muted">No logo uploaded</div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <form method="POST" enctype="multipart/form-data" class="row g-3 align-items-end">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">

                                <div class="col-md-8">
                                    <label class="form-label">Upload New Logo</label>
                                    <input type="file" name="logo" accept="image/png,image/jpeg,image/webp,image/svg+xml" class="form-control form-control-sm" required>
                                    <div class="form-text">Allowed: PNG, JPG, WEBP, SVG. Max 2MB.</div>
                                </div>

                                <div class="col-md-4 d-flex gap-2">
                                    <button type="submit" name="upload_logo" class="btn btn-success flex-fill">Upload</button>
                                    <?php if (!empty($settings['header_logo'])): ?>
                                        <button type="submit" name="remove_logo" class="btn btn-outline-danger flex-fill" onclick="return confirm('Remove current logo?')">Remove</button>
                                    <?php endif; ?>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="tab-pane fade" id="theme-pane" role="tabpanel">
                    <div class="card app-card settings-card section-gap">
                        <div class="card-header">Theme</div>
                        <div class="card-body">
                            <p class="settings-muted">Choose the default theme used across the application.</p>

                            <form method="POST">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">

                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="radio" name="theme" id="themeLight" value="light" <?php echo (($settings['theme'] ?? 'light') === 'light') ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="themeLight">Light Theme</label>
                                </div>

                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="radio" name="theme" id="themeDark" value="dark" <?php echo (($settings['theme'] ?? '') === 'dark') ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="themeDark">Dark Theme</label>
                                </div>

                                <div class="form-check mb-3">
                                    <input class="form-check-input" type="radio" name="theme" id="themeSystem" value="system" <?php echo (($settings['theme'] ?? '') === 'system') ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="themeSystem">System Default</label>
                                </div>

                                <button type="submit" name="theme_submit" class="btn btn-primary">Save Theme</button>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="tab-pane fade" id="footer-pane" role="tabpanel">
                    <div class="card app-card settings-card section-gap">
                        <div class="card-header">Footer Information</div>
                        <div class="card-body">
                            <p class="settings-muted">Control the contact information and footer display content shown across the site.</p>

                            <form method="POST">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">

                                <div class="mb-3">
                                    <label class="form-label">Map Embed (HTML / iframe)</label>
                                    <textarea name="map" rows="4" class="form-control"><?php echo htmlspecialchars($footerInfo['map'] ?? ''); ?></textarea>
                                    <div class="form-text">This content is rendered directly in the footer.</div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Address</label>
                                        <input name="address" class="form-control" value="<?php echo htmlspecialchars($footerInfo['address'] ?? ''); ?>" required>
                                    </div>

                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Phone</label>
                                        <input name="phone" class="form-control" value="<?php echo htmlspecialchars($footerInfo['phone'] ?? ''); ?>" required>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Email</label>
                                        <input name="email" type="email" class="form-control" value="<?php echo htmlspecialchars($footerInfo['email'] ?? ''); ?>" required>
                                    </div>

                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Created By (HTML allowed)</label>
                                        <input name="created_by" class="form-control" value="<?php echo htmlspecialchars($footerInfo['created_by'] ?? ''); ?>">
                                    </div>
                                </div>

                                <button type="submit" name="footer_submit" class="btn btn-primary">Save Footer</button>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="tab-pane fade" id="templates-pane" role="tabpanel">
                    <div class="card app-card settings-card section-gap">
                        <div class="card-header">WhatsApp Message Templates</div>
                        <div class="card-body">
                            <p class="settings-muted">Edit the messages sent via WhatsApp for new orders and deliveries. Use the placeholders below — they're replaced with real order values when the message is sent.</p>

                            <div class="alert alert-light border mb-4" style="font-size:.9rem;">
                                <div class="fw-semibold mb-2">Available placeholders</div>
                                <div class="d-flex flex-wrap gap-2">
                                    <code>{invoice_no}</code>
                                    <code>{phone}</code>
                                    <code>{address}</code>
                                    <code>{details}</code>
                                    <code>{price}</code>
                                    <code>{delivered_by}</code>
                                    <code>{date}</code>
                                    <code>{username}</code>
                                </div>
                                <div class="settings-muted mt-2">Unrecognized placeholders are left as-is. <code>{delivered_by}</code> only fills in for the delivered flow.</div>
                            </div>

                            <form method="POST">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">

                                <div class="mb-4">
                                    <label class="form-label fw-semibold">Order Confirmation (sent when placing an order)</label>
                                    <textarea name="template_place_order" rows="10" class="form-control" dir="auto" style="font-family: inherit;"><?php echo htmlspecialchars($settings['template_place_order'] ?? defaultMessageTemplate('template_place_order')); ?></textarea>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Delivered Notification (sent when marking delivered)</label>
                                    <textarea name="template_delivered" rows="10" class="form-control" dir="auto" style="font-family: inherit;"><?php echo htmlspecialchars($settings['template_delivered'] ?? defaultMessageTemplate('template_delivered')); ?></textarea>
                                </div>

                                <div class="d-flex gap-2">
                                    <button type="submit" name="templates_submit" class="btn btn-primary">Save Templates</button>
                                    <button type="button" id="resetPlaceOrderTpl" class="btn btn-outline-secondary btn-sm">Reset Order Confirmation to default</button>
                                    <button type="button" id="resetDeliveredTpl" class="btn btn-outline-secondary btn-sm">Reset Delivered to default</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="tab-pane fade" id="delivery-people-pane" role="tabpanel">
                    <div class="card app-card settings-card section-gap">
                        <div class="card-header">Delivery People</div>
                        <div class="card-body">
                            <p class="settings-muted">Manage the list of delivery people shown in the <strong>Mark as Delivered</strong> picker on the Orders page. Renaming someone updates all of their historical orders too.</p>

                            <form method="POST" class="row g-2 align-items-end mb-4">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">

                                <div class="col-sm-8">
                                    <label class="form-label">Add new delivery person</label>
                                    <input type="text" name="dp_name" class="form-control" placeholder="Full name" maxlength="100" required>
                                </div>

                                <div class="col-sm-4 d-grid">
                                    <button type="submit" name="add_delivery_person" class="btn btn-primary">Add</button>
                                </div>
                            </form>

                            <div class="app-table-wrap">
                                <div class="table-responsive">
                                    <table class="table app-table align-middle mb-0">
                                        <thead>
                                            <tr>
                                                <th style="width: 70px;">ID</th>
                                                <th>Name</th>
                                                <th style="min-width: 220px; width: 240px;" class="text-end">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (count($deliveryPeople) === 0): ?>
                                                <tr>
                                                    <td colspan="3">
                                                        <div class="empty-state text-center my-3">
                                                            <div class="empty-state-title">No delivery people yet</div>
                                                            <div class="settings-muted">Add your first delivery person above so they show up in the Mark as Delivered picker.</div>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php else: ?>
                                                <?php foreach ($deliveryPeople as $person): ?>
                                                    <tr>
                                                        <td class="text-muted"><?php echo (int)$person['id']; ?></td>
                                                        <td>
                                                            <form method="POST" class="d-flex gap-2 align-items-center dp-rename-form">
                                                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                                                                <input type="hidden" name="dp_id" value="<?php echo (int)$person['id']; ?>">
                                                                <input
                                                                    type="text"
                                                                    name="dp_name"
                                                                    class="form-control form-control-sm dp-name-input"
                                                                    value="<?php echo htmlspecialchars($person['name']); ?>"
                                                                    data-original="<?php echo htmlspecialchars($person['name']); ?>"
                                                                    maxlength="100"
                                                                    required
                                                                >
                                                                <button type="submit" name="rename_delivery_person" class="btn btn-sm btn-primary dp-save-btn" disabled>Save</button>
                                                            </form>
                                                        </td>
                                                        <td class="text-end">
                                                            <form method="POST" class="d-inline dp-delete-form" data-name="<?php echo htmlspecialchars($person['name']); ?>">
                                                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                                                                <input type="hidden" name="dp_id" value="<?php echo (int)$person['id']; ?>">
                                                                <button type="submit" name="delete_delivery_person" class="btn btn-sm btn-outline-danger">Remove</button>
                                                            </form>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="tab-pane fade" id="users-pane" role="tabpanel">
                    <div class="card app-card settings-card section-gap">
                        <div class="card-header">Register New User</div>
                        <div class="card-body">
                            <p class="settings-muted">Create a new user account with a role and optional admin role.</p>

                            <form method="POST" class="row g-3">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">

                                <div class="col-md-4">
                                    <label class="form-label">Username</label>
                                    <input name="new_username" class="form-control" placeholder="username" required>
                                </div>

                                <div class="col-md-4">
                                    <label class="form-label">Password</label>
                                    <input name="new_password" type="password" class="form-control" placeholder="password" required>
                                    <div class="form-text">Minimum 6 characters.</div>
                                </div>

                                <div class="col-md-4">
                                    <label class="form-label">Role</label>
                                    <select name="new_role" class="form-select">
                                        <option value="user">User</option>
                                        <option value="admin">Admin</option>
                                    </select>
                                </div>

                                <?php if ($hasAdminRoleColumn): ?>
                                <div class="col-md-6">
                                    <label class="form-label">Admin Role (optional)</label>
                                    <select name="new_admin_role" class="form-select">
                                        <option value="">(none)</option>
                                        <option value="superadmin">superadmin</option>
                                        <option value="manager">manager</option>
                                        <option value="analyst">analyst</option>
                                    </select>
                                </div>
                                <?php endif; ?>

                                <div class="col-12">
                                    <button type="submit" name="create_user" class="btn btn-primary">Create User</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="tab-pane fade" id="import-export-pane" role="tabpanel">
                    <div class="card app-card settings-card section-gap">
                        <div class="card-header">Import / Export Settings</div>
                        <div class="card-body">
                            <p class="settings-muted">Export current settings to JSON or import a saved configuration file.</p>

                            <div class="row g-4">
                                <div class="col-md-6">
                                    <div class="border rounded-3 p-3 h-100">
                                        <h6 class="fw-bold">Export Settings</h6>
                                        <p class="settings-muted mb-3">Download the current app configuration as a JSON file.</p>
                                        <form method="POST">
                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                                            <button type="submit" name="export_settings" class="btn btn-success">Export JSON</button>
                                        </form>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <div class="border rounded-3 p-3 h-100">
                                        <h6 class="fw-bold">Import Settings</h6>
                                        <p class="settings-muted mb-3">Upload a JSON file to restore or overwrite saved settings.</p>
                                        <form method="POST" enctype="multipart/form-data">
                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                                            <div class="mb-3">
                                                <input type="file" name="settings_file" accept=".json,application/json" class="form-control form-control-sm" required>
                                            </div>
                                            <button type="submit" name="import_settings" class="btn btn-outline-primary">Import JSON</button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="tab-pane fade" id="advanced-pane" role="tabpanel">
                    <div class="card app-card settings-card section-gap">
                        <div class="card-header">Advanced Key / Value Settings</div>
                        <div class="card-body">
                            <p class="settings-muted">Manually add or update raw configuration keys. Be careful when editing system keys.</p>

                            <form method="POST" class="row g-3 mb-4">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">

                                <div class="col-md-4">
                                    <label class="form-label">Setting Key</label>
                                    <input name="key" class="form-control" placeholder="setting_key" required>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label">Value</label>
                                    <input name="value" class="form-control" placeholder="value">
                                </div>

                                <div class="col-md-2 d-flex align-items-end">
                                    <button type="submit" name="kv_submit" class="btn btn-primary w-100">Save</button>
                                </div>
                            </form>

                            <div class="app-table-wrap">
                                <div class="table-responsive">
                                    <table class="table app-table settings-table mb-0">
                                        <thead>
                                            <tr>
                                                <th>Key</th>
                                                <th>Value</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (count($settings) === 0): ?>
                                                <tr>
                                                    <td colspan="2">
                                                        <div class="empty-state my-2">
                                                            <div class="empty-state-title">No settings saved</div>
                                                            <div>Add a key/value pair to get started.</div>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php else: ?>
                                                <?php foreach ($settings as $k => $v): ?>
                                                    <tr>
                                                        <td><?php echo htmlspecialchars($k); ?></td>
                                                        <td style="max-width: 420px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                                            <?php echo htmlspecialchars($v); ?>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <div class="form-text mt-3">
                                Known system keys include: <code>header_logo</code>, <code>header_name</code>, <code>theme</code>, and <code>primary_color</code>.
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const defaultPlaceOrder = <?php echo json_encode(defaultMessageTemplate('template_place_order')); ?>;
    const defaultDelivered = <?php echo json_encode(defaultMessageTemplate('template_delivered')); ?>;

    const btnResetPo = document.getElementById('resetPlaceOrderTpl');
    if (btnResetPo) {
        btnResetPo.addEventListener('click', function() {
            const ta = document.querySelector('textarea[name="template_place_order"]');
            if (ta && confirm('Reset Order Confirmation template to the default?')) ta.value = defaultPlaceOrder;
        });
    }

    const btnResetDel = document.getElementById('resetDeliveredTpl');
    if (btnResetDel) {
        btnResetDel.addEventListener('click', function() {
            const ta = document.querySelector('textarea[name="template_delivered"]');
            if (ta && confirm('Reset Delivered template to the default?')) ta.value = defaultDelivered;
        });
    }

    if (window.location.hash) {
        const targetSel = 'button[data-bs-target="' + window.location.hash + '"]';
        const tabBtn = document.querySelector(targetSel);
        if (tabBtn) tabBtn.click();
    }

    // Delivery People: enable Save only when the name changed
    document.querySelectorAll('.dp-rename-form').forEach(function(form) {
        const input = form.querySelector('.dp-name-input');
        const saveBtn = form.querySelector('.dp-save-btn');
        if (!input || !saveBtn) return;
        input.addEventListener('input', function() {
            const dirty = input.value.trim() !== input.dataset.original && input.value.trim() !== '';
            saveBtn.disabled = !dirty;
        });
    });

    // Delivery People: confirm before deleting
    document.querySelectorAll('.dp-delete-form').forEach(function(form) {
        form.addEventListener('submit', function(e) {
            const name = form.dataset.name || 'this delivery person';
            if (!confirm('Remove "' + name + '" from the delivery people list?\n\nHistorical orders that show their name will keep showing it.')) {
                e.preventDefault();
            }
        });
    });

    const logoInput = document.querySelector('input[name="logo"]');
    if (!logoInput) return;

    logoInput.addEventListener('change', function() {
        const file = this.files[0];
        if (!file || !file.type.startsWith('image/')) return;

        const reader = new FileReader();
        reader.onload = function(e) {
            const previewBox = document.querySelector('.logo-preview-box');
            if (!previewBox) return;
            previewBox.innerHTML = '<img src="' + e.target.result + '" alt="logo preview" class="preview-logo">';
        };
        reader.readAsDataURL(file);
    });
});
</script>

<?php include 'footer.php'; ?>
</body>
</html>