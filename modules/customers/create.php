<?php
session_start();
$page_title = 'Customer Registration';
$base_url = '../../';
require_once '../../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'customer_no'     => generateCustomerNo(),
        'full_name'       => $_POST['full_name'],
        'phone'           => $_POST['phone'],
        'email'           => $_POST['email'] ?? '',
        'address'         => $_POST['address'] ?? '',
        'city'            => $_POST['city'] ?? '',
        'cnic'            => $_POST['cnic'],
        'cnic_expiry'     => $_POST['cnic_expiry'] ?: null,
        'guardian_name'   => $_POST['guardian_name'] ?? '',
        'guardian_relation' => $_POST['guardian_relation'] ?? '',
        'occupation'      => $_POST['occupation'] ?? '',
        'monthly_income'  => $_POST['monthly_income'] ?? 0,
        'opening_due'     => (float)($_POST['opening_due'] ?? 0),
        'opening_paid'    => (float)($_POST['opening_paid'] ?? 0),
        'notes'           => $_POST['notes'] ?? '',
        'branch_id'       => 1,
        'created_by'      => 1,
        'created_at'      => date('Y-m-d'),
        'updated_at'      => date('Y-m-d')
    ];

    $customer_id = insert('customers', $data);

    for ($i = 1; $i <= 2; $i++) {
        if (!empty($_POST["g_full_name_$i"])) {
            insert('guarantors', [
                'customer_id'          => $customer_id,
                'full_name'            => $_POST["g_full_name_$i"],
                'phone'                => $_POST["g_phone_$i"],
                'email'                => $_POST["g_email_$i"] ?? '',
                'address'              => $_POST["g_address_$i"] ?? '',
                'cnic'                 => $_POST["g_cnic_$i"],
                'guardian_name'        => $_POST["g_guardian_$i"] ?? '',
                'relation_to_customer' => $_POST["g_relation_$i"] ?? '',
                'occupation'           => $_POST["g_occupation_$i"] ?? '',
                'monthly_income'       => $_POST["g_income_$i"] ?? 0,
                'created_at'           => date('Y-m-d'),
                'updated_at'           => date('Y-m-d')
            ]);
        }
    }

    redirect('index.php', 'Customer registered successfully');
}

require_once '../../includes/header.php';
?>

<form method="post" class="needs-validation" novalidate>

  <!-- Personal Information -->
  <div class="card shadow mb-4">
    <div class="card-header py-3">
      <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-user"></i> Personal Information</h6>
    </div>
    <div class="card-body">
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
        <div class="col-md-6 mb-3">
          <label class="form-label">Address</label>
          <textarea name="address" class="form-control" rows="2"></textarea>
        </div>
        <div class="col-md-3 mb-3">
          <label class="form-label">City</label>
          <input type="text" name="city" class="form-control">
        </div>
        <div class="col-md-3 mb-3">
          <label class="form-label">Occupation</label>
          <input type="text" name="occupation" class="form-control" placeholder="e.g. Shopkeeper, Teacher, Govt Job">
        </div>
      </div>
    </div>
  </div>

  <!-- CNIC / ID Record -->
  <div class="card shadow mb-4">
    <div class="card-header py-3">
      <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-id-card"></i> CNIC / ID Record</h6>
    </div>
    <div class="card-body">
      <div class="row">
        <div class="col-md-4 mb-3">
          <label class="form-label">CNIC Number <span class="text-danger">*</span></label>
          <input type="text" name="cnic" class="form-control" placeholder="XXXXX-XXXXXXX-X" required>
        </div>
        <div class="col-md-4 mb-3">
          <label class="form-label">CNIC Expiry</label>
          <input type="date" name="cnic_expiry" class="form-control">
        </div>
        <div class="col-md-4 mb-3">
          <label class="form-label">Guardian Name</label>
          <input type="text" name="guardian_name" class="form-control" placeholder="Father / Husband">
        </div>
        <div class="col-md-4 mb-3">
          <label class="form-label">Relation with Guardian</label>
          <select name="guardian_relation" class="form-control">
            <option value="">Select</option>
            <option value="Father">Father</option>
            <option value="Husband">Husband</option>
            <option value="Brother">Brother</option>
            <option value="Other">Other</option>
          </select>
        </div>
        <div class="col-md-4 mb-3">
          <label class="form-label">Monthly Income (PKR)</label>
          <input type="number" name="monthly_income" class="form-control" step="0.01" min="0">
        </div>
      </div>
    </div>
  </div>

  <!-- Opening Balance -->
  <div class="card shadow mb-4">
    <div class="card-header py-3">
      <h6 class="m-0 font-weight-bold text-warning"><i class="fas fa-coins"></i> Opening Balance</h6>
    </div>
    <div class="card-body">
      <p class="text-muted small">For customers who already paid before this system. Leave as 0 for new customers.</p>
      <div class="row">
        <div class="col-md-6 mb-3">
          <label class="form-label">Total Due (PKR)</label>
          <input type="number" name="opening_due" class="form-control" step="0.01" min="0" value="0" placeholder="Total amount customer owes">
        </div>
        <div class="col-md-6 mb-3">
          <label class="form-label">Already Paid (PKR)</label>
          <input type="number" name="opening_paid" class="form-control" step="0.01" min="0" value="0" placeholder="Amount already received">
        </div>
      </div>
      <div class="alert alert-info py-2 small mb-0">
        <strong>Remaining:</strong> <span id="openingRemaining">0.00</span>
        <button type="button" class="btn btn-sm btn-link p-0 ml-3" onclick="calcOpening()">Refresh</button>
      </div>
    </div>
  </div>

  <!-- Guarantor 1 -->
  <div class="card shadow mb-4">
    <div class="card-header py-3">
      <h6 class="m-0 font-weight-bold text-success"><i class="fas fa-handshake"></i> Guarantor / Co-Borrower 1</h6>
    </div>
    <div class="card-body">
      <div class="row">
        <div class="col-md-6 mb-3">
          <label class="form-label">Full Name</label>
          <input type="text" name="g_full_name_1" class="form-control">
        </div>
        <div class="col-md-3 mb-3">
          <label class="form-label">Phone</label>
          <input type="text" name="g_phone_1" class="form-control">
        </div>
        <div class="col-md-3 mb-3">
          <label class="form-label">Email</label>
          <input type="email" name="g_email_1" class="form-control">
        </div>
        <div class="col-md-4 mb-3">
          <label class="form-label">CNIC</label>
          <input type="text" name="g_cnic_1" class="form-control" placeholder="XXXXX-XXXXXXX-X">
        </div>
        <div class="col-md-4 mb-3">
          <label class="form-label">Relation to Customer</label>
          <input type="text" name="g_relation_1" class="form-control">
        </div>
        <div class="col-md-4 mb-3">
          <label class="form-label">Monthly Income</label>
          <input type="number" name="g_income_1" class="form-control" step="0.01" min="0">
        </div>
        <div class="col-md-6 mb-3">
          <label class="form-label">Address</label>
          <textarea name="g_address_1" class="form-control" rows="2"></textarea>
        </div>
        <div class="col-md-3 mb-3">
          <label class="form-label">Occupation</label>
          <input type="text" name="g_occupation_1" class="form-control">
        </div>
      </div>
    </div>
  </div>

  <!-- Guarantor 2 -->
  <!-- <div class="card shadow mb-4">
    <div class="card-header py-3">
      <h6 class="m-0 font-weight-bold text-info"><i class="fas fa-handshake"></i> Guarantor / Co-Borrower 2</h6>
    </div>
    <div class="card-body">
      <div class="row">
        <div class="col-md-6 mb-3">
          <label class="form-label">Full Name</label>
          <input type="text" name="g_full_name_2" class="form-control">
        </div>
        <div class="col-md-3 mb-3">
          <label class="form-label">Phone</label>
          <input type="text" name="g_phone_2" class="form-control">
        </div>
        <div class="col-md-3 mb-3">
          <label class="form-label">Email</label>
          <input type="email" name="g_email_2" class="form-control">
        </div>
        <div class="col-md-4 mb-3">
          <label class="form-label">CNIC</label>
          <input type="text" name="g_cnic_2" class="form-control" placeholder="XXXXX-XXXXXXX-X">
        </div>
        <div class="col-md-4 mb-3">
          <label class="form-label">Relation to Customer</label>
          <input type="text" name="g_relation_2" class="form-control">
        </div>
        <div class="col-md-4 mb-3">
          <label class="form-label">Monthly Income</label>
          <input type="number" name="g_income_2" class="form-control" step="0.01" min="0">
        </div>
        <div class="col-md-6 mb-3">
          <label class="form-label">Address</label>
          <textarea name="g_address_2" class="form-control" rows="2"></textarea>
        </div>
        <div class="col-md-3 mb-3">
          <label class="form-label">Guardian Name</label>
          <input type="text" name="g_guardian_2" class="form-control">
        </div>
        <div class="col-md-3 mb-3">
          <label class="form-label">Occupation</label>
          <input type="text" name="g_occupation_2" class="form-control">
        </div>
      </div>
    </div>
  </div> -->

  <!-- Notes -->
  <div class="card shadow mb-4">
    <div class="card-header py-3">
      <h6 class="m-0 font-weight-bold text-secondary"><i class="fas fa-sticky-note"></i> Notes</h6>
    </div>
    <div class="card-body">
      <textarea name="notes" class="form-control" rows="3" placeholder="Additional notes..."></textarea>
    </div>
  </div>

  <div class="mb-4">
    <button type="submit" class="btn btn-primary">
      <i class="fas fa-save"></i> Register Customer
    </button>
    <a href="index.php" class="btn btn-secondary">Cancel</a>
  </div>
</form>

<script>
function calcOpening() {
  var due = parseFloat(document.querySelector('[name=opening_due]').value) || 0;
  var paid = parseFloat(document.querySelector('[name=opening_paid]').value) || 0;
  document.getElementById('openingRemaining').textContent = (due - paid).toFixed(2);
}
document.querySelector('[name=opening_due]').addEventListener('input', calcOpening);
document.querySelector('[name=opening_paid]').addEventListener('input', calcOpening);
</script>

<?php require_once '../../includes/footer.php'; ?>
