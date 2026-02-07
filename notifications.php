<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['guardian_id'])) {
    header("Location: index.php");
    exit();
}

// Connect to database
$conn = new mysqli("localhost", "root", "", "PediaLink", 3307);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$guardian_id = $_SESSION['guardian_id'];

// Get child info
$stmt = $conn->prepare("SELECT child_id, first_name, last_name FROM children WHERE guardian_id = ? LIMIT 1");
$stmt->bind_param("i", $guardian_id);
$stmt->execute();
$child_result = $stmt->get_result();

if ($child_result->num_rows === 0) {
    header("Location: child_dashboard.php");
    exit();
}

$child = $child_result->fetch_assoc();
$child_id = $child['child_id'];
$child_name = $child['first_name'] . ' ' . $child['last_name'];
$stmt->close();

// Handle reminder setting
if (isset($_POST['set_reminder'])) {
    $appointment_id = $_POST['appointment_id'];
    // You can expand this to send email/SMS later
    $message = "Reminder set successfully!";
}

// Get upcoming appointments (next 30 days)
$upcoming_query = "SELECT a.*, d.full_name as doctor_name, d.specialization 
                   FROM appointments a 
                   LEFT JOIN doctors d ON a.doctor_id = d.doctor_id 
                   WHERE a.child_id = ? AND a.status != 'Cancelled' AND a.appointment_date >= CURDATE()
                   ORDER BY a.appointment_date ASC, a.appointment_time ASC";
$stmt = $conn->prepare($upcoming_query);
$stmt->bind_param("i", $child_id);
$stmt->execute();
$upcoming_appointments = $stmt->get_result();
$stmt->close();

// Get pending vaccination reminders
$pending_vaccines = $conn->query("
    SELECT v.vaccine_name, v.recommended_age 
    FROM vaccines v 
    LEFT JOIN vaccination_records vr ON v.vaccine_id = vr.vaccine_id AND vr.child_id = $child_id
    WHERE vr.vaccination_record_id IS NULL
    ORDER BY v.vaccine_id ASC
    LIMIT 5
");

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Notifications - PA-EHR System</title>
<link rel="stylesheet" href="assets/css/style.css">
<style>
.notification-card {
    margin-bottom: 20px;
    padding: 20px;
    border-left: 5px solid #007bff;
}
.notification-card.urgent {
    border-left-color: #dc3545;
    background-color: #fff5f5;
}
.notification-card.upcoming {
    border-left-color: #ffc107;
    background-color: #fffef5;
}
.notification-card.info {
    border-left-color: #17a2b8;
    background-color: #f0f9ff;
}
.notification-actions {
    margin-top: 15px;
    display: flex;
    gap: 10px;
}
.action-btn {
    padding: 8px 15px;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    font-size: 0.9em;
}
.btn-reschedule {
    background-color: #007bff;
    color: white;
}
.btn-cancel {
    background-color: #dc3545;
    color: white;
}
.btn-reminder {
    background-color: #28a745;
    color: white;
}
.btn-view {
    background-color: #6c757d;
    color: white;
}
.action-btn:hover {
    opacity: 0.9;
}
.days-until {
    display: inline-block;
    padding: 4px 10px;
    background-color: #ffc107;
    color: #000;
    border-radius: 3px;
    font-weight: bold;
    font-size: 0.85em;
    margin-left: 10px;
}
.days-until.urgent {
    background-color: #dc3545;
    color: white;
}
</style>
</head>
<body class="dashboard-body">

<aside class="sidebar">
  <h2>PA-EHR Dashboard</h2>
  <nav>
    <ul>
      <li><a href="child_dashboard.php">Dashboard</a></li>
      <li><a href="appointments.php">Appointments</a></li>
      <li><a href="medical-history.php">Medical History</a></li>
      <li><a href="vaccinations.php">Vaccinations</a></li>
      <li><a href="notifications.php">Notifications</a></li>
      <li><a href="profile.php">Profile</a></li>
      <li><a href="logout.php">Logout</a></li>
    </ul>
  </nav>
</aside>

<main class="dashboard-main">
  <h1>Notifications & Reminders</h1>

  <?php if (isset($message)): ?>
    <div class="card" style="background-color: #d4edda; border-left: 4px solid #28a745;">
      <p style="margin: 0; color: #155724;"><?php echo htmlspecialchars($message); ?></p>
    </div>
  <?php endif; ?>

  <!-- Upcoming Appointments Section -->
  <div class="card">
    <h3>📅 Upcoming Appointments</h3>
    
    <?php if ($upcoming_appointments->num_rows > 0): ?>
      <?php while ($apt = $upcoming_appointments->fetch_assoc()): 
          $appointment_date = new DateTime($apt['appointment_date']);
          $today = new DateTime();
          $days_until = $today->diff($appointment_date)->days;
          $is_urgent = $days_until <= 3;
      ?>
        <div class="notification-card <?php echo $is_urgent ? 'urgent' : 'upcoming'; ?>">
          <h4>
            Appointment with Dr. <?php echo htmlspecialchars($apt['doctor_name']); ?>
            <?php if ($days_until == 0): ?>
              <span class="days-until urgent">TODAY</span>
            <?php elseif ($days_until == 1): ?>
              <span class="days-until urgent">TOMORROW</span>
            <?php elseif ($days_until <= 7): ?>
              <span class="days-until <?php echo $is_urgent ? 'urgent' : ''; ?>">In <?php echo $days_until; ?> days</span>
            <?php endif; ?>
          </h4>
          <p>
            <strong>Date:</strong> <?php echo date('l, F d, Y', strtotime($apt['appointment_date'])); ?><br>
            <strong>Time:</strong> <?php echo date('g:i A', strtotime($apt['appointment_time'])); ?><br>
            <strong>Specialization:</strong> <?php echo htmlspecialchars($apt['specialization']); ?><br>
            <strong>Status:</strong> <?php echo htmlspecialchars($apt['status']); ?>
            <?php if (!empty($apt['notes'])): ?>
              <br><strong>Notes:</strong> <?php echo htmlspecialchars($apt['notes']); ?>
            <?php endif; ?>
          </p>
          
          <div class="notification-actions">
            <form method="POST" style="display: inline;">
              <input type="hidden" name="appointment_id" value="<?php echo $apt['appointment_id']; ?>">
              <button type="submit" name="set_reminder" class="action-btn btn-reminder">🔔 Set Reminder</button>
            </form>
            <button class="action-btn btn-reschedule" onclick="alert('Reschedule feature coming soon!')">📅 Reschedule</button>
            <button class="action-btn btn-cancel" onclick="if(confirm('Cancel this appointment?')) window.location.href='appointments.php?cancel=<?php echo $apt['appointment_id']; ?>'">❌ Cancel</button>
            <button class="action-btn btn-view" onclick="window.location.href='appointments.php'">👁️ View Details</button>
          </div>
        </div>
      <?php endwhile; ?>
    <?php else: ?>
      <p style="text-align: center; padding: 30px; color: #666;">No upcoming appointments. <a href="appointments.php">Book an appointment</a></p>
    <?php endif; ?>
  </div>

  <!-- Vaccination Reminders Section -->
  <div class="card">
    <h3>💉 Vaccination Reminders</h3>
    
    <?php if ($pending_vaccines->num_rows > 0): ?>
      <?php while ($vaccine = $pending_vaccines->fetch_assoc()): ?>
        <div class="notification-card info">
          <h4><?php echo htmlspecialchars($vaccine['vaccine_name']); ?> - Due</h4>
          <p>
            <strong>Recommended Age:</strong> <?php echo htmlspecialchars($vaccine['recommended_age']); ?><br>
            <strong>Child:</strong> <?php echo htmlspecialchars($child_name); ?>
          </p>
          
          <div class="notification-actions">
            <button class="action-btn btn-view" onclick="window.location.href='vaccinations.php'">📋 View Vaccine Schedule</button>
            <button class="action-btn btn-reschedule" onclick="window.location.href='appointments.php'">📅 Book Appointment</button>
          </div>
        </div>
      <?php endwhile; ?>
    <?php else: ?>
      <p style="text-align: center; padding: 30px; color: #666;">All vaccinations up to date! 🎉</p>
    <?php endif; ?>
  </div>

</main>
</body>
</html>