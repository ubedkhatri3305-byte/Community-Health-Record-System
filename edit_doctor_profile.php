<?php
require 'config.php';
ensure_logged_in();
ensure_role('doctor');

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$doctor = current_user($mysqli);

$msg = "";
$error = "";

/* ================= SUCCESS MESSAGE ================= */
if (isset($_GET['success'])) {
    $msg = "✅ Profile updated successfully";
}

/* ================= GET PROFILE ================= */
$profile = [
    'address' => '',
    'specialization' => '',
    'qualification' => ''
];

$stmt = $mysqli->prepare("SELECT * FROM doctor_profiles WHERE doctor_id=?");
$stmt->bind_param("i", $doctor['id']);
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows > 0) {
    $profile = $res->fetch_assoc();
}

/* ================= UPDATE PROFILE ================= */
if (isset($_POST['update_profile'])) {

    try {

        $name  = trim($_POST['name']);
        $email = trim($_POST['email']);
        $phone = trim($_POST['phone']);

        $address        = trim($_POST['address']);
        $specialization = trim($_POST['specialization']);
        $qualification  = trim($_POST['qualification']);

        if (!$name || !$email) {
            throw new Exception("Name and Email are required");
        }

        /* USERS TABLE */
        $stmt = $mysqli->prepare("
            UPDATE users SET name=?, email=?, phone=? WHERE id=?
        ");
        $stmt->bind_param("sssi", $name, $email, $phone, $doctor['id']);
        $stmt->execute();

        /* CHECK PROFILE */
        $check = $mysqli->prepare("SELECT doctor_id FROM doctor_profiles WHERE doctor_id=?");
        $check->bind_param("i", $doctor['id']);
        $check->execute();
        $result = $check->get_result();

        if ($result->num_rows > 0) {

            /* UPDATE */
            $stmt2 = $mysqli->prepare("
                UPDATE doctor_profiles 
                SET address=?, specialization=?, qualification=? 
                WHERE doctor_id=?
            ");
            $stmt2->bind_param("sssi", $address, $specialization, $qualification, $doctor['id']);
            $stmt2->execute();

        } else {

            /* INSERT */
            $stmt2 = $mysqli->prepare("
                INSERT INTO doctor_profiles 
                (doctor_id, address, specialization, qualification)
                VALUES (?, ?, ?, ?)
            ");
            $stmt2->bind_param("isss", $doctor['id'], $address, $specialization, $qualification);
            $stmt2->execute();
        }

        /* 🔥 REDIRECT (IMPORTANT) */
        header("Location: edit_doctor_profile.php?success=1");
        exit;

    } catch (Exception $e) {
        $error = "❌ " . $e->getMessage();
    }
}

/* ================= CHANGE PASSWORD ================= */
if (isset($_POST['change_password'])) {

    try {

        $old = $_POST['old_password'];
        $new = $_POST['new_password'];
        $con = $_POST['confirm_password'];

        $stmt = $mysqli->prepare("SELECT password FROM users WHERE id=?");
        $stmt->bind_param("i", $doctor['id']);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();

        if (!password_verify($old, $row['password'])) {
            throw new Exception("Old password incorrect");
        }

        if ($new !== $con) {
            throw new Exception("Passwords do not match");
        }

        if (strlen($new) < 6) {
            throw new Exception("Password must be 6+ characters");
        }

        $hash = password_hash($new, PASSWORD_DEFAULT);

        $stmt = $mysqli->prepare("UPDATE users SET password=? WHERE id=?");
        $stmt->bind_param("si", $hash, $doctor['id']);
        $stmt->execute();

        $msg = "✅ Password updated";

    } catch (Exception $e) {
        $error = "❌ " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html>
<head>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Edit Profile</title>

<style>
body{
  font-family:Segoe UI;
  background:#f4f7fb;
  margin:0;
}

.container{
  max-width:600px;
  margin:auto;
  padding:15px;
}

.card{
  background:white;
  padding:20px;
  border-radius:12px;
  box-shadow:0 5px 15px rgba(0,0,0,0.08);
}

.input{
  width:100%;
  padding:12px;
  margin:8px 0;
  border:1px solid #ddd;
  border-radius:8px;
  font-size:14px;
}

.btn{
  width:100%;
  padding:12px;
  background:#2563eb;
  color:white;
  border:none;
  border-radius:8px;
  cursor:pointer;
}

.btn:hover{
  background:#1e40af;
}

.topbar{
  display:flex;
  justify-content:space-between;
  flex-wrap:wrap;
  margin-bottom:15px;
}

.msg{
  padding:10px;
  border-radius:8px;
  margin-bottom:10px;
}

.success{
  background:#d1fae5;
  color:#065f46;
}

.error{
  background:#fee2e2;
  color:#991b1b;
}
</style>
</head>

<body>

<div class="container">

<div class="topbar">
  <h3>👨‍⚕️ Doctor Profile</h3>
  <div>
    <a href="dashboard_doctor.php">Dashboard</a> |
    <a href="logout.php">Logout</a>
  </div>
</div>

<div class="card">

<h2>Edit Profile</h2>

<?php if($msg): ?>
<div class="msg success"><?= $msg ?></div>
<?php endif; ?>

<?php if($error): ?>
<div class="msg error"><?= $error ?></div>
<?php endif; ?>

<form method="post">

<input class="input" name="name" value="<?= htmlspecialchars($doctor['name']) ?>" placeholder="Full Name" required>

<input class="input" name="email" value="<?= htmlspecialchars($doctor['email']) ?>" placeholder="Email" required>

<input class="input" name="phone" value="<?= htmlspecialchars($doctor['phone'] ?? '') ?>" placeholder="Phone">

<input class="input" name="specialization" value="<?= htmlspecialchars($profile['specialization']) ?>" placeholder="Specialization">

<input class="input" name="qualification" value="<?= htmlspecialchars($profile['qualification']) ?>" placeholder="Qualification">

<textarea class="input" name="address" placeholder="Address"><?= htmlspecialchars($profile['address']) ?></textarea>

<button class="btn" name="update_profile">Update Profile</button>

</form>

<hr>

<h3>Change Password</h3>

<form method="post">

<input class="input" type="password" name="old_password" placeholder="Old Password" required>

<input class="input" type="password" name="new_password" placeholder="New Password" required>

<input class="input" type="password" name="confirm_password" placeholder="Confirm Password" required>

<button class="btn" name="change_password">Change Password</button>

</form>

</div>
</div>

</body>
</html>