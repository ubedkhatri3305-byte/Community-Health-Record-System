<?php
require 'config.php';
ensure_logged_in();
ensure_role('doctor');

$doctor_id = (int)$_SESSION['user_id'];
$doctor = current_user($mysqli);

// Handle stop prescription action
if (isset($_GET['stop_id'])) {
    $stop_id = (int)$_GET['stop_id'];
    try {
        $stmt = $mysqli->prepare("UPDATE prescriptions SET status='stopped' WHERE id=? AND doctor_id=?");
        $stmt->bind_param("ii", $stop_id, $doctor_id);
        $stmt->execute();
        header("Location: doctor_prescriptions.php?msg=" . urlencode("Prescription marked as stopped."));
        exit;
    } catch (Throwable $e) {}
}

$search = trim($_GET['search'] ?? '');

$sql = "
    SELECT p.*, u.name as patient_name, u.email as patient_email, u.phone as patient_phone
    FROM prescriptions p
    JOIN users u ON p.patient_id = u.id
    WHERE p.doctor_id = ?
";

if (!empty($search)) {
    $sql .= " AND (u.name LIKE ? OR p.medicine_name LIKE ?)";
}

$sql .= " ORDER BY p.id DESC";

$stmt = $mysqli->prepare($sql);
if (!empty($search)) {
    $searchWild = "%$search%";
    $stmt->bind_param("iss", $doctor_id, $searchWild, $searchWild);
} else {
    $stmt->bind_param("i", $doctor_id);
}
$stmt->execute();
$prescriptions = $stmt->get_result();

$msg = $_GET['msg'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Prescriptions Management - CHR Doctor</title>
    <link rel="stylesheet" href="assets/css/styles.css">
    <style>
        .badge-timing {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 3px 8px;
            border-radius: 6px;
            font-size: 11px;
            font-weight: 700;
        }
        .badge-mor { background: #fef3c7; color: #92400e; }
        .badge-aft { background: #ffedd5; color: #9a3412; }
        .badge-nig { background: #ede9fe; color: #5b21b6; }
    </style>
</head>
<body>

<div class="container" style="max-width:1100px;margin:auto;padding:24px 16px;">

    <!-- TOPBAR -->
    <div class="topbar">
        <div class="brand">💊 Patient Prescriptions</div>
        <div>
            <a href="add_prescription.php" class="btn" style="background:#10b981;padding:8px 16px;color:white;">+ New Prescription</a>
            <a href="dashboard_doctor.php">⬅ Dashboard</a>
            <a href="logout.php">Logout</a>
        </div>
    </div>

    <?php if($msg): ?>
        <div class="msg" style="margin-top:16px;background:#ecfdf5;color:#065f46;border:1px solid #a7f3d0;padding:12px;border-radius:8px;">
            <?= htmlspecialchars($msg) ?>
        </div>
    <?php endif; ?>

    <!-- SEARCH & CONTROLS -->
    <div class="card" style="margin-top:20px;padding:16px 20px;">
        <form method="get" style="display:flex;gap:12px;flex-wrap:wrap;align-items:center;">
            <input type="text" name="search" class="input" placeholder="Search by patient name or medicine..." value="<?= htmlspecialchars($search) ?>" style="flex:1;min-width:240px;">
            <button type="submit" class="btn">🔍 Search</button>
            <?php if($search): ?>
                <a href="doctor_prescriptions.php" class="btn" style="background:#64748b;">Clear</a>
            <?php endif; ?>
        </form>
    </div>

    <!-- PRESCRIPTION LIST -->
    <div style="margin-top:20px;">
        <?php if($prescriptions->num_rows === 0): ?>
            <div class="card" style="text-align:center;padding:40px;">
                <div style="font-size:42px;margin-bottom:12px;">💊</div>
                <h3>No prescriptions found</h3>
                <p style="color:var(--text-muted);font-size:14px;margin-bottom:20px;">Start by adding a prescription for one of your patients.</p>
                <a href="add_prescription.php" class="btn">+ Add First Prescription</a>
            </div>
        <?php else: ?>
            <div style="display:grid;grid-template-columns:repeat(auto-fill, minmax(320px, 1fr));gap:16px;">
                <?php while($p = $prescriptions->fetch_assoc()): 
                    $isActive = ($p['status'] === 'active' && strtotime($p['end_date']) >= strtotime(date('Y-m-d')));
                ?>
                    <div class="card" style="display:flex;flex-direction:column;justify-content:space-between;border-top:4px solid <?= $isActive ? 'var(--primary)' : '#94a3b8' ?>;">
                        <div>
                            <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:8px;">
                                <div>
                                    <h3 style="margin:0 0 2px 0;font-size:17px;"><?= htmlspecialchars($p['medicine_name']) ?></h3>
                                    <span style="font-size:13px;color:var(--primary);font-weight:700;">
                                        <?= htmlspecialchars($p['dosage']) ?>
                                    </span>
                                </div>
                                <span class="badge" style="background:<?= $isActive ? '#dcfce7;color:#15803d;' : '#f1f5f9;color:#64748b;' ?>">
                                    <?= $isActive ? '🟢 Active' : ucfirst($p['status']) ?>
                                </span>
                            </div>

                            <p style="font-size:13px;color:var(--text-secondary);margin:4px 0 10px 0;">
                                👤 <strong>Patient:</strong> <?= htmlspecialchars($p['patient_name']) ?>
                            </p>

                            <!-- TIMINGS -->
                            <div style="margin:10px 0;display:flex;gap:6px;flex-wrap:wrap;">
                                <?php if($p['timing_morning']): ?>
                                    <span class="badge-timing badge-mor">🌅 <?= date('h:i A', strtotime($p['morning_time'])) ?></span>
                                <?php endif; ?>
                                <?php if($p['timing_afternoon']): ?>
                                    <span class="badge-timing badge-aft">☀️ <?= date('h:i A', strtotime($p['afternoon_time'])) ?></span>
                                <?php endif; ?>
                                <?php if($p['timing_night']): ?>
                                    <span class="badge-timing badge-nig">🌙 <?= date('h:i A', strtotime($p['night_time'])) ?></span>
                                <?php endif; ?>
                                <?php if(!empty($p['every_hours'])): ?>
                                    <span class="badge-timing" style="background:#e0f2fe;color:#0369a1;">⏰ Every <?= $p['every_hours'] ?>h</span>
                                <?php endif; ?>
                            </div>

                            <div style="font-size:12px;color:var(--text-muted);margin-bottom:8px;">
                                🍽️ <?= ucwords(str_replace('_', ' ', $p['intake_relation'])) ?> &bull; 
                                📅 <?= $p['duration_days'] ?> Days (<?= $p['start_date'] ?> to <?= $p['end_date'] ?>)
                            </div>

                            <?php if(!empty($p['instructions'])): ?>
                                <div style="background:#f8fafc;padding:8px 10px;border-radius:6px;font-size:12px;color:#334155;margin-bottom:12px;border-left:3px solid #cbd5e1;">
                                    <strong>Notes:</strong> <?= htmlspecialchars($p['instructions']) ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div style="display:flex;justify-content:space-between;align-items:center;border-top:1px solid #f1f5f9;padding-top:10px;margin-top:10px;">
                            <small style="color:var(--text-muted);font-size:11px;">Created <?= substr($p['created_at'], 0, 10) ?></small>
                            <?php if($p['status'] === 'active'): ?>
                                <a href="doctor_prescriptions.php?stop_id=<?= $p['id'] ?>" 
                                   style="color:#dc2626;font-size:12px;text-decoration:none;font-weight:600;"
                                   onclick="return confirm('Stop reminders and mark this prescription completed?')">
                                    ⏹️ Stop Reminders
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        <?php endif; ?>
    </div>

</div>

</body>
</html>
