<?php
// Insert / replace the existing @media print CSS in the <head> section with the CSS below.
// This CSS hides everything except the #receipt element when printing
// and configures a 4-inch wide print layout (101.6mm).
?>
<style>
/* Print styles: show only #receipt (not the full page) */
@media print {
  /* Set the physical page size for a 4" label printer (width x auto height).
     If your printer driver doesn't honor the width, you can set the page size
     in the printer properties as 4" width too. */
  @page {
    size: 101.6mm auto; /* 4 inches wide */
    margin: 3mm;        /* small margins */
  }

  /* Hide everything first */
  body * {
    visibility: hidden !important;
  }

  /* Make the receipt and its contents visible */
  #receipt, #receipt * {
    visibility: visible !important;
  }

  /* Position the receipt at the top-left of the printed page and limit its width */
  #receipt {
    position: absolute !important;
    left: 0 !important;
    top: 0 !important;
    width: 101.6mm !important; /* 4 inches */
    box-sizing: border-box !important;
    padding: 4mm !important;
    background: #fff !important;
  }

  /* Typography and spacing optimizations for narrow labels */
  #receipt .fs-4, #receipt h5, #receipt h4 {
    font-size: 14px !important;
  }
  #receipt table { font-size: 12px !important; }
  #receipt .small { font-size: 10px !important; }

  /* Avoid page breaks inside the receipt */
  #receipt { page-break-inside: avoid !important; }

  /* Hide modal backdrop if present */
  .modal-backdrop { display: none !important; }

  /* Some browsers add default margins — ensure body has no extra margin on print */
  body { margin: 0 !important; padding: 0 !important; }
}
</style>