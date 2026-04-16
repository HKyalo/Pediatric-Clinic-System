<?php
// Set session to last 24 hours 
ini_set('session.cookie_lifetime', 86400); // 24 hours in seconds
ini_set('session.gc_maxlifetime', 86400);   // 24 hours in seconds
session_set_cookie_params(86400); // Also set cookie params
session_start();

if (!isset($_SESSION['admin_id']) || $_SESSION['user_type'] !== 'admin') {
    header("Location: index.php");
    exit();
}

require_once __DIR__ . "/config/db.php";

$admin_id = $_SESSION['admin_id'];
$admin_name = $_SESSION['admin_name'] ?? 'Administrator';

// ============================================
// KEY STATISTICS - Only the essential ones
// ============================================

// Total Children
$children_query = $conn->query("SELECT COUNT(*) as total FROM children");
$total_children = $children_query->fetch_assoc()['total'];

// Total Guardians/Parents
$guardians_query = $conn->query("SELECT COUNT(*) as total FROM guardians");
$total_guardians = $guardians_query->fetch_assoc()['total'];

// Total Doctors
$doctors_query = $conn->query("SELECT COUNT(*) as total FROM doctors");
$total_doctors = $doctors_query->fetch_assoc()['total'];
$immunization_doctors = $conn->query("SELECT COUNT(*) as total FROM doctors WHERE doctor_role = 'immunization'")->fetch_assoc()['total'];
$specialist_doctors = $conn->query("SELECT COUNT(*) as total FROM doctors WHERE doctor_role = 'specialist'")->fetch_assoc()['total'];

// Missed Appointments (FIXED: use status = 'Missed')
$missed_query = $conn->query("SELECT COUNT(*) as total FROM appointments WHERE status = 'Missed'");
$missed_appointments = $missed_query->fetch_assoc()['total'];

// Appointments Today
$today = date('Y-m-d');
$today_query = $conn->query("SELECT COUNT(*) as total FROM appointments WHERE appointment_date = '$today'");
$appointments_today = $today_query->fetch_assoc()['total'];

// Upcoming Appointments (today and future)
$upcoming_query = $conn->query("SELECT COUNT(*) as total FROM appointments WHERE appointment_date >= CURDATE()");
$upcoming_appointments = $upcoming_query->fetch_assoc()['total'];

// New this month
$children_this_month = $conn->query("SELECT COUNT(*) as total FROM children WHERE MONTH(created_at) = MONTH(CURDATE())")->fetch_assoc()['total'];
$guardians_this_month = $conn->query("SELECT COUNT(*) as total FROM guardians WHERE MONTH(created_at) = MONTH(CURDATE())")->fetch_assoc()['total'];

// ============================================
// CALENDAR DATA
// ============================================

$calendar_query = $conn->query("
    SELECT a.*, c.first_name, c.last_name, d.full_name as doctor_name
    FROM appointments a
    LEFT JOIN children c ON a.child_id = c.child_id
    LEFT JOIN doctors d ON a.doctor_id = d.doctor_id
    WHERE MONTH(a.appointment_date) = MONTH(CURDATE())
    AND YEAR(a.appointment_date) = YEAR(CURDATE())
    AND a.status != 'Cancelled'
    ORDER BY a.appointment_date ASC, a.appointment_time ASC
");

$appointments_by_date = [];
while ($apt = $calendar_query->fetch_assoc()) {
    $date = $apt['appointment_date'];
    if (!isset($appointments_by_date[$date])) $appointments_by_date[$date] = [];
    $appointments_by_date[$date][] = $apt;
}

// ============================================
// RECENT ACTIVITIES - Fix status display
// ============================================

$recent_query = $conn->query("
    SELECT 
        a.created_at, 
        c.first_name, 
        c.last_name, 
        d.full_name as doctor_name,
        a.status,
        a.appointment_date,
        a.appointment_time,
        CASE 
            WHEN a.status = 'Pending' AND a.appointment_date < CURDATE() THEN 'Missed'
            ELSE a.status
        END as display_status
    FROM appointments a
    LEFT JOIN children c ON a.child_id = c.child_id
    LEFT JOIN doctors d ON a.doctor_id = d.doctor_id
    ORDER BY a.created_at DESC LIMIT 5
");

// ============================================
// MISSED APPOINTMENTS LIST (FIXED: use status = 'Missed')
// ============================================

$missed_list_query = $conn->query("
    SELECT a.appointment_id, a.appointment_date, a.appointment_time, a.status,
           c.first_name, c.last_name, c.child_id,
           d.full_name as doctor_name
    FROM appointments a
    LEFT JOIN children c ON a.child_id = c.child_id
    LEFT JOIN doctors d ON a.doctor_id = d.doctor_id
    WHERE a.status = 'Missed'
    ORDER BY a.appointment_date DESC
    LIMIT 5
");
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD HTML 4.01 Strict//EN">
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <title>Admin Dashboard - PCASS</title>
    <link rel="stylesheet" href="assets/css/style.css">
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

        /* Welcome Banner */
        .welcome-banner {
            background: linear-gradient(135deg, #0b1a33 0%, #1e3a5f 100%);
            color: white;
            padding: 28px 32px;
            border-radius: 4px;
            margin-bottom: 28px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 6px 20px rgba(11,26,51,0.2);
        }

        .welcome-text h1 { font-size: 26px; font-weight: 700; margin-bottom: 4px; }
        .welcome-text p  { color: #a3c6ff; font-size: 14px; }

        .date-box {
            background: rgba(255,255,255,0.12);
            padding: 10px 20px;
            border-radius: 4px;
            font-weight: 600;
            font-size: 14px;
            border: 1px solid rgba(255,255,255,0.2);
            white-space: nowrap;
        }

        /* Stats Grid - 5 Key Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 20px;
            margin-bottom: 28px;
            width: 100%;
        }

        .stat-card {
             background: #ffffff;
            border-radius: 4px;
            box-shadow: 0 2px 10px rgba(11,26,51,0.07);
            border-left: 5px solid;
            display: flex;
            flex-direction: column;
            padding: 28px 28px 24px;
            min-height: 180px;
            width: 100%;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            position: relative;
            overflow: hidden;
        }

        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 10px 24px rgba(11,26,51,0.12);
        }

        .stat-card.children  { border-left-color: #3b82f6; }
        .stat-card.guardians { border-left-color: #10b981; }
        .stat-card.doctors   { border-left-color: #8b5cf6; }
        .stat-card.missed    { border-left-color: #ef4444; }
        .stat-card.today     { border-left-color: #f59e0b; }

        .stat-label {
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            color: #5a6f8c;
            margin-bottom: 8px;
        }

        .stat-number {
            font-size: 42px;
            font-weight: 800;
            color: #0b1a33;
            line-height: 1;
            margin-bottom: 12px;
        }

        .stat-footer {
            font-size: 12px;
            color: #10b981;
            font-weight: 600;
            margin-top: 8px;
            padding-top: 8px;
            border-top: 1px solid #e8edf5;
        }

        .stat-footer.warning { color: #ef4444; }
        .stat-footer.neutral { color: #5a6f8c; }

        .doctor-badge {
            font-size: 11px;
            font-weight: 600;
            padding: 3px 10px;
            border-radius: 20px;
            display: inline-block;
            margin-right: 6px;
        }

        .doctor-badge.immuno     { background: #eff6ff; color: #1d4ed8; }
        .doctor-badge.specialist { background: #f5f3ff; color: #6b21a8; }

        /* Cards */
        .card {
            background: white;
            border-radius: 4px;
            margin-bottom: 28px;
            box-shadow: 0 2px 10px rgba(11,26,51,0.07);
            overflow: hidden;
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 18px 24px;
            background: #f8fafd;
            border-bottom: 1px solid #e8edf5;
        }

        .card-header h2 {
            color: #0b1a33;
            font-size: 16px;
            font-weight: 700;
            margin: 0;
        }

        .card-header a {
            color: #3b82f6;
            text-decoration: none;
            font-size: 13px;
            font-weight: 600;
            padding: 5px 14px;
            border: 1px solid #dbeafe;
            border-radius: 4px;
            transition: background 0.15s;
        }

        .card-header a:hover { background: #eff6ff; }
        .card-body { padding: 24px; }

        /* Missed Appointments List */
        .missed-list { list-style: none; }

        .missed-item {
            padding: 14px 0;
            border-bottom: 1px solid #f0f4fc;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
        }

        .missed-item:last-child { border-bottom: none; padding-bottom: 0; }
        .missed-item:first-child { padding-top: 0; }

        .missed-info { flex: 1; }
        .missed-title { color: #1e293b; font-weight: 600; font-size: 14px; margin-bottom: 4px; }
        .missed-meta { color: #8fa3bf; font-size: 12px; }

        .btn-reschedule {
            background: #f59e0b;
            color: white;
            border: none;
            padding: 6px 14px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            transition: background 0.2s;
        }

        .btn-reschedule:hover { background: #d97706; }

        /* Calendar */
        .calendar-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 6px;
        }

        .calendar-day-header {
            text-align: center;
            font-weight: 700;
            font-size: 11px;
            letter-spacing: 0.8px;
            padding: 10px 4px;
            background: #f0f4fc;
            color: #0b1a33;
        }

        .calendar-day {
            min-height: 90px;
            border: 1px solid #e8edf5;
            padding: 8px;
            background: white;
            transition: border-color 0.2s;
        }

        .calendar-day:hover { border-color: #0b1a33; }
        .calendar-day.today { background: #e6f0ff; border: 2px solid #0b1a33; }
        .calendar-day.other-month { background: #f8fafd; opacity: 0.5; }

        .day-number {
            font-weight: 700;
            font-size: 13px;
            color: #0b1a33;
            margin-bottom: 5px;
        }

        .appointment-item {
            font-size: 10px;
            font-weight: 600;
            padding: 3px 6px;
            margin: 2px 0;
            border-left: 3px solid;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .appointment-item.pending   { border-left-color: #f59e0b; background: #fffbeb; color: #92400e; }
        .appointment-item.completed { border-left-color: #3b82f6; background: #eff6ff; color: #1e40af; }
        .appointment-item.missed    { border-left-color: #ef4444; background: #fef2f2; color: #991b1b; }

        .calendar-legend {
            display: flex;
            gap: 20px;
            justify-content: center;
            margin-top: 18px;
            padding-top: 16px;
            border-top: 1px solid #e8edf5;
        }

        .legend-item {
            display: flex;
            align-items: center;
            gap: 7px;
            font-size: 12px;
            font-weight: 600;
            color: #5a6f8c;
        }

        .legend-dot { width: 10px; height: 10px; display: inline-block; }
        .dot-pending   { background: #f59e0b; }
        .dot-completed { background: #3b82f6; }
        .dot-missed    { background: #ef4444; }

        /* Activity List */
        .activity-list { list-style: none; }

        .activity-item {
            padding: 14px 0;
            border-bottom: 1px solid #f0f4fc;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
        }

        .activity-item:last-child { border-bottom: none; padding-bottom: 0; }
        .activity-item:first-child { padding-top: 0; }

        .activity-info { flex: 1; }
        .activity-title { color: #1e293b; font-weight: 600; font-size: 14px; margin-bottom: 4px; }
        .activity-meta { color: #8fa3bf; font-size: 12px; }

        .status-badge {
            padding: 4px 11px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            white-space: nowrap;
            border-radius: 2px;
        }

        .status-badge.pending   { background: #fffbeb; color: #92400e; border: 1px solid #fde68a; }
        .status-badge.completed { background: #eff6ff; color: #1e40af; border: 1px solid #bfdbfe; }
        .status-badge.cancelled { background: #fef2f2; color: #991b1b; border: 1px solid #fca5a5; }
        .status-badge.missed    { background: #fef2f2; color: #991b1b; border: 1px solid #fca5a5; }

        .empty-state {
            text-align: center;
            padding: 40px;
            color: #5a6f8c;
            font-style: italic;
        }

        /* Responsive */
        @media (max-width: 1000px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 600px) {
            .stats-grid {
                grid-template-columns: 1fr;
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
                <li><a href="admin_dashboard.php" class="active">Dashboard</a></li>
                <li><a href="manage_doctors.php">Manage Doctors</a></li>
                <li><a href="manage_guardians.php">Manage Guardians</a></li>
                <li><a href="manage_children.php">Manage Children</a></li>
                <li><a href="manage_appointments.php">Manage Appointments</a></li>
                <li><a href="manage_vaccines.php">Manage Vaccines</a></li>
                <li><a href="admin_reports.php">Reports</a></li>
                <li><a href="logout.php">Logout</a></li>
            </ul>
        </div>
    </div>

    <!-- MAIN CONTENT -->
    <div class="main-content">

        <!-- Welcome Banner -->
        <div class="welcome-banner">
            <div class="welcome-text">
                <h1>Admin Dashboard</h1>
                <p>Welcome back, <?= htmlspecialchars($admin_name) ?>!</p>
            </div>
            <div class="date-box"><?= date('l, F j, Y') ?></div>
        </div>

        <!-- KEY STATISTICS CARDS - Only 5 -->
        <div class="stats-grid">

            <!-- 1. Total Children -->
            <div class="stat-card children">
                <div class="stat-label">Total Children</div>
                <div class="stat-number"><?= $total_children ?></div>
                <div class="stat-footer">
                    +<?= $children_this_month ?> this month
                </div>
            </div>

            <!-- 2. Total Guardians / Parents -->
            <div class="stat-card guardians">
                <div class="stat-label">Guardians / Parents</div>
                <div class="stat-number"><?= $total_guardians ?></div>
                <div class="stat-footer">
                    +<?= $guardians_this_month ?> this month
                </div>
            </div>

            <!-- 3. Total Doctors -->
            <div class="stat-card doctors">
                <div class="stat-label">Total Doctors</div>
                <div class="stat-number"><?= $total_doctors ?></div>
                <div class="stat-footer">
                    <span class="doctor-badge immuno"><?= $immunization_doctors ?> Immunization</span>
                    <span class="doctor-badge specialist"><?= $specialist_doctors ?> Specialist</span>
                </div>
            </div>

            <!-- 4. Missed Appointments -->
            <div class="stat-card missed">
                <div class="stat-label">Missed Appointments</div>
                <div class="stat-number"><?= $missed_appointments ?></div>
                <div class="stat-footer warning">
                </div>
            </div>

            <!-- 5. Appointments Today -->
            <div class="stat-card today">
                <div class="stat-label">Appointments Today</div>
                <div class="stat-number"><?= $appointments_today ?></div>
                <div class="stat-footer neutral">
                </div>
            </div>

        </div>

        <!-- MISSED APPOINTMENTS SECTION (Requires Attention) -->
        <?php if ($missed_list_query && $missed_list_query->num_rows > 0): ?>
        <div class="card">
            <div class="card-header">
                <h2>Missed Appointments</h2>
                <a href="manage_appointments.php">Manage All →</a>
            </div>
            <div class="card-body">
                <div class="missed-list">
                    <?php while ($missed = $missed_list_query->fetch_assoc()): ?>
                        <div class="missed-item">
                            <div class="missed-info">
                                <div class="missed-title">
                                    <?= htmlspecialchars($missed['first_name'] . ' ' . $missed['last_name']) ?>
                                    with Dr. <?= htmlspecialchars($missed['doctor_name']) ?>
                                </div>
                                <div class="missed-meta">
                                    Missed on <?= date('F d, Y', strtotime($missed['appointment_date'])) ?> at <?= date('g:i A', strtotime($missed['appointment_time'])) ?>
                                </div>
                            </div>
                            <a href="manage_appointments.php?reschedule=<?= $missed['appointment_id'] ?>" class="btn-reschedule">
                                Reschedule
                            </a>
                        </div>
                    <?php endwhile; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Calendar -->
        <div class="card">
            <div class="card-header">
                <h2>Appointment Calendar — <?= date('F Y') ?></h2>
                <a href="manage_appointments.php">View All →</a>
            </div>
            <div class="card-body">
                <div class="calendar-grid">
                    <div class="calendar-day-header">SUN</div>
                    <div class="calendar-day-header">MON</div>
                    <div class="calendar-day-header">TUE</div>
                    <div class="calendar-day-header">WED</div>
                    <div class="calendar-day-header">THU</div>
                    <div class="calendar-day-header">FRI</div>
                    <div class="calendar-day-header">SAT</div>

                    <?php
                    $first_day = new DateTime(date('Y-m-01'));
                    $last_day  = new DateTime(date('Y-m-t'));
                    $today_dt  = new DateTime();
                    $start_day = (int)$first_day->format('w');

                    for ($i = 0; $i < $start_day; $i++) echo '<div class="calendar-day other-month"></div>';

                    $current_date = clone $first_day;
                    while ($current_date <= $last_day) {
                        $date_str = $current_date->format('Y-m-d');
                        $day_num  = $current_date->format('j');
                        $is_today = ($date_str == $today_dt->format('Y-m-d'));
                        $class    = 'calendar-day' . ($is_today ? ' today' : '');

                        echo '<div class="' . $class . '">';
                        echo '<div class="day-number">' . $day_num . '</div>';

                        if (isset($appointments_by_date[$date_str])) {
                            foreach ($appointments_by_date[$date_str] as $apt) {
                                $status = strtolower($apt['status']);
                                $time   = date('g:i A', strtotime($apt['appointment_time']));
                                echo '<div class="appointment-item ' . $status . '" title="' .
                                     htmlspecialchars($apt['first_name'] . ' ' . $apt['last_name']) .
                                     ' with Dr. ' . htmlspecialchars($apt['doctor_name']) . '">' . $time . '</div>';
                            }
                        }

                        echo '</div>';
                        $current_date->modify('+1 day');
                    }

                    $end_day = (int)$last_day->format('w');
                    for ($i = $end_day + 1; $i < 7; $i++) echo '<div class="calendar-day other-month"></div>';
                    ?>
                </div>

                <div class="calendar-legend">
                    <div class="legend-item"><span class="legend-dot dot-pending"></span> Pending</div>
                    <div class="legend-item"><span class="legend-dot dot-completed"></span> Completed</div>
                    <div class="legend-item"><span class="legend-dot dot-missed"></span> Missed</div>
                </div>
            </div>
        </div>

        <!-- Recent Activities -->
        <div class="card">
            <div class="card-header">
                <h2>Recent Activities</h2>
            </div>
            <div class="card-body">
                <?php if ($recent_query->num_rows > 0): ?>
                    <div class="activity-list">
                        <?php while ($activity = $recent_query->fetch_assoc()): ?>
                            <div class="activity-item">
                                <div class="activity-info">
                                    <div class="activity-title">
                                        <?= htmlspecialchars($activity['first_name'] . ' ' . $activity['last_name']) ?>
                                        with Dr. <?= htmlspecialchars($activity['doctor_name']) ?>
                                    </div>
                                    <div class="activity-meta">
                                        <?= date('F d, Y g:i A', strtotime($activity['created_at'])) ?>
                                    </div>
                                </div>
                                <span class="status-badge <?= strtolower($activity['display_status']) ?>">
                                    <?= htmlspecialchars($activity['display_status']) ?>
                                </span>
                            </div>
                        <?php endwhile; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state">No recent activities</div>
                <?php endif; ?>
            </div>
        </div>

    </div>
</div>
</body>
</html>
<?php $conn->close(); ?>