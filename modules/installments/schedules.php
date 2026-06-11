<?php
session_start();
$page_title = 'Installment Schedules';
$base_url = '../../';
require_once '../../includes/functions.php';

// Handle payment collection inline
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['collect_payment'])) {
    $inst_id = (int)($_POST['inst_id'] ?? 0);
    $pay_amount = (float)($_POST['amount'] ?? 0);
    $payment_method = $_POST['payment_method'] ?? 'cash';
    $payment_date = $_POST['payment_date'] ?? date('Y-m-d');
    $reference_no = $_POST['reference_no'] ?? '';
    $notes = $_POST['notes'] ?? '';
    $bank_account_id = (int)($_POST['bank_account_id'] ?? 0);

    if ($pay_amount > 0 && $inst_id) {
        $inst = getById('sale_installments', $inst_id);
        if ($inst) {
            $new_paid = $inst['paid_amount'] + $pay_amount;
            $new_balance = $inst['amount'] - $new_paid;
            $new_status = $new_balance <= 0 ? 'paid' : ($new_paid > 0 ? 'partial' : $inst['status']);

            update('sale_installments', [
                'paid_amount' => $new_paid,
                'balance' => max(0, $new_balance),
                'status' => $new_status,
                'paid_date' => $new_status === 'paid' ? $payment_date : $inst['paid_date'],
                'updated_at' => date('Y-m-d'),
            ], $inst_id);

            $payment_id = insert('payments', [
                'sale_id' => $inst['sale_id'],
                'installment_id' => $inst_id,
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

            $_SESSION['success'] = 'Payment of ' . formatCurrency($pay_amount) . ' recorded';
        }
    } else {
        $_SESSION['error'] = 'Invalid amount or installment';
    }
    header("Location: schedules.php?" . ($_SERVER['QUERY_STRING'] ?? ''));
    exit;
}

$status = $_GET['status'] ?? '';
$customer_id = (int)($_GET['customer_id'] ?? 0);
$from_date = $_GET['from_date'] ?? '';
$to_date = $_GET['to_date'] ?? '';

$sql = "SELECT si.*, s.invoice_no, s.total_amount, s.customer_id, s.sale_date,
        c.full_name AS customer_name, c.phone AS customer_phone,
        ip.name AS plan_name,
        (SELECT p.id FROM payments p WHERE p.installment_id = si.id ORDER BY p.id DESC LIMIT 1) AS payment_id
        FROM sale_installments si
        JOIN sales s ON si.sale_id = s.id
        JOIN customers c ON s.customer_id = c.id
        LEFT JOIN installment_plans ip ON s.installment_plan_id = ip.id
        WHERE 1=1";
$params = [];
if ($status) { $sql .= " AND si.status = ?"; $params[] = $status; }
if ($customer_id) { $sql .= " AND s.customer_id = ?"; $params[] = $customer_id; }
if ($from_date) { $sql .= " AND si.due_date >= ?"; $params[] = $from_date; }
if ($to_date) { $sql .= " AND si.due_date <= ?"; $params[] = $to_date; }
$sql .= " ORDER BY si.due_date ASC, si.status ASC";
$stmt = $pdo->prepare($sql); $stmt->execute($params);
$items = $stmt->fetchAll();

$customers = getAll('customers', 'full_name ASC');
$bank_accounts = getAll('bank_accounts', 'bank_name ASC, account_name ASC');

require_once '../../includes/header.php';
?>

<style>
  .progress-thin { height: 6px; }
  .summary-value { font-size: 1.5rem; font-weight: 700; color: #0f172a; }
  .summary-label { font-size: 0.75rem; color: #858796; text-transform: uppercase; letter-spacing: 0.5px; }
</style>

<?php
$total_due = 0; $total_paid = 0; $total_balance = 0; $overdue_count = 0;
foreach ($items as $i) {
    $total_due += $i['amount'];
    $total_paid += $i['paid_amount'];
    $total_balance += $i['balance'];
    if ($i['status'] === 'overdue' || $i['status'] === 'late') $overdue_count++;
}
?>

<div class="row mb-4">
  <div class="col-xl-3 col-md-6 mb-3">
    <div class="card border-left-primary shadow h-100 py-2">
      <div class="card-body"><div class="row no-gutters align-items-center">
        <div class="col mr-2"><div class="summary-label">Total Due</div><div class="summary-value"><?=formatCurrency($total_due)?></div></div>
        <div class="col-auto"><i class="fas fa-calendar fa-2x text-gray-300"></i></div>
      </div></div>
    </div>
  </div>
  <div class="col-xl-3 col-md-6 mb-3">
    <div class="card border-left-success shadow h-100 py-2">
      <div class="card-body"><div class="row no-gutters align-items-center">
        <div class="col mr-2"><div class="summary-label">Total Collected</div><div class="summary-value text-success"><?=formatCurrency($total_paid)?></div></div>
        <div class="col-auto"><i class="fas fa-check-circle fa-2x text-gray-300"></i></div>
      </div></div>
    </div>
  </div>
  <div class="col-xl-3 col-md-6 mb-3">
    <div class="card border-left-warning shadow h-100 py-2">
      <div class="card-body"><div class="row no-gutters align-items-center">
        <div class="col mr-2"><div class="summary-label">Remaining Balance</div><div class="summary-value text-warning"><?=formatCurrency($total_balance)?></div></div>
        <div class="col-auto"><i class="fas fa-hourglass-half fa-2x text-gray-300"></i></div>
      </div></div>
    </div>
  </div>
  <div class="col-xl-3 col-md-6 mb-3">
    <div class="card border-left-danger shadow h-100 py-2">
      <div class="card-body"><div class="row no-gutters align-items-center">
        <div class="col mr-2"><div class="summary-label">Overdue / Late</div><div class="summary-value text-danger"><?=$overdue_count?></div></div>
        <div class="col-auto"><i class="fas fa-exclamation-triangle fa-2x text-gray-300"></i></div>
      </div></div>
    </div>
  </div>
</div>

<div class="card shadow mb-4">
  <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
    <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-list"></i> Payment Schedules</h6>
    <a href="late_payments.php" class="btn btn-danger btn-sm"><i class="fas fa-exclamation-triangle"></i> Late Payments</a>
  </div>
  <div class="card-body">
    <form method="get" class="mb-3">
      <div class="row">
        <div class="col-md-3">
          <select name="status" class="form-control">
            <option value="">All Status</option>
            <option value="pending" <?=$status==='pending'?'selected':''?>>Pending</option>
            <option value="paid" <?=$status==='paid'?'selected':''?>>Paid</option>
            <option value="partial" <?=$status==='partial'?'selected':''?>>Partial</option>
            <option value="overdue" <?=$status==='overdue'?'selected':''?>>Overdue</option>
            <option value="late" <?=$status==='late'?'selected':''?>>Late</option>
          </select>
        </div>
        <div class="col-md-3">
          <select name="customer_id" class="form-control">
            <option value="">All Customers</option>
            <?php foreach ($customers as $c): ?>
              <option value="<?=$c['id']?>" <?=$customer_id===$c['id']?'selected':''?>><?=htmlspecialchars($c['full_name'])?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-2">
          <input type="date" name="from_date" class="form-control" placeholder="From date" value="<?=htmlspecialchars($from_date)?>">
        </div>
        <div class="col-md-2">
          <input type="date" name="to_date" class="form-control" placeholder="To date" value="<?=htmlspecialchars($to_date)?>">
        </div>
        <div class="col-md-2 d-flex align-items-center">
          <button type="submit" class="btn btn-sm btn-primary mr-1"><i class="fas fa-filter"></i> Filter</button>
          <a href="schedules.php" class="btn btn-sm btn-secondary"><i class="fas fa-undo"></i></a>
        </div>
      </div>
    </form>

    <div class="table-responsive">
      <table class="table table-bordered table-hover" id="dataTable">
        <thead class="thead-light">
          <tr>
            <th>Invoice</th><th>Customer</th><th>Plan</th><th>#</th><th>Due Date</th>
            <th class="text-right">Amount</th><th class="text-right">Paid</th><th class="text-right">Balance</th>
            <th>Status</th><th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($items)): ?>
            <tr><td colspan="10" class="text-center text-muted">No installment schedules found</td></tr>
          <?php else: foreach ($items as $si): ?>
            <?php
            $badge = match($si['status']) {
                'paid' => 'success',
                'partial' => 'info',
                'overdue' => 'danger',
                'late' => 'warning',
                default => 'secondary'
            };
            $progress = $si['amount'] > 0 ? min(100, ($si['paid_amount'] / $si['amount']) * 100) : 0;
            ?>
            <tr class="<?=$si['status']==='overdue'||$si['status']==='late'?'table-danger':''?>">
              <td><a href="../sales/invoice.php?id=<?=$si['sale_id']?>"><strong><?=htmlspecialchars($si['invoice_no'])?></strong></a></td>
              <td><?=htmlspecialchars($si['customer_name'])?><br><small class="text-muted"><?=htmlspecialchars($si['customer_phone'])?></small></td>
              <td><?=htmlspecialchars($si['plan_name']??'-')?></td>
              <td class="text-center"><?=$si['installment_no']?></td>
              <td><?=formatDate($si['due_date'])?></td>
              <td class="text-right"><?=formatCurrency($si['amount'])?></td>
              <td class="text-right"><?=formatCurrency($si['paid_amount'])?></td>
              <td class="text-right"><?=formatCurrency($si['balance'])?></td>
              <td>
                <span class="badge badge-<?=$badge?>"><?=ucfirst($si['status'])?></span>
                <?php if ($si['late_fee'] > 0): ?>
                  <br><small class="text-danger">Late fee: <?=formatCurrency($si['late_fee'])?></small>
                <?php endif; ?>
                <div class="progress progress-thin mt-1">
                  <div class="progress-bar bg-<?=$badge?>" style="width:<?=$progress?>%"></div>
                </div>
              </td>
              <td>
                  <?php if ($si['payment_id']): ?>
                    <a href="<?=$base_url?>modules/payments/receipt.php?id=<?=$si['payment_id']?>" class="btn btn-sm btn-info" title="View Receipt"><i class="fas fa-eye"></i></a>
                    <a href="<?=$base_url?>modules/payments/receipt.php?id=<?=$si['payment_id']?>&print=1" class="btn btn-sm btn-secondary" title="Print Receipt" target="_blank"><i class="fas fa-print"></i></a>
                  <?php endif; ?>
                  <?php if ($si['status'] !== 'paid'): ?>
                    <button class="btn btn-sm btn-success" onclick="openPayment(<?=$si['id']?>, '<?=htmlspecialchars($si['invoice_no'])?>', '<?=htmlspecialchars($si['customer_name'])?>', <?=$si['installment_no']?>, <?=$si['amount']?>, <?=$si['paid_amount']?>)"><i class="fas fa-hand-holding-usd"></i> Collect</button>
                  <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Payment Modal -->
<div class="modal fade" id="paymentModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="post">
        <div class="modal-header">
          <h6 class="modal-title"><i class="fas fa-hand-holding-usd text-success"></i> Collect Payment</h6>
          <button type="button" class="close" data-dismiss="modal">&times;</button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="inst_id" id="instId">
          <div class="bg-light rounded p-3 mb-3">
            <div class="d-flex justify-content-between"><span class="text-muted">Invoice</span><strong id="modalInvoice">-</strong></div>
            <div class="d-flex justify-content-between"><span class="text-muted">Customer</span><strong id="modalCustomer">-</strong></div>
            <div class="d-flex justify-content-between"><span class="text-muted">Installment #</span><strong id="modalInstNo">-</strong></div>
            <div class="d-flex justify-content-between"><span class="text-muted">Amount Due</span><strong id="modalDue">-</strong></div>
            <div class="d-flex justify-content-between"><span class="text-muted">Already Paid</span><strong id="modalPaid">-</strong></div>
            <hr class="my-2">
            <div class="d-flex justify-content-between"><span class="h5 mb-0">Balance</span><span class="h5 mb-0 font-weight-bold text-success" id="modalBalance">-</span></div>
          </div>
          <div class="row">
            <div class="col-6">
              <div class="form-group mb-2">
                <label class="small text-muted">Payment Date</label>
                <input type="date" name="payment_date" class="form-control" value="<?=date('Y-m-d')?>" required>
              </div>
            </div>
            <div class="col-6">
              <div class="form-group mb-2">
                <label class="small text-muted">Method</label>
                <select name="payment_method" class="form-control payment-method-select" required>
                  <option value="cash">Cash</option>
                  <option value="bank">Bank</option>
                </select>
              </div>
            </div>
            <div class="bank-account-row" style="display:none;">
              <div class="col-12">
                <div class="form-group mb-2">
                  <label class="small text-muted">Bank Account</label>
                  <select name="bank_account_id" class="form-control">
                    <option value="">Select Account</option>
                    <?php foreach ($bank_accounts as $ba): ?>
                      <option value="<?= $ba['id'] ?>"><?= htmlspecialchars($ba['bank_name'] . ' - ' . $ba['account_name']) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
              </div>
            </div>
          </div>
          <div class="form-group mb-2">
            <label class="small text-muted">Payment Amount</label>
            <input type="number" name="amount" id="payAmount" class="form-control form-control-lg font-weight-bold" step="0.01" min="0.01" required>
          </div>
          <div class="row">
            <div class="col-6">
              <div class="form-group mb-2">
                <label class="small text-muted">Reference No.</label>
                <input type="text" name="reference_no" class="form-control" placeholder="Optional">
              </div>
            </div>
            <div class="col-6">
              <div class="form-group mb-2">
                <label class="small text-muted">Notes</label>
                <textarea name="notes" class="form-control" rows="1" placeholder="Optional"></textarea>
              </div>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
          <button type="submit" name="collect_payment" class="btn btn-success"><i class="fas fa-save"></i> Record Payment</button>
        </div>
      </form>
    </div>
  </div>
</div>


<script>
$(document).on('change', '.payment-method-select', function() {
    $(this).closest('.modal-body').find('.bank-account-row').toggle($(this).val() === 'bank');
});

function openPayment(id, invoice, customer, instNo, amount, paid) {
  document.getElementById('instId').value = id;
  document.getElementById('modalInvoice').textContent = invoice;
  document.getElementById('modalCustomer').textContent = customer;
  document.getElementById('modalInstNo').textContent = '#' + instNo;
  document.getElementById('modalDue').textContent = 'PKR ' + parseFloat(amount).toLocaleString('en-US', {minimumFractionDigits:2});
  document.getElementById('modalPaid').textContent = 'PKR ' + parseFloat(paid).toLocaleString('en-US', {minimumFractionDigits:2});
  var bal = amount - paid;
  document.getElementById('modalBalance').textContent = 'PKR ' + bal.toLocaleString('en-US', {minimumFractionDigits:2});
  document.getElementById('payAmount').value = bal > 0 ? bal.toFixed(2) : '';
  document.getElementById('payAmount').max = amount;
  $('#paymentModal').modal('show');
}
</script>

<?php require_once '../../includes/footer.php'; ?>
