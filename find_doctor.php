<?php
require 'config.php';

$d = $_GET['disease'] ?? '';
$l = $_GET['location'] ?? '';

$lat = isset($_GET['lat']) ? (float)$_GET['lat'] : 0;
$lng = isset($_GET['lng']) ? (float)$_GET['lng'] : 0;

$search = "%$d%";
$loc    = "%$l%";

/* ================= QUERY ================= */
if ($lat && $lng) {

    $sql = "
    SELECT 
        u.*, 
        d.specialization,
        h.id AS hospital_id,
        h.name AS hospital_name,
        h.city,

        ROUND(
            6371 * acos(
                cos(radians(?)) *
                cos(radians(h.latitude)) *
                cos(radians(h.longitude) - radians(?)) +
                sin(radians(?)) *
                sin(radians(h.latitude))
            ), 2
        ) AS distance

    FROM users u
    JOIN doctor_profiles d ON u.id = d.doctor_id
    JOIN hospitals h ON u.hospital_id = h.id

    WHERE u.role='doctor'

    AND (
        (? = '' OR u.name LIKE ? OR d.specialization LIKE ?)
    )

    AND (
        (? = '' OR h.name LIKE ? OR h.city LIKE ?)
    )

    ORDER BY distance ASC
    ";

    $stmt = $mysqli->prepare($sql);

    $stmt->bind_param(
        "dddssssss",
        $lat, $lng, $lat,
        $d, $search, $search,
        $l, $loc, $loc
    );

} else {

    $sql = "
    SELECT 
        u.*, 
        d.specialization,
        h.id AS hospital_id,
        h.name AS hospital_name,
        h.city

    FROM users u
    JOIN doctor_profiles d ON u.id = d.doctor_id
    JOIN hospitals h ON u.hospital_id = h.id

    WHERE u.role='doctor'

    AND (
        (? = '' OR u.name LIKE ? OR d.specialization LIKE ?)
    )

    AND (
        (? = '' OR h.name LIKE ? OR h.city LIKE ?)
    )

    ORDER BY u.name
    ";

    $stmt = $mysqli->prepare($sql);

    $stmt->bind_param(
        "ssssss",
        $d, $search, $search,
        $l, $loc, $loc
    );
}

$stmt->execute();
$q = $stmt->get_result();
?>

<!DOCTYPE html>
<html>
<head>
<link rel="stylesheet" href="assets/css/styles.css">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Search Doctor</title>
</head>

<body>

<div class="container">

<!-- 🔝 HEADER -->
<div class="topbar">
  <div class="brand">🔍 Search a Nearby Doctor</div>
  <div>
    <a href="dashboard_patient.php">⬅ Back to Dashboard</a>
    <a href="logout.php">Logout</a>
  </div>
</div>

<!-- 🔍 SEARCH FORM -->
<form method="get" class="card" style="margin-bottom:15px;">

<input class="input" name="disease"
placeholder="Doctor / Disease / Specialization"
value="<?= htmlspecialchars($d) ?>">

<input class="input" name="location"
placeholder="Hospital / City"
value="<?= htmlspecialchars($l) ?>">

<button class="btn">Search</button>

</form>

<!-- RESULTS -->
<?php if($q->num_rows > 0): ?>

<?php while($r = $q->fetch_assoc()): ?>

<div class="card" style="margin-bottom:10px;">

<b><?= htmlspecialchars($r['name']) ?></b><br>

<?= htmlspecialchars($r['specialization']) ?><br>

🏥 <?= htmlspecialchars($r['hospital_name']) ?><br>

📍 <?= htmlspecialchars($r['city']) ?><br>

<?php if(isset($r['distance'])): ?>
📏 <?= $r['distance'] ?> km<br>
<?php endif; ?>

<div style="display:flex;gap:8px;margin-top:8px;flex-wrap:wrap;">
<a class="btn" style="flex:1;min-width:140px;"
href="appointments.php?doctor=<?= $r['id'] ?>">
Book Appointment
</a>

<a class="btn" style="flex:1;min-width:140px;background:#10b981;"
href="hospital_map.php?id=<?= $r['hospital_id'] ?>">
📍 View Location
</a>
</div>

</div>

<?php endwhile; ?>

<?php else: ?>

<div class="card">❌ No doctors found</div>

<?php endif; ?>

</div>

<!-- 📍 LOCATION PERMISSION -->
<script>
if (navigator.geolocation && !window.location.search.includes("lat")) {

    navigator.geolocation.getCurrentPosition(
        function(pos) {

            let lat = pos.coords.latitude;
            let lng = pos.coords.longitude;

            let url = new URL(window.location.href);
            url.searchParams.set("lat", lat);
            url.searchParams.set("lng", lng);

            window.location.href = url.toString();
        },
        function() {
            console.log("Location denied");
        }
    );
}
</script>

</body>
</html>