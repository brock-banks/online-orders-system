<?php
require_once 'config.php';
require_once 'functions.php';

if (!isLoggedIn()) {
    redirect('index.php');
}

$showReceipt = false;
$receiptOrder = null;
$receiptError = null;
$whatsappURL = '';
$qrTextRaw = '';
$receiptUser = null;

$headerLogo = '';
try {
    $row = query("SELECT value FROM settings WHERE key_name = 'header_logo' LIMIT 1")->fetch();
    if ($row && isset($row['value'])) {
        $headerLogo = $row['value'];
    }
} catch (Exception $e) {
    error_log("place_order.php: failed to read header_logo setting: " . $e->getMessage());
    $headerLogo = '';
}

$fragileUrl = (strpos($_SERVER['SCRIPT_NAME'], '/') === 0 ? '' : '/') . 'uploads/fragile.svg';
$shopAddress = 'ALSHAHEEN SHOP';
$shopPhone   = '+968 72202722';

$places = [];
try {
    $places = query("SELECT PlaceID, PlaceName FROM `Map` ORDER BY PlaceName")->fetchAll();
} catch (Exception $e) {
    error_log('Could not load Map table: ' . $e->getMessage());
    $places = [];
}

$users = [];
try {
    $users = query("SELECT id, username FROM users ORDER BY username ASC")->fetchAll();
} catch (Exception $e) {
    error_log('place_order.php: failed to load users: ' . $e->getMessage());
    $users = [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $details = trim($_POST['details'] ?? '');
    $invoiceNo = trim($_POST['invoice_no'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $price = $_POST['price'] ?? 0;
    $selectedUserId = (int)($_POST['selected_user_id'] ?? ($_SESSION['user']['id'] ?? 0));
    $selectedPlaceId = $_POST['place_id'] ?? '';

    if (!empty($selectedPlaceId)) {
        try {
            $placeRow = query("SELECT PlaceName FROM `Map` WHERE PlaceID = ? LIMIT 1", [$selectedPlaceId])->fetch();
            if ($placeRow && !empty($placeRow['PlaceName'])) {
                $address = $placeRow['PlaceName'];
            }
        } catch (Exception $e) {
            error_log('Failed to fetch selected place: ' . $e->getMessage());
        }
    }

    $invoiceNo = trim($invoiceNo);
    $phone = trim($phone);
    $price = is_numeric($price) ? (float)$price : 0.0;

    $phoneNormalized = preg_replace('/[^\d+]/', '', $phone);
    if (substr_count($phoneNormalized, '+') > 1) {
        $phoneNormalized = preg_replace('/[^\d]/', '', $phoneNormalized);
    }
    $phoneForUrl = $phoneNormalized !== '' ? $phoneNormalized : preg_replace('/[^\d]/', '', $phone);

    if ($invoiceNo === '' || $phone === '') {
        $receiptError = "Invoice number and phone are required.";
    } else {
        try {
            if (isset($pdo) && $pdo instanceof PDO) {
                $pdo->beginTransaction();
            }

            query(
                "INSERT INTO orders (user_id, details, invoice_no, phone, address, price) VALUES (?, ?, ?, ?, ?, ?)",
                [$selectedUserId, $details, $invoiceNo, $phone, $address, $price]
            );

            $orderId = null;
            if (isset($pdo) && $pdo instanceof PDO) {
                $orderId = $pdo->lastInsertId();
            }

            if (!$orderId || $orderId === '0') {
                $fallback = query(
                    "SELECT id FROM orders WHERE invoice_no = ? AND user_id = ? ORDER BY date DESC LIMIT 1",
                    [$invoiceNo, $selectedUserId]
                )->fetch();
                if ($fallback && isset($fallback['id'])) {
                    $orderId = $fallback['id'];
                }
            }

            if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
                $pdo->commit();
            }

            if ($orderId) {
                $receiptOrder = query("SELECT * FROM orders WHERE id = ? LIMIT 1", [$orderId])->fetch();
            } else {
                $receiptOrder = query("SELECT * FROM orders WHERE invoice_no = ? ORDER BY date DESC LIMIT 1", [$invoiceNo])->fetch();
            }

            if ($receiptOrder) {
                try {
                    $receiptUser = query("SELECT id, username FROM users WHERE id = ? LIMIT 1", [$receiptOrder['user_id']])->fetch();
                } catch (Exception $e) {
                    error_log('Failed to fetch receipt user: ' . $e->getMessage());
                    $receiptUser = null;
                }

                if (!$receiptUser) {
                    $receiptUser = [
                        'id' => (int)$receiptOrder['user_id'],
                        'username' => 'user-' . (int)$receiptOrder['user_id']
                    ];
                }

                $showReceipt = true;

                $message = "السلام عليكم ورحمة الله وبركاته\n"
                    . "شكرًا لطلبك من [متجر الشاهين للوازم الرحلات والتخييم]!\n"
                    . "رقم طلبك هو: # {$invoiceNo}\n"
                    . "مكان الاستلام: {$address}\n"
                    . "تفاصيل الطلب:\n{$details}\n\n"
                    . "لمزيد من المعلومات أو المتابعة، يمكنك مراسلتنا على\n"
                    . "72202722\n93211636\n\nتحياتنا،\nفريق [ALSHAHEEN ONLINE TEAM]";

                $encodedMessage = rawurlencode($message);
                $phoneForUrl = str_replace(' ', '', $phoneForUrl);
                if ($phoneForUrl === '') {
                    $phoneForUrl = preg_replace('/[^\d]/', '', $phone);
                }
                $whatsappURL = "https://api.whatsapp.com/send?phone={$phoneForUrl}&text={$encodedMessage}";

                $rInvoice = $receiptOrder['invoice_no'] ?? '';
                $rUser = $receiptUser['username'] ?? ('user-' . (int)$receiptOrder['user_id']);

                $qrTextRaw = "Invoice: {$rInvoice}\n"
                    . "User: {$rUser}\n"
                    . "Phone: {$receiptOrder['phone']}\n"
                    . "Address: {$receiptOrder['address']}\n"
                    . "Price: " . number_format((float)($receiptOrder['price'] ?? 0), 2) . "\n"
                    . "Details:\n{$receiptOrder['details']}\n"
                    . "Date: {$receiptOrder['date']}";
            } else {
                $receiptError = "Order was inserted but could not be retrieved.";
            }
        } catch (Exception $e) {
            if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('Place order error: ' . $e->getMessage());
            $receiptError = 'Failed to place order. Please try again or contact admin.';
        }
    }
}
?>
<?php include 'header.php'; ?>
<link href="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/css/tom-select.bootstrap5.min.css" rel="stylesheet">

<style>
   
.place-order-page {
    min-height: calc(100vh - 140px);
    display: flex;
    flex-direction: column;
    
}

.place-order-grid {
    flex: 1;
    min-height: 0;
}

.place-order-col {
    display: flex;
    flex-direction: column;
    gap: 1rem;
    min-height: 0;
}

.vh-card {
    display: flex;
    flex-direction: column;
    min-height: 0;
    overflow: hidden;
}

.vh-card-fill {
    flex: 1;
}

.vh-card .card-body {
    overflow: auto;
    min-height: 0;
}

.action-card .card-body {
    overflow: visible;
}

.item-code-group {
    display: flex;
    gap: 10px;
    align-items: center;
}

.item-code-group .code-input {
    flex: 1;
}

.item-code-group .qty-input {
    width: 110px;
}

.order-line-item {
    display: flex;
    justify-content: space-between;
    align-items: start;
    gap: 10px;
    padding: 10px 12px;
    border: 1px solid #e9ecef;
    border-radius: 10px;
    background: #fff;
}

.order-line-meta {
    flex: 1;
}

.order-lines-wrap {
    max-height: 180px;
    overflow: auto;
}

.compact-help {
    font-size: 0.84rem;
    color: #6c757d;
}

.quick-place-wrap {
    display: flex;
    flex-wrap: wrap;
    gap: .5rem;
}
.page-shell{
    margin-bottom: 2rem;
}

@media (min-width: 992px) {
    .place-order-grid {
        max-height: calc(100vh - 320px);
    }
}

@media (max-width: 991.98px) {
    .place-order-page {
        min-height: auto;
        padding-bottom: 2rem;
    }

    .place-order-grid {
        max-height: none;
    }

    .vh-card {
        min-height: auto;
    }

    .vh-card .card-body {
        overflow: visible;
    }
}

@media (max-width: 576px) {
    .item-code-group {
        flex-direction: column;
        align-items: stretch;
    }

    .item-code-group .qty-input {
        width: 100%;
    }
}
</style>

<div class="container page-shell place-order-page">
    <div class="page-title-row">
        <div>
            <h2 class="page-title text-primary">Place Order</h2>
            <p class="page-subtitle">Create and send an order quickly from one workspace.</p>
        </div>
    </div>

    <?php if (!empty($receiptError)): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($receiptError); ?></div>
    <?php endif; ?>

    <form id="placeOrderForm" method="POST" autocomplete="off" class="place-order-grid">
        <div class="row g-4 h-100">
            <div class="col-lg-7">
                <div class="place-order-col h-100">
                    <div class="card app-card vh-card">
                        <div class="card-header">Order Information</div>
                        <div class="card-body">
                            <div class="mb-3">
                                <label class="form-label">Select User</label>
                                <select id="selected_user_id" name="selected_user_id" class="form-select" required>
                                    <option value="">-- Select a user --</option>
                                    <?php foreach ($users as $u): ?>
                                        <option value="<?php echo (int)$u['id']; ?>"
                                            <?php echo ((int)($_SESSION['user']['id'] ?? 0) === (int)$u['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($u['username']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="compact-help mt-2">Choose the customer for this order.</div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3 mb-md-0">
                                    <label class="form-label">Invoice Number</label>
                                    <input id="invoice_no" name="invoice_no" class="form-control" required value="<?php echo isset($invoiceNo) ? htmlspecialchars($invoiceNo) : ''; ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Phone</label>
                                    <input id="phone" name="phone" class="form-control" required value="<?php echo isset($phone) ? htmlspecialchars($phone) : '+968'; ?>">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card app-card vh-card vh-card-fill">
                        <div class="card-header">Order Items</div>
                        <div class="card-body">
                            <div class="mb-3">
                                <label class="form-label">Add Item by Code</label>
                                <div class="item-code-group">
                                    <input id="item_code_input" type="text" class="form-control code-input" placeholder="Item code (e.g. ABC123)">
                                    <input id="item_qty_input" type="number" class="form-control qty-input" value="1" min="1">
                                    <button type="button" id="addItemBtn" class="btn btn-outline-primary">Add Item</button>
                                </div>
                                <div class="compact-help mt-2">Enter an item code and quantity, then click Add Item.</div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Added Items</label>
                                <div id="orderLinesList" class="order-lines-wrap d-grid gap-2">
                                    <div class="text-muted small" id="noItemsText">No items added yet.</div>
                                </div>
                            </div>

                            <div>
                                <label class="form-label">Order Details</label>
                                <textarea id="details" name="details" rows="3" class="form-control" required><?php echo isset($details) ? htmlspecialchars($details) : ''; ?></textarea>
                                <div class="compact-help mt-2">This field is auto-updated from added items, but you can still edit it manually if needed.</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-5">
                <div class="place-order-col h-100">
                    <div class="card app-card vh-card vh-card-fill">
                        <div class="card-header">Delivery & Pricing</div>
                        <div class="card-body">
                            <div class="mb-3">
                                <label class="form-label">Place (optional)</label>
                                <select id="place_id" name="place_id" class="form-select">
                                    <option value="">-- Select a place --</option>
                                    <?php foreach ($places as $p): ?>
                                        <option value="<?php echo (int)$p['PlaceID']; ?>"><?php echo htmlspecialchars($p['PlaceName']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="compact-help mt-2">Selecting a place will autofill the address automatically.</div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Quick Place Buttons</label>
                                <div class="quick-place-wrap">
                                    <button type="button" class="btn btn-outline-primary quick-place-btn" data-place="Shop1 - قرب نقليات الجيش ">Shop1</button>
                                    <button type="button" class="btn btn-outline-primary quick-place-btn" data-place="Shop2 - المعبيلة السابعة">Shop2</button>
                                    <button type="button" class="btn btn-outline-primary quick-place-btn" data-place="Shop3 - السويق">Shop3</button>
                                    
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Address</label>
                                <textarea id="address" name="address" rows="3" class="form-control" required><?php echo isset($address) ? htmlspecialchars($address) : ''; ?></textarea>
                            </div>

                            <div class="mb-0">
                                <label class="form-label">Price</label>
                                <input id="price" name="price" type="number" step="0.01" class="form-control" value="<?php echo isset($price) ? htmlspecialchars($price) : '0'; ?>" min="0" required>
                            </div>
                        </div>
                    </div>

                    <div class="card app-card action-card">
                        <div class="card-header">Actions</div>
                        <div class="card-body">
                            <div class="d-grid">
                                <button type="submit" id="placeOrderSubmitBtn" class="btn btn-primary btn-lg">Place Order</button>
                            </div>
                            <div class="compact-help mt-3">
                                Review the information, then place the order and send the receipt through WhatsApp.
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>

    <div class="modal fade" id="receiptModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Online Order</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <?php if ($showReceipt && $receiptOrder): ?>
                        <?php
                        $rInvoice = htmlspecialchars($receiptOrder['invoice_no']);
                        $rPhone = htmlspecialchars($receiptOrder['phone']);
                        $rAddressPlain = htmlspecialchars($receiptOrder['address']);
                        $rDate = htmlspecialchars($receiptOrder['date']);
                        $rUsername = htmlspecialchars($receiptUser['username'] ?? ('user-' . (int)$receiptOrder['user_id']));
                        ?>
                        <div id="receipt" class="receipt-card">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <div class="meta text-start" style="flex:1;">
                                    <div style="font-weight:600;font-size:14px;">Invoice: <?php echo $rInvoice; ?></div>
                                    <div style="font-size:11px; color:#666;"><?php echo $rDate; ?></div>
                                </div>
                                <div class="logo-wrap text-end" style="min-width:90px;">
                                    <?php if (!empty($headerLogo)): ?>
                                        <img src="<?php echo htmlspecialchars($headerLogo); ?>" alt="Logo" class="receipt-logo" style="max-height:48px;">
                                    <?php else: ?>
                                        <div style="font-weight:700;font-size:14px;">ALSHAHEEN</div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <table class="table table-borderless mb-2 small">
                                <tr><th style="width:30%;">Customer</th><td><?php echo $rUsername; ?></td></tr>
                                <tr><th>Phone</th><td><?php echo $rPhone; ?></td></tr>
                                <tr><th>Address</th><td style="white-space:pre-line;"><?php echo $rAddressPlain; ?></td></tr>
                            </table>

                            <div class="text-center">
                                <img src="https://api.qrserver.com/v1/create-qr-code/?size=220x220&data=<?php echo urlencode($qrTextRaw); ?>" alt="QR" style="max-width:120px;">
                                <div class="small text-muted mt-1">Scan QR for full details</div>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-warning">Receipt data not available.</div>
                    <?php endif; ?>
                </div>
                <div class="modal-footer flex-column align-items-stretch gap-2">
                    <div class="d-flex gap-2">
                        <button type="button" id="printAndSendBtn" class="btn btn-success flex-fill">Print Label &amp; WhatsApp</button>
                        <button type="button" id="saveAndSendBtn" class="btn btn-primary flex-fill">Send via WhatsApp</button>
                    </div>
                    <small class="text-muted text-center">Print Label opens the shipping label for printing, then opens WhatsApp.</small>
                    <a href="orders.php" class="btn btn-outline-secondary">Back to Orders</a>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/js/tom-select.complete.min.js"></script>

<script>
let orderLines = [];

const addressInput = document.getElementById('address');
const detailsInput = document.getElementById('details');
const orderLinesList = document.getElementById('orderLinesList');
const noItemsText = document.getElementById('noItemsText');

function renderOrderLines() {
    orderLinesList.innerHTML = '';

    if (orderLines.length === 0) {
        orderLinesList.appendChild(noItemsText);
        noItemsText.style.display = 'block';
        return;
    }

    noItemsText.style.display = 'none';

    orderLines.forEach((line, index) => {
        const item = document.createElement('div');
        item.className = 'order-line-item';
        item.innerHTML = `
            <div class="order-line-meta">
                <div><strong>${line.code}</strong> x${line.qty}</div>
                <div class="text-muted small">${line.name}</div>
            </div>
            <button type="button" class="btn btn-sm btn-outline-danger" data-index="${index}">Remove</button>
        `;

        item.querySelector('button').addEventListener('click', function() {
            orderLines.splice(index, 1);
            renderOrderLines();
            syncDetailsFromLines();
        });

        orderLinesList.appendChild(item);
    });
}

function syncDetailsFromLines() {
    if (orderLines.length > 0) {
        detailsInput.value = orderLines.map(line => `${line.code} x${line.qty} - ${line.name}`).join("\n");
    } else {
        detailsInput.value = '';
    }
}

window.fillAddress = function(text) {
    addressInput.value = text;
};

document.querySelectorAll('.quick-place-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        fillAddress(this.dataset.place);
    });
});

function addItemByCode() {
    const codeEl = document.getElementById('item_code_input');
    const qtyEl = document.getElementById('item_qty_input');

    const code = (codeEl.value || '').trim();
    const qty = parseInt(qtyEl.value || '1', 10) || 1;

    if (!code) {
        AppUI.showToast('Please enter an item code.', 'warning');
        codeEl.focus();
        return;
    }

    fetch('fetch_item.php?itemcode=' + encodeURIComponent(code), { credentials: 'same-origin' })
        .then(res => res.json())
        .then(data => {
            if (!data || data.error || !data.itemcode) {
                AppUI.showToast(data && data.error ? data.error : 'Item not found.', 'danger');
                return;
            }

            orderLines.push({
                code: data.itemcode,
                name: data.itemname || '',
                qty: qty
            });

            renderOrderLines();
            syncDetailsFromLines();

            codeEl.value = '';
            qtyEl.value = '1';
            codeEl.focus();
            AppUI.showToast('Item added.', 'success');
        })
        .catch(err => {
            console.warn('fetch_item failed:', err);
            AppUI.showToast('Failed to fetch item. Please try again.', 'danger');
        });
}

document.getElementById('addItemBtn').addEventListener('click', addItemByCode);

document.getElementById('item_code_input').addEventListener('keydown', function(e) {
    if (e.key === 'Enter') {
        e.preventDefault();
        addItemByCode();
    }
});

document.getElementById('item_qty_input').addEventListener('keydown', function(e) {
    if (e.key === 'Enter') {
        e.preventDefault();
        addItemByCode();
    }
});

document.addEventListener('DOMContentLoaded', function () {
    try {
        const userEl = document.getElementById('selected_user_id');
        if (userEl) {
            new TomSelect('#selected_user_id', {
                create: false,
                sortField: {
                    field: "text",
                    direction: "asc"
                }
            });
        }
    } catch (err) {
        console.warn('User select init failed', err);
    }

    try {
        const placeEl = document.getElementById('place_id');
        if (placeEl) {
            const ts = new TomSelect('#place_id', {
                valueField: 'id',
                labelField: 'text',
                searchField: 'text',
                preload: false,
                load: function(query, callback) {
                    if (!query.length) return callback();
                    fetch('map_search.php?q=' + encodeURIComponent(query), { credentials: 'same-origin' })
                        .then(r => r.json())
                        .then(json => callback(json.results || []))
                        .catch(err => {
                            console.error('Place search error', err);
                            callback();
                        });
                },
                render: {
                    option: function(item, escape) { return '<div>' + escape(item.text) + '</div>'; },
                    item: function(item, escape) { return '<div>' + escape(item.text) + '</div>'; }
                },
                maxOptions: 100,
                allowEmptyOption: true,
                create: false
            });

            ts.on('change', function(value) {
                if (!value) return;
                const opt = ts.options[value];
                if (opt && opt.text) {
                    addressInput.value = opt.text;
                    return;
                }

                fetch('map_search.php?id=' + encodeURIComponent(value), { credentials: 'same-origin' })
                    .then(r => r.json())
                    .then(json => {
                        if (json.results && json.results[0] && json.results[0].text) {
                            addressInput.value = json.results[0].text;
                        }
                    })
                    .catch(err => console.warn('Failed to fetch place name by id', err));
            });
        }
    } catch (err) {
        console.warn('TomSelect init failed', err);
    }

    renderOrderLines();

    const form = document.getElementById('placeOrderForm');
    const submitBtn = document.getElementById('placeOrderSubmitBtn');

    form.addEventListener('submit', function() {
        AppUI.setButtonLoading(submitBtn, true, 'Placing Order...');
    });
});
</script>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const showReceipt = <?php echo $showReceipt ? 'true' : 'false'; ?>;
    const whatsappURL = <?php echo json_encode($whatsappURL); ?>;
    const qrTextPHP = <?php echo json_encode($qrTextRaw); ?>;
    const fragileUrlJS = <?php echo json_encode($fragileUrl); ?>;
    const shopAddressJS = <?php echo json_encode($shopAddress); ?>;
    const shopPhoneJS = <?php echo json_encode($shopPhone); ?>;
    const headerLogoJS = <?php echo json_encode($headerLogo); ?>;

    const receiptData = <?php
        $r = $receiptOrder ?? [];
        echo json_encode([
            'invoice'  => $r['invoice_no'] ?? '',
            'date'     => $r['date'] ?? '',
            'order_id' => $r['id'] ?? '',
            'phone'    => $r['phone'] ?? '',
            'address'  => $r['address'] ?? '',
        ]);
    ?>;

    if (!showReceipt) return;

    const receiptModalEl = document.getElementById('receiptModal');
    const receiptModal = new bootstrap.Modal(receiptModalEl, { backdrop: 'static', keyboard: false });
    receiptModal.show();

    (function() {
        function loadQRious() {
            return new Promise((resolve, reject) => {
                if (window.QRious) return resolve(window.QRious);
                const s = document.createElement('script');
                s.src = 'https://cdnjs.cloudflare.com/ajax/libs/qrious/4.0.2/qrious.min.js';
                s.async = true;
                s.onload = () => resolve(window.QRious);
                s.onerror = () => reject(new Error('Failed to load QRious library'));
                document.head.appendChild(s);
            });
        }

        async function generateQRDataURL(text, size = 500) {
            try {
                const QRious = await loadQRious();
                const qr = new QRious({ value: text, size: size });
                return qr.toDataURL('image/png');
            } catch (e) {
                return 'https://api.qrserver.com/v1/create-qr-code/?size=' + size + 'x' + size + '&data=' + encodeURIComponent(text);
            }
        }

        function waitForImages(context, timeout = 3000) {
            return new Promise((resolve) => {
                try {
                    let imgs;
                    if (context && context.document && context.document.images) imgs = Array.from(context.document.images);
                    else if (context && context.getElementsByTagName) imgs = Array.from(context.getElementsByTagName('img'));
                    else { resolve(); return; }

                    if (imgs.length === 0) { resolve(); return; }

                    let remaining = imgs.length;
                    let finished = false;

                    function one() {
                        if (finished) return;
                        remaining--;
                        if (remaining <= 0) { finished = true; resolve(); }
                    }

                    imgs.forEach(img => {
                        try {
                            if (img.complete && img.naturalWidth > 0) one();
                            else {
                                img.addEventListener('load', one, { once: true });
                                img.addEventListener('error', one, { once: true });
                            }
                        } catch (e) {
                            one();
                        }
                    });

                    setTimeout(() => { if (!finished) { finished = true; resolve(); } }, timeout);
                } catch (e) {
                    resolve();
                }
            });
        }

        function escapeHtml(s) {
            if (s == null) return '';
            return String(s).replace(/[&<>"']/g, function(m) {
                return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m];
            });
        }

        function buildPrintDocumentHtml(data, qrDataUrl) {
            const invoice = data.invoice || '';
            const humanCode = 'INV ' + invoice;

            const css = `
            <style>
              @page { size: 101.6mm 101.6mm; margin: 4mm; }
              html, body { margin:0; padding:0; background:#fff; color:#000; -webkit-print-color-adjust:exact; }
              body { font-family: Arial, Helvetica, sans-serif; font-size:10px; line-height:1.2; }
              .label-frame {
                width: calc(101.6mm - 8mm);
                height: calc(101.6mm - 8mm);
                border: 1px solid #000;
                border-radius: 3mm;
                box-sizing: border-box;
                padding: 3mm;
                display:flex;
                flex-direction:column;
                justify-content:flex-start;
              }
              .logo-area { text-align:center; margin-bottom:3mm; }
              .logo-area img { max-height: 16mm; width:auto; }
              .logo-text { font-weight:700; font-size:13px; }
              .from-to { display:flex; justify-content:space-between; gap:3mm; font-size:9px; }
              .from, .to { flex:1; border-top:1px solid #000; padding-top:1.2mm; }
              .from-title, .to-title { font-weight:700; margin-bottom:0.8mm; }
              .mid-sep { border-top:1px solid #000; margin:2mm 0 1.5mm; }
              .middle-title { text-align:center; font-weight:700; font-size:12px; margin-bottom:2mm; }
              .mid-band { display:flex; flex:1; align-items:flex-start; gap:3mm; }
              .fragile-block { width: 22mm; border:1px solid #000; box-sizing:border-box; padding:1.5mm 1mm; text-align:center; font-size:9px; }
              .fragile-block .label { font-weight:700; margin-bottom:1mm; }
              .fragile-block img { max-width: 16mm; max-height: 16mm; }
              .code-bar-block { flex:1; display:flex; flex-direction:column; align-items:center; justify-content:flex-start; gap:1.5mm; }
              .human-code { font-size:9px; letter-spacing:1px; }
              .barcode { width:100%; height:16mm; display:flex; align-items:flex-end; justify-content:center; overflow:hidden; }
              .barcode-inner { width:80%; height:100%; display:flex; align-items:flex-end; }
              .barcode-bar { background:#000; margin-right:1px; }
              .qr-block { width:22mm; text-align:center; }
              .qr-block img { max-width:22mm; max-height:22mm; }
              .bottom-row { margin-top:2mm; border-top:1px solid #000; padding-top:1.5mm; display:flex; justify-content:space-between; font-size:9px; }
              .order-id { font-weight:700; }
              .instr-title { font-weight:700; margin-bottom:0.5mm; }
            </style>
            `;

            const fromBlock = `
              <div class="from">
                <div class="from-title">From:</div>
                <div>${escapeHtml(shopAddressJS)}</div>
                <div>${escapeHtml(shopPhoneJS)}</div>
              </div>
            `;

            const toBlock = `
              <div class="to">
                <div class="to-title">To:</div>
                <div>${escapeHtml(data.phone || '')}</div>
                <div>${escapeHtml(data.address || '')}</div>
              </div>
            `;

            const logoBlock = headerLogoJS
                ? `<img src="${escapeHtml(headerLogoJS)}" alt="Logo">`
                : `<div class="logo-text">ALSHAHEEN</div>`;

            let barsHtml = '';
            const codeStr = (invoice || '000000').replace(/\s+/g, '');
            for (let i = 0; i < codeStr.length; i++) {
                const ch = codeStr.charCodeAt(i);
                const w = 1 + (ch % 3);
                const h = 40 + (ch % 12);
                barsHtml += `<div class="barcode-bar" style="width:${w}px;height:${h}px"></div>`;
            }

            const orderIdText = data.order_id ? String(data.order_id).padStart(5, '0') : '';

            const bodyHtml = `
              <div class="label-frame">
                <div class="logo-area">${logoBlock}</div>
                <div class="from-to">
                  ${fromBlock}
                  ${toBlock}
                </div>
                <div class="mid-sep"></div>
                <div class="middle-title">ONLINE ORDER</div>
                <div class="mid-band">
                  <div class="fragile-block">
                    <div class="label">FRAGILE</div>
                    <img src="${escapeHtml(fragileUrlJS)}" alt="Fragile">
                  </div>
                  <div class="code-bar-block">
                    <div class="human-code">${escapeHtml(humanCode)}</div>
                    <div class="barcode"><div class="barcode-inner">${barsHtml}</div></div>
                  </div>
                  <div class="qr-block">
                    <img src="${qrDataUrl}" alt="QR">
                  </div>
                </div>
                <div class="bottom-row">
                  <div class="order-id">Order ID: ${escapeHtml(orderIdText)}</div>
                  <div class="instr">
                    <div class="instr-title">Delivery instructions</div>
                    <div>Handle with care – fragile</div>
                  </div>
                </div>
              </div>
            `;

            return `<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width">${css}</head><body>${bodyHtml}</body></html>`;
        }

        async function doPrint() {
            const qrDataUrl = await generateQRDataURL(qrTextPHP, 500);
            const html = buildPrintDocumentHtml(receiptData || {}, qrDataUrl);

            const popup = window.open('', '_blank', 'toolbar=0,location=0,menubar=0,width=700,height=900');
            if (popup) {
                popup.document.open();
                popup.document.write(html);
                popup.document.close();
                try { await waitForImages(popup, 3000); } catch (e) {}
                try { popup.focus(); popup.print(); } catch (e) { console.warn(e); }
                setTimeout(() => { try { popup.close(); } catch (e) {} }, 800);
            } else {
                try {
                    const iframe = document.createElement('iframe');
                    iframe.style.position = 'fixed';
                    iframe.style.left = '-9999px';
                    iframe.style.top = '0';
                    document.body.appendChild(iframe);
                    const idoc = iframe.contentWindow.document;
                    idoc.open();
                    idoc.write(html);
                    idoc.close();
                    try { await waitForImages(idoc, 3000); } catch (e) {}
                    try { iframe.contentWindow.focus(); iframe.contentWindow.print(); } catch (e) { console.warn(e); }
                    setTimeout(() => { try { document.body.removeChild(iframe); } catch (e) {} }, 1000);
                } catch (e) {
                    console.error('Print failed', e);
                    alert('Printing failed. Please check popup blocker.');
                    return;
                }
            }

            if (whatsappURL) {
                try { window.open(whatsappURL, '_blank'); } catch (e) { console.warn(e); }
            }
            setTimeout(() => { window.location.href = 'place_order.php'; }, 900);
        }

        const printBtn = document.getElementById('printAndSendBtn');
        if (printBtn) {
            printBtn.addEventListener('click', function() {
                doPrint().catch(err => {
                    console.error(err);
                    alert('Printing failed');
                });
            }, { once: true });
        }

        const saveBtn = document.getElementById('saveAndSendBtn');
        if (saveBtn) {
            saveBtn.addEventListener('click', function() {
                try {
                    if (whatsappURL) {
                        window.open(whatsappURL, '_blank');
                    }
                } catch (e) {
                    console.warn('Save & Send WhatsApp failed', e);
                } finally {
                    try { receiptModal.hide(); } catch (e) {}
                    setTimeout(() => { window.location.href = 'place_order.php'; }, 500);
                }
            }, { once: true });
        }
    })();
});
</script>

<?php include 'footer.php'; ?>
</body>
</html>