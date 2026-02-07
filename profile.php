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
$message = "";
$message_type = "";

// Handle guardian profile update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_guardian'])) {
    $name = $conn->real_escape_string($_POST['guardian_name']);
    $email = $conn->real_escape_string($_POST['guardian_email']);
    $phone = $conn->real_escape_string($_POST['guardian_phone']);
    $new_password = $_POST['guardian_password'];
    
    // Update basic info
    $stmt = $conn->prepare("UPDATE guardians SET name = ?, email = ?, phone = ? WHERE id = ?");
    $stmt->bind_param("sssi", $name, $email, $phone, $guardian_id);
    
    if ($stmt->execute()) {
        $_SESSION['guardian_name'] = $name;
        $_SESSION['guardian_email'] = $email;
        
        // Update password if provided
        if (!empty($new_password)) {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt2 = $conn->prepare("UPDATE guardians SET password = ? WHERE id = ?");
            $stmt2->bind_param("si", $hashed_password, $guardian_id);
            $stmt2->execute();
            $stmt2->close();
            $message = "Profile and password updated successfully!";
        } else {
            $message = "Profile updated successfully!";
        }
        $message_type = "success";
    } else {
        $message = "Error updating profile: " . $stmt->error;
        $message_type = "error";
    }
    $stmt->close();
}

// Handle adding new child
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_child'])) {
    $child_first_name = $conn->real_escape_string($_POST['child_first_name']);
    $child_last_name = $conn->real_escape_string($_POST['child_last_name']);
    $child_dob = $_POST['child_dob'];
    $child_gender = $_POST['child_gender'];
    
    $stmt = $conn->prepare("INSERT INTO children (guardian_id, first_name, last_name, gender, date_of_birth) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("issss", $guardian_id, $child_first_name, $child_last_name, $child_gender, $child_dob);
    
    if ($stmt->execute()) {
        $message = "Child added successfully!";
        $message_type = "success";
    } else {
        $message = "Error adding child: " . $stmt->error;
        $message_type = "error";
    }
    $stmt->close();
}

// Get guardian info
$stmt = $conn->prepare("SELECT name, email, phone FROM guardians WHERE id = ?");
$stmt->bind_param("i", $guardian_id);
$stmt->execute();
$guardian = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Get all children for this guardian
$stmt = $conn->prepare("SELECT * FROM children WHERE guardian_id = ? ORDER BY date_of_birth ASC");
$stmt->bind_param("i", $guardian_id);
$stmt->execute();
$children = $stmt->get_result();
$stmt->close();

// Get selected child (default to first child)
$selected_child_id = isset($_GET['child_id']) ? $_GET['child_id'] : null;

if ($children->num_rows > 0) {
    $children->data_seek(0);
    $first_child = $children->fetch_assoc();
    
    if (!$selected_child_id) {
        $selected_child_id = $first_child['child_id'];
    }
    
    // Get selected child's details
    $stmt = $conn->prepare("SELECT * FROM children WHERE child_id = ? AND guardian_id = ?");
    $stmt->bind_param("ii", $selected_child_id, $guardian_id);
    $stmt->execute();
    $selected_child = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    // Calculate age
    if ($selected_child) {
        $dob = new DateTime($selected_child['date_of_birth']);
        $now = new DateTime();
        $age = $now->diff($dob)->y;
        
        // Get last appointment
        $stmt = $conn->prepare("SELECT appointment_date FROM appointments WHERE child_id = ? AND appointment_date < CURDATE() ORDER BY appointment_date DESC LIMIT 1");
        $stmt->bind_param("i", $selected_child_id);
        $stmt->execute();
        $last_apt_result = $stmt->get_result();
        $last_appointment = $last_apt_result->num_rows > 0 ? $last_apt_result->fetch_assoc()['appointment_date'] : 'None';
        $stmt->close();
        
        // Get next appointment
        $stmt = $conn->prepare("SELECT appointment_date FROM appointments WHERE child_id = ? AND appointment_date >= CURDATE() ORDER BY appointment_date ASC LIMIT 1");
        $stmt->bind_param("i", $selected_child_id);
        $stmt->execute();
        $next_apt_result = $stmt->get_result();
        $next_appointment = $next_apt_result->num_rows > 0 ? $next_apt_result->fetch_assoc()['appointment_date'] : 'None scheduled';
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
<title>Profile - PA-EHR System</title>
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
.child-selection {
    margin-bottom: 20px;
    padding: 15px;
    background-color: #f8f9fa;
    border-radius: 5px;
    display: flex;
    align-items: center;
    gap: 15px;
}
.child-selection select {
    padding: 8px;
    border-radius: 4px;
    border: 1px solid #ddd;
    flex: 1;
    max-width: 300px;
}
.profile-sections {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 30px;
}
@media (max-width: 768px) {
    .profile-sections {
        grid-template-columns: 1fr;
    }
}
.add-child-section {
    margin-top: 20px;
}
.add-child-btn {
    background-color: #28a745;
    color: white;
    padding: 10px 20px;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    font-size: 1em;
}
.add-child-btn:hover {
    background-color: #218838;
}
#add-child-form {
    display: none;
    margin-top: 15px;
    padding: 20px;
    background-color: #f0f9ff;
    border-radius: 5px;
    border-left: 4px solid #007bff;
}
</style>
<script>
function toggleAddChildForm() {
    const form = document.getElementById('add-child-form');
    const btn = document.getElementById('add-child-btn');
    if (form.style.display === 'none' || form.style.display === '') {
        form.style.display = 'block';
        btn.textContent = '− Cancel';
        btn.style.backgroundColor = '#dc3545';
    } else {
        form.style.display = 'none';
        btn.textContent = '+ Add New Child';
        btn.style.backgroundColor = '#28a745';
    }
}

function changeChild() {
    const childId = document.getElementById('child-select').value;
    window.location.href = 'profile.php?child_id=' + childId;
}
</script>
</head>
<body class="dashboard-body">

<aside class="sidebar" id="sidebar">
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
  <h1>Guardian Profile</h1>

  <?php if ($message): ?>
    <div class="message <?php echo $message_type; ?>">
      <?php echo htmlspecialchars($message); ?>
    </div>
  <?php endif; ?>

  <!-- Child Selection -->
  <div class="child-selection">
    <?php if ($children->num_rows > 0): ?>
      <label for="child-select"><strong>Select Child:</strong></label>
      <select id="child-select" onchange="changeChild()">
        <?php 
        $children->data_seek(0);
        while ($child = $children->fetch_assoc()): 
        ?>
          <option value="<?php echo $child['child_id']; ?>" <?php echo $child['child_id'] == $selected_child_id ? 'selected' : ''; ?>>
            <?php echo htmlspecialchars($child['first_name'] . ' ' . $child['last_name']); ?>
          </option>
        <?php endwhile; ?>
      </select>
    <?php else: ?>
      <span><strong>No children registered yet.</strong></span>
    <?php endif; ?>
    
    <button id="add-child-btn" class="add-child-btn" onclick="toggleAddChildForm()">+ Add New Child</button>
  </div>

  <!-- Add New Child Form (Hidden by default) -->
  <div id="add-child-form">
    <h3>Add New Child</h3>
    <form method="POST" action="">
      <label for="child_first_name">First Name:</label>
      <input type="text" id="child_first_name" name="child_first_name" required>

      <label for="child_last_name">Last Name:</label>
      <input type="text" id="child_last_name" name="child_last_name" required>

      <label for="child_dob">Date of Birth:</label>
      <input type="date" id="child_dob" name="child_dob" required>

      <label for="child_gender">Gender:</label>
      <select id="child_gender" name="child_gender" required>
        <option value="">--Select--</option>
        <option value="Male">Male</option>
        <option value="Female">Female</option>
        <option value="Other">Other</option>
      </select>

      <button type="submit" name="add_child">Add Child</button>
    </form>
  </div>

  <!-- Profile Card -->
  <div class="card profile-card">
    <div class="profile-sections">

      <!-- Guardian Details -->
      <div class="profile-half">
        <h3>Guardian Details</h3>
        <form method="POST" action="">
          <label for="guardian-name">Full Name:</label>
          <input type="text" id="guardian-name" name="guardian_name" value="<?php echo htmlspecialchars($guardian['name']); ?>" required>

          <label for="guardian-email">Email:</label>
          <input type="email" id="guardian-email" name="guardian_email" value="<?php echo htmlspecialchars($guardian['email']); ?>" required>

          <label for="guardian-phone">Phone:</label>
          <input type="tel" id="guardian-phone" name="guardian_phone" value="<?php echo htmlspecialchars($guardian['phone']); ?>" required>

          <label for="guardian-password">Change Password (leave blank to keep current):</label>
          <input type="password" id="guardian-password" name="guardian_password" placeholder="New Password">

          <button type="submit" name="update_guardian">Save Changes</button>
        </form>
      </div>

      <!-- Child Overview -->
      <div class="profile-half">
        <h3>Child Overview</h3>
        <?php if (isset($selected_child)): ?>
          <p><strong>Name:</strong> <?php echo htmlspecialchars($selected_child['first_name'] . ' ' . $selected_child['last_name']); ?></p>
          <p><strong>Age:</strong> <?php echo $age; ?> years</p>
          <p><strong>Gender:</strong> <?php echo htmlspecialchars($selected_child['gender']); ?></p>
          <p><strong>Date of Birth:</strong> <?php echo date('F d, Y', strtotime($selected_child['date_of_birth'])); ?></p>
          <p><strong>Last Appointment:</strong> <?php echo $last_appointment != 'None' ? date('F d, Y', strtotime($last_appointment)) : 'None'; ?></p>
          <p><strong>Next Appointment:</strong> <?php echo $next_appointment != 'None scheduled' ? date('F d, Y', strtotime($next_appointment)) : 'None scheduled'; ?></p>
          <button onclick="window.location.href='child_dashboard.php'">Go to Child Dashboard</button>
        <?php else: ?>
          <p>No children registered yet. Click "Add New Child" above to get started.</p>
        <?php endif; ?>
      </div>

    </div>
  </div>

</main>
</body>
</html>