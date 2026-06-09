<?php
session_start();
$id = (int)($_GET['id'] ?? 0);
$is_print = isset($_GET['print']);
require_once '../../includes/functions.php';

$stmt = $pdo->prepare("
    SELECT p.*, s.invoice_no, s.sale_date, s.total_amount, s.down_payment, s.financed_amount,
           s.total_installments, s.payment_status AS sale_status,
           c.full_name AS customer_name, c.phone AS customer_phone, c.address AS customer_address,
           c.cnic AS customer_cnic,
           u.full_name AS received_by_name,
           b.name AS branch_name,
           si.installment_no, si.due_date, si.amount AS inst_amount, si.balance AS inst_balance,
           si.status AS inst_status, si.late_fee
    FROM payments p
    LEFT JOIN sales s ON p.sale_id = s.id
    LEFT JOIN customers c ON s.customer_id = c.id
    LEFT JOIN users u ON p.received_by = u.id
    LEFT JOIN branches b ON p.branch_id = b.id
    LEFT JOIN sale_installments si ON p.installment_id = si.id
    WHERE p.id = ?
");
$stmt->execute([$id]);
$payment = $stmt->fetch();

if (!$payment) {
    $_SESSION['error'] = 'Receipt not found.';
    header("Location: ../../modules/installments/schedules.php");
    exit;
}

$receipt_no = 'RCT-' . str_pad($payment['id'], 5, '0', STR_PAD_LEFT);
$title = 'Receipt ' . $receipt_no;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title><?=$title?></title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/4.6.2/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
<style>
  @media print{body{font-size:12px}.no-print{display:none!important}}
  body{background:#f1f5f9;font-family:'Segoe UI',sans-serif}
  .wrap{max-width:700px;margin:30px auto;background:#fff;border-radius:12px;padding:35px;box-shadow:0 4px 24px rgba(0,0,0,.08)}
  .receipt-header{border-bottom:2px dashed #e2e8f0;padding-bottom:20px;margin-bottom:20px}
  .receipt-footer{border-top:2px dashed #e2e8f0;padding-top:20px;margin-top:20px}
  .amount-box{background:#f8fafc;border-radius:8px;padding:15px;text-align:center;border:2px solid #e2e8f0}
  .amount-box .amount{font-size:2rem;font-weight:bold;color:#0f172a}
  table.details{width:100%}
  table.details td{padding:6px 10px}
  table.details td:first-child{color:#64748b;width:40%}
  table.details td:last-child{font-weight:600;width:60%}
</style>
</head>
<body>

<div class="no-print text-center mt-3 mb-3">
  <button class="btn btn-primary" onclick="window.print()"><i class="fas fa-print"></i> Print</button>
  <a href="../../modules/installments/schedules.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Back to Schedules</a>
</div>

<div class="wrap">

  <div class="receipt-header">
    <div class="d-flex justify-content-between align-items-start">
      <div>
        <h4 class="font-weight-bold mb-1" style="color:#0f172a;">Installment Business</h4>
        <p class="text-muted mb-0">POS System</p>
      </div>
      <div class="text-right">
        <h4 class="font-weight-bold mb-1" style="color:#0f172a;">RECEIPT</h4>
        <p class="mb-0"><strong><?= $receipt_no ?></strong></p>
        <p class="mb-0 text-muted"><?= formatDate($payment['payment_date']) ?></p>
      </div>
    </div>
  </div>

  <div class="row mb-4">
    <div class="col-sm-6">
      <h6 class="font-weight-bold text-uppercase" style="color:#0f172a;font-size:.8rem;">Customer</h6>
      <p class="mb-1 font-weight-bold"><?= htmlspecialchars($payment['customer_name'] ?? 'N/A') ?></p>
      <p class="mb-1 text-muted"><?= htmlspecialchars($payment['customer_phone'] ?? '') ?></p>
      <p class="mb-0 text-muted"><?= nl2br(htmlspecialchars($payment['customer_address'] ?? '')) ?></p>
    </div>
    <div class="col-sm-6 text-sm-right">
      <h6 class="font-weight-bold text-uppercase" style="color:#0f172a;font-size:.8rem;">Invoice</h6>
      <p class="mb-1">#<?= htmlspecialchars($payment['invoice_no'] ?? 'N/A') ?></p>
      <p class="mb-0 text-muted"><?= $payment['sale_date'] ? formatDate($payment['sale_date']) : '-' ?></p>
    </div>
  </div>

  <div class="amount-box mb-4">
    <p class="text-muted mb-1">Amount Received</p>
    <div class="amount"><?= formatCurrency($payment['amount']) ?></div>
    <p class="mb-0 mt-2">
      <span class="badge badge-<?= $payment['payment_method'] === 'cash' ? 'success' : 'info' ?> px-3 py-1">
        <?= $payment['payment_method'] === 'cash' ? 'Cash' : 'Bank' ?>
      </span>
      <span class="badge badge-secondary px-3 py-1 ml-1">
        <?= ucfirst(str_replace('_', ' ', $payment['payment_type'])) ?>
      </span>
    </p>
  </div>

  <table class="details">
    <?php if ($payment['reference_no']): ?>
      <tr><td>Reference No.</td><td><?= htmlspecialchars($payment['reference_no']) ?></td></tr>
    <?php endif; ?>
    <?php if ($payment['installment_id']): ?>
      <tr><td>Installment</td><td>#<?= $payment['installment_no'] ?> of <?= $payment['total_installments'] ?: '-' ?></td></tr>
      <tr><td>Due Date</td><td><?= formatDate($payment['due_date']) ?></td></tr>
      <tr><td>Installment Amount</td><td><?= formatCurrency($payment['inst_amount']) ?></td></tr>
      <?php if (($payment['late_fee'] ?? 0) > 0): ?>
        <tr><td>Late Fee</td><td class="text-danger">+ <?= formatCurrency($payment['late_fee']) ?></td></tr>
      <?php endif; ?>
      <tr><td>Balance After Payment</td><td><?= formatCurrency($payment['inst_balance']) ?></td></tr>
      <tr><td>Installment Status</td>
        <td>
          <?php
          $inst_badge = match($payment['inst_status']) {
            'paid' => 'success',
            'partial' => 'warning',
            'overdue', 'late' => 'danger',
            default => 'secondary'
          };
          ?>
          <span class="badge badge-<?= $inst_badge ?>"><?= ucfirst($payment['inst_status']) ?></span>
        </td>
      </tr>
    <?php endif; ?>
    <?php if ($payment['sale_id']): ?>
      <tr><td>Total Sale Amount</td><td><?= formatCurrency($payment['total_amount']) ?></td></tr>
      <tr><td>Financed Amount</td><td><?= formatCurrency($payment['financed_amount']) ?></td></tr>
    <?php endif; ?>
    <?php if ($payment['notes']): ?>
      <tr><td>Notes</td><td><?= nl2br(htmlspecialchars($payment['notes'])) ?></td></tr>
    <?php endif; ?>
    <tr><td>Received By</td><td><?= htmlspecialchars($payment['received_by_name'] ?? 'N/A') ?></td></tr>
    <?php if ($payment['branch_name']): ?>
      <tr><td>Branch</td><td><?= htmlspecialchars($payment['branch_name']) ?></td></tr>
    <?php endif; ?>
  </table>

  <div class="receipt-footer text-center">
    <p class="text-muted small mb-1">Thank you for your payment!</p>
    <p class="text-muted small mb-0">This is a computer-generated receipt.</p>
  </div>

</div>

<?php if ($is_print): ?><script>window.print()</script><?php endif; ?>
</body>
</html>
