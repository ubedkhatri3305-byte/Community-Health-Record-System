<?php
//session_start();
require 'config.php';

/* 🔁 IF ALREADY LOGGED IN */
if (isset($_SESSION['user_id']) && isset($_SESSION['role'])) {

    if ($_SESSION['role'] === 'doctor') {
        header("Location: dashboard_doctor.php");
        exit;
    }

    if ($_SESSION['role'] === 'patient') {
        header("Location: dashboard_patient.php");
        exit;
    }

    if ($_SESSION['role'] === 'hospital') {
        header("Location: hospital_dashboard.php");
        exit;
    }

    if ($_SESSION['role'] === 'laboratory') {
        header("Location: laboratory_dashboard.php");
        exit;
    }
}

$msg = "";

/* 🔐 LOGIN */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $role = $_POST['role'];

    if (empty($email) || empty($password) || empty($role)) {
        $msg = "❌ Please fill all fields!";
    } else {

        /* 🔴 HOSPITAL / LAB LOGIN */
        if ($role === 'hospital' || $role === 'laboratory') {

            $stmt = $mysqli->prepare("SELECT * FROM hospitals WHERE email=? AND type=?");
            $stmt->bind_param("ss", $email, $role);

        } else {

            /* 🔵 USERS TABLE */
            $stmt = $mysqli->prepare("SELECT * FROM users WHERE email=? AND role=?");
            $stmt->bind_param("ss", $email, $role);
        }

        $stmt->execute();
        $result = $stmt->get_result();

        if ($row = $result->fetch_assoc()) {

            if (password_verify($password, $row['password'])) {

                /* ✅ SESSION SET */
                $_SESSION['user_id'] = $row['id'];
                $_SESSION['role'] = $role;
                $_SESSION['name'] = $row['name'];

                /* 🔁 REDIRECT */
                if ($role === 'hospital') {
                    header("Location: hospital_dashboard.php");
                } elseif ($role === 'laboratory') {
                    header("Location: laboratory_dashboard.php");
                } elseif ($role === 'doctor') {
                    header("Location: dashboard_doctor.php");
                } else {
                    header("Location: dashboard_patient.php");
                }
                exit;

            } else {
                $msg = "❌ Wrong Password!";
            }

        } else {
            $msg = "❌ User not found!";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>CHR Login</title>
<meta name="viewport" content="width=device-width, initial-scale=1">

<link rel="stylesheet" href="assets/css/styles.css">
</head>

<body>

<div class="center" style="min-height:100vh">

<div class="card form">

<div class="logo">CHR</div>
<h2>Login</h2>

<?php if($msg): ?>
<div style="background:#fee2e2;color:#991b1b;padding:10px;border-radius:8px;text-align:center;margin-bottom:10px;">
<?= htmlspecialchars($msg) ?>
</div>
<?php endif; ?>

<form method="post">

<input class="input" type="email" name="email" placeholder="Email" required>

<input class="input" type="password" name="password" placeholder="Password" required>

<select class="input" name="role" required>
    <option value="">Login as</option>
    <option value="patient">Patient</option>
    <option value="doctor">Doctor</option>
    <option value="hospital">Hospital</option>
    <option value="laboratory">Laboratory</option>
</select>

<button class="btn" type="submit">Login</button>

</form>

<hr style="margin:14px 0">

<a class="link" href="register.php">👤 Patient Registration</a>
<a class="link" href="hospital_add.php">🏥 Hospital / Laboratory Registration</a>

</div>
</div>

</body>
</html>