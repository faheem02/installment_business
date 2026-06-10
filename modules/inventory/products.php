<?php
session_start();
$page_title = 'Products';
$base_url = '../../';
require_once '../../includes/functions.php';

$search = $_GET['search'] ?? '';
$cat_id = (int)($_GET['category_id'] ?? 0);
$supplier_id = (int)($_GET['supplier_id'] ?? 0);

$sql = "SELECT p.*, c.name AS category_name, b.name AS brand_name, s.name AS supplier_name, s.contact_person AS supplier_contact FROM products p LEFT JOIN categories c ON p.category_id=c.id LEFT JOIN brands b ON p.brand_id=b.id LEFT JOIN suppliers s ON p.supplier_id=s.id WHERE 1=1";
$params = [];
if ($search) { $sql .= " AND (p.code LIKE ? OR p.name LIKE ?)"; $params[] = "%$search%"; $params[] = "%$search%"; }
if ($cat_id) { $sql .= " AND p.category_id=?"; $params[] = $cat_id; }
if ($supplier_id) { $sql .= " AND p.supplier_id=?"; $params[] = $supplier_id; }
$sql .= " ORDER BY p.code ASC";
$stmt = $pdo->prepare($sql); $stmt->execute($params);
$items = $stmt->fetchAll();
$categories = getAll('categories', 'name ASC');

if (isset($_GET['toggle'])) {
    $id = (int)$_GET['toggle'];
    $c = getById('products', $id);
    if ($c) { $ns = $c['status'] ? 0 : 1; $pdo->prepare("UPDATE products SET status=?,updated_at=CURDATE() WHERE id=?")->execute([$ns,$id]); redirect('products.php', 'Product status updated'); }
}
if (isset($_GET['delete'])) { delete('products', (int)$_GET['delete']); redirect('products.php', 'Product deleted'); }

require_once '../../includes/header.php';
?>

<div class="card shadow mb-4">
  <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
    <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-box"></i> Products</h6>
    <a href="product_create.php" class="btn btn-primary btn-sm"><i class="fas fa-plus"></i> New Product</a>
  </div>
  <div class="card-body">
    <form method="get" class="mb-3">
      <div class="row">
        <div class="col-md-3"><input type="text" name="search" class="form-control" placeholder="Search by code or name..." value="<?=htmlspecialchars($search)?>"></div>
        <div class="col-md-3">
          <select name="category_id" class="form-control">
            <option value="">All Categories</option>
            <?php foreach ($categories as $cat): ?>
              <option value="<?=$cat['id']?>" <?=$cat_id===$cat['id']?'selected':''?>><?=htmlspecialchars($cat['name'])?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-3">
          <select name="supplier_id" class="form-control">
            <option value="">All Suppliers</option>
            <?php $all_suppliers = getAll('suppliers', 'name ASC'); foreach ($all_suppliers as $sup): ?>
              <option value="<?=$sup['id']?>" <?=$supplier_id===$sup['id']?'selected':''?>><?=htmlspecialchars($sup['contact_person'] ? $sup['contact_person'] . ' (' . $sup['name'] . ')' : $sup['name'])?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-3">
          <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Filter</button>
          <a href="products.php" class="btn btn-secondary">Reset</a>
        </div>
      </div>
    </form>
    <div class="table-responsive">
      <table class="table table-bordered table-hover">
        <thead class="thead-light">
          <tr><th>Code</th><th>Name</th><th>Type</th><th>Category</th><th>Brand</th><th>Supplier</th><th class="text-right">Sale Price</th><th>Status</th><th>Actions</th></tr>
        </thead>
        <tbody>
          <?php if (empty($items)): ?><tr><td colspan="9" class="text-center text-muted">No products found</td></tr>
          <?php else: foreach ($items as $p): ?>
            <tr>
              <td><strong><?=htmlspecialchars($p['code'])?></strong></td>
              <td><?=htmlspecialchars($p['name'])?>
                <?php if ($p['product_type']==='bike'): ?>
                  <small class="d-block text-muted">E:<?=htmlspecialchars($p['engine_no'])?> C:<?=htmlspecialchars($p['chassis_no'])?></small>
                <?php elseif ($p['product_type']==='mobile'): ?>
                  <small class="d-block text-muted">IMEI:<?=htmlspecialchars($p['imei_no_1'])?></small>
                <?php endif; ?>
              </td>
              <td><span class="badge badge-info"><?=ucfirst($p['product_type']??'general')?></span></td>
              <td><?=htmlspecialchars($p['category_name']??'-')?></td>
              <td><?=htmlspecialchars($p['brand_name']??'-')?></td>
              <td><?php if ($p['supplier_name']): $_c = $p['supplier_contact'] ?? ''; echo htmlspecialchars($_c ? "$_c ({$p['supplier_name']})" : $p['supplier_name']); else: echo '-'; endif; ?></td>
              <td class="text-right"><?=formatCurrency($p['sale_price'])?></td>
              <td><span class="badge badge-<?=$p['status']?'success':'secondary'?>"><?=$p['status']?'Active':'Inactive'?></span></td>
              <td>
                <a href="product_edit.php?id=<?=$p['id']?>" class="btn btn-sm btn-warning" title="Edit"><i class="fas fa-edit"></i></a>
                <button type="button" class="btn btn-sm btn-info view-product" title="View"
                  data-product='<?=htmlspecialchars(json_encode($p), ENT_QUOTES)?>'>
                  <i class="fas fa-eye"></i>
                </button>
                <a href="products.php?toggle=<?=$p['id']?>" class="btn btn-sm btn-<?=$p['status']?'secondary':'success'?>" title="<?=$p['status']?'Deactivate':'Activate'?>"><i class="fas fa-<?=$p['status']?'times':'check'?>"></i></a>
                <a href="products.php?delete=<?=$p['id']?>" class="btn btn-sm btn-danger" title="Delete" onclick="return confirm('Delete product?')"><i class="fas fa-trash"></i></a>
              </td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php require_once '../../includes/footer.php'; ?>

<!-- View Product Modal -->
<div class="modal fade" id="viewProductModal" tabindex="-1" role="dialog">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fas fa-box text-primary"></i> <span id="vpCode"></span></h5>
        <button type="button" class="close" data-dismiss="modal">&times;</button>
      </div>
      <div class="modal-body">
        <table class="table table-bordered table-sm mb-3">
          <tbody id="vpBody"></tbody>
        </table>
        <h6 class="font-weight-bold text-primary mt-3"><i class="fas fa-truck"></i> Purchase History</h6>
        <div class="table-responsive">
          <table class="table table-sm table-bordered">
            <thead class="thead-light">
              <tr><th>Date</th><th>Supplier</th><th>Invoice</th><th class="text-right">Qty</th><th class="text-right">Price</th></tr>
            </thead>
            <tbody id="vpPurchases"><tr><td colspan="5" class="text-center text-muted">Loading...</td></tr></tbody>
          </table>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<script>
$(document).ready(function() {
  $('.view-product').click(function() {
    var p = $(this).data('product');
    var rows = '';
    function r(label, val) {
      rows += '<tr><td class="font-weight-bold" style="width:200px">'+label+'</td><td>'+(val || '-')+'</td></tr>';
    }
    r('Product Code', p.code);
    r('Name', p.name);
    r('Type', p.product_type ? p.product_type.charAt(0).toUpperCase() + p.product_type.slice(1) : 'General');
    r('Category', p.category_name || '-');
    r('Brand', p.brand_name || '-');
    var supDisplay = p.supplier_contact ? p.supplier_contact + ' (' + (p.supplier_name || '-') + ')' : (p.supplier_name || '-');
    r('Supplier', supDisplay);
    r('Description', p.description);
    r('Purchase Price', p.purchase_price ? parseFloat(p.purchase_price).toLocaleString(undefined, {minimumFractionDigits:2}) : '0.00');
    r('Sale Price', p.sale_price ? parseFloat(p.sale_price).toLocaleString(undefined, {minimumFractionDigits:2}) : '0.00');
    r('Stock', p.stock_quantity + ' ' + (p.unit || 'pcs'));
    r('Min Stock Level', p.min_stock_level || '0');
    r('Status', p.status == '1' ? '<span class="badge badge-success">Active</span>' : '<span class="badge badge-secondary">Inactive</span>');
    r('Created', p.created_at);

    if (p.product_type === 'bike') {
      r('Engine No.', p.engine_no);
      r('Chassis No.', p.chassis_no);
      r('Color', p.color);
    } else if (p.product_type === 'mobile') {
      r('IMEI No. 1', p.imei_no_1);
      r('IMEI No. 2', p.imei_no_2 || '-');
      r('Storage', p.storage || '-');
      r('RAM', p.ram || '-');
      r('Color', p.color || '-');
      r('Condition', p.product_condition || 'New');
      r('Warranty', p.warranty_months ? p.warranty_months + ' months' : '-');
    }

    $('#vpCode').text(p.code + ' - ' + p.name);
    $('#vpBody').html(rows);
    $('#vpPurchases').html('<tr><td colspan="5" class="text-center text-muted">Loading...</td></tr>');
    $('#viewProductModal').modal('show');

    fetch('product_purchases.php?product_id=' + p.id)
      .then(function(r) { return r.json(); })
      .then(function(data) {
        if (data.length === 0) {
          $('#vpPurchases').html('<tr><td colspan="5" class="text-center text-muted">No purchases recorded</td></tr>');
        } else {
          var h = '';
          data.forEach(function(pr) {
            var supDisp = pr.supplier_contact ? pr.supplier_contact + ' (' + (pr.supplier_name || '-') + ')' : (pr.supplier_name || '-');
            h += '<tr><td>' + pr.purchase_date + '</td><td>' + supDisp + '</td><td>' + (pr.invoice_no || '-') + '</td><td class="text-right">' + pr.quantity + '</td><td class="text-right">' + parseFloat(pr.purchase_price).toFixed(2) + '</td></tr>';
          });
          $('#vpPurchases').html(h);
        }
      })
      .catch(function() {
        $('#vpPurchases').html('<tr><td colspan="5" class="text-center text-muted">Failed to load</td></tr>');
      });
  });
});
</script>
