<?php
require 'config.php';
ensure_logged_in();
$user = current_user($mysqli);
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Nearby Hospitals - CHR</title>

<link rel="stylesheet" href="assets/css/styles.css">

<!-- 🗺️ Leaflet CSS & JS (Free, OpenStreetMap) -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>

<style>
#map {
  width: 100%;
  height: 480px;
  border-radius: var(--radius-lg);
  border: 1px solid var(--card-border);
  box-shadow: var(--shadow);
  z-index: 1;
}

.map-container {
  position: relative;
  margin-top: 16px;
}

.map-status-bar {
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 10px;
  background: white;
  padding: 12px 18px;
  border-radius: var(--radius);
  border: 1px solid var(--card-border);
  margin-bottom: 16px;
  box-shadow: var(--shadow-sm);
}

.hospital-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
  gap: 16px;
  margin-top: 20px;
}

.hospital-card {
  background: white;
  border-radius: var(--radius);
  border: 1px solid var(--card-border);
  padding: 18px;
  box-shadow: var(--shadow-sm);
  display: flex;
  flex-direction: column;
  justify-content: space-between;
  transition: transform 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease;
  cursor: pointer;
}

.hospital-card:hover {
  transform: translateY(-2px);
  border-color: var(--primary);
  box-shadow: var(--shadow-md);
}

.badge-open {
  background: #ecfdf5;
  color: #065f46;
  border: 1px solid #a7f3d0;
  padding: 2px 8px;
  border-radius: 6px;
  font-size: 11px;
  font-weight: 700;
}

.badge-closed {
  background: #fef2f2;
  color: #991b1b;
  border: 1px solid #fecaca;
  padding: 2px 8px;
  border-radius: 6px;
  font-size: 11px;
  font-weight: 700;
}

.badge-distance {
  background: #eff6ff;
  color: #1d4ed8;
  border: 1px solid #bfdbfe;
  padding: 2px 8px;
  border-radius: 6px;
  font-size: 11px;
  font-weight: 700;
}

.search-input-wrap {
  position: relative;
  max-width: 380px;
  width: 100%;
}

.search-input-wrap input {
  padding-left: 36px;
  margin-bottom: 0;
}

.search-icon {
  position: absolute;
  left: 12px;
  top: 50%;
  transform: translateY(-50%);
  color: var(--text-muted);
  font-size: 15px;
}

/* User radar pin */
.user-radar-pin {
  width: 22px;
  height: 22px;
  background: #2563eb;
  border: 3px solid #ffffff;
  border-radius: 50%;
  box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.35);
  animation: pulse-ring 2s infinite;
}

@keyframes pulse-ring {
  0% { box-shadow: 0 0 0 0 rgba(37, 99, 235, 0.6); }
  70% { box-shadow: 0 0 0 14px rgba(37, 99, 235, 0); }
  100% { box-shadow: 0 0 0 0 rgba(37, 99, 235, 0); }
}
</style>
</head>

<body>

<div class="container">

<!-- TOPBAR -->
<div class="topbar">
  <div class="brand">CHR</div>
  <div>
    <a href="dashboard_patient.php">⬅ Patient Dashboard</a>
    <a href="appointments.php">📅 Appointments</a>
    <a href="logout.php">Logout</a>
  </div>
</div>

<!-- PAGE HEADER -->
<div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:14px;margin-bottom:16px;">
  <div>
    <h2 style="text-align:left;margin-bottom:4px;color:var(--primary-dark);">🗺️ Nearby Hospitals & Medical Centers</h2>
    <p style="color:var(--text-secondary);font-size:14px;">Explore healthcare centers near your location, view contact details, and get live directions.</p>
  </div>
  
  <div class="search-input-wrap">
    <span class="search-icon">🔍</span>
    <input type="text" id="searchInput" placeholder="Search hospital name or city..." class="input" oninput="filterHospitals()">
  </div>
</div>

<!-- STATUS BAR -->
<div class="map-status-bar">
  <div id="locationStatus" style="font-size:13px;display:flex;align-items:center;gap:6px;color:var(--text-secondary);">
    <span>📡</span> <span>Detecting your location...</span>
  </div>
  <div>
    <button onclick="requestUserLocation()" class="btn" style="width:auto;padding:6px 14px;font-size:12px;background:#2563eb;border-radius:6px;box-shadow:none;">
      🎯 Refetch My Location
    </button>
  </div>
</div>

<!-- MAP -->
<div class="map-container">
  <div id="map"></div>
</div>

<!-- HOSPITALS LIST HEADING -->
<div style="margin-top:28px;display:flex;justify-content:space-between;align-items:center;">
  <h3 class="header-title" style="margin:0;">🏥 Hospitals Directory (<span id="hospitalCount">0</span>)</h3>
  <small style="color:var(--text-muted);">Click any hospital card to zoom on map</small>
</div>

<!-- HOSPITALS GRID -->
<div id="hospitalsList" class="hospital-grid">
  <div style="grid-column:1/-1;text-align:center;padding:40px;color:var(--text-muted);">
    Loading nearby hospitals...
  </div>
</div>

</div>

<script>
let map = null;
let userMarker = null;
let hospitalMarkers = [];
let allHospitals = [];
let userLocation = null;

// Default center: Gujarat / West India
const DEFAULT_CENTER = { lat: 23.0225, lng: 72.5714 };

function initLeafletMap() {
  map = L.map('map', {
    scrollWheelZoom: true
  }).setView([DEFAULT_CENTER.lat, DEFAULT_CENTER.lng], 10);

  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 19,
    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
  }).addTo(map);

  // Initial load with default coordinates
  requestUserLocation();
}

function requestUserLocation() {
  const statusEl = document.getElementById('locationStatus');
  statusEl.innerHTML = '<span>📡</span> <span>Locating your current GPS position...</span>';

  if (!navigator.geolocation) {
    statusEl.innerHTML = '<span>ℹ️</span> <span>Geolocation not supported by browser. Showing standard area.</span>';
    loadHospitals(DEFAULT_CENTER.lat, DEFAULT_CENTER.lng);
    return;
  }

  navigator.geolocation.getCurrentPosition(
    function(pos) {
      userLocation = {
        lat: pos.coords.latitude,
        lng: pos.coords.longitude
      };

      statusEl.innerHTML = '<span>📍</span> <span style="color:#059669;font-weight:600;">Live GPS location active</span>';

      // Update or add user marker
      const userIcon = L.divIcon({
        className: 'user-pin-wrapper',
        html: '<div class="user-radar-pin" title="You are here"></div>',
        iconSize: [22, 22],
        iconAnchor: [11, 11]
      });

      if (userMarker) {
        userMarker.setLatLng([userLocation.lat, userLocation.lng]);
      } else {
        userMarker = L.marker([userLocation.lat, userLocation.lng], { icon: userIcon }).addTo(map);
        userMarker.bindPopup('<b>📍 You are here</b>');
      }

      map.setView([userLocation.lat, userLocation.lng], 13);
      loadHospitals(userLocation.lat, userLocation.lng);
    },
    function(err) {
      // Gracefully handle denied or failed permission without blocking alert
      statusEl.innerHTML = '<span>ℹ️</span> <span>Location permission disabled. Showing all hospitals.</span>';
      loadHospitals(DEFAULT_CENTER.lat, DEFAULT_CENTER.lng);
    },
    { timeout: 8000, enableHighAccuracy: true }
  );
}

function loadHospitals(lat, lng) {
  fetch('map_hospitals.php?lat=' + lat + '&lng=' + lng)
    .then(res => res.json())
    .then(data => {
      allHospitals = data || [];
      renderHospitalMarkers(allHospitals);
      renderHospitalCards(allHospitals);
    })
    .catch(err => {
      console.error('Failed to load hospitals:', err);
      document.getElementById('hospitalsList').innerHTML = 
        '<div style="grid-column:1/-1;text-align:center;padding:30px;color:#dc2626;">Unable to load hospital records. Please refresh the page.</div>';
    });
}

function renderHospitalMarkers(hospitals) {
  // Clear existing markers
  hospitalMarkers.forEach(m => map.removeLayer(m));
  hospitalMarkers = [];

  const bounds = [];
  if (userLocation) {
    bounds.push([userLocation.lat, userLocation.lng]);
  }

  hospitals.forEach(h => {
    if (!h.latitude || !h.longitude) return;

    const lat = parseFloat(h.latitude);
    const lng = parseFloat(h.longitude);
    if (isNaN(lat) || isNaN(lng)) return;

    bounds.push([lat, lng]);

    const hospitalPin = L.divIcon({
      className: 'hospital-marker-pin',
      html: '<div style="background:#2563eb;color:#ffffff;width:34px;height:34px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:18px;box-shadow:0 3px 10px rgba(37,99,235,0.4);border:2px solid #ffffff;">🏥</div>',
      iconSize: [34, 34],
      iconAnchor: [17, 17],
      popupAnchor: [0, -18]
    });

    const marker = L.marker([lat, lng], { icon: hospitalPin }).addTo(map);

    const distHtml = h.distance !== null ? `<span class="badge-distance" style="margin-bottom:6px;display:inline-block;">📍 ${h.distance} km away</span><br>` : '';
    const statusHtml = h.is_open ? '<span class="badge-open">🟢 Open Now</span>' : '<span class="badge-closed">🔴 Closed</span>';

    marker.bindPopup(`
      <div style="font-family:inherit;min-width:200px;padding:4px;">
        <div style="font-weight:700;font-size:14px;color:#1e3a8a;margin-bottom:4px;">🏥 ${escapeHtml(h.name)}</div>
        ${distHtml}
        <div style="font-size:12px;color:#475569;margin-bottom:4px;">📍 ${escapeHtml(h.address || h.city || '')}</div>
        <div style="font-size:12px;color:#475569;margin-bottom:8px;">🕐 ${escapeHtml(h.open_time ? h.open_time.slice(0,5) : '09:00')} - ${escapeHtml(h.close_time ? h.close_time.slice(0,5) : '18:00')} &bull; ${statusHtml}</div>
        <div style="display:flex;gap:6px;margin-top:6px;flex-wrap:wrap;">
          <a href="hospital_map.php?id=${h.id}" style="background:#2563eb;color:#fff;padding:4px 8px;border-radius:4px;font-size:11px;font-weight:600;text-decoration:none;">View Hospital</a>
          <a href="https://www.google.com/maps/search/?api=1&query=${lat},${lng}" target="_blank" style="background:#059669;color:#fff;padding:4px 8px;border-radius:4px;font-size:11px;font-weight:600;text-decoration:none;">🗺️ Directions</a>
        </div>
      </div>
    `);

    marker._hospitalId = h.id;
    hospitalMarkers.push(marker);
  });

  // Fit bounds if we have valid coordinates
  if (bounds.length > 1) {
    map.fitBounds(bounds, { padding: [40, 40], maxZoom: 14 });
  }
}

function renderHospitalCards(hospitals) {
  const container = document.getElementById('hospitalsList');
  document.getElementById('hospitalCount').textContent = hospitals.length;

  if (hospitals.length === 0) {
    container.innerHTML = '<div style="grid-column:1/-1;text-align:center;padding:30px;color:var(--text-muted);">No hospitals match your search.</div>';
    return;
  }

  let html = '';
  hospitals.forEach(h => {
    const lat = parseFloat(h.latitude);
    const lng = parseFloat(h.longitude);
    const distText = h.distance !== null ? `<span class="badge-distance">📍 ${h.distance} km</span>` : '';
    const openBadge = h.is_open ? '<span class="badge-open">🟢 Open Now</span>' : '<span class="badge-closed">🔴 Closed</span>';
    const hours = (h.open_time && h.close_time) ? `${h.open_time.slice(0,5)} &ndash; ${h.close_time.slice(0,5)}` : '09:00 &ndash; 18:00';

    html += `
      <div class="hospital-card" onclick="focusHospital(${h.id}, ${lat}, ${lng})">
        <div>
          <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:8px;margin-bottom:8px;">
            <h4 style="margin:0;font-size:16px;color:#1e3a8a;">🏥 ${escapeHtml(h.name)}</h4>
            ${openBadge}
          </div>

          <div style="display:flex;gap:6px;align-items:center;flex-wrap:wrap;margin-bottom:8px;">
            ${distText}
            ${h.city ? `<span style="font-size:11px;background:#f1f5f9;color:#475569;padding:2px 8px;border-radius:6px;font-weight:600;">🏙️ ${escapeHtml(h.city)}</span>` : ''}
          </div>

          <p style="font-size:13px;color:#475569;margin:0 0 6px 0;line-height:1.4;">
            📍 ${escapeHtml(h.address || 'Address not listed')}
          </p>

          <p style="font-size:12px;color:var(--text-muted);margin:0 0 14px 0;">
            🕐 Timings: ${hours}
          </p>
        </div>

        <div style="display:flex;gap:8px;flex-wrap:wrap;border-top:1px solid #f1f5f9;padding-top:12px;" onclick="event.stopPropagation()">
          <button onclick="focusHospital(${h.id}, ${lat}, ${lng})" class="btn" style="flex:1;min-width:90px;padding:6px 10px;font-size:12px;background:#2563eb;color:#fff;border-radius:6px;box-shadow:none;">
            🎯 On Map
          </button>
          <a href="hospital_map.php?id=${h.id}" class="btn" style="flex:1;min-width:90px;padding:6px 10px;font-size:12px;background:#0d9488;color:#fff;border-radius:6px;box-shadow:none;text-decoration:none;">
            👁️ Details
          </a>
          ${h.phone ? `
            <a href="tel:${escapeHtml(h.phone)}" class="btn btn-gray" style="padding:6px 10px;font-size:12px;border-radius:6px;text-decoration:none;">
              📞
            </a>
          ` : ''}
        </div>
      </div>
    `;
  });

  container.innerHTML = html;
}

function focusHospital(id, lat, lng) {
  if (isNaN(lat) || isNaN(lng)) return;

  map.setView([lat, lng], 15);
  window.scrollTo({ top: document.querySelector('.map-container').offsetTop - 20, behavior: 'smooth' });

  // Find and open popup
  const marker = hospitalMarkers.find(m => m._hospitalId === id);
  if (marker) {
    marker.openPopup();
  }
}

function filterHospitals() {
  const query = (document.getElementById('searchInput').value || '').toLowerCase().trim();
  if (!query) {
    renderHospitalCards(allHospitals);
    renderHospitalMarkers(allHospitals);
    return;
  }

  const filtered = allHospitals.filter(h => {
    const nameMatch = (h.name || '').toLowerCase().includes(query);
    const cityMatch = (h.city || '').toLowerCase().includes(query);
    const addrMatch = (h.address || '').toLowerCase().includes(query);
    return nameMatch || cityMatch || addrMatch;
  });

  renderHospitalCards(filtered);
  renderHospitalMarkers(filtered);
}

function escapeHtml(str) {
  if (!str) return '';
  return String(str)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#039;');
}

document.addEventListener('DOMContentLoaded', initLeafletMap);
</script>

</body>
</html>
