<?php
require 'config.php';
ensure_logged_in();

$report_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$record_id = isset($_GET['record_id']) ? (int)$_GET['record_id'] : 0;
$filename  = isset($_GET['file']) ? trim($_GET['file']) : '';

$db_filename = '';
$fileBlob = null;
$blobMime = null;
$recordInfo = null;
$reportInfo = null;
$uploadMsg = '';

// Handle file replacement / re-upload if submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_FILES['replacement_file']['name'])) {
    $rec_id_post = (int)($_POST['record_id'] ?? 0);
    $rep_id_post = (int)($_POST['report_id'] ?? 0);
    
    $upload_dir = __DIR__ . "/uploads/";
    if (!is_dir($upload_dir)) {
        @mkdir($upload_dir, 0777, true);
    }
    
    $safe_name = time() . "_" . preg_replace('/[^a-zA-Z0-9_\.-]/', '_', basename($_FILES['replacement_file']['name']));
    $target = $upload_dir . $safe_name;
    
    if (@move_uploaded_file($_FILES['replacement_file']['tmp_name'], $target)) {
        $bytes = @file_get_contents($target);
        $mime = !empty($_FILES['replacement_file']['type']) ? $_FILES['replacement_file']['type'] : 'application/octet-stream';
        
        if ($rec_id_post > 0) {
            $stmt = $mysqli->prepare("UPDATE records SET file_path = ?, file_data = ?, file_type = ? WHERE id = ?");
            $relPath = 'uploads/' . $safe_name;
            $null = NULL;
            $stmt->bind_param("sbsi", $relPath, $null, $mime, $rec_id_post);
            $stmt->send_long_data(1, $bytes);
            $stmt->execute();
            header("Location: view_report.php?record_id=" . $rec_id_post);
            exit;
        } elseif ($rep_id_post > 0) {
            $stmt = $mysqli->prepare("UPDATE lab_reports SET report_file = ?, file_data = ?, file_type = ? WHERE id = ?");
            $null = NULL;
            $stmt->bind_param("sbsi", $safe_name, $null, $mime, $rep_id_post);
            $stmt->send_long_data(1, $bytes);
            $stmt->execute();
            header("Location: view_report.php?id=" . $rep_id_post);
            exit;
        }
    } else {
        $uploadMsg = "❌ Could not upload file. Please try again.";
    }
}

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
            $reportInfo = $report;
            $db_filename = $report['report_file'] ?? '';
            $fileBlob = $report['file_data'] ?? null;
            $blobMime = $report['file_type'] ?? null;
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
            $recordInfo = $rec;
            $db_filename = basename($rec['file_path'] ?? '');
            $fileBlob = $rec['file_data'] ?? null;
            $blobMime = $rec['file_type'] ?? null;
        }
    } catch (Throwable $e) {}
}

// 3. Fallback check by filename
if (empty($db_filename) && !empty($filename)) {
    $db_filename = basename($filename);
    $clean = basename($filename);
    
    // Check lab_reports for filename
    try {
        $stmt = $mysqli->prepare("
            SELECT lr.*, la.test_name 
            FROM lab_reports lr 
            LEFT JOIN lab_appointments la ON lr.appointment_id = la.id 
            WHERE lr.report_file LIKE ? 
            ORDER BY lr.id DESC LIMIT 1
        ");
        $param = "%" . $clean;
        $stmt->bind_param("s", $param);
        $stmt->execute();
        $rep = $stmt->get_result()->fetch_assoc();
        if ($rep) {
            $reportInfo = $rep;
            $report_id = (int)$rep['id'];
            $fileBlob = $rep['file_data'] ?? null;
            $blobMime = $rep['file_type'] ?? null;
        }
    } catch (Throwable $e) {}

    // Check records for filename
    if (!$fileBlob) {
        try {
            $stmt = $mysqli->prepare("SELECT * FROM records WHERE file_path LIKE ? ORDER BY id DESC LIMIT 1");
            $param = "%" . $clean;
            $stmt->bind_param("s", $param);
            $stmt->execute();
            $rec = $stmt->get_result()->fetch_assoc();
            if ($rec) {
                $recordInfo = $rec;
                $record_id = (int)$rec['id'];
                $fileBlob = $rec['file_data'] ?? null;
                $blobMime = $rec['file_type'] ?? null;
            }
        } catch (Throwable $e) {}
    }
}

if (empty($db_filename)) {
    die("Invalid request: No report or record file specified.");
}

$clean_name = basename($db_filename);

// Look on filesystem
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

// If missing on disk but exists in DB blob, restore it immediately
if (!$filePath && !empty($fileBlob)) {
    $restorePath = $upload_dir . $clean_name;
    if (@file_put_contents($restorePath, $fileBlob) !== false) {
        $filePath = $restorePath;
    }
}

// Fallback UI if file is unavailable on both disk and database blob
if (!$filePath) {
    $role = $_SESSION['role'] ?? 'patient';
    $backUrl = ($role === 'doctor') ? 'dashboard_doctor.php' : (($role === 'laboratory') ? 'laboratory_dashboard.php' : (($role === 'hospital') ? 'hospital_dashboard.php' : 'dashboard_patient.php'));
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Document Storage - CHR</title>
        <link rel="stylesheet" href="assets/css/styles.css">
    </head>
    <body class="center" style="min-height:100vh;padding:20px;background:#f8fafc;">
        <div class="card" style="max-width:540px;width:100%;text-align:center;padding:32px 28px;box-shadow:0 10px 25px rgba(0,0,0,0.08);border-radius:16px;">
            <div style="font-size:52px;margin-bottom:12px;">📄⚠️</div>
            <h2 style="margin-bottom:8px;color:#1e293b;">File Storage Notice</h2>
            
            <p style="color:#64748b;font-size:14px;line-height:1.6;margin-bottom:18px;">
                The file <strong style="color:#0f172a;"><code><?= htmlspecialchars($clean_name) ?></code></strong> was uploaded in a previous cloud container session and is not currently on disk.
            </p>

            <?php if (!empty($uploadMsg)): ?>
                <div style="background:#fee2e2;color:#991b1b;padding:10px 14px;border-radius:8px;font-size:13px;margin-bottom:16px;">
                    <?= htmlspecialchars($uploadMsg) ?>
                </div>
            <?php endif; ?>

            <?php if ($recordInfo): ?>
                <div style="background:#f1f5f9;border-radius:10px;padding:16px;text-align:left;font-size:13px;margin-bottom:20px;">
                    <div style="font-weight:700;font-size:15px;color:#0f172a;margin-bottom:6px;">🧾 Record: <?= htmlspecialchars($recordInfo['title']) ?></div>
                    <div style="color:#475569;margin-bottom:4px;">📅 Date: <?= htmlspecialchars($recordInfo['record_date'] ?? 'N/A') ?></div>
                    <?php if(!empty($recordInfo['description'])): ?>
                        <div style="color:#64748b;margin-top:6px;font-style:italic;">"<?= htmlspecialchars($recordInfo['description']) ?>"</div>
                    <?php endif; ?>
                </div>

                <!-- Re-upload Form for Patient/Owner -->
                <form method="post" enctype="multipart/form-data" style="background:#f8fafc;border:1.5px dashed #cbd5e1;border-radius:12px;padding:16px;margin-bottom:20px;text-align:left;">
                    <input type="hidden" name="record_id" value="<?= (int)$recordInfo['id'] ?>">
                    <strong style="display:block;font-size:13px;color:#1e293b;margin-bottom:6px;">🔄 Restore / Re-upload Attachment:</strong>
                    <p style="font-size:12px;color:#64748b;margin:0 0 10px 0;">Upload your file (image or PDF) to permanently save and restore it into the database:</p>
                    <input type="file" name="replacement_file" required style="font-size:13px;margin-bottom:10px;width:100%;" accept="image/*,application/pdf">
                    <button type="submit" class="btn" style="width:100%;padding:8px 16px;font-size:13px;background:#2563eb;">⬆ Upload & Save Document</button>
                </form>

            <?php elseif ($reportInfo): ?>
                <div style="background:#f1f5f9;border-radius:10px;padding:16px;text-align:left;font-size:13px;margin-bottom:20px;">
                    <div style="font-weight:700;font-size:15px;color:#0f172a;margin-bottom:6px;">🧪 Lab Report: <?= htmlspecialchars($reportInfo['test_name'] ?? 'Diagnostic Test') ?></div>
                    <div style="color:#475569;margin-bottom:4px;">👤 Patient: <?= htmlspecialchars($reportInfo['patient_name'] ?? 'Patient') ?></div>
                    <div style="color:#64748b;">📅 Uploaded: <?= htmlspecialchars($reportInfo['uploaded_at'] ?? 'N/A') ?></div>
                </div>

                <?php if ($role === 'laboratory' || $role === 'admin'): ?>
                <form method="post" enctype="multipart/form-data" style="background:#f8fafc;border:1.5px dashed #cbd5e1;border-radius:12px;padding:16px;margin-bottom:20px;text-align:left;">
                    <input type="hidden" name="report_id" value="<?= (int)$reportInfo['id'] ?>">
                    <strong style="display:block;font-size:13px;color:#1e293b;margin-bottom:6px;">🔄 Re-upload Lab Report Document:</strong>
                    <input type="file" name="replacement_file" required style="font-size:13px;margin-bottom:10px;width:100%;" accept="image/*,application/pdf">
                    <button type="submit" class="btn" style="width:100%;padding:8px 16px;font-size:13px;background:#7c3aed;">⬆ Restore Report</button>
                </form>
                <?php endif; ?>
            <?php endif; ?>

            <div style="display:flex;gap:10px;justify-content:center;">
                <button onclick="history.back()" class="btn" style="background:#475569;padding:8px 18px;">⬅ Go Back</button>
                <a href="<?= $backUrl ?>" class="btn" style="background:#2563eb;padding:8px 18px;text-decoration:none;">Dashboard</a>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// Clear any output buffer before sending file headers
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

$mimeType = $blobMime ?: ($mimeMap[$ext] ?? 'application/octet-stream');
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
