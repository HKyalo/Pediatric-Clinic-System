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
$doctor_name = $_SESSION['name'];

// Get filter from URL (today, upcoming, past)
$filter = $_GET['filter'] ?? 'upcoming';
$today = date('Y-m-d');

if ($filter === 'today') {
    $where = "a.appointment_date = '$today'";
} elseif ($filter === 'past') {
    $where = "a.appointment_date < '$today'";
} else {
    $where = "a.appointment_date >= '$today'";
}

// Get appointments
$appointments = $conn->query("
    SELECT a.*, c.first_name, c.last_name, c.date_of_birth,
           TIMESTAMPDIFF(YEAR, c.date_of_birth, CURDATE()) as age
    FROM appointments a
    JOIN children c ON a.child_id = c.child_id
    WHERE a.doctor_id = $doctor_id 
    AND $where
    ORDER BY a.appointment_date ASC, a.appointment_time ASC
");
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>My Appointments - Specialist</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body { background:#f0f4fc; font-family:Arial; }
        .wrapper { display:flex; }
        .main { margin-left:260px; padding:30px; flex:1; }
        h1 { color:#0b1a33; margin-bottom:5px; }
        .subtitle { color:#5a6f8c; margin-bottom:25px; }
        
        .filter-tabs { display:flex; gap:10px; margin-bottom:25px; }
        .filter-tab { padding:10px 20px; background:white; border-left:4px solid #e2e8f0; text-decoration:none; color:#5a6f8c; }
        .filter-tab.active { border-left-color:#0b1a33; color:#0b1a33; font-weight:600; }
        
        .card { background:white; padding:25px; border-left:4px solid #0b1a33; }
        
        table { width:100%; border-collapse:collapse; }
        th { background:#0b1a33; color:white; padding:12px; text-align:left; }
        td { padding:12px; border-bottom:1px solid #e2e8f0; }
        tr:hover td { background:#f8fafd; }
        
        .btn-sm { background:#0b1a33; color:white; padding:5px 12px; text-decoration:none; border-radius:4px; font-size:13px; }
        .status { padding:4px 8px; border-radius:4px; font-size:12px; }
        .status-pending { background:#fff3cd; color:#856404; }
        .status-confirmed { background:#d4edda; color:#155724; }
        .status-completed { background:#d1ecf1; color:#0c5460; }
        .status-cancelled { background:#f8d7da; color:#721c24; }
        
        .empty { text-align:center; padding:40px; color:#5a6f8c; }
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
                <li><a href="doctor_specialist_appointments.php" class="active">My Appointments</a></li>
                <li><a href="doctor_specialist_patients.php">My Patients</a></li>
                <li><a href="doctor_profile.php">Profile</a></li>
                <li><a href="logout.php">Logout</a></li>
            </ul>
        </div>
    </div>
    
    <!-- Main Content -->
    <div class="main">
        <h1>My Appointments</h1>
        <p class="subtitle">Dr. <?= htmlspecialchars($doctor_name) ?></p>
        
        <!-- Filter Tabs -->
        <div class="filter-tabs">
            <a href="?filter=upcoming" class="filter-tab <?= $filter === 'upcoming' ? 'active' : '' ?>">Upcoming</a>
            <a href="?filter=today" class="filter-tab <?= $filter === 'today' ? 'active' : '' ?>">Today</a>
            <a href="?filter=past" class="filter-tab <?= $filter === 'past' ? 'active' : '' ?>">Past</a>
        </div>
        
        <!-- Appointments List -->
        <div class="card">
            <?php if ($appointments && $appointments->num_rows > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Time</th>
                        <th>Child</th>
                        <th>Age</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($apt = $appointments->fetch_assoc()): ?>
                    <tr>
                        <td><?= date('M d, Y', strtotime($apt['appointment_date'])) ?></td>
                        <td><?= date('g:i A', strtotime($apt['appointment_time'])) ?></td>
                        <td><?= htmlspecialchars($apt['first_name'] . ' ' . $apt['last_name']) ?></td>
                        <td><?= $apt['age'] ?> years</td>
                        <td><span class="status status-<?= strtolower($apt['status']) ?>"><?= $apt['status'] ?></span></td>
                        <td>
                            <a href="doctor_specialist_ehr.php?child_id=<?= $apt['child_id'] ?>" class="btn-sm">📋 View EHR</a>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
            <?php else: ?>
            <div class="empty">
                <p>No appointments found.</p>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>
</body>
</html>