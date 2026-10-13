<?php
require 'config.php';
ensure_logged_in();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$role = $_SESSION['role'] ?? 'patient';
$dashboard = ($role === 'doctor') ? 'dashboard_doctor.php' : (($role === 'hospital') ? 'hospital_dashboard.php' : (($role === 'laboratory') ? 'laboratory_dashboard.php' : 'dashboard_patient.php'));

$msg = '';

// Handle attachment re-upload / replacement
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_FILES['attachment']['name'])) {
    $upload_dir = __DIR__ . "/uploads/";
    if (!is_dir($upload_dir)) {
        @mkdir($upload_dir, 0777, true);
    }

    $safe_name = time() . "_" . preg_replace('/[^a-zA-Z0-9_\.-]/', '_', basename($_FILES['attachment']['name']));
    $target = $upload_dir . $safe_name;

    if (@move_uploaded_file($_FILES['attachment']['tmp_name'], $target)) {
        $bytes = @file_get_contents($target);
        $mime = !empty($_FILES['attachment']['type']) ? $_FILES['attachment']['type'] : 'application/octet-stream';
        $relPath = 'uploads/' . $safe_name;

        $hasBlob = false;
        $chk = $mysqli->query("SHOW COLUMNS FROM `records` LIKE 'file_data'");
        if ($chk && $chk->num_rows > 0) $hasBlob = true;

        if ($hasBlob && $bytes !== null) {
            $up = $mysqli->prepare("UPDATE records SET file_path = ?, file_data = ?, file_type = ? WHERE id = ?");
            $null = NULL;
            $up->bind_param("sbsi", $relPath, $null, $mime, $id);
            $up->send_long_data(1, $bytes);
            $up->execute();
        } else {
            $up = $mysqli->prepare("UPDATE records SET file_path = ? WHERE id = ?");
            $up->bind_param("si", $relPath, $id);
            $up->execute();
        }

        $msg = "✅ Attachment uploaded and saved successfully!";
    } else {
        $msg = "❌ Failed to upload attachment. Please try again.";
    }
}

// Fetch record
if ($role === 'patient') {
    $stmt = $mysqli->prepare("SELECT r.*, u.name AS patient_name FROM records r JOIN users u ON r.user_id = u.id WHERE r.id=? AND r.user_id=?");
    $stmt->bind_param("ii", $id, $_SESSION['user_id']);
} else {
    // Doctors and admins can view any patient record
    $stmt = $mysqli->prepare("SELECT r.*, u.name AS patient_name FROM records r JOIN users u ON r.user_id = u.id WHERE r.id=?");
    $stmt->bind_param("i", $id);
}
$stmt->execute();
$record = $stmt->get_result()->fetch_assoc();

if (!$record) {
    header("Location: $dashboard");
    exit;
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>View Record - CHR</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link rel="stylesheet" href="assets/css/styles.css">
</head>

<body>

<div class="container">

  <div class="topbar">
    <div class="brand">CHR</div>
    <div>
      <a href="<?= $dashboard ?>">Dashboard</a>
      <a href="logout.php">Logout</a>
    </div>
  </div>

  <div class="card" style="margin-top:20px;max-width:680px;margin-left:auto;margin-right:auto;">

    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;border-bottom:1px solid #e2e8f0;padding-bottom:10px;">
      <h2 style="margin:0;font-size:22px;">🧾 Record Details</h2>
      <span style="font-size:13px;color:var(--text-secondary);">Patient: <strong><?= htmlspecialchars($record['patient_name'] ?? 'Patient') ?></strong></span>
    </div>

    <?php if(!empty($msg)): ?>
      <div style="padding:10px 14px;border-radius:8px;margin-bottom:14px;font-size:13px;<?= strpos($msg, '✅') !== false ? 'background:#dcfce7;color:#166534;' : 'background:#fee2e2;color:#991b1b;' ?>">
        <?= htmlspecialchars($msg) ?>
      </div>
    <?php endif; ?>

    <p style="margin:8px 0;"><strong>Title:</strong> <?=htmlspecialchars($record['title'])?></p>
    <p style="margin:8px 0;"><strong>Date:</strong> <?=htmlspecialchars($record['record_date'])?></p>
    <div style="margin:12px 0;">
      <strong>Description / Clinical Notes:</strong>
      <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:12px;margin-top:6px;font-size:14px;line-height:1.6;color:#334155;">
        <?= !empty($record['description']) ? nl2br(htmlspecialchars($record['description'])) : '<em style="color:#94a3b8;">No notes provided.</em>' ?>
      </div>
    </div>

    <div style="margin-top:20px;padding-top:14px;border-top:1px solid #e2e8f0;">
      <strong>Attachment Document:</strong>
      <?php if(!empty($record['file_path'])): ?>
        <div style="display:flex;gap:10px;align-items:center;margin-top:8px;flex-wrap:wrap;">
          <a href="view_report.php?record_id=<?= $record['id'] ?>" target="_blank" class="btn" style="background:#2563eb;color:#fff;text-decoration:none;padding:8px 16px;border-radius:6px;font-size:13px;display:inline-flex;align-items:center;gap:6px;">
            👁️ Open / View File
          </a>
          <a href="download_report.php?record_id=<?= $record['id'] ?>" class="btn" style="background:#059669;color:#fff;text-decoration:none;padding:8px 16px;border-radius:6px;font-size:13px;display:inline-flex;align-items:center;gap:6px;">
            📥 Download File
          </a>
        </div>
      <?php else: ?>
        <p style="color:var(--text-muted);font-size:13px;margin:6px 0;">No file attached to this record.</p>
      <?php endif; ?>
    </div>

    <?php if ($role === 'patient'): ?>
      <!-- Option to upload or replace file -->
      <details style="margin-top:20px;background:#f8fafc;border:1px dashed #cbd5e1;border-radius:8px;padding:12px;">
        <summary style="font-size:13px;font-weight:600;color:var(--primary);cursor:pointer;">
          🔄 <?= !empty($record['file_path']) ? 'Replace or Re-upload Document' : 'Upload Document for this Record' ?>
        </summary>
        <form method="post" enctype="multipart/form-data" style="margin-top:12px;">
          <p style="font-size:12px;color:var(--text-secondary);margin:0 0 8px 0;">
            Select an image (JPG, PNG) or PDF report to save it permanently in the database:
          </p>
          <input type="file" name="attachment" required style="font-size:13px;margin-bottom:10px;" accept="image/*,application/pdf">
          <button type="submit" class="btn" style="font-size:12px;padding:6px 14px;">Upload & Save</button>
        </form>
      </details>
    <?php endif; ?>

    <div style="margin-top:24px;display:flex;gap:10px;">
      <a href="<?= $dashboard ?>" class="btn btn-secondary" style="text-decoration:none;padding:8px 20px;">⬅ Back</a>
    </div>

  </div>

</div>

</body>
</html>
