<?php
session_start();
$page_title = 'Edit Customer';
$base_url = '../../';
require_once '../../includes/functions.php';

$id = (int)($_GET['id'] ?? 0);
$customer = getById('customers', $id);
if (!$customer) redirect('index.php', 'Customer not found', 'error');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'full_name'       => $_POST['full_name'],
        'phone'           => $_POST['phone'],
        'email'           => $_POST['email'] ?? '',
        'address'         => $_POST['address'] ?? '',
        'city'            => $_POST['city'] ?? '',
        'cnic'            => $_POST['cnic'],
        'cnic_expiry'     => $_POST['cnic_expiry'] ?: null,
        'guardian_name'   => $_POST['guardian_name'] ?? '',
        'guardian_relation' => $_POST['guardian_relation'] ?? '',
        'occupation'      => $_POST['occupation'] ?? '',
        'monthly_income'  => $_POST['monthly_income'] ?? 0,
        'opening_due'     => (float)($_POST['opening_due'] ?? 0),
        'opening_paid'    => (float)($_POST['opening_paid'] ?? 0),
        'notes'           => $_POST['notes'] ?? '',
        'updated_at'      => date('Y-m-d')
    ];
    update('customers', $data, $id);
    redirect('index.php', 'Customer updated successfully');
}

require_once '../../includes/header.php';
?>

<form method="post">

  <div class="card shadow mb-4">
    <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
      <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-user-edit"></i> Edit Customer</h6>
      <a href="index.php" class="btn btn-secondary btn-sm">
        <i class="fas fa-arrow-left"></i> Back to List
      </a>
    </div>
    <div class="card-body">
      <div class="row">
        <div class="col-md-6 mb-3">
          <label class="form-label">Full Name <span class="text-danger">*</span></label>
          <input type="text" name="full_name" class="form-control" value="<?= htmlspecialchars($customer['full_name']) ?>" required>
        </div>
        <div class="col-md-3 mb-3">
          <label class="form-label">Phone <span class="text-danger">*</span></label>
          <input type="text" name="phone" class="form-control" value="<?= htmlspecialchars($customer['phone']) ?>" required>
        </div>
        <div class="col-md-3 mb-3">
          <label class="form-label">Email</label>
          <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($customer['email'] ?? '') ?>">
        </div>
        <div class="col-md-6 mb-3">
          <label class="form-label">Address</label>
          <textarea name="address" class="form-control" rows="2"><?= htmlspecialchars($customer['address'] ?? '') ?></textarea>
        </div>
        <div class="col-md-3 mb-3">
          <label class="form-label">City</label>
          <input type="text" name="city" class="form-control" value="<?= htmlspecialchars($customer['city'] ?? '') ?>">
        </div>
        <div class="col-md-3 mb-3">
          <label class="form-label">Occupation</label>
          <input type="text" name="occupation" class="form-control" value="<?= htmlspecialchars($customer['occupation'] ?? '') ?>">
        </div>
        <div class="col-md-4 mb-3">
          <label class="form-label">CNIC Number <span class="text-danger">*</span></label>
          <input type="text" name="cnic" class="form-control" value="<?= htmlspecialchars($customer['cnic']) ?>" required>
        </div>
        <div class="col-md-4 mb-3">
          <label class="form-label">CNIC Expiry</label>
          <input type="text" name="cnic_expiry" class="form-control datepicker" value="<?= $customer['cnic_expiry'] ?? '' ?>" autocomplete="off">
        </div>
        <div class="col-md-4 mb-3">
          <label class="form-label">Guardian Name</label>
          <input type="text" name="guardian_name" class="form-control" value="<?= htmlspecialchars($customer['guardian_name'] ?? '') ?>">
        </div>
        <div class="col-md-4 mb-3">
          <label class="form-label">Relation</label>
          <select name="guardian_relation" class="form-control">
            <option value="">Select</option>
            <?php foreach (['Father','Husband','Brother','Other'] as $opt): ?>
              <option value="<?= $opt ?>" <?= $customer['guardian_relation'] == $opt ? 'selected' : '' ?>><?= $opt ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-4 mb-3">
          <label class="form-label">Monthly Income (PKR)</label>
          <input type="number" name="monthly_income" class="form-control" step="0.01" min="0" value="<?= $customer['monthly_income'] ?>">
        </div>
        <div class="col-md-4 mb-3">
          <label class="form-label">Opening Due (PKR)</label>
          <input type="number" name="opening_due" class="form-control" step="0.01" min="0" value="<?= $customer['opening_due'] ?>">
        </div>
        <div class="col-md-4 mb-3">
          <label class="form-label">Already Paid (PKR)</label>
          <input type="number" name="opening_paid" class="form-control" step="0.01" min="0" value="<?= $customer['opening_paid'] ?>">
        </div>
        <div class="col-md-4 mb-3">
          <label class="form-label">Remaining</label>
          <input type="text" class="form-control" value="<?= number_format($customer['opening_due'] - $customer['opening_paid'], 2) ?>" readonly>
        </div>
        <div class="col-12 mb-3">
          <label class="form-label">Notes</label>
          <textarea name="notes" class="form-control" rows="3"><?= htmlspecialchars($customer['notes'] ?? '') ?></textarea>
        </div>
      </div>
    </div>
    <div class="card-footer">
      <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Update Customer</button>
      <a href="index.php" class="btn btn-secondary">Cancel</a>
    </div>
  </div>
</form>

<?php require_once '../../includes/footer.php'; ?>
