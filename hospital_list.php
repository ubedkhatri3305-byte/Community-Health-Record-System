<?php
require 'config.php';
ensure_logged_in();

$hospitals = $mysqli->query("SELECT * FROM hospitals ORDER BY created_at DESC");
?>
<!doctype html>
<html>
<head>
<title>Hospitals</title>
<link rel="stylesheet" href="assets/css/styles.css">
</head>
<body>

<div class="container">

<div class="topbar">
<div class="brand">CHR</div>
<div>
<a href="dashboard_admin.php">Dashboard</a>
<a href="logout.php">Logout</a>
</div>
</div>

<div class="card" style="margin-top:20px;">
<h2>Registered Hospitals</h2>

<table width="100%" cellpadding="8">
<tr>
<th>Name</th>
<th>Address</th>
<th>Latitude</th>
<th>Longitude</th>
</tr>

<?php while($h=$hospitals->fetch_assoc()): ?>
<tr>
<td><?=$h['name']?></td>
<td><?=$h['address']?></td>
<td><?=$h['latitude']?></td>
<td><?=$h['longitude']?></td>
</tr>
<?php endwhile; ?>

</table>
</div>
</div>
</body>
</html>
