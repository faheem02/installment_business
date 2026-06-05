<?php
session_start();
$page_title = 'Payment Methods';
$base_url = '../../';
require_once '../../includes/functions.php';

$methods = [
    ['id' => 'cash', 'name' => 'Cash', 'icon' => 'fas fa-money-bill-wave', 'color' => '#28a745', 'desc' => 'Receive payment in cash directly at the counter.'],
    ['id' => 'card', 'name' => 'Card', 'icon' => 'fas fa-credit-card', 'color' => '#007bff', 'desc' => 'Payment via debit or credit card through POS terminal.'],
    ['id' => 'bank_transfer', 'name' => 'Bank Transfer', 'icon' => 'fas fa-university', 'color' => '#17a2b8', 'desc' => 'Payment via bank transfer, mobile banking, or online transfer.'],
    ['id' => 'mixed', 'name' => 'Mixed', 'icon' => 'fas fa-random', 'color' => '#6f42c1', 'desc' => 'Combination of two or more payment methods in a single transaction.'],
];

$stmt = $pdo->query("SELECT payment_method, COUNT(*) as total, SUM(total_amount) as total_amount FROM sales WHERE status = 'active' GROUP BY payment_method");
$usage = [];
foreach ($stmt->fetchAll() as $row) {
    $usage[$row['payment_method']] = $row;
}

require_once '../../includes/header.php';
?>

<style>
  .method-card {
    border: 1px solid #e3e6f0;
    border-radius: 12px;
    padding: 24px;
    transition: all 0.2s;
    background: #fff;
    height: 100%;
  }
  .method-card:hover {
    box-shadow: 0 4px 16px rgba(0,0,0,0.08);
    border-color: #f59e0b;
    transform: translateY(-2px);
  }
  .method-icon {
    width: 56px;
    height: 56px;
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    color: #fff;
  }
  .stat-box {
    background: #f8f9fc;
    border-radius: 8px;
    padding: 12px;
    text-align: center;
  }
  .stat-box .stat-value {
    font-size: 1.1rem;
    font-weight: 700;
    color: #0f172a;
  }
  .stat-box .stat-label {
    font-size: 0.75rem;
    color: #858796;
    text-transform: uppercase;
    letter-spacing: 0.5px;
  }
</style>

<div class="card shadow mb-4">
  <div class="card-header py-3">
    <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-credit-card"></i> Payment Methods</h6>
  </div>
  <div class="card-body">
    <div class="row">
      <?php foreach ($methods as $m): ?>
        <div class="col-lg-3 col-md-6 mb-4">
          <div class="method-card">
            <div class="d-flex align-items-center mb-3">
              <div class="method-icon" style="background:<?= $m['color'] ?>;">
                <i class="<?= $m['icon'] ?>"></i>
              </div>
              <div class="ml-3">
                <h5 class="font-weight-bold mb-0"><?= $m['name'] ?></h5>
              </div>
            </div>
            <p class="text-muted small mb-3"><?= $m['desc'] ?></p>
            <?php if (isset($usage[$m['id']])): ?>
              <div class="row no-gutters">
                <div class="col-6 pr-1">
                  <div class="stat-box">
                    <div class="stat-value"><?= $usage[$m['id']]['total'] ?></div>
                    <div class="stat-label">Transactions</div>
                  </div>
                </div>
                <div class="col-6 pl-1">
                  <div class="stat-box">
                    <div class="stat-value"><?= formatCurrency($usage[$m['id']]['total_amount']) ?></div>
                    <div class="stat-label">Total</div>
                  </div>
                </div>
              </div>
            <?php else: ?>
              <div class="text-center text-muted small py-3">
                <i class="fas fa-chart-bar mr-1"></i> No transactions yet
              </div>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<div class="card shadow mb-4">
  <div class="card-header py-3">
    <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-history"></i> Recent Transactions by Method</h6>
  </div>
  <div class="card-body">
    <div class="table-responsive">
      <table class="table table-bordered table-hover" width="100%" cellspacing="0">
        <thead class="thead-light">
          <tr>
            <th>Invoice #</th>
            <th>Payment Method</th>
            <th>Amount</th>
            <th>Status</th>
            <th>Date</th>
          </tr>
        </thead>
        <tbody>
          <?php
          $recent = $pdo->query("SELECT s.invoice_no, s.payment_method, s.total_amount, s.payment_status, s.sale_date, c.full_name AS customer FROM sales s JOIN customers c ON s.customer_id = c.id WHERE s.status = 'active' ORDER BY s.created_at DESC LIMIT 15")->fetchAll();
          if (empty($recent)): ?>
            <tr><td colspan="5" class="text-center text-muted">No transactions yet</td></tr>
          <?php else: ?>
            <?php foreach ($recent as $r): ?>
              <tr>
                <td><a href="invoice.php?id=<?= $r['invoice_no'] ?>"><?= htmlspecialchars($r['invoice_no']) ?></a></td>
                <td>
                  <?php
                  $icon = match($r['payment_method']) {
                    'cash' => '<i class="fas fa-money-bill-wave text-success"></i>',
                    'card' => '<i class="fas fa-credit-card text-primary"></i>',
                    'bank_transfer' => '<i class="fas fa-university text-info"></i>',
                    'mixed' => '<i class="fas fa-random text-purple"></i>',
                    default => '<i class="fas fa-question-circle"></i>'
                  };
                  echo $icon . ' ' . ucfirst(str_replace('_', ' ', $r['payment_method']));
                  ?>
                </td>
                <td class="text-right"><?= formatCurrency($r['total_amount']) ?></td>
                <td>
                  <span class="badge badge-<?= match($r['payment_status']) { 'paid' => 'success', 'partial' => 'warning', 'installment' => 'info', default => 'secondary' } ?>">
                    <?= ucfirst($r['payment_status']) ?>
                  </span>
                </td>
                <td><?= formatDate($r['sale_date']) ?></td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php require_once '../../includes/footer.php'; ?>
