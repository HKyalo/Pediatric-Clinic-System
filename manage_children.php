<?php
// Set session to last 24 hours 
ini_set('session.cookie_lifetime', 86400); // 24 hours in seconds
ini_set('session.gc_maxlifetime', 86400);   // 24 hours in seconds
session_set_cookie_params(86400); // Also set cookie params
session_start();
require_once __DIR__ . "/config/db.php";

// ============================================
// SECURITY CHECK
// ============================================
if (!isset($_SESSION['admin_id']) || $_SESSION['user_type'] !== 'admin') {
    header("Location: index.php");
    exit();
}

$message = "";
$msg_type = "";

// ============================================
// HANDLE ADD CHILD
// ============================================
if (isset($_POST['add_child'])) {
    $guardian_id = $_POST['guardian_id'];
    $first_name = $_POST['first_name'];
    $last_name = $_POST['last_name'];
    $gender = $_POST['gender'];
    $date_of_birth = $_POST['date_of_birth'];
    
    $stmt = $conn->prepare("INSERT INTO children (guardian_id, first_name, last_name, gender, date_of_birth) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("isssss", $guardian_id, $first_name, $last_name, $gender, $date_of_birth);
    
    if ($stmt->execute()) {
        $message = "Child added successfully!";
        $msg_type = "success";
    } else {
        $message = "Error adding child.";
        $msg_type = "error";
    }
    $stmt->close();
}

// ============================================
// HANDLE DELETE CHILD
// ============================================
if (isset($_GET['delete'])) {
    $child_id = $_GET['delete'];
    
    $check = $conn->query("SELECT COUNT(*) as c FROM appointments WHERE child_id = $child_id")->fetch_assoc()['c'];
    if ($check > 0) {
        $message = "Cannot delete - has appointments";
        $msg_type = "error";
    } else {
        $conn->query("DELETE FROM children WHERE child_id = $child_id");
        $message = "Child deleted!";
        $msg_type = "success";
    }
}

// ============================================
// GET DATA
// ============================================

// Children with guardian info 
$children = $conn->query("
    SELECT c.*, g.name as guardian_name, g.email as guardian_email
    FROM children c
    LEFT JOIN guardians g ON c.guardian_id = g.id
    ORDER BY c.first_name ASC
");

// Stats
$total = $conn->query("SELECT COUNT(*) as c FROM children")->fetch_assoc()['c'];
$male = $conn->query("SELECT COUNT(*) as c FROM children WHERE gender = 'Male'")->fetch_assoc()['c'];
$female = $conn->query("SELECT COUNT(*) as c FROM children WHERE gender = 'Female'")->fetch_assoc()['c'];

// Get this month's new children
$new_this_month = $conn->query("SELECT COUNT(*) as c FROM children WHERE MONTH(created_at) = MONTH(CURDATE())")->fetch_assoc()['c'];

// Get all guardians for dropdown
$guardians = $conn->query("SELECT id, name, email FROM guardians ORDER BY name ASC");
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD HTML 4.01 Strict//EN">
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <title>Manage Children - PCASS</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            background: #f0f4fc;
        }
        
        .wrapper { display: flex; min-height: 100vh; }
        
        .main-content {
            margin-left: 260px;
            padding: 30px;
            background: #f0f4fc;
            flex: 1;
        }
        
        /* Page Header */
        .page-header {
            margin-bottom: 30px;
        }
        
        .page-header h1 {
            color: #0b1a33;
            font-size: 26px;
            font-weight: 700;
            margin-bottom: 5px;
        }
        
        .page-header p {
            color: #5a6f8c;
            font-size: 14px;
        }
        
        /* Messages */
        .message {
            padding: 15px 20px;
            border-radius: 4px;
            margin-bottom: 25px;
            font-weight: 600;
        }
        
        .message.success {
            background: #d4edda;
            color: #155724;
            border-left: 4px solid #28a745;
        }
        
        .message.error {
            background: #f8d7da;
            color: #721c24;
            border-left: 4px solid #dc3545;
        }
        
        /* Stats Row - Matching Guardian Page */
        .stats-row {
            display: flex;
            gap: 15px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        
        .stat-mini {
            background: white;
            padding: 12px 20px;
            border-radius: 4px;
            border-left: 4px solid #0b1a33;
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .stat-mini .num {
            font-size: 22px;
            font-weight: 700;
            color: #0b1a33;
        }
        
        .stat-mini .label {
            color: #5a6f8c;
            font-size: 12px;
        }
        
        /* Add Form Card */
        .add-card {
            background: white;
            border-radius: 4px;
            margin-bottom: 30px;
            box-shadow: 0 2px 10px rgba(11,26,51,0.07);
            border-left: 4px solid #10b981;
            overflow: hidden;
        }
        
        .add-card .card-header {
            background: #f8fafd;
            padding: 15px 24px;
            border-bottom: 1px solid #e8edf5;
        }
        
        .add-card .card-header h2 {
            color: #0b1a33;
            font-size: 16px;
            font-weight: 700;
            margin: 0;
        }
        
        .add-card .card-body {
            padding: 24px;
        }
        
        /* Form */
        .form-grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .form-grid-3 {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
        }
        
        .form-group { margin-bottom: 15px; }
        
        .form-group label {
            display: block;
            font-weight: 600;
            color: #1e3a5f;
            font-size: 13px;
            margin-bottom: 5px;
        }
        
        .form-control {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #e2e8f0;
            border-radius: 4px;
            font-size: 14px;
            font-family: inherit;
        }
        
        .form-control:focus {
            outline: none;
            border-color: #0b1a33;
        }
        
        select.form-control {
            background: white;
        }
        
        /* Buttons */
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            font-weight: 600;
            font-size: 13px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            transition: background 0.2s;
        }
        
        .btn-primary {
            background: #0b1a33;
            color: white;
        }
        
        .btn-primary:hover {
            background: #1e3a5f;
        }
        
        .btn-success {
            background: #10b981;
            color: white;
        }
        
        .btn-success:hover {
            background: #059669;
        }
        
        /* Card */
        .card {
            background: white;
            border-radius: 4px;
            margin-bottom: 30px;
            box-shadow: 0 2px 10px rgba(11,26,51,0.07);
            overflow: hidden;
        }
        
        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 24px;
            background: #f8fafd;
            border-bottom: 1px solid #e8edf5;
        }
        
        .card-header h2 {
            color: #0b1a33;
            font-size: 16px;
            font-weight: 700;
            margin: 0;
        }
        
        .card-header .badge {
            background: #0b1a33;
            color: white;
            padding: 4px 12px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .card-body { padding: 24px; }
        
        /* Table */
        .table-responsive {
            overflow-x: auto;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
        }
        
        th {
            background: #0b1a33;
            color: white;
            padding: 12px 10px;
            text-align: left;
            font-weight: 600;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        td {
            padding: 12px 10px;
            border-bottom: 1px solid #e8edf5;
            color: #1e293b;
            vertical-align: middle;
        }
        
        tr:hover td {
            background: #f8fafd;
        }
        
        /* Age badge */
        .age-badge {
            background: #e6f0ff;
            color: #0b1a33;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            display: inline-block;
        }
        
        /* Gender badge */
        .gender-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
        }
        
        .gender-badge.male {
            background: #e6f0ff;
            color: #1e40af;
        }
        
        .gender-badge.female {
            background: #fce7f3;
            color: #9d174d;
        }
        
        /* Action buttons */
        .action-btn {
            padding: 5px 10px;
            text-decoration: none;
            color: white;
            font-size: 11px;
            font-weight: 600;
            border-radius: 3px;
            display: inline-block;
        }
        
        .btn-delete {
            background: #dc3545;
        }
        
        .btn-delete:hover {
            background: #c82333;
        }
        
        .guardian-name {
            font-weight: 600;
            color: #0b1a33;
            margin-bottom: 2px;
        }
        
        .guardian-email {
            font-size: 11px;
            color: #5a6f8c;
        }
        
        /* Info Box */
        .info-box {
            background: #fff3cd;
            padding: 20px 24px;
            border-left: 4px solid #ffc107;
            border-radius: 0 4px 4px 0;
        }
        
        .info-box h3 {
            color: #856404;
            font-size: 15px;
            font-weight: 700;
            margin-bottom: 12px;
        }
        
        .info-box p {
            color: #856404;
            font-size: 13px;
            margin-bottom: 5px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .info-box p:before {
            content: "•";
            font-weight: 700;
            font-size: 16px;
        }
    </style>
</head>
<body>
<div class="wrapper">
    
    <!-- SIDEBAR -->
    <div class="sidebar">
        <div class="sidebar-header">
            <h2>PCASS</h2>
            <p>Admin Portal</p>
        </div>
        <div class="nav">
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
        </div>
    </div>
    
    <!-- MAIN CONTENT -->
    <div class="main-content">
        
        <div class="page-header">
            <h1>Manage Children</h1>
            <p>View and manage child patients</p>
        </div>
        
        <!-- Message Display -->
        <?php if ($message): ?>
            <div class="message <?= $msg_type ?>">
                <?= $msg_type == 'success' ? '✓' : '⚠' ?> <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>
        
        <!-- Mini Stats Row - Matching Guardian Page -->
        <div class="stats-row">
            <div class="stat-mini">
                <span class="num"><?= $total ?></span>
                <span class="label">Total Children</span>
            </div>
            <div class="stat-mini">
                <span class="num"><?= $male ?></span>
                <span class="label">Male</span>
            </div>
            <div class="stat-mini">
                <span class="num"><?= $female ?></span>
                <span class="label">Female</span>
            </div>
        </div>
        
        <!-- ADD CHILD FORM -->
        <div class="add-card">
            <div class="card-header">
                <h2>Add New Child</h2>
            </div>
            <div class="card-body">
                <form method="POST">
                    <div class="form-group">
                        <label>Select Guardian *</label>
                        <select name="guardian_id" class="form-control" required>
                            <option value="">-- Select Guardian --</option>
                            <?php while ($g = $guardians->fetch_assoc()): ?>
                                <option value="<?= $g['id'] ?>">
                                    <?= htmlspecialchars($g['name']) ?> (<?= htmlspecialchars($g['email']) ?>)
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    
                    <div class="form-grid-2">
                        <div class="form-group">
                            <label>First Name *</label>
                            <input type="text" name="first_name" class="form-control" required>
                        </div>
                        
                        <div class="form-group">
                            <label>Last Name *</label>
                            <input type="text" name="last_name" class="form-control" required>
                        </div>
                    </div>
                    
                    <div class="form-grid-3">
                        <div class="form-group">
                            <label>Date of Birth *</label>
                            <input type="date" name="date_of_birth" class="form-control" max="<?= date('Y-m-d') ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label>Gender *</label>
                            <select name="gender" class="form-control" required>
                                <option value="">-- Select --</option>
                                <option value="Male">Male</option>
                                <option value="Female">Female</option>
                            </select>
                        </div>
                    </div>
                    
                    <button type="submit" name="add_child" class="btn btn-success">Add Child</button>
                </form>
            </div>
        </div>
        
        <!-- CHILDREN LIST -->
        <div class="card">
            <div class="card-header">
                <h2>Children List</h2>
                <span class="badge"><?= $children->num_rows ?> registered</span>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <?php if ($children->num_rows > 0): ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>Child Name</th>
                                    <th>Date of Birth</th>
                                    <th>Age</th>
                                    <th>Gender</th>
                                    <th>Guardian</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $children->data_seek(0); // Reset pointer
                                while ($c = $children->fetch_assoc()): 
                                    $dob = new DateTime($c['date_of_birth']);
                                    $today = new DateTime();
                                    $age_years = $dob->diff($today)->y;
                                    $age_months = $dob->diff($today)->m;
                                    
                                    $age_display = $age_years > 0 
                                        ? $age_years . ' yr' . ($age_years > 1 ? 's' : '')
                                        : $age_months . ' mo' . ($age_months > 1 ? 's' : '');
                                ?>
                                <tr>
                                    <td>
                                        <strong><?= htmlspecialchars($c['first_name'] . ' ' . $c['last_name']) ?></strong>
                                    </td>
                                    <td><?= date('M d, Y', strtotime($c['date_of_birth'])) ?></td>
                                    <td>
                                        <span class="age-badge"><?= $age_display ?></span>
                                    </td>
                                    <td>
                                        <span class="gender-badge <?= strtolower($c['gender']) ?>">
                                            <?= $c['gender'] ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="guardian-name"><?= htmlspecialchars($c['guardian_name'] ?? 'N/A') ?></div>
                                        <?php if (!empty($c['guardian_email'])): ?>
                                            <div class="guardian-email"><?= htmlspecialchars($c['guardian_email']) ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <a href="?delete=<?= $c['child_id'] ?>" 
                                           class="action-btn btn-delete" 
                                           onclick="return confirm('Delete <?= htmlspecialchars($c['first_name']) ?>? This will also delete all associated records.')">
                                            Delete
                                        </a>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div style="text-align: center; padding: 60px; color: #5a6f8c;">
                            No children registered yet.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
    </div>
</div>
</body>
</html>
<?php $conn->close(); ?>