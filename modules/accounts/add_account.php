<?php
session_start();
require_once '../../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['account_code'])) {
    header("Location: general_ledger.php");
    exit;
}

$account_code = trim($_POST['account_code'] ?? '');
$account_name = trim($_POST['account_name'] ?? '');
$account_type = $_POST['account_type'] ?? '';
$description = trim($_POST['description'] ?? '');
$opening_balance = (float)($_POST['opening_balance'] ?? 0);
$parent_id = !empty($_POST['parent_id']) ? (int)$_POST['parent_id'] : null;

if (empty($account_code) || empty($account_name) || empty($account_type)) {
    $_SESSION['error'] = 'Account code, name, and type are required';
    header("Location: general_ledger.php");
    exit;
}

try {
    $existing = $pdo->prepare("SELECT id FROM chart_of_accounts WHERE account_code = ?");
    $existing->execute([$account_code]);
    if ($existing->fetch()) {
        $_SESSION['error'] = "Account code '$account_code' already exists";
        header("Location: general_ledger.php");
        exit;
    }

    insert('chart_of_accounts', [
        'account_code' => $account_code,
        'account_name' => $account_name,
        'account_type' => $account_type,
        'parent_id' => $parent_id,
        'description' => $description ?: null,
        'opening_balance' => $opening_balance,
        'current_balance' => $opening_balance,
        'status' => 1,
        'created_at' => date('Y-m-d'),
    ]);

    $_SESSION['success'] = "Account '$account_name' created successfully";
} catch (Exception $e) {
    $_SESSION['error'] = 'Failed to create account: ' . $e->getMessage();
}

header("Location: general_ledger.php");
exit;
