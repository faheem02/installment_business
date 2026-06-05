<?php
session_start();
$page_title = 'Dashboard';
require_once 'includes/functions.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$total_customers = countRows('customers');
$total_sales = countRows('sales');
$total_products = countRows('products', 'status', 1);
$stmt = $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM payments");
$total_collected = $stmt->fetchColumn();

require_once 'includes/header.php';
?>

<div class="row">

  <div class="col-xl-3 col-md-6 mb-4">
    <div class="card border-left-primary shadow h-100 py-2">
      <div class="card-body">
        <div class="row no-gutters align-items-center">
          <div class="col mr-2">
            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Customers</div>
            <div class="h5 mb-0 font-weight-bold text-gray-800"><?= $total_customers ?></div>
          </div>
          <div class="col-auto"><i class="fas fa-users fa-2x text-gray-300"></i></div>
        </div>
      </div>
    </div>
  </div>

  <div class="col-xl-3 col-md-6 mb-4">
    <div class="card border-left-success shadow h-100 py-2">
      <div class="card-body">
        <div class="row no-gutters align-items-center">
          <div class="col mr-2">
            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Total Sales</div>
            <div class="h5 mb-0 font-weight-bold text-gray-800"><?= $total_sales ?></div>
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
            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Products</div>
            <div class="h5 mb-0 font-weight-bold text-gray-800"><?= $total_products ?></div>
          </div>
          <div class="col-auto"><i class="fas fa-boxes fa-2x text-gray-300"></i></div>
        </div>
      </div>
    </div>
  </div>

  <div class="col-xl-3 col-md-6 mb-4">
    <div class="card border-left-warning shadow h-100 py-2">
      <div class="card-body">
        <div class="row no-gutters align-items-center">
          <div class="col mr-2">
            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Total Collected</div>
            <div class="h5 mb-0 font-weight-bold text-gray-800">PKR <?= formatCurrency($total_collected) ?></div>
          </div>
          <div class="col-auto"><i class="fas fa-dollar-sign fa-2x text-gray-300"></i></div>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="row">

  <!-- Recent Customers -->
  <div class="col-lg-6 mb-4">
    <div class="card shadow mb-4">
      <div class="card-header py-3 d-flex justify-content-between align-items-center">
        <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-users"></i> Recent Customers</h6>
        <a href="modules/customers/index.php" class="btn btn-primary btn-sm">View All</a>
      </div>
      <div class="card-body">
        <div class="table-responsive">
          <table class="table table-bordered" width="100%" cellspacing="0">
            <thead>
              <tr>
                <th>Name</th>
                <th>Phone</th>
                <th>CNIC</th>
                <th>Date</th>
              </tr>
            </thead>
            <tbody>
              <?php
                $recent = $pdo->query("SELECT id, full_name, phone, cnic, created_at FROM customers ORDER BY created_at DESC LIMIT 5")->fetchAll();
                foreach ($recent as $c):
              ?>
              <tr>
                <td><a href="modules/customers/view.php?id=<?= $c['id'] ?>"><?= htmlspecialchars($c['full_name']) ?></a></td>
                <td><?= htmlspecialchars($c['phone']) ?></td>
                <td><?= htmlspecialchars($c['cnic']) ?></td>
                <td><?= formatDate($c['created_at']) ?></td>
              </tr>
              <?php endforeach; ?>
              <?php if (!count($recent)): ?>
                <tr><td colspan="4" class="text-center py-4">No customers yet</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

  <!-- Due Installments -->
  <div class="col-lg-6 mb-4">
    <div class="card shadow mb-4">
      <div class="card-header py-3 d-flex justify-content-between align-items-center">
        <h6 class="m-0 font-weight-bold text-warning"><i class="fas fa-calendar-check"></i> Due Installments</h6>
        <span class="badge badge-warning"><?= countRows('sale_installments', 'status', 'pending') ?> pending</span>
      </div>
      <div class="card-body">
        <div class="table-responsive">
          <table class="table table-bordered" width="100%" cellspacing="0">
            <thead>
              <tr>
                <th>Customer</th>
                <th>Due Date</th>
                <th>Amount</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody>
              <?php
                $dues = $pdo->query("
                  SELECT si.*, s.invoice_no, c.full_name, c.id as cid
                  FROM sale_installments si
                  JOIN sales s ON si.sale_id = s.id
                  JOIN customers c ON s.customer_id = c.id
                  WHERE si.status IN ('pending','overdue')
                  ORDER BY si.due_date ASC LIMIT 5
                ")->fetchAll();
                foreach ($dues as $d):
              ?>
              <tr>
                <td><a href="modules/customers/view.php?id=<?= $d['cid'] ?>"><?= htmlspecialchars($d['full_name']) ?></a></td>
                <td><?= formatDate($d['due_date']) ?></td>
                <td>PKR <?= formatCurrency($d['amount']) ?></td>
                <td>
                  <span class="badge badge-<?= $d['status'] == 'overdue' ? 'danger' : 'warning' ?>">
                    <?= ucfirst($d['status']) ?>
                  </span>
                </td>
              </tr>
              <?php endforeach; ?>
              <?php if (!count($dues)): ?>
                <tr><td colspan="4" class="text-center py-4">All installments paid</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

<?php require_once 'includes/footer.php'; ?>
