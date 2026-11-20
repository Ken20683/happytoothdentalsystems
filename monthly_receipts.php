<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

require_once 'config.php';
require_once 'database.php';

$month = isset($_GET['month']) ? trim($_GET['month']) : date('Y-m');
$month_date = DateTime::createFromFormat('!Y-m', $month);
if (!$month_date || $month_date->format('Y-m') !== $month) {
    http_response_code(400);
    exit('Invalid month.');
}

$receipts = [];
try {
    $stmt = $pdo->prepare("SELECT v.id, v.visit_date, v.amount, p.name AS patient_name, p.phone,
        pr.name AS procedure_name, r.receipt_number, r.payment_method, r.payment_status
        FROM visits v
        JOIN patients p ON p.id = v.patient_id
        JOIN procedures pr ON pr.id = v.procedure_id
        LEFT JOIN receipts r ON r.visit_id = v.id
        WHERE YEAR(v.visit_date) = ? AND MONTH(v.visit_date) = ?
        ORDER BY v.visit_date ASC, v.id ASC");
    $stmt->execute([$month_date->format('Y'), $month_date->format('m')]);
    $receipts = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    exit('Could not load monthly receipts.');
}

$total = 0;
foreach ($receipts as $receipt) {
    $total += (float)$receipt['amount'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receipts - <?php echo htmlspecialchars($month_date->format('F Y')); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #f5f7fa; }
        .report { max-width: 1100px; margin: 2rem auto; background: #fff; padding: 2rem; }
        .report-logo { width: 82px; height: 82px; object-fit: contain; display: block; margin-bottom: 0.75rem; }
        .report-signature { margin-top: 2rem; padding-top: 0.75rem; border-top: 1px solid #adb5bd; width: 260px; text-align: center; }
        .report-signature strong { display: block; color: #198754; margin-top: 0.25rem; }
        @media print {
            body { background: #fff; }
            .report { margin: 0; max-width: none; padding: 0; }
            .no-print { display: none !important; }
        }
    </style>
</head>
<body>
    <main class="report">
        <div class="d-flex justify-content-between align-items-start mb-4">
            <div>
                <img src="Screenshot%20(1)_5509.jpg" alt="Happy Tooth Dental clinic logo" class="report-logo">
                <h2><?php echo htmlspecialchars(CLINIC_NAME); ?></h2>
                <h4>All Receipts - <?php echo htmlspecialchars($month_date->format('F Y')); ?></h4>
                <p class="text-muted mb-0"><?php echo htmlspecialchars(CLINIC_LOCATION); ?></p>
            </div>
            <div class="no-print">
                <a href="manage_months.php" class="btn btn-secondary me-2">Back</a>
                <button type="button" class="btn btn-primary" onclick="window.print()">Print All Receipts</button>
            </div>
        </div>

        <?php if (empty($receipts)): ?>
            <div class="alert alert-info">No receipts found for this month.</div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-bordered table-striped align-middle">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Receipt</th>
                            <th>Date</th>
                            <th>Patient</th>
                            <th>Phone</th>
                            <th>Procedure</th>
                            <th>Payment</th>
                            <th class="text-end">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($receipts as $index => $receipt): ?>
                            <tr>
                                <td><?php echo $index + 1; ?></td>
                                <td><?php echo htmlspecialchars($receipt['receipt_number'] ?: 'Pending'); ?></td>
                                <td><?php echo date('d/m/Y', strtotime($receipt['visit_date'])); ?></td>
                                <td><?php echo htmlspecialchars($receipt['patient_name']); ?></td>
                                <td><?php echo htmlspecialchars($receipt['phone']); ?></td>
                                <td><?php echo htmlspecialchars($receipt['procedure_name']); ?></td>
                                <td><?php echo htmlspecialchars(($receipt['payment_status'] ?: 'Pending') . ' / ' . ($receipt['payment_method'] ?: 'Cash')); ?></td>
                                <td class="text-end">MK <?php echo number_format((float)$receipt['amount'], 2); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <th colspan="7" class="text-end">Total</th>
                            <th class="text-end">MK <?php echo number_format($total, 2); ?></th>
                        </tr>
                    </tfoot>
                </table>
            </div>
            <p class="text-muted">Receipts: <?php echo count($receipts); ?> | Printed: <?php echo date('d/m/Y H:i'); ?></p>
        <?php endif; ?>
        <div class="report-signature">
            Signed by:
            <strong>Kenneth Chirambo</strong>
        </div>
    </main>
</body>
</html>
