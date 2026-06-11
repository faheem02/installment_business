<?php
session_start();
$id = (int)($_GET['id'] ?? 0);
$is_print = isset($_GET['print']);
require_once '../../includes/functions.php';

$purchase = getById('purchases', $id);
if (!$purchase) {
    $_SESSION['error'] = 'Purchase not found.';
    header("Location: purchases.php");
    exit;
}

$supplier = $purchase['supplier_id'] ? getById('suppliers', $purchase['supplier_id']) : null;
$items = $pdo->prepare("
    SELECT pi.*, p.name AS product_name, p.code AS product_code, p.product_type
    FROM purchase_items pi
    LEFT JOIN products p ON pi.product_id = p.id
    WHERE pi.purchase_id = ?
");
$items->execute([$id]);
$items_data = $items->fetchAll();

$serials = $pdo->prepare("
    SELECT ps.*, p.name AS product_name, p.code AS product_code, p.product_type
    FROM product_serials ps
    JOIN products p ON ps.product_id = p.id
    WHERE ps.purchase_id = ?
    ORDER BY ps.id
");
$serials->execute([$id]);
$serials_data = $serials->fetchAll();

$voucher_no = 'PUR-' . str_pad($purchase['id'], 5, '0', STR_PAD_LEFT);
$title = 'Purchase Voucher ' . $voucher_no;
$supplier_name = $supplier ? htmlspecialchars(($supplier['contact_person'] ?? '') . ' (' . ($supplier['name'] ?? '') . ')') : 'N/A';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title><?= $title ?></title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/4.6.2/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
<style>
  @media print {
      @page { size: A4; margin: 10mm; }
      body { background:#fff; font-size:11px; }
      .no-print { display:none !important; }
      .receipt-box { box-shadow:none !important; border:1px solid #000 !important; max-width:100%; margin:0; }
  }
  body {
      background:#f1f5f9;
      font-family: 'Segoe UI', Arial, sans-serif;
      font-size:14px;
  }
  .receipt-box {
      max-width: 720px;
      margin: 30px auto;
      background:#fff;
      border: 2px solid #1f2937;
      border-radius: 4px;
      padding: 0;
      box-shadow: 0 4px 24px rgba(0,0,0,.08);
  }
  .r-header {
      display:flex; align-items:center; gap:14px;
      padding: 14px 18px 8px 18px;
      border-bottom: 2px solid #1f2937;
  }
  .r-logo {
      width:60px; height:60px;
      border:3px solid #4b5563; border-radius:50%;
      display:flex; align-items:center; justify-content:center;
      font-weight:bold; font-size:1.4rem; color:#374151;
      flex-shrink:0;
  }
  .r-company-name { font-size:1.6rem; font-weight:700; margin:0; color:#1f2937; }
  .r-company-sub { font-size:.85rem; font-weight:600; letter-spacing:.03em; margin:0; color:#1f2937; }
  .r-company-contact { font-size:.7rem; color:#374151; margin:0; }
  .r-meta {
      display:flex; justify-content:space-between;
      padding: 6px 18px; font-size:.8rem; font-weight:600;
      border-bottom: 1px solid #1f2937;
  }
  .r-section-title {
      font-size:.95rem; font-weight:700;
      padding: 8px 18px;
      border-bottom: 1px solid #1f2937;
      text-decoration: underline; text-underline-offset: 3px;
  }
  .r-row {
      display:flex; flex-wrap:wrap;
      padding: 8px 18px;
      border-bottom: 1px solid #1f2937;
      font-size:.95rem; gap: 6px 24px;
  }
  .r-row .r-field { display:flex; gap:6px; }
  .r-row .r-field .lbl { font-weight:700; }
  .r-row .r-field .val { font-weight:400; }
  .r-table {
      width:100%; border-collapse:collapse; font-size:.85rem;
  }
  .r-table th, .r-table td {
      border:1px solid #1f2937; padding:6px 10px; text-align:center;
  }
  .r-table th { font-weight:700; }
  .r-balance-row {
      display:flex; justify-content:space-between;
      padding: 12px 18px;
      border-bottom: 1px solid #1f2937;
      font-size:1rem; font-weight:700;
  }
  .r-balance-row .current { font-size:1.15rem; color:#dc2626; }
  .r-sign-row {
      display:flex; justify-content:space-between; align-items:flex-end;
      padding: 24px 18px 10px 18px;
      border-bottom: 1px solid #1f2937;
      font-size:.9rem; font-weight:600;
      min-height:50px;
  }
  .r-thanks {
      text-align:center; font-weight:700; text-decoration:underline;
      padding: 8px 18px; font-size:.95rem;
      border-bottom: 1px solid #e5e7eb;
  }
  .r-software {
      text-align:left; font-size:.7rem; color:#9ca3af;
      padding: 6px 18px 14px 18px;
  }
</style>
</head>
<body>

<div class="no-print text-center mt-3 mb-3">
  <button class="btn btn-primary" onclick="window.print()"><i class="fas fa-print"></i> Print</button>
  <button class="btn btn-secondary" onclick="window.close()"><i class="fas fa-times"></i> Close</button>
</div>

<div class="receipt-box">

  <!-- Header -->
  <div class="r-header">
    <div class="r-logo">SHT</div>
    <div>
      <p class="r-company-name">Saim Hasnain Traders</p>
      <p class="r-company-sub">CHAK NUM 14/8AR Talambah Road Mia Chanu</p>
      <p class="r-company-contact">Phone: Mahar Falak 03030344214 / Mahar Shahid 03346881214</p>
    </div>
  </div>

  <!-- Date / Voucher No -->
  <div class="r-meta">
    <span>Voucher #: <?= $voucher_no ?></span>
    <span>Date: <?= date('n/j/Y', strtotime($purchase['purchase_date'])) ?></span>
  </div>

  <!-- Supplier Information -->
  <div class="r-section-title">Supplier Information</div>
  <div class="r-row">
    <div class="r-field"><span class="lbl">Supplier:</span><span class="val"><?= $supplier_name ?></span></div>
    <div class="r-field"><span class="lbl">Invoice No:</span><span class="val"><?= htmlspecialchars($purchase['invoice_no'] ?? 'N/A') ?></span></div>
  </div>

  <!-- Items Table -->
  <div class="r-section-title">Purchase Items</div>
  <?php if (!empty($items_data)): ?>
    <div style="padding:8px 18px;">
      <table class="r-table">
        <thead>
          <tr>
            <th width="8%">#</th>
            <th width="42%">Product</th>
            <th width="15%">Qty</th>
            <th width="20%">Price</th>
            <th width="15%">Total</th>
          </tr>
        </thead>
        <tbody>
          <?php $i = 1; foreach ($items_data as $item): ?>
            <tr>
              <td><?= $i++ ?></td>
              <td class="text-left"><?= htmlspecialchars($item['product_name'] ?? $item['product_code'] ?? 'Item #' . $item['product_id']) ?></td>
              <td><?= $item['quantity'] ?></td>
              <td><?= formatCurrency($item['purchase_price']) ?></td>
              <td><?= formatCurrency($item['subtotal']) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>

  <!-- Serials Table -->
  <?php if (!empty($serials_data)):
    $first_type = $serials_data[0]['product_type'] ?? 'general';
  ?>
    <div style="padding:8px 18px; border-top:1px solid #1f2937;">
      <table class="r-table">
        <thead>
          <tr>
            <th width="5%">#</th>
            <th width="35%">Product</th>
            <?php if ($first_type === 'mobile'): ?>
              <th width="60%">IMEI Number</th>
            <?php elseif ($first_type === 'bike'): ?>
              <th width="20%">Engine No.</th>
              <th width="20%">Chassis No.</th>
              <th width="20%">Color</th>
            <?php else: ?>
              <th width="60%">Serial / Identifier</th>
            <?php endif; ?>
          </tr>
        </thead>
        <tbody>
          <?php $i = 1; foreach ($serials_data as $s): $type = $s['product_type']; ?>
            <tr>
              <td><?= $i++ ?></td>
              <td class="text-left"><?= htmlspecialchars($s['product_name'] ?? $s['product_code']) ?></td>
              <?php if ($type === 'mobile'): ?>
                <td class="text-left"><?= htmlspecialchars($s['imei_number'] ?? '-') ?></td>
              <?php elseif ($type === 'bike'):
                $notes = $s['notes'] ?? '';
                $color = '';
                $chassis = '';
                if (preg_match('/^Bike\s+(.*?)\s*-\s*(.*)$/', $notes, $m)) {
                    $color = trim($m[1]);
                    $chassis = trim($m[2]);
                } else {
                    $chassis = $notes;
                }
              ?>
                <td><?= htmlspecialchars($s['serial_number'] ?? '-') ?></td>
                <td><?= htmlspecialchars($chassis ?: '-') ?></td>
                <td><?= htmlspecialchars($color ?: '-') ?></td>
              <?php else: ?>
                <td class="text-left"><?= htmlspecialchars($s['serial_number'] ?? $s['imei_number'] ?? '-') ?></td>
              <?php endif; ?>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>

  <!-- Balance Row -->
  <div class="r-balance-row">
    <span>Total Amount = <?= formatCurrency($purchase['total_amount']) ?></span>
    <?php if ($purchase['paid_amount'] > 0): ?>
      <span class="text-success">Paid = <?= formatCurrency($purchase['paid_amount']) ?></span>
    <?php endif; ?>
    <?php if ($purchase['due_amount'] > 0): ?>
      <span class="current">Due = <?= formatCurrency($purchase['due_amount']) ?></span>
    <?php endif; ?>
  </div>

  <!-- Signatures -->
  <div class="r-sign-row">
    <span>Received By</span>
    <span>Supplier Signature</span>
    <span>Manager Signature/Stamp</span>
  </div>

  <!-- Thanks -->
  <div class="r-thanks">[Thanks For Visiting:::Saim Hasnain Traders]</div>

  <!-- Software credit -->
  <div class="r-software">[Software By @ ATR ]</div>

</div>

<?php if ($is_print): ?><script>window.print()</script><?php endif; ?>
</body>
</html>
