<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
  <title><?= $page_title ?? 'Dashboard' ?> | Installment Business</title>

  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/4.6.2/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="<?= $base_url ?? '' ?>assets/css/style.css">
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body id="page-top">

<!-- Sidebar Backdrop (mobile) -->
<div class="sidebar-backdrop" id="sidebarBackdrop"></div>

<div id="wrapper">

  <!-- ===== SIDEBAR ===== -->
  <nav class="sidebar" id="sidebar">

    <a class="sidebar-brand" href="<?= $base_url ?? '' ?>index.php">
      <div class="sidebar-brand-icon"><i class="fas fa-handshake"></i></div>
      <span class="sidebar-brand-text">Installment Business</span>
    </a>

    <hr class="sidebar-divider">

    <!-- Dashboard -->
    <div class="nav-item <?= basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : '' ?>">
      <a class="nav-link" href="<?= $base_url ?? '' ?>index.php">
        <i class="fas fa-fw fa-tachometer-alt"></i>
        <span>Dashboard</span>
      </a>
    </div>

    <hr class="sidebar-divider">

    <div class="sidebar-heading"><span>Management</span></div>

    <!-- Customer Management -->
    <div class="nav-item">
      <?php $on_customer_page = str_contains($_SERVER['PHP_SELF'],'customers/') || str_contains($_SERVER['PHP_SELF'],'accounts/customer_ledger'); ?>
      <a class="nav-link <?= $on_customer_page ? '' : 'collapsed' ?>" data-toggle="collapse" href="#collapseCustomer" role="button" aria-expanded="<?= $on_customer_page ? 'true' : 'false' ?>">
        <i class="fas fa-fw fa-users"></i>
        <span>Customer Management</span>
        <span class="arrow"><i class="fas fa-chevron-right"></i></span>
      </a>
      <div class="collapse <?= $on_customer_page ? 'show' : '' ?>" id="collapseCustomer">
        <div class="collapse-inner">
          <a class="collapse-item <?= str_contains($_SERVER['PHP_SELF'],'customers/create.php') ? 'active' : '' ?>" href="<?= $base_url ?? '' ?>modules/customers/create.php">
            <i class="fas fa-user-plus"></i> Customer Registration
          </a>
          <a class="collapse-item <?= str_contains($_SERVER['PHP_SELF'],'customers/index.php') || str_contains($_SERVER['PHP_SELF'],'customers/edit.php') || str_contains($_SERVER['PHP_SELF'],'customers/view.php') ? 'active' : '' ?>" href="<?= $base_url ?? '' ?>modules/customers/index.php">
            <i class="fas fa-address-card"></i> View Customers
          </a>
          <a class="collapse-item <?= str_contains($_SERVER['PHP_SELF'],'accounts/customer_ledger') ? 'active' : '' ?>" href="<?= $base_url ?? '' ?>modules/accounts/customer_ledger.php">
            <i class="fas fa-book"></i> Customer Ledger
          </a>
          <a class="collapse-item <?= str_contains($_SERVER['PHP_SELF'],'customers/cnic.php') ? 'active' : '' ?>" href="<?= $base_url ?? '' ?>modules/customers/cnic.php">
            <i class="fas fa-id-card"></i> CNIC Records
          </a>
          <a class="collapse-item <?= str_contains($_SERVER['PHP_SELF'],'customers/contacts.php') ? 'active' : '' ?>" href="<?= $base_url ?? '' ?>modules/customers/contacts.php">
            <i class="fas fa-address-book"></i> Contacts
          </a>
          <a class="collapse-item <?= str_contains($_SERVER['PHP_SELF'],'customers/guarantors.php') ? 'active' : '' ?>" href="<?= $base_url ?? '' ?>modules/customers/guarantors.php">
            <i class="fas fa-handshake"></i> Guarantors
          </a>
        </div>
      </div>
    </div>

    <!-- Sales & Billing -->
    <div class="nav-item">
      <a class="nav-link" data-toggle="collapse" href="#collapseSales" role="button" aria-expanded="false">
        <i class="fas fa-fw fa-shopping-cart"></i>
        <span>Sales &amp; Billing</span>
        <span class="arrow"><i class="fas fa-chevron-right"></i></span>
      </a>
      <div class="collapse" id="collapseSales">
        <div class="collapse-inner">
          <a class="collapse-item <?= str_contains($_SERVER['PHP_SELF'],'sales/index.php') ? 'active' : '' ?>" href="<?= $base_url ?? '' ?>modules/sales/index.php"><i class="fas fa-plus-circle"></i> New Sale</a>
          <a class="collapse-item <?= str_contains($_SERVER['PHP_SELF'],'sales/invoices') ? 'active' : '' ?>" href="<?= $base_url ?? '' ?>modules/sales/invoices.php"><i class="fas fa-file-invoice"></i> Invoices</a>
          <a class="collapse-item <?= str_contains($_SERVER['PHP_SELF'],'sales/discount') ? 'active' : '' ?>" href="<?= $base_url ?? '' ?>modules/sales/discounts.php"><i class="fas fa-percent"></i> Discounts</a>
        </div>
      </div>
    </div>

    <!-- Installment Management -->
    <div class="nav-item">
      <a class="nav-link" data-toggle="collapse" href="#collapseInstallment" role="button" aria-expanded="false">
        <i class="fas fa-fw fa-calendar-check"></i>
        <span>Installment Management</span>
        <span class="arrow"><i class="fas fa-chevron-right"></i></span>
      </a>
      <div class="collapse" id="collapseInstallment">
        <div class="collapse-inner">
          <a class="collapse-item <?= str_contains($_SERVER['PHP_SELF'],'installments/plan') ? 'active' : '' ?>" href="<?= $base_url ?? '' ?>modules/installments/plans.php"><i class="fas fa-table"></i> Plans</a>
          <a class="collapse-item <?= str_contains($_SERVER['PHP_SELF'],'installments/down_payments') ? 'active' : '' ?>" href="<?= $base_url ?? '' ?>modules/installments/down_payments.php"><i class="fas fa-hand-holding-usd"></i> Down Payments</a>
          <a class="collapse-item <?= str_contains($_SERVER['PHP_SELF'],'installments/schedules') ? 'active' : '' ?>" href="<?= $base_url ?? '' ?>modules/installments/schedules.php"><i class="fas fa-calendar-alt"></i> Schedules & Balance</a>
          <a class="collapse-item <?= str_contains($_SERVER['PHP_SELF'],'installments/late') ? 'active' : '' ?>" href="<?= $base_url ?? '' ?>modules/installments/late_payments.php"><i class="fas fa-exclamation-triangle"></i> Late Payments</a>
        </div>
      </div>
    </div>

    <!-- Inventory Management -->
    <div class="nav-item">
      <a class="nav-link" data-toggle="collapse" href="#collapseInventory" role="button" aria-expanded="false">
        <i class="fas fa-fw fa-boxes"></i>
        <span>Inventory Management</span>
        <span class="arrow"><i class="fas fa-chevron-right"></i></span>
      </a>
      <div class="collapse" id="collapseInventory">
        <div class="collapse-inner">
          <a class="collapse-item <?= str_contains($_SERVER['PHP_SELF'],'inventory/products.php') ? 'active' : '' ?>" href="<?= $base_url ?? '' ?>modules/inventory/products.php"><i class="fas fa-box"></i> Products</a>
          <a class="collapse-item <?= str_contains($_SERVER['PHP_SELF'],'inventory/purchases') ? 'active' : '' ?>" href="<?= $base_url ?? '' ?>modules/inventory/purchases.php"><i class="fas fa-truck"></i> Purchases</a>
          <a class="collapse-item <?= str_contains($_SERVER['PHP_SELF'],'inventory/categor') ? 'active' : '' ?>" href="<?= $base_url ?? '' ?>modules/inventory/categories.php"><i class="fas fa-tags"></i> Categories</a>
          <a class="collapse-item <?= str_contains($_SERVER['PHP_SELF'],'inventory/brand') ? 'active' : '' ?>" href="<?= $base_url ?? '' ?>modules/inventory/brands.php"><i class="fas fa-copyright"></i> Brands</a>
        </div>
      </div>
    </div>

    <!-- Suppliers -->
    <div class="nav-item">
      <?php $on_supplier_page = str_contains($_SERVER['PHP_SELF'],'inventory/supplier') || str_contains($_SERVER['PHP_SELF'],'accounts/supplier_ledger'); ?>
      <a class="nav-link <?= $on_supplier_page ? '' : 'collapsed' ?>" data-toggle="collapse" href="#collapseSupplier" role="button" aria-expanded="<?= $on_supplier_page ? 'true' : 'false' ?>">
        <i class="fas fa-fw fa-truck-loading"></i>
        <span>Suppliers</span>
        <span class="arrow"><i class="fas fa-chevron-right"></i></span>
      </a>
      <div class="collapse <?= $on_supplier_page ? 'show' : '' ?>" id="collapseSupplier">
        <div class="collapse-inner">
          <a class="collapse-item <?= str_contains($_SERVER['PHP_SELF'],'inventory/supplier_create') ? 'active' : '' ?>" href="<?= $base_url ?? '' ?>modules/inventory/supplier_create.php"><i class="fas fa-plus-circle"></i> Add Supplier</a>
          <a class="collapse-item <?= str_contains($_SERVER['PHP_SELF'],'inventory/suppliers.php') ? 'active' : '' ?>" href="<?= $base_url ?? '' ?>modules/inventory/suppliers.php"><i class="fas fa-list"></i> View Suppliers</a>
          <a class="collapse-item <?= str_contains($_SERVER['PHP_SELF'],'accounts/supplier_ledger') ? 'active' : '' ?>" href="<?= $base_url ?? '' ?>modules/accounts/supplier_ledger.php"><i class="fas fa-truck"></i> Supplier Ledger</a>
          <a class="collapse-item <?= str_contains($_SERVER['PHP_SELF'],'inventory/supplier_payments') ? 'active' : '' ?>" href="<?= $base_url ?? '' ?>modules/inventory/supplier_payments.php"><i class="fas fa-money-bill-wave"></i> Supplier Payments</a>
        </div>
      </div>
    </div>

    <!-- Payments -->
    <div class="nav-item">
      <a class="nav-link" data-toggle="collapse" href="#collapsePayment" role="button" aria-expanded="false">
        <i class="fas fa-fw fa-wallet"></i>
        <span>Payment Collection</span>
        <span class="arrow"><i class="fas fa-chevron-right"></i></span>
      </a>
      <div class="collapse" id="collapsePayment">
        <div class="collapse-inner">
          <a class="collapse-item <?= str_contains($_SERVER['PHP_SELF'],'payments/index') ? 'active' : '' ?>" href="<?= $base_url ?? '' ?>modules/payments/index.php"><i class="fas fa-receipt"></i> Receipts</a>
          <a class="collapse-item <?= str_contains($_SERVER['PHP_SELF'],'daily') ? 'active' : '' ?>" href="<?= $base_url ?? '' ?>modules/payments/daily.php"><i class="fas fa-coins"></i> Daily Collection</a>
        </div>
      </div>
    </div>

    <hr class="sidebar-divider">

    <div class="sidebar-heading"><span>Finance</span></div>

    <!-- Cash Book -->
    <div class="nav-item">
      <a class="nav-link <?= str_contains($_SERVER['PHP_SELF'],'cashbook') ? 'active' : '' ?>" href="<?= $base_url ?? '' ?>modules/cashbook/index.php">
        <i class="fas fa-fw fa-money-bill-wave"></i>
        <span>Cash Book</span>
      </a>
    </div>

    <!-- Bank Book -->
    <div class="nav-item">
      <a class="nav-link <?= str_contains($_SERVER['PHP_SELF'],'bankbook') ? 'active' : '' ?>" href="<?= $base_url ?? '' ?>modules/bankbook/index.php">
        <i class="fas fa-fw fa-university"></i>
        <span>Bank Book</span>
      </a>
    </div>

    <!-- Expenses -->
    <div class="nav-item">
      <a class="nav-link <?= str_contains($_SERVER['PHP_SELF'],'expenses') ? '' : 'collapsed' ?>" data-toggle="collapse" href="#collapseExpense" role="button" aria-expanded="<?= str_contains($_SERVER['PHP_SELF'],'expenses') ? 'true' : 'false' ?>">
        <i class="fas fa-fw fa-file-invoice-dollar"></i>
        <span>Expenses</span>
        <span class="arrow"><i class="fas fa-chevron-right"></i></span>
      </a>
      <div class="collapse <?= str_contains($_SERVER['PHP_SELF'],'expenses') ? 'show' : '' ?>" id="collapseExpense">
        <div class="collapse-inner">
          <a class="collapse-item <?= str_contains($_SERVER['PHP_SELF'],'expenses/index') ? 'active' : '' ?>" href="<?= $base_url ?? '' ?>modules/expenses/index.php"><i class="fas fa-file-invoice-dollar"></i> All Expenses</a>
          <a class="collapse-item <?= str_contains($_SERVER['PHP_SELF'],'expenses/categories') ? 'active' : '' ?>" href="<?= $base_url ?? '' ?>modules/expenses/categories.php"><i class="fas fa-tags"></i> Categories</a>
        </div>
      </div>
    </div>

    <!-- Accounts -->
    <div class="nav-item">
      <a class="nav-link <?= str_contains($_SERVER['PHP_SELF'],'accounts') ? '' : 'collapsed' ?>" data-toggle="collapse" href="#collapseAccounts" role="button" aria-expanded="<?= str_contains($_SERVER['PHP_SELF'],'accounts') ? 'true' : 'false' ?>">
        <i class="fas fa-fw fa-book"></i>
        <span>Accounts & Finance</span>
        <span class="arrow"><i class="fas fa-chevron-right"></i></span>
      </a>
      <div class="collapse <?= str_contains($_SERVER['PHP_SELF'],'accounts') ? 'show' : '' ?>" id="collapseAccounts">
        <div class="collapse-inner">
          <a class="collapse-item <?= str_contains($_SERVER['PHP_SELF'],'accounts/general_ledger') ? 'active' : '' ?>" href="<?= $base_url ?? '' ?>modules/accounts/general_ledger.php"><i class="fas fa-journal-whills"></i> General Ledger</a>
          <a class="collapse-item <?= str_contains($_SERVER['PHP_SELF'],'accounts/trial_balance') ? 'active' : '' ?>" href="<?= $base_url ?? '' ?>modules/accounts/trial_balance.php"><i class="fas fa-balance-scale"></i> Trial Balance</a>
          <a class="collapse-item <?= str_contains($_SERVER['PHP_SELF'],'accounts/profit_loss') ? 'active' : '' ?>" href="<?= $base_url ?? '' ?>modules/accounts/profit_loss.php"><i class="fas fa-chart-line"></i> P&L Statement</a>
          <a class="collapse-item <?= str_contains($_SERVER['PHP_SELF'],'accounts/balance_sheet') ? 'active' : '' ?>" href="<?= $base_url ?? '' ?>modules/accounts/balance_sheet.php"><i class="fas fa-file-alt"></i> Balance Sheet</a>
        </div>
      </div>
    </div>

    <hr class="sidebar-divider">

    <div class="sidebar-heading"><span>Others</span></div>

    <!-- Reports -->
    <div class="nav-item">
      <a class="nav-link <?= str_contains($_SERVER['PHP_SELF'],'reports') ? '' : 'collapsed' ?>" data-toggle="collapse" href="#collapseReports" role="button" aria-expanded="<?= str_contains($_SERVER['PHP_SELF'],'reports') ? 'true' : 'false' ?>">
        <i class="fas fa-fw fa-chart-bar"></i>
        <span>Reports</span>
        <span class="arrow"><i class="fas fa-chevron-right"></i></span>
      </a>
      <div class="collapse <?= str_contains($_SERVER['PHP_SELF'],'reports') ? 'show' : '' ?>" id="collapseReports">
        <div class="collapse-inner">
          <a class="collapse-item <?= str_contains($_SERVER['PHP_SELF'],'reports/sales_report') ? 'active' : '' ?>" href="<?= $base_url ?? '' ?>modules/reports/sales_report.php"><i class="fas fa-cart-plus"></i> Sales Report</a>
          <a class="collapse-item" href="#"><i class="fas fa-undo"></i> Collection Report</a>
          <a class="collapse-item" href="#"><i class="fas fa-boxes"></i> Inventory Report</a>
          <a class="collapse-item" href="#"><i class="fas fa-coins"></i> Cash Book Report</a>
          <a class="collapse-item" href="#"><i class="fas fa-university"></i> Bank Book Report</a>
          <a class="collapse-item" href="#"><i class="fas fa-file-invoice"></i> Expense Report</a>
        </div>
      </div>
    </div>

    <!-- Employees -->
    <div class="nav-item">
      <a class="nav-link" data-toggle="collapse" href="#collapseEmployee" role="button" aria-expanded="false">
        <i class="fas fa-fw fa-user-tag"></i>
        <span>Employees</span>
        <span class="arrow"><i class="fas fa-chevron-right"></i></span>
      </a>
      <div class="collapse" id="collapseEmployee">
        <div class="collapse-inner">
          <a class="collapse-item" href="#"><i class="fas fa-user-shield"></i> Roles & Permissions</a>
          <a class="collapse-item" href="#"><i class="fas fa-clipboard-list"></i> Activity Logs</a>
        </div>
      </div>
    </div>

    <!-- Sidebar Toggle (Desktop) -->
    <div class="sidebar-toggle-area d-none d-md-flex">
      <button id="sidebarToggle" title="Toggle Sidebar">
        <i class="fas fa-chevron-left"></i>
        <span class="toggle-label">Collapse</span>
      </button>
    </div>
  </nav>
  <!-- End Sidebar -->

  <!-- ===== CONTENT WRAPPER ===== -->
  <div id="content-wrapper">

    <!-- Topbar -->
    <header class="topbar">
      <button class="sidebar-toggle-btn" id="sidebarMobileToggle">
        <i class="fas fa-bars"></i>
      </button>
      <div class="page-title"><?= $page_title ?? 'Dashboard' ?></div>
      <div class="user-area">
        <a href="#" class="text-muted mr-3" title="Notifications">
          <i class="fas fa-bell"></i>
          <span class="badge badge-danger badge-pill" style="font-size:0.6rem;vertical-align:top;">3</span>
        </a>
        <div class="dropdown">
          <button class="btn btn-link text-muted dropdown-toggle p-0" data-toggle="dropdown">
            <i class="fas fa-user-circle fa-lg"></i>
            <span class="ml-1 d-none d-sm-inline"><?= $_SESSION['user_name'] ?? 'Admin' ?></span>
          </button>
          <div class="dropdown-menu dropdown-menu-right">
            <a class="dropdown-item" href="#"><i class="fas fa-user fa-sm mr-2 text-muted"></i> Profile</a>
            <a class="dropdown-item" href="#"><i class="fas fa-cogs fa-sm mr-2 text-muted"></i> Settings</a>
            <div class="dropdown-divider"></div>
            <a class="dropdown-item" href="<?= $base_url ?? '' ?>login.php?logout=1">
              <i class="fas fa-sign-out-alt fa-sm mr-2 text-danger"></i> Logout
            </a>
          </div>
        </div>
      </div>
    </header>

    <!-- Page Content -->
    <div class="content">

      <!-- Page Heading -->
      <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 font-weight-bold" style="color:#0f172a;"><?= $page_title ?? 'Dashboard' ?></h1>
      </div>

      <!-- Flash Messages -->
      <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show"><?= $_SESSION['success']; unset($_SESSION['success']); ?>
          <button type="button" class="close" data-dismiss="alert">&times;</button>
        </div>
      <?php endif; ?>
      <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show"><?= $_SESSION['error']; unset($_SESSION['error']); ?>
          <button type="button" class="close" data-dismiss="alert">&times;</button>
        </div>
      <?php endif; ?>
