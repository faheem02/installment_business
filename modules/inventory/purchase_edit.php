<?php
session_start();
$page_title = 'Purchase Details';
$base_url = '../../';
require_once '../../includes/functions.php';

$id = (int)($_GET['id'] ?? 0);
$purchase = getById('purchases', $id);
if (!$purchase) {
    $_SESSION['error'] = 'Purchase not found.';
    header("Location: purchases.php");
    exit;
}

$suppliers = getAll('suppliers', 'name ASC');

// Handle update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_header'])) {
    $stmt = $pdo->prepare("UPDATE purchases SET supplier_id=?, invoice_no=?, purchase_date=?, notes=?, status=? WHERE id=?");
    $stmt->execute([
        (int)($_POST['supplier_id'] ?? 0) ?: null,
        $_POST['invoice_no'] ?? '',
        $_POST['purchase_date'],
        $_POST['notes'] ?? '',
        $_POST['status'] ?? 'received',
        $id
    ]);
    $_SESSION['success'] = 'Purchase updated.';
    header("Location: purchase_edit.php?id=$id");
    exit;
}

// Handle delete item
if (isset($_GET['delete_item'])) {
    $item_id = (int)$_GET['delete_item'];
    $stmt = $pdo->prepare("SELECT pi.*, p.stock_quantity FROM purchase_items pi JOIN products p ON pi.product_id = p.id WHERE pi.id = ? AND pi.purchase_id = ?");
    $stmt->execute([$item_id, $id]);
    $item = $stmt->fetch();
    if ($item) {
        // Reduce stock
        $pdo->prepare("UPDATE products SET stock_quantity = GREATEST(0, stock_quantity - ?) WHERE id = ?")->execute([$item['quantity'], $item['product_id']]);
        $pdo->prepare("DELETE FROM purchase_items WHERE id = ?")->execute([$item_id]);
        // Recalculate total
        $total = $pdo->prepare("SELECT COALESCE(SUM(subtotal),0) FROM purchase_items WHERE purchase_id = ?");
        $total->execute([$id]);
        $new_total = $total->fetchColumn();
        $pdo->prepare("UPDATE purchases SET total_amount = ?, due_amount = ? WHERE id = ?")->execute([$new_total, $new_total, $id]);
        $_SESSION['success'] = 'Item removed.';
    }
    header("Location: purchase_edit.php?id=$id");
    exit;
}

$items = getWhere('purchase_items', 'purchase_id', $id);
$supplier = $purchase['supplier_id'] ? getById('suppliers', $purchase['supplier_id']) : null;

require_once '../../includes/header.php';
?>

<style>
  .item-row td { vertical-align: middle; }
</style>

<div class="card shadow mb-4">
  <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
    <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-truck"></i> Purchase #<?=$id?></h6>
    <a href="purchases.php" class="btn btn-sm btn-secondary"><i class="fas fa-arrow-left"></i> Back</a>
  </div>
  <div class="card-body">
    <form method="post">
      <div class="row">
        <div class="col-md-3 form-group">
          <label class="font-weight-bold small">Date</label>
          <input type="text" name="purchase_date" class="form-control datepicker" value="<?=$purchase['purchase_date']?>" required autocomplete="off">
        </div>
        <div class="col-md-3 form-group">
          <label class="font-weight-bold small">Supplier</label>
          <select name="supplier_id" class="form-control">
            <option value="">Select Supplier</option>
            <?php foreach ($suppliers as $s): ?>
              <option value="<?=$s['id']?>" <?=$purchase['supplier_id']==$s['id']?'selected':''?>><?=htmlspecialchars($s['contact_person'] ? $s['contact_person'] . ' (' . $s['name'] . ')' : $s['name'])?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-3 form-group">
          <label class="font-weight-bold small">Invoice No.</label>
          <input type="text" name="invoice_no" class="form-control" value="<?=htmlspecialchars($purchase['invoice_no']??'')?>">
        </div>
        <div class="col-md-3 form-group">
          <label class="font-weight-bold small">Status</label>
          <select name="status" class="form-control">
            <option value="received" <?=$purchase['status']==='received'?'selected':''?>>Received</option>
            <option value="pending" <?=$purchase['status']==='pending'?'selected':''?>>Pending</option>
            <option value="cancelled" <?=$purchase['status']==='cancelled'?'selected':''?>>Cancelled</option>
          </select>
        </div>
      </div>
      <div class="form-group">
        <label class="font-weight-bold small">Notes</label>
        <textarea name="notes" class="form-control" rows="2"><?=htmlspecialchars($purchase['notes']??'')?></textarea>
      </div>
      <button type="submit" name="update_header" class="btn btn-primary"><i class="fas fa-save"></i> Update</button>
    </form>
  </div>
</div>

<!-- Items Table -->
<div class="card shadow mb-4">
  <div class="card-header py-3">
    <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-list"></i> Purchase Items</h6>
  </div>
  <div class="card-body">
    <div class="table-responsive">
      <table class="table table-bordered">
        <thead class="thead-light">
          <tr><th>Product</th><th class="text-center">Type</th><th class="text-center">Qty</th><th class="text-right">Price</th><th class="text-right">Subtotal</th><th class="text-center">Action</th></tr>
        </thead>
        <tbody>
          <?php $total = 0; foreach ($items as $item):
            $prod = getById('products', $item['product_id']);
            $total += $item['subtotal'];
          ?>
          <tr>
            <td><?=htmlspecialchars($prod['name']??'Unknown')?> <small class="text-muted d-block"><?=htmlspecialchars($prod['code']??'')?></small></td>
            <td class="text-center"><span class="badge badge-info"><?=ucfirst($prod['product_type']??'general')?></span></td>
            <td class="text-center"><?=$item['quantity']?></td>
            <td class="text-right"><?=formatCurrency($item['purchase_price'])?></td>
            <td class="text-right"><?=formatCurrency($item['subtotal'])?></td>
            <td class="text-center">
              <a href="purchase_edit.php?id=<?=$id?>&delete_item=<?=$item['id']?>" class="btn btn-sm btn-danger" onclick="return confirm('Remove this item? Stock will be reduced.')"><i class="fas fa-trash"></i></a>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
        <tfoot>
          <tr class="font-weight-bold"><td colspan="4" class="text-right">Total</td><td class="text-right"><?=formatCurrency($total)?></td><td></td></tr>
        </tfoot>
      </table>
    </div>
  </div>
</div>

<?php require_once '../../includes/footer.php'; ?>
