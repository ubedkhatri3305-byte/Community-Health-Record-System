<?php
require 'config.php';
ensure_logged_in();
ensure_role('doctor');

$doctor_id = $_SESSION['user_id'];
$patient_id = (int)$_GET['patient_id'];

// Verify that this patient is associated with this doctor
$verify = $mysqli->prepare("
    SELECT COUNT(*) as count FROM appointments 
    WHERE doctor_id = ? AND patient_id = ? AND status = 'accepted'
");
$verify->bind_param("ii", $doctor_id, $patient_id);
$verify->execute();
$result = $verify->get_result()->fetch_assoc();

if ($result['count'] == 0) {
    echo json_encode(['reports' => []]);
    exit;
}

// Fetch reports for this patient
$stmt = $mysqli->prepare("
    SELECT 
        lr.id,
        lr.report_file,
        la.test_name,
        lr.created_at as uploaded_at
    FROM lab_reports lr
    JOIN lab_appointments la ON lr.appointment_id = la.id
    WHERE lr.patient_id = ?
    ORDER BY lr.created_at DESC
");
$stmt->bind_param("i", $patient_id);
$stmt->execute();
$result = $stmt->get_result();

$reports = [];
while ($row = $result->fetch_assoc()) {
    $reports[] = [
        'id' => $row['id'],
        'test_name' => htmlspecialchars($row['test_name']),
        'report_file' => htmlspecialchars($row['report_file']),
        'uploaded_at' => $row['uploaded_at']
    ];
}

header('Content-Type: application/json');
echo json_encode(['reports' => $reports]);
?>
