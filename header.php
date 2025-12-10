<?php
require_once 'config.php';
require_once 'functions.php';

// Allow pages (e.g., login) to skip auth by defining SKIP_AUTH before include
if (!defined('SKIP_AUTH') && !isLoggedIn()) {
    redirect('index.php');
}

// Load settings once
$rows = query("SELECT key_name, value FROM settings")->fetchAll();
$settings = [];
foreach ($rows as $r) $settings[$r['key_name']] = $r['value'] ?? null;

// Defaults
$headerLogo = $settings['header_logo'] ?? '';
$headerName = $settings['header_name'] ?? 'Order System';
$currentTheme = $settings['theme'] ?? 'light';
$primaryColor = $settings['primary_color'] ?? ''; // new setting (hex color)

// Navbar classes
// For dark theme we rely on Bootstrap dark classes. For light theme we'll use navbar-light and inject
// a CSS variable that drives colors so the light theme doesn't force Bootstrap's .bg-primary blue.
$navbarThemeClass = $currentTheme === 'dark' ? 'navbar-dark bg-dark' : 'navbar-light';
$brandTextClass = $currentTheme === 'dark' ? 'text-light' : 'text-dark';

// Current user
$currentUser = $_SESSION['user'] ?? null;
$currentUsername = $currentUser['username'] ?? '';
$currentUserRole = $currentUser['role'] ?? 'user';
$adminRole = $currentUser['admin_role'] ?? null;

// Pending notifications count (safe)
try {
    $thresholdDate = (new DateTime())->modify('-29 days')->format('Y-m-d');
    $pendingRow = query("SELECT COUNT(*) AS c FROM orders WHERE status != 'delivered' AND date <= ?", [$thresholdDate])->fetch();
    $pendingCount = (int)($pendingRow['c'] ?? 0);
} catch (Exception $e) {
    error_log("Header notifications error: " . $e->getMessage());
    $pendingCount = 0;
}

// Build inline style to inject CSS variables (only for light theme, preserve dark theme defaults)
$navbarInlineStyle = '';
$rootCssVars = '';
if ($currentTheme !== 'dark') {
    // If admin provided a primary color (e.g., #1fa2b3), set CSS variable --primary and compute a fallback dark variant.
    $primary = '#007bff';
    $primaryDark = '#0056b3';
    if (!empty($primaryColor) && preg_match('/^#?[0-9a-fA-F]{6}$/', $primaryColor)) {
        $primary = strpos($primaryColor, '#') === 0 ? $primaryColor : '#' . $primaryColor;
        // compute a darker variant (simple approach: reduce brightness)
        // convert hex to rgb, darken by 18%
        $r = hexdec(substr($primary, 1, 2));
        $g = hexdec(substr($primary, 3, 2));
        $b = hexdec(substr($primary, 5, 2));
        $factor = 0.82;
        $rd = max(0, floor($r * $factor));
        $gd = max(0, floor($g * $factor));
        $bd = max(0, floor($b * $factor));
        $primaryDark = sprintf('#%02x%02x%02x', $rd, $gd, $bd);
    }
    $rootCssVars = "<style>:root { --primary: {$primary}; --primary-dark: {$primaryDark}; }</style>";
    // For the navbar we will not use Bootstrap bg-primary to avoid hard-coded blue.
    // We'll rely on CSS variable usage in light-theme.css and apply navbar-light class for contrast.
    $navbarInlineStyle = ''; // no per-element inline background needed because CSS variables will be global
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?php echo htmlspecialchars($headerName); ?></title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="light-theme.css" rel="stylesheet">

<?php
// Inject root CSS variables for primary color (light theme). This must appear after theme CSS so vars take effect.
echo $rootCssVars;
?>

<style>
/* compact header styles */
.navbar-brand img { max-height:36px; width:auto; }
.brand-title { font-weight:600; }
.navbar .nav-link { font-weight:500; }
.badge-notify { margin-left:6px; }
.header-search { max-width:360px; transition: width .18s ease; }
@media (max-width:575px) {
  .header-username { display:none; }
  .header-search { max-width:160px; }
}
/* Fallback dropdown styles: ensure dropdown-menu positioned correctly when JS toggles 'show' */
.dropdown.show > .dropdown-menu {
  display: block;
  opacity: 1;
  transform: none;
}
</style>
</head>
<body>
<nav class="navbar navbar-expand-md <?php echo $navbarThemeClass; ?> shadow-sm"<?php echo $navbarInlineStyle ? " {$navbarInlineStyle}" : ''; ?>>
  <div class="container-fluid">
    <a class="navbar-brand d-flex align-items-center" href="orders.php" aria-label="<?php echo htmlspecialchars($headerName); ?>">
      <?php if (!empty($headerLogo)): ?>
        <img src="<?php echo htmlspecialchars($headerLogo); ?>" alt="" loading="lazy" class="me-2">
      <?php endif; ?>
      <span class="<?php echo $brandTextClass; ?> brand-title"><?php echo htmlspecialchars($headerName); ?></span>
    </a>

    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNavbar" aria-controls="mainNavbar" aria-expanded="false" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>

    <div class="collapse navbar-collapse" id="mainNavbar">
      <ul class="navbar-nav me-auto mb-2 mb-md-0 align-items-center">
        <li class="nav-item"><a class="nav-link" href="orders.php">Orders</a></li>
        <li class="nav-item"><a class="nav-link" href="place_order.php">Place Order</a></li>
        <li class="nav-item"><a class="nav-link" href="reprint_receipt.php"> Receipt</a></li>

        <?php if (isAdmin()): ?>
          <li class="nav-item dropdown">
            <a class="nav-link dropdown-toggle" href="#" id="manageDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">Manage</a>
            <ul class="dropdown-menu" aria-labelledby="manageDropdown">
              <li><a class="dropdown-item" href="items.php">Products</a></li>
              <li><a class="dropdown-item" href="map.php">Places</a></li>
              <li><a class="dropdown-item" href="add_receive.php">Receives</a></li>
            </ul>
          </li>

          <li class="nav-item dropdown">
            <a class="nav-link dropdown-toggle" href="#" id="reportDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">Reports</a>
            <ul class="dropdown-menu" aria-labelledby="reportDropdown">
              <li><a class="dropdown-item" href="view_orders.php">Daily Report</a></li>
              <li><a class="dropdown-item" href="report.php">General Report</a></li>
              <li><a class="dropdown-item" href="receives_report.php">Receives Report</a></li>
              <li><a class="dropdown-item" href="archive_orders.php">Archive</a></li>
            </ul>
          </li>
        <?php endif; ?>
      </ul>

      <div class="d-flex align-items-center gap-2">
        <div class="d-flex align-items-center">
          <button id="searchToggle" class="btn btn-sm btn-outline-light me-2" type="button" aria-label="Toggle search">🔍</button>
          <form id="headerSearchForm" class="d-none d-md-flex" method="GET" action="orders.php" role="search" aria-label="Search orders">
            <input id="headerSearchInput" name="search" class="form-control form-control-sm header-search" type="search" placeholder="Search orders, invoice, phone..." aria-label="Search orders">
          </form>
        </div>

        <div class="nav-item dropdown">
          <a class="nav-link position-relative <?php echo ($pendingCount>0) ? 'text-danger' : 'text-secondary'; ?>" href="#" id="notifDropdown" data-bs-toggle="dropdown" aria-expanded="false" style="color:inherit;">
            🔔
            <span id="notification-count" class="badge <?php echo $pendingCount>0 ? 'bg-danger' : 'bg-secondary'; ?> badge-notify"><?php echo $pendingCount; ?></span>
          </a>
          <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="notifDropdown">
            <?php if ($pendingCount > 0): ?>
              <li><a class="dropdown-item" href="pending_orders.php">Pending Orders (<?php echo $pendingCount; ?>)</a></li>
            <?php else: ?>
              <li><span class="dropdown-item-text text-muted">No pending orders</span></li>
            <?php endif; ?>
          </ul>
        </div>

        <?php if ($currentUser): ?>
          <div class="nav-item dropdown">
            <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" id="profileDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false" style="color:grey;">
              <span class="header-username me-2"><?php echo htmlspecialchars($currentUsername); ?></span>
              <span class="small text-muted" style="opacity:.9">▾</span>
            </a>
            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="profileDropdown">
              <li class="dropdown-item-text small text-muted px-3">Role: <?php echo htmlspecialchars($currentUserRole . ($adminRole ? " / $adminRole" : '')); ?></li>
              <li><hr class="dropdown-divider"></li>
              <li><a class="dropdown-item" href="profile.php">Profile</a></li>
              <?php if (isAdmin()): ?><li><a class="dropdown-item" href="admin_dashboard.php">Admin Dashboard</a></li><?php endif; ?>
              <li><a class="dropdown-item" href="settings.php">Settings</a></li>
              <li><a class="dropdown-item text-danger" href="logout.php">Logout</a></li>
            </ul>
          </div>
        <?php else: ?>
          <a class="btn btn-outline-light btn-sm" href="index.php">Login</a>
        <?php endif; ?>
      </div>
    </div>
  </div>
</nav>

<!-- Bootstrap bundle: loaded only if bootstrap isn't already present -->
<script>
(function() {
  function hasBootstrap() {
    return typeof bootstrap !== 'undefined' && typeof bootstrap.Dropdown === 'function';
  }

  function initBootstrapBehavior() {
    try {
      // Bootstrap present: let it do the heavy lifting, but ensure aria-expanded toggles update.
      document.querySelectorAll('[data-bs-toggle="dropdown"]').forEach(function(toggle) {
        toggle.addEventListener('shown.bs.dropdown', function() {
          toggle.setAttribute('aria-expanded', 'true');
        });
        toggle.addEventListener('hidden.bs.dropdown', function() {
          toggle.setAttribute('aria-expanded', 'false');
        });
      });
    } catch (err) {
      console.warn('initBootstrapBehavior error', err);
    }
  }

  function fallbackDropdowns() {
    // Minimal dropdown behavior: toggle .show on .dropdown when the toggle is clicked.
    document.addEventListener('click', function(e) {
      var toggle = e.target.closest('[data-bs-toggle="dropdown"], .dropdown-toggle');
      if (toggle) {
        e.preventDefault();
        var dropdown = toggle.closest('.dropdown');
        if (!dropdown) return;
        var isOpen = dropdown.classList.contains('show');
        // close all open dropdowns first
        document.querySelectorAll('.dropdown.show').forEach(function(d) {
          if (d !== dropdown) d.classList.remove('show');
        });
        if (!isOpen) {
          dropdown.classList.add('show');
          toggle.setAttribute('aria-expanded', 'true');
        } else {
          dropdown.classList.remove('show');
          toggle.setAttribute('aria-expanded', 'false');
        }
        return;
      }
      // click outside: close dropdowns
      if (!e.target.closest('.dropdown')) {
        document.querySelectorAll('.dropdown.show').forEach(function(d) {
          d.classList.remove('show');
          var t = d.querySelector('[data-bs-toggle="dropdown"], .dropdown-toggle');
          if (t) t.setAttribute('aria-expanded', 'false');
        });
      }
    });
  }

  function fallbackCollapse() {
    document.querySelectorAll('.navbar-toggler').forEach(function(btn) {
      btn.addEventListener('click', function() {
        var targetSelector = btn.getAttribute('data-bs-target') || btn.getAttribute('data-target');
        if (!targetSelector) return;
        var target = document.querySelector(targetSelector);
        if (!target) return;
        target.classList.toggle('show');
      });
    });
  }

  if (hasBootstrap()) {
    initBootstrapBehavior();
    return;
  }

  var script = document.createElement('script');
  script.src = 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js';
  script.async = true;
  script.onload = function() {
    setTimeout(initBootstrapBehavior, 50);
  };
  script.onerror = function() {
    console.warn('Bootstrap bundle failed to load — applying lightweight fallbacks for dropdowns/collapse.');
    fallbackDropdowns();
    fallbackCollapse();
  };
  document.body.appendChild(script);
  fallbackDropdowns();
  fallbackCollapse();
})();
</script>
</body>
</html>