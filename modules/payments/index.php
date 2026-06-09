<?php
session_start();
$page_title = 'Receipts';
$base_url = '../../';
require_once '../../includes/functions.php';

$search = $_GET['search'] ?? '';
$method = $_GET['method'] ?? '';
$type = $_GET['type'] ?? '';
$from = $_GET['from'] ?? '';
$to = $_GET['to'] ?? '';

$sql = "SELECT p.id AS payment_id, p.payment_date, p.amount, p.payment_method, p.payment_type, p.reference_no,
               s.id AS sale_id, s.invoice_no,
               c.id AS customer_id, c.full_name AS customer_name, c.phone AS customer_phone
        FROM payments p
        LEFT JOIN sales s ON p.sale_id = s.id
        LEFT JOIN customers c ON s.customer_id = c.id
        WHERE 1=1";
$params = [];
if ($search) {
    $sql .= " AND (c.full_name LIKE ? OR c.phone LIKE ? OR s.invoice_no LIKE ?)";
    $params = array_merge($params, array_fill(0, 3, "%$search%"));
}
if ($method) {
    $sql .= " AND p.payment_method = ?";
    $params[] = $method;
}
if ($type) {
    $sql .= " AND p.payment_type = ?";
    $params[] = $type;
}
if ($from) {
    $sql .= " AND p.payment_date >= ?";
    $params[] = $from;
}
if ($to) {
    $sql .= " AND p.payment_date <= ?";
    $params[] = $to;
}
$sql .= " ORDER BY p.payment_date DESC, p.id DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$payments = $stmt->fetchAll();

require_once '../../includes/header.php';
?>

<div class="card shadow mb-4">
  <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
    <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-receipt"></i> Receipts</h6>
  </div>
  <div class="card-body">
    <form method="get" class="mb-3">
      <div class="row">
        <div class="col-md-4 mb-2">
          <input type="text" name="search" class="form-control" placeholder="Search by customer, phone, invoice..." value="<?= htmlspecialchars($search) ?>">
        </div>
        <div class="col-md-2 mb-2">
          <select name="method" class="form-control">
            <option value="">All Methods</option>
            <option value="cash" <?= $method === 'cash' ? 'selected' : '' ?>>Cash</option>
            <option value="card" <?= $method === 'card' ? 'selected' : '' ?>>Card</option>
            <option value="bank_transfer" <?= $method === 'bank_transfer' ? 'selected' : '' ?>>Bank Transfer</option>
          </select>
        </div>
        <div class="col-md-2 mb-2">
          <select name="type" class="form-control">
            <option value="">All Types</option>
            <option value="down_payment" <?= $type === 'down_payment' ? 'selected' : '' ?>>Down Payment</option>
            <option value="installment" <?= $type === 'installment' ? 'selected' : '' ?>>Installment</option>
            <option value="advance" <?= $type === 'advance' ? 'selected' : '' ?>>Advance</option>
            <option value="partial" <?= $type === 'partial' ? 'selected' : '' ?>>Partial</option>
          </select>
        </div>
        <div class="col-md-2 mb-2">
          <input type="date" name="from" class="form-control" value="<?= htmlspecialchars($from) ?>" placeholder="From date">
        </div>
        <div class="col-md-2 mb-2">
          <input type="date" name="to" class="form-control" value="<?= htmlspecialchars($to) ?>" placeholder="To date">
        </div>
      </div>
      <div class="row">
        <div class="col-md-12">
          <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Filter</button>
          <a href="index.php" class="btn btn-secondary">Reset</a>
        </div>
      </div>
    </form>

    <div class="table-responsive">
      <table class="table table-bordered table-hover" width="100%" cellspacing="0">
        <thead class="thead-light">
          <tr>
            <th>Receipt #</th>
            <th>Date</th>
            <th>Customer</th>
            <th>Invoice #</th>
            <th class="text-right">Amount</th>
            <th>Method</th>
            <th>Type</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($payments)): ?>
            <tr><td colspan="8" class="text-center text-muted">No receipts found</td></tr>
          <?php else: ?>
            <?php foreach ($payments as $p): ?>
              <tr>
                <td><a href="receipt.php?id=<?= $p['payment_id'] ?>"><strong>RCT-<?= str_pad($p['payment_id'], 5, '0', STR_PAD_LEFT) ?></strong></a></td>
                <td><?= formatDate($p['payment_date']) ?></td>
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
                  $method_badge = match($p['payment_method']) {
                    'cash' => 'success',
                    'card' => 'info',
                    'bank_transfer' => 'primary',
                    default => 'secondary'
                  };
                  ?>
                  <span class="badge badge-<?= $method_badge ?>"><?= ucfirst($p['payment_method']) ?></span>
                </td>
                <td><span class="badge badge-secondary"><?= ucfirst(str_replace('_', ' ', $p['payment_type'])) ?></span></td>
                <td>
                  <a href="receipt.php?id=<?= $p['payment_id'] ?>" class="btn btn-sm btn-info" title="View"><i class="fas fa-eye"></i></a>
                  <a href="receipt.php?id=<?= $p['payment_id'] ?>&print=1" class="btn btn-sm btn-secondary" title="Print" target="_blank"><i class="fas fa-print"></i></a>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php require_once '../../includes/footer.php'; ?>
