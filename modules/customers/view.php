<?php
session_start();
$page_title = 'Customer Details';
$base_url = '../../';
require_once '../../includes/functions.php';

$id = (int)($_GET['id'] ?? 0);
$customer = getById('customers', $id);
if (!$customer) redirect('index.php', 'Customer not found', 'error');

$users = $pdo->query("SELECT id, username FROM users ORDER BY username")->fetchAll();

// Handle Overview financial update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_financials'])) {
    update('customers', [
        'opening_due' => (float)($_POST['opening_due'] ?? 0),
        'opening_paid' => (float)($_POST['opening_paid'] ?? 0),
        'updated_at' => date('Y-m-d'),
    ], $id);
    $_SESSION['success'] = 'Financial details updated';
    header("Location: view.php?id=$id&tab=overview");
    exit;
}

// Handle Credit Sale save
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_sale'])) {
    $inv = trim($_POST['invoice_no'] ?? '');
    $date = $_POST['sale_date'] ?? date('Y-m-d');
    $amount = (float)($_POST['total_amount'] ?? 0);
    $status = $_POST['status'] ?? 'active';
    $created_by = (int)($_POST['created_by'] ?? $_SESSION['user_id'] ?? 1);
    if ($amount > 0) {
        insert('sales', [
            'customer_id' => $id,
            'invoice_no' => $inv ?: 'SL-' . date('ymd') . '-' . time(),
            'sale_date' => $date,
            'subtotal' => $amount,
            'total_amount' => $amount,
            'down_payment' => 0,
            'financed_amount' => $amount,
            'payment_status' => 'pending',
            'status' => $status,
            'created_by' => $created_by,
            'created_at' => date('Y-m-d'),
        ]);
        $_SESSION['success'] = 'Credit sale recorded';
    } else {
        $_SESSION['error'] = 'Amount must be greater than 0';
    }
    header("Location: view.php?id=$id&tab=sales");
    exit;
}

// Handle Sale Return save
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_return'])) {
    $ret_date = $_POST['return_date'] ?? date('Y-m-d');
    $amount = (float)($_POST['amount'] ?? 0);
    $notes = trim($_POST['notes'] ?? '');
    $sale_id = !empty($_POST['sale_id']) ? (int)$_POST['sale_id'] : null;
    if ($amount > 0) {
        insert('sale_returns', [
            'sale_id' => $sale_id,
            'customer_id' => $id,
            'return_date' => $ret_date,
            'amount' => $amount,
            'notes' => $notes ?: null,
            'created_by' => (int)($_POST['created_by'] ?? $_SESSION['user_id'] ?? 1),
            'created_at' => date('Y-m-d'),
        ]);
        $_SESSION['success'] = 'Sale return recorded';
    } else {
        $_SESSION['error'] = 'Amount must be greater than 0';
    }
    header("Location: view.php?id=$id&tab=returns");
    exit;
}

// Handle Credit Received save
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_payment'])) {
    $pay_date = $_POST['payment_date'] ?? date('Y-m-d');
    $amount = (float)($_POST['amount'] ?? 0);
    $method = $_POST['payment_method'] ?? 'cash';
    $desc = trim($_POST['notes'] ?? '');
    $installment_id = !empty($_POST['installment_id']) ? (int)$_POST['installment_id'] : null;

    if ($amount > 0 && $installment_id) {
        $inst = getById('sale_installments', $installment_id);
        if ($inst) {
            $new_paid = (float)$inst['paid_amount'] + $amount;
            $new_balance = max(0, (float)$inst['amount'] - $new_paid);
            $new_status = $new_balance <= 0 ? 'paid' : 'partial';

            update('sale_installments', [
                'paid_amount' => $new_paid,
                'balance' => $new_balance,
                'status' => $new_status,
                'paid_date' => $new_balance <= 0 ? $pay_date : null,
                'updated_at' => date('Y-m-d'),
            ], $installment_id);

            insert('payments', [
                'sale_id' => $inst['sale_id'],
                'installment_id' => $installment_id,
                'payment_date' => $pay_date,
                'amount' => $amount,
                'payment_type' => 'installment',
                'payment_method' => $method,
                'notes' => $desc ?: null,
                'received_by' => (int)($_POST['created_by'] ?? $_SESSION['user_id'] ?? 1),
                'created_at' => date('Y-m-d'),
            ]);

            $_SESSION['success'] = 'Payment recorded for installment #' . $inst['installment_no'];
        } else {
            $_SESSION['error'] = 'Installment not found';
        }
    } else {
        $_SESSION['error'] = 'Select an installment and enter a valid amount';
    }
    header("Location: view.php?id=$id&tab=payments");
    exit;
}

// Delete handlers
if (isset($_GET['del_sale'])) {
    $pdo->prepare("DELETE FROM sales WHERE id = ? AND customer_id = ?")->execute([(int)$_GET['del_sale'], $id]);
    $_SESSION['success'] = 'Sale deleted';
    header("Location: view.php?id=$id&tab=sales");
    exit;
}
if (isset($_GET['del_return'])) {
    $pdo->prepare("DELETE FROM sale_returns WHERE id = ? AND customer_id = ?")->execute([(int)$_GET['del_return'], $id]);
    $_SESSION['success'] = 'Return deleted';
    header("Location: view.php?id=$id&tab=returns");
    exit;
}
if (isset($_GET['del_payment'])) {
    $pdo->prepare("DELETE FROM payments WHERE id = ? AND sale_id IN (SELECT s2.id FROM (SELECT id FROM sales WHERE customer_id = ?) s2)")->execute([(int)$_GET['del_payment'], $id]);
    $_SESSION['success'] = 'Payment deleted';
    header("Location: view.php?id=$id&tab=payments");
    exit;
}
if (isset($_GET['del_inst_payment'])) {
    $inst_id = (int)$_GET['del_inst_payment'];
    $pdo->prepare("DELETE FROM payments WHERE installment_id = ?")->execute([$inst_id]);
    update('sale_installments', [
        'paid_amount' => 0,
        'balance' => $pdo->query("SELECT amount FROM sale_installments WHERE id = $inst_id")->fetchColumn(),
        'status' => 'pending',
        'paid_date' => null,
        'updated_at' => date('Y-m-d'),
    ], $inst_id);
    $_SESSION['success'] = 'Installment payments deleted and reset';
    header("Location: view.php?id=$id&tab=payments");
    exit;
}

// Fetch data
$sales = $pdo->prepare("SELECT s.*, u.username AS added_by FROM sales s LEFT JOIN users u ON u.id = s.created_by WHERE s.customer_id = ? ORDER BY s.sale_date DESC LIMIT 50");
$sales->execute([$id]);
$sales_data = $sales->fetchAll();

$payments = $pdo->prepare("SELECT p.*, u.username AS added_by FROM payments p LEFT JOIN users u ON u.id = p.received_by WHERE p.sale_id IN (SELECT s2.id FROM (SELECT id FROM sales WHERE customer_id = ?) s2) ORDER BY p.payment_date DESC LIMIT 50");
$payments->execute([$id]);
$payments_data = $payments->fetchAll();

$returns = $pdo->prepare("SELECT sr.*, s.invoice_no, u.username AS added_by FROM sale_returns sr LEFT JOIN sales s ON s.id = sr.sale_id LEFT JOIN users u ON u.id = sr.created_by WHERE sr.customer_id = ? ORDER BY sr.return_date DESC LIMIT 50");
$returns->execute([$id]);
$returns_data = $returns->fetchAll();

// Calculations
$opening_due = (float)$customer['opening_due'];
$opening_paid = (float)$customer['opening_paid'];
$credit_sale = 0;
foreach ($sales_data as $s) {
    if ($s['status'] !== 'cancelled') $credit_sale += (float)$s['total_amount'];
}
$payments_total = array_sum(array_column($payments_data, 'amount'));
$returns_total = array_sum(array_column($returns_data, 'amount'));
$closing = ($opening_due - $opening_paid) + $credit_sale - $returns_total - $payments_total;

// Installments data for payments tab
$installments = $pdo->prepare("
    SELECT si.*, s.invoice_no, s.total_amount, s.sale_date, s.down_payment, s.financed_amount,
           s.monthly_installment, s.total_installments
    FROM sale_installments si
    JOIN sales s ON s.id = si.sale_id
    WHERE s.customer_id = ? AND s.status = 'active'
    ORDER BY si.due_date ASC
");
$installments->execute([$id]);
$installments_data = $installments->fetchAll();

$sale_items = $pdo->prepare("
    SELECT si.*, p.code, p.name, si.item_description
    FROM sale_items si
    LEFT JOIN products p ON p.id = si.product_id
    WHERE si.sale_id IN (SELECT s2.id FROM (SELECT id FROM sales WHERE customer_id = ? AND status = 'active') s2)
");
$sale_items->execute([$id]);
$sale_items_data = $sale_items->fetchAll();

$sale_items_map = [];
foreach ($sale_items_data as $item) {
    $sale_items_map[$item['sale_id']][] = $item;
}

$sale_inst_map = [];
$current_installments = [];
foreach ($installments_data as $inst) {
    $sale_inst_map[$inst['sale_id']][] = $inst;
    if ($inst['status'] === 'pending' || $inst['status'] === 'partial') {
        if (!isset($current_installments[$inst['sale_id']])) {
            $current_installments[$inst['sale_id']] = $inst;
        }
    }
}

$installment_sales = array_filter($sales_data, function($s) {
    return ($s['total_installments'] ?? 0) > 0 && $s['status'] === 'active';
});

// Overview stats
$total_installments_count = count($installments_data);
$paid_installments_count = 0;
$pending_installments_count = 0;
$overdue_installments_count = 0;
$total_installed_paid = 0;
foreach ($installments_data as $inst) {
    $total_installed_paid += (float)$inst['paid_amount'];
    if ($inst['status'] === 'paid') $paid_installments_count++;
    elseif ($inst['status'] === 'overdue' || $inst['status'] === 'late') $overdue_installments_count++;
    else $pending_installments_count++;
}

// Total products purchased across all sales
$all_items = $pdo->prepare("
    SELECT SUM(si.quantity) as total_qty, COUNT(DISTINCT si.product_id) as distinct_products
    FROM sale_items si
    WHERE si.product_id IS NOT NULL AND si.sale_id IN (SELECT s2.id FROM (SELECT id FROM sales WHERE customer_id = ?) s2)
");
$all_items->execute([$id]);
$all_items_data = $all_items->fetch();
$total_products_qty = (int)($all_items_data['total_qty'] ?? 0);
$total_distinct_products = (int)($all_items_data['distinct_products'] ?? 0);

$tab = $_GET['tab'] ?? 'overview';

// Handle CSV download
if (isset($_GET['download']) && $_GET['download'] === 'csv') {
    $dl_tab = $_GET['tab'] ?? 'sales';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="customer_' . $id . '_' . $dl_tab . '_' . date('Ymd') . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    if ($dl_tab === 'sales') {
        fputcsv($out, ['Date', 'Invoice ID', 'Amount', 'Status', 'By']);
        foreach ($sales_data as $r) {
            fputcsv($out, [$r['sale_date'], $r['invoice_no'] ?? '-', $r['total_amount'], ucfirst($r['status']), $r['added_by'] ?? '-']);
        }
    } elseif ($dl_tab === 'returns') {
        fputcsv($out, ['Date', 'Invoice ID', 'Amount', 'Notes', 'By']);
        foreach ($returns_data as $r) {
            fputcsv($out, [$r['return_date'], $r['invoice_no'] ?? '-', $r['amount'], $r['notes'] ?? '-', $r['added_by'] ?? '-']);
        }
    } elseif ($dl_tab === 'payments') {
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
  <h5 class="m-0 font-weight-bold" style="color:#0f172a;"><i class="fas fa-user"></i> <?= htmlspecialchars($customer['full_name']) ?> <small class="text-muted">[<?= htmlspecialchars($customer['customer_no']) ?>]</small></h5>
  <a href="index.php" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left"></i> Back</a>
</div>



<ul class="nav nav-tabs mb-4" id="customerTabs">
  <li class="nav-item">
    <a class="nav-link <?= $tab === 'overview' ? 'active' : '' ?>" href="view.php?id=<?= $id ?>&tab=overview"><i class="fas fa-chart-pie"></i> Overview</a>
  </li>
  <!-- <li class="nav-item">
    <a class="nav-link <?= $tab === 'sales' ? 'active' : '' ?>" href="view.php?id=<?= $id ?>&tab=sales"><i class="fas fa-shopping-cart"></i> Credit Sale</a>
  </li>
  <li class="nav-item">
    <a class="nav-link <?= $tab === 'returns' ? 'active' : '' ?>" href="view.php?id=<?= $id ?>&tab=returns"><i class="fas fa-undo"></i> Sale Return</a>
  </li> -->
  <li class="nav-item">
    <a class="nav-link <?= $tab === 'payments' ? 'active' : '' ?>" href="view.php?id=<?= $id ?>&tab=payments"><i class="fas fa-hand-holding-usd"></i> Credit Received</a>
  </li>
</ul>

<?php if ($tab === 'overview'): ?>
<div class="row">
  <!-- Left: Customer Info + Stats Cards -->
  <div class="col-lg-7 mb-4">
    <!-- Financial Summary -->
    <div class="card shadow mb-4">
      <div class="card-header py-3 d-flex justify-content-between align-items-center">
        <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-calculator"></i> Financial Summary</h6>
        <button class="btn btn-sm btn-outline-primary" onclick="$('#editFinancials').toggleClass('d-none')"><i class="fas fa-pen"></i> Edit</button>
      </div>
      <div class="card-body">
        <div id="editFinancials" class="d-none mb-4 p-3 bg-light rounded border">
          <form method="post">
            <div class="form-row">
              <div class="form-group col-md-6 mb-2">
                <label class="small text-muted">Opening Due</label>
                <input type="number" name="opening_due" class="form-control form-control-sm" step="0.01" value="<?= $customer['opening_due'] ?>">
              </div>
              <div class="form-group col-md-6 mb-2">
                <label class="small text-muted">Opening Paid</label>
                <input type="number" name="opening_paid" class="form-control form-control-sm" step="0.01" value="<?= $customer['opening_paid'] ?>">
              </div>
            </div>
            <button type="submit" name="update_financials" class="btn btn-primary btn-sm"><i class="fas fa-save"></i> Update</button>
            <button type="button" class="btn btn-secondary btn-sm" onclick="$('#editFinancials').addClass('d-none')">Cancel</button>
          </form>
        </div>
        <div class="row">
          <div class="col-md-6">
            <table class="table table-sm table-borderless mb-0" style="font-size:.9rem;">
              <tr><td><i class="fas fa-coins text-primary fa-fw mr-2"></i> Opening Due</td><td class="text-right font-weight-bold"><?= formatCurrency($opening_due) ?></td></tr>
              <tr><td><i class="fas fa-check-circle text-success fa-fw mr-2"></i> Opening Paid</td><td class="text-right font-weight-bold text-success">- <?= formatCurrency($opening_paid) ?></td></tr>
              <tr><td><i class="fas fa-balance-scale fa-fw mr-2"></i> <strong>Net Opening</strong></td><td class="text-right font-weight-bold"><?= formatCurrency($opening_due - $opening_paid) ?></td></tr>
              <tr><td colspan="2"><hr class="my-1"></td></tr>
              <tr><td><i class="fas fa-shopping-cart text-info fa-fw mr-2"></i> Credit Sale</td><td class="text-right font-weight-bold">+ <?= formatCurrency($credit_sale) ?></td></tr>
              <tr><td><i class="fas fa-undo text-danger fa-fw mr-2"></i> Sale Return</td><td class="text-right font-weight-bold text-danger">- <?= formatCurrency($returns_total) ?></td></tr>
              <tr><td><i class="fas fa-hand-holding-usd text-warning fa-fw mr-2"></i> Credit Received</td><td class="text-right font-weight-bold text-success">- <?= formatCurrency($payments_total) ?></td></tr>
            </table>
          </div>
          <div class="col-md-6">
            <table class="table table-sm table-borderless mb-0" style="font-size:.9rem;">
              <tr><td><i class="fas fa-calendar-check text-primary fa-fw mr-2"></i> Total Installments</td><td class="text-right font-weight-bold"><?= $total_installments_count ?></td></tr>
              <tr><td><i class="fas fa-check-circle text-success fa-fw mr-2"></i> Paid</td><td class="text-right font-weight-bold text-success"><?= $paid_installments_count ?></td></tr>
              <tr><td><i class="fas fa-hourglass-half text-warning fa-fw mr-2"></i> Pending</td><td class="text-right font-weight-bold text-warning"><?= $pending_installments_count ?></td></tr>
              <tr><td><i class="fas fa-exclamation-circle text-danger fa-fw mr-2"></i> Overdue</td><td class="text-right font-weight-bold text-danger"><?= $overdue_installments_count ?></td></tr>
              <tr><td colspan="2"><hr class="my-1"></td></tr>
              <tr><td><i class="fas fa-money-check-alt text-success fa-fw mr-2"></i> Total Paid via Installments</td><td class="text-right font-weight-bold text-success"><?= formatCurrency($total_installed_paid) ?></td></tr>
              <tr><td><i class="fas fa-boxes text-info fa-fw mr-2"></i> Products Purchased</td><td class="text-right font-weight-bold"><?= $total_products_qty ?> (<?= $total_distinct_products ?> kinds)</td></tr>
            </table>
          </div>
        </div>
        <div class="text-center p-3 bg-primary text-white rounded mt-3">
          <div class="small text-uppercase opacity-75">Closing Balance</div>
          <h3 class="font-weight-bold mb-0"><?= formatCurrency($closing) ?></h3>
        </div>
      </div>
    </div>

    <!-- Recent Sales with Installment Progress -->
    <div class="card shadow">
      <div class="card-header py-3 d-flex justify-content-between align-items-center">
        <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-shopping-cart"></i> Active Installment Plans</h6>
      </div>
      <div class="card-body">
        <?php
        $active_plans = array_filter($sales_data, function($s) {
            return ($s['total_installments'] ?? 0) > 0 && $s['status'] === 'active';
        });
        ?>
        <?php if (empty($active_plans)): ?>
          <p class="text-muted mb-0 text-center">No active installment plans</p>
        <?php else: ?>
          <div class="table-responsive">
            <table class="table table-sm table-bordered">
              <thead class="thead-light">
                <tr>
                  <th>Invoice</th>
                  <th>Date</th>
                  <th class="text-right">Amount</th>
                  <th class="text-right">Financed</th>
                  <th class="text-center">Months</th>
                  <th class="text-center">Paid</th>
                  <th class="text-right">Monthly</th>
                  <th style="width:130px;">Progress</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($active_plans as $s):
                  $sid = $s['id'];
                  $s_insts = $sale_inst_map[$sid] ?? [];
                  $s_paid = 0;
                  $s_total = count($s_insts);
                  foreach ($s_insts as $inst) {
                      if ($inst['status'] === 'paid') $s_paid++;
                  }
                  $s_pct = $s_total > 0 ? round($s_paid / $s_total * 100) : 0;
                ?>
                  <tr>
                    <td><span class="badge badge-secondary"><?= htmlspecialchars($s['invoice_no']) ?></span></td>
                    <td><?= formatDate($s['sale_date']) ?></td>
                    <td class="text-right font-weight-bold"><?= formatCurrency($s['total_amount']) ?></td>
                    <td class="text-right"><?= formatCurrency($s['financed_amount']) ?></td>
                    <td class="text-center"><?= $s['total_installments'] ?></td>
                    <td class="text-center"><?= $s_paid ?>/<?= $s_total ?></td>
                    <td class="text-right"><?= formatCurrency($s['monthly_installment']) ?></td>
                    <td>
                      <div class="progress" style="height:12px;">
                        <div class="progress-bar bg-<?= $s_pct >= 100 ? 'success' : 'primary' ?>" style="width:<?= $s_pct ?>%;"><?= $s_pct ?>%</div>
                      </div>
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

  <!-- Right: Quick Stats Card -->
  <div class="col-lg-5 mb-4">
    <div class="card shadow h-100">
      <div class="card-header py-3"><h6 class="m-0 font-weight-bold text-info"><i class="fas fa-chart-bar"></i> Customer Profile</h6></div>
      <div class="card-body">
        <table class="table table-sm table-borderless mb-0">
          <tr><td class="text-muted"><i class="fas fa-user fa-fw mr-2"></i> Name</td><td class="text-right font-weight-bold"><?= htmlspecialchars($customer['full_name']) ?></td></tr>
          <tr><td class="text-muted"><i class="fas fa-id-card fa-fw mr-2"></i> Customer No</td><td class="text-right"><span class="badge badge-secondary"><?= htmlspecialchars($customer['customer_no']) ?></span></td></tr>
          <tr><td class="text-muted"><i class="fas fa-phone fa-fw mr-2"></i> Phone</td><td class="text-right"><?= htmlspecialchars($customer['phone'] ?? '-') ?></td></tr>
          <tr><td class="text-muted"><i class="fas fa-map-marker-alt fa-fw mr-2"></i> Address</td><td class="text-right small"><?= htmlspecialchars($customer['address'] ?? '-') ?></td></tr>
          <tr><td colspan="2"><hr class="my-1"></td></tr>
          <tr><td class="text-muted"><i class="fas fa-shopping-cart fa-fw mr-2"></i> Total Sales</td><td class="text-right font-weight-bold"><?= count($sales_data) ?></td></tr>
          <tr><td class="text-muted"><i class="fas fa-hand-holding-usd fa-fw mr-2"></i> Total Payments</td><td class="text-right font-weight-bold"><?= count($payments_data) ?></td></tr>
          <tr><td class="text-muted"><i class="fas fa-calendar-plus fa-fw mr-2"></i> Registered</td><td class="text-right"><?= formatDate($customer['created_at']) ?></td></tr>
          <tr><td class="text-muted"><i class="fas fa-clock fa-fw mr-2"></i> Last Sale</td><td class="text-right"><?= !empty($sales_data) ? formatDate($sales_data[0]['sale_date']) : '-' ?></td></tr>
        </table>

        <hr>

        <h6 class="font-weight-bold text-success"><i class="fas fa-chart-line"></i> Installment Summary</h6>
        <div class="row text-center mt-2">
          <div class="col-4">
            <div class="text-success h4 font-weight-bold mb-0"><?= $paid_installments_count ?></div>
            <div class="small text-muted">Paid</div>
          </div>
          <div class="col-4">
            <div class="text-warning h4 font-weight-bold mb-0"><?= $pending_installments_count ?></div>
            <div class="small text-muted">Pending</div>
          </div>
          <div class="col-4">
            <div class="text-danger h4 font-weight-bold mb-0"><?= $overdue_installments_count ?></div>
            <div class="small text-muted">Overdue</div>
          </div>
        </div>
        <div class="progress mt-2" style="height:16px;">
          <?php
          $inst_total = max(1, $total_installments_count);
          $paid_pct = round($paid_installments_count / $inst_total * 100);
          $pending_pct = round($pending_installments_count / $inst_total * 100);
          $overdue_pct = round($overdue_installments_count / $inst_total * 100);
          ?>
          <div class="progress-bar bg-success" style="width:<?= $paid_pct ?>%"><?= $paid_pct ?>%</div>
          <div class="progress-bar bg-warning" style="width:<?= $pending_pct ?>%"><?= $pending_pct ?>%</div>
          <div class="progress-bar bg-danger" style="width:<?= $overdue_pct ?>%"><?= $overdue_pct ?>%</div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- php elseif ($tab === 'sales'):  -->
<!-- <div class="row">
  <div class="col-lg-5 mb-4">
    <div class="card shadow">
      <div class="card-header py-3"><h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-plus-circle"></i> Add Credit Sale</h6></div>
      <div class="card-body">
        <form method="post">
          <div class="form-group">
            <label class="small">Date</label>
            <input type="date" name="sale_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
          </div>
          <div class="form-group">
            <label class="small">Invoice No</label>
            <input type="text" name="invoice_no" class="form-control" placeholder="e.g. INV-001">
          </div>
          <div class="form-group">
            <label class="small">Amount <span class="text-danger">*</span></label>
            <input type="number" name="total_amount" class="form-control" step="0.01" min="0.01" required>
          </div>
          <div class="form-group">
            <label class="small">Status</label>
            <select name="status" class="form-control">
              <option value="active">Active</option>
              <option value="completed">Completed</option>
            </select>
          </div>
          <div class="form-group">
            <label class="small">By</label>
            <select name="created_by" class="form-control">
              <?php foreach ($users as $u): ?>
                <option value="<?= $u['id'] ?>" <?= ($u['id'] == ($_SESSION['user_id'] ?? 1)) ? 'selected' : '' ?>><?= htmlspecialchars($u['username']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <button type="submit" name="save_sale" class="btn btn-primary btn-block py-2"><i class="fas fa-save"></i> Save Sale</button>
        </form>
      </div>
    </div>
  </div>
  <div class="col-lg-7 mb-4">
    <div class="card shadow">
      <div class="card-header py-3 d-flex justify-content-between align-items-center">
        <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-shopping-cart"></i> Credit Sale History</h6>
        <div>
          <span class="badge badge-primary status-badge mr-2">Total: <?= formatCurrency($credit_sale) ?></span>
          <a href="view.php?id=<?= $id ?>&tab=sales&download=csv" class="btn btn-sm btn-success"><i class="fas fa-download"></i> Download</a>
        </div>
      </div>
      <div class="card-body">
        <?php if (empty($sales_data)): ?>
          <p class="text-muted mb-0 text-center">No sales yet</p>
        <?php else: ?>
          <div class="table-responsive">
            <table class="table table-bordered table-hover table-sm">
              <thead class="thead-light">
                <tr><th>Date</th><th>Invoice ID</th><th class="text-right">Amount</th><th>Status</th><th>By</th><th>Action</th></tr>
              </thead>
              <tbody>
                <?php foreach ($sales_data as $s): ?>
                  <tr>
                    <td><?= formatDate($s['sale_date']) ?></td>
                    <td><span class="badge badge-secondary"><?= htmlspecialchars($s['invoice_no'] ?? '-') ?></span></td>
                    <td class="text-right font-weight-bold" style="color:#0f172a;"><?= formatCurrency($s['total_amount']) ?></td>
                    <td><span class="badge badge-<?= $s['status'] === 'active' ? 'warning' : ($s['status'] === 'completed' ? 'success' : 'secondary') ?> status-badge"><?= ucfirst($s['status']) ?></span></td>
                    <td><?= htmlspecialchars($s['added_by'] ?? '-') ?></td>
                    <td><a href="view.php?id=<?= $id ?>&tab=sales&del_sale=<?= $s['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete this sale?')"><i class="fas fa-trash"></i></a></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div> -->

<!-- php elseif ($tab === 'returns'):  -->
<!-- <div class="row">
  <div class="col-lg-5 mb-4">
    <div class="card shadow">
      <div class="card-header py-3"><h6 class="m-0 font-weight-bold text-danger"><i class="fas fa-plus-circle"></i> Add Sale Return</h6></div>
      <div class="card-body">
        <form method="post">
          <div class="form-group">
            <label class="small">Date</label>
            <input type="date" name="return_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
          </div>
          <div class="form-group">
            <label class="small">Amount <span class="text-danger">*</span></label>
            <input type="number" name="amount" class="form-control" step="0.01" min="0.01" required>
          </div>
          <div class="form-group">
            <label class="small">Reference Sale</label>
            <select name="sale_id" class="form-control">
              <option value="">-- None --</option>
              <?php foreach ($sales_data as $s): ?>
                <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['invoice_no'] ?? 'ID:'.$s['id']) ?> (<?= formatCurrency($s['total_amount']) ?>)</option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label class="small">Notes</label>
            <textarea name="notes" class="form-control" rows="2" placeholder="Reason for return"></textarea>
          </div>
          <div class="form-group">
            <label class="small">By</label>
            <select name="created_by" class="form-control">
              <?php foreach ($users as $u): ?>
                <option value="<?= $u['id'] ?>" <?= ($u['id'] == ($_SESSION['user_id'] ?? 1)) ? 'selected' : '' ?>><?= htmlspecialchars($u['username']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <button type="submit" name="save_return" class="btn btn-danger btn-block py-2"><i class="fas fa-save"></i> Save Return</button>
        </form>
      </div>
    </div>
  </div>
  <div class="col-lg-7 mb-4">
    <div class="card shadow">
      <div class="card-header py-3 d-flex justify-content-between align-items-center">
        <h6 class="m-0 font-weight-bold text-danger"><i class="fas fa-undo"></i> Sale Return History</h6>
        <div>
          <span class="badge badge-danger status-badge mr-2">Total: <?= formatCurrency($returns_total) ?></span>
          <a href="view.php?id=<?= $id ?>&tab=returns&download=csv" class="btn btn-sm btn-success"><i class="fas fa-download"></i> Download</a>
        </div>
      </div>
      <div class="card-body">
        <?php if (empty($returns_data)): ?>
          <p class="text-muted mb-0 text-center">No returns yet</p>
        <?php else: ?>
          <div class="table-responsive">
            <table class="table table-bordered table-hover table-sm">
              <thead class="thead-light">
                <tr><th>Date</th><th>Invoice ID</th><th class="text-right">Amount</th><th>Notes</th><th>By</th><th>Action</th></tr>
              </thead>
              <tbody>
                <?php foreach ($returns_data as $r): ?>
                  <tr>
                    <td><?= formatDate($r['return_date']) ?></td>
                    <td><span class="badge badge-secondary"><?= htmlspecialchars($r['invoice_no'] ?? '-') ?></span></td>
                    <td class="text-right font-weight-bold text-danger"><?= formatCurrency($r['amount']) ?></td>
                    <td><?= htmlspecialchars($r['notes'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($r['added_by'] ?? '-') ?></td>
                    <td><a href="view.php?id=<?= $id ?>&tab=returns&del_return=<?= $r['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete?')"><i class="fas fa-trash"></i></a></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div> -->

<?php elseif ($tab === 'payments'): ?>
<div class="row">
  <!-- LEFT: Sale selector + Installment detail + Payment form -->
  <div class="col-lg-5 mb-4">
    <div class="card shadow">
      <div class="card-header py-3"><h6 class="m-0 font-weight-bold text-success"><i class="fas fa-hand-holding-usd"></i> Collect Installment</h6></div>
      <div class="card-body">
        <?php if (empty($installment_sales)): ?>
          <p class="text-muted mb-0 text-center">No active installment sales for this customer.</p>
        <?php else: ?>
        <form method="post" id="paymentForm">
          <div class="form-group mb-3">
            <label class="small text-muted font-weight-bold">Select Sale <span class="text-danger">*</span></label>
            <select name="sale_id" id="saleSelector" class="form-control" required>
              <option value="">-- Choose a sale --</option>
              <?php foreach ($installment_sales as $s):
                $sid = $s['id'];
                $paid = 0;
                $total_inst = $s['total_installments'] ?? 0;
                $paid_inst = 0;
                if (isset($sale_inst_map[$sid])) {
                    foreach ($sale_inst_map[$sid] as $inst) {
                        $paid += (float)$inst['paid_amount'];
                        if ($inst['status'] === 'paid') $paid_inst++;
                    }
                }
              ?>
                <option value="<?= $sid ?>" data-items='<?= json_encode($sale_items_map[$sid] ?? []) ?>'
                  data-total="<?= $s['total_amount'] ?>" data-down="<?= $s['down_payment'] ?>"
                  data-financed="<?= $s['financed_amount'] ?>" data-paid="<?= $paid ?>"
                  data-installments='<?= json_encode($sale_inst_map[$sid] ?? []) ?>'
                  data-monthly="<?= $s['monthly_installment'] ?>">
                  #<?= htmlspecialchars($s['invoice_no']) ?> — <?= formatCurrency($s['total_amount']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <!-- Sale Detail + Payment Form (shown when sale selected) -->
          <div id="saleDetail" style="display:none;">
            <!-- Purchased Items -->
            <div class="card bg-light mb-2">
              <div class="card-body py-2">
                <div class="d-flex justify-content-between align-items-center mb-1">
                  <span class="small font-weight-bold text-muted">Purchased Items</span>
                  <span class="small font-weight-bold" id="detailSaleLabel"></span>
                </div>
                <div id="detailItems" class="small"></div>
              </div>
            </div>

            <!-- Installment Progress -->
            <div class="d-flex justify-content-between small mb-1">
              <span class="text-muted">Paid Installments</span>
              <span class="font-weight-bold" id="progressLabel">0/0</span>
            </div>
            <div class="progress mb-2" style="height:6px;">
              <div class="progress-bar bg-success" id="progressBar" style="width:0%;"></div>
            </div>

            <!-- This Month's Installment -->
            <div id="currentDueCard" class="card border-left-success mb-2" style="display:none;">
              <div class="card-body py-2">
                <div class="d-flex justify-content-between">
                  <span class="text-muted small">This Month's Installment</span>
                  <span class="font-weight-bold text-primary" id="dueInstLabel">#0</span>
                </div>
                <div class="d-flex justify-content-between align-items-center">
                  <span class="text-muted small">Due Date</span>
                  <span class="font-weight-bold" id="dueDateLabel">-</span>
                </div>
                <div class="d-flex justify-content-between align-items-center mt-1" style="font-size:1.3rem;">
                  <span class="font-weight-bold">Due Amount</span>
                  <span class="font-weight-bold text-success" id="dueAmountLabel">0.00</span>
                </div>
                <input type="hidden" name="installment_id" id="selectedInstallmentId" value="">
              </div>
            </div>

            <!-- All installments paid message -->
            <div id="allPaidMsg" class="alert alert-success py-2 small" style="display:none;">
              <i class="fas fa-check-circle"></i> All installments have been paid for this sale.
            </div>

            <!-- Payment Form Fields -->
            <div id="paymentFields" style="display:none;">
              <hr class="my-2">
              <h6 class="font-weight-bold text-success mb-2"><i class="fas fa-money-bill-wave"></i> Record Payment</h6>
              <div class="row">
                <div class="col-6">
                  <div class="form-group mb-2">
                    <label class="small text-muted">Date <span class="text-danger">*</span></label>
                    <input type="date" name="payment_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                  </div>
                </div>
                <div class="col-6">
                  <div class="form-group mb-2">
                    <label class="small text-muted">Method <span class="text-danger">*</span></label>
                    <select name="payment_method" class="form-control" required>
                      <option value="cash">Cash</option>
                      <option value="card">Card</option>
                      <option value="bank_transfer">Bank Transfer</option>
                    </select>
                  </div>
                </div>
              </div>
              <div class="form-group mb-2">
                <label class="small text-muted">Payment Amount <span class="text-danger">*</span></label>
                <input type="number" name="amount" id="payAmount" class="form-control form-control-lg font-weight-bold" step="0.01" min="0.01" required>
                <small class="text-muted" id="newRemainingHint" style="display:none;">Remaining after this: <span class="font-weight-bold text-info" id="newRemainingAmount">0.00</span></small>
              </div>
              <div class="row">
                <div class="col-6">
                  <div class="form-group mb-2">
                    <label class="small text-muted">Notes</label>
                    <textarea name="notes" class="form-control" rows="1"></textarea>
                  </div>
                </div>
                <div class="col-6">
                  <div class="form-group mb-2">
                    <label class="small text-muted">By <span class="text-danger">*</span></label>
                    <select name="created_by" class="form-control" required>
                      <?php foreach ($users as $u): ?>
                        <option value="<?= $u['id'] ?>" <?= ($u['id'] == ($_SESSION['user_id'] ?? 1)) ? 'selected' : '' ?>><?= htmlspecialchars($u['username']) ?></option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                </div>
              </div>
              <button type="submit" name="save_payment" class="btn btn-success btn-block"><i class="fas fa-save"></i> Record Payment</button>
            </div>
          </div>
        </form>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- RIGHT: Installment History -->
  <div class="col-lg-7 mb-4">
    <div class="card shadow">
      <div class="card-header py-3 d-flex justify-content-between align-items-center">
        <h6 class="m-0 font-weight-bold text-success"><i class="fas fa-history"></i> Installment History</h6>
        <span class="badge badge-success status-badge">Total Paid: <?= formatCurrency(array_sum(array_column($installments_data, 'paid_amount'))) ?></span>
      </div>
      <div class="card-body">
        <?php if (empty($installments_data)): ?>
          <p class="text-muted mb-0 text-center">No installment records found</p>
        <?php else: ?>
        <div class="table-responsive">
          <table class="table table-bordered table-hover table-sm">
            <thead class="thead-light">
              <tr>
                <th>Sale</th>
                <th class="text-center">#</th>
                <th>Due Date</th>
                <th class="text-right">Amount</th>
                <th class="text-right">Paid</th>
                <th class="text-right">Balance</th>
                <th>Status</th>
                <th>Paid Date</th>
                <th>Action</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($installments_data as $inst):
                $inst_sid = $inst['sale_id'];
                $inst_payments = array_filter($payments_data, function($p) use ($inst) {
                    return (int)$p['installment_id'] === (int)$inst['id'];
                });
              ?>
                <tr class="<?= $inst['status'] === 'paid' ? 'table-success' : ($inst['status'] === 'overdue' ? 'table-danger' : '') ?>">
                  <td><span class="badge badge-secondary"><?= htmlspecialchars($inst['invoice_no']) ?></span></td>
                  <td class="text-center"><?= $inst['installment_no'] ?> / <?= $inst['total_installments'] ?></td>
                  <td><?= formatDate($inst['due_date']) ?></td>
                  <td class="text-right font-weight-bold"><?= formatCurrency($inst['amount']) ?></td>
                  <td class="text-right text-success font-weight-bold"><?= formatCurrency($inst['paid_amount']) ?></td>
                  <td class="text-right <?= $inst['balance'] > 0 ? 'text-danger' : 'text-muted' ?>"><?= formatCurrency($inst['balance']) ?></td>
                  <td>
                    <span class="badge badge-<?= $inst['status'] === 'paid' ? 'success' : ($inst['status'] === 'partial' ? 'warning' : ($inst['status'] === 'overdue' ? 'danger' : 'secondary')) ?> status-badge">
                      <?= ucfirst($inst['status']) ?>
                    </span>
                  </td>
                  <td><?= $inst['paid_date'] ? formatDate($inst['paid_date']) : '-' ?></td>
                  <td>
                    <?php if ($inst['paid_amount'] > 0): ?>
                      <a href="view.php?id=<?= $id ?>&tab=payments&del_inst_payment=<?= $inst['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete all payments for installment #<?= $inst['installment_no'] ?>?')"><i class="fas fa-trash"></i></a>
                    <?php else: ?>
                      <span class="text-muted small">-</span>
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
  </div>
</div>

<script>
$(document).ready(function() {
    function loadSaleDetail(saleId) {
        var opt = $('#saleSelector option[value="' + saleId + '"]');
        if (!opt.length) { $('#saleDetail').hide(); return; }

        var items = opt.data('items') || [];
        var installments = opt.data('installments') || [];
        var label = opt.text().trim();

        $('#detailSaleLabel').text(label);

        var itemsHtml = '';
        if (items.length) {
            items.forEach(function(it) {
                var itemName = it.item_description || it.name || 'Item';
                itemsHtml += '<div>' + itemName + ' × ' + it.quantity + '</div>';
            });
        }
        $('#detailItems').html(itemsHtml || '<span class="text-muted">No items</span>');

        var totalInsts = installments.length;
        var paidInsts = installments.filter(function(i) { return i.status === 'paid'; }).length;
        var pct = totalInsts > 0 ? Math.round(paidInsts / totalInsts * 100) : 0;
        $('#progressLabel').text(paidInsts + '/' + totalInsts);
        $('#progressBar').css('width', pct + '%');

        var current = installments.find(function(i) { return i.status === 'pending' || i.status === 'partial'; });
        if (current) {
            $('#currentDueCard').show();
            $('#allPaidMsg').hide();
            $('#paymentFields').show();
            var remaining = parseFloat(current.amount) - parseFloat(current.paid_amount);
            $('#dueInstLabel').text('#' + current.installment_no + ' of ' + totalInsts);
            $('#dueDateLabel').text(current.due_date);
            $('#dueAmountLabel').text(remaining.toFixed(2));
            $('#selectedInstallmentId').val(current.id);
            $('#payAmount').val(remaining.toFixed(2)).trigger('input');
        } else {
            $('#currentDueCard').hide();
            $('#paymentFields').hide();
            $('#allPaidMsg').show();
            $('#selectedInstallmentId').val('');
            $('#payAmount').val('');
        }

        $('#saleDetail').show();
    }

    $('#saleSelector').on('change', function() {
        var val = $(this).val();
        if (val) loadSaleDetail(val);
        else $('#saleDetail').hide();
    });

    $('#payAmount').on('input', function() {
        var dueAmt = parseFloat($('#dueAmountLabel').text()) || 0;
        var entered = parseFloat($(this).val()) || 0;
        var newRem = Math.max(0, dueAmt - entered);
        if (entered > 0 && $('#selectedInstallmentId').val()) {
            $('#newRemainingHint').show();
            $('#newRemainingAmount').text(newRem.toFixed(2));
        } else {
            $('#newRemainingHint').hide();
        }
    });
});
</script>
<style>
.summary-divider { border-top:1px dashed #d1d3e2; margin:4px 0; }
.border-left-success { border-left: 4px solid #1cc88a !important; }
</style>
<?php endif; ?>

<?php require_once '../../includes/footer.php'; ?>
