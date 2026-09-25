<?php
require 'config.php';
ensure_logged_in();
ensure_role('doctor');

$doctor_id   = (int)$_SESSION['user_id'];
$search_date = $_GET['date'] ?? date('Y-m-d');

/* ================= DOCTOR INFO ================= */
$dinfo = $mysqli->prepare("SELECT name FROM users WHERE id=?");
$dinfo->bind_param("i", $doctor_id);
$dinfo->execute();
$doctor = $dinfo->get_result()->fetch_assoc();

/* ===============================================================
   APPOINTMENTS FOR SELECTED DATE
   – Use rescheduled_date if set (appointment moved to new date),
     otherwise use appt_date.
   – The original date row must NOT appear if rescheduled.
   =============================================================== */
$stmt = $mysqli->prepare("
    SELECT a.*, u.name AS patient_name
    FROM appointments a
    JOIN users u ON u.id = a.patient_id
    WHERE a.doctor_id = ?
      AND (
          (a.rescheduled_date IS NULL AND a.appt_date = ?)
          OR
          (a.rescheduled_date IS NOT NULL AND a.rescheduled_date = ?)
      )
    ORDER BY COALESCE(a.rescheduled_time, a.appt_time) ASC
");
$stmt->bind_param("iss", $doctor_id, $search_date, $search_date);
$stmt->execute();
$appointments = $stmt->get_result();

/* ================= LEAVES ================= */
$lstmt = $mysqli->prepare("SELECT leave_date FROM doctor_leaves WHERE doctor_id=?");
$lstmt->bind_param("i", $doctor_id);
$lstmt->execute();
$lres   = $lstmt->get_result();
$leaves = [];
while ($l = $lres->fetch_assoc()) {
    $leaves[] = $l['leave_date'];
}

/* ================= ALL APPOINTMENTS FOR CALENDAR ================= */
$cal_stmt = $mysqli->prepare("
    SELECT a.*, u.name AS patient_name
    FROM appointments a
    JOIN users u ON u.id = a.patient_id
    WHERE a.doctor_id = ?
");
$cal_stmt->bind_param("i", $doctor_id);
$cal_stmt->execute();
$cal_res    = $cal_stmt->get_result();
$cal_events = [];
while ($r = $cal_res->fetch_assoc()) {
    $date  = !empty($r['rescheduled_date']) ? $r['rescheduled_date'] : $r['appt_date'];
    $time  = !empty($r['rescheduled_time']) ? $r['rescheduled_time'] : $r['appt_time'];
    $color = '#2563eb';
    if ($r['status'] === 'accepted')    $color = '#16a34a';
    if ($r['status'] === 'rejected')    $color = '#dc2626';
    if ($r['status'] === 'rescheduled') $color = '#d97706';
    $cal_events[] = ['title' => $r['patient_name'], 'start' => $date . 'T' . $time, 'color' => $color];
}
?>
<!DOCTYPE html>
<html>
<head>
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Doctor Dashboard</title>

<link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.js"></script>

<style>
body{font-family:Segoe UI;background:#f4f7fb;margin:0}
.container{max-width:1000px;margin:auto;padding:15px}

.topbar{
  background:#2563eb;
  color:white;
  padding:12px;
  border-radius:10px;
  display:flex;
  justify-content:space-between;
  flex-wrap:wrap;
}

.card{
  background:white;
  padding:12px;
  border-radius:10px;
  margin-top:12px;
  box-shadow:0 5px 15px rgba(0,0,0,0.05);
}

.btn{
  padding:6px 10px;
  border:none;
  border-radius:6px;
  background:#2563eb;
  color:white;
  cursor:pointer;
  margin:3px;
}

.btn-red{background:red}
.btn-gray{background:gray}

input{
  padding:8px;
  border:1px solid #ddd;
  border-radius:6px;
}

#calendar{
  margin-top:15px;
  background:white;
  padding:10px;
  border-radius:10px;
}

.modal{
  display:none;
  position:fixed;
  inset:0;
  background:rgba(0,0,0,0.5);
  justify-content:center;
  align-items:center;
}

.modal-content{
  background:white;
  padding:20px;
  border-radius:10px;
  width:280px;
}

@media(max-width:600px){
  .topbar{flex-direction:column;gap:8px}
}
</style>
</head>

<body>

<div class="container">

<!-- TOP -->
<div class="topbar">
  <h3>Doctor Dashboard</h3>
  <div>
    <!-- <a href="doctor_reports.php" style="color:white;">📋 Patient Reports</a> | -->
    <a href="edit_doctor_profile.php" style="color:white;">Edit</a> |
    <a href="logout.php" style="color:white;">Logout</a>
  </div>
</div>

<!-- MARK LEAVE -->
<div class="card">
<h3>🩺 Mark Leave</h3>

<form method="post" action="doctor_leave.php" style="display:flex;gap:10px;flex-wrap:wrap">
  <input type="date" name="leave_date" required>
  <input type="text" name="reason" placeholder="Reason (optional)">
  <button class="btn">Mark Leave</button>
</form>
</div>

<!-- DATE SEARCH -->
<div class="card" style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
<form method="get" style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
  <input type="date" name="date" value="<?= htmlspecialchars($search_date) ?>">
  <button class="btn">Search</button>
  <a href="dashboard_doctor.php" class="btn btn-gray">Today</a>
</form>
<small style="color:gray;margin-left:5px;">
  Showing: <b><?= date('D, d M Y', strtotime($search_date)) ?></b>
  &nbsp;(rescheduled appointments appear on their new date)
</small>
</div>

<!-- APPOINTMENTS -->
<div class="card">
<h3>Appointments</h3>

<?php if ($appointments->num_rows === 0): ?>
<p style="color:gray;">No appointments for this date.</p>
<?php endif; ?>

<?php while ($a = $appointments->fetch_assoc()):
    $isRescheduled = !empty($a['rescheduled_date']);
    $canEdit       = in_array($a['status'], ['pending', 'rescheduled']);
?>
<div class="card" id="appt-<?= $a['id'] ?>">

<!-- PATIENT -->
<b>
<a href="view_patient.php?patient_id=<?= $a['patient_id'] ?>" style="color:#2563eb;">
<?= htmlspecialchars($a['patient_name']) ?>
</a>
</b><br>

<!-- SHOW RESCHEDULED OR ORIGINAL DATE -->
<?php if ($isRescheduled): ?>
<div style="background:#fff7ed;color:#9a3412;padding:6px;border-radius:6px;margin:5px 0;">
  🔄 Rescheduled: Originally <s><?= $a['appt_date'] ?> <?= substr($a['appt_time'],0,5) ?></s><br>
  <b>New: <?= $a['rescheduled_date'] ?> | <?= substr($a['rescheduled_time'],0,5) ?></b>
</div>
<?php else: ?>
<b>Date:</b> <?= $a['appt_date'] ?> | <?= $a['appt_time'] ?><br>
<?php endif; ?>

<?php if (!empty($a['reason'])): ?>
<small style="color:gray;">Reason: <?= htmlspecialchars($a['reason']) ?></small><br>
<?php endif; ?>

Status:
<span id="status-<?= $a['id'] ?>" style="color:<?=
    $a['status']=='accepted' ? 'green' :
    ($a['status']=='rejected'    ? 'red' :
    ($a['status']=='rescheduled' ? '#b45309' : 'gray')) ?>;">
  <?= ucfirst($a['status']) ?>
</span>

<br><br>

<!-- ACTIONS -->
<div id="actions-<?= $a['id'] ?>" style="<?= $canEdit ? '' : 'display:none;' ?>">

<button class="btn" onclick="doAction(<?= $a['id'] ?>,'accepted')">✔ Accept</button>

<button class="btn btn-red" onclick="doAction(<?= $a['id'] ?>,'rejected')">✖ Reject</button>

<button class="btn" style="background:#d97706;" onclick="openModal(<?= $a['id'] ?>)">🔄 Reschedule</button>

</div>

<!-- EDIT BUTTON (shown when accepted/rejected) -->
<?php if (!$canEdit): ?>
<div id="edit-toggle-<?= $a['id'] ?>">
  <button class="btn btn-gray" onclick="enableEdit(<?= $a['id'] ?>)">✏ Edit Decision</button>
</div>
<?php endif; ?>

</div>
<?php endwhile; ?>

</div>

<!-- CALENDAR TOGGLE -->
<div style="margin-top:12px;">
  <button class="btn" onclick="toggleCalendar()" id="cal-btn">📅 Show Calendar</button>
</div>

<!-- CALENDAR (hidden by default) -->
<div id="calendar-wrap" style="display:none;">
  <div id="calendar"></div>
</div>

</div>

<!-- MODAL -->
<div class="modal" id="modal">
<div class="modal-content">

<h3>🔄 Reschedule</h3>

<form id="resForm">
  <label>New Date</label><br>
  <input type="date" name="new_date" id="res_date" required><br><br>
  <label>New Time</label><br>
  <input type="time" name="new_time" required><br><br>
  <button class="btn">Save</button>
</form>

<br>
<button class="btn btn-red" onclick="closeModal()">Close</button>

</div>
</div>

<script>

let currentId = null;

/* ================= ACCEPT / REJECT ================= */
function doAction(id, status) {
  fetch('update_appointment.php?id=' + id + '&s=' + status)
  .then(r => r.text())
  .then(resp => {

    if (resp.trim() !== 'success') {
      alert('Error: ' + resp);
      return;
    }

    const colors = { accepted:'green', rejected:'red' };
    const el = document.getElementById('status-' + id);
    if (el) { el.textContent = status.charAt(0).toUpperCase() + status.slice(1); el.style.color = colors[status]; }

    document.getElementById('actions-' + id).style.display = 'none';

    /* Add edit toggle if not already there */
    const apptEl = document.getElementById('appt-' + id);
    if (apptEl && !document.getElementById('edit-toggle-' + id)) {
      const div = document.createElement('div');
      div.id = 'edit-toggle-' + id;
      div.innerHTML = '<button class="btn btn-gray" onclick="enableEdit(' + id + ')">✏ Edit Decision</button>';
      apptEl.appendChild(div);
    }
  });
}

/* ================= ENABLE EDIT ================= */
function enableEdit(id) {
  document.getElementById('actions-' + id).style.display = 'block';
  const t = document.getElementById('edit-toggle-' + id);
  if (t) t.style.display = 'none';
}

/* ================= MODAL ================= */
function openModal(id) {
  currentId = id;
  document.getElementById('modal').style.display = 'flex';
  /* Set minimum date to tomorrow */
  const tomorrow = new Date();
  tomorrow.setDate(tomorrow.getDate() + 1);
  document.getElementById('res_date').min = tomorrow.toISOString().split('T')[0];
}

function closeModal() {
  document.getElementById('modal').style.display = 'none';
  document.getElementById('resForm').reset();
  currentId = null;
}

/* ================= RESCHEDULE ================= */
document.getElementById('resForm').onsubmit = function(e) {
  e.preventDefault();
  if (!currentId) return;

  const data = new FormData(this);

  fetch('update_appointment.php?id=' + currentId + '&s=reschedule', {
    method: 'POST',
    body: data
  })
  .then(r => r.text())
  .then(resp => {
    closeModal();
    if (resp.trim() === 'success') {
      location.reload();
    } else {
      alert('Error: ' + resp);
    }
  });
};

/* ================= CALENDAR ================= */
const events = <?= json_encode($cal_events) ?>;

<?php foreach ($leaves as $ld): ?>
events.push({ title: '🚫 Leave', start: '<?= $ld ?>', color: 'red' });
<?php endforeach; ?>

let calendarInstance = null;
let calendarReady    = false;

function toggleCalendar() {
  const wrap = document.getElementById('calendar-wrap');
  const btn  = document.getElementById('cal-btn');

  if (wrap.style.display === 'none') {
    wrap.style.display = 'block';
    btn.textContent    = '📅 Hide Calendar';

    /* Initialise only once */
    if (!calendarReady) {
      calendarInstance = new FullCalendar.Calendar(document.getElementById('calendar'), {
        initialView: 'dayGridMonth',
        height: 600,
        headerToolbar: {
          left:   'prev,next today',
          center: 'title',
          right:  'dayGridMonth,timeGridWeek,timeGridDay'
        },
        events: events,
        dateClick: function(info) {
          window.location.href = '?date=' + info.dateStr;
        }
      });
      calendarInstance.render();
      calendarReady = true;
    }
  } else {
    wrap.style.display = 'none';
    btn.textContent    = '📅 Show Calendar';
  }
}
</script>

</body>
</html>