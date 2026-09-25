<?php
//session_start();
require 'config.php';

/* 🔒 SECURITY */
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'hospital') {
    header("Location: index.php");
    exit;
}

$hospital_id = $_SESSION['user_id'];

/* 🔥 FETCH HOSPITAL DATA */
$stmt = $mysqli->prepare("SELECT * FROM hospitals WHERE id=? AND type='hospital'");
$stmt->bind_param("i", $hospital_id);
$stmt->execute();

$result = $stmt->get_result();
$data = $result->fetch_assoc();

/* ❗ IF NOT FOUND */
if (!$data) {
    die("Hospital not found in hospitals table.");
}

/* 🔥 UPDATE PROFILE */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $name      = $_POST['name'] ?? '';
    $email     = $_POST['email'] ?? '';
    $phone     = $_POST['phone'] ?? '';
    $address   = $_POST['address'] ?? '';
    $open_time = $_POST['open_time'] ?? null;
    $close_time = $_POST['close_time'] ?? null;

    $stmt = $mysqli->prepare("
        UPDATE hospitals 
        SET name=?, email=?, phone=?, address=?, open_time=?, close_time=?
        WHERE id=? AND type='hospital'
    ");

    $stmt->bind_param(
        "ssssssi",
        $name,
        $email,
        $phone,
        $address,
        $open_time,
        $close_time,
        $hospital_id
    );

    $stmt->execute();

    header("Location: hospital_dashboard.php");
    exit;
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Edit Hospital Profile</title>

    <style>
        body{
            font-family: Arial;
            background:#f5f7fa;
            display:flex;
            justify-content:center;
            align-items:center;
            min-height:100vh;
        }

        .card{
            width:430px;
            background:#fff;
            padding:25px;
            border-radius:14px;
            box-shadow:0 10px 25px rgba(0,0,0,0.08);
        }

        h2{
            text-align:center;
            color:#1976d2;
        }

        input{
            width:100%;
            padding:10px;
            margin:6px 0;
            border:1px solid #ddd;
            border-radius:8px;
        }

        .time-box{
            display:flex;
            gap:10px;
        }

        button{
            width:100%;
            padding:12px;
            background:#1976d2;
            color:#fff;
            border:none;
            border-radius:8px;
            cursor:pointer;
            font-weight:bold;
        }

        button:hover{
            background:#125ca1;
        }

        a{
            display:block;
            text-align:center;
            margin-top:10px;
            color:#1976d2;
            text-decoration:none;
        }

        .label{
            font-size:12px;
            color:#6b7280;
            margin-top:8px;
        }
    </style>

</head>

<body>

<div class="card">

    <h2>🏥 Edit Hospital Profile</h2>

    <form method="post">

        <input type="text" name="name"
               value="<?= htmlspecialchars($data['name'] ?? '') ?>"
               placeholder="Hospital Name" required>

        <input type="email" name="email"
               value="<?= htmlspecialchars($data['email'] ?? '') ?>"
               placeholder="Email" required>

        <input type="text" name="phone"
               value="<?= htmlspecialchars($data['phone'] ?? '') ?>"
               placeholder="Phone">

        <input type="text" name="address"
               value="<?= htmlspecialchars($data['address'] ?? '') ?>"
               placeholder="Address">

        <div class="label">Hospital Working Time</div>

        <div class="time-box">

            <input type="time" name="open_time"
                   value="<?= htmlspecialchars($data['open_time'] ?? '') ?>">

            <input type="time" name="close_time"
                   value="<?= htmlspecialchars($data['close_time'] ?? '') ?>">

        </div>

        <button type="submit">Update Profile</button>

    </form>

    <a href="hospital_dashboard.php">← Back</a>

</div>

</body>
</html>