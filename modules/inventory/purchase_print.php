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
<title><?=$title?></title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/4.6.2/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
<style>
  @media print{body{font-size:12px}.no-print{display:none!important}}
  body{background:#f1f5f9;font-family:'Segoe UI',sans-serif}
  .wrap{max-width:800px;margin:30px auto;background:#fff;border-radius:12px;padding:35px;box-shadow:0 4px 24px rgba(0,0,0,.08)}
  .receipt-header{border-bottom:2px dashed #e2e8f0;padding-bottom:20px;margin-bottom:20px}
  .receipt-footer{border-top:2px dashed #e2e8f0;padding-top:20px;margin-top:20px}
  .amount-box{background:#f8fafc;border-radius:8px;padding:15px;text-align:center;border:2px solid #e2e8f0}
  .amount-box .amount{font-size:2rem;font-weight:bold;color:#007bff}
  table.details{width:100%}
  table.details td{padding:6px 10px}
  table.details td:first-child{color:#64748b;width:40%}
  table.details td:last-child{font-weight:600;width:60%}
  table.items{width:100%;border-collapse:collapse;margin-top:15px}
  table.items th, table.items td{border:1px solid #e2e8f0;padding:8px 10px;text-align:center}
  table.items th{background:#f8fafc;color:#475569;font-size:13px}
</style>
</head>
<body>

<div class="no-print text-center mt-3 mb-3">
  <button class="btn btn-primary" onclick="window.print()"><i class="fas fa-print"></i> Print</button>
  <button class="btn btn-secondary" onclick="window.close()"><i class="fas fa-times"></i> Close</button>
</div>

<div class="wrap">

  <div class="receipt-header">
    <div class="d-flex justify-content-between align-items-start">
      <div>
        <h4 class="font-weight-bold mb-1" style="color:#0f172a;">Installment Business</h4>
        <p class="text-muted mb-0">POS System</p>
      </div>
      <div class="text-right">
        <h4 class="font-weight-bold mb-1" style="color:#007bff;">PURCHASE VOUCHER</h4>
        <p class="mb-0"><strong><?= $voucher_no ?></strong></p>
        <p class="mb-0 text-muted"><?= formatDate($purchase['purchase_date']) ?></p>
      </div>
    </div>
  </div>

  <div class="amount-box mb-4">
    <p class="text-muted mb-1">Total Amount</p>
    <div class="amount"><?= formatCurrency($purchase['total_amount']) ?></div>
    <p class="mb-0 mt-2">
      <span class="badge badge-<?= $purchase['status'] === 'received' ? 'success' : ($purchase['status'] === 'cancelled' ? 'danger' : 'warning') ?> px-3 py-1">
        <?= ucfirst($purchase['status']) ?>
      </span>
    </p>
  </div>

  <table class="details">
    <tr><td>Supplier</td><td><?= $supplier_name ?></td></tr>
    <tr><td>Invoice No</td><td><?= htmlspecialchars($purchase['invoice_no'] ?? 'N/A') ?></td></tr>
    <?php if ($purchase['paid_amount'] > 0): ?>
      <tr><td>Paid Amount</td><td><?= formatCurrency($purchase['paid_amount']) ?></td></tr>
    <?php endif; ?>
    <?php if ($purchase['due_amount'] > 0): ?>
      <tr><td>Due Amount</td><td><?= formatCurrency($purchase['due_amount']) ?></td></tr>
    <?php endif; ?>
    <?php if ($purchase['notes']): ?>
      <tr><td>Notes</td><td><?= nl2br(htmlspecialchars($purchase['notes'])) ?></td></tr>
    <?php endif; ?>
  </table>

  <?php if (!empty($items_data)): ?>
    <table class="items">
      <thead>
        <tr>
          <th width="10%">#</th>
          <th width="40%">Product</th>
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
        <tr class="font-weight-bold" style="background:#f8f9fc;">
          <td colspan="4" class="text-right">Total</td>
          <td><?= formatCurrency($purchase['total_amount']) ?></td>
        </tr>
      </tbody>
    </table>
  <?php endif; ?>

  <?php if (!empty($serials_data)): ?>
    <table class="items mt-3">
      <thead>
        <tr>
          <th>#</th>
          <th>Product</th>
          <th>Serial / IMEI / Engine / Chassis</th>
        </tr>
      </thead>
      <tbody>
        <?php $i = 1; foreach ($serials_data as $s): $type = $s['product_type']; ?>
          <tr>
            <td><?= $i++ ?></td>
            <td class="text-left"><?= htmlspecialchars($s['product_name'] ?? $s['product_code']) ?></td>
            <td class="text-left">
              <?php if ($type === 'mobile'): ?>
                IMEI: <?= htmlspecialchars($s['imei_number'] ?? '-') ?>
              <?php elseif ($type === 'bike'): ?>
                Eng: <?= htmlspecialchars($s['serial_number'] ?? '-') ?> / Chassis: <?= htmlspecialchars($s['notes'] ?? '-') ?>
              <?php else: ?>
                <?= htmlspecialchars($s['serial_number'] ?? $s['imei_number'] ?? '-') ?>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>

  <div class="receipt-footer text-center">
    <p class="text-muted small mb-1">Purchase recorded on <?= formatDate($purchase['created_at']) ?></p>
    <p class="text-muted small mb-0">This is a computer-generated voucher.</p>
  </div>

</div>

<?php if ($is_print): ?><script>window.print()</script><?php endif; ?>
</body>
</html>
