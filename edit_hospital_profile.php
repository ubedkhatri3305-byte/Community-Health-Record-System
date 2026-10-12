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

$msg = "";

/* 🔥 UPDATE PROFILE */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $name      = trim($_POST['name'] ?? '');
    $email     = trim($_POST['email'] ?? '');
    $phone     = trim($_POST['phone'] ?? '');
    $address   = trim($_POST['address'] ?? '');

    $open_time  = !empty($_POST['open_time']) ? date('H:i:s', strtotime($_POST['open_time'])) : '09:00:00';
    $close_time = !empty($_POST['close_time']) ? date('H:i:s', strtotime($_POST['close_time'])) : '18:00:00';

    try {
        // Check if email taken by another hospital
        $chk = $mysqli->prepare("SELECT id FROM hospitals WHERE email=? AND id != ? LIMIT 1");
        $chk->bind_param("si", $email, $hospital_id);
        $chk->execute();
        if ($chk->get_result()->num_rows > 0) {
            $msg = "❌ This email is already registered to another hospital.";
        } else {
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
    } catch (Throwable $e) {
        if ($e->getCode() == 1062 || str_contains($e->getMessage(), 'Duplicate entry')) {
            $msg = "❌ This email is already registered to another hospital.";
        } else {
            $msg = "❌ Error updating profile: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Edit Hospital Profile - CHR</title>
    <link rel="stylesheet" href="assets/css/styles.css">

    <style>
        .time-box {
            display: flex;
            gap: 12px;
        }
        .label {
            font-size: 13px;
            color: var(--text-muted);
            margin: 8px 0 4px 0;
            display: block;
            font-weight: 500;
        }
    </style>
</head>

<body class="center" style="min-height:100vh;">

<div class="card">

    <h2>🏥 Edit Hospital Profile</h2>

    <?php if(!empty($msg)): ?>
        <div class="msg error" style="margin-bottom:14px;color:#dc2626;background:#fef2f2;padding:10px;border-radius:8px;">
            <?= htmlspecialchars($msg) ?>
        </div>
    <?php endif; ?>

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