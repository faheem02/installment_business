<?php
session_start();
$page_title = 'New Sale';
$base_url = '../../';
require_once '../../includes/functions.php';

$customers = getAll('customers', 'full_name ASC');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $customer_id = (int)$_POST['customer_id'];
    $sale_date = $_POST['sale_date'] ?? date('Y-m-d');
    $payment_method = $_POST['payment_method'] ?? 'cash';
    $descriptions = $_POST['item_description'] ?? [];
    $quantities = $_POST['quantity'] ?? [];
    $prices = $_POST['price'] ?? [];

    if (empty($customer_id) || empty($descriptions)) {
        $_SESSION['error'] = 'Please select a customer and add at least one item.';
        header("Location: index.php"); exit;
    }

    $total_amount = 0;
    $items = [];
    foreach ($descriptions as $i => $desc) {
        $desc = trim($desc);
        if ($desc === '') continue;
        $qty = max(1, (int)($quantities[$i] ?? 1));
        $price = (float)($prices[$i] ?? 0);
        $line_total = $qty * $price;
        $total_amount += $line_total;
        $items[] = ['description' => $desc, 'quantity' => $qty, 'price' => $price, 'subtotal' => $line_total];
    }

    if (empty($items)) {
        $_SESSION['error'] = 'Please add at least one valid item.';
        header("Location: index.php"); exit;
    }

    $invoice_no = generateInvoiceNo();

    $sale_id = insert('sales', [
        'invoice_no' => $invoice_no,
        'customer_id' => $customer_id,
        'sale_date' => $sale_date,
        'subtotal' => $total_amount,
        'discount_amount' => 0,
        'total_amount' => $total_amount,
        'down_payment' => 0,
        'financed_amount' => 0,
        'monthly_installment' => 0,
        'total_installments' => 0,
        'installment_plan_id' => null,
        'payment_method' => $payment_method,
        'payment_status' => 'paid',
        'status' => 'active',
        'notes' => null,
        'branch_id' => $_SESSION['branch_id'] ?? null,
        'created_by' => $_SESSION['user_id'] ?? null,
        'created_at' => date('Y-m-d'),
        'updated_at' => date('Y-m-d'),
    ]);

    foreach ($items as $item) {
        insert('sale_items', [
            'sale_id' => $sale_id,
            'product_id' => null,
            'item_description' => $item['description'],
            'quantity' => $item['quantity'],
            'price' => $item['price'],
            'subtotal' => $item['subtotal'],
        ]);
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
.cart-table th { font-size: .72rem; text-transform: uppercase; white-space: nowrap; letter-spacing: .3px; background:#f8f9fc; color:#4e73df; }
.cart-table td { vertical-align: middle; font-size: .85rem; }
.cart-table .item-row:hover { background:#f8f9fc; }
.finance-card { background:linear-gradient(135deg,#f8f9fc,#eef0f7); border-radius:8px; padding:12px; margin-bottom:0; }
</style>

<div class="d-flex justify-content-between align-items-center mb-3">
  <h5 class="m-0 font-weight-bold" style="color:#0f172a;"><i class="fas fa-shopping-cart"></i> New Sale</h5>
  <div>
    <a href="invoices.php" class="btn btn-sm btn-outline-secondary"><i class="fas fa-arrow-left"></i> Back to Invoices</a>
  </div>
</div>

<form method="post" id="saleForm">
<div class="row">
  <!-- LEFT: Entry -->
  <div class="col-lg-5 mb-3">
    <!-- Customer Info -->
    <div class="card shadow mb-3">
      <div class="card-body py-3">
        <div class="form-group mb-2">
          <label class="small text-muted">Customer</label>
          <select name="customer_id" class="form-control" required>
            <option value="">Select</option>
            <?php foreach ($customers as $c): ?>
              <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['full_name']) ?> (<?= htmlspecialchars($c['phone']) ?>)</option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="row">
          <div class="col-6"><div class="form-group mb-0"><label class="small text-muted">Date</label><input type="date" name="sale_date" class="form-control" value="<?= date('Y-m-d') ?>"></div></div>
          <div class="col-6"><div class="form-group mb-0"><label class="small text-muted">Method</label><select name="payment_method" class="form-control"><option value="cash">Cash</option><option value="card">Card</option><option value="bank_transfer">Bank Transfer</option></select></div></div>
        </div>
      </div>
    </div>

    <!-- Add Item -->
    <div class="card shadow mb-3">
      <div class="card-header py-2"><h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-plus-circle"></i> Add Item</h6></div>
      <div class="card-body py-3">
        <div class="form-group mb-2">
          <label class="small text-muted">Description <span class="text-danger">*</span></label>
          <input type="text" id="entryDesc" class="form-control form-control-lg" placeholder="e.g. Furniture, Electronics..." autofocus>
        </div>
        <div class="row">
          <div class="col-6"><div class="form-group mb-2"><label class="small text-muted">Qty</label><input type="number" id="entryQty" class="form-control form-control-lg" value="1" min="1"></div></div>
          <div class="col-6"><div class="form-group mb-2"><label class="small text-muted">Unit Price</label><input type="number" id="entryPrice" class="form-control form-control-lg" step="0.01" min="0" placeholder="Enter price"></div></div>
        </div>
        <div id="entryPreview" class="finance-card mt-2" style="display:none;">
          <div class="d-flex justify-content-between"><span class="summary-label">Line Amount</span><span class="summary-value" id="entryAmountPreview">0.00</span></div>
        </div>
      </div>
    </div>

    <button type="button" id="addToCartBtn" class="btn btn-primary btn-block btn-sm"><i class="fas fa-cart-plus"></i> Add to Cart</button>
  </div>

  <!-- RIGHT: Cart + Professional Installment Summary -->
  <div class="col-lg-7 mb-3">
    <div class="card shadow h-100">
      <div class="card-header py-2"><h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-list"></i> Cart</h6></div>
      <div class="card-body p-0">
        <!-- Cart Table -->
        <div class="table-responsive" style="max-height:280px; overflow-y:auto;">
          <table class="table table-sm table-bordered cart-table mb-0" id="cartTable">
            <thead class="sticky-top">
              <tr>
                <th>Description</th>
                <th style="width:55px;" class="text-center">Qty</th>
                <th style="width:95px;" class="text-right">Unit Price</th>
                <th style="width:95px;" class="text-right">Amount</th>
                <th style="width:30px;" class="text-center"></th>
              </tr>
            </thead>
            <tbody id="cartBody">
            </tbody>
          </table>
        </div>
        <div class="text-center text-muted small py-4" id="emptyCart"><i class="fas fa-box-open fa-2x d-block mb-2"></i>Cart is empty — add items from the left panel</div>

        <!-- Summary -->
        <div class="px-3 pb-3" id="summaryArea" style="display:none;">
          <hr class="my-2">
          <div class="d-flex justify-content-between align-items-center">
            <span class="font-weight-bold" style="font-size:1.1rem;">Total Amount</span>
            <span class="font-weight-bold" style="font-size:1.3rem;color:#0f172a;" id="totalDisplay">0.00</span>
          </div>
          <div class="d-flex justify-content-between align-items-center small mt-1">
            <span class="text-muted">Method</span>
            <span id="methodDisplay">Cash</span>
          </div>
          <button type="submit" class="btn btn-primary btn-block btn-sm mt-3" id="generateBtn"><i class="fas fa-file-invoice"></i> Generate Invoice</button>
        </div>

        <!-- Empty state button -->
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
        var amount = qty * price;

        if (desc && price > 0) {
            $('#entryPreview').show();
            $('#entryAmountPreview').text(amount.toFixed(2));
        } else {
            $('#entryPreview').hide();
        }
    }

    $('#addToCartBtn').on('click', addToCart);
    $('#entryQty, #entryPrice').on('keypress', function(e) {
        if (e.which === 13) addToCart();
    });
    $('#entryDesc, #entryQty, #entryPrice').on('input', updateEntryPreview);

    function addToCart() {
        var desc = $('#entryDesc').val().trim();
        if (!desc) { showMsg('Enter a description first'); return; }
        var qty = parseFloat($('#entryQty').val()) || 1;
        var price = parseFloat($('#entryPrice').val()) || 0;
        if (price <= 0) { showMsg('Enter a valid price'); return; }
        var amount = qty * price;

        var html = '<tr class="item-row">' +
            '<td><input type="hidden" name="item_description[]" value="' + $('<span>').text(desc).html() + '">' + $('<span>').text(desc).html() + '</td>' +
            '<td class="text-center"><input type="number" name="quantity[]" class="form-control form-control-sm qty-input text-center" value="' + qty + '" min="1" style="width:50px;"></td>' +
            '<td class="text-right"><input type="number" name="price[]" class="form-control form-control-sm price-input text-right" step="0.01" min="0" value="' + price.toFixed(2) + '" style="width:85px;"></td>' +
            '<td class="text-right font-weight-bold line-total align-middle">' + amount.toFixed(2) + '</td>' +
            '<td class="text-center align-middle p-0"><span class="remove-item" style="cursor:pointer;color:#e74a3b;"><i class="fas fa-times"></i></span></td>' +
            '</tr>';
        $('#cartBody').append(html);
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
        $('#methodDisplay').text($('select[name="payment_method"]').find('option:selected').text());

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

    $('select[name="payment_method"]').on('change', calcTotals);

    function showMsg(msg) {
        var $a = $('<div class="alert alert-warning py-1 small mb-1">' + msg + '</div>');
        $('#addToCartBtn').before($a);
        setTimeout(function() { $a.fadeOut(function() { $(this).remove(); }); }, 1500);
    }
});
</script>

<?php require_once '../../includes/footer.php'; ?>
