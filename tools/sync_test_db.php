<?php
/**
 * Sync reference data from client DB to test DB
 * Run: php tools/sync_test_db.php
 */

$host = 'localhost';
$username = 'root';
$password = '';

try {
    // Connect to client DB
    $client = new PDO("mysql:host=$host;dbname=installment_business;charset=utf8", $username, $password);
    $client->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $client->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    // Connect to test DB
    $test = new PDO("mysql:host=$host;dbname=installment_test;charset=utf8", $username, $password);
    $test->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $test->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    // Tables to sync (order matters for FK constraints)
    $tables = [
        'branches',
        'users',
        'categories',
        'brands',
        'suppliers',
        'products',
        'discounts',
        'installment_plans',
        'expense_categories',
        'bank_accounts',
        'general_parties',
    ];

    foreach ($tables as $table) {
        echo "Syncing $table... ";

        // Check if table exists in test DB
        $check = $test->query("SHOW TABLES LIKE '$table'")->fetch();
        if (!$check) {
            echo "SKIP (table doesn't exist in test DB)\n";
            continue;
        }

        // Get columns
        $cols = $client->query("SHOW COLUMNS FROM `$table`")->fetchAll(PDO::FETCH_COLUMN, 0);
        $col_list = implode(', ', array_map(fn($c) => "`$c`", $cols));
        $placeholders = implode(', ', array_fill(0, count($cols), '?'));

        // Truncate test table
        $test->exec("SET FOREIGN_KEY_CHECKS = 0");
        $test->exec("TRUNCATE TABLE `$table`");
        $test->exec("SET FOREIGN_KEY_CHECKS = 1");

        // Copy data
        $rows = $client->query("SELECT * FROM `$table`")->fetchAll();
        $count = 0;
        if (!empty($rows)) {
            $stmt = $test->prepare("INSERT INTO `$table` ($col_list) VALUES ($placeholders)");
            foreach ($rows as $row) {
                $vals = array_values($row);
                $stmt->execute($vals);
                $count++;
            }
        }
        echo "$count rows copied\n";
    }

    echo "\nSync complete!\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
