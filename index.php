<?php
session_start();
$page_title = 'Dashboard';
require_once 'includes/functions.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Summary stats
$today_sales = $pdo->query("SELECT COALESCE(SUM(total_amount), 0) FROM sales WHERE DATE(created_at) = CURDATE()")->fetchColumn();
$total_receivables = $pdo->query("SELECT COALESCE(SUM(balance), 0) FROM sale_installments WHERE status IN ('pending','overdue')")->fetchColumn();
$overdue_amount = $pdo->query("SELECT COALESCE(SUM(balance), 0) FROM sale_installments WHERE status = 'overdue'")->fetchColumn();
$overdue_count = $pdo->query("SELECT COUNT(*) FROM sale_installments WHERE status = 'overdue'")->fetchColumn();
$cash_in_hand = $pdo->query("SELECT closing_balance FROM cash_book_daily ORDER BY date DESC LIMIT 1")->fetchColumn();
if (!$cash_in_hand) $cash_in_hand = 0;

// Overdue installments
$overdues = $pdo->query("
    SELECT si.*, s.invoice_no, c.full_name, c.id as cid, c.phone
    FROM sale_installments si
    JOIN sales s ON si.sale_id = s.id
    JOIN customers c ON s.customer_id = c.id
    WHERE si.status = 'overdue'
    ORDER BY si.due_date ASC LIMIT 10
")->fetchAll();

// Recent sales
$recent_sales = $pdo->query("
    SELECT s.id, s.invoice_no, s.total_amount, s.payment_status, s.created_at,
           c.full_name, c.id as cid
    FROM sales s
    JOIN customers c ON s.customer_id = c.id
    ORDER BY s.id DESC LIMIT 5
")->fetchAll();

// Recent customers
$recent_customers = $pdo->query("SELECT id, full_name, phone, cnic, created_at FROM customers ORDER BY created_at DESC LIMIT 5")->fetchAll();

require_once 'includes/header.php';
?>

<div class="row">

  <div class="col-xl-3 col-md-6 mb-4">
    <div class="card border-left-success shadow h-100 py-2">
      <div class="card-body">
        <div class="row no-gutters align-items-center">
          <div class="col mr-2">
            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Today Sales</div>
            <div class="h5 mb-0 font-weight-bold text-gray-800">PKR <?=formatCurrency($today_sales)?></div>
          </div>
          <div class="col-auto"><i class="fas fa-shopping-cart fa-2x text-gray-300"></i></div>
        </div>
      </div>
    </div>
  </div>

  <div class="col-xl-3 col-md-6 mb-4">
    <div class="card border-left-info shadow h-100 py-2">
      <div class="card-body">
        <div class="row no-gutters align-items-center">
          <div class="col mr-2">
            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Outstanding Receivables</div>
            <div class="h5 mb-0 font-weight-bold text-gray-800">PKR <?=formatCurrency($total_receivables)?></div>
          </div>
          <div class="col-auto"><i class="fas fa-hand-holding-usd fa-2x text-gray-300"></i></div>
        </div>
      </div>
    </div>
  </div>

  <div class="col-xl-3 col-md-6 mb-4">
    <div class="card border-left-danger shadow h-100 py-2">
      <div class="card-body">
        <div class="row no-gutters align-items-center">
          <div class="col mr-2">
            <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">Overdue (<?=$overdue_count?>)</div>
            <div class="h5 mb-0 font-weight-bold text-gray-800">PKR <?=formatCurrency($overdue_amount)?></div>
          </div>
          <div class="col-auto"><i class="fas fa-exclamation-triangle fa-2x text-gray-300"></i></div>
        </div>
      </div>
    </div>
  </div>

  <div class="col-xl-3 col-md-6 mb-4">
    <div class="card border-left-warning shadow h-100 py-2">
      <div class="card-body">
        <div class="row no-gutters align-items-center">
          <div class="col mr-2">
            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Cash in Hand</div>
            <div class="h5 mb-0 font-weight-bold text-gray-800">PKR <?=formatCurrency($cash_in_hand)?></div>
          </div>
          <div class="col-auto"><i class="fas fa-money-bill-wave fa-2x text-gray-300"></i></div>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="row">

  <!-- Overdue Installments -->
  <div class="col-lg-12 mb-4">
    <div class="card shadow mb-4">
      <div class="card-header py-3 d-flex justify-content-between align-items-center">
        <h6 class="m-0 font-weight-bold text-danger"><i class="fas fa-exclamation-circle"></i> Overdue Installments</h6>
        <a href="modules/installments/late_payments.php" class="btn btn-danger btn-sm">View All</a>
      </div>
      <div class="card-body">
        <?php if (count($overdues)): ?>
        <div class="table-responsive">
          <table class="table table-bordered" width="100%" cellspacing="0">
            <thead>
              <tr><th>Customer</th><th>Phone</th><th>Invoice</th><th>Due Date</th><th>Amount</th><th>Balance</th></tr>
            </thead>
            <tbody>
              <?php foreach ($overdues as $d): ?>
              <tr>
                <td><a href="modules/customers/view.php?id=<?=$d['cid']?>"><?=htmlspecialchars($d['full_name'])?></a></td>
                <td><?=htmlspecialchars($d['phone'])?></td>
                <td><?=htmlspecialchars($d['invoice_no'])?></td>
                <td class="text-danger font-weight-bold"><?=formatDate($d['due_date'])?></td>
                <td>PKR <?=formatCurrency($d['amount'])?></td>
                <td>PKR <?=formatCurrency($d['balance'])?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php else: ?>
          <p class="text-center text-muted py-3 mb-0"><i class="fas fa-check-circle text-success fa-2x d-block mb-2"></i>No overdue installments</p>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<div class="row">

  <!-- Recent Sales -->
  <div class="col-lg-6 mb-4">
    <div class="card shadow mb-4">
      <div class="card-header py-3 d-flex justify-content-between align-items-center">
        <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-file-invoice"></i> Recent Sales</h6>
        <a href="modules/sales/index.php" class="btn btn-primary btn-sm">View All</a>
      </div>
      <div class="card-body">
        <?php if (count($recent_sales)): ?>
        <div class="table-responsive">
          <table class="table table-bordered" width="100%" cellspacing="0">
            <thead>
              <tr><th>Invoice</th><th>Customer</th><th>Amount</th><th>Status</th><th>Date</th></tr>
            </thead>
            <tbody>
              <?php foreach ($recent_sales as $s): ?>
              <tr>
                <td><a href="modules/sales/invoice.php?id=<?=$s['id']?>"><?=htmlspecialchars($s['invoice_no'])?></a></td>
                <td><a href="modules/customers/view.php?id=<?=$s['cid']?>"><?=htmlspecialchars($s['full_name'])?></a></td>
                <td>PKR <?=formatCurrency($s['total_amount'])?></td>
                <td><span class="badge badge-<?=$s['payment_status']==='paid'?'success':'warning'?>"><?=ucfirst($s['payment_status'])?></span></td>
                <td><?=formatDate($s['created_at'])?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php else: ?>
          <p class="text-center text-muted py-3 mb-0">No sales yet</p>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- Recent Customers -->
  <div class="col-lg-6 mb-4">
    <div class="card shadow mb-4">
      <div class="card-header py-3 d-flex justify-content-between align-items-center">
        <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-users"></i> Recent Customers</h6>
        <a href="modules/customers/index.php" class="btn btn-primary btn-sm">View All</a>
      </div>
      <div class="card-body">
        <?php if (count($recent_customers)): ?>
        <div class="table-responsive">
          <table class="table table-bordered" width="100%" cellspacing="0">
            <thead>
              <tr><th>Name</th><th>Phone</th><th>CNIC</th><th>Date</th></tr>
            </thead>
            <tbody>
              <?php foreach ($recent_customers as $c): ?>
              <tr>
                <td><a href="modules/customers/view.php?id=<?=$c['id']?>"><?=htmlspecialchars($c['full_name'])?></a></td>
                <td><?=htmlspecialchars($c['phone'])?></td>
                <td><?=htmlspecialchars($c['cnic'])?></td>
                <td><?=formatDate($c['created_at'])?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php else: ?>
          <p class="text-center text-muted py-3 mb-0">No customers yet</p>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<?php require_once 'includes/footer.php'; ?>
