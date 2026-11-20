<?php
// patient_history.php - View a patient's visit history
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

require_once 'config.php';
require_once 'database.php';

$patient_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($patient_id <= 0) {
    header("Location: patients.php");
    exit();
}

// Get patient details
$patient = null;
try {
    $stmt = $pdo->prepare("SELECT * FROM patients WHERE id = ?");
    $stmt->execute([$patient_id]);
    $patient = $stmt->fetch();
    
    if (!$patient) {
        header("Location: patients.php");
        exit();
    }
} catch(PDOException $e) {
    die("Error loading patient: " . $e->getMessage());
}

// Get patient's visits
$visits = [];
$total_spent = 0;
try {
    $stmt = $pdo->prepare("
        SELECT v.*, pr.name as procedure_name 
        FROM visits v 
        JOIN procedures pr ON v.procedure_id = pr.id 
        WHERE v.patient_id = ? 
        ORDER BY v.visit_date DESC
    ");
    $stmt->execute([$patient_id]);
    $visits = $stmt->fetchAll();
    
    foreach ($visits as $visit) {
        $total_spent += $visit['amount'];
    }
} catch(PDOException $e) {
    // Handle error
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo CLINIC_NAME; ?> - Patient History</title>
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
        }
        .navbar-brand {
            font-weight: bold;
            color: #ffffff !important;
            font-size: 1.6rem;
            letter-spacing: 1px;
        }
        .navbar-brand i {
            margin-right: 10px;
            color: #f1c40f;
        }
        .btn-action {
            border-radius: 25px;
            padding: 10px 25px;
            font-weight: 500;
            transition: all 0.3s;
        }
        .btn-action:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
        .main-card {
            background: white;
            border-radius: 20px;
            padding: 2rem;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
            margin-top: 2rem;
            margin-bottom: 2rem;
        }
       
        .patient-info {
            background: #f8f9fa;
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            border-left: 5px solid #2d8cf0;
        }
        .stat-box {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 15px;
            text-align: center;
        }
        .stat-box .number {
            font-size: 1.8rem;
            font-weight: bold;
            color: #1a2332;
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
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-custom">
        <div class="container">
            <a href="index.php" class="navbar-brand">
                <i class="bi bi-arrow-left"></i> Back to Dashboard
            </a>
            <span class="navbar-brand">
                <i class="bi bi-person"></i> Patient History
            </span>
        </div>
    </nav>

    <div class="container flex-grow-1">
        <div class="main-card">
            <!-- Patient Info -->
            <div class="patient-info">
                <div class="row">
                    <div class="col-md-8">
                        <h4><i class="bi bi-person-circle"></i> <?php echo htmlspecialchars($patient['name']); ?></h4>
                        <p class="mb-0"><i class="bi bi-telephone"></i> <?php echo htmlspecialchars($patient['phone']); ?></p>
                        <?php if ($patient['email']): ?>
                            <p class="mb-0"><i class="bi bi-envelope"></i> <?php echo htmlspecialchars($patient['email']); ?></p>
                        <?php endif; ?>
                        <?php if ($patient['address']): ?>
                            <p class="mb-0"><i class="bi bi-geo-alt"></i> <?php echo htmlspecialchars($patient['address']); ?></p>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-4 text-md-end">
                        <a href="add_visit.php?patient_id=<?php echo $patient['id']; ?>" class="btn btn-success btn-action">
                            <i class="bi bi-plus-circle"></i> New Visit
                        </a>
                    </div>
                </div>
            </div>

            <!-- Statistics -->
            <div class="row mb-4">
                <div class="col-md-4">
                    <div class="stat-box">
                        <div class="number"><?php echo count($visits); ?></div>
                        <div class="label">Total Visits</div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="stat-box">
                        <div class="number">MK <?php echo number_format($total_spent, 0); ?></div>
                        <div class="label">Total Spent</div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="stat-box">
                        <div class="number">
                            <?php 
                            $avg = count($visits) > 0 ? $total_spent / count($visits) : 0;
                            echo 'MK ' . number_format($avg, 0);
                            ?>
                        </div>
                        <div class="label">Average per Visit</div>
                    </div>
                </div>
            </div>

            <!-- Visits Table -->
            <h5><i class="bi bi-clock-history"></i> Visit History</h5>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead class="table-dark">
                        <tr>
                            <th>#</th>
                            <th>Procedure</th>
                            <th>Date</th>
                            <th class="text-end">Amount (MK)</th>
                            <th>Notes</th>
                            <th class="text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($visits) > 0): ?>
                            <?php $counter = 1; ?>
                            <?php foreach ($visits as $visit): ?>
                                <tr>
                                    <td><?php echo $counter++; ?></td>
                                    <td><?php echo htmlspecialchars($visit['procedure_name']); ?></td>
                                    <td><?php echo date('d/m/Y', strtotime($visit['visit_date'])); ?></td>
                                    <td class="text-end">MK <?php echo number_format($visit['amount'], 0); ?></td>
                                    <td><?php echo htmlspecialchars($visit['notes']); ?></td>
                                    <td class="text-center">
                                        <a href="receipt.php?id=<?php echo $visit['id']; ?>" target="_blank" 
                                           class="btn btn-sm btn-outline-primary">
                                            <i class="bi bi-receipt"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center text-muted py-3">
                                    <i class="bi bi-info-circle"></i> No visits recorded for this patient
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="row">
                <div class="col-md-4 mb-3">
                    <h5><img src="Screenshot%20(1)_5509.jpg" alt="Happy Tooth Dental logo" class="brand-logo" style="width:28px;height:28px;"> <?php echo CLINIC_NAME; ?></h5>
                    <p class="text-muted">Your trusted dental care provider in Lilongwe.</p>
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
                        <li><a href="visit.php"><i class="bi bi-chevron-right"></i> All Visits</a></li>
                        <li><a href="patients.php"><i class="bi bi-chevron-right"></i> All Patients</a></li>
                    </ul>
                </div>
                <div class="col-md-4 mb-3">
                    <h5 style="color: #10d420;">Contact Info</h5>
                    <ul class="list-unstyled">
                        <li><i class="bi bi-geo-alt"></i> <?php echo CLINIC_LOCATION; ?></li>
                        <li><i class="bi bi-telephone"></i> <?php echo CLINIC_CONTACT; ?></li>
                        <li><i class="bi bi-envelope"></i> happytoothdental25c@gmail.com</li>
                        <li><i class="bi bi-whatsapp"></i> 0882407607</li>
                        <li><i class="bi bi-clock"></i> Mon-Fri: 8:00 AM - 5:00 PM</li>
                    </ul>
                </div>
            </div>
            <hr class="bg-secondary">
            <div class="text-center text-muted">
                <p>&copy; <?php echo date('Y'); ?> <?php echo CLINIC_NAME; ?>. All rights reserved. | Developed by Kenneth Chirambo</p>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>