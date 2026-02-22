<?php
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
    // Invalid child selection, clear session and redirect
    unset($_SESSION['selected_child_id']);
    header("Location: select_child.php");
    exit();
}
$verify_child->close();

// ============================================
// AUTO-CREATE NOTIFICATIONS FOR UPCOMING APPOINTMENTS
// (Only for the selected child)
// ============================================

$today = date('Y-m-d');
$two_days = date('Y-m-d', strtotime('+2 days'));

// Find upcoming appointments for the selected child only
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
    AND a.appointment_date BETWEEN '$today' AND '$two_days'
";

$appointments = $conn->query($appointments_query);

if ($appointments && $appointments->num_rows > 0) {
    while ($apt = $appointments->fetch_assoc()) {
        $days = $apt['days_until'];
        
        // Set doctor icon based on role
        $doctor_icon = ($apt['doctor_role'] == 'specialist') ? '👨‍⚕️' : '💉';
        $doctor_type = ($apt['doctor_role'] == 'specialist') ? 'Specialist' : 'Immunization';
        
        // Set notification title based on how soon
        if ($days == 0) {
            $title = "🔴 TODAY: Appointment with {$doctor_type} Doctor";
        } elseif ($days == 1) {
            $title = "🟡 TOMORROW: Appointment with {$doctor_type} Doctor";
        } else {
            $title = "📅 In {$days} Days: Appointment with {$doctor_type} Doctor";
        }
        
        $message = $apt['child_first_name'] . " has an appointment with Dr. " . 
                   $apt['doctor_name'] . " " . $doctor_icon . " on " . 
                   date('l, F j, Y', strtotime($apt['appointment_date'])) . " at " . 
                   date('g:i A', strtotime($apt['appointment_time']));
        
        // Check if notification already exists for this appointment today
        $check_query = "
            SELECT notification_id 
            FROM notifications 
            WHERE guardian_id = $guardian_id 
            AND child_id = $selected_child_id
            AND related_id = {$apt['appointment_id']}
            AND notification_type = 'appointment_reminder'
            AND DATE(created_at) = CURDATE()
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
// HANDLE ACTIONS
// ============================================

if (isset($_GET['mark_read'])) {
    $notif_id = intval($_GET['mark_read']);
    $conn->query("UPDATE notifications SET is_read = 1 WHERE notification_id = $notif_id AND guardian_id = $guardian_id AND child_id = $selected_child_id");
    header("Location: notifications.php");
    exit();
}

if (isset($_GET['mark_all'])) {
    $conn->query("UPDATE notifications SET is_read = 1 WHERE guardian_id = $guardian_id AND child_id = $selected_child_id AND is_read = 0");
    header("Location: notifications.php");
    exit();
}

if (isset($_GET['delete'])) {
    $notif_id = intval($_GET['delete']);
    $conn->query("DELETE FROM notifications WHERE notification_id = $notif_id AND guardian_id = $guardian_id AND child_id = $selected_child_id");
    header("Location: notifications.php");
    exit();
}

// ============================================
// GET CHILD INFO FOR DISPLAY
// ============================================
$child_info = $conn->query("
    SELECT first_name, last_name 
    FROM children 
    WHERE child_id = $selected_child_id
")->fetch_assoc();

// ============================================
// GET ALL NOTIFICATIONS (Only for selected child)
// ============================================

$notifications_query = "
    SELECT * FROM notifications 
    WHERE guardian_id = $guardian_id 
    AND child_id = $selected_child_id
    ORDER BY is_read ASC, created_at DESC
";
$notifications = $conn->query($notifications_query);

// Count unread notifications for selected child
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
        }
        
        .page-header h1 {
            color: #0b1a33;
            font-size: 28px;
            margin-bottom: 5px;
        }
        
        .page-header p {
            color: #5a6f8c;
            font-size: 14px;
        }
        
        .child-indicator {
            background: white;
            padding: 10px 20px;
            border-left: 4px solid #0b1a33;
            display: inline-block;
            margin-bottom: 20px;
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
            padding: 25px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(11,26,51,0.1);
            text-align: center;
            border-left: 4px solid #0b1a33;
        }
        
        .stat-card.unread {
            border-left-color: #3b82f6;
            background: #eff6ff;
        }
        
        .stat-number {
            font-size: 32px;
            font-weight: 700;
            color: #0b1a33;
            margin-bottom: 5px;
        }
        
        .stat-label {
            color: #5a6f8c;
            font-size: 13px;
            text-transform: uppercase;
        }
        
        .action-bar {
            margin-bottom: 20px;
        }
        
        .btn-mark-all {
            background: #0b1a33;
            color: white;
            padding: 10px 20px;
            border-radius: 6px;
            text-decoration: none;
            display: inline-block;
            font-weight: 600;
            font-size: 14px;
        }
        
        .btn-mark-all:hover {
            background: #1e3a5f;
        }
        
        .card {
            background: white;
            border-radius: 8px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(11,26,51,0.1);
            border-left: 4px solid #0b1a33;
        }
        
        .card h2 {
            color: #0b1a33;
            font-size: 20px;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #e2e8f0;
        }
        
        .notification-list {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        
        .notification-item {
            background: #f8fafd;
            border-radius: 8px;
            padding: 20px;
            border-left: 4px solid #94a3b8;
        }
        
        .notification-item.unread {
            background: #eff6ff;
            border-left-color: #3b82f6;
        }
        
        .notification-item.urgent {
            border-left-color: #dc2626;
            background: #fee2e2;
        }
        
        .notification-header {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 10px;
        }
        
        .notification-title {
            font-weight: 700;
            color: #0b1a33;
            font-size: 16px;
        }
        
        .new-badge {
            background: #3b82f6;
            color: white;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 10px;
            font-weight: 700;
        }
        
        .doctor-badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 10px;
            font-weight: 600;
            margin-left: 8px;
        }
        
        .doctor-immunization {
            background: #e6f0ff;
            color: #0b1a33;
        }
        
        .doctor-specialist {
            background: #f3e8ff;
            color: #6b21a8;
        }
        
        .notification-time {
            font-size: 12px;
            color: #5a6f8c;
            margin-bottom: 10px;
        }
        
        .notification-message {
            color: #1e293b;
            font-size: 14px;
            line-height: 1.6;
            margin-bottom: 15px;
        }
        
        .notification-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .btn-action {
            padding: 6px 12px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
            text-decoration: none;
        }
        
        .btn-read {
            background: #e6f0ff;
            color: #0b1a33;
        }
        
        .btn-read:hover {
            background: #b8d1ff;
        }
        
        .btn-view {
            background: #e6f0ff;
            color: #0b1a33;
        }
        
        .btn-view:hover {
            background: #b8d1ff;
        }
        
        .btn-delete {
            background: #fee2e2;
            color: #991b1b;
        }
        
        .btn-delete:hover {
            background: #fecaca;
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #5a6f8c;
        }
        
        .empty-icon {
            font-size: 64px;
            margin-bottom: 20px;
            opacity: 0.5;
        }
        
        .unread-badge {
            background: #dc2626;
            color: white;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 700;
            margin-left: 8px;
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
            <h1>Notifications</h1>
            <p>Appointment reminders for <?= htmlspecialchars($child_info['first_name'] . ' ' . $child_info['last_name']) ?></p>
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
        
        <!-- Mark All Button -->
        <?php if ($unread_count > 0): ?>
        <div class="action-bar">
            <a href="?mark_all=1" class="btn-mark-all">✓ Mark All as Read</a>
        </div>
        <?php endif; ?>
        
        <!-- Notifications List -->
        <div class="card">
            <h2>All Notifications</h2>
            
            <?php if ($notifications && $notifications->num_rows > 0): ?>
                <div class="notification-list">
                    
                    <?php while ($notif = $notifications->fetch_assoc()): 
                        
                        // Check if urgent (today/tomorrow appointment)
                        $is_urgent = false;
                        if ($notif['notification_type'] == 'appointment_reminder' && $notif['related_id']) {
                            $apt_check = $conn->query("
                                SELECT DATEDIFF(appointment_date, CURDATE()) as days_until 
                                FROM appointments 
                                WHERE appointment_id = {$notif['related_id']}
                            ");
                            if ($apt_check && $apt_check->num_rows > 0) {
                                $apt_data = $apt_check->fetch_assoc();
                                $is_urgent = ($apt_data['days_until'] <= 1);
                            }
                        }
                        
                        // Get doctor type
                        $doctor_type = '';
                        $doctor_badge = '';
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
                                $doctor_badge = ($doctor_type == 'specialist') ? 'doctor-specialist' : 'doctor-immunization';
                            }
                        }
                        
                        $unread_class = $notif['is_read'] ? '' : 'unread';
                        $urgent_class = $is_urgent ? 'urgent' : '';
                    ?>
                    
                    <div class="notification-item <?= $unread_class ?> <?= $urgent_class ?>">
                        
                        <div class="notification-header">
                            <span class="notification-title">
                                <?= htmlspecialchars($notif['title']) ?>
                                <?php if ($doctor_type): ?>
                                    <span class="doctor-badge <?= $doctor_badge ?>">
                                        <?= ucfirst($doctor_type) ?>
                                    </span>
                                <?php endif; ?>
                            </span>
                            <?php if (!$notif['is_read']): ?>
                                <span class="new-badge">NEW</span>
                            <?php endif; ?>
                        </div>
                        
                        <div class="notification-time">
                            <?php
                            $time_ago = time() - strtotime($notif['created_at']);
                            if ($time_ago < 60) {
                                echo 'Just now';
                            } elseif ($time_ago < 3600) {
                                echo floor($time_ago / 60) . ' minutes ago';
                            } elseif ($time_ago < 86400) {
                                echo floor($time_ago / 3600) . ' hours ago';
                            } else {
                                echo date('M j, Y g:i A', strtotime($notif['created_at']));
                            }
                            ?>
                        </div>
                        
                        <div class="notification-message">
                            <?= nl2br(htmlspecialchars($notif['message'])) ?>
                        </div>
                        
                        <div class="notification-actions">
                            
                            <?php if (!$notif['is_read']): ?>
                                <a href="?mark_read=<?= $notif['notification_id'] ?>" class="btn-action btn-read">
                                    ✓ Mark as Read
                                </a>
                            <?php endif; ?>
                            
                            <?php if ($notif['notification_type'] == 'appointment_reminder'): ?>
                                <a href="appointments.php" class="btn-action btn-view">
                                    📅 View Appointment
                                </a>
                            <?php endif; ?>
                            
                            <a href="?delete=<?= $notif['notification_id'] ?>" 
                               class="btn-action btn-delete" 
                               onclick="return confirm('Delete this notification?')">
                                🗑️ Delete
                            </a>
                        </div>
                    </div>
                    
                    <?php endwhile; ?>
                    
                </div>
            <?php else: ?>
                
                <div class="empty-state">
                    <div class="empty-icon">🔕</div>
                    <h3>No Notifications</h3>
                    <p>You're all caught up! Check back later for appointment reminders.</p>
                </div>
                
            <?php endif; ?>
        </div>
    </div>
</div>

</body>
</html>
<?php $conn->close(); ?>