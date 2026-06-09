<?php
session_start();
$page_title = 'Guarantor Details';
$base_url = '../../';
require_once '../../includes/functions.php';

$customer_id = (int)($_GET['customer_id'] ?? 0);
$customer = null;
$guarantors = [];

if ($customer_id) {
    $customer = getById('customers', $customer_id);
    if ($customer) {
        $guarantors = getWhere('guarantors', 'customer_id', $customer_id);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $customer_id) {
    if (isset($_POST['add_guarantor'])) {
        insert('guarantors', [
            'customer_id'          => $customer_id,
            'full_name'            => $_POST['full_name'],
            'phone'                => $_POST['phone'],
            'email'                => $_POST['email'] ?? '',
            'address'              => $_POST['address'] ?? '',
            'cnic'                 => $_POST['cnic'],
            'guardian_name'        => $_POST['guardian_name'] ?? '',
            'relation_to_customer' => $_POST['relation_to_customer'] ?? '',
            'occupation'           => $_POST['occupation'] ?? '',
            'monthly_income'       => $_POST['monthly_income'] ?? 0,
            'created_at'           => date('Y-m-d'),
            'updated_at'           => date('Y-m-d')
        ]);
        redirect("guarantors.php?customer_id=$customer_id", 'Guarantor added successfully');
    }
    if (isset($_POST['edit_guarantor'])) {
        update('guarantors', [
            'full_name'            => $_POST['full_name'],
            'phone'                => $_POST['phone'],
            'email'                => $_POST['email'] ?? '',
            'address'              => $_POST['address'] ?? '',
            'cnic'                 => $_POST['cnic'],
            'guardian_name'        => $_POST['guardian_name'] ?? '',
            'relation_to_customer' => $_POST['relation_to_customer'] ?? '',
            'occupation'           => $_POST['occupation'] ?? '',
            'monthly_income'       => $_POST['monthly_income'] ?? 0,
            'updated_at'           => date('Y-m-d')
        ], (int)$_POST['guarantor_id']);
        redirect("guarantors.php?customer_id=$customer_id", 'Guarantor updated successfully');
    }
}

if (isset($_GET['delete_guarantor']) && $customer_id) {
    delete('guarantors', (int)$_GET['delete_guarantor']);
    redirect("guarantors.php?customer_id=$customer_id", 'Guarantor deleted successfully');
}

$customers = getAll('customers', 'full_name ASC');

require_once '../../includes/header.php';
?>

<div class="card shadow mb-4">
  <div class="card-header py-3">
    <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-handshake"></i> Guarantor Details</h6>
  </div>
  <div class="card-body">
    <form method="get" class="row">
      <div class="col-md-10 mb-3 mb-md-0">
        <label class="form-label">Select Customer</label>
        <select name="customer_id" class="form-control" onchange="this.form.submit()">
          <option value="">-- Choose Customer --</option>
          <?php foreach ($customers as $c): ?>
            <option value="<?= $c['id'] ?>" <?= $customer_id == $c['id'] ? 'selected' : '' ?>>
              <?= htmlspecialchars($c['full_name']) ?> (<?= htmlspecialchars($c['cnic']) ?>)
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2 d-flex align-items-end">
        <noscript><button type="submit" class="btn btn-primary btn-block">View</button></noscript>
      </div>
    </form>
  </div>
</div>

<?php if ($customer): ?>

  <div class="d-flex justify-content-between align-items-center mb-3">
    <h6 class="m-0 font-weight-bold text-gray-800">
      Guarantors for: <span class="text-primary"><?= htmlspecialchars($customer['full_name']) ?></span>
    </h6>
    <button class="btn btn-success btn-sm" data-toggle="modal" data-target="#addModal">
      <i class="fas fa-plus-circle"></i> Add Guarantor
    </button>
  </div>

  <?php if (count($guarantors) > 0): ?>
    <div class="row">
      <?php foreach ($guarantors as $g): ?>
      <div class="col-lg-6 mb-4">
        <div class="card shadow h-100">
          <div class="card-header py-3 d-flex justify-content-between align-items-center">
            <h6 class="m-0 font-weight-bold text-success"><i class="fas fa-user"></i> <?= htmlspecialchars($g['full_name']) ?></h6>
            <div>
              <button class="btn btn-primary btn-circle btn-sm" data-toggle="modal" data-target="#editModal<?= $g['id'] ?>">
                <i class="fas fa-pen"></i>
              </button>
              <a href="guarantors.php?customer_id=<?= $customer_id ?>&delete_guarantor=<?= $g['id'] ?>" class="btn btn-danger btn-circle btn-sm" onclick="return confirm('Delete this guarantor?')">
                <i class="fas fa-trash"></i>
              </a>
            </div>
          </div>
          <div class="card-body">
            <table class="table table-sm table-borderless mb-0">
              <tr><th class="text-muted" width="35%">Phone</th><td><?= htmlspecialchars($g['phone']) ?></td></tr>
              <tr><th class="text-muted">Email</th><td><?= htmlspecialchars($g['email'] ?? '-') ?></td></tr>
              <tr><th class="text-muted">CNIC</th><td><?= htmlspecialchars($g['cnic']) ?></td></tr>
              <tr><th class="text-muted">Relation</th><td><?= htmlspecialchars($g['relation_to_customer'] ?? '-') ?></td></tr>
              <tr><th class="text-muted">Occupation</th><td><?= htmlspecialchars($g['occupation'] ?? '-') ?></td></tr>
              <tr><th class="text-muted">Income</th><td>PKR <?= formatCurrency($g['monthly_income']) ?></td></tr>
              <tr><th class="text-muted">Address</th><td><?= htmlspecialchars($g['address'] ?? '-') ?></td></tr>
            </table>
          </div>
        </div>
      </div>

      <!-- Edit Modal -->
      <div class="modal fade" id="editModal<?= $g['id'] ?>" tabindex="-1" role="dialog">
        <div class="modal-dialog modal-lg" role="document">
          <div class="modal-content">
            <form method="post">
              <div class="modal-header">
                <h5 class="modal-title">Edit Guarantor</h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
              </div>
              <div class="modal-body">
                <input type="hidden" name="edit_guarantor" value="1">
                <input type="hidden" name="guarantor_id" value="<?= $g['id'] ?>">
                <div class="row">
                  <div class="col-md-6 mb-3">
                    <label class="form-label">Full Name</label>
                    <input type="text" name="full_name" class="form-control" value="<?= htmlspecialchars($g['full_name']) ?>" required>
                  </div>
                  <div class="col-md-3 mb-3">
                    <label class="form-label">Phone</label>
                    <input type="text" name="phone" class="form-control" value="<?= htmlspecialchars($g['phone']) ?>" required>
                  </div>
                  <div class="col-md-3 mb-3">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($g['email'] ?? '') ?>">
                  </div>
                  <div class="col-md-4 mb-3">
                    <label class="form-label">CNIC</label>
                    <input type="text" name="cnic" class="form-control" value="<?= htmlspecialchars($g['cnic']) ?>">
                  </div>
                  <div class="col-md-4 mb-3">
                    <label class="form-label">Relation</label>
                    <input type="text" name="relation_to_customer" class="form-control" value="<?= htmlspecialchars($g['relation_to_customer'] ?? '') ?>">
                  </div>
                  <div class="col-md-4 mb-3">
                    <label class="form-label">Monthly Income</label>
                    <input type="number" name="monthly_income" class="form-control" step="0.01" value="<?= $g['monthly_income'] ?>">
                  </div>
                  <div class="col-md-6 mb-3">
                    <label class="form-label">Address</label>
                    <textarea name="address" class="form-control" rows="2"><?= htmlspecialchars($g['address'] ?? '') ?></textarea>
                  </div>
                  <div class="col-md-3 mb-3">
                    <label class="form-label">Guardian Name</label>
                    <input type="text" name="guardian_name" class="form-control" value="<?= htmlspecialchars($g['guardian_name'] ?? '') ?>">
                  </div>
                  <div class="col-md-3 mb-3">
                    <label class="form-label">Occupation</label>
                    <input type="text" name="occupation" class="form-control" value="<?= htmlspecialchars($g['occupation'] ?? '') ?>">
                  </div>
                </div>
              </div>
              <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary">Update</button>
              </div>
            </form>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  <?php else: ?>
    <div class="card shadow mb-4">
      <div class="card-body text-center py-4">
        <i class="fas fa-user-plus fa-3x text-gray-300 mb-3"></i>
        <p class="text-gray-500">No guarantors added yet</p>
      </div>
    </div>
  <?php endif; ?>

  <!-- Add Modal -->
  <div class="modal fade" id="addModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
      <div class="modal-content">
        <form method="post">
          <div class="modal-header">
            <h5 class="modal-title">Add Guarantor</h5>
            <button type="button" class="close" data-dismiss="modal">&times;</button>
          </div>
          <div class="modal-body">
            <input type="hidden" name="add_guarantor" value="1">
            <div class="row">
              <div class="col-md-6 mb-3">
                <label class="form-label">Full Name <span class="text-danger">*</span></label>
                <input type="text" name="full_name" class="form-control" required>
              </div>
              <div class="col-md-3 mb-3">
                <label class="form-label">Phone <span class="text-danger">*</span></label>
                <input type="text" name="phone" class="form-control" required>
              </div>
              <div class="col-md-3 mb-3">
                <label class="form-label">Email</label>
                <input type="email" name="email" class="form-control">
              </div>
              <div class="col-md-4 mb-3">
                <label class="form-label">CNIC <span class="text-danger">*</span></label>
                <input type="text" name="cnic" class="form-control" required>
              </div>
              <div class="col-md-4 mb-3">
                <label class="form-label">Relation to Customer</label>
                <input type="text" name="relation_to_customer" class="form-control">
              </div>
              <div class="col-md-4 mb-3">
                <label class="form-label">Monthly Income</label>
                <input type="number" name="monthly_income" class="form-control" step="0.01" min="0">
              </div>
              <div class="col-md-6 mb-3">
                <label class="form-label">Address</label>
                <textarea name="address" class="form-control" rows="2"></textarea>
              </div>
              <div class="col-md-3 mb-3">
                <label class="form-label">Guardian Name</label>
                <input type="text" name="guardian_name" class="form-control">
              </div>
              <div class="col-md-3 mb-3">
                <label class="form-label">Occupation</label>
                <input type="text" name="occupation" class="form-control">
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-success">Add Guarantor</button>
          </div>
        </form>
      </div>
    </div>
  </div>

<?php elseif ($customer_id && !$customer): ?>
  <div class="alert alert-danger">Customer not found</div>
<?php else: ?>
  <div class="card shadow mb-4">
    <div class="card-body text-center py-5">
      <i class="fas fa-handshake fa-3x text-gray-300 mb-3"></i>
      <p class="text-gray-500">Select a customer to manage guarantors</p>
    </div>
  </div>
<?php endif; ?>

<?php require_once '../../includes/footer.php'; ?>
