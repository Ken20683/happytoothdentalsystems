<?php
// index.php - Main dashboard with footer
session_start();

// Check if user is logged in, if not redirect to login page
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

require_once 'config.php';
require_once 'database.php';

// Check if tables exist, if not redirect to install
try {
    $pdo->query("SELECT 1 FROM patients LIMIT 1");
} catch(PDOException $e) {
    header("Location: install.php");
    exit();
}

// Get statistics
$total_patients = 0;
$total_visits = 0;
$total_revenue = 0;
$today_visits = 0;
$today_revenue = 0;
$monthly_visits = 0;

try {
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM patients");
    $total_patients = $stmt->fetch()['total'];

    $stmt = $pdo->query("SELECT COUNT(*) as total FROM visits");
    $total_visits = $stmt->fetch()['total'];

    $stmt = $pdo->query("SELECT SUM(amount) as total FROM visits");
    $result = $stmt->fetch();
    $total_revenue = $result && $result['total'] ? $result['total'] : 0;

    $stmt = $pdo->prepare("SELECT COUNT(*) as total, SUM(amount) as revenue FROM visits WHERE DATE(visit_date) = CURDATE()");
    $stmt->execute();
    $today = $stmt->fetch();
    $today_visits = $today ? $today['total'] : 0;
    $today_revenue = $today && $today['revenue'] ? $today['revenue'] : 0;

    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM visits WHERE MONTH(visit_date) = MONTH(CURDATE()) AND YEAR(visit_date) = YEAR(CURDATE())");
    $stmt->execute();
    $monthly_visits = $stmt->fetch()['total'];

} catch(PDOException $e) {
    // Tables might not exist yet
}

// Get procedure statistics
$procedure_stats = [];
$procedure_names = [];
$procedure_counts = [];
$procedure_amounts = [];
$grand_total = 0;
$grand_patients = 0;

try {
    foreach ($procedures as $id => $name) {
        $stmt = $pdo->prepare("SELECT COUNT(*) as count, COALESCE(SUM(amount), 0) as total FROM visits WHERE procedure_id = ?");
        $stmt->execute([$id]);
        $result = $stmt->fetch();
        $count = $result ? (int)$result['count'] : 0;
        $total = $result ? (float)$result['total'] : 0;
        
        $procedure_stats[$id] = [
            'name' => $name,
            'count' => $count,
            'total' => $total
        ];
        
        if ($count > 0) {
            $procedure_names[] = $name;
            $procedure_counts[] = $count;
            $procedure_amounts[] = $total;
        }
        
        $grand_patients += $count;
        $grand_total += $total;
    }
} catch(PDOException $e) {
    foreach ($procedures as $id => $name) {
        $procedure_stats[$id] = [
            'name' => $name,
            'count' => 0,
            'total' => 0
        ];
    }
}

// Get monthly data for chart
$monthly_data = [];
for ($i = 5; $i >= 0; $i--) {
    $month = date('Y-m', strtotime("-$i months"));
    $month_name = date('M', strtotime("-$i months"));
    
    try {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as visits, COALESCE(SUM(amount), 0) as revenue 
            FROM visits 
            WHERE DATE_FORMAT(visit_date, '%Y-%m') = ?
        ");
        $stmt->execute([$month]);
        $result = $stmt->fetch();
        $monthly_data[] = [
            'month' => $month_name,
            'visits' => $result ? (int)$result['visits'] : 0,
            'revenue' => $result ? (float)$result['revenue'] : 0
        ];
    } catch(PDOException $e) {
        $monthly_data[] = [
            'month' => $month_name,
            'visits' => 0,
            'revenue' => 0
        ];
    }
}

// Get recent visits
$recent_visits = [];
try {
    $stmt = $pdo->query("
        SELECT v.id, p.name as patient_name, p.phone, pr.name as procedure_name, 
               v.visit_date, v.amount 
        FROM visits v 
        JOIN patients p ON v.patient_id = p.id 
        JOIN procedures pr ON v.procedure_id = pr.id 
        ORDER BY v.visit_date DESC, v.created_at DESC 
        LIMIT 10
    ");
    $recent_visits = $stmt->fetchAll();
} catch(PDOException $e) {
    $recent_visits = [];
}

// Get expense statistics
$total_expenses = 0;
$today_expenses = 0;
$monthly_expenses = 0;
try {
    $stmt = $pdo->query("SELECT COALESCE(SUM(amount), 0) as total FROM expenses");
    $row = $stmt->fetch();
    $total_expenses = (float)($row && isset($row['total']) ? $row['total'] : 0);

    $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) as total FROM expenses WHERE DATE(expense_date) = CURDATE()");
    $stmt->execute();
    $row = $stmt->fetch();
    $today_expenses = (float)($row && isset($row['total']) ? $row['total'] : 0);

    $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) as total FROM expenses WHERE MONTH(expense_date) = MONTH(CURDATE()) AND YEAR(expense_date) = YEAR(CURDATE())");
    $stmt->execute();
    $row = $stmt->fetch();
    $monthly_expenses = (float)($row && isset($row['total']) ? $row['total'] : 0);

} catch(PDOException $e) {
    $total_expenses = 0;
    $today_expenses = 0;
    $monthly_expenses = 0;
}

$growth_rate = 0;
if ($total_patients > 0 && $monthly_visits > 0) {
    $growth_rate = round(($monthly_visits / $total_patients) * 100);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo CLINIC_NAME; ?> - Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        /* Same styles as before with footer */
        :root {
            --primary-color: #12d161;
            --secondary-color: #2d8cf0;
            --accent-color: #f093fb;
            --success-color: #4bc0c0;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: rgb(26, 92, 190)d0;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        
        .navbar-custom {
            background: linear-gradient(135deg, #50a53f 0%, #2c3e50 100%);
            box-shadow: 0 2px 20px rgba(101, 235, 23, 0.88);
            padding: 0.8rem 0;
        }
        
        .navbar-brand {
            font-weight: bold;
            color: #ffffff !important;
            font-size: 1.6rem;
            letter-spacing: 1px;
            display: inline-block;
        }
        
        .navbar-brand i {
            margin-right: 10px;
            color: #da144fef;
        }

        .brand-logo {
            width: 42px;
            height: 42px;
            object-fit: contain;
            border-radius: 50%;
            background: rgba(255,255,255,0.9);
            padding: 4px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.12);
            margin-right: 10px;
            vertical-align: middle;
        }
        
        .btn-action {
            border-radius: 8px;
            padding: 4px 10px;
            font-weight: 500;
            transition: all 0.3s;
            font-size: 0.75rem;
            white-space: nowrap;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        
        .btn-action:hover {
            transform: translateY(-1px);
            box-shadow: 0 3px 10px rgba(38, 233, 38, 0.6);
        }
        
        .btn-action i {
            font-size: 0.9rem;
        }
        
        .btn-action .btn-text {
            display: inline;
        }
        
        .navbar-nav {
            gap: 0.2rem;
            flex-wrap: wrap;
            justify-content: flex-end;
        }
        
        .nav-item {
            margin: 2px;
        }
        
        .navbar-collapse {
            flex-basis: 100%;
        }
        
        @media (max-width: 1200px) {
            .btn-action .btn-text {
                display: none;
            }
            
            .btn-action {
                padding: 6px 8px;
            }
        }
        
        .header-card {
            background: linear-gradient(135deg, #1db632 0%, #2c3e50 100%);
            color: white;
            padding: 2rem;
            border-radius: 20px;
            margin-bottom: 2rem;
            box-shadow: 0 10px 40px rgba(13, 153, 13, 0.84);
            position: relative;
            overflow: hidden;
        }
        
        .header-card::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -20%;
            width: 300px;
            height: 300px;
            background: rgba(45, 140, 240, 0.1);
            border-radius: 50%;
        }
        
        .header-card h1 {
            font-size: 2.8rem;
            font-weight: 300;
            color: #cf1212da;
            position: relative;
            z-index: 1;
            display: inline-block;
        }
        
        .header-card .contact-info {
            font-size: 1rem;
            opacity: 0.9;
            position: relative;
            z-index: 1;
        }
        
        .header-card p {
            position: relative;
        }
        
        .stat-card {
            background: white;
            border-radius: 20px;
            padding: 1.5rem;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
            transition: all 0.3s;
            margin-bottom: 1rem;
            border-left: 5px solid var(--secondary-color);
            position: relative;
            overflow: hidden;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.15);
        }
        
        .stat-card .icon {
            font-size: 2.5rem;
            margin-bottom: 0.5rem;
            opacity: 0.8;
        }
        
        .stat-card .number {
            font-size: 2.2rem;
            font-weight: bold;
            color: var(--primary-color);
        }
        
        .stat-card .label {
            color: #7f8c8d;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .stat-card .growth {
            position: absolute;
            top: 1rem;
            right: 1rem;
            font-size: 0.8rem;
            padding: 3px 12px;
            border-radius: 50px;
            background: #1111d8cb;
            color: #2e7d32;
        }
        
        .stat-card.patients { border-left-color: #4caf50; }
        .stat-card.visits { border-left-color: #2196f3; }
        .stat-card.revenue { border-left-color: #ff9800; }
        .stat-card.today { bordeHAr-left-color: #9c27b0; }
        
        .chart-card {
            background: white;
            border-radius: 3px;
            padding: 0.1rem;
            box-shadow: 0 3px 3px rgba(0,0,0,0.08);
            margin-bottom: 0.1rem;
            transition: all 0.3s;
        }
        
        .chart-card:hover {
            box-shadow: 0 5px 10px rgba(0,0,0,0.12);
        }
        
        .chart-card h5 {
            color: var(--primary-color);
            font-weight: 600;
            margin-bottom: 1.5rem;
        }
        
        .chart-card h5 i {
            margin-right: 10px;
            color: var(--secondary-color);
        }
        
        .procedure-table {
            background: green;
            border-radius: 20px;
            padding: 1.5rem;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
            margin-top: 1.5rem;
        }
        
        .procedure-table-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            margin-bottom: 1rem;
            flex-wrap: wrap;
        }
        
        .procedure-summary-panel {
            display: none;
            margin-top: 1rem;
        }
        
        .procedure-table h5 {
            color: var(--primary-color);
            font-weight: 600;
            margin-bottom: 0;
        }
        
        .procedure-table h5 i {
            margin-right: 10px;
            color: var(--secondary-color);
        }
        
        .procedure-table th {
            background: var(--primary-color);
            color: white;
            border: none;
            padding: 12px;
            font-weight: 500;
        }
        
        .procedure-table td {
            vertical-align: middle;
            padding: 10px;
        }
        
        .procedure-count {
            display: inline-block;
            background: var(--secondary-color);
            color: white;
            border-radius: 50%;
            width: 32px;
            height: 32px;
            text-align: center;
            line-height: 32px;
            font-weight: bold;
            font-size: 0.9rem;
        }
        
        .total-row {
            background: #0a77e4e7;
            font-weight: bold;
        }
        
        .total-row td {
            border-top: 3px solid var(--primary-color);
            padding: 15px;
        }
        
        .progress-bar-custom {
            height: 8px;
            border-radius: 10px;
            background: #1564b3;
            margin-top: 5px;
        }
        
        .progress-bar-custom .progress-fill {
            height: 100%;
            border-radius: 10px;
            background: linear-gradient(90deg, var(--secondary-color), var(--accent-color));
            transition: width 1s ease;
        }
        
        .footer {
            background: linear-gradient(135deg, #1a2332 0%, #2c3e50 100%);
            color: white;
            padding: 2rem 0 1rem;
            margin-top: auto;
        }
        
        .footer a {
            color: #f1c40f;
            text-decoration: none;
        }
        
        .footer a:hover {
            color: #fff;
            text-decoration: underline;
        }
        
        .footer .social-icons i {
            font-size: 1.5rem;
            margin: 0 10px;
            transition: all 0.3s;
        }
        
        .footer .social-icons i:hover {
            transform: translateY(-3px);
            color: #f1c40f;
        }
        
        .footer .col-md-4:last-child li {
            color: #12d161;
            font-weight: 500;
            margin-bottom: 0.5rem;
        }
        
        .footer .col-md-4:last-child li i {
            color: #12d161;
            margin-right: 8px;
        }
        
        @media (max-width: 768px) {
            .header-card h1 {
                font-size: 1.8rem;
            }
            .navbar-brand {
                font-size: 1.2rem;
            }
            .stat-card .number {
                font-size: 1.5rem;
            }
            .btn-action {
                padding: 4px 6px;
                font-size: 0.7rem;
            }
            .btn-action .btn-text {
                display: none;
            }
            .navbar-nav {
                gap: 0.15rem;
            }
        }
        
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        @keyframes colorPulse {
            0% {
                color: #FF1493;
                text-shadow: 0 0 10px #FF1493, 0 0 20px #FF1493, 0 0 30px #FF1493;
            }
            25% {
                color: #00CED1;
                text-shadow: 0 0 10px #00CED1, 0 0 20px #00CED1, 0 0 30px #00CED1;
            }
            50% {
                color: #FFD700;
                text-shadow: 0 0 10px #FFD700, 0 0 20px #FFD700, 0 0 30px #FFD700;
            }
            75% {
                color: #00FF00;
                text-shadow: 0 0 10px #00FF00, 0 0 20px #00FF00, 0 0 30px #00FF00;
            }
            100% {
                color: #FF1493;
                text-shadow: 0 0 10px #FF1493, 0 0 20px #FF1493, 0 0 30px #FF1493;
            }
        }
        
        @keyframes bounceGlow {
            0%, 100% {
                transform: scale(1);
                text-shadow: 0 0 10px #FF1493, 0 0 20px #FF1493;
            }
            50% {
                transform: scale(1.05);
                text-shadow: 0 0 20px #00CED1, 0 0 30px #00CED1, 0 0 40px #00CED1;
            }
        }
        
        @keyframes floatUpDown {
            0%, 100% {
                transform: translateY(0px);
                opacity: 1;
            }
            50% {
                transform: translateY(-10px);
                opacity: 1;
            }
        }
        
        .animate-title {
            animation: colorPulse 4s infinite, bounceGlow 2s ease-in-out infinite;
            font-weight: bold;
        }
        
        .animate {
            animation: fadeInUp 0.5s ease forwards;
        }
        
        .delay-1 { animation-delay: 0.1s; }
        .delay-2 { animation-delay: 0.2s; }
        .delay-3 { animation-delay: 0.3s; }
        .delay-4 { animation-delay: 0.4s; }
        
        /* Dark Mode Styles */
        body.dark-mode {
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
            color: #e0e0e0;
        }
        
        .dark-mode-toggle {
            background: rgba(255, 255, 255, 0.2);
            border: none;
            color: white;
            padding: 6px 12px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 1rem;
            transition: all 0.3s ease;
            z-index: 1000;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        
        .dark-mode-toggle:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: scale(1.05);
        }
        
        body.dark-mode .dark-mode-toggle {
            background: rgba(100, 100, 120, 0.4);
            color: #e0e0e0;
        }
        
        body.dark-mode .dark-mode-toggle:hover {
            background: rgba(100, 100, 120, 0.6);
        }
        
        body.dark-mode .navbar-custom {
            background: linear-gradient(135deg, #1a2332 0%, #1d3557 100%);
            box-shadow: 0 2px 20px rgba(0, 0, 0, 0.5);
        }
        
        body.dark-mode .header-card {
            background: linear-gradient(135deg, #1d3557 0%, #2c3e50 100%);
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.5);
        }
        
        body.dark-mode .header-card h1 {
            color: #f1c40f;
        }
        
        body.dark-mode .stat-card {
            background: #2c3e50;
            border-left-color: var(--secondary-color);
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.3);
            color: #e0e0e0;
        }
        
        body.dark-mode .stat-card .number {
            color: #4bc0c0;
        }
        
        body.dark-mode .stat-card .label {
            color: #b0b0b0;
        }
        
        body.dark-mode .stat-card .growth {
            background: #1a1a2e;
            color: #4bc0c0;
        }
        
        body.dark-mode .chart-card {
            background: #2c3e50;
            box-shadow: 0 3px 3px rgba(0, 0, 0, 0.3);
        }
        
        body.dark-mode .chart-card h5 {
            color: #4bc0c0;
        }
        
        body.dark-mode .procedure-table {
            background: #2c3e50;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.3);
        }
        
        body.dark-mode .procedure-table h5 {
            color: #4bc0c0;
        }
        
        body.dark-mode .procedure-table th {
            background: #1a2332;
            color: #e0e0e0;
        }
        
        body.dark-mode .procedure-table td {
            color: #e0e0e0;
            border-color: #1a2332;
        }
        
        body.dark-mode .procedure-table tbody tr:hover {
            background-color: #1d3557;
        }
        
        body.dark-mode .total-row {
            background: #1a2332;
            color: #e0e0e0;
        }
        
        body.dark-mode .total-row td {
            border-top-color: #4bc0c0;
        }
        
        body.dark-mode .table {
            color: #e0e0e0;
        }
        
        body.dark-mode .table thead th {
            background: #1a2332;
            color: #e0e0e0;
            border-color: #1d3557;
        }
        
        body.dark-mode .table tbody tr:hover {
            background-color: #1d3557;
        }
        
        body.dark-mode .table tbody td {
            border-color: #1d3557;
        }
        
        body.dark-mode .alert {
            background-color: #2c3e50;
            color: #e0e0e0;
            border-color: #1d3557;
        }
        
        body.dark-mode .badge {
            background-color: #1a2332 !important;
            color: #e0e0e0 !important;
        }
        
        body.dark-mode .footer {
            background: linear-gradient(135deg, #1a1a2e 0%, #0f3460 100%);
        }
        
        body.dark-mode .footer a {
            color: #4bc0c0;
        }
        
        body.dark-mode .footer a:hover {
            color: #e0e0e0;
        }
        
        body.dark-mode .text-muted {
            color: #b0b0b0 !important;
        }
        
        body.dark-mode .btn-outline-danger:not(:hover) {
            color: #ff6b6b;
            border-color: #ff6b6b;
        }
        
        body.dark-mode .btn-success {
            background-color: #2d8cf0;
            border-color: #2d8cf0;
        }
        
        body.dark-mode .modal-content {
            background: #2c3e50;
            color: #e0e0e0;
        }
        
        body.dark-mode .modal-header {
            background: #1a2332;
            border-color: #1d3557;
        }
        
        body.dark-mode .modal-title {
            color: #4bc0c0;
        }
        
        body.dark-mode .btn-close {
            filter: brightness(1);
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    
    <nav class="navbar navbar-expand-lg navbar-custom no-print">
        <div class="container">
            <span class="navbar-brand">
                <img src="Screenshot%20(1)_5509.jpg" alt="Happy Tooth Dental logo" class="brand-logo">
                <?php echo CLINIC_NAME; ?>
            </span>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a href="add_patient.php" class="btn btn-success btn-action me-2">
                            <i class="bi bi-person-plus"></i> New Patient
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="add_visit.php" class="btn btn-info btn-action me-2 text-white">
                            <i class="bi bi-plus-circle"></i> New Visit
                        </a>
                    </li>
                    <li class="nav-item">
                        <button class="dark-mode-toggle" id="darkModeToggle" title="Toggle Dark Mode">
                            <i class="bi bi-moon-fill"></i>
                        </button>
                    </li>
                    <li class="nav-item">
                        <a href="add_expense.php" class="btn btn-danger btn-action me-2 text-white">
                            <i class="bi bi-wallet2"></i> New Expense
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="patients.php" class="btn btn-primary btn-action me-2">
                            <i class="bi bi-people"></i> All Patients
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="visit.php" class="btn btn-warning btn-action me-2 text-dark">
                            <i class="bi bi-list-ul"></i> View All Visits
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="expenses.php" class="btn btn-secondary btn-action text-white">
                            <i class="bi bi-receipt"></i> Expenses
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="materials_tracker.php" class="btn btn-info btn-action me-2 text-white">
                            <i class="bi bi-box-seam"></i> Materials Tracker
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="materials_tracker.php#stock-section" class="btn btn-success btn-action me-2 text-white">
                            <i class="bi bi-boxes"></i> Stock
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="manage_months.php" class="btn btn-outline-light btn-action me-2">
                            <i class="bi bi-calendar2-month"></i> Monthly Management
                        </a>
                    </li>
                    <?php if (strtolower(isset($_SESSION['role']) ? $_SESSION['role'] : '') === 'admin'): ?>
                    <li class="nav-item">
                        <a href="security.php" class="btn btn-outline-warning btn-action me-2">
                            <i class="bi bi-shield-lock"></i> Security
                        </a>
                    </li>
                    <?php endif; ?>
                    <li class="nav-item">
                        <a href="logout.php" class="btn btn-light btn-action text-dark">
                            <i class="bi bi-box-arrow-right"></i> Logout
                        </a>
                    </li>
            </div>
        </div>
    </nav>

    <div class="container mt-4 flex-grow-1">
        <!-- Header -->
        <div class="header-card">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1><img src="Screenshot%20(1)_5509.jpg" alt="Happy Tooth Dental logo" class="brand-logo"> <?php echo CLINIC_NAME; ?></h1>
                    <p class="mb-0"><i class="bi bi-geo-alt"></i> <?php echo CLINIC_LOCATION; ?></p>
                    <p class="contact-info"><i class="bi bi-telephone"></i> <?php echo CLINIC_CONTACT; ?></p>
                </div>
                <div class="col-md-4 text-md-end">
                    <div class="d-flex flex-column align-items-md-end">
                        <span class="badge bg-warning text-dark mb-2" style="font-size:1rem; padding:10px 20px;">
                            <i class="bi bi-calendar3"></i> <?php echo date('l, F d, Y'); ?>
                        </span>
                        <span class="badge bg-light text-dark" style="font-size:1rem; padding:10px 20px;">
                            <i class="bi bi-clock"></i> <?php echo date('h:i A'); ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Statistics -->
        <div class="row mb-4">
            <div class="col-md-3 col-6">
                <div class="stat-card patients animate delay-1">
                    <div class="icon"><i class="bi bi-people text-success"></i></div>
                    <div class="number"><?php echo number_format($total_patients); ?></div>
                    <div class="label">Total Patients</div>
                    <div class="growth"><i class="bi bi-arrow-up"></i> <?php echo $growth_rate; ?>%</div>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="stat-card visits animate delay-2">
                    <div class="icon"><i class="bi bi-clipboard2-pulse text-primary"></i></div>
                    <div class="number"><?php echo number_format($total_visits); ?></div>
                    <div class="label">Total Visits</div>
                    <div class="growth"><i class="bi bi-calendar"></i> <?php echo $monthly_visits; ?> this month</div>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="stat-card revenue animate delay-3">
                    <div class="icon"><i class="bi bi-cash-stack text-warning"></i></div>
                    <div class="number">MK <?php echo number_format($total_revenue, 0); ?></div>
                    <div class="label">Total Revenue</div>
                    <div class="growth"><i class="bi bi-arrow-up"></i> +12.5%</div>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="stat-card today animate delay-4">
                    <div class="icon"><i class="bi bi-calendar-day text-danger"></i></div>
                    <div class="number"><?php echo number_format($today_visits); ?></div>
                    <div class="label">Today's Visits</div>
                    <div class="growth">MK <?php echo number_format($today_revenue, 0); ?> revenue</div>
                </div>
            </div>
        </div>

        <!-- Charts -->
        <div class="row">
            <div class="col-lg-8">
                <div class="chart-card monthly-overview-card animate">
                    <h5><i class="bi bi-graph-up"></i> Monthly Overview</h5>
                    <canvas id="monthlyChart" height="140"></canvas>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="chart-card animate">
                    <h5><i class="bi bi-pie-chart"></i> Procedure Distribution</h5>
                    <canvas id="procedureChart" height="250"></canvas>
                </div>
            </div>
        </div>

        <div class="text-center mb-4 animate">
            <a href="visit.php" class="btn btn-primary btn-action me-2">
                <i class="bi bi-list-ul"></i> View All Visits
            </a>
            <a href="expenses.php" class="btn btn-danger btn-action">
                <i class="bi bi-wallet2"></i> View All Expenses
            </a>
        </div>

        <div class="row mb-4 animate">
            <div class="col-lg-12">
                <div class="chart-card p-4">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                        <h5 class="mb-0"><i class="bi bi-wallet2"></i> Expense Overview</h5>
                        <a href="add_expense.php" class="btn btn-outline-danger btn-sm">
                            <i class="bi bi-plus-circle"></i> Add Expense
                        </a>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <div class="stat-card revenue">
                                <div class="icon"><i class="bi bi-cash-stack text-danger"></i></div>
                                <div class="number">MK <?php echo number_format($total_expenses, 0); ?></div>
                                <div class="label">Total Expenses</div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="stat-card today">
                                <div class="icon"><i class="bi bi-calendar-day text-warning"></i></div>
                                <div class="number">MK <?php echo number_format($today_expenses, 0); ?></div>
                                <div class="label">Today's Expenses</div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="stat-card visits">
                                <div class="icon"><i class="bi bi-calendar3 text-success"></i></div>
                                <div class="number">MK <?php echo number_format($monthly_expenses, 0); ?></div>
                                <div class="label">This Month</div>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>

        <!-- Procedures Table -->
        <div class="procedure-table animate">
            <div class="procedure-table-header">
                <h5><i class="bi bi-list-task"></i> Procedures Summary</h5>
                <button id="toggleProcedureSummary" class="btn btn-success btn-action" type="button">
                    <i class="bi bi-eye"></i> View Procedures Summary
                </button>
            </div>

            <div class="procedure-summary-panel" id="procedureSummaryTable">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Procedure Name</th>
                                <th class="text-center">Patients</th>
                                <th class="text-end">Price (MK)</th>
                                <th class="text-end">Revenue (MK)</th>
                                <th class="text-center">Popularity</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $max_count = max(array_column($procedure_stats, 'count'));
                            $max_count = $max_count > 0 ? $max_count : 1;
                            foreach ($procedure_stats as $id => $stat): 
                                $percentage = round(($stat['count'] / $max_count) * 100);
                            ?>
                            <tr>
                                <td><?php echo $id; ?></td>
                                <td><?php echo htmlspecialchars($stat['name']); ?></td>
                                <td class="text-center">
                                    <span class="procedure-count"><?php echo $stat['count']; ?></span>
                                </td>
                                <td class="text-end">MK <?php echo number_format($procedure_prices[$id], 0); ?></td>
                                <td class="text-end">MK <?php echo number_format($stat['total'], 0); ?></td>
                                <td class="text-center" style="min-width: 120px;">
                                    <div class="progress-bar-custom">
                                        <div class="progress-fill" style="width: <?php echo $percentage; ?>%"></div>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr class="total-row">
                                <td colspan="2" class="text-end"><strong>TOTAL:</strong></td>
                                <td class="text-center"><strong><?php echo $grand_patients; ?></strong></td>
                                <td></td>
                                <td class="text-end"><strong>MK <?php echo number_format($grand_total, 0); ?></strong></td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>

    </div>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="row">
                <div class="col-md-4 mb-3">
                    <h5><img src="Screenshot%20(1)_5509.jpg" alt="Happy Tooth Dental logo" class="brand-logo"> <?php echo CLINIC_NAME; ?></h5>
                    <p class="text-muted">Your trusted dental care provider in Lilongwe. Committed to giving you a beautiful, healthy smile.</p>
                    <div class="social-icons">
                        <a href="#"><i class="bi bi-facebook"></i></a>
                        <a href="#"><i class="bi bi-instagram"></i></a>
                        <a href="#"><i class="bi bi-whatsapp"></i></a>
                        <a href="#"><i class="bi bi-youtube"></i></a>
                    </div>
                </div>
                <div class="col-md-4 mb-3">
                    <h5>Quick Links</h5>
                    <ul class="list-unstyled">
                        <li><a href="index.php"><i class="bi bi-chevron-right"></i> Dashboard</a></li>
                        <li><a href="add_patient.php"><i class="bi bi-chevron-right"></i> New Patient</a></li>
                        <li><a href="patients.php"><i class="bi bi-chevron-right"></i> All Patients</a></li>
                        <li><a href="add_visit.php"><i class="bi bi-chevron-right"></i> New Visit</a></li>
                    </ul>
                </div>
                <div class="col-md-4 mb-3">
                    <h5 style="color: #10d420;">Contact Information</h5>
                    <ul class="list-unstyled">
                        <li><i class="bi bi-geo-alt"></i> <?php echo CLINIC_LOCATION; ?></li>
                        <li><i class="bi bi-telephone"></i> <?php echo CLINIC_CONTACT; ?></li>
                        <li><i class="bi bi-envelope"></i> happytoothdental25c@gmail.com</li>
                        <li><i class="bi bi-whatsapp"></i> 0882407607</li>
                        <li><i class="bi bi-clock"></i> Mon-Fri: 8:00 AM - 5:00 PM</li>
                        <li><i class="bi bi-clock"></i> Sat: 8:00 AM - 1:00 PM</li>
                    </ul>
                </div>
            </div>
            <hr class="bg-secondary">
            <div class="text-center text-muted">
                <p>&copy; <?php echo date('Y'); ?> <?php echo CLINIC_NAME; ?>. All rights reserved. | Developed with <i class="bi bi-heart-fill text-danger"></i> for better smiles | Developed by Kenneth Chirambo</p>
                <p class="small">Powered by Dental Management Systems, for more infor contact 0994629781/0881749527/chiramboken789@gmail.com</p>
            </div>
        </div>
    </footer>

    <!-- Procedures Modal -->
    <div class="modal fade procedures-modal" id="proceduresModal" tabindex="-1" aria-labelledby="proceduresModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="proceduresModalLabel">
                        <i class="bi bi-list-task"></i> All Dental Procedures & Pricing
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="procedures-grid">
                        <?php foreach ($procedures as $id => $name): ?>
                        <div class="procedure-item">
                            <div>
                                <span class="procedure-id">#<?php echo $id; ?></span>
                                <span class="procedure-name"><?php echo htmlspecialchars($name); ?></span>
                            </div>
                            <div class="procedure-price">
                                <i class="bi bi-cash"></i> MK <?php echo number_format($procedure_prices[$id], 0); ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="modal-footer">
                    <p class="text-muted" style="font-size: 0.9rem;">
                        <i class="bi bi-info-circle"></i> Total Procedures: <strong><?php echo count($procedures); ?></strong>
                    </p>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Initialize dark mode from localStorage
        const darkModeToggle = document.getElementById('darkModeToggle');
        const savedTheme = localStorage.getItem('theme');
        
        if (savedTheme === 'dark') {
            document.body.classList.add('dark-mode');
            darkModeToggle.innerHTML = '<i class="bi bi-sun-fill"></i>';
        }
        
        // Toggle dark mode
        darkModeToggle.addEventListener('click', function() {
            document.body.classList.toggle('dark-mode');
            const isDarkMode = document.body.classList.contains('dark-mode');
            localStorage.setItem('theme', isDarkMode ? 'dark' : 'light');
            darkModeToggle.innerHTML = isDarkMode ? '<i class="bi bi-sun-fill"></i>' : '<i class="bi bi-moon-fill"></i>';
        });

        // Chart data from PHP
        const monthlyLabels = <?php echo json_encode(array_column($monthly_data, 'month')); ?>;
        const monthlyVisits = <?php echo json_encode(array_column($monthly_data, 'visits')); ?>;
        const monthlyRevenue = <?php echo json_encode(array_column($monthly_data, 'revenue')); ?>;
        
        const procedureLabels = <?php echo json_encode($procedure_names); ?>;
        const procedureData = <?php echo json_encode($procedure_counts); ?>;
        
        const colors = [
            '#2d8cf0', '#4bc0c0', '#ffc107', '#ff6b6b', '#f093fb',
            '#4caf50', '#ff9800', '#9c27b0', '#00bcd4', '#e91e63'
        ];

        // Monthly Chart
        const monthlyCtx = document.getElementById('monthlyChart').getContext('2d');
        new Chart(monthlyCtx, {
            type: 'bar',
            data: {
                labels: monthlyLabels,
                datasets: [
                    {
                        label: 'Visits',
                        data: monthlyVisits,
                        backgroundColor: 'rgba(45, 140, 240, 0.7)',
                        borderColor: '#2d8cf0',
                        borderWidth: 2,
                        borderRadius: 5,
                        order: 2
                    },
                    {
                        label: 'Revenue (MK)',
                        data: monthlyRevenue,
                        type: 'line',
                        backgroundColor: 'rgba(240, 147, 251, 0.1)',
                        borderColor: '#f093fb',
                        borderWidth: 3,
                        pointBackgroundColor: '#f093fb',
                        pointBorderColor: 'rgba(20, 219, 46, 0.9)336d7',
                        pointBorderWidth: 2,
                        pointRadius: 5,
                        fill: true,
                        tension: 0.4,
                        order: 1,
                        yAxisID: 'y1'
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        position: 'top',
                        labels: {
                            usePointStyle: true,
                            padding: 20
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(0,0,0,0.05)'
                        },
                        ticks: {
                            stepSize: 1
                        }
                    },
                    y1: {
                        position: 'right',
                        beginAtZero: true,
                        grid: {
                            drawOnChartArea: false
                        },
                        ticks: {
                            callback: function(value) {
                                return 'MK ' + value.toLocaleString();
                            }
                        }
                    }
                }
            }
        });

        // Procedure Chart
        const procedureCtx = document.getElementById('procedureChart').getContext('2d');
        new Chart(procedureCtx, {
            type: 'doughnut',
            data: {
                labels: procedureLabels.length > 0 ? procedureLabels : ['No Data'],
                datasets: [{
                    data: procedureData.length > 0 ? procedureData : [1],
                    backgroundColor: colors.slice(0, procedureLabels.length || 1),
                    borderColor: '#fff',
                    borderWidth: 3,
                    hoverOffset: 10
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            usePointStyle: true,
                            padding: 15,
                            font: {
                                size: 11
                            }
                        }
                    }
                },
                cutout: '65%'
            }
        });

        const procedureSummaryButton = document.getElementById('toggleProcedureSummary');
        const procedureSummaryPanel = document.getElementById('procedureSummaryTable');

        if (procedureSummaryButton && procedureSummaryPanel) {
            procedureSummaryButton.addEventListener('click', function() {
                const isHidden = procedureSummaryPanel.style.display === 'none';
                procedureSummaryPanel.style.display = isHidden ? 'block' : 'none';
                procedureSummaryButton.innerHTML = isHidden
                    ? '<i class="bi bi-eye-slash"></i> Hide Procedures Summary'
                    : '<i class="bi bi-eye"></i> View Procedures Summary';
            });
        }

        // Animate progress bars
        document.addEventListener('DOMContentLoaded', function() {
            const progressBars = document.querySelectorAll('.progress-fill');
            progressBars.forEach(bar => {
                const width = bar.style.width;
                bar.style.width = '0%';
                setTimeout(() => {
                    bar.style.width = width;
                }, 300);
            });
        });
    </script>
</body>
</html>