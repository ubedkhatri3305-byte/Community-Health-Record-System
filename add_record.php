<?php
require 'config.php';
ensure_logged_in();
$user = current_user($mysqli);

$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $record_date = !empty($_POST['record_date']) ? $_POST['record_date'] : date('Y-m-d');

    $file_path = NULL;

    // Upload file
    if (!empty($_FILES['report']['name'])) {
        $upload_dir = "uploads/";
        if (!is_dir($upload_dir)) {
            @mkdir($upload_dir, 0777, true);
        }

        $safe_name = time() . "_" . preg_replace('/[^a-zA-Z0-9_\.-]/', '_', basename($_FILES['report']['name']));
        $target = $upload_dir . $safe_name;

        if (@move_uploaded_file($_FILES['report']['tmp_name'], $target)) {
            $file_path = $target;
        } else {
            $temp_dir = sys_get_temp_dir() . "/uploads/";
            if (!is_dir($temp_dir)) {
                @mkdir($temp_dir, 0777, true);
            }
            if (@move_uploaded_file($_FILES['report']['tmp_name'], $temp_dir . $safe_name)) {
                $file_path = "uploads/" . $safe_name;
            } else {
                $error = "File upload failed!";
            }
        }
    }

    // Save record
    if ($error === "") {
        try {
            $file_bytes = null;
            $file_mime = !empty($_FILES['report']['type']) ? $_FILES['report']['type'] : 'application/octet-stream';
            if ($file_path) {
                $checkPaths = [
                    __DIR__ . '/' . $file_path,
                    $file_path,
                    sys_get_temp_dir() . '/' . basename($file_path),
                    sys_get_temp_dir() . '/uploads/' . basename($file_path)
                ];
                foreach ($checkPaths as $cp) {
                    if (file_exists($cp) && is_file($cp)) {
                        $file_bytes = @file_get_contents($cp);
                        break;
                    }
                }
            }

            $hasBlobCol = false;
            $checkCol = $mysqli->query("SHOW COLUMNS FROM `records` LIKE 'file_data'");
            if ($checkCol && $checkCol->num_rows > 0) {
                $hasBlobCol = true;
            }

            if ($hasBlobCol && $file_bytes !== null && strlen($file_bytes) > 0) {
                $stmt = $mysqli->prepare("
                    INSERT INTO records
                    (user_id, title, description, record_date, file_path, file_data, file_type)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                $null = NULL;
                $stmt->bind_param(
                    "issssbs",
                    $user['id'],
                    $title,
                    $description,
                    $record_date,
                    $file_path,
                    $null,
                    $file_mime
                );
                $stmt->send_long_data(5, $file_bytes);
                $res = $stmt->execute();
            } else {
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
                $res = $stmt->execute();
            }

            if ($res) {
                header("Location: dashboard_patient.php");
                exit;
            } else {
                $error = "Record not saved!";
            }
        } catch (Throwable $e) {
            $error = "Database error: " . $e->getMessage();
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
