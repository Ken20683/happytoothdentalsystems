<?php
// patients.php - View all patients
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

require_once 'config.php';
require_once 'database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_patient_id'])) {
    $delete_id = (int)$_POST['delete_patient_id'];
    if ($delete_id > 0) {
        try {
            $pdo->beginTransaction();

            $visit_stmt = $pdo->prepare("SELECT id FROM visits WHERE patient_id = ?");
            $visit_stmt->execute([$delete_id]);
            $visit_ids = $visit_stmt->fetchAll(PDO::FETCH_COLUMN);

            if (!empty($visit_ids)) {
                $visit_placeholders = implode(',', array_fill(0, count($visit_ids), '?'));

                $receipt_stmt = $pdo->prepare("SELECT id FROM receipts WHERE visit_id IN ($visit_placeholders)");
                $receipt_stmt->execute($visit_ids);
                $receipt_ids = $receipt_stmt->fetchAll(PDO::FETCH_COLUMN);

                if (!empty($receipt_ids)) {
                    $receipt_placeholders = implode(',', array_fill(0, count($receipt_ids), '?'));
                    $delete_airtel = $pdo->prepare("DELETE FROM airtel_payments WHERE receipt_id IN ($receipt_placeholders)");
                    $delete_airtel->execute($receipt_ids);
                }

                $delete_receipts = $pdo->prepare("DELETE FROM receipts WHERE visit_id IN ($visit_placeholders)");
                $delete_receipts->execute($visit_ids);
            }

            $pdo->prepare("DELETE FROM appointments WHERE patient_id = ?")->execute([$delete_id]);
            $pdo->prepare("DELETE FROM visits WHERE patient_id = ?")->execute([$delete_id]);
            $pdo->prepare("DELETE FROM patients WHERE id = ?")->execute([$delete_id]);

            $pdo->commit();
            $_SESSION['message'] = 'Patient deleted successfully.';
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $_SESSION['error'] = 'Unable to delete patient: ' . $e->getMessage();
        }
    }
    header('Location: patients.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_all_patients'])) {
    try {
        $pdo->beginTransaction();

        $all_receipt_ids = $pdo->query("SELECT id FROM receipts")->fetchAll(PDO::FETCH_COLUMN);
        if (!empty($all_receipt_ids)) {
            $receipt_placeholders = implode(',', array_fill(0, count($all_receipt_ids), '?'));
            $delete_airtel = $pdo->prepare("DELETE FROM airtel_payments WHERE receipt_id IN ($receipt_placeholders)");
            $delete_airtel->execute($all_receipt_ids);
        }

        $pdo->exec("DELETE FROM receipts");
        $pdo->exec("DELETE FROM appointments");
        $pdo->exec("DELETE FROM visits");
        $pdo->exec("DELETE FROM patients");
        $pdo->commit();
        $_SESSION['message'] = 'All patients deleted successfully.';
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $_SESSION['error'] = 'Unable to delete all patients: ' . $e->getMessage();
    }
    header('Location: patients.php');
    exit;
}

// Get all patients with their treatments
$patients = [];
$patient_treatments = [];
try {
    $stmt = $pdo->query("
        SELECT p.*, COUNT(v.id) as visit_count, COALESCE(SUM(v.amount), 0) as total_spent
        FROM patients p
        LEFT JOIN visits v ON p.id = v.patient_id
        GROUP BY p.id
        ORDER BY p.created_at DESC
    ");
    $patients = $stmt->fetchAll();
    
    // Get treatments for each patient
    foreach ($patients as $patient) {
        $treat_stmt = $pdo->prepare("
            SELECT v.id, v.visit_date, v.amount, pr.name as procedure_name, v.notes
            FROM visits v
            JOIN procedures pr ON v.procedure_id = pr.id
            WHERE v.patient_id = ?
            ORDER BY v.visit_date DESC
        ");
        $treat_stmt->execute([$patient['id']]);
        $patient_treatments[$patient['id']] = $treat_stmt->fetchAll();
    }
} catch(PDOException $e) {
    // Handle error
}

$message = isset($_SESSION['message']) ? $_SESSION['message'] : '';
$error = isset($_SESSION['error']) ? $_SESSION['error'] : '';
unset($_SESSION['message']);
unset($_SESSION['error']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo CLINIC_NAME; ?> - All Patients</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
        }
        .main-card {
            background:grey;
            border-radius: 15px;
            padding: 2rem;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            margin-top: 2rem;
        }
        .btn-action {
            border-radius: 25px;
            padding: 10px 25px;
            font-weight: 500;
            transition: all 0.3s;
        }
        .btn-action:hover {
            transform: scale(1.05);
        }
        .clinic-topbar {
            background: linear-gradient(135deg, #f0fdf4, #ecfeff);
            color: #0f172a;
            border-radius: 12px;
            padding: 1.1rem 1.25rem;
            margin-bottom: 1.25rem;
            border: 1px solid #d1fae5;
            box-shadow: 0 6px 18px rgba(22, 163, 74, 0.10);
        }
        .clinic-topbar .tooth-brand {
            width: 74px;
            height: 74px;
            margin: 0 auto 0.75rem;
            border-radius: 50%;
            display: block;
            background: linear-gradient(135deg, #dcfce7, #bbf7d0);
            border: 2px solid #86efac;
            animation: toothFloat 2.4s ease-in-out infinite;
            transform-origin: center;
            object-fit: contain;
            padding: 6px;
        }
        @keyframes toothFloat {
            0%, 100% {
                transform: translateY(0px);
            }
            50% {
                transform: translateY(-8px);
            }
        }
        .clinic-topbar h3 {
            margin: 0;
            font-weight: 800;
            color: #121312e8;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }
        .clinic-topbar .meta {
            font-size: 0.92rem;
            opacity: 0.95;
            margin-top: 0.5rem;
            display: inline-block;
            text-align: left;
            padding: 0.7rem 1rem;
            border-radius: 10px;
            background: rgba(255, 255, 255, 0.75);
            border: 1px solid #d1fae5;
        }
        .clinic-topbar .meta .location-text {
            color: #0f766e;
            font-weight: 600;
        }
        .clinic-topbar .meta .phone-text {
            color: #ea580c;
            font-weight: 700;
        }
        .clinic-topbar .meta .whatsapp-text {
            color: #16a34a;
            font-weight: 700;
        }
        .patient-signature {
            margin-top: 1.75rem;
            padding-top: 0.9rem;
            border-top: 1px solid #d1d5db;
            text-align: right;
            font-weight: 700;
            color: #0f172a;
            font-size: 0.9rem;
        }
        .patient-signature span {
            color: #1dbf73;
        }
        @media print {
            .no-print {
                display: none !important;
            }
            body {
                background: white;
            }
            .main-card {
                box-shadow: none;
                border: 1px solid #ddd;
                margin-top: 0;
                padding: 0.5rem;
            }
            .table {
                font-size: 11px;
            }
            .table-light {
                background-color: #f9f9f9 !important;
            }
            .patient-signature {
                margin-top: 0.75rem;
            }
            tr.table-light {
                page-break-inside: avoid;
            }
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-dark bg-dark no-print">
        <div class="container">
            <a href="index.php" class="navbar-brand">
                <i class="bi bi-arrow-left"></i> Back to Dashboard
            </a>
            <span class="navbar-brand">
                <i class="bi bi-people"></i> All Patients
            </span>
        </div>
    </nav>

    <div class="container">
        <div class="main-card">
            <div class="clinic-topbar text-center">
                <img src="Screenshot%20(1)_5509.jpg" alt="Happy Tooth Dental logo" class="tooth-brand" aria-label="Dental clinic logo">
                <h3><?php echo CLINIC_NAME; ?></h3>
                <div class="meta">
                    <span class="location-text"><i class="bi bi-geo-alt"></i> <?php echo CLINIC_LOCATION; ?></span><br>
                    <span class="phone-text"><i class="bi bi-telephone"></i> <?php echo CLINIC_CONTACT; ?></span><br>
                    <span class="whatsapp-text"><i class="bi bi-whatsapp"></i> <?php echo CLINIC_WHATSAPP; ?></span>
                </div>
            </div>

            <?php if ($message): ?>
                <div class="alert alert-success alert-dismissible fade show no-print" role="alert">
                    <i class="bi bi-check-circle"></i> <?php echo htmlspecialchars($message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show no-print" role="alert">
                    <i class="bi bi-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="d-flex justify-content-between align-items-center mb-3 no-print">
                <h3><i class="bi bi-people"></i> Patient List</h3>
                <div class="d-flex gap-2 flex-wrap">
                    <a href="index.php" class="btn btn-success btn-action" style="color: #fff;">
                        <i class="bi bi-arrow-left"></i> Back
                    </a>
                    <button onclick="window.print()" class="btn btn-warning btn-action">
                        <i class="bi bi-printer"></i> Print Patient List
                    </button>
                    <a href="add_patient.php" class="btn btn-success btn-action">
                        <i class="bi bi-person-plus"></i> New Patient
                    </a>
                    <button type="button" class="btn btn-danger btn-action" data-bs-toggle="modal" data-bs-target="#deleteAllPatientsModal">
                        <i class="bi bi-trash"></i> Delete All Patients
                    </button>
                </div>
            </div>
            
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead class="table-dark">
                        <tr>
                            <th>#</th>
                            <th>Name</th>
                            <th>Phone</th>
                            <th>Email</th>
                            <th>Visits</th>
                            <th>Total Spent (MK)</th>
                            <th class="no-print">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($patients) > 0): ?>
                            <?php foreach ($patients as $index => $patient): ?>
                            <tr>
                                <td><?php echo $index + 1; ?></td>
                                <td><?php echo htmlspecialchars($patient['name']); ?></td>
                                <td><?php echo htmlspecialchars($patient['phone']); ?></td>
                                <td><?php echo htmlspecialchars($patient['email']); ?></td>
                                <td><span class="badge bg-info"><?php echo $patient['visit_count']; ?></span></td>
                                <td>MK <?php echo number_format($patient['total_spent'], 0); ?></td>
                                <td class="no-print">
                                    <div class="d-flex gap-2">
                                        <a href="patient_history.php?id=<?php echo $patient['id']; ?>" class="btn btn-sm btn-primary">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        <form method="POST" onsubmit="return confirm('Are you sure you want to delete this patient?');" class="d-inline">
                                            <input type="hidden" name="delete_patient_id" value="<?php echo (int)$patient['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-danger" title="Delete patient">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            <!-- Treatment/Procedures Row -->
                            <?php if (!empty($patient_treatments[$patient['id']])): ?>
                                <tr class="table-light">
                                    <td colspan="7">
                                        <small><strong><i class="bi bi-hospital"></i> Treatments:</strong></small>
                                        <table class="table table-sm mb-0" style="margin-top: 0.5rem;">
                                            <thead>
                                                <tr style="background: #f0f0f0;">
                                                    <th style="width: 15%; font-size: 0.85rem;">Date</th>
                                                    <th style="width: 35%; font-size: 0.85rem;">Procedure</th>
                                                    <th style="width: 30%; font-size: 0.85rem;">Notes</th>
                                                    <th style="width: 20%; text-align: right; font-size: 0.85rem;">Amount (MK)</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($patient_treatments[$patient['id']] as $treatment): ?>
                                                    <tr>
                                                        <td style="font-size: 0.85rem;"><?php echo date('d/m/Y', strtotime($treatment['visit_date'])); ?></td>
                                                        <td style="font-size: 0.85rem;"><?php echo htmlspecialchars($treatment['procedure_name']); ?></td>
                                                        <td style="font-size: 0.85rem;"><?php echo htmlspecialchars($treatment['notes']); ?></td>
                                                        <td style="text-align: right; font-size: 0.85rem; font-weight: bold;">MK <?php echo number_format($treatment['amount'], 0); ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </td>
                                </tr>
                            <?php endif; ?>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="text-center text-muted py-3">
                                    <i class="bi bi-info-circle"></i> No patients found
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="patient-signature">
                Signed by: <span>KENNETH CHIRAMBO</span>
            </div>
        </div>
    </div>

    <!-- Delete All Patients Modal -->
    <div class="modal fade" id="deleteAllPatientsModal" tabindex="-1" aria-labelledby="deleteAllPatientsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title" id="deleteAllPatientsModalLabel">
                        <i class="bi bi-exclamation-triangle"></i> Delete All Patients
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-2"><strong>Warning:</strong> This will delete all patients and all their visits.</p>
                    <p class="text-danger mb-0"><strong>This action cannot be undone.</strong></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <form method="POST" class="d-inline">
                        <input type="hidden" name="delete_all_patients" value="1">
                        <button type="submit" class="btn btn-danger">
                            <i class="bi bi-trash"></i> Yes, Delete All
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>