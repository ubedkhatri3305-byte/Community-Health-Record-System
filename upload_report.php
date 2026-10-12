<?php

//ssion_start();
require 'config.php';

$lab_id = $_SESSION['user_id'];
$appointment_id = $_GET['id'];

/* FETCH APPOINTMENT */
$stmt = $mysqli->prepare("SELECT * FROM lab_appointments WHERE id=? AND lab_id=?");
$stmt->bind_param("ii", $appointment_id, $lab_id);
$stmt->execute();
$app = $stmt->get_result()->fetch_assoc();

/* UPLOAD REPORT */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $file = $_FILES['report']['name'];
    $tmp  = $_FILES['report']['tmp_name'];

    if (!is_dir("uploads")) {
        mkdir("uploads", 0777, true);
    }

    move_uploaded_file($tmp, "uploads/" . $file);

    $stmt = $mysqli->prepare("
        INSERT INTO lab_reports (appointment_id, patient_id, lab_id, report_file)
        VALUES (?, ?, ?, ?)
    ");

    $stmt->bind_param(
        "iiis",
        $appointment_id,
        $app['patient_id'],
        $lab_id,
        $file
    );

    $stmt->execute();

    header("Location: laboratory_dashboard.php");
    exit;
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