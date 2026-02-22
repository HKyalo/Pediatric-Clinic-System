<?php
session_start();
require_once __DIR__ . "/config/db.php";

// Security check - only immunization doctors
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'doctor' || $_SESSION['doctor_role'] !== 'immunization') {
    header("Location: index.php");
    exit();
}

$doctor_id = $_SESSION['user_id'];

// Search functionality
$search = $_GET['search'] ?? '';
$search_sql = '';
if ($search) {
    $search = $conn->real_escape_string($search);
    $search_sql = "AND (c.first_name LIKE '%$search%' OR c.last_name LIKE '%$search%')";
}

// Get all patients for this doctor
$patients = $conn->query("
    SELECT DISTINCT c.*, 
           MAX(a.appointment_date) as last_visit,
           TIMESTAMPDIFF(MONTH, c.date_of_birth, CURDATE()) as age_months,
           (SELECT COUNT(*) FROM vaccination_records vr WHERE vr.child_id = c.child_id) as vaccines_given
    FROM children c
    JOIN appointments a ON c.child_id = a.child_id
    WHERE a.doctor_id = $doctor_id
    $search_sql
    GROUP BY c.child_id
    ORDER BY last_visit DESC
");
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>My Patients - Immunization Doctor</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body { background:#f0f4fc; font-family:Arial; }
        .wrapper { display:flex; }
        .main { margin-left:260px; padding:30px; flex:1; }
        h1 { color:#0b1a33; margin-bottom:20px; }
        
        .search-box { margin-bottom:25px; }
        .search-box form { display:flex; gap:10px; }
        .search-input { flex:1; padding:12px; border:1px solid #e2e8f0; border-radius:6px; font-size:14px; }
        .search-btn { background:#0b1a33; color:white; padding:12px 25px; border:none; border-radius:6px; cursor:pointer; }
        
        .card { background:white; padding:25px; border-left:4px solid #0b1a33; }
        
        table { width:100%; border-collapse:collapse; }
        th { background:#0b1a33; color:white; padding:12px; text-align:left; }
        td { padding:12px; border-bottom:1px solid #e2e8f0; }
        tr:hover td { background:#f8fafd; }
        
        .btn-sm { background:#0b1a33; color:white; padding:5px 12px; text-decoration:none; border-radius:4px; font-size:13px; display:inline-block; margin:2px; }
        .vaccine-count { background:#e6f0ff; padding:3px 8px; border-radius:12px; font-size:11px; color:#0b1a33; }
        
        .empty { text-align:center; padding:60px; color:#5a6f8c; }
        .empty p { margin-bottom:15px; }
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
                <li><a href="doctor_immunization_patients.php" class="active">My Patients</a></li>
                <li><a href="doctor_profile.php">Profile</a></li>
                <li><a href="logout.php">Logout</a></li>
            </ul>
        </div>
    </div>
    
    <!-- Main Content -->
    <div class="main">
        <h1>My Patients</h1>
        
        <!-- Search -->
        <div class="search-box">
            <form method="GET">
                <input type="text" name="search" class="search-input" placeholder="Search by child name..." value="<?= htmlspecialchars($search) ?>">
                <button type="submit" class="search-btn">Search</button>
                <?php if ($search): ?>
                    <a href="doctor_immunization_patients.php" class="btn-sm" style="background:#6c757d;">Clear</a>
                <?php endif; ?>
            </form>
        </div>
        
        <!-- Patients List -->
        <div class="card">
            <?php if ($patients->num_rows > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th>Child Name</th>
                        <th>Age</th>
                        <th>Last Visit</th>
                        <th>Vaccines</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($p = $patients->fetch_assoc()): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($p['first_name'] . ' ' . $p['last_name']) ?></strong></td>
                        <td><?= $p['age_months'] ?> months</td>
                        <td><?= $p['last_visit'] ? date('M d, Y', strtotime($p['last_visit'])) : 'Never' ?></td>
                        <td><span class="vaccine-count"><?= $p['vaccines_given'] ?> given</span></td>

                        <td>
                        <a href="doctor_child_ehr.php?child_id=<?= $p['child_id'] ?>" class="btn-sm">View EHR</a>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
            <?php else: ?>
            <div class="empty">
                <p>No patients found.</p>
                <?php if ($search): ?>
                    <a href="doctor_immunization_patients.php" class="btn-sm">Clear Search</a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>
</body>
</html>