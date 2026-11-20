<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

require_once 'config.php';
require_once 'database.php';

try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS materials_tracker (
        id INT AUTO_INCREMENT PRIMARY KEY,
        patient_name VARCHAR(255) NOT NULL,
        tracked_date DATE NOT NULL,
        gloves_used INT NOT NULL DEFAULT 0,
        masks_used INT NOT NULL DEFAULT 0,
        dental_needles_used INT NOT NULL DEFAULT 0,
        lignocaine_used INT NOT NULL DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    $columns = $pdo->query("SHOW COLUMNS FROM materials_tracker")->fetchAll(PDO::FETCH_COLUMN);
    if (in_array('notes', $columns, true)) {
        $pdo->exec("ALTER TABLE materials_tracker DROP COLUMN notes");
    }
    if (!in_array('masks_used', $columns, true)) {
        $pdo->exec("ALTER TABLE materials_tracker ADD COLUMN masks_used INT NOT NULL DEFAULT 0 AFTER gloves_used");
    }

    $pdo->exec("CREATE TABLE IF NOT EXISTS materials_stock (
        id INT AUTO_INCREMENT PRIMARY KEY,
        material_name VARCHAR(50) NOT NULL UNIQUE,
        quantity INT NOT NULL DEFAULT 0,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");

    $stock_materials = array(
        'gloves' => 'Gloves',
        'masks' => 'Masks',
        'dental_needles' => 'Dental Needles',
        'lignocaine' => 'Lignocaine'
    );

    $stock_insert = $pdo->prepare("INSERT INTO materials_stock (material_name, quantity) VALUES (?, 0) ON DUPLICATE KEY UPDATE material_name = material_name");
    foreach ($stock_materials as $material_key => $label) {
        $stock_insert->execute([$material_key]);
    }
} catch (PDOException $e) {
    $error = 'Database setup failed: ' . $e->getMessage();
}

$message = '';
$error = isset($error) ? $error : '';

$stock_materials = array(
    'gloves' => 'Gloves',
    'masks' => 'Masks',
    'dental_needles' => 'Dental Needles',
    'lignocaine' => 'Lignocaine'
);
$stock = array(
    'gloves' => 0,
    'masks' => 0,
    'dental_needles' => 0,
    'lignocaine' => 0
);

try {
    $stock_stmt = $pdo->query("SELECT material_name, quantity FROM materials_stock");
    while ($row = $stock_stmt->fetch(PDO::FETCH_ASSOC)) {
        if (array_key_exists($row['material_name'], $stock)) {
            $stock[$row['material_name']] = (int)$row['quantity'];
        }
    }
} catch (PDOException $e) {
    $stock = array(
        'gloves' => 0,
        'masks' => 0,
        'dental_needles' => 0,
        'lignocaine' => 0
    );
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['stock_action']) && $_POST['stock_action'] === 'add_stock') {
        $stock_material = isset($_POST['stock_material']) ? trim((string)$_POST['stock_material']) : '';
        $stock_quantity = isset($_POST['stock_quantity']) ? (int)$_POST['stock_quantity'] : 0;

        if (!array_key_exists($stock_material, $stock_materials) || $stock_quantity <= 0) {
            $error = 'Please select a valid stock item and quantity.';
        } else {
            try {
                $add_stmt = $pdo->prepare("UPDATE materials_stock SET quantity = quantity + ? WHERE material_name = ?");
                $add_stmt->execute([$stock_quantity, $stock_material]);
                $message = $stock_materials[$stock_material] . ' stock updated successfully.';
            } catch (PDOException $e) {
                $error = 'Unable to update stock: ' . $e->getMessage();
            }
        }
    } elseif (isset($_POST['stock_action']) && $_POST['stock_action'] === 'edit_stock') {
        $stock_material = isset($_POST['stock_material']) ? trim((string)$_POST['stock_material']) : '';
        $stock_quantity = isset($_POST['stock_quantity']) ? (int)$_POST['stock_quantity'] : -1;

        if (!array_key_exists($stock_material, $stock_materials) || $stock_quantity < 0) {
            $error = 'Please select a valid stock item and quantity.';
        } else {
            try {
                $edit_stmt = $pdo->prepare("UPDATE materials_stock SET quantity = ? WHERE material_name = ?");
                $edit_stmt->execute([$stock_quantity, $stock_material]);
                $message = $stock_materials[$stock_material] . ' stock edited successfully.';
            } catch (PDOException $e) {
                $error = 'Unable to edit stock: ' . $e->getMessage();
            }
        }
    } elseif (isset($_POST['delete_material_id'])) {
        $delete_id = (int)$_POST['delete_material_id'];

        try {
            $delete_stmt = $pdo->prepare("DELETE FROM materials_tracker WHERE id = ?");
            $delete_stmt->execute([$delete_id]);
            $message = 'Material record deleted successfully.';
        } catch (PDOException $e) {
            $error = 'Unable to delete material record: ' . $e->getMessage();
        }
    } else {
        $patient_name = isset($_POST['patient_name']) ? trim($_POST['patient_name']) : '';
        $tracked_date = isset($_POST['tracked_date']) ? trim($_POST['tracked_date']) : date('Y-m-d');
        $gloves_used = isset($_POST['gloves_used']) ? (int)$_POST['gloves_used'] : 0;
        $masks_used = isset($_POST['masks_used']) ? (int)$_POST['masks_used'] : 0;
        $dental_needles_used = isset($_POST['dental_needles_used']) ? (int)$_POST['dental_needles_used'] : 0;
        $lignocaine_used = isset($_POST['lignocaine_used']) ? (int)$_POST['lignocaine_used'] : 0;

        if ($patient_name === '') {
            $error = 'Patient name is required.';
        } elseif ($gloves_used < 0 || $masks_used < 0 || $dental_needles_used < 0 || $lignocaine_used < 0) {
            $error = 'Material counts cannot be negative.';
        } else {
            $stock_needed = array(
                'gloves' => $gloves_used,
                'masks' => $masks_used,
                'dental_needles' => $dental_needles_used,
                'lignocaine' => $lignocaine_used
            );
            $shortages = array();

            foreach ($stock_needed as $material_key => $quantity) {
                if ($quantity > 0 && (isset($stock[$material_key]) ? $stock[$material_key] : 0) < $quantity) {
                    $shortages[] = $stock_materials[$material_key];
                }
            }

            if (!empty($shortages)) {
                $error = 'Insufficient stock for ' . implode(', ', $shortages) . '. Please add more stock before recording this usage.';
            } else {
                try {
                    $pdo->beginTransaction();

                    $stmt = $pdo->prepare("INSERT INTO materials_tracker (patient_name, tracked_date, gloves_used, masks_used, dental_needles_used, lignocaine_used) VALUES (?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$patient_name, $tracked_date, $gloves_used, $masks_used, $dental_needles_used, $lignocaine_used]);

                    foreach ($stock_needed as $material_key => $quantity) {
                        if ($quantity > 0) {
                            $stock_update = $pdo->prepare("UPDATE materials_stock SET quantity = quantity - ? WHERE material_name = ?");
                            $stock_update->execute([$quantity, $material_key]);
                        }
                    }

                    $pdo->commit();
                    $message = 'Material usage saved successfully and stock was updated.';
                } catch (PDOException $e) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    $error = 'Unable to save material usage: ' . $e->getMessage();
                }
            }
        }
    }
}

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$query = "SELECT * FROM materials_tracker WHERE 1=1";
$params = array();

if ($search !== '') {
    $query .= " AND patient_name LIKE ?";
    $like = "%" . $search . "%";
    $params[] = $like;
}

$query .= " ORDER BY tracked_date DESC, created_at DESC";

$records = array();
try {
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $records = array();
}

$totals = array(
    'gloves' => 0,
    'needles' => 0,
    'lignocaine' => 0,
    'masks' => 0
);

foreach ($records as $record) {
    $totals['gloves'] += (int)$record['gloves_used'];
    $totals['masks'] += (int)(isset($record['masks_used']) ? $record['masks_used'] : 0);
    $totals['needles'] += (int)$record['dental_needles_used'];
    $totals['lignocaine'] += (int)$record['lignocaine_used'];
}

if (isset($_GET['log_material']) && $_GET['log_material'] == 1) {
    try {
        $logTitle = 'Materials Tracker - ' . date('d M Y');
        $totalCount = (int)$totals['gloves'] + (int)$totals['needles'] + (int)$totals['lignocaine'] + (int)$totals['masks'];
        $logStmt = $pdo->prepare("INSERT INTO receipt_logs (type, item_id, title, amount, source_page, printed_at) VALUES (?, ?, ?, ?, ?, NOW())");
        $logStmt->execute(['material', 0, $logTitle, (float)$totalCount, 'materials_tracker.php']);
        http_response_code(200);
        exit('logged');
    } catch (PDOException $e) {
        http_response_code(500);
        exit('log failed');
    }
}

$materials_receipt_number = 'MAT-' . date('YmdHis');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo CLINIC_NAME; ?> - Materials Tracker</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <style>
        body {
            background: linear-gradient(135deg, #08367ac9 0%, #c3cfe2 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .main-card {
            background: green;
            border-radius: 5px;
            padding: 0.5rem;
            box-shadow: 0 2px 20px rgba(0,0,0,0.1);
            margin: 0.5rem auto;
        }
        .btn-action {
            border-radius: 25px;
            padding: 10px 25px;
            font-weight: 500;
        }
        .summary-box {
            background: #f8f9fa;
            border-left: 4px solid #0d6efd;
            border-radius: 12px;
            padding: 1rem;
        }
        .receipt-box {
            border: 1px solid #ddd;
            border-radius: 12px;
            padding: 1.5rem;
            background: #fff;
            margin-top: 1rem;
        }
        .receipt-header {
            border-bottom: 2px dashed #ddd;
            margin-bottom: 1rem;
            padding-bottom: 1rem;
        }
        .receipt-logo {
            width: 80px;
            height: 80px;
            object-fit: contain;
            margin-bottom: 10px;
        }
        .receipt-company {
            font-size: 0.95rem;
            line-height: 1.6;
        }
        .receipt-signature {
            margin-top: 2rem;
            padding-top: 0.75rem;
            border-top: 1px solid #adb5bd;
            width: 260px;
            text-align: center;
        }
        @media print {
            .no-print { display: none !important; }
            body { background: white; }
            .main-card { box-shadow: none; border: 1px solid #ddd; margin: 0; }
        }
    </style>
</head>
<body>
    <div class="container py-4">
        <div class="main-card">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4 no-print">
                <div>
                    <h3 class="mb-0"><i class="bi bi-box-seam"></i> Materials Tracker</h3>
                </div>
                <div class="d-flex gap-2 flex-wrap">
                    <a href="index.php" class="btn btn-outline-dark btn-action"><i class="bi bi-house"></i> Dashboard</a>
                    <button type="button" class="btn btn-warning btn-action" onclick="document.getElementById('stock-section').scrollIntoView({behavior: 'smooth'});"><i class="bi bi-boxes"></i> Stock</button>
                    <button type="button" class="btn btn-primary btn-action" onclick="fetch('materials_tracker.php?log_material=1'); window.print();"><i class="bi bi-printer"></i> Print Report</button>
                </div>
            </div>

            <div class="receipt-box">
                <div class="receipt-header">
                    <img src="Screenshot%20(1)_5509.jpg" alt="Happy Tooth Dental logo" class="receipt-logo">
                    <h2 class="mb-1">HAPPY TOOTH DENTAL</h2>
                    <div class="receipt-company">
                        <div><strong>Contact:</strong> 0992239889</div>
                        <div><strong>Location:</strong> Area 25C Near Nazareen, EML Complex</div>
                    </div>
                    <p class="mt-2 mb-0"><strong>Materials Receipt:</strong> <?php echo htmlspecialchars($materials_receipt_number); ?></p>
                </div>

                <div class="row mb-3">
                    <div class="col-md-6">
                        <p class="mb-1"><strong>Printed:</strong> <?php echo date('d M Y, h:i A'); ?></p>
                        <p class="mb-0"><strong>Records:</strong> <?php echo count($records); ?></p>
                    </div>
                    <div class="col-md-6 text-md-end">
                        <p class="mb-1"><strong>Total Gloves:</strong> <?php echo number_format($totals['gloves']); ?></p>
                        <p class="mb-1"><strong>Total Masks:</strong> <?php echo number_format($totals['masks']); ?></p>
                        <p class="mb-1"><strong>Total Needles:</strong> <?php echo number_format($totals['needles']); ?></p>
                        <p class="mb-0"><strong>Total Lignocaine:</strong> <?php echo number_format($totals['lignocaine']); ?></p>
                    </div>
                </div>

                <?php if (empty($records)): ?>
                    <div class="alert alert-info mb-0 d-print-none">No material records found.</div>
                <?php else: ?>
                    <div class="table-responsive d-none d-print-block">
                        <table class="table table-bordered align-middle">
                            <thead>
                                <tr>
                                    <th>Patient</th>
                                    <th>Date</th>
                                    <th>Gloves</th>
                                    <th>Masks</th>
                                    <th>Needles</th>
                                    <th>Lignocaine</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($records as $record): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($record['patient_name']); ?></td>
                                        <td><?php echo date('d/m/Y', strtotime($record['tracked_date'])); ?></td>
                                        <td><?php echo number_format((int)$record['gloves_used']); ?></td>
                                        <td><?php echo number_format((int)(isset($record['masks_used']) ? $record['masks_used'] : 0)); ?></td>
                                        <td><?php echo number_format((int)$record['dental_needles_used']); ?></td>
                                        <td><?php echo number_format((int)$record['lignocaine_used']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>

                <div class="receipt-signature">
                    Signed by:
                    <strong>KENNETH CHIRAMBO</strong>
                </div>
            </div>

            <?php if ($message): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <div class="row g-3 mb-4 no-print">
                <div class="col-md-4">
                    <div class="summary-box">
                        <small class="text-muted">Total Gloves</small>
                        <h4 class="mb-0"><?php echo number_format($totals['gloves']); ?></h4>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="summary-box">
                        <small class="text-muted">Total Dental Needles</small>
                        <h4 class="mb-0"><?php echo number_format($totals['needles']); ?></h4>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="summary-box">
                        <small class="text-muted">Total Lignocaine</small>
                        <h4 class="mb-0"><?php echo number_format($totals['lignocaine']); ?></h4>
                    </div>
                </div>
            </div>

            <div id="stock-section" class="card border-0 shadow-sm mb-2" style="transform: scale(0.98); transform-origin: top left; width: 100%;">
                <div class="card-body p-2">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h6 class="mb-0"><i class="bi bi-boxes"></i> Stock</h6>
                        <span class="badge bg-success">Remaining stock</span>
                    </div>

                    <div class="row g-2 mb-2">
                        <?php foreach ($stock_materials as $material_key => $material_label): ?>
                            <div class="col-md-3 col-sm-6">
                                <div class="summary-box p-2">
                                    <small class="text-muted"><?php echo htmlspecialchars($material_label); ?></small>
                                    <h6 class="mb-0"><?php echo number_format((int)$stock[$material_key]); ?></h6>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <form method="POST" action="materials_tracker.php" class="row g-2 align-items-end mb-2 d-print-none no-print">
                        <input type="hidden" name="stock_action" value="add_stock">
                        <div class="col-md-5">
                            <label class="form-label mb-0 small">Material</label>
                            <select name="stock_material" class="form-select form-select-sm" required>
                                <?php foreach ($stock_materials as $material_key => $material_label): ?>
                                    <option value="<?php echo htmlspecialchars($material_key); ?>"><?php echo htmlspecialchars($material_label); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label mb-0 small">Quantity</label>
                            <input type="number" min="1" name="stock_quantity" class="form-control form-control-sm" value="1" required>
                        </div>
                        <div class="col-md-3 text-end">
                            <button type="submit" class="btn btn-success btn-sm w-100"><i class="bi bi-plus-circle"></i> Add Stock</button>
                        </div>
                    </form>

                    <form method="POST" action="materials_tracker.php" class="row g-2 align-items-end mb-0 d-print-none no-print">
                        <input type="hidden" name="stock_action" value="edit_stock">
                        <div class="col-md-5">
                            <label class="form-label mb-0 small">Edit Material</label>
                            <select name="stock_material" class="form-select form-select-sm" required>
                                <?php foreach ($stock_materials as $material_key => $material_label): ?>
                                    <option value="<?php echo htmlspecialchars($material_key); ?>"><?php echo htmlspecialchars($material_label); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label mb-0 small">New Quantity</label>
                            <input type="number" min="0" name="stock_quantity" class="form-control form-control-sm" value="0" required>
                        </div>
                        <div class="col-md-3 text-end">
                            <button type="submit" class="btn btn-outline-primary btn-sm w-100"><i class="bi bi-pencil-square"></i> Edit Stock</button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card border-0 shadow-sm mb-2 no-print" style="transform: scale(0.98); transform-origin: top left; width: 100%;">
                <div class="card-body p-2">
                    <form method="POST" action="materials_tracker.php" class="mx-auto" style="max-width: 1000px;">
                        <div class="row g-1 align-items-end">
                            <div class="col-md-3">
                                <label class="form-label mb-0 small">Patient</label>
                                <input type="text" name="patient_name" class="form-control form-control-sm" required value="<?php echo isset($_POST['patient_name']) ? htmlspecialchars($_POST['patient_name']) : ''; ?>">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label mb-0 small">Date</label>
                                <input type="date" name="tracked_date" class="form-control form-control-sm" value="<?php echo date('Y-m-d'); ?>">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label mb-0 small">Gloves</label>
                                <input type="number" min="0" name="gloves_used" class="form-control form-control-sm" value="0">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label mb-0 small">Masks</label>
                                <input type="number" min="0" name="masks_used" class="form-control form-control-sm" value="0">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label mb-0 small">Needles</label>
                                <input type="number" min="0" name="dental_needles_used" class="form-control form-control-sm" value="0">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label mb-0 small">Lignocaine</label>
                                <input type="number" min="0" name="lignocaine_used" class="form-control form-control-sm" value="0">
                            </div>
                            <div class="col-md-1 text-end">
                                <button type="submit" class="btn btn-success btn-sm w-100 px-1" title="Save Material Usage"><i class="bi bi-save"></i></button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card border-0 shadow-sm mb-2 no-print" style="transform: scale(0.98); transform-origin: top left; width: 100%;">
                <div class="card-body p-2">
                    <form method="GET" class="row g-2 align-items-end mb-0">
                        <div class="col-md-8">
                            <label class="form-label mb-0 small">Search patient</label>
                            <input type="text" name="search" class="form-control form-control-sm" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search patient...">
                        </div>
                        <div class="col-md-4 text-end">
                            <button type="submit" class="btn btn-outline-primary btn-sm btn-action"><i class="bi bi-search"></i> Search</button>
                        </div>
                    </form>
                </div>
            </div>

            <?php if (empty($records)): ?>
                <div class="alert alert-info no-print">No material records found.</div>
            <?php else: ?>
                <div class="table-responsive no-print">
                    <table class="table table-bordered table-striped align-middle">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Patient Name</th>
                                <th>Date</th>
                                <th>Gloves</th>
                                <th>Masks</th>
                                <th>Dental Needles</th>
                                <th>Lignocaine</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($records as $index => $record): ?>
                                <tr>
                                    <td><?php echo $index + 1; ?></td>
                                    <td><?php echo htmlspecialchars($record['patient_name']); ?></td>
                                    <td><?php echo date('d/m/Y', strtotime($record['tracked_date'])); ?></td>
                                    <td><?php echo number_format((int)$record['gloves_used']); ?></td>
                                    <td><?php echo number_format((int)(isset($record['masks_used']) ? $record['masks_used'] : 0)); ?></td>
                                    <td><?php echo number_format((int)$record['dental_needles_used']); ?></td>
                                    <td><?php echo number_format((int)$record['lignocaine_used']); ?></td>
                                    <td>
                                        <form method="POST" onsubmit="return confirm('Delete this material record?');">
                                            <input type="hidden" name="delete_material_id" value="<?php echo (int)$record['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-danger">
                                                <i class="bi bi-trash"></i> Delete
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
