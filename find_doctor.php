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
        COALESCE(d.specialization, u.specialization, 'General Practitioner') AS specialization,
        h.id AS hospital_id,
        COALESCE(h.name, 'Independent Practice') AS hospital_name,
        COALESCE(h.city, u.location, 'Available') AS city,

        ROUND(
            6371 * acos(
                cos(radians(?)) *
                cos(radians(COALESCE(h.latitude, 0))) *
                cos(radians(COALESCE(h.longitude, 0)) - radians(?)) +
                sin(radians(?)) *
                sin(radians(COALESCE(h.latitude, 0)))
            ), 2
        ) AS distance

    FROM users u
    LEFT JOIN doctor_profiles d ON u.id = d.doctor_id
    LEFT JOIN hospitals h ON u.hospital_id = h.id

    WHERE u.role='doctor'

    AND (
        (? = '' OR u.name LIKE ? OR d.specialization LIKE ? OR u.specialization LIKE ?)
    )

    AND (
        (? = '' OR h.name LIKE ? OR h.city LIKE ? OR u.location LIKE ?)
    )

    ORDER BY distance ASC
    ";

    $stmt = $mysqli->prepare($sql);

    $stmt->bind_param(
        "dddssssssss",
        $lat, $lng, $lat,
        $d, $search, $search, $search,
        $l, $loc, $loc, $loc
    );

} else {

    $sql = "
    SELECT 
        u.*, 
        COALESCE(d.specialization, u.specialization, 'General Practitioner') AS specialization,
        h.id AS hospital_id,
        COALESCE(h.name, 'Independent Practice') AS hospital_name,
        COALESCE(h.city, u.location, 'Available') AS city

    FROM users u
    LEFT JOIN doctor_profiles d ON u.id = d.doctor_id
    LEFT JOIN hospitals h ON u.hospital_id = h.id

    WHERE u.role='doctor'

    AND (
        (? = '' OR u.name LIKE ? OR d.specialization LIKE ? OR u.specialization LIKE ?)
    )

    AND (
        (? = '' OR h.name LIKE ? OR h.city LIKE ? OR u.location LIKE ?)
    )

    ORDER BY u.name
    ";

    $stmt = $mysqli->prepare($sql);

    $stmt->bind_param(
        "ssssssss",
        $d, $search, $search, $search,
        $l, $loc, $loc, $loc
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

<b><?= htmlspecialchars($r['name'] ?? '') ?></b><br>

<?= htmlspecialchars($r['specialization'] ?? 'Doctor') ?><br>

🏥 <?= htmlspecialchars($r['hospital_name'] ?? 'General Practice') ?><br>

📍 <?= htmlspecialchars($r['city'] ?? 'Available') ?><br>

<?php if(isset($r['distance'])): ?>
📏 <?= htmlspecialchars((string)$r['distance']) ?> km<br>
<?php endif; ?>

<div style="display:flex;gap:8px;margin-top:8px;flex-wrap:wrap;">
<a class="btn" style="flex:1;min-width:140px;"
href="appointments.php?doctor=<?= (int)$r['id'] ?>">
Book Appointment
</a>

<?php if(!empty($r['hospital_id'])): ?>
<a class="btn" style="flex:1;min-width:140px;background:#10b981;"
href="hospital_map.php?id=<?= (int)$r['hospital_id'] ?>">
📍 View Location
</a>
<?php endif; ?>
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