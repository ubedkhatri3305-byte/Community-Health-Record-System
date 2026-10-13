<?php
require 'config.php';

/* 🔒 SECURITY */
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'hospital') {
    header("Location: index.php");
    exit;
}

$hospital_id = $_SESSION['user_id'];

/* 🔍 SEARCH */
$search = $_GET['search'] ?? '';

/* 🔥 DELETE DOCTOR */
if (isset($_GET['delete'])) {
    $doctor_id = (int)$_GET['delete'];

    try {
        @$mysqli->query("DELETE FROM doctor_profiles WHERE doctor_id = $doctor_id");
        $stmt = $mysqli->prepare("
            DELETE FROM users 
            WHERE id=? AND hospital_id=? AND role='doctor'
        ");
        $stmt->bind_param("ii", $doctor_id, $hospital_id);
        $stmt->execute();
    } catch (Throwable $e) {
        // Handled cleanly
    }

    header("Location: hospital_dashboard.php");
    exit;
}

/* 🔥 FETCH DOCTORS WITH PROFILE */
$sql = "
SELECT u.*, dp.specialization, dp.qualification
FROM users u
LEFT JOIN doctor_profiles dp ON dp.doctor_id = u.id
WHERE u.role='doctor' AND u.hospital_id=?
";

$params = [$hospital_id];
$types  = "i";

if (!empty($search)) {
    $sql .= " AND u.name LIKE ?";
    $params[] = "%$search%";
    $types .= "s";
}

$stmt = $mysqli->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html>
<head>
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Hospital Doctor Management - CHR</title>
<link rel="stylesheet" href="assets/css/styles.css">

<style>
.doctor-link {
    color: var(--primary);
    text-decoration: none;
    font-weight: 600;
}
.doctor-link:hover {
    color: var(--primary-hover);
    text-decoration: underline;
}
</style>
</head>

<body>

<div class="container">

<div class="topbar">
    <div class="brand">Doctor Management</div>
    <div>
        <a href="register_doctor.php">+ Add Doctor</a>
        <a href="hospital_map.php?id=<?= $hospital_id ?>">Map View</a>
        <a href="edit_hospital_profile.php">Edit Profile</a>
        <a href="logout.php">Logout</a>
    </div>
</div>

<!-- 🔍 SEARCH -->
<form method="get" style="display:flex;gap:10px;margin-bottom:18px;max-width:440px;">
    <input type="text" name="search" placeholder="Search doctor name..."
           value="<?= htmlspecialchars($search) ?>" class="input" style="margin-bottom:0;">
    <button class="btn" style="width:auto;padding:0 22px;">Search</button>
</form>

<div class="card" style="padding:0;overflow:hidden;">
<table>

<tr>
<th>Name</th>
<th>Email</th>
<th>Phone</th>
<th>Specialization</th>
<th>Qualification</th>
<th>Action</th>
</tr>

<?php if ($result->num_rows > 0): ?>
    <?php while($row = $result->fetch_assoc()): ?>
    <tr>

        <td>
            <a href="doctor_profile.php?id=<?= $row['id'] ?>" class="doctor-link">
                <?= htmlspecialchars($row['name']) ?>
            </a>
        </td>

        <td><?= htmlspecialchars($row['email']) ?></td>
        <td><?= !empty($row['phone']) ? htmlspecialchars($row['phone']) : 'N/A' ?></td>

        <!-- ✅ FROM doctor_profiles -->
        <td><?= !empty($row['specialization']) ? htmlspecialchars($row['specialization']) : 'N/A' ?></td>

        <td><?= !empty($row['qualification']) ? htmlspecialchars($row['qualification']) : 'N/A' ?></td>

        <td>
            <a href="hospital_dashboard.php?delete=<?= $row['id'] ?>"
               class="btn btn-danger"
               onclick="return confirm('Delete this doctor?')">
               Delete
            </a>
        </td>

    </tr>
    <?php endwhile; ?>
<?php else: ?>
    <tr>
        <td colspan="6">No doctors found</td>
    </tr>
<?php endif; ?>

</table>
</div>

</div>

</body>
</html>