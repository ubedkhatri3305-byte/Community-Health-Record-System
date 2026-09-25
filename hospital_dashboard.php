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
    $doctor_id = $_GET['delete'];

    $stmt = $mysqli->prepare("
        DELETE FROM users 
        WHERE id=? AND hospital_id=? AND role='doctor'
    ");
    $stmt->bind_param("ii", $doctor_id, $hospital_id);
    $stmt->execute();

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
<title>Hospital Dashboard</title>

<style>
body {
    font-family: Arial;
    background: #f1f5f9;
}

.container {
    width: 85%;
    margin: auto;
    margin-top: 30px;
}

.topbar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
}

.btn {
    padding: 8px 14px;
    background: green;
    color: white;
    border-radius: 6px;
    text-decoration: none;
    margin-left: 5px;
}

.btn-danger {
    background: red;
}

/* 🔍 SEARCH BOX */
.search-box {
    margin-top: 15px;
    display: flex;
    gap: 10px;
}

.search-box input {
    padding: 8px;
    width: 250px;
    border-radius: 6px;
    border: 1px solid #ccc;
}

.search-box button {
    padding: 8px 12px;
    background: #2563eb;
    color: white;
    border: none;
    border-radius: 6px;
    cursor: pointer;
}

table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 20px;
    background: white;
}

th, td {
    padding: 10px;
    border-bottom: 1px solid #ddd;
    text-align: center;
}

th {
    background: #e2e8f0;
}

.doctor-link {
    color: #2563eb;
    text-decoration: none;
    font-weight: bold;
}

.doctor-link:hover {
    text-decoration: underline;
}
</style>

</head>

<body>

<div class="container">

<div class="topbar">
<h2>🏥 Hospital Doctor Management</h2>

<div>
<a href="register_doctor.php" class="btn">+ Add Doctor</a>
<a href="edit_hospital_profile.php" class="btn">Edit Profile</a>
<a href="logout.php" class="btn btn-danger">Logout</a>
</div>
</div>

<!-- 🔍 SEARCH -->
<form method="get" class="search-box">
    <input type="text" name="search" placeholder="Search doctor name..."
           value="<?= htmlspecialchars($search) ?>">
    <button>Search</button>
</form>

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

</body>
</html>