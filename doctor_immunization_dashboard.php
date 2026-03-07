<?php
session_start();
require_once __DIR__ . "/config/db.php";

// Security check - only immunization doctors
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'doctor' || $_SESSION['doctor_role'] !== 'immunization') {
    header("Location: index.php");
    exit();
}

$doctor_id = $_SESSION['user_id'];
$doctor_name = $_SESSION['name'];

// Get today's appointments
$today = date('Y-m-d');
$appointments = $conn->query("
    SELECT a.*, c.first_name, c.last_name, c.date_of_birth
    FROM appointments a
    JOIN children c ON a.child_id = c.child_id
    WHERE a.doctor_id = $doctor_id 
    AND a.appointment_date = '$today'
    ORDER BY a.appointment_time
");

// Get children needing vaccines
$needing_vaccines = $conn->query("
    SELECT DISTINCT c.child_id, c.first_name, c.last_name, 
           TIMESTAMPDIFF(MONTH, c.date_of_birth, CURDATE()) as age_months
    FROM children c
    JOIN appointments a ON c.child_id = a.child_id
    LEFT JOIN vaccination_records vr ON c.child_id = vr.child_id
    WHERE a.doctor_id = $doctor_id
    AND vr.vaccination_record_id IS NULL
    LIMIT 5
");

// Get recent patients
$recent = $conn->query("
    SELECT c.*, MAX(a.appointment_date) as last_visit
    FROM children c
    JOIN appointments a ON c.child_id = a.child_id
    WHERE a.doctor_id = $doctor_id
    GROUP BY c.child_id
    ORDER BY last_visit DESC
    LIMIT 5
");
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Immunization Doctor - PCASS</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body { background:#f0f4fc; font-family:Arial; }
        .wrapper { display:flex; }
        .main { margin-left:260px; padding:30px; flex:1; }
        h1 { color:#0b1a33; margin-bottom:20px; }
        
        .stats-grid { display:grid; grid-template-columns:repeat(3,1fr); gap:20px; margin-bottom:25px; }
        .stat-box { background:white; padding:25px; border-left:4px solid #0b1a33; }
        .stat-number { font-size:42px; font-weight:700; color:#0b1a33; }
        .stat-label { color:#5a6f8c; font-size:14px; text-transform:uppercase; letter-spacing:0.5px; }
        
        .card { background:white; padding:25px; margin-bottom:25px; border-left:4px solid #0b1a33; }
        .card-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:15px; }
        .card-header h2 { color:#0b1a33; font-size:20px; margin:0; }
        
        table { width:100%; border-collapse:collapse; }
        th { background:#0b1a33; color:white; padding:12px; text-align:left; }
        td { padding:12px; border-bottom:1px solid #e2e8f0; }
        
        .btn { background:#0b1a33; color:white; padding:10px 20px; text-decoration:none; border-radius:6px; display:inline-block; }
        .btn-sm { background:#0b1a33; color:white; padding:5px 12px; text-decoration:none; border-radius:4px; font-size:13px; }
        
        .patient-row { display:flex; justify-content:space-between; align-items:center; padding:12px; border-bottom:1px solid #e2e8f0; }
        .patient-row:hover { background:#f8fafd; }
        .patient-name { font-weight:600; color:#0b1a33; }
        .patient-age { color:#5a6f8c; font-size:13px; margin-left:10px; }
        
        .alert-box { background:#fee2e2; border-left:4px solid #dc2626; padding:15px; margin-bottom:15px; }
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
                <li><a href="doctor_immunization_dashboard.php" class="active">Dashboard</a></li>
                <li><a href="doctor_immunization_appointments.php">My Appointments</a></li>
                <li><a href="doctor_immunization_patients.php">My Patients</a></li>
                <li><a href="doctor_profile.php">Profile</a></li>
                <li><a href="logout.php">Logout</a></li>
            </ul>
        </div>
    </div>
    
    <!-- Main Content -->
    <div class="main">
        <h1>Welcome, Dr. <?= htmlspecialchars($doctor_name) ?></h1>
        
        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-box">
                <div class="stat-number"><?= $appointments->num_rows ?></div>
                <div class="stat-label">Today's Visits</div>
            </div>
            <div class="stat-box">
                <div class="stat-number"><?= $needing_vaccines->num_rows ?></div>
                <div class="stat-label">Need Vaccines</div>
            </div>
            <div class="stat-box">
                <div class="stat-number"><?= $recent->num_rows ?></div>
                <div class="stat-label">Recent Patients</div>
            </div>
        </div>
        
        <!-- Today's Schedule -->
        <div class="card">
            <div class="card-header">
                <h2>Today's Schedule</h2>
                <a href="doctor_immunization_appointments.php" class="btn-sm">View All</a>
            </div>
            <?php if ($appointments->num_rows > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th>Time</th>
                        <th>Child</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($apt = $appointments->fetch_assoc()): ?>
                    <tr>
                        <td><?= date('g:i A', strtotime($apt['appointment_time'])) ?></td>
                        <td><?= htmlspecialchars($apt['first_name'] . ' ' . $apt['last_name']) ?></td>
                        <td><a href="doctor_vaccines.php?child_id=<?= $apt['child_id'] ?>" class="btn-sm">Vaccines</a></td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
            <?php else: ?>
            <p style="color:#5a6f8c;">No appointments scheduled today.</p>
            <?php endif; ?>
        </div>
        
        <!-- Children Needing Vaccines -->
        <?php if ($needing_vaccines->num_rows > 0): ?>
        <div class="card">
            <h2>Children Needing Vaccines</h2>
            <?php while ($child = $needing_vaccines->fetch_assoc()): ?>
            <div class="patient-row">
                <div>
                    <span class="patient-name"><?= htmlspecialchars($child['first_name'] . ' ' . $child['last_name']) ?></span>
                    <span class="patient-age"><?= $child['age_months'] ?> months</span>
                </div> 
            </div>
            <?php endwhile; ?>
        </div>
        <?php endif; ?>
        
        <!-- Recent Patients -->
        <div class="card">
            <h2>Recent Patients</h2>
            <?php if ($recent->num_rows > 0): ?>
                <?php while ($p = $recent->fetch_assoc()): ?>
                <div class="patient-row">
                    <span class="patient-name"><?= htmlspecialchars($p['first_name'] . ' ' . $p['last_name']) ?></span>
                    <a href="doctor_child_ehr.php?child_id=<?= $p['child_id'] ?>" class="btn-sm">View</a>
                </div>
                <?php endwhile; ?>
            <?php else: ?>
            <p style="color:#5a6f8c;">No recent patients.</p>
            <?php endif; ?>
        </div>
    </div>
</div>
</body>
</html>
<?php $conn->close(); ?>