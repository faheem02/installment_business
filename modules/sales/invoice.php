<?php
session_start();
$id = (int)($_GET['id'] ?? 0);
$is_print = isset($_GET['print']);
require_once '../../includes/functions.php';

$sale = getById('sales', $id);
if (!$sale) {
    $_SESSION['error'] = 'Invoice not found.';
    header("Location: invoices.php");
    exit;
}

$customer = getById('customers', $sale['customer_id']);
$items    = getWhere('sale_items', 'sale_id', $id);
$discount = $sale['discount_id'] ? getById('discounts', $sale['discount_id']) : null;

$pay_stmt = $pdo->prepare("SELECT * FROM payments WHERE sale_id = ? ORDER BY payment_date ASC");
$pay_stmt->execute([$id]);
$payments = $pay_stmt->fetchAll();

$badge_map = ['paid'=>'success', 'partial'=>'warning', 'installment'=>'info'];
$badge = $badge_map[$sale['payment_status']] ?? 'secondary';

$title = 'Invoice #' . htmlspecialchars($sale['invoice_no']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title><?=$title?></title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/4.6.2/css/bootstrap.min.css">
<style>
  @media print{body{font-size:12px}.no-print{display:none!important}}
  body{background:#f1f5f9;font-family:'Segoe UI',sans-serif}
  .wrap{max-width:800px;margin:30px auto;background:#fff;border-radius:12px;padding:40px;box-shadow:0 4px 24px rgba(0,0,0,.08)}
</style>
</head>
<body>

<div class="no-print text-center mt-3 mb-3">
  <button class="btn btn-primary" onclick="window.print()"><i class="fas fa-print"></i> Print</button>
  <a href="invoices.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Back</a>
</div>

<div class="wrap">

  <div class="d-flex justify-content-between align-items-start mb-4">
    <div>
      <h3 class="font-weight-bold mb-1" style="color:#0f172a;">Installment Business</h3>
      <p class="text-muted mb-0">POS System</p>
    </div>
    <div class="text-right">
      <h4 class="font-weight-bold mb-1" style="color:#0f172a;">INVOICE</h4>
      <p class="mb-0"><strong>#<?=htmlspecialchars($sale['invoice_no'])?></strong></p>
      <p class="mb-0 text-muted"><?=formatDate($sale['sale_date'])?></p>
      <span class="badge badge-<?=$badge?> px-3 py-1 mt-1"><?=ucfirst($sale['payment_status'])?></span>
    </div>
  </div>

  <hr>

  <div class="row mb-4">
    <div class="col-sm-6">
      <h6 class="font-weight-bold text-uppercase" style="color:#0f172a;font-size:.8rem;">Bill To</h6>
      <p class="mb-1 font-weight-bold"><?=htmlspecialchars($customer['full_name']??'N/A')?></p>
      <p class="mb-1 text-muted"><?=htmlspecialchars($customer['phone']??'')?></p>
      <p class="mb-0 text-muted"><?=nl2br(htmlspecialchars($customer['address']??''))?></p>
    </div>
    <div class="col-sm-6 text-sm-right">
      <h6 class="font-weight-bold text-uppercase" style="color:#0f172a;font-size:.8rem;">Payment</h6>
      <p class="mb-1"><?=ucfirst(str_replace('_',' ',$sale['payment_method']))?></p>
    </div>
  </div>

  <table class="table table-bordered">
    <thead class="thead-dark">
      <tr><th class="text-center" style="width:40px;">#</th><th>Product</th><th class="text-center" style="width:60px;">Qty</th><th class="text-right" style="width:120px;">Price</th><th class="text-right" style="width:120px;">Total</th></tr>
    </thead>
    <tbody>
      <?php $i=1; foreach($items as $item):
        $prod = getById('products', $item['product_id']);
      ?>
      <tr>
        <td class="text-center"><?=$i++?></td>
        <td><?=htmlspecialchars($prod['name']??'Unknown')?> <small class="text-muted d-block"><?=htmlspecialchars($prod['code']??'')?></small></td>
        <td class="text-center"><?=$item['quantity']?></td>
        <td class="text-right"><?=formatCurrency($item['price'])?></td>
        <td class="text-right"><?=formatCurrency($item['subtotal'])?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>

  <div class="row">
    <div class="col-sm-6">
      <?php if($discount): ?><p class="mb-1"><strong>Discount:</strong> <?=htmlspecialchars($discount['name'])?> (<?=$discount['discount_type']==='percentage'?$discount['discount_value'].'%':formatCurrency($discount['discount_value'])?>)</p><?php endif; ?>
      <?php if($sale['notes']): ?><p class="mb-0"><strong>Notes:</strong> <?=nl2br(htmlspecialchars($sale['notes']))?></p><?php endif; ?>
    </div>
    <div class="col-sm-6">
      <table class="table table-sm table-borderless text-right mb-0">
        <tr><td style="width:60%;">Subtotal</td><td style="width:40%;"><strong><?=formatCurrency($sale['subtotal'])?></strong></td></tr>
        <?php if(($sale['discount_amount']??0)>0): ?><tr><td class="text-danger">Discount</td><td class="text-danger">-<?=formatCurrency($sale['discount_amount'])?></td></tr><?php endif; ?>
        <tr class="font-weight-bold" style="font-size:1.15rem;"><td>Total</td><td style="color:#0f172a;"><?=formatCurrency($sale['total_amount'])?></td></tr>
        <tr><td>Down Payment</td><td><?=formatCurrency($sale['down_payment'])?></td></tr>
        <tr class="font-weight-bold text-primary"><td>Financed</td><td><?=formatCurrency($sale['financed_amount'])?></td></tr>
      </table>
    </div>
  </div>

  <?php if($payments): ?>
  <hr>
  <h6 class="font-weight-bold text-uppercase" style="color:#0f172a;font-size:.8rem;">Payment History</h6>
  <table class="table table-sm table-bordered">
    <thead class="thead-light">
      <tr><th>Date</th><th class="text-right">Amount</th><th>Method</th><th>Type</th></tr>
    </thead>
    <tbody>
      <?php foreach($payments as $p): ?>
      <tr><td><?=formatDate($p['payment_date'])?></td><td class="text-right"><?=formatCurrency($p['amount'])?></td><td><?=ucfirst($p['payment_method'])?></td><td><?=ucfirst(str_replace('_',' ',$p['payment_type']))?></td></tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>

  <hr>
  <p class="text-center text-muted small mb-0">Thank you for your business!</p>

</div>

<?php if ($is_print): ?><script>window.print()</script><?php endif; ?>
</body>
</html>
