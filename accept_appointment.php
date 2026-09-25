<?php
require 'config.php';
ensure_logged_in();
ensure_role('doctor');

$doctor_id = (int)$_SESSION['user_id'];

$search_date = $_GET['date'] ?? date('Y-m-d');

/* ================= APPOINTMENTS (TODAY / SEARCH) ================= */
$stmt = $mysqli->prepare("
SELECT a.*, u.name AS patient_name 
FROM appointments a
JOIN users u ON u.id = a.patient_id
WHERE a.doctor_id = ?
AND a.appt_date = ?
ORDER BY a.appt_time ASC
");

$stmt->bind_param("is", $doctor_id, $search_date);
$stmt->execute();
$appointments = $stmt->get_result();

/* ================= LEAVES ================= */
$lstmt = $mysqli->prepare("
SELECT * FROM doctor_leaves WHERE doctor_id = ?
");
$lstmt->bind_param("i", $doctor_id);
$lstmt->execute();
$lres = $lstmt->get_result();

$leaves = [];
while($l = $lres->fetch_assoc()){
    $leaves[] = $l['leave_date'];
}
?>

<!DOCTYPE html>
<html>
<head>
<title>Doctor Dashboard</title>

<link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.js"></script>

<style>
body{
  font-family:Segoe UI;
  background:#f4f7fb;
  margin:0;
}

.container{max-width:1100px;margin:auto;padding:20px}

.topbar{
  background:linear-gradient(135deg,#2563eb,#1e40af);
  color:white;
  padding:15px;
  border-radius:12px;
  display:flex;
  justify-content:space-between;
  align-items:center;
}

.card{
  background:white;
  padding:15px;
  border-radius:12px;
  margin-top:15px;
  box-shadow:0 8px 20px rgba(0,0,0,0.06);
}

.btn{
  padding:8px 12px;
  border:none;
  border-radius:8px;
  background:#2563eb;
  color:white;
  cursor:pointer;
  margin-right:5px;
}

.search-box{
  margin-top:15px;
  display:flex;
  gap:10px;
}

input{
  padding:10px;
  border:1px solid #ddd;
  border-radius:8px;
}

/* CALENDAR */
#calendar{
  background:white;
  padding:10px;
  border-radius:12px;
  margin-top:20px;
}

/* LEAVE */
.fc-event-leave{
  background:#ef4444 !important;
  border:none !important;
}

/* MODAL */
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
  border-radius:12px;
  width:300px;
}
</style>
</head>

<body>

<div class="container">

<!-- TOP -->
<div class="topbar">
  <h2>Doctor Dashboard</h2>
  <div>
    <a href="edit_doctor_profile.php" style="color:white;margin-right:10px;">Edit Profile</a>
    <a href="logout.php" style="color:white;">Logout</a>
  </div>
</div>

<!-- ================= MARK LEAVE ================= -->
<div class="card">
<h3>🩺 Mark Leave</h3>

<form method="post" action="doctor_leave.php" style="display:flex;gap:10px;flex-wrap:wrap">

  <input type="date" name="leave_date" required>
  <input type="text" name="reason" placeholder="Reason (optional)">
  <button class="btn">Mark Leave</button>

</form>
</div>

<!-- ================= SEARCH ================= -->
<div class="search-box">
<form method="get">
  <input type="date" name="date" value="<?=$search_date?>">
  <button class="btn">Search</button>
  <a href="dashboard_doctor.php" class="btn" style="background:gray">Today</a>
</form>
</div>

<!-- ================= APPOINTMENTS ================= -->
<div class="card">
<h3>Appointments</h3>

<?php if($appointments->num_rows == 0): ?>
<p>No appointments</p>
<?php endif; ?>

<?php while($a = $appointments->fetch_assoc()): ?>
<div class="card">

<!-- PATIENT -->
<b>
<a href="view_patient.php?patient_id=<?=$a['patient_id']?>" style="color:#2563eb">
<?=$a['patient_name']?>
</a>
</b><br>

Time: <?=$a['appt_time']?><br>
Status: <?=$a['status']?>

<br><br>

<!-- ================= ACTIONS ================= -->

<?php if($a['status'] == 'pending'): ?>

<!-- ACCEPT / REJECT (YOUR FILE) -->
<a class="btn"
   href="accept_appointment.php?id=<?=$a['id']?>&s=accepted">
✔ Accept
</a>

<a class="btn"
   style="background:red"
   href="accept_appointment.php?id=<?=$a['id']?>&s=rejected">
✖ Reject
</a>

<!-- RESCHEDULE -->
<a class="btn" onclick="openModal(<?=$a['id']?>)">
🔄 Reschedule
</a>

<?php endif; ?>

</div>
<?php endwhile; ?>

</div>

<!-- ================= CALENDAR ================= -->
<div id="calendar"></div>

</div>

<!-- ================= RESCHEDULE MODAL ================= -->
<div class="modal" id="modal">
  <div class="modal-content">

    <h3>Reschedule</h3>

    <form method="post" id="rescheduleForm">
      <input type="date" name="new_date" required><br><br>
      <input type="time" name="new_time" required><br><br>
      <button class="btn">Save</button>
    </form>

    <br>
    <button class="btn" style="background:red" onclick="closeModal()">Close</button>

  </div>
</div>

<script>

/* ================= MODAL ================= */
function openModal(id){
  document.getElementById('modal').style.display='flex';
  document.getElementById('rescheduleForm').action =
    "update_appointment.php?id="+id+"&s=rescheduled";
}

function closeModal(){
  document.getElementById('modal').style.display='none';
}

/* ================= CALENDAR EVENTS ================= */
const events = [
<?php
$stmt = $mysqli->prepare("
SELECT a.*, u.name AS patient_name 
FROM appointments a
JOIN users u ON u.id = a.patient_id
WHERE a.doctor_id = ?
");
$stmt->bind_param("i", $doctor_id);
$stmt->execute();
$res = $stmt->get_result();

while($r = $res->fetch_assoc()){

$start = $r['appt_date']."T".$r['appt_time'];

echo "{
  title: '".addslashes($r['patient_name'])."',
  start: '$start',
  url: 'view_patient.php?patient_id=".$r['patient_id']."'
},";
}
?>

/* LEAVES */
<?php foreach($leaves as $ld): ?>
{
  title: "🚫 Leave",
  start: "<?=$ld?>",
  classNames: ['fc-event-leave']
},
<?php endforeach; ?>

];

document.addEventListener('DOMContentLoaded', function () {

const calendar = new FullCalendar.Calendar(document.getElementById('calendar'), {

  initialView: 'dayGridMonth',

  height: 650,

  headerToolbar: {
    left: 'prev,next today',
    center: 'title',
    right: 'dayGridMonth,timeGridWeek,timeGridDay'
  },

  events: events,

  eventClick: function(info){
    info.jsEvent.preventDefault();
    if(info.event.url){
      window.location.href = info.event.url;
    }
  }

});

calendar.render();

});

</script>

</body>
</html>