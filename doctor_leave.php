<?php
require 'config.php';
ensure_logged_in();
ensure_role('doctor');

$doctor_id = (int)$_SESSION['user_id'];
$leave_date = trim($_POST['leave_date'] ?? '');
$reason = trim($_POST['reason'] ?? '');

if (empty($leave_date)) {
    header("Location: dashboard_doctor.php");
    exit;
}

try {
    // Check if already on leave for this date
    $chk = $mysqli->prepare("SELECT id FROM doctor_leaves WHERE doctor_id=? AND leave_date=? LIMIT 1");
    $chk->bind_param("is", $doctor_id, $leave_date);
    $chk->execute();
    if ($chk->get_result()->num_rows === 0) {
        $stmt = $mysqli->prepare("INSERT INTO doctor_leaves (doctor_id, leave_date, reason) VALUES (?, ?, ?)");
        $stmt->bind_param("iss", $doctor_id, $leave_date, $reason);
        $stmt->execute();
    }
} catch (Throwable $e) {
    // Silent catch so it doesn't crash
}

header("Location: dashboard_doctor.php?date=" . urlencode($leave_date));
exit;
?>
