<?php
require 'config.php';

header('Content-Type: text/html; charset=utf-8');

$dbStatus = "Connected successfully ✅";
$dbHost = $DB_HOST ?? 'localhost';
$dbName = $DB_NAME ?? 'chr_db';

$phpTz = date_default_timezone_get();
$phpNow = date('Y-m-d H:i:s T');

$mysqlTz = 'N/A';
$mysqlNow = 'N/A';

try {
    $tzRes = $mysqli->query("SELECT @@session.time_zone AS sess_tz, NOW() AS db_now");
    if ($tzRes && $row = $tzRes->fetch_assoc()) {
        $mysqlTz = $row['sess_tz'];
        $mysqlNow = $row['db_now'];
    }
} catch (Throwable $e) {
    $mysqlTz = 'Error: ' . $e->getMessage();
}

$tablesToCheck = [
    'users' => 'Users & Staff',
    'hospitals' => 'Hospitals & Laboratories',
    'appointments' => 'Doctor Appointments',
    'lab_appointments' => 'Lab Test Bookings',
    'lab_reports' => 'Lab Reports (with file_data BLOB)',
    'records' => 'Patient Medical Records (with file_data BLOB)',
    'prescriptions' => 'Prescriptions & Medicines'
];

$tableStats = [];
foreach ($tablesToCheck as $tbl => $label) {
    try {
        $cntRes = $mysqli->query("SELECT COUNT(*) AS total FROM `$tbl`");
        $count = $cntRes ? $cntRes->fetch_assoc()['total'] : 'N/A';
        
        $hasBlob = 'N/A';
        if (in_array($tbl, ['lab_reports', 'records'])) {
            $colRes = $mysqli->query("SHOW COLUMNS FROM `$tbl` LIKE 'file_data'");
            $hasBlob = ($colRes && $colRes->num_rows > 0) ? '✅ Present' : '❌ Missing';
        }

        $tableStats[$tbl] = [
            'label' => $label,
            'count' => $count,
            'blob' => $hasBlob,
            'status' => 'OK'
        ];
    } catch (Throwable $e) {
        $tableStats[$tbl] = [
            'label' => $label,
            'count' => 0,
            'blob' => 'N/A',
            'status' => 'Error: ' . $e->getMessage()
        ];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>CHR - Database Health & Diagnostic Tool</title>
    <link rel="stylesheet" href="assets/css/styles.css">
    <style>
        .diag-card {
            max-width: 720px;
            width: 94%;
            margin: 30px auto;
            background: #ffffff;
            border-radius: 14px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.06);
            padding: 28px;
            border: 1px solid #e2e8f0;
        }
        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
            margin: 14px 0;
        }
        @media (max-width: 600px) {
            .info-grid { grid-template-columns: 1fr; }
        }
        .stat-badge {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 12px;
            font-size: 13px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 14px;
            font-size: 13px;
        }
        th, td {
            padding: 10px 12px;
            text-align: left;
            border-bottom: 1px solid #f1f5f9;
        }
        th {
            background: #f8fafc;
            color: #475569;
            font-weight: 700;
        }
    </style>
</head>
<body style="background:#f1f5f9;min-height:100vh;padding:20px 0;">

<div class="diag-card">
    <div style="display:flex;justify-content:space-between;align-items:center;border-bottom:1px solid #e2e8f0;padding-bottom:12px;margin-bottom:16px;">
        <div>
            <h2 style="margin:0;color:#0f172a;font-size:22px;">🔍 Database & System Health Diagnostics</h2>
            <small style="color:#64748b;">Community Health Record System (CHR)</small>
        </div>
        <a href="index.php" class="btn" style="padding:6px 14px;font-size:12px;text-decoration:none;">Home</a>
    </div>

    <div class="info-grid">
        <div class="stat-badge">
            <strong style="color:#475569;display:block;margin-bottom:4px;">📡 Database Connection:</strong>
            <span style="color:#16a34a;font-weight:700;"><?= $dbStatus ?></span><br>
            <small style="color:#64748b;">Host: <?= htmlspecialchars($dbHost) ?> | DB: <?= htmlspecialchars($dbName) ?></small>
        </div>
        <div class="stat-badge">
            <strong style="color:#475569;display:block;margin-bottom:4px;">🕒 Timezone Synchronization:</strong>
            <div>PHP: <strong><?= htmlspecialchars($phpTz) ?></strong> (<?= htmlspecialchars($phpNow) ?>)</div>
            <div>MySQL: <strong><?= htmlspecialchars($mysqlTz) ?></strong> (<?= htmlspecialchars($mysqlNow) ?>)</div>
        </div>
    </div>

    <h3 style="margin:18px 0 8px 0;font-size:16px;color:#1e293b;">📊 Database Tables & Storage Migration Status</h3>
    <table>
        <thead>
            <tr>
                <th>Table</th>
                <th>Description</th>
                <th>Records</th>
                <th>Blob Storage Column</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($tableStats as $table => $info): ?>
                <tr>
                    <td><code><?= htmlspecialchars($table) ?></code></td>
                    <td><?= htmlspecialchars($info['label']) ?></td>
                    <td><strong><?= htmlspecialchars((string)$info['count']) ?></strong></td>
                    <td><?= htmlspecialchars($info['blob']) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div style="margin-top:24px;text-align:center;">
        <button onclick="location.reload()" class="btn" style="background:#2563eb;padding:8px 20px;">🔄 Re-check Status</button>
    </div>
</div>

</body>
</html>
