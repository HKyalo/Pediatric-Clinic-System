<?php
// Set session to last 24 hours 
ini_set('session.cookie_lifetime', 86400); // 24 hours in seconds
ini_set('session.gc_maxlifetime', 86400);   // 24 hours in seconds
session_set_cookie_params(86400); // Also set cookie params
session_start();
require_once __DIR__ . "/config/db.php";

// Security check - only specialists
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'doctor' || $_SESSION['doctor_role'] !== 'specialist') {
    header("Location: index.php");
    exit();
}

$doctor_id = $_SESSION['user_id'];
$child_id = $_GET['child_id'] ?? 0;
$flag_id = $_GET['flag_id'] ?? null;
$active_tab = $_GET['tab'] ?? 'overview';

// Get child details
$child = $conn->query("SELECT * FROM children WHERE child_id = $child_id")->fetch_assoc();
if (!$child) {
    header("Location: doctor_specialist_patients.php");
    exit();
}

// Calculate age
$dob = new DateTime($child['date_of_birth']);
$today = new DateTime();
$age_years = $dob->diff($today)->y;
$age_months = $dob->diff($today)->m + ($age_years * 12);
$age_weeks = floor($dob->diff($today)->days / 7);

// Get flag details if any
$flag = null;
if ($flag_id) {
    $flag = $conn->query("
        SELECT f.*, d.full_name as flagged_by_name
        FROM flags f
        LEFT JOIN doctors d ON f.flagged_by = d.doctor_id
        WHERE f.flag_id = $flag_id
    ")->fetch_assoc();
}

// ============================================
// CONSULTATION MODE - Check if specialist can add assessment
// ============================================
$today_date = date('Y-m-d');
$has_appointment_today = $conn->query("
    SELECT appointment_id, appointment_time
    FROM appointments 
    WHERE child_id = $child_id 
    AND doctor_id = $doctor_id 
    AND appointment_date = '$today_date'
    AND status = 'Pending'
")->num_rows > 0;

$can_assess = ($has_appointment_today || ($flag && $flag['status'] == 'new'));

// ============================================
// FETCH MILESTONE DEFINITIONS FROM DATABASE
// ============================================
$milestone_defs = $conn->query("
    SELECT * FROM milestone_definitions 
    WHERE is_active = 1 
    ORDER BY sort_order
");

$milestone_data = [];
while ($row = $milestone_defs->fetch_assoc()) {
    $milestone_data[$row['milestone_number']] = [
        'description' => $row['description'],
        'expected_range' => $row['expected_age_range'],
        'min_age' => $row['expected_age_min'],
        'max_age' => $row['expected_age_max'],
        'category' => $row['category']
    ];
}

// ============================================
// FETCH ALL CHILD DATA
// ============================================

// Growth records for charts
$growth = $conn->query("
    SELECT * FROM growth_records 
    WHERE child_id = $child_id 
    ORDER BY record_date ASC
");

$growth_dates = [];
$growth_weights = [];
$growth_heights = [];
$growth_heads = [];

while ($g = $growth->fetch_assoc()) {
    $growth_dates[] = date('M d, Y', strtotime($g['record_date']));
    $growth_weights[] = $g['weight_kg'];
    $growth_heights[] = $g['height_cm'];
    $growth_heads[] = $g['head_circumference'] ?? 0;
}
$growth->data_seek(0);

// Vaccine data
$all_vaccines = $conn->query("SELECT * FROM vaccines ORDER BY min_age_weeks");
$given_vaccines = $conn->query("SELECT vaccine_id FROM vaccination_records WHERE child_id = $child_id");
$given_ids = [];
while ($row = $given_vaccines->fetch_assoc()) {
    $given_ids[] = $row['vaccine_id'];
}

$due_vaccines = [];
$upcoming_vaccines = [];
while ($vax = $all_vaccines->fetch_assoc()) {
    $vax_id = $vax['vaccine_id'];
    
    if (in_array($vax_id, $given_ids)) {
        continue;
    } elseif ($age_weeks >= $vax['min_age_weeks']) {
        $vax['status'] = ($vax['max_age_weeks'] && $age_weeks > $vax['max_age_weeks']) ? 'overdue' : 'due';
        $due_vaccines[] = $vax;
    } else {
        $upcoming_vaccines[] = $vax;
    }
}

// Completed vaccines
$completed_vaccines = $conn->query("
    SELECT vr.*, v.vaccine_name, v.dose_number 
    FROM vaccination_records vr
    JOIN vaccines v ON vr.vaccine_id = v.vaccine_id
    WHERE vr.child_id = $child_id
    ORDER BY vr.date_administered DESC
");

// Child's achieved milestones
$child_milestones = $conn->query("
    SELECT milestone_number, achieved, date_achieved 
    FROM child_milestones 
    WHERE child_id = $child_id
");

$milestone_map = [];
while ($m = $child_milestones->fetch_assoc()) {
    $milestone_map[$m['milestone_number']] = $m;
}

// Teeth
$teeth_defs = $conn->query("SELECT * FROM teeth_definitions ORDER BY expected_age_min");
$child_teeth = $conn->query("SELECT tooth_id, emerged_date FROM child_teeth WHERE child_id = $child_id");
$teeth_map = [];
while ($t = $child_teeth->fetch_assoc()) {
    $teeth_map[$t['tooth_id']] = $t;
}

// Previous assessments
$assessments = $conn->query("
    SELECT sr.*, d.full_name as doctor_name
    FROM specialist_reviews sr
    LEFT JOIN doctors d ON sr.doctor_id = d.doctor_id
    WHERE sr.child_id = $child_id 
    ORDER BY sr.review_date DESC
");

// Lab results
$labs = $conn->query("
    SELECT l.*, d.full_name AS doctor_name
    FROM lab_results l
    LEFT JOIN doctors d ON l.doctor_id = d.doctor_id
    WHERE l.child_id = $child_id 
    ORDER BY l.test_date DESC
");

// Growth history
$growth_history = $conn->query("SELECT * FROM growth_records WHERE child_id = $child_id ORDER BY record_date DESC");

// ============================================
// HANDLE ASSESSMENT SUBMISSION (only if can assess)
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_assessment']) && $can_assess) {
    $diagnosis = $_POST['diagnosis'];
    $clinical_notes = $_POST['clinical_notes'];
    $treatment_plan = $_POST['treatment_plan'];
    $lab_orders = $_POST['lab_orders'] ?? '';
    $referrals = $_POST['referrals'] ?? '';
    $follow_up_date = $_POST['follow_up_date'] ?: null;
    
    $conn->begin_transaction();
    
    try {
        $stmt = $conn->prepare("
            INSERT INTO specialist_reviews 
            (child_id, flag_id, doctor_id, diagnosis, diagnosis_notes, treatment_plan, lab_orders, referrals, follow_up_date, status) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'active')
        ");
        $stmt->bind_param("iiissssss", $child_id, $flag_id, $doctor_id, $diagnosis, $clinical_notes, $treatment_plan, $lab_orders, $referrals, $follow_up_date);
        $stmt->execute();
        $review_id = $stmt->insert_id;
        $stmt->close();
        
        // Save prescriptions
        if (isset($_POST['med_name']) && is_array($_POST['med_name'])) {
            $presc_stmt = $conn->prepare("
                INSERT INTO prescriptions 
                (child_id, doctor_id, review_id, medication_name, dosage, frequency, duration, instructions, start_date) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, CURDATE())
            ");
            
            for ($i = 0; $i < count($_POST['med_name']); $i++) {
                if (!empty($_POST['med_name'][$i])) {
                    $med_name = $_POST['med_name'][$i];
                    $dosage = $_POST['dosage'][$i] ?? '';
                    $frequency = $_POST['frequency'][$i] ?? '';
                    $duration = $_POST['duration'][$i] ?? '';
                    $instructions = $_POST['instructions'][$i] ?? '';
                    
                    $presc_stmt->bind_param("iiisssss", 
                        $child_id, 
                        $doctor_id, 
                        $review_id, 
                        $med_name, 
                        $dosage, 
                        $frequency, 
                        $duration, 
                        $instructions
                    );
                    $presc_stmt->execute();
                }
            }
            $presc_stmt->close();
        }
        
        // Update flag if exists
        if ($flag_id) {
            $conn->query("UPDATE flags SET status = 'resolved', resolved_at = NOW(), resolved_by = $doctor_id WHERE flag_id = $flag_id");
        }
        
        $conn->commit();
        $message = "Assessment saved successfully!";
        $msg_type = "success";
        $active_tab = 'overview';
        
        // Refresh flag status
        if ($flag_id) {
            $flag = $conn->query("
                SELECT f.*, d.full_name as flagged_by_name
                FROM flags f
                LEFT JOIN doctors d ON f.flagged_by = d.doctor_id
                WHERE f.flag_id = $flag_id
            ")->fetch_assoc();
        }
        
    } catch (Exception $e) {
        $conn->rollback();
        $message = "Error saving assessment: " . $e->getMessage();
        $msg_type = "error";
    }
}

// Handle lab result upload (only if can assess)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_lab_results']) && $can_assess) {
    $test_names = $_POST['test_name'] ?? [];
    $test_dates = $_POST['test_date'] ?? [];
    $result_values = $_POST['result_value'] ?? [];
    $summaries = $_POST['summary'] ?? [];
    
    $latest_review = $conn->query("SELECT review_id FROM specialist_reviews WHERE child_id = $child_id ORDER BY review_date DESC LIMIT 1")->fetch_assoc();
    $review_id = $latest_review ? $latest_review['review_id'] : null;
    
    for ($i = 0; $i < count($test_names); $i++) {
        if (empty($test_names[$i])) continue;
        
        $attachment_path = null;
        if (isset($_FILES['lab_file_' . $i]) && $_FILES['lab_file_' . $i]['error'] === UPLOAD_ERR_OK) {
            $upload_dir = __DIR__ . '/uploads/lab_results/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
            
            $filename = 'lab_' . $child_id . '_' . time() . '_' . $i . '.pdf';
            $filepath = $upload_dir . $filename;
            $attachment_path = 'uploads/lab_results/' . $filename;
            move_uploaded_file($_FILES['lab_file_' . $i]['tmp_name'], $filepath);
        }
        
        $stmt = $conn->prepare("
            INSERT INTO lab_results 
            (child_id, doctor_id, review_id, test_name, test_date, result_value, result_summary, attachment) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param("iiisssss", $child_id, $doctor_id, $review_id, $test_names[$i], $test_dates[$i], $result_values[$i], $summaries[$i], $attachment_path);
        $stmt->execute();
        $stmt->close();
    }
    
    $message = "Lab results saved successfully!";
    $msg_type = "success";
}

// Get latest assessment
$latest_assessment = $assessments->fetch_assoc();
$assessments->data_seek(0);

// Calculate delays
$delays = [];
foreach ($milestone_data as $num => $data) {
    if (!isset($milestone_map[$num]) && $age_months > $data['min_age']) {
        $delays[] = $data['description'] . " (by " . $data['expected_range'] . ")";
    }
}

// Get mode badge text
$mode_text = "";
$mode_class = "";
if ($can_assess) {
    if ($has_appointment_today) {
        $mode_text = "Consultation Mode";
        $mode_class = "mode-edit";
    } elseif ($flag && $flag['status'] == 'new') {
        $mode_text = "Review Mode";
        $mode_class = "mode-flag";
    }
} else {
    $mode_text = "View Only";
    $mode_class = "mode-view";
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Specialist EHR - <?= htmlspecialchars($child['first_name']) ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body { background:#f0f4fc; font-family:Arial; }
        .wrapper { display:flex; }
        .main { margin-left:260px; padding:30px; flex:1; }
        
        .child-header { background:white; padding:25px; margin-bottom:25px; border-left:4px solid #0b1a33; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; }
        .child-name { font-size:28px; font-weight:700; color:#0b1a33; }
        .child-info { color:#5a6f8c; margin-top:5px; font-size:14px; }
        
        .mode-badge {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            margin-left: 15px;
        }
        .mode-edit { background: #d4edda; color: #155724; }
        .mode-view { background: #e2e8f0; color: #4a5568; }
        .mode-flag { background: #fef3c7; color: #92400e; }
        
        .flag-box { background:#fee2e2; padding:15px; margin-bottom:20px; border-left:4px solid #dc2626; }
        .consultation-box { background:#d4edda; padding:15px; margin-bottom:20px; border-left:4px solid #28a745; }
        
        .tabs { display:flex; gap:10px; margin-bottom:25px; border-bottom:2px solid #e2e8f0; padding-bottom:10px; flex-wrap:wrap; }
        .tab { padding:10px 20px; background:none; border:none; cursor:pointer; font-size:16px; color:#5a6f8c; text-decoration:none; }
        .tab:hover { background:#e6f0ff; color:#0b1a33; }
        .tab.active { color:#0b1a33; font-weight:600; border-bottom:3px solid #0b1a33; background:#f0f4fc; }
        
        .tab-content { display:none; }
        .tab-content.active { display:block; }
        
        .section { background:white; padding:25px; margin-bottom:30px; border-left:4px solid #0b1a33; }
        .section-header { margin-bottom:20px; border-bottom:2px solid #e2e8f0; padding-bottom:10px; display:flex; justify-content:space-between; align-items:center; }
        .section-header h2 { color:#0b1a33; font-size:20px; }
        .section-header h3 { color:#0b1a33; font-size:16px; margin:0; }
        
        .grid-2 { display:grid; grid-template-columns:1fr 1fr; gap:20px; }
        .grid-3 { display:grid; grid-template-columns:1fr 1fr 1fr; gap:15px; }
        .grid-4 { display:grid; grid-template-columns:repeat(4,1fr); gap:15px; }
        
        .form-group { margin-bottom:15px; }
        .form-group label { display:block; margin-bottom:5px; color:#1e3a5f; font-weight:600; }
        .form-control { width:100%; padding:10px; border:1px solid #e2e8f0; border-radius:4px; }
        textarea.form-control { min-height:80px; resize:vertical; }
        
        .btn { background:#0b1a33; color:white; padding:12px 25px; border:none; border-radius:6px; cursor:pointer; font-size:14px; }
        .btn:hover { background:#1e3a5f; }
        .btn-success { background:#10b981; }
        .btn-success:hover { background:#059669; }
        .btn-sm { background:#0b1a33; color:white; padding:5px 12px; border:none; border-radius:4px; font-size:12px; cursor:pointer; }
        
        .alert { padding:15px; margin-bottom:20px; border-radius:6px; }
        .alert.success { background:#d4edda; color:#155724; border-left:4px solid #28a745; }
        .alert.error { background:#f8d7da; color:#721c24; border-left:4px solid #dc3545; }
        .alert.info { background:#d1ecf1; color:#0c5460; border-left:4px solid #17a2b8; }
        
        table { width:100%; border-collapse:collapse; font-size:14px; }
        th { background:#0b1a33; color:white; padding:10px; text-align:left; }
        td { padding:10px; border-bottom:1px solid #e2e8f0; }
        tr:hover td { background:#f8fafd; }
        
        .back-link { display:inline-block; margin-bottom:20px; color:#0b1a33; text-decoration:none; }
        
        .badge { padding:4px 8px; border-radius:4px; font-size:12px; }
        .badge.due { background:#fff3cd; color:#856404; }
        .badge.overdue { background:#f8d7da; color:#721c24; }
        .badge.achieved { background:#10b981; color:white; }
        .badge.pending { background:#fff3cd; color:#856404; }
        .badge.delayed { background:#fee2e2; color:#dc2626; }
        
        .chart-container { height:250px; margin-bottom:30px; }
        .delay-box { margin-top:20px; padding:15px; background:#fee2e2; border-left:4px solid #dc2626; }
        .med-item { background:#f8fafd; padding:10px; margin-bottom:8px; border-left:3px solid #0b1a33; }
    </style>
</head>
<body>
<div class="wrapper">
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-header">
            <h2>PCASS</h2>
            <p>Specialist</p>
        </div>
        <div class="nav">
            <ul>
                <li><a href="doctor_specialist_dashboard.php">Dashboard</a></li>
                <li><a href="doctor_specialist_appointments.php">My Appointments</a></li>
                <li><a href="doctor_specialist_patients.php">My Patients</a></li>
                <li><a href="doctor_profile.php">Profile</a></li>
                <li><a href="logout.php">Logout</a></li>
            </ul>
        </div>
    </div>
    
    <div class="main">
        <a href="doctor_specialist_patients.php" class="back-link">Back to Patients</a>
        
        <!-- Child Header with Mode Badge -->
        <div class="child-header">
            <div>
                <div class="child-name">
                    <?= htmlspecialchars($child['first_name'] . ' ' . $child['last_name']) ?>
                    <span class="mode-badge <?= $mode_class ?>"><?= $mode_text ?></span>
                </div>
                <div class="child-info">
                    Age: <?= $age_years ?> years (<?= $age_months ?> months) • 
                    DOB: <?= $child['date_of_birth'] ?> • 
                    Gender: <?= $child['gender'] ?> 
                </div>
            </div>
        </div>
        
        <!-- Flag Box (if flag exists and not resolved) -->
        <?php if ($flag && $flag['status'] == 'new'): ?>
        <div class="flag-box">
            <strong>Flag Reason:</strong> <?= htmlspecialchars($flag['reason']) ?><br>
            <small>Flagged by: <?= htmlspecialchars($flag['flagged_by_name'] ?? 'System') ?> on <?= date('M d, Y', strtotime($flag['created_at'])) ?></small>
        </div>
        <?php endif; ?>
        
        <!-- Consultation Mode Box (if appointment today) -->
        <?php if ($has_appointment_today): ?>
        <div class="consultation-box">
            <strong>Consultation Mode Active</strong><br>
            You have an appointment with this patient today.
        </div>
        <?php endif; ?>
        
        <?php if (isset($message)): ?>
        <div class="alert <?= $msg_type ?>"><?= $message ?></div>
        <?php endif; ?>
        
        <!-- Tabs -->
        <div class="tabs">
            <a href="?child_id=<?= $child_id ?>&tab=overview" class="tab <?= $active_tab == 'overview' ? 'active' : '' ?>">Patient Overview</a>
            <a href="?child_id=<?= $child_id ?>&tab=growth" class="tab <?= $active_tab == 'growth' ? 'active' : '' ?>">Growth</a>
            <a href="?child_id=<?= $child_id ?>&tab=vaccines" class="tab <?= $active_tab == 'vaccines' ? 'active' : '' ?>">Vaccines</a>
            <a href="?child_id=<?= $child_id ?>&tab=development" class="tab <?= $active_tab == 'development' ? 'active' : '' ?>">Development</a>
            <a href="?child_id=<?= $child_id ?>&tab=assessment" class="tab <?= $active_tab == 'assessment' ? 'active' : '' ?>">Assessment</a>
            <a href="?child_id=<?= $child_id ?>&tab=labs" class="tab <?= $active_tab == 'labs' ? 'active' : '' ?>">Labs</a>
        </div>
        
        <!-- OVERVIEW TAB -->
        <div id="tab-overview" class="tab-content <?= $active_tab == 'overview' ? 'active' : '' ?>">
            <div class="stats-grid" style="display:grid; grid-template-columns:repeat(4,1fr); gap:15px; margin-bottom:20px;">
                <div class="stat-box" style="background:white; padding:15px; text-align:center; border-left:4px solid #0b1a33;">
                    <div class="stat-value" style="font-size:24px; font-weight:700; color:#0b1a33;"><?= $growth->num_rows ?></div>
                    <div class="stat-label" style="color:#5a6f8c; font-size:12px;">Growth Records</div>
                </div>
                <div class="stat-box" style="background:white; padding:15px; text-align:center; border-left:4px solid #0b1a33;">
                    <div class="stat-value" style="font-size:24px; font-weight:700; color:#0b1a33;"><?= $completed_vaccines->num_rows ?></div>
                    <div class="stat-label" style="color:#5a6f8c; font-size:12px;">Vaccines Given</div>
                </div>
                <div class="stat-box" style="background:white; padding:15px; text-align:center; border-left:4px solid #0b1a33;">
                    <div class="stat-value" style="font-size:24px; font-weight:700; color:#0b1a33;"><?= $assessments->num_rows ?></div>
                    <div class="stat-label" style="color:#5a6f8c; font-size:12px;">Assessments</div>
                </div>
                <div class="stat-box" style="background:white; padding:15px; text-align:center; border-left:4px solid #0b1a33;">
                    <div class="stat-value" style="font-size:24px; font-weight:700; color:#0b1a33;"><?= $labs->num_rows ?></div>
                    <div class="stat-label" style="color:#5a6f8c; font-size:12px;">Lab Results</div>
                </div>
            </div>
            
            <?php if ($latest_assessment): ?>
            <div style="background:#f8fafd; padding:15px; margin-bottom:20px; border-left:4px solid #0b1a33;">
                <h3 style="margin-bottom:10px;">Latest Assessment (<?= date('M d, Y', strtotime($latest_assessment['review_date'])) ?>)</h3>
                <p><strong>Diagnosis:</strong> <?= htmlspecialchars($latest_assessment['diagnosis'] ?? 'None') ?></p>
            </div>
            <?php endif; ?>
            
            <div class="section">
                <h2 style="margin-bottom:15px;">Recent Growth</h2>
                <?php 
                $recent_growth = $conn->query("SELECT * FROM growth_records WHERE child_id = $child_id ORDER BY record_date DESC LIMIT 1")->fetch_assoc();
                if ($recent_growth): 
                ?>
                <p><strong>Last visit:</strong> <?= date('M d, Y', strtotime($recent_growth['record_date'])) ?></p>
                <p><strong>Weight:</strong> <?= $recent_growth['weight_kg'] ?> kg</p>
                <p><strong>Height:</strong> <?= $recent_growth['height_cm'] ?> cm</p>
                <?php else: ?>
                <p>No growth records yet.</p>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- GROWTH TAB -->
        <div id="tab-growth" class="tab-content <?= $active_tab == 'growth' ? 'active' : '' ?>">
            <div class="section">
                <h2>Growth Chart</h2>
                <div style="height:300px;">
                    <canvas id="growthChart"></canvas>
                </div>
            </div>
            
            <div class="section">
                <h2>Growth History</h2>
                <?php if ($growth_history->num_rows > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Weight (kg)</th>
                            <th>Height (cm)</th>
                            <th>Head (cm)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($g = $growth_history->fetch_assoc()): ?>
                        <tr>
                            <td><?= date('M d, Y', strtotime($g['record_date'])) ?></td>
                            <td><?= $g['weight_kg'] ?></td>
                            <td><?= $g['height_cm'] ?></td>
                            <td><?= $g['head_circumference'] ?? '-' ?></td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <p>No growth records yet.</p>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- VACCINES TAB -->
<div id="tab-vaccines" class="tab-content <?= $active_tab == 'vaccines' ? 'active' : '' ?>">
    <div class="section">
        <h2>Vaccine History</h2>
        
        <?php if ($completed_vaccines->num_rows > 0): ?>
        <table style="width:100%; margin-bottom:30px;">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Vaccine</th>
                    <th>Dose</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($rec = $completed_vaccines->fetch_assoc()): ?>
                <tr>
                    <td><?= date('M d, Y', strtotime($rec['date_administered'])) ?></td>
                    <td><?= htmlspecialchars($rec['vaccine_name']) ?></td>
                    <td>Dose <?= $rec['dose_number'] ?></td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
        <?php else: ?>
        <p>No vaccine records yet.</p>
        <?php endif; ?>
        
        <?php if (!empty($due_vaccines)): ?>
        <h3 style="margin:20px 0 10px;">Due Vaccines</h3>
        <table style="width:100%;">
            <thead>
                <tr>
                    <th>Vaccine</th>
                    <th>Dose</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($due_vaccines as $vax): ?>
                <tr>
                    <td><?= htmlspecialchars($vax['vaccine_name']) ?></td>
                    <td>Dose <?= $vax['dose_number'] ?></td>
                    <td><span class="badge <?= $vax['status'] ?>"><?= $vax['status'] ?></span></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>
        
        <!-- DEVELOPMENT TAB -->
        <div id="tab-development" class="tab-content <?= $active_tab == 'development' ? 'active' : '' ?>">
            <div class="section">
                <h2>Developmental Milestones</h2>
                <table style="width:100%;">
                    <thead>
                        <tr>
                            <th>Milestone</th>
                            <th>Normal Limits</th>
                            <th>Category</th>
                            <th>Status</th>
                            <th>Date Achieved</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($milestone_data as $num => $data): 
                            $achieved = isset($milestone_map[$num]) && $milestone_map[$num]['achieved'] ? true : false;
                            $date_val = $milestone_map[$num]['date_achieved'] ?? '';
                            $is_delayed = !$achieved && ($age_months > $data['min_age']);
                        ?>
                        <tr>
                            <td><?= $data['description'] ?></td>
                            <td><?= $data['expected_range'] ?></td>
                            <td><?= ucfirst($data['category']) ?></td>
                            <td>
                                <?php if ($achieved): ?>
                                    <span class="badge achieved">Achieved</span>
                                <?php elseif ($is_delayed): ?>
                                    <span class="badge delayed">Delayed</span>
                                <?php else: ?>
                                    <span class="badge pending">Pending</span>
                                <?php endif; ?>
                            </td>
                            <td><?= $date_val ? date('M d, Y', strtotime($date_val)) : '-' ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <?php if (!empty($delays)): ?>
            <div class="delay-box">
                <h3 style="color:#991b1b; margin-bottom:10px;">Potential Delays Detected</h3>
                <ul style="color:#991b1b; margin-left:20px;">
                    <?php foreach ($delays as $d): ?><li><?= $d ?></li><?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>
            
            <div class="section">
                <h2>Teeth Development</h2>
                <div class="grid-4">
                    <?php 
                    $teeth_defs->data_seek(0);
                    while ($tooth = $teeth_defs->fetch_assoc()): 
                        $emerged = $teeth_map[$tooth['tooth_id']]['emerged_date'] ?? '';
                    ?>
                    <div style="margin-bottom:10px;">
                        <strong><?= $tooth['tooth_type'] ?></strong><br>
                        <?= $emerged ? date('M d, Y', strtotime($emerged)) : '<span style="color:#5a6f8c;">Not emerged</span>' ?>
                        <br><small>Expected: <?= $tooth['expected_age_min'] ?>-<?= $tooth['expected_age_max'] ?> mo</small>
                    </div>
                    <?php endwhile; ?>
                </div>
            </div>
        </div>
        
        <!-- ASSESSMENT TAB -->
        <div id="tab-assessment" class="tab-content <?= $active_tab == 'assessment' ? 'active' : '' ?>">
            
            <?php if ($can_assess): ?>
            <div class="section">
                <h2>New Assessment</h2>
                <form method="POST">
                    <div class="form-group">
                        <label>Diagnosis *</label>
                        <input type="text" name="diagnosis" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Clinical Notes</label>
                        <textarea name="clinical_notes" class="form-control" rows="3"></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label>Treatment Plan</label>
                        <textarea name="treatment_plan" class="form-control" rows="3"></textarea>
                    </div>
                    
                    <div class="section-header">Lab Orders</div>
                    <div class="form-group">
                        <label>Tests to Order</label>
                        <textarea name="lab_orders" class="form-control" rows="3" placeholder="Complete Blood Count&#10;Iron Studies&#10;Thyroid Function Test"></textarea>
                    </div>
                    
                    <div class="section-header">Prescriptions</div>
                    <div id="prescriptions-container">
                        <div class="prescription-row">
                            <div class="grid-2">
                                <div class="form-group">
                                    <label>Medicine Name</label>
                                    <input type="text" name="med_name[]" class="form-control">
                                </div>
                                <div class="form-group">
                                    <label>Dosage</label>
                                    <input type="text" name="dosage[]" class="form-control">
                                </div>
                            </div>
                            <div class="grid-2">
                                <div class="form-group">
                                    <label>Frequency</label>
                                    <input type="text" name="frequency[]" class="form-control">
                                </div>
                                <div class="form-group">
                                    <label>Duration</label>
                                    <input type="text" name="duration[]" class="form-control">
                                </div>
                            </div>
                            <div class="form-group">
                                <label>Instructions</label>
                                <textarea name="instructions[]" class="form-control" rows="2"></textarea>
                            </div>
                        </div>
                    </div>
                    <button type="button" onclick="addPrescription()" class="btn btn-sm" style="margin-bottom:20px;">+ Add Another Medication</button>
                    
                    <div class="section-header">Referrals</div>
                    <div class="form-group">
                        <label>Refer to</label>
                        <textarea name="referrals" class="form-control" rows="2"></textarea>
                    </div>
                    
                    <div class="section-header">Follow-up</div>
                    <div class="form-group">
                        <label>Follow-up Date</label>
                        <input type="date" name="follow_up_date" class="form-control">
                    </div>
                    
                    <button type="submit" name="save_assessment" class="btn">Save Assessment</button>
                </form>
            </div>
            <?php else: ?>
            <div class="section">
                <h2>New Assessment</h2>
                <div class="info-box" style="background:#f8fafd;">
                    <p>To add a new assessment, please ensure there is a scheduled appointment.</p>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Previous Assessments -->
            <?php if ($assessments->num_rows > 0): ?>
            <div class="section">
                <h2>Previous Assessments</h2>
                <?php 
                $assessments->data_seek(0);
                while ($a = $assessments->fetch_assoc()): 
                    $review_prescriptions = $conn->query("
                        SELECT * FROM prescriptions 
                        WHERE review_id = " . $a['review_id'] . "
                        ORDER BY prescription_id
                    ");
                ?>
                <div style="border:1px solid #e2e8f0; padding:15px; margin-bottom:15px; border-radius:4px;">
                    <div style="display:flex; justify-content:space-between; margin-bottom:10px;">
                        <strong><?= date('M d, Y', strtotime($a['review_date'])) ?></strong>
                        <span style="color:#5a6f8c;">Dr. <?= htmlspecialchars($a['doctor_name'] ?? 'Unknown') ?></span>
                    </div>
                    
                    <p><strong>Diagnosis:</strong> <?= htmlspecialchars($a['diagnosis'] ?? 'None') ?></p>
                    
                    <?php if ($a['diagnosis_notes']): ?>
                    <p><strong>Notes:</strong> <?= nl2br(htmlspecialchars($a['diagnosis_notes'])) ?></p>
                    <?php endif; ?>
                    
                    <?php if ($review_prescriptions->num_rows > 0): ?>
                    <div style="margin-top:10px;">
                        <strong>Prescriptions:</strong>
                        <?php while ($p = $review_prescriptions->fetch_assoc()): ?>
                        <div class="med-item">
                            <strong><?= htmlspecialchars($p['medication_name']) ?></strong><br>
                            <span style="font-size:13px;">
                                Dosage: <?= htmlspecialchars($p['dosage']) ?>
                                <?php if ($p['frequency']): ?> • <?= htmlspecialchars($p['frequency']) ?><?php endif; ?>
                                <?php if ($p['duration']): ?> • Duration: <?= htmlspecialchars($p['duration']) ?><?php endif; ?>
                            </span>
                            <?php if ($p['instructions']): ?>
                            <div style="font-size:12px; color:#5a6f8c; margin-top:3px;"><?= htmlspecialchars($p['instructions']) ?></div>
                            <?php endif; ?>
                        </div>
                        <?php endwhile; ?>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($a['follow_up_date']): ?>
                    <p style="margin-top:10px;"><strong>Follow-up:</strong> <?= date('M d, Y', strtotime($a['follow_up_date'])) ?></p>
                    <?php endif; ?>
                </div>
                <?php endwhile; ?>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- LABS TAB -->
        <div id="tab-labs" class="tab-content <?= $active_tab == 'labs' ? 'active' : '' ?>">
            <?php if ($can_assess): ?>
            <div class="section">
                <h2>Add Lab Results</h2>
                <form method="POST" enctype="multipart/form-data">
                    <div id="lab-results-container">
                        <div class="lab-row" id="lab-row-0">
                            <div class="grid-2">
                                <div class="form-group">
                                    <label>Test Name</label>
                                    <input type="text" name="test_name[0]" class="form-control" required>
                                </div>
                                <div class="form-group">
                                    <label>Test Date</label>
                                    <input type="date" name="test_date[0]" class="form-control" value="<?= date('Y-m-d') ?>" required>
                                </div>
                            </div>
                            <div class="grid-2">
                                <div class="form-group">
                                    <label>Result Value</label>
                                    <input type="text" name="result_value[0]" class="form-control">
                                </div>
                                <div class="form-group">
                                    <label>Upload File</label>
                                    <input type="file" name="lab_file_0" accept=".pdf,.jpg,.png">
                                </div>
                            </div>
                            <div class="form-group">
                                <label>Summary</label>
                                <textarea name="summary[0]" class="form-control" rows="2"></textarea>
                            </div>
                        </div>
                    </div>
                    
                    <button type="button" onclick="addLabRow()" class="btn btn-sm" style="margin-bottom:15px;">+ Add Another Test</button>
                    <button type="submit" name="save_lab_results" class="btn btn-success">Save All Lab Results</button>
                </form>
            </div>
            <?php else: ?>
            <div class="section">
                <h2>Add Lab Results</h2>
                <div class="info-box" style="background:#f8fafd;">
                    <p>To add lab results, please ensure there is a scheduled appointment.</p>
                </div>
            </div>
            <?php endif; ?>
            
            <div class="section">
                <h2>Previous Lab Results</h2>
                <?php if ($labs->num_rows > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Test</th>
                            <th>Result</th>
                            <th>File</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($l = $labs->fetch_assoc()): ?>
                        <tr>
                            <td><?= date('M d, Y', strtotime($l['test_date'])) ?></td>
                            <td><?= htmlspecialchars($l['test_name']) ?></td>
                            <td><?= htmlspecialchars($l['result_value'] ?? '-') ?></td>
                            <td>
                                <?php if (!empty($l['attachment'])): ?>
                                <a href="<?= htmlspecialchars($l['attachment']) ?>" download>Download</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <p>No lab results yet.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
let labRowCount = 1;
let prescriptionCount = 1;

function addLabRow() {
    const container = document.getElementById('lab-results-container');
    const html = `
        <div class="lab-row" id="lab-row-${labRowCount}">
            <button type="button" onclick="this.closest('.lab-row').remove()" style="float:right; background:#dc2626; color:white; border:none; padding:2px 8px; border-radius:4px;">✕</button>
            <div class="grid-2">
                <div class="form-group">
                    <label>Test Name</label>
                    <input type="text" name="test_name[${labRowCount}]" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Test Date</label>
                    <input type="date" name="test_date[${labRowCount}]" class="form-control" value="<?= date('Y-m-d') ?>" required>
                </div>
            </div>
            <div class="grid-2">
                <div class="form-group">
                    <label>Result Value</label>
                    <input type="text" name="result_value[${labRowCount}]" class="form-control">
                </div>
                <div class="form-group">
                    <label>Upload File</label>
                    <input type="file" name="lab_file_${labRowCount}" accept=".pdf,.jpg,.png">
                </div>
            </div>
            <div class="form-group">
                <label>Summary</label>
                <textarea name="summary[${labRowCount}]" class="form-control" rows="2"></textarea>
            </div>
        </div>
    `;
    container.insertAdjacentHTML('beforeend', html);
    labRowCount++;
}

function addPrescription() {
    const container = document.getElementById('prescriptions-container');
    const html = `
        <div class="prescription-row" style="margin-top:15px;">
            <button type="button" onclick="this.closest('.prescription-row').remove()" style="float:right; background:#dc2626; color:white; border:none; padding:2px 8px; border-radius:4px;">✕</button>
            <div class="grid-2">
                <div class="form-group">
                    <label>Medicine Name</label>
                    <input type="text" name="med_name[]" class="form-control">
                </div>
                <div class="form-group">
                    <label>Dosage</label>
                    <input type="text" name="dosage[]" class="form-control">
                </div>
            </div>
            <div class="grid-2">
                <div class="form-group">
                    <label>Frequency</label>
                    <input type="text" name="frequency[]" class="form-control">
                </div>
                <div class="form-group">
                    <label>Duration</label>
                    <input type="text" name="duration[]" class="form-control">
                </div>
            </div>
            <div class="form-group">
                <label>Instructions</label>
                <textarea name="instructions[]" class="form-control" rows="2"></textarea>
            </div>
        </div>
    `;
    container.insertAdjacentHTML('beforeend', html);
    prescriptionCount++;
}

// Growth Chart
const growthCtx = document.getElementById('growthChart')?.getContext('2d');
if (growthCtx) {
    new Chart(growthCtx, {
        type: 'line',
        data: {
            labels: <?= json_encode($growth_dates) ?>,
            datasets: [
                {
                    label: 'Weight (kg)',
                    data: <?= json_encode($growth_weights) ?>,
                    borderColor: '#0b1a33',
                    backgroundColor: 'rgba(11,26,51,0.1)',
                    tension: 0.3
                },
                {
                    label: 'Height (cm)',
                    data: <?= json_encode($growth_heights) ?>,
                    borderColor: '#10b981',
                    backgroundColor: 'rgba(16,185,129,0.1)',
                    tension: 0.3
                },
                {
                    label: 'Head Circ (cm)',
                    data: <?= json_encode($growth_heads) ?>,
                    borderColor: '#f59e0b',
                    backgroundColor: 'rgba(245,158,11,0.1)',
                    tension: 0.3
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false
        }
    });
}
</script>
</body>
</html>