<?php
require 'config.php';
ensure_logged_in();
ensure_role('laboratory');

$lab_id = (int)$_SESSION['user_id'];
$appointment_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$msg = "";

if (!$appointment_id) {
    header("Location: laboratory_dashboard.php");
    exit;
}

/* FETCH APPOINTMENT */
$stmt = $mysqli->prepare("SELECT * FROM lab_appointments WHERE id=? AND lab_id=?");
$stmt->bind_param("ii", $appointment_id, $lab_id);
$stmt->execute();
$app = $stmt->get_result()->fetch_assoc();

if (!$app) {
    header("Location: laboratory_dashboard.php");
    exit;
}

/* UPLOAD REPORT */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!empty($_FILES['report']['name'])) {
        $upload_dir = __DIR__ . "/uploads/";
        if (!is_dir($upload_dir)) {
            @mkdir($upload_dir, 0777, true);
        }

        $safe_name = time() . "_" . preg_replace('/[^a-zA-Z0-9_\.-]/', '_', basename($_FILES['report']['name']));
        $target = $upload_dir . $safe_name;

        $uploaded = false;
        if (@move_uploaded_file($_FILES['report']['tmp_name'], $target)) {
            $uploaded = true;
        } else {
            $temp_dir = sys_get_temp_dir() . "/uploads/";
            if (!is_dir($temp_dir)) {
                @mkdir($temp_dir, 0777, true);
            }
            if (@move_uploaded_file($_FILES['report']['tmp_name'], $temp_dir . $safe_name)) {
                $uploaded = true;
                @copy($temp_dir . $safe_name, $target);
            }
        }

        if ($uploaded) {
            try {
                $stmt = $mysqli->prepare("
                    INSERT INTO lab_reports (appointment_id, patient_id, lab_id, report_file)
                    VALUES (?, ?, ?, ?)
                ");
                $stmt->bind_param(
                    "iiis",
                    $appointment_id,
                    $app['patient_id'],
                    $lab_id,
                    $safe_name
                );
                $stmt->execute();

                // Send notification to patient
                $notif_msg = "Your lab report for " . ($app['test_name'] ?? 'test') . " is now available.";
                $notif = $mysqli->prepare("INSERT INTO notifications (user_id, message) VALUES (?, ?)");
                $notif->bind_param("is", $app['patient_id'], $notif_msg);
                @$notif->execute();

                header("Location: laboratory_dashboard.php");
                exit;
            } catch (Throwable $e) {
                $msg = "Database error: " . $e->getMessage();
            }
        } else {
            $msg = "File upload failed. Please try again.";
        }
    } else {
        $msg = "Please choose a file to upload.";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Upload Lab Report - CHR</title>
    <link rel="stylesheet" href="assets/css/styles.css">
</head>

<body>

<div class="container">

    <div class="card">

        <div class="icon">🧪</div>

        <h2>Upload Lab Report</h2>

        <?php if(!empty($msg)): ?>
            <div class="msg error" style="margin-bottom:14px;color:#dc2626;background:#fef2f2;padding:10px;border-radius:8px;">
                ❌ <?= htmlspecialchars($msg) ?>
            </div>
        <?php endif; ?>

        <form method="post" enctype="multipart/form-data">

            <div class="file-box">
                <input type="file" name="report" required>
            </div>

            <button type="submit">Upload Report</button>

        </form>

        <div class="note">
            Supported: PDF, JPG, PNG
        </div>

        <a href="laboratory_dashboard.php">← Back to Dashboard</a>

    </div>

</div>

</body>
</html>