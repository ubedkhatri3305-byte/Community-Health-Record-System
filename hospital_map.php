<?php
require 'config.php';

$hospital_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$hospital_id) {
    die("❌ Hospital ID not provided");
}

/* ================= FETCH HOSPITAL ================= */
$stmt = $mysqli->prepare("
    SELECT id, name, address, phone, email, latitude, longitude, city, open_time, close_time
    FROM hospitals
    WHERE id = ?
");
$stmt->bind_param("i", $hospital_id);
$stmt->execute();
$result = $stmt->get_result();
$hospital = $result->fetch_assoc();

if (!$hospital) {
    die("❌ Hospital not found");
}

$lat = $hospital['latitude'] ?: 23.0225;  // Default: Ahmedabad, India
$lng = $hospital['longitude'] ?: 72.5714;
$name = htmlspecialchars($hospital['name']);
$address = htmlspecialchars($hospital['address']);
$phone = htmlspecialchars($hospital['phone']);
$email = htmlspecialchars($hospital['email']);
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $name ?> - Location</title>
    <link rel="stylesheet" href="assets/css/styles.css">
    <style>
        #map {
            width: 100%;
            height: 500px;
            border-radius: 8px;
            margin: 20px 0;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .hospital-info {
            background: #f3f4f6;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
        }
        
        .hospital-info h3 {
            margin: 0 0 15px 0;
            color: #1f2937;
        }
        
        .info-row {
            display: flex;
            align-items: center;
            margin: 10px 0;
            color: #374151;
        }
        
        .info-row strong {
            min-width: 120px;
            color: #1f2937;
        }
        
        .info-row a {
            color: #3b82f6;
            text-decoration: none;
        }
        
        .info-row a:hover {
            text-decoration: underline;
        }
        
        .icon {
            margin-right: 10px;
            font-size: 18px;
        }
        
        .action-buttons {
            display: flex;
            gap: 10px;
            margin-top: 20px;
            flex-wrap: wrap;
        }
        
        .action-buttons a,
        .action-buttons button {
            flex: 1;
            min-width: 150px;
            padding: 12px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            text-decoration: none;
            text-align: center;
            transition: all 0.3s ease;
        }
        
        .btn-directions {
            background: #10b981;
            color: white;
        }
        
        .btn-directions:hover {
            background: #059669;
        }
        
        .btn-back {
            background: #6b7280;
            color: white;
        }
        
        .btn-back:hover {
            background: #4b5563;
        }
        
        .btn-call {
            background: #3b82f6;
            color: white;
        }
        
        .btn-call:hover {
            background: #2563eb;
        }
        
        .status-open {
            color: #10b981;
            font-weight: bold;
        }
        
        .status-closed {
            color: #ef4444;
            font-weight: bold;
        }
        
        @media (max-width: 768px) {
            #map {
                height: 400px;
            }
            
            .action-buttons {
                flex-direction: column;
            }
            
            .action-buttons a,
            .action-buttons button {
                min-width: auto;
            }
        }
    </style>
</head>
<body>

<div class="container">
    
    <!-- 🔝 HEADER -->
    <div class="topbar">
        <div class="brand">📍 <?= $name ?></div>
        <div>
            <a id="back_btn" onclick="goBack()" style="cursor:pointer;">⬅ Back</a>
            <a href="logout.php">Logout</a>
        </div>
    </div>

    <!-- 🗺️ MAP -->
    <div id="map"></div>

    <!-- 📋 HOSPITAL INFO -->
    <div class="hospital-info">
        <h3><?= $name ?></h3>
        
        <div class="info-row">
            <span class="icon">📍</span>
            <strong>Address:</strong>
            <span><?= $address ?></span>
        </div>
        
        <div class="info-row">
            <span class="icon">📞</span>
            <strong>Phone:</strong>
            <a href="tel:<?= $phone ?>"><?= $phone ?></a>
        </div>
        
        <div class="info-row">
            <span class="icon">📧</span>
            <strong>Email:</strong>
            <a href="mailto:<?= $email ?>"><?= $email ?></a>
        </div>
        
        <div class="info-row">
            <span class="icon">🕐</span>
            <strong>Timings:</strong>
            <span><?= substr($hospital['open_time'], 0, 5) ?> - <?= substr($hospital['close_time'], 0, 5) ?></span>
        </div>
        
        <div class="info-row">
            <span class="icon">📍</span>
            <strong>Coordinates:</strong>
            <span><?= number_format($lat, 4) ?>, <?= number_format($lng, 4) ?></span>
        </div>
    </div>

    <!-- 🔘 ACTION BUTTONS -->
    <div class="action-buttons">
        <a href="https://www.google.com/maps/search/?api=1&query=<?= $lat ?>,<?= $lng ?>" 
           target="_blank" class="btn-directions">
            🗺️ Open in Google Maps
        </a>
        <a href="tel:<?= $phone ?>" class="btn-call">
            ☎️ Call Hospital
        </a>
        <button onclick="goBack()" class="btn-back" style="cursor:pointer;border:none;">
            ⬅ Back
        </button>
    </div>

</div>

<!-- 🌐 GOOGLE MAPS API (for mapping functionality) -->
<script src="https://maps.googleapis.com/maps/api/js?key=AIzaSyDummyKey123456"></script>

<script>
    // ⬅️ Back button handler
    function goBack() {
        if (window.history.length > 1) {
            window.history.back();
        } else {
            // If no history, go to dashboard
            window.location.href = 'dashboard_patient.php';
        }
    }
    
    // 🗺️ Initialize map (using Leaflet for free alternative)
    if (typeof L === 'undefined') {
        // Fallback: Load Leaflet.js if Google Maps API key is not available
        document.write('<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.min.css">');
        document.write('<script src="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.min.js"><\/script>');
        
        setTimeout(initializeMap, 500);
    } else {
        initializeMap();
    }
    
    function initializeMap() {
        const lat = <?= $lat ?>;
        const lng = <?= $lng ?>;
        const hospitalName = "<?= $name ?>";
        const address = "<?= $address ?>";

        // Create map using Leaflet
        const map = L.map('map').setView([lat, lng], 15);
        
        // Add tile layer from OpenStreetMap
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '© OpenStreetMap contributors'
        }).addTo(map);
        
        // Add marker for hospital
        const marker = L.marker([lat, lng]).addTo(map);
        marker.bindPopup(`
            <div style="font-weight: bold; margin-bottom: 5px;">📍 ${hospitalName}</div>
            <div style="font-size: 13px;">${address}</div>
        `).openPopup();
        
        // Customize marker icon
        marker.setIcon(L.icon({
            iconUrl: 'https://cdn-icons-png.flaticon.com/512/1524/1524879.png',
            iconSize: [30, 30],
            popupAnchor: [0, -15]
        }));
    }
    
    // ⬅️ Directions link
    function openDirections() {
        const lat = <?= $lat ?>;
        const lng = <?= $lng ?>;
        window.open(`https://www.google.com/maps/search/?api=1&query=${lat},${lng}`, '_blank');
    }
</script>

</body>
</html>
