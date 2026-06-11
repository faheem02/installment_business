<?php
session_start();
$page_title = 'New Sale';
$base_url = '../../';
require_once '../../includes/functions.php';

$customers = getAll('customers', 'full_name ASC');
$plans = $pdo->query("SELECT * FROM installment_plans WHERE status = 1 ORDER BY name")->fetchAll();
$all_products = $pdo->query("SELECT id, code, name, sale_price, stock_quantity FROM products WHERE status = 1 ORDER BY name")->fetchAll();
$bank_accounts = getAll('bank_accounts', 'bank_name ASC, account_name ASC');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $customer_id = (int)$_POST['customer_id'];
    $sale_date = $_POST['sale_date'] ?? date('Y-m-d');
    $descriptions = $_POST['item_description'] ?? [];
    $quantities = $_POST['quantity'] ?? [];
    $prices = $_POST['price'] ?? [];
    $down_payment = (float)($_POST['down_payment'] ?? 0);
    $plan_id = !empty($_POST['installment_plan_id']) ? (int)$_POST['installment_plan_id'] : null;
    $manual_months = (int)($_POST['manual_months'] ?? 0);
    $notes = trim($_POST['notes'] ?? '');
    $payment_method = $_POST['payment_method'] ?? 'cash';
    $bank_account_id = (int)($_POST['bank_account_id'] ?? 0);

    if (empty($customer_id) || empty($descriptions)) {
        $_SESSION['error'] = 'Please select a customer and add at least one item.';
        header("Location: index.php"); exit;
    }

    $product_ids = $_POST['product_ids'] ?? [];
    $total_amount = 0;
    $items = [];
    foreach ($descriptions as $i => $desc) {
        $desc = trim($desc);
        if ($desc === '') continue;
        $qty = max(1, (int)($quantities[$i] ?? 1));
        $price = (float)($prices[$i] ?? 0);
        $line_total = $qty * $price;
        $total_amount += $line_total;
        $pid = !empty($product_ids[$i]) ? (int)$product_ids[$i] : null;
        $items[] = ['description' => $desc, 'quantity' => $qty, 'price' => $price, 'subtotal' => $line_total, 'product_id' => $pid];
    }

    if (empty($items)) {
        $_SESSION['error'] = 'Please add at least one valid item.';
        header("Location: index.php"); exit;
    }

    $financed_amount = $total_amount - $down_payment;
    $total_installments = 0;
    $monthly_installment = 0;

    $interest_rate = 0;
    $interest_amount = 0;
    if ($plan_id && $financed_amount > 0) {
        $plan = getById('installment_plans', $plan_id);
        if ($plan) {
            $total_installments = (int)$plan['duration_months'];
            $interest_rate = (float)$plan['interest_rate'];
            $interest_amount = $financed_amount * ($interest_rate / 100);
            $total_payable = $financed_amount + $interest_amount;
            $monthly_installment = $total_installments > 0 ? round($total_payable / $total_installments, 2) : 0;
        }
    } elseif ($manual_months > 0 && $financed_amount > 0) {
        $total_installments = $manual_months;
        $monthly_installment = round($financed_amount / $total_installments, 2);
    }

    $invoice_no = generateInvoiceNo();
    $payment_status = $financed_amount > 0 ? 'installment' : 'paid';

    $sale_id = insert('sales', [
        'invoice_no' => $invoice_no,
        'customer_id' => $customer_id,
        'sale_date' => $sale_date,
        'subtotal' => $total_amount,
        'discount_amount' => 0,
        'total_amount' => $total_amount,
        'down_payment' => $down_payment,
        'financed_amount' => $financed_amount,
        'interest_rate' => $interest_rate,
        'interest_amount' => $interest_amount,
        'installment_plan_id' => $plan_id,
        'monthly_installment' => $monthly_installment,
        'total_installments' => $total_installments,
        'payment_method' => $plan_id ? 'mixed' : 'cash',
        'payment_status' => $payment_status,
        'status' => 'active',
        'notes' => $notes ?: null,
        'branch_id' => $_SESSION['branch_id'] ?? null,
        'created_by' => $_SESSION['user_id'] ?? null,
        'created_at' => date('Y-m-d'),
        'updated_at' => date('Y-m-d'),
    ]);

    foreach ($items as $item) {
        insert('sale_items', [
            'sale_id' => $sale_id,
            'product_id' => $item['product_id'],
            'item_description' => $item['description'],
            'quantity' => $item['quantity'],
            'price' => $item['price'],
            'subtotal' => $item['subtotal'],
        ]);
        if ($item['product_id']) {
            $pdo->prepare("UPDATE products SET stock_quantity = GREATEST(0, stock_quantity - ?) WHERE id = ?")->execute([$item['quantity'], $item['product_id']]);
        }
    }

    if ($down_payment > 0) {
        $pay_id = insert('payments', [
            'sale_id' => $sale_id,
            'payment_date' => $sale_date,
            'amount' => $down_payment,
            'payment_type' => 'down_payment',
            'payment_method' => $payment_method,
            'notes' => 'Down payment for ' . $invoice_no,
            'branch_id' => $_SESSION['branch_id'] ?? null,
            'received_by' => $_SESSION['user_id'] ?? null,
            'created_at' => date('Y-m-d'),
        ]);
        if ($payment_method === 'cash') {
            recordCashInflow($pdo, $sale_date, $down_payment, 'Down payment - ' . $invoice_no, 'payment', $pay_id, $_SESSION['user_id'] ?? null);
        } elseif ($payment_method === 'bank') {
            recordBankInflow($pdo, $sale_date, $down_payment, 'Down payment (bank) - ' . $invoice_no, 'payment', $pay_id, $_SESSION['user_id'] ?? null, $bank_account_id);
        }
    }

    if ($financed_amount > 0) {
        if ($total_installments > 0) {
            $due_date = new DateTime($sale_date);
            $due_date->modify('+1 month');
            for ($i = 1; $i <= $total_installments; $i++) {
                if ($plan_id) {
                    $total_payable = $financed_amount + ($financed_amount * ((float)$plan['interest_rate'] / 100));
                    $inst_amount = ($i < $total_installments) ? $monthly_installment : round($total_payable - ($monthly_installment * ($total_installments - 1)), 2);
                } else {
                    $inst_amount = ($i < $total_installments) ? $monthly_installment : round($financed_amount - ($monthly_installment * ($total_installments - 1)), 2);
                }
                insert('sale_installments', [
                    'sale_id' => $sale_id,
                    'installment_no' => $i,
                    'due_date' => $due_date->format('Y-m-d'),
                    'amount' => $inst_amount,
                    'paid_amount' => 0,
                    'balance' => $inst_amount,
                    'status' => 'pending',
                    'created_at' => date('Y-m-d'),
                ]);
                $due_date->modify('+1 month');
            }
        } else {
            insert('sale_installments', [
                'sale_id' => $sale_id,
                'installment_no' => 1,
                'due_date' => $sale_date,
                'amount' => $financed_amount,
                'paid_amount' => 0,
                'balance' => $financed_amount,
                'status' => 'pending',
                'created_at' => date('Y-m-d'),
            ]);
        }
    }

    $_SESSION['success'] = "Sale created successfully. Invoice #$invoice_no";
    header("Location: invoices.php");
    exit;
}

require_once '../../includes/header.php';
?>

<style>
#wrapper { padding-left: 0 !important; }
.sidebar { display: none !important; }
#content-wrapper { margin-left: 0 !important; width: 100% !important; }

.cart-table th {
    font-size: .72rem; text-transform: uppercase; white-space: nowrap;
    letter-spacing: .3px; background: #f8f9fc; color: #4e73df;
}
.cart-table td { vertical-align: middle; font-size: .85rem; }
.cart-table .item-row:hover { background: #f8f9fc; }

.summary-card {
    background: linear-gradient(135deg, #f8f9fc, #eef0f7);
    border-radius: 8px; padding: 15px;
}
.finance-value { font-size: 1.2rem; font-weight: 700; color: #0f172a; }
</style>

<div class="d-flex justify-content-between align-items-center mb-3">
  <h5 class="m-0 font-weight-bold" style="color:#0f172a;"><i class="fas fa-shopping-cart"></i> New Sale (Installment)</h5>
  <a href="invoices.php" class="btn btn-sm btn-outline-secondary"><i class="fas fa-arrow-left"></i> Invoices</a>
</div>

<form method="post" id="saleForm">
<div class="row">

  <!-- LEFT COLUMN -->
  <div class="col-lg-5 mb-3">

    <!-- Customer & Date -->
    <div class="card shadow mb-3">
      <div class="card-body py-3">
        <div class="form-group mb-2">
          <label class="small text-muted">Customer <span class="text-danger">*</span></label>
          <select name="customer_id" class="form-control" required>
            <option value="">Select Customer</option>
            <?php foreach ($customers as $c): ?>
              <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['full_name']) ?> (<?= htmlspecialchars($c['phone']) ?>)</option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="row">
          <div class="col-6"><div class="form-group mb-0"><label class="small text-muted">Date</label><input type="text" name="sale_date" class="form-control datepicker" value="<?= date('Y-m-d') ?>" autocomplete="off"></div></div>
          <div class="col-6"><div class="form-group mb-0"><label class="small text-muted">Notes</label><input type="text" name="notes" class="form-control" placeholder="Optional"></div></div>
        </div>
      </div>
    </div>

    <!-- Add Item -->
    <div class="card shadow mb-3">
      <div class="card-header py-2"><h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-plus-circle"></i> Add Item</h6></div>
      <div class="card-body py-3">
        <div class="form-group mb-2">
          <label class="small text-muted">Description <span class="text-danger">*</span></label>
          <input type="text" id="entryDesc" class="form-control form-control-lg" list="productList" placeholder="Type or select product..." autofocus>
          <input type="hidden" id="entryProductId" value="0">
          <datalist id="productList">
            <option value="">-- Select Product --</option>
            <?php foreach ($all_products as $p): ?>
            <option value="<?= htmlspecialchars($p['name'].' ('.$p['code'].')') ?>" data-price="<?= $p['sale_price'] ?>" data-product-id="<?= $p['id'] ?>" data-stock="<?= (int)$p['stock_quantity'] ?>"><?= htmlspecialchars($p['name'].' - '.$p['code'])?></option>
            <?php endforeach; ?>
          </datalist>
          <small class="text-muted" id="stockInfo" style="display:none;"></small>
        </div>
        <div class="row">
          <div class="col-6"><div class="form-group mb-2"><label class="small text-muted">Qty</label><input type="number" id="entryQty" class="form-control form-control-lg" value="1" min="1"></div></div>
          <div class="col-6"><div class="form-group mb-2"><label class="small text-muted">Unit Price</label><input type="number" id="entryPrice" class="form-control form-control-lg" step="0.01" min="0" placeholder="Enter price"></div></div>
        </div>
        <div id="entryPreview" class="summary-card mt-2" style="display:none;">
          <div class="d-flex justify-content-between"><span class="text-muted">Line Amount</span><span class="font-weight-bold" id="entryAmountPreview">0.00</span></div>
        </div>
      </div>
    </div>

    <button type="button" id="addToCartBtn" class="btn btn-primary btn-block btn-sm"><i class="fas fa-cart-plus"></i> Add to Cart</button>
  </div>

  <!-- RIGHT COLUMN -->
  <div class="col-lg-7 mb-3">
    <div class="card shadow h-100">
      <div class="card-header py-2"><h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-list"></i> Cart</h6></div>
      <div class="card-body p-0">

        <!-- Cart Table -->
        <div class="table-responsive" style="max-height:200px; overflow-y:auto;">
          <table class="table table-sm table-bordered cart-table mb-0" id="cartTable">
            <thead class="sticky-top">
              <tr>
                <th>Description</th>
                <th style="width:50px;" class="text-center">Qty</th>
                <th style="width:90px;" class="text-right">Price</th>
                <th style="width:90px;" class="text-right">Amount</th>
                <th style="width:28px;"></th>
              </tr>
            </thead>
            <tbody id="cartBody"></tbody>
          </table>
        </div>
        <div class="text-center text-muted small py-3" id="emptyCart"><i class="fas fa-box-open fa-2x d-block mb-2"></i>Cart is empty</div>

        <!-- Installment Summary -->
        <div class="px-3 pb-3" id="summaryArea" style="display:none;">
          <hr class="my-2">

          <div class="row">
            <div class="col-6">
              <div class="form-group mb-2">
                <label class="small text-muted">Total Amount</label>
                <div class="finance-value" id="totalDisplay">0.00</div>
              </div>
            </div>
            <div class="col-6">
              <div class="form-group mb-2">
                <label class="small text-muted">Down Payment <span class="text-danger">*</span></label>
                <input type="number" name="down_payment" id="downPayment" class="form-control form-control-lg font-weight-bold" step="0.01" min="0" value="0" placeholder="Upfront payment">
              </div>
            </div>
            <div class="col-6">
              <div class="form-group mb-2">
                <label class="small text-muted">Payment Method</label>
                <select name="payment_method" id="paymentMethod" class="form-control">
                  <option value="cash">Cash</option>
                  <option value="bank">Bank</option>
                </select>
              </div>
            </div>
          </div>
          <div class="row" id="bankAccountRow" style="display:none;">
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

          <div class="row">
            <div class="col-6">
              <div class="form-group mb-2">
                <label class="small text-muted">Installment Plan</label>
                <select name="installment_plan_id" id="planSelect" class="form-control">
                  <option value="">-- Custom (No Plan) --</option>
                  <?php foreach ($plans as $p): ?>
                    <option value="<?= $p['id'] ?>" data-months="<?= $p['duration_months'] ?>" data-rate="<?= $p['interest_rate'] ?>">
                      <?= htmlspecialchars($p['name']) ?> (<?= $p['duration_months'] ?>mo @ <?= $p['interest_rate'] ?>%)
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>
            <div class="col-6">
              <div class="form-group mb-2">
                <label class="small text-muted">No. of Months</label>
                <input type="number" name="manual_months" id="manualMonths" class="form-control" min="1" value="1" placeholder="Months">
              </div>
            </div>
          </div>
          <div class="row">
            <div class="col-6">
              <div class="form-group mb-2">
                <label class="small text-muted">Financed Amount</label>
                <div class="finance-value text-info" id="financedDisplay">0.00</div>
              </div>
            </div>
          </div>

          <!-- Plan breakdown -->
          <div id="planBreakdown" class="summary-card mt-2" style="display:none;">
            <div class="d-flex justify-content-between small"><span class="text-muted">Interest Rate</span><span class="font-weight-bold" id="rateDisplay">0%</span></div>
            <div class="d-flex justify-content-between small"><span class="text-muted">Interest Amount</span><span class="font-weight-bold" id="interestDisplay">0.00</span></div>
            <div class="d-flex justify-content-between small mt-1"><span class="text-muted">Total Payable (Financed + Interest)</span><span class="font-weight-bold" id="totalPayableDisplay">0.00</span></div>
            <hr class="my-1">
            <div class="d-flex justify-content-between align-items-center">
              <span class="font-weight-bold">Monthly Installment</span>
              <span class="font-weight-bold text-success" style="font-size:1.3rem;" id="monthlyDisplay">0.00</span>
            </div>
            <div class="d-flex justify-content-between small"><span class="text-muted">Number of Installments</span><span class="font-weight-bold" id="installmentsCountDisplay">0</span></div>
          </div>

          <button type="submit" class="btn btn-primary btn-block mt-3 py-2"><i class="fas fa-file-invoice"></i> Generate Invoice</button>
        </div>

        <div class="text-center pb-3" id="emptySummary">
          <button type="submit" class="btn btn-primary btn-block btn-sm mx-3" disabled style="width:auto;"><i class="fas fa-file-invoice"></i> Generate</button>
          <small class="text-muted d-block mt-1">Add items to the cart first</small>
        </div>

      </div>
    </div>
  </div>

</div>
</form>

<script>
$(document).ready(function() {

    function updateEntryPreview() {
        var desc = $('#entryDesc').val().trim();
        var qty = parseFloat($('#entryQty').val()) || 1;
        var price = parseFloat($('#entryPrice').val()) || 0;
        if (desc && price > 0) {
            $('#entryPreview').show();
            $('#entryAmountPreview').text((qty * price).toFixed(2));
        } else {
            $('#entryPreview').hide();
        }
    }

    function calcInstallment() {
        var total = parseFloat($('#totalDisplay').text()) || 0;
        var down = parseFloat($('#downPayment').val()) || 0;
        var financed = Math.max(0, total - down);
        $('#financedDisplay').text(financed.toFixed(2));

        var $plan = $('#planSelect').find('option:selected');
        var months, rate, interest = 0;

        if ($plan.val()) {
            months = parseInt($plan.data('months')) || 0;
            rate = parseFloat($plan.data('rate')) || 0;
            $('#manualMonths').val(months).prop('disabled', true);
            if (months > 0 && financed > 0) {
                interest = financed * (rate / 100);
            }
        } else {
            months = parseInt($('#manualMonths').val()) || 0;
            $('#manualMonths').prop('disabled', false);
            interest = 0;
        }

        if (months > 0 && financed > 0) {
            var totalPayable = financed + interest;
            var monthly = totalPayable / months;
            $('#planBreakdown').show();
            $('#rateDisplay').text(rate > 0 ? rate + '%' : '0%');
            $('#interestDisplay').text(interest.toFixed(2));
            $('#totalPayableDisplay').text(totalPayable.toFixed(2));
            $('#monthlyDisplay').text(monthly.toFixed(2));
            $('#installmentsCountDisplay').text(months);
        } else {
            $('#planBreakdown').hide();
        }
    }

    $('#addToCartBtn').on('click', addToCart);
    $('#entryQty, #entryPrice').on('keypress', function(e) {
        if (e.which === 13) addToCart();
    });
    $('#entryDesc, #entryQty, #entryPrice').on('input', updateEntryPreview);
    $('#entryDesc').on('input', function() {
        var val = $(this).val();
        var matched = false;
        $('#productList option').each(function() {
            if ($(this).attr('value') && $(this).attr('value') === val) {
                var price = $(this).data('price');
                var productId = $(this).data('product-id');
                var stock = $(this).data('stock');
                if (price) { $('#entryPrice').val(price); updateEntryPreview(); }
                $('#entryProductId').val(productId || 0);
                if (stock !== undefined) {
                    if (stock > 0) {
                        $('#stockInfo').text('In Stock: ' + stock).removeClass('text-danger').addClass('text-muted').show();
                    } else {
                        $('#stockInfo').text('Out of Stock!').removeClass('text-muted').addClass('text-danger').show();
                    }
                }
                matched = true;
                return false;
            }
        });
        if (!matched) {
            $('#entryProductId').val(0);
            $('#stockInfo').hide();
            if (!val) { $('#entryPrice').val(''); updateEntryPreview(); }
        }
    });
    $('#downPayment').on('input', calcInstallment);
    $('#planSelect').on('change', calcInstallment);
    $('#manualMonths').on('input', calcInstallment);

    $('#paymentMethod').on('change', function() {
      $('#bankAccountRow').toggle($(this).val() === 'bank');
    });

    function addToCart() {
        var desc = $('#entryDesc').val().trim();
        if (!desc) { showMsg('Enter a description first'); return; }
        var qty = parseFloat($('#entryQty').val()) || 1;
        var price = parseFloat($('#entryPrice').val()) || 0;
        if (price <= 0) { showMsg('Enter a valid price'); return; }
        var amount = qty * price;
        var productId = parseInt($('#entryProductId').val()) || 0;
        var stockWarn = '';
        var stock = 0;
        $('#productList option').each(function() {
            if ($(this).data('product-id') == productId) {
                stock = parseInt($(this).data('stock')) || 0;
                if (qty > stock) stockWarn = ' <small class="text-danger">(only ' + stock + ' in stock)</small>';
                return false;
            }
        });

        var safeDesc = $('<span>').text(desc).html();
        var html = '<tr class="item-row">' +
            '<td><input type="hidden" name="item_description[]" value="' + safeDesc + '"><input type="hidden" name="product_ids[]" value="' + productId + '">' + safeDesc + stockWarn + '</td>' +
            '<td class="text-center p-1"><input type="number" name="quantity[]" class="form-control form-control-sm qty-input text-center" value="' + qty + '" min="1" style="width:48px;"></td>' +
            '<td class="text-right p-1"><input type="number" name="price[]" class="form-control form-control-sm price-input text-right" step="0.01" min="0" value="' + price.toFixed(2) + '" style="width:80px;"></td>' +
            '<td class="text-right font-weight-bold line-total align-middle p-1">' + amount.toFixed(2) + '</td>' +
            '<td class="text-center align-middle p-1"><span class="remove-item" style="cursor:pointer;color:#e74a3b;"><i class="fas fa-times"></i></span></td>' +
            '</tr>';
        $('#cartBody').append(html);
        if (stockWarn) showMsg('Insufficient stock for ' + safeDesc);
        $('#emptyCart').hide();
        $('#summaryArea').show();
        $('#emptySummary').hide();
        clearEntry();
        calcTotals();
    }

    function clearEntry() {
        $('#entryDesc').val('').focus();
        $('#entryPrice').val('');
        $('#entryQty').val(1);
        $('#entryProductId').val(0);
        $('#stockInfo').hide();
        $('#entryPreview').hide();
    }

    $(document).on('input', '.qty-input, .price-input', function() {
        calcRow($(this).closest('.item-row'));
        calcTotals();
    });

    function calcRow($row) {
        var qty = parseFloat($row.find('.qty-input').val()) || 0;
        var price = parseFloat($row.find('.price-input').val()) || 0;
        $row.find('.line-total').text((qty * price).toFixed(2));
    }

    function calcTotals() {
        var total = 0, itemCount = 0;
        $('#cartBody .item-row').each(function() {
            itemCount++;
            total += parseFloat($(this).find('.line-total').text()) || 0;
        });
        $('#totalDisplay').text(total.toFixed(2));
        calcInstallment();

        if (itemCount === 0) {
            $('#summaryArea').hide();
            $('#emptySummary').show();
        }
    }

    $(document).on('click', '.remove-item', function() {
        $(this).closest('.item-row').remove();
        if ($('#cartBody .item-row').length === 0) $('#emptyCart').show();
        calcTotals();
    });

    function showMsg(msg) {
        var $a = $('<div class="alert alert-warning py-1 small mb-1">' + msg + '</div>');
        $('#addToCartBtn').before($a);
        setTimeout(function() { $a.fadeOut(function() { $(this).remove(); }); }, 1500);
    }
});
</script>

<?php require_once '../../includes/footer.php'; ?>
