<?php
require_once 'config.php';
require_once 'functions.php';

if (!isLoggedIn()) {
    redirect('index.php');
}
if (!isAdmin()) {
    die('Access denied. Admins only.');
}

// Determine admin_role for current user. We prefer a per-user admin_role column (if present in the session/user record).
// If you haven't added admin_role to users, default to 'manager'.
$adminRole = $_SESSION['user']['admin_role'] ?? ($_SESSION['user']['role'] === 'admin' ? 'manager' : 'user');

// Default date range: last 30 days
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
</head>
<body>
<?php include 'header.php'; ?>

<div class="container my-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="text-primary">Admin Dashboard</h2>
        <div>
            <?php if ($adminRole === 'superadmin' || $adminRole === 'manager'): ?>
                <a href="export_orders_csv.php?start_date=<?php echo htmlspecialchars($startDate); ?>&end_date=<?php echo htmlspecialchars($endDate); ?>" class="btn btn-success me-2">Export Orders CSV</a>
            <?php endif; ?>
            <a href="view_orders.php" class="btn btn-outline-secondary">View Orders</a>
        </div>
    </div>

    <!-- Filters -->
    <div class="card mb-3">
        <div class="card-body">
            <form id="filterForm" class="row g-2 align-items-end">
                <div class="col-auto">
                    <label class="form-label">Start Date</label>
                    <input type="date" id="start_date" name="start_date" class="form-control" value="<?php echo htmlspecialchars($startDate); ?>">
                </div>
                <div class="col-auto">
                    <label class="form-label">End Date</label>
                    <input type="date" id="end_date" name="end_date" class="form-control" value="<?php echo htmlspecialchars($endDate); ?>">
                </div>
                <div class="col-auto">
                    <label class="form-label d-block">&nbsp;</label>
                    <button type="button" id="applyFilter" class="btn btn-primary">Apply</button>
                </div>
                <div class="col-auto">
                    <label class="form-label d-block">&nbsp;</label>
                    <div class="btn-group">
                        <button type="button" class="btn btn-outline-secondary quick-range" data-days="7">Last 7</button>
                        <button type="button" class="btn btn-outline-secondary quick-range" data-days="30">Last 30</button>
                        <button type="button" class="btn btn-outline-secondary quick-range" data-days="90">Last 90</button>
                    </div>
                </div>
                <div class="col-auto">
                    <label class="form-label">Role</label>
                    <input class="form-control" readonly value="<?php echo htmlspecialchars($adminRole); ?>">
                </div>
            </form>
        </div>
    </div>

    <!-- Summary cards -->
    <div id="summaryRow" class="row g-3 mb-3">
        <!-- Filled by JS based on role -->
    </div>

    <!-- Charts -->
    <div class="row gy-3">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">Orders & Revenue</div>
                <div class="card-body">
                    <canvas id="ordersRevenueChart" style="height:320px;"></canvas>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card mb-3">
                <div class="card-header">Top Users (by orders)</div>
                <div class="card-body">
                    <canvas id="topUsersChart" style="height:240px;"></canvas>
                </div>
            </div>

            <div class="card">
                <div class="card-header">Hour-of-Day Orders</div>
                <div class="card-body">
                    <canvas id="hourOfDayChart" style="height:240px;"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Funnel -->
    <div class="row mt-3">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">Conversion Funnel (placed → delivered)</div>
                <div class="card-body">
                    <canvas id="funnelChart" style="height:200px;"></canvas>
                    <div id="funnelStats" class="mt-3"></div>
                </div>
            </div>
        </div>

        <!-- Recent Orders: only for manager and superadmin -->
        <?php if ($adminRole === 'superadmin' || $adminRole === 'manager'): ?>
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">Recent Orders</div>
                <div class="card-body">
                    <div id="recentOrdersContainer" class="table-responsive">
                        <table class="table table-striped" id="recentOrdersTable">
                            <thead class="table-light">
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
        <?php endif; ?>
    </div>
</div>

<script>
const dashboardUrl = 'dashboard_data.php';
const adminRole = <?php echo json_encode($adminRole); ?>;

function formatCurrency(v) {
    return parseFloat(v).toFixed(2) + ' OMR';
}

async function fetchDashboardData(startDate, endDate) {
    const params = new URLSearchParams({ start: startDate, end: endDate });
    const res = await fetch(dashboardUrl + '?' + params.toString(), { credentials: 'same-origin' });
    if (!res.ok) throw new Error('Failed to load dashboard data');
    return res.json();
}

function renderSummary(stats) {
    const row = document.getElementById('summaryRow');

    // Role-based widgets: 
    // superadmin: all cards
    // manager: main KPIs (orders, revenue, delivered, pending)
    // analyst: charts-only (show condensed KPIs)
    if (adminRole === 'superadmin') {
        row.innerHTML = `
            <div class="col-md-3">
                <div class="card text-center shadow-sm p-3">
                    <h6>Total Orders</h6>
                    <div class="fs-4 fw-bold">${stats.total_orders}</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center shadow-sm p-3">
                    <h6>Total Revenue</h6>
                    <div class="fs-4 fw-bold">${formatCurrency(stats.total_revenue)}</div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card text-center shadow-sm p-3">
                    <h6>Delivered</h6>
                    <div class="fs-4 fw-bold text-success">${stats.delivered}</div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card text-center shadow-sm p-3">
                    <h6>Pending</h6>
                    <div class="fs-4 fw-bold text-warning">${stats.pending}</div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card text-center shadow-sm p-3">
                    <h6>Total Users</h6>
                    <div class="fs-4 fw-bold">${stats.total_users}</div>
                </div>
            </div>
        `;
    } else if (adminRole === 'manager') {
        row.innerHTML = `
            <div class="col-md-4">
                <div class="card text-center shadow-sm p-3">
                    <h6>Total Orders</h6>
                    <div class="fs-4 fw-bold">${stats.total_orders}</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card text-center shadow-sm p-3">
                    <h6>Total Revenue</h6>
                    <div class="fs-4 fw-bold">${formatCurrency(stats.total_revenue)}</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card text-center shadow-sm p-3">
                    <h6>Pending</h6>
                    <div class="fs-4 fw-bold text-warning">${stats.pending}</div>
                </div>
            </div>
        `;
    } else { // analyst or others
        row.innerHTML = `
            <div class="col-md-4">
                <div class="card text-center shadow-sm p-3">
                    <h6>Total Orders</h6>
                    <div class="fs-4 fw-bold">${stats.total_orders}</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card text-center shadow-sm p-3">
                    <h6>Total Revenue</h6>
                    <div class="fs-4 fw-bold">${formatCurrency(stats.total_revenue)}</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card text-center shadow-sm p-3">
                    <h6>Delivered</h6>
                    <div class="fs-4 fw-bold text-success">${stats.delivered}</div>
                </div>
            </div>
        `;
    }
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
                    tension: 0.2,
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
        type: 'pie',
        data: {
            labels,
            datasets: [{ data: counts, backgroundColor: ['#0d6efd','#198754','#ffc107','#dc3545','#6f42c1'] }]
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
    // Represent funnel as horizontal bar (two bars): placed and delivered
    funnelChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: ['Placed', 'Delivered'],
            datasets: [{ label: 'Count', data: [funnel.placed, funnel.delivered], backgroundColor: ['#0d6efd','#198754'] }]
        },
        options: { indexAxis: 'y', responsive: true, scales: { x: { beginAtZero: true } } }
    });

    document.getElementById('funnelStats').innerHTML = `
        <div><strong>Placed:</strong> ${funnel.placed}</div>
        <div><strong>Delivered:</strong> ${funnel.delivered}</div>
        <div><strong>Conversion:</strong> ${funnel.conversion_percent}%</div>
    `;
}

function renderRecentOrders(tableRows) {
    const tbody = document.getElementById('recentOrdersBody');
    if (!tbody) return;
    tbody.innerHTML = '';
    tableRows.forEach(r => {
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td>${r.id}</td>
            <td>${r.invoice_no}</td>
            <td>${r.username ?? r.user_id}</td>
            <td>${r.phone}</td>
            <td>${r.address}</td>
            <td>${parseFloat(r.price).toFixed(2)}</td>
            <td>${r.status}</td>
            <td>${r.date}</td>
        `;
        tbody.appendChild(tr);
    });
}

async function loadAndRender(startDate, endDate) {
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
        alert('Failed to load dashboard data: ' + err.message);
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

// initial load
loadAndRender(document.getElementById('start_date').value, document.getElementById('end_date').value);
</script>

<?php include 'footer.php'; ?>
</body>
</html>