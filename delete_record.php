<?php
require 'config.php';
ensure_logged_in();
require 'notify.php';

$user = current_user($mysqli);
$id   = isset($_GET['id']) ? (int)$_GET['id'] : 0;

/* Fetch record — must belong to logged-in patient */
$stmt = $mysqli->prepare("SELECT file_path FROM records WHERE id=? AND user_id=?");
$stmt->bind_param('ii', $id, $user['id']);
$stmt->execute();
$rec = $stmt->get_result()->fetch_assoc();

if ($rec) {
    /* Remove attached file from disk */
    if ($rec['file_path'] && file_exists($rec['file_path'])) {
        @unlink($rec['file_path']);
    }
    $del = $mysqli->prepare("DELETE FROM records WHERE id=? AND user_id=?");
    $del->bind_param('ii', $id, $user['id']);
    $del->execute();
}

header("Location: dashboard_patient.php");
exit;
