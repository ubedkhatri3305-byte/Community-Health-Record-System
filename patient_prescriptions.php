<?php
require 'config.php';
ensure_logged_in();
ensure_role('patient');

$patient_id = (int)$_SESSION['user_id'];
$user = current_user($mysqli);

/* Fetch active and past prescriptions for this patient */
$query = "
    SELECT p.*, 
           u.name as doctor_name, 
           u.phone as doctor_phone,
           u.email as doctor_email,
           COALESCE(dp.specialization, u.specialization, 'Medical Specialist') as specialization,
           h.name as hospital_name
    FROM prescriptions p
    JOIN users u ON p.doctor_id = u.id
    LEFT JOIN doctor_profiles dp ON u.id = dp.doctor_id
    LEFT JOIN hospitals h ON u.hospital_id = h.id
    WHERE p.patient_id = ?
    ORDER BY p.status ASC, p.id DESC
";

$stmt = $mysqli->prepare($query);
$stmt->bind_param("i", $patient_id);
$stmt->execute();
$res = $stmt->get_result();

$active_prescriptions = [];
$past_prescriptions = [];
$today = date('Y-m-d');

while ($row = $res->fetch_assoc()) {
    $isActive = ($row['status'] === 'active' && strtotime($row['end_date']) >= strtotime($today));
    if ($isActive) {
        $active_prescriptions[] = $row;
    } else {
        $past_prescriptions[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>My Prescriptions & Medicine Reminders - CHR</title>
    <link rel="stylesheet" href="assets/css/styles.css">
    <style>
        .reminder-banner {
            background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%);
            border: 1.5px solid #bfdbfe;
            border-radius: var(--radius-lg);
            padding: 22px;
            margin-top: 20px;
            box-shadow: 0 4px 15px rgba(37, 99, 235, 0.08);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 16px;
        }
        .dose-pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 14px;
            border-radius: 9999px;
            font-size: 13px;
            font-weight: 700;
            border: 1px solid transparent;
            transition: all 0.2s ease;
        }
        .dose-morning { background: #fef3c7; color: #92400e; border-color: #fde68a; }
        .dose-afternoon { background: #ffedd5; color: #9a3412; border-color: #fed7aa; }
        .dose-night { background: #ede9fe; color: #5b21b6; border-color: #ddd6fe; }
        
        .medicine-card {
            border-left: 5px solid var(--primary);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        .medicine-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(0,0,0,0.08);
        }

        /* Reminder Alert Modal */
        .reminder-modal {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, 0.65);
            backdrop-filter: blur(5px);
            z-index: 9999;
            align-items: center;
            justify-content: center;
            padding: 16px;
        }
        .reminder-modal.active {
            display: flex;
        }
        .reminder-modal-box {
            background: white;
            border-radius: 20px;
            max-width: 440px;
            width: 100%;
            padding: 28px;
            text-align: center;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            animation: popIn 0.3s cubic-bezier(0.16, 1, 0.3, 1);
        }
        @keyframes popIn {
            from { transform: scale(0.92); opacity: 0; }
            to { transform: scale(1); opacity: 1; }
        }
    </style>
</head>
<body>

<div class="container" style="max-width:1050px;margin:auto;padding:24px 16px;">

    <!-- TOPBAR -->
    <div class="topbar">
        <div class="brand">💊 My Prescriptions & Medicine Reminders</div>
        <div>
            <a href="dashboard_patient.php">⬅ Dashboard</a>
            <a href="notification.php">Notifications</a>
            <a href="logout.php">Logout</a>
        </div>
    </div>

    <!-- NOTIFICATION PERMISSION BANNER -->
    <div class="reminder-banner">
        <div style="flex:1;min-width:280px;">
            <div style="display:flex;align-items:center;gap:8px;margin-bottom:6px;">
                <span style="font-size:24px;">🔔</span>
                <h3 style="margin:0;color:#1e3a8a;">Daily Medicine Reminders</h3>
                <span id="perm-badge" class="badge" style="background:#e2e8f0;color:#475569;">Checking...</span>
            </div>
            <p style="margin:0;font-size:13px;color:#3b82f6;line-height:1.5;">
                Allow notifications so your device can chime and notify you in the <strong>Morning (8:00 AM)</strong>, <strong>Afternoon (1:00 PM)</strong>, and <strong>Night (8:00 PM)</strong> whenever it's time to take your medicine!
            </p>
        </div>
        <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
            <button id="btn-enable-notif" class="btn" style="background:#2563eb;padding:10px 18px;font-size:14px;" onclick="enableNotifications()">
                🔔 Allow Notifications
            </button>
            <button class="btn" style="background:#059669;padding:10px 16px;font-size:14px;" onclick="testReminder()">
                🔊 Test Reminder
            </button>
        </div>
    </div>

    <!-- ACTIVE MEDICATIONS SECTION -->
    <div style="margin-top:28px;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
            <h2 style="font-size:20px;margin:0;">Active Medication Schedule (<?= count($active_prescriptions) ?>)</h2>
            <span style="font-size:13px;color:var(--text-muted);">Today is <?= date('l, d M Y') ?></span>
        </div>

        <?php if(empty($active_prescriptions)): ?>
            <div class="card" style="text-align:center;padding:48px 24px;">
                <div style="font-size:48px;margin-bottom:12px;">🌿</div>
                <h3>No active prescriptions right now</h3>
                <p style="color:var(--text-muted);font-size:14px;max-width:480px;margin:auto;">
                    When your doctor adds a medicine prescription, it will appear here with automated Morning, Afternoon, and Night reminders.
                </p>
                <a href="find_doctor.php" class="btn" style="margin-top:16px;display:inline-block;">🔍 Consult a Doctor</a>
            </div>
        <?php else: ?>
            <div style="display:grid;grid-template-columns:repeat(auto-fill, minmax(320px, 1fr));gap:18px;">
                <?php foreach($active_prescriptions as $p): 
                    $relationLabel = [
                        'after_meal'  => '🍽️ Take After Meal',
                        'before_meal' => '🥗 Take Before Meal',
                        'with_meal'   => '🥪 Take With Meal',
                        'anytime'     => '⏰ Take Anytime'
                    ][$p['intake_relation']] ?? '🍽️ After Meal';

                    $daysLeft = (int)ceil((strtotime($p['end_date']) - strtotime($today)) / 86400) + 1;
                    if ($daysLeft < 1) $daysLeft = 1;
                ?>
                    <div class="card medicine-card">
                        <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:10px;">
                            <div>
                                <h3 style="margin:0 0 4px 0;font-size:18px;color:var(--text-main);">
                                    💊 <?= htmlspecialchars($p['medicine_name']) ?>
                                </h3>
                                <span style="font-size:14px;font-weight:700;color:var(--primary);">
                                    <?= htmlspecialchars($p['dosage']) ?>
                                </span>
                            </div>
                            <span class="badge" style="background:#dcfce7;color:#15803d;">
                                Active (<?= $daysLeft ?>d left)
                            </span>
                        </div>

                        <!-- DOCTOR INFO -->
                        <div style="font-size:12px;color:var(--text-muted);margin-bottom:12px;">
                            Prescribed by <strong>Dr. <?= htmlspecialchars($p['doctor_name']) ?></strong> 
                            (<?= htmlspecialchars($p['specialization']) ?>)
                        </div>

                        <!-- INTAKE INSTRUCTION BADGE -->
                        <div style="margin-bottom:14px;">
                            <span style="display:inline-block;background:#f1f5f9;color:#334155;padding:4px 10px;border-radius:6px;font-size:12px;font-weight:600;">
                                <?= $relationLabel ?>
                            </span>
                        </div>

                        <!-- REMINDER DOSE BADGES -->
                        <div style="margin-bottom:14px;">
                            <div style="font-size:11px;font-weight:700;color:var(--text-muted);text-transform:uppercase;margin-bottom:6px;">
                                Daily Dose Timings:
                            </div>
                            <div style="display:flex;gap:8px;flex-wrap:wrap;">
                                <?php if($p['timing_morning']): ?>
                                    <span class="dose-pill dose-morning">
                                        🌅 Morning &bull; <?= date('h:i A', strtotime($p['morning_time'])) ?>
                                    </span>
                                <?php endif; ?>
                                <?php if($p['timing_afternoon']): ?>
                                    <span class="dose-pill dose-afternoon">
                                        ☀️ Afternoon &bull; <?= date('h:i A', strtotime($p['afternoon_time'])) ?>
                                    </span>
                                <?php endif; ?>
                                <?php if($p['timing_night']): ?>
                                    <span class="dose-pill dose-night">
                                        🌙 Night &bull; <?= date('h:i A', strtotime($p['night_time'])) ?>
                                    </span>
                                <?php endif; ?>
                                <?php if(!empty($p['every_hours'])): ?>
                                    <span class="dose-pill" style="background:#e0f2fe;color:#0284c7;">
                                        ⏰ Every <?= $p['every_hours'] ?>h
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <?php if(!empty($p['instructions'])): ?>
                            <div style="background:#f8fafc;border-left:3px solid #cbd5e1;padding:8px 12px;border-radius:6px;font-size:12px;color:#475569;margin-bottom:12px;">
                                <strong>Doctor's Advice:</strong> <?= htmlspecialchars($p['instructions']) ?>
                            </div>
                        <?php endif; ?>

                        <div style="border-top:1px solid #f1f5f9;padding-top:10px;display:flex;justify-content:space-between;align-items:center;font-size:11px;color:var(--text-muted);">
                            <span>📅 Course: <?= $p['duration_days'] ?> Days</span>
                            <span>Valid till <?= date('d M Y', strtotime($p['end_date'])) ?></span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- PAST MEDICATIONS SECTION -->
    <?php if(!empty($past_prescriptions)): ?>
        <div style="margin-top:36px;">
            <h3 style="font-size:18px;margin-bottom:14px;color:var(--text-secondary);">Past Prescriptions History</h3>
            <div style="display:grid;grid-template-columns:repeat(auto-fill, minmax(300px, 1fr));gap:14px;">
                <?php foreach($past_prescriptions as $p): ?>
                    <div class="card" style="opacity:0.85;border-left:4px solid #94a3b8;">
                        <div style="display:flex;justify-content:space-between;">
                            <b><?= htmlspecialchars($p['medicine_name']) ?> (<?= htmlspecialchars($p['dosage']) ?>)</b>
                            <span class="badge" style="background:#f1f5f9;color:#64748b;">Completed</span>
                        </div>
                        <div style="font-size:12px;color:var(--text-muted);margin:6px 0;">
                            Dr. <?= htmlspecialchars($p['doctor_name']) ?> &bull; Ended <?= $p['end_date'] ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

</div>

<!-- REAL-TIME REMINDER MODAL -->
<div class="reminder-modal" id="reminderModal">
    <div class="reminder-modal-box">
        <div style="font-size:52px;margin-bottom:8px;animation:pulse 1s infinite;">💊</div>
        <h2 style="margin:0 0 6px 0;color:#1e3a8a;" id="modalMedTitle">Medicine Reminder!</h2>
        <p style="font-size:14px;color:var(--text-secondary);margin-bottom:16px;" id="modalMedDesc">
            It is time to take your scheduled dose.
        </p>
        <div style="background:#eff6ff;padding:12px;border-radius:10px;margin-bottom:20px;font-size:13px;color:#1e40af;" id="modalMedSlots">
            🌅 Morning Dose &bull; After Meal
        </div>
        <div style="display:flex;gap:10px;justify-content:center;">
            <button class="btn" style="background:#10b981;padding:10px 24px;font-size:14px;" onclick="acknowledgeReminder()">
                ✔ Mark as Taken
            </button>
            <button class="btn" style="background:#64748b;padding:10px 20px;font-size:14px;" onclick="closeModal()">
                Close
            </button>
        </div>
    </div>
</div>

<!-- LIVE MEDICINE REMINDER JAVASCRIPT ENGINE -->
<script>
// Active prescription schedule from database
const prescriptions = <?= json_encode($active_prescriptions) ?>;

// Web Audio API Synthesizer Chime (Gentle medical notification bell)
function playMedicineChime() {
    try {
        const AudioContext = window.AudioContext || window.webkitAudioContext;
        if (!AudioContext) return;
        const ctx = new AudioContext();

        const now = ctx.currentTime;
        // Primary chime tone
        const osc1 = ctx.createOscillator();
        const gain1 = ctx.createGain();
        osc1.type = 'sine';
        osc1.frequency.setValueAtTime(587.33, now); // D5
        osc1.frequency.exponentialRampToValueAtTime(880.00, now + 0.3); // A5
        gain1.gain.setValueAtTime(0.3, now);
        gain1.gain.exponentialRampToValueAtTime(0.001, now + 0.8);
        osc1.connect(gain1);
        gain1.connect(ctx.destination);
        osc1.start(now);
        osc1.stop(now + 0.8);

        // Secondary chime harmonic
        const osc2 = ctx.createOscillator();
        const gain2 = ctx.createGain();
        osc2.type = 'triangle';
        osc2.frequency.setValueAtTime(880.00, now + 0.15);
        gain2.gain.setValueAtTime(0.2, now + 0.15);
        gain2.gain.exponentialRampToValueAtTime(0.001, now + 0.9);
        osc2.connect(gain2);
        gain2.connect(ctx.destination);
        osc2.start(now + 0.15);
        osc2.stop(now + 0.9);
    } catch(e) {
        console.log('Audio chime error:', e);
    }
}

// Update Permission Badge
function updatePermissionStatus() {
    const badge = document.getElementById('perm-badge');
    const btn = document.getElementById('btn-enable-notif');

    if (!('Notification' in window)) {
        badge.textContent = 'Not supported';
        badge.style.background = '#fee2e2';
        badge.style.color = '#991b1b';
        btn.style.display = 'none';
        return;
    }

    if (Notification.permission === 'granted') {
        badge.textContent = '✅ Reminders Active';
        badge.style.background = '#dcfce7';
        badge.style.color = '#15803d';
        btn.textContent = '✅ Notifications Enabled';
        btn.style.background = '#10b981';
        btn.disabled = true;
    } else if (Notification.permission === 'denied') {
        badge.textContent = '❌ Blocked in Browser';
        badge.style.background = '#fee2e2';
        badge.style.color = '#991b1b';
        btn.textContent = '⚠️ Blocked (Enable in Settings)';
    } else {
        badge.textContent = '⚠️ Not allowed yet';
        badge.style.background = '#fef3c7';
        badge.style.color = '#92400e';
        btn.textContent = '🔔 Allow Notifications';
    }
}

// Request Notification Permission
function enableNotifications() {
    if (!('Notification' in window)) {
        alert('Your browser does not support desktop notifications.');
        return;
    }

    Notification.requestPermission().then(permission => {
        updatePermissionStatus();
        if (permission === 'granted') {
            testReminder();
        } else if (permission === 'denied') {
            alert('Notifications were blocked. Please click the padlock or settings icon in your browser URL bar to allow notifications for medicine reminders.');
        }
    });
}

// Test Notification Button
function testReminder() {
    playMedicineChime();

    if ('Notification' in window && Notification.permission === 'granted') {
        const notif = new Notification('💊 CHR Medicine Reminder (Test)', {
            body: 'Reminders are successfully configured! You will be alerted for your Morning, Afternoon, and Night doses.',
            icon: '/assets/icons/pill.png'
        });
        notif.onclick = function() { window.focus(); };
    }

    // Also display in-page modal
    showModal('Sample: Paracetamol 500mg', 'Test Medicine Reminder', '🌅 Morning Dose &bull; After Meal');
}

// Show In-Page Alert Modal
function showModal(medName, title, slotInfo) {
    document.getElementById('modalMedTitle').textContent = '💊 ' + medName;
    document.getElementById('modalMedDesc').textContent = title || 'It is time to take your scheduled dose.';
    document.getElementById('modalMedSlots').innerHTML = slotInfo || '';
    document.getElementById('reminderModal').classList.add('active');
}

function closeModal() {
    document.getElementById('reminderModal').classList.remove('active');
}

function acknowledgeReminder() {
    closeModal();
    // Flash visual confirmation
    alert('✅ Dose marked as taken. Stay healthy!');
}

// Live Interval: Checks every 30 seconds for scheduled times
function checkMedicineReminders() {
    if (!prescriptions || prescriptions.length === 0) return;

    const now = new Date();
    const hours = String(now.getHours()).padStart(2, '0');
    const minutes = String(now.getMinutes()).padStart(2, '0');
    const currentTimeStr = `${hours}:${minutes}`;
    const todayStr = now.toISOString().slice(0, 10);

    prescriptions.forEach(p => {
        const slotsToCheck = [];

        if (parseInt(p.timing_morning) === 1 && p.morning_time) {
            slotsToCheck.push({ name: 'Morning', time: p.morning_time.slice(0, 5) });
        }
        if (parseInt(p.timing_afternoon) === 1 && p.afternoon_time) {
            slotsToCheck.push({ name: 'Afternoon', time: p.afternoon_time.slice(0, 5) });
        }
        if (parseInt(p.timing_night) === 1 && p.night_time) {
            slotsToCheck.push({ name: 'Night', time: p.night_time.slice(0, 5) });
        }

        slotsToCheck.forEach(slot => {
            // Check if within matching minute
            if (slot.time === currentTimeStr) {
                const storageKey = `chr_reminded_${p.id}_${slot.name}_${todayStr}`;
                
                // Only alert once per slot per day
                if (!localStorage.getItem(storageKey)) {
                    localStorage.setItem(storageKey, 'true');

                    // Play audio chime
                    playMedicineChime();

                    // Send browser push notification if permitted
                    if ('Notification' in window && Notification.permission === 'granted') {
                        const notif = new Notification(`💊 Medicine Reminder: ${p.medicine_name}`, {
                            body: `It's time to take your ${slot.name} dose (${p.dosage}) - ${p.intake_relation.replace('_', ' ')}.`,
                            tag: `med_${p.id}_${slot.name}`
                        });
                        notif.onclick = function() { window.focus(); };
                    }

                    // Show on-screen modal
                    const slotText = `${slot.name} Dose &bull; ${p.dosage} &bull; ${p.intake_relation.replace('_', ' ')}`;
                    showModal(p.medicine_name, `It's ${slot.time}! Time for your ${slot.name} medicine.`, slotText);

                    // Log in server database via API
                    const formData = new FormData();
                    formData.append('medicine', p.medicine_name);
                    formData.append('dosage', p.dosage);
                    formData.append('slot', slot.name);
                    fetch('api_reminder_log.php', { method: 'POST', body: formData });
                }
            }
        });
    });
}

document.addEventListener('DOMContentLoaded', function() {
    updatePermissionStatus();
    // Run reminder check immediately, then every 30 seconds
    checkMedicineReminders();
    setInterval(checkMedicineReminders, 30000);
});
</script>

</body>
</html>
