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
<title>Login - Community Health Record System</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="assets/css/styles.css">
</head>

<body>

<div class="center" style="min-height:100vh;padding:20px 0;">

<div class="card form">

<div class="logo">🏥</div>
<h2 style="margin-bottom:4px;">CHR Portal</h2>
<p style="text-align:center;color:var(--text-muted);font-size:13px;margin-bottom:22px;">Community Health Record System</p>

<?php if($msg): ?>
<div class="msg error" style="margin-bottom:16px;">
    <?= htmlspecialchars($msg) ?>
</div>
<?php endif; ?>

<form method="post">

<label style="font-size:13px;font-weight:600;color:var(--text-secondary);display:block;margin-bottom:5px;">Email Address</label>
<input class="input" type="email" name="email" placeholder="name@example.com" required>

<label style="font-size:13px;font-weight:600;color:var(--text-secondary);display:block;margin-bottom:5px;">Password</label>
<input class="input" type="password" name="password" placeholder="••••••••" required>

<label style="font-size:13px;font-weight:600;color:var(--text-secondary);display:block;margin-bottom:5px;">Account Role</label>
<select class="input" name="role" required>
    <option value="">Select your role...</option>
    <option value="patient">👤 Patient</option>
    <option value="doctor">🩺 Doctor</option>
    <option value="hospital">🏥 Hospital</option>
    <option value="laboratory">🔬 Laboratory</option>
</select>

<button class="btn" type="submit" style="margin-top:8px;">Sign In to Portal</button>

</form>

<div style="border-top:1px solid var(--card-border);margin:20px 0 16px 0;"></div>

<div style="display:flex;flex-direction:column;gap:8px;">
    <a class="link" href="register.php" style="margin-top:0;">👤 New Patient? Create Account &rarr;</a>
    <a class="link" href="hospital_add.php" style="margin-top:0;">🏥 Register Hospital or Laboratory &rarr;</a>
</div>

</div>
</div>

</body>
</html>