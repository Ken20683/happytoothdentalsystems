<?php
// add_patient.php - Add new patient and redirect to visit
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
$patient_id = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $phone = trim($_POST['phone']);
    $address = trim($_POST['address']);
    
    if (empty($name)) {
        $error = 'Patient name is required';
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO patients (name, phone, address) VALUES (?, ?, ?)");
            $stmt->execute([$name, $phone, $address]);
            $patient_id = $pdo->lastInsertId();
            
            // Redirect to add visit with patient ID
            header("Location: add_visit.php?patient_id=$patient_id&new_patient=1");
            exit();
        } catch(PDOException $e) {
            $error = 'Database error: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo CLINIC_NAME; ?> - New Patient</title>
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
            box-shadow: 0 2px 20px rgba(163, 177, 168, 0.92);
        }
        
        .navbar-brand {
            font-weight: bold;
            color: #11e034e1 !important;
            font-size: 1.6rem;
            letter-spacing: 1px;
        }
        
        .navbar-brand i {
            margin-right: 10px;
            color: #f1c40f;
        }
        
        .form-card {
            background: blue;
            border-radius: 10px;
            padding: 0.5rem;
            box-shadow: 0 5px 10px rgba(174, 179, 175, 0.91);
            margin-top: 2rem;
            margin-bottom: 2rem;              
            max-width: 300px;
            margin-left: auto;
            margin-right: auto;
            animation: fadeInUp 0.5s ease;
        }
        
        .form-card h3 {
            color: var(--primary-color);
            font-weight: 600;
        }
        
        .form-card h3 i {
            color: var(--secondary-color);
        }
        
        .btn-action {
            border-radius: 25px;
            padding: 12px 30px;
            font-weight: 200;
            transition: all 0.3s;
        }
        
        .btn-action:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
        
        .form-control, .form-select {
            border-radius: 10px;
            padding: 12px 15px;
            border: 2px solid #178f21d3;
            transition: all 0.3s;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: var(--secondary-color);
            box-shadow: 0 0 0 0.2rem rgba(45, 140, 240, 0.25);
        }
        
        .form-label {
            font-weight: 600;
            color: var(--primary-color);
        }
        
        .required {
            color: #dc3545;
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
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-custom">
        <div class="container">
            <a href="index.php" class="navbar-brand">
                <i class="bi bi-arrow-left"></i> Back to Dashboard
            </a>
            <span class="navbar-brand">
                <i class="bi bi-person-plus"></i> New Patient
            </span>
        </div>
    </nav>

    <div class="container flex-grow-1">
        <div class="form-card">
            <h3 class="mb-4"><i class="bi bi-person-plus"></i> Register New Patient</h3>
            
            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <form method="POST">
                <div class="mb-3">
                    <label for="name" class="form-label">Full Name <span class="required">*</span></label>
                    <input type="text" class="form-control" id="name" name="name" placeholder="Enter patient's full name" required autofocus>
                </div>
                
                <div class="mb-3">
                    <label for="phone" class="form-label">Phone Number</label>
                    <input type="tel" class="form-control" id="phone" name="phone" placeholder="e.g., 0992239889">
                </div>
                
                <div class="mb-3">
                    <label for="address" class="form-label">Address</label>
                    <textarea class="form-control" id="address" name="address" rows="2" placeholder="Enter patient's address"></textarea>
                </div>
                
                <div class="d-grid gap-2">
                    <button type="submit" class="btn btn-primary btn-action">
                        <i class="bi bi-save"></i> Register & Add Treatment
                    </button>
                    <a href="index.php" class="btn btn-secondary btn-action">Cancel</a>
                </div>
            </form>
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
                        <li><a href="add_visit.php"><i class="bi bi-chevron-right"></i> New Visit</a></li>
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
</body>
</html>