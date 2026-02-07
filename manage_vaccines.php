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

// Handle Add Vaccine
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_vaccine'])) {
    $vaccine_name = $conn->real_escape_string($_POST['vaccine_name']);
    $recommended_age = $conn->real_escape_string($_POST['recommended_age']);
    $description = $conn->real_escape_string($_POST['description']);
    
    $stmt = $conn->prepare("INSERT INTO vaccines (vaccine_name, recommended_age, description) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $vaccine_name, $recommended_age, $description);
    
    if ($stmt->execute()) {
        $message = "Vaccine added successfully!";
        $message_type = "success";
    } else {
        $message = "Error adding vaccine: " . $stmt->error;
        $message_type = "error";
    }
    $stmt->close();
}

// Handle Edit Vaccine
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['edit_vaccine'])) {
    $vaccine_id = $_POST['vaccine_id'];
    $vaccine_name = $conn->real_escape_string($_POST['vaccine_name']);
    $recommended_age = $conn->real_escape_string($_POST['recommended_age']);
    $description = $conn->real_escape_string($_POST['description']);
    
    $stmt = $conn->prepare("UPDATE vaccines SET vaccine_name = ?, recommended_age = ?, description = ? WHERE vaccine_id = ?");
    $stmt->bind_param("sssi", $vaccine_name, $recommended_age, $description, $vaccine_id);
    
    if ($stmt->execute()) {
        $message = "Vaccine updated successfully!";
        $message_type = "success";
    } else {
        $message = "Error updating vaccine: " . $stmt->error;
        $message_type = "error";
    }
    $stmt->close();
}

// Handle Delete Vaccine
if (isset($_GET['delete'])) {
    $vaccine_id = $_GET['delete'];
    
    // Check if vaccine has records
    $check = $conn->prepare("SELECT COUNT(*) as count FROM vaccination_records WHERE vaccine_id = ?");
    $check->bind_param("i", $vaccine_id);
    $check->execute();
    $result = $check->get_result();
    $count = $result->fetch_assoc()['count'];
    $check->close();
    
    if ($count > 0) {
        $message = "Cannot delete vaccine with existing vaccination records!";
        $message_type = "error";
    } else {
        $stmt = $conn->prepare("DELETE FROM vaccines WHERE vaccine_id = ?");
        $stmt->bind_param("i", $vaccine_id);
        
        if ($stmt->execute()) {
            $message = "Vaccine deleted successfully!";
            $message_type = "success";
        } else {
            $message = "Error deleting vaccine: " . $stmt->error;
            $message_type = "error";
        }
        $stmt->close();
    }
}

// Get all vaccines
$vaccines = $conn->query("SELECT * FROM vaccines ORDER BY vaccine_id ASC");

// Get vaccine for editing
$edit_vaccine = null;
if (isset($_GET['edit'])) {
    $edit_id = $_GET['edit'];
    $stmt = $conn->prepare("SELECT * FROM vaccines WHERE vaccine_id = ?");
    $stmt->bind_param("i", $edit_id);
    $stmt->execute();
    $edit_vaccine = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

// Get statistics
$total_vaccines = $conn->query("SELECT COUNT(*) as count FROM vaccines")->fetch_assoc()['count'];
$total_administered = $conn->query("SELECT COUNT(*) as count FROM vaccination_records WHERE status = 'Completed'")->fetch_assoc()['count'];
$pending_vaccinations = $conn->query("SELECT COUNT(*) as count FROM vaccination_records WHERE status = 'Pending'")->fetch_assoc()['count'];

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Manage Vaccines - PediaLink</title>
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
.form-section {
    background-color: #f8f9fa;
    padding: 25px;
    border-radius: 8px;
    margin-bottom: 30px;
}
.vaccines-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 20px;
}
.vaccines-table th,
.vaccines-table td {
    padding: 12px;
    text-align: left;
    border-bottom: 1px solid #ddd;
}
.vaccines-table th {
    background-color: #f8f9fa;
    font-weight: bold;
}
.vaccines-table tr:hover {
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
.btn-edit {
    background-color: #007bff;
    color: white;
}
.btn-edit:hover {
    background-color: #0056b3;
}
.btn-delete {
    background-color: #dc3545;
    color: white;
}
.btn-delete:hover {
    background-color: #c82333;
}
.btn-cancel {
    background-color: #6c757d;
    color: white;
}
.btn-cancel:hover {
    background-color: #5a6268;
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
function confirmDelete(vaccineName) {
    return confirm('Are you sure you want to delete ' + vaccineName + '?\n\nThis action cannot be undone.');
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
      <li><a href="manage_appointments.php">Manage Appointments</a></li>
      <li><a href="manage_vaccines.php" class="active">Manage Vaccines</a></li>
      <li><a href="admin_reports.php">Reports</a></li>
      <li><a href="logout.php">Logout</a></li>
    </ul>
  </nav>
</aside>

<main class="dashboard-main">
  <h1>Manage Vaccines</h1>

  <?php if ($message): ?>
    <div class="message <?php echo $message_type; ?>">
      <?php echo htmlspecialchars($message); ?>
    </div>
  <?php endif; ?>

  <!-- Statistics -->
  <div class="stats-grid">
    <div class="stat-box">
      <div class="stat-label">Total Vaccines</div>
      <div class="stat-number"><?php echo $total_vaccines; ?></div>
    </div>
    <div class="stat-box">
      <div class="stat-label">Administered</div>
      <div class="stat-number"><?php echo $total_administered; ?></div>
    </div>
    <div class="stat-box">
      <div class="stat-label">Pending</div>
      <div class="stat-number"><?php echo $pending_vaccinations; ?></div>
    </div>
  </div>

  <!-- Add/Edit Vaccine Form -->
  <div class="card">
    <h3><?php echo $edit_vaccine ? '✏️ Edit Vaccine' : '➕ Add New Vaccine'; ?></h3>
    <div class="form-section">
      <form method="POST" action="">
        <?php if ($edit_vaccine): ?>
          <input type="hidden" name="vaccine_id" value="<?php echo $edit_vaccine['vaccine_id']; ?>">
        <?php endif; ?>

        <label for="vaccine_name">Vaccine Name: *</label>
        <input type="text" id="vaccine_name" name="vaccine_name" 
               value="<?php echo $edit_vaccine ? htmlspecialchars($edit_vaccine['vaccine_name']) : ''; ?>" 
               placeholder="e.g., BCG, OPV 1, Measles-Rubella" required>

        <label for="recommended_age">Recommended Age: *</label>
        <input type="text" id="recommended_age" name="recommended_age" 
               value="<?php echo $edit_vaccine ? htmlspecialchars($edit_vaccine['recommended_age']) : ''; ?>" 
               placeholder="e.g., At birth, 6 weeks, 9 months" required>

        <label for="description">Description:</label>
        <textarea id="description" name="description" rows="3" 
                  placeholder="e.g., Protects against tuberculosis"><?php echo $edit_vaccine ? htmlspecialchars($edit_vaccine['description']) : ''; ?></textarea>

        <div style="margin-top: 15px;">
          <button type="submit" name="<?php echo $edit_vaccine ? 'edit_vaccine' : 'add_vaccine'; ?>">
            <?php echo $edit_vaccine ? '💾 Update Vaccine' : '➕ Add Vaccine'; ?>
          </button>
          
          <?php if ($edit_vaccine): ?>
            <a href="manage_vaccines.php" class="action-btn btn-cancel">❌ Cancel Edit</a>
          <?php endif; ?>
        </div>
      </form>
    </div>
  </div>

  <!-- Vaccines List -->
  <div class="card">
    <h3>💉 Kenya Immunization Schedule (<?php echo $vaccines->num_rows; ?>)</h3>
    
    <?php if ($vaccines->num_rows > 0): ?>
      <table class="vaccines-table">
        <thead>
          <tr>
            <th>ID</th>
            <th>Vaccine Name</th>
            <th>Recommended Age</th>
            <th>Description</th>
            <th>Date Added</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php while ($vaccine = $vaccines->fetch_assoc()): ?>
            <tr>
              <td><?php echo $vaccine['vaccine_id']; ?></td>
              <td><strong><?php echo htmlspecialchars($vaccine['vaccine_name']); ?></strong></td>
              <td><?php echo htmlspecialchars($vaccine['recommended_age']); ?></td>
              <td><?php echo htmlspecialchars($vaccine['description']); ?></td>
              <td><?php echo date('M d, Y', strtotime($vaccine['created_at'])); ?></td>
              <td>
                <a href="manage_vaccines.php?edit=<?php echo $vaccine['vaccine_id']; ?>" 
                   class="action-btn btn-edit">✏️ Edit</a>
                <a href="manage_vaccines.php?delete=<?php echo $vaccine['vaccine_id']; ?>" 
                   class="action-btn btn-delete" 
                   onclick="return confirmDelete('<?php echo htmlspecialchars($vaccine['vaccine_name']); ?>')">🗑️ Delete</a>
              </td>
            </tr>
          <?php endwhile; ?>
        </tbody>
      </table>
    <?php else: ?>
      <p style="text-align: center; padding: 40px; color: #666;">
        No vaccines in the schedule yet. Add vaccines to the Kenya Immunization Schedule above!
      </p>
    <?php endif; ?>
  </div>

  <!-- Information Card -->
  <div class="card" style="background-color: #e7f3ff; border-left: 4px solid #2196F3;">
    <h3>ℹ️ About Vaccine Management</h3>
    <p><strong>Purpose:</strong> This page allows you to manage the master vaccine schedule. These vaccines will appear on all children's vaccination records.</p>
    <p><strong>Note:</strong> Doctors will mark individual children's vaccines as completed through their panel. This page only manages which vaccines are in the schedule.</p>
    <p><strong>Tip:</strong> Follow the Kenya Ministry of Health immunization schedule when adding vaccines.</p>
  </div>

</main>
</body>
</html>