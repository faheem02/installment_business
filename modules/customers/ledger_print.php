<?php
session_start();
$id = (int)($_GET['id'] ?? 0);
$is_print = isset($_GET['print']);
require_once '../../includes/functions.php';

$customer = getById('customers', $id);
if (!$customer) { $_SESSION['error'] = 'Customer not found.'; header("Location: view.php?id=$id"); exit; }

$sales_data = $pdo->prepare("SELECT * FROM sales WHERE customer_id = ? AND status != 'cancelled' ORDER BY sale_date ASC");
$sales_data->execute([$id]); $sales_data = $sales_data->fetchAll();

$payments_data = $pdo->prepare("SELECT p.* FROM payments p WHERE p.sale_id IN (SELECT id FROM sales WHERE customer_id = ?) ORDER BY p.payment_date ASC");
$payments_data->execute([$id]); $payments_data = $payments_data->fetchAll();

$returns_data = $pdo->prepare("SELECT * FROM sale_returns WHERE customer_id = ? ORDER BY return_date ASC");
$returns_data->execute([$id]); $returns_data = $returns_data->fetchAll();

// Build sale_id → products map for ledger
$sale_products_map = [];
$sale_ids = array_unique(array_filter(array_column($payments_data, 'sale_id')));
if (!empty($sale_ids)) {
    $ids = implode(',', array_map('intval', $sale_ids));
    $prod_rows = $pdo->query("SELECT si.sale_id, GROUP_CONCAT(DISTINCT COALESCE(p.name, si.item_description) SEPARATOR ', ') AS products FROM sale_items si LEFT JOIN products p ON si.product_id = p.id WHERE si.sale_id IN ($ids) GROUP BY si.sale_id")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($prod_rows as $row) { $sale_products_map[$row['sale_id']] = $row['products']; }
}

$opening_due = (float)$customer['opening_due'];
$opening_paid = (float)$customer['opening_paid'];
$net_opening = $opening_due - $opening_paid;

$customer_ledger = [];
foreach ($sales_data as $s) {
    $total = (float)$s['total_amount'] + (float)$s['interest_amount'];
    $customer_ledger[] = ['date' => $s['sale_date'], 'type' => 'sale', 'ref' => $s['invoice_no'] ?? '#' . $s['id'], 'debit' => $total, 'credit' => 0, 'desc' => 'Credit sale' . ((float)$s['interest_amount'] > 0 ? ' (incl. interest)' : '')];
}
foreach ($payments_data as $p) {
    $products = $sale_products_map[$p['sale_id']] ?? '';
    $desc = 'Payment' . ($products ? ' - ' . $products : '');
    if ($p['notes']) $desc .= ' (' . $p['notes'] . ')';
    $customer_ledger[] = ['date' => $p['payment_date'], 'type' => 'payment', 'ref' => '#' . $p['id'], 'debit' => 0, 'credit' => (float)$p['amount'], 'desc' => $desc];
}
foreach ($returns_data as $r) {
    $customer_ledger[] = ['date' => $r['return_date'], 'type' => 'return', 'ref' => $r['invoice_no'] ?? '#' . $r['id'], 'debit' => 0, 'credit' => (float)$r['amount'], 'desc' => $r['notes'] ?? 'Return'];
}
usort($customer_ledger, function($a, $b) { return strcmp($a['date'], $b['date']); });

$credit_total = array_sum(array_column($customer_ledger, 'debit'));
$debit_total = array_sum(array_column($customer_ledger, 'credit'));
$closing = $net_opening + $credit_total - $debit_total;

$title = 'Customer Ledger - ' . htmlspecialchars($customer['full_name']);
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
      <p class="text-muted mb-0">Customer Ledger</p>
    </div>
    <div class="text-right">
      <h5 class="font-weight-bold mb-1">LEDGER</h5>
      <p class="mb-0 text-muted"><?= date('d-m-Y') ?></p>
    </div>
  </div>

  <hr>

  <div class="row mb-3">
    <div class="col-sm-6">
      <strong><?= htmlspecialchars($customer['full_name']) ?></strong><br>
      <span class="text-muted"><?= htmlspecialchars($customer['phone'] ?? '') ?></span>
    </div>
    <div class="col-sm-6 text-sm-right">
      <span class="text-muted">Opening Balance: </span><strong><?= formatCurrency($net_opening) ?></strong><br>
      <span class="text-muted">Closing Balance: </span><strong><?= formatCurrency($closing) ?></strong>
    </div>
  </div>

  <table>
    <thead>
      <tr><th style="width:100px;">Date</th><th style="width:90px;">Ref</th><th class="text-left">Description</th><th style="width:100px;">Debit</th><th style="width:100px;">Credit</th><th style="width:100px;">Balance</th></tr>
    </thead>
    <tbody>
      <tr style="background:#f8f9fc;"><td colspan="5" class="text-right"><strong>Opening Balance</strong></td><td><strong><?= formatCurrency($net_opening) ?></strong></td></tr>
      <?php $bal = $net_opening; foreach ($customer_ledger as $l):
        $bal += $l['debit'] - $l['credit'];
        $bg = $l['type'] === 'payment' || $l['type'] === 'return' ? 'style="background:#f0fff4;"' : '';
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
        <td class="text-right"><?= formatCurrency($credit_total) ?></td>
        <td class="text-right"><?= formatCurrency($debit_total) ?></td>
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
