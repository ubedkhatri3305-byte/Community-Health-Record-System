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
    <title>Upload Lab Report</title>

    <!-- ===== INTERNAL CSS START ===== -->
    <style>
        body{
            font-family: Arial, sans-serif;
            background: #f5f7fa;
            margin: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }

        .container{
            width: 100%;
            padding: 20px;
        }

        .card{
            max-width: 420px;
            margin: auto;
            padding: 26px;
            border-radius: 16px;
            background: #fff;
            box-shadow: 0 10px 25px rgba(0,0,0,0.08);
            text-align: center;
        }

        .icon{
            font-size: 40px;
            margin-bottom: 10px;
        }

        h2{
            color: #1976d2;
            margin-bottom: 20px;
        }

        .file-box{
            border: 2px dashed #cfe3ff;
            padding: 18px;
            border-radius: 12px;
            background: #f8fbff;
            margin-bottom: 15px;
            transition: 0.3s;
        }

        .file-box:hover{
            border-color: #1976d2;
            background: #eef6ff;
        }

        input[type="file"]{
            width: 100%;
            cursor: pointer;
        }

        button{
            width: 100%;
            padding: 12px;
            border: none;
            border-radius: 10px;
            background: #1976d2;
            color: white;
            font-weight: bold;
            cursor: pointer;
            transition: 0.3s;
        }

        button:hover{
            background: #125ca1;
            transform: translateY(-2px);
        }

        .note{
            font-size: 12px;
            color: #6b7280;
            margin-top: 10px;
        }

        a{
            display: block;
            margin-top: 15px;
            color: #1976d2;
            text-decoration: none;
            font-size: 14px;
        }
    </style>
    <!-- ===== INTERNAL CSS END ===== -->

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