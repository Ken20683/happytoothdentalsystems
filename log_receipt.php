<?php
require_once 'database.php';
require_once 'config.php';

header('Content-Type: application/json');

$type = isset($_GET['type']) ? strtolower(trim($_GET['type'])) : '';
$item_id = isset($_GET['item_id']) ? (int)$_GET['item_id'] : 0;
$title = isset($_GET['title']) ? trim($_GET['title']) : '';
$amount = isset($_GET['amount']) ? (float)$_GET['amount'] : 0;
$source_page = isset($_GET['source_page']) ? trim($_GET['source_page']) : 'Receipt';

if (!in_array($type, ['visit', 'expense'], true) || $item_id <= 0 || $title === '' || $amount <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'Invalid receipt log payload.']);
    exit;
}

try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS receipt_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        type VARCHAR(20) NOT NULL,
        item_id INT NOT NULL,
        title VARCHAR(255) NOT NULL,
        amount DECIMAL(10,2) NOT NULL,
        source_page VARCHAR(100) NOT NULL,
        clinic_name VARCHAR(100) NOT NULL,
        clinic_location VARCHAR(255) NOT NULL,
        clinic_contact VARCHAR(50) NOT NULL,
        clinic_whatsapp VARCHAR(50) NOT NULL,
        printed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    $stmt = $pdo->prepare("INSERT INTO receipt_logs (type, item_id, title, amount, source_page, clinic_name, clinic_location, clinic_contact, clinic_whatsapp) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$type, $item_id, $title, $amount, $source_page, CLINIC_NAME, CLINIC_LOCATION, CLINIC_CONTACT, CLINIC_WHATSAPP]);

    echo json_encode(['ok' => true]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => $e->getMessage()]);
}
