<?php
// login.php - User login page
session_start();

// If already logged in, redirect to dashboard
if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

require_once 'config.php';
require_once 'database.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = trim(isset($_POST['password']) ? $_POST['password'] : '');
    $admin_password = 'happytoothdental25c@gmail.com';

    try {
        $stmt = $pdo->prepare("SELECT setting_value FROM clinic_settings WHERE setting_key = 'admin_password_hash' LIMIT 1");
        $stmt->execute();
        $stored_hash = $stmt->fetchColumn();
    } catch (PDOException $e) {
        $stored_hash = false;
    }
    
    if (empty($password)) {
        $error = 'Please enter the password';
    } else {
        // Check if password matches the admin password
        if (($stored_hash && password_verify($password, $stored_hash)) || (!$stored_hash && $password === $admin_password)) {
            // Set session variables for admin access
            $_SESSION['user_id'] = 1;
            $_SESSION['username'] = 'admin';
            $_SESSION['full_name'] = 'Administrator';
            $_SESSION['role'] = 'admin';
            
            // Redirect to dashboard
            header("Location: index.php");
            exit();
        } else {
            $error = 'Invalid password';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo CLINIC_NAME; ?> - Login</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 1rem;
            padding: 2rem 0;
            transition: background 0.3s ease;
        }
        body.dark-mode {
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
        }
        .dark-mode-toggle {
            position: fixed;
            top: 20px;
            right: 20px;
            background: rgba(255, 255, 255, 0.2);
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
            background: rgba(255, 255, 255, 0.3);
            transform: scale(1.1);
        }
        body.dark-mode .dark-mode-toggle {
            background: rgba(100, 100, 120, 0.4);
        }
        body.dark-mode .dark-mode-toggle:hover {
            background: rgba(100, 100, 120, 0.6);
        }
        .top-nav {
            width: 100%;
            max-width: 420px;
            display: flex;
            justify-content: flex-start;
        }
        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: #ffffff !important;
            text-decoration: none;
            font-weight: 600;
            background: rgba(255,255,255,0.15);
            padding: 10px 16px;
            border-radius: 999px;
            backdrop-filter: blur(4px);
        }
        .back-link:hover {
            color: #ffffff !important;
            text-decoration: none;
            background: rgba(255,255,255,0.25);
        }
        .login-container {
            background: white;
            border-radius: 10px;
            padding: 1rem 2rem;
            box-shadow: 0 5px 10px rgba(0,0,0,0.3);
            max-width: 360px;
            width: 100%;
            margin: 0 1rem;
            animation: fadeInUp 0.6s ease;
            transition: all 0.3s ease;
        }
        body.dark-mode .login-container {
            background: #2a2a3e;
            color: #e0e0e0;
        }
        .login-header {
            text-align: center;
            margin-bottom: 0.75rem;
        }
        .login-header h2 {
            color: #1a2332;
            font-weight: 700;
        }
        body.dark-mode .login-header h2 {
            color: #e0e0e0;
        }
        .login-header .clinic-name {
            color: #000000;
            font-size: 1.2rem;
        }
        body.dark-mode .login-header .clinic-name {
            color: #b0b0c0;
        }
        .login-header .icon {
            font-size: 3rem;
            color: #2d8cf0;
            margin-bottom: 0.25rem;
        }
        .form-control {
            border-radius: 12px;
            padding: 9px 14px;
            border: 2px solid #e9ecef;
            transition: all 0.3s;
        }
        body.dark-mode .form-control {
            background: #3a3a4e;
            border-color: #4a4a5e;
            color: #e0e0e0;
        }
        body.dark-mode .form-control::placeholder {
            color: #8080a0;
        }
        .form-control:focus {
            border-color: #2d8cf0;
            box-shadow: 0 0 0 0.2rem rgba(45, 140, 240, 0.25);
        }
        body.dark-mode .form-control:focus {
            background: #3a3a4e;
            border-color: #2d8cf0;
        }
        .btn-login {
            border-radius: 12px;
            padding: 9px;
            font-weight: 600;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            transition: all 0.3s;
            width: 100%;
        }
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(102, 126, 234, 0.4);
        }
        .register-link {
            text-align: center;
            margin-top: 1.5rem;
        }
        .register-link a {
            color: #2d8cf0;
            text-decoration: none;
            font-weight: 600;
        }
        .register-link a:hover {
            text-decoration: underline;
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
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        .input-group {
            position: relative;
        }
        .input-group .password-toggle {
            position: relative;
            border: 2px solid #e9ecef;
            border-left: none;
            background: white;
            padding: 0 12px;
            display: flex;
            align-items: center;
            cursor: pointer;
            transition: all 0.3s;
        }
        body.dark-mode .input-group .password-toggle {
            background: #3a3a4e;
            border-color: #4a4a5e;
        }
        .input-group .password-toggle:hover {
            color: #667eea;
        }
        .input-group .form-control:focus ~ .password-toggle {
            border-color: #2d8cf0;
        }
        .text-muted {
            color: #6c757d !important;
        }
        body.dark-mode .text-muted {
            color: #8080a0 !important;
        }
        .form-label {
            color: #1a2332;
        }
        body.dark-mode .form-label {
            color: #e0e0e0;
        }
        .input-group-text {
            background: white;
            border: 2px solid #e9ecef;
            color: #6c757d;
        }
        body.dark-mode .input-group-text {
            background: #3a3a4e;
            border-color: #4a4a5e;
            color: #b0b0c0;
        }
        .login-footer {
            width: 100%;
            max-width: 300px;
            text-align: center;
            color: #ffffff;
            font-size: 0.72rem;
            line-height: 1.2;
        }
        .login-footer a {
            color: #25d366;
            font-weight: 700;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            margin-left: 4px;
        }
        .login-footer a:hover {
            color: #8ff0ae;
            text-decoration: underline;
        }
        @media (max-width: 480px) {
            body {
                padding: 1rem 0;
            }
            .login-container {
                padding: 1.5rem;
            }
            .top-nav,
            .login-footer {
                width: calc(100% - 2rem);
            }
        }
    </style>
</head>
<body>
    <!-- Dark Mode Toggle Button -->
    <button class="dark-mode-toggle" id="darkModeToggle" title="Toggle Dark Mode">
        <i class="bi bi-moon" id="themeIcon"></i>
    </button>
    <div class="top-nav">
        <a href="index.php" class="back-link">
            <i class="bi bi-arrow-left"></i> Back to Dashboard
        </a>
    </div>
    <div class="login-container">
        <div class="login-header">
            <div class="icon">
                <img src="Screenshot%20(1)_5509.jpg" alt="Happy Tooth Dental logo">
            </div>
            <h2>Welcome Back</h2>
            <p class="clinic-name"><?php echo CLINIC_NAME; ?></p>
            <p class="text-muted">Sign in to your account</p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger">
                <i class="bi bi-exclamation-triangle"></i> <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success">
                <i class="bi bi-check-circle"></i> <?php echo $success; ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <div class="mb-3">
                <label for="password" class="form-label">Administrator Password</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-lock"></i></span>
                    <input type="password" class="form-control" id="password" name="password" 
                           placeholder="Enter administrator password" required autofocus>
                    <span class="input-group-text password-toggle" onclick="togglePassword()">
                        <i class="bi bi-eye" id="toggleIcon"></i>
                    </span>
                </div>
            </div>
            <button type="submit" class="btn btn-primary btn-login">
                <i class="bi bi-box-arrow-in-right"></i> Sign In
            </button>
        </form>
        
        <hr class="my-3">
        <div class="text-center text-muted small">
            <i class="bi bi-shield-check"></i> Secure administrator access only
        </div>
    </div>

    <footer class="login-footer">
        Contact us on WhatsApp:
        <a href="https://wa.me/265882407607" target="_blank" rel="noopener noreferrer">
            <i class="bi bi-whatsapp"></i> 0882407607
        </a>
    </footer>

    <script>
        // Initialize dark mode from localStorage
        const html = document.documentElement;
        const darkModeToggle = document.getElementById('darkModeToggle');
        const themeIcon = document.getElementById('themeIcon');
        
        // Check if user has a saved preference
        const savedTheme = localStorage.getItem('theme');
        if (savedTheme === 'dark') {
            document.body.classList.add('dark-mode');
            themeIcon.className = 'bi bi-sun';
        }
        
        // Toggle dark mode
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
        
        // Password visibility toggle
        function togglePassword() {
            const passwordField = document.getElementById('password');
            const toggleIcon = document.getElementById('toggleIcon');
            if (passwordField.type === 'password') {
                passwordField.type = 'text';
                toggleIcon.className = 'bi bi-eye-slash';
            } else {
                passwordField.type = 'password';
                toggleIcon.className = 'bi bi-eye';
            }
        }
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>