<?php
require 'config.php';
ensure_logged_in();
ensure_role('doctor');

$doctor = current_user($mysqli);
$doctor_id = (int)$_SESSION['user_id'];

$preselected_patient_id = isset($_GET['patient_id']) ? (int)$_GET['patient_id'] : 0;
$appointment_id = isset($_GET['appointment_id']) ? (int)$_GET['appointment_id'] : null;

$msg = "";
$error = "";

/* Fetch patients associated with this doctor (or all patients as fallback) */
$patients_res = $mysqli->prepare("
    SELECT DISTINCT u.id, u.name, u.email, u.phone
    FROM users u
    JOIN appointments a ON u.id = a.patient_id
    WHERE a.doctor_id = ?
    ORDER BY u.name ASC
");
$patients_res->bind_param("i", $doctor_id);
$patients_res->execute();
$patients = $patients_res->get_result();

// If doctor has no appointments with patients yet, fallback to patient list
if ($patients->num_rows === 0) {
    $patients = $mysqli->query("SELECT id, name, email, phone FROM users WHERE role='patient' ORDER BY name ASC");
}

/* Handle prescription form submission */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $patient_id    = (int)($_POST['patient_id'] ?? 0);
    $medicine_name = trim($_POST['medicine_name'] ?? '');
    $dosage        = trim($_POST['dosage'] ?? '');
    $relation      = $_POST['intake_relation'] ?? 'after_meal';
    $instructions  = trim($_POST['instructions'] ?? '');
    
    $timing_morning   = isset($_POST['timing_morning']) ? 1 : 0;
    $morning_time     = !empty($_POST['morning_time']) ? date('H:i:s', strtotime($_POST['morning_time'])) : '08:00:00';

    $timing_afternoon = isset($_POST['timing_afternoon']) ? 1 : 0;
    $afternoon_time   = !empty($_POST['afternoon_time']) ? date('H:i:s', strtotime($_POST['afternoon_time'])) : '13:00:00';

    $timing_night     = isset($_POST['timing_night']) ? 1 : 0;
    $night_time       = !empty($_POST['night_time']) ? date('H:i:s', strtotime($_POST['night_time'])) : '20:00:00';

    $every_hours      = !empty($_POST['every_hours']) ? (int)$_POST['every_hours'] : null;

    $start_date    = !empty($_POST['start_date']) ? $_POST['start_date'] : date('Y-m-d');
    $duration_days = (int)($_POST['duration_days'] ?? 7);
    if ($duration_days < 1) $duration_days = 1;

    $end_date = date('Y-m-d', strtotime($start_date . " + " . ($duration_days - 1) . " days"));

    if (!$patient_id || empty($medicine_name) || empty($dosage)) {
        $error = "❌ Please select a patient and fill medicine name and dosage.";
    } elseif (!$timing_morning && !$timing_afternoon && !$timing_night && !$every_hours) {
        $error = "❌ Please select at least one reminder timing (Morning, Afternoon, or Night).";
    } else {
        try {
            $stmt = $mysqli->prepare("
                INSERT INTO prescriptions 
                (doctor_id, patient_id, appointment_id, medicine_name, dosage, 
                 timing_morning, morning_time, timing_afternoon, afternoon_time, timing_night, night_time,
                 every_hours, intake_relation, instructions, start_date, duration_days, end_date, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active')
            ");

            $stmt->bind_param(
                "iiissisisisisssis",
                $doctor_id,
                $patient_id,
                $appointment_id,
                $medicine_name,
                $dosage,
                $timing_morning,
                $morning_time,
                $timing_afternoon,
                $afternoon_time,
                $timing_night,
                $night_time,
                $every_hours,
                $relation,
                $instructions,
                $start_date,
                $duration_days,
                $end_date
            );

            if ($stmt->execute()) {
                // Prepare human-readable schedule for notification
                $slots = [];
                if ($timing_morning)   $slots[] = "Morning (" . date('h:i A', strtotime($morning_time)) . ")";
                if ($timing_afternoon) $slots[] = "Afternoon (" . date('h:i A', strtotime($afternoon_time)) . ")";
                if ($timing_night)     $slots[] = "Night (" . date('h:i A', strtotime($night_time)) . ")";
                $slotStr = implode(', ', $slots);

                $docName = $doctor['name'] ?? 'Your doctor';
                $notifMsg = "💊 Dr. {$docName} prescribed {$medicine_name} ({$dosage}) for {$duration_days} days. Medicine reminders scheduled: {$slotStr}.";

                $notif = $mysqli->prepare("INSERT INTO notifications (user_id, message) VALUES (?, ?)");
                $notif->bind_param("is", $patient_id, $notifMsg);
                @$notif->execute();

                header("Location: doctor_prescriptions.php?msg=" . urlencode("✅ Prescription for {$medicine_name} created successfully!"));
                exit;
            } else {
                $error = "❌ Failed to save prescription.";
            }
        } catch (Throwable $e) {
            $error = "❌ Database error: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Add Prescription & Medicine Schedule - CHR</title>
    <link rel="stylesheet" href="assets/css/styles.css">
    <style>
        .schedule-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));
            gap: 14px;
            margin: 14px 0;
        }
        .schedule-box {
            background: var(--bg-surface);
            border: 1.5px solid var(--border-color);
            border-radius: var(--radius-md);
            padding: 14px;
            transition: var(--transition-fast);
        }
        .schedule-box:has(input[type="checkbox"]:checked) {
            border-color: var(--primary);
            background: #f0f7ff;
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.08);
        }
        .schedule-box label {
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 700;
            cursor: pointer;
            color: var(--text-main);
            margin-bottom: 8px;
        }
        .badge-slot {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 6px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
        }
        .badge-morning { background: #fef3c7; color: #92400e; }
        .badge-afternoon { background: #ffedd5; color: #9a3412; }
        .badge-night { background: #ede9fe; color: #5b21b6; }
    </style>
</head>
<body>

<div class="container" style="max-width:850px;margin:auto;padding:24px 16px;">

    <!-- TOPBAR -->
    <div class="topbar">
        <div class="brand">💊 Add Prescription & Schedule</div>
        <div>
            <a href="dashboard_doctor.php">⬅ Dashboard</a>
            <a href="doctor_prescriptions.php">All Prescriptions</a>
        </div>
    </div>

    <?php if($error): ?>
        <div class="msg error" style="margin-top:16px;">
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <form method="post" class="card" style="margin-top:20px;padding:30px;">
        <h2 style="margin-bottom:6px;">Patient Medicine Prescription</h2>
        <p style="color:var(--text-muted);font-size:13px;margin-bottom:22px;">
            Set dosages, food instructions, and automatic reminder hours (Morning, Afternoon, Night) for your patient.
        </p>

        <!-- SELECT PATIENT -->
        <div style="margin-bottom:18px;">
            <label style="font-weight:600;font-size:13px;display:block;margin-bottom:6px;">Select Patient *</label>
            <select name="patient_id" class="input" required style="font-size:14px;">
                <option value="">-- Choose Patient --</option>
                <?php 
                $patients->data_seek(0);
                while($p = $patients->fetch_assoc()): 
                    $selected = ($p['id'] == $preselected_patient_id) ? 'selected' : '';
                ?>
                    <option value="<?= $p['id'] ?>" <?= $selected ?>>
                        👤 <?= htmlspecialchars($p['name']) ?> (<?= htmlspecialchars($p['email']) ?>)
                    </option>
                <?php endwhile; ?>
            </select>
        </div>

        <div class="flex-wrap" style="gap:16px;margin-bottom:18px;">
            <!-- MEDICINE NAME -->
            <div style="flex:2;min-width:240px;">
                <label style="font-weight:600;font-size:13px;display:block;margin-bottom:6px;">Medicine Name *</label>
                <input type="text" name="medicine_name" list="medicines_list" class="input" placeholder="e.g. Paracetamol, Amoxicillin" required>
                <datalist id="medicines_list">
                    <option value="Paracetamol 500mg">
                    <option value="Amoxicillin 500mg">
                    <option value="Azithromycin 500mg">
                    <option value="Pantoprazole 40mg">
                    <option value="Cetirizine 10mg">
                    <option value="Metformin 500mg">
                    <option value="Ibuprofen 400mg">
                    <option value="Omeprazole 20mg">
                    <option value="Atorvastatin 10mg">
                    <option value="Multivitamin Tablet">
                    <option value="Cough Syrup 10ml">
                </datalist>
            </div>

            <!-- DOSAGE -->
            <div style="flex:1;min-width:180px;">
                <label style="font-weight:600;font-size:13px;display:block;margin-bottom:6px;">Dosage *</label>
                <input type="text" name="dosage" class="input" placeholder="e.g. 1 Tablet, 5ml, 500mg" required>
            </div>
        </div>

        <!-- INTAKE RELATION -->
        <div style="margin-bottom:20px;">
            <label style="font-weight:600;font-size:13px;display:block;margin-bottom:6px;">Intake Relation (When to Take)</label>
            <div style="display:flex;gap:12px;flex-wrap:wrap;">
                <label style="display:flex;align-items:center;gap:6px;font-size:13px;cursor:pointer;">
                    <input type="radio" name="intake_relation" value="after_meal" checked> 🍽️ After Meal (Food ke baad)
                </label>
                <label style="display:flex;align-items:center;gap:6px;font-size:13px;cursor:pointer;">
                    <input type="radio" name="intake_relation" value="before_meal"> 🥗 Before Meal (Food se pehle)
                </label>
                <label style="display:flex;align-items:center;gap:6px;font-size:13px;cursor:pointer;">
                    <input type="radio" name="intake_relation" value="with_meal"> 🥪 With Meal (Khane ke sath)
                </label>
                <label style="display:flex;align-items:center;gap:6px;font-size:13px;cursor:pointer;">
                    <input type="radio" name="intake_relation" value="anytime"> ⏰ Anytime
                </label>
            </div>
        </div>

        <!-- REMINDER SCHEDULE SLOTS (MORNING, AFTERNOON, NIGHT) -->
        <div style="margin-bottom:20px;">
            <label style="font-weight:700;font-size:14px;display:block;margin-bottom:4px;color:var(--primary);">
                ⏰ Medicine Notification Schedule (Everyday Reminders)
            </label>
            <span style="font-size:12px;color:var(--text-muted);display:block;margin-bottom:10px;">
                Patient will receive notifications on their device at these times:
            </span>

            <div class="schedule-grid">
                <!-- MORNING -->
                <div class="schedule-box">
                    <label>
                        <input type="checkbox" name="timing_morning" value="1" checked id="chk_morning">
                        <span>🌅 Morning Dose</span>
                    </label>
                    <span class="badge-slot badge-morning">Morning</span>
                    <div style="margin-top:10px;">
                        <input type="time" name="morning_time" value="08:00" class="input" style="font-size:13px;padding:6px 10px;">
                    </div>
                </div>

                <!-- AFTERNOON -->
                <div class="schedule-box">
                    <label>
                        <input type="checkbox" name="timing_afternoon" value="1" checked id="chk_afternoon">
                        <span>☀️ Afternoon Dose</span>
                    </label>
                    <span class="badge-slot badge-afternoon">Afternoon</span>
                    <div style="margin-top:10px;">
                        <input type="time" name="afternoon_time" value="13:00" class="input" style="font-size:13px;padding:6px 10px;">
                    </div>
                </div>

                <!-- NIGHT -->
                <div class="schedule-box">
                    <label>
                        <input type="checkbox" name="timing_night" value="1" checked id="chk_night">
                        <span>🌙 Night Dose</span>
                    </label>
                    <span class="badge-slot badge-night">Night</span>
                    <div style="margin-top:10px;">
                        <input type="time" name="night_time" value="20:00" class="input" style="font-size:13px;padding:6px 10px;">
                    </div>
                </div>
            </div>
        </div>

        <!-- DURATION & DATES -->
        <div class="flex-wrap" style="gap:16px;margin-bottom:20px;">
            <div style="flex:1;min-width:160px;">
                <label style="font-weight:600;font-size:13px;display:block;margin-bottom:6px;">Start Date</label>
                <input type="date" name="start_date" value="<?= date('Y-m-d') ?>" class="input" required>
            </div>
            <div style="flex:1;min-width:160px;">
                <label style="font-weight:600;font-size:13px;display:block;margin-bottom:6px;">Duration (Days)</label>
                <input type="number" name="duration_days" value="7" min="1" max="180" class="input" required>
            </div>
            <div style="flex:1;min-width:160px;">
                <label style="font-weight:600;font-size:13px;display:block;margin-bottom:6px;">Or Interval (Hours)</label>
                <select name="every_hours" class="input" style="font-size:13px;">
                    <option value="">Default (3 times daily)</option>
                    <option value="4">Every 4 Hours</option>
                    <option value="6">Every 6 Hours</option>
                    <option value="8">Every 8 Hours</option>
                    <option value="12">Every 12 Hours</option>
                </select>
            </div>
        </div>

        <!-- SPECIAL INSTRUCTIONS -->
        <div style="margin-bottom:24px;">
            <label style="font-weight:600;font-size:13px;display:block;margin-bottom:6px;">Special Instructions / Doctor Notes (Optional)</label>
            <textarea name="instructions" class="input" rows="3" placeholder="e.g. Take with warm water. Avoid dairy products 1 hour before and after. Drink plenty of fluids."></textarea>
        </div>

        <!-- SUBMIT -->
        <div style="display:flex;gap:12px;align-items:center;">
            <button type="submit" class="btn" style="padding:12px 28px;font-size:15px;flex:1;">
                💾 Save Prescription & Schedule Reminders
            </button>
            <a href="dashboard_doctor.php" class="btn" style="background:#64748b;padding:12px 20px;">Cancel</a>
        </div>
    </form>

</div>

</body>
</html>
