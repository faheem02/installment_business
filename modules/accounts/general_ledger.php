<?php
session_start();
$page_title = 'General Ledger';
$base_url = '../../';
require_once '../../includes/functions.php';

$from = $_GET['from'] ?? date('Y-m-01');
$to = $_GET['to'] ?? date('Y-m-d');
$account_id = (int)($_GET['account_id'] ?? 0);

require_once '../../includes/header.php';
?>

<style>
.status-badge { font-size: .75rem; padding: 3px 10px; }
.account-link { cursor: pointer; }
.running-balance { font-weight: 600; color: #0f172a; }
</style>

<?php if ($account_id): ?>
<?php
$account = getById('chart_of_accounts', $account_id);
if (!$account) {
    echo '<div class="alert alert-danger">Account not found. <a href="general_ledger.php">Go back</a></div>';
    require_once '../../includes/footer.php';
    exit;
}

$stmt = $pdo->prepare("
    SELECT je.*, jei.debit, jei.credit, jei.description AS item_desc
    FROM journal_entry_items jei
    JOIN journal_entries je ON je.id = jei.journal_id
    WHERE jei.account_id = ? AND je.entry_date BETWEEN ? AND ?
    ORDER BY je.entry_date ASC, je.id ASC
");
$stmt->execute([$account_id, $from, $to]);
$entries = $stmt->fetchAll();

$dr_total = 0; $cr_total = 0;
foreach ($entries as $e) {
    $dr_total += $e['debit'];
    $cr_total += $e['credit'];
}

$normal_debit = in_array($account['account_type'], ['asset','expense']);
$opening_debit = 0; $opening_credit = 0;
if ($normal_debit) {
    $balance = (float)$account['opening_balance'];
    $opening_debit = $balance;
} else {
    $balance = (float)$account['opening_balance'];
    $opening_credit = $balance;
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
  <h5 class="m-0 font-weight-bold" style="color:#0f172a;">
    <i class="fas fa-journal-whills"></i> <?= htmlspecialchars($account['account_name']) ?>
    <small class="text-muted">[<?= htmlspecialchars($account['account_code']) ?>]</small>
  </h5>
  <div>
    <a href="general_ledger.php?from=<?= $from ?>&to=<?= $to ?>" class="btn btn-secondary btn-sm">
      <i class="fas fa-arrow-left"></i> All Accounts
    </a>
  </div>
</div>

<form method="get" class="form-inline mb-4">
  <input type="hidden" name="account_id" value="<?= $account_id ?>">
  <label class="mr-2 text-muted small">From:</label>
  <input type="date" name="from" class="form-control form-control-sm mr-3" value="<?= $from ?>">
  <label class="mr-2 text-muted small">To:</label>
  <input type="date" name="to" class="form-control form-control-sm mr-3" value="<?= $to ?>">
  <button type="submit" class="btn btn-sm btn-primary"><i class="fas fa-search"></i> View</button>
  <a href="general_ledger.php?account_id=<?= $account_id ?>" class="btn btn-sm btn-secondary ml-2">This Month</a>
</form>

<div class="row mb-4">
  <div class="col-md-3 mb-3">
    <div class="card border-left-primary shadow h-100 py-2">
      <div class="card-body py-2">
        <div class="row no-gutters align-items-center">
          <div class="col mr-2">
            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Account Type</div>
            <div class="h5 mb-0 font-weight-bold" style="color:#0f172a;">
              <span class="badge badge-info status-badge"><?= ucfirst($account['account_type']) ?></span>
            </div>
          </div>
          <div class="col-auto"><i class="fas fa-tag fa-2x text-gray-300"></i></div>
        </div>
      </div>
    </div>
  </div>
  <div class="col-md-3 mb-3">
    <div class="card border-left-success shadow h-100 py-2">
      <div class="card-body py-2">
        <div class="row no-gutters align-items-center">
          <div class="col mr-2">
            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Total Debit</div>
            <div class="h5 mb-0 font-weight-bold" style="color:#0f172a;"><?= formatCurrency($dr_total) ?></div>
          </div>
          <div class="col-auto"><i class="fas fa-arrow-left fa-2x text-gray-300"></i></div>
        </div>
      </div>
    </div>
  </div>
  <div class="col-md-3 mb-3">
    <div class="card border-left-danger shadow h-100 py-2">
      <div class="card-body py-2">
        <div class="row no-gutters align-items-center">
          <div class="col mr-2">
            <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">Total Credit</div>
            <div class="h5 mb-0 font-weight-bold" style="color:#0f172a;"><?= formatCurrency($cr_total) ?></div>
          </div>
          <div class="col-auto"><i class="fas fa-arrow-right fa-2x text-gray-300"></i></div>
        </div>
      </div>
    </div>
  </div>
  <div class="col-md-3 mb-3">
    <div class="card border-left-warning shadow h-100 py-2">
      <div class="card-body py-2">
        <div class="row no-gutters align-items-center">
          <div class="col mr-2">
            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Current Balance</div>
            <div class="h5 mb-0 font-weight-bold" style="color:#0f172a;">
              <?= formatCurrency((float)$account['current_balance']) ?>
            </div>
          </div>
          <div class="col-auto"><i class="fas fa-calculator fa-2x text-gray-300"></i></div>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="card shadow mb-4">
  <div class="card-header py-3">
    <h6 class="m-0 font-weight-bold text-primary">
      <i class="fas fa-list"></i> Journal Entries
      <span class="text-muted">(<?= count($entries) ?> items)</span>
    </h6>
  </div>
  <div class="card-body">
    <?php if (empty($entries)): ?>
      <div class="text-center text-muted py-3">
        <i class="fas fa-exchange-alt fa-3x mb-3"></i>
        <p>No journal entries for this period.</p>
      </div>
    <?php else: ?>
      <div class="table-responsive">
        <table class="table table-bordered table-hover table-sm" width="100%" cellspacing="0">
          <thead class="thead-light">
            <tr>
              <th>Date</th>
              <th>Reference</th>
              <th>Description</th>
              <th class="text-right">Debit</th>
              <th class="text-right">Credit</th>
              <th class="text-right">Balance</th>
            </tr>
          </thead>
          <tbody>
            <tr class="table-secondary">
              <td colspan="3"><strong>Opening Balance</strong></td>
              <td class="text-right"><?= $opening_debit > 0 ? formatCurrency($opening_debit) : '-' ?></td>
              <td class="text-right"><?= $opening_credit > 0 ? formatCurrency($opening_credit) : '-' ?></td>
              <td class="text-right font-weight-bold" style="color:#0f172a;"><?= formatCurrency($balance) ?></td>
            </tr>
            <?php foreach ($entries as $e): ?>
              <?php
              if ($normal_debit) {
                  $balance += $e['debit'] - $e['credit'];
              } else {
                  $balance += $e['credit'] - $e['debit'];
              }
              ?>
              <tr>
                <td><?= formatDate($e['entry_date']) ?></td>
                <td>
                  <?php if ($e['reference_type']): ?>
                    <small class="text-muted"><?= htmlspecialchars(ucfirst($e['reference_type'])) ?> #<?= $e['reference_id'] ?></small>
                  <?php else: ?>
                    <span class="text-muted">-</span>
                  <?php endif; ?>
                </td>
                <td><?= htmlspecialchars($e['item_desc'] ?? $e['description'] ?? '-') ?></td>
                <td class="text-right"><?= $e['debit'] > 0 ? formatCurrency($e['debit']) : '-' ?></td>
                <td class="text-right"><?= $e['credit'] > 0 ? formatCurrency($e['credit']) : '-' ?></td>
                <td class="text-right running-balance"><?= formatCurrency($balance) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>

<?php else: ?>
<?php
$stmt = $pdo->prepare("
    SELECT ca.*,
           COALESCE(SUM(jei.debit), 0) AS period_debit,
           COALESCE(SUM(jei.credit), 0) AS period_credit
    FROM chart_of_accounts ca
    LEFT JOIN journal_entry_items jei ON jei.account_id = ca.id
    LEFT JOIN journal_entries je ON je.id = jei.journal_id AND je.entry_date BETWEEN ? AND ?
    WHERE ca.status = 1
    GROUP BY ca.id
    ORDER BY ca.account_code
");
$stmt->execute([$from, $to]);
$accounts = $stmt->fetchAll();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
  <h5 class="m-0 font-weight-bold" style="color:#0f172a;"><i class="fas fa-journal-whills"></i> General Ledger</h5>
  <div>
    <button class="btn btn-primary btn-sm" data-toggle="modal" data-target="#addAccountModal"><i class="fas fa-plus"></i> Add Account</button>
  </div>
</div>

<form method="get" class="form-inline mb-4">
  <label class="mr-2 text-muted small">From:</label>
  <input type="date" name="from" class="form-control form-control-sm mr-3" value="<?= $from ?>">
  <label class="mr-2 text-muted small">To:</label>
  <input type="date" name="to" class="form-control form-control-sm mr-3" value="<?= $to ?>">
  <button type="submit" class="btn btn-sm btn-primary"><i class="fas fa-search"></i> View</button>
  <a href="general_ledger.php" class="btn btn-sm btn-secondary ml-2">This Month</a>
</form>

<div class="row mb-4">
  <div class="col-md-3 mb-3">
    <div class="card border-left-primary shadow h-100 py-2">
      <div class="card-body py-2">
        <div class="row no-gutters align-items-center">
          <div class="col mr-2">
            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Total Accounts</div>
            <div class="h5 mb-0 font-weight-bold" style="color:#0f172a;"><?= count($accounts) ?></div>
          </div>
          <div class="col-auto"><i class="fas fa-book fa-2x text-gray-300"></i></div>
        </div>
      </div>
    </div>
  </div>
  <?php
  $total_debit = 0; $total_credit = 0; $total_balance = 0;
  foreach ($accounts as $a) {
      $bal = (float)$a['current_balance'];
      $total_balance += $bal;
      $total_debit += (float)$a['period_debit'];
      $total_credit += (float)$a['period_credit'];
  }
  ?>
  <div class="col-md-3 mb-3">
    <div class="card border-left-success shadow h-100 py-2">
      <div class="card-body py-2">
        <div class="row no-gutters align-items-center">
          <div class="col mr-2">
            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Period Debit</div>
            <div class="h5 mb-0 font-weight-bold" style="color:#0f172a;"><?= formatCurrency($total_debit) ?></div>
          </div>
          <div class="col-auto"><i class="fas fa-arrow-left fa-2x text-gray-300"></i></div>
        </div>
      </div>
    </div>
  </div>
  <div class="col-md-3 mb-3">
    <div class="card border-left-danger shadow h-100 py-2">
      <div class="card-body py-2">
        <div class="row no-gutters align-items-center">
          <div class="col mr-2">
            <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">Period Credit</div>
            <div class="h5 mb-0 font-weight-bold" style="color:#0f172a;"><?= formatCurrency($total_credit) ?></div>
          </div>
          <div class="col-auto"><i class="fas fa-arrow-right fa-2x text-gray-300"></i></div>
        </div>
      </div>
    </div>
  </div>
  <div class="col-md-3 mb-3">
    <div class="card border-left-warning shadow h-100 py-2">
      <div class="card-body py-2">
        <div class="row no-gutters align-items-center">
          <div class="col mr-2">
            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Total Balance</div>
            <div class="h5 mb-0 font-weight-bold" style="color:#0f172a;"><?= formatCurrency($total_balance) ?></div>
          </div>
          <div class="col-auto"><i class="fas fa-calculator fa-2x text-gray-300"></i></div>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="card shadow mb-4">
  <div class="card-header py-3">
    <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-list"></i> Chart of Accounts</h6>
  </div>
  <div class="card-body">
    <?php if (empty($accounts)): ?>
      <div class="text-center text-muted py-3">
        <i class="fas fa-book fa-3x mb-3"></i>
        <p>No accounts found. Add an account to get started.</p>
      </div>
    <?php else: ?>
      <div class="table-responsive">
        <table class="table table-bordered table-hover table-sm" width="100%" cellspacing="0">
          <thead class="thead-light">
            <tr>
              <th>Code</th>
              <th>Account Name</th>
              <th>Type</th>
              <th class="text-right">Opening Balance</th>
              <th class="text-right">Period Debit</th>
              <th class="text-right">Period Credit</th>
              <th class="text-right">Current Balance</th>
              <th class="text-center">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($accounts as $a): ?>
              <tr>
                <td><span class="badge badge-secondary"><?= htmlspecialchars($a['account_code']) ?></span></td>
                <td>
                  <a href="general_ledger.php?account_id=<?= $a['id'] ?>&from=<?= $from ?>&to=<?= $to ?>" class="font-weight-bold" style="color:#0f172a;">
                    <?= htmlspecialchars($a['account_name']) ?>
                  </a>
                </td>
                <td>
                  <?php
                  $type_colors = [
                      'asset' => 'info',
                      'liability' => 'warning',
                      'equity' => 'primary',
                      'income' => 'success',
                      'expense' => 'danger',
                  ];
                  $badge = $type_colors[$a['account_type']] ?? 'secondary';
                  ?>
                  <span class="badge badge-<?= $badge ?> status-badge"><?= ucfirst($a['account_type']) ?></span>
                </td>
                <td class="text-right"><?= formatCurrency($a['opening_balance']) ?></td>
                <td class="text-right"><?= formatCurrency($a['period_debit']) ?></td>
                <td class="text-right"><?= formatCurrency($a['period_credit']) ?></td>
                <td class="text-right font-weight-bold" style="color:#0f172a;"><?= formatCurrency($a['current_balance']) ?></td>
                <td class="text-center">
                  <a href="general_ledger.php?account_id=<?= $a['id'] ?>&from=<?= $from ?>&to=<?= $to ?>" class="btn btn-sm btn-outline-primary" title="View Ledger">
                    <i class="fas fa-eye"></i>
                  </a>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>

<!-- Add Account Modal -->
<div class="modal fade" id="addAccountModal" tabindex="-1" role="dialog">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <form method="post" action="add_account.php">
        <div class="modal-header">
          <h6 class="modal-title"><i class="fas fa-plus-circle text-primary"></i> Add Account</h6>
          <button type="button" class="close" data-dismiss="modal">&times;</button>
        </div>
        <div class="modal-body">
          <div class="row">
            <div class="col-md-6 form-group">
              <label class="small">Account Code</label>
              <input type="text" name="account_code" class="form-control" placeholder="e.g. 1-1000" required>
            </div>
            <div class="col-md-6 form-group">
              <label class="small">Account Type</label>
              <select name="account_type" class="form-control" required>
                <option value="asset">Asset</option>
                <option value="liability">Liability</option>
                <option value="equity">Equity</option>
                <option value="income">Income</option>
                <option value="expense">Expense</option>
              </select>
            </div>
          </div>
          <div class="form-group">
            <label class="small">Account Name</label>
            <input type="text" name="account_name" class="form-control" placeholder="e.g. Cash on Hand" required>
          </div>
          <div class="form-group">
            <label class="small">Description</label>
            <textarea name="description" class="form-control" rows="2"></textarea>
          </div>
          <div class="row">
            <div class="col-md-6 form-group">
              <label class="small">Opening Balance</label>
              <input type="number" name="opening_balance" class="form-control" step="0.01" value="0">
            </div>
            <div class="col-md-6 form-group">
              <label class="small">Parent Account</label>
              <select name="parent_id" class="form-control">
                <option value="">None (Top Level)</option>
                <?php
                $parents = $pdo->query("SELECT id, account_code, account_name FROM chart_of_accounts ORDER BY account_code")->fetchAll();
                foreach ($parents as $p):
                ?>
                  <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['account_code'] . ' - ' . $p['account_name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-save"></i> Save Account</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php endif; ?>

<?php require_once '../../includes/footer.php'; ?>
