<?php
session_start();
$page_title = 'New Discount';
$base_url = '../../';
require_once '../../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    insert('discounts', [
        'name' => $_POST['name'],
        'description' => $_POST['description'] ?? '',
        'discount_type' => $_POST['discount_type'],
        'discount_value' => (float)$_POST['discount_value'],
        'start_date' => $_POST['start_date'] ?: null,
        'end_date' => $_POST['end_date'] ?: null,
        'min_purchase_amount' => (float)($_POST['min_purchase_amount'] ?? 0),
        'status' => isset($_POST['status']) ? 1 : 0,
        'created_at' => date('Y-m-d'),
        'updated_at' => date('Y-m-d'),
    ]);
    redirect('discounts.php', 'Discount created');
}

require_once '../../includes/header.php';
?>

<div class="row justify-content-center">
  <div class="col-lg-6">
    <div class="card shadow mb-4">
      <div class="card-header py-3"><h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-percent"></i> New Discount</h6></div>
      <div class="card-body">
        <form method="post">
          <div class="form-group"><label class="form-label">Name <span class="text-danger">*</span></label><input type="text" name="name" class="form-control" required></div>
          <div class="form-group"><label class="form-label">Description</label><textarea name="description" class="form-control" rows="2"></textarea></div>
          <div class="row">
            <div class="col-6">
              <div class="form-group"><label class="form-label">Type <span class="text-danger">*</span></label>
                <select name="discount_type" class="form-control" required>
                  <option value="percentage">Percentage (%)</option>
                  <option value="fixed">Fixed Amount</option>
                </select>
              </div>
            </div>
            <div class="col-6">
              <div class="form-group"><label class="form-label">Value <span class="text-danger">*</span></label><input type="number" name="discount_value" class="form-control" step="0.01" min="0" required></div>
            </div>
          </div>
          <div class="row">
            <div class="col-6">
              <div class="form-group"><label class="form-label">Start Date</label><input type="date" name="start_date" class="form-control"></div>
            </div>
            <div class="col-6">
              <div class="form-group"><label class="form-label">End Date</label><input type="date" name="end_date" class="form-control"></div>
            </div>
          </div>
          <div class="form-group"><label class="form-label">Min. Purchase Amount</label><input type="number" name="min_purchase_amount" class="form-control" step="0.01" min="0" value="0"></div>
          <div class="form-group"><div class="custom-control custom-switch"><input type="checkbox" class="custom-control-input" id="s" name="status" checked><label class="custom-control-label" for="s">Active</label></div></div>
          <button type="submit" class="btn btn-primary btn-block py-2"><i class="fas fa-save"></i> Create</button>
          <a href="discounts.php" class="btn btn-secondary btn-block">Cancel</a>
        </form>
      </div>
    </div>
  </div>
</div>

<?php require_once '../../includes/footer.php'; ?>
