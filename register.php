<?php
// register.php - User registration page
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
$username = '';
$email = '';
$full_name = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $email = trim($_POST['email']);
    $full_name = trim($_POST['full_name']);
    
    // Validation
    if (empty($username) || empty($password) || empty($email) || empty($full_name)) {
        $error = 'All fields are required';
    } elseif (strlen($username) < 3) {
        $error = 'Username must be at least 3 characters long';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters long';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address';
    } else {
        try {
            // Check if username or email already exists
            $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM users WHERE username = ? OR email = ?");
            $stmt->execute([$username, $email]);
            $exists = $stmt->fetch()['count'];
            
            if ($exists > 0) {
                $error = 'Username or email already exists';
            } else {
                // Hash password and insert user
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO users (username, password, email, full_name, role) VALUES (?, ?, ?, ?, 'staff')");
                $stmt->execute([$username, $hashed_password, $email, $full_name]);
                
                $success = 'Account created successfully! You can now <a href="login.php">login</a>.';
                // Clear form fields
                $username = $email = $full_name = '';
            }
        } catch(PDOException $e) {
            $error = 'Registration failed: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo CLINIC_NAME; ?> - Create Account</title>
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
            max-width: 480px;
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
        .register-container {
            background: white;
            border-radius: 20px;
            padding: 3rem;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            max-width: 480px;
            width: 100%;
            animation: fadeInUp 0.6s ease;
            transition: all 0.3s ease;
        }
        body.dark-mode .register-container {
            background: #2a2a3e;
            color: #e0e0e0;
        }
        .register-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        .register-header h2 {
            color: #1a2332;
            font-weight: 700;
        }
        body.dark-mode .register-header h2 {
            color: #e0e0e0;
        }
        .register-header .clinic-name {
            color: #000000;
            font-size: 1.2rem;
        }
        body.dark-mode .register-header .clinic-name {
            color: #b0b0c0;
        }
        .register-header .icon {
            font-size: 3rem;
            color: #2d8cf0;
            margin-bottom: 0.5rem;
        }
        .form-control {
            border-radius: 12px;
            padding: 12px 16px;
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
        .btn-register {
            border-radius: 12px;
            padding: 12px;
            font-weight: 600;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            transition: all 0.3s;
            width: 100%;
        }
        .btn-register:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(102, 126, 234, 0.4);
        }
        .login-link {
            text-align: center;
            margin-top: 1.5rem;
        }
        .login-link a {
            color: #2d8cf0;
            text-decoration: none;
            font-weight: 600;
        }
        .login-link a:hover {
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
        body.dark-mode .alert-warning {
            background: #4a3a2a;
            border-color: #6a5a3a;
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
        .text-muted {
            color: #6c757d !important;
        }
        body.dark-mode .text-muted {
            color: #8080a0 !important;
        }
        .input-group-text {
            background: white;
            border: 2px solid #e9ecef;
            border-right: none;
            color: #6c757d;
        }
        body.dark-mode .input-group-text {
            background: #3a3a4e;
            border-color: #4a4a5e;
            color: #b0b0c0;
        }
        .input-group .form-control {
            border-left: none;
        }
        .input-group .form-control:focus {
            border-color: #2d8cf0;
        }
        .input-group .form-control:focus + .input-group-text {
            border-color: #2d8cf0;
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
    <div class="register-container">
        <div class="register-header">
            <div class="icon">
                <i class="bi bi-exclamation-circle" style="color: #dc3545;"></i>
            </div>
            <h2>Registration Disabled</h2>
            <p class="clinic-name"><?php echo CLINIC_NAME; ?></p>
        </div>

        <div class="alert alert-warning alert-dismissible fade show">
            <i class="bi bi-info-circle"></i> 
            <strong>Registration is disabled.</strong> This system is for authorized administrators only. 
            Please contact your system administrator if you need access.
        </div>

        <div class="text-center mt-4">
            <p class="text-muted mb-3">Return to login page:</p>
            <a href="login.php" class="btn btn-primary" style="width: 100%; border-radius: 12px; padding: 12px;">
                <i class="bi bi-arrow-left"></i> Back to Login
            </a>
        </div>
    </div>

    <script>
        // Initialize dark mode from localStorage
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
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>