<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/database.php';

$message = '';
$error = '';

function ensure_monthly_archive_tables($pdo) {
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

    $pdo->exec("CREATE TABLE IF NOT EXISTS monthly_archives (
        id INT AUTO_INCREMENT PRIMARY KEY,
        archive_month VARCHAR(7) NOT NULL UNIQUE,
        total_patients INT DEFAULT 0,
        total_visits INT DEFAULT 0,
        total_revenue DECIMAL(15,2) DEFAULT 0,
        total_expenses_amount DECIMAL(15,2) DEFAULT 0,
        archived_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
}

function archive_monthly_expenses($pdo, $archive_month, $month, $year) {
    ensure_monthly_archive_tables($pdo);

    $delete_old = $pdo->prepare("DELETE FROM monthly_expense_archives WHERE archive_month = ?");
    $delete_old->execute([$archive_month]);

    $stmt = $pdo->prepare("SELECT id, title, category, amount, expense_date, payment_method, notes FROM expenses WHERE MONTH(expense_date) = ? AND YEAR(expense_date) = ? ORDER BY expense_date ASC, created_at ASC");
    $stmt->execute([$month, $year]);
    $expenses = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($expenses)) {
        return;
    }

    $insert_stmt = $pdo->prepare("
        INSERT INTO monthly_expense_archives
        (archive_month, expense_id, title, category, amount, expense_date, payment_method, notes)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            title = VALUES(title),
            category = VALUES(category),
            amount = VALUES(amount),
            expense_date = VALUES(expense_date),
            payment_method = VALUES(payment_method),
            notes = VALUES(notes)
    ");

    foreach ($expenses as $expense) {
        $insert_stmt->execute([
            $archive_month,
            (int)$expense['id'],
            $expense['title'],
            $expense['category'],
            $expense['amount'],
            $expense['expense_date'],
            $expense['payment_method'],
            $expense['notes']
        ]);
    }
}

function archive_month_data($pdo, $month, $year) {
    $archive_month = $year . '-' . str_pad((int)$month, 2, '0', STR_PAD_LEFT);

    $stmt = $pdo->prepare("
        SELECT 
            COUNT(DISTINCT v.patient_id) as total_patients,
            COUNT(v.id) as total_visits,
            COALESCE(SUM(v.amount), 0) as total_revenue,
            COUNT(DISTINCT e.id) as total_expenses,
            COALESCE(SUM(e.amount), 0) as total_expenses_amount
        FROM visits v
        LEFT JOIN expenses e ON MONTH(e.expense_date) = ? AND YEAR(e.expense_date) = ?
        WHERE MONTH(v.visit_date) = ? AND YEAR(v.visit_date) = ?
    ");
    $stmt->execute([$month, $year, $month, $year]);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);

    $insert_stmt = $pdo->prepare("
        INSERT INTO monthly_archives
        (archive_month, total_patients, total_visits, total_revenue, total_expenses_amount, archived_at)
        VALUES (?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            total_patients = VALUES(total_patients),
            total_visits = VALUES(total_visits),
            total_revenue = VALUES(total_revenue),
            total_expenses_amount = VALUES(total_expenses_amount),
            archived_at = VALUES(archived_at)
    ");
    $insert_stmt->execute([
        $archive_month,
        (int)$stats['total_patients'],
        (int)$stats['total_visits'],
        (float)$stats['total_revenue'],
        (float)$stats['total_expenses_amount'],
        date('Y-m-d H:i:s')
    ]);

    archive_monthly_expenses($pdo, $archive_month, $month, $year);

    return $archive_month;
}

function auto_archive_month_if_needed($pdo) {
    ensure_monthly_archive_tables($pdo);

    $today = new DateTime();
    $target_month = (int)$today->format('n');
    $target_year = (int)$today->format('Y');

    if ((int)$today->format('d') < (int)$today->format('t')) {
        $previous_month = clone $today;
        $previous_month->modify('first day of this month');
        $previous_month->modify('-1 month');
        $target_month = (int)$previous_month->format('n');
        $target_year = (int)$previous_month->format('Y');
    }

    $archive_key = $target_year . '-' . str_pad($target_month, 2, '0', STR_PAD_LEFT);

    $check = $pdo->prepare("SELECT COUNT(*) FROM monthly_archives WHERE archive_month = ?");
    $check->execute([$archive_key]);
    if ((int)$check->fetchColumn() > 0) {
        return null;
    }

    $has_data = $pdo->prepare("SELECT COUNT(*) FROM visits WHERE MONTH(visit_date) = ? AND YEAR(visit_date) = ?");
    $has_data->execute([$target_month, $target_year]);
    if ((int)$has_data->fetchColumn() === 0) {
        $expense_check = $pdo->prepare("SELECT COUNT(*) FROM expenses WHERE MONTH(expense_date) = ? AND YEAR(expense_date) = ?");
        $expense_check->execute([$target_month, $target_year]);
        if ((int)$expense_check->fetchColumn() === 0) {
            return null;
        }
    }

    return archive_month_data($pdo, $target_month, $target_year);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    ensure_monthly_archive_tables($pdo);

    if ($_POST['action'] === 'archive_month') {
        try {
            $pdo->beginTransaction();

            $current_month = date('m');
            $current_year = date('Y');

            $stmt = $pdo->prepare("
                SELECT 
                    COUNT(DISTINCT v.patient_id) as total_patients,
                    COUNT(v.id) as total_visits,
                    COALESCE(SUM(v.amount), 0) as total_revenue,
                    COUNT(DISTINCT e.id) as total_expenses,
                    COALESCE(SUM(e.amount), 0) as total_expenses_amount
                FROM visits v
                LEFT JOIN expenses e ON MONTH(e.expense_date) = ? AND YEAR(e.expense_date) = ?
                WHERE MONTH(v.visit_date) = ? AND YEAR(v.visit_date) = ?
            ");
            $stmt->execute([$current_month, $current_year, $current_month, $current_year]);
            $stats = $stmt->fetch();

            $archive_date = date('Y-m-d H:i:s');
            $archive_month = $current_year . '-' . str_pad($current_month, 2, '0', STR_PAD_LEFT);

            $insert_stmt = $pdo->prepare("
                INSERT INTO monthly_archives
                (archive_month, total_patients, total_visits, total_revenue, total_expenses_amount, archived_at)
                VALUES (?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    total_patients = VALUES(total_patients),
                    total_visits = VALUES(total_visits),
                    total_revenue = VALUES(total_revenue),
                    total_expenses_amount = VALUES(total_expenses_amount),
                    archived_at = VALUES(archived_at)
            ");
            $insert_stmt->execute([
                $archive_month,
                $stats['total_patients'],
                $stats['total_visits'],
                $stats['total_revenue'],
                $stats['total_expenses_amount'],
                $archive_date
            ]);

            archive_monthly_expenses($pdo, $archive_month, $current_month, $current_year);

            $pdo->commit();
            $message = 'Month archived successfully! Data for ' . date('F Y') . ' has been archived.';
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = 'Error archiving month: ' . $e->getMessage();
        }
    } elseif ($_POST['action'] === 'delete_archive' && isset($_POST['month'])) {
        try {
            $delete_month = trim($_POST['month']);
            if (!preg_match('/^\d{4}-\d{2}$/', $delete_month)) {
                throw new InvalidArgumentException('Invalid archive month value.');
            }

            $pdo->beginTransaction();

            $delete_summary = $pdo->prepare("DELETE FROM monthly_archives WHERE archive_month = ?");
            $delete_summary->execute([$delete_month]);

            $delete_expenses = $pdo->prepare("DELETE FROM monthly_expense_archives WHERE archive_month = ?");
            $delete_expenses->execute([$delete_month]);

            $pdo->commit();
            $message = 'Achieved month removed successfully for ' . date('F Y', strtotime($delete_month . '-01')) . '.';
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = 'Error deleting achieved month: ' . $e->getMessage();
        }
    } elseif ($_POST['action'] === 'clear_month' && isset($_POST['confirm_clear'])) {
        try {
            $pdo->beginTransaction();

            $current_month = date('m');
            $current_year = date('Y');

            $stmt = $pdo->prepare("
                SELECT 
                    COUNT(DISTINCT v.patient_id) as total_patients,
                    COUNT(v.id) as total_visits,
                    COALESCE(SUM(v.amount), 0) as total_revenue,
                    COUNT(DISTINCT e.id) as total_expenses,
                    COALESCE(SUM(e.amount), 0) as total_expenses_amount
                FROM visits v
                LEFT JOIN expenses e ON MONTH(e.expense_date) = ? AND YEAR(e.expense_date) = ?
                WHERE MONTH(v.visit_date) = ? AND YEAR(v.visit_date) = ?
            ");
            $stmt->execute([$current_month, $current_year, $current_month, $current_year]);
            $stats = $stmt->fetch();

            $archive_date = date('Y-m-d H:i:s');
            $archive_month = $current_year . '-' . str_pad($current_month, 2, '0', STR_PAD_LEFT);

            $insert_stmt = $pdo->prepare("
                INSERT INTO monthly_archives
                (archive_month, total_patients, total_visits, total_revenue, total_expenses_amount, archived_at)
                VALUES (?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    total_patients = VALUES(total_patients),
                    total_visits = VALUES(total_visits),
                    total_revenue = VALUES(total_revenue),
                    total_expenses_amount = VALUES(total_expenses_amount),
                    archived_at = VALUES(archived_at)
            ");
            $insert_stmt->execute([
                $archive_month,
                $stats['total_patients'],
                $stats['total_visits'],
                $stats['total_revenue'],
                $stats['total_expenses_amount'],
                $archive_date
            ]);

            archive_monthly_expenses($pdo, $archive_month, $current_month, $current_year);

            $delete_visits = $pdo->prepare("DELETE FROM visits WHERE MONTH(visit_date) = ? AND YEAR(visit_date) = ?");
            $delete_visits->execute([$current_month, $current_year]);

            $delete_expenses = $pdo->prepare("DELETE FROM expenses WHERE MONTH(expense_date) = ? AND YEAR(expense_date) = ?");
            $delete_expenses->execute([$current_month, $current_year]);

            $pdo->commit();
            $message = 'Month cleared successfully! All data for ' . date('F Y') . ' has been achieved and removed. Ready for next month!';
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = 'Error clearing month: ' . $e->getMessage();
        }
    }
}

$archives = [];
ensure_monthly_archive_tables($pdo);
try {
    $auto_archive_month = auto_archive_month_if_needed($pdo);
    if ($auto_archive_month) {
        $message = 'Monthly data for ' . date('F Y', strtotime($auto_archive_month . '-01')) . ' was archived automatically.';
    }

    $stmt = $pdo->query("SELECT * FROM monthly_archives ORDER BY archive_month DESC");
    $archives = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // safe fallback: table is created above
}

$current_stats = [
    'patients' => 0,
    'visits' => 0,
    'revenue' => 0,
    'expenses' => 0,
    'expenses_amount' => 0
];

$current_month_archived = false;
try {
    $current_month = date('m');
    $current_year = date('Y');
    $archive_month_key = $current_year . '-' . str_pad($current_month, 2, '0', STR_PAD_LEFT);
    
    $stmt = $pdo->prepare("SELECT id FROM monthly_archives WHERE archive_month = ?");
    $stmt->execute([$archive_month_key]);
    $current_month_archived = $stmt->rowCount() > 0;

    $stmt = $pdo->prepare("
        SELECT 
            COUNT(DISTINCT patient_id) as patients,
            COUNT(*) as visits,
            COALESCE(SUM(amount), 0) as revenue
        FROM visits
        WHERE MONTH(visit_date) = ? AND YEAR(visit_date) = ?
    ");
    $stmt->execute([$current_month, $current_year]);
    $result = $stmt->fetch();
    if ($result) {
        $current_stats['patients'] = $result['patients'];
        $current_stats['visits'] = $result['visits'];
        $current_stats['revenue'] = $result['revenue'];
    }

    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as expenses,
            COALESCE(SUM(amount), 0) as expenses_amount
        FROM expenses
        WHERE MONTH(expense_date) = ? AND YEAR(expense_date) = ?
    ");
    $stmt->execute([$current_month, $current_year]);
    $result = $stmt->fetch();
    if ($result) {
        $current_stats['expenses'] = $result['expenses'];
        $current_stats['expenses_amount'] = $result['expenses_amount'];
    }
} catch (PDOException $e) {
    // ignore and keep defaults
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo CLINIC_NAME; ?> - Manage Months</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <style>
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        body.dark-mode {
            background-color: #1a1a2e;
            color: #e0e0e0;
        }
        .dark-mode-toggle {
            position: fixed;
            top: 20px;
            right: 20px;
            background: rgba(0, 0, 0, 0.2);
            border: none;
            color: white;
            padding: 10px 15px;
            border-radius: 50px;
            cursor: pointer;
            font-size: 1.2rem;
            backdrop-filter: blur(4px);
            transition: all 0.3s ease;
            z-index: 1000;
        }
        .dark-mode-toggle:hover {
            background: rgba(0, 0, 0, 0.3);
        }
        .navbar {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        body.dark-mode .navbar {
            background: linear-gradient(135deg, #2a2a3e 0%, #1a1a2e 100%);
        }
        .card {
            border: none;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
        }
        body.dark-mode .card {
            background: #2a2a3e;
            border: 1px solid #3a3a4e;
        }
        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            text-align: center;
            border-left: 4px solid #667eea;
        }
        body.dark-mode .stat-card {
            background: #2a2a3e;
            border-color: #764ba2;
        }
        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: #667eea;
        }
        body.dark-mode .stat-value {
            color: #b0a0ff;
        }
        .stat-label {
            color: #6c757d;
            font-size: 0.9rem;
        }
        body.dark-mode .stat-label {
            color: #8080a0;
        }
        .btn-archive {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            color: white;
            padding: 10px 20px;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s;
        }
        .btn-archive:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
        .month-item {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            border-left: 4px solid #28a745;
        }
        body.dark-mode .month-item {
            background: #2a2a3e;
            border-color: #764ba2;
        }
        .action-buttons {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            margin-top: 1rem;
            align-items: center;
        }
        .month-title {
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: #1a2332;
        }
        body.dark-mode .month-title {
            color: #e0e0e0;
        }
        .month-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
            margin-top: 1rem;
        }
        .detail-item {
            text-align: center;
        }
        .detail-value {
            font-size: 1.5rem;
            font-weight: 600;
            color: #667eea;
        }
        body.dark-mode .detail-value {
            color: #b0a0ff;
        }
        .detail-label {
            font-size: 0.85rem;
            color: #6c757d;
        }
        body.dark-mode .detail-label {
            color: #8080a0;
        }
        .alert {
            border-radius: 12px;
        }
        body.dark-mode .alert {
            background: #3a3a4e;
            border-color: #4a4a5e;
            color: #e0e0e0;
        }
        body.dark-mode .alert-danger {
            background: #4a2a2a;
            border-color: #6a3a3a;
        }
        body.dark-mode .alert-success {
            background: #2a4a2a;
            border-color: #3a6a3a;
        }
        .form-label {
            color: #1a2332;
        }
        body.dark-mode .form-label {
            color: #e0e0e0;
        }
        .text-muted {
            color: #6c757d !important;
        }
        body.dark-mode .text-muted {
            color: #8080a0 !important;
        }
    </style>
</head>
<body>
    <!-- Dark Mode Toggle -->
    <button class="dark-mode-toggle" id="darkModeToggle" title="Toggle Dark Mode">
        <i class="bi bi-moon" id="themeIcon"></i>
    </button>

    <!-- Navigation -->
    <nav class="navbar navbar-dark navbar-expand-lg">
        <div class="container-fluid">
            <a class="navbar-brand" href="index.php">
                <i class="bi bi-calendar-check"></i> Monthly Management
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="index.php"><i class="bi bi-house"></i> Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container-fluid py-4">
        <!-- Messages -->
        <?php if ($message): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="bi bi-check-circle"></i> <?php echo $message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-triangle"></i> <?php echo $error; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Current Month Section -->
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="bi bi-calendar-month"></i> Current Month - <?php echo date('F Y'); ?></h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3">
                        <div class="stat-card">
                            <div class="stat-label">Patients</div>
                            <div class="stat-value"><?php echo $current_stats['patients']; ?></div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card">
                            <div class="stat-label">Visits</div>
                            <div class="stat-value"><?php echo $current_stats['visits']; ?></div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card">
                            <div class="stat-label">Revenue</div>
                            <div class="stat-value">MK<?php echo number_format($current_stats['revenue'], 2); ?></div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card">
                            <div class="stat-label">Expenses</div>
                            <div class="stat-value">MK<?php echo number_format($current_stats['expenses_amount'], 2); ?></div>
                        </div>
                    </div>
                </div>

                <div class="mt-4">
                    <h6>Month Management</h6>
                    <p class="text-muted">View and print the current month's financial report and expense records.</p>

                    <form method="POST" class="d-inline-block me-2">
                        <input type="hidden" name="action" value="archive_month">
                        <button type="submit" class="btn btn-<?php echo $current_month_archived ? 'warning' : 'success'; ?>">
                            <i class="bi bi-archive"></i> <?php echo $current_month_archived ? 'Re-Achieve' : 'Achieve'; ?>
                        </button>
                    </form>

                    <?php if ($current_month_archived): ?>
                        <form method="POST" class="d-inline-block me-2" onsubmit="return confirm('Delete the current month archive?');">
                            <input type="hidden" name="action" value="delete_archive">
                            <input type="hidden" name="month" value="<?php echo date('Y-m'); ?>">
                            <button type="submit" class="btn btn-outline-danger">
                                <i class="bi bi-trash"></i> Delete Archive
                            </button>
                        </form>
                    <?php endif; ?>

                    <a href="monthly_report.php?month=<?php echo date('Y-m'); ?>" target="_blank" class="btn btn-danger me-2">
                        <i class="bi bi-file-earmark-text"></i> Print Monthly Report
                    </a>

                    <a href="monthly_expenses.php?month=<?php echo date('Y-m'); ?>" target="_blank" class="btn btn-outline-danger me-2">
                        <i class="bi bi-wallet2"></i> Print Monthly Expenses
                    </a>
                </div>
            </div>
        </div>

        <!-- Archived Months Section -->
        <div class="card">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0"><i class="bi bi-archive"></i> Archived Months (<?php echo count($archives); ?>)</h5>
            </div>
            <div class="card-body">
                <?php if (empty($archives)): ?>
                    <p class="text-muted">No archived months yet. Print the current month report or expenses to generate a record.</p>
                <?php else: ?>
                    <?php foreach ($archives as $archive): ?>
                        <div class="month-item">
                            <div class="month-title">
                                <?php 
                                    $month_date = DateTime::createFromFormat('Y-m', $archive['archive_month']);
                                    echo $month_date->format('F Y');
                                ?>
                            </div>
                            <div class="month-details">
                                <div class="detail-item">
                                    <div class="detail-value"><?php echo $archive['total_patients']; ?></div>
                                    <div class="detail-label">Patients</div>
                                </div>
                                <div class="detail-item">
                                    <div class="detail-value"><?php echo $archive['total_visits']; ?></div>
                                    <div class="detail-label">Visits</div>
                                </div>
                                <div class="detail-item">
                                    <div class="detail-value">MK<?php echo number_format($archive['total_revenue'], 2); ?></div>
                                    <div class="detail-label">Revenue</div>
                                </div>
                                <div class="detail-item">
                                    <div class="detail-value">MK<?php echo number_format($archive['total_expenses_amount'], 2); ?></div>
                                    <div class="detail-label">Expenses</div>
                                </div>
                                <div class="detail-item">
                                    <div class="detail-value">MK<?php echo number_format($archive['total_revenue'] - $archive['total_expenses_amount'], 2); ?></div>
                                    <div class="detail-label">Profit</div>
                                </div>
                            </div>
                            <div class="action-buttons">
                                <a href="monthly_report.php?month=<?php echo urlencode($archive['archive_month']); ?>" target="_blank" class="btn btn-outline-success btn-sm">
                                    <i class="bi bi-file-earmark-text"></i> Print Report
                                </a>
                                <a href="monthly_expenses.php?month=<?php echo urlencode($archive['archive_month']); ?>" target="_blank" class="btn btn-outline-danger btn-sm">
                                    <i class="bi bi-wallet2"></i> Print Expenses
                                </a>
                                <form method="POST" class="d-inline-block" onsubmit="return confirm('Are you sure you want to delete this archived month? This action cannot be undone.');">
                                    <input type="hidden" name="action" value="delete_archive">
                                    <input type="hidden" name="month" value="<?php echo $archive['archive_month']; ?>">
                                    <button type="submit" class="btn btn-danger btn-sm">
                                        <i class="bi bi-trash"></i> Delete Archive
                                    </button>
                                </form>
                            </div>
                            <small class="text-muted mt-2 d-block">
                                <i class="bi bi-calendar-check"></i> Archived: <?php echo date('M d, Y H:i', strtotime($archive['archived_at'])); ?>
                            </small>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Initialize dark mode from localStorage
        const darkModeToggle = document.getElementById('darkModeToggle');
        const themeIcon = document.getElementById('themeIcon');
        
        const savedTheme = localStorage.getItem('theme');
        if (savedTheme === 'dark') {
            document.body.classList.add('dark-mode');
            themeIcon.className = 'bi bi-sun';
        }
        
        darkModeToggle.addEventListener('click', function() {
            document.body.classList.toggle('dark-mode');
            
            if (document.body.classList.contains('dark-mode')) {
                localStorage.setItem('theme', 'dark');
                themeIcon.className = 'bi bi-sun';
            } else {
                localStorage.setItem('theme', 'light');
                themeIcon.className = 'bi bi-moon';
            }
        });
    </script>
</body>
</html>
