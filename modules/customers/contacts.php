<?php
session_start();
$page_title = 'Contact Information';
$base_url = '../../';
require_once '../../includes/functions.php';

$search = $_GET['search'] ?? '';
$customers = [];

if ($search) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM customers WHERE full_name LIKE ? OR phone LIKE ? OR email LIKE ? ORDER BY full_name ASC");
    $stmt->execute(["%$search%", "%$search%", "%$search%"]);
    $customers = $stmt->fetchAll();
} else {
    $customers = getAll('customers', 'full_name ASC');
}

require_once '../../includes/header.php';
?>

<div class="card shadow mb-4">
  <div class="card-header py-3">
    <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-search"></i> Search Contacts</h6>
  </div>
  <div class="card-body">
    <form method="get" class="row">
      <div class="col-md-10 mb-3 mb-md-0">
        <input type="text" name="search" class="form-control" placeholder="Search by name, phone or email..." value="<?= htmlspecialchars($search) ?>">
      </div>
      <div class="col-md-2">
        <button type="submit" class="btn btn-primary btn-block"><i class="fas fa-search"></i> Search</button>
      </div>
    </form>
  </div>
</div>

<div class="row">
  <?php if (count($customers) > 0): ?>
    <?php foreach ($customers as $c): ?>
    <div class="col-xl-3 col-md-4 col-sm-6 mb-4">
      <div class="card shadow h-100">
        <div class="card-body text-center">
          <div class="mx-auto mb-3 d-flex align-items-center justify-content-center bg-primary text-white rounded-circle" style="width: 64px; height: 64px; font-size: 1.5rem; font-weight: 700;">
            <?= strtoupper(substr($c['full_name'], 0, 1)) ?>
          </div>
          <h6 class="font-weight-bold mb-0"><?= htmlspecialchars($c['full_name']) ?></h6>
          <small class="text-muted"><?= htmlspecialchars($c['customer_no']) ?></small>
          <hr>
          <div class="text-left small">
            <div class="mb-1"><i class="fas fa-phone text-primary mr-2"></i> <?= htmlspecialchars($c['phone']) ?></div>
            <div class="mb-1"><i class="fas fa-envelope text-primary mr-2"></i> <?= htmlspecialchars($c['email'] ?? '-') ?></div>
            <div class="mb-1"><i class="fas fa-map-marker-alt text-primary mr-2"></i> <?= htmlspecialchars($c['address'] ?? '-') ?></div>
            <div class="mb-1"><i class="fas fa-city text-primary mr-2"></i> <?= htmlspecialchars($c['city'] ?? '-') ?></div>
            <div class="mb-1"><i class="fas fa-id-card text-primary mr-2"></i> <?= htmlspecialchars($c['cnic']) ?></div>
          </div>
          <a href="view.php?id=<?= $c['id'] ?>" class="btn btn-primary btn-sm mt-2 btn-block">
            <i class="fas fa-eye"></i> View Profile
          </a>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  <?php else: ?>
    <div class="col-12">
      <div class="card shadow mb-4">
        <div class="card-body text-center py-5">
          <i class="fas fa-address-book fa-3x text-gray-300 mb-3"></i>
          <p class="text-gray-500">No contacts found</p>
        </div>
      </div>
    </div>
  <?php endif; ?>
</div>

<?php require_once '../../includes/footer.php'; ?>
