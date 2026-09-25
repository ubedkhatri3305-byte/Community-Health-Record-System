<?php
require 'config.php';

/* Run only by admin/developer – add rescheduled columns if missing */
$queries = [
    "ALTER TABLE appointments ADD COLUMN IF NOT EXISTS rescheduled_date DATE NULL DEFAULT NULL",
    "ALTER TABLE appointments ADD COLUMN IF NOT EXISTS rescheduled_time TIME NULL DEFAULT NULL",
    "ALTER TABLE appointments ADD COLUMN IF NOT EXISTS reschedule_msg TEXT NULL DEFAULT NULL",
];

foreach ($queries as $q) {
    if ($mysqli->query($q)) {
        echo "✅ OK: " . htmlspecialchars($q) . "<br>";
    } else {
        echo "❌ Error on: " . htmlspecialchars($q) . "<br>Error: " . $mysqli->error . "<br>";
    }
}

/* Also add 'rescheduled' to status ENUM if not there */
$r = $mysqli->query("SHOW COLUMNS FROM appointments LIKE 'status'");
$col = $r->fetch_assoc();
echo "<br>Current status column type: <b>" . htmlspecialchars($col['Type']) . "</b><br>";

if (strpos($col['Type'], 'rescheduled') === false) {
    /* Add rescheduled to enum */
    $res = $mysqli->query("ALTER TABLE appointments MODIFY status ENUM('pending','accepted','rejected','rescheduled') NOT NULL DEFAULT 'pending'");
    if ($res) {
        echo "✅ Added 'rescheduled' to status enum<br>";
    } else {
        echo "❌ " . $mysqli->error . "<br>";
    }
} else {
    echo "✅ 'rescheduled' already in status enum<br>";
}

echo "<br><b>Done.</b> <a href='dashboard_doctor.php'>Go to Doctor Dashboard</a>";
