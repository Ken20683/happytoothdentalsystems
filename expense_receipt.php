<?php
require_once 'config.php';
require_once 'database.php';

$expense_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$expense = null;

if ($expense_id > 0) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM expenses WHERE id = ?");
        $stmt->execute([$expense_id]);
        $expense = $stmt->fetch();

        if ($expense) {
            $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM expenses WHERE expense_date = ?");
            $stmt->execute([$expense['expense_date']]);
            $day_total = (float)$stmt->fetchColumn();
        }
    } catch (PDOException $e) {
        $expense = null;
    }
}

if (!$expense) {
    die('Expense receipt not found.');
}

if (!isset($day_total)) {
    $day_total = (float)$expense['amount'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Expense Receipt - <?php echo CLINIC_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <style>
        body {
            background: #f5f7fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .receipt-wrapper {
            max-width: 500px;
            margin: 2rem auto;
        }
        .receipt-card {
            background: white;
            border-radius: 15px;
            padding: 2rem;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        .receipt-header {
            text-align: center;
            border-bottom: 2px dashed #ddd;
            padding-bottom: 1rem;
            margin-bottom: 1rem;
        }
        .receipt-header h2 {
            color: #000000;
            font-weight: 800;
            margin-bottom: 0.5rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .receipt-body {
            margin: 1rem 0;
        }
        .receipt-item {
            display: flex;
            justify-content: space-between;
            padding: 0.6rem 0;
            border-bottom: 1px solid #eee;
        }
        .receipt-item .label {
            color: #0d6efd;
            font-weight: 600;
        }
        .receipt-item .value {
            font-weight: 600;
            color: #1f2937;
            text-align: right;
        }
        .receipt-total {
            display: flex;
            justify-content: space-between;
            padding: 1rem 0;
            font-weight: bold;
            font-size: 1.25rem;
            border-top: 2px solid #dc3545;
            margin-top: 1rem;
            color: #dc3545;
        }
        .receipt-footer {
            text-align: center;
            margin-top: 1.5rem;
            padding-top: 1rem;
            border-top: 2px dashed #ddd;
            font-size: 0.9rem;
            color: #555;
        }
        .btn-print {
            border-radius: 25px;
            padding: 10px 24px;
            font-weight: 600;
            margin-top: 1rem;
        }
        .signature-image {
            display: none;
            max-width: 220px;
            max-height: 70px;
            margin: 0.5rem auto 0;
        }
        .signature-pad {
            display: none;
            margin: 1rem auto;
            text-align: center;
        }
        .signature-pad canvas {
            width: 100%;
            max-width: 360px;
            height: 140px;
            border: 1px solid #adb5bd;
            border-radius: 6px;
            background: #fff;
            touch-action: none;
        }
        @media print {
            .no-print {
                display: none !important;
            }
            .receipt-card {
                box-shadow: none !important;
                border: 1px solid #ddd;
            }
        }
    </style>
</head>
<body>
    <div class="receipt-wrapper">
        <div class="receipt-card">
            <div class="receipt-header">
                <h2><?php echo CLINIC_NAME; ?></h2>
                <p class="mb-0"><i class="bi bi-geo-alt"></i> <?php echo CLINIC_LOCATION; ?></p>
                <p class="mb-0"><i class="bi bi-telephone"></i> <?php echo CLINIC_CONTACT; ?> OR WHATSAPP ONLY <?php echo CLINIC_WHATSAPP; ?></p>
            </div>

            <div class="receipt-body">
                <div class="receipt-item">
                    <span class="label">Expense ID</span>
                    <span class="value">#<?php echo str_pad($expense['id'], 6, '0', STR_PAD_LEFT); ?></span>
                </div>
                <div class="receipt-item">
                    <span class="label">Title</span>
                    <span class="value"><?php echo htmlspecialchars($expense['title']); ?></span>
                </div>
                <div class="receipt-item">
                    <span class="label">Category</span>
                    <span class="value"><?php echo htmlspecialchars($expense['category']); ?></span>
                </div>
                <div class="receipt-item">
                    <span class="label">Date</span>
                    <span class="value"><?php echo date('d M Y', strtotime($expense['expense_date'])); ?></span>
                </div>
                <div class="receipt-item">
                    <span class="label">Payment</span>
                    <span class="value"><?php echo htmlspecialchars($expense['payment_method']); ?></span>
                </div>
                <?php if (!empty($expense['notes'])): ?>
                <div class="receipt-item">
                    <span class="label">Notes</span>
                    <span class="value"><?php echo htmlspecialchars($expense['notes']); ?></span>
                </div>
                <?php endif; ?>
                <div class="receipt-total">
                    <span>Total Expenses for This Date</span>
                    <span>MK <?php echo number_format($day_total, 0); ?></span>
                </div>
            </div>

            <div class="receipt-footer">
                <p>Thank you for your business.</p>
                <p>Printed on <?php echo date('d M Y, h:i A'); ?></p>
                <div class="signature-line">
                    Signed by: <span id="printedSignatureName">KENNETH CHIRAMBO</span>
                    <img id="signatureImage" class="signature-image" alt="Written signature">
                </div>
            </div>

            <div class="text-center no-print d-flex justify-content-center gap-2 flex-wrap">
                <div id="signaturePad" class="signature-pad w-100">
                    <canvas id="signatureCanvas" width="720" height="280"></canvas>
                    <div class="mt-2">
                        <button type="button" onclick="clearSignature()" class="btn btn-outline-secondary btn-sm">Clear</button>
                        <button type="button" onclick="useSignature()" class="btn btn-success btn-sm">Use Signature</button>
                    </div>
                </div>
                <button type="button" onclick="toggleSignaturePad()" class="btn btn-warning btn-print">
                    <i class="bi bi-pen"></i> Write Signature
                </button>
                <a href="expenses.php" class="btn btn-light btn-print" style="color: #000; border-color: #fff;">
                    <i class="bi bi-arrow-left"></i> Back
                </a>
                <button class="btn btn-primary btn-print" onclick="window.print(); logReceipt('expense', '<?php echo (int)$expense['id']; ?>', '<?php echo addslashes(htmlspecialchars($expense['title'])); ?>', '<?php echo (float)$expense['amount']; ?>', 'Expense Receipt');">
                    <i class="bi bi-printer"></i> Print Receipt
                </button>
            </div>
        </div>
    </div>
    <script>
        const signatureCanvas = document.getElementById('signatureCanvas');
        const signatureContext = signatureCanvas.getContext('2d');
        let drawingSignature = false;

        function signaturePosition(event) {
            const bounds = signatureCanvas.getBoundingClientRect();
            return {
                x: (event.clientX - bounds.left) * signatureCanvas.width / bounds.width,
                y: (event.clientY - bounds.top) * signatureCanvas.height / bounds.height
            };
        }

        signatureCanvas.addEventListener('pointerdown', event => {
            drawingSignature = true;
            signatureCanvas.setPointerCapture(event.pointerId);
            const position = signaturePosition(event);
            signatureContext.beginPath();
            signatureContext.moveTo(position.x, position.y);
        });
        signatureCanvas.addEventListener('pointermove', event => {
            if (!drawingSignature) return;
            const position = signaturePosition(event);
            signatureContext.lineTo(position.x, position.y);
            signatureContext.stroke();
        });
        signatureCanvas.addEventListener('pointerup', () => drawingSignature = false);
        signatureContext.lineWidth = 4;
        signatureContext.lineCap = 'round';
        signatureContext.strokeStyle = '#111827';

        function toggleSignaturePad() {
            const pad = document.getElementById('signaturePad');
            pad.style.display = pad.style.display === 'block' ? 'none' : 'block';
        }

        function clearSignature() {
            signatureContext.clearRect(0, 0, signatureCanvas.width, signatureCanvas.height);
        }

        function useSignature() {
            document.getElementById('signatureImage').src = signatureCanvas.toDataURL('image/png');
            document.getElementById('signatureImage').style.display = 'block';
            document.getElementById('printedSignatureName').style.display = 'none';
            document.getElementById('signaturePad').style.display = 'none';
        }

        function logReceipt(type, itemId, title, amount, sourcePage) {
            const params = new URLSearchParams({
                type: type,
                item_id: itemId,
                title: title,
                amount: amount,
                source_page: sourcePage
            });

            fetch('log_receipt.php?' + params.toString())
                .catch(() => {});
        }
    </script>
</body>
</html>
