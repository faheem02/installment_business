<?php
session_start();
$page_title = 'Purchases';
$base_url = '../../';
require_once '../../includes/functions.php';

// Handle delete
if (isset($_GET['delete'])) {
    $pid = (int)$_GET['delete'];
    $pdo->prepare("UPDATE products SET stock_quantity = stock_quantity - COALESCE((SELECT SUM(quantity) FROM purchase_items WHERE purchase_id = ?), 0) WHERE id IN (SELECT product_id FROM purchase_items WHERE purchase_id = ?)")->execute([$pid, $pid]);
    $pdo->prepare("DELETE FROM product_serials WHERE purchase_id = ?")->execute([$pid]);
    $pdo->prepare("DELETE FROM purchase_items WHERE purchase_id = ?")->execute([$pid]);
    $pdo->prepare("DELETE FROM purchases WHERE id = ?")->execute([$pid]);
    $_SESSION['success'] = 'Purchase deleted successfully.';
    header("Location: purchases.php");
    exit;
}

$suppliers = getAll('suppliers', 'name ASC');

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $purchase_date = $_POST['purchase_date'];
    $supplier_id   = (int)($_POST['supplier_id'] ?? 0);
    $invoice_no    = $_POST['invoice_no'] ?? '';
    $product_id    = (int)($_POST['product_id'] ?? 0);
    $purchase_price = (float)($_POST['purchase_price'] ?? 0);
    $product_type  = $_POST['product_type'] ?? 'general';
    $notes         = $_POST['notes'] ?? '';

    // Determine quantity based on product type
    if ($product_type === 'bike') {
        $engines = $_POST['bike_engine'] ?? [];
        $chassis = $_POST['bike_chassis'] ?? [];
        $quantity = max(count($engines), count($chassis), 1);
    } elseif ($product_type === 'mobile') {
        $imei1s = $_POST['mobile_imei1'] ?? [];
        $quantity = max(count($imei1s), 1);
    } elseif ($product_type === 'laptop') {
        $quantity = (int)($_POST['quantity'] ?? 1);
    } else {
        $quantity = (int)($_POST['quantity'] ?? 1);
    }

    $subtotal = $quantity * $purchase_price;

    // Uniqueness checks for bike/mobile identifiers
    if ($product_type === 'bike') {
        $engines = $_POST['bike_engine'] ?? [];
        $chassis = $_POST['bike_chassis'] ?? [];
        $all_serials = array_merge(array_filter($engines), array_filter($chassis));
        foreach ($all_serials as $s) {
            $chk = $pdo->prepare("SELECT COUNT(*) FROM product_serials WHERE serial_number = ?");
            $chk->execute([$s]);
            if ($chk->fetchColumn() > 0) {
                $_SESSION['error'] = "Engine/Chassis No. '$s' already exists in system.";
                header("Location: purchases.php");
                exit;
            }
        }
    } elseif ($product_type === 'mobile') {
        $imei1s = $_POST['mobile_imei1'] ?? [];
        $imei2s = $_POST['mobile_imei2'] ?? [];
        $all_imeis = array_merge(array_filter($imei1s), array_filter($imei2s));
        foreach ($all_imeis as $i) {
            $chk = $pdo->prepare("SELECT COUNT(*) FROM product_serials WHERE imei_number = ?");
            $chk->execute([$i]);
            if ($chk->fetchColumn() > 0) {
                $_SESSION['error'] = "IMEI '$i' already exists in system.";
                header("Location: purchases.php");
                exit;
            }
        }
    }

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("INSERT INTO purchases (supplier_id, invoice_no, purchase_date, total_amount, paid_amount, due_amount, status, notes, created_by, created_at, updated_at) VALUES (?, ?, ?, ?, 0, ?, 'received', ?, ?, NOW(), NOW())");
        $stmt->execute([$supplier_id ?: null, $invoice_no, $purchase_date, $subtotal, $subtotal, $notes, $_SESSION['user_id'] ?? 1]);
        $purchase_id = $pdo->lastInsertId();

        $stmt = $pdo->prepare("INSERT INTO purchase_items (purchase_id, product_id, quantity, purchase_price, subtotal) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$purchase_id, $product_id, $quantity, $purchase_price, $subtotal]);

        // Update product stock
        $pdo->prepare("UPDATE products SET stock_quantity = stock_quantity + ? WHERE id = ?")->execute([$quantity, $product_id]);

        // Record individual unit identifiers
        if ($product_type === 'bike') {
            $engines = $_POST['bike_engine'] ?? [];
            $chassis = $_POST['bike_chassis'] ?? [];
            $color = $_POST['bike_color'] ?? '';
            for ($i = 0; $i < $quantity; $i++) {
                $eng = $engines[$i] ?? '';
                $cha = $chassis[$i] ?? '';
                $serial = $eng ?: $cha;
                if ($serial) {
                    $stmt = $pdo->prepare("INSERT INTO product_serials (product_id, serial_number, purchase_id, status, notes, created_at, updated_at) VALUES (?, ?, ?, 'available', ?, NOW(), NOW())");
                    $stmt->execute([$product_id, $serial, $purchase_id, "Bike $color - $cha"]);
                }
            }
        } elseif ($product_type === 'mobile') {
            $imei1s = $_POST['mobile_imei1'] ?? [];
            $imei2s = $_POST['mobile_imei2'] ?? [];
            $storages = $_POST['mobile_storage'] ?? [];
            $rams = $_POST['mobile_ram'] ?? [];
            $cond = $_POST['mobile_condition'] ?? 'New';
            for ($i = 0; $i < $quantity; $i++) {
                $i1 = $imei1s[$i] ?? '';
                $i2 = $imei2s[$i] ?? '';
                $st = $storages[$i] ?? '';
                $ra = $rams[$i] ?? '';
                if ($i1) {
                    $stmt = $pdo->prepare("INSERT INTO product_serials (product_id, imei_number, purchase_id, status, notes, created_at, updated_at) VALUES (?, ?, ?, 'available', ?, NOW(), NOW())");
                    $stmt->execute([$product_id, $i1, $purchase_id, "Mobile - $st/$ra - $cond"]);
                }
                if ($i2) {
                    $stmt = $pdo->prepare("INSERT INTO product_serials (product_id, imei_number, purchase_id, status, created_at, updated_at) VALUES (?, ?, ?, 'available', NOW(), NOW())");
                    $stmt->execute([$product_id, $i2, $purchase_id]);
                }
            }
        }

        $pdo->commit();
        $_SESSION['success'] = 'Purchase recorded successfully.';
        header("Location: purchases.php");
        exit;
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['error'] = 'Error: ' . $e->getMessage();
    }
}

// Fetch recent purchases with product info
$recent = $pdo->query("
    SELECT p.*, s.name AS supplier_name, s.contact_person AS supplier_contact,
        GROUP_CONCAT(DISTINCT pr.name SEPARATOR ', ') AS product_names,
        GROUP_CONCAT(DISTINCT pi.quantity SEPARATOR ', ') AS product_qtys,
        (SELECT COALESCE(SUM(quantity), 0) FROM purchase_items pi WHERE pi.purchase_id = p.id) AS item_count
    FROM purchases p
    LEFT JOIN suppliers s ON p.supplier_id = s.id
    LEFT JOIN purchase_items pi ON pi.purchase_id = p.id
    LEFT JOIN products pr ON pr.id = pi.product_id
    GROUP BY p.id
    ORDER BY p.created_at DESC
")->fetchAll();

require_once '../../includes/header.php';
?>

<style>
  .type-radio-group { display: flex; gap: 20px; margin-bottom: 20px; }
  .type-radio-group label { display: flex; align-items: center; gap: 8px; cursor: pointer; padding: 10px 20px; border: 2px solid #e2e8f0; border-radius: 10px; transition: all .2s; }
  .type-radio-group label:hover { border-color: #a0aec0; }
  .type-radio-group input:checked + label { border-color: #4e73df; background: #eef2ff; font-weight: 600; }
  .type-radio-group input { display: none; }
  .type-section { display: none; }
  .type-section.active { display: block; }
</style>

<div class="d-flex justify-content-between align-items-center mb-3">
  <h5 class="m-0 font-weight-bold" style="color:#0f172a;"><i class="fas fa-truck"></i> Purchases</h5>
  <button class="btn btn-primary btn-sm" onclick="togglePurchaseForm()">
    <i class="fas fa-plus" id="toggleBtnIcon"></i> New Purchase
  </button>
</div>

<!-- Purchase List -->
<div class="card shadow mb-4" id="purchaseHistoryCard">
  <div class="card-header py-3 d-flex justify-content-between align-items-center">
    <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-history"></i> Purchase History</h6>
    <span class="text-muted small"><?= count($recent) ?> records</span>
  </div>
  <div class="card-body">
    <div class="table-responsive">
      <table class="table table-bordered table-hover">
        <thead class="thead-light">
          <tr><th>Date</th><th>Supplier</th><th>Invoice</th><th>Product</th><th class="text-right">Qty</th><th class="text-right">Total</th><th>Status</th><th class="text-center">Actions</th></tr>
        </thead>
        <tbody>
          <?php if (empty($recent)): ?>
            <tr><td colspan="8" class="text-center text-muted">No purchases yet</td></tr>
          <?php else: foreach ($recent as $r): ?>
            <tr>
              <td><?=formatDate($r['purchase_date'])?></td>
              <td><?php if ($r['supplier_name']): $_contact = $r['supplier_contact'] ?? ''; echo htmlspecialchars($_contact ? "$_contact ({$r['supplier_name']})" : $r['supplier_name']); else: echo '-'; endif; ?></td>
              <td><?=htmlspecialchars($r['invoice_no']??'-')?></td>
              <td><?=htmlspecialchars($r['product_names']??'-')?></td>
              <td class="text-right"><?=$r['item_count']?></td>
              <td class="text-right"><?=formatCurrency($r['total_amount'])?></td>
              <td><span class="badge badge-<?=$r['status']==='received'?'success':'warning'?>"><?=ucfirst($r['status'])?></span></td>
              <td class="text-center">
                <button type="button" class="btn btn-sm btn-info" title="View" data-id="<?=$r['id']?>" onclick="viewPurchase(this)"><i class="fas fa-eye"></i></button>
                <a href="purchase_edit.php?id=<?=$r['id']?>" class="btn btn-sm btn-primary" title="Edit"><i class="fas fa-pen"></i></a>
                <a href="javascript:void(0)" onclick="window.open('purchase_print.php?id=<?=$r['id']?>','popup','width=900,height=600')" class="btn btn-sm btn-secondary" title="Print"><i class="fas fa-print"></i></a>
                <a href="purchases.php?delete=<?=$r['id']?>" class="btn btn-sm btn-danger" title="Delete" onclick="return confirm('Delete this purchase?')"><i class="fas fa-trash"></i></a>
              </td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<div class="card shadow mb-4" id="newPurchaseCard" style="display:none;">
  <div class="card-header py-3">
    <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-plus-circle"></i> New Purchase Entry</h6>
  </div>
  <div class="card-body">
    <form method="post">
      <div class="row mb-3">
        <div class="col-md-4 form-group">
          <label class="font-weight-bold small">Purchase Date</label>
          <input type="text" name="purchase_date" class="form-control datepicker" value="<?=date('Y-m-d')?>" required autocomplete="off">
        </div>
        <div class="col-md-4 form-group">
          <label class="font-weight-bold small">Supplier</label>
          <select name="supplier_id" class="form-control">
            <option value="">Select Supplier</option>
            <?php foreach ($suppliers as $s): ?>
              <option value="<?=$s['id']?>"><?=htmlspecialchars(($s['contact_person'] ? $s['contact_person'] . ' (' . $s['name'] . ')' : $s['name']))?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-4 form-group">
          <label class="font-weight-bold small">Invoice No.</label>
          <input type="text" name="invoice_no" class="form-control" placeholder="Supplier invoice #">
        </div>
      </div>

      <hr>
      <label class="font-weight-bold small">Product Type</label>
      <div class="type-radio-group">
        <input type="radio" name="product_type" value="bike" id="typeBike" checked>
        <label for="typeBike"><i class="fas fa-motorcycle fa-lg"></i> Bike</label>

        <input type="radio" name="product_type" value="mobile" id="typeMobile">
        <label for="typeMobile"><i class="fas fa-mobile-alt fa-lg"></i> Mobile</label>

        <input type="radio" name="product_type" value="laptop" id="typeLaptop">
        <label for="typeLaptop"><i class="fas fa-laptop fa-lg"></i> Laptop</label>

        <input type="radio" name="product_type" value="general" id="typeGeneral">
        <label for="typeGeneral"><i class="fas fa-box fa-lg"></i> Others</label>
        
      </div>

      <!-- Bike Section -->
      <div class="type-section active" id="sectionBike">
        <div class="row mb-3">
          <div class="col-md-4 form-group">
            <label class="font-weight-bold small">Model</label>
            <select class="form-control" id="bikeModel" onchange="fillBikeDetails(this)">
              <option value="">Select Model</option>
              <?php
              $bikes = $pdo->query("SELECT id, code, name, purchase_price, color FROM products WHERE product_type='bike' AND status=1")->fetchAll();
              foreach ($bikes as $b): ?>
              <option value="<?=$b['id']?>" data-price="<?=$b['purchase_price']?>" data-color="<?=htmlspecialchars($b['color']??'')?>"><?=htmlspecialchars($b['name'])?> (<?=htmlspecialchars($b['code'])?>)</option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-4 form-group">
            <label class="font-weight-bold small">Purchase Price</label>
            <input type="number" class="form-control" id="bikePrice" step="0.01" required oninput="document.getElementById('bikePriceHidden').value = this.value">
          </div>
          <div class="col-md-4 form-group">
            <label class="font-weight-bold small">Color</label>
            <input type="text" class="form-control" id="bikeColor" placeholder="Color">
          </div>
        </div>
        <label class="font-weight-bold small">Enter Bike Details</label>
        <span class="badge badge-primary ml-2" id="bikeCount">Quantity: 1</span>
        <div class="table-responsive mb-2">
          <table class="table table-bordered table-sm" id="bikeTable">
            <thead class="thead-light">
              <tr><th style="width:40px;">#</th><th>Engine No.</th><th>Chassis No.</th><th style="width:50px;"></th></tr>
            </thead>
            <tbody>
              <tr>
                <td class="text-center">1</td>
                <td><input type="text" name="bike_engine[]" class="form-control form-control-sm" placeholder="Engine No."></td>
                <td><input type="text" name="bike_chassis[]" class="form-control form-control-sm" placeholder="Chassis No."></td>
                <td class="text-center"></td>
              </tr>
            </tbody>
          </table>
        </div>
        <button type="button" class="btn btn-sm btn-success" onclick="addBikeRow()"><i class="fas fa-plus"></i> Add Bike</button>
        <input type="hidden" name="product_id" id="bikeProductId" value="">
        <input type="hidden" name="purchase_price" id="bikePriceHidden" value="">
        <input type="hidden" name="bike_color" id="bikeColorHidden" value="">
      </div>

      <!-- Mobile Section -->
      <div class="type-section" id="sectionMobile">
        <div class="row mb-3">
          <div class="col-md-4 form-group">
            <label class="font-weight-bold small">Model</label>
            <select class="form-control" id="mobileModel" onchange="fillMobileDetails(this)">
              <option value="">Select Model</option>
              <?php
              $mobiles = $pdo->query("SELECT id, code, name, purchase_price, storage, ram, product_condition FROM products WHERE product_type='mobile' AND status=1")->fetchAll();
              foreach ($mobiles as $m): ?>
              <option value="<?=$m['id']?>" data-price="<?=$m['purchase_price']?>" data-storage="<?=htmlspecialchars($m['storage']??'')?>" data-ram="<?=htmlspecialchars($m['ram']??'')?>" data-condition="<?=htmlspecialchars($m['product_condition']??'')?>"><?=htmlspecialchars($m['name'])?> (<?=htmlspecialchars($m['code'])?>)</option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-4 form-group">
            <label class="font-weight-bold small">Purchase Price</label>
            <input type="number" class="form-control" id="mobilePrice" step="0.01" required oninput="document.getElementById('mobilePriceHidden').value = this.value">
          </div>
          <div class="col-md-4 form-group">
            <label class="font-weight-bold small">Condition</label>
            <select class="form-control" id="mobileCondition">
              <option value="New">New</option>
              <option value="Used">Used</option>
              <option value="Refurbished">Refurbished</option>
            </select>
          </div>
        </div>
        <label class="font-weight-bold small">Enter Mobile Details</label>
        <span class="badge badge-primary ml-2" id="mobileCount">Quantity: 1</span>
        <div class="table-responsive mb-2">
          <table class="table table-bordered table-sm" id="mobileTable">
            <thead class="thead-light">
              <tr><th style="width:40px;">#</th><th>IMEI No. 1</th><th>IMEI No. 2</th><th>Storage</th><th>RAM</th><th style="width:50px;"></th></tr>
            </thead>
            <tbody>
              <tr>
                <td class="text-center">1</td>
                <td><input type="text" name="mobile_imei1[]" class="form-control form-control-sm" placeholder="IMEI 1"></td>
                <td><input type="text" name="mobile_imei2[]" class="form-control form-control-sm" placeholder="IMEI 2"></td>
                <td><input type="text" name="mobile_storage[]" class="form-control form-control-sm mobileStorageFill" placeholder="Storage"></td>
                <td><input type="text" name="mobile_ram[]" class="form-control form-control-sm mobileRamFill" placeholder="RAM"></td>
                <td class="text-center"></td>
              </tr>
            </tbody>
          </table>
        </div>
        <button type="button" class="btn btn-sm btn-success" onclick="addMobileRow()"><i class="fas fa-plus"></i> Add Mobile</button>
        <input type="hidden" name="product_id" id="mobileProductId" value="">
        <input type="hidden" name="purchase_price" id="mobilePriceHidden" value="">
        <input type="hidden" name="mobile_condition" id="mobileConditionHidden" value="">
      </div>

      <!-- General Section -->
      <div class="type-section" id="sectionGeneral">
        <div class="row">
          <div class="col-md-4 form-group">
            <label class="font-weight-bold small">Product</label>
            <select name="product_id" class="form-control" id="generalProduct" onchange="fillGeneralPrice(this)">
              <option value="">Select Product</option>
              <?php
              $generals = $pdo->query("SELECT id, code, name, purchase_price FROM products WHERE product_type='general' AND status=1")->fetchAll();
              foreach ($generals as $g): ?>
              <option value="<?=$g['id']?>" data-price="<?=$g['purchase_price']?>"><?=htmlspecialchars($g['name'])?> (<?=htmlspecialchars($g['code'])?>)</option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-4 form-group">
            <label class="font-weight-bold small">Purchase Price</label>
            <input type="number" name="purchase_price" class="form-control" id="generalPrice" step="0.01" required>
          </div>
          <div class="col-md-4 form-group">
            <label class="font-weight-bold small">Quantity</label>
            <input type="number" name="quantity" class="form-control" id="generalQty" value="1" min="1" required>
          </div>
        </div>
      </div>

      <!-- Laptop Section -->
      <div class="type-section" id="sectionLaptop">
        <div class="row">
          <div class="col-md-4 form-group">
            <label class="font-weight-bold small">Model</label>
            <select name="product_id" class="form-control" id="laptopProduct" onchange="fillLaptopPrice(this)">
              <option value="">Select Model</option>
              <?php
              $laptops = $pdo->query("SELECT id, code, name, purchase_price, ram, storage FROM products WHERE product_type='laptop' AND status=1")->fetchAll();
              foreach ($laptops as $l): ?>
              <option value="<?=$l['id']?>" data-price="<?=$l['purchase_price']?>" data-ram="<?=htmlspecialchars($l['ram']??'')?>" data-storage="<?=htmlspecialchars($l['storage']??'')?>"><?=htmlspecialchars($l['name'])?> (<?=htmlspecialchars($l['code'])?>)</option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-4 form-group">
            <label class="font-weight-bold small">Purchase Price</label>
            <input type="number" name="purchase_price" class="form-control" id="laptopPrice" step="0.01" required>
          </div>
          <div class="col-md-4 form-group">
            <label class="font-weight-bold small">Quantity</label>
            <input type="number" name="quantity" class="form-control" id="laptopQty" value="1" min="1" required>
          </div>
        </div>
        <div class="row">
          <div class="col-md-4 form-group">
            <label class="font-weight-bold small">RAM</label>
            <input type="text" name="laptop_ram" class="form-control" id="laptopRam" placeholder="e.g. 8GB, 16GB">
          </div>
          <div class="col-md-4 form-group">
            <label class="font-weight-bold small">Storage</label>
            <input type="text" name="laptop_storage" class="form-control" id="laptopStorage" placeholder="e.g. 512GB, 1TB">
          </div>
          <div class="col-md-4 form-group">
            <label class="font-weight-bold small">Color</label>
            <input type="text" name="laptop_color" class="form-control" id="laptopColor" placeholder="e.g. Silver, Black">
          </div>
        </div>
      </div>

      <div class="row">
        <div class="col-md-12 form-group">
          <label class="font-weight-bold small">Description</label>
          <textarea name="notes" class="form-control" rows="2" placeholder="Optional Description..."></textarea>
        </div>
      </div>

      <hr>
      <button type="submit" class="btn btn-primary px-4"><i class="fas fa-save"></i> Make Entry</button>
    </form>
  </div>
</div>

<!-- Recent Purchases -->
<div class="card shadow mb-4">
  <div class="card-header py-3">
    <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-history"></i> Recent Purchases</h6>
  </div>
  <div class="card-body">
    <div class="table-responsive">
      <table class="table table-bordered table-hover">
        <thead class="thead-light">
          <tr><th>Date</th><th>Supplier</th><th>Invoice</th><th>Product</th><th class="text-right">Qty</th><th class="text-right">Total</th><th>Status</th><th class="text-center">Actions</th></tr>
        </thead>
        <tbody>
          <?php if (empty($recent)): ?>
            <tr><td colspan="8" class="text-center text-muted">No purchases yet</td></tr>
          <?php else: foreach ($recent as $r): ?>
            <tr>
              <td><?=formatDate($r['purchase_date'])?></td>
              <td><?php if ($r['supplier_name']): $_contact = $r['supplier_contact'] ?? ''; echo htmlspecialchars($_contact ? "$_contact ({$r['supplier_name']})" : $r['supplier_name']); else: echo '-'; endif; ?></td>
              <td><?=htmlspecialchars($r['invoice_no']??'-')?></td>
              <td><?=htmlspecialchars($r['product_names']??'-')?></td>
              <td class="text-right"><?=$r['item_count']?></td>
              <td class="text-right"><?=formatCurrency($r['total_amount'])?></td>
              <td><span class="badge badge-<?=$r['status']==='received'?'success':'warning'?>"><?=ucfirst($r['status'])?></span></td>
              <td class="text-center">
                <button type="button" class="btn btn-sm btn-info" title="View" data-id="<?=$r['id']?>" onclick="viewPurchase(this)"><i class="fas fa-eye"></i></button>
                <a href="purchase_edit.php?id=<?=$r['id']?>" class="btn btn-sm btn-primary" title="Edit"><i class="fas fa-pen"></i></a>
                <a href="purchases.php?delete=<?=$r['id']?>" class="btn btn-sm btn-danger" title="Delete" onclick="return confirm('Delete this purchase?')"><i class="fas fa-trash"></i></a>
              </td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<script>
function switchType(type) {
  document.querySelectorAll('.type-section').forEach(function(s) {
    s.classList.remove('active');
    s.querySelectorAll('input, select, textarea').forEach(function(el) { el.disabled = true; });
  });
  var active = document.getElementById('section' + type.charAt(0).toUpperCase() + type.slice(1));
  active.classList.add('active');
  active.querySelectorAll('input, select, textarea').forEach(function(el) { el.disabled = false; });
}

document.querySelectorAll('input[name="product_type"]').forEach(function(r) {
  r.addEventListener('change', function() { switchType(this.value); });
});

function fillBikeDetails(sel) {
  var opt = sel.options[sel.selectedIndex];
  document.getElementById('bikeProductId').value = opt.value || '';
  document.getElementById('bikePrice').value = opt.dataset.price || '';
  document.getElementById('bikePriceHidden').value = opt.dataset.price || '';
  document.getElementById('bikeColor').value = opt.value ? opt.dataset.color : '';
  document.getElementById('bikeColorHidden').value = opt.value ? opt.dataset.color : '';
}

function fillMobileDetails(sel) {
  var opt = sel.options[sel.selectedIndex];
  document.getElementById('mobileProductId').value = opt.value || '';
  document.getElementById('mobilePrice').value = opt.dataset.price || '';
  document.getElementById('mobilePriceHidden').value = opt.dataset.price || '';
  document.getElementById('mobileCondition').value = opt.dataset.condition || 'New';
  document.getElementById('mobileConditionHidden').value = opt.dataset.condition || 'New';
  var storage = opt.dataset.storage || '';
  var ram = opt.dataset.ram || '';
  document.querySelectorAll('.mobileStorageFill').forEach(function(e) { if (!e.value) e.value = storage; });
  document.querySelectorAll('.mobileRamFill').forEach(function(e) { if (!e.value) e.value = ram; });
}

function fillGeneralPrice(sel) {
  document.getElementById('generalPrice').value = '';
}

function fillLaptopPrice(sel) {
  var opt = sel.options[sel.selectedIndex];
  document.getElementById('laptopPrice').value = opt.dataset.price || '';
  document.getElementById('laptopRam').value = opt.dataset.ram || '';
  document.getElementById('laptopStorage').value = opt.dataset.storage || '';
  document.getElementById('laptopColor').value = '';
}

function addBikeRow() {
  var tbody = document.querySelector('#bikeTable tbody');
  var rows = tbody.querySelectorAll('tr');
  var num = rows.length + 1;
  var tr = document.createElement('tr');
  tr.innerHTML = '<td class="text-center">'+num+'</td><td><input type="text" name="bike_engine[]" class="form-control form-control-sm" placeholder="Engine No."></td><td><input type="text" name="bike_chassis[]" class="form-control form-control-sm" placeholder="Chassis No."></td><td class="text-center"><button type="button" class="btn btn-sm btn-danger" onclick="removeBikeRow(this)"><i class="fas fa-times"></i></button></td>';
  tbody.appendChild(tr);
  updateBikeCount();
}

function removeBikeRow(btn) {
  btn.closest('tr').remove();
  renumberBikeRows();
  updateBikeCount();
}

function renumberBikeRows() {
  var rows = document.querySelectorAll('#bikeTable tbody tr');
  rows.forEach(function(r, i) { r.cells[0].textContent = i + 1; });
}

function updateBikeCount() {
  var count = document.querySelectorAll('#bikeTable tbody tr').length;
  document.getElementById('bikeCount').textContent = 'Quantity: ' + count;
}

function addMobileRow() {
  var tbody = document.querySelector('#mobileTable tbody');
  var rows = tbody.querySelectorAll('tr');
  var num = rows.length + 1;
  var storage = document.getElementById('mobileModel').options[document.getElementById('mobileModel').selectedIndex]?.dataset?.storage || '';
  var ram = document.getElementById('mobileModel').options[document.getElementById('mobileModel').selectedIndex]?.dataset?.ram || '';
  var tr = document.createElement('tr');
  tr.innerHTML = '<td class="text-center">'+num+'</td><td><input type="text" name="mobile_imei1[]" class="form-control form-control-sm" placeholder="IMEI 1"></td><td><input type="text" name="mobile_imei2[]" class="form-control form-control-sm" placeholder="IMEI 2"></td><td><input type="text" name="mobile_storage[]" class="form-control form-control-sm mobileStorageFill" value="'+storage+'" placeholder="Storage"></td><td><input type="text" name="mobile_ram[]" class="form-control form-control-sm mobileRamFill" value="'+ram+'" placeholder="RAM"></td><td class="text-center"><button type="button" class="btn btn-sm btn-danger" onclick="removeMobileRow(this)"><i class="fas fa-times"></i></button></td>';
  tbody.appendChild(tr);
  updateMobileCount();
}

function removeMobileRow(btn) {
  btn.closest('tr').remove();
  renumberMobileRows();
  updateMobileCount();
}

function renumberMobileRows() {
  var rows = document.querySelectorAll('#mobileTable tbody tr');
  rows.forEach(function(r, i) { r.cells[0].textContent = i + 1; });
}

function updateMobileCount() {
  var count = document.querySelectorAll('#mobileTable tbody tr').length;
  document.getElementById('mobileCount').textContent = 'Quantity: ' + count;
}

switchType('bike');
updateBikeCount();
updateMobileCount();

function togglePurchaseForm() {
  var form = document.getElementById('newPurchaseCard');
  var history = document.getElementById('purchaseHistoryCard');
  var icon = document.getElementById('toggleBtnIcon');
  if (form.style.display === 'none') {
    form.style.display = '';
    history.style.display = 'none';
    icon.classList.remove('fa-plus');
    icon.classList.add('fa-minus');
  } else {
    form.style.display = 'none';
    history.style.display = '';
    icon.classList.remove('fa-minus');
    icon.classList.add('fa-plus');
  }
}
</script>

<!-- Purchase Detail Modal -->
<div class="modal fade" id="purchaseModal" tabindex="-1" role="dialog" aria-labelledby="purchaseModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="purchaseModalLabel"><i class="fas fa-truck"></i> Purchase Details</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body" id="purchaseModalBody">
        <div class="text-center py-4">
          <i class="fas fa-spinner fa-spin fa-2x"></i>
          <p class="mt-2 text-muted">Loading...</p>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
        <a href="#" id="purchaseEditLink" class="btn btn-primary"><i class="fas fa-pen"></i> Edit</a>
      </div>
    </div>
  </div>
</div>

<script>
function viewPurchase(btn) {
    var id = btn.getAttribute('data-id');
    document.getElementById('purchaseEditLink').href = 'purchase_edit.php?id=' + id;
    document.getElementById('purchaseModalBody').innerHTML =
        '<div class="text-center py-4"><i class="fas fa-spinner fa-spin fa-2x"></i><p class="mt-2 text-muted">Loading...</p></div>';
    $('#purchaseModal').modal('show');

    fetch('purchase_view.php?id=' + id)
        .then(function(r) { return r.json(); })
        .then(function(d) {
            if (d.error) {
                document.getElementById('purchaseModalBody').innerHTML = '<div class="alert alert-danger">' + d.error + '</div>';
                return;
            }
            var badge = d.status === 'received' ? 'success' : (d.status === 'cancelled' ? 'danger' : 'warning');
            var html = '';

            // Header info
            html += '<div class="row mb-3">';
            html += '<div class="col-md-3"><small class="text-muted">Date</small><p class="font-weight-bold mb-0">' + d.purchase_date + '</p></div>';
            var supName = d.supplier_name; var supContact = d.supplier_contact || ''; var supDisplay = supContact ? supContact + ' (' + supName + ')' : supName;
            html += '<div class="col-md-3"><small class="text-muted">Supplier</small><p class="font-weight-bold mb-0">' + escapeHtml(supDisplay) + '</p></div>';
            html += '<div class="col-md-3"><small class="text-muted">Invoice No.</small><p class="font-weight-bold mb-0">' + escapeHtml(d.invoice_no || '-') + '</p></div>';
            html += '<div class="col-md-3"><small class="text-muted">Status</small><p class="mb-0"><span class="badge badge-' + badge + '">' + d.status.charAt(0).toUpperCase() + d.status.slice(1) + '</span></p></div>';
            html += '</div>';

            if (d.notes) {
                html += '<div class="mb-3"><small class="text-muted">Notes</small><p class="mb-0">' + escapeHtml(d.notes) + '</p></div>';
            }

            // Items
            html += '<h6 class="font-weight-bold" style="color:#0f172a;">Items</h6>';
            html += '<div class="table-responsive"><table class="table table-bordered table-sm"><thead class="thead-light"><tr><th>Product</th><th>Type</th><th class="text-center">Qty</th><th class="text-right">Price</th><th class="text-right">Subtotal</th></tr></thead><tbody>';
            for (var i = 0; i < d.items.length; i++) {
                var it = d.items[i];
                html += '<tr><td>' + escapeHtml(it.product_name) + '<br><small class="text-muted">' + escapeHtml(it.product_code) + '</small></td>';
                html += '<td><span class="badge badge-info">' + escapeHtml(it.product_type) + '</span></td>';
                html += '<td class="text-center">' + it.quantity + '</td>';
                html += '<td class="text-right">' + formatNum(it.purchase_price) + '</td>';
                html += '<td class="text-right">' + formatNum(it.subtotal) + '</td></tr>';
            }
            html += '<tfoot><tr class="font-weight-bold"><td colspan="4" class="text-right">Total</td><td class="text-right">' + formatNum(d.total_amount) + '</td></tr></tfoot>';
            html += '</tbody></table></div>';

            // Serials
            if (d.serials && d.serials.length > 0) {
                var firstType = d.serials[0].product_type || 'general';
                html += '<h6 class="font-weight-bold" style="color:#0f172a;">Product Identifiers</h6>';
                html += '<div class="table-responsive"><table class="table table-bordered table-sm"><thead class="thead-light"><tr>';
                if (firstType === 'mobile') {
                    html += '<th>#</th><th>IMEI Number</th>';
                } else if (firstType === 'bike') {
                    html += '<th>#</th><th>Engine No.</th><th>Chassis No.</th><th>Color</th>';
                } else {
                    html += '<th>#</th><th>Serial / Identifier</th>';
                }
                html += '<th>Status</th></tr></thead><tbody>';
                for (var j = 0; j < d.serials.length; j++) {
                    var sn = d.serials[j];
                    var pt = sn.product_type || 'general';
                    var snBadge = sn.status === 'available' ? 'success' : (sn.status === 'sold' ? 'secondary' : 'warning');
                    html += '<tr>';
                    html += '<td>' + (j + 1) + '</td>';
                    if (pt === 'mobile') {
                        html += '<td>' + escapeHtml(sn.imei_number || '-') + '</td>';
                    } else if (pt === 'bike') {
                        var eng = sn.serial_number || '-';
                        var col = '', cha = '';
                        if (sn.notes) {
                            var match = sn.notes.match(/^Bike\s+(.*?)\s*-\s*(.*)$/);
                            if (match) {
                                col = match[1].trim();
                                cha = match[2].trim();
                            } else {
                                cha = sn.notes;
                            }
                        }
                        html += '<td>' + escapeHtml(eng) + '</td><td>' + escapeHtml(cha) + '</td><td>' + escapeHtml(col || '-') + '</td>';
                    } else {
                        html += '<td>' + escapeHtml(sn.serial_number || sn.imei_number || '-') + '</td>';
                    }
                    html += '<td><span class="badge badge-' + snBadge + '">' + sn.status + '</span></td>';
                    html += '</tr>';
                }
                html += '</tbody></table></div>';
            }

            html += '<p class="text-muted small mb-0">Created: ' + d.created_at + '</p>';
            document.getElementById('purchaseModalBody').innerHTML = html;
        })
        .catch(function() {
            document.getElementById('purchaseModalBody').innerHTML = '<div class="alert alert-danger">Failed to load purchase details.</div>';
        });
}

function escapeHtml(str) {
    if (!str) return '';
    var div = document.createElement('div');
    div.appendChild(document.createTextNode(str));
    return div.innerHTML;
}

function formatNum(n) {
    return parseFloat(n).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}
</script>

<?php require_once '../../includes/footer.php'; ?>
