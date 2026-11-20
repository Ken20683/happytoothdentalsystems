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
$visits = [];
$total_revenue = 0.0;

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
    $visits = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $visits = [];
    error_log('Could not load monthly report visits: ' . $e->getMessage());
}

for ($i = 0; $i < count($visits); $i++) {
    $visit = $visits[$i];
    $total_revenue += (float)$visit['amount'];
}

$expenses = [];
$total_expenses = 0.0;
try {
    $stmt = $pdo->prepare("SELECT * FROM monthly_expense_archives WHERE archive_month = ? ORDER BY expense_date ASC, id ASC");
    $stmt->execute([$archive_month]);
    $expenses = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($expenses)) {
        $stmt = $pdo->prepare("SELECT id, title, category, amount, expense_date, payment_method, notes FROM expenses WHERE YEAR(expense_date) = ? AND MONTH(expense_date) = ? ORDER BY expense_date ASC, created_at ASC");
        $stmt->execute([$month_date->format('Y'), $month_date->format('m')]);
        $expenses = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    error_log('Could not load monthly report expenses: ' . $e->getMessage());
    $expenses = [];
}

for ($i = 0; $i < count($expenses); $i++) {
    $expense = $expenses[$i];
    $total_expenses += (float)$expense['amount'];
}

$patient_count = 0;
if (!empty($visits)) {
    $patient_names = [];
    for ($i = 0; $i < count($visits); $i++) {
        $visit = $visits[$i];
        if (!empty($visit['patient_name'])) {
            $patient_names[$visit['patient_name']] = true;
        }
    }
    $patient_count = count($patient_names);
}

$net_profit = $total_revenue - $total_expenses;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Monthly Report - <?php echo htmlspecialchars($month_date->format('F Y')); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #f5f7fa; }
        .report { max-width: 1200px; margin: 2rem auto; background: #fff; padding: 2rem; }
        .report-logo { width: 82px; height: 82px; object-fit: contain; display: block; margin-bottom: 0.75rem; }
        .summary-card { border: 1px solid #e9ecef; border-radius: 12px; padding: 1rem; background: #f8f9fa; }
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
                <h4>Monthly Financial Report - <?php echo htmlspecialchars($month_date->format('F Y')); ?></h4>
                <p class="text-muted mb-0"><?php echo htmlspecialchars(CLINIC_LOCATION); ?></p>
            </div>
            <div class="no-print">
                <a href="manage_months.php" class="btn btn-secondary me-2">Back</a>
                <button type="button" class="btn btn-primary" onclick="window.print()">Print Monthly Report</button>
            </div>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <div class="summary-card">
                    <div class="text-muted small">Patients</div>
                    <div class="fs-4 fw-bold"><?php echo $patient_count; ?></div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="summary-card">
                    <div class="text-muted small">Visits</div>
                    <div class="fs-4 fw-bold"><?php echo count($visits); ?></div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="summary-card">
                    <div class="text-muted small">Revenue</div>
                    <div class="fs-4 fw-bold">MK <?php echo number_format($total_revenue, 2); ?></div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="summary-card">
                    <div class="text-muted small">Expenses</div>
                    <div class="fs-4 fw-bold">MK <?php echo number_format($total_expenses, 2); ?></div>
                </div>
            </div>
        </div>

        <div class="alert alert-info d-flex justify-content-between align-items-center mb-4">
            <span><strong>Net Profit:</strong> MK <?php echo number_format($net_profit, 2); ?></span>
            <span class="small">Generated: <?php echo date('d/m/Y H:i'); ?></span>
        </div>

        <h5 class="mb-3">Receipts / Visits</h5>
        <?php if (empty($visits)): ?>
            <div class="alert alert-warning">No visit records found for this month.</div>
        <?php else: ?>
            <div class="table-responsive mb-5">
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
                        <?php for ($i = 0; $i < count($visits); $i++): ?>
                            <?php $visit = $visits[$i]; ?>
                            <tr>
                                <td><?php echo $i + 1; ?></td>
                                <td><?php echo htmlspecialchars($visit['receipt_number'] ?: 'Pending'); ?></td>
                                <td><?php echo date('d/m/Y', strtotime($visit['visit_date'])); ?></td>
                                <td><?php echo htmlspecialchars($visit['patient_name']); ?></td>
                                <td><?php echo htmlspecialchars($visit['phone']); ?></td>
                                <td><?php echo htmlspecialchars($visit['procedure_name']); ?></td>
                                <td><?php echo htmlspecialchars(($visit['payment_status'] ?: 'Pending') . ' / ' . ($visit['payment_method'] ?: 'Cash')); ?></td>
                                <td class="text-end">MK <?php echo number_format((float)$visit['amount'], 2); ?></td>
                            </tr>
                        <?php endfor; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <th colspan="7" class="text-end">Revenue Total</th>
                            <th class="text-end">MK <?php echo number_format($total_revenue, 2); ?></th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        <?php endif; ?>

        <h5 class="mb-3">Expenses</h5>
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
                        <?php for ($i = 0; $i < count($expenses); $i++): ?>
                            <?php $expense = $expenses[$i]; ?>
                            <tr>
                                <td><?php echo $i + 1; ?></td>
                                <td><?php echo htmlspecialchars($expense['title']); ?></td>
                                <td><?php echo htmlspecialchars($expense['category']); ?></td>
                                <td><?php echo date('d/m/Y', strtotime($expense['expense_date'])); ?></td>
                                <td><?php echo htmlspecialchars($expense['payment_method'] ?: 'Cash'); ?></td>
                                <td><?php echo htmlspecialchars($expense['notes'] ?: '—'); ?></td>
                                <td class="text-end">MK <?php echo number_format((float)$expense['amount'], 2); ?></td>
                            </tr>
                        <?php endfor; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <th colspan="6" class="text-end">Expense Total</th>
                            <th class="text-end">MK <?php echo number_format($total_expenses, 2); ?></th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        <?php endif; ?>

        <div class="report-signature">
            Signed by:
            <strong>Kenneth Chirambo</strong>
        </div>
    </main>
</body>
</html>
