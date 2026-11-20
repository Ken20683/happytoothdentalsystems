<?php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/airtel_money.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'message' => 'Please log in first.']);
    exit;
}

$payment_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

try {
    $stmt = $pdo->prepare('SELECT * FROM airtel_payments WHERE id = ?');
    $stmt->execute([$payment_id]);
    $payment = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$payment) {
        throw new RuntimeException('Payment record not found.');
    }

    $result = airtel_check_collection($payment['transaction_id']);
    $status_value = isset($result['status']) ? $result['status'] : 'PENDING';
    $status = strtoupper((string)$status_value);
    $mapped_status = in_array($status, array('SUCCESS', 'SUCCESSFUL', 'COMPLETED'), true)
        ? 'SUCCESS'
        : (in_array($status, array('FAILED', 'FAILURE', 'CANCELLED'), true) ? 'FAILED' : 'PENDING');

    $pdo->beginTransaction();
    $stmt = $pdo->prepare('UPDATE airtel_payments SET status = ?, raw_response = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?');
    $stmt->execute([$mapped_status, json_encode($result), $payment_id]);

    if ($mapped_status === 'SUCCESS' && !empty($payment['receipt_id'])) {
        $stmt = $pdo->prepare("UPDATE receipts SET payment_method = 'Mobile Money', payment_status = 'Paid' WHERE id = ?");
        $stmt->execute([$payment['receipt_id']]);
    }

    $pdo->commit();
    $message = isset($result['message']) ? $result['message'] : 'Payment status checked.';
    echo json_encode(array('ok' => true, 'status' => $mapped_status, 'message' => $message));
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => $e->getMessage()]);
}