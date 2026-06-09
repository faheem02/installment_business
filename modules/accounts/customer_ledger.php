<?php
session_start();
$page_title = 'Customer Ledger';
$base_url = '../../';
require_once '../../includes/functions.php';

$from = $_GET['from'] ?? date('Y-m-01');
$to = $_GET['to'] ?? date('Y-m-d');
$customer_id = (int)($_GET['customer_id'] ?? 0);

require_once '../../includes/header.php';
?>

<style>
.status-badge { font-size: .75rem; padding: 3px 10px; }
</style>

<?php if ($customer_id): ?>
<?php
$customer = getById('customers', $customer_id);
if (!$customer) {
    echo '<div class="alert alert-danger">Customer not found. <a href="customer_ledger.php">Go back</a></div>';
    require_once '../../includes/footer.php';
    exit;
}

$sales = $pdo->prepare("SELECT id, invoice_no, sale_date, total_amount, down_payment, financed_amount, payment_status, status FROM sales WHERE customer_id = ? AND sale_date BETWEEN ? AND ? ORDER BY sale_date ASC");
$sales->execute([$customer_id, $from, $to]);
$sales_data = $sales->fetchAll();

$payments = $pdo->prepare("
    SELECT p.*, s.invoice_no
    FROM payments p
    JOIN sales s ON s.id = p.sale_id
    WHERE s.customer_id = ? AND p.payment_date BETWEEN ? AND ?
    ORDER BY p.payment_date ASC, p.id ASC
");
$payments->execute([$customer_id, $from, $to]);
$payments_data = $payments->fetchAll();

$opening_due = (float)$customer['opening_due'];
$opening_paid = (float)$customer['opening_paid'];
$opening_balance = $opening_due - $opening_paid;

$sale_total = 0;
$down_payment_total = 0;
foreach ($sales_data as $s) {
    $sale_total += (float)$s['total_amount'];
    $down_payment_total += (float)$s['down_payment'];
}

$payment_total = 0;
foreach ($payments_data as $p) {
    $payment_total += (float)$p['amount'];
}

$outstanding = $opening_balance + $sale_total - $down_payment_total - $payment_total;

$transactions = [];

if ($opening_balance != 0) {
    $transactions[] = [
        'date' => null,
        'type' => 'Opening Balance',
        'ref' => '',
        'description' => 'Opening due - opening paid',
        'debit' => $opening_balance > 0 ? $opening_balance : 0,
        'credit' => $opening_balance < 0 ? abs($opening_balance) : 0,
        'sort' => 0,
    ];
}

foreach ($sales_data as $s) {
    $transactions[] = [
        'date' => $s['sale_date'],
        'type' => 'Sale',
        'ref' => $s['invoice_no'],
        'description' => 'Invoice ' . $s['invoice_no'],
        'debit' => (float)$s['total_amount'],
        'credit' => 0,
        'sort' => 1,
    ];
    if ((float)$s['down_payment'] > 0) {
        $transactions[] = [
            'date' => $s['sale_date'],
            'type' => 'Down Payment',
            'ref' => $s['invoice_no'],
            'description' => 'Down payment - ' . $s['invoice_no'],
            'debit' => 0,
            'credit' => (float)$s['down_payment'],
            'sort' => 2,
        ];
    }
}

foreach ($payments_data as $p) {
    $type_label = ucfirst(str_replace('_', ' ', $p['payment_type']));
    $transactions[] = [
        'date' => $p['payment_date'],
        'type' => $type_label,
        'ref' => 'RCT-' . str_pad($p['id'], 5, '0', STR_PAD_LEFT),
        'description' => 'Payment for ' . $p['invoice_no'] . (!empty($p['notes']) ? ' - ' . $p['notes'] : ''),
        'debit' => 0,
        'credit' => (float)$p['amount'],
        'sort' => 3,
    ];
}

usort($transactions, function ($a, $b) {
    if ($a['date'] === null && $b['date'] === null) return $a['sort'] - $b['sort'];
    if ($a['date'] === null) return -1;
    if ($b['date'] === null) return 1;
    $cmp = strcmp($a['date'], $b['date']);
    if ($cmp === 0) return $a['sort'] - $b['sort'];
    return $cmp;
});
?>

<div class="d-flex justify-content-between align-items-center mb-4">
  <h5 class="m-0 font-weight-bold" style="color:#0f172a;">
    <i class="fas fa-user-tie"></i> <?= htmlspecialchars($customer['full_name']) ?>
    <small class="text-muted">[<?= htmlspecialchars($customer['customer_no']) ?>]</small>
  </h5>
  <div>
    <a href="customer_ledger.php?from=<?= $from ?>&to=<?= $to ?>" class="btn btn-secondary btn-sm">
      <i class="fas fa-arrow-left"></i> All Customers
    </a>
  </div>
</div>

<div class="card shadow mb-4">
  <div class="card-body py-3">
    <div class="row">
      <div class="col-md-3"><strong>Phone:</strong> <?= htmlspecialchars($customer['phone']) ?></div>
      <div class="col-md-3"><strong>CNIC:</strong> <?= htmlspecialchars($customer['cnic']) ?></div>
      <div class="col-md-3"><strong>City:</strong> <?= htmlspecialchars($customer['city'] ?? '-') ?></div>
      <div class="col-md-3"><strong>Opening:</strong> <?= formatCurrency($opening_balance) ?></div>
    </div>
  </div>
</div>

<form method="get" class="form-inline mb-4">
  <input type="hidden" name="customer_id" value="<?= $customer_id ?>">
  <label class="mr-2 text-muted small">From:</label>
  <input type="date" name="from" class="form-control form-control-sm mr-3" value="<?= $from ?>">
  <label class="mr-2 text-muted small">To:</label>
  <input type="date" name="to" class="form-control form-control-sm mr-3" value="<?= $to ?>">
  <button type="submit" class="btn btn-sm btn-primary"><i class="fas fa-search"></i> View</button>
  <a href="customer_ledger.php?customer_id=<?= $customer_id ?>" class="btn btn-sm btn-secondary ml-2">This Month</a>
</form>

<div class="row mb-4">
  <div class="col-md-3 mb-3">
    <div class="card border-left-primary shadow h-100 py-2">
      <div class="card-body py-2">
        <div class="row no-gutters align-items-center">
          <div class="col mr-2">
            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Total Sales</div>
            <div class="h5 mb-0 font-weight-bold" style="color:#0f172a;"><?= count($sales_data) ?></div>
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
            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Total Purchases</div>
            <div class="h5 mb-0 font-weight-bold" style="color:#0f172a;"><?= formatCurrency($sale_total) ?></div>
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
            <div class="h5 mb-0 font-weight-bold" style="color:#0f172a;"><?= formatCurrency($down_payment_total + $payment_total) ?></div>
          </div>
          <div class="col-auto"><i class="fas fa-check-circle fa-2x text-gray-300"></i></div>
        </div>
      </div>
    </div>
  </div>
  <div class="col-md-3 mb-3">
    <div class="card border-left-danger shadow h-100 py-2">
      <div class="card-body py-2">
        <div class="row no-gutters align-items-center">
          <div class="col mr-2">
            <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">Outstanding Balance</div>
            <div class="h5 mb-0 font-weight-bold" style="color:#0f172a;"><?= formatCurrency($outstanding) ?></div>
          </div>
          <div class="col-auto"><i class="fas fa-coins fa-2x text-gray-300"></i></div>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="card shadow mb-4">
  <div class="card-header py-3">
    <h6 class="m-0 font-weight-bold text-primary">
      <i class="fas fa-list"></i> Ledger Transactions
      <span class="text-muted">(<?= count($transactions) ?> entries)</span>
    </h6>
  </div>
  <div class="card-body">
    <?php if (empty($transactions)): ?>
      <div class="text-center text-muted py-3">
        <i class="fas fa-exchange-alt fa-3x mb-3"></i>
        <p>No transactions for this period.</p>
      </div>
    <?php else: ?>
      <div class="table-responsive">
        <table class="table table-bordered table-hover table-sm" width="100%" cellspacing="0">
          <thead class="thead-light">
            <tr>
              <th>Date</th>
              <th>Type</th>
              <th>Reference</th>
              <th>Description</th>
              <th class="text-right">Debit</th>
              <th class="text-right">Credit</th>
              <th class="text-right">Balance</th>
            </tr>
          </thead>
          <tbody>
            <?php
            $balance = 0;
            foreach ($transactions as $t):
                $balance += $t['debit'] - $t['credit'];
                $row_class = '';
                if ($t['type'] === 'Opening Balance') $row_class = 'table-secondary';
                elseif ($t['type'] === 'Sale') $row_class = 'table-primary';
            ?>
              <tr class="<?= $row_class ?>">
                <td><?= $t['date'] ? formatDate($t['date']) : '-' ?></td>
                <td>
                  <?php
                  $badge_map = [
                      'Opening Balance' => 'secondary',
                      'Sale' => 'primary',
                      'Down Payment' => 'warning',
                      'Installment' => 'info',
                      'Advance' => 'success',
                      'Partial Payment' => 'info',
                  ];
                  $badge = $badge_map[$t['type']] ?? 'secondary';
                  ?>
                  <span class="badge badge-<?= $badge ?> status-badge"><?= htmlspecialchars($t['type']) ?></span>
                </td>
                <td><small class="text-muted"><?= htmlspecialchars($t['ref']) ?: '-' ?></small></td>
                <td><?= htmlspecialchars($t['description']) ?></td>
                <td class="text-right"><?= $t['debit'] > 0 ? formatCurrency($t['debit']) : '-' ?></td>
                <td class="text-right"><?= $t['credit'] > 0 ? formatCurrency($t['credit']) : '-' ?></td>
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
$customers = $pdo->query("
    SELECT c.*,
           IFNULL(s.sale_count, 0) AS sale_count,
           IFNULL(s.total_amount, 0) AS sale_total,
           IFNULL(s.down_payment, 0) AS down_payment_total,
           IFNULL(p.paid_total, 0) AS payment_total
    FROM customers c
    LEFT JOIN (
        SELECT customer_id,
               COUNT(*) AS sale_count,
               SUM(total_amount) AS total_amount,
               SUM(down_payment) AS down_payment
        FROM sales
        WHERE status != 'cancelled'
        GROUP BY customer_id
    ) s ON s.customer_id = c.id
    LEFT JOIN (
        SELECT s.customer_id, SUM(p.amount) AS paid_total
        FROM payments p
        JOIN sales s ON s.id = p.sale_id
        GROUP BY s.customer_id
    ) p ON p.customer_id = c.id
    ORDER BY c.full_name ASC
")->fetchAll();

if ($search) {
    $customers = array_filter($customers, function ($c) use ($search) {
        $s = strtolower($search);
        return str_contains(strtolower($c['full_name']), $s)
            || str_contains(strtolower($c['phone']), $s)
            || str_contains(strtolower($c['customer_no']), $s)
            || str_contains(strtolower($c['cnic']), $s);
    });
}

$total_balance = 0;
$total_purchases = 0;
$total_paid = 0;
foreach ($customers as $c) {
    $bal = ((float)$c['opening_due'] - (float)$c['opening_paid'])
         + (float)$c['sale_total']
         - (float)$c['down_payment_total']
         - (float)$c['payment_total'];
    $total_balance += $bal;
    $total_purchases += (float)$c['sale_total'];
    $total_paid += (float)$c['down_payment_total'] + (float)$c['payment_total'];
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
  <h5 class="m-0 font-weight-bold" style="color:#0f172a;"><i class="fas fa-user-tie"></i> Customer Ledger</h5>
</div>

<form method="get" class="form-inline mb-4">
  <input type="text" name="search" class="form-control form-control-sm mr-3" style="width:300px" placeholder="Search by name, phone, or CNIC..." value="<?= htmlspecialchars($search) ?>">
  <button type="submit" class="btn btn-sm btn-primary"><i class="fas fa-search"></i> Search</button>
  <a href="customer_ledger.php" class="btn btn-sm btn-secondary ml-2">Clear</a>
</form>

<div class="row mb-4">
  <div class="col-md-3 mb-3">
    <div class="card border-left-primary shadow h-100 py-2">
      <div class="card-body py-2">
        <div class="row no-gutters align-items-center">
          <div class="col mr-2">
            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Total Customers</div>
            <div class="h5 mb-0 font-weight-bold" style="color:#0f172a;"><?= count($customers) ?></div>
          </div>
          <div class="col-auto"><i class="fas fa-users fa-2x text-gray-300"></i></div>
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
            <div class="h5 mb-0 font-weight-bold" style="color:#0f172a;"><?= formatCurrency($total_purchases) ?></div>
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
    <div class="card border-left-danger shadow h-100 py-2">
      <div class="card-body py-2">
        <div class="row no-gutters align-items-center">
          <div class="col mr-2">
            <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">Total Outstanding</div>
            <div class="h5 mb-0 font-weight-bold" style="color:#0f172a;"><?= formatCurrency($total_balance) ?></div>
          </div>
          <div class="col-auto"><i class="fas fa-coins fa-2x text-gray-300"></i></div>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="card shadow mb-4">
  <div class="card-header py-3">
    <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-users"></i> Customers</h6>
  </div>
  <div class="card-body">
    <?php if (empty($customers)): ?>
      <div class="text-center text-muted py-3">
        <i class="fas fa-users fa-3x mb-3"></i>
        <p>No customers found.</p>
      </div>
    <?php else: ?>
      <div class="table-responsive">
        <table class="table table-bordered table-hover table-sm" width="100%" cellspacing="0">
          <thead class="thead-light">
            <tr>
              <th>Customer No</th>
              <th>Name</th>
              <th>Phone</th>
              <th>Opening Due</th>
              <th>Purchases</th>
              <th>Paid</th>
              <th class="text-right">Balance</th>
              <th class="text-center">Action</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($customers as $c):
                $bal = ((float)$c['opening_due'] - (float)$c['opening_paid'])
                     + (float)$c['sale_total']
                     - (float)$c['down_payment_total']
                     - (float)$c['payment_total'];
            ?>
              <tr>
                <td><span class="badge badge-secondary"><?= htmlspecialchars($c['customer_no']) ?></span></td>
                <td><strong><?= htmlspecialchars($c['full_name']) ?></strong></td>
                <td><?= htmlspecialchars($c['phone']) ?></td>
                <td><?= formatCurrency((float)$c['opening_due'] - (float)$c['opening_paid']) ?></td>
                <td><?= formatCurrency((float)$c['sale_total']) ?></td>
                <td><?= formatCurrency((float)$c['down_payment_total'] + (float)$c['payment_total']) ?></td>
                <td class="text-right font-weight-bold <?= $bal > 0 ? 'text-danger' : 'text-success' ?>" style="color:#0f172a;">
                  <?= formatCurrency($bal) ?>
                </td>
                <td class="text-center">
                  <a href="customer_ledger.php?customer_id=<?= $c['id'] ?>" class="btn btn-sm btn-outline-primary" title="View Ledger">
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
