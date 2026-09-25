<?php
require 'config.php';
ensure_logged_in();
$user = current_user($mysqli);

$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $record_date = $_POST['record_date'];

    $file_path = NULL;

    // Upload file
    if (!empty($_FILES['report']['name'])) {

        $upload_dir = "uploads/";
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        $filename = time() . "_" . basename($_FILES['report']['name']);
        $target = $upload_dir . $filename;

        if (move_uploaded_file($_FILES['report']['tmp_name'], $target)) {
            $file_path = $target;
        } else {
            $error = "File upload failed!";
        }
    }

    // Save record
    if ($error === "") {
        $stmt = $mysqli->prepare("
            INSERT INTO records
            (user_id, title, description, record_date, file_path)
            VALUES (?, ?, ?, ?, ?)
        ");

        $stmt->bind_param(
            "issss",
            $user['id'],
            $title,
            $description,
            $record_date,
            $file_path
        );

        if ($stmt->execute()) {
            header("Location: dashboard_patient.php");
            exit;
        } else {
            $error = "Record not saved!";
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Add Health Record</title>
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

    <div class="logo">+</div>
    <h2>Add Health Record</h2>

    <?php if(!empty($error)): ?>
      <div style="background:#fdecea;color:#b91c1c;padding:10px;border-radius:8px;margin-bottom:10px;text-align:center;">
        <?=htmlspecialchars($error)?>
      </div>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data">

      <input type="text" name="title" class="input" placeholder="Record Title" required>

      <textarea name="description" class="input" rows="3" placeholder="Description"></textarea>

      <label style="font-size:14px;">Record Date</label>
      <input type="date" name="record_date" class="input">

      <label style="font-size:14px;">Upload Report (PDF/JPG/PNG)</label>
      <input type="file" name="report" class="input" accept=".pdf,.jpg,.jpeg,.png">

      <button class="btn" style="margin-top:10px;">Save Record</button>

    </form>

    <a href="dashboard_patient.php" class="link">Back to Dashboard</a>
  </div>

</div>

</body>
</html>
