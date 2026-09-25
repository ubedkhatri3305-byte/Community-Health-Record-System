<?php
// register.php
require 'config.php';

// If already logged in
if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit;
}

$err = "";

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $name    = trim($_POST['name']);
    $email   = trim($_POST['email']);
    $pass    = $_POST['password'];
    $phone   = trim($_POST['phone']);
    $address = trim($_POST['address']);

    if ($name === "" || $email === "" || $pass === "") {
        $err = "Please fill all required fields";
    } else {

        // Hash password
        $password = password_hash($pass, PASSWORD_DEFAULT);

        // By rule: normal registration = PATIENT
        $role = "patient";

        $stmt = $mysqli->prepare(
            "INSERT INTO users (name, email, password, role, phone, address)
             VALUES (?, ?, ?, ?, ?, ?)"
        );

        if (!$stmt) {
            $err = "Database error: " . $mysqli->error;
        } else {
            $stmt->bind_param(
                "ssssss",
                $name,
                $email,
                $password,
                $role,
                $phone,
                $address
            );

            if ($stmt->execute()) {
                header("Location: index.php?registered=1");
                exit;
            } else {
                $err = "Email already exists";
            }
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <title>CHR - Register</title>
  <meta name="viewport" content="width=device-width,initial-scale=1"/>
  <link rel="stylesheet" href="assets/css/styles.css">
</head>
<body>
  <div class="center" style="min-height:100vh; padding:28px;">
    <div class="card form">
      <div class="logo">CHR</div>
      <h2>Create an account</h2>

      <?php if(!empty($err)): ?>
        <div style="color:#842029;background:#f8d7da;padding:8px;border-radius:8px;margin-bottom:10px;text-align:center;">
          <?=htmlspecialchars($err)?>
        </div>
      <?php endif; ?>

      <form method="post" style="margin-top:6px;">
        <input class="input" type="text" name="name" placeholder="Full name" required />
        <input class="input" type="email" name="email" placeholder="Email" required />
        <input class="input" type="password" name="password" placeholder="Password" required />
        <input class="input" type="text" name="phone" placeholder="Phone (optional)" />
        <textarea class="input" name="address" placeholder="Address (optional)" rows="3"></textarea>
        <button class="btn" type="submit">Register</button>
      </form>

      <a class="link" href="index.php">Already have an account? Login</a>
    </div>
  </div>
</body>
</html>
