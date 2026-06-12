<?php
session_start();
$page_title = 'Late Payments';
$base_url = '../../';
require_once '../../includes/functions.php';

$is_print = isset($_GET['print']);
$period = $_GET['period'] ?? 'all';

$sql = "SELECT si.*, s.invoice_no, s.total_amount, s.customer_id, s.sale_date,
        c.full_name AS customer_name, c.phone AS customer_phone,
        ip.name AS plan_name, DATEDIFF(CURDATE(), si.due_date) AS days_overdue
        FROM sale_installments si
        JOIN sales s ON si.sale_id = s.id
        JOIN customers c ON s.customer_id = c.id
        LEFT JOIN installment_plans ip ON s.installment_plan_id = ip.id
        WHERE si.status IN ('overdue', 'late', 'pending') AND si.due_date < CURDATE()";
$params = [];

if ($period === '7') { $sql .= " AND si.due_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)"; }
elseif ($period === '30') { $sql .= " AND si.due_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)"; }
elseif ($period === '60') { $sql .= " AND si.due_date >= DATE_SUB(CURDATE(), INTERVAL 60 DAY)"; }

$sql .= " ORDER BY si.due_date ASC";
$stmt = $pdo->prepare($sql); $stmt->execute($params);
$items = $stmt->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['apply_late_fee'])) {
    $installment_id = (int)$_POST['installment_id'];
    $late_fee = (float)($_POST['late_fee'] ?? 0);
    if ($late_fee > 0) {
        $inst = getById('sale_installments', $installment_id);
        if ($inst) {
            $new_balance = $inst['balance'] + $late_fee;
            update('sale_installments', [
                'late_fee' => $inst['late_fee'] + $late_fee,
                'balance' => $new_balance,
                'status' => 'late',
                'updated_at' => date('Y-m-d'),
            ], $installment_id);
            redirect('late_payments.php', "Late fee of " . formatCurrency($late_fee) . " applied");
        }
    }
}

if ($is_print) {
    $total_overdue = 0; $total_late_fees = 0;
    foreach ($items as $i) { $total_overdue += $i['balance']; $total_late_fees += $i['late_fee']; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Late Payments Report</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/4.6.2/css/bootstrap.min.css">
<style>
  @media print {
    @page { size: A4 landscape; margin: 8mm; }
    body{ background:#fff; font-size:10px; }
    .no-print { display:none !important; }
    .wrap { box-shadow:none !important; border:1px solid #000 !important; max-width:100%; margin:0; }
  }
  body { background: #f1f5f9; font-family: 'Segoe UI', Arial, sans-serif; font-size:12px; }
  .wrap { max-width: 1100px; margin: 20px auto; background: #fff; border: 2px solid #1f2937; border-radius: 4px; padding: 0; box-shadow: 0 4px 24px rgba(0,0,0,.08); }
  .r-header { display:flex; align-items:center; gap:14px; padding: 14px 18px 8px 18px; border-bottom: 2px solid #1f2937; }
  .r-logo { width:60px; height:60px; border:3px solid #4b5563; border-radius:50%; display:flex; align-items:center; justify-content:center; font-weight:bold; font-size:1.4rem; color:#374151; flex-shrink:0; }
  .r-company-name { font-size:1.6rem; font-weight:700; margin:0; color:#1f2937; }
  .r-company-sub { font-size:.85rem; font-weight:600; letter-spacing:.03em; margin:0; color:#1f2937; }
  .r-company-contact { font-size:.7rem; color:#374151; margin:0; }
  .r-section-title { font-size:.95rem; font-weight:700; padding: 8px 18px; border-bottom: 1px solid #1f2937; text-decoration: underline; text-underline-offset: 3px; }
  table { width: 100%; border-collapse: collapse; }
  th, td { border: 1px solid #1f2937; padding: 4px 6px; text-align: center; font-size:11px; }
  th { background: #f8fafc; color: #475569; font-weight:700; }
  td.text-right { text-align: right; }
  td.text-left { text-align: left; }
  .r-software { text-align:left; font-size:.7rem; color:#9ca3af; padding: 6px 18px 14px 18px; }
</style>
</head>
<body>

<div class="no-print text-center mt-3 mb-3">
  <button class="btn btn-primary" onclick="window.print()"><i class="fas fa-download"></i> Print / PDF</button>
  <button class="btn btn-secondary" onclick="window.close()"><i class="fas fa-times"></i> Close</button>
</div>

<div class="wrap">
  <div class="r-header">
    <div class="r-logo">SHT</div>
    <div>
      <p class="r-company-name">Saim Hasnain Traders</p>
      <p class="r-company-sub">CHAK NUM 14/8AR Talambah Road Mia Chanu</p>
      <p class="r-company-contact">Phone: Mahar Falak 03030344214 / Mahar Shahid 03346881214</p>
    </div>
  </div>

  <div class="r-section-title">Late Payments Report</div>

  <div style="padding:8px 18px;">
    <table>
      <thead>
        <tr>
          <th>Invoice</th>
          <th class="text-left">Customer</th>
          <th>Phone</th>
          <th>Plan</th>
          <th>#</th>
          <th>Due Date</th>
          <th>Amount</th>
          <th>Balance</th>
          <th>Days Overdue</th>
          <th>Late Fee</th>
          <th>Status</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($items as $si): ?>
          <tr>
            <td><?=htmlspecialchars($si['invoice_no'])?></td>
            <td class="text-left"><?=htmlspecialchars($si['customer_name'])?></td>
            <td><?=htmlspecialchars($si['customer_phone'])?></td>
            <td><?=htmlspecialchars($si['plan_name']??'-')?></td>
            <td><?=$si['installment_no']?></td>
            <td><?=formatDate($si['due_date'])?></td>
            <td class="text-right"><?=formatCurrency($si['amount'])?></td>
            <td class="text-right" style="font-weight:700;color:#dc2626;"><?=formatCurrency($si['balance'])?></td>
            <td><?=$si['days_overdue']?> days</td>
            <td class="text-right"><?=formatCurrency($si['late_fee'])?></td>
            <td><span class="badge badge-<?=$si['status']==='late'?'warning':'danger'?>"><?=ucfirst($si['status'])?></span></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
      <tfoot>
        <tr style="background:#f8f9fc;font-weight:bold;">
          <td colspan="6" class="text-right">Totals</td>
          <td class="text-right"><?=formatCurrency(array_sum(array_column($items, 'amount')))?></td>
          <td class="text-right" style="color:#dc2626;"><?=formatCurrency($total_overdue)?></td>
          <td></td>
          <td class="text-right"><?=formatCurrency($total_late_fees)?></td>
          <td></td>
        </tr>
      </tfoot>
    </table>
  </div>

  <div class="r-software">[Software By @ ATR]</div>
</div>

<script>window.print()</script>
</body>
</html>
<?php
    exit;
}

require_once '../../includes/header.php';
?>

<style>
  .overdue-highlight { background: #fff5f5; border-left: 4px solid #e74a3b; }
  .summary-card { border-radius: 10px; padding: 20px; text-align: center; }
  .summary-card .num { font-size: 2rem; font-weight: 700; }
</style>

<?php
$total_overdue = 0; $total_late_fees = 0;
foreach ($items as $i) {
    $total_overdue += $i['balance'];
    $total_late_fees += $i['late_fee'];
}
?>

<div class="row mb-4">
  <div class="col-md-3">
    <div class="summary-card bg-danger text-white">
      <div class="num"><?=count($items)?></div>
      <div>Overdue / Late Payments</div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="summary-card bg-warning text-white">
      <div class="num"><?=formatCurrency($total_overdue)?></div>
      <div>Total Outstanding</div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="summary-card bg-info text-white">
      <div class="num"><?=formatCurrency($total_late_fees)?></div>
      <div>Total Late Fees</div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="summary-card bg-secondary text-white">
      <div class="num">
        <?php
        $oldest = !empty($items) ? max(array_column($items, 'days_overdue')) : 0;
        echo $oldest . ' days';
        ?>
      </div>
      <div>Max Days Overdue</div>
    </div>
  </div>
</div>

<div class="card shadow mb-4">
  <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
    <h6 class="m-0 font-weight-bold text-danger"><i class="fas fa-exclamation-triangle"></i> Late / Overdue Payments</h6>
    <div>
      <a href="late_payments.php?period=7" class="btn btn-sm btn-outline-danger <?=$period==='7'?'active':''?>">7 Days</a>
      <a href="late_payments.php?period=30" class="btn btn-sm btn-outline-danger <?=$period==='30'?'active':''?>">30 Days</a>
      <a href="late_payments.php?period=60" class="btn btn-sm btn-outline-danger <?=$period==='60'?'active':''?>">60 Days</a>
      <a href="late_payments.php" class="btn btn-sm btn-outline-danger <?=$period==='all'?'active':''?>">All</a>
      <a href="late_payments.php?print=1<?= $period !== 'all' ? '&period='.$period : '' ?>" class="btn btn-sm btn-success" target="_blank"><i class="fas fa-print"></i></a>
    </div>
  </div>
  <div class="card-body">
    <?php if (empty($items)): ?>
      <div class="text-center text-success py-4">
        <i class="fas fa-check-circle fa-3x mb-3"></i>
        <p class="font-weight-bold">No overdue payments!</p>
        <p class="text-muted">All installment payments are on time.</p>
      </div>
    <?php else: ?>
      <div class="table-responsive">
        <table class="table table-bordered table-hover">
          <thead class="thead-light">
            <tr>
              <th>Invoice</th><th>Customer</th><th>Plan</th><th>#</th>
              <th>Due Date</th><th class="text-right">Amount</th><th class="text-right">Balance</th>
              <th>Days Overdue</th><th>Late Fee</th><th>Status</th><th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($items as $si): ?>
              <tr class="<?=$si['days_overdue']>30?'table-danger':'table-warning'?>">
                <td><a href="../sales/invoice.php?id=<?=$si['sale_id']?>"><strong><?=htmlspecialchars($si['invoice_no'])?></strong></a></td>
                <td><?=htmlspecialchars($si['customer_name'])?><br><small class="text-muted"><?=htmlspecialchars($si['customer_phone'])?></small></td>
                <td><?=htmlspecialchars($si['plan_name']??'-')?></td>
                <td class="text-center"><?=$si['installment_no']?></td>
                <td><?=formatDate($si['due_date'])?></td>
                <td class="text-right"><?=formatCurrency($si['amount'])?></td>
                <td class="text-right font-weight-bold text-danger"><?=formatCurrency($si['balance'])?></td>
                <td class="text-center"><span class="badge badge-<?=$si['days_overdue']>30?'danger':'warning'?>"><?=$si['days_overdue']?> days</span></td>
                <td class="text-right"><?=formatCurrency($si['late_fee'])?></td>
                <td><span class="badge badge-<?=$si['status']==='late'?'warning':'danger'?>"><?=ucfirst($si['status'])?></span></td>
                <td>
                  <a href="collect.php?installment_id=<?=$si['id']?>" class="btn btn-sm btn-success mb-1"><i class="fas fa-hand-holding-usd"></i> Collect</a>
                  <button type="button" class="btn btn-sm btn-warning mb-1" onclick="$('#lateFeeModal<?=$si['id']?>').modal('show')"><i class="fas fa-percent"></i> Late Fee</button>

                  <div class="modal fade" id="lateFeeModal<?=$si['id']?>">
                    <div class="modal-dialog modal-sm">
                      <div class="modal-content">
                        <form method="post">
                          <div class="modal-header"><h6 class="modal-title">Apply Late Fee</h6><button type="button" class="close" data-dismiss="modal">&times;</button></div>
                          <div class="modal-body">
                            <input type="hidden" name="installment_id" value="<?=$si['id']?>">
                            <div class="form-group">
                              <label>Current Late Fee: <?=formatCurrency($si['late_fee'])?></label>
                              <input type="number" name="late_fee" class="form-control" step="0.01" min="0" placeholder="Enter late fee amount" required>
                            </div>
                          </div>
                          <div class="modal-footer">
                            <button type="submit" name="apply_late_fee" class="btn btn-warning">Apply Fee</button>
                            <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                          </div>
                        </form>
                      </div>
                    </div>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>

<?php require_once '../../includes/footer.php'; ?>
