<?php
require 'config.php';

header('Content-Type: application/json');

$lat = isset($_GET['lat']) ? (float)$_GET['lat'] : 0.0;
$lng = isset($_GET['lng']) ? (float)$_GET['lng'] : 0.0;

$data = [];

try {
    if ($lat != 0.0 && $lng != 0.0) {
        $sql = "
        SELECT h.id, h.name, h.open_time, h.close_time,
        TIME(NOW()) BETWEEN h.open_time AND h.close_time AS is_open,
        ROUND(6371 * acos(
            cos(radians(?)) *
            cos(radians(COALESCE(h.latitude, 0))) *
            cos(radians(COALESCE(h.longitude, 0)) - radians(?)) +
            sin(radians(?)) *
            sin(radians(COALESCE(h.latitude, 0)))
        ),2) AS distance
        FROM hospitals h
        ORDER BY distance ASC
        LIMIT 10
        ";

        $stmt = $mysqli->prepare($sql);
        $stmt->bind_param("ddd", $lat, $lng, $lat);
        $stmt->execute();
        $res = $stmt->get_result();
    } else {
        $res = $mysqli->query("
        SELECT h.id, h.name, h.open_time, h.close_time,
        TIME(NOW()) BETWEEN h.open_time AND h.close_time AS is_open,
        0 AS distance
        FROM hospitals h
        ORDER BY h.name ASC
        LIMIT 10
        ");
    }

    if ($res) {
        while ($r = $res->fetch_assoc()) {
            $data[] = $r;
        }
    }
} catch (Throwable $e) {
    // Return empty array on exception
}

echo json_encode($data);
