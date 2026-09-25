<?php
require 'config.php';
ensure_logged_in();

$id = (int)$_GET['id'];
$stmt = $mysqli->prepare("SELECT * FROM records WHERE id=? AND user_id=?");
$stmt->bind_param("ii", $id, $_SESSION['user_id']);
$stmt->execute();
$record = $stmt->get_result()->fetch_assoc();

if (!$record) {
    header("Location: dashboard.php");
    exit;
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>View Record</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link rel="stylesheet" href="assets/css/styles.css">
</head>

<body>

<div class="container">

  <div class="topbar">
    <div class="brand">CHR</div>
    <div>
      <a href="dashboard.php">Dashboard</a>
      <a href="logout.php">Logout</a>
    </div>
  </div>

  <div class="card" style="margin-top:20px;">

    <h2 style="text-align:left;">Record Details</h2>

    <p><strong>Title:</strong> <?=htmlspecialchars($record['title'])?></p>
    <p><strong>Date:</strong> <?=htmlspecialchars($record['record_date'])?></p>
    <p><strong>Description:</strong><br><?=nl2br(htmlspecialchars($record['description']))?></p>

    <?php if($record['file_path']): ?>
      <p><strong>Attachment:</strong><br>
      <a style="color:var(--primary);" href="<?=$record['file_path']?>" target="_blank">Open File</a></p>
    <?php endif; ?>

    <a href="dashboard.php" class="btn" style="margin-top:12px;display:block;">Back</a>

  </div>

</div>

</body>
</html>
