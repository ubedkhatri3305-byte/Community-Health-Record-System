<?php
require 'config.php';
ensure_logged_in();
ensure_role('patient');

$user = current_user($mysqli);
$msg = "";

/* ================= LOCATION ================= */
$lat = isset($_GET['lat']) ? (float)$_GET['lat'] : 0;
$lng = isset($_GET['lng']) ? (float)$_GET['lng'] : 0;

/* ================= DOCTOR LEAVES ================= */
$doctorLeaves = [];
$res = $mysqli->query("SELECT doctor_id, leave_date FROM doctor_leaves");

while ($row = $res->fetch_assoc()) {
    $doctorLeaves[$row['doctor_id']][] = $row['leave_date'];
}

/* ================= SAVE APPOINTMENT ================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $doctor_id   = (int)$_POST['doctor_id'];
    $hospital_id = (int)$_POST['hospital_id'];
    $date        = $_POST['appt_date'];
    $time        = $_POST['appt_time'];
    $reason      = trim($_POST['reason']);

    if (empty($time)) $time = "Any Time";

    /* ❌ CHECK DOCTOR LEAVE */
    $checkLeave = $mysqli->prepare("
        SELECT 1 FROM doctor_leaves 
        WHERE doctor_id=? AND leave_date=?
    ");
    $checkLeave->bind_param("is", $doctor_id, $date);
    $checkLeave->execute();
    $checkLeave->store_result();

    if ($checkLeave->num_rows > 0) {

        $msg = "❌ Doctor is on leave. Please select another date.";

    } else {

        $stmt = $mysqli->prepare("
            INSERT INTO appointments 
            (patient_id, doctor_id, appt_date, appt_time, reason, status)
            VALUES (?, ?, ?, ?, ?, 'pending')
        ");
        $stmt->bind_param("iisss",
            $user['id'], $doctor_id, $date, $time, $reason
        );
        $stmt->execute();

        $msg = "✅ Appointment Requested Successfully!";
    }
}

/* ================= HOSPITAL QUERY ================= */
if ($lat && $lng) {

    $sql = "
    SELECT 
        id, name, city, open_time, close_time,
        TIME(NOW()) BETWEEN open_time AND close_time AS is_open,

        ROUND(
            6371 * acos(
                cos(radians(?)) *
                cos(radians(latitude)) *
                cos(radians(longitude) - radians(?)) +
                sin(radians(?)) *
                sin(radians(latitude))
            ), 2
        ) AS distance

    FROM hospitals
    WHERE LOWER(type)='hospital'
    ORDER BY distance ASC
    ";

    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param("ddd", $lat, $lng, $lat);
    $stmt->execute();
    $hospitals = $stmt->get_result();

} else {

    $hospitals = $mysqli->query("
        SELECT 
            id, name, city, open_time, close_time,
            TIME(NOW()) BETWEEN open_time AND close_time AS is_open
        FROM hospitals
        WHERE LOWER(type)='hospital'
        ORDER BY name
    ");
}

/* ================= DOCTORS ================= */
$doctors = $mysqli->query("
SELECT id, name, hospital_id 
FROM users 
WHERE role='doctor'
");
?>

<!doctype html>
<html>
<head>
<title>Book Appointment</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<link rel="stylesheet" href="assets/css/styles.css">
</head>
<body>

<div class="container">

<div class="topbar">
<div class="brand">CHR</div>
<div>
<a href="dashboard_patient.php">Dashboard</a>
<a href="logout.php">Logout</a>
</div>
</div>

<div class="card form" style="margin-top:20px;">
<div class="logo">AP</div>
<h2>Book Appointment</h2>

<?php if($msg): ?>
<div style="background:#ecfeff;color:#0369a1;padding:10px;border-radius:8px;text-align:center;">
<?= htmlspecialchars($msg) ?>
</div>
<?php endif; ?>

<form method="post">

<!-- 🔍 SEARCH -->
<label>Search Hospital (Type name or city)</label>

<input type="text" id="hospital_input" class="input" placeholder="Type hospital or city...">

<div id="hospital_list"
style="border:1px solid #ccc;border-radius:8px;max-height:200px;overflow-y:auto;display:none;background:#fff;">

<?php while($h = $hospitals->fetch_assoc()): ?>
<div class="hospital-item"
     data-id="<?= $h['id'] ?>"
     data-name="<?= strtolower($h['name']) ?>"
     data-city="<?= strtolower($h['city']) ?>"
     style="padding:8px;cursor:pointer;border-bottom:1px solid #eee;display:flex;justify-content:space-between;align-items:center;">

<div style="flex:1;">
<?= $h['name'] ?> (<?= $h['city'] ?>)

<?php if(isset($h['distance'])): ?>
 - <?= $h['distance'] ?> km
<?php endif; ?>

<br>
<small>
<?php if($h['is_open']): ?>
🟢 OPEN (<?= substr($h['open_time'],0,5) ?> - <?= substr($h['close_time'],0,5) ?>)
<?php else: ?>
🔴 CLOSED (<?= substr($h['open_time'],0,5) ?> - <?= substr($h['close_time'],0,5) ?>)
<?php endif; ?>
</small>
</div>

<a href="hospital_map.php?id=<?= $h['id'] ?>" 
   target="_blank"
   style="padding:5px 10px;background:#10b981;color:white;border-radius:4px;text-decoration:none;font-size:12px;margin-left:8px;"
   onclick="event.stopPropagation();">
📍 Map
</a>

</div>
<?php endwhile; ?>

</div>

<input type="hidden" name="hospital_id" id="hospital_id">

<!-- � VIEW HOSPITAL LOCATION -->
<button type="button" id="view_location_btn" 
        style="display:none;margin-top:10px;background:#10b981;"
        onclick="viewHospitalLocation()">
📍 View Selected Hospital Location
</button>

<!-- �👨‍⚕️ DOCTOR -->
<label>Doctor</label>
<select name="doctor_id" id="doctor_select" class="input" required>
<option value="">Select Doctor</option>

<?php while($d = $doctors->fetch_assoc()): ?>
<option 
    value="<?= $d['id'] ?>" 
    data-hospital="<?= $d['hospital_id'] ?>"
    data-leaves='<?= json_encode($doctorLeaves[$d['id']] ?? []) ?>'
>
<?= $d['name'] ?>
</option>
<?php endwhile; ?>

</select>

<label>Date</label>
<input type="date" name="appt_date" class="input" required>

<div id="leave_msg" style="color:red;font-size:13px;"></div>

<label>Time</label>
<input type="time" name="appt_time" class="input">

<textarea name="reason" class="input" rows="3" placeholder="Reason"></textarea>

<button class="btn" style="margin-top:10px;">Book Appointment</button>

</form>

<a href="dashboard_patient.php" class="link">Back</a>

</div>
</div>

<script>
/* 📍 LOCATION PERMISSION */
if (navigator.geolocation && !window.location.search.includes("lat")) {

    navigator.geolocation.getCurrentPosition(
        function(pos) {
            let lat = pos.coords.latitude;
            let lng = pos.coords.longitude;
            window.location.href = "?lat=" + lat + "&lng=" + lng;
        },
        function() {
            console.log("Location denied");
        }
    );
}

/* 🔍 SEARCH */
const input = document.getElementById("hospital_input");
const list = document.getElementById("hospital_list");
const items = document.querySelectorAll(".hospital-item");
const hidden = document.getElementById("hospital_id");

input.addEventListener("focus", () => list.style.display = "block");

input.addEventListener("keyup", function () {
    let val = this.value.toLowerCase();
    list.style.display = "block";

    items.forEach(item => {
        let name = item.dataset.name;
        let city = item.dataset.city;

        item.style.display =
            (name.includes(val) || city.includes(val)) ? "block" : "none";
    });
});

/* SELECT */
items.forEach(item => {
    item.addEventListener("click", function () {
        input.value = this.innerText.split('\n')[0]; // Get first line only
        hidden.value = this.dataset.id;
        list.style.display = "none";
        filterDoctors(this.dataset.id);
        
        // Show view location button
        document.getElementById("view_location_btn").style.display = "block";
    });
});

/* 📍 VIEW HOSPITAL LOCATION */
function viewHospitalLocation() {
    let hospitalId = document.getElementById("hospital_id").value;
    if (hospitalId) {
        window.open("hospital_map.php?id=" + hospitalId, "_blank");
    } else {
        alert("❌ Please select a hospital first!");
    }
}

/* FILTER DOCTORS */
function filterDoctors(hid) {
    let options = document.getElementById("doctor_select").options;

    for (let i = 0; i < options.length; i++) {
        let hospital = options[i].dataset.hospital;
        options[i].style.display = (!hospital || hospital == hid) ? "" : "none";
    }
}

/* ❌ LEAVE CHECK */
const doctorSelect = document.getElementById("doctor_select");
const dateInput = document.querySelector("input[name='appt_date']");
const msg = document.getElementById("leave_msg");
const btn = document.querySelector(".btn");

function checkDoctorLeave() {
    let opt = doctorSelect.options[doctorSelect.selectedIndex];
    if (!opt || !opt.dataset.leaves) return;

    let leaves = JSON.parse(opt.dataset.leaves);
    let date = dateInput.value;

    if (date && leaves.includes(date)) {
        msg.innerHTML = "❌ Doctor is on leave. Choose another date.";
        btn.disabled = true;
    } else {
        msg.innerHTML = "";
        btn.disabled = false;
    }
}

doctorSelect.addEventListener("change", checkDoctorLeave);
dateInput.addEventListener("change", checkDoctorLeave);
</script>

</body>
</html>