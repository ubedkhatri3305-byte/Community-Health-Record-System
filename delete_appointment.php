<?php
require 'config.php';
ensure_logged_in();
ensure_role('patient');

$id = (int)$_GET['id'];
$user_id = $_SESSION['user_id'];

$stmt = $mysqli->prepare("
    DELETE FROM appointments 
    WHERE id=? AND patient_id=?
");
$stmt->bind_param("ii", $id, $user_id);
$stmt->execute();

echo "success";

