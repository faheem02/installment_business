<?php
session_start();
$page_title = 'Supplier Payments';
$base_url = '../../';
require_once '../../includes/functions.php';

// Handle payment submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_payment'])) {
    $supplier_id = (int)$_POST['supplier_id'];
    $amount = (float)($_POST['amount'] ?? 0);
    $method = $_POST['payment_method'] ?? 'cash';
    $description = trim($_POST['description'] ?? '');
    $payment_date = $_POST['payment_date'] ?? date('Y-m-d');

    if ($supplier_id <= 0 || $amount <= 0) {
        $_SESSION['error'] = 'Select a supplier and enter a valid amount';
    } else {
        $supplier = getById('suppliers', $supplier_id);
        if (!$supplier) {
            $_SESSION['error'] = 'Supplier not found';
        } else {
            insert('supplier_payments', [
                'supplier_id' => $supplier_id,
                'amount' => $amount,
                'payment_method' => $method,
                'description' => $description ?: null,
                'payment_date' => $payment_date,
                'created_by' => $_SESSION['user_id'] ?? 1,
                'created_at' => date('Y-m-d'),
            ]);
            $_SESSION['success'] = 'Payment of ' . formatCurrency($amount) . ' recorded for ' . $supplier['name'];
        }
    }
    header("Location: supplier_payments.php");
    exit;
}

// Handle delete
if (isset($_GET['delete'])) {
    $pid = (int)$_GET['delete'];
    $pdo->prepare("DELETE FROM supplier_payments WHERE id = ?")->execute([$pid]);
    $_SESSION['success'] = 'Payment deleted';
    header("Location: supplier_payments.php");
    exit;
}

$suppliers = getAll('suppliers', 'name ASC');
$from = $_GET['from'] ?? date('Y-m-01');
$to = $_GET['to'] ?? date('Y-m-d');

$payments = $pdo->prepare("
    SELECT sp.*, s.name AS supplier_name, u.username AS added_by
    FROM supplier_payments sp
    JOIN suppliers s ON s.id = sp.supplier_id
    LEFT JOIN users u ON u.id = sp.created_by
    WHERE sp.payment_date BETWEEN ? AND ?
    ORDER BY sp.payment_date DESC, sp.id DESC
");
$payments->execute([$from, $to]);
$payments_data = $payments->fetchAll();

$total_amount = array_sum(array_column($payments_data, 'amount'));

require_once '../../includes/header.php';
?>

<style>
.status-badge { font-size: .75rem; padding: 3px 10px; }
</style>

<div class="d-flex justify-content-between align-items-center mb-4">
  <h5 class="m-0 font-weight-bold" style="color:#0f172a;"><i class="fas fa-money-bill-wave"></i> Supplier Payments</h5>
</div>

<div class="row">
  <!-- Left: Payment Form -->
  <div class="col-lg-4 mb-4">
    <div class="card shadow">
      <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-plus-circle"></i> Record Payment</h6>
      </div>
      <div class="card-body">
        <form method="post">
          <div class="form-group">
            <label class="small">Supplier <span class="text-danger">*</span></label>
            <select name="supplier_id" class="form-control" required>
              <option value="">-- Select Supplier --</option>
              <?php foreach ($suppliers as $s): ?>
                <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['name']) ?> (<?= htmlspecialchars($s['phone'] ?? '-') ?>)</option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="row">
            <div class="col-md-6 form-group">
              <label class="small">Amount <span class="text-danger">*</span></label>
              <input type="number" name="amount" class="form-control" step="0.01" min="0.01" placeholder="0.00" required>
            </div>
            <div class="col-md-6 form-group">
              <label class="small">Payment Method</label>
              <select name="payment_method" class="form-control">
                <option value="cash">Cash</option>
                <option value="card">Card</option>
                <option value="bank_transfer">Bank Transfer</option>
              </select>
            </div>
          </div>
          <div class="form-group">
            <label class="small">Description</label>
            <textarea name="description" class="form-control" rows="2" placeholder="Invoice ref, notes..."></textarea>
          </div>
          <div class="form-group">
            <label class="small">Date</label>
            <input type="date" name="payment_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
          </div>
          <button type="submit" name="add_payment" class="btn btn-primary btn-block py-2">
            <i class="fas fa-save"></i> Record Payment
          </button>
        </form>
      </div>
    </div>
  </div>

  <!-- Right: Payment History -->
  <div class="col-lg-8 mb-4">
    <div class="card shadow">
      <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-history"></i> Payment History</h6>
      </div>
      <div class="card-body">
        <form method="get" class="form-inline mb-3">
          <label class="mr-2 text-muted small">From:</label>
          <input type="date" name="from" class="form-control form-control-sm mr-3" value="<?= $from ?>">
          <label class="mr-2 text-muted small">To:</label>
          <input type="date" name="to" class="form-control form-control-sm mr-3" value="<?= $to ?>">
          <button type="submit" class="btn btn-sm btn-primary"><i class="fas fa-search"></i> View</button>
          <a href="supplier_payments.php" class="btn btn-sm btn-secondary ml-2">This Month</a>
        </form>

        <div class="mb-3">
          <span class="text-muted small">Total Payments: </span>
          <strong class="text-danger"><?= formatCurrency($total_amount) ?></strong>
          <span class="text-muted small ml-3">Count: </span>
          <strong><?= count($payments_data) ?></strong>
        </div>

        <?php if (empty($payments_data)): ?>
          <div class="text-center text-muted py-4">
            <i class="fas fa-money-bill-wave fa-3x mb-3"></i>
            <p>No payments recorded in this period.</p>
          </div>
        <?php else: ?>
          <div class="table-responsive">
            <table class="table table-bordered table-hover table-sm">
              <thead class="thead-light">
                <tr>
                  <th>Date</th>
                  <th>Supplier</th>
                  <th>Description</th>
                  <th class="text-right">Amount</th>
                  <th>Method</th>
                  <th>Added By</th>
                  <th>Action</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($payments_data as $p): ?>
                  <tr>
                    <td><?= formatDate($p['payment_date']) ?></td>
                    <td><strong><?= htmlspecialchars($p['supplier_name']) ?></strong></td>
                    <td><?= htmlspecialchars($p['description'] ?? '-') ?></td>
                    <td class="text-right font-weight-bold text-danger"><?= formatCurrency($p['amount']) ?></td>
                    <td>
                      <span class="badge badge-<?= $p['payment_method'] === 'cash' ? 'success' : ($p['payment_method'] === 'card' ? 'info' : 'primary') ?> status-badge">
                        <?= ucfirst(str_replace('_', ' ', $p['payment_method'])) ?>
                      </span>
                    </td>
                    <td><?= htmlspecialchars($p['added_by'] ?? '-') ?></td>
                    <td>
                      <a href="supplier_payments.php?delete=<?= $p['id'] ?>" class="btn btn-sm btn-danger" title="Delete" onclick="return confirm('Delete this payment?')">
                        <i class="fas fa-trash"></i>
                      </a>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<?php require_once '../../includes/footer.php'; ?>
