<?php
// Replacement print handler that ensures the printed receipt height fits its content.
// It opens a minimal print-only window containing only the sanitized receipt markup,
// sets print CSS that uses auto height, and prints. There is a fallback that appends
// the sanitized receipt into the current document if popup is blocked.
?>
<script>
document.addEventListener('DOMContentLoaded', function () {
  // expect showReceipt and whatsappURL variables to be present in the page scope
  const showReceipt = <?php echo $showReceipt ? 'true' : 'false'; ?>;
  const whatsappURL = <?php echo json_encode($whatsappURL); ?>;

  if (!showReceipt) return;

  const receiptModalEl = document.getElementById('receiptModal');
  const receiptModal = new bootstrap.Modal(receiptModalEl, { backdrop: 'static', keyboard: false });
  receiptModal.show();

  const printAndSendBtn = document.getElementById('printAndSendBtn');
  if (!printAndSendBtn) return;

  // Sanitize a receipt node by cloning and removing interactive elements
  function sanitizeNode(node) {
    const clone = node.cloneNode(true);
    clone.querySelectorAll('button, a, input, textarea, select, script, style').forEach(n => n.remove());
    return clone;
  }

  printAndSendBtn.addEventListener('click', function () {
    try {
      const modalReceipt = document.querySelector('#receipt');
      if (!modalReceipt) {
        // fallback: print the full page if receipt not found
        window.print();
        if (whatsappURL) window.open(whatsappURL, '_blank');
        setTimeout(() => window.location.href = 'orders.php', 700);
        return;
      }

      const sanitized = sanitizeNode(modalReceipt);
      const innerHTML = sanitized.innerHTML;

      // Minimal print CSS to fit a narrow label and ensure height fits content.
      // Note: @page height is set to 'auto' so the printed page height will fit the content.
      const printStyles = `
        <style>
          /* Ensure page width is fixed (4") and height grows to fit content */
          @page { size: 101.6mm auto; margin: 3mm; }
          html, body {
            margin: 0;
            padding: 0;
            background: #fff;
            color: #000;
            height: auto !important;
            min-height: 0 !important;
          }
          body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            box-sizing: border-box;
            padding: 3mm;
            -webkit-print-color-adjust: exact;
          }
          /* Receipt wrapper: allow height to be automatic and avoid page-breaks inside */
          .receipt-card {
            display: block;
            width: 100%;
            max-width: 101.6mm;
            box-sizing: border-box;
            page-break-inside: avoid;
            overflow: visible !important;
            margin: 0;
            padding: 0;
            background: #fff;
          }
          table { width: 100%; border-collapse: collapse; }
          th, td { padding: 2px 4px; text-align: left; vertical-align: top; }
          img { max-width: 100%; height: auto; display: block; margin: 0 auto; }
          /* Reduce margins that may cause forced additional page */
          .no-print-margin { margin:0; padding:0; }
        </style>
      `;

      // Try opening a new popup window for printing
      const printWindow = window.open('', '_blank', 'toolbar=0,location=0,menubar=0,width=700,height=900');
      if (!printWindow) {
        // Popup blocked: fallback to in-place print by appending sanitized receipt
        const existing = document.getElementById('printOnlyReceipt');
        if (existing) existing.remove();
        const cloneContainer = document.createElement('div');
        cloneContainer.id = 'printOnlyReceipt';
        cloneContainer.style.display = 'block';
        // Make sure the appended container won't introduce extra margins or page-breaks
        cloneContainer.style.position = 'absolute';
        cloneContainer.style.left = '-9999px';
        cloneContainer.style.top = '0';
        cloneContainer.appendChild(sanitized);
        document.body.appendChild(cloneContainer);

        setTimeout(() => {
          try { window.print(); } catch (e) { console.warn('Print failed', e); }
          const c = document.getElementById('printOnlyReceipt');
          if (c && c.parentNode) c.parentNode.removeChild(c);
          if (whatsappURL) window.open(whatsappURL, '_blank');
          setTimeout(() => window.location.href = 'orders.php', 700);
        }, 250);
        return;
      }

      // Build minimal document and write sanitized receipt into it
      const doc = printWindow.document;
      doc.open();
      doc.write(`<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=320"><title>Receipt</title>${printStyles}</head><body><div class="receipt-card no-print-margin">${innerHTML}</div></body></html>`);
      doc.close();

      // Ensure the new window's body has auto height and render completes
      printWindow.focus();

      // Allow time for images and fonts to load before printing
      setTimeout(function () {
        try {
          // Trigger print
          printWindow.print();
        } catch (err) {
          console.warn('Print error in popup', err);
        } finally {
          // Close print window after a short delay to give the print job a chance to start
          setTimeout(function () {
            try { printWindow.close(); } catch (e) { /* ignore */ }

            // After printing, open WhatsApp (if configured) and redirect back
            if (whatsappURL) {
              try { window.open(whatsappURL, '_blank'); } catch (e) { console.warn('Failed to open WhatsApp', e); }
            }
            setTimeout(function () { window.location.href = 'orders.php'; }, 600);
          }, 400);
        }
      }, 400);

    } catch (ex) {
      console.error('Print & send error', ex);
      if (whatsappURL) window.open(whatsappURL, '_blank');
      setTimeout(() => window.location.href = 'orders.php', 700);
    }
  }, { once: true });
});
</script>