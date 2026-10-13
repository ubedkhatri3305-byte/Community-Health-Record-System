<?php
require 'config.php';
ensure_logged_in();

$report_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$record_id = isset($_GET['record_id']) ? (int)$_GET['record_id'] : 0;
$filename  = isset($_GET['file']) ? trim($_GET['file']) : '';

$db_filename = '';
$displayName = '';
$fileBlob = null;

// 1. Check lab_reports by id
if ($report_id > 0) {
    try {
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
            $db_filename = $report['report_file'] ?? '';
            $fileBlob = $report['file_data'] ?? null;
            $cleanTest = preg_replace('/[^a-zA-Z0-9_-]/', '_', $report['test_name'] ?? 'Lab_Report');
            $ext = pathinfo($db_filename, PATHINFO_EXTENSION);
            $displayName = $cleanTest . '_' . ($report['patient_name'] ?? 'patient') . '.' . ($ext ?: 'pdf');
        }
    } catch (Throwable $e) {}
}

// 2. Check records by record_id
if ($record_id > 0) {
    try {
        $stmt = $mysqli->prepare("SELECT * FROM records WHERE id = ?");
        $stmt->bind_param("i", $record_id);
        $stmt->execute();
        $rec = $stmt->get_result()->fetch_assoc();
        if ($rec) {
            $db_filename = basename($rec['file_path'] ?? '');
            $fileBlob = $rec['file_data'] ?? null;
            $cleanTitle = preg_replace('/[^a-zA-Z0-9_-]/', '_', $rec['title'] ?? 'Record');
            $ext = pathinfo($db_filename, PATHINFO_EXTENSION);
            $displayName = $cleanTitle . '_' . ($rec['record_date'] ?? date('Y-m-d')) . '.' . ($ext ?: 'pdf');
        }
    } catch (Throwable $e) {}
}

// 3. Fallback check by filename
if (empty($db_filename) && !empty($filename)) {
    $db_filename = basename($filename);
    $clean = basename($filename);

    try {
        $stmt = $mysqli->prepare("SELECT * FROM lab_reports WHERE report_file LIKE ? ORDER BY id DESC LIMIT 1");
        $param = "%" . $clean;
        $stmt->bind_param("s", $param);
        $stmt->execute();
        $rep = $stmt->get_result()->fetch_assoc();
        if ($rep && !empty($rep['file_data'])) {
            $fileBlob = $rep['file_data'];
        }
    } catch (Throwable $e) {}

    if (!$fileBlob) {
        try {
            $stmt = $mysqli->prepare("SELECT * FROM records WHERE file_path LIKE ? ORDER BY id DESC LIMIT 1");
            $param = "%" . $clean;
            $stmt->bind_param("s", $param);
            $stmt->execute();
            $rec = $stmt->get_result()->fetch_assoc();
            if ($rec && !empty($rec['file_data'])) {
                $fileBlob = $rec['file_data'];
            }
        } catch (Throwable $e) {}
    }
}

if (empty($db_filename)) {
    die("Invalid download request: No file specified.");
}

$clean_name = basename($db_filename);

$upload_dir = __DIR__ . '/uploads/';
if (!is_dir($upload_dir)) {
    @mkdir($upload_dir, 0777, true);
}

$candidates = [
    $upload_dir . $clean_name,
    __DIR__ . '/' . ltrim($db_filename, '/\\'),
    sys_get_temp_dir() . '/uploads/' . $clean_name,
    sys_get_temp_dir() . '/' . $clean_name
];

$filePath = null;
foreach ($candidates as $cand) {
    if (file_exists($cand) && is_file($cand) && filesize($cand) > 0) {
        $filePath = $cand;
        break;
    }
}

// Restore from blob if missing from disk
if (!$filePath && !empty($fileBlob)) {
    $restorePath = $upload_dir . $clean_name;
    if (@file_put_contents($restorePath, $fileBlob) !== false) {
        $filePath = $restorePath;
    }
}

if (!$filePath) {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Download Unavailable - CHR</title>
        <link rel="stylesheet" href="assets/css/styles.css">
    </head>
    <body class="center" style="min-height:100vh;padding:20px;">
        <div class="card" style="max-width:480px;text-align:center;">
            <div style="font-size:48px;margin-bottom:12px;">📁⚠️</div>
            <h2 style="margin-bottom:10px;">File Unavailable for Download</h2>
            <p style="color:var(--text-secondary);font-size:14px;margin-bottom:20px;">
                The requested file (<code><?= htmlspecialchars($clean_name) ?></code>) cannot be loaded from the storage folder.
            </p>
            <button onclick="history.back()" class="btn">⬅ Go Back</button>
        </div>
    </body>
    </html>
    <?php
    exit;
}

$downloadFileName = $displayName ?: $clean_name;

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
header('Expires: 0');
header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
header('Pragma: public');
header('Content-Length: ' . filesize($filePath));

readfile($filePath);
exit;
