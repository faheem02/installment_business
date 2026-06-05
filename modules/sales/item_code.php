<?php
session_start();
$page_title = 'Item Code Lookup';
$base_url = '../../';
require_once '../../includes/functions.php';

$search = $_GET['search'] ?? '';
$category_id = (int)($_GET['category_id'] ?? 0);

$sql = "SELECT p.*, c.name AS category_name, b.name AS brand_name
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.id
        LEFT JOIN brands b ON p.brand_id = b.id
        WHERE p.status = 1";
$params = [];

if ($search) {
    $sql .= " AND (p.code LIKE ? OR p.name LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if ($category_id) {
    $sql .= " AND p.category_id = ?";
    $params[] = $category_id;
}
$sql .= " ORDER BY p.code ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();

$categories = getAll('categories', 'name ASC');

require_once '../../includes/header.php';
?>

<style>
  .item-card {
    border: 1px solid #e3e6f0;
    border-radius: 10px;
    padding: 16px;
    transition: all 0.2s;
    background: #fff;
    height: 100%;
  }
  .item-card:hover {
    box-shadow: 0 4px 12px rgba(0,0,0,0.08);
    border-color: #f59e0b;
  }
  .item-card .code-badge {
    font-family: monospace;
    font-size: 0.9rem;
    background: #0f172a;
    color: #fff;
    padding: 4px 10px;
    border-radius: 6px;
    display: inline-block;
    letter-spacing: 0.5px;
  }
  .item-card .price-tag {
    font-size: 1.3rem;
    font-weight: 700;
    color: #0f172a;
  }
  .stock-badge {
    font-size: 0.8rem;
  }
  .search-box {
    background: #f8f9fc;
    border-radius: 10px;
    padding: 20px;
  }
</style>

<div class="card shadow mb-4">
  <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
    <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-barcode"></i> Item Code Lookup</h6>
  </div>
  <div class="card-body">
    <form method="get" class="search-box mb-4">
      <div class="row">
        <div class="col-md-5">
          <div class="input-group">
            <div class="input-group-prepend">
              <span class="input-group-text"><i class="fas fa-search"></i></span>
            </div>
            <input type="text" name="search" class="form-control" placeholder="Search by Item Code or Name..." value="<?= htmlspecialchars($search) ?>" autofocus>
          </div>
        </div>
        <div class="col-md-3">
          <select name="category_id" class="form-control">
            <option value="">All Categories</option>
            <?php foreach ($categories as $cat): ?>
              <option value="<?= $cat['id'] ?>" <?= $category_id === (int)$cat['id'] ? 'selected' : '' ?>><?= htmlspecialchars($cat['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-2">
          <button type="submit" class="btn btn-primary btn-block"><i class="fas fa-search"></i> Search</button>
        </div>
        <div class="col-md-2">
          <a href="item_code.php" class="btn btn-secondary btn-block"><i class="fas fa-redo"></i> Reset</a>
        </div>
      </div>
    </form>

    <?php if (empty($products)): ?>
      <div class="text-center text-muted py-5">
        <i class="fas fa-box-open fa-3x mb-3"></i>
        <p>No products found. Try a different search term.</p>
      </div>
    <?php else: ?>
      <div class="row">
        <?php foreach ($products as $p): ?>
          <div class="col-xl-3 col-lg-4 col-md-6 mb-4">
            <div class="item-card">
              <div class="d-flex justify-content-between align-items-start mb-2">
                <span class="code-badge"><i class="fas fa-barcode mr-1"></i> <?= htmlspecialchars($p['code']) ?></span>
                <span class="stock-badge badge badge-<?= $p['stock_quantity'] > 0 ? ($p['stock_quantity'] <= $p['min_stock_level'] ? 'warning' : 'success') : 'danger' ?>">
                  <?= $p['stock_quantity'] > 0 ? $p['stock_quantity'] . ' in stock' : 'Out of stock' ?>
                </span>
              </div>
              <h6 class="font-weight-bold mb-1"><?= htmlspecialchars($p['name']) ?></h6>
              <?php if ($p['description']): ?>
                <p class="small text-muted mb-2"><?= htmlspecialchars(substr($p['description'], 0, 60)) ?></p>
              <?php endif; ?>
              <div class="d-flex justify-content-between align-items-end mt-2">
                <div>
                  <div class="price-tag"><?= formatCurrency($p['sale_price']) ?></div>
                  <small class="text-muted">Cost: <?= formatCurrency($p['purchase_price']) ?></small>
                </div>
                <div class="text-right small text-muted">
                  <?php if ($p['category_name']): ?>
                    <div><?= htmlspecialchars($p['category_name']) ?></div>
                  <?php endif; ?>
                  <?php if ($p['brand_name']): ?>
                    <div><?= htmlspecialchars($p['brand_name']) ?></div>
                  <?php endif; ?>
                </div>
              </div>
              <hr class="my-2">
              <a href="../sales/index.php?product_id=<?= $p['id'] ?>" class="btn btn-sm btn-primary btn-block">
                <i class="fas fa-shopping-cart"></i> Quick Sale
              </a>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</div>

<script>
$(document).ready(function() {});
</script>

<?php require_once '../../includes/footer.php'; ?>
