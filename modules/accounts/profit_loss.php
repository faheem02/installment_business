<?php
session_start();
$page_title = 'Profit & Loss Statement';
$base_url = '../../';
require_once '../../includes/functions.php';

$from = $_GET['from'] ?? date('Y-m-01');
$to = $_GET['to'] ?? date('Y-m-d');

require_once '../../includes/header.php';
?>

<style>
.status-badge { font-size: .75rem; padding: 3px 10px; }
.pl-total { background: #e8f0fe !important; font-weight: 700; }
.pl-section { background: #f1f5f9 !important; font-weight: 600; }
.pl-amount { text-align: right; color: #0f172a; }
</style>

<?php
$stmt = $pdo->prepare("
    SELECT ca.*,
           COALESCE(SUM(jei.debit), 0) AS period_debit,
           COALESCE(SUM(jei.credit), 0) AS period_credit
    FROM chart_of_accounts ca
    LEFT JOIN journal_entry_items jei ON jei.account_id = ca.id
    LEFT JOIN journal_entries je ON je.id = jei.journal_id AND je.entry_date BETWEEN ? AND ?
    WHERE ca.status = 1 AND ca.account_type IN ('income','expense')
    GROUP BY ca.id
    ORDER BY FIELD(ca.account_type, 'income','expense'), ca.account_name
");
$stmt->execute([$from, $to]);
$accounts = $stmt->fetchAll();

$income_accounts = [];
$expense_accounts = [];
$total_income = 0;
$total_expense = 0;

foreach ($accounts as $a) {
    $movement = (float)$a['period_debit'] - (float)$a['period_credit'];
    if ($a['account_type'] === 'income') {
        $balance = (float)$a['opening_balance'] - $movement;
        if ($balance != 0) {
            $a['pl_amount'] = $balance;
            $income_accounts[] = $a;
            $total_income += $balance;
        }
    } else {
        $balance = (float)$a['opening_balance'] + $movement;
        if ($balance != 0) {
            $a['pl_amount'] = $balance;
            $expense_accounts[] = $a;
            $total_expense += $balance;
        }
    }
}

$net_profit = $total_income - $total_expense;
?>

<div class="d-flex justify-content-between align-items-center mb-4">
  <h5 class="m-0 font-weight-bold" style="color:#0f172a;"><i class="fas fa-chart-line"></i> Profit & Loss Statement</h5>
</div>

<form method="get" class="form-inline mb-4">
  <label class="mr-2 text-muted small">From:</label>
  <input type="date" name="from" class="form-control form-control-sm mr-3" value="<?= $from ?>">
  <label class="mr-2 text-muted small">To:</label>
  <input type="date" name="to" class="form-control form-control-sm mr-3" value="<?= $to ?>">
  <button type="submit" class="btn btn-sm btn-primary"><i class="fas fa-search"></i> View</button>
  <a href="profit_loss.php" class="btn btn-sm btn-secondary ml-2">This Month</a>
</form>

<div class="row mb-4">
  <div class="col-md-4 mb-3">
    <div class="card border-left-success shadow h-100 py-2">
      <div class="card-body py-2">
        <div class="row no-gutters align-items-center">
          <div class="col mr-2">
            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Total Income</div>
            <div class="h5 mb-0 font-weight-bold" style="color:#0f172a;"><?= formatCurrency($total_income) ?></div>
          </div>
          <div class="col-auto"><i class="fas fa-arrow-down fa-2x text-gray-300"></i></div>
        </div>
      </div>
    </div>
  </div>
  <div class="col-md-4 mb-3">
    <div class="card border-left-danger shadow h-100 py-2">
      <div class="card-body py-2">
        <div class="row no-gutters align-items-center">
          <div class="col mr-2">
            <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">Total Expenses</div>
            <div class="h5 mb-0 font-weight-bold" style="color:#0f172a;"><?= formatCurrency($total_expense) ?></div>
          </div>
          <div class="col-auto"><i class="fas fa-arrow-up fa-2x text-gray-300"></i></div>
        </div>
      </div>
    </div>
  </div>
  <div class="col-md-4 mb-3">
    <div class="card border-left-<?= $net_profit >= 0 ? 'primary' : 'warning' ?> shadow h-100 py-2">
      <div class="card-body py-2">
        <div class="row no-gutters align-items-center">
          <div class="col mr-2">
            <div class="text-xs font-weight-bold text-<?= $net_profit >= 0 ? 'primary' : 'warning' ?> text-uppercase mb-1">
              <?= $net_profit >= 0 ? 'Net Profit' : 'Net Loss' ?>
            </div>
            <div class="h5 mb-0 font-weight-bold" style="color:#0f172a;"><?= formatCurrency(abs($net_profit)) ?></div>
          </div>
          <div class="col-auto"><i class="fas fa-<?= $net_profit >= 0 ? 'chart-line' : 'chart-bar' ?> fa-2x text-gray-300"></i></div>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="row">
  <div class="col-md-6 mb-4">
    <div class="card shadow h-100">
      <div class="card-header py-3 bg-success text-white">
        <h6 class="m-0 font-weight-bold"><i class="fas fa-arrow-down"></i> Income</h6>
      </div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-bordered table-sm mb-0">
            <thead class="thead-light">
              <tr><th>Account</th><th class="text-right">Amount</th></tr>
            </thead>
            <tbody>
              <?php if (empty($income_accounts)): ?>
                <tr><td colspan="2" class="text-center text-muted py-3">No income recorded</td></tr>
              <?php else: ?>
                <?php foreach ($income_accounts as $a): ?>
                  <tr>
                    <td><span class="badge badge-secondary status-badge"><?= htmlspecialchars($a['account_code']) ?></span> <?= htmlspecialchars($a['account_name']) ?></td>
                    <td class="pl-amount"><?= formatCurrency($a['pl_amount']) ?></td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
            <tfoot>
              <tr class="pl-total">
                <td>Total Income</td>
                <td class="pl-amount"><?= formatCurrency($total_income) ?></td>
              </tr>
            </tfoot>
          </table>
        </div>
      </div>
    </div>
  </div>

  <div class="col-md-6 mb-4">
    <div class="card shadow h-100">
      <div class="card-header py-3 bg-danger text-white">
        <h6 class="m-0 font-weight-bold"><i class="fas fa-arrow-up"></i> Expenses</h6>
      </div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-bordered table-sm mb-0">
            <thead class="thead-light">
              <tr><th>Account</th><th class="text-right">Amount</th></tr>
            </thead>
            <tbody>
              <?php if (empty($expense_accounts)): ?>
                <tr><td colspan="2" class="text-center text-muted py-3">No expenses recorded</td></tr>
              <?php else: ?>
                <?php foreach ($expense_accounts as $a): ?>
                  <tr>
                    <td><span class="badge badge-secondary status-badge"><?= htmlspecialchars($a['account_code']) ?></span> <?= htmlspecialchars($a['account_name']) ?></td>
                    <td class="pl-amount"><?= formatCurrency($a['pl_amount']) ?></td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
            <tfoot>
              <tr class="pl-total">
                <td>Total Expenses</td>
                <td class="pl-amount"><?= formatCurrency($total_expense) ?></td>
              </tr>
            </tfoot>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="card shadow mb-4 border-left-<?= $net_profit >= 0 ? 'primary' : 'warning' ?>">
  <div class="card-body text-center py-4">
    <h4 class="font-weight-bold" style="color:#0f172a;">
      <?php if ($net_profit > 0): ?>
        <i class="fas fa-arrow-circle-up text-success"></i> Net Profit: <?= formatCurrency($net_profit) ?>
      <?php elseif ($net_profit < 0): ?>
        <i class="fas fa-arrow-circle-down text-danger"></i> Net Loss: <?= formatCurrency(abs($net_profit)) ?>
      <?php else: ?>
        <i class="fas fa-minus-circle text-muted"></i> Break Even
      <?php endif; ?>
    </h4>
    <p class="text-muted mb-0">For the period <?= formatDate($from) ?> — <?= formatDate($to) ?></p>
  </div>
</div>

<?php require_once '../../includes/footer.php'; ?>
