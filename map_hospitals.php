<?php
require 'config.php';

header('Content-Type: application/json');

$lat = isset($_GET['lat']) ? (float)$_GET['lat'] : 0.0;
$lng = isset($_GET['lng']) ? (float)$_GET['lng'] : 0.0;
$filterType = isset($_GET['type']) ? strtolower(trim($_GET['type'])) : 'all';

$data = [];

// Fallback coordinates for cities if lat/lng is missing in DB
$cityCoords = [
    'modasa'    => ['lat' => 23.4636, 'lng' => 73.3005],
    'bhuj'      => ['lat' => 23.2420, 'lng' => 69.6669],
    'bhujpur'   => ['lat' => 22.8680, 'lng' => 69.5840],
    'mundra'    => ['lat' => 22.8396, 'lng' => 69.7226],
    'ahmedabad' => ['lat' => 23.0225, 'lng' => 72.5714],
];

function checkFacilityIsOpen($openTime, $closeTime) {
    if (empty($openTime) || empty($closeTime)) return true;
    if ($openTime === $closeTime || ($openTime === '00:00:00' && $closeTime === '00:00:00')) return true;

    $curSec = (int)date('H') * 3600 + (int)date('i') * 60 + (int)date('s');
    $partsO = explode(':', $openTime);
    $partsC = explode(':', $closeTime);
    $openSec = ((int)($partsO[0] ?? 0)) * 3600 + ((int)($partsO[1] ?? 0)) * 60;
    $closeSec = ((int)($partsC[0] ?? 0)) * 3600 + ((int)($partsC[1] ?? 0)) * 60;

    if ($openSec < $closeSec) {
        return ($curSec >= $openSec && $curSec <= $closeSec);
    } else {
        // Crosses midnight, e.g. 20:00 to 06:00
        return ($curSec >= $openSec || $curSec <= $closeSec);
    }
}

try {
    $whereTypeClause = "";
    $typeParam = null;
    if ($filterType === 'hospital' || $filterType === 'laboratory') {
        $whereTypeClause = " WHERE h.type = ? ";
        $typeParam = $filterType;
    }

    if ($lat != 0.0 && $lng != 0.0) {
        $sql = "
        SELECT h.id, h.name, h.address, h.phone, h.email, h.city, h.type, h.latitude, h.longitude,
               h.open_time, h.close_time,
               ROUND(6371 * acos(
                   LEAST(1.0, GREATEST(-1.0,
                       cos(radians(?)) *
                       cos(radians(COALESCE(h.latitude, 23.4636))) *
                       cos(radians(COALESCE(h.longitude, 73.3005)) - radians(?)) +
                       sin(radians(?)) *
                       sin(radians(COALESCE(h.latitude, 23.4636)))
                   ))
               ), 2) AS distance
        FROM hospitals h
        {$whereTypeClause}
        ORDER BY distance ASC
        LIMIT 50
        ";

        $stmt = $mysqli->prepare($sql);
        if ($typeParam) {
            $stmt->bind_param("ddds", $lat, $lng, $lat, $typeParam);
        } else {
            $stmt->bind_param("ddd", $lat, $lng, $lat);
        }
        $stmt->execute();
        $res = $stmt->get_result();
    } else {
        $sql = "
        SELECT h.id, h.name, h.address, h.phone, h.email, h.city, h.type, h.latitude, h.longitude,
               h.open_time, h.close_time,
               NULL AS distance
        FROM hospitals h
        {$whereTypeClause}
        ORDER BY h.name ASC
        LIMIT 50
        ";

        if ($typeParam) {
            $stmt = $mysqli->prepare($sql);
            $stmt->bind_param("s", $typeParam);
            $stmt->execute();
            $res = $stmt->get_result();
        } else {
            $res = $mysqli->query($sql);
        }
    }

    if ($res) {
        $index = 0;
        while ($r = $res->fetch_assoc()) {
            $cLower = strtolower(trim($r['city'] ?? ''));
            $fallback = $cityCoords[$cLower] ?? ['lat' => 23.4636, 'lng' => 73.3005];

            // If coordinates are missing, provide default city coords with slight jitter so markers don't overlap exactly
            if (empty($r['latitude']) || empty($r['longitude'])) {
                $jitter = ($index % 5) * 0.003;
                $r['latitude'] = $fallback['lat'] + $jitter;
                $r['longitude'] = $fallback['lng'] + $jitter;
            } else {
                $r['latitude'] = (float)$r['latitude'];
                $r['longitude'] = (float)$r['longitude'];
            }

            $r['id'] = (int)$r['id'];
            $r['type'] = !empty($r['type']) ? strtolower($r['type']) : 'hospital';
            $r['distance'] = isset($r['distance']) && $r['distance'] !== null ? (float)$r['distance'] : null;
            $r['is_open'] = checkFacilityIsOpen($r['open_time'] ?? null, $r['close_time'] ?? null);

            $data[] = $r;
            $index++;
        }
    }
} catch (Throwable $e) {
    error_log("map_hospitals error: " . $e->getMessage());
}

echo json_encode($data);
