<?php
require 'config.php';
ensure_logged_in();

$report_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$filename = isset($_GET['file']) ? trim($_GET['file']) : '';

$db_filename = '';

if ($report_id > 0) {
    $stmt = $mysqli->prepare("SELECT report_file FROM lab_reports WHERE id = ?");
    $stmt->bind_param("i", $report_id);
    $stmt->execute();
    $report = $stmt->get_result()->fetch_assoc();
    if ($report) {
        $db_filename = $report['report_file'];
    }
}

if (empty($db_filename) && !empty($filename)) {
    $db_filename = basename($filename);
}

if (empty($db_filename)) {
    die("Invalid report request.");
}

$clean_name = basename($db_filename);

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
            <div style="font-size:48px;margin-bottom:12px;">📄⚠️</div>
            <h2 style="margin-bottom:10px;">Report File Unavailable</h2>
            <p style="color:var(--text-secondary);font-size:14px;margin-bottom:20px;">
                The report file <code><?= htmlspecialchars($clean_name) ?></code> cannot be loaded from the storage folder.
            </p>
            <button onclick="history.back()" class="btn">⬅ Go Back</button>
        </div>
    </body>
    </html>
    <?php
    exit;
}

while (ob_get_level()) {
    ob_end_clean();
}

$ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
$mimeMap = [
    'pdf'  => 'application/pdf',
    'png'  => 'image/png',
    'jpg'  => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'gif'  => 'image/gif',
    'webp' => 'image/webp',
    'txt'  => 'text/plain',
];

$mimeType = $mimeMap[$ext] ?? 'application/octet-stream';
if ($mimeType === 'application/octet-stream' && function_exists('mime_content_type')) {
    $detected = @mime_content_type($filePath);
    if ($detected) {
        $mimeType = $detected;
    }
}

header('Content-Type: ' . $mimeType);
header('Content-Disposition: inline; filename="' . str_replace('"', '', $clean_name) . '"');
header('Content-Length: ' . filesize($filePath));
header('Cache-Control: public, max-age=86400');

readfile($filePath);
exit;
