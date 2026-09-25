<?php
require 'config.php';
ensure_logged_in();
ensure_role('doctor');

$doctor_id = $_SESSION['user_id'];
$leave_date = $_POST['leave_date'];
$reason = $_POST['reason'] ?? '';

if(!$leave_date){
    die("Leave date required");
}

$stmt = $mysqli->prepare("INSERT INTO doctor_leaves (doctor_id, leave_date, reason) VALUES (?, ?, ?)");
$stmt->bind_param("iss", $doctor_id, $leave_date, $reason);
$stmt->execute();

header("Location: dashboard_doctor.php?date=".$leave_date);
exit;
?>
