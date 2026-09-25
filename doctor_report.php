<?php require 'config.php';

$doctor_id = $_GET['id'];

$query = "
SELECT leave_date, reason
FROM doctor_leaves
WHERE doctor_id = ?
ORDER BY leave_date DESC
";

$stmt = $mysqli->prepare($query);
$stmt->bind_param("i", $doctor_id);
$stmt->execute();
$result = $stmt->get_result();
?>

<!doctype html>
<html>
<head>
<title>Doctor Report</title>
<link rel="stylesheet" href="assets/css/styles.css">
</head>

<body>

<div class="center">
<div class="card">

<h2>📋 Doctor Leave Details</h2>

<table class="table">
<tr>
<th>Date</th>
<th>Reason</th>
</tr>

<?php while($row = $result->fetch_assoc()): ?>
<tr>
<td><?= $row['leave_date'] ?></td>
<td><?= $row['reason'] ?></td>
</tr>
<?php endwhile; ?>

</table>

<br>

<button onclick="history.back()" class="btn">⬅ Back</button>

</div>
</div>

</body>
</html>