<?php
require 'config.php';
ensure_logged_in();

$user_id = $_SESSION['user_id'];

// Fetch notifications for the logged-in user (newest first)
$stmt = $mysqli->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$n = $stmt->get_result();
?>
<!DOCTYPE html>
<html>
<head>
<link rel="stylesheet" href="assets/css/styles.css">
<title>Notifications</title>
</head>
<body>
<div class="container">
  <h2>Notifications</h2>

  <?php if($n->num_rows === 0): ?>
    <p>No notifications yet.</p>
  <?php else: ?>
    <?php while($r = $n->fetch_assoc()): ?>
      <div class="card"><?= htmlspecialchars($r['message']) ?></div>
    <?php endwhile; ?>
  <?php endif; ?>

</div>
</body>
</html>
