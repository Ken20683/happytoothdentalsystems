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

$archive_month = $month_date->format('Y-m');
$expenses = [];

try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS monthly_expense_archives (
        id INT AUTO_INCREMENT PRIMARY KEY,
        archive_month VARCHAR(7) NOT NULL,
        expense_id INT NOT NULL,
        title VARCHAR(255) NOT NULL,
        category VARCHAR(100) NOT NULL,
        amount DECIMAL(10,2) NOT NULL,
        expense_date DATE NOT NULL,
        payment_method VARCHAR(50) DEFAULT 'Cash',
        notes TEXT,
        archived_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_monthly_expense_archive (archive_month, expense_id)
    )");

    $stmt = $pdo->prepare("SELECT * FROM monthly_expense_archives WHERE archive_month = ? ORDER BY expense_date ASC, id ASC");
    $stmt->execute([$archive_month]);
    $expenses = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($expenses)) {
        $stmt = $pdo->prepare("SELECT id, title, category, amount, expense_date, payment_method, notes FROM expenses WHERE YEAR(expense_date) = ? AND MONTH(expense_date) = ? ORDER BY expense_date ASC, created_at ASC");
        $stmt->execute([$month_date->format('Y'), $month_date->format('m')]);
        $expenses = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    error_log('Could not load monthly expense records: ' . $e->getMessage());
    try {
        $stmt = $pdo->prepare("SELECT id, title, category, amount, expense_date, payment_method, notes FROM expenses WHERE YEAR(expense_date) = ? AND MONTH(expense_date) = ? ORDER BY expense_date ASC, created_at ASC");
        $stmt->execute([$month_date->format('Y'), $month_date->format('m')]);
        $expenses = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $fallbackException) {
        error_log('Could not load fallback expense records: ' . $fallbackException->getMessage());
        $expenses = [];
    }
}

$total = 0;
foreach ($expenses as $expense) {
    $total += (float)$expense['amount'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Monthly Expenses - <?php echo htmlspecialchars($month_date->format('F Y')); ?></title>
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
                <h4>Monthly Expenses - <?php echo htmlspecialchars($month_date->format('F Y')); ?></h4>
                <p class="text-muted mb-0"><?php echo htmlspecialchars(CLINIC_LOCATION); ?></p>
            </div>
            <div class="no-print">
                <a href="manage_months.php" class="btn btn-secondary me-2">Back</a>
                <button type="button" class="btn btn-danger" onclick="window.print()">Print Monthly Expenses</button>
            </div>
        </div>

        <?php if (empty($expenses)): ?>
            <div class="alert alert-info">No expense records found for this month.</div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-bordered table-striped align-middle">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Title</th>
                            <th>Category</th>
                            <th>Date</th>
                            <th>Payment</th>
                            <th>Notes</th>
                            <th class="text-end">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($expenses as $index => $expense): ?>
                            <tr>
                                <td><?php echo $index + 1; ?></td>
                                <td><?php echo htmlspecialchars($expense['title']); ?></td>
                                <td><?php echo htmlspecialchars($expense['category']); ?></td>
                                <td><?php echo date('d/m/Y', strtotime($expense['expense_date'])); ?></td>
                                <td><?php echo htmlspecialchars($expense['payment_method'] ?: 'Cash'); ?></td>
                                <td><?php echo htmlspecialchars($expense['notes'] ?: '—'); ?></td>
                                <td class="text-end">MK <?php echo number_format((float)$expense['amount'], 2); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <th colspan="6" class="text-end">Total</th>
                            <th class="text-end">MK <?php echo number_format($total, 2); ?></th>
                        </tr>
                    </tfoot>
                </table>
            </div>
            <p class="text-muted">Expenses: <?php echo count($expenses); ?> | Printed: <?php echo date('d/m/Y H:i'); ?></p>
        <?php endif; ?>

        <div class="report-signature">
            Signed by:
            <strong>Kenneth Chirambo</strong>
        </div>
    </main>
</body>
</html>
