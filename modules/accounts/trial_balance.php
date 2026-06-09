<?php
session_start();
$page_title = 'Trial Balance';
$base_url = '../../';
require_once '../../includes/functions.php';

$as_of = $_GET['as_of'] ?? date('Y-m-d');
$show_zero = isset($_GET['show_zero']);

require_once '../../includes/header.php';
?>

<style>
.status-badge { font-size: .75rem; padding: 3px 10px; }
.tb-total { background: #e8f0fe !important; font-weight: 700; }
.tb-section { background: #f1f5f9 !important; font-weight: 600; }
.tb-debit { color: #0f172a; text-align: right; }
.tb-credit { color: #0f172a; text-align: right; }
</style>

<?php
$stmt = $pdo->prepare("
    SELECT ca.*,
           COALESCE(SUM(jei.debit), 0) AS total_debit,
           COALESCE(SUM(jei.credit), 0) AS total_credit
    FROM chart_of_accounts ca
    LEFT JOIN journal_entry_items jei ON jei.account_id = ca.id
    LEFT JOIN journal_entries je ON je.id = jei.journal_id AND je.entry_date <= ?
    WHERE ca.status = 1
    GROUP BY ca.id
    ORDER BY FIELD(ca.account_type, 'asset','liability','equity','income','expense'), ca.account_code
");
$stmt->execute([$as_of]);
$accounts = $stmt->fetchAll();

$type_order = ['asset', 'liability', 'equity', 'income', 'expense'];
$type_labels = [
    'asset' => 'Assets',
    'liability' => 'Liabilities',
    'equity' => 'Equity',
    'income' => 'Income',
    'expense' => 'Expenses',
];

$grouped = [];
foreach ($accounts as $a) {
    $normal_debit = in_array($a['account_type'], ['asset', 'expense']);
    $movement = (float)$a['total_debit'] - (float)$a['total_credit'];
    if ($normal_debit) {
        $balance = (float)$a['opening_balance'] + $movement;
    } else {
        $balance = (float)$a['opening_balance'] - $movement;
    }
    $a['calc_balance'] = $balance;

    if ($balance > 0) {
        $a['debit_balance'] = $normal_debit ? $balance : 0;
        $a['credit_balance'] = $normal_debit ? 0 : $balance;
    } elseif ($balance < 0) {
        $a['debit_balance'] = $normal_debit ? 0 : abs($balance);
        $a['credit_balance'] = $normal_debit ? abs($balance) : 0;
    } else {
        $a['debit_balance'] = 0;
        $a['credit_balance'] = 0;
    }

    $grouped[$a['account_type']][] = $a;
}

$grand_debit = 0;
$grand_credit = 0;
foreach ($grouped as $type => $list) {
    foreach ($list as $a) {
        $grand_debit += $a['debit_balance'];
        $grand_credit += $a['credit_balance'];
    }
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
  <h5 class="m-0 font-weight-bold" style="color:#0f172a;"><i class="fas fa-balance-scale"></i> Trial Balance</h5>
</div>

<form method="get" class="form-inline mb-4">
  <label class="mr-2 text-muted small">As of:</label>
  <input type="date" name="as_of" class="form-control form-control-sm mr-3" value="<?= $as_of ?>">
  <button type="submit" class="btn btn-sm btn-primary"><i class="fas fa-search"></i> View</button>
  <a href="trial_balance.php" class="btn btn-sm btn-secondary ml-2">Today</a>
  <div class="ml-3 form-check">
    <input type="checkbox" class="form-check-input" name="show_zero" id="showZero" value="1" <?= $show_zero ? 'checked' : '' ?> onchange="this.form.submit()">
    <label class="form-check-label small" for="showZero">Show zero balances</label>
  </div>
</form>

<div class="row mb-4">
  <div class="col-md-4 mb-3">
    <div class="card border-left-primary shadow h-100 py-2">
      <div class="card-body py-2">
        <div class="row no-gutters align-items-center">
          <div class="col mr-2">
            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">As of Date</div>
            <div class="h5 mb-0 font-weight-bold" style="color:#0f172a;"><?= formatDate($as_of) ?></div>
          </div>
          <div class="col-auto"><i class="fas fa-calendar fa-2x text-gray-300"></i></div>
        </div>
      </div>
    </div>
  </div>
  <div class="col-md-4 mb-3">
    <div class="card border-left-success shadow h-100 py-2">
      <div class="card-body py-2">
        <div class="row no-gutters align-items-center">
          <div class="col mr-2">
            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Total Debits</div>
            <div class="h5 mb-0 font-weight-bold" style="color:#0f172a;"><?= formatCurrency($grand_debit) ?></div>
          </div>
          <div class="col-auto"><i class="fas fa-arrow-left fa-2x text-gray-300"></i></div>
        </div>
      </div>
    </div>
  </div>
  <div class="col-md-4 mb-3">
    <div class="card border-left-danger shadow h-100 py-2">
      <div class="card-body py-2">
        <div class="row no-gutters align-items-center">
          <div class="col mr-2">
            <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">Total Credits</div>
            <div class="h5 mb-0 font-weight-bold" style="color:#0f172a;"><?= formatCurrency($grand_credit) ?></div>
          </div>
          <div class="col-auto"><i class="fas fa-arrow-right fa-2x text-gray-300"></i></div>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="card shadow mb-4">
  <div class="card-header py-3">
    <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-balance-scale"></i> Trial Balance</h6>
  </div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-bordered table-sm mb-0" width="100%" cellspacing="0">
        <thead class="thead-light">
          <tr>
            <th width="12%">Code</th>
            <th>Account</th>
            <th width="18%" class="text-right">Debit (<?= formatCurrency(0) ?>)</th>
            <th width="18%" class="text-right">Credit (<?= formatCurrency(0) ?>)</th>
          </tr>
        </thead>
        <tbody>
          <?php
          $has_any = false;
          foreach ($type_order as $type):
              $list = $grouped[$type] ?? [];
              $filtered = array_filter($list, function ($a) use ($show_zero) {
                  return $show_zero || $a['calc_balance'] != 0;
              });
              if (empty($filtered)) continue;
              $has_any = true;
              $type_debit = 0;
              $type_credit = 0;
              foreach ($filtered as $a) {
                  $type_debit += $a['debit_balance'];
                  $type_credit += $a['credit_balance'];
              }
          ?>
            <tr class="tb-section">
              <td colspan="4"><i class="fas fa-folder-open mr-2"></i> <?= $type_labels[$type] ?? ucfirst($type) ?></td>
            </tr>
            <?php foreach ($filtered as $a): ?>
              <tr>
                <td><span class="badge badge-secondary status-badge"><?= htmlspecialchars($a['account_code']) ?></span></td>
                <td><?= htmlspecialchars($a['account_name']) ?></td>
                <td class="tb-debit"><?= $a['debit_balance'] > 0 ? formatCurrency($a['debit_balance']) : '-' ?></td>
                <td class="tb-credit"><?= $a['credit_balance'] > 0 ? formatCurrency($a['credit_balance']) : '-' ?></td>
              </tr>
            <?php endforeach; ?>
            <tr class="tb-total">
              <td colspan="2" class="text-right small text-muted">Total <?= $type_labels[$type] ?? ucfirst($type) ?></td>
              <td class="tb-debit"><?= formatCurrency($type_debit) ?></td>
              <td class="tb-credit"><?= formatCurrency($type_credit) ?></td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$has_any): ?>
            <tr>
              <td colspan="4" class="text-center text-muted py-4">
                <i class="fas fa-balance-scale fa-3x mb-3"></i>
                <p>No accounts found.</p>
              </td>
            </tr>
          <?php endif; ?>
        </tbody>
        <tfoot>
          <tr class="tb-total" style="font-size:1rem;">
            <td colspan="2" class="text-right">Grand Total</td>
            <td class="text-right" style="color:#0f172a;"><?= formatCurrency($grand_debit) ?></td>
            <td class="text-right" style="color:#0f172a;"><?= formatCurrency($grand_credit) ?></td>
          </tr>
          <?php if ($grand_debit != $grand_credit): ?>
            <tr class="table-danger">
              <td colspan="4" class="text-center">
                <i class="fas fa-exclamation-triangle"></i>
                Trial balance is out of balance by <?= formatCurrency(abs($grand_debit - $grand_credit)) ?>
              </td>
            </tr>
          <?php else: ?>
            <tr class="table-success">
              <td colspan="4" class="text-center">
                <i class="fas fa-check-circle"></i> Trial balance is in balance
              </td>
            </tr>
          <?php endif; ?>
        </tfoot>
      </table>
    </div>
  </div>
</div>

<?php require_once '../../includes/footer.php'; ?>
