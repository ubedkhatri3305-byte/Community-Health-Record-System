<?php
require 'config.php';
ensure_logged_in();
ensure_role('doctor');

$doctor_id = (int)$_SESSION['user_id'];
$id        = (int)($_GET['id'] ?? 0);
$status    = $_GET['s'] ?? '';

if (!$id) exit("invalid");

/* ================= FETCH CURRENT APPOINTMENT ================= */
$check = $mysqli->prepare("SELECT status FROM appointments WHERE id=? AND doctor_id=?");
$check->bind_param("ii", $id, $doctor_id);
$check->execute();
$appt = $check->get_result()->fetch_assoc();

if (!$appt) exit("invalid");

/* ================= HANDLE RESCHEDULE ================= */
if ($status === 'reschedule') {

    $new_date = $_POST['new_date'] ?? '';
    $new_time = $_POST['new_time'] ?? '';

    if (!$new_date) exit("missing date");

    $stmt = $mysqli->prepare("
        UPDATE appointments 
        SET rescheduled_date = ?,
            rescheduled_time = ?,
            reschedule_msg   = 'Doctor rescheduled your appointment',
            status           = 'rescheduled'
        WHERE id = ? AND doctor_id = ?
    ");
    $stmt->bind_param("ssii", $new_date, $new_time, $id, $doctor_id);
    $stmt->execute();

    $msg = "Your appointment has been rescheduled to $new_date at $new_time by your doctor.";

/* ================= HANDLE ACCEPT / REJECT ================= */
} elseif (in_array($status, ['accepted', 'rejected'])) {

    /* Only allow changing if status is pending or rescheduled */
    if (!in_array($appt['status'], ['pending', 'rescheduled'])) {
        exit("locked");
    }

    $stmt = $mysqli->prepare("
        UPDATE appointments 
        SET status = ?
        WHERE id = ? AND doctor_id = ?
    ");
    $stmt->bind_param("sii", $status, $id, $doctor_id);
    $stmt->execute();

    $msg = "Your appointment has been $status by your doctor.";

} else {
    exit("unknown action");
}

/* ================= SEND NOTIFICATION ================= */
$appt2 = $mysqli->prepare("SELECT patient_id FROM appointments WHERE id=?");
$appt2->bind_param("i", $id);
$appt2->execute();
$row = $appt2->get_result()->fetch_assoc();

if ($row) {
    $notif = $mysqli->prepare("INSERT INTO notifications (user_id, message) VALUES (?, ?)");
    $notif->bind_param("is", $row['patient_id'], $msg);
    $notif->execute();
}

echo "success";