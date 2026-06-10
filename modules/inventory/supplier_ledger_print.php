<?php
session_start();
$id = (int)($_GET['id'] ?? 0);
$is_print = isset($_GET['print']);
require_once '../../includes/functions.php';

$supplier = getById('suppliers', $id);
if (!$supplier) { $_SESSION['error'] = 'Supplier not found.'; header("Location: suppliers.php"); exit; }

$purchases_data = $pdo->prepare("SELECT * FROM purchases WHERE supplier_id = ? AND status != 'cancelled' ORDER BY purchase_date ASC");
$purchases_data->execute([$id]); $purchases_data = $purchases_data->fetchAll();

$payments_data = $pdo->prepare("SELECT * FROM supplier_payments WHERE supplier_id = ? ORDER BY payment_date ASC");
$payments_data->execute([$id]); $payments_data = $payments_data->fetchAll();

$purchase_items_data = $pdo->prepare("SELECT pi.*, p.name AS product_name, p.code AS product_code, pu.id as purchase_id FROM purchase_items pi JOIN purchases pu ON pu.id = pi.purchase_id LEFT JOIN products p ON p.id = pi.product_id WHERE pu.supplier_id = ?");
$purchase_items_data->execute([$id]); $purchase_items_data = $purchase_items_data->fetchAll();

$purchase_products = [];
foreach ($purchase_items_data as $pi) {
    $pid = $pi['purchase_id'];
    $pname = $pi['product_name'] ?? $pi['product_code'] ?? 'Item';
    if (!isset($purchase_products[$pid])) $purchase_products[$pid] = [];
    if (!in_array($pname, $purchase_products[$pid])) $purchase_products[$pid][] = $pname;
}

$ledger = [];
foreach ($purchases_data as $p) {
    $products = $purchase_products[$p['id']] ?? [];
    $desc = !empty($products) ? implode(', ', array_slice($products, 0, 3)) : 'Products supplied';
    if (count($products) > 3) $desc .= ' +' . (count($products) - 3) . ' more';
    $ledger[] = ['date' => $p['purchase_date'], 'type' => 'purchase', 'ref' => $p['invoice_no'] ?? '#' . $p['id'], 'debit' => (float)$p['total_amount'], 'credit' => 0, 'desc' => $desc];
}
foreach ($payments_data as $p) {
    $ledger[] = ['date' => $p['payment_date'], 'type' => 'payment', 'ref' => '#' . $p['id'], 'debit' => 0, 'credit' => (float)$p['amount'], 'desc' => $p['description'] ?? 'Cash paid'];
}
usort($ledger, function($a, $b) { return strcmp($a['date'], $b['date']); });

$opening = (float)$supplier['opening_balance'] + (float)$supplier['adjustment'];
$debit_total = array_sum(array_column($ledger, 'debit'));
$credit_total = array_sum(array_column($ledger, 'credit'));
$closing = $opening + $debit_total - $credit_total;

$title = 'Supplier Ledger - ' . htmlspecialchars($supplier['contact_person'] ?? $supplier['name']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title><?=$title?></title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/4.6.2/css/bootstrap.min.css">
<style>
  @media print { body { font-size: 11px; } .no-print { display: none !important; } }
  body { background: #f1f5f9; font-family: 'Segoe UI', sans-serif; }
  .wrap { max-width: 900px; margin: 30px auto; background: #fff; border-radius: 12px; padding: 35px; box-shadow: 0 4px 24px rgba(0,0,0,.08); }
  table { width: 100%; border-collapse: collapse; }
  th, td { border: 1px solid #e2e8f0; padding: 6px 10px; text-align: center; }
  th { background: #f8fafc; color: #475569; font-size: 12px; }
  td.text-right { text-align: right; }
  td.text-left { text-align: left; }
</style>
</head>
<body>

<div class="no-print text-center mt-3 mb-3">
  <button class="btn btn-primary" onclick="window.print()"><i class="fas fa-download"></i> Download PDF</button>
  <button class="btn btn-secondary" onclick="window.close()"><i class="fas fa-times"></i> Close</button>
</div>

<div class="wrap">

  <div class="d-flex justify-content-between align-items-start mb-4">
    <div>
      <h4 class="font-weight-bold mb-1" style="color:#0f172a;">Installment Business</h4>
      <p class="text-muted mb-0">Supplier Ledger</p>
    </div>
    <div class="text-right">
      <h5 class="font-weight-bold mb-1">LEDGER</h5>
      <p class="mb-0 text-muted"><?= date('d-m-Y') ?></p>
    </div>
  </div>

  <hr>

  <div class="row mb-3">
    <div class="col-sm-6">
      <strong><?= htmlspecialchars(($supplier['contact_person'] ?? '') . ' (' . ($supplier['name'] ?? '') . ')') ?></strong><br>
      <span class="text-muted"><?= htmlspecialchars($supplier['phone'] ?? '') ?></span>
    </div>
    <div class="col-sm-6 text-sm-right">
      <span class="text-muted">Opening Balance: </span><strong><?= formatCurrency($opening) ?></strong><br>
      <span class="text-muted">Closing Balance: </span><strong><?= formatCurrency($closing) ?></strong>
    </div>
  </div>

  <table>
    <thead>
      <tr><th style="width:100px;">Date</th><th style="width:90px;">Ref</th><th class="text-left">Description</th><th style="width:100px;">Debit</th><th style="width:100px;">Credit</th><th style="width:100px;">Balance</th></tr>
    </thead>
    <tbody>
      <tr style="background:#f8f9fc;"><td colspan="5" class="text-right"><strong>Opening Balance</strong></td><td><strong><?= formatCurrency($opening) ?></strong></td></tr>
      <?php $bal = $opening; foreach ($ledger as $l):
        $bal += $l['debit'] - $l['credit'];
        $bg = $l['type'] === 'payment' ? 'style="background:#f0fff4;"' : '';
      ?>
        <tr <?= $bg ?>>
          <td><?= formatDate($l['date']) ?></td>
          <td><span class="badge badge-secondary"><?= htmlspecialchars($l['ref']) ?></span></td>
          <td class="text-left small"><?= htmlspecialchars($l['desc']) ?></td>
          <td class="text-right"><?= $l['debit'] ? formatCurrency($l['debit']) : '-' ?></td>
          <td class="text-right"><?= $l['credit'] ? formatCurrency($l['credit']) : '-' ?></td>
          <td class="text-right"><strong><?= formatCurrency($bal) ?></strong></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
    <tfoot>
      <tr style="background:#f8f9fc;font-weight:bold;">
        <td colspan="3" class="text-right">Totals</td>
        <td class="text-right"><?= formatCurrency($debit_total) ?></td>
        <td class="text-right"><?= formatCurrency($credit_total) ?></td>
        <td class="text-right"><?= formatCurrency($closing) ?></td>
      </tr>
    </tfoot>
  </table>

  <hr>
  <p class="text-center text-muted small mb-0">Generated on <?= date('d-m-Y H:i') ?></p>

</div>

<?php if ($is_print): ?><script>window.print()</script><?php endif; ?>
</body>
</html>
