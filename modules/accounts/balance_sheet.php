<?php
session_start();
$page_title = 'Balance Sheet';
$base_url = '../../';
require_once '../../includes/functions.php';

$as_of = $_GET['as_of'] ?? date('Y-m-d');

require_once '../../includes/header.php';
?>

<style>
.status-badge { font-size: .75rem; padding: 3px 10px; }
.bs-total { background: #e8f0fe !important; font-weight: 700; }
.bs-section { background: #f1f5f9 !important; font-weight: 600; }
.bs-amount { text-align: right; color: #0f172a; }
.bs-sub { padding-left: 2rem !important; }
</style>

<?php
// Fetch all active accounts with their journal totals up to as_of date
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
$all_accounts = $stmt->fetchAll();

$type_labels = [
    'asset' => 'Assets',
    'liability' => 'Liabilities',
    'equity' => 'Equity',
    'income' => 'Income',
    'expense' => 'Expenses',
];

// Calculate balance for each account based on normal balance direction
$asset_accounts = [];
$liability_accounts = [];
$equity_accounts = [];

// Also track income/expense for net profit calculation
$total_income = 0;
$total_expense = 0;

foreach ($all_accounts as $a) {
    $normal_debit = in_array($a['account_type'], ['asset', 'expense']);
    $movement = (float)$a['total_debit'] - (float)$a['total_credit'];
    if ($normal_debit) {
        $balance = (float)$a['opening_balance'] + $movement;
    } else {
        $balance = (float)$a['opening_balance'] - $movement;
    }
    $a['calc_balance'] = $balance;
    $a['abs_balance'] = abs($balance);

    if ($balance == 0) continue;

    switch ($a['account_type']) {
        case 'asset':
            $asset_accounts[] = $a;
            break;
        case 'liability':
            $liability_accounts[] = $a;
            break;
        case 'equity':
            $equity_accounts[] = $a;
            break;
        case 'income':
            $total_income += $balance;
            break;
        case 'expense':
            $total_expense += $balance;
            break;
    }
}

$net_profit = $total_income - $total_expense;

$total_assets = array_sum(array_column($asset_accounts, 'calc_balance'));
$total_liabilities = array_sum(array_column($liability_accounts, 'calc_balance'));
$total_equity = array_sum(array_column($equity_accounts, 'calc_balance')) + $net_profit;
?>

<div class="d-flex justify-content-between align-items-center mb-4">
  <h5 class="m-0 font-weight-bold" style="color:#0f172a;"><i class="fas fa-file-alt"></i> Balance Sheet</h5>
</div>

<form method="get" class="form-inline mb-4">
  <label class="mr-2 text-muted small">As of:</label>
  <input type="date" name="as_of" class="form-control form-control-sm mr-3" value="<?= $as_of ?>">
  <button type="submit" class="btn btn-sm btn-primary"><i class="fas fa-search"></i> View</button>
  <a href="balance_sheet.php" class="btn btn-sm btn-secondary ml-2">Today</a>
</form>

<div class="row mb-4">
  <div class="col-md-4 mb-3">
    <div class="card border-left-primary shadow h-100 py-2">
      <div class="card-body py-2">
        <div class="row no-gutters align-items-center">
          <div class="col mr-2">
            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Total Assets</div>
            <div class="h5 mb-0 font-weight-bold" style="color:#0f172a;"><?= formatCurrency($total_assets) ?></div>
          </div>
          <div class="col-auto"><i class="fas fa-building fa-2x text-gray-300"></i></div>
        </div>
      </div>
    </div>
  </div>
  <div class="col-md-4 mb-3">
    <div class="card border-left-info shadow h-100 py-2">
      <div class="card-body py-2">
        <div class="row no-gutters align-items-center">
          <div class="col mr-2">
            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Total Liabilities</div>
            <div class="h5 mb-0 font-weight-bold" style="color:#0f172a;"><?= formatCurrency($total_liabilities) ?></div>
          </div>
          <div class="col-auto"><i class="fas fa-credit-card fa-2x text-gray-300"></i></div>
        </div>
      </div>
    </div>
  </div>
  <div class="col-md-4 mb-3">
    <div class="card border-left-success shadow h-100 py-2">
      <div class="card-body py-2">
        <div class="row no-gutters align-items-center">
          <div class="col mr-2">
            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Total Equity</div>
            <div class="h5 mb-0 font-weight-bold" style="color:#0f172a;"><?= formatCurrency($total_equity) ?></div>
          </div>
          <div class="col-auto"><i class="fas fa-chart-pie fa-2x text-gray-300"></i></div>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="row">
  <!-- Assets Column -->
  <div class="col-md-6 mb-4">
    <div class="card shadow h-100">
      <div class="card-header py-3 bg-primary text-white">
        <h6 class="m-0 font-weight-bold"><i class="fas fa-building"></i> Assets</h6>
      </div>
      <div class="card-body p-0">
        <table class="table table-bordered table-sm mb-0">
          <thead class="thead-light">
            <tr><th>Account</th><th class="text-right">Amount</th></tr>
          </thead>
          <tbody>
            <?php if (empty($asset_accounts)): ?>
              <tr><td colspan="2" class="text-center text-muted py-3">No assets recorded</td></tr>
            <?php else: ?>
              <?php foreach ($asset_accounts as $a): ?>
                <tr>
                  <td><span class="badge badge-secondary status-badge"><?= htmlspecialchars($a['account_code']) ?></span> <?= htmlspecialchars($a['account_name']) ?></td>
                  <td class="bs-amount"><?= formatCurrency($a['calc_balance']) ?></td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
          <tfoot>
            <tr class="bs-total">
              <td>Total Assets</td>
              <td class="bs-amount"><?= formatCurrency($total_assets) ?></td>
            </tr>
          </tfoot>
        </table>
      </div>
    </div>
  </div>

  <!-- Liabilities & Equity Column -->
  <div class="col-md-6 mb-4">
    <div class="card shadow mb-4">
      <div class="card-header py-3 bg-info text-white">
        <h6 class="m-0 font-weight-bold"><i class="fas fa-credit-card"></i> Liabilities</h6>
      </div>
      <div class="card-body p-0">
        <table class="table table-bordered table-sm mb-0">
          <thead class="thead-light">
            <tr><th>Account</th><th class="text-right">Amount</th></tr>
          </thead>
          <tbody>
            <?php if (empty($liability_accounts)): ?>
              <tr><td colspan="2" class="text-center text-muted py-3">No liabilities recorded</td></tr>
            <?php else: ?>
              <?php foreach ($liability_accounts as $a): ?>
                <tr>
                  <td><span class="badge badge-secondary status-badge"><?= htmlspecialchars($a['account_code']) ?></span> <?= htmlspecialchars($a['account_name']) ?></td>
                  <td class="bs-amount"><?= formatCurrency($a['calc_balance']) ?></td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
          <tfoot>
            <tr class="bs-total">
              <td>Total Liabilities</td>
              <td class="bs-amount"><?= formatCurrency($total_liabilities) ?></td>
            </tr>
          </tfoot>
        </table>
      </div>
    </div>

    <div class="card shadow">
      <div class="card-header py-3 bg-success text-white">
        <h6 class="m-0 font-weight-bold"><i class="fas fa-chart-pie"></i> Equity</h6>
      </div>
      <div class="card-body p-0">
        <table class="table table-bordered table-sm mb-0">
          <thead class="thead-light">
            <tr><th>Account</th><th class="text-right">Amount</th></tr>
          </thead>
          <tbody>
            <?php foreach ($equity_accounts as $a): ?>
              <tr>
                <td><span class="badge badge-secondary status-badge"><?= htmlspecialchars($a['account_code']) ?></span> <?= htmlspecialchars($a['account_name']) ?></td>
                <td class="bs-amount"><?= formatCurrency($a['calc_balance']) ?></td>
              </tr>
            <?php endforeach; ?>
            <?php if ($net_profit != 0): ?>
              <tr>
                <td><span class="badge badge-<?= $net_profit > 0 ? 'success' : 'danger' ?> status-badge">PL</span> Retained Earnings (Current Period)</td>
                <td class="bs-amount"><?= formatCurrency($net_profit) ?></td>
              </tr>
            <?php endif; ?>
            <?php if (empty($equity_accounts) && $net_profit == 0): ?>
              <tr><td colspan="2" class="text-center text-muted py-3">No equity recorded</td></tr>
            <?php endif; ?>
          </tbody>
          <tfoot>
            <tr class="bs-total">
              <td>Total Equity</td>
              <td class="bs-amount"><?= formatCurrency($total_equity) ?></td>
            </tr>
          </tfoot>
        </table>
      </div>
    </div>
  </div>
</div>

<!-- Balance Check -->
<?php $diff = abs($total_assets - ($total_liabilities + $total_equity)); ?>
<div class="card shadow mb-4 border-left-<?= $diff < 0.01 ? 'success' : 'danger' ?>">
  <div class="card-body text-center py-3">
    <h5 class="font-weight-bold" style="color:#0f172a;">
      <?php if ($diff < 0.01): ?>
        <i class="fas fa-check-circle text-success"></i> Balance Sheet is Balanced
      <?php else: ?>
        <i class="fas fa-exclamation-triangle text-danger"></i> Out of Balance by <?= formatCurrency($diff) ?>
      <?php endif; ?>
    </h5>
    <p class="text-muted mb-0 small">
      Assets (<?= formatCurrency($total_assets) ?>) = Liabilities (<?= formatCurrency($total_liabilities) ?>) + Equity (<?= formatCurrency($total_equity) ?>)
    </p>
  </div>
</div>

<?php require_once '../../includes/footer.php'; ?>
