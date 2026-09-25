<?php
//session_start();
require 'config.php';

if (!isset($_GET['id'])) {
    die("Doctor ID missing");
}

$doctor_id = $_GET['id'];

/* 🔥 FETCH DOCTOR DETAILS */
$stmt = $mysqli->prepare("SELECT * FROM users WHERE id=? AND role='doctor'");
$stmt->bind_param("i", $doctor_id);
$stmt->execute();
$doctor = $stmt->get_result()->fetch_assoc();

if (!$doctor) {
    die("Doctor not found");
}

/* 🔥 CHECK TODAY AVAILABILITY */
$today = date("Y-m-d");

$stmt2 = $mysqli->prepare("SELECT * FROM doctor_leaves WHERE doctor_id=? AND leave_date=?");
$stmt2->bind_param("is", $doctor_id, $today);
$stmt2->execute();
$leave = $stmt2->get_result()->fetch_assoc();

$is_available = $leave ? "❌ On Leave" : "✅ Available";

/* 🔥 MONTHLY LEAVE RECORD */
$stmt3 = $mysqli->prepare("
SELECT DATE_FORMAT(leave_date, '%M') as month, COUNT(*) as total
FROM doctor_leaves
WHERE doctor_id=?
GROUP BY month
");

$stmt3->bind_param("i", $doctor_id);
$stmt3->execute();
$res = $stmt3->get_result();

$months = [];
$totals = [];

while ($row = $res->fetch_assoc()) {
    $months[] = $row['month'];
    $totals[] = $row['total'];
}
?>

<!DOCTYPE html>
<html>
<head>
<title>Doctor Profile</title>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<style>
body { font-family: Arial; background: #f1f5f9; }
.card { background:white; padding:20px; margin:20px; border-radius:10px; }
.status { font-weight:bold; font-size:18px; }
</style>
</head>

<body>

<div class="card">
<h2>👨‍⚕️ <?= $doctor['name'] ?></h2>
<p><b>Phone:</b> <?= $doctor['phone'] ?></p>
<p><b>Specialization:</b> <?= $doctor['specialization'] ?></p>

<p class="status">Status: <?= $is_available ?></p>
</div>

<div class="card">
<h3>📊 Monthly Leave Record</h3>
<canvas id="chart"></canvas>
</div>

<script>
new Chart(document.getElementById('chart'), {
    type: 'bar',
    data: {
        labels: <?= json_encode($months) ?>,
        datasets: [{
            label: 'Leaves',
            data: <?= json_encode($totals) ?>
        }]
    }
});
</script>

</body>
</html>