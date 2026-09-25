<?php
require 'config.php';

$error = "";

/* 🔒 Only hospital can register doctor */
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'hospital') {
    header("Location: index.php");
    exit;
}

$hospital_id = $_SESSION['user_id'];

/* GET HOSPITAL NAME */
$hospital = $mysqli->prepare("SELECT name FROM hospitals WHERE id=?");
$hospital->bind_param("i", $hospital_id);
$hospital->execute();
$hospitalData = $hospital->get_result()->fetch_assoc();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $name  = $_POST['name'];
    $email = $_POST['email'];
    $password = $_POST['password'];
    $pass  = password_hash($password, PASSWORD_DEFAULT);

    $phone = $_POST['phone'];
    $spec  = $_POST['specialization'];
    $qualification = $_POST['qualification'];   // ✅ NEW FIELD
    $loc   = $_POST['location'];

    /* CHECK EMAIL */
    $chk = $mysqli->prepare("SELECT id FROM users WHERE email=?");
    $chk->bind_param("s", $email);
    $chk->execute();
    $chk->store_result();

    if ($chk->num_rows > 0) {
        $error = "❌ Email already exists!";
    } else {

        /* INSERT DOCTOR */
        $stmt = $mysqli->prepare("
            INSERT INTO users
            (name, email, password, role, phone, specialization, qualification, location, hospital_id)
            VALUES (?, ?, ?, 'doctor', ?, ?, ?, ?, ?)
        ");

        $stmt->bind_param(
            "sssssssi",
            $name,
            $email,
            $pass,
            $phone,
            $spec,
            $qualification,
            $loc,
            $hospital_id
        );

        if ($stmt->execute()) {
            header("Location: hospital_dashboard.php");
            exit;
        } else {
            $error = "❌ Error registering doctor!";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Register Doctor</title>
<meta name="viewport" content="width=device-width, initial-scale=1">

<link rel="stylesheet" href="assets/css/styles.css">
</head>

<body>

<div class="center" style="min-height:100vh">
<div class="card form">

<div class="logo">DR</div>
<h2>Doctor Registration</h2>

<?php if($error): ?>
<div style="background:#fdecea;color:#b91c1c;padding:10px;border-radius:8px;text-align:center;margin-bottom:10px;">
<?= htmlspecialchars($error) ?>
</div>
<?php endif; ?>

<form method="post">

<input class="input" name="name" placeholder="Full Name" required>

<input class="input" type="email" name="email" placeholder="Email" required>

<input class="input" type="password" name="password" placeholder="Password" required>

<input class="input" name="phone" placeholder="Phone Number" required>

<input class="input" name="specialization" placeholder="Specialization" required>

<!-- ✅ NEW FIELD -->
<input class="input" name="qualification" placeholder="Qualification (MBBS, MD, etc)" required>

<input class="input" name="location" placeholder="Location" required>

<!-- Hospital name (readonly) -->
<input class="input" value="Hospital: <?= htmlspecialchars($hospitalData['name']) ?>" disabled>

<button class="btn" type="submit">Register Doctor</button>

</form>

<hr style="margin:14px 0">

<a class="link" href="hospital_dashboard.php">⬅ Back to Dashboard</a>

</div>
</div>

</body>
</html>