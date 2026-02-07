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

// Handle Add Doctor
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_doctor'])) {
    $full_name = $conn->real_escape_string($_POST['full_name']);
    $email = $conn->real_escape_string($_POST['email']);
    $phone = $conn->real_escape_string($_POST['phone']);
    $specialization = $conn->real_escape_string($_POST['specialization']);
    $license_number = $conn->real_escape_string($_POST['license_number']);
    $qualification = $conn->real_escape_string($_POST['qualification']);
    $years_of_experience = (int)$_POST['years_of_experience'];
    $department = $conn->real_escape_string($_POST['department']);
    $employment_type = $_POST['employment_type'];
    $status = $_POST['status'];
    $password = $_POST['password'];
    
    // Check if email already exists
    $check = $conn->prepare("SELECT doctor_id FROM doctors WHERE email = ?");
    $check->bind_param("s", $email);
    $check->execute();
    $check->store_result();
    
    if ($check->num_rows > 0) {
        $message = "Email already exists!";
        $message_type = "error";
    } else {
        // Check if license number already exists
        $check2 = $conn->prepare("SELECT doctor_id FROM doctors WHERE license_number = ?");
        $check2->bind_param("s", $license_number);
        $check2->execute();
        $check2->store_result();
        
        if ($check2->num_rows > 0) {
            $message = "License number already exists!";
            $message_type = "error";
        } else {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            $stmt = $conn->prepare("INSERT INTO doctors (full_name, email, phone, specialization, license_number, qualification, years_of_experience, department, employment_type, status, password) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssssissss", $full_name, $email, $phone, $specialization, $license_number, $qualification, $years_of_experience, $department, $employment_type, $status, $hashed_password);
            
            if ($stmt->execute()) {
                $message = "Doctor added successfully!";
                $message_type = "success";
            } else {
                $message = "Error adding doctor: " . $stmt->error;
                $message_type = "error";
            }
            $stmt->close();
        }
        $check2->close();
    }
    $check->close();
}

// Handle Edit Doctor
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['edit_doctor'])) {
    $doctor_id = $_POST['doctor_id'];
    $full_name = $conn->real_escape_string($_POST['full_name']);
    $email = $conn->real_escape_string($_POST['email']);
    $phone = $conn->real_escape_string($_POST['phone']);
    $specialization = $conn->real_escape_string($_POST['specialization']);
    $license_number = $conn->real_escape_string($_POST['license_number']);
    $qualification = $conn->real_escape_string($_POST['qualification']);
    $years_of_experience = (int)$_POST['years_of_experience'];
    $department = $conn->real_escape_string($_POST['department']);
    $employment_type = $_POST['employment_type'];
    $status = $_POST['status'];
    $password = $_POST['password'];
    
    if (!empty($password)) {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE doctors SET full_name = ?, email = ?, phone = ?, specialization = ?, license_number = ?, qualification = ?, years_of_experience = ?, department = ?, employment_type = ?, status = ?, password = ? WHERE doctor_id = ?");
        $stmt->bind_param("ssssssissssi", $full_name, $email, $phone, $specialization, $license_number, $qualification, $years_of_experience, $department, $employment_type, $status, $hashed_password, $doctor_id);
    } else {
        $stmt = $conn->prepare("UPDATE doctors SET full_name = ?, email = ?, phone = ?, specialization = ?, license_number = ?, qualification = ?, years_of_experience = ?, department = ?, employment_type = ?, status = ? WHERE doctor_id = ?");
        $stmt->bind_param("ssssssisssi", $full_name, $email, $phone, $specialization, $license_number, $qualification, $years_of_experience, $department, $employment_type, $status, $doctor_id);
    }
    
    if ($stmt->execute()) {
        $message = "Doctor updated successfully!";
        $message_type = "success";
    } else {
        $message = "Error updating doctor: " . $stmt->error;
        $message_type = "error";
    }
    $stmt->close();
}

// Handle Delete Doctor
if (isset($_GET['delete'])) {
    $doctor_id = $_GET['delete'];
    
    // Check if doctor has appointments
    $check = $conn->prepare("SELECT COUNT(*) as count FROM appointments WHERE doctor_id = ?");
    $check->bind_param("i", $doctor_id);
    $check->execute();
    $result = $check->get_result();
    $count = $result->fetch_assoc()['count'];
    $check->close();
    
    if ($count > 0) {
        $message = "Cannot delete doctor with existing appointments! Consider deactivating instead.";
        $message_type = "error";
    } else {
        $stmt = $conn->prepare("DELETE FROM doctors WHERE doctor_id = ?");
        $stmt->bind_param("i", $doctor_id);
        
        if ($stmt->execute()) {
            $message = "Doctor deleted successfully!";
            $message_type = "success";
        } else {
            $message = "Error deleting doctor: " . $stmt->error;
            $message_type = "error";
        }
        $stmt->close();
    }
}

// Get all doctors
$doctors = $conn->query("SELECT * FROM doctors ORDER BY full_name ASC");

// Get doctor for editing if edit ID is set
$edit_doctor = null;
if (isset($_GET['edit'])) {
    $edit_id = $_GET['edit'];
    $stmt = $conn->prepare("SELECT * FROM doctors WHERE doctor_id = ?");
    $stmt->bind_param("i", $edit_id);
    $stmt->execute();
    $edit_doctor = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Manage Doctors - PediaLink</title>
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
.doctors-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 20px;
}
.doctors-table th,
.doctors-table td {
    padding: 12px;
    text-align: left;
    border-bottom: 1px solid #ddd;
}
.doctors-table th {
    background-color: #f8f9fa;
    font-weight: bold;
}
.doctors-table tr:hover {
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
.two-column {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 15px;
}
.three-column {
    display: grid;
    grid-template-columns: 1fr 1fr 1fr;
    gap: 15px;
}
@media (max-width: 968px) {
    .two-column, .three-column {
        grid-template-columns: 1fr;
    }
}
.status-badge {
    padding: 4px 8px;
    border-radius: 3px;
    font-size: 0.85em;
    font-weight: bold;
}
.status-active {
    background-color: #d4edda;
    color: #155724;
}
.status-inactive {
    background-color: #f8d7da;
    color: #721c24;
}
.status-on-leave {
    background-color: #fff3cd;
    color: #856404;
}
.form-group-header {
    background-color: #e9ecef;
    padding: 10px 15px;
    margin: 20px -25px 15px -25px;
    font-weight: bold;
    border-left: 4px solid #007bff;
}
</style>
<script>
function confirmDelete(doctorName) {
    return confirm('Are you sure you want to delete Dr. ' + doctorName + '?\n\nThis action cannot be undone.');
}
</script>
</head>
<body class="dashboard-body">

<aside class="sidebar">
  <h2>Admin Panel</h2>
  <nav>
    <ul>
      <li><a href="admin_dashboard.php">Dashboard</a></li>
      <li><a href="manage_doctors.php" class="active">Manage Doctors</a></li>
      <li><a href="manage_guardians.php">Manage Guardians</a></li>
      <li><a href="manage_children.php">Manage Children</a></li>
      <li><a href="manage_appointments.php">Manage Appointments</a></li>
      <li><a href="manage_vaccines.php">Manage Vaccines</a></li>
      <li><a href="admin_reports.php">Reports</a></li>
      <li><a href="logout.php">Logout</a></li>
    </ul>
  </nav>
</aside>

<main class="dashboard-main">
  <h1>Manage Doctors</h1>

  <?php if ($message): ?>
    <div class="message <?php echo $message_type; ?>">
      <?php echo htmlspecialchars($message); ?>
    </div>
  <?php endif; ?>

  <!-- Add/Edit Doctor Form -->
  <div class="card">
    <h3><?php echo $edit_doctor ? '✏️ Edit Doctor' : '➕ Add New Doctor'; ?></h3>
    <div class="form-section">
      <form method="POST" action="">
        <?php if ($edit_doctor): ?>
          <input type="hidden" name="doctor_id" value="<?php echo $edit_doctor['doctor_id']; ?>">
        <?php endif; ?>

        <div class="form-group-header">Personal Information</div>
        
        <div class="two-column">
          <div>
            <label for="full_name">Full Name: *</label>
            <input type="text" id="full_name" name="full_name" 
                   value="<?php echo $edit_doctor ? htmlspecialchars($edit_doctor['full_name']) : ''; ?>" 
                   placeholder="Dr. John Kamau" required>
          </div>

          <div>
            <label for="email">Email Address: *</label>
            <input type="email" id="email" name="email" 
                   value="<?php echo $edit_doctor ? htmlspecialchars($edit_doctor['email']) : ''; ?>" 
                   placeholder="doctor@pedialink.com" required>
          </div>
        </div>

        <div class="two-column">
          <div>
            <label for="phone">Phone Number: *</label>
            <input type="tel" id="phone" name="phone" 
                   value="<?php echo $edit_doctor ? htmlspecialchars($edit_doctor['phone']) : ''; ?>" 
                   placeholder="0712345678" required>
          </div>

          <div>
            <label for="license_number">Medical License Number: *</label>
            <input type="text" id="license_number" name="license_number" 
                   value="<?php echo $edit_doctor ? htmlspecialchars($edit_doctor['license_number']) : ''; ?>" 
                   placeholder="e.g., MP-12345-KE" required>
          </div>
        </div>

        <div class="form-group-header">Professional Details</div>

        <div class="three-column">
          <div>
            <label for="specialization">Specialization: *</label>
            <select id="specialization" name="specialization" required>
              <option value="">--Select--</option>
              <option value="General Pediatrics" <?php echo ($edit_doctor && $edit_doctor['specialization'] == 'General Pediatrics') ? 'selected' : ''; ?>>General Pediatrics</option>
              <option value="Neonatology" <?php echo ($edit_doctor && $edit_doctor['specialization'] == 'Neonatology') ? 'selected' : ''; ?>>Neonatology</option>
              <option value="Pediatric Cardiology" <?php echo ($edit_doctor && $edit_doctor['specialization'] == 'Pediatric Cardiology') ? 'selected' : ''; ?>>Pediatric Cardiology</option>
              <option value="Pediatric Neurology" <?php echo ($edit_doctor && $edit_doctor['specialization'] == 'Pediatric Neurology') ? 'selected' : ''; ?>>Pediatric Neurology</option>
              <option value="Pediatric Surgery" <?php echo ($edit_doctor && $edit_doctor['specialization'] == 'Pediatric Surgery') ? 'selected' : ''; ?>>Pediatric Surgery</option>
              <option value="Child Psychology" <?php echo ($edit_doctor && $edit_doctor['specialization'] == 'Child Psychology') ? 'selected' : ''; ?>>Child Psychology</option>
              <option value="General Practitioner" <?php echo ($edit_doctor && $edit_doctor['specialization'] == 'General Practitioner') ? 'selected' : ''; ?>>General Practitioner</option>
              <option value="Other" <?php echo ($edit_doctor && $edit_doctor['specialization'] == 'Other') ? 'selected' : ''; ?>>Other</option>
            </select>
          </div>

          <div>
            <label for="qualification">Highest Qualification: *</label>
            <input type="text" id="qualification" name="qualification" 
                   value="<?php echo $edit_doctor ? htmlspecialchars($edit_doctor['qualification']) : ''; ?>" 
                   placeholder="e.g., MD, MBBS, PhD" required>
          </div>

          <div>
            <label for="years_of_experience">Years of Experience: *</label>
            <input type="number" id="years_of_experience" name="years_of_experience" 
                   value="<?php echo $edit_doctor ? $edit_doctor['years_of_experience'] : ''; ?>" 
                   min="0" max="50" required>
          </div>
        </div>

        <div class="form-group-header">Employment Details</div>

        <div class="three-column">
          <div>
            <label for="department">Department: *</label>
            <select id="department" name="department" required>
              <option value="">--Select--</option>
              <option value="Pediatrics" <?php echo ($edit_doctor && $edit_doctor['department'] == 'Pediatrics') ? 'selected' : ''; ?>>Pediatrics</option>
              <option value="Neonatal Unit" <?php echo ($edit_doctor && $edit_doctor['department'] == 'Neonatal Unit') ? 'selected' : ''; ?>>Neonatal Unit</option>
              <option value="Outpatient" <?php echo ($edit_doctor && $edit_doctor['department'] == 'Outpatient') ? 'selected' : ''; ?>>Outpatient</option>
              <option value="Emergency" <?php echo ($edit_doctor && $edit_doctor['department'] == 'Emergency') ? 'selected' : ''; ?>>Emergency</option>
              <option value="Surgery" <?php echo ($edit_doctor && $edit_doctor['department'] == 'Surgery') ? 'selected' : ''; ?>>Surgery</option>
            </select>
          </div>

          <div>
            <label for="employment_type">Employment Type: *</label>
            <select id="employment_type" name="employment_type" required>
              <option value="">--Select--</option>
              <option value="Full-Time" <?php echo ($edit_doctor && $edit_doctor['employment_type'] == 'Full-Time') ? 'selected' : ''; ?>>Full-Time</option>
              <option value="Part-Time" <?php echo ($edit_doctor && $edit_doctor['employment_type'] == 'Part-Time') ? 'selected' : ''; ?>>Part-Time</option>
              <option value="Contract" <?php echo ($edit_doctor && $edit_doctor['employment_type'] == 'Contract') ? 'selected' : ''; ?>>Contract</option>
              <option value="Visiting" <?php echo ($edit_doctor && $edit_doctor['employment_type'] == 'Visiting') ? 'selected' : ''; ?>>Visiting</option>
            </select>
          </div>

          <div>
            <label for="status">Status: *</label>
            <select id="status" name="status" required>
              <option value="Active" <?php echo ($edit_doctor && $edit_doctor['status'] == 'Active') ? 'selected' : ''; ?>>Active</option>
              <option value="Inactive" <?php echo ($edit_doctor && $edit_doctor['status'] == 'Inactive') ? 'selected' : ''; ?>>Inactive</option>
              <option value="On Leave" <?php echo ($edit_doctor && $edit_doctor['status'] == 'On Leave') ? 'selected' : ''; ?>>On Leave</option>
            </select>
          </div>
        </div>

        <div class="two-column">
          <div>
            <label for="password">Password <?php echo $edit_doctor ? '(leave blank to keep current)' : ''; ?>: *</label>
            <input type="password" id="password" name="password" 
                   placeholder="<?php echo $edit_doctor ? 'Leave blank to keep current password' : 'Enter password'; ?>" 
                   <?php echo $edit_doctor ? '' : 'required'; ?>>
          </div>
        </div>

        <div style="margin-top: 20px;">
          <button type="submit" name="<?php echo $edit_doctor ? 'edit_doctor' : 'add_doctor'; ?>">
            <?php echo $edit_doctor ? '💾 Update Doctor' : '➕ Add Doctor'; ?>
          </button>
          
          <?php if ($edit_doctor): ?>
            <a href="manage_doctors.php" class="action-btn btn-cancel">❌ Cancel Edit</a>
          <?php endif; ?>
        </div>
      </form>
    </div>
  </div>

  <!-- Doctors List -->
  <div class="card">
    <h3>📋 All Registered Doctors (<?php echo $doctors->num_rows; ?>)</h3>
    
    <?php if ($doctors->num_rows > 0): ?>
      <div style="overflow-x: auto;">
        <table class="doctors-table">
          <thead>
            <tr>
              <th>ID</th>
              <th>Full Name</th>
              <th>License No.</th>
              <th>Specialization</th>
              <th>Department</th>
              <th>Experience</th>
              <th>Status</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php while ($doctor = $doctors->fetch_assoc()): ?>
              <tr>
                <td><?php echo $doctor['doctor_id']; ?></td>
                <td>
                  <strong>Dr. <?php echo htmlspecialchars($doctor['full_name']); ?></strong><br>
                  <small style="color: #666;"><?php echo htmlspecialchars($doctor['email']); ?></small><br>
                  <small style="color: #666;"><?php echo htmlspecialchars($doctor['phone']); ?></small>
                </td>
                <td><strong><?php echo htmlspecialchars($doctor['license_number']); ?></strong><br>
                    <small><?php echo htmlspecialchars($doctor['qualification']); ?></small>
                </td>
                <td><?php echo htmlspecialchars($doctor['specialization']); ?></td>
                <td><?php echo htmlspecialchars($doctor['department']); ?><br>
                    <small style="color: #666;"><?php echo htmlspecialchars($doctor['employment_type']); ?></small>
                </td>
                <td><?php echo $doctor['years_of_experience']; ?> years</td>
                <td>
                  <span class="status-badge status-<?php echo strtolower(str_replace(' ', '-', $doctor['status'])); ?>">
                    <?php echo htmlspecialchars($doctor['status']); ?>
                  </span>
                </td>
                <td>
                  <a href="manage_doctors.php?edit=<?php echo $doctor['doctor_id']; ?>" class="action-btn btn-edit">✏️ Edit</a>
                  <a href="manage_doctors.php?delete=<?php echo $doctor['doctor_id']; ?>" 
                     class="action-btn btn-delete" 
                     onclick="return confirmDelete('<?php echo htmlspecialchars($doctor['full_name']); ?>')">🗑️ Delete</a>
                </td>
              </tr>
            <?php endwhile; ?>
          </tbody>
        </table>
      </div>
    <?php else: ?>
      <p style="text-align: center; padding: 40px; color: #666;">No doctors registered yet. Add one above!</p>
    <?php endif; ?>
  </div>

</main>
</body>
</html>