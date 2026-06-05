<?php
session_start();
$page_title = 'EMI Calculator';
$base_url = '../../';
require_once '../../includes/functions.php';
require_once '../../includes/header.php';
?>
<style>
  .emi-card { border-radius: 12px; border: none; }
  .emi-card .card-body { padding: 30px; }
  .result-box { border-radius: 10px; padding: 20px; text-align: center; }
  .result-box h5 { font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.5px; opacity: 0.85; margin-bottom: 8px; }
  .result-box .value { font-size: 1.8rem; font-weight: 700; }
</style>

<div class="card shadow mb-4 emi-card">
  <div class="card-header py-3">
    <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-calculator"></i> EMI Calculator</h6>
  </div>
  <div class="card-body">
    <form id="emiForm" class="row">
      <div class="col-md-3 form-group">
        <label class="font-weight-bold small">Loan Amount</label>
        <input type="number" id="loanAmount" class="form-control" step="0.01" required placeholder="e.g. 50000">
      </div>
      <div class="col-md-3 form-group">
        <label class="font-weight-bold small">Annual Rate (%)</label>
        <input type="number" id="interestRate" class="form-control" step="0.01" value="20" placeholder="e.g. 20">
      </div>
      <div class="col-md-3 form-group">
        <label class="font-weight-bold small">Months</label>
        <input type="number" id="loanMonths" class="form-control" min="1" placeholder="e.g. 12">
      </div>
      <div class="col-md-3 form-group d-flex align-items-end">
        <button type="submit" class="btn btn-primary btn-block"><i class="fas fa-calculator"></i> Calculate</button>
      </div>
    </form>

    <div id="emiResult" class="mt-4" style="display:none;">
      <hr>
      <div class="row">
        <div class="col-md-4 mb-3">
          <div class="result-box bg-primary text-white">
            <h5>Monthly EMI</h5>
            <div class="value" id="emiMonthly">0</div>
          </div>
        </div>
        <div class="col-md-4 mb-3">
          <div class="result-box bg-success text-white">
            <h5>Total Interest</h5>
            <div class="value" id="emiInterest">0</div>
          </div>
        </div>
        <div class="col-md-4 mb-3">
          <div class="result-box bg-info text-white">
            <h5>Total Payment</h5>
            <div class="value" id="emiTotal">0</div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
$('#emiForm').on('submit', function(e) {
  e.preventDefault();
  var P = parseFloat($('#loanAmount').val());
  var r = parseFloat($('#interestRate').val()) / 12 / 100;
  var n = parseInt($('#loanMonths').val());
  if (!P || !r || !n) return;
  var emi = P * r * Math.pow(1 + r, n) / (Math.pow(1 + r, n) - 1);
  $('#emiMonthly').text(emi.toFixed(2));
  $('#emiInterest').text((emi * n - P).toFixed(2));
  $('#emiTotal').text((emi * n).toFixed(2));
  $('#emiResult').slideDown();
});
</script>

<?php require_once '../../includes/footer.php'; ?>
