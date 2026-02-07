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

// Handle Delete Child
if (isset($_GET['delete'])) {
    $child_id = $_GET['delete'];
    
    // Check if child has appointments
    $check = $conn->prepare("SELECT COUNT(*) as count FROM appointments WHERE child_id = ?");
    $check->bind_param("i", $child_id);
    $check->execute();
    $result = $check->get_result();
    $count = $result->fetch_assoc()['count'];
    $check->close();
    
    if ($count > 0) {
        $message = "Cannot delete child with existing appointments!";
        $message_type = "error";
    } else {
        $stmt = $conn->prepare("DELETE FROM children WHERE child_id = ?");
        $stmt->bind_param("i", $child_id);
        
        if ($stmt->execute()) {
            $message = "Child record deleted successfully!";
            $message_type = "success";
        } else {
            $message = "Error deleting child: " . $stmt->error;
            $message_type = "error";
        }
        $stmt->close();
    }
}

// Get all children with guardian info
$children = $conn->query("
    SELECT c.*, g.name as guardian_name, g.phone as guardian_phone, g.email as guardian_email
    FROM children c
    LEFT JOIN guardians g ON c.guardian_id = g.id
    ORDER BY c.first_name ASC
");

// Get statistics
$total_children = $conn->query("SELECT COUNT(*) as count FROM children")->fetch_assoc()['count'];
$male_children = $conn->query("SELECT COUNT(*) as count FROM children WHERE gender = 'Male'")->fetch_assoc()['count'];
$female_children = $conn->query("SELECT COUNT(*) as count FROM children WHERE gender = 'Female'")->fetch_assoc()['count'];

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Manage Children - PediaLink</title>
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
.children-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 20px;
}
.children-table th,
.children-table td {
    padding: 12px;
    text-align: left;
    border-bottom: 1px solid #ddd;
}
.children-table th {
    background-color: #f8f9fa;
    font-weight: bold;
}
.children-table tr:hover {
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
    font-size: 0.9em;
}
.btn-view {
    background-color: #007bff;
    color: white;
}
.btn-view:hover {
    background-color: #0056b3;
}
.btn-delete {
    background-color: #dc3545;
    color: white;
}
.btn-delete:hover {
    background-color: #c82333;
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
function confirmDelete(childName) {
    return confirm('Are you sure you want to delete ' + childName + '?\n\nThis action cannot be undone.');
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
      <li><a href="manage_children.php" class="active">Manage Children</a></li>
      <li><a href="manage_appointments.php">Manage Appointments</a></li>
      <li><a href="manage_vaccines.php">Manage Vaccines</a></li>
      <li><a href="admin_reports.php">Reports</a></li>
      <li><a href="logout.php">Logout</a></li>
    </ul>
  </nav>
</aside>

<main class="dashboard-main">
  <h1>Manage Children</h1>

  <?php if ($message): ?>
    <div class="message <?php echo $message_type; ?>">
      <?php echo htmlspecialchars($message); ?>
    </div>
  <?php endif; ?>

  <!-- Statistics -->
  <div class="stats-grid">
    <div class="stat-box">
      <div class="stat-label">Total Children</div>
      <div class="stat-number"><?php echo $total_children; ?></div>
    </div>
    <div class="stat-box">
      <div class="stat-label">Male</div>
      <div class="stat-number"><?php echo $male_children; ?></div>
    </div>
    <div class="stat-box">
      <div class="stat-label">Female</div>
      <div class="stat-number"><?php echo $female_children; ?></div>
    </div>
  </div>

  <!-- Children List -->
  <div class="card">
    <h3>📋 All Registered Children (<?php echo $children->num_rows; ?>)</h3>
    
    <?php if ($children->num_rows > 0): ?>
      <div style="overflow-x: auto;">
        <table class="children-table">
          <thead>
            <tr>
              <th>ID</th>
              <th>Child Name</th>
              <th>Date of Birth</th>
              <th>Age</th>
              <th>Gender</th>
              <th>Guardian</th>
              <th>Guardian Contact</th>
              <th>Registered</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php while ($child = $children->fetch_assoc()): 
                $dob = new DateTime($child['date_of_birth']);
                $now = new DateTime();
                $age = $now->diff($dob)->y;
            ?>
              <tr>
                <td><?php echo $child['child_id']; ?></td>
                <td><strong><?php echo htmlspecialchars($child['first_name'] . ' ' . $child['last_name']); ?></strong></td>
                <td><?php echo date('M d, Y', strtotime($child['date_of_birth'])); ?></td>
                <td><?php echo $age; ?> years</td>
                <td><?php echo htmlspecialchars($child['gender']); ?></td>
                <td><?php echo htmlspecialchars($child['guardian_name']); ?></td>
                <td>
                  <?php echo htmlspecialchars($child['guardian_phone']); ?><br>
                  <small style="color: #666;"><?php echo htmlspecialchars($child['guardian_email']); ?></small>
                </td>
                <td><?php echo date('M d, Y', strtotime($child['created_at'])); ?></td>
                <td>
                  <a href="view_child.php?id=<?php echo $child['child_id']; ?>" class="action-btn btn-view">👁️ View</a>
                  <a href="manage_children.php?delete=<?php echo $child['child_id']; ?>" 
                     class="action-btn btn-delete" 
                     onclick="return confirmDelete('<?php echo htmlspecialchars($child['first_name'] . ' ' . $child['last_name']); ?>')">🗑️ Delete</a>
                </td>
              </tr>
            <?php endwhile; ?>
          </tbody>
        </table>
      </div>
    <?php else: ?>
      <p style="text-align: center; padding: 40px; color: #666;">No children registered yet.</p>
    <?php endif; ?>
  </div>

</main>
</body>
</html>