<?php
require_once 'config.php';
require_once 'functions.php';

if (!isLoggedIn()) {
    redirect('index.php');
}
if (!isAdmin()) {
    die('Access denied. Admins only.');
}

$adminRole = $_SESSION['user']['admin_role'] ?? ($_SESSION['user']['role'] === 'admin' ? 'manager' : 'user');

$endDate = $_GET['end_date'] ?? date('Y-m-d');
$startDate = $_GET['start_date'] ?? date('Y-m-d', strtotime('-29 days', strtotime($endDate)));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="admin_dashboard.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <style>
        .dashboard-subtitle {
            color: #6c757d;
            margin-bottom: 0;
        }
        .dashboard-filter-card {
            border-radius: 16px;
        }
        .chart-card canvas {
            max-height: 320px;
        }
        .mini-muted {
            font-size: 0.85rem;
            color: #6c757d;
        }
        .kpi-icon-box {
            width: 42px;
            height: 42px;
            border-radius: 12px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: rgba(13, 110, 253, 0.10);
            color: #0d6efd;
            font-weight: 700;
            margin-bottom: 8px;
        }
        .table-address {
            max-width: 180px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .dashboard-alert {
            display: none;
        }
    </style>
</head>
<body>
<?php include 'header.php'; ?>

<div class="container page-shell">
    <div class="page-title-row">
        <div>
            <h2 class="page-title text-primary">Admin Dashboard</h2>
            <p class="dashboard-subtitle">Overview of orders, revenue, user activity, and delivery performance.</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <?php if ($adminRole === 'superadmin' || $adminRole === 'manager'): ?>
                <a href="export_orders_csv.php?start_date=<?php echo htmlspecialchars($startDate); ?>&end_date=<?php echo htmlspecialchars($endDate); ?>" class="btn btn-success">Export Orders CSV</a>
            <?php endif; ?>
            <a href="view_orders.php" class="btn btn-outline-secondary">View Orders</a>
        </div>
    </div>

    <div id="dashboardError" class="alert alert-danger dashboard-alert"></div>

    <div class="card app-card dashboard-filter-card section-gap">
        <div class="card-body">
            <div class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label">Start Date</label>
                    <input type="date" id="start_date" name="start_date" class="form-control" value="<?php echo htmlspecialchars($startDate); ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">End Date</label>
                    <input type="date" id="end_date" name="end_date" class="form-control" value="<?php echo htmlspecialchars($endDate); ?>">
                </div>
                <div class="col-md-2">
                    <button type="button" id="applyFilter" class="btn btn-primary w-100">Apply</button>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Role</label>
                    <div>
                        <span class="badge bg-light text-dark border px-3 py-2"><?php echo htmlspecialchars($adminRole); ?></span>
                    </div>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Quick Range</label>
                    <div class="d-flex flex-wrap gap-2">
                        <button type="button" class="btn btn-outline-secondary btn-sm quick-range" data-days="7">7D</button>
                        <button type="button" class="btn btn-outline-secondary btn-sm quick-range" data-days="30">30D</button>
                        <button type="button" class="btn btn-outline-secondary btn-sm quick-range" data-days="90">90D</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div id="summaryRow" class="row g-3 section-gap"></div>

    <div class="row g-3">
        <div class="col-lg-8">
            <div class="card app-card chart-card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span>Orders & Revenue</span>
                    <span class="mini-muted">Daily totals in selected range</span>
                </div>
                <div class="card-body">
                    <canvas id="ordersRevenueChart"></canvas>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card app-card chart-card mb-3">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span>Top Users</span>
                    <span class="mini-muted">By order count</span>
                </div>
                <div class="card-body">
                    <canvas id="topUsersChart"></canvas>
                </div>
            </div>

            <div class="card app-card chart-card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span>Hour-of-Day Orders</span>
                    <span class="mini-muted">Distribution by time</span>
                </div>
                <div class="card-body">
                    <canvas id="hourOfDayChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mt-1">
        <div class="col-md-6">
            <div class="card app-card chart-card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span>Conversion Funnel</span>
                    <span class="mini-muted">Placed → Delivered</span>
                </div>
                <div class="card-body">
                    <canvas id="funnelChart" style="max-height:220px;"></canvas>
                    <div id="funnelStats" class="mt-3"></div>
                </div>
            </div>
        </div>

        <?php if ($adminRole === 'superadmin' || $adminRole === 'manager'): ?>
        <div class="col-md-6">
            <div class="card app-card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span>Recent Orders</span>
                    <span class="mini-muted">Latest 20 in range</span>
                </div>
                <div class="card-body">
                    <div class="app-table-wrap">
                        <div class="table-responsive">
                            <table class="table app-table table-sm align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Invoice</th>
                                        <th>User</th>
                                        <th>Phone</th>
                                        <th>Address</th>
                                        <th>Price</th>
                                        <th>Status</th>
                                        <th>Date</th>
                                    </tr>
                                </thead>
                                <tbody id="recentOrdersBody"></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
const dashboardUrl = 'dashboard_data.php';
const adminRole = <?php echo json_encode($adminRole); ?>;

function formatCurrency(v) {
    return parseFloat(v || 0).toFixed(2) + ' OMR';
}

function badgeHtml(status) {
    const s = String(status || '').toLowerCase();
    if (s === 'delivered') {
        return '<span class="badge-status badge-delivered">Delivered</span>';
    }
    return '<span class="badge-status badge-pending">Pending</span>';
}

async function fetchDashboardData(startDate, endDate) {
    const params = new URLSearchParams({ start: startDate, end: endDate });
    const res = await fetch(dashboardUrl + '?' + params.toString(), { credentials: 'same-origin' });
    if (!res.ok) throw new Error('Failed to load dashboard data');
    return res.json();
}

function renderSummary(stats) {
    const row = document.getElementById('summaryRow');

    const cards = [];

    cards.push(`
        <div class="col-md-3">
            <div class="kpi-card">
                <div class="kpi-icon-box">#</div>
                <div class="kpi-label">Total Orders</div>
                <div class="kpi-value">${stats.total_orders}</div>
            </div>
        </div>
    `);

    cards.push(`
        <div class="col-md-3">
            <div class="kpi-card">
                <div class="kpi-icon-box">OMR</div>
                <div class="kpi-label">Total Revenue</div>
                <div class="kpi-value">${formatCurrency(stats.total_revenue)}</div>
            </div>
        </div>
    `);

    if (adminRole === 'superadmin') {
        cards.push(`
            <div class="col-md-2">
                <div class="kpi-card">
                    <div class="kpi-icon-box">✓</div>
                    <div class="kpi-label">Delivered</div>
                    <div class="kpi-value text-success">${stats.delivered}</div>
                </div>
            </div>
        `);
        cards.push(`
            <div class="col-md-2">
                <div class="kpi-card">
                    <div class="kpi-icon-box">!</div>
                    <div class="kpi-label">Pending</div>
                    <div class="kpi-value text-warning">${stats.pending}</div>
                </div>
            </div>
        `);
        cards.push(`
            <div class="col-md-2">
                <div class="kpi-card">
                    <div class="kpi-icon-box">U</div>
                    <div class="kpi-label">Total Users</div>
                    <div class="kpi-value">${stats.total_users}</div>
                </div>
            </div>
        `);
    } else if (adminRole === 'manager') {
        cards.push(`
            <div class="col-md-3">
                <div class="kpi-card">
                    <div class="kpi-icon-box">!</div>
                    <div class="kpi-label">Pending</div>
                    <div class="kpi-value text-warning">${stats.pending}</div>
                </div>
            </div>
        `);
        cards.push(`
            <div class="col-md-3">
                <div class="kpi-card">
                    <div class="kpi-icon-box">✓</div>
                    <div class="kpi-label">Delivered</div>
                    <div class="kpi-value text-success">${stats.delivered}</div>
                </div>
            </div>
        `);
    } else {
        cards.push(`
            <div class="col-md-3">
                <div class="kpi-card">
                    <div class="kpi-icon-box">✓</div>
                    <div class="kpi-label">Delivered</div>
                    <div class="kpi-value text-success">${stats.delivered}</div>
                </div>
            </div>
        `);
        cards.push(`
            <div class="col-md-3">
                <div class="kpi-card">
                    <div class="kpi-icon-box">!</div>
                    <div class="kpi-label">Pending</div>
                    <div class="kpi-value text-warning">${stats.pending}</div>
                </div>
            </div>
        `);
    }

    row.innerHTML = cards.join('');
}

let ordersRevenueChart = null;
let topUsersChart = null;
let hourOfDayChart = null;
let funnelChart = null;

function renderOrdersRevenueChart(labels, ordersData, revenueData) {
    const ctx = document.getElementById('ordersRevenueChart');
    if (ordersRevenueChart) ordersRevenueChart.destroy();
    ordersRevenueChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels,
            datasets: [
                {
                    type: 'line',
                    label: 'Revenue (OMR)',
                    data: revenueData,
                    yAxisID: 'y1',
                    borderColor: '#198754',
                    backgroundColor: '#19875422',
                    tension: 0.25,
                    fill: true
                },
                {
                    type: 'bar',
                    label: 'Orders',
                    data: ordersData,
                    yAxisID: 'y',
                    backgroundColor: '#0d6efd'
                }
            ]
        },
        options: {
            responsive: true,
            interaction: { mode: 'index', intersect: false },
            scales: {
                y: { position: 'left', title: { display: true, text: 'Orders' } },
                y1: { position: 'right', grid: { display: false }, title: { display: true, text: 'Revenue OMR' } }
            }
        }
    });
}

function renderTopUsersChart(labels, counts) {
    const ctx = document.getElementById('topUsersChart');
    if (topUsersChart) topUsersChart.destroy();
    topUsersChart = new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels,
            datasets: [{ data: counts, backgroundColor: ['#0d6efd','#198754','#ffc107','#dc3545','#6f42c1','#20c997','#fd7e14','#6610f2'] }]
        },
        options: { responsive: true }
    });
}

function renderHourOfDay(labels, counts) {
    const ctx = document.getElementById('hourOfDayChart');
    if (hourOfDayChart) hourOfDayChart.destroy();
    hourOfDayChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels,
            datasets: [{ label: 'Orders by Hour', data: counts, backgroundColor: '#0dcaf0' }]
        },
        options: { responsive: true, scales: { y: { beginAtZero: true } } }
    });
}

function renderFunnel(funnel) {
    const ctx = document.getElementById('funnelChart');
    if (funnelChart) funnelChart.destroy();
    funnelChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: ['Placed', 'Delivered'],
            datasets: [{ label: 'Count', data: [funnel.placed, funnel.delivered], backgroundColor: ['#0d6efd','#198754'] }]
        },
        options: { indexAxis: 'y', responsive: true, scales: { x: { beginAtZero: true } } }
    });

    document.getElementById('funnelStats').innerHTML = `
        <div class="row g-2">
            <div class="col-4">
                <div class="border rounded p-2 text-center">
                    <div class="mini-muted">Placed</div>
                    <div class="fw-bold">${funnel.placed}</div>
                </div>
            </div>
            <div class="col-4">
                <div class="border rounded p-2 text-center">
                    <div class="mini-muted">Delivered</div>
                    <div class="fw-bold">${funnel.delivered}</div>
                </div>
            </div>
            <div class="col-4">
                <div class="border rounded p-2 text-center">
                    <div class="mini-muted">Conversion</div>
                    <div class="fw-bold">${funnel.conversion_percent}%</div>
                </div>
            </div>
        </div>
    `;
}

function renderRecentOrders(tableRows) {
    const tbody = document.getElementById('recentOrdersBody');
    if (!tbody) return;

    tbody.innerHTML = '';

    if (!tableRows || tableRows.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="8">
                    <div class="empty-state my-2">
                        <div class="empty-state-title">No recent orders</div>
                        <div>There are no orders in the selected range.</div>
                    </div>
                </td>
            </tr>
        `;
        return;
    }

    tableRows.forEach(r => {
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td>${r.id}</td>
            <td>${r.invoice_no}</td>
            <td>${r.username ?? r.user_id}</td>
            <td>${r.phone}</td>
            <td class="table-address" title="${r.address}">${r.address}</td>
            <td>${parseFloat(r.price || 0).toFixed(2)}</td>
            <td>${badgeHtml(r.status)}</td>
            <td>${r.date}</td>
        `;
        tbody.appendChild(tr);
    });
}

async function loadAndRender(startDate, endDate) {
    const errorBox = document.getElementById('dashboardError');
    errorBox.style.display = 'none';
    errorBox.textContent = '';

    try {
        const data = await fetchDashboardData(startDate, endDate);
        renderSummary(data.stats);
        renderOrdersRevenueChart(data.labels, data.orders, data.revenue);
        renderTopUsersChart(data.top_users.labels, data.top_users.counts);
        renderHourOfDay(data.hours.labels, data.hours.counts);
        renderFunnel(data.funnel);

        if (adminRole === 'superadmin' || adminRole === 'manager') {
            renderRecentOrders(data.recent_orders);
        }
    } catch (err) {
        console.error(err);
        errorBox.textContent = 'Failed to load dashboard data: ' + err.message;
        errorBox.style.display = 'block';
    }
}

document.getElementById('applyFilter').addEventListener('click', function() {
    const s = document.getElementById('start_date').value;
    const e = document.getElementById('end_date').value;
    loadAndRender(s, e);
});

document.querySelectorAll('.quick-range').forEach(btn => {
    btn.addEventListener('click', function() {
        const days = parseInt(this.dataset.days, 10);
        const end = new Date();
        const start = new Date();
        start.setDate(end.getDate() - (days - 1));
        const f = d => d.toISOString().slice(0,10);
        document.getElementById('start_date').value = f(start);
        document.getElementById('end_date').value = f(end);
        loadAndRender(f(start), f(end));
    });
});

loadAndRender(document.getElementById('start_date').value, document.getElementById('end_date').value);
</script>

<?php include 'footer.php'; ?>
</body>
</html>