<?php
require 'config.php';
ensure_logged_in();
ensure_role('laboratory');

$lab_id = $_SESSION['user_id'];
$user = current_user($mysqli);

/* ================= HANDLE ACCEPT / REJECT ================= */
if (isset($_GET['action']) && isset($_GET['appt_id'])) {
    $action = $_GET['action'];
    $appt_id = (int)$_GET['appt_id'];
    
    if (in_array($action, ['accepted', 'rejected'])) {
        $stmt = $mysqli->prepare("UPDATE lab_appointments SET status = ? WHERE id = ? AND lab_id = ?");
        $stmt->bind_param("sii", $action, $appt_id, $lab_id);
        if ($stmt->execute()) {
            $get_pt = $mysqli->prepare("SELECT patient_id, test_name FROM lab_appointments WHERE id = ?");
            $get_pt->bind_param("i", $appt_id);
            $get_pt->execute();
            if ($pt_row = $get_pt->get_result()->fetch_assoc()) {
                $msg = "Your lab appointment for " . $pt_row['test_name'] . " has been " . $action . " by the laboratory.";
                $notif = $mysqli->prepare("INSERT INTO notifications (user_id, message) VALUES (?, ?)");
                $notif->bind_param("is", $pt_row['patient_id'], $msg);
                $notif->execute();
            }
        }
        header("Location: laboratory_dashboard.php");
        exit;
    }
}

/* ================= LAB APPOINTMENTS ================= */
$stmt = $mysqli->prepare("
    SELECT 
        la.*,
        u.name AS patient_name,
        u.email AS patient_email,
        u.phone AS patient_phone,
        d.specialization
    FROM lab_appointments la
    JOIN users u ON u.id = la.patient_id
    LEFT JOIN doctor_profiles d ON u.id = d.doctor_id
    WHERE la.lab_id = ?
    ORDER BY la.id DESC
");
$stmt->bind_param("i", $lab_id);
$stmt->execute();
$lab_appointments = $stmt->get_result();

/* ================= LAB REPORTS ================= */
$stmt_reports = $mysqli->prepare("
    SELECT 
        lr.*,
        la.test_name,
        u.name AS patient_name,
        u.email AS patient_email,
        u.phone AS patient_phone
    FROM lab_reports lr
    JOIN lab_appointments la ON lr.appointment_id = la.id
    JOIN users u ON lr.patient_id = u.id
    WHERE lr.lab_id = ?
    ORDER BY lr.id DESC
");
$stmt_reports->bind_param("i", $lab_id);
$stmt_reports->execute();
$lab_reports = $stmt_reports->get_result();
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Laboratory Dashboard</title>
    <link rel="stylesheet" href="assets/css/styles.css">
    
    <style>
        body {
            font-family: Segoe UI, Tahoma, Geneva, Verdana, sans-serif;
            background: #f4f7fb;
            margin: 0;
        }
        
        .container {
            max-width: 1200px;
            margin: auto;
            padding: 15px;
        }
        
        .topbar {
            background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
            color: white;
            padding: 16px;
            border-radius: 10px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        
        .topbar h2 {
            margin: 0;
            font-size: 24px;
        }
        
        .topbar a {
            color: white;
            text-decoration: none;
            margin: 0 12px;
            transition: all 0.3s ease;
        }
        
        .topbar a:hover {
            opacity: 0.8;
            text-decoration: underline;
        }
        
        .grid-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(380px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        
        .card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
        }
        
        .card:hover {
            box-shadow: 0 8px 25px rgba(0,0,0,0.12);
            transform: translateY(-2px);
        }
        
        .card h3 {
            margin: 0 0 15px 0;
            color: #1f2937;
            font-size: 18px;
            border-bottom: 2px solid #e5e7eb;
            padding-bottom: 10px;
        }
        
        .report-item,
        .appointment-item {
            background: #f9fafb;
            padding: 16px;
            margin-bottom: 12px;
            border-radius: 8px;
            border-left: 4px solid #3b82f6;
        }
        
        .report-item:hover,
        .appointment-item:hover {
            background: #f3f4f6;
            border-left-color: #1d4ed8;
        }
        
        .patient-info {
            margin: 10px 0;
            font-size: 14px;
            color: #4b5563;
        }
        
        .patient-info strong {
            color: #1f2937;
        }
        
        .test-name {
            font-size: 16px;
            font-weight: bold;
            color: #1f2937;
            margin-bottom: 8px;
        }
        
        .status-badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: bold;
            margin-top: 8px;
        }
        
        .status-pending {
            background: #fef3c7;
            color: #92400e;
        }
        
        .status-accepted {
            background: #d1fae5;
            color: #065f46;
        }
        
        .status-completed {
            background: #dbeafe;
            color: #0c4a6e;
        }
        
        .action-buttons {
            display: flex;
            gap: 8px;
            margin-top: 12px;
            flex-wrap: wrap;
        }
        
        .btn {
            padding: 8px 14px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 13px;
            font-weight: 600;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
            text-align: center;
        }
        
        .btn-primary {
            background: #3b82f6;
            color: white;
        }
        
        .btn-primary:hover {
            background: #2563eb;
        }
        
        .btn-success {
            background: #10b981;
            color: white;
        }
        
        .btn-success:hover {
            background: #059669;
        }
        
        .btn-info {
            background: #06b6d4;
            color: white;
        }
        
        .btn-info:hover {
            background: #0891b2;
        }
        
        .btn-danger {
            background: #ef4444;
            color: white;
        }
        
        .btn-danger:hover {
            background: #dc2626;
        }
        
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #6b7280;
        }
        
        .empty-state-icon {
            font-size: 48px;
            margin-bottom: 12px;
        }
        
        .file-link {
            color: #3b82f6;
            text-decoration: none;
            font-weight: 600;
        }
        
        .file-link:hover {
            text-decoration: underline;
        }
        
        .badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            background: #ede9fe;
            color: #6d28d9;
            font-size: 11px;
            font-weight: 600;
            margin-left: 8px;
        }
        
        @media (max-width: 768px) {
            .grid-container {
                grid-template-columns: 1fr;
            }
            
            .topbar {
                flex-direction: column;
                text-align: center;
            }
            
            .action-buttons {
                flex-direction: column;
            }
            
            .btn {
                width: 100%;
            }
        }
    </style>
</head>

<body>

<div class="container">
    
    <!-- 🔝 TOPBAR -->
    <div class="topbar">
        <div>
            <h2>🧪 Laboratory Dashboard</h2>
            <p style="margin: 5px 0; font-size: 14px; opacity: 0.9;">
                Welcome, <?= htmlspecialchars($user['name']) ?>
            </p>
        </div>
        <div>
            <a href="edit_hospital_profile.php">⚙️ Settings</a>
            <a href="logout.php">🚪 Logout</a>
        </div>
    </div>

    <!-- 📊 GRID LAYOUT -->
    <div class="grid-container">
        
        <!-- 📋 LAB APPOINTMENTS SECTION -->
        <div class="card">
            <h3>📅 Lab Appointments</h3>
            
            <?php if ($lab_appointments->num_rows === 0): ?>
                <div class="empty-state">
                    <div class="empty-state-icon">📭</div>
                    <p>No lab appointments yet</p>
                </div>
            <?php else: ?>
                <?php while ($appt = $lab_appointments->fetch_assoc()): ?>
                    <div class="appointment-item">
                        <div class="test-name">🧬 <?= htmlspecialchars($appt['test_name']) ?></div>
                        
                        <div class="patient-info">
                            <strong>Patient:</strong> <?= htmlspecialchars($appt['patient_name']) ?>
                        </div>
                        
                        <div class="patient-info">
                            <strong>📧 Email:</strong> <?= htmlspecialchars($appt['patient_email']) ?>
                        </div>
                        
                        <div class="patient-info">
                            <strong>📞 Phone:</strong> <?= htmlspecialchars($appt['patient_phone']) ?>
                        </div>
                        
                        <div class="patient-info">
                            <strong>📅 Appointment Date:</strong> <?= htmlspecialchars($appt['appointment_date']) ?>
                        </div>
                        
                        <span class="status-badge status-<?= $appt['status'] ?: 'pending' ?>">
                            <?= ucfirst($appt['status'] ?: 'pending') ?>
                        </span>
                        
                        <div class="action-buttons">
                            <?php if (empty($appt['status']) || $appt['status'] === 'pending'): ?>
                                <a href="laboratory_dashboard.php?action=accepted&appt_id=<?= $appt['id'] ?>" class="btn btn-success">
                                    ✔️ Accept
                                </a>
                                <a href="laboratory_dashboard.php?action=rejected&appt_id=<?= $appt['id'] ?>" class="btn btn-danger">
                                    ❌ Reject
                                </a>
                            <?php elseif ($appt['status'] === 'accepted'): ?>
                                <a href="upload_report.php?id=<?= $appt['id'] ?>" class="btn btn-primary">
                                    📤 Upload Report
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php endif; ?>
        </div>

        <!-- 📄 LAB REPORTS SECTION -->
        <div class="card">
            <h3>📊 Lab Reports</h3>
            
            <?php if ($lab_reports->num_rows === 0): ?>
                <div class="empty-state">
                    <div class="empty-state-icon">📄</div>
                    <p>No reports uploaded yet</p>
                </div>
            <?php else: ?>
                <?php while ($report = $lab_reports->fetch_assoc()): ?>
                    <div class="report-item">
                        <div class="test-name">🔬 <?= htmlspecialchars($report['test_name']) ?></div>
                        
                        <div class="patient-info">
                            <strong>Patient:</strong> <?= htmlspecialchars($report['patient_name']) ?>
                        </div>
                        
                        <div class="patient-info">
                            <strong>📧 Email:</strong> <?= htmlspecialchars($report['patient_email']) ?>
                        </div>
                        
                        <div class="patient-info">
                            <strong>📞 Phone:</strong> <?= htmlspecialchars($report['patient_phone']) ?>
                        </div>
                        
                        <div class="patient-info">
                            <strong>📝 File:</strong> 
                            <a href="uploads/<?= htmlspecialchars($report['report_file']) ?>" 
                               target="_blank" 
                               class="file-link">
                                📥 <?= htmlspecialchars($report['report_file']) ?>
                            </a>
                        </div>
                        
                        <span class="badge">✅ Completed</span>
                        
                        <div class="action-buttons">
                            <a href="uploads/<?= htmlspecialchars($report['report_file']) ?>" 
                               target="_blank" 
                               class="btn btn-info">
                                👁️ View Report
                            </a>
                            <a href="uploads/<?= htmlspecialchars($report['report_file']) ?>" 
                               download 
                               class="btn btn-primary">
                                ⬇️ Download
                            </a>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php endif; ?>
        </div>

    </div>

</div>

</body>
</html>
