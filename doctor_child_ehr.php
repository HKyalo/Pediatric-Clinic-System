<?php
// Set session to last 24 hours 
ini_set('session.cookie_lifetime', 86400); // 24 hours in seconds
ini_set('session.gc_maxlifetime', 86400);   // 24 hours in seconds
session_set_cookie_params(86400); // Also set cookie params
session_start();
require_once __DIR__ . "/config/db.php";

// Security check - only immunization doctors
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'doctor' || $_SESSION['doctor_role'] !== 'immunization') {
    header("Location: index.php");
    exit();
}

$doctor_id = $_SESSION['user_id'];
$child_id = $_GET['child_id'] ?? 0;
$active_tab = $_GET['tab'] ?? 'growth';

// Get child details
$child = $conn->query("SELECT * FROM children WHERE child_id = $child_id")->fetch_assoc();
if (!$child) {
    header("Location: doctor_immunization_patients.php");
    exit();
}

// Calculate age from DOB
$dob = new DateTime($child['date_of_birth']);
$today = new DateTime();
$age_months = $dob->diff($today)->m + ($dob->diff($today)->y * 12);
$age_years = floor($age_months / 12);
$age_remaining_months = $age_months % 12;
$age_weeks = floor($dob->diff($today)->days / 7);


// EDIT WINDOW: 2 hours before to 2 hours after appointment

$can_edit = false;
$today_date = date('Y-m-d');
$current_appointment_id = null;

$appointment_check = $conn->query("
    SELECT appointment_id, appointment_date, appointment_time, status
    FROM appointments 
    WHERE child_id = $child_id 
    AND doctor_id = $doctor_id 
    AND status != 'Completed'
    AND appointment_date = '$today_date'
    ORDER BY appointment_time ASC
    LIMIT 1
");

if ($appointment_check && $appointment_check->num_rows > 0) {
    $apt = $appointment_check->fetch_assoc();
    $current_appointment_id = $apt['appointment_id'];
    
    $can_edit_query = $conn->query("
        SELECT appointment_id 
        FROM appointments 
        WHERE child_id = $child_id 
        AND doctor_id = $doctor_id 
        AND status != 'Completed'
        AND appointment_date = '$today_date'
        AND TIMESTAMP(appointment_date, appointment_time) >= NOW() - INTERVAL 2 HOUR
        AND TIMESTAMP(appointment_date, appointment_time) <= NOW() + INTERVAL 2 HOUR
        LIMIT 1
    ");
    
    $can_edit = ($can_edit_query && $can_edit_query->num_rows > 0);
}


// CHECK IF VITALS OR VACCINES RECORDED TODAY

$has_vitals_today = $conn->query("
    SELECT growth_id FROM growth_records 
    WHERE child_id = $child_id 
    AND doctor_id = $doctor_id 
    AND record_date = CURDATE()
")->num_rows > 0;

$has_vaccines_today = $conn->query("
    SELECT vaccination_record_id FROM vaccination_records 
    WHERE child_id = $child_id 
    AND administered_by = $doctor_id 
    AND date_administered = CURDATE()
")->num_rows > 0;

$can_mark_complete = ($current_appointment_id && ($has_vitals_today || $has_vaccines_today));

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_appointment_complete'])) {
    $appointment_id = $_POST['appointment_id'];
    $conn->query("UPDATE appointments SET status = 'Completed' WHERE appointment_id = $appointment_id");
    $message = "Appointment marked as completed!";
    $msg_type = "success";
    $can_mark_complete = false;
    $can_edit = false;
}


// FETCH MILESTONE DEFINITIONS FROM DATABASE

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

// FORM HANDLERS

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_vitals']) && $can_edit) {
    $weight = $_POST['weight_kg'];
    $height = $_POST['height_cm'];
    $head = $_POST['head_circumference'] ?: null;
    $notes = $_POST['vital_notes'] ?? '';
    
    $stmt = $conn->prepare("INSERT INTO growth_records (child_id, doctor_id, weight_kg, height_cm, head_circumference, notes, record_date) VALUES (?, ?, ?, ?, ?, ?, CURDATE())");
    $stmt->bind_param("iiddss", $child_id, $doctor_id, $weight, $height, $head, $notes);
    
    if ($stmt->execute()) {
        $message = "Vitals recorded successfully!";
        $msg_type = "success";
    } else {
        $message = "Error recording vitals.";
        $msg_type = "error";
    }
    $stmt->close();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['give_vaccines']) && $can_edit) {
    $selected = $_POST['vaccine_ids'] ?? [];
    $date_given = $_POST['date_given'] ?? date('Y-m-d');
    
    $given_count = 0;
    foreach ($selected as $vaccine_id) {
        $check = $conn->query("SELECT * FROM vaccination_records WHERE child_id = $child_id AND vaccine_id = $vaccine_id");
        if ($check->num_rows == 0) {
            $stmt = $conn->prepare("INSERT INTO vaccination_records (child_id, vaccine_id, date_administered, administered_by, status) VALUES (?, ?, ?, ?, 'Completed')");
            $stmt->bind_param("iisi", $child_id, $vaccine_id, $date_given, $doctor_id);
            if ($stmt->execute()) {
                $given_count++;
            }
            $stmt->close();
        }
    }
    
    if ($given_count > 0) {
        $message = "$given_count vaccine(s) recorded successfully!";
        $msg_type = "success";
    } else {
        $message = "No new vaccines recorded.";
        $msg_type = "info";
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_milestones']) && $can_edit) {
    $milestone_numbers = $_POST['milestone_number'] ?? [];
    $achieved_vals = $_POST['achieved'] ?? [];
    $achieved_dates = $_POST['achieved_date'] ?? [];
    
    foreach ($milestone_numbers as $milestone_number) {
        $is_achieved = isset($achieved_vals[$milestone_number]) ? 1 : 0;
        $date = $achieved_dates[$milestone_number] ?? ($is_achieved ? date('Y-m-d') : null);
        
        $check = $conn->query("SELECT id FROM child_milestones WHERE child_id = $child_id AND milestone_number = $milestone_number");
        if ($check->num_rows > 0) {
            $conn->query("UPDATE child_milestones SET achieved = $is_achieved, date_achieved = " . ($date ? "'$date'" : "NULL") . ", age_months = $age_months WHERE child_id = $child_id AND milestone_number = $milestone_number");
        } else {
            if ($is_achieved) {
                $conn->query("INSERT INTO child_milestones (child_id, milestone_number, achieved, date_achieved, age_months) VALUES ($child_id, $milestone_number, $is_achieved, " . ($date ? "'$date'" : "NULL") . ", $age_months)");
            }
        }
    }
    $message = "Milestones saved successfully!";
    $msg_type = "success";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_teeth']) && $can_edit) {
    $tooth_ids = $_POST['tooth_id'] ?? [];
    $emerged_dates = $_POST['emerged_date'] ?? [];
    
    foreach ($tooth_ids as $index => $tooth_id) {
        $emerged_date = !empty($emerged_dates[$index]) ? $emerged_dates[$index] : null;
        
        $check = $conn->query("SELECT id FROM child_teeth WHERE child_id = $child_id AND tooth_id = $tooth_id");
        if ($check->num_rows > 0) {
            if ($emerged_date) {
                $conn->query("UPDATE child_teeth SET emerged_date = '$emerged_date', age_months = $age_months WHERE child_id = $child_id AND tooth_id = $tooth_id");
            }
        } else {
            if ($emerged_date) {
                $conn->query("INSERT INTO child_teeth (child_id, tooth_id, emerged_date, age_months) VALUES ($child_id, $tooth_id, '$emerged_date', $age_months)");
            }
        }
    }
    $message = "Teeth records saved successfully!";
    $msg_type = "success";
}


// FETCH DATA

$growth = $conn->query("SELECT * FROM growth_records WHERE child_id = $child_id ORDER BY record_date ASC");
$growth_dates = []; $growth_weights = []; $growth_heights = []; $growth_heads = [];
while ($g = $growth->fetch_assoc()) {
    $growth_dates[] = date('M d, Y', strtotime($g['record_date']));
    $growth_weights[] = $g['weight_kg'];
    $growth_heights[] = $g['height_cm'];
    $growth_heads[] = $g['head_circumference'] ?? 0;
}
$growth->data_seek(0);

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

$completed_vaccines = $conn->query("
    SELECT vr.*, v.vaccine_name, v.dose_number 
    FROM vaccination_records vr
    JOIN vaccines v ON vr.vaccine_id = v.vaccine_id
    WHERE vr.child_id = $child_id
    ORDER BY vr.date_administered DESC
");

$child_milestones = $conn->query("
    SELECT milestone_number, achieved, date_achieved 
    FROM child_milestones 
    WHERE child_id = $child_id
");

$milestone_map = [];
while ($m = $child_milestones->fetch_assoc()) {
    $milestone_map[$m['milestone_number']] = $m;
}

$teeth_defs = $conn->query("SELECT * FROM teeth_definitions ORDER BY expected_age_min");
$child_teeth = $conn->query("SELECT tooth_id, emerged_date FROM child_teeth WHERE child_id = $child_id");
$teeth_map = [];
while ($t = $child_teeth->fetch_assoc()) {
    $teeth_map[$t['tooth_id']] = $t;
}

$growth_history = $conn->query("SELECT * FROM growth_records WHERE child_id = $child_id ORDER BY record_date DESC");

$delays = [];
foreach ($milestone_data as $num => $data) {
    if (!isset($milestone_map[$num]) && $age_months > $data['min_age']) {
        $delays[] = $data['description'] . " (by " . $data['expected_range'] . ")";
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Child EHR - <?= htmlspecialchars($child['first_name']) ?></title>
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
        
        .mode-badge { display: inline-block; padding: 5px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; margin-left: 15px; }
        .mode-edit { background: #d4edda; color: #155724; }
        .mode-view { background: #e2e8f0; color: #4a5568; }
        
        .tabs { display:flex; gap:10px; margin-bottom:25px; border-bottom:2px solid #e2e8f0; padding-bottom:10px; }
        .tab { padding:10px 20px; background:none; border:none; cursor:pointer; font-size:16px; color:#5a6f8c; text-decoration:none; }
        .tab:hover { background:#e6f0ff; color:#0b1a33; }
        .tab.active { color:#0b1a33; font-weight:600; border-bottom:3px solid #0b1a33; background:#f0f4fc; }
        
        .tab-content { display:none; }
        .tab-content.active { display:block; }
        
        .section { background:white; padding:25px; margin-bottom:30px; border-left:4px solid #0b1a33; }
        .section-header { margin-bottom:20px; border-bottom:2px solid #e2e8f0; padding-bottom:10px; display:flex; justify-content:space-between; align-items:center; }
        .section-header h2 { color:#0b1a33; font-size:20px; }
        
        .grid-2 { display:grid; grid-template-columns:1fr 1fr; gap:20px; }
        .grid-3 { display:grid; grid-template-columns:1fr 1fr 1fr; gap:15px; }
        .grid-4 { display:grid; grid-template-columns:repeat(4,1fr); gap:15px; }
        
        .form-group { margin-bottom:15px; }
        .form-group label { display:block; margin-bottom:5px; color:#1e3a5f; font-weight:600; }
        .form-control { width:100%; padding:10px; border:1px solid #e2e8f0; border-radius:4px; }
        
        .btn { background:#0b1a33; color:white; padding:12px 25px; border:none; border-radius:6px; cursor:pointer; font-size:14px; }
        .btn:hover { background:#1e3a5f; }
        .btn-success { background:#10b981; }
        .btn-success:hover { background:#059669; }
        .btn-complete-final { background:#10b981; color:white; padding:15px 30px; font-size:16px; border-radius:8px; border:none; cursor:pointer; font-weight:700; }
        .flag-btn { background:#dc2626; color:white; padding:8px 16px; text-decoration:none; border-radius:4px; }
        
        .alert { padding:15px; margin-bottom:20px; border-radius:6px; }
        .alert.success { background:#d4edda; color:#155724; border-left:4px solid #28a745; }
        .alert.error { background:#f8d7da; color:#721c24; border-left:4px solid #dc3545; }
        
        table { width:100%; border-collapse:collapse; }
        th { background:#0b1a33; color:white; padding:10px; text-align:left; }
        td { padding:10px; border-bottom:1px solid #e2e8f0; }
        
        .back-link { display:inline-block; margin-bottom:20px; color:#0b1a33; text-decoration:none; }
        
        .badge { padding:4px 8px; border-radius:4px; font-size:12px; }
        .badge.due { background:#fff3cd; color:#856404; }
        .badge.overdue { background:#f8d7da; color:#721c24; }
        .badge.achieved { background:#10b981; color:white; }
        .badge.pending { background:#fff3cd; color:#856404; }
        
        .chart-container { height:250px; margin-bottom:30px; }
        .delay-box { margin-top:20px; padding:15px; background:#fee2e2; border-left:4px solid #dc2626; }
        .complete-btn-container { margin-top:30px; padding:20px; background:white; border-left:4px solid #10b981; border-radius:8px; text-align:center; }
    </style>
</head>
<body>
<div class="wrapper">
    <div class="sidebar">
        <div class="sidebar-header">
            <h2>PCASS</h2>
            <p>Immunization Doctor</p>
        </div>
        <div class="nav">
            <ul>
                <li><a href="doctor_immunization_dashboard.php">Dashboard</a></li>
                <li><a href="doctor_immunization_appointments.php">My Appointments</a></li>
                <li><a href="doctor_immunization_patients.php">My Patients</a></li>
                <li><a href="doctor_profile.php">Profile</a></li>
                <li><a href="logout.php">Logout</a></li>
            </ul>
        </div>
    </div>
    
    <div class="main">
        <a href="doctor_immunization_patients.php" class="back-link">Back to Patients</a>
        
        <div class="child-header">
            <div>
                <div class="child-name">
                    <?= htmlspecialchars($child['first_name'] . ' ' . $child['last_name']) ?>
                    <?php if ($can_edit): ?>
                        <span class="mode-badge mode-edit">Edit Mode</span>
                    <?php else: ?>
                        <span class="mode-badge mode-view">View Only</span>
                    <?php endif; ?>
                </div>
                <div class="child-info">
                    Age: <?= $age_years ?>y <?= $age_remaining_months ?>m • DOB: <?= $child['date_of_birth'] ?> • <?= $age_weeks ?> weeks
                </div>
            </div>
            <a href="doctor_flag.php?child_id=<?= $child_id ?>" class="flag-btn">Flag for Review</a>
        </div>
        
        <?php if (isset($message)): ?>
        <div class="alert <?= $msg_type ?>"><?= $message ?></div>
        <?php endif; ?>
        
        <div class="tabs">
            <a href="?child_id=<?= $child_id ?>&tab=growth" class="tab <?= $active_tab == 'growth' ? 'active' : '' ?>">Growth & Vaccines</a>
            <a href="?child_id=<?= $child_id ?>&tab=development" class="tab <?= $active_tab == 'development' ? 'active' : '' ?>">Development</a>
        </div>
        
        <!-- TAB 1: GROWTH & VACCINES -->
        <div id="tab-growth" class="tab-content <?= $active_tab == 'growth' ? 'active' : '' ?>">
            
            <div class="section">
                <div class="section-header"><h2>Record Vitals</h2></div>
                <?php if ($can_edit): ?>
                <form method="POST">
                    <div class="grid-3">
                        <div class="form-group"><label>Weight (kg) *</label><input type="number" step="0.1" name="weight_kg" class="form-control" required></div>
                        <div class="form-group"><label>Height (cm) *</label><input type="number" step="0.1" name="height_cm" class="form-control" required></div>
                        <div class="form-group"><label>Head Circ (cm)</label><input type="number" step="0.1" name="head_circumference" class="form-control"></div>
                    </div>
                    <div class="form-group"><label>Notes</label><textarea name="vital_notes" class="form-control" rows="2"></textarea></div>
                    <button type="submit" name="save_vitals" class="btn">Save Vitals</button>
                </form>
                <?php else: ?>
                <p style="color:#5a6f8c; font-style:italic;">Editing available within 2 hours of appointment.</p>
                <?php endif; ?>
            </div>
            
            <div class="section">
                <div class="section-header"><h2>Growth Charts</h2></div>
                <div class="grid-2">
                    <div><h3 style="margin-bottom:15px;">Weight-for-Age</h3><div class="chart-container"><canvas id="weightChart"></canvas></div></div>
                    <div><h3 style="margin-bottom:15px;">Height-for-Age</h3><div class="chart-container"><canvas id="heightChart"></canvas></div></div>
                </div>
            </div>
            
            <div class="section">
                <div class="section-header"><h2>Vaccines</h2></div>
                
                <?php if (!empty($due_vaccines)): ?>
                <div style="margin-bottom:30px;">
                    <h3 style="color:#0b1a33; margin-bottom:15px;">Vaccines Due Now</h3>
                    <?php if ($can_edit): ?>
                    <form method="POST">
                        <table>
                            <thead><tr><th class="checkbox-col">Give</th><th>Vaccine</th><th>Dose</th><th>Due Age</th><th>Due Date</th><th>Status</th></tr></thead>
                            <tbody>
                                <?php foreach ($due_vaccines as $vax): 
                                    $due_date = date('Y-m-d', strtotime($child['date_of_birth'] . " + {$vax['min_age_weeks']} weeks"));
                                ?>
                                <tr>
                                    <td><input type="checkbox" name="vaccine_ids[]" value="<?= $vax['vaccine_id'] ?>"></td>
                                    <td><?= htmlspecialchars($vax['vaccine_name']) ?></td>
                                    <td>Dose <?= $vax['dose_number'] ?></td>
                                    <td><?= $vax['min_age_weeks'] ?> weeks</td>
                                    <td><?= date('M d, Y', strtotime($due_date)) ?></td>
                                    <td><span class="badge <?= $vax['status'] ?>"><?= $vax['status'] ?></span></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <div class="vaccine-row" style="margin-top:20px;">
                            <div class="form-group" style="margin-bottom:0;"><label>Date Given</label><input type="date" name="date_given" class="form-control" value="<?= date('Y-m-d') ?>" required></div>
                            <button type="submit" name="give_vaccines" class="btn btn-success">Give Selected</button>
                        </div>
                    </form>
                    <?php else: ?>
                    <p style="color:#5a6f8c; font-style:italic;">Editing available within 2 hours of appointment.</p>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
                
                <?php if ($completed_vaccines->num_rows > 0): ?>
                <div style="margin-bottom:30px;">
                    <h3 style="color:#0b1a33; margin-bottom:15px;">Completed Vaccines</h3>
                    <table>
                        <thead><tr><th>Date</th><th>Vaccine</th><th>Dose</th></tr></thead>
                        <tbody>
                            <?php while ($rec = $completed_vaccines->fetch_assoc()): ?>
                            <tr><td><?= date('M d, Y', strtotime($rec['date_administered'])) ?></td><td><?= htmlspecialchars($rec['vaccine_name']) ?></td><td>Dose <?= $rec['dose_number'] ?></td></tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($upcoming_vaccines)): ?>
                <div>
                    <h3 style="color:#0b1a33; margin-bottom:15px;">Upcoming Vaccines</h3>
                    <table style="width:100%;">
                        <thead>
                            <tr>
                                <th>Vaccine</th>
                                <th>Dose</th>
                                <th>Due Age</th>
                                <th>Estimated Date</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($upcoming_vaccines as $vax): 
                                $due_date = date('M d, Y', strtotime($child['date_of_birth'] . " + {$vax['min_age_weeks']} weeks"));
                            ?>
                            <tr>
                                <td><?= htmlspecialchars($vax['vaccine_name']) ?></td>
                                <td>Dose <?= $vax['dose_number'] ?></td>
                                <td><?= $vax['min_age_weeks'] ?> weeks</td>
                                <td><?= date('M d, Y', strtotime($due_date)) ?></td>
                                <td><span class="badge upcoming">Upcoming</span></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
            
            <div class="section">
                <div class="section-header"><h2>Growth History</h2></div>
                <?php if ($growth_history->num_rows > 0): ?>
                <table>
                    <thead><tr><th>Date</th><th>Weight</th><th>Height</th><th>Head</th><th>Notes</th></tr></thead>
                    <tbody>
                        <?php while ($g = $growth_history->fetch_assoc()): ?>
                        <tr><td><?= date('M d, Y', strtotime($g['record_date'])) ?></td><td><?= $g['weight_kg'] ?> kg</td><td><?= $g['height_cm'] ?> cm</td><td><?= $g['head_circumference'] ?? '-' ?> cm</td><td><?= htmlspecialchars($g['notes'] ?? '') ?></td></tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
                <?php else: ?><p>No growth records yet.</p><?php endif; ?>
            </div>
        </div>
        
        <!-- TAB 2: DEVELOPMENT -->
        <div id="tab-development" class="tab-content <?= $active_tab == 'development' ? 'active' : '' ?>">
            
            <div class="section">
                <div class="section-header"><h2>Developmental Milestones</h2></div>
                <?php if ($can_edit): ?>
                <form method="POST">
                    <table style="width:100%;">
                        <thead><tr><th style="width:40px;">✓</th><th>Milestone</th><th style="width:120px;">Normal Limits</th><th style="width:100px;">Category</th><th style="width:150px;">Date Achieved</th></tr></thead>
                        <tbody>
                            <?php foreach ($milestone_data as $num => $data):
                                $checked = isset($milestone_map[$num]) && $milestone_map[$num]['achieved'] ? 'checked' : '';
                                $date_val = $milestone_map[$num]['date_achieved'] ?? '';
                            ?>
                            <tr>
                                <td class="milestone-checkbox"><input type="checkbox" name="achieved[<?= $num ?>]" value="1" <?= $checked ?>></td>
                                <td class="milestone-desc"><?= $data['description'] ?></td>
                                <td><?= $data['expected_range'] ?></td>
                                <td><?= ucfirst($data['category']) ?></td>
                                <td><input type="date" name="achieved_date[<?= $num ?>]" class="form-control" value="<?= $date_val ?>" style="width:140px;"></td>
                            </tr>
                            <input type="hidden" name="milestone_number[]" value="<?= $num ?>">
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <button type="submit" name="save_milestones" class="btn" style="margin-top:20px;">Save Milestones</button>
                </form>
                <?php else: ?>
                <table style="width:100%;">
                    <thead><tr><th>Milestone</th><th style="width:120px;">Normal Limits</th><th style="width:100px;">Category</th><th style="width:150px;">Status</th></tr></thead>
                    <tbody>
                        <?php foreach ($milestone_data as $num => $data):
                            $achieved = isset($milestone_map[$num]) && $milestone_map[$num]['achieved'];
                            $status = $achieved ? 'Achieved' : 'Not yet';
                            $status_class = $achieved ? 'badge achieved' : 'badge pending';
                        ?>
                        <tr><td><?= $data['description'] ?></td><td><?= $data['expected_range'] ?></td><td><?= ucfirst($data['category']) ?></td><td><span class="<?= $status_class ?>"><?= $status ?></span></td></tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <p style="color:#5a6f8c; font-style:italic; margin-top:15px;">Editing available within 2 hours of appointment.</p>
                <?php endif; ?>
            </div>
            
            <?php if (!empty($delays)): ?>
            <div class="delay-box">
                <h3 style="color:#991b1b; margin-bottom:10px;">Potential Delays Detected</h3>
                <ul style="color:#991b1b; margin-left:20px;"><?php foreach ($delays as $d): ?><li><?= $d ?></li><?php endforeach; ?></ul>
                <p><small>Consider flagging for specialist review.</small></p>
            </div>
            <?php endif; ?>
            
            <div class="section">
                <div class="section-header"><h2>Teeth Development</h2></div>
                <?php if ($can_edit): ?>
                <form method="POST">
                    <div class="grid-4">
                        <?php 
                        $teeth_defs->data_seek(0);
                        while ($tooth = $teeth_defs->fetch_assoc()): 
                            $emerged = $teeth_map[$tooth['tooth_id']]['emerged_date'] ?? '';
                        ?>
                        <div class="form-group">
                            <label><?= $tooth['tooth_type'] ?></label>
                            <input type="date" name="emerged_date[]" class="form-control" value="<?= $emerged ?>">
                            <small style="color:#5a6f8c;">Expected: <?= $tooth['expected_age_min'] ?>-<?= $tooth['expected_age_max'] ?> mo</small>
                            <input type="hidden" name="tooth_id[]" value="<?= $tooth['tooth_id'] ?>">
                        </div>
                        <?php endwhile; ?>
                    </div>
                    <button type="submit" name="save_teeth" class="btn" style="margin-top:20px;">Save Teeth Records</button>
                </form>
                <?php else: ?>
                <div class="grid-4">
                    <?php 
                    $teeth_defs->data_seek(0);
                    while ($tooth = $teeth_defs->fetch_assoc()): 
                        $emerged = $teeth_map[$tooth['tooth_id']]['emerged_date'] ?? '';
                    ?>
                    <div class="form-group">
                        <label><?= $tooth['tooth_type'] ?></label>
                        <div class="form-control" style="background:#f8fafd;"><?= $emerged ? date('M d, Y', strtotime($emerged)) : 'Not yet emerged' ?></div>
                        <small style="color:#5a6f8c;">Expected: <?= $tooth['expected_age_min'] ?>-<?= $tooth['expected_age_max'] ?> mo</small>
                    </div>
                    <?php endwhile; ?>
                </div>
                <p style="color:#5a6f8c; font-style:italic; margin-top:15px;">Editing available within 2 hours of appointment.</p>
                <?php endif; ?>
                
                <?php
                $emerged_count = 0;
                foreach ($teeth_map as $t) if (!empty($t['emerged_date'])) $emerged_count++;
                ?>
                <div style="margin-top:20px; padding:15px; background:#f8fafd; border-left:4px solid #0b1a33;">
                    <p><strong>Teeth emerged:</strong> <?= $emerged_count ?> of <?= $teeth_defs->num_rows ?></p>
                </div>
            </div>
        </div>
        
        <!-- MARK COMPLETE BUTTON -->
        <?php if ($can_mark_complete && $current_appointment_id): ?>
        <div class="complete-btn-container">
            <form method="POST">
                <input type="hidden" name="appointment_id" value="<?= $current_appointment_id ?>">
                <button type="submit" name="mark_appointment_complete" class="btn-complete-final" onclick="return confirm('Mark this appointment as completed? No further edits will be allowed.')">
                    Mark Complete & Finish
                </button>
            </form>
            <p style="color:#5a6f8c; font-size:12px; margin-top:10px;">
                <?php if ($has_vitals_today && $has_vaccines_today): ?>Vitals and vaccines recorded. Click to complete appointment.
                <?php elseif ($has_vitals_today): ?>Vitals recorded. Click to complete appointment.
                <?php elseif ($has_vaccines_today): ?>Vaccines recorded. Click to complete appointment.
                <?php endif; ?>
            </p>
        </div>
        <?php endif; ?>
        
    </div>
</div>

<script>
new Chart(document.getElementById('weightChart'), {
    type: 'line', data: { labels: <?= json_encode($growth_dates) ?>, datasets: [{ label: 'Weight (kg)', data: <?= json_encode($growth_weights) ?>, borderColor: '#0b1a33', tension: 0.3, fill: false }] },
    options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } } }
});
new Chart(document.getElementById('heightChart'), {
    type: 'line', data: { labels: <?= json_encode($growth_dates) ?>, datasets: [{ label: 'Height (cm)', data: <?= json_encode($growth_heights) ?>, borderColor: '#10b981', tension: 0.3, fill: false }] },
    options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } } }
});
</script>
</body>
</html>
<?php $conn->close(); ?>