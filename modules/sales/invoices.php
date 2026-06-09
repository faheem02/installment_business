<?php
session_start();
$page_title = 'Invoices';
$base_url = '../../';
require_once '../../includes/functions.php';

$search = $_GET['search'] ?? '';
$status = $_GET['status'] ?? '';

$sql = "SELECT s.*, c.full_name AS customer_name, c.phone AS customer_phone,
       COALESCE((SELECT SUM(si2.paid_amount) FROM sale_installments si2 WHERE si2.sale_id = s.id), 0) AS total_inst_paid
       FROM sales s
       JOIN customers c ON s.customer_id = c.id WHERE 1=1";
$params = [];
if ($search) {
    $sql .= " AND (s.invoice_no LIKE ? OR c.full_name LIKE ? OR c.phone LIKE ?)";
    $params = array_fill(0, 3, "%$search%");
}
if ($status) {
    $sql .= " AND s.payment_status = ?";
    $params[] = $status;
}
$sql .= " ORDER BY s.created_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$sales = $stmt->fetchAll();

require_once '../../includes/header.php';
?>

<div class="card shadow mb-4">
  <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
    <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-file-invoice"></i> Invoices</h6>
    <a href="index.php" class="btn btn-primary btn-sm">
      <i class="fas fa-plus-circle"></i> New Sale
    </a>
  </div>
  <div class="card-body">
    <form method="get" class="mb-3">
      <div class="row">
        <div class="col-md-4">
          <input type="text" name="search" class="form-control" placeholder="Search by invoice no, customer..." value="<?= htmlspecialchars($search) ?>">
        </div>
        <div class="col-md-3">
          <select name="status" class="form-control">
            <option value="">All Status</option>
            <option value="paid" <?= $status === 'paid' ? 'selected' : '' ?>>Paid</option>
            <option value="partial" <?= $status === 'partial' ? 'selected' : '' ?>>Partial</option>
            <option value="installment" <?= $status === 'installment' ? 'selected' : '' ?>>Installment</option>
            <option value="pending" <?= $status === 'pending' ? 'selected' : '' ?>>Pending</option>
          </select>
        </div>
        <div class="col-md-3">
          <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Filter</button>
          <a href="invoices.php" class="btn btn-secondary">Reset</a>
        </div>
      </div>
    </form>

    <div class="table-responsive">
      <table class="table table-bordered table-hover" width="100%" cellspacing="0">
        <thead class="thead-light">
          <tr>
            <th>Invoice #</th>
            <th>Customer</th>
            <th>Date</th>
            <th>Total</th>
            <th>Total Paid</th>
            <th>Remaining</th>
            <th>Method</th>
            <th>Status</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($sales)): ?>
            <tr><td colspan="9" class="text-center text-muted">No invoices found</td></tr>
          <?php else: ?>
            <?php foreach ($sales as $s): ?>
              <tr>
                <td><a href="invoice.php?id=<?= $s['id'] ?>"><strong><?= htmlspecialchars($s['invoice_no']) ?></strong></a></td>
                <td><?= htmlspecialchars($s['customer_name']) ?><br><small class="text-muted"><?= htmlspecialchars($s['customer_phone']) ?></small></td>
                <td><?= formatDate($s['sale_date']) ?></td>
                <td class="text-right"><?= formatCurrency($s['total_amount']) ?></td>
                <td class="text-right"><?php $total_paid = (float)$s['down_payment'] + (float)$s['total_inst_paid']; ?><?= formatCurrency($total_paid) ?></td>
                <td class="text-right"><?php $remaining = (float)$s['total_amount'] - $total_paid; ?><span class="<?= $remaining > 0 ? 'text-danger font-weight-bold' : '' ?>"><?= formatCurrency($remaining) ?></span></td>
                <td><?= ucfirst(str_replace('_', ' ', $s['payment_method'])) ?></td>
                <td>
                  <?php
                  $badge = match($s['payment_status']) {
                    'paid' => 'success',
                    'partial' => 'warning',
                    'installment' => 'info',
                    default => 'secondary'
                  };
                  ?>
                  <span class="badge badge-<?= $badge ?>"><?= ucfirst($s['payment_status']) ?></span>
                </td>
                <td>
                  <a href="invoice.php?id=<?= $s['id'] ?>" class="btn btn-sm btn-info" title="View"><i class="fas fa-eye"></i></a>
                  <a href="invoice.php?id=<?= $s['id'] ?>&print=1" class="btn btn-sm btn-secondary" title="Print" target="_blank"><i class="fas fa-print"></i></a>
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
