<?php
require 'config.php';
ensure_logged_in();
ensure_role('doctor');

$doctor_id = $_SESSION['user_id'];
$patient_id = (int)$_GET['patient_id'];

header('Content-Type: application/json');

// Verify that this patient has had consultations or appointments with this doctor
$verify = $mysqli->prepare("
    SELECT COUNT(*) as count FROM appointments 
    WHERE doctor_id = ? AND patient_id = ?
");
$verify->bind_param("ii", $doctor_id, $patient_id);
$verify->execute();
$result = $verify->get_result()->fetch_assoc();

if ($result['count'] == 0) {
    echo json_encode(['reports' => []]);
    exit;
}

try {
    // Fetch reports for this patient using LEFT JOIN so all uploaded reports are included
    $stmt = $mysqli->prepare("
        SELECT 
            lr.id,
            lr.report_file,
            COALESCE(la.test_name, 'Laboratory Test') AS test_name,
            lr.uploaded_at
        FROM lab_reports lr
        LEFT JOIN lab_appointments la ON lr.appointment_id = la.id
        WHERE lr.patient_id = ?
        ORDER BY lr.uploaded_at DESC
    ");
    $stmt->bind_param("i", $patient_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $reports = [];
    while ($row = $result->fetch_assoc()) {
        $cleanFile = basename($row['report_file'] ?? '');
        $reports[] = [
            'id' => (int)$row['id'],
            'test_name' => htmlspecialchars($row['test_name'] ?? 'Laboratory Test'),
            'report_file' => htmlspecialchars($cleanFile),
            'view_url' => 'view_report.php?id=' . (int)$row['id'],
            'download_url' => 'download_report.php?id=' . (int)$row['id'],
            'uploaded_at' => $row['uploaded_at'] ?? ''
        ];
    }

    echo json_encode(['reports' => $reports]);
} catch (Throwable $e) {
    echo json_encode(['reports' => [], 'error' => $e->getMessage()]);
}
?>
