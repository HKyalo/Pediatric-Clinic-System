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

$children_query = $conn->query("SELECT COUNT(*) as total FROM children");
$total_children = $children_query->fetch_assoc()['total'];

$guardians_query = $conn->query("SELECT COUNT(*) as total FROM guardians");
$total_guardians = $guardians_query->fetch_assoc()['total'];

$doctors_query = $conn->query("SELECT COUNT(*) as total FROM doctors");
$total_doctors = $doctors_query->fetch_assoc()['total'];
$immunization_doctors = $conn->query("SELECT COUNT(*) as total FROM doctors WHERE doctor_role = 'immunization'")->fetch_assoc()['total'];
$specialist_doctors = $conn->query("SELECT COUNT(*) as total FROM doctors WHERE doctor_role = 'specialist'")->fetch_assoc()['total'];

$today = date('Y-m-d');
$today_query = $conn->query("SELECT COUNT(*) as total FROM appointments WHERE appointment_date = '$today'");
$appointments_today = $today_query->fetch_assoc()['total'];

$upcoming_query = $conn->query("SELECT COUNT(*) as total FROM appointments WHERE appointment_date >= CURDATE()");
$upcoming_appointments = $upcoming_query->fetch_assoc()['total'];

$pending_query = $conn->query("SELECT COUNT(*) as total FROM appointments WHERE status = 'Pending' AND appointment_date >= CURDATE()");
$pending_appointments = $pending_query->fetch_assoc()['total'];

$children_this_month = $conn->query("SELECT COUNT(*) as total FROM children WHERE MONTH(created_at) = MONTH(CURDATE())")->fetch_assoc()['total'];
$guardians_this_month = $conn->query("SELECT COUNT(*) as total FROM guardians WHERE MONTH(created_at) = MONTH(CURDATE())")->fetch_assoc()['total'];

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

$recent_query = $conn->query("
    SELECT a.created_at, c.first_name, c.last_name, d.full_name as doctor_name, a.status
    FROM appointments a
    LEFT JOIN children c ON a.child_id = c.child_id
    LEFT JOIN doctors d ON a.doctor_id = d.doctor_id
    ORDER BY a.created_at DESC LIMIT 5
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
            padding: 30px 30px 30px 30px;
            background: #f0f4fc;
            flex: 1;
            width: calc(100vw - 260px);
            max-width: calc(100vw - 260px);
        }

        /* ── Welcome Banner ── */
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

        /* ── Stats Grid — ALL 6 EQUAL CARDS ── */
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

        /* Faint watermark circle */
        .stat-card::after {
            content: '';
            position: absolute;
            top: -20px; right: -20px;
            width: 90px; height: 90px;
            border-radius: 50%;
            opacity: 0.06;
            pointer-events: none;
        }

        /* Colours */
        .stat-card.children  { border-left-color: #3b82f6; }
        .stat-card.guardians { border-left-color: #10b981; }
        .stat-card.doctors   { border-left-color: #8b5cf6; }
        .stat-card.today     { border-left-color: #3b82f6; }
        .stat-card.upcoming  { border-left-color: #10b981; }
        .stat-card.pending   { border-left-color: #f59e0b; }

        .stat-card.children::after,
        .stat-card.today::after    { background: #3b82f6; }
        .stat-card.guardians::after,
        .stat-card.upcoming::after { background: #10b981; }
        .stat-card.doctors::after  { background: #8b5cf6; }
        .stat-card.pending::after  { background: #f59e0b; }

        .stat-label {
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.9px;
            color: #5a6f8c;
            margin-bottom: 8px;
            line-height: 1.4;
        }

        .stat-number {
            font-size: 48px;
            font-weight: 800;
            color: #0b1a33;
            line-height: 1;
            letter-spacing: -2px;
            margin-bottom: 16px;
        }

        .stat-divider {
            height: 1px;
            background: #e8edf5;
            margin-bottom: 12px;
        }

        /* Footer row — trend text OR doctor badges */
        .stat-footer {
            margin-top: auto;
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 12px;
            font-weight: 600;
            color: #10b981;
            flex-wrap: wrap;
        }

        .stat-footer.neutral { color: #5a6f8c; }

        .stat-footer-dot {
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background: currentColor;
            flex-shrink: 0;
        }

        /* Doctor sub-badges */
        .doctor-badge {
            font-size: 11px;
            font-weight: 600;
            padding: 3px 10px;
            border-radius: 20px;
            white-space: nowrap;
        }

        .doctor-badge.immuno     { background: #eff6ff; color: #1d4ed8; }
        .doctor-badge.specialist { background: #f5f3ff; color: #6b21a8; }

        /* ── Section Cards ── */
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
            padding: 18px 24px 16px;
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

        /* ── Calendar ── */
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

        .calendar-day:hover       { border-color: #0b1a33; }
        .calendar-day.today       { background: #e6f0ff; border: 2px solid #0b1a33; }
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
        .appointment-item.confirmed { border-left-color: #10b981; background: #ecfdf5; color: #065f46; }
        .appointment-item.completed { border-left-color: #3b82f6; background: #eff6ff; color: #1e40af; }

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
        .dot-confirmed { background: #10b981; }
        .dot-completed { background: #3b82f6; }

        /* ── Activity List ── */
        .activity-list { list-style: none; }

        .activity-item {
            padding: 14px 0;
            border-bottom: 1px solid #f0f4fc;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
        }

        .activity-item:last-child  { border-bottom: none; padding-bottom: 0; }
        .activity-item:first-child { padding-top: 0; }

        .activity-info    { flex: 1; }
        .activity-title   { color: #1e293b; font-weight: 600; font-size: 14px; margin-bottom: 4px; }
        .activity-meta    { color: #8fa3bf; font-size: 12px; }

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
        .status-badge.confirmed { background: #ecfdf5; color: #065f46; border: 1px solid #6ee7b7; }
        .status-badge.completed { background: #eff6ff; color: #1e40af; border: 1px solid #bfdbfe; }
        .status-badge.cancelled { background: #fef2f2; color: #991b1b; border: 1px solid #fca5a5; }

        .empty-state {
            text-align: center;
            padding: 40px;
            color: #5a6f8c;
            font-style: italic;
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

        <!-- ALL 6 STAT CARDS - NO ICONS -->
        <div class="stats-grid">

            <!-- 1. Children -->
            <div class="stat-card children">
                <div class="stat-label">Total Children</div>
                <div class="stat-number"><?= $total_children ?></div>
                <div class="stat-divider"></div>
                <div class="stat-footer">
                    <span class="stat-footer-dot"></span>
                    +<?= $children_this_month ?> this month
                </div>
            </div>

            <!-- 2. Guardians -->
            <div class="stat-card guardians">
                <div class="stat-label">Total Guardians</div>
                <div class="stat-number"><?= $total_guardians ?></div>
                <div class="stat-divider"></div>
                <div class="stat-footer">
                    <span class="stat-footer-dot"></span>
                    +<?= $guardians_this_month ?> this month
                </div>
            </div>

            <!-- 3. Doctors -->
            <div class="stat-card doctors">
                <div class="stat-label">Total Doctors</div>
                <div class="stat-number"><?= $total_doctors ?></div>
                <div class="stat-divider"></div>
                <div class="stat-footer">
                    <span class="doctor-badge immuno"><?= $immunization_doctors ?> Immunization</span>
                    <span class="doctor-badge specialist"><?= $specialist_doctors ?> Specialist</span>
                </div>
            </div>

            <!-- 4. Appointments Today -->
            <div class="stat-card today">
                <div class="stat-label">Appointments Today</div>
                <div class="stat-number"><?= $appointments_today ?></div>
            </div>

            <!-- 5. Upcoming -->
            <div class="stat-card upcoming">
                <div class="stat-label">Upcoming Appointments</div>
                <div class="stat-number"><?= $upcoming_appointments ?></div>
            </div>

            <!-- 6. Pending -->
            <div class="stat-card pending">
                <div class="stat-label">Pending Appointments</div>
                <div class="stat-number"><?= $pending_appointments ?></div>
            </div>

        </div>

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
                    $today     = new DateTime();
                    $start_day = (int)$first_day->format('w');

                    for ($i = 0; $i < $start_day; $i++) echo '<div class="calendar-day other-month"></div>';

                    $current_date = clone $first_day;
                    while ($current_date <= $last_day) {
                        $date_str = $current_date->format('Y-m-d');
                        $day_num  = $current_date->format('j');
                        $is_today = ($date_str == $today->format('Y-m-d'));
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
                    <div class="legend-item"><span class="legend-dot dot-confirmed"></span> Confirmed</div>
                    <div class="legend-item"><span class="legend-dot dot-completed"></span> Completed</div>
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
                                <span class="status-badge <?= strtolower($activity['status']) ?>">
                                    <?= htmlspecialchars($activity['status']) ?>
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