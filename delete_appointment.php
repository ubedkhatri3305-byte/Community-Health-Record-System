<?php
require 'config.php';
ensure_logged_in();
ensure_role('patient');

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$user_id = (int)$_SESSION['user_id'];

if (!$id) {
    exit("invalid");
}

try {
    $stmt = $mysqli->prepare("
        DELETE FROM appointments 
        WHERE id=? AND patient_id=?
    ");
    $stmt->bind_param("ii", $id, $user_id);
    $stmt->execute();
    echo "success";
} catch (Throwable $e) {
    exit("error: " . $e->getMessage());
}
?>

