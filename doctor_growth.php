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

// Get child details
$child = $conn->query("SELECT * FROM children WHERE child_id = $child_id")->fetch_assoc();
if (!$child) {
    header("Location: doctor_immunization_patients.php");
    exit();
}

// Calculate age in months
$dob = new DateTime($child['date_of_birth']);
$today = new DateTime();
$age_months = $dob->diff($today)->m + ($dob->diff($today)->y * 12);
$age_years = floor($age_months / 12);
$age_remaining_months = $age_months % 12;
$age_weeks = floor($dob->diff($today)->days / 7);

// ============================================
// HANDLE VITALS SUBMISSION
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_vitals'])) {
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

// ============================================
// HANDLE VACCINE ADMINISTRATION
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['give_vaccines'])) {
    $selected = $_POST['vaccine_ids'] ?? [];
    $date_given = $_POST['date_given'] ?? date('Y-m-d');
    $batch = $_POST['batch_number'] ?? '';
    
    $given_count = 0;
    foreach ($selected as $vaccine_id) {
        $check = $conn->query("SELECT * FROM vaccination_records WHERE child_id = $child_id AND vaccine_id = $vaccine_id");
        if ($check->num_rows == 0) {
            $stmt = $conn->prepare("INSERT INTO vaccination_records (child_id, vaccine_id, date_administered, administered_by, batch_number, status) VALUES (?, ?, ?, ?, ?, 'Completed')");
            $stmt->bind_param("iisis", $child_id, $vaccine_id, $date_given, $doctor_id, $batch);
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

// ============================================
// HANDLE MILESTONES SUBMISSION
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_milestones'])) {
    $definition_ids = $_POST['definition_id'] ?? [];
    $achieved_vals = $_POST['achieved'] ?? [];
    $achieved_dates = $_POST['achieved_date'] ?? [];
    
    foreach ($definition_ids as $def_id) {
        $is_achieved = isset($achieved_vals[$def_id]) ? 1 : 0;
        $date = $achieved_dates[$def_id] ?? ($is_achieved ? date('Y-m-d') : null);
        
        $check = $conn->query("SELECT id FROM child_milestones WHERE child_id = $child_id AND definition_id = $def_id");
        if ($check->num_rows > 0) {
            $conn->query("UPDATE child_milestones SET achieved = $is_achieved, date_achieved = " . ($date ? "'$date'" : "NULL") . ", age_months = $age_months WHERE child_id = $child_id AND definition_id = $def_id");
        } else {
            if ($is_achieved) {
                $conn->query("INSERT INTO child_milestones (child_id, definition_id, achieved, date_achieved, age_months) VALUES ($child_id, $def_id, $is_achieved, " . ($date ? "'$date'" : "NULL") . ", $age_months)");
            }
        }
    }
    $message = "Milestones saved successfully!";
    $msg_type = "success";
}

// ============================================
// FETCH ALL DATA
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

// Vaccines
$all_vaccines = $conn->query("SELECT * FROM vaccines ORDER BY min_age_weeks");
$given_vaccines = $conn->query("SELECT vaccine_id FROM vaccination_records WHERE child_id = $child_id");
$given_ids = [];
while ($row = $given_vaccines->fetch_assoc()) {
    $given_ids[] = $row['vaccine_id'];
}

// Categorize vaccines
$due_vaccines = [];
$completed_vaccines = [];
$upcoming_vaccines = [];

while ($vax = $all_vaccines->fetch_assoc()) {
    if (in_array($vax['vaccine_id'], $given_ids)) {
        $completed_vaccines[] = $vax;
    } elseif ($age_weeks >= $vax['min_age_weeks']) {
        $vax['status'] = ($vax['max_age_weeks'] && $age_weeks > $vax['max_age_weeks']) ? 'overdue' : 'due';
        $due_vaccines[] = $vax;
    } else {
        $upcoming_vaccines[] = $vax;
    }
}

// Milestones
$milestones = $conn->query("SELECT * FROM milestone_definitions ORDER BY expected_age_min, sort_order");
$child_milestones = $conn->query("SELECT definition_id, achieved, date_achieved FROM child_milestones WHERE child_id = $child_id");
$milestone_map = [];
while ($m = $child_milestones->fetch_assoc()) {
    $milestone_map[$m['definition_id']] = $m;
}

// Growth history table
$growth_history = $conn->query("SELECT * FROM growth_records WHERE child_id = $child_id ORDER BY record_date DESC");
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
        
        .section { background:white; padding:25px; margin-bottom:30px; border-left:4px solid #0b1a33; }
        .section-header { margin-bottom:20px; border-bottom:2px solid #e2e8f0; padding-bottom:10px; display:flex; justify-content:space-between; align-items:center; }
        .section-header h2 { color:#0b1a33; font-size:20px; }
        
        .grid-2 { display:grid; grid-template-columns:1fr 1fr; gap:20px; }
        .grid-3 { display:grid; grid-template-columns:1fr 1fr 1fr; gap:15px; }
        
        .form-group { margin-bottom:15px; }
        .form-group label { display:block; margin-bottom:5px; color:#1e3a5f; font-weight:600; }
        .form-control { width:100%; padding:10px; border:1px solid #e2e8f0; border-radius:4px; }
        
        .btn { background:#0b1a33; color:white; padding:12px 25px; border:none; border-radius:6px; cursor:pointer; font-size:14px; }
        .btn:hover { background:#1e3a5f; }
        .btn-success { background:#10b981; }
        .btn-success:hover { background:#059669; }
        .btn-danger { background:#dc2626; }
        .btn-danger:hover { background:#b91c1c; }
        .btn-sm { background:#0b1a33; color:white; padding:5px 12px; border:none; border-radius:4px; font-size:12px; cursor:pointer; }
        
        .alert { padding:15px; margin-bottom:20px; border-radius:6px; }
        .alert.success { background:#d4edda; color:#155724; border-left:4px solid #28a745; }
        .alert.error { background:#f8d7da; color:#721c24; border-left:4px solid #dc3545; }
        .alert.info { background:#d1ecf1; color:#0c5460; border-left:4px solid #17a2b8; }
        
        table { width:100%; border-collapse:collapse; }
        th { background:#0b1a33; color:white; padding:10px; text-align:left; }
        td { padding:10px; border-bottom:1px solid #e2e8f0; }
        
        .back-link { display:inline-block; margin-bottom:20px; color:#0b1a33; text-decoration:none; }
        
        .badge { padding:4px 8px; border-radius:4px; font-size:12px; }
        .badge.due { background:#fff3cd; color:#856404; }
        .badge.overdue { background:#f8d7da; color:#721c24; }
        .badge.given { background:#d4edda; color:#155724; }
        
        .checkbox-col { width:30px; }
        .milestone-row { display:flex; align-items:center; padding:8px; border-bottom:1px solid #e2e8f0; gap:10px; }
        .milestone-name { flex:2; }
        .milestone-age { width:120px; color:#5a6f8c; }
        .milestone-date { width:150px; }
        
        .chart-container { height:250px; margin-bottom:30px; }
        
        .flag-btn { background:#dc2626; color:white; padding:8px 16px; text-decoration:none; border-radius:4px; }
        .flag-btn:hover { background:#b91c1c; }
    </style>
</head>
<body>
<div class="wrapper">
    <!-- Sidebar -->
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
    
    <!-- Main Content -->
    <div class="main">
        <a href="doctor_immunization_patients.php" class="back-link">← Back to Patients</a>
        
        <!-- Child Header -->
        <div class="child-header">
            <div>
                <div class="child-name"><?= htmlspecialchars($child['first_name'] . ' ' . $child['last_name']) ?></div>
                <div class="child-info">
                    Age: <?= $age_years ?> years <?= $age_remaining_months ?> months • 
                    DOB: <?= $child['date_of_birth'] ?>
                </div>
            </div>
            <a href="doctor_flag.php?child_id=<?= $child_id ?>" class="flag-btn">🚩 Flag for Review</a>
        </div>
        
        <?php if (isset($message)): ?>
        <div class="alert <?= $msg_type ?>"><?= $message ?></div>
        <?php endif; ?>
        
        <!-- ==================== VITALS SECTION ==================== -->
        <div class="section">
            <div class="section-header">
                <h2>📏 Record Vitals</h2>
            </div>
            <form method="POST">
                <div class="grid-3">
                    <div class="form-group">
                        <label>Weight (kg) *</label>
                        <input type="number" step="0.1" name="weight_kg" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>Height (cm) *</label>
                        <input type="number" step="0.1" name="height_cm" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>Head Circumference (cm)</label>
                        <input type="number" step="0.1" name="head_circumference" class="form-control">
                    </div>
                </div>
                <div class="form-group">
                    <label>Notes</label>
                    <textarea name="vital_notes" class="form-control" rows="2"></textarea>
                </div>
                <button type="submit" name="save_vitals" class="btn">Save Vitals</button>
            </form>
        </div>
        
        <!-- ==================== GROWTH CHARTS ==================== -->
        <div class="section">
            <div class="section-header">
                <h2>📈 Growth Charts</h2>
            </div>
            <div class="grid-2">
                <div>
                    <h3 style="margin-bottom:15px;">Weight-for-Age</h3>
                    <div class="chart-container">
                        <canvas id="weightChart"></canvas>
                    </div>
                </div>
                <div>
                    <h3 style="margin-bottom:15px;">Height-for-Age</h3>
                    <div class="chart-container">
                        <canvas id="heightChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- ==================== VACCINES SECTION ==================== -->
        <div class="section">
            <div class="section-header">
                <h2>💉 Vaccines</h2>
            </div>
            
            <?php if (!empty($due_vaccines)): ?>
            <form method="POST">
                <table>
                    <thead>
                        <tr>
                            <th class="checkbox-col">Give</th>
                            <th>Vaccine</th>
                            <th>Dose</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($due_vaccines as $vax): ?>
                        <tr>
                            <td><input type="checkbox" name="vaccine_ids[]" value="<?= $vax['vaccine_id'] ?>"></td>
                            <td><?= htmlspecialchars($vax['vaccine_name']) ?></td>
                            <td>Dose <?= $vax['dose_number'] ?></td>
                            <td><span class="badge <?= $vax['status'] ?>"><?= $vax['status'] ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <div style="margin-top:20px; display:flex; gap:15px; align-items:center;">
                    <div class="form-group" style="margin-bottom:0;">
                        <label>Date Given</label>
                        <input type="date" name="date_given" class="form-control" value="<?= date('Y-m-d') ?>" required>
                    </div>
                    <div class="form-group" style="margin-bottom:0;">
                        <label>Batch Number</label>
                        <input type="text" name="batch_number" class="form-control" placeholder="Enter batch #" required>
                    </div>
                    <button type="submit" name="give_vaccines" class="btn btn-success">Give Selected Vaccines</button>
                </div>
            </form>
            <?php else: ?>
            <p>No vaccines due at this time.</p>
            <?php endif; ?>
        </div>
        
        <!-- ==================== MILESTONES SECTION ==================== -->
        <div class="section">
            <div class="section-header">
                <h2>🧠 Developmental Milestones</h2>
            </div>
            <form method="POST">
                <?php
                $current_category = '';
                while ($m = $milestones->fetch_assoc()):
                    if ($current_category != $m['category']):
                        if ($current_category != '') echo '</div>';
                        $current_category = $m['category'];
                        echo '<div style="margin-bottom:20px;">';
                        echo '<h3 style="color:#0b1a33; margin-bottom:10px;">' . ucfirst($current_category) . '</h3>';
                    endif;
                    
                    $is_achieved = isset($milestone_map[$m['definition_id']]) && $milestone_map[$m['definition_id']]['achieved'] == 1;
                    $achieved_date = $milestone_map[$m['definition_id']]['date_achieved'] ?? '';
                ?>
                <div class="milestone-row">
                    <div class="milestone-check">
                        <input type="checkbox" name="achieved[<?= $m['definition_id'] ?>]" value="1" <?= $is_achieved ? 'checked' : '' ?>>
                    </div>
                    <div class="milestone-name"><?= htmlspecialchars($m['milestone_name']) ?></div>
                    <div class="milestone-age">Expected: <?= $m['expected_age_min'] ?>-<?= $m['expected_age_max'] ?> mo</div>
                    <div class="milestone-date">
                        <input type="date" name="achieved_date[<?= $m['definition_id'] ?>]" class="form-control" value="<?= $achieved_date ?>" placeholder="Date achieved">
                    </div>
                    <input type="hidden" name="definition_id[]" value="<?= $m['definition_id'] ?>">
                </div>
                <?php endwhile; ?>
                </div>
                <button type="submit" name="save_milestones" class="btn" style="margin-top:20px;">Save Milestones</button>
            </form>
        </div>
        
        <!-- ==================== GROWTH HISTORY ==================== -->
        <div class="section">
            <div class="section-header">
                <h2>📋 Growth History</h2>
            </div>
            <?php if ($growth_history->num_rows > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Weight (kg)</th>
                        <th>Height (cm)</th>
                        <th>Head (cm)</th>
                        <th>Notes</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($g = $growth_history->fetch_assoc()): ?>
                    <tr>
                        <td><?= date('M d, Y', strtotime($g['record_date'])) ?></td>
                        <td><?= $g['weight_kg'] ?></td>
                        <td><?= $g['height_cm'] ?></td>
                        <td><?= $g['head_circumference'] ?? '-' ?></td>
                        <td><?= htmlspecialchars($g['notes'] ?? '') ?></td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
            <?php else: ?>
            <p>No growth records yet.</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
// Weight Chart
const weightCtx = document.getElementById('weightChart').getContext('2d');
new Chart(weightCtx, {
    type: 'line',
    data: {
        labels: <?= json_encode($growth_dates) ?>,
        datasets: [{
            label: 'Weight (kg)',
            data: <?= json_encode($growth_weights) ?>,
            borderColor: '#0b1a33',
            backgroundColor: 'rgba(11,26,51,0.1)',
            tension: 0.3,
            fill: false
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { display: false }
        }
    }
});

// Height Chart
const heightCtx = document.getElementById('heightChart').getContext('2d');
new Chart(heightCtx, {
    type: 'line',
    data: {
        labels: <?= json_encode($growth_dates) ?>,
        datasets: [{
            label: 'Height (cm)',
            data: <?= json_encode($growth_heights) ?>,
            borderColor: '#10b981',
            backgroundColor: 'rgba(16,185,129,0.1)',
            tension: 0.3,
            fill: false
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { display: false }
        }
    }
});
</script>
</body>
</html>