<?php
require 'config.php';
ensure_logged_in();

$report_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$report_id) {
    header("Location: dashboard_patient.php");
    exit;
}

try {
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

    if ($report && $report['patient_id'] == $_SESSION['user_id']) {
        // Delete the report file from both local uploads and temp uploads
        if (!empty($report['report_file'])) {
            $fname = basename($report['report_file']);
            if (file_exists("uploads/" . $fname)) {
                @unlink("uploads/" . $fname);
            }
            if (file_exists(sys_get_temp_dir() . "/uploads/" . $fname)) {
                @unlink(sys_get_temp_dir() . "/uploads/" . $fname);
            }
        }

        // Delete from database
        $del_stmt = $mysqli->prepare("DELETE FROM lab_reports WHERE id=?");
        $del_stmt->bind_param("i", $report_id);
        $del_stmt->execute();
    }
} catch (Throwable $e) {
    // Silent catch
}

header("Location: dashboard_patient.php?msg=" . urlencode("Report deleted successfully"));
exit;
?>
