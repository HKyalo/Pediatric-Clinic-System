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

// Handle Edit Guardian
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['edit_guardian'])) {
    $guardian_id = $_POST['guardian_id'];
    $name = $conn->real_escape_string($_POST['name']);
    $email = $conn->real_escape_string($_POST['email']);
    $phone = $conn->real_escape_string($_POST['phone']);
    $password = $_POST['password'];
    
    if (!empty($password)) {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE guardians SET name = ?, email = ?, phone = ?, password = ? WHERE id = ?");
        $stmt->bind_param("ssssi", $name, $email, $phone, $hashed_password, $guardian_id);
    } else {
        $stmt = $conn->prepare("UPDATE guardians SET name = ?, email = ?, phone = ? WHERE id = ?");
        $stmt->bind_param("sssi", $name, $email, $phone, $guardian_id);
    }
    
    if ($stmt->execute()) {
        $message = "Guardian updated successfully!";
        $message_type = "success";
    } else {
        $message = "Error updating guardian: " . $stmt->error;
        $message_type = "error";
    }
    $stmt->close();
}

// Handle Delete Guardian
if (isset($_GET['delete'])) {
    $guardian_id = $_GET['delete'];
    
    // Check if guardian has children
    $check = $conn->prepare("SELECT COUNT(*) as count FROM children WHERE guardian_id = ?");
    $check->bind_param("i", $guardian_id);
    $check->execute();
    $result = $check->get_result();
    $count = $result->fetch_assoc()['count'];
    $check->close();
    
    if ($count > 0) {
        $message = "Cannot delete guardian with registered children!";
        $message_type = "error";
    } else {
        $stmt = $conn->prepare("DELETE FROM guardians WHERE id = ?");
        $stmt->bind_param("i", $guardian_id);
        
        if ($stmt->execute()) {
            $message = "Guardian deleted successfully!";
            $message_type = "success";
        } else {
            $message = "Error deleting guardian: " . $stmt->error;
            $message_type = "error";
        }
        $stmt->close();
    }
}

// Get all guardians with child count
$guardians = $conn->query("
    SELECT g.*, COUNT(c.child_id) as child_count
    FROM guardians g
    LEFT JOIN children c ON g.id = c.guardian_id
    GROUP BY g.id
    ORDER BY g.name ASC
");

// Get guardian for editing
$edit_guardian = null;
if (isset($_GET['edit'])) {
    $edit_id = $_GET['edit'];
    $stmt = $conn->prepare("SELECT * FROM guardians WHERE id = ?");
    $stmt->bind_param("i", $edit_id);
    $stmt->execute();
    $edit_guardian = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

// Get statistics
$total_guardians = $conn->query("SELECT COUNT(*) as count FROM guardians")->fetch_assoc()['count'];
$guardians_with_children = $conn->query("SELECT COUNT(DISTINCT guardian_id) as count FROM children")->fetch_assoc()['count'];
$guardians_without_children = $total_guardians - $guardians_with_children;

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Manage Guardians - PediaLink</title>
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
.guardians-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 20px;
}
.guardians-table th,
.guardians-table td {
    padding: 12px;
    text-align: left;
    border-bottom: 1px solid #ddd;
}
.guardians-table th {
    background-color: #f8f9fa;
    font-weight: bold;
}
.guardians-table tr:hover {
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
.btn-view {
    background-color: #17a2b8;
    color: white;
}
.btn-view:hover {
    background-color: #138496;
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
.two-column {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 15px;
}
@media (max-width: 768px) {
    .two-column {
        grid-template-columns: 1fr;
    }
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
function confirmDelete(guardianName) {
    return confirm('Are you sure you want to delete ' + guardianName + '?\n\nThis action cannot be undone.');
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
      <li><a href="manage_guardians.php" class="active">Manage Guardians</a></li>
      <li><a href="manage_children.php">Manage Children</a></li>
      <li><a href="manage_appointments.php">Manage Appointments</a></li>
      <li><a href="manage_vaccines.php">Manage Vaccines</a></li>
      <li><a href="admin_reports.php">Reports</a></li>
      <li><a href="logout.php">Logout</a></li>
    </ul>
  </nav>
</aside>

<main class="dashboard-main">
  <h1>Manage Guardians</h1>

  <?php if ($message): ?>
    <div class="message <?php echo $message_type; ?>">
      <?php echo htmlspecialchars($message); ?>
    </div>
  <?php endif; ?>

  <!-- Statistics -->
  <div class="stats-grid">
    <div class="stat-box">
      <div class="stat-label">Total Guardians</div>
      <div class="stat-number"><?php echo $total_guardians; ?></div>
    </div>
    <div class="stat-box">
      <div class="stat-label">With Children</div>
      <div class="stat-number"><?php echo $guardians_with_children; ?></div>
    </div>
    <div class="stat-box">
      <div class="stat-label">Without Children</div>
      <div class="stat-number"><?php echo $guardians_without_children; ?></div>
    </div>
  </div>

  <!-- Edit Guardian Form (Only shows when editing) -->
  <?php if ($edit_guardian): ?>
  <div class="card">
    <h3>✏️ Edit Guardian</h3>
    <div class="form-section">
      <form method="POST" action="">
        <input type="hidden" name="guardian_id" value="<?php echo $edit_guardian['id']; ?>">

        <div class="two-column">
          <div>
            <label for="name">Full Name: *</label>
            <input type="text" id="name" name="name" 
                   value="<?php echo htmlspecialchars($edit_guardian['name']); ?>" required>
          </div>

          <div>
            <label for="email">Email Address: *</label>
            <input type="email" id="email" name="email" 
                   value="<?php echo htmlspecialchars($edit_guardian['email']); ?>" required>
          </div>
        </div>

        <div class="two-column">
          <div>
            <label for="phone">Phone Number: *</label>
            <input type="tel" id="phone" name="phone" 
                   value="<?php echo htmlspecialchars($edit_guardian['phone']); ?>" required>
          </div>

          <div>
            <label for="password">Password (leave blank to keep current):</label>
            <input type="password" id="password" name="password" 
                   placeholder="Leave blank to keep current password">
          </div>
        </div>

        <div style="margin-top: 15px;">
          <button type="submit" name="edit_guardian">💾 Update Guardian</button>
          <a href="manage_guardians.php" class="action-btn btn-cancel">❌ Cancel Edit</a>
        </div>
      </form>
    </div>
  </div>
  <?php endif; ?>

  <!-- Guardians List -->
  <div class="card">
    <h3>👨‍👩‍👧 All Registered Guardians (<?php echo $guardians->num_rows; ?>)</h3>
    
    <?php if ($guardians->num_rows > 0): ?>
      <div style="overflow-x: auto;">
        <table class="guardians-table">
          <thead>
            <tr>
              <th>ID</th>
              <th>Name</th>
              <th>Email</th>
              <th>Phone</th>
              <th>Children</th>
              <th>Registered</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php while ($guardian = $guardians->fetch_assoc()): ?>
              <tr>
                <td><?php echo $guardian['id']; ?></td>
                <td><strong><?php echo htmlspecialchars($guardian['name']); ?></strong></td>
                <td><?php echo htmlspecialchars($guardian['email']); ?></td>
                <td><?php echo htmlspecialchars($guardian['phone']); ?></td>
                <td>
                  <strong><?php echo $guardian['child_count']; ?></strong> 
                  <?php echo $guardian['child_count'] == 1 ? 'child' : 'children'; ?>
                </td>
                <td><?php echo date('M d, Y', strtotime($guardian['created_at'])); ?></td>
                <td>
                  <a href="view_guardian.php?id=<?php echo $guardian['id']; ?>" 
                     class="action-btn btn-view">👁️ View</a>
                  <a href="manage_guardians.php?edit=<?php echo $guardian['id']; ?>" 
                     class="action-btn btn-edit">✏️ Edit</a>
                  <a href="manage_guardians.php?delete=<?php echo $guardian['id']; ?>" 
                     class="action-btn btn-delete" 
                     onclick="return confirmDelete('<?php echo htmlspecialchars($guardian['name']); ?>')">🗑️ Delete</a>
                </td>
              </tr>
            <?php endwhile; ?>
          </tbody>
        </table>
      </div>
    <?php else: ?>
      <p style="text-align: center; padding: 40px; color: #666;">No guardians registered yet.</p>
    <?php endif; ?>
  </div>

  <!-- Information Card -->
  <div class="card" style="background-color: #fff3cd; border-left: 4px solid #ffc107;">
    <h3>ℹ️ About Guardian Management</h3>
    <p><strong>Note:</strong> Guardians register themselves through the main registration page. As an admin, you can view, edit, or delete guardian accounts here.</p>
    <p><strong>Warning:</strong> You cannot delete a guardian who has registered children. You must delete or reassign the children first.</p>
    <p><strong>Password Reset:</strong> When editing a guardian, you can reset their password by entering a new one. Leave the password field blank to keep their current password.</p>
  </div>

</main>
</body>
</html>