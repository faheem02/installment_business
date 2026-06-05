<?php
session_start();
$page_title = 'Discounts & Promotions';
$base_url = '../../';
require_once '../../includes/functions.php';

if (isset($_GET['toggle'])) {
    $id = (int)$_GET['toggle'];
    $d = getById('discounts', $id);
    if ($d) {
        $new_status = $d['status'] ? 0 : 1;
        $pdo->prepare("UPDATE discounts SET status = ?, updated_at = CURDATE() WHERE id = ?")->execute([$new_status, $id]);
        redirect('discounts.php', 'Discount status updated');
    }
}

if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    delete('discounts', $id);
    redirect('discounts.php', 'Discount deleted successfully');
}

$discounts = getAll('discounts', 'created_at DESC');

require_once '../../includes/header.php';
?>

<div class="card shadow mb-4">
  <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
    <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-percent"></i> Discounts & Promotions</h6>
    <a href="discount_create.php" class="btn btn-primary btn-sm">
      <i class="fas fa-plus"></i> New Discount
    </a>
  </div>
  <div class="card-body">
    <div class="table-responsive">
      <table class="table table-bordered table-hover" width="100%" cellspacing="0">
        <thead class="thead-light">
          <tr>
            <th>Name</th>
            <th>Type</th>
            <th>Value</th>
            <th>Min Purchase</th>
            <th>Valid Period</th>
            <th>Status</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($discounts)): ?>
            <tr><td colspan="7" class="text-center text-muted">No discounts found</td></tr>
          <?php else: ?>
            <?php foreach ($discounts as $d): ?>
              <tr>
                <td><?= htmlspecialchars($d['name']) ?></td>
                <td><?= $d['discount_type'] === 'percentage' ? 'Percentage' : 'Fixed Amount' ?></td>
                <td><?= $d['discount_type'] === 'percentage' ? $d['discount_value'] . '%' : formatCurrency($d['discount_value']) ?></td>
                <td class="text-right"><?= formatCurrency($d['min_purchase_amount']) ?></td>
                <td>
                  <?= $d['start_date'] ? formatDate($d['start_date']) : 'Any' ?>
                  →
                  <?= $d['end_date'] ? formatDate($d['end_date']) : 'Unlimited' ?>
                </td>
                <td>
                  <span class="badge badge-<?= $d['status'] ? 'success' : 'secondary' ?>">
                    <?= $d['status'] ? 'Active' : 'Inactive' ?>
                  </span>
                </td>
                <td>
                  <a href="discount_edit.php?id=<?= $d['id'] ?>" class="btn btn-sm btn-warning" title="Edit"><i class="fas fa-edit"></i></a>
                  <a href="discounts.php?toggle=<?= $d['id'] ?>" class="btn btn-sm btn-<?= $d['status'] ? 'secondary' : 'success' ?>" title="<?= $d['status'] ? 'Deactivate' : 'Activate' ?>">
                    <i class="fas fa-<?= $d['status'] ? 'times' : 'check' ?>"></i>
                  </a>
                  <a href="discounts.php?delete=<?= $d['id'] ?>" class="btn btn-sm btn-danger" title="Delete" onclick="return confirm('Delete this discount?')"><i class="fas fa-trash"></i></a>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php require_once '../../includes/footer.php'; ?>
