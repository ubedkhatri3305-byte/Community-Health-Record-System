<?php
require 'config.php';
ensure_logged_in();
ensure_role('patient');   // 🔐 doctor blocked

$patient = current_user($mysqli);
$msg = "";
$error = "";

/* ================= UPDATE PROFILE ================= */
if (isset($_POST['update_profile'])) {

    $name    = trim($_POST['name']);
    $email   = trim($_POST['email']);
    $phone   = trim($_POST['phone']);
    $address = trim($_POST['address']);

    if ($name && $email) {
        $stmt = $mysqli->prepare("
            UPDATE users 
            SET name=?, email=?, phone=?, address=? 
            WHERE id=?
        ");
        $stmt->bind_param(
            "ssssi",
            $name,
            $email,
            $phone,
            $address,
            $patient['id']
        );
        $stmt->execute();

        $msg = "Profile updated successfully";
        $patient = current_user($mysqli); // refresh data
    } else {
        $error = "Name and Email are required";
    }
}

/* ================= CHANGE PASSWORD ================= */
if (isset($_POST['change_password'])) {

    $old_pass = $_POST['old_password'];
    $new_pass = $_POST['new_password'];
    $confirm  = $_POST['confirm_password'];

    // Fetch current password hash
    $stmt = $mysqli->prepare("SELECT password FROM users WHERE id=?");
    $stmt->bind_param("i", $patient['id']);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();

    if (!password_verify($old_pass, $row['password'])) {
        $error = "Old password is incorrect";
    } elseif ($new_pass !== $confirm) {
        $error = "New passwords do not match";
    } elseif (strlen($new_pass) < 6) {
        $error = "Password must be at least 6 characters";
    } else {
        $hash = password_hash($new_pass, PASSWORD_DEFAULT);
        $stmt = $mysqli->prepare("UPDATE users SET password=? WHERE id=?");
        $stmt->bind_param("si", $hash, $patient['id']);
        $stmt->execute();

        $msg = "Password changed successfully";
    }
}
?>
<!DOCTYPE html>
<html>
<head>
<link rel="stylesheet" href="assets/css/styles.css">
<title>Edit Patient Profile</title>
</head>
<body>

<div class="container">

  <div class="topbar">
    <div class="brand">Patient</div>
    <div>
      <a href="dashboard_patient.php">Dashboard</a>
      <a href="logout.php">Logout</a>
    </div>
  </div>

  <div class="card form" style="margin-top:20px;">

    <h2>Edit Profile</h2>

    <?php if($msg): ?>
      <div style="background:#d1fae5;color:#065f46;padding:10px;border-radius:6px;margin-bottom:10px;">
        <?= htmlspecialchars($msg) ?>
      </div>
    <?php endif; ?>

    <?php if($error): ?>
      <div style="background:#fee2e2;color:#991b1b;padding:10px;border-radius:6px;margin-bottom:10px;">
        <?= htmlspecialchars($error) ?>
      </div>
    <?php endif; ?>

    <!-- UPDATE PROFILE -->
    <form method="post">
      <input class="input" name="name" placeholder="Full Name"
             value="<?= htmlspecialchars($patient['name']) ?>" required>

      <input class="input" name="email" type="email" placeholder="Email"
             value="<?= htmlspecialchars($patient['email']) ?>" required>

      <input class="input" name="phone" placeholder="Phone"
             value="<?= htmlspecialchars($patient['phone'] ?? '') ?>">

      <textarea class="input" name="address" rows="3"
                placeholder="Address"><?= htmlspecialchars($patient['address'] ?? '') ?></textarea>

      <button class="btn" name="update_profile">Update Profile</button>
    </form>

    <hr style="margin:20px 0;">

    <!-- CHANGE PASSWORD -->
    <h3>Change Password</h3>
    <form method="post">
      <input class="input" type="password" name="old_password"
             placeholder="Old Password" required>

      <input class="input" type="password" name="new_password"
             placeholder="New Password" required>

      <input class="input" type="password" name="confirm_password"
             placeholder="Confirm New Password" required>

      <button class="btn" name="change_password">Change Password</button>
    </form>

  </div>
</div>

</body>
</html>
