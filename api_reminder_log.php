<?php
require 'config.php';
ensure_logged_in();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $patient_id = (int)$_SESSION['user_id'];
    $medicine = trim($_POST['medicine'] ?? '');
    $dosage = trim($_POST['dosage'] ?? '');
    $slot = trim($_POST['slot'] ?? ''); // Morning, Afternoon, Night

    if (!empty($medicine)) {
        $message = "⏰ Medicine Reminder: It's time to take your " . htmlspecialchars($slot) . " dose of " . htmlspecialchars($medicine) . " (" . htmlspecialchars($dosage) . ").";
        
        try {
            $stmt = $mysqli->prepare("INSERT INTO notifications (user_id, message) VALUES (?, ?)");
            $stmt->bind_param("is", $patient_id, $message);
            $stmt->execute();
            echo json_encode(['success' => true]);
            exit;
        } catch (Throwable $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            exit;
        }
    }
}

echo json_encode(['success' => false, 'error' => 'Invalid request']);
