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
    SELECT lr.*, COALESCE(la.test_name, 'Medical Test') AS test_name
    FROM lab_reports lr
    LEFT JOIN lab_appointments la ON lr.appointment_id = la.id
    WHERE lr.patient_id = ?
    ORDER BY lr.id DESC
");
$stmt4->bind_param("i", $user['id']);
$stmt4->execute();
$reports = $stmt4->get_result();

/* ================= PRESCRIPTIONS ================= */
$stmt_presc = $mysqli->prepare("
    SELECT p.*, u.name as doctor_name
    FROM prescriptions p
    JOIN users u ON p.doctor_id = u.id
    WHERE p.patient_id = ? AND p.status = 'active' AND p.end_date >= CURDATE()
    ORDER BY p.id DESC
");
$stmt_presc->bind_param("i", $user['id']);
$stmt_presc->execute();
$prescriptions_list = $stmt_presc->get_result();
$active_presc_array = [];
while ($prow = $prescriptions_list->fetch_assoc()) {
    $active_presc_array[] = $prow;
}
?>

<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>CHR - Patient Dashboard</title>

<link rel="stylesheet" href="assets/css/styles.css">
</head>

<body>

<div class="container">

<!-- TOPBAR -->
<div class="topbar">
    <div class="brand">CHR</div>
    <div>
        <a href="patient_prescriptions.php" style="background:rgba(255,255,255,0.18);padding:6px 12px;border-radius:6px;font-weight:600;">💊 My Prescriptions</a>
        <a href="notification.php">🔔 Notifications</a>
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
        <div class="card-small" style="background:#eff6ff;border:1px solid #bfdbfe;">
            <a href="patient_prescriptions.php">💊<h3 style="color:#1d4ed8;">Medicine Reminders</h3></a>
        </div>

        <div class="card-small">
            <a href="add_record.php">📁<h3>Add Record</h3></a>
        </div>

        <div class="card-small">
            <a href="map.php">🗺️<h3>Hospitals & Labs</h3></a>
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

<!-- ACTIVE PRESCRIPTIONS & REMINDERS WIDGET -->
<div class="card" style="margin-bottom:14px;border-left:4px solid var(--primary);background:linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;">
        <h3 class="header-title" style="margin:0;">💊 Active Prescriptions & Reminders</h3>
        <a href="patient_prescriptions.php" style="font-size:12px;font-weight:700;color:var(--primary);text-decoration:none;">View All &rarr;</a>
    </div>

    <?php if(empty($active_presc_array)): ?>
        <p style="color:var(--text-muted);font-size:13px;margin:0;">No active prescriptions scheduled. Consult a doctor to get personalized reminders.</p>
    <?php else: ?>
        <div style="display:flex;flex-direction:column;gap:10px;">
            <?php foreach(array_slice($active_presc_array, 0, 3) as $pr): ?>
                <div style="background:#f1f5f9;border-radius:8px;padding:10px 12px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;">
                    <div>
                        <strong>💊 <?= htmlspecialchars($pr['medicine_name']) ?></strong> (<?= htmlspecialchars($pr['dosage']) ?>)<br>
                        <small style="color:var(--text-secondary);">Dr. <?= htmlspecialchars($pr['doctor_name']) ?></small>
                    </div>
                    <div style="display:flex;gap:4px;">
                        <?php if($pr['timing_morning']): ?>
                            <span style="background:#fef3c7;color:#92400e;padding:2px 6px;border-radius:4px;font-size:10px;font-weight:700;">🌅 <?= substr($pr['morning_time'],0,5) ?></span>
                        <?php endif; ?>
                        <?php if($pr['timing_afternoon']): ?>
                            <span style="background:#ffedd5;color:#9a3412;padding:2px 6px;border-radius:4px;font-size:10px;font-weight:700;">☀️ <?= substr($pr['afternoon_time'],0,5) ?></span>
                        <?php endif; ?>
                        <?php if($pr['timing_night']): ?>
                            <span style="background:#ede9fe;color:#5b21b6;padding:2px 6px;border-radius:4px;font-size:10px;font-weight:700;">🌙 <?= substr($pr['night_time'],0,5) ?></span>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <div style="margin-top:12px;text-align:right;">
            <a href="patient_prescriptions.php" class="btn" style="padding:6px 14px;font-size:12px;display:inline-block;">🔔 Manage Notification Times</a>
        </div>
    <?php endif; ?>
</div>

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
    $statusClass = 'badge-' . ($a['status'] ?? 'pending');
?>

<div class="row" style="padding:12px;border-bottom:1px solid #f1f5f9;">

<div>
<b><?= htmlspecialchars($a['doctor_name']) ?></b><br>
<small style="color:var(--text-secondary);"><?= htmlspecialchars($a['doctor_hospital']) ?></small><br>

<?php if ($isRescheduled): ?>

<!-- RESCHEDULED -->
<div class="badge-rescheduled" style="padding:8px 12px;border-radius:8px;margin-top:6px;display:inline-block;">
    <strong>🔄 Doctor rescheduled your appointment</strong><br>
    <span style="text-decoration:line-through;font-size:12px;opacity:0.8;">
        Was: <?= $a['appt_date'] ?> | <?= substr($a['appt_time'],0,5) ?>
    </span><br>
    <b>New Date: <?= $a['rescheduled_date'] ?> | <?= substr($a['rescheduled_time'],0,5) ?></b>
    <?php if (!empty($a['reschedule_msg'])): ?>
    <br><small><?= htmlspecialchars($a['reschedule_msg']) ?></small>
    <?php endif; ?>
</div>

<?php else: ?>

<!-- ORIGINAL DATE -->
<div style="font-size:13px;color:var(--text-secondary);margin-top:3px;">
    <strong>Date:</strong> <?= $a['appt_date'] ?> | <?= $a['appt_time'] ?>
</div>

<?php endif; ?>

<?php if (!empty($a['reason'])): ?>
<div style="font-size:12px;color:var(--text-muted);margin-top:2px;">Reason: <?= htmlspecialchars($a['reason']) ?></div>
<?php endif; ?>

<div style="margin-top:6px;">
    <span class="badge <?= $statusClass ?>">
        <?= ucfirst($a['status']) ?>
    </span>
</div>
</div>

<!-- DELETE -->
<div>
<a class="delete-btn"
href="?delete=<?= $a['id'] ?>"
onclick="return confirm('Delete appointment?')">
Delete
</a>
</div>

</div>

<?php endwhile; ?>

<?php endif; ?>

</div>

<!-- LAB APPOINTMENTS -->
<div class="card" style="margin-top:16px;">
<h3 class="header-title">Lab Appointments</h3>

<?php if($lab_appts->num_rows === 0): ?>
<p style="color:var(--text-muted);font-size:14px;">No lab appointments yet.</p>
<?php else: ?>

<?php while($l = $lab_appts->fetch_assoc()): ?>
<div class="row" style="padding:10px 12px;border-bottom:1px solid #f1f5f9;">
<div>
<b><?=htmlspecialchars($l['lab_name'])?></b><br>
<span style="font-size:13px;color:var(--text-secondary);"><?=htmlspecialchars($l['test_name'])?> &bull; <?=htmlspecialchars($l['appointment_date'])?></span>
</div>

<div>
<span class="badge badge-<?=htmlspecialchars($l['status'])?>">
<?= ucfirst($l['status']) ?>
</span>
</div>
</div>
<?php endwhile; ?>

<?php endif; ?>
</div>

<!-- LAB REPORTS -->
<div class="card" style="margin-top:12px;">
<h3 class="header-title">🧪 Lab Reports</h3>

<?php if($reports->num_rows === 0): ?>
<p style="color:var(--text-muted);font-size:14px;">No lab reports uploaded yet.</p>
<?php else: ?>

<?php while($r = $reports->fetch_assoc()): ?>
<div class="row" style="border-bottom:1px solid #f1f5f9;padding:12px 6px;align-items:center;">
    <div>
        <b><?=htmlspecialchars($r['test_name'])?></b><br>
        <small style="color:var(--text-muted);">
            📅 <?= !empty($r['uploaded_at']) ? htmlspecialchars($r['uploaded_at']) : 'Recent' ?>
        </small>
    </div>
    <div style="display:flex;gap:6px;align-items:center;flex-wrap:wrap;">
        <a href="view_report.php?id=<?= (int)$r['id'] ?>" target="_blank" class="btn" style="padding:5px 12px;font-size:12px;background:#2563eb;color:#fff;border-radius:6px;text-decoration:none;display:inline-flex;align-items:center;gap:4px;">
            👁️ View
        </a>
        <a href="download_report.php?id=<?= (int)$r['id'] ?>" download class="btn" style="padding:5px 12px;font-size:12px;background:#059669;color:#fff;border-radius:6px;text-decoration:none;display:inline-flex;align-items:center;gap:4px;">
            📥 Download
        </a>
        <a href="delete_report.php?id=<?= $r['id'] ?>"
           class="delete-btn"
           onclick="return confirm('Delete this report?')">🗑</a>
    </div>
</div>
<?php endwhile; ?>

<?php endif; ?>
</div>

</div>

</div>
</div>

<!-- MEDICINE REMINDERS BACKGROUND ENGINE -->
<script>
const activeMedications = <?= json_encode($active_presc_array) ?>;

function checkPatientReminders() {
    if (!activeMedications || activeMedications.length === 0) return;
    if (!('Notification' in window) || Notification.permission !== 'granted') return;

    const now = new Date();
    const hh = String(now.getHours()).padStart(2, '0');
    const mm = String(now.getMinutes()).padStart(2, '0');
    const timeNow = `${hh}:${mm}`;
    const today = now.toISOString().slice(0, 10);

    activeMedications.forEach(med => {
        const slots = [];
        if (parseInt(med.timing_morning) === 1 && med.morning_time) {
            slots.push({ name: 'Morning', time: med.morning_time.slice(0, 5) });
        }
        if (parseInt(med.timing_afternoon) === 1 && med.afternoon_time) {
            slots.push({ name: 'Afternoon', time: med.afternoon_time.slice(0, 5) });
        }
        if (parseInt(med.timing_night) === 1 && med.night_time) {
            slots.push({ name: 'Night', time: med.night_time.slice(0, 5) });
        }

        slots.forEach(slot => {
            if (slot.time === timeNow) {
                const key = `chr_rem_${med.id}_${slot.name}_${today}`;
                if (!localStorage.getItem(key)) {
                    localStorage.setItem(key, '1');

                    // Audio Chime via Web Audio API
                    try {
                        const AudioContext = window.AudioContext || window.webkitAudioContext;
                        if (AudioContext) {
                            const ctx = new AudioContext();
                            const osc = ctx.createOscillator();
                            const gain = ctx.createGain();
                            osc.type = 'sine';
                            osc.frequency.setValueAtTime(587.33, ctx.currentTime);
                            osc.frequency.exponentialRampToValueAtTime(880.00, ctx.currentTime + 0.3);
                            gain.gain.setValueAtTime(0.3, ctx.currentTime);
                            gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.8);
                            osc.connect(gain);
                            gain.connect(ctx.destination);
                            osc.start();
                            osc.stop(ctx.currentTime + 0.8);
                        }
                    } catch(e) {}

                    new Notification(`💊 Medicine Reminder: ${med.medicine_name}`, {
                        body: `It's time for your ${slot.name} dose (${med.dosage}) - ${med.intake_relation.replace('_', ' ')}.`,
                        tag: `med_${med.id}_${slot.name}`
                    });

                    // Log in server
                    const data = new FormData();
                    data.append('medicine', med.medicine_name);
                    data.append('dosage', med.dosage);
                    data.append('slot', slot.name);
                    fetch('api_reminder_log.php', { method: 'POST', body: data });
                }
            }
        });
    });
}

document.addEventListener('DOMContentLoaded', function() {
    checkPatientReminders();
    setInterval(checkPatientReminders, 30000);
});
</script>

</body>
</html>