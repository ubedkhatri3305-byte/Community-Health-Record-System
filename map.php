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
<title>Nearby Hospitals & Laboratories - CHR</title>

<link rel="stylesheet" href="assets/css/styles.css">

<!-- 🗺️ Leaflet CSS & JS (Free, OpenStreetMap) -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>

<style>
#map {
  width: 100%;
  height: 490px;
  border-radius: var(--radius-lg);
  border: 1px solid var(--card-border);
  box-shadow: var(--shadow);
  z-index: 1;
}

.map-container {
  position: relative;
  margin-top: 14px;
}

.map-status-bar {
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 12px;
  background: white;
  padding: 12px 18px;
  border-radius: var(--radius);
  border: 1px solid var(--card-border);
  margin-bottom: 16px;
  box-shadow: var(--shadow-sm);
}

/* Category Filter Tabs */
.facility-filter-tabs {
  display: flex;
  gap: 8px;
  flex-wrap: wrap;
  margin-bottom: 16px;
}

.filter-tab-btn {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 8px 16px;
  border-radius: 9999px;
  border: 1.5px solid var(--card-border);
  background: white;
  color: var(--text-secondary);
  font-weight: 600;
  font-size: 13px;
  cursor: pointer;
  transition: all 0.2s ease;
  user-select: none;
}

.filter-tab-btn:hover {
  border-color: #94a3b8;
  color: var(--text-main);
}

.filter-tab-btn.active-all {
  background: #1e3a8a;
  border-color: #1e3a8a;
  color: white;
  box-shadow: 0 2px 8px rgba(30, 58, 138, 0.25);
}

.filter-tab-btn.active-hospital {
  background: #2563eb;
  border-color: #2563eb;
  color: white;
  box-shadow: 0 2px 8px rgba(37, 99, 235, 0.25);
}

.filter-tab-btn.active-lab {
  background: #7c3aed;
  border-color: #7c3aed;
  color: white;
  box-shadow: 0 2px 8px rgba(124, 58, 237, 0.25);
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
  position: relative;
  overflow: hidden;
}

.hospital-card:hover {
  transform: translateY(-2px);
  box-shadow: var(--shadow-md);
}

.card-hospital {
  border-top: 4px solid #2563eb;
}

.card-lab {
  border-top: 4px solid #7c3aed;
}

.badge-type-hospital {
  background: #eff6ff;
  color: #1d4ed8;
  border: 1px solid #bfdbfe;
  padding: 3px 8px;
  border-radius: 6px;
  font-size: 11px;
  font-weight: 700;
  display: inline-flex;
  align-items: center;
  gap: 4px;
}

.badge-type-lab {
  background: #f5f3ff;
  color: #6d28d9;
  border: 1px solid #ddd6fe;
  padding: 3px 8px;
  border-radius: 6px;
  font-size: 11px;
  font-weight: 700;
  display: inline-flex;
  align-items: center;
  gap: 4px;
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
  background: #f1f5f9;
  color: #334155;
  border: 1px solid #e2e8f0;
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
  background: #0284c7;
  border: 3px solid #ffffff;
  border-radius: 50%;
  box-shadow: 0 0 0 4px rgba(2, 132, 199, 0.35);
  animation: pulse-ring 2s infinite;
}

@keyframes pulse-ring {
  0% { box-shadow: 0 0 0 0 rgba(2, 132, 199, 0.6); }
  70% { box-shadow: 0 0 0 14px rgba(2, 132, 199, 0); }
  100% { box-shadow: 0 0 0 0 rgba(2, 132, 199, 0); }
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
    <a href="appointments.php">📅 Doctor Appointments</a>
    <a href="lab_booking.php">🧪 Lab Test Booking</a>
    <a href="logout.php">Logout</a>
  </div>
</div>

<!-- PAGE HEADER -->
<div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:14px;margin-bottom:16px;">
  <div>
    <h2 style="text-align:left;margin-bottom:4px;color:var(--primary-dark);">🗺️ Healthcare Facilities Map</h2>
    <p style="color:var(--text-secondary);font-size:14px;">Locate both nearby <b>Hospitals</b> and <b>Diagnostic Laboratories</b> with live status and directions.</p>
  </div>
  
  <div class="search-input-wrap">
    <span class="search-icon">🔍</span>
    <input type="text" id="searchInput" placeholder="Search by name, city, or type..." class="input" oninput="applyFilters()">
  </div>
</div>

<!-- CATEGORY TABS -->
<div class="facility-filter-tabs">
  <button class="filter-tab-btn active-all" id="tab-all" onclick="setFacilityType('all')">
    🌐 All Facilities (<span id="countAll">0</span>)
  </button>
  <button class="filter-tab-btn" id="tab-hospital" onclick="setFacilityType('hospital')">
    🏥 Hospitals (<span id="countHospitals">0</span>)
  </button>
  <button class="filter-tab-btn" id="tab-lab" onclick="setFacilityType('laboratory')">
    🧪 Laboratories (<span id="countLabs">0</span>)
  </button>
</div>

<!-- STATUS BAR -->
<div class="map-status-bar">
  <div id="locationStatus" style="font-size:13px;display:flex;align-items:center;gap:6px;color:var(--text-secondary);">
    <span>📡</span> <span>Detecting your live GPS position...</span>
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
<div style="margin-top:28px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;">
  <h3 class="header-title" style="margin:0;">
    📍 Directory Results (<span id="facilityCount">0</span>)
  </h3>
  <small style="color:var(--text-muted);">Showing facility details &bull; Click card to zoom on map</small>
</div>

<!-- HOSPITALS GRID -->
<div id="hospitalsList" class="hospital-grid">
  <div style="grid-column:1/-1;text-align:center;padding:40px;color:var(--text-muted);">
    Loading healthcare facilities...
  </div>
</div>

</div>

<script>
let map = null;
let userMarker = null;
let facilityMarkers = [];
let allFacilities = [];
let userLocation = null;
let currentFacilityType = 'all';

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

  requestUserLocation();
}

function requestUserLocation() {
  const statusEl = document.getElementById('locationStatus');
  statusEl.innerHTML = '<span>📡</span> <span>Locating your current GPS position...</span>';

  if (!navigator.geolocation) {
    statusEl.innerHTML = '<span>ℹ️</span> <span>GPS disabled or unsupported. Showing regional centers.</span>';
    loadFacilities(DEFAULT_CENTER.lat, DEFAULT_CENTER.lng);
    return;
  }

  navigator.geolocation.getCurrentPosition(
    function(pos) {
      userLocation = {
        lat: pos.coords.latitude,
        lng: pos.coords.longitude
      };

      statusEl.innerHTML = '<span>📍</span> <span style="color:#059669;font-weight:600;">Live GPS location active</span>';

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
      loadFacilities(userLocation.lat, userLocation.lng);
    },
    function(err) {
      statusEl.innerHTML = '<span>ℹ️</span> <span>Location permission disabled. Showing all facilities.</span>';
      loadFacilities(DEFAULT_CENTER.lat, DEFAULT_CENTER.lng);
    },
    { timeout: 8000, enableHighAccuracy: true }
  );
}

function loadFacilities(lat, lng) {
  fetch('map_hospitals.php?lat=' + lat + '&lng=' + lng)
    .then(res => res.json())
    .then(data => {
      allFacilities = data || [];
      updateTabCounts();
      applyFilters();
    })
    .catch(err => {
      console.error('Failed to load facilities:', err);
      document.getElementById('hospitalsList').innerHTML = 
        '<div style="grid-column:1/-1;text-align:center;padding:30px;color:#dc2626;">Unable to load records. Please refresh the page.</div>';
    });
}

function updateTabCounts() {
  const total = allFacilities.length;
  const hospitals = allFacilities.filter(f => (f.type || 'hospital') === 'hospital').length;
  const labs = allFacilities.filter(f => (f.type || 'hospital') === 'laboratory').length;

  document.getElementById('countAll').textContent = total;
  document.getElementById('countHospitals').textContent = hospitals;
  document.getElementById('countLabs').textContent = labs;
}

function setFacilityType(type) {
  currentFacilityType = type;

  // Update tabs styling
  const tabAll = document.getElementById('tab-all');
  const tabHosp = document.getElementById('tab-hospital');
  const tabLab = document.getElementById('tab-lab');

  tabAll.className = 'filter-tab-btn' + (type === 'all' ? ' active-all' : '');
  tabHosp.className = 'filter-tab-btn' + (type === 'hospital' ? ' active-hospital' : '');
  tabLab.className = 'filter-tab-btn' + (type === 'laboratory' ? ' active-lab' : '');

  applyFilters();
}

function applyFilters() {
  const query = (document.getElementById('searchInput').value || '').toLowerCase().trim();

  const filtered = allFacilities.filter(f => {
    const fType = (f.type || 'hospital').toLowerCase();

    // Type filter
    if (currentFacilityType !== 'all' && fType !== currentFacilityType) {
      return false;
    }

    // Text search filter
    if (query) {
      const nameMatch = (f.name || '').toLowerCase().includes(query);
      const cityMatch = (f.city || '').toLowerCase().includes(query);
      const addrMatch = (f.address || '').toLowerCase().includes(query);
      const typeMatch = fType.includes(query) || (fType === 'laboratory' && query.includes('lab'));
      if (!nameMatch && !cityMatch && !addrMatch && !typeMatch) {
        return false;
      }
    }

    return true;
  });

  renderMapMarkers(filtered);
  renderFacilityCards(filtered);
}

function renderMapMarkers(facilities) {
  // Clear existing markers
  facilityMarkers.forEach(m => map.removeLayer(m));
  facilityMarkers = [];

  const bounds = [];
  if (userLocation) {
    bounds.push([userLocation.lat, userLocation.lng]);
  }

  facilities.forEach(f => {
    if (!f.latitude || !f.longitude) return;

    const lat = parseFloat(f.latitude);
    const lng = parseFloat(f.longitude);
    if (isNaN(lat) || isNaN(lng)) return;

    bounds.push([lat, lng]);

    const isLab = (f.type || 'hospital') === 'laboratory';
    const pinColor = isLab ? '#7c3aed' : '#2563eb';
    const pinIcon = isLab ? '🧪' : '🏥';
    const typeTitle = isLab ? 'Diagnostic Laboratory' : 'Hospital';
    const typeBadge = isLab 
      ? '<span class="badge-type-lab">🧪 Laboratory</span>' 
      : '<span class="badge-type-hospital">🏥 Hospital</span>';

    const actionBtn = isLab
      ? `<a href="lab_booking.php" style="background:#7c3aed;color:#fff;padding:4px 9px;border-radius:4px;font-size:11px;font-weight:600;text-decoration:none;">🧪 Book Lab Test</a>`
      : `<a href="appointments.php" style="background:#2563eb;color:#fff;padding:4px 9px;border-radius:4px;font-size:11px;font-weight:600;text-decoration:none;">📅 Book Doctor</a>`;

    const pinMarker = L.divIcon({
      className: 'facility-marker-pin',
      html: `<div style="background:${pinColor};color:#ffffff;width:34px;height:34px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:17px;box-shadow:0 3px 10px rgba(0,0,0,0.3);border:2px solid #ffffff;">${pinIcon}</div>`,
      iconSize: [34, 34],
      iconAnchor: [17, 17],
      popupAnchor: [0, -18]
    });

    const marker = L.marker([lat, lng], { icon: pinMarker }).addTo(map);

    const distHtml = f.distance !== null ? `<span class="badge-distance" style="margin-left:4px;">📍 ${f.distance} km away</span>` : '';
    const statusHtml = f.is_open ? '<span class="badge-open">🟢 Open Now</span>' : '<span class="badge-closed">🔴 Closed</span>';

    marker.bindPopup(`
      <div style="font-family:inherit;min-width:220px;padding:4px;">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:6px;">
          ${typeBadge}
          ${statusHtml}
        </div>
        <div style="font-weight:700;font-size:14px;color:#0f172a;margin-bottom:4px;">${pinIcon} ${escapeHtml(f.name)}</div>
        <div style="font-size:12px;color:#475569;margin-bottom:4px;">📍 ${escapeHtml(f.address || f.city || '')}</div>
        <div style="font-size:12px;color:#64748b;margin-bottom:8px;">
          🕐 ${escapeHtml(f.open_time ? f.open_time.slice(0,5) : '09:00')} &ndash; ${escapeHtml(f.close_time ? f.close_time.slice(0,5) : '18:00')} ${distHtml}
        </div>
        <div style="display:flex;gap:6px;margin-top:6px;flex-wrap:wrap;">
          ${actionBtn}
          <a href="hospital_map.php?id=${f.id}" style="background:#0d9488;color:#fff;padding:4px 9px;border-radius:4px;font-size:11px;font-weight:600;text-decoration:none;">📍 Details</a>
          <a href="https://www.google.com/maps/search/?api=1&query=${lat},${lng}" target="_blank" style="background:#059669;color:#fff;padding:4px 9px;border-radius:4px;font-size:11px;font-weight:600;text-decoration:none;">🗺️ Directions</a>
        </div>
      </div>
    `);

    marker._facilityId = f.id;
    facilityMarkers.push(marker);
  });

  if (bounds.length > 1) {
    map.fitBounds(bounds, { padding: [40, 40], maxZoom: 14 });
  }
}

function renderFacilityCards(facilities) {
  const container = document.getElementById('hospitalsList');
  document.getElementById('facilityCount').textContent = facilities.length;

  if (facilities.length === 0) {
    container.innerHTML = '<div style="grid-column:1/-1;text-align:center;padding:36px;color:var(--text-muted);background:#fff;border-radius:var(--radius);border:1px solid var(--card-border);">No healthcare facilities found matching your criteria.</div>';
    return;
  }

  let html = '';
  facilities.forEach(f => {
    const lat = parseFloat(f.latitude);
    const lng = parseFloat(f.longitude);
    const isLab = (f.type || 'hospital') === 'laboratory';
    const cardClass = isLab ? 'card-lab' : 'card-hospital';
    const icon = isLab ? '🧪' : '🏥';

    const typeBadge = isLab 
      ? '<span class="badge-type-lab">🧪 Diagnostic Laboratory</span>' 
      : '<span class="badge-type-hospital">🏥 Hospital</span>';

    const distBadge = f.distance !== null ? `<span class="badge-distance">📍 ${f.distance} km</span>` : '';
    const openBadge = f.is_open ? '<span class="badge-open">🟢 Open Now</span>' : '<span class="badge-closed">🔴 Closed</span>';
    const hours = (f.open_time && f.close_time) ? `${f.open_time.slice(0,5)} &ndash; ${f.close_time.slice(0,5)}` : '09:00 &ndash; 18:00';

    const primaryAction = isLab
      ? `<a href="lab_booking.php" class="btn" style="flex:1;min-width:100px;padding:6px 10px;font-size:12px;background:#7c3aed;color:#fff;border-radius:6px;box-shadow:none;text-decoration:none;">🧪 Book Lab Test</a>`
      : `<a href="appointments.php" class="btn" style="flex:1;min-width:100px;padding:6px 10px;font-size:12px;background:#2563eb;color:#fff;border-radius:6px;box-shadow:none;text-decoration:none;">📅 Book Doctor</a>`;

    html += `
      <div class="hospital-card ${cardClass}" onclick="focusFacility(${f.id}, ${lat}, ${lng})">
        <div>
          <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:8px;margin-bottom:8px;">
            ${typeBadge}
            ${openBadge}
          </div>

          <h4 style="margin:4px 0 8px 0;font-size:16px;color:#0f172a;line-height:1.3;">
            ${icon} ${escapeHtml(f.name)}
          </h4>

          <div style="display:flex;gap:6px;align-items:center;flex-wrap:wrap;margin-bottom:8px;">
            ${distBadge}
            ${f.city ? `<span style="font-size:11px;background:#f1f5f9;color:#475569;padding:2px 8px;border-radius:6px;font-weight:600;">🏙️ ${escapeHtml(f.city)}</span>` : ''}
          </div>

          <p style="font-size:13px;color:#475569;margin:0 0 6px 0;line-height:1.4;">
            📍 ${escapeHtml(f.address || 'Address not listed')}
          </p>

          <p style="font-size:12px;color:var(--text-muted);margin:0 0 14px 0;">
            🕐 Timings: ${hours}
          </p>
        </div>

        <div style="display:flex;gap:6px;flex-wrap:wrap;border-top:1px solid #f1f5f9;padding-top:12px;" onclick="event.stopPropagation()">
          <button onclick="focusFacility(${f.id}, ${lat}, ${lng})" class="btn" style="flex:1;min-width:85px;padding:6px 10px;font-size:12px;background:#0f172a;color:#fff;border-radius:6px;box-shadow:none;">
            🎯 On Map
          </button>
          ${primaryAction}
          <a href="hospital_map.php?id=${f.id}" class="btn" style="padding:6px 10px;font-size:12px;background:#0d9488;color:#fff;border-radius:6px;box-shadow:none;text-decoration:none;" title="View Details">
            📍 Details
          </a>
          ${f.phone ? `
            <a href="tel:${escapeHtml(f.phone)}" class="btn btn-gray" style="padding:6px 10px;font-size:12px;border-radius:6px;text-decoration:none;" title="Call Facility">
              📞
            </a>
          ` : ''}
        </div>
      </div>
    `;
  });

  container.innerHTML = html;
}

function focusFacility(id, lat, lng) {
  if (isNaN(lat) || isNaN(lng)) return;

  map.setView([lat, lng], 15);
  window.scrollTo({ top: document.querySelector('.map-container').offsetTop - 20, behavior: 'smooth' });

  const marker = facilityMarkers.find(m => m._facilityId === id);
  if (marker) {
    marker.openPopup();
  }
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
