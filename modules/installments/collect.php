<?php
session_start();
$page_title = 'Collect Payment';
$base_url = '../../';
require_once '../../includes/functions.php';

$bank_accounts = getAll('bank_accounts', 'bank_name ASC, account_name ASC');

$installment_id = (int)($_GET['installment_id'] ?? 0);
$sale_id = (int)($_GET['sale_id'] ?? 0);

if (!$installment_id && !$sale_id) {
    redirect('schedules.php', 'No installment specified', 'error');
}

$installment = null;
$sale = null;
$customer = null;

if ($installment_id) {
    $installment = getById('sale_installments', $installment_id);
    if ($installment) $sale_id = $installment['sale_id'];
}

if ($sale_id) {
    $sale = getById('sales', $sale_id);
    if ($sale) $customer = getById('customers', $sale['customer_id']);
}

$remaining_installments = [];
if ($sale_id) {
    $remaining_installments = $pdo->prepare("SELECT * FROM sale_installments WHERE sale_id = ? AND status != 'paid' ORDER BY installment_no ASC");
    $remaining_installments->execute([$sale_id]);
    $remaining_installments = $remaining_installments->fetchAll();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pay_installment_id = (int)($_POST['installment_id'] ?? 0);
    $pay_amount = (float)($_POST['amount'] ?? 0);
    $payment_method = $_POST['payment_method'] ?? 'cash';
    $payment_date = $_POST['payment_date'] ?? date('Y-m-d');
    $reference_no = $_POST['reference_no'] ?? '';
    $notes = $_POST['notes'] ?? '';
    $bank_account_id = (int)($_POST['bank_account_id'] ?? 0);

    if ($pay_amount <= 0) {
        $_SESSION['error'] = 'Amount must be greater than 0.';
        header("Location: collect.php?installment_id=$pay_installment_id" . ($sale_id ? "&sale_id=$sale_id" : ""));
        exit;
    }

    if ($pay_installment_id) {
        $inst = getById('sale_installments', $pay_installment_id);
        if (!$inst) { $_SESSION['error'] = 'Installment not found.'; header("Location: schedules.php"); exit; }

        $new_paid = $inst['paid_amount'] + $pay_amount;
        $new_balance = $inst['amount'] - $new_paid;
        $new_status = $new_balance <= 0 ? 'paid' : ($new_paid > 0 ? 'partial' : $inst['status']);

        update('sale_installments', [
            'paid_amount' => $new_paid,
            'balance' => max(0, $new_balance),
            'status' => $new_status,
            'paid_date' => $new_status === 'paid' ? $payment_date : $inst['paid_date'],
            'updated_at' => date('Y-m-d'),
        ], $pay_installment_id);

        $payment_id = insert('payments', [
            'sale_id' => $inst['sale_id'],
            'installment_id' => $pay_installment_id,
            'payment_date' => $payment_date,
            'amount' => $pay_amount,
            'payment_type' => 'installment',
            'payment_method' => $payment_method,
            'reference_no' => $reference_no ?: null,
            'notes' => $notes,
            'branch_id' => $_SESSION['branch_id'] ?? null,
            'received_by' => $_SESSION['user_id'] ?? null,
            'created_at' => date('Y-m-d'),
        ]);

        if ($payment_method === 'cash') {
            recordCashInflow($pdo, $payment_date, $pay_amount, 'Installment - Sale #' . $inst['sale_id'], 'payment', $payment_id, $_SESSION['user_id'] ?? null);
        } elseif ($payment_method === 'bank') {
            recordBankInflow($pdo, $payment_date, $pay_amount, 'Installment (bank) - Sale #' . $inst['sale_id'], 'payment', $payment_id, $_SESSION['user_id'] ?? null, $bank_account_id);
        }

        $msg = "Payment of " . formatCurrency($pay_amount) . " recorded for installment #{$inst['installment_no']}.";
        redirect("schedules.php?sale_id={$inst['sale_id']}", $msg);
    } else {
        $_SESSION['error'] = 'Please select an installment.';
        header("Location: collect.php?sale_id=$sale_id");
        exit;
    }
}

require_once '../../includes/header.php';
?>

<div class="row">
  <div class="col-lg-5">
    <div class="card shadow mb-4">
      <div class="card-header py-3"><h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-user"></i> Sale Details</h6></div>
      <div class="card-body">
        <?php if ($sale && $customer): ?>
          <table class="table table-sm">
            <tr><td>Invoice</td><td><strong><?=htmlspecialchars($sale['invoice_no'])?></strong></td></tr>
            <tr><td>Customer</td><td><?=htmlspecialchars($customer['full_name'])?></td></tr>
            <tr><td>Phone</td><td><?=htmlspecialchars($customer['phone'])?></td></tr>
            <tr><td>Total Amount</td><td><strong><?=formatCurrency($sale['total_amount'])?></strong></td></tr>
            <tr><td>Down Payment</td><td><?=formatCurrency($sale['down_payment'])?></td></tr>
            <tr><td>Financed</td><td><strong><?=formatCurrency($sale['financed_amount'])?></strong></td></tr>
            <tr><td>Plan</td>
              <td><?php $plan = $sale['installment_plan_id'] ? getById('installment_plans', $sale['installment_plan_id']) : null; echo $plan ? htmlspecialchars($plan['name']) : 'N/A'; ?></td>
            </tr>
          </table>
        <?php else: ?>
          <p class="text-muted">Sale not found.</p>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <div class="col-lg-7">
    <div class="card shadow mb-4">
      <div class="card-header py-3"><h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-hand-holding-usd"></i> Record Payment</h6></div>
      <div class="card-body">
        <form method="post">
          <div class="form-group">
            <label class="form-label">Select Installment <span class="text-danger">*</span></label>
            <select name="installment_id" class="form-control" required>
              <option value="">Select installment</option>
              <?php foreach ($remaining_installments as $ri): ?>
                <option value="<?=$ri['id']?>" <?=$installment_id===$ri['id']?'selected':''?>>
                  #<?=$ri['installment_no']?> — Due: <?=formatDate($ri['due_date'])?> — Amount: <?=formatCurrency($ri['amount'])?> — Balance: <?=formatCurrency($ri['balance'])?>
                  <?=$ri['status']==='overdue'||$ri['status']==='late'?' (OVERDUE)':''?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="row">
            <div class="col-md-4 form-group">
              <label class="form-label">Amount <span class="text-danger">*</span></label>
              <input type="number" name="amount" class="form-control" step="0.01" min="0.01" required>
            </div>
            <div class="col-md-4 form-group">
              <label class="form-label">Payment Date</label>
              <input type="text" name="payment_date" class="form-control datepicker" value="<?=date('Y-m-d')?>" autocomplete="off">
            </div>
            <div class="col-md-4 form-group">
              <label class="form-label">Method</label>
              <select name="payment_method" class="form-control payment-method-select">
                <option value="cash">Cash</option>
                <option value="bank">Bank</option>
              </select>
            </div>
          </div>
          <div class="row bank-account-row" style="display:none;">
            <div class="col-md-12 form-group">
              <label class="form-label">Bank Account</label>
              <select name="bank_account_id" class="form-control">
                <option value="">Select Account</option>
                <?php foreach ($bank_accounts as $ba): ?>
                  <option value="<?= $ba['id'] ?>"><?= htmlspecialchars($ba['bank_name'] . ' - ' . $ba['account_name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
          <div class="row">
            <div class="col-md-6 form-group">
              <label class="form-label">Reference No.</label>
              <input type="text" name="reference_no" class="form-control" placeholder="Cheque/Transaction #">
            </div>
            <div class="col-md-6 form-group">
              <label class="form-label">Notes</label>
              <input type="text" name="notes" class="form-control">
            </div>
          </div>
          <button type="submit" class="btn btn-success btn-block py-2"><i class="fas fa-check-circle"></i> Record Payment</button>
          <a href="schedules.php" class="btn btn-secondary btn-block">Cancel</a>
        </form>
      </div>
    </div>
  </div>
</div>

<div class="card shadow mb-4">
  <div class="card-header py-3"><h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-history"></i> Installment Schedule</h6></div>
  <div class="card-body">
    <div class="table-responsive">
      <table class="table table-bordered table-hover">
        <thead class="thead-light">
          <tr><th>#</th><th>Due Date</th><th class="text-right">Amount</th><th class="text-right">Paid</th><th class="text-right">Balance</th><th>Status</th><th>Paid Date</th></tr>
        </thead>
        <tbody>
          <?php
          if ($sale_id) {
              $all_inst = $pdo->prepare("SELECT * FROM sale_installments WHERE sale_id = ? ORDER BY installment_no ASC");
              $all_inst->execute([$sale_id]);
              $rows = $all_inst->fetchAll();
              if (empty($rows)): ?>
                <tr><td colspan="7" class="text-center text-muted">No installments generated yet</td></tr>
              <?php else: foreach ($rows as $r): ?>
                <tr class="<?=$r['status']==='overdue'||$r['status']==='late'?'table-danger':''?>">
                  <td><?=$r['installment_no']?></td>
                  <td><?=formatDate($r['due_date'])?></td>
                  <td class="text-right"><?=formatCurrency($r['amount'])?></td>
                  <td class="text-right"><?=formatCurrency($r['paid_amount'])?></td>
                  <td class="text-right"><?=formatCurrency($r['balance'])?></td>
                  <td><span class="badge badge-<?=match($r['status']){'paid'=>'success','partial'=>'info','overdue'=>'danger','late'=>'warning',default=>'secondary'}?>"><?=ucfirst($r['status'])?></span></td>
                  <td><?=$r['paid_date']?formatDate($r['paid_date']):'-'?></td>
                </tr>
              <?php endforeach; endif;
          } ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<script>
$(document).on('change', '.payment-method-select', function() {
    $(this).closest('form').find('.bank-account-row').toggle($(this).val() === 'bank');
});
</script>
<?php require_once '../../includes/footer.php'; ?>
