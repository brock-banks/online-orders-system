// Paste this where your previous print handler was (after showReceipt, whatsappURL, qrTextPHP, and receiptData definitions)
// It will replace the old IIFE that cloned the DOM.

if (showReceipt) {
  const receiptModalEl = document.getElementById('receiptModal');
  const receiptModal = new bootstrap.Modal(receiptModalEl, { backdrop: 'static', keyboard: false });
  receiptModal.show();

  (function() {
    // Load QRious
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

    async function generateQRDataURL(text, size = 600) {
      try {
        const QRious = await loadQRious();
        const qr = new QRious({ value: text, size: size });
        return qr.toDataURL('image/png');
      } catch (e) {
        // fallback
        return 'https://api.qrserver.com/v1/create-qr-code/?size=' + size + 'x' + size + '&data=' + encodeURIComponent(text);
      }
    }

    function waitForImages(context, timeout = 3000) {
      return new Promise(resolve => {
        try {
          let imgs;
          if (context && context.document && context.document.images) imgs = Array.from(context.document.images);
          else if (context && context.getElementsByTagName) imgs = Array.from(context.getElementsByTagName('img'));
          else { resolve(); return; }
          if (imgs.length === 0) { resolve(); return; }
          let remaining = imgs.length;
          let done = false;
          function one() {
            if (done) return;
            remaining--;
            if (remaining <= 0) { done = true; resolve(); }
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
          setTimeout(() => { if (!done) { done = true; resolve(); } }, timeout);
        } catch (e) { resolve(); }
      });
    }

    // Build HTML from canonical receiptData (no DOM cloning) — excludes price and details intentionally
    function buildPrintableHtml(receiptDataObj, qrDataUrl, svgUrl) {
      // 4" x 4" = 101.6mm; keep small fonts and compact spacing
      const css = `
        <style>
          @page { size: 101.6mm 101.6mm; margin: 4mm; }
          html,body { margin:0; padding:0; background:#fff; color:#000; -webkit-print-color-adjust:exact; }
          body { font-family: Arial, Helvetica, sans-serif; font-size: 11px; line-height:1.08; padding:0; }
          .wrap { width: calc(101.6mm - 8mm); box-sizing:border-box; padding:0; display:flex; flex-direction:column; gap:6px; }
          .hdr { display:flex; justify-content:space-between; align-items:flex-start; gap:8px; }
          .hdr .left { font-weight:700; font-size:13px; }
          .hdr .right { font-weight:700; font-size:13px; text-align:right; }
          .meta { font-size:10px; color:#444; }
          .table { width:100%; border-collapse:collapse; font-size:10px; }
          .table td{ padding:2px 0; vertical-align:top; }
          .label { font-weight:700; width:28%; }
          .value { width:72%; }
          .visual-row { display:flex; justify-content:center; align-items:center; gap:6mm; margin-top:4px; }
          .visual { width: 30mm; height: 30mm; display:flex; align-items:center; justify-content:center; }
          .visual img { max-width:100%; max-height:100%; display:block; }
          .note { font-size:9px; text-align:center; color:#666; margin-top:6px; }
          /* Prevent page-breaks inside */
          .wrap, .wrap * { page-break-inside: avoid; break-inside: avoid; -webkit-column-break-inside: avoid; }
        </style>
      `;

      // Build a concise header + small info table (no price, no order details)
      const headerHtml = `
        <div class="hdr">
          <div class="left">Invoice: ${escapeHtml(receiptDataObj.invoice)}</div>
          <div class="right">ALSHAHEEN</div>
        </div>
        <div class="meta">${escapeHtml(receiptDataObj.date)}</div>
      `;

      const infoHtml = `
        <table class="table" role="presentation">
          <tr><td class="label">Customer</td><td class="value">${escapeHtml(receiptDataObj.customer)}</td></tr>
          <tr><td class="label">Phone</td><td class="value">${escapeHtml(receiptDataObj.phone)}</td></tr>
          <tr><td class="label">Address</td><td class="value">${escapeHtml(receiptDataObj.address)}</td></tr>
        </table>
      `;

      const visualHtml = `
        <div class="visual-row">
          <div class="visual"><img src="${qrDataUrl}" alt="QR"></div>
          <div class="visual"><img src="${svgUrl}" alt="Icon"></div>
        </div>
        <div class="note">Scan the QR to see full order details</div>
      `;

      const bodyHtml = `<div class="wrap">${headerHtml}${infoHtml}${visualHtml}</div>`;
      return `<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width"><title>Receipt</title>${css}</head><body>${bodyHtml}</body></html>`;
    }

    // Simple HTML escape
    function escapeHtml(s) {
      if (s == null) return '';
      return String(s).replace(/[&<>"']/g, function(m) { return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]; });
    }

    async function printFromReceiptData() {
      // Generate QR (qrTextPHP contains the full details — price & items included in QR only)
      const qrDataUrl = await generateQRDataURL(qrTextPHP, 600);

      // Build svg absolute URL (ensure correct origin)
      const origin = (window.location.origin || (location.protocol + '//' + location.host));
      const svgUrl = origin + '/uploads/fragile.svg';

      // Build final HTML from receiptData (server-provided canonical values)
      const html = buildPrintableHtml(typeof receiptData !== 'undefined' ? receiptData : {}, qrDataUrl, svgUrl);

      // Print (popup or iframe fallback)
      const printed = await (async function(docHtml) {
        // popup attempt
        const popup = window.open('', '_blank', 'toolbar=0,location=0,menubar=0,width=700,height=900');
        if (popup) {
          popup.document.open();
          popup.document.write(docHtml);
          popup.document.close();
          try { await waitForImages(popup, 3000); } catch (e) {}
          try { popup.focus(); popup.print(); } catch (e) { console.warn(e); }
          setTimeout(()=>{ try { popup.close(); } catch(e){} }, 600);
          return true;
        }
        // fallback iframe
        try {
          const iframe = document.createElement('iframe');
          iframe.style.position = 'fixed';
          iframe.style.left = '-9999px';
          iframe.style.top = '0';
          document.body.appendChild(iframe);
          const idoc = iframe.contentWindow.document;
          idoc.open(); idoc.write(docHtml); idoc.close();
          try { await waitForImages(idoc, 3000); } catch (e) {}
          try { iframe.contentWindow.focus(); iframe.contentWindow.print(); } catch (e) { console.warn(e); }
          setTimeout(()=>{ try { document.body.removeChild(iframe); } catch(e){} }, 800);
          return true;
        } catch (e) {
          console.error('Print failed', e);
          return false;
        }
      })(html);

      // After print, open WhatsApp (if available) and go back to place_order.php
      if (printed && whatsappURL) {
        try { window.open(whatsappURL, '_blank'); } catch (e) { console.warn(e); }
      }
      setTimeout(() => { window.location.href = 'place_order.php'; }, 700);
    }

    // Wire button
    const pb = document.getElementById('printAndSendBtn');
    if (pb) pb.addEventListener('click', function(){ printFromReceiptData().catch(err => { console.error(err); alert('Printing failed'); }); }, { once:true });

    // Save & send (no print) — just open WhatsApp
    const sb = document.getElementById('saveAndSendBtn');
    if (sb) sb.addEventListener('click', function() {
      try {
        if (whatsappURL) window.open(whatsappURL, '_blank');
      } catch (e) { console.warn(e); }
      try { receiptModal.hide(); } catch(e) {}
      setTimeout(()=>{ window.location.href = 'place_order.php'; }, 500);
    }, { once:true });

  })();
}