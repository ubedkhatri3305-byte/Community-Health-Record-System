<?php
require 'config.php';
ensure_logged_in();
ensure_role('doctor');

$patient_id = isset($_GET['patient_id']) ? (int)$_GET['patient_id'] : 0;

if (!$patient_id) {
    header("Location: dashboard_doctor.php");
    exit;
}

/* PATIENT INFO */
$stmt = $mysqli->prepare("SELECT * FROM users WHERE id=?");
$stmt->bind_param("i", $patient_id);
$stmt->execute();
$patient = $stmt->get_result()->fetch_assoc();

if (!$patient) {
    header("Location: dashboard_doctor.php");
    exit;
}

/* RECORDS */
$r = $mysqli->prepare("SELECT * FROM records WHERE user_id=? ORDER BY record_date DESC");
$r->bind_param("i", $patient_id);
$r->execute();
$records = $r->get_result();

/* LAB REPORTS */
$l = $mysqli->prepare("SELECT * FROM lab_reports WHERE patient_id=? ORDER BY uploaded_at DESC");
$l->bind_param("i", $patient_id);
$l->execute();
$labs = $l->get_result();
?>

<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Patient Profile - <?= htmlspecialchars($patient['name'] ?? '') ?></title>
<link rel="stylesheet" href="assets/css/styles.css">

<style>
.profile{
  background:linear-gradient(135deg,#fff,#f1f5f9);
  border-left:5px solid var(--primary);
}
.section-title{
  font-size:18px;
  font-weight:700;
  margin:24px 0 12px;
  color:var(--text-main);
}
</style>
</head>

<body>

<div class="container" style="max-width:900px;margin:auto;padding:20px;">

<div class="topbar">
  <div class="brand">Patient Profile</div>
  <div>
    <a href="dashboard_doctor.php">⬅ Back to Dashboard</a>
  </div>
</div>

<div class="card profile" style="margin-top:16px;">
  <h2 style="margin-bottom:4px;"><?= htmlspecialchars($patient['name'] ?? '') ?></h2>
  <p style="margin:2px 0;color:var(--text-secondary);">📧 <?= htmlspecialchars($patient['email'] ?? '') ?></p>
  <?php if(!empty($patient['phone'])): ?>
    <p style="margin:2px 0;color:var(--text-secondary);">📞 <?= htmlspecialchars($patient['phone']) ?></p>
  <?php endif; ?>
</div>

<div class="section-title">🧾 Medical Records</div>

<?php if($records->num_rows === 0): ?>
  <div class="card"><p style="color:var(--text-muted);margin:0;">No medical records found.</p></div>
<?php else: ?>
  <?php while($row = $records->fetch_assoc()): ?>
  <div class="card" style="margin-bottom:12px;">
    <b><?= htmlspecialchars($row['title'] ?? '') ?></b><br>
    <small style="color:var(--text-muted);"><?= htmlspecialchars($row['record_date'] ?? '') ?></small>
    <p style="margin:8px 0;"><?= nl2br(htmlspecialchars($row['description'] ?? '')) ?></p>

    <?php if(!empty($row['file_path'])): 
      $fileUrl = (strpos($row['file_path'], 'uploads/') === 0) ? $row['file_path'] : 'uploads/' . $row['file_path'];
    ?>
      <a href="<?= htmlspecialchars($fileUrl) ?>" target="_blank" class="btn" style="display:inline-block;padding:6px 14px;font-size:13px;">View File</a>
    <?php endif; ?>
  </div>
  <?php endwhile; ?>
<?php endif; ?>

<div class="section-title">🧪 Lab Reports</div>

<?php if($labs->num_rows === 0): ?>
  <div class="card"><p style="color:var(--text-muted);margin:0;">No lab reports found.</p></div>
<?php else: ?>
  <?php while($row = $labs->fetch_assoc()): ?>
  <div class="card" style="margin-bottom:12px;">
    <small style="color:var(--text-muted);"><?= htmlspecialchars($row['uploaded_at'] ?? '') ?></small><br>

    <?php if(!empty($row['report_file'])): 
      $reportUrl = (strpos($row['report_file'], 'uploads/') === 0) ? $row['report_file'] : 'uploads/' . $row['report_file'];
    ?>
      <a href="<?= htmlspecialchars($reportUrl) ?>" target="_blank" class="btn" style="display:inline-block;padding:6px 14px;font-size:13px;margin-top:6px;">View Lab Report</a>
    <?php endif; ?>
  </div>
  <?php endwhile; ?>
<?php endif; ?>

</div>

</body>
</html>