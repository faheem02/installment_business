<?php
session_start();
$page_title = 'Down Payments';
$base_url = '../../';
require_once '../../includes/functions.php';

$customers = getAll('customers', 'full_name ASC');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $customer_id = (int)($_POST['customer_id'] ?? 0);
    $amount = (float)($_POST['amount'] ?? 0);
    $payment_date = $_POST['payment_date'] ?? date('Y-m-d');
    $payment_method = $_POST['payment_method'] ?? 'cash';
    $reference = $_POST['reference_no'] ?? '';
    $notes = $_POST['notes'] ?? '';

    // Create a quick sale record for this down payment
    $sale_id = insert('sales', [
        'customer_id' => $customer_id,
        'invoice_no' => 'DP-' . date('ymd') . '-' . time(),
        'total_amount' => $amount,
        'down_payment' => $amount,
        'sale_date' => $payment_date,
        'status' => 'completed',
        'branch_id' => 1,
        'created_by' => 1,
        'created_at' => date('Y-m-d'),
        'updated_at' => date('Y-m-d'),
    ]);

    // Record the payment
    $payment_id = insert('payments', [
        'sale_id' => $sale_id,
        'payment_date' => $payment_date,
        'amount' => $amount,
        'payment_type' => 'down_payment',
        'payment_method' => $payment_method,
        'reference_no' => $reference,
        'notes' => $notes,
        'branch_id' => 1,
        'received_by' => 1,
        'created_at' => date('Y-m-d'),
    ]);

    if ($payment_method === 'cash') {
        recordCashInflow($pdo, $payment_date, $amount, 'Down payment - ' . ($notes ?: 'Customer'), 'payment', $payment_id, 1);
    } elseif ($payment_method === 'card') {
        recordBankInflow($pdo, $payment_date, $amount, 'Down payment (card) - ' . ($notes ?: 'Customer'), 'payment', $payment_id, 1);
    }

    redirect('down_payments.php', 'Down payment of ' . number_format($amount, 2) . ' recorded');
}

$sql = "SELECT p.*, c.full_name AS customer_name, s.id AS sale_ref
        FROM payments p
        LEFT JOIN sales s ON p.sale_id = s.id
        LEFT JOIN customers c ON s.customer_id = c.id
        WHERE p.payment_type = 'down_payment'
        ORDER BY p.created_at DESC";
$items = $pdo->query($sql)->fetchAll();

require_once '../../includes/header.php';
?>

<div class="row">
  <div class="col-md-5">
    <div class="card shadow mb-4">
      <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-hand-holding-usd"></i> New Down Payment</h6>
      </div>
      <div class="card-body">
        <form method="post">
          <div class="form-group">
            <label class="form-label">Customer <span class="text-danger">*</span></label>
            <select name="customer_id" class="form-control" required>
              <option value="">Select Customer</option>
              <?php foreach ($customers as $c): ?>
                <option value="<?=$c['id']?>"><?=htmlspecialchars($c['full_name'] . ' (' . $c['phone'] . ')')?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="row">
            <div class="col-md-6 form-group">
              <label class="form-label">Amount <span class="text-danger">*</span></label>
              <input type="number" name="amount" class="form-control" step="0.01" min="0" required>
            </div>
            <div class="col-md-6 form-group">
              <label class="form-label">Date</label>
              <input type="date" name="payment_date" class="form-control" value="<?=date('Y-m-d')?>">
            </div>
          </div>
          <div class="row">
            <div class="col-md-6 form-group">
              <label class="form-label">Payment Method</label>
              <select name="payment_method" class="form-control">
                <option value="cash">Cash</option>
                <option value="card">Card</option>
                <option value="bank_transfer">Bank Transfer</option>
              </select>
            </div>
            <div class="col-md-6 form-group">
              <label class="form-label">Reference No.</label>
              <input type="text" name="reference_no" class="form-control" placeholder="Optional">
            </div>
          </div>
          <div class="form-group">
            <label class="form-label">Notes</label>
            <textarea name="notes" class="form-control" rows="2" placeholder="Optional notes"></textarea>
          </div>
          <button type="submit" class="btn btn-primary btn-block"><i class="fas fa-save"></i> Record Down Payment</button>
        </form>
      </div>
    </div>
  </div>

  <div class="col-md-7">
    <div class="card shadow mb-4">
      <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-list"></i> Down Payment History</h6>
      </div>
      <div class="card-body">
        <div class="table-responsive">
          <table class="table table-bordered table-hover">
            <thead class="thead-light">
              <tr>
                <th>Date</th>
                <th>Customer</th>
                <th class="text-right">Amount</th>
                <th>Method</th>
                <th>Notes</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($items)): ?>
                <tr><td colspan="5" class="text-center text-muted">No down payments recorded</td></tr>
              <?php else: foreach ($items as $p): ?>
                <tr>
                  <td><?=formatDate($p['payment_date'])?></td>
                  <td><?=htmlspecialchars($p['customer_name'] ?? 'N/A')?></td>
                  <td class="text-right font-weight-bold text-success"><?=formatCurrency($p['amount'])?></td>
                  <td><span class="badge badge-info"><?=ucfirst($p['payment_method'])?></span></td>
                  <td><?=htmlspecialchars($p['notes'] ?? '-')?></td>
                </tr>
              <?php endforeach; endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

<?php require_once '../../includes/footer.php'; ?>
