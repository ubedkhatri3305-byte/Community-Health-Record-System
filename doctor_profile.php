<?php
//session_start();
require 'config.php';

$doctor_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$doctor_id) {
    header("Location: index.php");
    exit;
}

/* 🔥 FETCH DOCTOR DETAILS */
$stmt = $mysqli->prepare("
    SELECT u.*, dp.qualification, dp.specialization as profile_spec, dp.address as profile_addr
    FROM users u
    LEFT JOIN doctor_profiles dp ON dp.doctor_id = u.id
    WHERE u.id=? AND u.role='doctor'
");
$stmt->bind_param("i", $doctor_id);
$stmt->execute();
$doctor = $stmt->get_result()->fetch_assoc();

if (!$doctor) {
    header("Location: index.php");
    exit;
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
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Doctor Profile - <?= htmlspecialchars($doctor['name'] ?? '') ?></title>
<link rel="stylesheet" href="assets/css/styles.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<style>
.status { font-weight:700; font-size:16px; margin-top:8px; }
</style>
</head>

<body>

<div class="container" style="max-width:800px;margin:auto;padding:20px;">

<div class="topbar">
    <div class="brand">Doctor Details</div>
    <div>
        <a href="javascript:history.back()">⬅ Back</a>
    </div>
</div>

<div class="card" style="margin-top:20px;">
<h2>👨‍⚕️ <?= htmlspecialchars($doctor['name'] ?? '') ?></h2>
<?php if(!empty($doctor['phone'])): ?>
<p><b>📞 Phone:</b> <?= htmlspecialchars($doctor['phone']) ?></p>
<?php endif; ?>
<p><b>🩺 Specialization:</b> <?= htmlspecialchars($doctor['specialization'] ?: ($doctor['profile_spec'] ?: 'General Practice')) ?></p>
<?php if(!empty($doctor['qualification'])): ?>
<p><b>🎓 Qualification:</b> <?= htmlspecialchars($doctor['qualification']) ?></p>
<?php endif; ?>

<p class="status">Availability: <?= $is_available ?></p>

<a href="appointments.php?doctor=<?= (int)$doctor['id'] ?>" class="btn" style="display:inline-block;margin-top:12px;">📅 Book Appointment</a>
</div>

<div class="card" style="margin-top:16px;">
<h3 style="margin-bottom:12px;">📊 Monthly Leave Record</h3>
<canvas id="chart"></canvas>
</div>

</div>

<script>
new Chart(document.getElementById('chart'), {
    type: 'bar',
    data: {
        labels: <?= json_encode($months) ?>,
        datasets: [{
            label: 'Leaves',
            data: <?= json_encode($totals) ?>,
            backgroundColor: '#3b82f6',
            borderRadius: 6
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: { display: false }
        }
    }
});
</script>

</body>
</html>