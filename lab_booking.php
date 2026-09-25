<?php
require 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'patient') {
    header("Location: index.php");
    exit;
}

$patient_id = $_SESSION['user_id'];
$msg = "";

/* ================= LOCATION ================= */
$lat = $_GET['lat'] ?? 0;
$lng = $_GET['lng'] ?? 0;

/* ================= FETCH LABS ================= */
if ($lat && $lng) {

    $sql = "
    SELECT id, name, city,

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
    WHERE type='laboratory'
    ORDER BY distance ASC
    ";

    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param("ddd", $lat, $lng, $lat);

} else {

    $sql = "
    SELECT id, name, city
    FROM hospitals
    WHERE type='laboratory'
    ORDER BY name ASC
    ";

    $stmt = $mysqli->prepare($sql);
}

$stmt->execute();
$result = $stmt->get_result();

/* convert to array */
$labs = [];
while($row = $result->fetch_assoc()){
    $labs[] = $row;
}

/* ================= BOOK ================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $lab_id = $_POST['lab_id'];
    $test = trim($_POST['test_name']);
    $date = $_POST['appointment_date'];

    if (!$lab_id || !$test || !$date) {
        $msg = "❌ Please select lab and fill all fields!";
    } else {

        $stmt = $mysqli->prepare("
            INSERT INTO lab_appointments 
            (patient_id, lab_id, test_name, appointment_date)
            VALUES (?, ?, ?, ?)
        ");

        $stmt->bind_param("iiss", $patient_id, $lab_id, $test, $date);

        if ($stmt->execute()) {
            $msg = "✅ Lab appointment booked successfully!";
        } else {
            $msg = "❌ Error booking appointment!";
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="assets/css/styles.css">
<title>Lab Booking</title>

<style>
.dropdown-list{
    border:1px solid #ddd;
    max-height:220px;
    overflow-y:auto;
    background:white;
    position:absolute;
    width:100%;
    z-index:1000;
    border-radius:8px;
}
.dropdown-item{
    padding:10px;
    cursor:pointer;
    border-bottom:1px solid #eee;
}
.dropdown-item:hover{
    background:#f1f5f9;
}
.loc-btn{
    margin-bottom:10px;
    background:#10b981;
}
</style>

</head>
<body>

<div class="center" style="min-height:100vh">
<div class="card form">

<div class="logo">🧪 LAB</div>
<h2>Book Lab Test</h2>

<?php if($msg): ?>
<div style="background:#ecfdf5;color:#065f46;padding:10px;border-radius:8px;text-align:center;margin-bottom:10px;">
<?= htmlspecialchars($msg) ?>
</div>
<?php endif; ?>

<!-- 📍 LOCATION BUTTON -->
<button type="button" class="btn loc-btn" onclick="getLocation()">
📍 Use My Location
</button>

<form method="post">

<!-- 🔍 SEARCH BOX -->
<div style="position:relative;">
<input type="text" id="lab_search" class="input"
placeholder="Search Laboratory or City"
onclick="showAllLabs()">

<div id="dropdown" class="dropdown-list" style="display:none;"></div>
</div>

<input type="hidden" name="lab_id" id="lab_id">

<input class="input" name="test_name" placeholder="Test Name" required>
<input class="input" type="date" name="appointment_date" required>

<button class="btn">Book Appointment</button>

</form>

<hr>
<a class="link" href="dashboard_patient.php">⬅ Back to Dashboard</a>

</div>
</div>

<script>
let labs = <?= json_encode($labs) ?>;

let input = document.getElementById("lab_search");
let dropdown = document.getElementById("dropdown");

/* 🔥 SHOW ALL LABS WHEN CLICK */
function showAllLabs(){
    renderLabs(labs);
}

/* 🔍 SEARCH FILTER */
input.addEventListener("keyup", function(){

    let val = this.value.toLowerCase();

    let filtered = labs.filter(l =>
        l.name.toLowerCase().includes(val) ||
        (l.city && l.city.toLowerCase().includes(val))
    );

    renderLabs(filtered);
});

/* 🔄 RENDER DROPDOWN */
function renderLabs(list){

    dropdown.innerHTML = "";

    if(list.length === 0){
        dropdown.style.display = "none";
        return;
    }

    dropdown.style.display = "block";

    list.forEach(lab => {

        let div = document.createElement("div");
        div.classList.add("dropdown-item");

        div.innerHTML = `
            <b>${lab.name}</b><br>
            <small>📍 ${lab.city ?? 'N/A'}</small>
            ${lab.distance ? `<br><small>📏 ${lab.distance} km</small>` : ''}
        `;

        div.onclick = function(){
            input.value = lab.name + " (" + (lab.city ?? '') + ")";
            document.getElementById("lab_id").value = lab.id;
            dropdown.style.display = "none";
        };

        dropdown.appendChild(div);
    });
}

/* CLOSE DROPDOWN */
document.addEventListener("click", function(e){
    if(!input.contains(e.target)){
        dropdown.style.display = "none";
    }
});

/* 📍 LOCATION */
function getLocation(){

    if (!navigator.geolocation) {
        alert("Geolocation not supported");
        return;
    }

    navigator.geolocation.getCurrentPosition(
        function(pos){

            let lat = pos.coords.latitude;
            let lng = pos.coords.longitude;

            let url = new URL(window.location.href);
            url.searchParams.set("lat", lat);
            url.searchParams.set("lng", lng);

            window.location.href = url.toString();
        },
        function(){
            alert("❌ Location permission denied");
        }
    );
}
</script>

</body>
</html>