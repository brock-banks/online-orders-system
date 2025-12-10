<?php
require_once 'config.php';
require_once 'functions.php';

if (!isLoggedIn()) {
    redirect('index.php');
}

// Basic CSRF token helper (stored in session)
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(24));
}
$csrfToken = $_SESSION['csrf_token'];

$uid = (int)($_SESSION['user']['id'] ?? 0);
if ($uid <= 0) {
    // defensively redirect to login
    redirect('index.php');
}

// Messages (flash)
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

// Load latest user info from DB
$user = query("SELECT * FROM users WHERE id = ? LIMIT 1", [$uid])->fetch();
if (!$user) {
    $_SESSION['flash'] = ['type' => 'danger', 'text' => 'User not found.'];
    redirect('index.php');
}

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Basic CSRF validation
    $postedToken = $_POST['csrf_token'] ?? '';
    if (!hash_equals($csrfToken, (string)$postedToken)) {
        $_SESSION['flash'] = ['type' => 'danger', 'text' => 'Invalid CSRF token.'];
        header('Location: profile.php');
        exit;
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'update_profile') {
        $fullname = trim($_POST['fullname'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');

        // Basic validation
        if ($fullname === '' || $email === '') {
            $_SESSION['flash'] = ['type' => 'danger', 'text' => 'Full name and email are required.'];
            header('Location: profile.php');
            exit;
        }

        // Check email uniqueness (except current user)
        $exists = query("SELECT id FROM users WHERE email = ? AND id != ? LIMIT 1", [$email, $uid])->fetch();
        if ($exists) {
            $_SESSION['flash'] = ['type' => 'danger', 'text' => 'Email is already in use by another account.'];
            header('Location: profile.php');
            exit;
        }

        // Avatar upload handling (optional)
        $avatarPath = $user['avatar'] ?? null; // existing
        if (isset($_FILES['avatar']) && is_uploaded_file($_FILES['avatar']['tmp_name'])) {
            $file = $_FILES['avatar'];
            $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
            $maxBytes = 2 * 1024 * 1024; // 2MB

            if ($file['error'] !== UPLOAD_ERR_OK) {
                $_SESSION['flash'] = ['type' => 'danger', 'text' => 'Avatar upload failed.'];
                header('Location: profile.php');
                exit;
            }
            if ($file['size'] > $maxBytes) {
                $_SESSION['flash'] = ['type' => 'danger', 'text' => 'Avatar must be 2MB or smaller.'];
                header('Location: profile.php');
                exit;
            }
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);

            if (!isset($allowed[$mime])) {
                $_SESSION['flash'] = ['type' => 'danger', 'text' => 'Unsupported avatar format. Use PNG, JPG or WEBP.'];
                header('Location: profile.php');
                exit;
            }

            // Ensure upload directory exists
            $uploadDir = __DIR__ . '/uploads/avatars';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

            $ext = $allowed[$mime];
            $safeName = 'user_' . $uid . '.' . $ext;
            $destPath = $uploadDir . '/' . $safeName;

            if (!move_uploaded_file($file['tmp_name'], $destPath)) {
                $_SESSION['flash'] = ['type' => 'danger', 'text' => 'Failed to save avatar file.'];
                header('Location: profile.php');
                exit;
            }

            // Optionally, set restrictive permissions
            @chmod($destPath, 0644);

            // Save relative path
            $avatarPath = 'uploads/avatars/' . $safeName;
        }

        // Update DB
        query("UPDATE users SET fullname = ?, email = ?, phone = ?, avatar = ? WHERE id = ?", [
            $fullname, $email, $phone, $avatarPath, $uid
        ]);

        // Refresh session user row
        $newUser = query("SELECT * FROM users WHERE id = ? LIMIT 1", [$uid])->fetch();
        if ($newUser) {
            $_SESSION['user'] = $newUser;
        }

        $_SESSION['flash'] = ['type' => 'success', 'text' => 'Profile updated successfully.'];
        header('Location: profile.php');
        exit;
    }

    if ($action === 'change_password') {
        $current = $_POST['current_password'] ?? '';
        $new = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        if ($new === '' || $confirm === '' || $current === '') {
            $_SESSION['flash'] = ['type' => 'danger', 'text' => 'All password fields are required.'];
            header('Location: profile.php');
            exit;
        }
        if ($new !== $confirm) {
            $_SESSION['flash'] = ['type' => 'danger', 'text' => 'New password and confirm do not match.'];
            header('Location: profile.php');
            exit;
        }
        if (strlen($new) < 8) {
            $_SESSION['flash'] = ['type' => 'danger', 'text' => 'Password must be at least 8 characters.'];
            header('Location: profile.php');
            exit;
        }

        // Verify current password against stored hash: detect column name used
        $storedHash = $user['password'] ?? $user['password_hash'] ?? null;
        if (!$storedHash) {
            $_SESSION['flash'] = ['type' => 'danger', 'text' => 'Password verification not possible (no stored hash). Contact admin.'];
            header('Location: profile.php');
            exit;
        }

        if (!password_verify($current, $storedHash)) {
            $_SESSION['flash'] = ['type' => 'danger', 'text' => 'Current password is incorrect.'];
            header('Location: profile.php');
            exit;
        }

        // Update with new password hash
        $newHash = password_hash($new, PASSWORD_DEFAULT);
        // Try to update appropriate column name
        if (array_key_exists('password', $user)) {
            query("UPDATE users SET password = ? WHERE id = ?", [$newHash, $uid]);
        } else {
            query("UPDATE users SET password_hash = ? WHERE id = ?", [$newHash, $uid]);
        }

        $_SESSION['flash'] = ['type' => 'success', 'text' => 'Password updated successfully.'];
        header('Location: profile.php');
        exit;
    }
}

// Re-load user in case of updates
$user = query("SELECT * FROM users WHERE id = ? LIMIT 1", [$uid])->fetch();
$avatarUrl = $user['avatar'] ?? '';
if ($avatarUrl && !preg_match('#^https?://#i', $avatarUrl)) {
    // make relative urls safe for src attribute
    $avatarUrl = htmlspecialchars($avatarUrl);
}
?>
<?php include 'header.php'; ?>

<div class="container mt-4">
    <div class="row justify-content-center">
        <div class="col-md-10">

            <?php if ($flash): ?>
                <div class="alert alert-<?php echo htmlspecialchars($flash['type'] ?? 'info'); ?> alert-dismissible">
                    <?php echo htmlspecialchars($flash['text'] ?? ''); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <div class="card mb-4">
                <div class="card-body d-flex align-items-center">
                    <div class="me-4 text-center">
                        <?php if ($avatarUrl): ?>
                            <img src="<?php echo $avatarUrl; ?>" alt="Avatar" class="rounded-circle" style="width:96px;height:96px;object-fit:cover;">
                        <?php else: ?>
                            <div class="rounded-circle bg-secondary text-white d-inline-flex align-items-center justify-content-center" style="width:96px;height:96px;font-size:28px;">
                                <?php echo strtoupper(substr($user['username'] ?? 'U', 0, 1)); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div>
                        <h4 class="mb-0"><?php echo htmlspecialchars($user['fullname'] ?? $user['username']); ?></h4>
                        <div class="text-muted"><?php echo htmlspecialchars($user['email'] ?? ''); ?></div>
                        <div class="mt-2 small">
                            Role: <?php echo htmlspecialchars($user['role'] ?? 'user'); ?>
                            <?php if (!empty($user['admin_role'])): ?>
                                / <?php echo htmlspecialchars($user['admin_role']); ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row gy-4">
                <div class="col-md-7">
                    <div class="card">
                        <div class="card-header">
                            Edit Profile
                        </div>
                        <div class="card-body">
                            <form method="POST" enctype="multipart/form-data" novalidate>
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                                <input type="hidden" name="action" value="update_profile">

                                <div class="mb-3">
                                    <label class="form-label">Full name</label>
                                    <input type="text" name="fullname" class="form-control" required value="<?php echo htmlspecialchars($user['fullname'] ?? $user['username']); ?>">
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Email</label>
                                    <input type="email" name="email" class="form-control" required value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>">
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Phone</label>
                                    <input type="text" name="phone" class="form-control" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>">
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Avatar (PNG/JPG/WEBP, ≤ 2MB)</label>
                                    <input type="file" name="avatar" accept="image/png, image/jpeg, image/webp" class="form-control form-control-sm">
                                    <?php if ($avatarUrl): ?>
                                        <div class="form-text">Uploading a new avatar will replace the current one.</div>
                                    <?php endif; ?>
                                </div>

                                <button class="btn btn-primary">Save changes</button>
                            </form>
                        </div>
                    </div>

                    <div class="card mt-3">
                        <div class="card-header">
                            Change Password
                        </div>
                        <div class="card-body">
                            <form method="POST" novalidate>
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                                <input type="hidden" name="action" value="change_password">

                                <div class="mb-3">
                                    <label class="form-label">Current password</label>
                                    <input type="password" name="current_password" class="form-control" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">New password</label>
                                    <input type="password" name="new_password" class="form-control" required>
                                    <div class="form-text">Minimum 8 characters.</div>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Confirm new password</label>
                                    <input type="password" name="confirm_password" class="form-control" required>
                                </div>

                                <button class="btn btn-warning">Change password</button>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Side info -->
                <div class="col-md-5">
                    <div class="card">
                        <div class="card-header">Account Info</div>
                        <div class="card-body">
                            <dl class="row">
                                <dt class="col-5">Username</dt>
                                <dd class="col-7"><?php echo htmlspecialchars($user['username'] ?? ''); ?></dd>

                                <dt class="col-5">Email</dt>
                                <dd class="col-7"><?php echo htmlspecialchars($user['email'] ?? ''); ?></dd>

                                <dt class="col-5">Phone</dt>
                                <dd class="col-7"><?php echo htmlspecialchars($user['phone'] ?? '-'); ?></dd>

                                <dt class="col-5">Joined</dt>
                                <dd class="col-7"><?php
                                    if (!empty($user['created_at'])) echo htmlspecialchars($user['created_at']);
                                    else echo '-';
                                ?></dd>

                                <dt class="col-5">Last login</dt>
                                <dd class="col-7"><?php
                                    if (!empty($user['last_login'])) echo htmlspecialchars($user['last_login']);
                                    else echo '-';
                                ?></dd>
                            </dl>
                        </div>
                    </div>

                    <div class="card mt-3">
                        <div class="card-header">Security</div>
                        <div class="card-body">
                            <p class="small text-muted">For better security, use a strong unique password. Consider enabling two-factor authentication if available.</p>
                        </div>
                    </div>

                </div>
            </div>

        </div>
    </div>
</div>

<?php include 'footer.php'; ?>