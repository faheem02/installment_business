<?php
session_start();
$page_title = 'Expenses';
$base_url = '../../';
require_once '../../includes/functions.php';

$from = $_GET['from'] ?? date('Y-m-01');
$to = $_GET['to'] ?? date('Y-m-d');
$cat_filter = (int)($_GET['category'] ?? 0);

$categories = getAll('expense_categories', 'name ASC');

// Handle add expense
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_expense'])) {
    $data = [
        'category_id' => (int)($_POST['category_id'] ?? 0) ?: null,
        'expense_date' => $_POST['expense_date'],
        'amount' => (float)($_POST['amount'] ?? 0),
        'description' => $_POST['description'] ?? '',
        'vendor_name' => $_POST['vendor_name'] ?? '',
        'bill_no' => $_POST['bill_no'] ?? '',
        'payment_method' => $_POST['payment_method'] ?? 'cash',
        'approval_status' => 'pending',
        'notes' => $_POST['notes'] ?? '',
        'created_by' => $_SESSION['user_id'] ?? 1,
        'created_at' => date('Y-m-d'),
    ];

    if ($data['amount'] <= 0) {
        $_SESSION['error'] = 'Amount must be greater than 0';
    } else {
        insert('expenses', $data);
        $_SESSION['success'] = 'Expense recorded: ' . formatCurrency($data['amount']);
    }
    header("Location: index.php?from=$from&to=$to" . ($cat_filter ? "&category=$cat_filter" : ''));
    exit;
}

// Handle update status
if (isset($_GET['approve'])) {
    $eid = (int)$_GET['approve'];
    $pdo->prepare("UPDATE expenses SET approval_status = 'approved', updated_at = CURDATE() WHERE id = ?")->execute([$eid]);
    $_SESSION['success'] = 'Expense approved';
    header("Location: index.php?from=$from&to=$to" . ($cat_filter ? "&category=$cat_filter" : ''));
    exit;
}
if (isset($_GET['reject'])) {
    $eid = (int)$_GET['reject'];
    $pdo->prepare("UPDATE expenses SET approval_status = 'rejected', updated_at = CURDATE() WHERE id = ?")->execute([$eid]);
    $_SESSION['success'] = 'Expense rejected';
    header("Location: index.php?from=$from&to=$to" . ($cat_filter ? "&category=$cat_filter" : ''));
    exit;
}

// Fetch expenses
$sql = "SELECT e.*, ec.name AS category_name FROM expenses e LEFT JOIN expense_categories ec ON e.category_id = ec.id WHERE e.expense_date BETWEEN ? AND ?";
$params = [$from, $to];
if ($cat_filter) {
    $sql .= " AND e.category_id = ?";
    $params[] = $cat_filter;
}
$sql .= " ORDER BY e.expense_date DESC, e.id DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$expenses = $stmt->fetchAll();

// Calculate totals
$total_amount = 0;
$cash_total = 0;
$card_total = 0;
$bank_total = 0;
foreach ($expenses as $e) {
    $total_amount += $e['amount'];
    match ($e['payment_method']) {
        'cash' => $cash_total += $e['amount'],
        'card' => $card_total += $e['amount'],
        'bank_transfer' => $bank_total += $e['amount'],
        default => null
    };
}

require_once '../../includes/header.php';
?>

<style>
.status-badge { font-size: .75rem; padding: 3px 10px; }
</style>

<div class="d-flex justify-content-between align-items-center mb-4">
  <h5 class="m-0 font-weight-bold" style="color:#0f172a;"><i class="fas fa-file-invoice-dollar"></i> Expenses</h5>
  <div>
    <a href="categories.php" class="btn btn-secondary btn-sm"><i class="fas fa-tags"></i> Categories</a>
    <button class="btn btn-primary btn-sm" data-toggle="modal" data-target="#addExpenseModal"><i class="fas fa-plus"></i> Add Expense</button>
  </div>
</div>

<form method="get" class="form-inline mb-4">
  <label class="mr-2 text-muted small">From:</label>
  <input type="date" name="from" class="form-control form-control-sm mr-3" value="<?= $from ?>">
  <label class="mr-2 text-muted small">To:</label>
  <input type="date" name="to" class="form-control form-control-sm mr-3" value="<?= $to ?>">
  <label class="mr-2 text-muted small">Category:</label>
  <select name="category" class="form-control form-control-sm mr-3">
    <option value="">All Categories</option>
    <?php foreach ($categories as $c): ?>
      <option value="<?= $c['id'] ?>" <?= $cat_filter === (int)$c['id'] ? 'selected' : '' ?>><?= htmlspecialchars($c['name']) ?></option>
    <?php endforeach; ?>
  </select>
  <button type="submit" class="btn btn-sm btn-primary"><i class="fas fa-search"></i> View</button>
  <a href="index.php" class="btn btn-sm btn-secondary ml-2">This Month</a>
</form>

<!-- Summary Cards -->
<div class="row mb-4">
  <div class="col-md-3 mb-3">
    <div class="card border-left-danger shadow h-100 py-2">
      <div class="card-body py-2">
        <div class="row no-gutters align-items-center">
          <div class="col mr-2">
            <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">Total Expenses</div>
            <div class="h5 mb-0 font-weight-bold" style="color:#0f172a;"><?= formatCurrency($total_amount) ?></div>
          </div>
          <div class="col-auto"><i class="fas fa-arrow-up fa-2x text-gray-300"></i></div>
        </div>
      </div>
    </div>
  </div>
  <div class="col-md-3 mb-3">
    <div class="card border-left-success shadow h-100 py-2">
      <div class="card-body py-2">
        <div class="row no-gutters align-items-center">
          <div class="col mr-2">
            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Cash</div>
            <div class="h5 mb-0 font-weight-bold" style="color:#0f172a;"><?= formatCurrency($cash_total) ?></div>
          </div>
          <div class="col-auto"><i class="fas fa-money-bill-wave fa-2x text-gray-300"></i></div>
        </div>
      </div>
    </div>
  </div>
  <div class="col-md-3 mb-3">
    <div class="card border-left-info shadow h-100 py-2">
      <div class="card-body py-2">
        <div class="row no-gutters align-items-center">
          <div class="col mr-2">
            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Card</div>
            <div class="h5 mb-0 font-weight-bold" style="color:#0f172a;"><?= formatCurrency($card_total) ?></div>
          </div>
          <div class="col-auto"><i class="fas fa-credit-card fa-2x text-gray-300"></i></div>
        </div>
      </div>
    </div>
  </div>
  <div class="col-md-3 mb-3">
    <div class="card border-left-primary shadow h-100 py-2">
      <div class="card-body py-2">
        <div class="row no-gutters align-items-center">
          <div class="col mr-2">
            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Bank Transfer</div>
            <div class="h5 mb-0 font-weight-bold" style="color:#0f172a;"><?= formatCurrency($bank_total) ?></div>
          </div>
          <div class="col-auto"><i class="fas fa-university fa-2x text-gray-300"></i></div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Expenses Table -->
<div class="card shadow mb-4">
  <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
    <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-list"></i> Expense Records</h6>
    <span class="text-muted small"><?= count($expenses) ?> record<?= count($expenses) !== 1 ? 's' : '' ?></span>
  </div>
  <div class="card-body">
    <?php if (empty($expenses)): ?>
      <div class="text-center text-muted py-3">
        <i class="fas fa-file-invoice fa-3x mb-3"></i>
        <p>No expenses found for this period.</p>
      </div>
    <?php else: ?>
      <div class="table-responsive">
        <table class="table table-bordered table-hover table-sm" width="100%" cellspacing="0">
          <thead class="thead-light">
            <tr>
              <th>Date</th>
              <th>Category</th>
              <th>Description</th>
              <th>Vendor</th>
              <th>Bill No.</th>
              <th class="text-right">Amount</th>
              <th>Method</th>
              <th>Status</th>
              <th class="text-center">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($expenses as $e): ?>
              <tr>
                <td><?= formatDate($e['expense_date']) ?></td>
                <td><span class="badge badge-secondary"><?= htmlspecialchars($e['category_name'] ?? 'Uncategorized') ?></span></td>
                <td><?= htmlspecialchars($e['description'] ?? '-') ?></td>
                <td><?= htmlspecialchars($e['vendor_name'] ?: '-') ?></td>
                <td><?= htmlspecialchars($e['bill_no'] ?: '-') ?></td>
                <td class="text-right font-weight-bold text-danger"><?= formatCurrency($e['amount']) ?></td>
                <td>
                  <?php
                  $mb = match($e['payment_method']) {
                    'cash' => 'success',
                    'card' => 'info',
                    'bank_transfer' => 'primary',
                    default => 'secondary'
                  };
                  ?>
                  <span class="badge badge-<?= $mb ?> status-badge"><?= ucfirst($e['payment_method']) ?></span>
                </td>
                <td>
                  <?php
                  $sb = match($e['approval_status']) {
                    'approved' => 'success',
                    'rejected' => 'danger',
                    default => 'warning'
                  };
                  ?>
                  <span class="badge badge-<?= $sb ?> status-badge"><?= ucfirst($e['approval_status']) ?></span>
                </td>
                <td class="text-center">
                  <?php if ($e['approval_status'] === 'pending'): ?>
                    <a href="index.php?approve=<?= $e['id'] ?>&from=<?= $from ?>&to=<?= $to ?><?= $cat_filter ? "&category=$cat_filter" : '' ?>" class="btn btn-sm btn-success" title="Approve"><i class="fas fa-check"></i></a>
                    <a href="index.php?reject=<?= $e['id'] ?>&from=<?= $from ?>&to=<?= $to ?><?= $cat_filter ? "&category=$cat_filter" : '' ?>" class="btn btn-sm btn-danger" title="Reject"><i class="fas fa-times"></i></a>
                  <?php else: ?>
                    <span class="text-muted">-</span>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>

<!-- Add Expense Modal -->
<div class="modal fade" id="addExpenseModal" tabindex="-1" role="dialog">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <form method="post">
        <div class="modal-header">
          <h6 class="modal-title"><i class="fas fa-plus-circle text-primary"></i> Add Expense</h6>
          <button type="button" class="close" data-dismiss="modal">&times;</button>
        </div>
        <div class="modal-body">
          <div class="row">
            <div class="col-md-6 form-group">
              <label class="small">Date</label>
              <input type="date" name="expense_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
            </div>
            <div class="col-md-6 form-group">
              <label class="small">Amount <span class="text-danger">*</span></label>
              <input type="number" name="amount" class="form-control" step="0.01" min="0.01" required>
            </div>
          </div>
          <div class="form-group">
            <label class="small">Category</label>
            <select name="category_id" class="form-control">
              <option value="">Uncategorized</option>
              <?php foreach ($categories as $c): ?>
                <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label class="small">Description</label>
            <textarea name="description" class="form-control" rows="2" placeholder="What is this expense for?"></textarea>
          </div>
          <div class="row">
            <div class="col-md-6 form-group">
              <label class="small">Vendor / Payee</label>
              <input type="text" name="vendor_name" class="form-control" placeholder="Vendor name">
            </div>
            <div class="col-md-6 form-group">
              <label class="small">Bill / Ref No.</label>
              <input type="text" name="bill_no" class="form-control" placeholder="Bill number">
            </div>
          </div>
          <div class="form-group">
            <label class="small">Payment Method</label>
            <select name="payment_method" class="form-control">
              <option value="cash">Cash</option>
              <option value="card">Card</option>
              <option value="bank_transfer">Bank Transfer</option>
            </select>
          </div>
          <div class="form-group">
            <label class="small">Notes</label>
            <textarea name="notes" class="form-control" rows="2" placeholder="Additional notes..."></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cancel</button>
          <button type="submit" name="add_expense" class="btn btn-primary btn-sm"><i class="fas fa-save"></i> Add Expense</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php require_once '../../includes/footer.php'; ?>
