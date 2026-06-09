<?php
session_start();
require_once '../../includes/functions.php';

$id = (int)($_GET['id'] ?? 0);
if (!$id) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid ID']);
    exit;
}

$purchase = getById('purchases', $id);
if (!$purchase) {
    http_response_code(404);
    echo json_encode(['error' => 'Purchase not found']);
    exit;
}

$supplier = $purchase['supplier_id'] ? getById('suppliers', $purchase['supplier_id']) : null;
$items = getWhere('purchase_items', 'purchase_id', $id);

$serials = $pdo->prepare("SELECT * FROM product_serials WHERE purchase_id = ?");
$serials->execute([$id]);
$serials = $serials->fetchAll();

$data = [
    'id' => $purchase['id'],
    'purchase_date' => $purchase['purchase_date'],
    'invoice_no' => $purchase['invoice_no'],
    'total_amount' => $purchase['total_amount'],
    'paid_amount' => $purchase['paid_amount'],
    'due_amount' => $purchase['due_amount'],
    'status' => $purchase['status'],
    'notes' => $purchase['notes'],
    'created_at' => $purchase['created_at'],
    'supplier_name' => $supplier ? $supplier['name'] : '-',
    'items' => [],
    'serials' => []
];

foreach ($items as $item) {
    $prod = getById('products', $item['product_id']);
    $data['items'][] = [
        'product_name' => $prod ? $prod['name'] : 'Unknown',
        'product_code' => $prod ? $prod['code'] : '',
        'product_type' => $prod ? $prod['product_type'] : 'general',
        'quantity' => $item['quantity'],
        'purchase_price' => $item['purchase_price'],
        'subtotal' => $item['subtotal'],
    ];
}

foreach ($serials as $s) {
    $data['serials'][] = [
        'serial_number' => $s['serial_number'],
        'imei_number' => $s['imei_number'],
        'status' => $s['status'],
    ];
}

header('Content-Type: application/json');
echo json_encode($data);
