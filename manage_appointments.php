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
$message = "";
$message_type = "";

// Handle Status Update
if (isset($_GET['update_status'])) {
    $appointment_id = $_GET['update_status'];
    $new_status = $_GET['status'];
    
    $stmt = $conn->prepare("UPDATE appointments SET status = ? WHERE appointment_id = ?");
    $stmt->bind_param("si", $new_status, $appointment_id);
    
    if ($stmt->execute()) {
        $message = "Appointment status updated to $new_status!";
        $message_type = "success";
    } else {
        $message = "Error updating status: " . $stmt->error;
        $message_type = "error";
    }
    $stmt->close();
}

// Handle Delete Appointment
if (isset($_GET['delete'])) {
    $appointment_id = $_GET['delete'];
    
    $stmt = $conn->prepare("DELETE FROM appointments WHERE appointment_id = ?");
    $stmt->bind_param("i", $appointment_id);
    
    if ($stmt->execute()) {
        $message = "Appointment deleted successfully!";
        $message_type = "success";
    } else {
        $message = "Error deleting appointment: " . $stmt->error;
        $message_type = "error";
    }
    $stmt->close();
}

// Get all appointments
$appointments = $conn->query("
    SELECT a.*, 
           c.first_name as child_first_name, c.last_name as child_last_name,
           g.name as guardian_name, g.phone as guardian_phone,
           d.full_name as doctor_name, d.specialization
    FROM appointments a
    LEFT JOIN children c ON a.child_id = c.child_id
    LEFT JOIN guardians g ON c.guardian_id = g.id
    LEFT JOIN doctors d ON a.doctor_id = d.doctor_id
    ORDER BY a.appointment_date DESC, a.appointment_time DESC
");

// Get statistics
$total_appointments = $conn->query("SELECT COUNT(*) as count FROM appointments")->fetch_assoc()['count'];
$pending = $conn->query("SELECT COUNT(*) as count FROM appointments WHERE status = 'Pending'")->fetch_assoc()['count'];
$confirmed = $conn->query("SELECT COUNT(*) as count FROM appointments WHERE status = 'Confirmed'")->fetch_assoc()['count'];
$cancelled = $conn->query("SELECT COUNT(*) as count FROM appointments WHERE status = 'Cancelled'")->fetch_assoc()['count'];

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Manage Appointments - PediaLink</title>
<link rel="stylesheet" href="assets/css/style.css">
<style>
.message {
    padding: 15px;
    margin-bottom: 20px;
    border-radius: 5px;
    text-align: center;
}
.message.success {
    background-color: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}
.message.error {
    background-color: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}
.appointments-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 20px;
}
.appointments-table th,
.appointments-table td {
    padding: 12px;
    text-align: left;
    border-bottom: 1px solid #ddd;
}
.appointments-table th {
    background-color: #f8f9fa;
    font-weight: bold;
}
.appointments-table tr:hover {
    background-color: #f5f5f5;
}
.action-btn {
    padding: 6px 12px;
    margin: 0 3px;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    text-decoration: none;
    display: inline-block;
    font-size: 0.85em;
}
.btn-confirm {
    background-color: #28a745;
    color: white;
}
.btn-confirm:hover {
    background-color: #218838;
}
.btn-cancel {
    background-color: #dc3545;
    color: white;
}
.btn-cancel:hover {
    background-color: #c82333;
}
.btn-delete {
    background-color: #6c757d;
    color: white;
}
.btn-delete:hover {
    background-color: #5a6268;
}
.status-badge {
    padding: 4px 8px;
    border-radius: 3px;
    font-size: 0.85em;
    font-weight: bold;
}
.status-pending {
    background-color: #fff3cd;
    color: #856404;
}
.status-confirmed {
    background-color: #d4edda;
    color: #155724;
}
.status-cancelled {
    background-color: #f8d7da;
    color: #721c24;
}
.status-completed {
    background-color: #d1ecf1;
    color: #0c5460;
}
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
    margin-bottom: 20px;
}
.stat-box {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 20px;
    border-radius: 8px;
    text-align: center;
}
.stat-box:nth-child(2) {
    background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
}
.stat-box:nth-child(3) {
    background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
}
.stat-box:nth-child(4) {
    background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);
}
.stat-number {
    font-size: 2.5em;
    font-weight: bold;
    margin: 10px 0;
}
.stat-label {
    font-size: 1em;
    opacity: 0.9;
}
</style>
<script>
function confirmDelete() {
    return confirm('Are you sure you want to delete this appointment?');
}
</script>
</head>
<body class="dashboard-body">

<aside class="sidebar">
  <h2>Admin Panel</h2>
  <nav>
    <ul>
      <li><a href="admin_dashboard.php">Dashboard</a></li>
      <li><a href="manage_doctors.php">Manage Doctors</a></li>
      <li><a href="manage_guardians.php">Manage Guardians</a></li>
      <li><a href="manage_children.php">Manage Children</a></li>
      <li><a href="manage_appointments.php" class="active">Manage Appointments</a></li>
      <li><a href="manage_vaccines.php">Manage Vaccines</a></li>
      <li><a href="admin_reports.php">Reports</a></li>
      <li><a href="logout.php">Logout</a></li>
    </ul>
  </nav>
</aside>

<main class="dashboard-main">
  <h1>Manage Appointments</h1>

  <?php if ($message): ?>
    <div class="message <?php echo $message_type; ?>">
      <?php echo htmlspecialchars($message); ?>
    </div>
  <?php endif; ?>

  <!-- Statistics -->
  <div class="stats-grid">
    <div class="stat-box">
      <div class="stat-label">Total Appointments</div>
      <div class="stat-number"><?php echo $total_appointments; ?></div>
    </div>
    <div class="stat-box">
      <div class="stat-label">Pending</div>
      <div class="stat-number"><?php echo $pending; ?></div>
    </div>
    <div class="stat-box">
      <div class="stat-label">Confirmed</div>
      <div class="stat-number"><?php echo $confirmed; ?></div>
    </div>
    <div class="stat-box">
      <div class="stat-label">Cancelled</div>
      <div class="stat-number"><?php echo $cancelled; ?></div>
    </div>
  </div>

  <!-- Appointments List -->
  <div class="card">
    <h3>📅 All Appointments (<?php echo $appointments->num_rows; ?>)</h3>
    
    <?php if ($appointments->num_rows > 0): ?>
      <div style="overflow-x: auto;">
        <table class="appointments-table">
          <thead>
            <tr>
              <th>ID</th>
              <th>Date & Time</th>
              <th>Child</th>
              <th>Guardian</th>
              <th>Doctor</th>
              <th>Status</th>
              <th>Notes</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php while ($apt = $appointments->fetch_assoc()): ?>
              <tr>
                <td><?php echo $apt['appointment_id']; ?></td>
                <td>
                  <strong><?php echo date('M d, Y', strtotime($apt['appointment_date'])); ?></strong><br>
                  <?php echo date('g:i A', strtotime($apt['appointment_time'])); ?>
                </td>
                <td><?php echo htmlspecialchars($apt['child_first_name'] . ' ' . $apt['child_last_name']); ?></td>
                <td>
                  <?php echo htmlspecialchars($apt['guardian_name']); ?><br>
                  <small style="color: #666;"><?php echo htmlspecialchars($apt['guardian_phone']); ?></small>
                </td>
                <td>
                  Dr. <?php echo htmlspecialchars($apt['doctor_name']); ?><br>
                  <small style="color: #666;"><?php echo htmlspecialchars($apt['specialization']); ?></small>
                </td>
                <td>
                  <span class="status-badge status-<?php echo strtolower($apt['status']); ?>">
                    <?php echo htmlspecialchars($apt['status']); ?>
                  </span>
                </td>
                <td><?php echo htmlspecialchars($apt['notes'] ?? '-'); ?></td>
                <td>
                  <?php if ($apt['status'] == 'Pending'): ?>
                    <a href="?update_status=<?php echo $apt['appointment_id']; ?>&status=Confirmed" 
                       class="action-btn btn-confirm">✓ Confirm</a>
                  <?php endif; ?>
                  
                  <?php if ($apt['status'] != 'Cancelled'): ?>
                    <a href="?update_status=<?php echo $apt['appointment_id']; ?>&status=Cancelled" 
                       class="action-btn btn-cancel">✗ Cancel</a>
                  <?php endif; ?>
                  
                  <a href="?delete=<?php echo $apt['appointment_id']; ?>" 
                     class="action-btn btn-delete" 
                     onclick="return confirmDelete()">🗑️ Delete</a>
                </td>
              </tr>
            <?php endwhile; ?>
          </tbody>
        </table>
      </div>
    <?php else: ?>
      <p style="text-align: center; padding: 40px; color: #666;">No appointments yet.</p>
    <?php endif; ?>
  </div>

</main>
</body>
</html>