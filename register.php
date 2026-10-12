<?php
// register.php
require 'config.php';

// If already logged in
if (isset($_SESSION['user_id'])) {
    header("Location: dashboard_patient.php");
    exit;
}

$err = "";

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $name    = trim($_POST['name'] ?? '');
    $email   = trim($_POST['email'] ?? '');
    $pass    = $_POST['password'] ?? '';
    $phone   = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');

    if ($name === "" || $email === "" || $pass === "") {
        $err = "❌ Please fill all required fields";
    } else {

        // Check if email already exists
        $chk = $mysqli->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
        $chk->bind_param("s", $email);
        $chk->execute();
        $chk->store_result();

        if ($chk->num_rows > 0) {
            $err = "❌ This email is already registered! Please login or use a different email.";
        } else {
            // Hash password
            $password = password_hash($pass, PASSWORD_DEFAULT);

            // Normal registration = PATIENT
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

                try {
                    if ($stmt->execute()) {
                        header("Location: index.php?msg=" . urlencode("✅ Account created successfully! Please login."));
                        exit;
                    } else {
                        $err = "❌ Registration failed. Please try again.";
                    }
                } catch (Throwable $e) {
                    if ($e->getCode() == 1062 || str_contains($e->getMessage(), 'Duplicate entry')) {
                        $err = "❌ This email is already registered! Please login or use a different email.";
                    } else {
                        $err = "❌ Registration error: " . $e->getMessage();
                    }
                }
            }
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <title>Patient Registration - CHR</title>
  <meta name="viewport" content="width=device-width,initial-scale=1"/>
  <link rel="stylesheet" href="assets/css/styles.css">
</head>
<body>
  <div class="center" style="min-height:100vh; padding:28px 14px;">
    <div class="card form">
      <div class="logo">🏥</div>
      <h2 style="margin-bottom:4px;">Patient Registration</h2>
      <p style="text-align:center;color:var(--text-muted);font-size:13px;margin-bottom:20px;">Join the Community Health Record System</p>

      <?php if(!empty($err)): ?>
        <div class="msg error" style="margin-bottom:16px;">
          <?=htmlspecialchars($err)?>
        </div>
      <?php endif; ?>

      <form method="post" style="margin-top:6px;">
        <label style="font-size:13px;font-weight:600;color:var(--text-secondary);display:block;margin-bottom:4px;">Full Name *</label>
        <input class="input" type="text" name="name" placeholder="John Doe" required />

        <label style="font-size:13px;font-weight:600;color:var(--text-secondary);display:block;margin-bottom:4px;">Email Address *</label>
        <input class="input" type="email" name="email" placeholder="name@example.com" required />

        <label style="font-size:13px;font-weight:600;color:var(--text-secondary);display:block;margin-bottom:4px;">Password *</label>
        <input class="input" type="password" name="password" placeholder="Create password" required />

        <label style="font-size:13px;font-weight:600;color:var(--text-secondary);display:block;margin-bottom:4px;">Phone Number</label>
        <input class="input" type="text" name="phone" placeholder="+91 9876543210" />

        <label style="font-size:13px;font-weight:600;color:var(--text-secondary);display:block;margin-bottom:4px;">Address</label>
        <textarea class="input" name="address" placeholder="City, State" rows="2"></textarea>

        <button class="btn" type="submit" style="margin-top:8px;">Complete Registration</button>
      </form>

      <div style="border-top:1px solid var(--card-border);margin:20px 0 16px 0;"></div>
      <a class="link" href="index.php" style="margin-top:0;">Already have an account? Sign in &rarr;</a>
    </div>
  </div>
</body>
</html>
