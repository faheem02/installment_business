# Saim Hasnain Traders - Installment Business Management System

A PHP-based management system for tracking customers, sales, installments, payments, inventory, and financial books for an installment-based business.

## Features

- **Customer Management** - Registration, CNIC tracking, guarantors, full history
- **Sales & Billing** - Invoicing with product/item tracking, down payment, and financed amount calculation
- **Installment Plans** - Configurable plans with interest rates, automated installment schedules
- **Payment Collection** - Record cash/bank payments against installments with running balance
- **Inventory Management** - Products, brands, categories, suppliers, purchases with serial tracking
- **Cash Book** - Daily cash position with opening/closing balances, inflow/outflow tracking
- **Bank Book** - Multi-account bank transaction management with cheque tracking
- **General Ledger** - Party-based ledger entries
- **Expense Management** - Categories and expense recording
- **Reports** - Sales report, closing report with customer-wise balances, late payments tracking

## Tech Stack

- **Backend:** PHP 7.4+ (native, no framework)
- **Database:** MySQL 5.7+ / MariaDB
- **Frontend:** Bootstrap 4.6, jQuery, Font Awesome 5
- **Date Picker:** Flatpickr
- **Print:** Dedicated print-optimized layouts

## Installation

1. Clone the repository to your web server directory (e.g., `htdocs`, `wwwroot`):
   ```bash
   git clone https://github.com/faheem02/installment_business.git
   ```

2. Import the database schema:
   ```bash
   mysql -u root -p < database_schema.sql
   ```

3. Import the admin seed data:
   ```bash
   mysql -u root -p installment_business < seed_admin.sql
   ```

4. Configure database connection in `config/db.php`:
   ```php
   $host = 'localhost';
   $username = 'root';
   $password = '';
   $db_mode = 'client'; // 'client' for production, 'test' for development
   ```

5. Access the application at `http://localhost/installment_business`

## Usage

### Login
Default admin credentials are seeded via `seed_admin.sql`. Login at `login.php`.

### Workflow
1. **Register customers** under Customer Management
2. **Create installment plans** (e.g., 3-month, 6-month with interest rates)
3. **Make a sale** with down payment and select an installment plan
4. **Collect payments** against generated installment schedules
5. **Track finances** via Cash Book, Bank Book, and reports

## Directory Structure

```
├── assets/              # CSS, JS
├── config/              # Database configuration
├── includes/            # Header, footer, auth, helper functions
├── modules/
│   ├── bankbook/        # Bank account & transaction management
│   ├── cashbook/        # Daily cash book
│   ├── customers/       # Customer CRUD, view, history
│   ├── expenses/        # Expense categories & entries
│   ├── general/         # General ledger
│   ├── installments/    # Plans, schedules, late payments
│   ├── inventory/       # Products, purchases, suppliers
│   ├── payments/        # Payment receipt, daily collection
│   ├── reports/         # Sales report, closing report
│   └── sales/           # New sale, invoices
├── tools/               # Database sync utilities
├── database_schema.sql  # Full database schema
└── seed_admin.sql       # Admin user seed
```

## Database

The system uses 32+ tables including:
- `customers`, `sales`, `sale_items`, `sale_installments`
- `payments`, `sale_returns`
- `installment_plans`, `products`, `suppliers`, `purchases`
- `cash_book_daily`, `cash_book`, `bank_accounts`, `bank_transactions`
- `chart_of_accounts`, `journal_entries`, `general_parties`, `general_transactions`

## License

MIT
