<?php
session_start();
$page_title = 'Discounts & Promotions';
$base_url = '../../';
require_once '../../includes/functions.php';

if (isset($_GET['toggle'])) {
    $id = (int)$_GET['toggle'];
    $c = getById('discounts', $id);
    if ($c) { $ns = $c['status'] ? 0 : 1; $pdo->prepare("UPDATE discounts SET status=?,updated_at=CURDATE() WHERE id=?")->execute([$ns,$id]); redirect('discounts.php', 'Discount status updated'); }
}
if (isset($_GET['delete'])) { delete('discounts', (int)$_GET['delete']); redirect('discounts.php', 'Discount deleted'); }

$items = getAll('discounts', 'name ASC');
require_once '../../includes/header.php';
?>

<div class="card shadow mb-4">
  <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
    <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-percent"></i> Discounts & Promotions</h6>
    <a href="discount_create.php" class="btn btn-primary btn-sm"><i class="fas fa-plus"></i> New Discount</a>
  </div>
  <div class="card-body">
    <div class="table-responsive">
      <table class="table table-bordered table-hover">
        <thead class="thead-light"><tr><th>Name</th><th>Type</th><th>Value</th><th>Start Date</th><th>End Date</th><th>Status</th><th>Actions</th></tr></thead>
        <tbody>
          <?php if (empty($items)): ?><tr><td colspan="7" class="text-center text-muted">No discounts found</td></tr>
          <?php else: foreach ($items as $c): ?>
            <tr>
              <td><?= htmlspecialchars($c['name']) ?></td>
              <td><span class="badge badge-info"><?= ucfirst($c['discount_type']) ?></span></td>
              <td><?= $c['discount_type'] === 'percentage' ? $c['discount_value'].'%' : formatCurrency($c['discount_value']) ?></td>
              <td><?= $c['start_date'] ? formatDate($c['start_date']) : '-' ?></td>
              <td><?= $c['end_date'] ? formatDate($c['end_date']) : '-' ?></td>
              <td><span class="badge badge-<?= $c['status']?'success':'secondary'?>"><?= $c['status']?'Active':'Inactive'?></span></td>
              <td>
                <a href="discount_edit.php?id=<?=$c['id']?>" class="btn btn-sm btn-warning"><i class="fas fa-edit"></i></a>
                <a href="discounts.php?toggle=<?=$c['id']?>" class="btn btn-sm btn-<?=$c['status']?'secondary':'success'?>"><i class="fas fa-<?=$c['status']?'times':'check'?>"></i></a>
                <a href="discounts.php?delete=<?=$c['id']?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete this discount?')"><i class="fas fa-trash"></i></a>
              </td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php require_once '../../includes/footer.php'; ?>
