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
if (!isset($_SESSION['guardian_id'])) {
    header("Location: index.php");
    exit();
}

$guardian_id = $_SESSION['guardian_id'];
$selected_child_id = $_SESSION['selected_child_id'] ?? null;

// If no child selected, redirect to select_child page
if (!$selected_child_id) {
    header("Location: select_child.php");
    exit();
}

// Verify the selected child belongs to this guardian
$verify_child = $conn->prepare("SELECT child_id FROM children WHERE child_id = ? AND guardian_id = ?");
$verify_child->bind_param("ii", $selected_child_id, $guardian_id);
$verify_child->execute();
if ($verify_child->get_result()->num_rows == 0) {
    unset($_SESSION['selected_child_id']);
    header("Location: select_child.php");
    exit();
}
$verify_child->close();

// ============================================
// AUTO-CREATE NOTIFICATIONS FOR UPCOMING APPOINTMENTS (1 DAY BEFORE)
// ============================================

$today = date('Y-m-d');
$tomorrow = date('Y-m-d', strtotime('+1 days'));

$appointments_query = "
    SELECT 
        a.appointment_id,
        a.appointment_date,
        a.appointment_time,
        a.status,
        a.child_id,
        c.first_name as child_first_name,
        d.full_name as doctor_name,
        d.doctor_role,
        DATEDIFF(a.appointment_date, CURDATE()) as days_until
    FROM appointments a
    JOIN children c ON a.child_id = c.child_id
    JOIN doctors d ON a.doctor_id = d.doctor_id
    WHERE c.guardian_id = $guardian_id
    AND a.child_id = $selected_child_id
    AND a.status IN ('Pending', 'Confirmed')
    AND a.appointment_date BETWEEN '$today' AND '$tomorrow'
";

$appointments = $conn->query($appointments_query);

if ($appointments && $appointments->num_rows > 0) {
    while ($apt = $appointments->fetch_assoc()) {
        $days = $apt['days_until'];
        $doctor_type = ($apt['doctor_role'] == 'specialist') ? 'Specialist' : 'Immunization';
        
        if ($days == 0) {
            $title = "Today: Appointment with {$doctor_type} Doctor";
        } elseif ($days == 1) {
            $title = "Tomorrow: Appointment with {$doctor_type} Doctor";
        } else {
            $title = "In {$days} Days: Appointment with {$doctor_type} Doctor";
        }
        
        $message = $apt['child_first_name'] . " has an appointment with Dr. " . 
                   $apt['doctor_name'] . " on " . 
                   date('l, F j, Y', strtotime($apt['appointment_date'])) . " at " . 
                   date('g:i A', strtotime($apt['appointment_time']));
        
        $check_query = "
            SELECT notification_id 
            FROM notifications 
            WHERE guardian_id = $guardian_id 
            AND child_id = $selected_child_id
            AND related_id = {$apt['appointment_id']}
            AND notification_type = 'appointment_reminder'
        ";
        $check = $conn->query($check_query);
        
        if ($check && $check->num_rows == 0) {
            $insert_query = "
                INSERT INTO notifications 
                (guardian_id, child_id, notification_type, title, message, related_id, is_read, created_at)
                VALUES 
                ($guardian_id, {$apt['child_id']}, 'appointment_reminder', 
                 '" . $conn->real_escape_string($title) . "',
                 '" . $conn->real_escape_string($message) . "',
                 {$apt['appointment_id']}, 0, NOW())
            ";
            $conn->query($insert_query);
        }
    }
}

// ============================================
// AUTO-CREATE VACCINE REMINDER NOTIFICATIONS
// ============================================

// Get child details for vaccine calculation
$child_data = $conn->query("SELECT date_of_birth, first_name, last_name FROM children WHERE child_id = $selected_child_id")->fetch_assoc();
if ($child_data) {
    $child_name = $child_data['first_name'] . ' ' . $child_data['last_name'];
    $dob = new DateTime($child_data['date_of_birth']);
    $today_dt = new DateTime();
    $age_weeks = floor($dob->diff($today_dt)->days / 7);
    
    // Get all vaccines
    $all_vaccines = $conn->query("SELECT * FROM vaccines ORDER BY min_age_weeks");
    
    // Get vaccines already given
    $given_vaccines = $conn->query("SELECT vaccine_id FROM vaccination_records WHERE child_id = $selected_child_id");
    $given_ids = [];
    while ($row = $given_vaccines->fetch_assoc()) {
        $given_ids[] = $row['vaccine_id'];
    }
    
    // Check for upcoming or overdue vaccines
    while ($vaccine = $all_vaccines->fetch_assoc()) {
        $vaccine_id = $vaccine['vaccine_id'];
        
        // Skip if already given
        if (in_array($vaccine_id, $given_ids)) {
            continue;
        }
        
        $due_weeks = $vaccine['min_age_weeks'];
        $status = '';
        $title = '';
        
        if ($age_weeks >= $due_weeks) {
            $status = 'overdue';
            $title = "Vaccine Overdue: " . $vaccine['vaccine_name'];
        } elseif ($age_weeks >= $due_weeks - 2) {
            $status = 'upcoming';
            $title = "Vaccine Due Soon: " . $vaccine['vaccine_name'];
        } else {
            continue; // Not due yet
        }
        
        $due_date = date('M d, Y', strtotime($child_data['date_of_birth'] . " + {$due_weeks} weeks"));
        $message = $child_name . " needs " . $vaccine['vaccine_name'] . " (Dose " . $vaccine['dose_number'] . "). Due by " . $due_date . ".";
        
        // Check if notification already exists
        $check_vaccine = $conn->query("
            SELECT notification_id FROM notifications 
            WHERE guardian_id = $guardian_id 
            AND child_id = $selected_child_id
            AND related_id = $vaccine_id
            AND notification_type = 'vaccine_reminder'
        ");
        
        if ($check_vaccine && $check_vaccine->num_rows == 0) {
            $insert_vaccine = "
                INSERT INTO notifications 
                (guardian_id, child_id, notification_type, title, message, related_id, is_read, created_at)
                VALUES 
                ($guardian_id, $selected_child_id, 'vaccine_reminder', 
                 '" . $conn->real_escape_string($title) . "',
                 '" . $conn->real_escape_string($message) . "',
                 $vaccine_id, 0, NOW())
            ";
            $conn->query($insert_vaccine);
        }
    }
}

// ============================================
// HANDLE ACTIONS
// ============================================

if (isset($_GET['mark_read'])) {
    $notif_id = intval($_GET['mark_read']);
    $conn->query("UPDATE notifications SET is_read = 1 WHERE notification_id = $notif_id AND guardian_id = $guardian_id AND child_id = $selected_child_id");
    header("Location: notifications.php" . (isset($_GET['filter']) ? "?filter=" . $_GET['filter'] : ""));
    exit();
}

if (isset($_GET['mark_all'])) {
    $conn->query("UPDATE notifications SET is_read = 1 WHERE guardian_id = $guardian_id AND child_id = $selected_child_id AND is_read = 0");
    header("Location: notifications.php" . (isset($_GET['filter']) ? "?filter=" . $_GET['filter'] : ""));
    exit();
}

if (isset($_GET['delete'])) {
    $notif_id = intval($_GET['delete']);
    $conn->query("DELETE FROM notifications WHERE notification_id = $notif_id AND guardian_id = $guardian_id AND child_id = $selected_child_id");
    header("Location: notifications.php" . (isset($_GET['filter']) ? "?filter=" . $_GET['filter'] : ""));
    exit();
}

if (isset($_GET['delete_all_read'])) {
    $conn->query("DELETE FROM notifications WHERE guardian_id = $guardian_id AND child_id = $selected_child_id AND is_read = 1");
    header("Location: notifications.php" . (isset($_GET['filter']) ? "?filter=" . $_GET['filter'] : ""));
    exit();
}

// ============================================
// GET CHILD INFO
// ============================================
$child_info = $conn->query("
    SELECT first_name, last_name 
    FROM children 
    WHERE child_id = $selected_child_id
")->fetch_assoc();

// ============================================
// GET NOTIFICATIONS WITH FILTER
// ============================================

$filter = $_GET['filter'] ?? 'all';
$type_filter = "";
if ($filter == 'unread') {
    $type_filter = "AND is_read = 0";
}

$notifications_query = "
    SELECT * FROM notifications 
    WHERE guardian_id = $guardian_id 
    AND child_id = $selected_child_id
    $type_filter
    ORDER BY 
        CASE WHEN is_read = 0 THEN 0 ELSE 1 END,
        created_at DESC
";
$notifications = $conn->query($notifications_query);

// Count unread notifications
$unread_result = $conn->query("
    SELECT COUNT(*) as count 
    FROM notifications 
    WHERE guardian_id = $guardian_id 
    AND child_id = $selected_child_id 
    AND is_read = 0
");
$unread_count = $unread_result->fetch_assoc()['count'];
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD HTML 4.01 Strict//EN">
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <title>Notifications - <?= htmlspecialchars($child_info['first_name']) ?> - PCASS</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: Arial, sans-serif;
            background: #f0f4fc;
        }
        
        .wrapper {
            display: flex;
            min-height: 100vh;
        }
        
        .main {
            margin-left: 260px;
            padding: 30px;
            background: #f0f4fc;
            flex: 1;
        }
        
        .page-header {
            margin-bottom: 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
        }
        
        .page-header h1 {
            color: #0b1a33;
            font-size: 28px;
        }
        
        .child-badge {
            background: white;
            padding: 8px 16px;
            border-radius: 4px;
            border-left: 4px solid #0b1a33;
            font-weight: 600;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
            margin-bottom: 25px;
        }
        
        .stat-card {
            background: white;
            padding: 20px 25px;
            border-radius: 4px;
            border-left: 4px solid #0b1a33;
            box-shadow: 0 2px 8px rgba(11,26,51,0.08);
        }
        
        .stat-card.unread {
            border-left-color: #3b82f6;
            background: #eff6ff;
        }
        
        .stat-number {
            font-size: 36px;
            font-weight: 700;
            color: #0b1a33;
            line-height: 1.2;
        }
        
        .stat-label {
            color: #5a6f8c;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .filter-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }
        
        .filter-tab {
            padding: 8px 16px;
            border-radius: 4px;
            background: white;
            color: #5a6f8c;
            text-decoration: none;
            font-size: 13px;
            font-weight: 600;
            border: 1px solid #e2e8f0;
        }
        
        .filter-tab:hover {
            background: #e6f0ff;
        }
        
        .filter-tab.active {
            background: #0b1a33;
            color: white;
            border-color: #0b1a33;
        }
        
        .action-bar {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }
        
        .btn {
            padding: 8px 16px;
            border-radius: 4px;
            font-weight: 600;
            font-size: 13px;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn-primary {
            background: #0b1a33;
            color: white;
        }
        
        .btn-primary:hover {
            background: #1e3a5f;
        }
        
        .btn-danger {
            background: #fee2e2;
            color: #991b1b;
        }
        
        .btn-danger:hover {
            background: #fecaca;
        }
        
        .card {
            background: white;
            border-radius: 4px;
            border-left: 4px solid #0b1a33;
            overflow: hidden;
        }
        
        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 20px;
            background: #f8fafd;
            border-bottom: 1px solid #e2e8f0;
        }
        
        .card-header h2 {
            color: #0b1a33;
            font-size: 18px;
            font-weight: 600;
        }
        
        .badge {
            background: #0b1a33;
            color: white;
            padding: 4px 10px;
            border-radius: 4px;
            font-size: 12px;
        }
        
        .notification-list {
            list-style: none;
        }
        
        .notification-item {
            padding: 20px;
            border-bottom: 1px solid #e2e8f0;
        }
        
        .notification-item:hover {
            background: #f8fafd;
        }
        
        .notification-item.unread {
            background: #eff6ff;
        }
        
        .notification-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 8px;
        }
        
        .notification-title {
            font-weight: 700;
            color: #0b1a33;
            font-size: 16px;
        }
        
        .unread-dot {
            width: 8px;
            height: 8px;
            background: #3b82f6;
            border-radius: 50%;
            display: inline-block;
        }
        
        .notification-meta {
            display: flex;
            gap: 15px;
            margin-bottom: 10px;
            font-size: 12px;
            color: #5a6f8c;
        }
        
        .notification-message {
            color: #1e293b;
            font-size: 14px;
            line-height: 1.6;
            margin-bottom: 15px;
        }
        
        .notification-actions {
            display: flex;
            gap: 8px;
        }
        
        .btn-sm {
            padding: 5px 10px;
            font-size: 11px;
            border-radius: 3px;
            text-decoration: none;
            font-weight: 600;
        }
        
        .btn-read {
            background: #e6f0ff;
            color: #0b1a33;
        }
        
        .btn-view {
            background: #0b1a33;
            color: white;
        }
        
        .btn-delete {
            background: #fee2e2;
            color: #991b1b;
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #5a6f8c;
        }
        
        .empty-state h3 {
            color: #0b1a33;
            margin-bottom: 8px;
        }
        
        .unread-badge {
            background: #dc2626;
            color: white;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 11px;
            margin-left: 8px;
        }
        
        .doctor-tag {
            display: inline-block;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 10px;
            font-weight: 600;
            margin-left: 5px;
            background: #e6f0ff;
            color: #0b1a33;
        }
        
        .doctor-tag.specialist {
            background: #f3e8ff;
            color: #6b21a8;
        }
        
        .notification-type-badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 10px;
            font-weight: 600;
            margin-left: 8px;
        }
        
        .type-appointment {
            background: #dbeafe;
            color: #1e40af;
        }
        
        .type-vaccine {
            background: #dcfce7;
            color: #166534;
        }
        
        .type-flag {
            background: #fef3c7;
            color: #92400e;
        }
    </style>
</head>
<body>
<div class="wrapper">
    
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-header">
            <h2>PCASS</h2>
            <p>Family Portal</p>
        </div>
        <div class="nav">
            <ul>
                <li><a href="child_dashboard.php">Dashboard</a></li>
                <li><a href="appointments.php">Appointments</a></li>
                <li><a href="medical-history.php">Medical History</a></li>
                <li><a href="notifications.php" class="active">
                    Notifications
                    <?php if ($unread_count > 0): ?>
                        <span class="unread-badge"><?= $unread_count ?></span>
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
                <h1>Notifications</h1>
                <p><?= htmlspecialchars($child_info['first_name'] . ' ' . $child_info['last_name']) ?></p>
            </div>
        </div>
        
        <!-- Stats Cards -->
        <div class="stats-grid">
            <div class="stat-card unread">
                <div class="stat-number"><?= $unread_count ?></div>
                <div class="stat-label">Unread</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= $notifications->num_rows ?></div>
                <div class="stat-label">Total</div>
            </div>
        </div>
        
        <!-- Filter Tabs -->
        <div class="filter-tabs">
            <a href="?filter=all" class="filter-tab <?= $filter == 'all' ? 'active' : '' ?>">All</a>
            <a href="?filter=unread" class="filter-tab <?= $filter == 'unread' ? 'active' : '' ?>">Unread (<?= $unread_count ?>)</a>
        </div>
        
        <!-- Action Bar -->
        <div class="action-bar">
            <?php if ($unread_count > 0): ?>
                <a href="?mark_all=1<?= $filter == 'unread' ? '&filter=unread' : '' ?>" class="btn btn-primary">Mark All as Read</a>
            <?php endif; ?>
            <?php if ($notifications->num_rows > 0): ?>
                <a href="?delete_all_read=1<?= $filter == 'unread' ? '&filter=unread' : '' ?>" class="btn btn-danger" onclick="return confirm('Delete all read notifications?')">Clear Read</a>
            <?php endif; ?>
        </div>
        
        <!-- Notifications List -->
        <div class="card">
            <div class="card-header">
                <h2>Notification Center</h2>
                <span class="badge"><?= $notifications->num_rows ?></span>
            </div>
            <div class="card-body">
                
                <?php if ($notifications && $notifications->num_rows > 0): ?>
                    <div class="notification-list">
                        
                        <?php while ($notif = $notifications->fetch_assoc()): 
                            
                            // Get doctor type if appointment
                            $doctor_type = '';
                            $doctor_tag = '';
                            if ($notif['notification_type'] == 'appointment_reminder' && $notif['related_id']) {
                                $apt_info = $conn->query("
                                    SELECT d.doctor_role
                                    FROM appointments a
                                    JOIN doctors d ON a.doctor_id = d.doctor_id
                                    WHERE a.appointment_id = {$notif['related_id']}
                                ");
                                if ($apt_info && $apt_info->num_rows > 0) {
                                    $info = $apt_info->fetch_assoc();
                                    $doctor_type = $info['doctor_role'];
                                    $doctor_tag = $doctor_type == 'specialist' ? 'specialist' : '';
                                }
                            }
                            
                            // Get notification type label and badge class
                            $type_labels = [
                                'appointment_reminder' => ['label' => 'Appointment Reminder', 'class' => 'type-appointment'],
                                'appointment_confirmation' => ['label' => 'Appointment Confirmation', 'class' => 'type-appointment'],
                                'appointment_cancelled' => ['label' => 'Appointment Cancelled', 'class' => 'type-appointment'],
                                'appointment_rescheduled' => ['label' => 'Appointment Rescheduled', 'class' => 'type-appointment'],
                                'vaccine_reminder' => ['label' => 'Vaccine Reminder', 'class' => 'type-vaccine'],
                                'flag' => ['label' => 'Medical Review Needed', 'class' => 'type-flag']
                            ];
                            
                            $type_info = $type_labels[$notif['notification_type']] ?? ['label' => 'Update', 'class' => ''];
                            $type_label = $type_info['label'];
                            $type_class = $type_info['class'];
                            
                            $unread_class = $notif['is_read'] ? '' : 'unread';
                        ?>
                        
                        <div class="notification-item <?= $unread_class ?>">
                            
                            <div class="notification-header">
                                <span class="notification-title">
                                    <?= htmlspecialchars($notif['title']) ?>
                                    <?php if ($doctor_type): ?>
                                        <span class="doctor-tag <?= $doctor_tag ?>">
                                            <?= ucfirst($doctor_type) ?>
                                        </span>
                                    <?php endif; ?>
                                    <span class="notification-type-badge <?= $type_class ?>">
                                        <?= $type_label ?>
                                    </span>
                                </span>
                                <?php if (!$notif['is_read']): ?>
                                    <span class="unread-dot"></span>
                                <?php endif; ?>
                            </div>
                            
                            <div class="notification-meta">
                                <span>
                                    <?php
                                    $time_ago = time() - strtotime($notif['created_at']);
                                    if ($time_ago < 60) {
                                        echo 'Just now';
                                    } elseif ($time_ago < 3600) {
                                        echo floor($time_ago / 60) . ' minutes ago';
                                    } elseif ($time_ago < 86400) {
                                        echo floor($time_ago / 3600) . ' hours ago';
                                    } else {
                                        echo date('M j, Y', strtotime($notif['created_at']));
                                    }
                                    ?>
                                </span>
                                <span>•</span>
                                <span><?= $type_label ?></span>
                            </div>
                            
                            <div class="notification-message">
                                <?= nl2br(htmlspecialchars($notif['message'])) ?>
                            </div>
                            
                            <div class="notification-actions">
                                
                                <?php if (!$notif['is_read']): ?>
                                    <a href="?mark_read=<?= $notif['notification_id'] ?><?= $filter == 'unread' ? '&filter=unread' : '' ?>" class="btn-sm btn-read">
                                        Mark Read
                                    </a>
                                <?php endif; ?>
                                
                                <?php if (strpos($notif['notification_type'], 'appointment') !== false): ?>
                                    <a href="appointments.php" class="btn-sm btn-view">
                                        View Appointments
                                    </a>
                                <?php endif; ?>
                                
                                <?php if ($notif['notification_type'] == 'vaccine_reminder'): ?>
                                    <a href="medical-history.php" class="btn-sm btn-view">
                                        View Vaccines
                                    </a>
                                <?php endif; ?>
                                
                                <?php if ($notif['notification_type'] == 'flag'): ?>
                                    <a href="medical-history.php" class="btn-sm btn-view">
                                        View Details
                                    </a>
                                <?php endif; ?>
                                
                                <a href="?delete=<?= $notif['notification_id'] ?><?= $filter == 'unread' ? '&filter=unread' : '' ?>" 
                                   class="btn-sm btn-delete" 
                                   onclick="return confirm('Delete this notification?')">
                                    Delete
                                </a>
                            </div>
                        </div>
                        
                        <?php endwhile; ?>
                        
                    </div>
                <?php else: ?>
                    
                    <div class="empty-state">
                        <h3>No Notifications</h3>
                        <p>You're all caught up!</p>
                    </div>
                    
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

</body>
</html>
<?php $conn->close(); ?>