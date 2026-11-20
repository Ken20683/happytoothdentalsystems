<?php
// visits.php - View all visits with search and filters
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

require_once 'config.php';
require_once 'database.php';

// Get filter parameters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$procedure_filter = isset($_GET['procedure']) ? (int)$_GET['procedure'] : 0;
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';

// Build query
$query = "
    SELECT v.id, p.name as patient_name, p.phone, p.id as patient_id,
           pr.name as procedure_name, v.visit_date, v.amount, v.notes,
           v.created_at
    FROM visits v 
    JOIN patients p ON v.patient_id = p.id 
    JOIN procedures pr ON v.procedure_id = pr.id 
    WHERE 1=1
";

$params = [];

if (!empty($search)) {
    $query .= " AND (p.name LIKE ? OR p.phone LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($procedure_filter > 0) {
    $query .= " AND v.procedure_id = ?";
    $params[] = $procedure_filter;
}

if (!empty($date_from)) {
    $query .= " AND v.visit_date >= ?";
    $params[] = $date_from;
}

if (!empty($date_to)) {
    $query .= " AND v.visit_date <= ?";
    $params[] = $date_to;
}

$query .= " ORDER BY v.visit_date DESC, v.created_at DESC";

// Execute query
$visits = [];
$total_revenue = 0;
$error = '';

try {
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $visits = $stmt->fetchAll();
    
    // Calculate total revenue
    foreach ($visits as $visit) {
        $total_revenue += $visit['amount'];
    }
} catch(PDOException $e) {
    $error = 'Error loading visits: ' . $e->getMessage();
}

// Get procedures for filter dropdown
$all_procedures = [];
try {
    $stmt = $pdo->query("SELECT id, name FROM procedures ORDER BY name");
    $all_procedures = $stmt->fetchAll();
} catch(PDOException $e) {
    // Handle error
}

// Get success/error messages
$message = isset($_SESSION['message']) ? $_SESSION['message'] : '';
$error_msg = isset($_SESSION['error']) ? $_SESSION['error'] : '';
unset($_SESSION['message']);
unset($_SESSION['error']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo CLINIC_NAME; ?> - All Visits</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <style>
        :root {
            --primary-color: #1a2332;
            --secondary-color: #2d8cf0;
        }
        
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
            animation: fadeInUp 0.5s ease;
        }
        
        .stat-box {
            background: #1971c9d3;
            border-radius: 10px;
            padding: 15px;
            text-align: center;
            transition: all 0.3s;
        }
        
        .stat-box:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .stat-box .number {
            font-size: 1.8rem;
            font-weight: bold;
            color: var(--primary-color);
        }
        
        .stat-box .label {
            color: #7f8c8d;
            font-size: 0.9rem;
        }
        
        .filter-section {
            background: #1d77d1d8;
            border-radius: 10px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }
        
        .table-hover tbody tr {
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .table-hover tbody tr:hover {
            background-color: #a9adb3dc;
            transform: scale(1.01);
        }
        
        .badge-visit {
            padding: 5px 12px;
            border-radius: 50px;
            font-size: 0.8rem;
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
        
        .amount-high {
            color: #28a745;
            font-weight: bold;
        }
        
        .amount-medium {
            color: #ffc107;
            font-weight: bold;
        }
        
        .amount-low {
            color: #dc3545;
            font-weight: bold;
        }
        
        @media (max-width: 768px) {
            .navbar-brand {
                font-size: 1.2rem;
            }
            .btn-action {
                padding: 6px 12px;
                font-size: 0.8rem;
            }
            .main-card {
                padding: 1rem;
            }
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-custom no-print">
        <div class="container">
            <a href="index.php" class="navbar-brand">
                <i class="bi bi-arrow-left"></i> Back to Dashboard
            </a>
            <span class="navbar-brand">
                <i class="bi bi-clock-history"></i> All Visits
            </span>
        </div>
    </nav>

    <div class="container flex-grow-1">
        <div class="main-card">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h3><i class="bi bi-clock-history"></i> Visit History</h3>
                <div>
                    <a href="add_visit.php" class="btn btn-success btn-action">
                        <i class="bi bi-plus-circle"></i> New Visit
                    </a>
                    <button onclick="window.print()" class="btn btn-secondary btn-action">
                        <i class="bi bi-printer"></i> Print
                    </button>
                </div>
            </div>

            <?php if ($message): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <i class="bi bi-check-circle"></i> <?php echo $message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <?php if ($error_msg): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <i class="bi bi-exclamation-triangle"></i> <?php echo $error_msg; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Statistics -->
            <div class="row mb-4">
                <div class="col-md-3 col-6">
                    <div class="stat-box">
                        <div class="number"><?php echo count($visits); ?></div>
                        <div class="label">Total Visits</div>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="stat-box">
                        <div class="number">MK <?php echo number_format($total_revenue, 0); ?></div>
                        <div class="label">Total Revenue</div>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="stat-box">
                        <div class="number">
                            <?php 
                            $unique_patients = array_unique(array_column($visits, 'patient_id'));
                            echo count($unique_patients);
                            ?>
                        </div>
                        <div class="label">Unique Patients</div>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="stat-box">
                        <div class="number">
                            <?php 
                            $avg_amount = count($visits) > 0 ? $total_revenue / count($visits) : 0;
                            echo 'MK ' . number_format($avg_amount, 0);
                            ?>
                        </div>
                        <div class="label">Average Amount</div>
                    </div>
                </div>
            </div>

            <!-- Filter Section -->
            <div class="filter-section no-print">
                <form method="GET" class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label"><i class="bi bi-search"></i> Search</label>
                        <input type="text" class="form-control" name="search" 
                               placeholder="Search by patient name or phone..." 
                               value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label"><i class="bi bi-list"></i> Procedure</label>
                        <select class="form-select" name="procedure">
                            <option value="0">All Procedures</option>
                            <?php foreach ($all_procedures as $proc): ?>
                                <option value="<?php echo $proc['id']; ?>" 
                                    <?php echo $procedure_filter == $proc['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($proc['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label"><i class="bi bi-calendar"></i> From</label>
                        <input type="date" class="form-control" name="date_from" 
                               value="<?php echo $date_from; ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label"><i class="bi bi-calendar"></i> To</label>
                        <input type="date" class="form-control" name="date_to" 
                               value="<?php echo $date_to; ?>">
                    </div>
                    <div class="col-md-1 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100 btn-action">
                            <i class="bi bi-funnel"></i>
                        </button>
                    </div>
                </form>
            </div>

            <!-- Visits Table -->
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead class="table-dark">
                        <tr>
                            <th>#</th>
                            <th>Patient</th>
                            <th>Phone</th>
                            <th>Procedure</th>
                            <th>Date</th>
                            <th class="text-end">Amount (MK)</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($visits) > 0): ?>
                            <?php $counter = 1; ?>
                            <?php foreach ($visits as $visit): ?>
                                <?php 
                                $amount_class = 'amount-low';
                                if ($visit['amount'] >= 50000) {
                                    $amount_class = 'amount-high';
                                } elseif ($visit['amount'] >= 20000) {
                                    $amount_class = 'amount-medium';
                                }
                                ?>
                                <tr>
                                    <td><?php echo $counter++; ?></td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($visit['patient_name']); ?></strong>
                                        <?php if (!empty($visit['notes'])): ?>
                                            <br><small class="text-muted"><i class="bi bi-info-circle"></i> <?php echo htmlspecialchars(substr($visit['notes'], 0, 30)); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($visit['phone']); ?></td>
                                    <td>
                                        <span class="badge bg-primary"><?php echo htmlspecialchars($visit['procedure_name']); ?></span>
                                    </td>
                                    <td>
                                        <i class="bi bi-calendar3"></i> <?php echo date('d/m/Y', strtotime($visit['visit_date'])); ?>
                                        <br><small class="text-muted"><?php echo date('h:i A', strtotime($visit['created_at'])); ?></small>
                                    </td>
                                    <td class="text-end <?php echo $amount_class; ?>">
                                        MK <?php echo number_format($visit['amount'], 0); ?>
                                    </td>
                                    <td class="text-center">
                                        <div class="btn-group" role="group">
                                            <a href="receipt.php?id=<?php echo $visit['id']; ?>" target="_blank" 
                                               class="btn btn-sm btn-outline-primary" title="View Receipt">
                                                <i class="bi bi-receipt"></i>
                                            </a>
                                            <a href="patient_history.php?id=<?php echo $visit['patient_id']; ?>" 
                                               class="btn btn-sm btn-outline-info" title="Patient History">
                                                <i class="bi bi-person"></i>
                                            </a>
                                            <button onclick="deleteVisit(<?php echo $visit['id']; ?>)" 
                                                    class="btn btn-sm btn-outline-danger" title="Delete">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="text-center text-muted py-4">
                                    <i class="bi bi-inbox" style="font-size: 3rem; display: block; margin-bottom: 1rem;"></i>
                                    No visits found matching your criteria
                                    <?php if (!empty($search) || $procedure_filter > 0 || !empty($date_from) || !empty($date_to)): ?>
                                        <br><a href="visit.php" class="btn btn-sm btn-primary mt-2">Clear Filters</a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Export Options -->
            <div class="mt-3 no-print">
                <small class="text-muted">
                    <i class="bi bi-info-circle"></i> 
                    Showing <?php echo count($visits); ?> visits
                    <?php if (!empty($search)): ?>
                        | Search: "<?php echo htmlspecialchars($search); ?>"
                    <?php endif; ?>
                </small>
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
                        <li><a href="add_patient.php"><i class="bi bi-chevron-right"></i> New Patient</a></li>
                        <li><a href="patients.php"><i class="bi bi-chevron-right"></i> All Patients</a></li>
                        <li><a href="visit.php"><i class="bi bi-chevron-right"></i> All Visits</a></li>
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
                <p>&copy; <?php echo date('Y'); ?> <?php echo CLINIC_NAME; ?>. All rights reserved. | Developed with <i class="bi bi-heart-fill text-danger"></i> for better smiles | Developed by Kenneth Chirambo</p>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function deleteVisit(id) {
            if (confirm('Are you sure you want to delete this visit? This action cannot be undone.')) {
                window.location.href = 'delete_visit.php?id=' + id;
            }
        }
    </script>
</body>
</html>