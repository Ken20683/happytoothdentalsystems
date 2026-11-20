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
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim(isset($_POST['title']) ? $_POST['title'] : '');
    $category = trim(isset($_POST['category']) ? $_POST['category'] : '');
    $amount = (float)(isset($_POST['amount']) ? $_POST['amount'] : 0);
    $expense_date = isset($_POST['expense_date']) ? $_POST['expense_date'] : date('Y-m-d');
    $payment_method = trim(isset($_POST['payment_method']) ? $_POST['payment_method'] : 'Cash');
    $notes = trim(isset($_POST['notes']) ? $_POST['notes'] : '');

    if ($title === '' || $category === '' || $amount <= 0 || $expense_date === '') {
        $error = 'Please fill in all required expense fields.';
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO expenses (title, category, amount, expense_date, payment_method, notes) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$title, $category, $amount, $expense_date, $payment_method, $notes]);

            $expense_id = $pdo->lastInsertId();
            $message = 'Expense saved successfully!';
            $message .= " <a href='expense_receipt.php?id={$expense_id}' target='_blank' class='btn btn-sm btn-success ms-2'>Print Receipt</a>";
            $message .= " <a href='expenses.php' class='btn btn-sm btn-primary ms-1'>View Expenses</a>";
        } catch (PDOException $e) {
            $error = 'Error saving expense: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo CLINIC_NAME; ?> - Add Expense</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
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
        .form-card {
            background: 0000;
            border-radius: 20px;
            padding: 2rem;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
            margin: 2rem auto;
            max-width: 700px;
            width: 100%;
        }
        .form-label {
            font-weight: 600;
            color: #1a2332;
        }
        .form-control, .form-select {
            border-radius: 12px;
            padding: 12px 14px;
            border: 2px solid #e9ecef;
        }
        .form-control:focus, .form-select:focus {
            border-color: #dc3545;
            box-shadow: 0 0 0 0.2rem rgba(220, 53, 69, 0.15);
        }
        .btn-action {
            border-radius: 25px;
            padding: 12px 28px;
            font-weight: 600;
        }
        .required {
            color: #dc3545;
        }
        .footer {
            background: linear-gradient(135deg, #1a2332 0%, #2c3e50 100%);
            color: white;
            padding: 2rem 0 1rem;
            margin-top: auto;
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
                <i class="bi bi-wallet2"></i> Add Expense
            </span>
        </div>
    </nav>

    <div class="container flex-grow-1">
        <div class="form-card">
            <h3 class="mb-4"><i class="bi bi-wallet2"></i> Add Expense</h3>

            <?php if ($message): ?>
                <div class="alert alert-success"><?php echo $message; ?></div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>

            <form method="POST" action="add_expense.php">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Expense Title <span class="required">*</span></label>
                        <input type="text" name="title" class="form-control" placeholder="e.g. Dental Instruments" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Category <span class="required">*</span></label>
                        <input type="text" name="category" class="form-control" placeholder="e.g. Supplies, Rent, Utilities" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Amount (MK) <span class="required">*</span></label>
                        <input type="number" step="0.01" min="0.01" name="amount" class="form-control" placeholder="25000" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Expense Date <span class="required">*</span></label>
                        <input type="date" name="expense_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Payment Method</label>
                        <select name="payment_method" class="form-select">
                            <option value="Cash">Cash</option>
                            <option value="Card">Card</option>
                            <option value="Mobile Money">Mobile Money</option>
                            <option value="Bank Transfer">Bank Transfer</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Notes</label>
                        <input type="text" name="notes" class="form-control" placeholder="Optional details">
                    </div>
                </div>

                <div class="d-flex gap-2 flex-wrap mt-4">
                    <button type="submit" class="btn btn-danger btn-action">
                        <i class="bi bi-save"></i> Save Expense
                    </button>
                    <a href="expenses.php" class="btn btn-outline-secondary btn-action">
                        <i class="bi bi-list-ul"></i> View Expenses
                    </a>
                </div>
            </form>
        </div>
    </div>

    <footer class="footer">
        <div class="container text-center text-muted">
            <p>&copy; <?php echo date('Y'); ?> <?php echo CLINIC_NAME; ?>. All rights reserved.</p>
        </div>
    </footer>
</body>
</html>
