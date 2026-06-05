<?php
session_start();
$page_title = 'Suppliers';
$base_url = '../../';
require_once '../../includes/functions.php';

if (isset($_GET['toggle'])) {
    $id = (int)$_GET['toggle'];
    $c = getById('suppliers', $id);
    if ($c) { $ns = $c['status'] ? 0 : 1; $pdo->prepare("UPDATE suppliers SET status=?,updated_at=CURDATE() WHERE id=?")->execute([$ns,$id]); redirect('suppliers.php', 'Supplier status updated'); }
}
if (isset($_GET['delete'])) { delete('suppliers', (int)$_GET['delete']); redirect('suppliers.php', 'Supplier deleted'); }

$items = getAll('suppliers', 'name ASC');
require_once '../../includes/header.php';
?>

<div class="card shadow mb-4">
  <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
    <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-truck-loading"></i> Suppliers</h6>
    <a href="supplier_create.php" class="btn btn-primary btn-sm"><i class="fas fa-plus"></i> New Supplier</a>
  </div>
  <div class="card-body">
    <div class="table-responsive">
      <table class="table table-bordered table-hover">
        <thead class="thead-light"><tr><th>Name</th><th>Contact</th><th>Phone</th><th>City</th><th>Status</th><th>Actions</th></tr></thead>
        <tbody>
          <?php if (empty($items)): ?><tr><td colspan="6" class="text-center text-muted">No suppliers found</td></tr>
          <?php else: foreach ($items as $c): ?>
            <tr>
              <td><?=htmlspecialchars($c['name'])?></td>
              <td><?=htmlspecialchars($c['contact_person']??'-')?></td>
              <td><?=htmlspecialchars($c['phone']??'-')?></td>
              <td><?=htmlspecialchars($c['city']??'-')?></td>
              <td><span class="badge badge-<?=$c['status']?'success':'secondary'?>"><?=$c['status']?'Active':'Inactive'?></span></td>
              <td>
                <a href="supplier_edit.php?id=<?=$c['id']?>" class="btn btn-sm btn-warning"><i class="fas fa-edit"></i></a>
                <a href="suppliers.php?toggle=<?=$c['id']?>" class="btn btn-sm btn-<?=$c['status']?'secondary':'success'?>"><i class="fas fa-<?=$c['status']?'times':'check'?>"></i></a>
                <a href="suppliers.php?delete=<?=$c['id']?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete?')"><i class="fas fa-trash"></i></a>
              </td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php require_once '../../includes/footer.php'; ?>
