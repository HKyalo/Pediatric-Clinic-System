<?php
session_start();
require_once __DIR__ . "/config/db.php";

// Security check - only specialists
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'doctor' || $_SESSION['doctor_role'] !== 'specialist') {
    header("Location: index.php");
    exit();
}

$doctor_id = $_SESSION['user_id'];

// Search functionality
$search = $_GET['search'] ?? '';
$filter = $_GET['filter'] ?? 'all'; // all, flagged, reviewed

$search_sql = '';
if ($search) {
    $search = $conn->real_escape_string($search);
    $search_sql = "AND (c.first_name LIKE '%$search%' OR c.last_name LIKE '%$search%')";
}

// Build query based on filter
if ($filter === 'flagged') {
    // Children with active flags assigned to this specialist
    $patients = $conn->query("
        SELECT DISTINCT c.*, 
               f.flag_id, f.reason as flag_reason, f.created_at as flag_date,
               TIMESTAMPDIFF(YEAR, c.date_of_birth, CURDATE()) as age,
               (SELECT COUNT(*) FROM specialist_reviews sr WHERE sr.child_id = c.child_id) as review_count
        FROM children c
        JOIN flags f ON c.child_id = f.child_id
        WHERE f.assigned_to = $doctor_id AND f.status = 'new'
        $search_sql
        ORDER BY f.created_at DESC
    ");
} elseif ($filter === 'reviewed') {
    // Children with past reviews
    $patients = $conn->query("
        SELECT DISTINCT c.*, 
               MAX(sr.review_date) as last_review,
               TIMESTAMPDIFF(YEAR, c.date_of_birth, CURDATE()) as age,
               (SELECT COUNT(*) FROM specialist_reviews sr WHERE sr.child_id = c.child_id) as review_count
        FROM children c
        JOIN specialist_reviews sr ON c.child_id = sr.child_id
        WHERE sr.doctor_id = $doctor_id
        $search_sql
        GROUP BY c.child_id
        ORDER BY last_review DESC
    ");
} else {
    // All children this specialist has seen
    $patients = $conn->query("
        SELECT DISTINCT c.*, 
               MAX(sr.review_date) as last_review,
               TIMESTAMPDIFF(YEAR, c.date_of_birth, CURDATE()) as age,
               (SELECT COUNT(*) FROM specialist_reviews sr WHERE sr.child_id = c.child_id) as review_count
        FROM children c
        LEFT JOIN specialist_reviews sr ON c.child_id = sr.child_id
        WHERE sr.doctor_id = $doctor_id
        $search_sql
        GROUP BY c.child_id
        ORDER BY last_review DESC
    ");
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>My Patients - Specialist</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body { background:#f0f4fc; font-family:Arial; }
        .wrapper { display:flex; }
        .main { margin-left:260px; padding:30px; flex:1; }
        h1 { color:#0b1a33; margin-bottom:20px; }
        
        .filter-bar { display:flex; justify-content:space-between; align-items:center; margin-bottom:25px; flex-wrap:wrap; gap:15px; }
        .filter-tabs { display:flex; gap:10px; }
        .filter-tab { padding:8px 16px; background:white; border-left:4px solid #e2e8f0; text-decoration:none; color:#5a6f8c; }
        .filter-tab.active { border-left-color:#0b1a33; color:#0b1a33; font-weight:600; }
        
        .search-box { display:flex; gap:10px; }
        .search-input { padding:8px 12px; border:1px solid #e2e8f0; border-radius:4px; width:250px; }
        .search-btn { background:#0b1a33; color:white; padding:8px 16px; border:none; border-radius:4px; cursor:pointer; }
        
        .card { background:white; padding:25px; border-left:4px solid #0b1a33; }
        
        table { width:100%; border-collapse:collapse; }
        th { background:#0b1a33; color:white; padding:12px; text-align:left; }
        td { padding:12px; border-bottom:1px solid #e2e8f0; }
        tr:hover td { background:#f8fafd; }
        
        .btn-sm { background:#0b1a33; color:white; padding:5px 12px; text-decoration:none; border-radius:4px; font-size:13px; display:inline-block; }
        .flag-badge { background:#fee2e2; color:#dc2626; padding:2px 8px; border-radius:12px; font-size:11px; }
        .review-count { background:#e6f0ff; padding:2px 8px; border-radius:12px; font-size:11px; color:#0b1a33; }
        
        .empty { text-align:center; padding:60px; color:#5a6f8c; }
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
                <li><a href="doctor_specialist_patients.php" class="active">My Patients</a></li>
                <li><a href="doctor_profile.php">Profile</a></li>
                <li><a href="logout.php">Logout</a></li>
            </ul>
        </div>
    </div>
    
    <!-- Main Content -->
    <div class="main">
        <h1>My Patients</h1>
        
        <!-- Filter and Search -->
        <div class="filter-bar">
            <div class="filter-tabs">
                <a href="?filter=all" class="filter-tab <?= $filter === 'all' ? 'active' : '' ?>">All Patients</a>
                <a href="?filter=flagged" class="filter-tab <?= $filter === 'flagged' ? 'active' : '' ?>">Flagged</a>
                <a href="?filter=reviewed" class="filter-tab <?= $filter === 'reviewed' ? 'active' : '' ?>">Reviewed</a>
            </div>
            
            <form method="GET" class="search-box">
                <input type="hidden" name="filter" value="<?= $filter ?>">
                <input type="text" name="search" class="search-input" placeholder="Search by child name..." value="<?= htmlspecialchars($search) ?>">
                <button type="submit" class="search-btn">Search</button>
                <?php if ($search): ?>
                    <a href="?filter=<?= $filter ?>" class="btn-sm" style="background:#6c757d;">Clear</a>
                <?php endif; ?>
            </form>
        </div>
        
        <!-- Patients List -->
        <div class="card">
            <?php if ($patients && $patients->num_rows > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th>Child Name</th>
                        <th>Age</th>
                        <th>Status</th>
                        <th>Last Review</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($p = $patients->fetch_assoc()): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($p['first_name'] . ' ' . $p['last_name']) ?></strong></td>
                        <td><?= $p['age'] ?> years</td>
                        <td>
                            <?php if (isset($p['flag_id'])): ?>
                                <span class="flag-badge">Flagged</span>
                            <?php endif; ?>
                            <span class="review-count"><?= $p['review_count'] ?? 0 ?> reviews</span>
                        </td>
                        <td>
                            <?= isset($p['last_review']) ? date('M d, Y', strtotime($p['last_review'])) : 'Never' ?>
                        </td>
                        <td>
                            <a href="doctor_specialist_ehr.php?child_id=<?= $p['child_id'] ?><?= isset($p['flag_id']) ? '&flag_id=' . $p['flag_id'] : '' ?>" class="btn-sm">
                                📋 View EHR
                            </a>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
            <?php else: ?>
            <div class="empty">
                <p>No patients found.</p>
                <?php if ($search): ?>
                    <a href="?filter=<?= $filter ?>" class="btn-sm">Clear Search</a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>
</body>
</html>