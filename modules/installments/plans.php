<?php
session_start();
$page_title = 'Installment Plans';
$base_url = '../../';
require_once '../../includes/functions.php';

if (isset($_GET['toggle'])) {
    $id = (int)$_GET['toggle'];
    $c = getById('installment_plans', $id);
    if ($c) { $ns = $c['status'] ? 0 : 1; $pdo->prepare("UPDATE installment_plans SET status=?,updated_at=CURDATE() WHERE id=?")->execute([$ns,$id]); redirect('plans.php', 'Plan status updated'); }
}
if (isset($_GET['delete'])) { delete('installment_plans', (int)$_GET['delete']); redirect('plans.php', 'Plan deleted'); }

$items = getAll('installment_plans', 'duration_months ASC');

$sales_count = [];
$stmt = $pdo->query("SELECT installment_plan_id, COUNT(*) as cnt FROM sales WHERE installment_plan_id IS NOT NULL AND status='active' GROUP BY installment_plan_id");
foreach ($stmt as $r) { $sales_count[$r['installment_plan_id']] = $r['cnt']; }

require_once '../../includes/header.php';
?>

<div class="card shadow mb-4">
  <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
    <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-calendar-check"></i> Installment Plans</h6>
    <a href="plan_create.php" class="btn btn-primary btn-sm"><i class="fas fa-plus"></i> New Plan</a>
  </div>
  <div class="card-body">
    <p class="text-muted small">Define installment plans (e.g. 3 Months, 6 Months, 12 Months) with interest rates. Plans are used during sales to auto-generate EMI schedules.</p>
    <div class="table-responsive">
      <table class="table table-bordered table-hover">
        <thead class="thead-light">
          <tr><th>Plan Name</th><th>Duration</th><th>Interest Rate</th><th class="text-center">Active Sales</th><th>Status</th><th>Actions</th></tr>
        </thead>
        <tbody>
          <?php if (empty($items)): ?>
            <tr><td colspan="6" class="text-center text-muted">No installment plans found. <a href="plan_create.php">Create one</a>.</td></tr>
          <?php else: foreach ($items as $p): ?>
            <tr>
              <td><strong><?=htmlspecialchars($p['name'])?></strong></td>
              <td><?=$p['duration_months']?> month<?=$p['duration_months']>1?'s':''?></td>
              <td><?=$p['interest_rate']?>%</td>
              <td class="text-center"><?=$sales_count[$p['id']]??0?></td>
              <td><span class="badge badge-<?=$p['status']?'success':'secondary'?>"><?=$p['status']?'Active':'Inactive'?></span></td>
              <td>
                <a href="plan_edit.php?id=<?=$p['id']?>" class="btn btn-sm btn-warning"><i class="fas fa-edit"></i></a>
                <a href="plans.php?toggle=<?=$p['id']?>" class="btn btn-sm btn-<?=$p['status']?'secondary':'success'?>"><i class="fas fa-<?=$p['status']?'times':'check'?>"></i></a>
                <a href="plans.php?delete=<?=$p['id']?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete this plan?')"><i class="fas fa-trash"></i></a>
              </td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php require_once '../../includes/footer.php'; ?>
