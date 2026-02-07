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
$stmt = $conn->prepare("SELECT child_id, first_name, last_name, date_of_birth FROM children WHERE guardian_id = ? LIMIT 1");
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

// Get all vaccines from master list
$all_vaccines = $conn->query("SELECT * FROM vaccines ORDER BY vaccine_id ASC");

// Get child's vaccination records
$stmt = $conn->prepare("SELECT vr.*, v.vaccine_name, v.recommended_age 
                        FROM vaccination_records vr 
                        INNER JOIN vaccines v ON vr.vaccine_id = v.vaccine_id 
                        WHERE vr.child_id = ?");
$stmt->bind_param("i", $child_id);
$stmt->execute();
$child_vaccinations = $stmt->get_result();

// Create array of completed vaccines
$completed_vaccines = [];
while ($row = $child_vaccinations->fetch_assoc()) {
    $completed_vaccines[$row['vaccine_id']] = $row;
}
$stmt->close();

// Calculate completion statistics
$total_vaccines = $all_vaccines->num_rows;
$completed_count = count($completed_vaccines);
$pending_count = $total_vaccines - $completed_count;
$completion_percentage = $total_vaccines > 0 ? round(($completed_count / $total_vaccines) * 100) : 0;

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Vaccinations - PA-EHR System</title>
<link rel="stylesheet" href="assets/css/style.css">
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
  <h1>Vaccinations / Immunizations - <?php echo htmlspecialchars($child_name); ?></h1>

  <!-- Vaccination Progress -->
  <div class="card">
    <h3>Vaccination Progress</h3>
    <p><strong><?php echo $completed_count; ?></strong> of <strong><?php echo $total_vaccines; ?></strong> vaccines completed (<?php echo $completion_percentage; ?>%)</p>
    <div class="progress-bar">
      <div class="progress" style="width: <?php echo $completion_percentage; ?>%;"></div>
    </div>
  </div>

  <div class="card">
    <h3>Kenya Child Immunization Schedule</h3>
    <table class="medical-table">
      <thead>
        <tr>
          <th>Vaccine</th>
          <th>Recommended Age</th>
          <th>Date Administered</th>
          <th>Next Due</th>
          <th>Status</th>
        </tr>
      </thead>
      <tbody>
        <?php 
        $all_vaccines->data_seek(0); // Reset pointer
        while ($vaccine = $all_vaccines->fetch_assoc()): 
            $vaccine_id = $vaccine['vaccine_id'];
            $is_completed = isset($completed_vaccines[$vaccine_id]);
        ?>
          <tr>
            <td><?php echo htmlspecialchars($vaccine['vaccine_name']); ?></td>
            <td><?php echo htmlspecialchars($vaccine['recommended_age']); ?></td>
            <td>
              <?php 
              if ($is_completed) {
                  echo date('Y-m-d', strtotime($completed_vaccines[$vaccine_id]['date_administered']));
              } else {
                  echo '<span style="color: #999;">Not yet administered</span>';
              }
              ?>
            </td>
            <td>
              <?php 
              if ($is_completed && isset($completed_vaccines[$vaccine_id]['next_due_date'])) {
                  echo date('Y-m-d', strtotime($completed_vaccines[$vaccine_id]['next_due_date']));
              } else {
                  echo 'N/A';
              }
              ?>
            </td>
            <td>
              <?php if ($is_completed): ?>
                <span style="color: green; font-weight: bold;">✓ Completed</span>
              <?php else: ?>
                <span style="color: orange; font-weight: bold;">⚠ Pending</span>
              <?php endif; ?>
            </td>
          </tr>
        <?php endwhile; ?>
      </tbody>
    </table>
  </div>

  <!-- Additional Notes Section -->
  <?php if ($completed_count > 0): ?>
  <div class="card">
    <h3>Vaccination Notes</h3>
    <table class="medical-table">
      <thead>
        <tr>
          <th>Vaccine</th>
          <th>Date</th>
          <th>Notes</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($completed_vaccines as $vax): ?>
          <?php if (!empty($vax['notes'])): ?>
            <tr>
              <td><?php echo htmlspecialchars($vax['vaccine_name']); ?></td>
              <td><?php echo date('Y-m-d', strtotime($vax['date_administered'])); ?></td>
              <td><?php echo htmlspecialchars($vax['notes']); ?></td>
            </tr>
          <?php endif; ?>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>

</main>
</body>
</html>