<?php
require 'config.php';

/* 🔒 SECURITY: Allow both hospital and laboratory roles */
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['hospital', 'laboratory'])) {
    header("Location: index.php");
    exit;
}

$hospital_id = (int)$_SESSION['user_id'];
$user_role = $_SESSION['role'];
$back_url = ($user_role === 'laboratory') ? 'laboratory_dashboard.php' : 'hospital_dashboard.php';
$facility_label = ($user_role === 'laboratory') ? 'Laboratory' : 'Hospital';
$facility_icon = ($user_role === 'laboratory') ? '🧪' : '🏥';

/* 🔥 FETCH DATA */
$stmt = $mysqli->prepare("SELECT * FROM hospitals WHERE id=?");
$stmt->bind_param("i", $hospital_id);
$stmt->execute();
$data = $stmt->get_result()->fetch_assoc();

if (!$data) {
    die("Healthcare facility record not found.");
}

$msg = "";
$msg_type = "error";

/* 🔥 UPDATE PROFILE */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name      = trim($_POST['name'] ?? '');
    $email     = trim($_POST['email'] ?? '');
    $phone     = trim($_POST['phone'] ?? '');
    $address   = trim($_POST['address'] ?? '');

    $open_raw  = trim($_POST['open_time'] ?? '');
    $close_raw = trim($_POST['close_time'] ?? '');

    // Normalize time inputs (supports 8:00, 08:00, 8.00, 20:00, etc.)
    $open_time = '08:00:00';
    $close_time = '20:00:00';

    if ($open_raw !== '') {
        $open_raw_std = str_replace('.', ':', $open_raw);
        $parsed_open = strtotime($open_raw_std);
        if ($parsed_open !== false) {
            $open_time = date('H:i:s', $parsed_open);
        }
    }

    if ($close_raw !== '') {
        $close_raw_std = str_replace('.', ':', $close_raw);
        $parsed_close = strtotime($close_raw_std);
        if ($parsed_close !== false) {
            $close_time = date('H:i:s', $parsed_close);
        }
    }

    // Parse latitude and longitude
    $lat_val = trim($_POST['latitude'] ?? '');
    $lng_val = trim($_POST['longitude'] ?? '');

    $latitude = ($lat_val !== '' && is_numeric($lat_val)) ? (float)$lat_val : null;
    $longitude = ($lng_val !== '' && is_numeric($lng_val)) ? (float)$lng_val : null;

    try {
        // Check if email taken by another facility
        $chk = $mysqli->prepare("SELECT id FROM hospitals WHERE email=? AND id != ? LIMIT 1");
        $chk->bind_param("si", $email, $hospital_id);
        $chk->execute();
        if ($chk->get_result()->num_rows > 0) {
            $msg = "❌ This email address is already registered to another facility.";
            $msg_type = "error";
        } else {
            $update_stmt = $mysqli->prepare("
                UPDATE hospitals 
                SET name=?, email=?, phone=?, address=?, open_time=?, close_time=?, latitude=?, longitude=?
                WHERE id=?
            ");

            $update_stmt->bind_param(
                "ssssssddi",
                $name,
                $email,
                $phone,
                $address,
                $open_time,
                $close_time,
                $latitude,
                $longitude,
                $hospital_id
            );

            if ($update_stmt->execute()) {
                // Update session name if present
                if (isset($_SESSION['user_name'])) {
                    $_SESSION['user_name'] = $name;
                }
                header("Location: $back_url?saved=1");
                exit;
            } else {
                $msg = "❌ Failed to save changes: " . $update_stmt->error;
                $msg_type = "error";
            }
        }
    } catch (Throwable $e) {
        if ($e->getCode() == 1062 || str_contains($e->getMessage(), 'Duplicate entry')) {
            $msg = "❌ This email address is already registered to another facility.";
        } else {
            $msg = "❌ Error updating profile: " . $e->getMessage();
        }
        $msg_type = "error";
    }

    // Refresh data in memory
    $data['name'] = $name;
    $data['email'] = $email;
    $data['phone'] = $phone;
    $data['address'] = $address;
    $data['open_time'] = $open_time;
    $data['close_time'] = $close_time;
    $data['latitude'] = $latitude;
    $data['longitude'] = $longitude;
}

$defaultLat = !empty($data['latitude']) ? (float)$data['latitude'] : 23.4636;
$defaultLng = !empty($data['longitude']) ? (float)$data['longitude'] : 73.3005;
$hasCoords = !empty($data['latitude']) && !empty($data['longitude']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Edit <?= $facility_label ?> Profile - CHR</title>
    <link rel="stylesheet" href="assets/css/styles.css">
    <!-- Leaflet CSS for live map coordinate picker -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <style>
        .profile-container {
            max-width: 680px;
            width: 94%;
            margin: 30px auto;
            background: #ffffff;
            border-radius: var(--radius);
            box-shadow: 0 10px 25px rgba(0,0,0,0.06);
            border: 1px solid var(--card-border);
            padding: 32px 28px;
        }
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }
        @media (max-width: 600px) {
            .form-grid {
                grid-template-columns: 1fr;
            }
        }
        .time-box {
            display: flex;
            gap: 12px;
        }
        .label {
            font-size: 13px;
            color: var(--text-muted);
            margin: 10px 0 4px 0;
            display: block;
            font-weight: 600;
        }
        .coord-box {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 16px;
            margin: 16px 0;
        }
        #miniMap {
            height: 240px;
            width: 100%;
            border-radius: 8px;
            margin-top: 12px;
            border: 1px solid #cbd5e1;
        }
        .gps-btn {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: #ffffff;
            border: none;
            padding: 9px 16px;
            font-size: 13px;
            font-weight: 600;
            border-radius: 6px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.2s ease;
        }
        .gps-btn:hover {
            background: #047857;
            transform: translateY(-1px);
        }
        .quick-badge {
            background: #e2e8f0;
            color: #334155;
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 11px;
            cursor: pointer;
            border: 1px solid #cbd5e1;
        }
        .quick-badge:hover {
            background: #cbd5e1;
        }
    </style>
</head>

<body style="background:#f1f5f9;min-height:100vh;padding:20px 0;">

<div class="profile-container">

    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;border-bottom:1px solid #e2e8f0;padding-bottom:14px;">
        <div style="display:flex;align-items:center;gap:10px;">
            <span style="font-size:32px;"><?= $facility_icon ?></span>
            <div>
                <h2 style="margin:0;font-size:22px;color:#0f172a;">Edit <?= $facility_label ?> Profile</h2>
                <small style="color:var(--text-muted);">Update details, working hours, and GPS location coordinates</small>
            </div>
        </div>
        <a href="<?= $back_url ?>" style="font-size:13px;color:var(--primary);text-decoration:none;font-weight:600;">⬅ Back</a>
    </div>

    <?php if(!empty($msg)): ?>
        <div style="margin-bottom:16px;padding:12px;border-radius:8px;font-size:13px;background:<?= $msg_type === 'error' ? '#fee2e2;color:#991b1b;border:1px solid #fecaca;' : '#dcfce7;color:#166534;border:1px solid #bbf7d0;' ?>">
            <?= htmlspecialchars($msg) ?>
        </div>
    <?php endif; ?>

    <form method="post" id="profileForm">

        <label class="label">Facility Name *</label>
        <input type="text" name="name"
               value="<?= htmlspecialchars($data['name'] ?? '') ?>"
               placeholder="<?= $facility_label ?> Name" required class="input" style="width:100%;">

        <div class="form-grid">
            <div>
                <label class="label">Email Address *</label>
                <input type="email" name="email"
                       value="<?= htmlspecialchars($data['email'] ?? '') ?>"
                       placeholder="Email" required class="input" style="width:100%;">
            </div>
            <div>
                <label class="label">Phone Number</label>
                <input type="text" name="phone"
                       value="<?= htmlspecialchars($data['phone'] ?? '') ?>"
                       placeholder="Phone number" class="input" style="width:100%;">
            </div>
        </div>

        <label class="label">Full Physical Address</label>
        <input type="text" name="address"
               value="<?= htmlspecialchars($data['address'] ?? '') ?>"
               placeholder="Street address, city, landmark" class="input" style="width:100%;">

        <!-- 🕒 WORKING HOURS SECTION -->
        <div style="margin-top:16px;background:#f8fafc;padding:14px;border-radius:10px;border:1px solid #e2e8f0;">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
                <label class="label" style="margin:0;">🕐 Operating Hours (Working Time)</label>
                <div style="display:flex;gap:6px;">
                    <span class="quick-badge" onclick="setQuickHours('08:00', '20:00')">8 AM - 8 PM</span>
                    <span class="quick-badge" onclick="setQuickHours('09:00', '18:00')">9 AM - 6 PM</span>
                    <span class="quick-badge" onclick="setQuickHours('00:00', '00:00')">24/7 Open</span>
                </div>
            </div>
            <div class="time-box">
                <div style="flex:1;">
                    <small style="color:var(--text-muted);display:block;margin-bottom:4px;">Opening Time:</small>
                    <input type="time" name="open_time" id="open_time"
                           value="<?= htmlspecialchars(substr($data['open_time'] ?? '08:00', 0, 5)) ?>" class="input" style="width:100%;">
                </div>
                <div style="flex:1;">
                    <small style="color:var(--text-muted);display:block;margin-bottom:4px;">Closing Time:</small>
                    <input type="time" name="close_time" id="close_time"
                           value="<?= htmlspecialchars(substr($data['close_time'] ?? '20:00', 0, 5)) ?>" class="input" style="width:100%;">
                </div>
            </div>
            <small style="color:var(--text-muted);margin-top:6px;display:block;">
                💡 <em>Tip: For 24-hour round-the-clock emergency service, select "24/7 Open" (00:00 - 00:00).</em>
            </small>
        </div>

        <!-- 📍 GPS COORDINATES & LIVE LOCATION -->
        <div class="coord-box">
            <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;margin-bottom:10px;">
                <div>
                    <strong style="color:#0f172a;font-size:14px;">📍 GPS Map Coordinates</strong>
                    <div style="font-size:12px;color:var(--text-muted);">Enables patients to find you on the interactive map & get accurate driving distance</div>
                </div>
                <button type="button" class="gps-btn" id="fetchGpsBtn" onclick="fetchLiveLocation()">
                    🎯 Fetch Live GPS Location
                </button>
            </div>

            <div id="gpsStatusMsg" style="display:none;font-size:12px;padding:8px 12px;border-radius:6px;margin-bottom:10px;"></div>

            <div class="form-grid">
                <div>
                    <label class="label" style="margin-top:0;">Latitude</label>
                    <input type="number" step="any" name="latitude" id="lat_input"
                           value="<?= htmlspecialchars($data['latitude'] ?? '') ?>"
                           placeholder="e.g. 23.463600" class="input" style="width:100%;" oninput="updatePinFromInputs()">
                </div>
                <div>
                    <label class="label" style="margin-top:0;">Longitude</label>
                    <input type="number" step="any" name="longitude" id="lng_input"
                           value="<?= htmlspecialchars($data['longitude'] ?? '') ?>"
                           placeholder="e.g. 73.300500" class="input" style="width:100%;" oninput="updatePinFromInputs()">
                </div>
            </div>

            <div style="font-size:12px;color:var(--text-secondary);margin-top:8px;">
                🗺️ <em>You can also click anywhere on the map or drag the marker pin to set your exact hospital entrance location:</em>
            </div>

            <div id="miniMap"></div>
        </div>

        <div style="margin-top:24px;display:flex;gap:12px;">
            <button type="submit" class="btn" style="flex:2;padding:12px;font-size:15px;background:#2563eb;">
                💾 Save Changes & Update Profile
            </button>
            <a href="<?= $back_url ?>" class="btn btn-secondary" style="flex:1;padding:12px;text-align:center;text-decoration:none;">
                Cancel
            </a>
        </div>

    </form>

</div>

<!-- Leaflet JS -->
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
let initialLat = <?= $defaultLat ?>;
let initialLng = <?= $defaultLng ?>;
let hasExistingCoords = <?= $hasCoords ? 'true' : 'false' ?>;

const map = L.map('miniMap').setView([initialLat, initialLng], hasExistingCoords ? 15 : 13);

L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 19,
    attribution: '&copy; OpenStreetMap'
}).addTo(map);

const pinIcon = L.divIcon({
    className: 'custom-pin',
    html: '<div style="background:#2563eb;color:#fff;width:32px;height:32px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:16px;box-shadow:0 3px 8px rgba(0,0,0,0.3);border:2px solid #fff;"><?= $facility_icon ?></div>',
    iconSize: [32, 32],
    iconAnchor: [16, 16]
});

let marker = L.marker([initialLat, initialLng], {
    icon: pinIcon,
    draggable: true
}).addTo(map);

marker.bindPopup('<b><?= htmlspecialchars($data['name'] ?? $facility_label) ?></b><br>Drag me to fine-tune your location!').openPopup();

// Marker drag event
marker.on('dragend', function(e) {
    const pos = marker.getLatLng();
    setCoordinates(pos.lat, pos.lng);
});

// Map click event
map.on('click', function(e) {
    marker.setLatLng(e.latlng);
    setCoordinates(e.latlng.lat, e.latlng.lng);
});

function setCoordinates(lat, lng) {
    document.getElementById('lat_input').value = lat.toFixed(6);
    document.getElementById('lng_input').value = lng.toFixed(6);
    showGpsStatus(`📍 Coordinates updated: ${lat.toFixed(6)}, ${lng.toFixed(6)}`, 'success');
}

function updatePinFromInputs() {
    const lat = parseFloat(document.getElementById('lat_input').value);
    const lng = parseFloat(document.getElementById('lng_input').value);
    if (!isNaN(lat) && !isNaN(lng)) {
        marker.setLatLng([lat, lng]);
        map.panTo([lat, lng]);
    }
}

// 🎯 Live GPS Location Fetcher
function fetchLiveLocation() {
    const btn = document.getElementById('fetchGpsBtn');
    const originalText = btn.innerHTML;

    if (!navigator.geolocation) {
        showGpsStatus('❌ Geolocation is not supported by your browser.', 'error');
        return;
    }

    btn.innerHTML = '📡 Fetching live GPS...';
    btn.disabled = true;
    showGpsStatus('🛰️ Communicating with GPS satellites / device location sensor...', 'info');

    navigator.geolocation.getCurrentPosition(
        function(position) {
            btn.innerHTML = originalText;
            btn.disabled = false;

            const lat = position.coords.latitude;
            const lng = position.coords.longitude;
            const accuracy = position.coords.accuracy ? Math.round(position.coords.accuracy) : null;

            setCoordinates(lat, lng);
            marker.setLatLng([lat, lng]);
            map.setView([lat, lng], 16);
            marker.bindPopup(`<b>🎯 Live Location Detected!</b><br>Accuracy: ±${accuracy || 10}m`).openPopup();

            showGpsStatus(`✅ Live GPS location captured! (Accuracy: ±${accuracy || 10} meters)`, 'success');
        },
        function(error) {
            btn.innerHTML = originalText;
            btn.disabled = false;
            let errMsg = 'Could not fetch location.';
            switch(error.code) {
                case error.PERMISSION_DENIED:
                    errMsg = 'Location permission was denied. Please allow location access in your browser address bar.';
                    break;
                case error.POSITION_UNAVAILABLE:
                    errMsg = 'Location information is currently unavailable.';
                    break;
                case error.TIMEOUT:
                    errMsg = 'Location request timed out. Please try again.';
                    break;
            }
            showGpsStatus('❌ ' + errMsg, 'error');
        },
        {
            enableHighAccuracy: true,
            timeout: 10000,
            maximumAge: 0
        }
    );
}

function showGpsStatus(text, type) {
    const box = document.getElementById('gpsStatusMsg');
    box.style.display = 'block';
    box.textContent = text;
    if (type === 'success') {
        box.style.background = '#dcfce7';
        box.style.color = '#166534';
        box.style.border = '1px solid #bbf7d0';
    } else if (type === 'info') {
        box.style.background = '#e0f2fe';
        box.style.color = '#0369a1';
        box.style.border = '1px solid #bae6fd';
    } else {
        box.style.background = '#fee2e2';
        box.style.color = '#991b1b';
        box.style.border = '1px solid #fecaca';
    }
}

function setQuickHours(open, close) {
    document.getElementById('open_time').value = open;
    document.getElementById('close_time').value = close;
}
</script>

</body>
</html>