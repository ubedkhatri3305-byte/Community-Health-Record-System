<?php
require 'config.php';
ensure_logged_in();
?>

<!doctype html>
<html lang="en">
<head>
<title>Nearby Hospitals Map</title>
<meta name="viewport" content="width=device-width,initial-scale=1">

<link rel="stylesheet" href="assets/css/styles.css">

<style>
#map {
  width: 100%;
  height: 450px;
  border-radius: 12px;
  margin-top: 15px;
}
</style>
</head>

<body>

<div class="container">

<div class="topbar">
  <div class="brand">CHR</div>
  <div>
    <a href="dashboard_patient.php">Dashboard</a>
    <a href="logout.php">Logout</a>
  </div>
</div>

<div class="card">
<h2>Nearby Hospitals</h2>
<p>Showing hospitals near your live location</p>

<div id="map"></div>
</div>

</div>

<script>
let map, userMarker;

function initMap() {

  map = new google.maps.Map(document.getElementById("map"), {
    zoom: 13,
    center: { lat: 0, lng: 0 }
  });

  // User Location
  navigator.geolocation.getCurrentPosition(function(pos) {

    let userLoc = {
      lat: pos.coords.latitude,
      lng: pos.coords.longitude
    };

    map.setCenter(userLoc);

    userMarker = new google.maps.Marker({
      position: userLoc,
      map: map,
      icon: "https://maps.google.com/mapfiles/ms/icons/blue-dot.png",
      title: "You are here"
    });

    loadHospitals(userLoc.lat, userLoc.lng);

  }, function(){
    alert("Location access required!");
  });
}

// Load hospitals from PHP
function loadHospitals(lat, lng) {

  fetch("map_hospitals.php?lat=" + lat + "&lng=" + lng)
    .then(res => res.json())
    .then(data => {

      data.forEach(h => {
        let marker = new google.maps.Marker({
          position: { lat: parseFloat(h.latitude), lng: parseFloat(h.longitude) },
          map: map,
          title: h.name
        });

        let info = new google.maps.InfoWindow({
          content: "<b>" + h.name + "</b><br>" + h.distance + " km"
        });

        marker.addListener("click", function(){
          info.open(map, marker);
        });
      });

    });
}
</script>

<!-- ⚠️ PUT YOUR API KEY BELOW -->
<script src="https://maps.googleapis.com/maps/api/js?key=AIzaSyA_eP-O_SEWn7EiSqxarAvGGSxLlS5O_qk&callback=initMap" async defer></script>

</body>
</html>
