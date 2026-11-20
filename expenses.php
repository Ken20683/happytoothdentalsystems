<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

require_once 'config.php';
require_once 'database.php';

$message = '';       

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_expense_id'])) {
    $delete_id = (int)$_POST['delete_expense_id'];

    if ($delete_id > 0) {
        try {
            $stmt = $pdo->prepare("DELETE FROM expenses WHERE id = ?");
            $stmt->execute([$delete_id]);
            $message = 'Expense deleted successfully.';
        } catch (PDOException $e) {
            $message = 'Unable to delete expense.';
        }
    }

    header('Location: expenses.php');
    exit;
}

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$category = isset($_GET['category']) ? trim($_GET['category']) : '';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';

$query = "SELECT id, title, category, amount, expense_date, payment_method, notes FROM expenses WHERE 1=1";
$params = [];

if ($search !== '') {
    $query .= " AND (title LIKE ? OR category LIKE ? OR notes LIKE ?)";
    $like = "%$search%";
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

if ($category !== '') {
    $query .= " AND category = ?";
    $params[] = $category;
}

if ($date_from !== '') {
    $query .= " AND expense_date >= ?";
    $params[] = $date_from;
}

if ($date_to !== '') {
    $query .= " AND expense_date <= ?";
    $params[] = $date_to;
}

$query .= " ORDER BY expense_date DESC, created_at DESC";

$expenses = [];
$total_expenses = 0;

try {
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $expenses = $stmt->fetchAll();

    foreach ($expenses as $expense) {
        $total_expenses += (float)$expense['amount'];
    }
} catch (PDOException $e) {
    $expenses = [];
    $total_expenses = 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo CLINIC_NAME; ?> - Expenses</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #08367ac9 0%, #c3cfe2 100%);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        .navbar-custom {
            background: linear-gradient(135deg, #1a2332 0%, #2c3e50 100%);
            box-shadow: 0 2px 20px rgba(0,0,0,0.3);
            padding: 0.8rem 0;
        }
        .navbar-brand {
            color: #ffffff !important;
            font-weight: bold;
            font-size: 1.5rem;
            letter-spacing: 1px;
        }
        .navbar-brand i {
            margin-right: 8px;
            color: #f1c40f;
        }
        .main-card {
            background: white;
            border-radius: 20px;
            padding: 2rem;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
            margin: 2rem auto;
        }
        .btn-action {
            border-radius: 25px;
            padding: 10px 25px;
            font-weight: 500;
        }
        .filter-box {
            background: #20c043;
            border: 1px solid #f5c2c7;
            border-radius: 12px;
            padding: 1rem;
            margin-bottom: 1rem;
        }
        .stat-box {
            background: #f8f9fa9f;
            border-radius: 12px;
            padding: 1rem;
            border-left: 4px solid #dc3545;
        }
        .amount {
            color: #dc3545;
            font-weight: 700;
        }
        .footer {
            background: linear-gradient(135deg, #1a2332 0%, #2c3e50 100%);
            color: white;
            padding: 2rem 0 1rem;
            margin-top: auto;
        }
        @media print {
            .no-print,
            .filter-box,
            .navbar-custom,
            .footer {
                display: none !important;
            }
            body {
                background: white;
            }
            .main-card {
                box-shadow: none;
                border: 1px solid #6525ad;
                margin: 0;
                padding: 0;
            }
            .table {
                font-size: 12px;
            }
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-custom">
        <div class="container">
            <a href="index.php" class="navbar-brand">
                <i class="bi bi-arrow-left"></i> Back to Dashboard
            </a>
            <span class="navbar-brand">
                <i class="bi bi-wallet2"></i> Expenses
            </span>
        </div>
    </nav>

    <div class="container flex-grow-1">
        <div class="main-card">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                <h3 class="mb-0"><i class="bi bi-wallet2"></i> All Expenses</h3>
                <div class="d-flex gap-2 flex-wrap no-print">
                    <button type="button" class="btn btn-outline-dark btn-action" onclick="window.print();">
                        <i class="bi bi-printer"></i> Print All
                    </button>
                    <a href="add_expense.php" class="btn btn-danger btn-action">
                        <i class="bi bi-plus-circle"></i> Add Expense
                    </a>
                </div>
            </div>

            <?php if (!empty($message)): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <div class="stat-box mb-3">
                <div class="row align-items-center">
                    <div class="col-md-6">
                        <h6 class="mb-1 text-muted">Total Expenses</h6>
                        <h3 class="mb-0 amount">MK <?php echo number_format($total_expenses, 0); ?></h3>
                    </div>
                    <div class="col-md-6 text-md-end">
                        <small class="text-muted"><?php echo count($expenses); ?> records</small>
                    </div>
                </div>
            </div>

            <div class="filter-box">
                <form method="GET" class="row g-2 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label">Search</label>
                        <input type="text" class="form-control" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Title / category / notes">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Category</label>
                        <input type="text" class="form-control" name="category" value="<?php echo htmlspecialchars($category); ?>" placeholder="e.g. Rent">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">From</label>
                        <input type="date" class="form-control" name="date_from" value="<?php echo htmlspecialchars($date_from); ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">To</label>
                        <input type="date" class="form-control" name="date_to" value="<?php echo htmlspecialchars($date_to); ?>">
                    </div>
                    <div class="col-md-2 d-grid">
                        <button type="submit" class="btn btn-primary btn-action">
                            <i class="bi bi-search"></i> Filter
                        </button>
                    </div>
                </form>
            </div>

            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Title</th>
                            <th>Category</th>
                            <th>Date</th>
                            <th>Method</th>
                            <th>Notes</th>
                            <th class="text-end">Amount</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($expenses)): ?>
                            <?php foreach ($expenses as $expense): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($expense['id']); ?></td>
                                    <td><?php echo htmlspecialchars($expense['title']); ?></td>
                                    <td><?php echo htmlspecialchars($expense['category']); ?></td>
                                    <td><?php echo date('d M Y', strtotime($expense['expense_date'])); ?></td>
                                    <td><?php echo htmlspecialchars($expense['payment_method']); ?></td>
                                    <td><?php echo htmlspecialchars($expense['notes'] ?: '—'); ?></td>
                                    <td class="text-end amount">MK <?php echo number_format($expense['amount'], 0); ?></td>
                                    <td class="text-center">
                                        <div class="d-flex justify-content-center gap-2">
                                            <a href="expense_receipt.php?id=<?php echo $expense['id']; ?>" target="_blank" class="btn btn-sm btn-outline-primary" title="Print receipt">
                                                <i class="bi bi-printer"></i>
                                            </a>
                                            <form method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this expense?');">
                                                <input type="hidden" name="delete_expense_id" value="<?php echo (int)$expense['id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete expense">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" class="text-center text-muted py-4">No expenses found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <footer class="footer">
        <div class="container text-center text-muted">
            <p>&copy; <?php echo date('Y'); ?> <?php echo CLINIC_NAME; ?>. All rights reserved.</p>
        </div>
    </footer>
</body>
</html>
