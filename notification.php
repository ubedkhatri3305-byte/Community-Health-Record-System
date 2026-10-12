<?php
require 'config.php';
ensure_logged_in();

$user_id = (int)$_SESSION['user_id'];
$role = $_SESSION['role'] ?? 'patient';
$dashboard = ($role === 'doctor') ? 'dashboard_doctor.php' : (($role === 'hospital') ? 'hospital_dashboard.php' : (($role === 'laboratory') ? 'laboratory_dashboard.php' : (($role === 'admin') ? 'dashboard_admin.php' : 'dashboard_patient.php')));

// Mark notifications as seen
@$mysqli->query("UPDATE notifications SET seen = 1 WHERE user_id = $user_id");

// Fetch notifications for the logged-in user (newest first)
$stmt = $mysqli->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$n = $stmt->get_result();
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="assets/css/styles.css">
<title>Notifications - CHR</title>
</head>
<body>
<div class="container" style="max-width:800px;margin:auto;padding:20px;">
  <div class="topbar">
    <div class="brand">Notifications</div>
    <div>
      <a href="<?= $dashboard ?>">⬅ Back to Dashboard</a>
      <a href="logout.php">Logout</a>
    </div>
  </div>

  <div style="margin-top:20px;">
    <?php if($n->num_rows === 0): ?>
      <div class="card"><p style="color:var(--text-muted);margin:0;">No notifications yet.</p></div>
    <?php else: ?>
      <?php while($r = $n->fetch_assoc()): ?>
        <div class="card" style="margin-bottom:12px;border-left:4px solid var(--primary);">
          <div style="font-size:14px;"><?= htmlspecialchars($r['message'] ?? '') ?></div>
          <small style="color:var(--text-muted);font-size:11px;margin-top:4px;display:block;"><?= htmlspecialchars($r['created_at'] ?? '') ?></small>
        </div>
      <?php endwhile; ?>
    <?php endif; ?>
  </div>

</div>
</body>
</html>
