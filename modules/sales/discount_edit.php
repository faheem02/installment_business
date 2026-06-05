<?php
session_start();
$page_title = 'Edit Discount';
$base_url = '../../';
require_once '../../includes/functions.php';

$id = (int)($_GET['id'] ?? 0);
$discount = getById('discounts', $id);
if (!$discount) {
    redirect('discounts.php', 'Discount not found', 'error');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'name' => $_POST['name'],
        'description' => $_POST['description'] ?? '',
        'discount_type' => $_POST['discount_type'],
        'discount_value' => (float)$_POST['discount_value'],
        'min_purchase_amount' => (float)($_POST['min_purchase_amount'] ?? 0),
        'start_date' => $_POST['start_date'] ?: null,
        'end_date' => $_POST['end_date'] ?: null,
        'status' => isset($_POST['status']) ? 1 : 0,
        'updated_at' => date('Y-m-d'),
    ];
    update('discounts', $data, $id);
    redirect('discounts.php', 'Discount updated successfully');
}

require_once '../../includes/header.php';
?>

<div class="row justify-content-center">
  <div class="col-lg-6">
    <div class="card shadow mb-4">
      <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-edit"></i> Edit Discount</h6>
      </div>
      <div class="card-body">
        <form method="post">
          <div class="form-group">
            <label class="form-label">Discount Name <span class="text-danger">*</span></label>
            <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($discount['name']) ?>" required>
          </div>
          <div class="form-group">
            <label class="form-label">Description</label>
            <textarea name="description" class="form-control" rows="2"><?= htmlspecialchars($discount['description']) ?></textarea>
          </div>
          <div class="row">
            <div class="col-md-6 form-group">
              <label class="form-label">Type <span class="text-danger">*</span></label>
              <select name="discount_type" class="form-control" required>
                <option value="percentage" <?= $discount['discount_type'] === 'percentage' ? 'selected' : '' ?>>Percentage (%)</option>
                <option value="fixed" <?= $discount['discount_type'] === 'fixed' ? 'selected' : '' ?>>Fixed Amount</option>
              </select>
            </div>
            <div class="col-md-6 form-group">
              <label class="form-label">Value <span class="text-danger">*</span></label>
              <input type="number" name="discount_value" class="form-control" step="0.01" min="0" value="<?= $discount['discount_value'] ?>" required>
            </div>
          </div>
          <div class="form-group">
            <label class="form-label">Minimum Purchase Amount</label>
            <input type="number" name="min_purchase_amount" class="form-control" step="0.01" min="0" value="<?= $discount['min_purchase_amount'] ?>">
          </div>
          <div class="row">
            <div class="col-md-6 form-group">
              <label class="form-label">Start Date</label>
              <input type="date" name="start_date" class="form-control" value="<?= $discount['start_date'] ?>">
            </div>
            <div class="col-md-6 form-group">
              <label class="form-label">End Date</label>
              <input type="date" name="end_date" class="form-control" value="<?= $discount['end_date'] ?>">
            </div>
          </div>
          <div class="form-group">
            <div class="custom-control custom-switch">
              <input type="checkbox" class="custom-control-input" id="statusSwitch" name="status" <?= $discount['status'] ? 'checked' : '' ?>>
              <label class="custom-control-label" for="statusSwitch">Active</label>
            </div>
          </div>
          <button type="submit" class="btn btn-primary btn-block py-2"><i class="fas fa-save"></i> Update Discount</button>
          <a href="discounts.php" class="btn btn-secondary btn-block">Cancel</a>
        </form>
      </div>
    </div>
  </div>
</div>

<?php require_once '../../includes/footer.php'; ?>
