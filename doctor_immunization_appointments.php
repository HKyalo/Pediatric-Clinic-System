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
$doctor_name = $_SESSION['name'];

// Handle marking appointment as complete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_complete'])) {
    $appointment_id = intval($_POST['appointment_id']);
    $child_id = intval($_POST['child_id']);
    $visit_date = $_POST['visit_date'];
    
    // Check if vitals were recorded today
    $vitals_check = $conn->query("
        SELECT * FROM growth_records 
        WHERE child_id = $child_id 
        AND record_date = '$visit_date'
    ");
    
    // Check if vaccines were given today
    $vaccines_check = $conn->query("
        SELECT * FROM vaccination_records 
        WHERE child_id = $child_id 
        AND date_administered = '$visit_date'
    ");
    
    // Check if milestones were updated (optional - can be skipped)
    // For now, only require vitals or vaccines
    
    if ($vitals_check->num_rows == 0 && $vaccines_check->num_rows == 0) {
        $error = "Cannot mark complete. No vitals recorded and no vaccines given for this visit.";
    } else {
        $update = $conn->prepare("UPDATE appointments SET status = 'Completed' WHERE appointment_id = ? AND doctor_id = ?");
        $update->bind_param("ii", $appointment_id, $doctor_id);
        
        if ($update->execute()) {
            $message = "Appointment marked as completed!";
            $msg_type = "success";
        }
        $update->close();
    }
}

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

// Get appointments with additional info
$appointments = $conn->query("
    SELECT a.*, c.first_name, c.last_name, c.date_of_birth,
           TIMESTAMPDIFF(MONTH, c.date_of_birth, CURDATE()) as age_months,
           (SELECT COUNT(*) FROM growth_records gr WHERE gr.child_id = c.child_id AND gr.record_date = a.appointment_date) as vitals_recorded,
           (SELECT COUNT(*) FROM vaccination_records vr WHERE vr.child_id = c.child_id AND vr.date_administered = a.appointment_date) as vaccines_given
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
    <title>My Appointments - Immunization Doctor</title>
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
        
        .alert { padding:15px; margin-bottom:20px; border-radius:6px; }
        .alert.success { background:#d4edda; color:#155724; border-left:4px solid #28a745; }
        .alert.error { background:#f8d7da; color:#721c24; border-left:4px solid #dc3545; }
        
        table { width:100%; border-collapse:collapse; }
        th { background:#0b1a33; color:white; padding:12px; text-align:left; }
        td { padding:12px; border-bottom:1px solid #e2e8f0; }
        tr:hover td { background:#f8fafd; }
        
        .btn-sm { background:#0b1a33; color:white; padding:5px 12px; text-decoration:none; border-radius:4px; font-size:13px; display:inline-block; margin:2px; }
        .btn-success { background:#10b981; }
        .btn-success:hover { background:#059669; }
        .btn-disabled { background:#9ca3af; cursor:not-allowed; opacity:0.6; pointer-events:none; }
        
        .status { padding:4px 8px; border-radius:4px; font-size:12px; }
        .status-pending { background:#fff3cd; color:#856404; }
        .status-confirmed { background:#d4edda; color:#155724; }
        .status-completed { background:#d1ecf1; color:#0c5460; }
        .status-cancelled { background:#f8d7da; color:#721c24; }
        
        .action-group { display:flex; gap:5px; flex-wrap:wrap; }
        .empty { text-align:center; padding:40px; color:#5a6f8c; }
        
        .visit-indicator { display:flex; gap:10px; margin-top:5px; }
        .indicator-dot { width:10px; height:10px; border-radius:50%; display:inline-block; }
        .dot-green { background:#10b981; }
        .dot-red { background:#ef4444; }
        .dot-yellow { background:#f59e0b; }
        
        form { display:inline; }
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
                <li><a href="doctor_immunization_appointments.php" class="active">My Appointments</a></li>
                <li><a href="doctor_immunization_patients.php">My Patients</a></li>
                <li><a href="doctor_profile.php">Profile</a></li>
                <li><a href="logout.php">Logout</a></li>
            </ul>
        </div>
    </div>
    
    <!-- Main Content -->
    <div class="main">
        <h1>My Appointments</h1>
        <p class="subtitle">Dr. <?= htmlspecialchars($doctor_name) ?></p>
        
        <?php if (isset($message)): ?>
        <div class="alert success"><?= $message ?></div>
        <?php endif; ?>
        
        <?php if (isset($error)): ?>
        <div class="alert error"><?= $error ?></div>
        <?php endif; ?>
        
        <!-- Filter Tabs -->
        <div class="filter-tabs">
            <a href="?filter=upcoming" class="filter-tab <?= $filter === 'upcoming' ? 'active' : '' ?>">Upcoming</a>
            <a href="?filter=today" class="filter-tab <?= $filter === 'today' ? 'active' : '' ?>">Today</a>
            <a href="?filter=past" class="filter-tab <?= $filter === 'past' ? 'active' : '' ?>">Past</a>
        </div>
        
        <!-- Appointments List -->
        <div class="card">
            <?php if ($appointments->num_rows > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Time</th>
                        <th>Child</th>
                        <th>Age</th>
                        <th>Status</th>
                        <th>Visit Data</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($apt = $appointments->fetch_assoc()): 
                        $can_complete = ($apt['status'] != 'Completed' && $apt['status'] != 'Cancelled' && strtotime($apt['appointment_date']) <= strtotime($today));
                        $has_vitals = $apt['vitals_recorded'] > 0;
                        $has_vaccines = $apt['vaccines_given'] > 0;
                        $can_mark_complete = $can_complete && ($has_vitals || $has_vaccines);
                    ?>
                    <tr>
                        <td><?= date('M d, Y', strtotime($apt['appointment_date'])) ?></td>
                        <td><?= date('g:i A', strtotime($apt['appointment_time'])) ?></td>
                        <td><?= htmlspecialchars($apt['first_name'] . ' ' . $apt['last_name']) ?></td>
                        <td><?= $apt['age_months'] ?> months</td>
                        <td><span class="status status-<?= strtolower($apt['status']) ?>"><?= $apt['status'] ?></span></td>
                        <td>
                            <div class="visit-indicator">
                                <span><span class="indicator-dot <?= $has_vitals ? 'dot-green' : 'dot-red' ?>"></span> Vitals</span>
                                <span><span class="indicator-dot <?= $has_vaccines ? 'dot-green' : 'dot-red' ?>"></span> Vaccines</span>
                            </div>
                        </td>
                        <td class="action-group">
                            <!-- Single View EHR button for all actions -->
                            <a href="doctor_child_ehr.php?child_id=<?= $apt['child_id'] ?>" class="btn-sm">📋 View EHR</a>
                            
                            <!-- Mark as Complete button (only if vitals or vaccines recorded) -->
                            <?php if ($can_mark_complete): ?>
                            <form method="POST">
                                <input type="hidden" name="appointment_id" value="<?= $apt['appointment_id'] ?>">
                                <input type="hidden" name="child_id" value="<?= $apt['child_id'] ?>">
                                <input type="hidden" name="visit_date" value="<?= $apt['appointment_date'] ?>">
                                <button type="submit" name="mark_complete" class="btn-sm btn-success" onclick="return confirm('Mark this appointment as completed?')">✓ Mark Complete</button>
                            </form>
                            <?php elseif ($can_complete): ?>
                            <span class="btn-sm btn-disabled" title="Record vitals or vaccines first">✓ Complete</span>
                            <?php endif; ?>
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