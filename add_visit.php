<?php
// add_visit.php - Add new visit with editable amount
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
$patient_id = isset($_GET['patient_id']) ? (int)$_GET['patient_id'] : 0;
$is_new_patient = isset($_GET['new_patient']) ? true : false;

// Get patients for dropdown
$patients = [];
try {
    $stmt = $pdo->query("SELECT id, name, phone FROM patients ORDER BY name");
    $patients = $stmt->fetchAll();
} catch(PDOException $e) {
    // Handle error
}

// Get selected patient details if ID is provided
$selected_patient = null;
if ($patient_id > 0) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM patients WHERE id = ?");
        $stmt->execute([$patient_id]);
        $selected_patient = $stmt->fetch();
    } catch(PDOException $e) {
        // Handle error
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $patient_id = (int)$_POST['patient_id'];
    $procedure_id = (int)$_POST['procedure_id'];
    $visit_date = $_POST['visit_date'];
    $amount = (float)$_POST['amount'];
    $notes = trim($_POST['notes']);
    $discount_note = trim($_POST['discount_note']);
    
    if ($patient_id <= 0 || $procedure_id <= 0 || empty($visit_date) || $amount <= 0) {
        $error = 'Please fill in all required fields';
    } else {
        try {
            // Get procedure name for receipt
            $proc_stmt = $pdo->prepare("SELECT name FROM procedures WHERE id = ?");
            $proc_stmt->execute([$procedure_id]);
            $procedure = $proc_stmt->fetch();
            
            $stmt = $pdo->prepare("INSERT INTO visits (patient_id, procedure_id, visit_date, amount, notes) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$patient_id, $procedure_id, $visit_date, $amount, $notes]);
            $visit_id = $pdo->lastInsertId();
            
            $message = "Treatment added successfully!";
            if ($is_new_patient) {
                $message .= " <a href='receipt.php?id=$visit_id' target='_blank' class='btn btn-sm btn-success'>View Receipt</a>";
                $message .= " <a href='index.php' class='btn btn-sm btn-primary'>Go to Dashboard</a>";
            } else {
                $message .= " <a href='receipt.php?id=$visit_id' target='_blank' class='btn btn-sm btn-success'>View Receipt</a>";
                $message .= " <a href='add_visit.php' class='btn btn-sm btn-primary'>Add Another</a>";
            }
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
    <title><?php echo CLINIC_NAME; ?> - Add Treatment</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <style>
        :root {
            --primary-color: #1a2332;
            --secondary-color: #2d8cf0;
            --success-color: #28a745;
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
        }
        
        .navbar-brand {
            font-weight: bold;
            color: #eeebf3e0 !important;
            font-size: 1.6rem;
            letter-spacing: 1px;
        }
        
        .navbar-brand i {
            margin-right: 10px;
            color: #f1c40f;
        }
        
        .form-card {
            background: green;
            border-radius: 20px;
            padding: 2.5rem;
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
            margin-top: 2rem;
            margin-bottom: 2rem;
            max-width: 350px;
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
            font-weight: 500;
            transition: all 0.3s;
        }
        
        .btn-action:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
        
        .form-control, .form-select {
            border-radius: 10px;
            padding: 12px 15px;
            border: 2px solid #7c1010de;
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
        
        .patient-info-box {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 15px;
            border-left: 4px solid var(--secondary-color);
            margin-bottom: 1.5rem;
        }
        
        .price-edit-box {
            background: #fff3cd;
            border-radius: 10px;
            padding: 15px;
            border-left: 4px solid #ffc107;
            margin-top: 1rem;
        }
        
        .price-edit-box label {
            color: #856404;
        }
        
        .badge-new {
            background: #28a745;
            color: white;
            padding: 5px 15px;
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
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-custom">
        <div class="container">
            <a href="index.php" class="navbar-brand">
                <i class="bi bi-arrow-left"></i> Back to Dashboard
            </a>
            <span class="navbar-brand">
                <i class="bi bi-plus-circle"></i> Add Treatment
                <?php if ($is_new_patient): ?>
                    <span class="badge-new"><i class="bi bi-star-fill"></i> New Patient</span>
                <?php endif; ?>
            </span>
        </div>
    </nav>

    <div class="container flex-grow-1">
        <div class="form-card">
            <h3 class="mb-4"><i class="bi bi-plus-circle"></i> Add Treatment / Visit</h3>
            
            <?php if ($message): ?>
                <div class="alert alert-success"><?php echo $message; ?></div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if ($selected_patient): ?>
                <div class="patient-info-box">
                    <div class="row">
                        <div class="col-md-8">
                            <strong><i class="bi bi-person"></i> Patient:</strong> <?php echo htmlspecialchars($selected_patient['name']); ?>
                            <br>
                            <small><i class="bi bi-telephone"></i> <?php echo htmlspecialchars($selected_patient['phone']); ?></small>
                            <?php if ($selected_patient['email']): ?>
                                <br><small><i class="bi bi-envelope"></i> <?php echo htmlspecialchars($selected_patient['email']); ?></small>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-4 text-md-end">
                            <span class="badge bg-success">Patient ID: <?php echo $selected_patient['id']; ?></span>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
            
            <form method="POST" id="visitForm">
                <div class="mb-3">
                    <label for="patient_id" class="form-label">Select Patient <span class="required">*</span></label>
                    <select class="form-select" id="patient_id" name="patient_id" required <?php echo $patient_id > 0 ? 'disabled' : ''; ?>>
                        <option value="">Choose a patient...</option>
                        <?php foreach ($patients as $patient): ?>
                            <option value="<?php echo $patient['id']; ?>" <?php echo $patient_id == $patient['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($patient['name']); ?> - <?php echo htmlspecialchars($patient['phone']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if ($patient_id > 0): ?>
                        <input type="hidden" name="patient_id" value="<?php echo $patient_id; ?>">
                    <?php endif; ?>
                    <small class="text-muted">
                        <i class="bi bi-info-circle"></i> 
                        <a href="add_patient.php" target="_blank">New patient? Register here</a>
                    </small>
                </div>
                
                <div class="mb-3">
                    <label for="procedure_id" class="form-label">Treatment / Procedure <span class="required">*</span></label>
                    <select class="form-select" id="procedure_id" name="procedure_id" required onchange="updatePrice()">
                        <option value="">Select Treatment...</option>
                        <?php foreach ($procedures as $id => $name): ?>
                            <option value="<?php echo $id; ?>" data-price="<?php echo $procedure_prices[$id]; ?>">
                                <?php echo htmlspecialchars($name); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="mb-3">
                    <label for="visit_date" class="form-label">Date <span class="required">*</span></label>
                    <input type="date" class="form-control" id="visit_date" name="visit_date" value="<?php echo date('Y-m-d'); ?>" required>
                </div>
                
                <div class="price-edit-box">
                    <div class="row align-items-end">
                        <div class="col-md-6">
                            <label for="amount" class="form-label">
                                <i class="bi bi-cash"></i> Amount (MK) <span class="required">*</span>
                            </label>
                            <input type="number" class="form-control" id="amount" name="amount" step="100" min="0" required>
                            <small class="text-muted"><i class="bi bi-pencil"></i> Edit amount if needed (discounts, special pricing)</small>
                        </div>
                        <div class="col-md-6">
                            <label for="discount_note" class="form-label">
                                <i class="bi bi-tag"></i> Discount/Adjustment Note
                            </label>
                            <input type="text" class="form-control" id="discount_note" name="discount_note" placeholder="e.g., 20% discount, patient hardship">
                        </div>
                    </div>
                </div>
                
                <div class="mb-3 mt-3">
                    <label for="notes" class="form-label">Additional Notes</label>
                    <textarea class="form-control" id="notes" name="notes" rows="2" placeholder="Any additional notes about this visit..."></textarea>
                </div>
                
                <div class="d-grid gap-2">
                    <button type="submit" class="btn btn-primary btn-action">
                        <i class="bi bi-save"></i> Save Treatment
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

    <script>
        function updatePrice() {
            const select = document.getElementById('procedure_id');
            const priceInput = document.getElementById('amount');
            const selectedOption = select.options[select.selectedIndex];
            
            if (selectedOption && selectedOption.dataset.price) {
                priceInput.value = selectedOption.dataset.price;
            } else {
                priceInput.value = '';
            }
        }
        
        // Auto-update price on page load if a procedure is pre-selected
        document.addEventListener('DOMContentLoaded', function() {
            updatePrice();
            
            // Add event listener for amount input to show editing feedback
            const amountInput = document.getElementById('amount');
            amountInput.addEventListener('change', function() {
                const originalPrice = document.getElementById('procedure_id').selectedOptions[0]?.dataset?.price;
                if (originalPrice && this.value != originalPrice) {
                    // Show subtle indicator that amount was modified
                    this.style.borderColor = '#ffc107';
                    this.style.backgroundColor = '#fff3cd';
                } else {
                    this.style.borderColor = '#e9ecef';
                    this.style.backgroundColor = 'white';
                }
            });
        });
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>