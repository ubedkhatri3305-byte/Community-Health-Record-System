<?php
require 'config.php';

$hospital_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// If hospital_id is not provided, try to use logged-in hospital user ID
if (!$hospital_id && isset($_SESSION['user_id']) && ($_SESSION['role'] ?? '') === 'hospital') {
    $hospital_id = (int)$_SESSION['user_id'];
}

// Fallback to first available hospital if still not found
if (!$hospital_id) {
    $firstQuery = $mysqli->query("SELECT id FROM hospitals LIMIT 1");
    if ($firstQuery && $firstRow = $firstQuery->fetch_assoc()) {
        $hospital_id = (int)$firstRow['id'];
    }
}

$hospital = null;
if ($hospital_id) {
    $stmt = $mysqli->prepare("
        SELECT id, name, address, phone, email, latitude, longitude, city, type, open_time, close_time
        FROM hospitals
        WHERE id = ?
    ");
    $stmt->bind_param("i", $hospital_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $hospital = $result->fetch_assoc();
}

if (!$hospital) {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Facility Not Found - CHR</title>
        <link rel="stylesheet" href="assets/css/styles.css">
    </head>
    <body class="center" style="min-height:100vh;padding:20px;">
        <div class="card" style="max-width:480px;text-align:center;">
            <div style="font-size:48px;margin-bottom:12px;">🏥⚠️</div>
            <h2>Facility Not Found</h2>
            <p style="color:var(--text-secondary);margin:12px 0 20px 0;">No healthcare facility record exists for this identifier.</p>
            <a href="<?= (isset($_SESSION['role']) && $_SESSION['role'] === 'hospital') ? 'hospital_dashboard.php' : 'index.php' ?>" class="btn">⬅ Return to Dashboard</a>
        </div>
    </body>
    </html>
    <?php
    exit;
}

$type = strtolower($hospital['type'] ?? 'hospital');
$is_lab = ($type === 'laboratory');
$typeLabel = $is_lab ? 'Diagnostic Laboratory' : 'Hospital';
$typeIcon = $is_lab ? '🧪' : '🏥';

$has_exact_coords = !empty($hospital['latitude']) && !empty($hospital['longitude']);
$lat = $has_exact_coords ? (float)$hospital['latitude'] : 23.0225;  // Default: Gujarat center
$lng = $has_exact_coords ? (float)$hospital['longitude'] : 72.5714;
$name = htmlspecialchars($hospital['name'] ?? $typeLabel);
$address = htmlspecialchars($hospital['address'] ?? 'Address not specified');
$phone = htmlspecialchars($hospital['phone'] ?? 'N/A');
$email = htmlspecialchars($hospital['email'] ?? 'N/A');
$city = htmlspecialchars($hospital['city'] ?? '');
$open_time = !empty($hospital['open_time']) ? substr($hospital['open_time'], 0, 5) : '08:00';
$close_time = !empty($hospital['close_time']) ? substr($hospital['close_time'], 0, 5) : '20:00';

$is_open = true;
$open_status_label = '🟢 Open Now';
if (!empty($hospital['open_time']) && !empty($hospital['close_time'])) {
    $curSec = (int)date('H') * 3600 + (int)date('i') * 60;
    $pO = explode(':', $hospital['open_time']);
    $pC = explode(':', $hospital['close_time']);
    $oSec = ((int)($pO[0] ?? 0)) * 3600 + ((int)($pO[1] ?? 0)) * 60;
    $cSec = ((int)($pC[0] ?? 0)) * 3600 + ((int)($pC[1] ?? 0)) * 60;
    if ($oSec === $cSec || ($oSec === 0 && $cSec === 0)) {
        $is_open = true;
        $open_status_label = '🟢 24/7 Open';
    } elseif ($oSec < $cSec) {
        $is_open = ($curSec >= $oSec && $curSec <= $cSec);
    } else {
        $is_open = ($curSec >= $oSec || $curSec <= $cSec);
    }
}

$is_hospital_role = isset($_SESSION['role']) && $_SESSION['role'] === 'hospital';
$is_lab_role = isset($_SESSION['role']) && $_SESSION['role'] === 'laboratory';
$back_url = $is_hospital_role ? 'hospital_dashboard.php' : ($is_lab_role ? 'laboratory_dashboard.php' : (isset($_SESSION['role']) && $_SESSION['role'] === 'patient' ? 'dashboard_patient.php' : 'index.php'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $name ?> - Map View</title>
    <link rel="stylesheet" href="assets/css/styles.css">
    
    <!-- 🗺️ Leaflet CSS & JS (Free, Reliable OpenStreetMap) -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
    
    <style>
        #map {
            width: 100%;
            height: 480px;
            border-radius: var(--radius-lg);
            margin: 16px 0;
            box-shadow: var(--shadow);
            border: 1px solid var(--card-border);
            z-index: 1;
        }
        
        .hospital-info {
            background: white;
            padding: 24px;
            border-radius: var(--radius-lg);
            border: 1px solid var(--card-border);
            box-shadow: var(--shadow-sm);
            margin: 20px 0;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 16px;
            margin-top: 14px;
        }
        
        .info-item {
            display: flex;
            align-items: flex-start;
            gap: 10px;
        }
        
        .info-icon {
            font-size: 20px;
            line-height: 1;
            margin-top: 2px;
        }
        
        .action-buttons {
            display: flex;
            gap: 12px;
            margin-top: 20px;
            flex-wrap: wrap;
        }
        
        .action-buttons a, .action-buttons button {
            flex: 1;
            min-width: 160px;
            text-align: center;
        }
    </style>
</head>
<body>

<div class="container">
    
    <!-- 🔝 HEADER -->
    <div class="topbar">
        <div class="brand">📍 <?= $name ?> <span style="font-size:12px;opacity:0.85;background:rgba(255,255,255,0.2);padding:2px 8px;border-radius:12px;margin-left:6px;"><?= $typeIcon ?> <?= $typeLabel ?></span></div>
        <div>
            <a href="<?= $back_url ?>">⬅ Back to Dashboard</a>
            <?php if($is_hospital_role): ?>
                <a href="edit_hospital_profile.php">✏ Edit Coordinates</a>
            <?php endif; ?>
            <a href="logout.php">Logout</a>
        </div>
    </div>

    <?php if (!$has_exact_coords): ?>
    <div style="background:#eff6ff;border:1px solid #bfdbfe;border-radius:var(--radius);padding:14px 18px;margin-bottom:14px;color:#1e40af;display:flex;align-items:center;gap:12px;">
        <span style="font-size:24px;">ℹ️</span>
        <div>
            <strong>Notice:</strong> This <?= strtolower($typeLabel) ?> does not have exact GPS coordinates saved. A regional location is shown.
            <?php if ($is_hospital_role): ?>
                <br><a href="edit_hospital_profile.php" style="color:#2563eb;font-weight:700;text-decoration:underline;">Click here to set your exact latitude & longitude</a> in your profile.
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- 🗺️ MAP -->
    <div id="map"></div>

    <!-- 📋 HOSPITAL/LAB INFO -->
    <div class="hospital-info">
        <h3 class="header-title" style="margin-bottom:16px;"><?= $typeIcon ?> <?= $typeLabel ?> Information</h3>
        
        <div class="info-grid">
            <div class="info-item">
                <span class="info-icon">📍</span>
                <div>
                    <strong style="display:block;font-size:12px;color:var(--text-muted);text-transform:uppercase;">Address</strong>
                    <span><?= $address ?><?= $city ? ', ' . $city : '' ?></span>
                </div>
            </div>
            
            <div class="info-item">
                <span class="info-icon">📞</span>
                <div>
                    <strong style="display:block;font-size:12px;color:var(--text-muted);text-transform:uppercase;">Phone</strong>
                    <a href="tel:<?= $phone ?>" style="color:var(--primary);text-decoration:none;font-weight:600;"><?= $phone ?></a>
                </div>
            </div>
            
            <div class="info-item">
                <span class="info-icon">📧</span>
                <div>
                    <strong style="display:block;font-size:12px;color:var(--text-muted);text-transform:uppercase;">Email</strong>
                    <a href="mailto:<?= $email ?>" style="color:var(--primary);text-decoration:none;"><?= $email ?></a>
                </div>
            </div>
            
            <div class="info-item">
                <span class="info-icon">🕐</span>
                <div>
                    <strong style="display:block;font-size:12px;color:var(--text-muted);text-transform:uppercase;">Working Hours</strong>
                    <span>
                        <?= $open_time ?> &ndash; <?= $close_time ?>
                        <?= $is_open 
                            ? '<span style="background:#dcfce7;color:#166534;padding:2px 8px;border-radius:10px;font-size:11px;font-weight:700;margin-left:6px;">' . $open_status_label . '</span>' 
                            : '<span style="background:#fee2e2;color:#991b1b;padding:2px 8px;border-radius:10px;font-size:11px;font-weight:700;margin-left:6px;">🔴 Closed</span>' ?>
                    </span>
                </div>
            </div>
        </div>
    </div>

    <!-- 🔘 ACTION BUTTONS -->
    <div class="action-buttons">
        <a href="https://www.google.com/maps/search/?api=1&query=<?= $lat ?>,<?= $lng ?>" 
           target="_blank" class="btn" style="background:#059669;box-shadow:none;">
            🗺️ Open in Google Maps
        </a>
        <?php if ($is_lab): ?>
            <a href="lab_booking.php" class="btn" style="background:#7c3aed;box-shadow:none;">
                🧪 Book Lab Test
            </a>
        <?php else: ?>
            <a href="appointments.php" class="btn" style="background:#2563eb;box-shadow:none;">
                📅 Book Appointment
            </a>
        <?php endif; ?>
        <?php if (!empty($phone) && $phone !== 'N/A'): ?>
        <a href="tel:<?= $phone ?>" class="btn btn-secondary" style="box-shadow:none;">
            ☎️ Call <?= $typeLabel ?>
        </a>
        <?php endif; ?>
        <a href="<?= $back_url ?>" class="btn btn-gray" style="box-shadow:none;">
            ⬅ Back to Dashboard
        </a>
    </div>

</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const lat = <?= json_encode($lat) ?>;
    const lng = <?= json_encode($lng) ?>;
    const facilityName = <?= json_encode($name) ?>;
    const address = <?= json_encode($address) ?>;
    const isLab = <?= json_encode($is_lab) ?>;
    const pinColor = isLab ? '#7c3aed' : '#2563eb';
    const pinIcon = isLab ? '🧪' : '🏥';

    // Initialize Leaflet map
    const map = L.map('map', {
        scrollWheelZoom: true
    }).setView([lat, lng], 15);
    
    // Add OpenStreetMap tiles
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
    }).addTo(map);

    // Custom pulse pin
    const facilityIcon = L.divIcon({
        className: 'custom-facility-pin',
        html: `<div style="background:${pinColor};color:#ffffff;width:38px;height:38px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:20px;box-shadow:0 4px 14px rgba(0,0,0,0.3);border:3px solid white;">${pinIcon}</div>`,
        iconSize: [38, 38],
        iconAnchor: [19, 19],
        popupAnchor: [0, -22]
    });
    
    // Add marker
    const marker = L.marker([lat, lng], { icon: facilityIcon }).addTo(map);
    marker.bindPopup(`
        <div style="font-family:inherit;padding:4px;">
            <div style="font-weight:700;font-size:14px;color:#1e3a8a;margin-bottom:4px;">${pinIcon} ${facilityName}</div>
            <div style="font-size:12px;color:#475569;margin-bottom:8px;">${address}</div>
            <a href="https://www.google.com/maps/search/?api=1&query=${lat},${lng}" target="_blank" style="display:inline-block;background:${pinColor};color:#fff;padding:4px 10px;border-radius:4px;font-size:11px;font-weight:600;text-decoration:none;">🗺️ Navigate</a>
        </div>
    `).openPopup();
});
</script>

</body>
</html>
