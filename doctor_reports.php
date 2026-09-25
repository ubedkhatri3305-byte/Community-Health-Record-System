<?php
require 'config.php';
ensure_logged_in();
ensure_role('doctor');

$doctor_id = $_SESSION['user_id'];
$user = current_user($mysqli);

/* ================= GET ALL PATIENTS FOR THIS DOCTOR ================= */
$stmt = $mysqli->prepare("
    SELECT DISTINCT
        u.id,
        u.name,
        u.email,
        u.phone,
        COUNT(lr.id) as total_reports
    FROM users u
    JOIN appointments a ON u.id = a.patient_id
    LEFT JOIN lab_reports lr ON u.id = lr.patient_id
    WHERE a.doctor_id = ? AND a.status = 'accepted'
    GROUP BY u.id
    ORDER BY u.name ASC
");
$stmt->bind_param("i", $doctor_id);
$stmt->execute();
$patients = $stmt->get_result();
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Patient Reports - Doctor View</title>
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
        }
        
        .topbar h2 {
            margin: 0;
            font-size: 24px;
        }
        
        .topbar a {
            color: white;
            text-decoration: none;
            margin: 0 8px;
        }
        
        .topbar a:hover {
            text-decoration: underline;
        }
        
        .search-box {
            margin: 20px 0;
        }
        
        .search-box input {
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 8px;
            width: 100%;
            max-width: 400px;
            font-size: 14px;
        }
        
        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        
        .patient-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
            cursor: pointer;
            border-left: 4px solid #3b82f6;
        }
        
        .patient-card:hover {
            box-shadow: 0 8px 25px rgba(0,0,0,0.12);
            transform: translateY(-3px);
        }
        
        .patient-name {
            font-size: 18px;
            font-weight: bold;
            color: #1f2937;
            margin-bottom: 10px;
        }
        
        .patient-info {
            font-size: 13px;
            color: #6b7280;
            margin: 6px 0;
        }
        
        .patient-info strong {
            color: #4b5563;
        }
        
        .report-count {
            display: inline-block;
            background: #dbeafe;
            color: #0c4a6e;
            padding: 8px 12px;
            border-radius: 6px;
            font-weight: bold;
            font-size: 14px;
            margin-top: 10px;
        }
        
        .btn {
            padding: 10px 16px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn-primary {
            background: #3b82f6;
            color: white;
        }
        
        .btn-primary:hover {
            background: #2563eb;
        }
        
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            overflow-y: auto;
        }
        
        .modal-content {
            background: white;
            margin: 20px auto;
            padding: 30px;
            border-radius: 10px;
            max-width: 600px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            border-bottom: 2px solid #e5e7eb;
            padding-bottom: 15px;
        }
        
        .modal-header h3 {
            margin: 0;
            color: #1f2937;
        }
        
        .close-btn {
            background: none;
            border: none;
            font-size: 28px;
            cursor: pointer;
            color: #6b7280;
        }
        
        .close-btn:hover {
            color: #1f2937;
        }
        
        .report-list {
            margin-top: 20px;
        }
        
        .report-item {
            background: #f9fafb;
            padding: 16px;
            margin-bottom: 12px;
            border-radius: 8px;
            border-left: 4px solid #10b981;
        }
        
        .report-test {
            font-size: 16px;
            font-weight: bold;
            color: #1f2937;
            margin-bottom: 8px;
        }
        
        .report-file {
            margin: 10px 0;
        }
        
        .report-file a {
            color: #3b82f6;
            text-decoration: none;
            font-weight: 600;
        }
        
        .report-file a:hover {
            text-decoration: underline;
        }
        
        .empty-state {
            text-align: center;
            padding: 40px;
            color: #6b7280;
            background: white;
            border-radius: 10px;
        }
        
        .empty-state-icon {
            font-size: 48px;
            margin-bottom: 15px;
        }
        
        @media (max-width: 768px) {
            .grid {
                grid-template-columns: 1fr;
            }
            
            .modal-content {
                margin: 10px;
                padding: 20px;
            }
        }
    </style>
</head>

<body>

<div class="container">
    
    <!-- 🔝 TOPBAR -->
    <div class="topbar">
        <div>
            <h2>📋 Patient Reports</h2>
            <p style="margin: 5px 0; font-size: 14px; opacity: 0.9;">
                View your patients' lab reports
            </p>
        </div>
        <div>
            <a href="dashboard_doctor.php">← Back to Dashboard</a>
            <a href="logout.php">Logout</a>
        </div>
    </div>

    <!-- 🔍 SEARCH -->
    <div class="search-box">
        <input 
            type="text" 
            id="searchInput" 
            placeholder="Search patient by name or email..."
            onkeyup="filterPatients()">
    </div>

    <!-- 👥 PATIENTS GRID -->
    <div class="grid" id="patientsGrid">
        <?php if ($patients->num_rows === 0): ?>
            <div style="grid-column: 1 / -1;">
                <div class="empty-state">
                    <div class="empty-state-icon">👥</div>
                    <p>No patients found. Once you have accepted appointments, patients will appear here.</p>
                </div>
            </div>
        <?php else: ?>
            <?php while ($patient = $patients->fetch_assoc()): ?>
                <div class="patient-card" data-name="<?= strtolower($patient['name']) ?>" data-email="<?= strtolower($patient['email']) ?>">
                    <div class="patient-name">👤 <?= htmlspecialchars($patient['name']) ?></div>
                    
                    <div class="patient-info">
                        <strong>📧 Email:</strong> <?= htmlspecialchars($patient['email']) ?>
                    </div>
                    
                    <div class="patient-info">
                        <strong>📞 Phone:</strong> <?= htmlspecialchars($patient['phone']) ?>
                    </div>
                    
                    <div class="report-count">
                        📄 <?= $patient['total_reports'] ?> Report<?= $patient['total_reports'] !== 1 ? 's' : '' ?>
                    </div>
                    
                    <button class="btn btn-primary" style="margin-top: 12px; width: 100%;" 
                            onclick="viewPatientReports(<?= $patient['id'] ?>, '<?= htmlspecialchars($patient['name']) ?>')">
                        View Reports
                    </button>
                </div>
            <?php endwhile; ?>
        <?php endif; ?>
    </div>

</div>

<!-- 📋 MODAL FOR REPORTS -->
<div id="reportsModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="modalTitle">Patient Reports</h3>
            <button class="close-btn" onclick="closeModal()">&times;</button>
        </div>
        <div id="reportsList" class="report-list"></div>
    </div>
</div>

<script>
function filterPatients() {
    const input = document.getElementById('searchInput').value.toLowerCase();
    const cards = document.querySelectorAll('.patient-card');
    
    cards.forEach(card => {
        const name = card.dataset.name;
        const email = card.dataset.email;
        
        if (name.includes(input) || email.includes(input)) {
            card.style.display = '';
        } else {
            card.style.display = 'none';
        }
    });
}

function viewPatientReports(patientId, patientName) {
    const modal = document.getElementById('reportsModal');
    const reportsList = document.getElementById('reportsList');
    document.getElementById('modalTitle').textContent = `Reports for ${patientName}`;
    
    // Fetch reports via AJAX
    fetch('get_patient_reports.php?patient_id=' + patientId)
        .then(res => res.json())
        .then(data => {
            if (data.reports && data.reports.length > 0) {
                reportsList.innerHTML = '';
                data.reports.forEach(report => {
                    reportsList.innerHTML += `
                        <div class="report-item">
                            <div class="report-test">🔬 ${report.test_name}</div>
                            <div style="font-size: 12px; color: #6b7280;">
                                📅 ${report.uploaded_at || 'N/A'}
                            </div>
                            <div class="report-file">
                                <a href="uploads/${report.report_file}" target="_blank">
                                    📥 Download Report
                                </a> | 
                                <a href="uploads/${report.report_file}" target="_blank">
                                    👁️ View Report
                                </a>
                            </div>
                        </div>
                    `;
                });
            } else {
                reportsList.innerHTML = '<div style="text-align: center; padding: 20px; color: #6b7280;">No reports available</div>';
            }
            modal.style.display = 'block';
        });
}

function closeModal() {
    document.getElementById('reportsModal').style.display = 'none';
}

window.onclick = function(event) {
    const modal = document.getElementById('reportsModal');
    if (event.target === modal) {
        modal.style.display = 'none';
    }
}
</script>

</body>
</html>
