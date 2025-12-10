<?php
require_once 'config.php';
require_once 'functions.php';

if (!isLoggedIn()) {
    redirect('index.php');
}

// --- shared settings (match place_order3.php) ---

$headerLogo = '';
try {
    $row = query("SELECT value FROM settings WHERE key_name = 'header_logo' LIMIT 1")->fetch();
    if ($row && isset($row['value'])) $headerLogo = $row['value'];
} catch (Exception $e) {
    error_log("reprint_receipt.php: failed to read header_logo setting: " . $e->getMessage());
    $headerLogo = '';
}

$fragileUrl = (strpos($_SERVER['SCRIPT_NAME'],'/')===0 ? '' : '/') . 'uploads/fragile.svg';

$shopAddress = 'ALSHAHEEN SHOP';
$shopPhone   = '+968 72202722';

// --- AJAX endpoints ---

if (isset($_GET['action'])) {
    header('Content-Type: application/json; charset=utf-8');

    if ($_GET['action'] === 'fetch' && !empty($_GET['order_id'])) {
        $orderId = (int)$_GET['order_id'];
        $row = query(
            "SELECT o.*, u.username
             FROM orders o
             LEFT JOIN users u ON o.user_id = u.id
             WHERE o.id = ? LIMIT 1",
            [$orderId]
        )->fetch();

        if (!$row) {
            echo json_encode(['success' => false, 'message' => 'Order not found.']);
            exit;
        }

        echo json_encode(['success' => true, 'order' => $row], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($_GET['action'] === 'search') {
        $q = trim($_GET['q'] ?? '');
        $params = [];
        $sql = "SELECT o.id, o.invoice_no, o.phone, o.address, o.date, o.price, o.status, u.username
                FROM orders o
                LEFT JOIN users u ON o.user_id = u.id";
        if ($q !== '') {
            if (ctype_digit($q) && strlen($q) <= 8) {
                $sql .= " WHERE o.id = ?";
                $params[] = (int)$q;
            } else {
                $sql .= " WHERE o.invoice_no LIKE ? OR o.phone LIKE ? OR o.address LIKE ?";
                $like = '%' . $q . '%';
                $params[] = $like; $params[] = $like; $params[] = $like;
            }
        }
        $sql .= " ORDER BY o.date DESC LIMIT 100";
        $rows = query($sql, $params)->fetchAll();

        echo json_encode(['success' => true, 'results' => $rows], JSON_UNESCAPED_UNICODE);
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Unknown action.']);
    exit;
}

?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Reprint Receipts</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">

  <style>
    .receipt-card {
      max-width: 320px;
      margin: 0 auto;
      font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
      color: #000;
    }
    .receipt-header {
      display: flex;
      justify-content: space-between;
      align-items: flex-start;
      gap: 8px;
    }
    .receipt-header .logo-wrap { display:flex; align-items:flex-start; }
    .receipt-header .logo-wrap img { max-height:64px; width:auto; display:block; }
    .receipt-header .meta {
      text-align: right;
      min-width:120px;
    }
    .receipt-header .meta .inv { font-weight:600; font-size:14px; }
    .receipt-header .meta .date { font-size:12px; color:#666; }

    .receipt-table th { width: 110px; text-align: left; vertical-align: top; padding-right: 6px; font-weight:600; }
    .receipt-table td { vertical-align: top; padding-bottom:4px; }

    @media print {
      body * { visibility: hidden !important; }
      #printOnlyReceipt, #printOnlyReceipt * { visibility: visible !important; }
      #printOnlyReceipt {
        position: absolute !important;
        left: 0 !important;
        top: 0 !important;
        width: 80mm !important;
        height: 80mm !important;
        box-sizing: border-box !important;
        padding: 2mm !important;
        background: #fff;
      }
    }
  </style>
</head>
<body>
<?php include 'header.php'; ?>

<div class="container my-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h3 class="mb-0">Reprint Receipts</h3>
    <a href="dashboard.php" class="btn btn-outline-secondary">Back</a>
  </div>

  <div class="card mb-3">
    <div class="card-body">
      <form id="searchForm" class="row g-2 align-items-center">
        <div class="col-sm-8">
          <input id="q" class="form-control" placeholder="Search by invoice, phone, address or order id">
        </div>
        <div class="col-auto">
          <button type="submit" class="btn btn-primary">Search</button>
        </div>
        <div class="col-auto">
          <button type="button" id="reloadRecent" class="btn btn-outline-secondary">Recent</button>
        </div>
      </form>
      <div id="searchMsg" class="mt-2"></div>
    </div>
  </div>

  <div class="card">
    <div class="card-body">
      <h5 class="card-title">Results</h5>
      <div class="table-responsive">
        <table class="table table-striped" id="resultsTable">
          <thead class="table-light">
            <tr>
              <th>ID</th>
              <th>Invoice</th>
              <th>User</th>
              <th>Phone</th>
              <th>Address</th>
              <th>Price</th>
              <th>Date</th>
              <th>Status</th>
              <th class="text-center">Action</th>
            </tr>
          </thead>
          <tbody id="resultsBody">
          </tbody>
        </table>
      </div>
      <div id="resultsMsg" class="mt-2"></div>
    </div>
  </div>
</div>

<div class="modal fade" id="reprintModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Receipt</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body" id="reprintModalBody">
        <div class="text-center small text-muted">Loading...</div>
      </div>
      <div class="modal-footer">
        <button id="modalPrintBtn" type="button" class="btn btn-success">Print (4x4)</button>
        <a id="modalWhatsappLink" class="btn btn-outline-success" target="_blank" rel="noopener">Open WhatsApp</a>
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>

<script>
(function(){
  function esc(s) {
    if (s === null || s === undefined) return '';
    return String(s).replace(/[&<>"']/g, function(m){
      return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m];
    });
  }

  const resultsBody = document.getElementById('resultsBody');
  const resultsMsg = document.getElementById('resultsMsg');
  const searchMsg = document.getElementById('searchMsg');
  const reprintModal = new bootstrap.Modal(document.getElementById('reprintModal'), { backdrop: 'static', keyboard: false });

  function renderResults(rows) {
    resultsBody.innerHTML = '';
    if (!rows || rows.length === 0) {
      resultsMsg.innerHTML = '<div class="alert alert-info py-2">No results found.</div>';
      return;
    }
    resultsMsg.innerHTML = '';
    rows.forEach(r => {
      const tr = document.createElement('tr');
      tr.innerHTML = `
        <td>${esc(r.id)}</td>
        <td>${esc(r.invoice_no)}</td>
        <td>${esc(r.username || '')}</td>
        <td>${esc(r.phone)}</td>
        <td style="max-width:220px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">${esc(r.address)}</td>
        <td>${parseFloat(r.price || 0).toFixed(2)}</td>
        <td>${esc(r.date)}</td>
        <td>${esc(r.status || '')}</td>
        <td class="text-center">
          <button class="btn btn-sm btn-outline-primary reprintBtn" data-id="${esc(r.id)}">Reprint</button>
        </td>
      `;
      resultsBody.appendChild(tr);
    });
  }

  async function loadRecent() {
    searchMsg.innerHTML = '';
    try {
      const res = await fetch('reprint_receipt.php?action=search', { credentials: 'same-origin' });
      const json = await res.json();
      if (json.success) {
        renderResults(json.results);
      } else {
        resultsMsg.innerHTML = '<div class="alert alert-danger">Failed to load recent orders.</div>';
      }
    } catch (err) {
      console.error(err);
      resultsMsg.innerHTML = '<div class="alert alert-danger">Server error loading recent orders.</div>';
    }
  }

  document.getElementById('searchForm').addEventListener('submit', function(e){
    e.preventDefault();
    const q = document.getElementById('q').value.trim();
    doSearch(q);
  });

  document.getElementById('reloadRecent').addEventListener('click', function(){
    document.getElementById('q').value='';
    loadRecent();
  });

  async function doSearch(q) {
    resultsMsg.innerHTML = '';
    searchMsg.innerHTML = '';
    try {
      const url = 'reprint_receipt.php?action=search&q=' + encodeURIComponent(q);
      const res = await fetch(url, { credentials: 'same-origin' });
      const json = await res.json();
      if (json.success) {
        renderResults(json.results);
      } else {
        resultsMsg.innerHTML = '<div class="alert alert-danger">Search failed.</div>';
      }
    } catch (err) {
      console.error(err);
      resultsMsg.innerHTML = '<div class="alert alert-danger">Server error during search.</div>';
    }
  }

  function buildReceiptHtml(order) {
    const invoice = esc(order.invoice_no || '');
    const phone   = esc(order.phone || '');
    const address = esc(order.address || '').replace(/\n/g, '<br>');
    const date    = esc(order.date || '');
    const username = esc(order.username || '');

    const qrText = encodeURIComponent(
      `Invoice: ${order.invoice_no}\nUser: ${order.username || ''}\nPhone: ${order.phone}\nAddress: ${order.address}\nPrice: ${parseFloat(order.price || 0).toFixed(2)}\nDetails:\n${order.details || ''}\nDate: ${order.date}`
    );
    const qrUrlRemote = "https://api.qrserver.com/v1/create-qr-code/?size=260x260&data=" + qrText;

    const hasLogo = <?php echo json_encode(!empty($headerLogo)); ?>;
    const logoUrl = <?php echo json_encode($headerLogo ?? ''); ?>;

    return `
      <div id="receiptContent" class="receipt-card">
        <div class="receipt-header mb-2">
          <div class="meta">
            <div class="small text-muted">Online Order</div>
            <div class="inv">Invoice: ${invoice}</div>
            <div class="date">${date}</div>
          </div>
          <div class="logo-wrap">
            ${hasLogo
              ? `<img src="${esc(logoUrl)}" alt="Logo" class="receipt-logo">`
              : `<div style="font-weight:700;font-size:14px;">ALSHAHEEN</div>`}
          </div>
        </div>

        <table class="table table-borderless receipt-table mb-0">
          <tr><th>Done by</th><td>${username}</td></tr>
          <tr><th>Phone</th><td>${phone}</td></tr>
          <tr><th>Address</th><td>${address}</td></tr>
        </table>

        <div class="text-center mt-2">
          <img src="${qrUrlRemote}" alt="QR" style="max-width:120px;" class="img-fluid mb-1 receipt-qr">
          <div class="small text-muted">Scan for order details</div>
        </div>
      </div>
    `;
  }

  async function openReceipt(orderId) {
    const body = document.getElementById('reprintModalBody');
    body.innerHTML = '<div class="text-center small text-muted">Loading...</div>';
    try {
      const res = await fetch('reprint_receipt.php?action=fetch&order_id=' + encodeURIComponent(orderId), { credentials: 'same-origin' });
      const json = await res.json();
      if (!json.success) {
        body.innerHTML = '<div class="alert alert-danger">Order not found.</div>';
        reprintModal.show();
        return;
      }
      const order = json.order;
      body.innerHTML = buildReceiptHtml(order);

      const waBtn = document.getElementById('modalWhatsappLink');
      const waMsg = encodeURIComponent(`Order #${order.invoice_no}\nAddress: ${order.address}\nDetails:\n${order.details || ''}`);
      if (waBtn) {
        waBtn.href = 'https://wa.me/' + encodeURIComponent(order.phone || '') + '/?text=' + waMsg;
        waBtn.style.display = 'inline-block';
      }

      reprintModal.show();

      const printBtn = document.getElementById('modalPrintBtn');
      if (printBtn) {
        printBtn.replaceWith(printBtn.cloneNode(true));
        const newPrintBtn = document.getElementById('modalPrintBtn');

        newPrintBtn.addEventListener('click', async function () {
          await handlePrintFromModal(order);
        }, { once: true });
      }

    } catch (err) {
      console.error(err);
      body.innerHTML = '<div class="alert alert-danger">Server error fetching order.</div>';
      reprintModal.show();
    }
  }

  resultsBody.addEventListener('click', async function(e){
    const btn = e.target.closest('.reprintBtn');
    if (!btn) return;
    const id = btn.dataset.id;
    if (!id) return;
    await openReceipt(id);
  });

  loadRecent();

  // ---------- print helpers: matching place_order3 label ----------

  function loadQRious() {
    return new Promise((resolve, reject) => {
      if (window.QRious) return resolve(window.QRious);
      const s = document.createElement('script');
      s.src = 'https://cdnjs.cloudflare.com/ajax/libs/qrious/4.0.2/qrious.min.js';
      s.async = true;
      s.onload = () => resolve(window.QRious);
      s.onerror = () => reject(new Error('Failed to load QRious'));
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
          } catch (e) { one(); }
        });
        setTimeout(() => { if (!finished) { finished = true; resolve(); } }, timeout);
      } catch (e) { resolve(); }
    });
  }

  function escapeHtml(s) {
    if (s == null) return '';
    return String(s).replace(/[&<>"']/g, function(m) {
      return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m];
    });
  }

  const fragileUrlJS = <?php echo json_encode($fragileUrl); ?>;
  const shopAddressJS = <?php echo json_encode($shopAddress); ?>;
  const shopPhoneJS   = <?php echo json_encode($shopPhone); ?>;
  const headerLogoJS  = <?php echo json_encode($headerLogo); ?>;

  function buildLabelHtmlFromOrder(order, qrDataUrl) {
    const invoice = order.invoice_no || '';
    const phone   = order.phone || '';
    const address = order.address || '';
    const orderId = order.id || '';

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
        .from-line, .to-line { white-space:normal; }

        .mid-sep { border-top:1px solid #000; margin:2mm 0 1.5mm; }

        .middle-title { text-align:center; font-weight:700; font-size:12px; margin-bottom:2mm; }

        .mid-band { display:flex; flex:1; align-items:flex-start; gap:3mm; }

        .fragile-block {
          width: 22mm;
          border:1px solid #000;
          box-sizing:border-box;
          padding:1.5mm 1mm;
          text-align:center;
          font-size:9px;
        }
        .fragile-block .label { font-weight:700; margin-bottom:1mm; }
        .fragile-block img { max-width: 16mm; max-height: 16mm; }

        .code-bar-block {
          flex:1;
          display:flex;
          flex-direction:column;
          align-items:center;
          justify-content:flex-start;
          gap:1.5mm;
        }
        .human-code { font-size:9px; letter-spacing:1px; }
        .barcode {
          width:100%;
          height:16mm;
          display:flex;
          align-items:flex-end;
          justify-content:center;
          overflow:hidden;
        }
        .barcode-inner {
          width:80%;
          height:100%;
          display:flex;
          align-items:flex-end;
        }
        .barcode-bar {
          background:#000;
          margin-right:1px;
        }

        .qr-block {
          width:22mm;
          text-align:center;
        }
        .qr-block img {
          max-width:22mm;
          max-height:22mm;
        }

        .bottom-row {
          margin-top:2mm;
          border-top:1px solid #000;
          padding-top:1.5mm;
          display:flex;
          justify-content:space-between;
          font-size:9px;
        }
        .order-id { font-weight:700; }
        .instr-title { font-weight:700; margin-bottom:0.5mm; }
      </style>
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

    const orderIdText = orderId ? String(orderId).padStart(5, '0') : '';

    const htmlBody = `
      <div class="label-frame">
        <div class="logo-area">${logoBlock}</div>

        <div class="from-to">
          <div class="from">
            <div class="from-title">From:</div>
            <div class="from-line">${escapeHtml(shopAddressJS)}</div>
            <div class="from-line">${escapeHtml(shopPhoneJS)}</div>
          </div>
          <div class="to">
            <div class="to-title">To:</div>
            <div class="to-line">${escapeHtml(phone)}</div>
            <div class="to-line">${escapeHtml(address)}</div>
          </div>
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
            <div class="barcode">
              <div class="barcode-inner">
                ${barsHtml}
              </div>
            </div>
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

    return `<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width">${css}</head><body>${htmlBody}</body></html>`;
  }

  async function printLabelForOrder(order) {
    const qrText = `Invoice: ${order.invoice_no}
User: ${order.username || ''}
Phone: ${order.phone}
Address: ${order.address}
Price: ${parseFloat(order.price || 0).toFixed(2)}
Details:
${order.details || ''}
Date: ${order.date}`;

    const qrDataUrl = await generateQRDataURL(qrText, 500);
    const html = buildLabelHtmlFromOrder(order, qrDataUrl);

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
        idoc.open(); idoc.write(html); idoc.close();
        try { await waitForImages(idoc, 3000); } catch (e) {}
        try { iframe.contentWindow.focus(); iframe.contentWindow.print(); } catch (e) { console.warn(e); }
        setTimeout(() => { try { document.body.removeChild(iframe); } catch (e) {} }, 1000);
      } catch (e) {
        console.error('Print failed', e);
        alert('Printing failed. Please check popup blocker.');
      }
    }
  }

  async function handlePrintFromModal(order) {
    await printLabelForOrder(order);
  }

})();
</script>

<?php include 'footer.php'; ?>
</body>
</html>