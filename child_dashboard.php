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

// Get guardian data
$guardian_id = $_SESSION['guardian_id'];
$guardian_name = $_SESSION['guardian_name'];
$guardian_email = $_SESSION['guardian_email'];

// Get guardian's phone number from database
$stmt = $conn->prepare("SELECT phone FROM guardians WHERE id = ?");
$stmt->bind_param("i", $guardian_id);
$stmt->execute();
$guardian_result = $stmt->get_result();

if ($guardian_result->num_rows > 0) {
    $guardian_data = $guardian_result->fetch_assoc();
    $guardian_phone = $guardian_data['phone'] ?? 'N/A';
} else {
    $guardian_phone = 'N/A';
}
$stmt->close();

// Get children linked to this guardian - using child_id column
$stmt = $conn->prepare("SELECT child_id, first_name, last_name, gender, date_of_birth FROM children WHERE guardian_id = ? ORDER BY date_of_birth ASC");
$stmt->bind_param("i", $guardian_id);
$stmt->execute();
$children_result = $stmt->get_result();

// Check if guardian has any children registered
if ($children_result->num_rows === 0) {
    $no_children = true;
    $child = null;
} else {
    $no_children = false;
    // Get the first child (you can modify this to handle multiple children)
    $child = $children_result->fetch_assoc();
    
    // Calculate age from date of birth
    $dob = new DateTime($child['date_of_birth']);
    $now = new DateTime();
    $age = $now->diff($dob)->y;
    
    $child_id = $child['child_id'];
    $child_full_name = $child['first_name'] . ' ' . $child['last_name'];
    $child_dob = $child['date_of_birth'];
    $child_gender = $child['gender'];
}

$stmt->close();

// Get upcoming appointments (if appointments table exists)
$next_appointment = null;

if (!$no_children) {
    // Check if appointments table exists before querying
    $table_check = $conn->query("SHOW TABLES LIKE 'appointments'");
    
    if ($table_check->num_rows > 0) {
        // FIXED: Using d.full_name and d.doctor_id
        $appointment_query = "SELECT a.*, d.full_name as doctor_name 
                              FROM appointments a 
                              LEFT JOIN doctors d ON a.doctor_id = d.doctor_id 
                              WHERE a.child_id = ? AND a.appointment_date >= CURDATE() 
                              ORDER BY a.appointment_date ASC, a.appointment_time ASC 
                              LIMIT 1";
        
        $stmt = $conn->prepare($appointment_query);
        $stmt->bind_param("i", $child_id);
        $stmt->execute();
        $appointment_result = $stmt->get_result();
        
        if ($appointment_result->num_rows > 0) {
            $next_appointment = $appointment_result->fetch_assoc();
        }
        $stmt->close();
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dashboard - PA-EHR System</title>
<link rel="stylesheet" href="assets/css/style.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js" defer></script>
</head>
<body class="dashboard-body">

<aside class="sidebar" id="sidebar">
  <h2>PediaLink Dashboard</h2>
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
  <?php if ($no_children): ?>
    <h1>Welcome, <?php echo htmlspecialchars($guardian_name); ?>!</h1>
    <div class="card" style="text-align: center; padding: 40px;">
      <h3>No Children Registered</h3>
      <p>You haven't registered any children yet. Please contact the administrator to add a child to your account.</p>
    </div>
  <?php else: ?>
    <h1>Child Dashboard: <?php echo htmlspecialchars($child_full_name); ?></h1>
    <p>Guardian: <?php echo htmlspecialchars($guardian_name); ?> | Contact: <?php echo htmlspecialchars($guardian_phone); ?></p>

    <!-- Quick Overview Cards -->
    <div class="quick-cards">

      <!-- Child & Guardian Details Card -->
      <div class="card child-details-card">
        <h3>Personal Details</h3>
        <div class="details-grid">
          <!-- Left: Child Details -->
          <div class="details-half">
            <h4>Child Details</h4>
            <p><strong>Name:</strong> <?php echo htmlspecialchars($child_full_name); ?></p>
            <p><strong>Date of Birth:</strong> <?php echo htmlspecialchars($child_dob); ?> (Age <?php echo $age; ?>)</p>
            <p><strong>Gender:</strong> <?php echo htmlspecialchars($child_gender); ?></p>
            <p><strong>Blood Group:</strong> <em>Not yet recorded</em></p>
            <p><strong>Allergies / Conditions:</strong> <em>None recorded</em></p>
          </div>

          <!-- Right: Guardian Details -->
          <div class="details-half">
            <h4>Guardian Details</h4>
            <p><strong>Name:</strong> <?php echo htmlspecialchars($guardian_name); ?></p>
            <p><strong>Contact:</strong> <?php echo htmlspecialchars($guardian_phone); ?></p>
            <p><strong>Email:</strong> <?php echo htmlspecialchars($guardian_email); ?></p>
            <p><strong>Guardian ID:</strong> #<?php echo $guardian_id; ?></p>
          </div>
        </div>
      </div>

      <!-- Next Appointment -->
      <div class="card">
        <h3>Next Appointment</h3>
        <?php if ($next_appointment): ?>
          <p><?php echo date('F d, Y', strtotime($next_appointment['appointment_date'])); ?>, 
             <?php echo date('g:i A', strtotime($next_appointment['appointment_time'])); ?> - 
             Dr. <?php echo htmlspecialchars($next_appointment['doctor_name']); ?></p>
        <?php else: ?>
          <p>No upcoming appointments</p>
        <?php endif; ?>
        <button onclick="window.location.href='appointments.php'">View Appointments</button>
      </div>

      <!-- Vaccination Progress -->
      <div class="card">
        <h3>Vaccination Progress</h3>
        <div class="progress-bar">
          <div class="progress" style="width: 0%;"></div>
        </div>
        <p>0 vaccines completed</p>
        <button onclick="window.location.href='vaccinations.php'">View Vaccines</button>
      </div>

      <!-- Growth Status -->
      <div class="card">
        <h3>Growth Status</h3>
        <p><em>No growth records yet</em></p>
        <button onclick="window.location.href='medical-history.php'">View Growth History</button>
      </div>
    </div>

    <!-- Mini Growth Chart -->
    <div class="card">
      <h3>Growth Overview</h3>
      <p style="text-align: center; padding: 40px; color: #666;">
        <em>Growth tracking data will appear here once medical records are added by healthcare providers.</em>
      </p>
      <p><a href="medical-history.php">View Full Growth History</a></p>
    </div>

    <!-- Next Due Vaccine -->
    <div class="card">
      <h3>Next Due Vaccine</h3>
      <p><em>Vaccination schedule will be determined by your pediatrician</em></p>
      <div class="progress-bar">
        <div class="progress" style="width: 0%;"></div>
      </div>
      <p><a href="vaccinations.php">View All Vaccines</a></p>
    </div>

    <!-- Recent Notifications -->
    <div class="card">
      <h3>Recent Notifications</h3>
      <ul>
        <li>Welcome to PediaLink! Your account has been created successfully.</li>
        <?php if ($next_appointment): ?>
          <li>Upcoming appointment on <?php echo date('F d, Y', strtotime($next_appointment['appointment_date'])); ?></li>
        <?php endif; ?>
      </ul>
      <button onclick="window.location.href='notifications.php'">View All Notifications</button>
    </div>
  <?php endif; ?>
</main>

</body>
</html>