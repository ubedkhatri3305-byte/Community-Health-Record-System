<?php
require 'config.php';

$lat = $_GET['lat'];
$lng = $_GET['lng'];
$sql = "
SELECT h.id, h.name, h.open_time, h.close_time,
TIME(NOW()) BETWEEN h.open_time AND h.close_time AS is_open,
ROUND(6371 * acos(
    cos(radians(?)) *
    cos(radians(h.latitude)) *
    cos(radians(h.longitude) - radians(?)) +
    sin(radians(?)) *
    sin(radians(h.latitude))
),2) AS distance
FROM hospitals h
ORDER BY distance ASC
LIMIT 10
";


$stmt = $mysqli->prepare($sql);
$stmt->bind_param("ddd",$lat,$lng,$lat);
$stmt->execute();

$res = $stmt->get_result();
$data = [];

while($r = $res->fetch_assoc()){
  $data[] = $r;
}

echo json_encode($data);
