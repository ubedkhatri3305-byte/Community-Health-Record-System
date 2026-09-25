<?php require 'config.php';

$msg = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $type    = $_POST['type'];
    $name    = trim($_POST['name']);
    $country = $_POST['country'];
    $state   = $_POST['state'];
    $city    = $_POST['city'];
    $phone   = $_POST['phone'];
    $latitude = isset($_POST['latitude']) ? (float)$_POST['latitude'] : NULL;
    $longitude = isset($_POST['longitude']) ? (float)$_POST['longitude'] : NULL;

    // 🔥 CONVERT TIME TO AM/PM
    function convertTo12Hour($time) {
        if (!$time) return "";
        return date("h:i A", strtotime($time));
    }

    $open  = convertTo12Hour($_POST['open_time']);
    $close = convertTo12Hour($_POST['close_time']);

    $email   = trim($_POST['email']);
    $password= $_POST['password'];

    if (!$type || !$name || !$country || !$state || !$city || !$phone || !$email || !$password) {
        $msg = "❌ Please fill all fields!";
    } else {

        $stmt = $mysqli->prepare("SELECT id FROM hospitals WHERE email=?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            $msg = "❌ Email already exists!";
        } else {

            $address = "$city, $state, $country";
            $password_hash = password_hash($password, PASSWORD_DEFAULT);

            $stmt = $mysqli->prepare("
                INSERT INTO hospitals
                (type, name, address, phone, open_time, close_time, email, password, latitude, longitude)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $stmt->bind_param("ssssssssdd",
                $type, $name, $address, $phone, $open, $close, $email, $password_hash, $latitude, $longitude
            );

            $stmt->execute();
            $msg = "✅ Registered Successfully!";
        }
    }
}
?>

<!doctype html>
<html>
<head>
<title>Register Hospital / Laboratory</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="assets/css/styles.css">
</head>

<body>

<div class="center" style="min-height:100vh">
<div class="card form">

<h2>Register Hospital / Laboratory</h2>

<?php if($msg): ?>
<div style="background:#d1fae5;color:#065f46;padding:10px;border-radius:8px;text-align:center;">
<?=htmlspecialchars($msg)?>
</div>
<?php endif; ?>

<form method="post">

<label>Register As</label>
<select name="type" class="input" required>
    <option value="">Select</option>
    <option value="hospital">Hospital</option>
    <option value="laboratory">Laboratory</option>
</select>

<input class="input" name="name" placeholder="Hospital / Lab Name" required>

<label>Email</label>
<input class="input" name="email" type="email" required>

<label>Password</label>
<input class="input" name="password" type="password" required>

<!-- LIVE LOCATION -->
<button type="button" class="btn" onclick="getLocation()">📍 Use My Location</button>

<label>Country</label>
<select name="country" id="country" class="input" required onchange="setCountry()">
    <option value="">Select Country</option>
</select>

<label>State</label>
<select name="state" id="state" class="input" required onchange="setCity()">
    <option value="">Select State</option>
</select>

<label>City</label>
<select name="city" id="city" class="input" required>
    <option value="">Select City</option>
</select>

<label>Phone</label>
<input class="input" name="phone" id="phone" required>

<!-- ✅ TIME INPUT (AUTO CONVERTED) -->
<label>Opening Time</label>
<input type="time" name="open_time" class="input">

<label>Closing Time</label>
<input type="time" name="close_time" class="input">

<!-- 📍 HIDDEN LOCATION FIELDS -->
<input type="hidden" name="latitude" id="latitude" value="">
<input type="hidden" name="longitude" id="longitude" value="">

<button class="btn" style="margin-top:10px">Save</button>

<!-- 🔙 BACK BUTTON -->
<button type="button" class="btn" onclick="history.back()" style="margin-top:10px;background:#6b7280;">
⬅ Back
</button>

</form>

</div>
</div>

<script>

// COUNTRY DATA
let locationData = {
    "India": {
        code: "+91",
        states: {
            "Gujarat": ["Ahmedabad","Surat","Nadiad","Vadodara"],
            "Maharashtra": ["Mumbai","Pune"]
        }
    }
};

// LOAD COUNTRIES
window.onload = function() {
    let country = document.getElementById("country");
    for (let c in locationData) {
        country.innerHTML += `<option value="${c}">${c}</option>`;
    }
};

// STATE
function setCountry(){
    let country = document.getElementById("country").value;
    let state = document.getElementById("state");
    let city = document.getElementById("city");
    let phone = document.getElementById("phone");

    state.innerHTML = '<option>Select State</option>';
    city.innerHTML = '<option>Select City</option>';

    if(!country) return;

    phone.value = locationData[country].code;

    for(let s in locationData[country].states){
        state.innerHTML += `<option value="${s}">${s}</option>`;
    }
}

// CITY
function setCity(){
    let country = document.getElementById("country").value;
    let state = document.getElementById("state").value;
    let city = document.getElementById("city");

    city.innerHTML = '<option>Select City</option>';

    if(!state) return;

    locationData[country].states[state].forEach(c => {
        city.innerHTML += `<option value="${c}">${c}</option>`;
    });
}

// LIVE LOCATION
function getLocation() {

    navigator.geolocation.getCurrentPosition(
        function(pos){

            let lat = pos.coords.latitude;
            let lon = pos.coords.longitude;

            // 📍 Store latitude and longitude
            document.getElementById("latitude").value = lat;
            document.getElementById("longitude").value = lon;

            fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lon}`)
            .then(res => res.json())
            .then(data => {

                let addr = data.address || {};

                let country = addr.country || "India";
                let state   = addr.state || "";
                let city    = addr.city || addr.town || addr.village || "";

                document.getElementById("country").value = country;
                setCountry();

                setTimeout(()=>{
                    document.getElementById("state").value = state;
                    setCity();

                    setTimeout(()=>{
                        let cityDropdown = document.getElementById("city");

                        let exists = [...cityDropdown.options].some(opt => opt.value === city);

                        if (!exists && city !== "") {
                            let option = new Option(city, city);
                            cityDropdown.add(option);
                        }

                        cityDropdown.value = city;

                    },300);

                },300);

                document.getElementById("phone").value = "+91";

                alert("✅ Location captured! Coordinates: " + lat.toFixed(4) + ", " + lon.toFixed(4));

            });

        },
        function(){
            alert("❌ Allow location permission!");
        }
    );
}

</script>

</body>
</html>