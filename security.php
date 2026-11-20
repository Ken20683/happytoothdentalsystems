<?php
// security.php - Administrator credential settings
session_start();

if (!isset($_SESSION['user_id']) || strtolower(isset($_SESSION['role']) ? $_SESSION['role'] : '') !== 'admin') {
    header('Location: login.php');
    exit();
}

require_once 'config.php';
require_once 'database.php';

$error = '';
$success = '';

try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS clinic_settings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        setting_key VARCHAR(50) UNIQUE NOT NULL,
        setting_value TEXT,
        description VARCHAR(255),
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");
} catch (PDOException $e) {
    $error = 'Security settings are currently unavailable.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$error) {
    $new_password = trim(isset($_POST['new_password']) ? $_POST['new_password'] : '');
    $confirm_password = trim(isset($_POST['confirm_password']) ? $_POST['confirm_password'] : '');

    if ($new_password === '' || $confirm_password === '') {
        $error = 'Enter the new email or PIN in both fields.';
    } elseif ($new_password !== $confirm_password) {
        $error = 'The email or PIN values do not match.';
    } elseif (filter_var($new_password, FILTER_VALIDATE_EMAIL) === false && !preg_match('/^\d{4,12}$/', $new_password)) {
        $error = 'Use a valid email address or a PIN containing 4 to 12 digits.';
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO clinic_settings (setting_key, setting_value, description)
                VALUES ('admin_password_hash', ?, 'Hashed administrator email or PIN')
                ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
            $stmt->execute([password_hash($new_password, PASSWORD_DEFAULT)]);
            $success = 'Administrator password updated successfully.';
        } catch (PDOException $e) {
            $error = 'The password could not be updated. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo CLINIC_NAME; ?> - Security</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <style>
        body { background: linear-gradient(135deg, #3a15db, #dce9f7); min-height: 100vh; }
        .security-card { max-width:400px; margin: 7vh auto; border: 0; border-radius: 16px; box-shadow: 0 16px 40px rgba(31, 55, 79, 0.16); }
        .security-icon { font-size: 3rem; color: #198754; }
        .form-control { border-radius: 10px; padding: 11px 14px; }
    </style>
</head>
<body>
    <main class="container">
        <div class="card security-card">
            <div class="card-body p-4 p-md-5">
                <div class="text-center mb-4">
                    <div class="security-icon"><i class="bi bi-shield-lock"></i></div>
                    <h2 class="mt-2">Security Settings</h2>
                    <p class="text-muted mb-0">Change the administrator login password.</p>
                </div>

                <?php if ($error): ?>
                    <div class="alert alert-danger"><i class="bi bi-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>
                <?php if ($success): ?>
                    <div class="alert alert-success"><i class="bi bi-check-circle"></i> <?php echo htmlspecialchars($success); ?></div>
                <?php endif; ?>

                <form method="POST">
                    <div class="mb-3">
                        <label for="new_password" class="form-label">New email or PIN</label>
                        <input type="password" class="form-control" id="new_password" name="new_password" placeholder="Email address or 4-12 digit PIN" required autofocus>
                    </div>
                    <div class="mb-4">
                        <label for="confirm_password" class="form-label">Confirm email or PIN</label>
                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                    </div>
                    <button type="submit" class="btn btn-success w-100">
                        <i class="bi bi-check2-circle"></i> Update Password
                    </button>
                </form>
                <a href="index.php" class="btn btn-outline-secondary w-100 mt-3">
                    <i class="bi bi-arrow-left"></i> Back to Dashboard
                </a>
            </div>
        </div>
    </main>
</body>
</html>
