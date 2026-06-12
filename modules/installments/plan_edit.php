<?php
session_start();
$page_title = 'Edit Installment Plan';
$base_url = '../../';
require_once '../../includes/functions.php';
$id = (int)($_GET['id']??0);
$item = getById('installment_plans', $id);
if (!$item) redirect('plans.php', 'Plan not found', 'error');

if ($_SERVER['REQUEST_METHOD']==='POST') {
    update('installment_plans', [
        'name'=>$_POST['name'],
        'duration_months'=>(int)$_POST['duration_months'],
        'interest_rate'=>(float)($_POST['interest_rate']??0),
        'status'=>isset($_POST['status'])?1:0,
        'updated_at'=>date('Y-m-d'),
    ], $id);
    redirect('plans.php', 'Plan updated');
}

require_once '../../includes/header.php';
?>

<div class="row justify-content-center">
  <div class="col-lg-6">
    <div class="card shadow mb-4">
      <div class="card-header py-3"><h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-edit"></i> Edit Installment Plan</h6></div>
      <div class="card-body">
        <form method="post">
          <div class="form-group">
            <label>Plan Name <span class="text-danger">*</span></label>
            <input type="text" name="name" class="form-control" value="<?=htmlspecialchars($item['name'])?>" required>
          </div>
          <div class="row">
            <div class="col-md-6 form-group">
              <label>Duration (Months) <span class="text-danger">*</span></label>
              <select name="duration_months" class="form-control" required>
                <?php foreach ([3,6,9,12,18,24,36,48] as $m): ?>
                  <option value="<?=$m?>" <?=$item['duration_months']==$m?'selected':''?>><?=$m?> Month<?=$m>1?'s':''?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-6 form-group">
              <label>Interest Rate (%)</label>
              <input type="number" name="interest_rate" class="form-control" step="0.01" min="0" value="<?=$item['interest_rate']?>">
            </div>
          </div>
          <div class="form-group">
            <div class="custom-control custom-switch">
              <input type="checkbox" class="custom-control-input" id="s" name="status" <?=$item['status']?'checked':''?>>
              <label class="custom-control-label" for="s">Active</label>
            </div>
          </div>
          <button type="submit" class="btn btn-primary btn-block py-2"><i class="fas fa-save"></i> Update Plan</button>
          <a href="plans.php" class="btn btn-secondary btn-block">Cancel</a>
        </form>
      </div>
    </div>
  </div>
</div>

<?php require_once '../../includes/footer.php'; ?>
