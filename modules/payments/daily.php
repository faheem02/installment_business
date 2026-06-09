<?php
session_start();
$page_title = 'Daily Collection';
$base_url = '../../';
require_once '../../includes/functions.php';

$date = $_GET['date'] ?? date('Y-m-d');

$stmt = $pdo->prepare("
    SELECT p.*, s.invoice_no, s.customer_id,
           c.full_name AS customer_name, c.phone AS customer_phone,
           u.full_name AS received_by_name
    FROM payments p
    LEFT JOIN sales s ON p.sale_id = s.id
    LEFT JOIN customers c ON s.customer_id = c.id
    LEFT JOIN users u ON p.received_by = u.id
    WHERE p.payment_date = ?
    ORDER BY p.created_at ASC
");
$stmt->execute([$date]);
$payments = $stmt->fetchAll();

$total_collected = 0;
$cash_total = 0;
$card_total = 0;
$bank_total = 0;
foreach ($payments as $p) {
    $total_collected += $p['amount'];
    if ($p['payment_method'] === 'cash') {
        $cash_total += $p['amount'];
    } else {
        $card_total += $p['amount'];
    }
}

require_once '../../includes/header.php';
?>

<style>
  .summary-card { border-radius: 10px; padding: 20px; text-align: center; }
  .summary-card .num { font-size: 1.75rem; font-weight: 700; }
  .summary-card .label { font-size: .85rem; opacity: .85; }
</style>

<div class="d-flex justify-content-between align-items-center mb-4">
  <h5 class="m-0 font-weight-bold" style="color:#0f172a;"><i class="fas fa-coins"></i> Daily Collection</h5>
  <form method="get" class="form-inline">
    <label class="mr-2 text-muted small">Date:</label>
    <input type="date" name="date" class="form-control form-control-sm" value="<?= $date ?>" onchange="this.form.submit()">
  </form>
</div>

<div class="row mb-4">
  <div class="col-md-3 mb-3">
    <div class="summary-card bg-primary text-white">
      <div class="num"><?= formatCurrency($total_collected) ?></div>
      <div class="label">Total Collection</div>
      <small><?= count($payments) ?> transaction<?= count($payments) !== 1 ? 's' : '' ?></small>
    </div>
  </div>
  <div class="col-md-3 mb-3">
    <div class="summary-card bg-success text-white">
      <div class="num"><?= formatCurrency($cash_total) ?></div>
      <div class="label">Cash</div>
    </div>
  </div>
  <div class="col-md-3 mb-3">
    <div class="summary-card bg-info text-white">
      <div class="num"><?= formatCurrency($card_total) ?></div>
      <div class="label">Card</div>
    </div>
  </div>
  <div class="col-md-3 mb-3">
    <div class="summary-card bg-secondary text-white">
      <div class="num"><?= formatCurrency($bank_total) ?></div>
      <div class="label">Bank Transfer</div>
    </div>
  </div>
</div>

<div class="card shadow mb-4">
  <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
    <h6 class="m-0 font-weight-bold text-primary">
      <i class="fas fa-list"></i> Payments on <?= formatDate($date) ?>
    </h6>
    <a href="daily.php?date=<?= date('Y-m-d') ?>" class="btn btn-sm btn-outline-primary <?= $date === date('Y-m-d') ? 'active' : '' ?>">Today</a>
  </div>
  <div class="card-body">
    <?php if (empty($payments)): ?>
      <div class="text-center text-muted py-4">
        <i class="fas fa-receipt fa-3x mb-3"></i>
        <p>No payments recorded for this date.</p>
      </div>
    <?php else: ?>
      <div class="table-responsive">
        <table class="table table-bordered table-hover" width="100%" cellspacing="0">
          <thead class="thead-light">
            <tr>
              <th>Receipt</th>
              <th>Time</th>
              <th>Customer</th>
              <th>Invoice</th>
              <th class="text-right">Amount</th>
              <th>Method</th>
              <th>Type</th>
              <th>Received By</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($payments as $p): ?>
              <tr>
                <td><a href="receipt.php?id=<?= $p['id'] ?>"><strong>RCT-<?= str_pad($p['id'], 5, '0', STR_PAD_LEFT) ?></strong></a></td>
                <td><?= $p['created_at'] ? date('h:i A', strtotime($p['created_at'])) : '-' ?></td>
                <td>
                  <?php if ($p['customer_name']): ?>
                    <?= htmlspecialchars($p['customer_name']) ?>
                    <br><small class="text-muted"><?= htmlspecialchars($p['customer_phone'] ?? '') ?></small>
                  <?php else: ?>
                    <span class="text-muted">N/A</span>
                  <?php endif; ?>
                </td>
                <td><?= $p['invoice_no'] ? '<a href="' . $base_url . 'modules/sales/invoice.php?id=' . $p['sale_id'] . '">' . htmlspecialchars($p['invoice_no']) . '</a>' : '<span class="text-muted">-</span>' ?></td>
                <td class="text-right font-weight-bold text-success"><?= formatCurrency($p['amount']) ?></td>
                <td>
                  <?php
                  $mb = $p['payment_method'] === 'cash' ? 'success' : 'info';
                  $ml = $p['payment_method'] === 'cash' ? 'Cash' : 'Bank';
                  ?>
                  <span class="badge badge-<?= $mb ?>"><?= $ml ?></span>
                </td>
                <td><span class="badge badge-secondary"><?= ucfirst(str_replace('_', ' ', $p['payment_type'])) ?></span></td>
                <td><?= htmlspecialchars($p['received_by_name'] ?? '-') ?></td>
                <td>
                  <a href="receipt.php?id=<?= $p['id'] ?>" class="btn btn-sm btn-info" title="View"><i class="fas fa-eye"></i></a>
                  <a href="receipt.php?id=<?= $p['id'] ?>&print=1" class="btn btn-sm btn-secondary" title="Print" target="_blank"><i class="fas fa-print"></i></a>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>

<?php require_once '../../includes/footer.php'; ?>
