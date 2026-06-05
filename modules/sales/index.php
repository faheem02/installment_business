<?php
session_start();
$page_title = 'New Sale';
$base_url = '../../';
require_once '../../includes/functions.php';

$products = getAll('products', 'name ASC');
$customers = getAll('customers', 'full_name ASC');
$discounts = $pdo->query("SELECT * FROM discounts WHERE status = 1 AND (start_date IS NULL OR start_date <= CURDATE()) AND (end_date IS NULL OR end_date >= CURDATE())")->fetchAll();
$plans = getAll('installment_plans', 'duration_months ASC');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $customer_id = (int)$_POST['customer_id'];
    $sale_date = $_POST['sale_date'] ?? date('Y-m-d');
    $payment_method = $_POST['payment_method'] ?? 'cash';
    $discount_id = !empty($_POST['discount_id']) ? (int)$_POST['discount_id'] : null;
    $installment_plan_id = !empty($_POST['installment_plan_id']) ? (int)$_POST['installment_plan_id'] : null;
    $down_payment = (float)($_POST['down_payment'] ?? 0);
    $notes = $_POST['notes'] ?? '';
    $product_ids = $_POST['product_id'] ?? [];
    $quantities = $_POST['quantity'] ?? [];
    $prices = $_POST['price'] ?? [];

    if (empty($customer_id) || empty($product_ids)) {
        $_SESSION['error'] = 'Please select a customer and at least one product.';
        header("Location: index.php");
        exit;
    }

    $subtotal = 0;
    $items = [];
    foreach ($product_ids as $i => $pid) {
        $pid = (int)$pid;
        if ($pid <= 0) continue;
        $qty = max(1, (int)($quantities[$i] ?? 1));
        $price = (float)($prices[$i] ?? 0);
        $line_total = $qty * $price;
        $subtotal += $line_total;
        $items[] = ['product_id' => $pid, 'quantity' => $qty, 'price' => $price, 'subtotal' => $line_total];
    }

    if (empty($items)) {
        $_SESSION['error'] = 'Please select at least one valid product.';
        header("Location: index.php");
        exit;
    }

    $discount_amount = 0;
    if ($discount_id) {
        $disc = getById('discounts', $discount_id);
        if ($disc) {
            $discount_amount = $disc['discount_type'] === 'percentage'
                ? $subtotal * ($disc['discount_value'] / 100)
                : min($disc['discount_value'], $subtotal);
        }
    }

    $total_amount = $subtotal - $discount_amount;
    $invoice_no = generateInvoiceNo();

    $sale_id = insert('sales', [
        'invoice_no' => $invoice_no,
        'customer_id' => $customer_id,
        'sale_date' => $sale_date,
        'subtotal' => $subtotal,
        'discount_id' => $discount_id,
        'discount_amount' => $discount_amount,
        'total_amount' => $total_amount,
        'down_payment' => $down_payment,
        'financed_amount' => $total_amount - $down_payment,
        'installment_plan_id' => $installment_plan_id,
        'payment_method' => $payment_method,
        'payment_status' => $down_payment >= $total_amount ? 'paid' : ($installment_plan_id ? 'installment' : 'partial'),
        'status' => 'active',
        'notes' => $notes,
        'branch_id' => $_SESSION['branch_id'] ?? null,
        'created_by' => $_SESSION['user_id'] ?? null,
        'created_at' => date('Y-m-d'),
    ]);

    foreach ($items as $item) {
        insert('sale_items', [
            'sale_id' => $sale_id,
            'product_id' => $item['product_id'],
            'quantity' => $item['quantity'],
            'price' => $item['price'],
            'subtotal' => $item['subtotal'],
        ]);
    }

    if ($installment_plan_id && ($total_amount - $down_payment) > 0) {
        $plan = getById('installment_plans', $installment_plan_id);
        if ($plan) {
            $financed = $total_amount - $down_payment;
            $months = (int)$plan['duration_months'];
            $rate = (float)$plan['interest_rate'];

            if ($rate > 0) {
                $monthly_rate = $rate / 100 / 12;
                $emi = $financed * $monthly_rate * pow(1 + $monthly_rate, $months) / (pow(1 + $monthly_rate, $months) - 1);
            } else {
                $emi = $financed / $months;
            }

            $emi = round($emi, 2);
            $sale_date_obj = new DateTime($sale_date);

            for ($i = 1; $i <= $months; $i++) {
                $due = clone $sale_date_obj;
                $due->modify("+{$i} month");
                $remaining = $financed - ($emi * ($i - 1));
                $inst_amount = ($i === $months) ? round($remaining, 2) : $emi;

                insert('sale_installments', [
                    'sale_id' => $sale_id,
                    'installment_no' => $i,
                    'due_date' => $due->format('Y-m-d'),
                    'amount' => $inst_amount,
                    'paid_amount' => 0,
                    'balance' => $inst_amount,
                    'status' => 'pending',
                    'created_at' => date('Y-m-d'),
                ]);
            }
        }
    }

    $_SESSION['success'] = "Sale created successfully. Invoice #$invoice_no";
    header("Location: invoices.php");
    exit;
}

require_once '../../includes/header.php';
?>

<style>
  .item-row { background: #f8f9fc; border-radius: 8px; padding: 12px; margin-bottom: 8px; border: 1px solid #e3e6f0; }
  .item-row .remove-item { color: #e74a3b; cursor: pointer; }
  #totals-section { background: #f8f9fc; border-radius: 8px; padding: 16px; }
  #totals-section .total-row { display: flex; justify-content: space-between; padding: 4px 0; }
  #totals-section .grand-total { font-size: 1.25rem; font-weight: 700; color: #0f172a; border-top: 2px solid #0f172a; padding-top: 8px; margin-top: 4px; }
</style>

<div class="row">
  <div class="col-lg-8">
    <div class="card shadow mb-4">
      <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-shopping-cart"></i> New Sale</h6>
      </div>
      <div class="card-body">
        <form method="post" id="saleForm">
          <div class="row">
            <div class="col-md-6 form-group">
              <label class="form-label">Customer <span class="text-danger">*</span></label>
              <select name="customer_id" class="form-control" required>
                <option value="">Select Customer</option>
                <?php foreach ($customers as $c): ?>
                  <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['full_name']) ?> (<?= htmlspecialchars($c['phone']) ?>)</option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-3 form-group">
              <label class="form-label">Date</label>
              <input type="date" name="sale_date" class="form-control" value="<?= date('Y-m-d') ?>">
            </div>
            <div class="col-md-3 form-group">
              <label class="form-label">Payment Method</label>
              <select name="payment_method" class="form-control">
                <option value="cash">Cash</option>
                <option value="card">Card</option>
                <option value="bank_transfer">Bank Transfer</option>
                <option value="mixed">Mixed</option>
              </select>
            </div>
          </div>

          <hr>
          <div class="d-flex justify-content-between align-items-center mb-3">
            <h6 class="font-weight-bold mb-0"><i class="fas fa-box"></i> Products</h6>
            <button type="button" class="btn btn-success btn-sm" id="addItemBtn"><i class="fas fa-plus"></i> Add Item</button>
          </div>

          <div id="itemsContainer">
            <div class="item-row">
              <div class="row align-items-center">
                <div class="col-md-5">
                  <label class="form-label small">Product / Item Code</label>
                  <select name="product_id[]" class="form-control product-select" required>
                    <option value="">Select product</option>
                    <?php foreach ($products as $p): ?>
                      <option value="<?= $p['id'] ?>" data-price="<?= $p['sale_price'] ?>">
                        <?= htmlspecialchars($p['code']) ?> - <?= htmlspecialchars($p['name']) ?> (<?= formatCurrency($p['sale_price']) ?>)
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="col-md-2">
                  <label class="form-label small">Qty</label>
                  <input type="number" name="quantity[]" class="form-control qty-input" value="1" min="1" required>
                </div>
                <div class="col-md-3">
                  <label class="form-label small font-weight-bold text-primary">Unit Price</label>
                  <input type="number" name="price[]" class="form-control price-input font-weight-bold" step="0.01" min="0" required>
                </div>
                <div class="col-md-2">
                  <label class="form-label small">Total</label>
                  <div class="line-total pt-2 font-weight-bold" style="font-size:1.1rem;">0.00</div>
                </div>
                <div class="col-md-auto text-center pt-4 pl-0">
                  <span class="remove-item d-none" style="cursor:pointer;color:#e74a3b;"><i class="fas fa-times-circle fa-lg"></i></span>
                </div>
              </div>
            </div>
          </div>

          <hr>

          <div class="row">
            <div class="col-md-4 form-group">
              <label class="form-label">Discount</label>
              <select name="discount_id" class="form-control" id="discountSelect">
                <option value="">No Discount</option>
                <?php foreach ($discounts as $d): ?>
                  <option value="<?= $d['id'] ?>" data-type="<?= $d['discount_type'] ?>" data-value="<?= $d['discount_value'] ?>">
                    <?= htmlspecialchars($d['name']) ?> (<?= $d['discount_type'] === 'percentage' ? $d['discount_value'] . '%' : formatCurrency($d['discount_value']) ?>)
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-4 form-group">
              <label class="form-label">Installment Plan</label>
              <select name="installment_plan_id" class="form-control">
                <option value="">Cash Sale</option>
                <?php foreach ($plans as $p): ?>
                  <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['name']) ?> (<?= $p['interest_rate'] ?>% interest)</option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-4 form-group">
              <label class="form-label">Down Payment</label>
              <input type="number" name="down_payment" id="downPayment" class="form-control" step="0.01" min="0" value="0">
            </div>
          </div>

          <div class="form-group">
            <label class="form-label">Notes</label>
            <textarea name="notes" class="form-control" rows="2"></textarea>
          </div>

          <button type="submit" class="btn btn-primary btn-block py-2"><i class="fas fa-save"></i> Create Sale & Generate Invoice</button>
        </form>
      </div>
    </div>
  </div>

  <div class="col-lg-4">
    <div class="card shadow mb-4">
      <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-calculator"></i> Totals</h6>
      </div>
      <div class="card-body" id="totals-section">
        <div class="total-row"><span>Subtotal:</span><span id="subtotalDisplay">0.00</span></div>
        <div class="total-row" id="discountDisplayRow" style="display:none;"><span>Discount:</span><span id="discountDisplay" class="text-danger">-0.00</span></div>
        <div class="total-row grand-total"><span>Total:</span><span id="totalDisplay">0.00</span></div>
        <hr>
        <div class="total-row"><span>Down Payment:</span><span id="downPaymentDisplay">0.00</span></div>
        <div class="total-row font-weight-bold text-primary"><span>Financed Amount:</span><span id="financedDisplay">0.00</span></div>
      </div>
    </div>
  </div>
</div>

<script>
$(document).ready(function() {
    function calcLine($row) {
        var qty = parseFloat($row.find('.qty-input').val()) || 0;
        var price = parseFloat($row.find('.price-input').val()) || 0;
        $row.find('.line-total').text((qty * price).toFixed(2));
    }

    function calcTotals() {
        var subtotal = 0;
        $('#itemsContainer .item-row').each(function() {
            subtotal += parseFloat($(this).find('.line-total').text()) || 0;
        });
        var discountVal = 0;
        var $discOpt = $('#discountSelect').find('option:selected');
        if ($discOpt.val()) {
            var dType = $discOpt.data('type');
            var dValue = parseFloat($discOpt.data('value')) || 0;
            discountVal = dType === 'percentage' ? subtotal * (dValue / 100) : Math.min(dValue, subtotal);
            $('#discountDisplayRow').show();
            $('#discountDisplay').text('-' + discountVal.toFixed(2));
        } else {
            $('#discountDisplayRow').hide();
        }
        var total = subtotal - discountVal;
        var downPay = parseFloat($('#downPayment').val()) || 0;
        var financed = Math.max(0, total - downPay);
        $('#subtotalDisplay').text(subtotal.toFixed(2));
        $('#totalDisplay').text(total.toFixed(2));
        $('#downPaymentDisplay').text(downPay.toFixed(2));
        $('#financedDisplay').text(financed.toFixed(2));
    }

    $(document).on('change', '.product-select', function() {
        var $row = $(this).closest('.item-row');
        var price = $(this).find('option:selected').data('price') || 0;
        $row.find('.price-input').val(price);
        calcLine($row);
        calcTotals();
    });

    $(document).on('input', '.qty-input, .price-input', function() {
        calcLine($(this).closest('.item-row'));
        calcTotals();
    });

    $(document).on('click', '.remove-item', function() {
        if ($('#itemsContainer .item-row').length > 1) {
            $(this).closest('.item-row').remove();
            calcTotals();
        }
    });

    $('#addItemBtn').on('click', function() {
        var $first = $('#itemsContainer .item-row:first');
        var $clone = $first.clone();
        $clone.find('select').val('');
        $clone.find('input').val('1');
        $clone.find('.line-total').text('0.00');
        $clone.find('.remove-item').removeClass('d-none');
        $('#itemsContainer').append($clone);
    });

    $('#discountSelect').on('change', calcTotals);
    $('#downPayment').on('input', calcTotals);
    $('#itemsContainer .item-row:first .remove-item').addClass('d-none');
});
</script>

<?php require_once '../../includes/footer.php'; ?>
