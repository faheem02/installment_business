<?php
session_start();
$page_title = 'New Installment Plan';
$base_url = '../../';
require_once '../../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    insert('installment_plans', [
        'name' => $_POST['name'],
        'duration_months' => (int)$_POST['duration_months'],
        'interest_rate' => (float)($_POST['interest_rate'] ?? 0),
        'status' => isset($_POST['status']) ? 1 : 0,
        'created_at' => date('Y-m-d'),
        'updated_at' => date('Y-m-d'),
    ]);
    redirect('plans.php', 'Installment plan created');
}

require_once '../../includes/header.php';
?>

<div class="row justify-content-center">
  <div class="col-lg-6">
    <div class="card shadow mb-4">
      <div class="card-header py-3"><h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-calendar-plus"></i> New Installment Plan</h6></div>
      <div class="card-body">
        <form method="post">
          <div class="form-group">
            <label class="form-label">Plan Name <span class="text-danger">*</span></label>
            <input type="text" name="name" class="form-control" placeholder="e.g. 3 Months, 6 Months, 12 Months" required>
            <small class="text-muted">Example: "6 Months", "Easy 12"</small>
          </div>
          <div class="row">
            <div class="col-md-6 form-group">
              <label class="form-label">Duration (Months) <span class="text-danger">*</span></label>
              <select name="duration_months" class="form-control" required>
                <option value="">Select</option>
                <?php foreach ([3,6,9,12,18,24,36,48] as $m): ?>
                  <option value="<?=$m?>"><?=$m?> Month<?=$m>1?'s':''?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-6 form-group">
              <label class="form-label">Interest Rate (%)</label>
              <input type="number" name="interest_rate" class="form-control" step="0.01" min="0" value="0" placeholder="e.g. 5 for 5%">
              <small class="text-muted">Annual interest rate (0 for interest-free)</small>
            </div>
          </div>
          <div class="form-group">
            <div class="custom-control custom-switch">
              <input type="checkbox" class="custom-control-input" id="s" name="status" checked>
              <label class="custom-control-label" for="s">Active</label>
            </div>
          </div>
          <button type="submit" class="btn btn-primary btn-block py-2"><i class="fas fa-save"></i> Create Plan</button>
          <a href="plans.php" class="btn btn-secondary btn-block">Cancel</a>
        </form>
      </div>
    </div>
  </div>
</div>

<?php require_once '../../includes/footer.php'; ?>
