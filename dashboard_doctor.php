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
    $date     = !empty($r['rescheduled_date']) ? $r['rescheduled_date'] : (!empty($r['appt_date']) ? $r['appt_date'] : date('Y-m-d'));
    $raw_time = !empty($r['rescheduled_time']) ? $r['rescheduled_time'] : (!empty($r['appt_time']) ? $r['appt_time'] : '09:00:00');
    $time     = substr($raw_time, 0, 8);
    $status   = $r['status'] ?: 'pending';
    $color    = '#2563eb';
    if ($status === 'accepted')    $color = '#16a34a';
    if ($status === 'rejected')    $color = '#dc2626';
    if ($status === 'rescheduled') $color = '#d97706';
    $cal_events[] = [
        'id'        => (int)$r['id'],
        'title'     => ($r['patient_name'] ?? 'Patient') . ' (' . substr($time, 0, 5) . ')',
        'start'     => $date . 'T' . $time,
        'color'     => $color,
        'extendedProps' => [
            'patient_id'   => (int)$r['patient_id'],
            'patient_name' => $r['patient_name'] ?? 'Patient',
            'status'       => $status,
            'time'         => substr($time, 0, 5),
            'date'         => $date,
            'reason'       => $r['reason'] ?? '',
            'is_leave'     => false
        ]
    ];
}
?>
<!DOCTYPE html>
<html>
<head>
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Doctor Dashboard - CHR</title>

<link rel="stylesheet" href="assets/css/styles.css">
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.js"></script>

<style>
.btn-sm {
  padding: 6px 14px;
  font-size: 0.85rem;
  width: auto;
  display: inline-flex;
}

/* ================= FULLCALENDAR MASTER STYLES ================= */
#calendar-wrap {
  margin-top: 18px;
  background: white;
  border-radius: var(--radius-lg);
  border: 1px solid var(--card-border);
  box-shadow: var(--shadow);
  padding: 24px;
}

#calendar {
  min-height: 580px;
  font-family: inherit;
}

/* Header & Toolbar */
.fc .fc-toolbar.fc-header-toolbar {
  margin-bottom: 20px;
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 12px;
}

.fc .fc-toolbar-title {
  font-size: 1.35rem;
  font-weight: 800;
  color: var(--primary-dark);
  letter-spacing: -0.01em;
}

/* Toolbar Buttons */
.fc .fc-button {
  background: #f1f5f9;
  color: var(--text-main);
  border: 1px solid var(--card-border);
  border-radius: var(--radius-sm);
  padding: 7px 14px;
  font-weight: 600;
  font-size: 0.85rem;
  box-shadow: none;
  transition: all 0.2s ease;
  text-transform: capitalize;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: auto;
}

.fc .fc-button:hover {
  background: #e2e8f0;
  border-color: #cbd5e1;
  color: var(--text-main);
}

.fc .fc-button-primary:not(:disabled).fc-button-active,
.fc .fc-button-primary:not(:disabled):active,
.fc .fc-button.fc-today-button:active {
  background: var(--primary) !important;
  color: #ffffff !important;
  border-color: var(--primary) !important;
  box-shadow: 0 2px 8px rgba(37,99,235,0.3) !important;
}

.fc .fc-button-group {
  display: inline-flex;
  gap: 2px;
  background: #f1f5f9;
  padding: 3px;
  border-radius: 10px;
  border: 1px solid var(--card-border);
}

.fc .fc-button-group > .fc-button {
  border-radius: 7px;
  border: none;
  background: transparent;
  color: var(--text-secondary);
}

.fc .fc-button-group > .fc-button.fc-button-active {
  background: #ffffff !important;
  color: var(--primary) !important;
  font-weight: 700;
  box-shadow: 0 1px 3px rgba(0,0,0,0.08) !important;
}

/* Day Table Headers */
.fc .fc-col-header-cell {
  background: #f8fafc;
  padding: 10px 4px;
  font-weight: 700;
  font-size: 0.85rem;
  color: var(--text-secondary);
  border: 1px solid var(--card-border);
}

/* Grid Cells & Dates */
.fc-theme-standard td, .fc-theme-standard th {
  border-color: #eef2f6;
}

.fc .fc-daygrid-day {
  transition: background 0.15s ease;
}

.fc .fc-daygrid-day:hover {
  background: #f8fafc;
  cursor: pointer;
}

.fc .fc-daygrid-day-top {
  padding: 6px 8px;
  display: flex;
  justify-content: flex-end;
}

.fc .fc-daygrid-day-number {
  font-weight: 600;
  font-size: 0.88rem;
  color: var(--text-main);
  padding: 2px 6px;
  border-radius: 6px;
  text-decoration: none;
}

.fc .fc-daygrid-day.fc-day-today {
  background: rgba(37, 99, 235, 0.04) !important;
}

.fc .fc-daygrid-day.fc-day-today .fc-daygrid-day-number {
  background: var(--primary);
  color: #ffffff;
  font-weight: 700;
}

/* Events */
.fc-daygrid-event {
  margin: 2px 4px !important;
  padding: 3px 8px !important;
  border-radius: 6px !important;
  font-size: 0.8rem !important;
  font-weight: 600 !important;
  border: none !important;
  box-shadow: 0 1px 3px rgba(0,0,0,0.08);
  transition: transform 0.15s ease, box-shadow 0.15s ease;
  cursor: pointer;
}

.fc-daygrid-event:hover {
  transform: translateY(-1px);
  box-shadow: 0 4px 8px rgba(0,0,0,0.12);
}

.fc-daygrid-event-dot {
  display: none;
}

/* Calendar Legend */
.calendar-legend {
  display: flex;
  align-items: center;
  gap: 16px;
  flex-wrap: wrap;
  padding: 12px 16px;
  background: #f8fafc;
  border-radius: var(--radius);
  border: 1px solid var(--card-border);
  margin-bottom: 16px;
  font-size: 13px;
}

.legend-item {
  display: flex;
  align-items: center;
  gap: 6px;
  font-weight: 600;
  color: var(--text-secondary);
}

.legend-dot {
  width: 10px;
  height: 10px;
  border-radius: 50%;
}

.modal {
  display: none;
  position: fixed;
  inset: 0;
  background: rgba(15, 23, 42, 0.6);
  backdrop-filter: blur(4px);
  justify-content: center;
  align-items: center;
  z-index: 1000;
}

.modal-content {
  background: white;
  padding: 24px;
  border-radius: var(--radius-lg);
  width: 90%;
  max-width: 360px;
  box-shadow: var(--shadow-lg);
  border: 1px solid var(--card-border);
}
</style>
</head>

<body>

<div class="container">

<!-- TOP -->
<div class="topbar">
  <h3>Doctor Dashboard</h3>
  <div>
    <a href="doctor_prescriptions.php" style="color:white;font-weight:600;background:rgba(255,255,255,0.2);padding:5px 12px;border-radius:6px;text-decoration:none;margin-right:8px;">💊 Prescriptions</a>
    <a href="doctor_reports.php" style="color:white;margin-right:8px;">📋 Reports</a>
    <a href="edit_doctor_profile.php" style="color:white;margin-right:8px;">Edit Profile</a>
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
<div id="actions-<?= $a['id'] ?>" style="<?= $canEdit ? '' : 'display:none;' ?> margin-bottom:8px;">

<button class="btn" onclick="doAction(<?= $a['id'] ?>,'accepted')">✔ Accept</button>

<button class="btn btn-red" onclick="doAction(<?= $a['id'] ?>,'rejected')">✖ Reject</button>

<button class="btn" style="background:#d97706;" onclick="openModal(<?= $a['id'] ?>)">🔄 Reschedule</button>

</div>

<div style="display:flex; gap:8px; flex-wrap:wrap; align-items:center; margin-top:6px;">
  <a href="add_prescription.php?patient_id=<?= (int)$a['patient_id'] ?>&appointment_id=<?= (int)$a['id'] ?>" class="btn" style="background:linear-gradient(135deg, #0284c7, #0369a1); text-decoration:none; display:inline-flex; align-items:center; gap:5px; padding:6px 14px; font-size:13px; font-weight:600; border-radius:6px; color:#fff; box-shadow:0 1px 3px rgba(0,0,0,0.1);">
    💊 Prescribe Medicine
  </a>

  <!-- EDIT BUTTON (shown when accepted/rejected) -->
  <?php if (!$canEdit): ?>
  <div id="edit-toggle-<?= $a['id'] ?>" style="display:inline-block;">
    <button class="btn btn-gray" onclick="enableEdit(<?= $a['id'] ?>)" style="padding:6px 12px; font-size:12px;">✏ Edit Decision</button>
  </div>
  <?php endif; ?>
</div>

</div>
<?php endwhile; ?>

</div>

<!-- CALENDAR TOGGLE -->
<div style="margin-top:16px;">
  <button class="btn" onclick="toggleCalendar()" id="cal-btn" style="width:auto;display:inline-flex;padding:10px 22px;">
    📅 Show Calendar
  </button>
</div>

<!-- CALENDAR (hidden by default) -->
<div id="calendar-wrap" style="display:none;">
  <div class="calendar-legend">
    <span style="font-weight:700;color:var(--primary-dark);">Status Legend:</span>
    <span class="legend-item"><span class="legend-dot" style="background:#16a34a;"></span> Accepted</span>
    <span class="legend-item"><span class="legend-dot" style="background:#d97706;"></span> Rescheduled</span>
    <span class="legend-item"><span class="legend-dot" style="background:#2563eb;"></span> Pending</span>
    <span class="legend-item"><span class="legend-dot" style="background:#dc2626;"></span> Rejected</span>
    <span class="legend-item"><span class="legend-dot" style="background:#9333ea;"></span> Leave Day</span>
  </div>
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

<!-- 📋 EVENT DETAILS MODAL -->
<div class="modal" id="eventModal">
  <div class="modal-content" style="max-width:380px;">
    <h3 id="evModalTitle" style="margin-top:0;margin-bottom:12px;color:var(--primary-dark);">Appointment Details</h3>
    <div id="evModalBody" style="margin:16px 0;font-size:14px;color:var(--text-secondary);line-height:1.6;"></div>
    <div style="display:flex;gap:8px;flex-wrap:wrap;">
      <a id="evModalPatientLink" href="#" class="btn" style="flex:1;background:#2563eb;color:#fff;padding:8px 12px;font-size:13px;text-decoration:none;border-radius:6px;">👤 Patient</a>
      <a id="evModalDateLink" href="#" class="btn" style="flex:1;background:#0d9488;color:#fff;padding:8px 12px;font-size:13px;text-decoration:none;border-radius:6px;">📅 Date View</a>
      <button onclick="closeEventModal()" class="btn btn-gray" style="flex:1;padding:8px 12px;font-size:13px;border-radius:6px;">Close</button>
    </div>
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

/* ================= RESCHEDULE MODAL ================= */
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

/* ================= EVENT DETAILS MODAL ================= */
function openEventModal(event) {
  const props = event.extendedProps || {};
  if (props.is_leave) {
    document.getElementById('evModalTitle').textContent = '🚫 Doctor Leave Day';
    document.getElementById('evModalBody').innerHTML = `
      <div style="background:#fef2f2;border:1px solid #fecaca;padding:12px;border-radius:8px;color:#991b1b;">
        <strong>Date:</strong> ${props.date}<br>
        <span>You have marked this entire day as off-duty / leave.</span>
      </div>
    `;
    document.getElementById('evModalPatientLink').style.display = 'none';
    document.getElementById('evModalDateLink').href = '?date=' + props.date;
    document.getElementById('eventModal').style.display = 'flex';
    return;
  }

  const statusColors = {
    accepted: '#16a34a',
    rejected: '#dc2626',
    rescheduled: '#d97706',
    pending: '#2563eb'
  };

  const stColor = statusColors[props.status] || '#475569';
  document.getElementById('evModalTitle').textContent = '👤 ' + (props.patient_name || 'Appointment');
  document.getElementById('evModalBody').innerHTML = `
    <div style="background:#f8fafc;border:1px solid #e2e8f0;padding:12px;border-radius:8px;">
      <div style="margin-bottom:6px;">
        <strong>📅 Date:</strong> ${props.date} &bull; <strong>🕐 Time:</strong> ${props.time}
      </div>
      <div style="margin-bottom:6px;">
        <strong>Status:</strong> <span style="font-weight:700;color:${stColor};text-transform:capitalize;">● ${props.status}</span>
      </div>
      ${props.reason ? `<div><strong>Reason:</strong> ${props.reason}</div>` : ''}
    </div>
  `;

  document.getElementById('evModalPatientLink').style.display = 'inline-flex';
  document.getElementById('evModalPatientLink').href = 'view_patient.php?patient_id=' + props.patient_id;
  document.getElementById('evModalDateLink').href = '?date=' + props.date;
  document.getElementById('eventModal').style.display = 'flex';
}

function closeEventModal() {
  document.getElementById('eventModal').style.display = 'none';
}

window.addEventListener('click', function(event) {
  const modal = document.getElementById('modal');
  const evModal = document.getElementById('eventModal');
  if (event.target === modal) closeModal();
  if (event.target === evModal) closeEventModal();
});

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
events.push({
  title: '🚫 Leave Day',
  start: '<?= $ld ?>',
  color: '#9333ea',
  allDay: true,
  extendedProps: {
    is_leave: true,
    date: '<?= $ld ?>'
  }
});
<?php endforeach; ?>

let calendarInstance = null;
let calendarReady    = false;

function toggleCalendar() {
  const wrap = document.getElementById('calendar-wrap');
  const btn  = document.getElementById('cal-btn');

  if (wrap.style.display === 'none') {
    wrap.style.display = 'block';
    btn.innerHTML      = '📅 Hide Calendar';

    /* Initialise once, then update size on every reveal */
    if (!calendarReady) {
      calendarInstance = new FullCalendar.Calendar(document.getElementById('calendar'), {
        initialView: 'dayGridMonth',
        height: 'auto',
        headerToolbar: {
          left:   'prev,next today',
          center: 'title',
          right:  'dayGridMonth,timeGridWeek,timeGridDay'
        },
        buttonText: {
          today: 'Today',
          month: 'Month',
          week:  'Week',
          day:   'Day'
        },
        events: events,
        eventClick: function(info) {
          info.jsEvent.preventDefault();
          openEventModal(info.event);
        },
        dateClick: function(info) {
          window.location.href = '?date=' + info.dateStr;
        }
      });
      calendarInstance.render();
      calendarReady = true;
    }

    // Force layout update after container transitions from display:none
    setTimeout(() => {
      if (calendarInstance) {
        calendarInstance.updateSize();
      }
    }, 100);
  } else {
    wrap.style.display = 'none';
    btn.innerHTML      = '📅 Show Calendar';
  }
}
</script>

</body>
</html>