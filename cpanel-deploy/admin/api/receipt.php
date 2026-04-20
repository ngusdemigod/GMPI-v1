<?php
/**
 * Transaction Receipt PDF Generator
 * Church Financial Partnership System
 */

require_once __DIR__ . '/../includes/config.php';

header('Cache-Control: private, max-age=0, must-revalidate');
header('Pragma: public');

// Get transaction reference
$ref = $_GET['ref'] ?? '';

if (empty($ref)) {
    http_response_code(400);
    die('Invalid transaction reference');
}

// Get transaction data
try {
    $db = Database::getInstance();
    $transaction = $db->fetchOne(
    "SELECT t.*, u.first_name, u.last_name, u.email, u.phone, p.title as project_title, 
            pm.card_type, pm.last_four_digits
         FROM transactions t
         INNER JOIN users u ON t.user_id = u.user_id
         LEFT JOIN projects p ON t.project_id = p.project_id
         LEFT JOIN payment_methods pm ON t.payment_method_id = pm.payment_method_id
         WHERE t.transaction_reference = :ref",
    ['ref' => $ref]
);

    if (!$transaction) {
        http_response_code(404);
        die('Transaction not found');
    }
} catch (Exception $e) {
    http_response_code(500);
    die('Database error: ' . $e->getMessage());
}

// Get exchange rate
$exchangeRate = getExchangeRate();
$amountNgn = usdToNgn($transaction['amount']);

// Check if TCPDF is available
$pdfPath = __DIR__ . '/../includes/tcpdf/tcpdf.php';
$useTCPDF = file_exists($pdfPath);

if ($useTCPDF) {
    require_once $pdfPath;
    
    // Set PDF headers
    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="receipt_' . $ref . '.pdf"');
    
    $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
    
    // Set document information
    $pdf->SetCreator('Bright Light Ministry Int\'l Partnership Portal');
    $pdf->SetAuthor('Bright Light Ministry Int\'l');
    $pdf->SetTitle('Transaction Receipt - ' . $transaction['transaction_reference']);
    $pdf->SetSubject('Transaction Receipt');
    
    // Set default header data
    $pdf->SetHeaderData('', '', 'Bright Light Ministry Int\'l', 'Partnership Portal');
    
    // Set header and footer fonts
    $pdf->setHeaderFont(Array(PDF_FONT_NAME_MAIN, '', PDF_FONT_SIZE_MAIN));
    $pdf->setFooterFont(Array(PDF_FONT_NAME_DATA, '', PDF_FONT_SIZE_DATA));
    
    // Set default monospace font
    $pdf->SetDefaultMonospaceFonts(FONT_MONOSPACE);
    
    // Set margins
    $pdf->SetMargins(15, 25, 15);
    $pdf->SetAutoPageBreak(TRUE, 15);
    
    // Set font
    $pdf->SetFont('helvetica', '', 10);
    
    // Add a page
    $pdf->AddPage();
    
    // Header section with church branding
    $pdf->SetFillColor(212, 175, 55);
    $pdf->Rect(0, 0, 210, 25, 'F');
    
    $pdf->SetTextColor(26, 26, 46);
    $pdf->SetFont('helvetica', 'B', 16);
    $pdf->Cell(0, 8, 'BRIGHT LIGHT MINISTRY INT\'L', 0, 0, 'L', 0, '', 1);
    
    $pdf->SetFont('helvetica', '', 10);
    $pdf->Cell(0, 6, 'Partnership Portal', 0, 0, 'L', 0, '', 1);
    
    $pdf->Ln(10);
    
    // Receipt title
    $pdf->SetTextColor(212, 175, 55);
    $pdf->SetFont('helvetica', 'B', 14);
    $pdf->Cell(0, 8, 'TRANSACTION RECEIPT', 0, 1, 'C', 0, '', 1);
    
    $pdf->Ln(5);
    
    // Transaction details
    $pdf->SetTextColor(26, 26, 46);
    $pdf->SetFont('helvetica', '', 10);
    
    $widths = array(70, 140);
    
    $pdf->Cell($widths[0], 6, 'Transaction Reference', 0, 0, 'L', 0, '', 1);
    $pdf->Cell($widths[1], 6, $transaction['transaction_reference'], 0, 1, 'L', 0, '', 1);
    
    $pdf->Cell($widths[0], 6, 'Date', 0, 0, 'L', 0, '', 1);
    $pdf->Cell($widths[1], 6, date('F d, Y', strtotime($transaction['transaction_date'])), 0, 0, 'L', 0, '', 1);
    
    $pdf->Cell($widths[0], 6, 'Time', 0, 0, 'L', 0, '', 1);
    $pdf->Cell($widths[1], 6, date('g:i A', strtotime($transaction['transaction_date'])), 0, 1, 'L', 0, '', 1);
    
    $pdf->Cell($widths[0], 6, 'Payer', 0, 0, 'L', 0, '', 1);
    $pdf->Cell($widths[1], 6, $transaction['first_name'] . ' ' . $transaction['last_name'], 0, 1, 'L', 0, '', 1);
    
    $pdf->Cell($widths[0], 6, 'Email', 0, 0, 'L', 0, '', 1);
    $pdf->Cell($widths[1], 6, $transaction['email'], 0, 1, 'L', 0, '', 1);
    
    $pdf->Cell($widths[0], 6, 'Phone', 0, 0, 'L', 0, '', 1);
    $pdf->Cell($widths[1], 6, $transaction['phone'] ?? 'N/A', 0, 1, 'L', 0, '', 1);
    
    $pdf->Cell($widths[0], 6, 'Project/Category', 0, 0, 'L', 0, '', 1);
    $pdf->Cell($widths[1], 6, $transaction['project_title'] ?? ($transaction['category'] ?? 'N/A'), 0, 1, 'L', 0, '', 1);
    
    $pdf->Cell($widths[0], 6, 'Payment Method', 0, 0, 'L', 0, '', 1);
    $pdf->Cell($widths[1], 6, ($transaction['card_type'] ?? 'N/A') . ' •••• ' . ($transaction['last_four_digits'] ?? 'N/A'), 0, 1, 'L', 0, '', 1);
    
    $pdf->Cell($widths[0], 6, 'Status', 0, 0, 'L', 0, '', 1);
    $pdf->Cell($widths[1], 6, ucfirst($transaction['status']), 0, 1, 'L', 0, '', 1);
    
    // Amount section
    $pdf->Ln(5);
    $pdf->SetFillColor(253, 251, 247);
    $pdf->SetDrawColor(212, 175, 55);
    $pdf->Rect($pdf->GetX(), $pdf->GetY(), 180, 25, 'F');
    
    $pdf->SetTextColor(26, 26, 46);
    $pdf->SetFont('helvetica', 'B', 11);
    $pdf->Cell(180, 6, 'Payment Amount', 0, 0, 'L', 0, '', 1);
    
    $pdf->SetFont('helvetica', 'B', 14);
    $pdf->Cell(180, 6, '$' . number_format($transaction['amount'], 2), 0, 0, 'R', 0, '', 1);
    
    $pdf->SetFont('helvetica', '', 10);
    $pdf->Cell(180, 6, '(' . number_format($amountNgn, 2) . ' NGN)', 0, 1, 'R', 0, '', 1);
    
    // Footer
    $pdf->Ln(15);
    $pdf->SetFillColor(212, 175, 55);
    $pdf->Rect(0, $pdf->GetY(), 210, 20, 'F');
    
    $pdf->SetTextColor(26, 26, 46);
    $pdf->SetFont('helvetica', '', 8);
    $pdf->SetXY(15, $pdf->GetY() + 3);
    $pdf->Cell(0, 5, 'Thank you for your generous contribution!', 0, 0, 'C', 0, '', 1);
    
    $pdf->SetXY(15, $pdf->GetY() + 5);
    $pdf->Cell(0, 5, 'This is a computer-generated receipt. No signature required.', 0, 0, 'C', 0, '', 1);
    
    $pdf->SetXY(15, $pdf->GetY() + 5);
    $pdf->Cell(0, 5, 'For questions, contact: support@brightlightministry.org', 0, 0, 'C', 0, '', 1);
    
    $pdf->Output('receipt_' . $transaction['transaction_reference'] . '.pdf', 'I');
    exit;
} else {
    // Fallback: Generate HTML that can be printed to PDF
    header('Content-Type: text/html; charset=utf-8');
    $html = generateReceiptHTML($transaction, $amountNgn);
    echo $html;
    exit;
}

function generateReceiptHTML($transaction, $amountNgn) {
    $date = date('F d, Y', strtotime($transaction['transaction_date']));
    $time = date('g:i A', strtotime($transaction['transaction_date']));
    $phone = $transaction['phone'] ?? 'N/A';
    $project = $transaction['project_title'] ?? ($transaction['category'] ?? 'N/A');
    $cardType = $transaction['card_type'] ?? 'N/A';
    $lastFour = $transaction['last_four_digits'] ?? 'N/A';
    $status = ucfirst($transaction['status']);
    $fullName = htmlspecialchars($transaction['first_name'] . ' ' . $transaction['last_name']);
    $email = htmlspecialchars($transaction['email']);
    $ref = htmlspecialchars($transaction['transaction_reference']);
    $amount = number_format($transaction['amount'], 2);
    
    $html = '<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Payment Receipt - Bright Light Ministries Int\'l</title>
<style>
* { margin: 0; padding: 0; box-sizing: border-box; }
body {
    font-family: \'Inter\', -apple-system, BlinkMacSystemFont, \'Segoe UI\', sans-serif;
    background: #f5f0e8;
    display: flex;
    justify-content: center;
    align-items: center;
    min-height: 100vh;
    padding: 20px;
}
.receipt-card {
    background: #ffffff;
    width: 100%;
    max-width: 480px;
    padding: 40px 32px;
    border-radius: 24px;
    box-shadow: 0 20px 60px rgba(0,0,0,0.08);
    text-align: center;
}
.church-header {
    margin-bottom: 24px;
}
.church-logo {
    width: 56px;
    height: 56px;
    background: linear-gradient(135deg, #C9A24B, #E7D9B4);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 12px;
    font-size: 24px;
    color: #1d2b4f;
}
.church-name {
    font-size: 14px;
    font-weight: 600;
    color: #1d2b4f;
    text-transform: uppercase;
    letter-spacing: 0.1em;
}
.church-subtitle {
    font-size: 11px;
    color: #888;
    margin-top: 2px;
}
.icon {
    width: 64px;
    height: 64px;
    background: linear-gradient(135deg, #C9A24B, #E7D9B4);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 16px;
    font-size: 28px;
    color: #1d2b4f;
}
h2 {
    margin: 0 0 24px;
    color: #1d2b4f;
    font-size: 24px;
    font-weight: 600;
}
.info {
    text-align: left;
}
.row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px 16px;
    border-radius: 10px;
    margin-bottom: 6px;
}
.row:nth-child(odd) {
    background: #f7f7f7;
}
.label {
    color: #6b7280;
    font-size: 14px;
}
.value {
    color: #1d2b4f;
    font-weight: 500;
    font-size: 14px;
    text-align: right;
}
.status {
    background: #d6eadf;
    color: #2f7a45;
    padding: 4px 12px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 600;
}
.divider {
    margin: 24px 0;
    height: 1px;
    background: #e5e5e5;
}
.total {
    font-size: 32px;
    font-weight: 700;
    color: #1d2b4f;
}
.currency {
    color: #C9A24B;
}
.footer {
    margin-top: 24px;
    padding-top: 20px;
    border-top: 1px solid #e5e5e5;
    font-size: 12px;
    color: #888;
    line-height: 1.6;
}
@media print {
    body { background: white; padding: 0; }
    .receipt-card { box-shadow: none; }
}
</style>
</head>
<body>
<div class="receipt-card">
    
    <div class="icon">✓</div>
    <h2>Payment Receipt</h2>
    <div class="church-name">Bright Light Ministries Int\'l</div>
        <div class="church-subtitle">Partnership Portal</div> <br><br></br></br>
    <div class="info">
        <div class="row">
            <span class="label">Transaction Reference</span>
            <span class="value">' . $ref . '</span>
        </div>
        <div class="row">
            <span class="label">Date</span>
            <span class="value">' . htmlspecialchars($date) . '</span>
        </div>
        <div class="row">
            <span class="label">Time</span>
            <span class="value">' . htmlspecialchars($time) . '</span>
        </div>
        <div class="row">
            <span class="label">Payer</span>
            <span class="value">' . $fullName . '</span>
        </div>
        <div class="row">
            <span class="label">Email</span>
            <span class="value">' . $email . '</span>
        </div>
        <div class="row">
            <span class="label">Phone</span>
            <span class="value">' . htmlspecialchars($phone) . '</span>
        </div>
        <div class="row">
            <span class="label">Project/Category</span>
            <span class="value">' . htmlspecialchars($project) . '</span>
        </div>
        <div class="row">
            <span class="label">Payment Method</span>
            <span class="value">' . htmlspecialchars($cardType . ' •••• ' . $lastFour) . '</span>
        </div>
        <div class="row">
            <span class="label">Status</span>
            <span class="status">' . htmlspecialchars($status) . '</span>
        </div>
        <div class="row">
            <span class="label">Total Amount</span>
            <span class="value">₦' . $amount . '</span>
        </div>
    </div>
    <div class="divider"></div>
    <div class="total">
        <span class="currency">₦</span>' . $amount . '
    </div>
    <div class="footer">
        Thank you for your generous contribution!<br>
        This is a computer-generated receipt. No signature required.<br>
        For questions, contact: support@brightlightministry.org
    </div>
</div>
<script>
    window.onload = function() {
        window.print();
    };
</script>
</body>
</html>';
    
    return $html;
}