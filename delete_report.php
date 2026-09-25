<?php
require 'config.php';
ensure_logged_in();

$report_id = (int)$_GET['id'];

// Check if user is the patient who owns this report
$stmt = $mysqli->prepare("
    SELECT lr.*, la.patient_id 
    FROM lab_reports lr
    JOIN lab_appointments la ON lr.appointment_id = la.id
    WHERE lr.id = ?
");
$stmt->bind_param("i", $report_id);
$stmt->execute();
$report = $stmt->get_result()->fetch_assoc();

if (!$report || $report['patient_id'] != $_SESSION['user_id']) {
    die("❌ Unauthorized access");
}

// Delete the report file
if ($report['report_file'] && file_exists("uploads/" . $report['report_file'])) {
    unlink("uploads/" . $report['report_file']);
}

// Delete from database
$del_stmt = $mysqli->prepare("DELETE FROM lab_reports WHERE id=?");
$del_stmt->bind_param("i", $report_id);
$del_stmt->execute();

header("Location: dashboard_patient.php?msg=Report deleted successfully");
exit;
?>
