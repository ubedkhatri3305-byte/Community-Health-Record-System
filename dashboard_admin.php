<?php
require 'config.php';
ensure_logged_in();
ensure_role('admin');

$admin = current_user($mysqli);

// Fetch hospitals
$hospitals = $mysqli->query("
    SELECT id, name, address, latitude, longitude, created_at
    FROM hospitals
    ORDER BY created_at DESC
");
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Admin Dashboard</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<link rel="stylesheet" href="assets/css/styles.css">
</head>
<body>

<div class="container">

<div class="topbar">
  <div class="brand">CHR Admin</div>
  <div>
    <span style="margin-right:12px;">
      <?=htmlspecialchars($admin['name'])?>
    </span>
    <a href="logout.php">Logout</a>
  </div>
</div>

<div style="margin-top:20px;display:flex;gap:16px;flex-wrap:wrap;">

<!-- ADMIN PROFILE -->
<div style="flex:1;min-width:260px;">
  <div class="card">
    <h3 class="header-title">Admin Profile</h3>
    <p><strong>Email:</strong> <?=htmlspecialchars($admin['email'])?></p>
    <p><strong>Role:</strong> ADMIN</p>
  </div>

  <div class="card" style="margin-top:14px;">
    <h3 class="header-title">Actions</h3>
    <div class="grid">
      <div class="card-small">
        <a href="hospital_add.php" style="text-decoration:none;color:inherit;">
          ➕<br><h3>Add Hospital</h3>
        </a>
      </div>
    </div>
  </div>
</div>

<!-- HOSPITAL LIST -->
<div style="flex:2;min-width:340px;">
  <div class="card">
    <h3 class="header-title">Registered Hospitals</h3>

    <div class="table" style="margin-top:12px;">
      <?php if($hospitals->num_rows === 0): ?>
        <div class="row">No hospitals registered yet.</div>
      <?php else: ?>
        <?php while($h = $hospitals->fetch_assoc()): ?>
          <div class="row">
            <div class="col-1">
              <strong><?=htmlspecialchars($h['name'])?></strong>
              <div style="font-size:12px;color:var(--muted);margin-top:4px;">
                <?=htmlspecialchars($h['address'])?>
              </div>
            </div>
            <div class="col-2">
              <?=date("d M Y", strtotime($h['created_at']))?>
            </div>
          </div>
        <?php endwhile; ?>
      <?php endif; ?>
    </div>

  </div>
</div>

</div>

</div>

</body>
</html>
