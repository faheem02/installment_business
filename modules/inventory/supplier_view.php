<?php
session_start();
$page_title = 'Supplier Details';
$base_url = '../../';
require_once '../../includes/functions.php';

$id = (int)($_GET['id'] ?? 0);
$supplier = getById('suppliers', $id);
if (!$supplier) redirect('suppliers.php', 'Supplier not found', 'error');

$users = $pdo->query("SELECT id, username FROM users ORDER BY username")->fetchAll();

// Handle Overview financial update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_financials'])) {
    update('suppliers', [
        'opening_balance' => (float)($_POST['opening_balance'] ?? 0),
        'adjustment' => (float)($_POST['adjustment'] ?? 0),
        'updated_at' => date('Y-m-d'),
    ], $id);
    $_SESSION['success'] = 'Financial details updated';
    header("Location: supplier_view.php?id=$id&tab=overview");
    exit;
}

// Handle Cash Paid save
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_payment'])) {
    $pay_date = $_POST['payment_date'] ?? date('Y-m-d');
    $amount = (float)($_POST['amount'] ?? 0);
    $method = $_POST['payment_method'] ?? 'cash';
    $desc = trim($_POST['description'] ?? '');
    if ($amount > 0) {
        insert('supplier_payments', [
            'supplier_id' => $id,
            'amount' => $amount,
            'payment_method' => $method,
            'description' => $desc ?: null,
            'payment_date' => $pay_date,
            'created_by' => (int)($_POST['created_by'] ?? $_SESSION['user_id'] ?? 1),
            'created_at' => date('Y-m-d'),
        ]);
        $_SESSION['success'] = 'Payment recorded';
    } else {
        $_SESSION['error'] = 'Amount must be greater than 0';
    }
    header("Location: supplier_view.php?id=$id&tab=payments");
    exit;
}

// Delete handlers
if (isset($_GET['del_payment'])) {
    $pdo->prepare("DELETE FROM supplier_payments WHERE id = ? AND supplier_id = ?")->execute([(int)$_GET['del_payment'], $id]);
    $_SESSION['success'] = 'Payment deleted';
    header("Location: supplier_view.php?id=$id&tab=payments");
    exit;
}

// Fetch data
$purchases = $pdo->prepare("SELECT p.*, u.username AS added_by FROM purchases p LEFT JOIN users u ON u.id = p.created_by WHERE p.supplier_id = ? ORDER BY p.purchase_date DESC LIMIT 50");
$purchases->execute([$id]);
$purchases_data = $purchases->fetchAll();

$payments = $pdo->prepare("SELECT sp.*, u.username AS added_by FROM supplier_payments sp LEFT JOIN users u ON u.id = sp.created_by WHERE sp.supplier_id = ? ORDER BY sp.payment_date DESC LIMIT 50");
$payments->execute([$id]);
$payments_data = $payments->fetchAll();

// Calculations
$opening = (float)$supplier['opening_balance'];
$adjustment = (float)$supplier['adjustment'];
$credit_purchase = 0;
foreach ($purchases_data as $p) {
    if ($p['status'] !== 'cancelled') $credit_purchase += (float)$p['total_amount'];
}
$cash_paid = array_sum(array_column($payments_data, 'amount'));
$closing = $opening + $adjustment + $credit_purchase - $cash_paid;

$tab = $_GET['tab'] ?? 'overview';

// Handle CSV download
if (isset($_GET['download']) && $_GET['download'] === 'csv') {
    $dl_tab = $_GET['tab'] ?? 'purchases';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="supplier_' . $id . '_' . $dl_tab . '_' . date('Ymd') . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    if ($dl_tab === 'payments') {
        fputcsv($out, ['Date', 'Invoice ID', 'Amount', 'Method', 'By']);
        foreach ($payments_data as $r) {
            fputcsv($out, [$r['payment_date'], '#' . $r['id'], $r['amount'], ucfirst($r['payment_method']), $r['added_by'] ?? '-']);
        }
    }
    fclose($out);
    exit;
}

require_once '../../includes/header.php';
?>

<style>
.status-badge { font-size: .75rem; padding: 3px 10px; }
</style>

<div class="d-flex justify-content-between align-items-center mb-4">
  <h5 class="m-0 font-weight-bold" style="color:#0f172a;"><i class="fas fa-truck"></i> <?= htmlspecialchars($supplier['name']) ?></h5>
  <a href="suppliers.php" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left"></i> Back</a>
</div>

<ul class="nav nav-tabs mb-4" id="supplierTabs">
  <li class="nav-item">
    <a class="nav-link <?= $tab === 'overview' ? 'active' : '' ?>" href="supplier_view.php?id=<?= $id ?>&tab=overview"><i class="fas fa-chart-pie"></i> Overview</a>
  </li>
  <li class="nav-item">
    <a class="nav-link <?= $tab === 'payments' ? 'active' : '' ?>" href="supplier_view.php?id=<?= $id ?>&tab=payments"><i class="fas fa-hand-holding-usd"></i> Transactions</a>
  </li>
</ul>

<?php if ($tab === 'overview'): ?>
<div class="row">
  <div class="col-lg-7 mb-4">
    <div class="card shadow">
      <div class="card-header py-3 d-flex justify-content-between align-items-center">
        <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-calculator"></i> Financial Summary</h6>
        <button class="btn btn-sm btn-outline-primary" onclick="$('#editFinancials').toggleClass('d-none')"><i class="fas fa-pen"></i> Edit</button>
      </div>
      <div class="card-body">
        <div id="editFinancials" class="d-none mb-4 p-3 bg-light rounded border">
          <form method="post">
            <div class="form-row">
              <div class="form-group col-md-6 mb-2">
                <label class="small text-muted">Opening Balance</label>
                <input type="number" name="opening_balance" class="form-control form-control-sm" step="0.01" value="<?= $supplier['opening_balance'] ?>">
              </div>
              <div class="form-group col-md-6 mb-2">
                <label class="small text-muted">Adjustment <i class="fas fa-info-circle" title="Positive = we owe more, Negative = supplier owes us"></i></label>
                <input type="number" name="adjustment" class="form-control form-control-sm" step="0.01" value="<?= $supplier['adjustment'] ?>">
              </div>
            </div>
            <button type="submit" name="update_financials" class="btn btn-primary btn-sm"><i class="fas fa-save"></i> Update</button>
            <button type="button" class="btn btn-secondary btn-sm" onclick="$('#editFinancials').addClass('d-none')">Cancel</button>
          </form>
        </div>
        <table class="table table-sm table-borderless mb-0" style="font-size:.95rem;">
          <tbody>
            <tr><td><i class="fas fa-coins text-primary fa-fw mr-2"></i> Opening Balance</td><td class="text-right font-weight-bold"><?= formatCurrency($opening) ?></td></tr>
            <tr><td><i class="fas fa-adjust text-info fa-fw mr-2"></i> Adjustment <span class="text-muted small">(+/-)</span></td><td class="text-right font-weight-bold"><?= formatCurrency($adjustment) ?></td></tr>
            <tr><td colspan="2"><hr class="my-1"></td></tr>
            <tr><td><i class="fas fa-shopping-cart text-success fa-fw mr-2"></i> Products Supplied</td><td class="text-right font-weight-bold">+ <?= formatCurrency($credit_purchase) ?></td></tr>
            <tr><td><i class="fas fa-hand-holding-usd text-warning fa-fw mr-2"></i> Cash Paid</td><td class="text-right font-weight-bold text-success">- <?= formatCurrency($cash_paid) ?></td></tr>
          </tbody>
        </table>
        <div class="text-center p-3 bg-primary text-white rounded mt-3">
          <div class="small text-uppercase opacity-75">Closing Balance</div>
          <h3 class="font-weight-bold mb-0"><?= formatCurrency($closing) ?></h3>
        </div>
      </div>
    </div>
  </div>
  <div class="col-lg-5 mb-4">
    <div class="card shadow h-100">
      <div class="card-header py-3"><h6 class="m-0 font-weight-bold text-info"><i class="fas fa-chart-bar"></i> Quick Stats</h6></div>
      <div class="card-body py-2">
        <table class="table table-sm table-borderless mb-0">
          <tr><td class="text-muted">Total Purchases</td><td class="text-right font-weight-bold"><?= count($purchases_data) ?></td></tr>
          <tr><td class="text-muted">Last Purchase</td><td class="text-right"><?= !empty($purchases_data) ? formatDate($purchases_data[0]['purchase_date']) : '-' ?></td></tr>
          <tr><td class="text-muted">Total Payments</td><td class="text-right font-weight-bold"><?= count($payments_data) ?></td></tr>
          <tr><td class="text-muted">Registered</td><td class="text-right"><?= formatDate($supplier['created_at']) ?></td></tr>
          <tr><td class="text-muted">Status</td><td class="text-right"><span class="badge badge-<?= $supplier['status'] ? 'success' : 'secondary' ?>"><?= $supplier['status'] ? 'Active' : 'Inactive' ?></span></td></tr>
        </table>
      </div>
    </div>
  </div>
</div>

<?php elseif ($tab === 'payments'): ?>
<?php
// Fetch purchase items for product detail
$purchase_items = $pdo->prepare("
    SELECT pi.*, p.name AS product_name, p.code AS product_code, pu.purchase_date, pu.invoice_no AS purchase_invoice
    FROM purchase_items pi
    JOIN purchases pu ON pu.id = pi.purchase_id
    LEFT JOIN products p ON p.id = pi.product_id
    WHERE pu.supplier_id = ?
    ORDER BY pu.purchase_date DESC
");
$purchase_items->execute([$id]);
$purchase_items_data = $purchase_items->fetchAll();

// Build a unified ledger (purchases as debit, payments as credit)
$ledger = [];
foreach ($purchases_data as $p) {
    $ledger[] = [
        'date' => $p['purchase_date'],
        'type' => 'purchase',
        'ref' => $p['invoice_no'] ?? '#' . $p['id'],
        'debit' => (float)$p['total_amount'],
        'credit' => 0,
        'desc' => 'Products supplied',
        'status' => $p['status'],
        'id' => $p['id'],
    ];
}
foreach ($payments_data as $p) {
    $ledger[] = [
        'date' => $p['payment_date'],
        'type' => 'payment',
        'ref' => '#' . $p['id'],
        'debit' => 0,
        'credit' => (float)$p['amount'],
        'desc' => $p['description'] ?? 'Cash paid',
        'status' => null,
        'method' => $p['payment_method'],
        'id' => $p['id'],
    ];
}
usort($ledger, function($a, $b) { return strcmp($a['date'], $b['date']); });
?>
<div class="row">
  <!-- LEFT: Record Payment -->
  <div class="col-lg-5 mb-4">
    <div class="card shadow">
      <div class="card-header py-3"><h6 class="m-0 font-weight-bold text-success"><i class="fas fa-plus-circle"></i> Record Payment</h6></div>
      <div class="card-body">
        <form method="post">
          <div class="form-group">
            <label class="small">Date</label>
            <input type="date" name="payment_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
          </div>
          <div class="form-group">
            <label class="small">Amount <span class="text-danger">*</span></label>
            <input type="number" name="amount" class="form-control" step="0.01" min="0.01" required>
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
            <label class="small">Description</label>
            <textarea name="description" class="form-control" rows="2" placeholder="Notes about this payment"></textarea>
          </div>
          <div class="form-group">
            <label class="small">By</label>
            <select name="created_by" class="form-control">
              <?php foreach ($users as $u): ?>
                <option value="<?= $u['id'] ?>" <?= ($u['id'] == ($_SESSION['user_id'] ?? 1)) ? 'selected' : '' ?>><?= htmlspecialchars($u['username']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <button type="submit" name="save_payment" class="btn btn-success btn-block py-2"><i class="fas fa-save"></i> Save Payment</button>
        </form>
      </div>
    </div>

    <!-- Supplier Info Card -->
    <div class="card shadow mt-3">
      <div class="card-header py-3"><h6 class="m-0 font-weight-bold text-info"><i class="fas fa-info-circle"></i> Supplier Summary</h6></div>
      <div class="card-body py-2">
        <table class="table table-sm table-borderless mb-0">
          <tr><td class="text-muted">Total Products Supplied</td><td class="text-right font-weight-bold"><?= array_sum(array_column($purchase_items_data, 'quantity')) ?></td></tr>
          <tr><td class="text-muted">Total Purchase Value</td><td class="text-right font-weight-bold"><?= formatCurrency($credit_purchase) ?></td></tr>
          <tr><td class="text-muted">Total Paid</td><td class="text-right font-weight-bold text-success"><?= formatCurrency($cash_paid) ?></td></tr>
          <tr><td class="text-muted">Balance</td><td class="text-right font-weight-bold <?= $closing > 0 ? 'text-danger' : 'text-success' ?>"><?= formatCurrency($closing) ?></td></tr>
        </table>
      </div>
    </div>
  </div>

  <!-- RIGHT: Unified Ledger -->
  <div class="col-lg-7 mb-4">
    <!-- Recent Payments -->
    <div class="card shadow mb-3">
      <div class="card-header py-3 d-flex justify-content-between align-items-center">
        <h6 class="m-0 font-weight-bold text-success"><i class="fas fa-money-bill-wave"></i> Payment History</h6>
        <span class="badge badge-success status-badge">Total: <?= formatCurrency($cash_paid) ?></span>
      </div>
      <div class="card-body">
        <?php if (empty($payments_data)): ?>
          <p class="text-muted mb-0 text-center">No payments recorded</p>
        <?php else: ?>
          <div class="table-responsive">
            <table class="table table-bordered table-hover table-sm">
              <thead class="thead-light">
                <tr><th>Date</th><th>Ref</th><th class="text-right">Amount</th><th>Method</th><th>Description</th><th>By</th><th>Action</th></tr>
              </thead>
              <tbody>
                <?php foreach ($payments_data as $p): ?>
                  <tr>
                    <td><?= formatDate($p['payment_date']) ?></td>
                    <td><span class="badge badge-secondary">#<?= $p['id'] ?></span></td>
                    <td class="text-right font-weight-bold text-danger"><?= formatCurrency($p['amount']) ?></td>
                    <td><span class="badge badge-<?= $p['payment_method'] === 'cash' ? 'success' : ($p['payment_method'] === 'card' ? 'info' : 'primary') ?> status-badge"><?= ucfirst(str_replace('_', ' ', $p['payment_method'])) ?></span></td>
                    <td class="small"><?= htmlspecialchars($p['description'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($p['added_by'] ?? '-') ?></td>
                    <td><a href="supplier_view.php?id=<?= $id ?>&tab=payments&del_payment=<?= $p['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete?')"><i class="fas fa-trash"></i></a></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Products Supplied -->
    <div class="card shadow mb-3">
      <div class="card-header py-3 d-flex justify-content-between align-items-center">
        <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-boxes"></i> Products Supplied</h6>
        <span class="text-muted small"><?= count($purchase_items_data) ?> records</span>
      </div>
      <div class="card-body">
        <?php if (empty($purchase_items_data)): ?>
          <p class="text-muted mb-0 text-center">No products supplied yet</p>
        <?php else: ?>
          <div class="table-responsive">
            <table class="table table-bordered table-hover table-sm">
              <thead class="thead-light">
                <tr><th>Date</th><th>Product</th><th class="text-center">Qty</th><th class="text-right">Price</th><th class="text-right">Total</th></tr>
              </thead>
              <tbody>
                <?php foreach ($purchase_items_data as $pi): ?>
                  <tr>
                    <td><?= formatDate($pi['purchase_date']) ?></td>
                    <td><?= htmlspecialchars($pi['product_name'] ?? $pi['product_code'] ?? 'Item') ?> <small class="text-muted">(<?= htmlspecialchars($pi['purchase_invoice'] ?? '-') ?>)</small></td>
                    <td class="text-center"><?= $pi['quantity'] ?></td>
                    <td class="text-right"><?= formatCurrency($pi['purchase_price']) ?></td>
                    <td class="text-right font-weight-bold"><?= formatCurrency($pi['subtotal']) ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Full Ledger -->
    <div class="card shadow">
      <div class="card-header py-3"><h6 class="m-0 font-weight-bold text-info"><i class="fas fa-balance-scale"></i> Account Ledger</h6></div>
      <div class="card-body">
        <?php if (empty($ledger)): ?>
          <p class="text-muted mb-0 text-center">No transactions yet</p>
        <?php else: ?>
          <div class="table-responsive">
            <table class="table table-bordered table-hover table-sm">
              <thead class="thead-light">
                <tr><th>Date</th><th>Ref</th><th>Description</th><th class="text-right">Debit (Supplied)</th><th class="text-right">Credit (Paid)</th><th class="text-right">Balance</th></tr>
              </thead>
              <tbody>
                <?php $bal = $opening + $adjustment; foreach ($ledger as $l):
                  $bal += $l['debit'] - $l['credit'];
                ?>
                  <tr class="<?= $l['type'] === 'purchase' ? '' : 'table-success' ?>">
                    <td><?= formatDate($l['date']) ?></td>
                    <td><span class="badge badge-<?= $l['type'] === 'purchase' ? 'primary' : 'success' ?>"><?= htmlspecialchars($l['ref']) ?></span></td>
                    <td class="small"><?= htmlspecialchars($l['desc']) ?></td>
                    <td class="text-right"><?= $l['debit'] ? formatCurrency($l['debit']) : '-' ?></td>
                    <td class="text-right"><?= $l['credit'] ? formatCurrency($l['credit']) : '-' ?></td>
                    <td class="text-right font-weight-bold"><?= formatCurrency($bal) ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
              <tfoot>
                <tr class="font-weight-bold" style="background:#f8f9fc;">
                  <td colspan="3" class="text-right">Closing Balance</td>
                  <td class="text-right"><?= formatCurrency(array_sum(array_column($ledger, 'debit'))) ?></td>
                  <td class="text-right"><?= formatCurrency(array_sum(array_column($ledger, 'credit'))) ?></td>
                  <td class="text-right"><?= formatCurrency($closing) ?></td>
                </tr>
              </tfoot>
            </table>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<?php endif; ?>

<?php require_once '../../includes/footer.php'; ?>
