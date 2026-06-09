<?php
session_start();
$page_title = 'Edit Product';
$base_url = '../../';
require_once '../../includes/functions.php';

$id = (int)($_GET['id'] ?? 0);
$item = getById('products', $id);
if (!$item) redirect('products.php', 'Product not found', 'error');

$categories = $pdo->query("SELECT id, name, product_type FROM categories ORDER BY name ASC")->fetchAll();
$brands = getAll('brands', 'name ASC');
$suppliers = getAll('suppliers', 'name ASC');
$product_type = $item['product_type'] ?? 'general';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $code = trim($_POST['code']);
    $dup = $pdo->prepare("SELECT id FROM products WHERE code = ? AND id != ?");
    $dup->execute([$code, $id]);
    if ($dup->fetch()) {
        $_SESSION['error'] = "Product code '$code' already exists.";
        header("Location: product_edit.php?id=$id");
        exit;
    }

    update('products', [
        'code' => $code,
        'name' => $_POST['name'],
        'description' => $_POST['description'] ?? '',
        'category_id' => (int)($_POST['category_id'] ?? 0) ?: null,
        'brand_id' => (int)($_POST['brand_id'] ?? 0) ?: null,
        'supplier_id' => (int)($_POST['supplier_id'] ?? 0) ?: null,
        'engine_no' => $_POST['engine_no'] ?? '',
        'chassis_no' => $_POST['chassis_no'] ?? '',
        'color' => $_POST['color'] ?? '',
        'imei_no_1' => $_POST['imei_no_1'] ?? '',
        'imei_no_2' => $_POST['imei_no_2'] ?? '',
        'storage' => $_POST['storage'] ?? '',
        'ram' => $_POST['ram'] ?? '',
        'warranty_months' => (int)($_POST['warranty_months'] ?? 0) ?: null,
        'product_condition' => $_POST['product_condition'] ?? 'New',
        'purchase_price' => (float)($_POST['purchase_price'] ?? 0),
        'sale_price' => (float)($_POST['sale_price'] ?? 0),
        'stock_quantity' => (int)($_POST['stock_quantity'] ?? 0),
        'min_stock_level' => (int)($_POST['min_stock_level'] ?? 0),
        'unit' => $_POST['unit'] ?? 'pcs',
        'product_type' => $_POST['product_type'] ?? 'general',
        'has_serial' => isset($_POST['has_serial']) ? 1 : 0,
        'status' => isset($_POST['status']) ? 1 : 0,
        'updated_at' => date('Y-m-d'),
    ], $id);
    redirect('products.php', 'Product updated');
}

require_once '../../includes/header.php';
?>

<style>
.product-type-selector { text-align: right; margin-bottom: 1rem; }
.product-type-selector label { font-weight: 600; margin-right: 0.5rem; color: #0f172a; }
.product-type-selector select { width: auto; display: inline-block; }
.bike-fields, .general-fields, .mobile-fields { display: none; }
.bike-fields.active, .general-fields.active, .mobile-fields.active { display: block; }
</style>

<div class="row justify-content-center">
  <div class="col-lg-12">
    <div class="card shadow mb-4">
      <div class="card-header py-3 d-flex justify-content-between align-items-center">
        <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-edit"></i> Edit Product</h6>
        <div class="product-type-selector mb-0">
          <label for="productType">Product Type</label>
          <select id="productType" name="product_type" class="form-control form-control-sm" onchange="toggleProductType(this.value)">
            <option value="general" <?=$product_type==='general'?'selected':''?>>General</option>
            <option value="bike" <?=$product_type==='bike'?'selected':''?>>Bike</option>
            <option value="mobile" <?=$product_type==='mobile'?'selected':''?>>Mobile</option>
          </select>
        </div>
      </div>
      <div class="card-body">
        <form method="post" novalidate>
          <input type="hidden" name="product_type" id="productTypeHidden" value="<?=htmlspecialchars($product_type)?>">

          <!-- General Fields -->
          <div class="general-fields <?=$product_type==='general'?'active':''?>" id="generalFields">
            <div class="row">
              <div class="col-md-4 form-group">
                <label class="form-label">Item Code <span class="text-danger">*</span></label>
                <input type="text" name="code" class="form-control" value="<?=htmlspecialchars($item['code'])?>" required>
              </div>
              <div class="col-md-8 form-group">
                <label class="form-label">Product Name <span class="text-danger">*</span></label>
                <input type="text" name="name" class="form-control" value="<?=htmlspecialchars($item['name'])?>" required>
              </div>
            </div>
            <div class="form-group">
              <label class="form-label">Description</label>
              <textarea name="description" class="form-control" rows="2"><?=htmlspecialchars($item['description'])?></textarea>
            </div>
            <div class="row">
              <div class="col-md-6 form-group">
                <label class="form-label">Category</label>
                <select name="category_id" class="form-control category-select">
                  <option value="">Select Category</option>
                  <?php foreach ($categories as $c): ?><option value="<?=$c['id']?>" data-type="<?=$c['product_type'] ?? ''?>" <?=$item['category_id']==$c['id']?'selected':''?>><?=htmlspecialchars($c['name'])?></option><?php endforeach; ?>
                </select>
              </div>
              <div class="col-md-6 form-group">
                <label class="form-label">Brand</label>
                <select name="brand_id" class="form-control">
                  <option value="">Select Brand</option>
                  <?php foreach ($brands as $b): ?><option value="<?=$b['id']?>" <?=$item['brand_id']==$b['id']?'selected':''?>><?=htmlspecialchars($b['name'])?></option><?php endforeach; ?>
                </select>
              </div>
            </div>
            <div class="row">
              <div class="col-md-6 form-group">
                <label class="form-label">Supplier</label>
                <select name="supplier_id" class="form-control">
                  <option value="">Select Supplier</option>
                  <?php foreach ($suppliers as $s): ?><option value="<?=$s['id']?>" <?=$item['supplier_id']==$s['id']?'selected':''?>><?=htmlspecialchars($s['name'])?></option><?php endforeach; ?>
                </select>
              </div>
            </div>
            <div class="row">
              <div class="col-md-6 form-group"><label>Purchase Price</label><input type="number" name="purchase_price" class="form-control" step="0.01" min="0" value="<?=$item['purchase_price']?>"></div>
              <div class="col-md-6 form-group"><label>Sale Price <span class="text-danger">*</span></label><input type="number" name="sale_price" class="form-control" step="0.01" min="0" value="<?=$item['sale_price']?>" required></div>
            </div>
          </div>

          <!-- Bike Fields -->
          <div class="bike-fields <?=$product_type==='bike'?'active':''?>" id="bikeFields">
            <div class="row">
              <div class="col-md-4 form-group">
                <label class="form-label">Item Code <span class="text-danger">*</span></label>
                <input type="text" name="code" class="form-control" value="<?=htmlspecialchars($item['code'])?>" required>
              </div>
              <div class="col-md-8 form-group">
                <label class="form-label">Model <span class="text-danger">*</span></label>
                <input type="text" name="name" class="form-control" value="<?=htmlspecialchars($item['name'])?>" placeholder="e.g. CD 70, CG 125" required>
              </div>
            </div>
            <div class="row">
              <div class="col-md-6 form-group">
                <label class="form-label">Company <span class="text-danger">*</span></label>
                <select name="brand_id" class="form-control" required>
                  <option value="">Select Company</option>
                  <?php foreach ($brands as $b): ?><option value="<?=$b['id']?>" <?=$item['brand_id']==$b['id']?'selected':''?>><?=htmlspecialchars($b['name'])?></option><?php endforeach; ?>
                </select>
              </div>
              <div class="col-md-6 form-group">
                <label class="form-label">Category <span class="text-danger">*</span></label>
                <select name="category_id" class="form-control category-select" required>
                  <option value="">Select Category</option>
                  <?php foreach ($categories as $c): ?><option value="<?=$c['id']?>" data-type="<?=$c['product_type'] ?? ''?>" <?=$item['category_id']==$c['id']?'selected':''?>><?=htmlspecialchars($c['name'])?></option><?php endforeach; ?>
                </select>
              </div>
            </div>
            <div class="row">
              <div class="col-md-6 form-group">
                <label class="form-label">Supplier</label>
                <select name="supplier_id" class="form-control">
                  <option value="">Select Supplier</option>
                  <?php foreach ($suppliers as $s): ?><option value="<?=$s['id']?>" <?=$item['supplier_id']==$s['id']?'selected':''?>><?=htmlspecialchars($s['name'])?></option><?php endforeach; ?>
                </select>
              </div>
            </div>
            <div class="row">
              <div class="col-md-4 form-group">
                <label class="form-label">Engine No. <span class="text-danger">*</span></label>
                <input type="text" name="engine_no" class="form-control" value="<?=htmlspecialchars($item['engine_no'])?>" placeholder="Engine number" required>
              </div>
              <div class="col-md-4 form-group">
                <label class="form-label">Chassis No. <span class="text-danger">*</span></label>
                <input type="text" name="chassis_no" class="form-control" value="<?=htmlspecialchars($item['chassis_no'])?>" placeholder="Chassis number" required>
              </div>
              <div class="col-md-4 form-group">
                <label class="form-label">Color</label>
                <input type="text" name="color" class="form-control" value="<?=htmlspecialchars($item['color'])?>" placeholder="e.g. Red, Black">
              </div>
            </div>
            <div class="row">
              <div class="col-md-6 form-group">
                <label class="form-label">Purchase Price <span class="text-danger">*</span></label>
                <input type="number" name="purchase_price" class="form-control" step="0.01" min="0" value="<?=$item['purchase_price']?>" required>
              </div>
              <div class="col-md-6 form-group">
                <label class="form-label">Sale Price <span class="text-danger">*</span></label>
                <input type="number" name="sale_price" class="form-control" step="0.01" min="0" value="<?=$item['sale_price']?>" required>
              </div>
            </div>
          </div>

          <!-- Mobile Fields -->
          <div class="mobile-fields <?=$product_type==='mobile'?'active':''?>" id="mobileFields">
            <div class="row">
              <div class="col-md-4 form-group">
                <label class="form-label">Item Code <span class="text-danger">*</span></label>
                <input type="text" name="code" class="form-control" value="<?=htmlspecialchars($item['code'])?>" placeholder="e.g. MOB-001">
              </div>
              <div class="col-md-8 form-group">
                <label class="form-label">Model <span class="text-danger">*</span></label>
                <input type="text" name="name" class="form-control" value="<?=htmlspecialchars($item['name'])?>" placeholder="e.g. iPhone 14, Galaxy S24">
              </div>
            </div>
            <div class="row">
              <div class="col-md-6 form-group">
                <label class="form-label">Company <span class="text-danger">*</span></label>
                <select name="brand_id" class="form-control">
                  <option value="">Select Company</option>
                  <?php foreach ($brands as $b): ?><option value="<?=$b['id']?>" <?=$item['brand_id']==$b['id']?'selected':''?>><?=htmlspecialchars($b['name'])?></option><?php endforeach; ?>
                </select>
              </div>
              <div class="col-md-6 form-group">
                <label class="form-label">Category <span class="text-danger">*</span></label>
                <select name="category_id" class="form-control category-select">
                  <option value="">Select Category</option>
                  <?php foreach ($categories as $c): ?><option value="<?=$c['id']?>" data-type="<?=$c['product_type'] ?? ''?>" <?=$item['category_id']==$c['id']?'selected':''?>><?=htmlspecialchars($c['name'])?></option><?php endforeach; ?>
                </select>
              </div>
            </div>
            <div class="row">
              <div class="col-md-6 form-group">
                <label class="form-label">Supplier</label>
                <select name="supplier_id" class="form-control">
                  <option value="">Select Supplier</option>
                  <?php foreach ($suppliers as $s): ?><option value="<?=$s['id']?>" <?=$item['supplier_id']==$s['id']?'selected':''?>><?=htmlspecialchars($s['name'])?></option><?php endforeach; ?>
                </select>
              </div>
            </div>
            <div class="row">
              <div class="col-md-6 form-group">
                <label class="form-label">IMEI No. 1 <span class="text-danger">*</span></label>
                <input type="text" name="imei_no_1" class="form-control" value="<?=htmlspecialchars($item['imei_no_1'])?>" placeholder="IMEI number">
              </div>
              <div class="col-md-6 form-group">
                <label class="form-label">IMEI No. 2</label>
                <input type="text" name="imei_no_2" class="form-control" value="<?=htmlspecialchars($item['imei_no_2'])?>" placeholder="Second IMEI (dual SIM)">
              </div>
            </div>
            <div class="row">
              <div class="col-md-4 form-group">
                <label class="form-label">Storage</label>
                <select name="storage" class="form-control">
                  <option value="">Select Storage</option>
                  <?php foreach (['16GB','32GB','64GB','128GB','256GB','512GB','1TB'] as $s): ?>
                    <option value="<?=$s?>" <?=$item['storage']===$s?'selected':''?>><?=$s?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-md-4 form-group">
                <label class="form-label">RAM</label>
                <select name="ram" class="form-control">
                  <option value="">Select RAM</option>
                  <?php foreach (['2GB','3GB','4GB','6GB','8GB','12GB','16GB'] as $r): ?>
                    <option value="<?=$r?>" <?=$item['ram']===$r?'selected':''?>><?=$r?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-md-4 form-group">
                <label class="form-label">Condition</label>
                <select name="product_condition" class="form-control">
                  <option value="New" <?=$item['product_condition']==='New'?'selected':''?>>New</option>
                  <option value="Used" <?=$item['product_condition']==='Used'?'selected':''?>>Used</option>
                  <option value="Refurbished" <?=$item['product_condition']==='Refurbished'?'selected':''?>>Refurbished</option>
                </select>
              </div>
            </div>
            <div class="row">
              <div class="col-md-4 form-group">
                <label class="form-label">Warranty (months)</label>
                <input type="number" name="warranty_months" class="form-control" min="0" value="<?=$item['warranty_months']?>" placeholder="e.g. 12">
              </div>
              <div class="col-md-4 form-group">
                <label class="form-label">Purchase Price <span class="text-danger">*</span></label>
                <input type="number" name="purchase_price" class="form-control" step="0.01" min="0" value="<?=$item['purchase_price']?>">
              </div>
              <div class="col-md-4 form-group">
                <label class="form-label">Sale Price <span class="text-danger">*</span></label>
                <input type="number" name="sale_price" class="form-control" step="0.01" min="0" value="<?=$item['sale_price']?>">
              </div>
            </div>
          </div>

          <div class="row">
            <div class="col-md-6 form-group">
              <div class="custom-control custom-switch">
                <input type="checkbox" class="custom-control-input" id="serialSwitch" name="has_serial" <?=$item['has_serial']?'checked':''?>>
                <label class="custom-control-label" for="serialSwitch">Track Serial / IMEI</label>
              </div>
            </div>
            <div class="col-md-6 form-group">
              <div class="custom-control custom-switch">
                <input type="checkbox" class="custom-control-input" id="statusSwitch" name="status" <?=$item['status']?'checked':''?>>
                <label class="custom-control-label" for="statusSwitch">Active</label>
              </div>
            </div>
          </div>
          <button type="submit" class="btn btn-primary btn-block py-2"><i class="fas fa-save"></i> Update Product</button>
          <a href="products.php" class="btn btn-secondary btn-block">Cancel</a>
        </form>
      </div>
    </div>
  </div>
</div>

<script>
function toggleProductType(type) {
  document.getElementById('productTypeHidden').value = type;
  document.querySelectorAll('.general-fields, .bike-fields, .mobile-fields').forEach(function(el) {
    el.classList.remove('active');
    el.querySelectorAll('input, select, textarea').forEach(function(inp) {
      inp.disabled = true;
    });
  });
  var target = document.getElementById(type === 'bike' ? 'bikeFields' : type === 'mobile' ? 'mobileFields' : 'generalFields');
  target.classList.add('active');
  target.querySelectorAll('input, select, textarea').forEach(function(inp) {
    inp.disabled = false;
  });
  target.querySelectorAll('.category-select option').forEach(function(opt) {
    if (opt.value === '') return;
    var optType = opt.getAttribute('data-type');
    opt.hidden = optType !== '' && optType !== type;
  });
}
toggleProductType('<?=htmlspecialchars($product_type)?>');
</script>

<?php require_once '../../includes/footer.php'; ?>
