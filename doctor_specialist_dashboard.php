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

// Get flagged children needing review
$flagged = $conn->query("
    SELECT f.*, c.first_name, c.last_name, c.date_of_birth,
           d.full_name as flagged_by_name
    FROM flags f
    JOIN children c ON f.child_id = c.child_id
    LEFT JOIN doctors d ON f.flagged_by = d.doctor_id
    WHERE f.assigned_to = $doctor_id AND f.status = 'new'
    ORDER BY f.created_at DESC
");

// Get today's consultations
$today = date('Y-m-d');
$consultations = $conn->query("
    SELECT a.*, c.first_name, c.last_name, c.date_of_birth
    FROM appointments a
    JOIN children c ON a.child_id = c.child_id
    WHERE a.doctor_id = $doctor_id 
    AND a.appointment_date = '$today'
    ORDER BY a.appointment_time
");

// Get recent cases (last 5 resolved)
$recent = $conn->query("
    SELECT sr.*, c.first_name, c.last_name, c.date_of_birth
    FROM specialist_reviews sr
    JOIN children c ON sr.child_id = c.child_id
    WHERE sr.doctor_id = $doctor_id
    ORDER BY sr.review_date DESC
    LIMIT 5
");

// Count stats
$flagged_count = $flagged->num_rows;
$consultations_count = $consultations->num_rows;
$recent_count = $recent->num_rows;
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Specialist Dashboard - PCASS</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body { background:#f0f4fc; font-family:Arial; }
        .wrapper { display:flex; }
        .main { margin-left:260px; padding:30px; flex:1; }
        h1 { color:#0b1a33; margin-bottom:5px; }
        .subtitle { color:#5a6f8c; margin-bottom:25px; }
        
        .stats-grid { display:grid; grid-template-columns:repeat(3,1fr); gap:20px; margin-bottom:25px; }
        .stat-box { background:white; padding:25px; border-left:4px solid #0b1a33; }
        .stat-number { font-size:42px; font-weight:700; color:#0b1a33; }
        .stat-label { color:#5a6f8c; font-size:14px; text-transform:uppercase; }
        
        .card { background:white; padding:25px; margin-bottom:25px; border-left:4px solid #0b1a33; }
        .card-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:15px; }
        .card-header h2 { color:#0b1a33; font-size:20px; margin:0; }
        
        .flag-item { background:#fee2e2; padding:15px; margin-bottom:10px; border-left:4px solid #dc2626; }
        .flag-item:hover { background:#fecaca; }
        .flag-title { font-weight:700; color:#0b1a33; font-size:16px; }
        .flag-meta { color:#5a6f8c; font-size:12px; margin:5px 0; }
        
        .consult-item { background:#f8fafd; padding:15px; margin-bottom:10px; border-left:4px solid #0b1a33; display:flex; justify-content:space-between; align-items:center; }
        .consult-item:hover { background:#e8f0fe; }
        
        .btn { background:#0b1a33; color:white; padding:10px 20px; text-decoration:none; border-radius:6px; display:inline-block; }
        .btn-sm { background:#0b1a33; color:white; padding:5px 12px; text-decoration:none; border-radius:4px; font-size:13px; }
        .btn-danger { background:#dc2626; }
        
        .empty { text-align:center; padding:40px; color:#5a6f8c; }
        .patient-name { font-weight:600; color:#0b1a33; }
        .patient-age { color:#5a6f8c; font-size:13px; }
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
                <li><a href="doctor_specialist_dashboard.php" class="active">Dashboard</a></li>
                <li><a href="doctor_specialist_appointments.php">My Appointments</a></li>
                <li><a href="doctor_specialist_patients.php">My Patients</a></li>
                <li><a href="doctor_profile.php">Profile</a></li>
                <li><a href="logout.php">Logout</a></li>
            </ul>
        </div>
    </div>
    
    <!-- Main Content -->
    <div class="main">
        <h1>Welcome, Dr. <?= htmlspecialchars($doctor_name) ?></h1>
        <p class="subtitle">Specialist Dashboard</p>
        
        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-box">
                <div class="stat-number"><?= $flagged_count ?></div>
                <div class="stat-label">Flagged for Review</div>
            </div>
            <div class="stat-box">
                <div class="stat-number"><?= $consultations_count ?></div>
                <div class="stat-label">Today's Cases</div>
            </div>
            <div class="stat-box">
                <div class="stat-number"><?= $recent_count ?></div>
                <div class="stat-label">Recent Reviews</div>
            </div>
        </div>
        
        <!-- Flagged Children (Priority) -->
        <?php if ($flagged->num_rows > 0): ?>
        <div class="card">
            <div class="card-header">
                <h2>Flagged for Review</h2>
            </div>
            <?php while ($f = $flagged->fetch_assoc()): 
                $age = floor((time() - strtotime($f['date_of_birth'])) / (365 * 24 * 60 * 60));
            ?>
            <div class="flag-item">
                <div class="flag-title">
                    <?= htmlspecialchars($f['first_name'] . ' ' . $f['last_name']) ?> (<?= $age ?> years)
                </div>
                <div class="flag-meta">
                    <strong>Reason:</strong> <?= htmlspecialchars($f['reason']) ?><br>
                    Flagged by: <?= htmlspecialchars($f['flagged_by_name'] ?? 'System') ?> • <?= date('M d, Y', strtotime($f['created_at'])) ?>
                </div>
                <a href="doctor_specialist_ehr.php?child_id=<?= $f['child_id'] ?>&flag_id=<?= $f['flag_id'] ?>" class="btn-sm">Review Child</a>
            </div>
            <?php endwhile; ?>
        </div>
        <?php endif; ?>
        
        <!-- Today's Consultations -->
        <div class="card">
            <div class="card-header">
                <h2>📅 Today's Consultations</h2>
                <a href="doctor_specialist_appointments.php" class="btn-sm">View All</a>
            </div>
            <?php if ($consultations->num_rows > 0): ?>
                <?php while ($apt = $consultations->fetch_assoc()): 
                    $age = floor((time() - strtotime($apt['date_of_birth'])) / (365 * 24 * 60 * 60));
                ?>
                <div class="consult-item">
                    <div>
                        <span class="patient-name"><?= htmlspecialchars($apt['first_name'] . ' ' . $apt['last_name']) ?></span>
                        <span class="patient-age">(<?= $age ?> years)</span><br>
                        <span style="color:#5a6f8c; font-size:12px;"><?= date('g:i A', strtotime($apt['appointment_time'])) ?></span>
                    </div>
                    <a href="specialist_review.php?child_id=<?= $apt['child_id'] ?>" class="btn-sm">Start Review</a>
                </div>
                <?php endwhile; ?>
            <?php else: ?>
            <div class="empty">
                <p>No consultations scheduled today.</p>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Recently Reviewed -->
        <?php if ($recent->num_rows > 0): ?>
        <div class="card">
            <div class="card-header">
                <h2>Recently Reviewed</h2>
            </div>
            <?php while ($r = $recent->fetch_assoc()): 
                $age = floor((time() - strtotime($r['date_of_birth'])) / (365 * 24 * 60 * 60));
            ?>
            <div class="consult-item">
                <div>
                    <span class="patient-name"><?= htmlspecialchars($r['first_name'] . ' ' . $r['last_name']) ?></span>
                    <span class="patient-age">(<?= $age ?> years)</span><br>
                    <span style="color:#5a6f8c; font-size:12px;">Reviewed: <?= date('M d, Y', strtotime($r['review_date'])) ?></span>
                </div>
                <a href="specialist_review.php?child_id=<?= $r['child_id'] ?>" class="btn-sm">View</a>
            </div>
            <?php endwhile; ?>
        </div>
        <?php endif; ?>
    </div>
</div>
</body>
</html>