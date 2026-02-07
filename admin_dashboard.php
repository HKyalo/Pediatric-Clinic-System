<?php
session_start();

// Check if user is logged in as admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'admin') {
    header("Location: index.php");
    exit();
}

// Connect to database
$conn = new mysqli("localhost", "root", "", "PediaLink", 3307);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$admin_name = $_SESSION['name'];

// Fetch totals dynamically
$total_patients = $conn->query("SELECT COUNT(*) as count FROM children")->fetch_assoc()['count'];
$total_guardians = $conn->query("SELECT COUNT(*) as count FROM guardians")->fetch_assoc()['count'];
$total_doctors = $conn->query("SELECT COUNT(*) as count FROM doctors")->fetch_assoc()['count'];
$appointments_today = $conn->query("SELECT COUNT(*) as count FROM appointments WHERE DATE(appointment_date) = CURDATE()")->fetch_assoc()['count'];

// Check if vaccinations table exists, if not set to 0
$vaccination_check = $conn->query("SHOW TABLES LIKE 'vaccinations'");
if ($vaccination_check->num_rows > 0) {
    $pending_vaccinations = $conn->query("SELECT COUNT(*) as count FROM vaccinations WHERE status='Pending'")->fetch_assoc()['count'];
} else {
    // Use vaccination_records table instead
    $pending_vaccinations = $conn->query("SELECT COUNT(*) as count FROM vaccination_records WHERE status='Pending'")->fetch_assoc()['count'];
}

// Get upcoming appointments
$upcoming_appointments = $conn->query("SELECT COUNT(*) as count FROM appointments WHERE appointment_date >= CURDATE()")->fetch_assoc()['count'];
$pending_appointments = $conn->query("SELECT COUNT(*) as count FROM appointments WHERE status = 'Pending' AND appointment_date >= CURDATE()")->fetch_assoc()['count'];

// ===== CALENDAR - GET ALL APPOINTMENTS FOR CURRENT MONTH =====
$calendar_appointments = $conn->query("
    SELECT a.*, c.first_name, c.last_name, d.full_name as doctor_name
    FROM appointments a
    LEFT JOIN children c ON a.child_id = c.child_id
    LEFT JOIN doctors d ON a.doctor_id = d.doctor_id
    WHERE MONTH(a.appointment_date) = MONTH(CURDATE()) 
    AND YEAR(a.appointment_date) = YEAR(CURDATE())
    AND a.status != 'Cancelled'
    ORDER BY a.appointment_date ASC, a.appointment_time ASC
");

// Organize appointments by date
$appointments_by_date = [];
while ($apt = $calendar_appointments->fetch_assoc()) {
    $date = $apt['appointment_date'];
    if (!isset($appointments_by_date[$date])) {
        $appointments_by_date[$date] = [];
    }
    $appointments_by_date[$date][] = $apt;
}

// Get recent activities
$recent_activities = $conn->query("
    SELECT 'appointment' as type, a.created_at, c.first_name, c.last_name, d.full_name as doctor_name, a.status
    FROM appointments a
    LEFT JOIN children c ON a.child_id = c.child_id
    LEFT JOIN doctors d ON a.doctor_id = d.doctor_id
    ORDER BY a.created_at DESC
    LIMIT 5
");

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Dashboard - PediaLink</title>
<link rel="stylesheet" href="assets/css/style.css">
<style>
.cards-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}
.cards-grid .card {
    text-align: center;
    padding: 30px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border-radius: 10px;
}
.cards-grid .card:nth-child(2) {
    background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
}
.cards-grid .card:nth-child(3) {
    background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
}
.cards-grid .card:nth-child(4) {
    background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);
}
.cards-grid .card:nth-child(5) {
    background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);
}
.cards-grid .card:nth-child(6) {
    background: linear-gradient(135deg, #30cfd0 0%, #330867 100%);
}
.cards-grid .card h3 {
    margin: 0 0 15px 0;
    font-size: 1.1em;
    opacity: 0.9;
}
.cards-grid .card p {
    font-size: 3em;
    font-weight: bold;
    margin: 0;
}
.activity-item {
    padding: 10px;
    border-bottom: 1px solid #eee;
}
.activity-item:last-child {
    border-bottom: none;
}
.activity-time {
    color: #999;
    font-size: 0.85em;
}

/* Calendar Styles */
.calendar-container {
    background: white;
    border-radius: 10px;
    padding: 20px;
}
.calendar-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    padding-bottom: 15px;
    border-bottom: 2px solid #eee;
}
.calendar-grid {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    gap: 10px;
}
.calendar-day-header {
    text-align: center;
    font-weight: bold;
    padding: 10px;
    background-color: #f8f9fa;
    border-radius: 5px;
}
.calendar-day {
    min-height: 100px;
    border: 1px solid #e0e0e0;
    border-radius: 8px;
    padding: 8px;
    background-color: #fff;
    position: relative;
}
.calendar-day.today {
    background-color: #e7f3ff;
    border: 2px solid #2196F3;
}
.calendar-day.other-month {
    background-color: #f9f9f9;
    opacity: 0.5;
}
.day-number {
    font-weight: bold;
    margin-bottom: 5px;
    color: #333;
}
.appointment-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    display: inline-block;
    margin-right: 3px;
}
.appointment-dot.pending { background-color: #ffc107; }
.appointment-dot.confirmed { background-color: #28a745; }
.appointment-dot.completed { background-color: #17a2b8; }
.appointment-item {
    font-size: 0.75em;
    padding: 3px 5px;
    margin: 2px 0;
    background-color: #e3f2fd;
    border-radius: 3px;
    cursor: pointer;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.appointment-item:hover {
    background-color: #bbdefb;
}
.calendar-legend {
    display: flex;
    gap: 20px;
    justify-content: center;
    margin-top: 15px;
    padding-top: 15px;
    border-top: 1px solid #eee;
}
.legend-item {
    display: flex;
    align-items: center;
    gap: 5px;
    font-size: 0.9em;
}
</style>
</head>
<body class="dashboard-body">

<aside class="sidebar">
  <h2>Admin Panel</h2>
  <nav>
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
  </nav>++
</aside>

<main class="dashboard-main">
  <h1>Welcome, <?php echo htmlspecialchars($admin_name); ?></h1>

  <div class="cards-grid">
    <div class="card">
      <h3>Total Patients</h3>
      <p><?php echo $total_patients; ?></p>
    </div>
    <div class="card">
      <h3>Total Guardians</h3>
      <p><?php echo $total_guardians; ?></p>
    </div>
    <div class="card">
      <h3>Total Doctors</h3>
      <p><?php echo $total_doctors; ?></p>
    </div>
    <div class="card">
      <h3>Appointments Today</h3>
      <p><?php echo $appointments_today; ?></p>
    </div>
    <div class="card">
      <h3>Upcoming Appointments</h3>
      <p><?php echo $upcoming_appointments; ?></p>
    </div>
    <div class="card">
      <h3>Pending Appointments</h3>
      <p><?php echo $pending_appointments; ?></p>
    </div>
  </div>

  <!-- Calendar View -->
  <div class="card">
    <div class="calendar-container">
      <div class="calendar-header">
        <h3 style="margin: 0;">📅 Appointment Calendar - <?php echo date('F Y'); ?></h3>
        <a href="manage_appointments.php" style="color: #2196F3; text-decoration: none; font-weight: bold;">View All Appointments →</a>
      </div>

      <div class="calendar-grid">
        <!-- Day Headers -->
        <div class="calendar-day-header">Sun</div>
        <div class="calendar-day-header">Mon</div>
        <div class="calendar-day-header">Tue</div>
        <div class="calendar-day-header">Wed</div>
        <div class="calendar-day-header">Thu</div>
        <div class="calendar-day-header">Fri</div>
        <div class="calendar-day-header">Sat</div>

        <?php
        // Get first day of current month
        $first_day = new DateTime(date('Y-m-01'));
        $last_day = new DateTime(date('Y-m-t'));
        $today = new DateTime();
        
        // Get day of week for first day (0 = Sunday, 6 = Saturday)
        $start_day_of_week = (int)$first_day->format('w');
        
        // Fill in empty cells before first day
        for ($i = 0; $i < $start_day_of_week; $i++) {
            echo '<div class="calendar-day other-month"></div>';
        }
        
        // Fill in days of month
        $current_date = clone $first_day;
        while ($current_date <= $last_day) {
            $date_str = $current_date->format('Y-m-d');
            $day_num = $current_date->format('j');
            $is_today = ($current_date->format('Y-m-d') == $today->format('Y-m-d'));
            
            echo '<div class="calendar-day' . ($is_today ? ' today' : '') . '">';
            echo '<div class="day-number">' . $day_num . '</div>';
            
            // Show appointments for this day
            if (isset($appointments_by_date[$date_str])) {
                foreach ($appointments_by_date[$date_str] as $apt) {
                    $status_class = strtolower($apt['status']);
                    echo '<div class="appointment-item" title="' . 
                         htmlspecialchars($apt['first_name'] . ' ' . $apt['last_name']) . 
                         ' - Dr. ' . htmlspecialchars($apt['doctor_name']) . 
                         ' at ' . date('g:i A', strtotime($apt['appointment_time'])) . '">';
                    echo '<span class="appointment-dot ' . $status_class . '"></span>';
                    echo date('g:i A', strtotime($apt['appointment_time'])) . ' - ' . 
                         htmlspecialchars(substr($apt['first_name'], 0, 1) . '. ' . $apt['last_name']);
                    echo '</div>';
                }
            }
            
            echo '</div>';
            $current_date->modify('+1 day');
        }
        
        // Fill remaining cells
        $end_day_of_week = (int)$last_day->format('w');
        for ($i = $end_day_of_week + 1; $i < 7; $i++) {
            echo '<div class="calendar-day other-month"></div>';
        }
        ?>
      </div>

      <!-- Legend -->
      <div class="calendar-legend">
        <div class="legend-item">
          <span class="appointment-dot pending"></span>
          <span>Pending</span>
        </div>
        <div class="legend-item">
          <span class="appointment-dot confirmed"></span>
          <span>Confirmed</span>
        </div>
        <div class="legend-item">
          <span class="appointment-dot completed"></span>
          <span>Completed</span>
        </div>
      </div>
    </div>
  </div>

  <div class="card">
    <h3>Recent Activities</h3>
    <?php if ($recent_activities->num_rows > 0): ?>
      <?php while ($activity = $recent_activities->fetch_assoc()): ?>
        <div class="activity-item">
          <strong><?php echo htmlspecialchars($activity['first_name'] . ' ' . $activity['last_name']); ?></strong> 
          - Appointment with Dr. <?php echo htmlspecialchars($activity['doctor_name']); ?> 
          - Status: <em><?php echo htmlspecialchars($activity['status']); ?></em>
          <br>
          <span class="activity-time"><?php echo date('F d, Y g:i A', strtotime($activity['created_at'])); ?></span>
        </div>
      <?php endwhile; ?>
    <?php else: ?>
      <p style="text-align: center; padding: 20px; color: #999;">No recent activities</p>
    <?php endif; ?>
  </div>

</main>
</body>
</html>