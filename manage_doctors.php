<?php
session_start();
require_once __DIR__ . "/config/db.php";


// SECURITY CHECK
if (!isset($_SESSION['admin_id']) || $_SESSION['user_type'] !== 'admin') {
    header("Location: index.php");
    exit();
}

$message = "";
$msg_type = "";

// HANDLE ADD DOCTOR (UPDATED - removed department)
if (isset($_POST['add_doctor'])) {
    $pass = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $doctor_role = $_POST['doctor_role'] ?? 'immunization';
    
    $check = $conn->query("SELECT doctor_id FROM doctors WHERE email = '{$_POST['email']}'");
    if ($check->num_rows > 0) {
        $message = "Email already exists!";
        $msg_type = "error";
    } else {
        // Removed department from the query
        $stmt = $conn->prepare("INSERT INTO doctors (full_name, email, phone, specialization, license_number, qualification, years_of_experience, employment_type, status, doctor_role, password) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssssissss", $_POST['full_name'], $_POST['email'], $_POST['phone'], $_POST['specialization'], $_POST['license_number'], $_POST['qualification'], $_POST['years_of_experience'], $_POST['employment_type'], $_POST['status'], $doctor_role, $pass);
        
        if ($stmt->execute()) {
            $message = "Doctor added successfully!";
            $msg_type = "success";
        } else {
            $message = "Error adding doctor: " . $stmt->error;
            $msg_type = "error";
        }
        $stmt->close();
    }
}

// HANDLE EDIT DOCTOR (UPDATED - removed department)
if (isset($_POST['edit_doctor'])) {
    $doctor_role = $_POST['doctor_role'] ?? 'immunization';
    
    if (!empty($_POST['password'])) {
        $pass = password_hash($_POST['password'], PASSWORD_DEFAULT);
        // Removed department from the query
        $stmt = $conn->prepare("UPDATE doctors SET full_name=?, email=?, phone=?, specialization=?, license_number=?, qualification=?, years_of_experience=?, employment_type=?, status=?, doctor_role=?, password=? WHERE doctor_id=?");
        $stmt->bind_param("ssssssissssi", $_POST['full_name'], $_POST['email'], $_POST['phone'], $_POST['specialization'], $_POST['license_number'], $_POST['qualification'], $_POST['years_of_experience'], $_POST['employment_type'], $_POST['status'], $doctor_role, $pass, $_POST['doctor_id']);
    } else {
        // Removed department from the query
        $stmt = $conn->prepare("UPDATE doctors SET full_name=?, email=?, phone=?, specialization=?, license_number=?, qualification=?, years_of_experience=?, employment_type=?, status=?, doctor_role=? WHERE doctor_id=?");
        $stmt->bind_param("ssssssissi", $_POST['full_name'], $_POST['email'], $_POST['phone'], $_POST['specialization'], $_POST['license_number'], $_POST['qualification'], $_POST['years_of_experience'], $_POST['employment_type'], $_POST['status'], $doctor_role, $_POST['doctor_id']);
    }
    
    if ($stmt->execute()) {
        $message = "Doctor updated successfully!";
        $msg_type = "success";
    } else {
        $message = "Error updating doctor: " . $stmt->error;
        $msg_type = "error";
    }
    $stmt->close();
}

// HANDLE DELETE DOCTOR
if (isset($_GET['delete'])) {
    $check = $conn->query("SELECT COUNT(*) as c FROM appointments WHERE doctor_id = {$_GET['delete']}")->fetch_assoc()['c'];
    if ($check > 0) {
        $message = "Cannot delete - has appointments";
        $msg_type = "error";
    } else {
        $conn->query("DELETE FROM doctors WHERE doctor_id = {$_GET['delete']}");
        $message = "Doctor deleted!";
        $msg_type = "success";
    }
}


// GET DATA FROM DATABASE
$doctors = $conn->query("SELECT * FROM doctors ORDER BY full_name");
$edit_doctor = isset($_GET['edit']) ? $conn->query("SELECT * FROM doctors WHERE doctor_id = {$_GET['edit']}")->fetch_assoc() : null;
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Manage Doctors - PCASS</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        body {
            background: #f0f4fc;
            font-family: Arial, sans-serif;
        }
        .wrapper {
            display: flex;
        }
        .main {
            margin-left: 260px;
            padding: 30px;
            flex: 1;
        }
        h1 {
            color: #0b1a33;
            margin-bottom: 20px;
        }
        .card {
            background: white;
            padding: 20px;
            margin-bottom: 30px;
            border-left: 4px solid #0b1a33;
        }
        .msg {
            padding: 10px;
            margin: 10px 0;
        }
        .msg.success {
            background: #d4edda;
            color: #155724;
        }
        .msg.error {
            background: #f8d7da;
            color: #721c24;
        }
        .grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }
        .grid-3 {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 15px;
        }
        .form-group {
            margin-bottom: 10px;
        }
        .form-group label {
            display: block;
            font-weight: bold;
            color: #1e3a5f;
            margin-bottom: 3px;
        }
        .form-control {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            box-sizing: border-box;
        }
        .btn {
            background: #0b1a33;
            color: white;
            padding: 10px 20px;
            border: none;
            cursor: pointer;
            margin-right: 10px;
        }
        .btn-cancel {
            background: #6c757d;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        th {
            background: #0b1a33;
            color: white;
            padding: 10px;
            text-align: left;
        }
        td {
            padding: 10px;
            border-bottom: 1px solid #ddd;
        }
        .badge {
            padding: 3px 8px;
            font-size: 12px;
            border-radius: 3px;
        }
        .badge.Active {
            background: #d4edda;
            color: #155724;
        }
        .badge.Inactive {
            background: #f8d7da;
            color: #721c24;
        }
        .badge.On-Leave {
            background: #fff3cd;
            color: #856404;
        }
        .role-badge {
            padding: 3px 8px;
            font-size: 11px;
            border-radius: 3px;
            background: #e6f0ff;
            color: #0b1a33;
            margin-left: 5px;
        }
        .role-immunization {
            background: #e6f0ff;
            color: #0b1a33;
        }
        .role-specialist {
            background: #f3e8ff;
            color: #6b21a8;
        }
        .action-btn {
            padding: 5px 10px;
            text-decoration: none;
            color: white;
            margin: 2px;
            display: inline-block;
            border-radius: 3px;
        }
        .btn-edit {
            background: #0b1a33;
        }
        .btn-delete {
            background: #dc3545;
        }
    </style>
</head>
<body>
<div class="wrapper">
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-header">
            <h2>PCASS</h2>
            <p>Admin Portal</p>
        </div>
        <div class="nav">
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
        </div>
    </div>
    
    <!-- Main Content -->
    <div class="main">
        <h1>Manage Doctors</h1>
        
        <?php if ($message): ?>
            <div class="msg <?= $msg_type ?>"><?= $message ?></div>
        <?php endif; ?>

        <!-- Add/Edit Form -->
        <div class="card">
            <h3><?= $edit_doctor ? 'Edit Doctor' : 'Add New Doctor' ?></h3>
            <form method="POST">
                <?php if ($edit_doctor): ?>
                    <input type="hidden" name="doctor_id" value="<?= $edit_doctor['doctor_id'] ?>">
                <?php endif; ?>
                
                <div class="grid-2">
                    <div class="form-group">
                        <label>Full Name *</label>
                        <input type="text" name="full_name" class="form-control" value="<?= $edit_doctor['full_name'] ?? '' ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Email *</label>
                        <input type="email" name="email" class="form-control" value="<?= $edit_doctor['email'] ?? '' ?>" required>
                    </div>
                </div>
                
                <div class="grid-2">
                    <div class="form-group">
                        <label>Phone *</label>
                        <input type="text" name="phone" class="form-control" value="<?= $edit_doctor['phone'] ?? '' ?>" required>
                    </div>
                    <div class="form-group">
                        <label>License Number *</label>
                        <input type="text" name="license_number" class="form-control" value="<?= $edit_doctor['license_number'] ?? '' ?>" required>
                    </div>
                </div>
                
                <div class="grid-3">
                    <div class="form-group">
                        <label>Specialization</label>
                        <select name="specialization" class="form-control">
                            <option>General Pediatrics</option>
                            <option>Neonatology</option>
                            <option>Pediatric Cardiology</option>
                            <option>Pediatric Neurology</option>
                            <option>Pediatric Gastroenterology</option>
                            <option>Pediatric Endocrinology</option>
                            <option>Pediatric Surgery</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Qualification</label>
                        <input type="text" name="qualification" class="form-control" value="<?= $edit_doctor['qualification'] ?? '' ?>">
                    </div>
                    <div class="form-group">
                        <label>Experience (yrs)</label>
                        <input type="number" name="years_of_experience" class="form-control" value="<?= $edit_doctor['years_of_experience'] ?? '' ?>">
                    </div>
                </div>
                
                <div class="grid-2">
                    <div class="form-group">
                        <label>Employment Type</label>
                        <select name="employment_type" class="form-control">
                            <option>Full-Time</option>
                            <option>Part-Time</option>
                            <option>Contract</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Status</label>
                        <select name="status" class="form-control">
                            <option>Active</option>
                            <option>Inactive</option>
                            <option>On Leave</option>
                        </select>
                    </div>
                </div>
                
                <!-- Doctor Role Field -->
                <div class="form-group">
                    <label>Doctor Role *</label>
                    <select name="doctor_role" class="form-control" required>
                        <option value="immunization" <?= ($edit_doctor['doctor_role'] ?? '') == 'immunization' ? 'selected' : '' ?>>Immunization Doctor</option>
                        <option value="specialist" <?= ($edit_doctor['doctor_role'] ?? '') == 'specialist' ? 'selected' : '' ?>>Specialist</option>
                    </select>
                    <small style="color: #5a6f8c;">This determines which dashboard they see after login</small>
                </div>
                
                <div class="form-group">
                    <label>Password <?= $edit_doctor ? '(leave blank to keep current)' : '' ?> *</label>
                    <input type="password" name="password" class="form-control" <?= $edit_doctor ? '' : 'required' ?>>
                </div>
                
                <button type="submit" name="<?= $edit_doctor ? 'edit_doctor' : 'add_doctor' ?>" class="btn">
                    <?= $edit_doctor ? 'Update Doctor' : 'Add Doctor' ?>
                </button>
                
                <?php if ($edit_doctor): ?>
                    <a href="manage_doctors.php" class="btn btn-cancel">Cancel</a>
                <?php endif; ?>
            </form>
        </div>

        <!-- Doctors List -->
        <div class="card">
            <h3>Registered Doctors (<?= $doctors->num_rows ?>)</h3>
            <table>
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Specialization</th>
                        <th>Role</th>
                        <th>License</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($d = $doctors->fetch_assoc()): 
                        $role_class = $d['doctor_role'] == 'specialist' ? 'role-specialist' : 'role-immunization';
                        $role_display = $d['doctor_role'] == 'specialist' ? 'Specialist' : 'Immunization';
                    ?>
                    <tr>
                        <td>
                            <strong>Dr. <?= htmlspecialchars($d['full_name']) ?></strong><br>
                            <small><?= $d['email'] ?></small>
                        </td>
                        <td><?= $d['specialization'] ?></td>
                        <td>
                            <span class="role-badge <?= $role_class ?>">
                                <?= $role_display ?>
                            </span>
                        </td>
                        <td><?= $d['license_number'] ?></td>
                        <td>
                            <span class="badge <?= str_replace(' ', '-', $d['status']) ?>">
                                <?= $d['status'] ?>
                            </span>
                        </td>
                        <td>
                            <a href="?edit=<?= $d['doctor_id'] ?>" class="action-btn btn-edit">Edit</a>
                            <a href="?delete=<?= $d['doctor_id'] ?>" class="action-btn btn-delete" onclick="return confirm('Delete this doctor?')">Delete</a>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
</body>
</html>
<?php $conn->close(); ?>