<?php
session_start();
require_once __DIR__ . "/config/db.php";

// Security check
if (!isset($_SESSION['guardian_id']) || $_SESSION['user_type'] !== 'guardian') {
    header("Location: index.php");
    exit();
}

$guardian_id = $_SESSION['guardian_id'];
$child_id = $_SESSION['selected_child_id'] ?? null;

if (!$child_id) {
    header("Location: child_dashboard.php");
    exit();
}
$selected_child_id = $_SESSION['selected_child_id'] ?? null;

// to get notification count
$unread_count = 0;
if ($guardian_id && $selected_child_id) {
    $result = $conn->query("SELECT COUNT(*) as count FROM notifications WHERE guardian_id = $guardian_id AND child_id = $selected_child_id AND is_read = 0");
    $unread_count = $result->fetch_assoc()['count'];
}

// Get child details
$child_query = $conn->prepare("SELECT child_id, first_name, last_name, date_of_birth FROM children WHERE child_id = ? AND guardian_id = ?");
$child_query->bind_param("ii", $child_id, $guardian_id);
$child_query->execute();
$child = $child_query->get_result()->fetch_assoc();
$child_query->close();

if (!$child) {
    header("Location: child_dashboard.php");
    exit();
}

// Calculate age
$dob = new DateTime($child['date_of_birth']);
$today = new DateTime();
$age_years = $dob->diff($today)->y;
$age_months = $dob->diff($today)->m + ($age_years * 12);
$age_weeks = floor($dob->diff($today)->days / 7);

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
// FETCH ALL MEDICAL DATA
// ============================================

// 1. Specialist Reviews/Assessments
$visits = $conn->query("
    SELECT 
        sr.review_id,
        sr.review_date,
        sr.diagnosis,
        sr.diagnosis_notes,
        sr.treatment_plan,
        sr.lab_orders,
        sr.referrals,
        sr.follow_up_date,
        sr.status,
        d.full_name AS doctor_name,
        d.doctor_role,
        d.specialization
    FROM specialist_reviews sr
    JOIN doctors d ON sr.doctor_id = d.doctor_id
    WHERE sr.child_id = $child_id
    ORDER BY sr.review_date DESC
");

// 3. Vaccinations - Completed
$vaccines = $conn->query("
    SELECT vr.*, v.vaccine_name, v.dose_number, d.full_name AS doctor_name
    FROM vaccination_records vr
    JOIN vaccines v ON vr.vaccine_id = v.vaccine_id
    LEFT JOIN doctors d ON vr.administered_by = d.doctor_id
    WHERE vr.child_id = $child_id
    ORDER BY vr.date_administered DESC
");

// 4. All vaccines for upcoming calculation
$all_vaccines = $conn->query("SELECT * FROM vaccines ORDER BY min_age_weeks");
$given_vaccines = $conn->query("SELECT vaccine_id FROM vaccination_records WHERE child_id = $child_id");
$given_ids = [];
while ($row = $given_vaccines->fetch_assoc()) {
    $given_ids[] = $row['vaccine_id'];
}

// Calculate upcoming vaccines
$upcoming_vaccines = [];
while ($vax = $all_vaccines->fetch_assoc()) {
    $vax_id = $vax['vaccine_id'];
    
    if (in_array($vax_id, $given_ids)) {
        continue;
    }
    
    $est_date = date('M d, Y', strtotime($child['date_of_birth'] . " + {$vax['min_age_weeks']} weeks"));
    
    if ($age_weeks >= $vax['min_age_weeks']) {
        $status = 'overdue';
        $status_text = 'Overdue';
    } else {
        $status = 'upcoming';
        $status_text = 'Upcoming';
    }
    
    $upcoming_vaccines[] = [
        'name' => $vax['vaccine_name'],
        'dose' => $vax['dose_number'],
        'due_age' => $vax['min_age_weeks'] . ' weeks',
        'est_date' => $est_date,
        'status' => $status,
        'status_text' => $status_text
    ];
}

// 5. Child's achieved milestones
$child_milestones = $conn->query("
    SELECT milestone_number, achieved, date_achieved 
    FROM child_milestones 
    WHERE child_id = $child_id
");

$milestone_map = [];
while ($m = $child_milestones->fetch_assoc()) {
    $milestone_map[$m['milestone_number']] = $m;
}

// 6. Teeth
$teeth = $conn->query("
    SELECT ct.*, td.tooth_type, td.expected_age_min, td.expected_age_max
    FROM child_teeth ct
    JOIN teeth_definitions td ON ct.tooth_id = td.tooth_id
    WHERE ct.child_id = $child_id
    ORDER BY td.expected_age_min
");

// 7. Growth records for charts
$growth_chart = $conn->query("
    SELECT * FROM growth_records 
    WHERE child_id = $child_id 
    ORDER BY record_date ASC
");

$growth_dates = [];
$growth_weights = [];
$growth_heights = [];
$growth_heads = [];

while ($g = $growth_chart->fetch_assoc()) {
    $growth_dates[] = date('M d, Y', strtotime($g['record_date']));
    $growth_weights[] = floatval($g['weight_kg']);
    $growth_heights[] = floatval($g['height_cm']);
    $growth_heads[] = $g['head_circumference'] ? floatval($g['head_circumference']) : null;
}
$growth_chart->data_seek(0);

// 8. Growth records for table
$growth = $conn->query("
    SELECT g.*, d.full_name AS doctor_name
    FROM growth_records g
    JOIN doctors d ON g.doctor_id = d.doctor_id
    WHERE g.child_id = $child_id
    ORDER BY g.record_date DESC
");

// 9. Lab results
$labs = $conn->query("
    SELECT l.*, d.full_name AS doctor_name
    FROM lab_results l
    LEFT JOIN doctors d ON l.doctor_id = d.doctor_id
    WHERE l.child_id = $child_id
    ORDER BY l.test_date DESC
");

// 10. Check for developmental delays using database values
$delays = [];
foreach ($milestone_data as $num => $data) {
    if (!isset($milestone_map[$num]) && $age_months > $data['min_age']) {
        $delays[] = "Milestone " . $num . " (by " . $data['expected_range'] . ")";
    }
}
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD HTML 4.01 Strict//EN">
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <title>Medical History - PCASS</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body { background:#f0f4fc; font-family:Arial; }
        .wrapper { display:flex; min-height:100vh; }
        .main { margin-left:260px; padding:30px; background:#f0f4fc; flex:1; }
        
        .page-header { margin-bottom:25px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; }
        .page-header h1 { color:#0b1a33; font-size:28px; margin-bottom:5px; }
        .page-header p { color:#5a6f8c; }
        .child-age-info { background:white; padding:10px 20px; border-left:4px solid #0b1a33; font-size:14px; }
        
        .card { background:white; border-radius:8px; margin-bottom:25px; border-left:4px solid #0b1a33; overflow:hidden; }
        .card-header { background:#f8fafd; padding:15px 20px; border-bottom:2px solid #e2e8f0; display:flex; align-items:center; }
        .card-header h3 { color:#0b1a33; font-size:18px; font-weight:700; margin:0; flex:1; }
        .card-header .count { background:#0b1a33; color:white; padding:3px 10px; border-radius:20px; font-size:12px; }
        .card-body { padding:20px; }
        
        .allergy-chips { display:flex; flex-wrap:wrap; gap:10px; }
        .allergy-chip { background:#f8fafd; padding:8px 12px; border-left:4px solid; border-radius:4px; }
        .allergy-chip.severe, .allergy-chip.life-threatening { border-left-color:#dc2626; }
        .allergy-chip.moderate { border-left-color:#f59e0b; }
        .allergy-chip.mild { border-left-color:#10b981; }
        
        table { width:100%; border-collapse:collapse; font-size:14px; }
        th { background:#0b1a33; color:white; padding:8px; text-align:left; }
        td { padding:8px; border-bottom:1px solid #e2e8f0; }
        
        .badge { padding:2px 8px; border-radius:4px; font-size:12px; display:inline-block; }
        .badge.achieved { background:#10b981; color:white; }
        .badge.pending { background:#fff3cd; color:#856404; }
        .badge.delay { background:#fee2e2; color:#dc2626; }
        .badge.specialist { background:#6b21a8; color:white; }
        .badge.upcoming { background:#fff3cd; color:#856404; }
        .badge.overdue { background:#fee2e2; color:#dc2626; }
        
        .grid-2 { display:grid; grid-template-columns:1fr 1fr; gap:20px; }
        .grid-4 { display:grid; grid-template-columns:repeat(4,1fr); gap:15px; }
        
        .empty { text-align:center; padding:40px; color:#5a6f8c; font-style:italic; }
        
        .delay-box { background:#fee2e2; padding:15px; border-left:4px solid #dc2626; margin-bottom:20px; }
        .delay-box h4 { color:#991b1b; margin-bottom:10px; }
        
        .milestone-table td { vertical-align:top; }
        
        .chart-container { height:300px; margin-bottom:20px; }
        .chart-title { font-size:16px; font-weight:700; color:#0b1a33; margin-bottom:15px; }
        
        .assessment-item { border:1px solid #e2e8f0; margin-bottom:20px; border-radius:4px; overflow:hidden; }
        .assessment-header { background:#f8fafd; padding:15px; border-bottom:1px solid #e2e8f0; display:flex; justify-content:space-between; align-items:center; }
        .assessment-body { padding:15px; }
        .assessment-section { margin-bottom:20px; padding-bottom:15px; border-bottom:1px solid #e2e8f0; }
        .assessment-section:last-child { border-bottom:none; margin-bottom:0; padding-bottom:0; }
        
        .med-item { background:#f8fafd; padding:12px; margin-bottom:8px; border-left:4px solid #0b1a33; border-radius:0 4px 4px 0; }
        .med-item:last-child { margin-bottom:0; }
        
        .lab-item { background:#f8fafd; padding:10px; margin-bottom:8px; border-left:3px solid #0891b2; }
        
        .download-link { color:#0b1a33; text-decoration:none; font-weight:600; }
        
        .section-divider { margin:20px 0 10px; border-top:1px solid #e2e8f0; }
        .section-title { color:#0b1a33; font-size:16px; font-weight:700; margin:20px 0 10px; }
    </style>
</head>
<body>
<div class="wrapper">
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-header">
            <h2>PCASS</h2>
            <p>Child and Guardian Portal</p>
        </div>
        <div class="nav">
            <ul>
                <li><a href="child_dashboard.php">Dashboard</a></li>
                <li><a href="appointments.php">Appointments</a></li>
                <li><a href="medical-history.php" class="active">Medical History</a></li>
                <li><a href="notifications.php"> Notifications
                    <?php if ($unread_count > 0): ?>
                        <span style="background:#dc2626; color:white; padding:2px 8px; border-radius:12px; font-size:11px; margin-left:8px;"><?= $unread_count ?></span>
                    <?php endif; ?>
                </a></li>
                <li><a href="profile.php">Profile</a></li>
                <li><a href="logout.php">Logout</a></li>
            </ul>
        </div>
    </div>
    
    <!-- Main Content -->
    <div class="main">
        <div class="page-header">
            <div>
                <h1>Medical History</h1>
                <p><?= htmlspecialchars($child['first_name'] . ' ' . $child['last_name']) ?></p>
            </div>
            <div class="child-age-info">
                Age: <?= $age_years ?> years (<?= $age_months ?> months) • 
                DOB: <?= $child['date_of_birth'] ?> •
            </div>
        </div>
        
        <!-- Specialist Assessments -->
        <div class="card">
            <div class="card-header">
                <h3>Specialist Assessments</h3>
                <span class="count"><?= $visits->num_rows ?></span>
            </div>
            <div class="card-body">
                <?php if ($visits->num_rows == 0): ?>
                    <div class="empty">No assessments recorded</div>
                <?php else: ?>
                    <?php while ($v = $visits->fetch_assoc()): 
                        $review_id = $v['review_id'];
                        $review_prescriptions = $conn->query("
                            SELECT p.*, d.full_name AS doctor_name
                            FROM prescriptions p
                            LEFT JOIN doctors d ON p.doctor_id = d.doctor_id
                            WHERE p.review_id = $review_id
                            ORDER BY p.prescription_id
                        ");
                        
                        $review_labs = $conn->query("
                            SELECT l.*, d.full_name AS doctor_name
                            FROM lab_results l
                            LEFT JOIN doctors d ON l.doctor_id = d.doctor_id
                            WHERE l.review_id = $review_id
                            ORDER BY l.test_date DESC
                        ");
                    ?>
                    <div class="assessment-item">
                        <div class="assessment-header">
                            <div>
                                <span style="font-weight:700; color:#0b1a33;"><?= date('M d, Y', strtotime($v['review_date'])) ?></span>
                                <span class="badge specialist" style="margin-left:10px;">Specialist</span>
                                <?php if ($v['status']): ?>
                                <span class="badge" style="background:#d4edda; color:#155724; margin-left:5px;"><?= ucfirst($v['status']) ?></span>
                                <?php endif; ?>
                            </div>
                            <div style="text-align:right;">
                                <div style="font-size:14px; color:#1e3a5f;">Dr. <?= htmlspecialchars($v['doctor_name']) ?></div>
                                <div style="font-size:12px; color:#5a6f8c;"><?= htmlspecialchars($v['specialization']) ?></div>
                            </div>
                        </div>
                        
                        <div class="assessment-body">
                            <!-- Diagnosis -->
                            <div class="assessment-section">
                                <strong>Diagnosis:</strong>
                                <p style="margin-top:5px;"><?= htmlspecialchars($v['diagnosis'] ?? 'Not specified') ?></p>
                                <?php if ($v['diagnosis_notes']): ?>
                                <p style="margin-top:5px; color:#1e293b; background:#f8fafd; padding:8px; border-radius:4px;">
                                    <?= nl2br(htmlspecialchars($v['diagnosis_notes'])) ?>
                                </p>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Treatment Plan -->
                            <?php if ($v['treatment_plan']): ?>
                            <div class="assessment-section">
                                <strong>Treatment Plan:</strong>
                                <p style="margin-top:5px; background:#f8fafd; padding:8px; border-radius:4px;">
                                    <?= nl2br(htmlspecialchars($v['treatment_plan'])) ?>
                                </p>
                            </div>
                            <?php endif; ?>
                            
                            <!-- Lab Orders -->
                            <?php if ($v['lab_orders']): ?>
                            <div class="assessment-section">
                                <strong>Lab Tests Ordered:</strong>
                                <p style="margin-top:5px; white-space:pre-line; background:#f8fafd; padding:8px; border-radius:4px;">
                                    <?= nl2br(htmlspecialchars($v['lab_orders'])) ?>
                                </p>
                            </div>
                            <?php endif; ?>
                            
                            <!-- Lab Results -->
                            <?php if ($review_labs->num_rows > 0): ?>
                            <div class="assessment-section">
                                <strong>Lab Results:</strong>
                                <?php while ($lab = $review_labs->fetch_assoc()): ?>
                                <div class="lab-item">
                                    <div style="display:flex; justify-content:space-between;">
                                        <strong><?= htmlspecialchars($lab['test_name']) ?></strong>
                                        <span style="font-size:12px;"><?= date('M d, Y', strtotime($lab['test_date'])) ?></span>
                                    </div>
                                    <?php if ($lab['result_value']): ?>
                                    <div style="margin-top:5px;"><strong>Result:</strong> <?= htmlspecialchars($lab['result_value']) ?></div>
                                    <?php endif; ?>
                                    <?php if ($lab['result_summary']): ?>
                                    <div style="margin-top:5px; font-size:13px;"><?= htmlspecialchars($lab['result_summary']) ?></div>
                                    <?php endif; ?>
                                    <?php if (!empty($lab['attachment'])): ?>
                                    <div style="margin-top:5px;">
                                        <a href="<?= htmlspecialchars($lab['attachment']) ?>" class="download-link" download>Download Report</a>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                <?php endwhile; ?>
                            </div>
                            <?php endif; ?>
                            
                            <!-- Prescriptions -->
                            <?php if ($review_prescriptions->num_rows > 0): ?>
                            <div class="assessment-section">
                                <strong>Prescriptions:</strong>
                                <?php while ($p = $review_prescriptions->fetch_assoc()): ?>
                                <div class="med-item">
                                    <div style="display:flex; justify-content:space-between;">
                                        <strong><?= htmlspecialchars($p['medication_name']) ?></strong>
                                        <span style="font-size:12px;"><?= date('M d, Y', strtotime($p['start_date'])) ?></span>
                                    </div>
                                    <div style="margin-top:5px; font-size:13px;">
                                        <span class="med-label">Dosage:</span> <?= htmlspecialchars($p['dosage']) ?>
                                        <?php if ($p['frequency']): ?> • <span class="med-label">Frequency:</span> <?= htmlspecialchars($p['frequency']) ?><?php endif; ?>
                                        <?php if ($p['duration']): ?> • <span class="med-label">Duration:</span> <?= htmlspecialchars($p['duration']) ?><?php endif; ?>
                                    </div>
                                    <?php if ($p['instructions']): ?>
                                    <div style="margin-top:5px; font-size:12px; color:#5a6f8c;"><?= nl2br(htmlspecialchars($p['instructions'])) ?></div>
                                    <?php endif; ?>
                                </div>
                                <?php endwhile; ?>
                            </div>
                            <?php endif; ?>
                            
                            <!-- Referrals -->
                            <?php if ($v['referrals']): ?>
                            <div class="assessment-section">
                                <strong>Referrals:</strong>
                                <p style="margin-top:5px; background:#f8fafd; padding:8px; border-radius:4px;">
                                    <?= nl2br(htmlspecialchars($v['referrals'])) ?>
                                </p>
                            </div>
                            <?php endif; ?>
                            
                            <!-- Follow-up -->
                            <?php if ($v['follow_up_date']): ?>
                            <div class="assessment-section">
                                <strong>Follow-up Appointment:</strong>
                                <p style="margin-top:5px; background:#f0fdf4; padding:8px; border-left:4px solid #10b981;">
                                    <?= date('l, F j, Y', strtotime($v['follow_up_date'])) ?>
                                </p>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endwhile; ?>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Vaccinations -->
        <div class="card">
            <div class="card-header">
                <h3>Vaccination Records</h3>
                <span class="count"><?= $vaccines->num_rows ?> given</span>
            </div>
            <div class="card-body">
                <!-- Completed Vaccines -->
                <div class="section-title">Completed Vaccines</div>
                <?php if ($vaccines->num_rows == 0): ?>
                    <div class="empty">No vaccines recorded yet</div>
                <?php else: ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Date Given</th>
                                <th>Vaccine</th>
                                <th>Dose</th>
                                <th>Given By</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($v = $vaccines->fetch_assoc()): ?>
                            <tr>
                                <td><?= date('M d, Y', strtotime($v['date_administered'])) ?></td>
                                <td><?= htmlspecialchars($v['vaccine_name']) ?></td>
                                <td>Dose <?= $v['dose_number'] ?></td>
                                <td>Dr. <?= htmlspecialchars($v['doctor_name'] ?? 'Unknown') ?></td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
                
                <!-- Upcoming Vaccines -->
                <?php if (!empty($upcoming_vaccines)): ?>
                <div class="section-divider"></div>
                <div class="section-title">Upcoming Vaccines</div>
                <table>
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
                        <?php foreach ($upcoming_vaccines as $uv): ?>
                        <tr>
                            <td><?= htmlspecialchars($uv['name']) ?></td>
                            <td>Dose <?= $uv['dose'] ?></td>
                            <td><?= $uv['due_age'] ?></td>
                            <td><?= $uv['est_date'] ?></td>
                            <td>
                                <span class="badge <?= $uv['status'] ?>" 
                                      style="<?= $uv['status'] == 'overdue' ? 'background:#fee2e2; color:#dc2626;' : 'background:#fff3cd; color:#856404;' ?>">
                                    <?= $uv['status_text'] ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <p style="font-size:12px; color:#5a6f8c; margin-top:10px;">
                    * Estimated dates are based on your child's age and standard vaccination schedule.
                    Please consult your doctor for exact timing.
                </p>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Growth Charts -->
        <?php if ($growth_chart->num_rows > 0): ?>
        <div class="card">
            <div class="card-header">
                <h3>Growth Charts</h3>
            </div>
            <div class="card-body">
                <div class="grid-2">
                    <div>
                        <div class="chart-title">Weight-for-Age</div>
                        <div class="chart-container">
                            <canvas id="weightChart"></canvas>
                        </div>
                    </div>
                    <div>
                        <div class="chart-title">Height-for-Age</div>
                        <div class="chart-container">
                            <canvas id="heightChart"></canvas>
                        </div>
                    </div>
                </div>
                <?php 
                $has_head_data = false;
                foreach ($growth_heads as $head) {
                    if ($head > 0) {
                        $has_head_data = true;
                        break;
                    }
                }
                if ($has_head_data): 
                ?>
                <div style="margin-top:20px;">
                    <div class="chart-title">Head Circumference</div>
                    <div class="chart-container" style="height:250px;">
                        <canvas id="headChart"></canvas>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Growth Records Table -->
        <div class="card">
            <div class="card-header">
                <h3>Growth Records</h3>
                <span class="count"><?= $growth->num_rows ?></span>
            </div>
            <div class="card-body">
                <?php if ($growth->num_rows == 0): ?>
                    <div class="empty">No growth records</div>
                <?php else: ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Weight</th>
                                <th>Height</th>
                                <th>Head</th>
                                <th>Doctor</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($g = $growth->fetch_assoc()): ?>
                            <tr>
                                <td><?= date('M d, Y', strtotime($g['record_date'])) ?></td>
                                <td><?= $g['weight_kg'] ?> kg</td>
                                <td><?= $g['height_cm'] ?> cm</td>
                                <td><?= $g['head_circumference'] ?? '-' ?> cm</td>
                                <td>Dr. <?= htmlspecialchars($g['doctor_name']) ?></td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Milestones -->
        <div class="card">
            <div class="card-header">
                <h3>Developmental Milestones</h3>
                <span class="count"><?= count($milestone_map) ?> of <?= count($milestone_data) ?> achieved</span>
            </div>
            <div class="card-body">
                <table class="milestone-table">
                    <thead>
                        <tr>
                            <th>Milestone</th>
                            <th>Expected</th>
                            <th>Status</th>
                            <th>Date Achieved</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($milestone_data as $num => $data): 
                            $achieved = isset($milestone_map[$num]);
                            $is_delayed = !$achieved && ($age_months > $data['min_age']);
                        ?>
                        <tr>
                            <td><?= $data['description'] ?></td>
                            <td><?= $data['expected_range'] ?></td>
                            <td>
                                <?php if ($achieved): ?>
                                    <span class="badge achieved">Achieved</span>
                                <?php elseif ($is_delayed): ?>
                                    <span class="badge delay">Delayed</span>
                                <?php else: ?>
                                    <span class="badge pending">Pending</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?= $achieved ? date('M d, Y', strtotime($milestone_map[$num]['date_achieved'])) : '-' ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Teeth -->
        <div class="card">
            <div class="card-header">
                <h3>Teeth Development</h3>
                <span class="count"><?= $teeth->num_rows ?> emerged</span>
            </div>
            <div class="card-body">
                <?php if ($teeth->num_rows == 0): ?>
                    <div class="empty">No teeth records</div>
                <?php else: ?>
                    <div class="grid-4">
                        <?php while ($t = $teeth->fetch_assoc()): ?>
                        <div style="border:1px solid #e2e8f0; padding:10px; border-radius:4px;">
                            <strong><?= htmlspecialchars($t['tooth_type']) ?></strong><br>
                            <span style="font-size:13px;"><?= date('M d, Y', strtotime($t['emerged_date'])) ?></span><br>
                            <small style="color:#5a6f8c;">Expected: <?= $t['expected_age_min'] ?>-<?= $t['expected_age_max'] ?> mo</small>
                        </div>
                        <?php endwhile; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Lab Results -->
        <?php if ($labs->num_rows > 0): ?>
        <div class="card">
            <div class="card-header">
                <h3>Lab Results</h3>
                <span class="count"><?= $labs->num_rows ?></span>
            </div>
            <div class="card-body">
                <?php while ($l = $labs->fetch_assoc()): ?>
                <div class="lab-item">
                    <div style="display:flex; justify-content:space-between;">
                        <strong><?= htmlspecialchars($l['test_name']) ?></strong>
                        <span style="font-size:12px;"><?= date('M d, Y', strtotime($l['test_date'])) ?></span>
                    </div>
                    <?php if ($l['result_value']): ?>
                    <div style="margin-top:5px;"><strong>Result:</strong> <?= htmlspecialchars($l['result_value']) ?></div>
                    <?php endif; ?>
                    <?php if ($l['result_summary']): ?>
                    <div style="margin-top:5px; font-size:13px;"><?= htmlspecialchars($l['result_summary']) ?></div>
                    <?php endif; ?>
                    <div style="margin-top:5px; font-size:11px; color:#5a6f8c;">
                        Dr. <?= htmlspecialchars($l['doctor_name'] ?? 'Unknown') ?>
                        <?php if (!empty($l['attachment'])): ?> • <a href="<?= htmlspecialchars($l['attachment']) ?>" class="download-link" download>Download</a><?php endif; ?>
                    </div>
                </div>
                <?php endwhile; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
<?php if ($growth_chart->num_rows > 0): ?>
new Chart(document.getElementById('weightChart'), {
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
        plugins: { legend: { display: false } }
    }
});

new Chart(document.getElementById('heightChart'), {
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
        plugins: { legend: { display: false } }
    }
});

<?php 
$has_head_data = false;
foreach ($growth_heads as $head) {
    if ($head > 0) {
        $has_head_data = true;
        break;
    }
}
if ($has_head_data): 
?>
new Chart(document.getElementById('headChart'), {
    type: 'line',
    data: {
        labels: <?= json_encode($growth_dates) ?>,
        datasets: [{
            label: 'Head Circumference (cm)',
            data: <?= json_encode($growth_heads) ?>,
            borderColor: '#f59e0b',
            backgroundColor: 'rgba(245,158,11,0.1)',
            tension: 0.3,
            fill: false
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false } }
    }
});
<?php endif; ?>
<?php endif; ?>
</script>

</body>
</html>
<?php $conn->close(); ?>