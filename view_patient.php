<?php
require 'config.php';
ensure_logged_in();
ensure_role('doctor');

$patient_id = $_GET['patient_id'];

/* PATIENT INFO */
$stmt = $mysqli->prepare("SELECT * FROM users WHERE id=?");
$stmt->bind_param("i", $patient_id);
$stmt->execute();
$patient = $stmt->get_result()->fetch_assoc();

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
<title>Patient Profile</title>

<style>
body{
  font-family:Segoe UI;
  background:#f4f7fb;
}

.container{
  max-width:900px;
  margin:auto;
  padding:20px;
}

.card{
  background:white;
  padding:15px;
  border-radius:12px;
  margin-bottom:15px;
  box-shadow:0 8px 20px rgba(0,0,0,0.06);
}

.profile{
  background:linear-gradient(135deg,#fff,#f1f5f9);
  border-left:5px solid #2563eb;
}

.section-title{
  font-size:18px;
  font-weight:600;
  margin:20px 0 10px;
}

a{
  color:#2563eb;
}
</style>

</head>

<body>

<div class="container">

<a href="dashboard_doctor.php">⬅ Back</a>

<div class="card profile">
  <h2><?=$patient['name']?></h2>
  <p><?=$patient['email']?></p>
</div>

<div class="section-title">🧾 Medical Records</div>

<?php while($row = $records->fetch_assoc()): ?>
<div class="card">
  <b><?=$row['title']?></b><br>
  <small><?=$row['record_date']?></small>
  <p><?=$row['description']?></p>

  <?php if($row['file_path']): ?>
    <a href="<?=$row['file_path']?>" target="_blank">View File</a>
  <?php endif; ?>
</div>
<?php endwhile; ?>


<div class="section-title">🧪 Lab Reports</div>

<?php while($row = $labs->fetch_assoc()): ?>
<div class="card">
  <small><?=$row['uploaded_at']?></small><br>

  <?php if($row['report_file']): ?>
    <a href="<?=$row['report_file']?>" target="_blank">View Lab Report</a>
  <?php endif; ?>
</div>
<?php endwhile; ?>

</div>

</body>
</html>