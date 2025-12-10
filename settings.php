<?php
require_once 'config.php';
require_once 'functions.php';

if (!isLoggedIn() || !isAdmin()) {
    redirect('index.php');
}

// Ensure upload dir exists
$uploadDir = __DIR__ . '/uploads';
if (!is_dir($uploadDir)) {
    @mkdir($uploadDir, 0755, true);
}

// CSRF token helper
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(24));
}
$csrfToken = $_SESSION['csrf_token'];

// Flash helper
function flash($type, $msg) {
    $_SESSION['flash'] = ['type' => $type, 'text' => $msg];
}

// Load current settings (key_name => value)
$settingsRows = query("SELECT key_name, value FROM settings")->fetchAll();
$settings = [];
foreach ($settingsRows as $r) $settings[$r['key_name']] = $r['value'];

// Load footer_info (single row)
$footerInfo = query("SELECT * FROM footer_info LIMIT 1")->fetch();
$footerInfo = $footerInfo ?: [];

// Helpers
function saveSetting($key, $value) {
    $exists = query("SELECT id FROM settings WHERE key_name = ? LIMIT 1", [$key])->fetch();
    if ($exists) {
        query("UPDATE settings SET value = ? WHERE key_name = ?", [$value, $key]);
    } else {
        query("INSERT INTO settings (key_name, value) VALUES (?, ?)", [$key, $value]);
    }
}

// Check if users table has admin_role column (optional migration)
$hasAdminRoleColumn = false;
try {
    $col = query("SHOW COLUMNS FROM `users` LIKE 'admin_role'")->fetch();
    if ($col) $hasAdminRoleColumn = true;
} catch (Exception $e) {
    // ignore - table may not exist yet in some setups
    $hasAdminRoleColumn = false;
}

// Handle POST forms
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF
    $posted = $_POST['csrf_token'] ?? '';
    if (!hash_equals($csrfToken, (string)$posted)) {
        flash('danger', 'Invalid CSRF token. Please refresh and try again.');
        header('Location: settings.php');
        exit;
    }

    try {
        // Theme
        if (isset($_POST['theme_submit'])) {
            $theme = in_array($_POST['theme'] ?? '', ['light', 'dark', 'system']) ? $_POST['theme'] : 'light';
            saveSetting('theme', $theme);
            flash('success', 'Theme updated successfully.');
            header('Location: settings.php');
            exit;
        }

        // Header name + primary color
        if (isset($_POST['header_name_submit'])) {
            $headerName = trim($_POST['header_name'] ?? '');
            $primaryColor = trim($_POST['primary_color'] ?? '');
            if ($headerName === '') throw new RuntimeException('Header name cannot be empty.');
            if ($primaryColor !== '' && !preg_match('/^#?[0-9a-fA-F]{6}$/', $primaryColor)) {
                throw new RuntimeException('Primary color must be a hex color like #1fa2b3.');
            }
            saveSetting('header_name', $headerName);
            if ($primaryColor !== '') {
                saveSetting('primary_color', strpos($primaryColor, '#') === 0 ? $primaryColor : '#' . $primaryColor);
            } else {
                saveSetting('primary_color', '');
            }
            flash('success', 'Header settings updated.');
            header('Location: settings.php');
            exit;
        }

        // Upload logo
        if (isset($_POST['upload_logo'])) {
            if (!isset($_FILES['logo']) || $_FILES['logo']['error'] !== UPLOAD_ERR_OK) {
                throw new RuntimeException('No file uploaded or upload error.');
            }
            $file = $_FILES['logo'];
            $maxBytes = 2 * 1024 * 1024;
            if ($file['size'] > $maxBytes) throw new RuntimeException('Logo exceeds 2MB size limit.');
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);
            $allowed = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp', 'image/svg+xml' => 'svg'];
            if (!isset($allowed[$mime])) throw new RuntimeException('Unsupported file type. Allowed: PNG, JPG, WEBP, SVG.');
            $ext = $allowed[$mime];
            $safeName = 'header_logo_' . time() . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
            $dest = $uploadDir . '/' . $safeName;
            if (!move_uploaded_file($file['tmp_name'], $dest)) throw new RuntimeException('Failed to move uploaded file.');
            @chmod($dest, 0644);
            $prev = $settings['header_logo'] ?? '';
            if ($prev && !preg_match('#^https?://#i', $prev)) {
                $prevPath = __DIR__ . '/' . ltrim($prev, '/');
                if (is_file($prevPath)) @unlink($prevPath);
            }
            $webPath = 'uploads/' . $safeName;
            saveSetting('header_logo', $webPath);
            flash('success', 'Header logo uploaded successfully.');
            header('Location: settings.php');
            exit;
        }

        // Remove logo
        if (isset($_POST['remove_logo'])) {
            $prev = $settings['header_logo'] ?? '';
            if ($prev && !preg_match('#^https?://#i', $prev)) {
                $prevPath = __DIR__ . '/' . ltrim($prev, '/');
                if (is_file($prevPath)) @unlink($prevPath);
            }
            saveSetting('header_logo', '');
            flash('success', 'Header logo removed.');
            header('Location: settings.php');
            exit;
        }

        // Footer
        if (isset($_POST['footer_submit'])) {
            $map = $_POST['map'] ?? '';
            $address = trim($_POST['address'] ?? '');
            $phone = trim($_POST['phone'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $createdBy = $_POST['created_by'] ?? '';
            if ($address === '' || $phone === '' || $email === '') throw new RuntimeException('Address, phone and email are required for footer.');
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) throw new RuntimeException('Invalid email address.');
            $exists = query("SELECT id FROM footer_info LIMIT 1")->fetch();
            if ($exists) {
                query("UPDATE footer_info SET map = ?, address = ?, phone = ?, email = ?, created_by = ? WHERE id = ?", [
                    $map, $address, $phone, $email, $createdBy, $exists['id']
                ]);
            } else {
                query("INSERT INTO footer_info (map, address, phone, email, created_by) VALUES (?, ?, ?, ?, ?)", [
                    $map, $address, $phone, $email, $createdBy
                ]);
            }
            flash('success', 'Footer information updated.');
            header('Location: settings.php');
            exit;
        }

        // Settings export
        if (isset($_POST['export_settings'])) {
            $rows = query("SELECT key_name, value FROM settings")->fetchAll();
            $export = [];
            foreach ($rows as $r) $export[$r['key_name']] = $r['value'];
            header('Content-Type: application/json; charset=utf-8');
            header('Content-Disposition: attachment; filename=settings_export_' . date('Ymd_His') . '.json');
            echo json_encode($export, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            exit;
        }

        // Settings import
        if (isset($_POST['import_settings'])) {
            if (!isset($_FILES['settings_file']) || $_FILES['settings_file']['error'] !== UPLOAD_ERR_OK) {
                throw new RuntimeException('No file uploaded for import.');
            }
            $content = file_get_contents($_FILES['settings_file']['tmp_name']);
            $decoded = json_decode($content, true);
            if (!is_array($decoded)) throw new RuntimeException('Uploaded file is not valid JSON.');
            foreach ($decoded as $k => $v) {
                if (!preg_match('/^[a-z0-9_\\-]+$/i', $k)) continue;
                saveSetting($k, (string)$v);
            }
            flash('success', 'Settings imported.');
            header('Location: settings.php');
            exit;
        }

        // Generic key/value
        if (isset($_POST['kv_submit'])) {
            $key = trim($_POST['key'] ?? '');
            $value = trim($_POST['value'] ?? '');
            if ($key === '') throw new RuntimeException('Setting key cannot be empty.');
            if (!preg_match('/^[a-z0-9_\\-]+$/i', $key)) throw new RuntimeException('Key may only contain letters, numbers, dash and underscore.');
            saveSetting($key, $value);
            flash('success', 'Setting saved.');
            header('Location: settings.php');
            exit;
        }

        // NEW: Create a user from Settings (admin-only)
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

            // Check uniqueness
            $exists = query("SELECT id FROM users WHERE username = ? LIMIT 1", [$username])->fetch();
            if ($exists) {
                throw new RuntimeException('Username already exists.');
            }

            // Insert user (password hashed)
            $hash = password_hash($password, PASSWORD_DEFAULT);
            query("INSERT INTO users (username, password, role) VALUES (?, ?, ?)", [$username, $hash, $role]);
            $newId = $pdo->lastInsertId();

            // If admin_role column exists and value provided, set it
            if ($hasAdminRoleColumn && $adminRole !== '') {
                // limit admin_role length to 50 to match migration
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

// Refresh settings and footer info after changes
$settingsRows = query("SELECT key_name, value FROM settings")->fetchAll();
$settings = [];
foreach ($settingsRows as $r) $settings[$r['key_name']] = $r['value'];
$footerInfo = query("SELECT * FROM footer_info LIMIT 1")->fetch() ?: [];
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
    .preview-logo { max-height:64px; object-fit:contain; }
    .small-muted { font-size:0.9rem; color:#6c757d; }
    .kv-list { max-height:240px; overflow:auto; }
</style>
</head>
<body>
<?php include 'header.php'; ?>

<div class="container my-4">
    <h2 class="text-primary text-center mb-4">Settings</h2>

    <?php if ($flash): ?>
        <div class="alert alert-<?php echo htmlspecialchars($flash['type'] ?? 'info'); ?>"><?php echo htmlspecialchars($flash['text'] ?? ''); ?></div>
    <?php endif; ?>

    <div class="row gy-4">
        <div class="col-md-6">
            <div class="card shadow">
                <div class="card-body">
                    <h5 class="card-title">Theme</h5>
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="theme" id="themeLight" value="light" <?php echo (($settings['theme'] ?? 'light') === 'light') ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="themeLight">Light</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="theme" id="themeDark" value="dark" <?php echo (($settings['theme'] ?? '') === 'dark') ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="themeDark">Dark</label>
                        </div>
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="radio" name="theme" id="themeSystem" value="system" <?php echo (($settings['theme'] ?? '') === 'system') ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="themeSystem">System Default</label>
                        </div>
                        <button type="submit" name="theme_submit" class="btn btn-primary">Save Theme</button>
                    </form>
                </div>
            </div>

            <div class="card mt-3 shadow">
                <div class="card-body">
                    <h5 class="card-title">Header</h5>
                    <div class="mb-3">
                        <label class="form-label">Header Name</label>
                        <form method="POST" class="d-flex gap-2">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                            <input type="text" name="header_name" value="<?php echo htmlspecialchars($settings['header_name'] ?? ''); ?>" class="form-control" placeholder="Header display name" required>
                            <input type="text" name="primary_color" value="<?php echo htmlspecialchars($settings['primary_color'] ?? ''); ?>" class="form-control" placeholder="#RRGGBB (primary color)" style="max-width:140px;">
                            <button type="submit" name="header_name_submit" class="btn btn-primary">Save</button>
                        </form>
                        <div class="form-text">Primary color (hex) overrides light theme accent. Example: #1fa2b3</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Header Logo</label>
                        <div class="d-flex align-items-center gap-3 mb-2">
                            <?php if (!empty($settings['header_logo'])): ?>
                                <img src="<?php echo htmlspecialchars($settings['header_logo']); ?>" alt="logo" class="preview-logo border">
                            <?php else: ?>
                                <div class="small-muted">No logo uploaded</div>
                            <?php endif; ?>
                        </div>
                        <form method="POST" enctype="multipart/form-data" class="d-flex gap-2 align-items-start">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                            <input type="file" name="logo" accept="image/png,image/jpeg,image/webp,image/svg+xml" class="form-control form-control-sm" required>
                            <div class="d-flex flex-column gap-2">
                                <button type="submit" name="upload_logo" class="btn btn-success btn-sm">Upload</button>
                                <?php if (!empty($settings['header_logo'])): ?>
                                    <button type="submit" name="remove_logo" class="btn btn-outline-danger btn-sm" onclick="return confirm('Remove current logo?')">Remove</button>
                                <?php endif; ?>
                            </div>
                        </form>
                        <div class="form-text">Allowed: PNG, JPG, WEBP, SVG. Max 2MB.</div>
                    </div>
                </div>
            </div>

            <!-- New: Register User -->
            <div class="card mt-3 shadow">
                <div class="card-body">
                    <h5 class="card-title">Register New User</h5>
                    <form method="POST" class="row g-2">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                        <div class="col-md-4">
                            <label class="form-label">Username</label>
                            <input name="new_username" class="form-control" placeholder="username" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Password</label>
                            <input name="new_password" type="password" class="form-control" placeholder="password" required>
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
                            <button type="submit" name="create_user" class="btn btn-outline-primary">Create User</button>
                        </div>
                    </form>
                    <div class="form-text mt-2">New users will be created with a hashed password. If you set "admin" role, consider filling Admin Role.</div>
                </div>
            </div>
        </div>

        <!-- Footer Info -->
        <div class="col-md-6">
            <div class="card shadow">
                <div class="card-body">
                    <h5 class="card-title">Footer Information</h5>
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                        <div class="mb-3">
                            <label class="form-label">Map Embed (HTML/iframe)</label>
                            <textarea name="map" rows="4" class="form-control"><?php echo htmlspecialchars($footerInfo['map'] ?? ''); ?></textarea>
                            <div class="form-text">You may paste an iframe or embed code. This is rendered in footer as-is (admin only).</div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Address</label>
                            <input name="address" class="form-control" value="<?php echo htmlspecialchars($footerInfo['address'] ?? ''); ?>" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Phone</label>
                            <input name="phone" class="form-control" value="<?php echo htmlspecialchars($footerInfo['phone'] ?? ''); ?>" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input name="email" type="email" class="form-control" value="<?php echo htmlspecialchars($footerInfo['email'] ?? ''); ?>" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Created By (HTML allowed)</label>
                            <input name="created_by" class="form-control" value="<?php echo htmlspecialchars($footerInfo['created_by'] ?? ''); ?>">
                        </div>

                        <button type="submit" name="footer_submit" class="btn btn-primary">Save Footer</button>
                    </form>
                </div>
            </div>

            <div class="card mt-3 shadow">
                <div class="card-body">
                    <h5 class="card-title">Settings Import / Export</h5>

                    <form method="POST" enctype="multipart/form-data" class="mb-2">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                        <div class="d-flex gap-2">
                            <button type="submit" name="export_settings" class="btn btn-success">Export Settings JSON</button>
                        </div>
                    </form>

                    <form method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                        <div class="mb-2">
                            <input type="file" name="settings_file" accept=".json,application/json" class="form-control form-control-sm" required>
                        </div>
                        <div class="d-flex gap-2">
                            <button type="submit" name="import_settings" class="btn btn-outline-primary btn-sm">Import Settings JSON</button>
                            <div class="form-text">Import will only write valid keys (letters, numbers, - and _).</div>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Generic key/value settings editor -->
        <div class="col-12">
            <div class="card shadow">
                <div class="card-body">
                    <h5 class="card-title">Key / Value Settings</h5>

                    <div class="row">
                        <div class="col-md-6">
                            <form method="POST" class="d-flex gap-2">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                                <input name="key" class="form-control" placeholder="setting_key (letters, numbers, - _)" required>
                                <input name="value" class="form-control" placeholder="value">
                                <button type="submit" name="kv_submit" class="btn btn-primary">Save</button>
                            </form>
                        </div>
                        <div class="col-md-6">
                            <div class="kv-list border rounded p-2">
                                <?php if (count($settings) === 0): ?>
                                    <div class="small-muted">No settings saved yet.</div>
                                <?php else: ?>
                                    <table class="table table-sm mb-0">
                                        <thead><tr><th>Key</th><th>Value</th></tr></thead>
                                        <tbody>
                                            <?php foreach ($settings as $k => $v): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($k); ?></td>
                                                    <td style="max-width:60%;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?php echo htmlspecialchars($v); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="form-text mt-2">Be careful with keys used by the app (header_logo, header_name, theme, primary_color). Admin-only access.</div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
/* Small preview for uploaded logo file */
document.addEventListener('DOMContentLoaded', function() {
    const logoInput = document.querySelector('input[name="logo"]');
    if (!logoInput) return;
    logoInput.addEventListener('change', function() {
        const file = this.files[0];
        if (!file) return;
        if (!file.type.startsWith('image/')) return;
        const reader = new FileReader();
        reader.onload = function(e) {
            let img = document.querySelector('.preview-logo');
            if (!img) {
                img = document.createElement('img');
                img.className = 'preview-logo border';
                logoInput.parentNode.insertBefore(img, logoInput);
            }
            img.src = e.target.result;
        };
        reader.readAsDataURL(file);
    });
});
</script>

<?php include 'footer.php'; ?>
</body>
</html>