<?php
// receipt.php - View receipt
session_start();
require_once 'config.php';
require_once 'database.php';
require_once 'airtel_money.php';

$visit_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($visit_id <= 0) {
    die('Invalid receipt ID');
}

try {
    $stmt = $pdo->prepare("
        SELECT v.*, p.name as patient_name, p.phone, p.address, pr.name as procedure_name
        FROM visits v 
        JOIN patients p ON v.patient_id = p.id 
        JOIN procedures pr ON v.procedure_id = pr.id 
        WHERE v.id = ?
    ");
    $stmt->execute([$visit_id]);
    $visit = $stmt->fetch();
    
    if (!$visit) {
        die('Receipt not found');
    }

    $stmt = $pdo->prepare("\n        SELECT v.amount, pr.name as procedure_name, v.notes\n        FROM visits v\n        JOIN procedures pr ON v.procedure_id = pr.id\n        WHERE v.patient_id = ? AND v.visit_date = ?\n        ORDER BY v.created_at ASC, v.id ASC\n    ");
    $stmt->execute([$visit['patient_id'], $visit['visit_date']]);
    $receipt_items = $stmt->fetchAll();
    $stmt = $pdo->prepare('SELECT payment_status, payment_method FROM receipts WHERE visit_id = ? ORDER BY id DESC LIMIT 1');
    $stmt->execute([$visit_id]);
    $payment_receipt = $stmt->fetch() ?: ['payment_status' => 'Pending', 'payment_method' => 'Mobile Money'];
    $receipt_total = 0;
    foreach ($receipt_items as $receipt_item) {
        $receipt_total += (float)$receipt_item['amount'];
    }
} catch(PDOException $e) {
    die('Error loading receipt');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receipt - <?php echo CLINIC_NAME; ?></title>
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
        .receipt-logo {
            width: 76px;
            height: 76px;
            margin: 0 auto 0.8rem;
            display: block;
            border-radius: 50%;
            background: linear-gradient(135deg, #ecfdf5, #d1fae5);
            box-shadow: 0 8px 18px rgba(22, 163, 74, 0.25);
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
        .receipt-header h2 {
            color: #000000;
            font-weight: 800;
            margin-bottom: 0.5rem;
            letter-spacing: 0.6px;
            text-transform: uppercase;
        }
        .receipt-header .clinic-info {
            font-size: 0.9rem;
            line-height: 1.7;
            display: inline-block;
            text-align: left;
            margin: 0.25rem auto 0;
            padding: 0.7rem 1rem;
            border-radius: 10px;
            background: linear-gradient(135deg, #f0fdf4, #eff6ff);
            border: 1px solid #d1fae5;
            color: #0f766e;
        }
        .receipt-header .clinic-info i {
            margin-right: 6px;
        }
        .receipt-header .clinic-info strong {
            color: #2563eb;
        }
        .receipt-header .clinic-info .location-text {
            color: #0f766e;
            font-weight: 600;
        }
        .receipt-header .clinic-info .phone-text {
            color: #ea580c;
            font-weight: 700;
        }
        .receipt-header .clinic-info .whatsapp-text {
            color: #16a34a;
            font-weight: 700;
        }
        .receipt-body {
            margin: 1rem 0;
        }
        .receipt-item {
            display: flex;
            justify-content: space-between;
            padding: 0.5rem 0;
            border-bottom: 1px solid #eee;
        }
        .receipt-item .label {
            color: #0b7a4a;
            font-weight: 600;
        }
        .receipt-item .value {
            font-weight: 600;
            color: #1f2937;
        }
        .receipt-total {
            display: flex;
            justify-content: space-between;
            padding: 1rem 0;
            font-weight: bold;
            font-size: 1.3rem;
            border-top: 2px solid #1dbf73;
            margin-top: 1rem;
            color: #0b7a4a;
        }
        .receipt-footer {
            text-align: center;
            margin-top: 1.5rem;
            padding-top: 1rem;
            border-top: 2px dashed #ddd;
            font-size: 0.9rem;
            color: #0b7a4a;
        }
        .receipt-footer p:last-child {
            color: #d97706;
            font-weight: 600;
        }
        .signature-line {
            margin: 1.25rem auto 0;
            padding-top: 0.75rem;
            border-top: 1px solid #d1d5db;
            max-width: 260px;
            font-size: 0.85rem;
            color: #0f172a;
            font-weight: 700;
            letter-spacing: 0.4px;
            text-align: center;
        }
        .signature-line span {
            color: #1dbf73;
            display: block;
            margin-top: 0.3rem;
            font-size: 1rem;
            letter-spacing: 0;
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
        .btn-print {
            border-radius: 25px;
            padding: 10px 30px;
            font-weight: 500;
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
                <img src="Screenshot%20(1)_5509.jpg" alt="Happy Tooth Dental clinic logo" class="receipt-logo" aria-label="Happy Tooth Dental clinic logo">
                <h2><?php echo CLINIC_NAME; ?></h2>
                <p class="clinic-info">
                    <span class="location-text"><i class="bi bi-geo-alt"></i> <?php echo CLINIC_LOCATION; ?></span><br>
                    <span class="phone-text"><i class="bi bi-telephone"></i> <?php echo CLINIC_CONTACT; ?> OR WHATSAPP ONLY <?php echo CLINIC_WHATSAPP; ?></span>
                </p>
                <p class="clinic-info">
                    <strong>Receipt #:</strong> <?php echo str_pad($visit['id'], 6, '0', STR_PAD_LEFT); ?><br>
                    <strong>Date:</strong> <?php echo date('d/m/Y', strtotime($visit['visit_date'])); ?>
                </p>
            </div>
            
            <div class="receipt-body">
                <div class="receipt-item">
                    <span class="label">Patient</span>
                    <span class="value"><?php echo htmlspecialchars($visit['patient_name']); ?></span>
                </div>
                <div class="receipt-item">
                    <span class="label">Phone</span>
                    <span class="value"><?php echo htmlspecialchars($visit['phone']); ?></span>
                </div>
                <div class="receipt-item">
                    <span class="label">Address</span>
                    <span class="value"><?php echo htmlspecialchars($visit['address'] ?: 'Not provided'); ?></span>
                </div>
                <div class="receipt-item">
                    <span class="label">Visit Date</span>
                    <span class="value"><?php echo date('d/m/Y', strtotime($visit['visit_date'])); ?></span>
                </div>
                <div class="receipt-item">
                    <span class="label">Procedure</span>
                    <span class="value"><?php echo htmlspecialchars($visit['procedure_name']); ?></span>
                </div>
                <?php if ($visit['notes']): ?>
                <div class="receipt-item">
                    <span class="label">Notes</span>
                    <span class="value"><?php echo htmlspecialchars($visit['notes']); ?></span>
                </div>
                <?php endif; ?>

                <?php foreach ($receipt_items as $receipt_item): ?>
                    <div class="receipt-item">
                        <span class="label"><?php echo htmlspecialchars($receipt_item['procedure_name']); ?></span>
                        <span class="value">MK <?php echo number_format((float)$receipt_item['amount'], 0); ?></span>
                    </div>
                <?php endforeach; ?>
                
                <div class="receipt-total">
                    <span>Total Amount</span>
                    <span>MK <?php echo number_format($receipt_total, 0); ?></span>
                </div>
                <div class="receipt-item">
                    <span class="label">Payment</span>
                    <span class="value" id="paymentStatus"><?php echo htmlspecialchars($payment_receipt['payment_status']); ?> (<?php echo htmlspecialchars($payment_receipt['payment_method']); ?>)</span>
                </div>
            </div>
            
            <div class="receipt-footer">
                <p>Thank you for choosing <?php echo CLINIC_NAME; ?></p>
                <p>We wish you good health!</p>
                <div class="signature-line">
                    <span>Signed by:</span>
                    <span id="printedSignatureName">Kenneth Chirambo</span>
                    <img id="signatureImage" class="signature-image" alt="Written signature">
                </div>
            </div>
        </div>
        
        <div class="text-center mt-3 no-print">
            <?php if ($payment_receipt['payment_status'] !== 'Paid'): ?>
            <div class="card border-success mb-3 text-start">
                <div class="card-body">
                    <h5 class="card-title"><i class="bi bi-phone"></i> Pay with Airtel Money</h5>
                    <p class="card-text small text-muted">Customer Airtel number: enter the sender's phone. Send the total due to <strong>0992239889</strong>.</p>
                    <div class="small text-muted mb-2">
                        Total due: <strong>MK <?php echo number_format((float)$visit['amount'] + airtel_calculate_withdrawal_fee((float)$visit['amount']), 0); ?></strong>
                        <span class="d-block">Recommended fee: MK <?php echo number_format(airtel_calculate_withdrawal_fee((float)$visit['amount']), 0); ?></span>
                    </div>
                    <div class="input-group">
                        <input type="tel" id="airtelPhone" class="form-control" placeholder="099xxxxxxx" value="<?php echo htmlspecialchars($visit['phone']); ?>">
                        <button type="button" id="airtelPayButton" onclick="startAirtelPayment()" class="btn btn-success">Pay now</button>
                    </div>
                    <div id="airtelMessage" class="small mt-2"></div>
                </div>
            </div>
            <?php endif; ?>
            <div id="signaturePad" class="signature-pad">
                <canvas id="signatureCanvas" width="720" height="280"></canvas>
                <div class="mt-2">
                    <button type="button" onclick="clearSignature()" class="btn btn-outline-secondary btn-sm">Clear</button>
                    <button type="button" onclick="useSignature()" class="btn btn-success btn-sm">Use Signature</button>
                </div>
            </div>
            <button type="button" onclick="toggleSignaturePad()" class="btn btn-warning btn-print">
                <i class="bi bi-pen"></i> Write Signature
            </button>
            <button onclick="window.print(); logReceipt('visit', '<?php echo (int)$visit['id']; ?>', '<?php echo addslashes(htmlspecialchars($visit['procedure_name'])); ?>', '<?php echo (float)$visit['amount']; ?>', 'Visit Receipt');" class="btn btn-primary btn-print">
                <i class="bi bi-printer"></i> Print Receipt
            </button>
            <a href="index.php" class="btn btn-secondary btn-print">
                <i class="bi bi-house"></i> Dashboard
            </a>
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

        async function startAirtelPayment() {
            const button = document.getElementById('airtelPayButton');
            const message = document.getElementById('airtelMessage');
            const phone = document.getElementById('airtelPhone').value.trim();
            const totalDue = <?php echo (float)airtel_total_to_send((float)$visit['amount'])['total_amount']; ?>;
            button.disabled = true;
            message.textContent = 'Preparing Airtel payment...';

            try {
                const response = await fetch('start_airtel_payment.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: new URLSearchParams({visit_id: '<?php echo (int)$visit_id; ?>', phone: phone})
                });

                const responseText = await response.text();
                let result = {};
                const rawText = (responseText || '').trim();
                try {
                    result = rawText ? JSON.parse(rawText) : {};
                } catch (e) {
                    const text = rawText.toLowerCase();
                    if (text.includes('fatal') || text.includes('parse error') || text.includes('warning') || text.includes('<html') || text.includes('<!doctype')) {
                        throw new Error('Airtel API is not configured yet. Please send MK ' + Number(totalDue).toLocaleString() + ' to 0992239889 and confirm the payment manually.');
                    }
                    throw new Error(rawText || 'Airtel payment is currently unavailable. Please send the total amount to 0992239889 and confirm it manually.');
                }

                if (!result.ok) throw new Error(result.message || 'Unable to start Airtel payment.');

                if (result.manual || result.total_amount) {
                    message.textContent = 'Please send MK ' + Number(result.total_amount || totalDue).toLocaleString() + ' to 0992239889. ' + (result.message || '');
                    button.disabled = false;
                    return;
                }

                message.textContent = result.message;
                const check = async () => {
                    const statusResponse = await fetch('check_airtel_payment.php?id=' + encodeURIComponent(result.payment_id));
                    const statusText = await statusResponse.text();
                    let status = {};
                    try {
                        status = statusText ? JSON.parse(statusText) : {};
                    } catch (e) {
                        throw new Error('The payment status response was invalid.');
                    }

                    if (status.ok && status.status === 'SUCCESS') {
                        document.getElementById('paymentStatus').textContent = 'Paid (Mobile Money)';
                        message.textContent = 'Payment confirmed. You can now print the receipt.';
                        button.remove();
                    } else if (status.ok && status.status === 'FAILED') {
                        message.textContent = 'Payment failed or was cancelled.';
                        button.disabled = false;
                    } else {
                        setTimeout(check, 4000);
                    }
                };
                check();
            } catch (error) {
                message.textContent = error.message;
                button.disabled = false;
            }
        }
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>