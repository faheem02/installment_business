<?php
session_start();
$page_title = 'Customer Profile';
$base_url = '../../';
require_once '../../includes/functions.php';

$id = (int)($_GET['id'] ?? 0);
$customer = getById('customers', $id);
if (!$customer) redirect('index.php', 'Customer not found', 'error');

$guarantors = getWhere('guarantors', 'customer_id', $id);
$sales = getWhere('sales', 'customer_id', $id, 'sale_date DESC');

require_once '../../includes/header.php';
?>

<div class="row">

  <!-- Personal Info -->
  <div class="col-lg-4 mb-4">
    <div class="card shadow h-100">
      <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-user"></i> Personal Info</h6>
      </div>
      <div class="card-body">
        <table class="table table-sm table-borderless mb-0">
          <tr><th class="text-muted" width="40%">Customer No</th><td><span class="badge badge-secondary"><?= htmlspecialchars($customer['customer_no']) ?></span></td></tr>
          <tr><th class="text-muted">Name</th><td><?= htmlspecialchars($customer['full_name']) ?></td></tr>
          <tr><th class="text-muted">Phone</th><td><?= htmlspecialchars($customer['phone']) ?></td></tr>
          <tr><th class="text-muted">Email</th><td><?= htmlspecialchars($customer['email'] ?? '-') ?></td></tr>
          <tr><th class="text-muted">Address</th><td><?= htmlspecialchars($customer['address'] ?? '-') ?></td></tr>
          <tr><th class="text-muted">City</th><td><?= htmlspecialchars($customer['city'] ?? '-') ?></td></tr>
          <tr><th class="text-muted">Occupation</th><td><?= htmlspecialchars($customer['occupation'] ?? '-') ?></td></tr>
          <tr><th class="text-muted">Registered</th><td><?= formatDate($customer['created_at']) ?></td></tr>
        </table>
      </div>
      <div class="card-footer">
        <a href="edit.php?id=<?= $id ?>" class="btn btn-primary btn-sm"><i class="fas fa-pen"></i> Edit</a>
        <a href="index.php" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left"></i> Back</a>
      </div>
    </div>
  </div>

  <!-- CNIC / ID Record -->
  <div class="col-lg-4 mb-4">
    <div class="card shadow h-100">
      <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-info"><i class="fas fa-id-card"></i> CNIC / ID Record</h6>
      </div>
      <div class="card-body">
        <table class="table table-sm table-borderless mb-0">
          <tr><th class="text-muted" width="40%">CNIC</th><td><?= htmlspecialchars($customer['cnic']) ?></td></tr>
          <tr><th class="text-muted">Expiry</th><td><?= formatDate($customer['cnic_expiry']) ?></td></tr>
          <tr><th class="text-muted">Guardian</th><td><?= htmlspecialchars($customer['guardian_name'] ?? '-') ?></td></tr>
          <tr><th class="text-muted">Relation</th><td><?= htmlspecialchars($customer['guardian_relation'] ?? '-') ?></td></tr>
          <tr><th class="text-muted">Income</th><td>PKR <?= formatCurrency($customer['monthly_income']) ?></td></tr>
        </table>
      </div>
    </div>
  </div>

  <!-- Guarantors -->
  <div class="col-lg-4 mb-4">
    <div class="card shadow h-100">
      <div class="card-header py-3 d-flex justify-content-between align-items-center">
        <h6 class="m-0 font-weight-bold text-success"><i class="fas fa-handshake"></i> Guarantors</h6>
        <span class="badge badge-success"><?= count($guarantors) ?></span>
      </div>
      <div class="card-body">
        <?php if (count($guarantors) > 0): ?>
          <?php foreach ($guarantors as $g): ?>
            <div class="mb-2 pb-2 <?= $g !== end($guarantors) ? 'border-bottom' : '' ?>">
              <strong><?= htmlspecialchars($g['full_name']) ?></strong>
              <div class="small text-muted">
                CNIC: <?= htmlspecialchars($g['cnic']) ?> | Phone: <?= htmlspecialchars($g['phone']) ?><br>
                Relation: <?= htmlspecialchars($g['relation_to_customer'] ?? '-') ?>
              </div>
            </div>
          <?php endforeach; ?>
        <?php else: ?>
          <p class="text-muted mb-0">No guarantors added.</p>
        <?php endif; ?>
      </div>
      <div class="card-footer">
        <a href="guarantors.php?customer_id=<?= $id ?>" class="btn btn-success btn-sm">
          <i class="fas fa-plus-circle"></i> Manage Guarantors
        </a>
      </div>
    </div>
  </div>
</div>

<!-- Purchase History -->
<div class="card shadow mb-4">
  <div class="card-header py-3">
    <h6 class="m-0 font-weight-bold text-warning"><i class="fas fa-history"></i> Purchase History</h6>
  </div>
  <div class="card-body">
    <div class="table-responsive">
      <table class="table table-bordered" width="100%" cellspacing="0">
        <thead>
          <tr>
            <th>Invoice</th>
            <th>Date</th>
            <th>Total</th>
            <th>Down Payment</th>
            <th>Financed</th>
            <th>Status</th>
          </tr>
        </thead>
        <tbody>
          <?php if (count($sales) > 0): ?>
            <?php foreach ($sales as $s): ?>
            <tr>
              <td><span class="badge badge-info"><?= htmlspecialchars($s['invoice_no']) ?></span></td>
              <td><?= formatDate($s['sale_date']) ?></td>
              <td>PKR <?= formatCurrency($s['total_amount']) ?></td>
              <td>PKR <?= formatCurrency($s['down_payment']) ?></td>
              <td>PKR <?= formatCurrency($s['financed_amount']) ?></td>
              <td>
                <span class="badge badge-<?= $s['payment_status'] == 'paid' ? 'success' : ($s['payment_status'] == 'installment' ? 'warning' : 'secondary') ?>">
                  <?= ucfirst($s['payment_status']) ?>
                </span>
              </td>
            </tr>
            <?php endforeach; ?>
          <?php else: ?>
            <tr><td colspan="6" class="text-center py-4">No purchases yet</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php require_once '../../includes/footer.php'; ?>
