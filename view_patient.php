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

/* PRESCRIPTIONS */
$p = $mysqli->prepare("SELECT p.*, d.name AS doc_name FROM prescriptions p LEFT JOIN users d ON p.doctor_id = d.id WHERE p.patient_id=? ORDER BY p.id DESC");
$p->bind_param("i", $patient_id);
$p->execute();
$prescriptions = $p->get_result();
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
  <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px;">
    <div>
      <h2 style="margin-bottom:4px;"><?= htmlspecialchars($patient['name'] ?? '') ?></h2>
      <p style="margin:2px 0;color:var(--text-secondary);">📧 <?= htmlspecialchars($patient['email'] ?? '') ?></p>
      <?php if(!empty($patient['phone'])): ?>
        <p style="margin:2px 0;color:var(--text-secondary);">📞 <?= htmlspecialchars($patient['phone']) ?></p>
      <?php endif; ?>
    </div>
    <div>
      <a href="add_prescription.php?patient_id=<?= (int)$patient['id'] ?>" class="btn" style="background:linear-gradient(135deg, #0284c7, #0369a1); color:#fff; text-decoration:none; padding:10px 18px; font-weight:700; border-radius:8px; display:inline-flex; align-items:center; gap:6px; box-shadow:0 2px 6px rgba(2,132,199,0.3);">
        💊 + Prescribe Medicine
      </a>
    </div>
  </div>
</div>

<div class="section-title" style="display:flex; justify-content:space-between; align-items:center;">
  <span>💊 Prescriptions & Medication Reminders</span>
  <a href="add_prescription.php?patient_id=<?= (int)$patient['id'] ?>" style="font-size:13px; font-weight:600; color:var(--primary); text-decoration:none;">+ New Prescription</a>
</div>

<?php if($prescriptions->num_rows === 0): ?>
  <div class="card" style="border-left:4px solid #cbd5e1;">
    <p style="color:var(--text-muted);margin:0;">No active or past prescriptions recorded for this patient.</p>
  </div>
<?php else: ?>
  <div style="display:grid; grid-template-columns:repeat(auto-fill, minmax(280px, 1fr)); gap:14px; margin-bottom:20px;">
    <?php while($rx = $prescriptions->fetch_assoc()): ?>
    <div class="card" style="border-left:4px solid <?= $rx['status'] === 'active' ? '#10b981' : '#94a3b8' ?>; margin:0; display:flex; flex-direction:column; justify-content:space-between;">
      <div>
        <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:6px;">
          <h3 style="margin:0; font-size:16px; color:var(--text-main);"><?= htmlspecialchars($rx['medicine_name']) ?></h3>
          <span style="font-size:11px; font-weight:700; padding:3px 8px; border-radius:12px; background:<?= $rx['status'] === 'active' ? '#ecfdf5' : '#f1f5f9' ?>; color:<?= $rx['status'] === 'active' ? '#047857' : '#64748b' ?>;">
            <?= ucfirst($rx['status']) ?>
          </span>
        </div>
        <p style="margin:0 0 6px 0; font-size:13px; color:var(--primary); font-weight:600;">
          Dose: <?= htmlspecialchars($rx['dosage']) ?> <?= !empty($rx['intake_relation']) ? '(' . htmlspecialchars($rx['intake_relation']) . ')' : '' ?>
        </p>

        <!-- SCHEDULE BADGES -->
        <div style="display:flex; flex-wrap:wrap; gap:4px; margin-bottom:8px;">
          <?php if(!empty($rx['timing_morning'])): ?>
            <span style="background:#fffbeb; color:#b45309; padding:2px 7px; border-radius:6px; font-size:11px; font-weight:600;">🌅 <?= date('h:i A', strtotime($rx['morning_time'])) ?></span>
          <?php endif; ?>
          <?php if(!empty($rx['timing_afternoon'])): ?>
            <span style="background:#eff6ff; color:#1d4ed8; padding:2px 7px; border-radius:6px; font-size:11px; font-weight:600;">☀️ <?= date('h:i A', strtotime($rx['afternoon_time'])) ?></span>
          <?php endif; ?>
          <?php if(!empty($rx['timing_night'])): ?>
            <span style="background:#faf5ff; color:#6b21a8; padding:2px 7px; border-radius:6px; font-size:11px; font-weight:600;">🌙 <?= date('h:i A', strtotime($rx['night_time'])) ?></span>
          <?php endif; ?>
          <?php if(!empty($rx['every_hours'])): ?>
            <span style="background:#f0fdf4; color:#15803d; padding:2px 7px; border-radius:6px; font-size:11px; font-weight:600;">⏱️ Every <?= (int)$rx['every_hours'] ?>h</span>
          <?php endif; ?>
        </div>

        <?php if(!empty($rx['instructions'])): ?>
          <p style="margin:0 0 8px 0; font-size:12px; color:var(--text-secondary); background:#f8fafc; padding:6px 8px; border-radius:6px;">
            ℹ️ <?= htmlspecialchars($rx['instructions']) ?>
          </p>
        <?php endif; ?>
      </div>

      <div style="border-top:1px dashed #e2e8f0; padding-top:6px; margin-top:6px; font-size:11px; color:var(--text-muted); display:flex; justify-content:space-between;">
        <span>📅 <?= $rx['duration_days'] ? (int)$rx['duration_days'] . ' days' : 'Ongoing' ?></span>
        <span>Dr. <?= htmlspecialchars($rx['doc_name'] ?? 'Doctor') ?></span>
      </div>
    </div>
    <?php endwhile; ?>
  </div>
<?php endif; ?>

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

    <?php if(!empty($row['report_file'])): ?>
      <div style="display:flex;gap:8px;margin-top:8px;flex-wrap:wrap;">
        <a href="view_report.php?id=<?= (int)$row['id'] ?>" target="_blank" class="btn" style="display:inline-flex;align-items:center;gap:4px;padding:6px 14px;font-size:13px;background:#2563eb;color:#fff;width:auto;border-radius:6px;text-decoration:none;">👁️ View Report</a>
        <a href="download_report.php?id=<?= (int)$row['id'] ?>" download class="btn" style="display:inline-flex;align-items:center;gap:4px;padding:6px 14px;font-size:13px;background:#059669;color:#fff;width:auto;border-radius:6px;text-decoration:none;">📥 Download Report</a>
      </div>
    <?php endif; ?>
  </div>
  <?php endwhile; ?>
<?php endif; ?>

</div>

</body>
</html>