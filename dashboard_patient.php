<?php
require 'config.php';
ensure_logged_in();
ensure_role('patient');

$user = current_user($mysqli);

/* ================= DELETE APPOINTMENT ================= */
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];

    $stmt = $mysqli->prepare("
        DELETE FROM appointments 
        WHERE id=? AND patient_id=?
    ");
    $stmt->bind_param("ii", $id, $user['id']);
    $stmt->execute();

    header("Location: dashboard_patient.php");
    exit;
}

/* ================= DOCTOR APPOINTMENTS ================= */
$stmt = $mysqli->prepare("
    SELECT 
        a.*, 
        u.name AS doctor_name, 
        h.name AS doctor_hospital
    FROM appointments a
    JOIN users u ON u.id = a.doctor_id
    JOIN hospitals h ON h.id = u.hospital_id
    WHERE a.patient_id = ?
    ORDER BY 
        COALESCE(a.rescheduled_date, a.appt_date) DESC,
        COALESCE(a.rescheduled_time, a.appt_time) DESC
");
$stmt->bind_param("i", $user['id']);
$stmt->execute();
$appts = $stmt->get_result();

/* ================= HEALTH RECORDS ================= */
$stmt2 = $mysqli->prepare("
    SELECT id, title, record_date, file_path, created_at 
    FROM records 
    WHERE user_id = ? 
    ORDER BY created_at DESC 
    LIMIT 10
");
$stmt2->bind_param('i', $user['id']);
$stmt2->execute();
$records = $stmt2->get_result();

/* ================= LAB APPOINTMENTS ================= */
$stmt3 = $mysqli->prepare("
    SELECT la.*, h.name as lab_name
    FROM lab_appointments la
    JOIN hospitals h ON la.lab_id = h.id
    WHERE la.patient_id = ?
    ORDER BY la.id DESC
");
$stmt3->bind_param("i", $user['id']);
$stmt3->execute();
$lab_appts = $stmt3->get_result();

/* ================= LAB REPORTS ================= */
$stmt4 = $mysqli->prepare("
    SELECT lr.*, la.test_name
    FROM lab_reports lr
    JOIN lab_appointments la ON lr.appointment_id = la.id
    WHERE lr.patient_id = ?
    ORDER BY lr.id DESC
");
$stmt4->bind_param("i", $user['id']);
$stmt4->execute();
$reports = $stmt4->get_result();
?>

<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>CHR - Patient Dashboard</title>

<link rel="stylesheet" href="assets/css/styles.css">

<style>
/* MOBILE RESPONSIVE (NO CSS CHANGE) */
.flex-wrap{
    display:flex;
    gap:16px;
    flex-wrap:wrap;
}

@media(max-width:768px){
    .flex-wrap{flex-direction:column;}
    .topbar{flex-direction:column;gap:10px;}
    .row{flex-direction:column;gap:5px;}
    .grid{grid-template-columns:repeat(2,1fr);}
}

.delete-btn{
    background:red;
    color:white;
    padding:5px 10px;
    border-radius:6px;
    text-decoration:none;
    font-size:12px;
}
</style>

</head>

<body>

<div class="container">

<!-- TOPBAR -->
<div class="topbar">
    <div class="brand">CHR</div>
    <div>
        <a href="edit_patient_profile.php">Edit Profile</a>
        <span style="margin-right:14px">
            Hello, <?=htmlspecialchars($user['name'])?>
        </span>
        <a href="logout.php">Logout</a>
    </div>
</div>

<div class="flex-wrap" style="margin-top:18px;">

<!-- LEFT SIDE -->
<div style="flex:1;min-width:260px;">

<!-- PROFILE -->
<div class="card">
    <h3 class="header-title">Profile</h3>
    <p><b>Email:</b> <?=htmlspecialchars($user['email'])?></p>
    <p><b>Phone:</b> <?=htmlspecialchars($user['phone'])?></p>
    <p><b>Address:</b><br>
        <?=nl2br(htmlspecialchars($user['address']))?>
    </p>
</div>

<!-- QUICK ACTIONS -->
<div class="card" style="margin-top:12px;">
    <h3 class="header-title">Quick Actions</h3>

    <div class="grid">
        <div class="card-small">
            <a href="add_record.php">📁<h3>Add Record</h3></a>
        </div>

        <div class="card-small">
            <a href="map.php">🗺️<h3>Nearby Hospitals</h3></a>
        </div>

        <div class="card-small">
            <a href="find_doctor.php">🩺<h3>Find Doctor</h3></a>
        </div>

        <div class="card-small">
            <a href="appointments.php">📅<h3>Book Appointment</h3></a>
        </div>

        <div class="card-small">
            <a href="lab_booking.php">🧪<h3>Lab Test</h3></a>
        </div>
    </div>
</div>

</div>

<!-- RIGHT SIDE -->
<div style="flex:1;min-width:320px;">

<!-- RECORDS -->
<div class="card">
    <h3 class="header-title">Recent Records</h3>

    <?php if ($records->num_rows === 0): ?>
        <p>No records yet.</p>
    <?php else: ?>
        <?php while($r = $records->fetch_assoc()): ?>
            <div class="row" style="border-bottom:1px solid #eee;padding:6px 0;">
                <div><?=htmlspecialchars($r['title'])?></div>
                <div style="font-size:12px;color:gray;">
                    <?=htmlspecialchars($r['record_date'])?>
                </div>
                <div style="display:flex;gap:8px;align-items:center;">
                    <?php if($r['file_path']): ?>
                        <a href="<?=htmlspecialchars($r['file_path'])?>" target="_blank">View</a>
                    <?php else: ?>—<?php endif; ?>
                    <a href="delete_record.php?id=<?= $r['id'] ?>"
                       style="color:red;font-size:12px;"
                       onclick="return confirm('Delete this record?')">🗑 Delete</a>
                </div>
            </div>
        <?php endwhile; ?>
    <?php endif; ?>
</div>


<!-- DOCTOR APPOINTMENTS -->
<div class="card" style="margin-top:12px;">
<h3 class="header-title">Your Appointments</h3>

<?php if($appts->num_rows === 0): ?>
<p>No appointments yet.</p>
<?php else: ?>

<?php while($a = $appts->fetch_assoc()):
    $isRescheduled = !empty($a['rescheduled_date']);
    $statusColors = [
        'accepted'    => 'green',
        'rejected'    => 'red',
        'rescheduled' => '#b45309',
        'pending'     => 'gray',
    ];
    $statusColor = $statusColors[$a['status']] ?? 'gray';
?>

<div class="row" style="border-bottom:1px solid #ddd;padding:8px;">

<b><?= htmlspecialchars($a['doctor_name']) ?></b><br>
<?= htmlspecialchars($a['doctor_hospital']) ?><br>

<?php if ($isRescheduled): ?>

<!-- RESCHEDULED -->
<div style="background:#fff7ed;color:#9a3412;padding:8px;border-radius:6px;margin-top:5px;">
    ⚠ Doctor rescheduled your appointment<br>
    <span style="text-decoration:line-through;font-size:12px;color:#999;">
        Was: <?= $a['appt_date'] ?> | <?= substr($a['appt_time'],0,5) ?>
    </span><br>
    <b>New Date: <?= $a['rescheduled_date'] ?> | <?= substr($a['rescheduled_time'],0,5) ?></b><br>
    <?php if (!empty($a['reschedule_msg'])): ?>
    <small><?= htmlspecialchars($a['reschedule_msg']) ?></small>
    <?php endif; ?>
</div>

<?php else: ?>

<!-- ORIGINAL DATE -->
<b>Date:</b> <?= $a['appt_date'] ?> | <?= $a['appt_time'] ?><br>

<?php endif; ?>

<?php if (!empty($a['reason'])): ?>
<small style="color:gray;">Reason: <?= htmlspecialchars($a['reason']) ?></small><br>
<?php endif; ?>

<!-- STATUS -->
<b>Status:</b>
<span style="color:<?= $statusColor ?>;">
    <?= ucfirst($a['status']) ?>
</span>

<br><br>

<!-- DELETE -->
<a class="delete-btn"
href="?delete=<?= $a['id'] ?>"
onclick="return confirm('Delete appointment?')">
Delete
</a>

</div>

<?php endwhile; ?>

<?php endif; ?>

</div>

<!-- LAB APPOINTMENTS -->
<div class="card" style="margin-top:12px;">
<h3 class="header-title">Lab Appointments</h3>

<?php if($lab_appts->num_rows === 0): ?>
<p>No lab appointments yet.</p>
<?php else: ?>

<?php while($l = $lab_appts->fetch_assoc()): ?>
<div class="row" style="border-bottom:1px solid #ddd;padding:6px;">
<b><?=htmlspecialchars($l['lab_name'])?></b><br>
<?=htmlspecialchars($l['test_name'])?><br>
<?=htmlspecialchars($l['appointment_date'])?><br>

<b>Status:</b>
<span style="color:
<?= $l['status']=='accepted'?'green':($l['status']=='rejected'?'red':'gray')?>;">
<?= ucfirst($l['status']) ?>
</span>
</div>
<?php endwhile; ?>

<?php endif; ?>
</div>

<!-- LAB REPORTS -->
<div class="card" style="margin-top:12px;">
<h3 class="header-title">Lab Reports</h3>

<?php if($reports->num_rows === 0): ?>
<p>No reports yet.</p>
<?php else: ?>

<?php while($r = $reports->fetch_assoc()): ?>
<div class="row" style="border-bottom:1px solid #ddd;padding:6px;">
<b><?=htmlspecialchars($r['test_name'])?></b><br>
<div style="display:flex;gap:8px;align-items:center;margin-top:4px;">
<a href="uploads/<?=htmlspecialchars($r['report_file'])?>" target="_blank">
View Report
</a>
<a href="delete_report.php?id=<?= $r['id'] ?>"
   style="color:red;font-size:12px;"
   onclick="return confirm('Delete this report?')">🗑 Delete</a>
</div>
</div>
<?php endwhile; ?>

<?php endif; ?>
</div>

</div>

</div>
</div>

</body>
</html>