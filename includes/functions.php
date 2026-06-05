<?php
require_once __DIR__ . '/../config/db.php';

// Get all records from a table
function getAll($table, $order = 'id DESC') {
    global $pdo;
    $stmt = $pdo->query("SELECT * FROM $table ORDER BY $order");
    return $stmt->fetchAll();
}

// Get single record by ID
function getById($table, $id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM $table WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch();
}

// Get records with a condition
function getWhere($table, $column, $value, $order = 'id DESC') {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM $table WHERE $column = ? ORDER BY $order");
    $stmt->execute([$value]);
    return $stmt->fetchAll();
}

// Insert and return last ID
function insert($table, $data) {
    global $pdo;
    $columns = implode(', ', array_keys($data));
    $placeholders = implode(', ', array_fill(0, count($data), '?'));
    $stmt = $pdo->prepare("INSERT INTO $table ($columns) VALUES ($placeholders)");
    $stmt->execute(array_values($data));
    return $pdo->lastInsertId();
}

// Update record
function update($table, $data, $id) {
    global $pdo;
    $sets = implode(', ', array_map(fn($c) => "$c = ?", array_keys($data)));
    $stmt = $pdo->prepare("UPDATE $table SET $sets WHERE id = ?");
    $stmt->execute([...array_values($data), $id]);
    return $stmt->rowCount();
}

// Delete record
function delete($table, $id) {
    global $pdo;
    $stmt = $pdo->prepare("DELETE FROM $table WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->rowCount();
}

// Count records
function countRows($table, $column = null, $value = null) {
    global $pdo;
    if ($column && $value !== null) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM $table WHERE $column = ?");
        $stmt->execute([$value]);
    } else {
        $stmt = $pdo->query("SELECT COUNT(*) FROM $table");
    }
    return $stmt->fetchColumn();
}

// Generate invoice number
function generateInvoiceNo() {
    global $pdo;
    $prefix = 'INV-' . date('ymd') . '-';
    $stmt = $pdo->query("SELECT COUNT(*) FROM sales WHERE invoice_no LIKE '$prefix%'");
    $count = $stmt->fetchColumn() + 1;
    return $prefix . str_pad($count, 3, '0', STR_PAD_LEFT);
}

// Generate customer number
function generateCustomerNo() {
    global $pdo;
    $prefix = 'CUS-' . date('ym') . '-';
    $stmt = $pdo->query("SELECT COUNT(*) FROM customers WHERE customer_no LIKE '$prefix%'");
    $count = $stmt->fetchColumn() + 1;
    return $prefix . str_pad($count, 4, '0', STR_PAD_LEFT);
}

// Format currency
function formatCurrency($amount) {
    return number_format($amount, 2);
}

// Format date
function formatDate($date) {
    return $date ? date('d-m-Y', strtotime($date)) : '-';
}

// Get customer full name with ID
function customerName($id) {
    $c = getById('customers', $id);
    return $c ? $c['full_name'] : 'N/A';
}

// Redirect with message
function redirect($url, $msg = null, $type = 'success') {
    if ($msg) {
        session_start();
        $_SESSION[$type] = $msg;
    }
    header("Location: $url");
    exit;
}
