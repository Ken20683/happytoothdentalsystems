<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'message' => 'Please log in first.']);
    exit;
}

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/airtel_money.php';

$visit_id = isset($_POST['visit_id']) ? (int)$_POST['visit_id'] : 0;
$phone = isset($_POST['phone']) ? preg_replace('/\D+/', '', $_POST['phone']) : '';

if ($visit_id <= 0 || $phone === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'Invalid visit or phone number.']);
    exit;
}

try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS airtel_payments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        visit_id INT NOT NULL,
        receipt_id INT NOT NULL,
        transaction_id VARCHAR(100) UNIQUE NOT NULL,
        phone VARCHAR(20) NOT NULL,
        amount DECIMAL(10,2) NOT NULL,
        fee_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        receiver_phone VARCHAR(20) NOT NULL DEFAULT '0992239889',
        status ENUM('PENDING', 'SUCCESS', 'FAILED') DEFAULT 'PENDING',
        raw_response LONGTEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");

    $visit_stmt = $pdo->prepare('SELECT * FROM visits WHERE id = ?');
    $visit_stmt->execute([$visit_id]);
    $visit = $visit_stmt->fetch(PDO::FETCH_ASSOC);

    if (!$visit) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'message' => 'Visit not found.']);
        exit;
    }

    $receipt_stmt = $pdo->prepare('SELECT id, receipt_number FROM receipts WHERE visit_id = ? ORDER BY id DESC LIMIT 1');
    $receipt_stmt->execute([$visit_id]);
    $receipt = $receipt_stmt->fetch(PDO::FETCH_ASSOC);
    $receipt_id = $receipt ? (int)$receipt['id'] : 0;

    $reference = 'VISIT-' . $visit_id . '-' . date('YmdHis');
    $base_amount = (float)($visit['amount'] ?? 0);
    $fee_summary = airtel_total_to_send($base_amount);
    $total_amount = $fee_summary['total_amount'];

    $columns = $pdo->query("SHOW COLUMNS FROM airtel_payments")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('fee_amount', $columns, true)) {
        $pdo->exec('ALTER TABLE airtel_payments ADD COLUMN fee_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER amount');
    }
    if (!in_array('receiver_phone', $columns, true)) {
        $pdo->exec('ALTER TABLE airtel_payments ADD COLUMN receiver_phone VARCHAR(20) NOT NULL DEFAULT "0992239889" AFTER fee_amount');
    }

    $result = airtel_start_collection($phone, $base_amount, $reference, 'Dental payment request');

    if (!$result['ok']) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'message' => $result['message']]);
        exit;
    }

    $transaction_id = $result['transaction_id'] ?? $reference;
    $receiver_phone = airtel_merchant_phone();
    $insert_stmt = $pdo->prepare('INSERT INTO airtel_payments (visit_id, receipt_id, transaction_id, phone, amount, fee_amount, receiver_phone, status, raw_response) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
    $insert_stmt->execute([
        $visit_id,
        $receipt_id,
        $transaction_id,
        $phone,
        $total_amount,
        $fee_summary['fee_amount'],
        $receiver_phone,
        'PENDING',
        json_encode($result)
    ]);

    $manual = !empty($result['manual']);
    echo json_encode([
        'ok' => true,
        'manual' => $manual,
        'payment_id' => (int)$pdo->lastInsertId(),
        'transaction_id' => $transaction_id,
        'message' => $result['message'] ?? 'Airtel payment request created.',
        'total_amount' => isset($result['amount']) ? (float)$result['amount'] : $total_amount,
        'fee_amount' => $fee_summary['fee_amount'],
        'receiver_phone' => $receiver_phone,
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Unable to start Airtel payment: ' . $e->getMessage()]);
}
