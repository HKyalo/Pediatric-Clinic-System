<?php
// Set session to last 24 hours 
ini_set('session.cookie_lifetime', 86400); // 24 hours in seconds
ini_set('session.gc_maxlifetime', 86400);   // 24 hours in seconds
session_set_cookie_params(86400); // Also set cookie params
session_start();
require_once __DIR__ . "/config/db.php";

// ============================================
// SECURITY CHECK
// ============================================
if (!isset($_SESSION['admin_id']) || $_SESSION['user_type'] !== 'admin') {
    header("Location: index.php");
    exit();
}

// ============================================
// PATIENT REPORTS
// ============================================

// Total patients
$total_patients = $conn->query("SELECT COUNT(*) as c FROM children")->fetch_assoc()['c'];

// Gender distribution
$gender_stats = $conn->query("SELECT gender, COUNT(*) as count FROM children GROUP BY gender");

// Age distribution
$age_stats = $conn->query("
    SELECT 
        CASE 
            WHEN TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) < 1 THEN 'Infant (0-1)'
            WHEN TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) BETWEEN 1 AND 5 THEN 'Toddler (1-5)'
            WHEN TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) BETWEEN 6 AND 12 THEN 'Child (6-12)'
            WHEN TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) BETWEEN 13 AND 17 THEN 'Teen (13-17)'
            ELSE 'Adult (18+)'
        END as age_group,
        COUNT(*) as count
    FROM children
    GROUP BY age_group
");

// New registrations (last 6 months)
$new_patients = $conn->query("
    SELECT DATE_FORMAT(created_at, '%Y-%m') as month, COUNT(*) as count
    FROM children
    WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
    GROUP BY month
    ORDER BY month
");

// Guardians per child average (rounded to whole number)
$avg_guardians = $conn->query("
    SELECT AVG(child_count) as avg
    FROM (SELECT guardian_id, COUNT(*) as child_count FROM children GROUP BY guardian_id) as counts
")->fetch_assoc()['avg'];
$avg_guardians_display = $avg_guardians ? round($avg_guardians) : 0;

// ============================================
// APPOINTMENT REPORTS
// ============================================

$total_appointments = $conn->query("SELECT COUNT(*) as c FROM appointments")->fetch_assoc()['c'];

// Appointments by status
$status_stats = $conn->query("
    SELECT status, COUNT(*) as count, 
           ROUND(COUNT(*) * 100.0 / (SELECT COUNT(*) FROM appointments), 1) as percentage
    FROM appointments 
    GROUP BY status
");

// Cancellation rate
$cancelled = $conn->query("SELECT COUNT(*) as c FROM appointments WHERE status = 'Cancelled'")->fetch_assoc()['c'];
$cancelled_rate = $total_appointments > 0 ? round(($cancelled / $total_appointments) * 100, 1) : 0;

// Today's and upcoming
$today = date('Y-m-d');
$today_count = $conn->query("SELECT COUNT(*) as c FROM appointments WHERE appointment_date = '$today'")->fetch_assoc()['c'];

$next_week = date('Y-m-d', strtotime('+7 days'));
$upcoming_count = $conn->query("
    SELECT COUNT(*) as c FROM appointments 
    WHERE appointment_date BETWEEN '$today' AND '$next_week'
")->fetch_assoc()['c'];

// Monthly trends
$monthly_appointments = $conn->query("
    SELECT DATE_FORMAT(appointment_date, '%Y-%m') as month, COUNT(*) as count
    FROM appointments
    WHERE appointment_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
    GROUP BY month
    ORDER BY month
");

// ============================================
// DOCTOR REPORTS
// ============================================

$total_doctors = $conn->query("SELECT COUNT(*) as c FROM doctors")->fetch_assoc()['c'];
$immunization_doctors = $conn->query("SELECT COUNT(*) as c FROM doctors WHERE doctor_role = 'immunization'")->fetch_assoc()['c'];
$specialist_doctors = $conn->query("SELECT COUNT(*) as c FROM doctors WHERE doctor_role = 'specialist'")->fetch_assoc()['c'];

// Doctor workload
$doctor_workload = $conn->query("
    SELECT d.full_name, d.doctor_role, COUNT(a.appointment_id) as count
    FROM doctors d
    LEFT JOIN appointments a ON d.doctor_id = a.doctor_id
    GROUP BY d.doctor_id
    ORDER BY count DESC
");

// ============================================
// FLAG REPORTS
// ============================================

$total_flags = $conn->query("SELECT COUNT(*) as c FROM flags")->fetch_assoc()['c'];
$pending_flags = $conn->query("SELECT COUNT(*) as c FROM flags WHERE status = 'new' OR status = 'in_review'")->fetch_assoc()['c'];
$resolved_flags = $conn->query("SELECT COUNT(*) as c FROM flags WHERE status = 'resolved'")->fetch_assoc()['c'];

// ============================================
// PREPARE CHART DATA
// ============================================

// Patient registration trend
$reg_months = [];
$reg_counts = [];
while ($row = $new_patients->fetch_assoc()) {
    $reg_months[] = date('M Y', strtotime($row['month'] . '-01'));
    $reg_counts[] = $row['count'];
}

// Appointment trend
$apt_months = [];
$apt_counts = [];
while ($row = $monthly_appointments->fetch_assoc()) {
    $apt_months[] = date('M Y', strtotime($row['month'] . '-01'));
    $apt_counts[] = $row['count'];
}

// Gender data
$gender_labels = [];
$gender_counts = [];
while ($row = $gender_stats->fetch_assoc()) {
    $gender_labels[] = $row['gender'];
    $gender_counts[] = $row['count'];
}

// Age data
$age_labels = [];
$age_counts = [];
while ($row = $age_stats->fetch_assoc()) {
    $age_labels[] = $row['age_group'];
    $age_counts[] = $row['count'];
}

// Status data
$status_labels = [];
$status_counts = [];
while ($row = $status_stats->fetch_assoc()) {
    $status_labels[] = $row['status'];
    $status_counts[] = $row['count'];
}
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD HTML 4.01 Strict//EN">
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <title>Reports - PCASS</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            background: #f0f4fc;
        }
        
        .wrapper { display: flex; min-height: 100vh; }
        
        .main-content {
            margin-left: 260px;
            padding: 30px;
            background: #f0f4fc;
            flex: 1;
        }
        
        /* Page Header */
        .page-header {
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .page-header h1 {
            color: #0b1a33;
            font-size: 26px;
            font-weight: 700;
        }
        
        .page-header p {
            color: #5a6f8c;
            font-size: 14px;
            margin-top: 5px;
        }
        
        .print-btn {
            background: #0b1a33;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 600;
            font-size: 13px;
        }
        
        .print-btn:hover {
            background: #1e3a5f;
        }
        
        /* Section Title */
        .section-title {
            color: #0b1a33;
            font-size: 18px;
            font-weight: 700;
            margin: 30px 0 20px;
            padding-bottom: 8px;
            border-bottom: 2px solid #0b1a33;
            letter-spacing: 0.5px;
        }
        
        /* Stats Row */
        .stats-row {
            display: flex;
            gap: 15px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        
        .stat-mini {
            background: white;
            padding: 15px 25px;
            border-radius: 4px;
            border-left: 4px solid #0b1a33;
            min-width: 150px;
            flex: 1;
        }
        
        .stat-mini .num {
            font-size: 28px;
            font-weight: 700;
            color: #0b1a33;
            line-height: 1.2;
        }
        
        .stat-mini .label {
            color: #5a6f8c;
            font-size: 12px;
            text-transform: uppercase;
        }
        
        .stat-mini .small {
            font-size: 11px;
            color: #5a6f8c;
            margin-top: 3px;
        }
        
        /* Grid */
        .grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .grid-4 {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .grid-3 {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin-bottom: 20px;
        }
        
        /* Card */
        .card {
            background: white;
            border-radius: 4px;
            box-shadow: 0 2px 10px rgba(11,26,51,0.07);
            border-left: 4px solid #0b1a33;
            overflow: hidden;
            margin-bottom: 20px;
        }
        
        .card-header {
            background: #f8fafd;
            padding: 15px 20px;
            border-bottom: 1px solid #e8edf5;
        }
        
        .card-header h3 {
            color: #0b1a33;
            font-size: 15px;
            font-weight: 700;
            margin: 0;
        }
        
        .card-body {
            padding: 20px;
        }
        
        /* Chart container */
        .chart-container {
            height: 200px;
            width: 100%;
        }
        
        /* Table */
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }
        
        th {
            background: #f8fafd;
            color: #0b1a33;
            padding: 8px 10px;
            text-align: left;
            font-weight: 600;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        td {
            padding: 8px 10px;
            border-bottom: 1px solid #e8edf5;
            color: #1e293b;
        }
        
        tr:hover td {
            background: #f8fafd;
        }
        
        .badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 10px;
            font-weight: 600;
        }
        
        .badge.immunization {
            background: #e6f0ff;
            color: #0b1a33;
        }
        
        .badge.specialist {
            background: #f3e8ff;
            color: #6b21a8;
        }
        
        .badge.pending {
            background: #f59e0b;
            color: white;
        }
        
        .badge.resolved {
            background: #10b981;
            color: white;
        }
        
        /* Print styles */
        @media print {
            .sidebar, .print-btn {
                display: none;
            }
            
            .main-content {
                margin-left: 0;
                padding: 20px;
            }
            
            .card {
                break-inside: avoid;
                box-shadow: none;
                border: 1px solid #ddd;
            }
        }
    </style>
</head>
<body>
<div class="wrapper">
    
    <!-- SIDEBAR -->
    <div class="sidebar">
        <div class="sidebar-header">
            <h2>PCASS</h2>
            <p>Admin Portal</p>
        </div>
        <div class="nav">
            <ul>
                <li><a href="admin_dashboard.php">Dashboard</a></li>
                <li><a href="manage_doctors.php">Manage Doctors</a></li>
                <li><a href="manage_guardians.php">Manage Guardians</a></li>
                <li><a href="manage_children.php">Manage Children</a></li>
                <li><a href="manage_appointments.php">Manage Appointments</a></li>
                <li><a href="manage_vaccines.php">Manage Vaccines</a></li>
                <li><a href="admin_reports.php" class="active">Reports</a></li>
                <li><a href="logout.php">Logout</a></li>
            </ul>
        </div>
    </div>
    
    <!-- MAIN CONTENT -->
    <div class="main-content">
        
        <div class="page-header">
            <div>
                <h1>System Reports</h1>
                <p>Analytics and insights for <?= date('F Y') ?></p>
            </div>
            <button class="print-btn" onclick="window.print()">Print Report</button>
        </div>
        
        <!-- ========== PATIENT REPORTS ========== -->
        <div class="section-title">Patient Demographics</div>
        
        <!-- Stats Row -->
        <div class="grid-4">
            <div class="stat-mini">
                <div class="num"><?= $total_patients ?></div>
                <div class="label">Total Patients</div>
            </div>
            <div class="stat-mini">
                <div class="num"><?= array_sum($age_counts) ?></div>
                <div class="label">Active Patients</div>
            </div>
            <div class="stat-mini">
                <div class="num"><?= $avg_guardians_display ?></div>
                <div class="label">Avg Children/Family</div>
            </div>
            <div class="stat-mini">
                <div class="num"><?= $total_patients ?></div>
                <div class="label">Total Registrations</div>
            </div>
        </div>
        
        <!-- Charts -->
        <div class="grid-2">
            <div class="card">
                <div class="card-header">
                    <h3>Gender Distribution</h3>
                </div>
                <div class="card-body">
                    <div class="chart-container">
                        <canvas id="genderChart"></canvas>
                    </div>
                </div>
            </div>
            
            <div class="card">
                <div class="card-header">
                    <h3>Age Distribution</h3>
                </div>
                <div class="card-body">
                    <div class="chart-container">
                        <canvas id="ageChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header">
                <h3>New Patient Registrations (Last 6 Months)</h3>
            </div>
            <div class="card-body">
                <div class="chart-container" style="height: 250px;">
                    <canvas id="registrationChart"></canvas>
                </div>
            </div>
        </div>
        
        <!-- ========== APPOINTMENT REPORTS ========== -->
        <div class="section-title">Appointment Analytics</div>
        
        <div class="grid-4">
            <div class="stat-mini">
                <div class="num"><?= $total_appointments ?></div>
                <div class="label">Total Appointments</div>
            </div>
            <div class="stat-mini">
                <div class="num"><?= $today_count ?></div>
                <div class="label">Today</div>
            </div>
            <div class="stat-mini">
                <div class="num"><?= $upcoming_count ?></div>
                <div class="label">Next 7 Days</div>
            </div>
            <div class="stat-mini">
                <div class="num"><?= $cancelled_rate ?>%</div>
                <div class="label">Cancellation Rate</div>
            </div>
        </div>
        
        <div class="grid-2">
            <div class="card">
                <div class="card-header">
                    <h3>Appointment Status</h3>
                </div>
                <div class="card-body">
                    <div class="chart-container">
                        <canvas id="statusChart"></canvas>
                    </div>
                </div>
            </div>
            
            <div class="card">
                <div class="card-header">
                    <h3>Monthly Trends</h3>
                </div>
                <div class="card-body">
                    <div class="chart-container">
                        <canvas id="trendChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- ========== DOCTOR REPORTS ========== -->
        <div class="section-title">Doctor Analytics</div>
        
        <div class="grid-3">
            <div class="stat-mini">
                <div class="num"><?= $total_doctors ?></div>
                <div class="label">Total Doctors</div>
            </div>
            <div class="stat-mini">
                <div class="num"><?= $immunization_doctors ?></div>
                <div class="label">Immunization</div>
            </div>
            <div class="stat-mini">
                <div class="num"><?= $specialist_doctors ?></div>
                <div class="label">Specialist</div>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header">
                <h3>Appointments per Doctor</h3>
            </div>
            <div class="card-body">
                <table>
                    <thead>
                        <tr>
                            <th>Doctor</th>
                            <th>Type</th>
                            <th>Appointments</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $doctor_workload->data_seek(0);
                        while ($d = $doctor_workload->fetch_assoc()): 
                            $badge_class = ($d['doctor_role'] ?? '') == 'specialist' ? 'specialist' : 'immunization';
                        ?>
                        <tr>
                            <td>Dr. <?= htmlspecialchars($d['full_name'] ?? 'Unknown') ?></td>
                            <td>
                                <span class="badge <?= $badge_class ?>">
                                    <?= ucfirst($d['doctor_role'] ?? 'Unknown') ?>
                                </span>
                            </td>
                            <td><strong><?= $d['count'] ?></strong></td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- ========== FLAG REPORTS ========== -->
        <div class="section-title">Flags for Specialist Review</div>
        
        <div class="grid-3">
            <div class="stat-mini">
                <div class="num"><?= $total_flags ?></div>
                <div class="label">Total Flags</div>
            </div>
            <div class="stat-mini">
                <div class="num"><?= $pending_flags ?></div>
                <div class="label">Pending Review</div>
                <div class="small">Awaiting specialist</div>
            </div>
            <div class="stat-mini">
                <div class="num"><?= $resolved_flags ?></div>
                <div class="label">Resolved</div>
                <div class="small">Reviewed by specialist</div>
            </div>
        </div>
        
    </div>
</div>

<script>
// Gender Chart
new Chart(document.getElementById('genderChart'), {
    type: 'pie',
    data: {
        labels: [<?= implode(',', array_map(function($l) { return "'$l'"; }, $gender_labels)) ?>],
        datasets: [{
            data: [<?= implode(',', $gender_counts) ?>],
            backgroundColor: ['#3b82f6', '#ec4899']
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { position: 'bottom' }
        }
    }
});

// Age Chart
new Chart(document.getElementById('ageChart'), {
    type: 'bar',
    data: {
        labels: [<?= implode(',', array_map(function($l) { return "'$l'"; }, $age_labels)) ?>],
        datasets: [{
            data: [<?= implode(',', $age_counts) ?>],
            backgroundColor: '#0b1a33'
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false } }
    }
});

// Registration Trend
new Chart(document.getElementById('registrationChart'), {
    type: 'line',
    data: {
        labels: [<?= implode(',', array_map(function($m) { return "'$m'"; }, $reg_months)) ?>],
        datasets: [{
            data: [<?= implode(',', $reg_counts) ?>],
            borderColor: '#10b981',
            backgroundColor: 'rgba(16,185,129,0.1)',
            tension: 0.3,
            fill: true
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false } }
    }
});

// Status Chart with meaningful colors
new Chart(document.getElementById('statusChart'), {
    type: 'doughnut',
    data: {
        labels: [<?= implode(',', array_map(function($l) { return "'$l'"; }, $status_labels)) ?>],
        datasets: [{
            data: [<?= implode(',', $status_counts) ?>],
            backgroundColor: <?php 
                $colors = [];
                foreach ($status_labels as $status) {
                    switch (strtolower($status)) {
                        case 'completed':
                            $colors[] = '#10b981'; // Green
                            break;
                        case 'pending':
                            $colors[] = '#f59e0b'; // Orange/Yellow
                            break;
                        case 'cancelled':
                            $colors[] = '#6c757d'; // Gray
                            break;
                        case 'missed':
                            $colors[] = '#ef4444'; // Red
                            break;
                        case 'confirmed':
                            $colors[] = '#3b82f6'; // Blue
                            break;
                        default:
                            $colors[] = '#8b5cf6'; // Purple
                    }
                }
                echo '["' . implode('", "', $colors) . '"]';
            ?>
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { position: 'bottom' }
        }
    }
});

// Appointment Trend
new Chart(document.getElementById('trendChart'), {
    type: 'line',
    data: {
        labels: [<?= implode(',', array_map(function($m) { return "'$m'"; }, $apt_months)) ?>],
        datasets: [{
            data: [<?= implode(',', $apt_counts) ?>],
            borderColor: '#0b1a33',
            backgroundColor: 'rgba(11,26,51,0.1)',
            tension: 0.3,
            fill: true
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false } }
    }
});
</script>

</body>
</html>
<?php $conn->close(); ?>