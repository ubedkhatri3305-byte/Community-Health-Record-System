<?php
require 'config.php';
ensure_logged_in();

$report_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$filename = isset($_GET['file']) ? trim($_GET['file']) : '';

$db_filename = '';
$displayName = '';

if ($report_id > 0) {
    $stmt = $mysqli->prepare("
        SELECT lr.*, la.test_name, u.name AS patient_name
        FROM lab_reports lr
        LEFT JOIN lab_appointments la ON lr.appointment_id = la.id
        LEFT JOIN users u ON lr.patient_id = u.id
        WHERE lr.id = ?
    ");
    $stmt->bind_param("i", $report_id);
    $stmt->execute();
    $report = $stmt->get_result()->fetch_assoc();

    if ($report) {
        $db_filename = $report['report_file'];
        $cleanTest = preg_replace('/[^a-zA-Z0-9_-]/', '_', $report['test_name'] ?? 'Lab_Report');
        $ext = pathinfo($db_filename, PATHINFO_EXTENSION);
        $displayName = $cleanTest . '_' . ($report['patient_name'] ?? 'patient') . '.' . ($ext ?: 'pdf');
    }
}

if (empty($db_filename) && !empty($filename)) {
    $db_filename = basename($filename);
}

if (empty($db_filename)) {
    die("Invalid report request.");
}

// Clean up stored path if prefixed with 'uploads/'
$clean_name = basename($db_filename);

// Potential paths to search
$candidates = [
    __DIR__ . '/uploads/' . $clean_name,
    __DIR__ . '/' . ltrim($db_filename, '/\\'),
    sys_get_temp_dir() . '/uploads/' . $clean_name,
    sys_get_temp_dir() . '/' . $clean_name
];

$filePath = null;
foreach ($candidates as $cand) {
    if (file_exists($cand) && is_file($cand)) {
        $filePath = $cand;
        break;
    }
}

if (!$filePath) {
    // Elegant fallback UI if file missing from disk
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Report Not Found - CHR</title>
        <link rel="stylesheet" href="assets/css/styles.css">
    </head>
    <body class="center" style="min-height:100vh;padding:20px;">
        <div class="card" style="max-width:480px;text-align:center;">
            <div style="font-size:48px;margin-bottom:12px;">📁⚠️</div>
            <h2 style="margin-bottom:10px;">Report File Unavailable</h2>
            <p style="color:var(--text-secondary);font-size:14px;margin-bottom:20px;">
                The requested report file (<code><?= htmlspecialchars($clean_name) ?></code>) is not currently found on the server storage.
            </p>
            <button onclick="history.back()" class="btn">⬅ Go Back</button>
        </div>
    </body>
    </html>
    <?php
    exit;
}

$downloadFileName = $displayName ?: $clean_name;

// Clean output buffers
while (ob_get_level()) {
    ob_end_clean();
}

$mimeType = 'application/octet-stream';
if (function_exists('mime_content_type')) {
    $detected = @mime_content_type($filePath);
    if ($detected) {
        $mimeType = $detected;
    }
}

header('Content-Description: File Transfer');
header('Content-Type: ' . $mimeType);
header('Content-Disposition: attachment; filename="' . str_replace('"', '', $downloadFileName) . '"');
header('Content-Transfer-Encoding: binary');
header('Expires: 0');
header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
header('Pragma: public');
header('Content-Length: ' . filesize($filePath));

readfile($filePath);
exit;
