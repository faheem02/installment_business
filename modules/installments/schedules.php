<?php
session_start();
$page_title = 'Installment Schedules';
$base_url = '../../';
require_once '../../includes/functions.php';

$status = $_GET['status'] ?? '';
$customer_id = (int)($_GET['customer_id'] ?? 0);
$from_date = $_GET['from_date'] ?? '';
$to_date = $_GET['to_date'] ?? '';

$sql = "SELECT si.*, s.invoice_no, s.total_amount, s.customer_id, s.sale_date,
        c.full_name AS customer_name, c.phone AS customer_phone,
        ip.name AS plan_name
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
                <?php if ($si['status'] !== 'paid'): ?>
                  <a href="collect.php?installment_id=<?=$si['id']?>&sale_id=<?=$si['sale_id']?>" class="btn btn-sm btn-success"><i class="fas fa-hand-holding-usd"></i> Collect</a>
                <?php else: ?>
                  <button class="btn btn-sm btn-success" disabled><i class="fas fa-check"></i> Paid</button>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php require_once '../../includes/footer.php'; ?>
