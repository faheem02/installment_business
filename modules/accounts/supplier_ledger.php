<?php
session_start();
$page_title = 'Supplier Ledger';
$base_url = '../../';
require_once '../../includes/functions.php';

$from = $_GET['from'] ?? date('Y-m-01');
$to = $_GET['to'] ?? date('Y-m-d');
$supplier_id = (int)($_GET['supplier_id'] ?? 0);

require_once '../../includes/header.php';
?>

<style>
.status-badge { font-size: .75rem; padding: 3px 10px; }
</style>

<?php if ($supplier_id): ?>
<?php
$supplier = getById('suppliers', $supplier_id);
if (!$supplier) {
    echo '<div class="alert alert-danger">Supplier not found. <a href="supplier_ledger.php">Go back</a></div>';
    require_once '../../includes/footer.php';
    exit;
}

$purchases = $pdo->prepare("
    SELECT * FROM purchases
    WHERE supplier_id = ? AND purchase_date BETWEEN ? AND ? AND status != 'cancelled'
    ORDER BY purchase_date ASC, id ASC
");
$purchases->execute([$supplier_id, $from, $to]);
$purchases_data = $purchases->fetchAll();

$purchase_total = 0;
$paid_total = 0;
$due_total = 0;
foreach ($purchases_data as $p) {
    $purchase_total += (float)$p['total_amount'];
    $paid_total += (float)$p['paid_amount'];
    $due_total += (float)$p['due_amount'];
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
  <h5 class="m-0 font-weight-bold" style="color:#0f172a;">
    <i class="fas fa-truck"></i> <?= htmlspecialchars($supplier['name']) ?>
  </h5>
  <div>
    <a href="supplier_ledger.php?from=<?= $from ?>&to=<?= $to ?>" class="btn btn-secondary btn-sm">
      <i class="fas fa-arrow-left"></i> All Suppliers
    </a>
  </div>
</div>

<div class="card shadow mb-4">
  <div class="card-body py-3">
    <div class="row">
      <div class="col-md-3"><strong>Contact:</strong> <?= htmlspecialchars($supplier['contact_person'] ?? '-') ?></div>
      <div class="col-md-3"><strong>Phone:</strong> <?= htmlspecialchars($supplier['phone'] ?? '-') ?></div>
      <div class="col-md-3"><strong>Email:</strong> <?= htmlspecialchars($supplier['email'] ?? '-') ?></div>
      <div class="col-md-3"><strong>City:</strong> <?= htmlspecialchars($supplier['city'] ?? '-') ?></div>
    </div>
  </div>
</div>

<form method="get" class="form-inline mb-4">
  <input type="hidden" name="supplier_id" value="<?= $supplier_id ?>">
  <label class="mr-2 text-muted small">From:</label>
  <input type="date" name="from" class="form-control form-control-sm mr-3" value="<?= $from ?>">
  <label class="mr-2 text-muted small">To:</label>
  <input type="date" name="to" class="form-control form-control-sm mr-3" value="<?= $to ?>">
  <button type="submit" class="btn btn-sm btn-primary"><i class="fas fa-search"></i> View</button>
  <a href="supplier_ledger.php?supplier_id=<?= $supplier_id ?>" class="btn btn-sm btn-secondary ml-2">This Month</a>
</form>

<div class="row mb-4">
  <div class="col-md-3 mb-3">
    <div class="card border-left-primary shadow h-100 py-2">
      <div class="card-body py-2">
        <div class="row no-gutters align-items-center">
          <div class="col mr-2">
            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Total Purchases</div>
            <div class="h5 mb-0 font-weight-bold" style="color:#0f172a;"><?= count($purchases_data) ?></div>
          </div>
          <div class="col-auto"><i class="fas fa-shopping-cart fa-2x text-gray-300"></i></div>
        </div>
      </div>
    </div>
  </div>
  <div class="col-md-3 mb-3">
    <div class="card border-left-info shadow h-100 py-2">
      <div class="card-body py-2">
        <div class="row no-gutters align-items-center">
          <div class="col mr-2">
            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Purchase Amount</div>
            <div class="h5 mb-0 font-weight-bold" style="color:#0f172a;"><?= formatCurrency($purchase_total) ?></div>
          </div>
          <div class="col-auto"><i class="fas fa-money-bill-wave fa-2x text-gray-300"></i></div>
        </div>
      </div>
    </div>
  </div>
  <div class="col-md-3 mb-3">
    <div class="card border-left-success shadow h-100 py-2">
      <div class="card-body py-2">
        <div class="row no-gutters align-items-center">
          <div class="col mr-2">
            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Paid Amount</div>
            <div class="h5 mb-0 font-weight-bold" style="color:#0f172a;"><?= formatCurrency($paid_total) ?></div>
          </div>
          <div class="col-auto"><i class="fas fa-check-circle fa-2x text-gray-300"></i></div>
        </div>
      </div>
    </div>
  </div>
  <div class="col-md-3 mb-3">
    <div class="card border-left-warning shadow h-100 py-2">
      <div class="card-body py-2">
        <div class="row no-gutters align-items-center">
          <div class="col mr-2">
            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Due Amount</div>
            <div class="h5 mb-0 font-weight-bold" style="color:#0f172a;"><?= formatCurrency($due_total) ?></div>
          </div>
          <div class="col-auto"><i class="fas fa-clock fa-2x text-gray-300"></i></div>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="card shadow mb-4">
  <div class="card-header py-3">
    <h6 class="m-0 font-weight-bold text-primary">
      <i class="fas fa-list"></i> Purchase Transactions
      <span class="text-muted">(<?= count($purchases_data) ?> entries)</span>
    </h6>
  </div>
  <div class="card-body">
    <?php if (empty($purchases_data)): ?>
      <div class="text-center text-muted py-3">
        <i class="fas fa-exchange-alt fa-3x mb-3"></i>
        <p>No purchases for this period.</p>
      </div>
    <?php else: ?>
      <div class="table-responsive">
        <table class="table table-bordered table-hover table-sm" width="100%" cellspacing="0">
          <thead class="thead-light">
            <tr>
              <th>Date</th>
              <th>Invoice No</th>
              <th>Notes</th>
              <th class="text-right">Total Amount</th>
              <th class="text-right">Paid</th>
              <th class="text-right">Due</th>
              <th class="text-center">Status</th>
              <th class="text-right">Balance</th>
            </tr>
          </thead>
          <tbody>
            <?php
            $balance = 0;
            foreach ($purchases_data as $p):
                $balance += (float)$p['due_amount'];
            ?>
              <tr>
                <td><?= formatDate($p['purchase_date']) ?></td>
                <td><span class="badge badge-secondary"><?= htmlspecialchars($p['invoice_no'] ?? '-') ?></span></td>
                <td><?= htmlspecialchars($p['notes'] ?? '-') ?></td>
                <td class="text-right"><?= formatCurrency($p['total_amount']) ?></td>
                <td class="text-right text-success"><?= formatCurrency($p['paid_amount']) ?></td>
                <td class="text-right text-danger"><?= formatCurrency($p['due_amount']) ?></td>
                <td class="text-center">
                  <?php
                  $status_badge = [
                      'pending' => 'warning',
                      'received' => 'success',
                      'cancelled' => 'secondary',
                  ];
                  $badge = $status_badge[$p['status']] ?? 'secondary';
                  ?>
                  <span class="badge badge-<?= $badge ?> status-badge"><?= ucfirst($p['status']) ?></span>
                </td>
                <td class="text-right font-weight-bold" style="color:#0f172a;"><?= formatCurrency($balance) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>

<?php else: ?>
<?php
$search = trim($_GET['search'] ?? '');
$suppliers = $pdo->query("
    SELECT s.*,
           IFNULL(p.purchase_count, 0) AS purchase_count,
           IFNULL(p.total_amount, 0) AS purchase_total,
           IFNULL(p.paid_amount, 0) AS paid_total,
           IFNULL(p.due_amount, 0) AS due_total
    FROM suppliers s
    LEFT JOIN (
        SELECT supplier_id,
               COUNT(*) AS purchase_count,
               SUM(total_amount) AS total_amount,
               SUM(paid_amount) AS paid_amount,
               SUM(due_amount) AS due_amount
        FROM purchases
        WHERE status != 'cancelled'
        GROUP BY supplier_id
    ) p ON p.supplier_id = s.id
    WHERE s.status = 1
    ORDER BY s.name ASC
")->fetchAll();

if ($search) {
    $suppliers = array_filter($suppliers, function ($s) use ($search) {
        $q = strtolower($search);
        return str_contains(strtolower($s['name']), $q)
            || str_contains(strtolower($s['phone'] ?? ''), $q)
            || str_contains(strtolower($s['contact_person'] ?? ''), $q)
            || str_contains(strtolower($s['city'] ?? ''), $q);
    });
}

$total_purchase = 0;
$total_paid = 0;
$total_due = 0;
foreach ($suppliers as $s) {
    $total_purchase += (float)$s['purchase_total'];
    $total_paid += (float)$s['paid_total'];
    $total_due += (float)$s['due_total'];
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
  <h5 class="m-0 font-weight-bold" style="color:#0f172a;"><i class="fas fa-truck"></i> Supplier Ledger</h5>
</div>

<form method="get" class="form-inline mb-4">
  <input type="text" name="search" class="form-control form-control-sm mr-3" style="width:300px" placeholder="Search by name, contact, or city..." value="<?= htmlspecialchars($search) ?>">
  <button type="submit" class="btn btn-sm btn-primary"><i class="fas fa-search"></i> Search</button>
  <a href="supplier_ledger.php" class="btn btn-sm btn-secondary ml-2">Clear</a>
</form>

<div class="row mb-4">
  <div class="col-md-3 mb-3">
    <div class="card border-left-primary shadow h-100 py-2">
      <div class="card-body py-2">
        <div class="row no-gutters align-items-center">
          <div class="col mr-2">
            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Active Suppliers</div>
            <div class="h5 mb-0 font-weight-bold" style="color:#0f172a;"><?= count($suppliers) ?></div>
          </div>
          <div class="col-auto"><i class="fas fa-truck fa-2x text-gray-300"></i></div>
        </div>
      </div>
    </div>
  </div>
  <div class="col-md-3 mb-3">
    <div class="card border-left-info shadow h-100 py-2">
      <div class="card-body py-2">
        <div class="row no-gutters align-items-center">
          <div class="col mr-2">
            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Total Purchases</div>
            <div class="h5 mb-0 font-weight-bold" style="color:#0f172a;"><?= formatCurrency($total_purchase) ?></div>
          </div>
          <div class="col-auto"><i class="fas fa-money-bill-wave fa-2x text-gray-300"></i></div>
        </div>
      </div>
    </div>
  </div>
  <div class="col-md-3 mb-3">
    <div class="card border-left-success shadow h-100 py-2">
      <div class="card-body py-2">
        <div class="row no-gutters align-items-center">
          <div class="col mr-2">
            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Total Paid</div>
            <div class="h5 mb-0 font-weight-bold" style="color:#0f172a;"><?= formatCurrency($total_paid) ?></div>
          </div>
          <div class="col-auto"><i class="fas fa-check-circle fa-2x text-gray-300"></i></div>
        </div>
      </div>
    </div>
  </div>
  <div class="col-md-3 mb-3">
    <div class="card border-left-warning shadow h-100 py-2">
      <div class="card-body py-2">
        <div class="row no-gutters align-items-center">
          <div class="col mr-2">
            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Total Due</div>
            <div class="h5 mb-0 font-weight-bold" style="color:#0f172a;"><?= formatCurrency($total_due) ?></div>
          </div>
          <div class="col-auto"><i class="fas fa-clock fa-2x text-gray-300"></i></div>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="card shadow mb-4">
  <div class="card-header py-3">
    <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-truck"></i> Suppliers</h6>
  </div>
  <div class="card-body">
    <?php if (empty($suppliers)): ?>
      <div class="text-center text-muted py-3">
        <i class="fas fa-truck fa-3x mb-3"></i>
        <p>No active suppliers found.</p>
      </div>
    <?php else: ?>
      <div class="table-responsive">
        <table class="table table-bordered table-hover table-sm" width="100%" cellspacing="0">
          <thead class="thead-light">
            <tr>
              <th>Name</th>
              <th>Contact Person</th>
              <th>Phone</th>
              <th>City</th>
              <th class="text-right">Purchases</th>
              <th class="text-right">Paid</th>
              <th class="text-right">Due</th>
              <th class="text-center">Action</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($suppliers as $s): ?>
              <tr>
                <td><strong><?= htmlspecialchars($s['name']) ?></strong></td>
                <td><?= htmlspecialchars($s['contact_person'] ?? '-') ?></td>
                <td><?= htmlspecialchars($s['phone'] ?? '-') ?></td>
                <td><?= htmlspecialchars($s['city'] ?? '-') ?></td>
                <td class="text-right"><?= formatCurrency((float)$s['purchase_total']) ?></td>
                <td class="text-right text-success"><?= formatCurrency((float)$s['paid_total']) ?></td>
                <td class="text-right font-weight-bold text-danger" style="color:#0f172a;"><?= formatCurrency((float)$s['due_total']) ?></td>
                <td class="text-center">
                  <a href="supplier_ledger.php?supplier_id=<?= $s['id'] ?>" class="btn btn-sm btn-outline-primary" title="View Ledger">
                    <i class="fas fa-eye"></i>
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

<?php endif; ?>

<?php require_once '../../includes/footer.php'; ?>
