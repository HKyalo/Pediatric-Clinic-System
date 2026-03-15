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
// HANDLE ADD GUARDIAN
// ============================================
if (isset($_POST['add_guardian'])) {
    $login_type = $_POST['login_email_type'];
    
    // Set login credentials based on selected type
    if ($login_type == 'mother') {
        $name = $_POST['mother_name'];
        $email = $_POST['mother_email'];
        $phone = $_POST['mother_phone'];
    } elseif ($login_type == 'father') {
        $name = $_POST['father_name'];
        $email = $_POST['father_email'];
        $phone = $_POST['father_phone'];
    } else {
        $name = $_POST['guardian_name'];
        $email = $_POST['guardian_email'];
        $phone = $_POST['guardian_phone'];
    }
    
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    
    // Check if email already exists
    $check = $conn->query("SELECT id FROM guardians WHERE email = '$email'");
    if ($check->num_rows > 0) {
        $message = "Email already exists!";
        $msg_type = "error";
    } else {
        $stmt = $conn->prepare("INSERT INTO guardians 
            (name, email, phone, password, 
             mother_name, mother_email, mother_phone,
             father_name, father_email, father_phone,
             guardian_name, guardian_email, guardian_relationship, guardian_phone,
             login_email_type, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
        
        $stmt->bind_param("sssssssssssssss", 
            $name, $email, $phone, $password,
            $_POST['mother_name'], $_POST['mother_email'], $_POST['mother_phone'],
            $_POST['father_name'], $_POST['father_email'], $_POST['father_phone'],
            $_POST['guardian_name'], $_POST['guardian_email'], $_POST['guardian_relationship'], $_POST['guardian_phone'],
            $login_type
        );
        
        if ($stmt->execute()) {
            $message = "Guardian added successfully!";
            $msg_type = "success";
        } else {
            $message = "Error adding guardian.";
            $msg_type = "error";
        }
        $stmt->close();
    }
}

// ============================================
// HANDLE EDIT GUARDIAN
// ============================================
if (isset($_POST['edit_guardian'])) {
    $id = $_POST['guardian_id'];
    $login_type = $_POST['login_email_type'];
    
    // Set login credentials based on selected type
    if ($login_type == 'mother') {
        $name = $_POST['mother_name'];
        $email = $_POST['mother_email'];
        $phone = $_POST['mother_phone'];
    } elseif ($login_type == 'father') {
        $name = $_POST['father_name'];
        $email = $_POST['father_email'];
        $phone = $_POST['father_phone'];
    } else {
        $name = $_POST['guardian_name'];
        $email = $_POST['guardian_email'];
        $phone = $_POST['guardian_phone'];
    }
    
    $password = $_POST['password'];
    
    if (!empty($password)) {
        $pass = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE guardians SET 
            name=?, email=?, password=?,
            mother_name=?, mother_email=?, mother_phone=?,
            father_name=?, father_email=?, father_phone=?,
            guardian_name=?, guardian_email=?, guardian_relationship=?, guardian_phone=?,
            login_email_type=?
            WHERE id=?");
        $stmt->bind_param("ssssssssssssssi", 
            $name, $email, $pass,
            $_POST['mother_name'], $_POST['mother_email'], $_POST['mother_phone'],
            $_POST['father_name'], $_POST['father_email'], $_POST['father_phone'],
            $_POST['guardian_name'], $_POST['guardian_email'], $_POST['guardian_relationship'], $_POST['guardian_phone'],
            $login_type, $id
        );
    } else {
        $stmt = $conn->prepare("UPDATE guardians SET 
            name=?, email=?, phone=?,
            mother_name=?, mother_email=?, mother_phone=?,
            father_name=?, father_email=?, father_phone=?,
            guardian_name=?, guardian_email=?, guardian_relationship=?, guardian_phone=?,
            login_email_type=?
            WHERE id=?");
        $stmt->bind_param("ssssssssssssssi", 
            $name, $email, $phone,
            $_POST['mother_name'], $_POST['mother_email'], $_POST['mother_phone'],
            $_POST['father_name'], $_POST['father_email'], $_POST['father_phone'],
            $_POST['guardian_name'], $_POST['guardian_email'], $_POST['guardian_relationship'], $_POST['guardian_phone'],
            $login_type, $id
        );
    }
    
    if ($stmt->execute()) {
        $message = "Guardian updated successfully!";
        $msg_type = "success";
    } else {
        $message = "Error updating guardian.";
        $msg_type = "error";
    }
    $stmt->close();
}

// ============================================
// HANDLE DELETE GUARDIAN
// ============================================
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    
    $check = $conn->query("SELECT COUNT(*) as c FROM children WHERE guardian_id = $id")->fetch_assoc()['c'];
    if ($check > 0) {
        $message = "Cannot delete - guardian has registered children.";
        $msg_type = "error";
    } else {
        if ($conn->query("DELETE FROM guardians WHERE id = $id")) {
            $message = "Guardian deleted successfully!";
            $msg_type = "success";
        } else {
            $message = "Error deleting guardian.";
            $msg_type = "error";
        }
    }
}

// ============================================
// HANDLE SWITCH LOGIN
// ============================================
if (isset($_POST['switch_login'])) {
    $id = $_POST['guardian_id'];
    $new_login_type = $_POST['new_login_type'];
    
    // Get current guardian data
    $guardian = $conn->query("SELECT * FROM guardians WHERE id = $id")->fetch_assoc();
    
    // Set new login credentials based on selected type
    if ($new_login_type == 'mother') {
        $new_name = $guardian['mother_name'];
        $new_email = $guardian['mother_email'];
        $new_phone = $guardian['mother_phone'];
    } elseif ($new_login_type == 'father') {
        $new_name = $guardian['father_name'];
        $new_email = $guardian['father_email'];
        $new_phone = $guardian['father_phone'];
    } else {
        $new_name = $guardian['guardian_name'];
        $new_email = $guardian['guardian_email'];
        $new_phone = $guardian['guardian_phone'];
    }
    
    // Check if new email already exists (but not for this same guardian)
    $check = $conn->query("SELECT id FROM guardians WHERE email = '$new_email' AND id != $id");
    if ($check->num_rows > 0) {
        $message = "This email is already used by another account!";
        $msg_type = "error";
    } else {
        // Update the guardian with new login credentials
        $stmt = $conn->prepare("UPDATE guardians SET 
            name=?, email=?, phone=?, login_email_type=? 
            WHERE id=?");
        $stmt->bind_param("ssssi", $new_name, $new_email, $new_phone, $new_login_type, $id);
        
        if ($stmt->execute()) {
            $message = "Login switched to " . ucfirst($new_login_type) . " successfully!";
            $msg_type = "success";
        } else {
            $message = "Error switching login.";
            $msg_type = "error";
        }
        $stmt->close();
    }
}

// ============================================
// GET DATA
// ============================================

// Guardians with child count
$guardians = $conn->query("
    SELECT g.*, COUNT(c.child_id) as child_count
    FROM guardians g
    LEFT JOIN children c ON g.id = c.guardian_id
    GROUP BY g.id
    ORDER BY g.created_at DESC
");

// Stats
$total = $conn->query("SELECT COUNT(*) as c FROM guardians")->fetch_assoc()['c'];
$with_children = $conn->query("SELECT COUNT(DISTINCT guardian_id) as c FROM children")->fetch_assoc()['c'];
$without_children = $total - $with_children;

// Edit data
$edit = null;
if (isset($_GET['edit'])) {
    $edit = $conn->query("SELECT * FROM guardians WHERE id = {$_GET['edit']}")->fetch_assoc();
}

// Switch data
$switch = null;
if (isset($_GET['switch'])) {
    $switch = $conn->query("SELECT * FROM guardians WHERE id = {$_GET['switch']}")->fetch_assoc();
}

// Get this month's new guardians
$new_this_month = $conn->query("SELECT COUNT(*) as c FROM guardians WHERE MONTH(created_at) = MONTH(CURDATE())")->fetch_assoc()['c'];
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD HTML 4.01 Strict//EN">
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <title>Manage Guardians - PCASS</title>
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
        
        /* Stats Row */
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
        .form-grid-3 {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin-bottom: 20px;
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
        
        .form-help {
            font-size: 11px;
            color: #5a6f8c;
            margin-top: 3px;
        }
        
        /* Radio Group */
        .radio-group {
            display: flex;
            gap: 20px;
            padding: 10px 0;
        }
        
        .radio-item {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .radio-item input[type="radio"] {
            accent-color: #0b1a33;
            width: 16px;
            height: 16px;
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
        
        .btn-warning {
            background: #f59e0b;
            color: white;
        }
        
        .btn-warning:hover {
            background: #d97706;
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .btn-secondary:hover {
            background: #5a6268;
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
            vertical-align: top;
        }
        
        tr:hover td {
            background: #f8fafd;
        }
        
        /* Login Badge */
        .login-badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 600;
            background: #e6f0ff;
            color: #0b1a33;
        }
        
        .login-badge.mother {
            background: #fce7f3;
            color: #9d174d;
        }
        
        .login-badge.father {
            background: #e6f0ff;
            color: #1e40af;
        }
        
        .login-badge.guardian {
            background: #fff3cd;
            color: #856404;
        }
        
        /* Child count badge */
        .child-count {
            font-weight: 600;
            background: #e6f0ff;
            color: #0b1a33;
            padding: 4px 10px;
            border-radius: 20px;
            display: inline-block;
            font-size: 12px;
            min-width: 30px;
            text-align: center;
        }
        
        /* Action Buttons */
        .action-group {
            display: flex;
            gap: 4px;
            flex-wrap: wrap;
        }
        
        .action-btn {
            padding: 5px 10px;
            text-decoration: none;
            color: white;
            font-size: 11px;
            font-weight: 600;
            border-radius: 3px;
            display: inline-block;
            white-space: nowrap;
        }
        
        .btn-edit {
            background: #0b1a33;
        }
        
        .btn-edit:hover {
            background: #1e3a5f;
        }
        
        .btn-switch {
            background: #f59e0b;
        }
        
        .btn-switch:hover {
            background: #d97706;
        }
        
        .btn-delete {
            background: #dc3545;
        }
        
        .btn-delete:hover {
            background: #c82333;
        }
        
        /* Date text */
        .date-text {
            color: #5a6f8c;
            font-size: 12px;
        }
        
        /* Tabs for edit form */
        .form-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            border-bottom: 1px solid #e8edf5;
            padding-bottom: 10px;
        }
        
        .tab-btn {
            padding: 8px 16px;
            background: none;
            border: none;
            cursor: pointer;
            font-weight: 600;
            color: #5a6f8c;
            border-bottom: 3px solid transparent;
        }
        
        .tab-btn.active {
            color: #0b1a33;
            border-bottom-color: #0b1a33;
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
        }
    </style>
    <script>
        function showTab(tabName) {
            var tabs = document.querySelectorAll('.tab-content');
            var btns = document.querySelectorAll('.tab-btn');
            
            tabs.forEach(t => t.classList.remove('active'));
            btns.forEach(b => b.classList.remove('active'));
            
            document.getElementById(tabName + '-tab').classList.add('active');
            event.target.classList.add('active');
        }
    </script>
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
                <li><a href="manage_guardians.php" class="active">Manage Guardians</a></li>
                <li><a href="manage_children.php">Manage Children</a></li>
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
            <h1>Manage Guardians</h1>
            <p>Add, edit, and manage parent/guardian accounts</p>
        </div>
        
        <!-- Message Display -->
        <?php if ($message): ?>
            <div class="message <?= $msg_type ?>">
                <?= $msg_type == 'success' ? '✓' : '⚠' ?> <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>
        
        <!-- Mini Stats Row -->
        <div class="stats-row">
            <div class="stat-mini">
                <span class="num"><?= $total ?></span>
                <span class="label">Total Families</span>
            </div>
            <div class="stat-mini">
                <span class="num"><?= $with_children ?></span>
                <span class="label">With Children</span>
            </div>
            <div class="stat-mini">
                <span class="num"><?= $without_children ?></span>
                <span class="label">Without Children</span>
            </div>
            <div class="stat-mini">
                <span class="num">+<?= $new_this_month ?></span>
                <span class="label">This Month</span>
            </div>
        </div>
        
        <!-- ADD GUARDIAN FORM -->
        <div class="add-card">
            <div class="card-header">
                <h2>Add New Parent/Guardian Account</h2>
            </div>
            <div class="card-body">
                <form method="POST">
                    <div class="form-grid-3">
                        <!-- Mother -->
                        <div style="border-right: 1px solid #e8edf5; padding-right: 20px;">
                            <h3 style="color:#ec4899; margin-bottom:15px;">Mother's Details</h3>
                            <div class="form-group">
                                <label>Full Name</label>
                                <input type="text" name="mother_name" class="form-control">
                            </div>
                            <div class="form-group">
                                <label>Email</label>
                                <input type="email" name="mother_email" class="form-control">
                            </div>
                            <div class="form-group">
                                <label>Phone</label>
                                <input type="text" name="mother_phone" class="form-control">
                            </div>
                        </div>
                        
                        <!-- Father -->
                        <div style="border-right: 1px solid #e8edf5; padding-right: 20px;">
                            <h3 style="color:#3b82f6; margin-bottom:15px;">Father's Details</h3>
                            <div class="form-group">
                                <label>Full Name</label>
                                <input type="text" name="father_name" class="form-control">
                            </div>
                            <div class="form-group">
                                <label>Email</label>
                                <input type="email" name="father_email" class="form-control">
                            </div>
                            <div class="form-group">
                                <label>Phone</label>
                                <input type="text" name="father_phone" class="form-control">
                            </div>
                        </div>
                        
                        <!-- Guardian -->
                        <div>
                            <h3 style="color:#f59e0b; margin-bottom:15px;">Guardian's Details</h3>
                            <div class="form-group">
                                <label>Full Name</label>
                                <input type="text" name="guardian_name" class="form-control">
                            </div>
                            <div class="form-group">
                                <label>Relationship</label>
                                <input type="text" name="guardian_relationship" class="form-control">
                            </div>
                            <div class="form-group">
                                <label>Email</label>
                                <input type="email" name="guardian_email" class="form-control">
                            </div>
                            <div class="form-group">
                                <label>Phone</label>
                                <input type="text" name="guardian_phone" class="form-control">
                            </div>
                        </div>
                    </div>
                    
                    <!-- Login Selection -->
                    <div style="background: #f8fafd; padding: 20px; margin: 20px 0; border-radius: 4px;">
                        <h3 style="color:#0b1a33; margin-bottom:15px;">Select Login Account</h3>
                        <p style="font-size:13px; color:#5a6f8c; margin-bottom:15px;">Choose which email will be used to log in:</p>
                        
                        <div class="radio-group">
                            <div class="radio-item">
                                <input type="radio" name="login_email_type" id="add_mother" value="mother" required>
                                <label for="add_mother">Mother's email</label>
                            </div>
                            <div class="radio-item">
                                <input type="radio" name="login_email_type" id="add_father" value="father" required>
                                <label for="add_father">Father's email</label>
                            </div>
                            <div class="radio-item">
                                <input type="radio" name="login_email_type" id="add_guardian" value="guardian" required>
                                <label for="add_guardian">Guardian's email</label>
                            </div>
                        </div>
                        
                        <div class="form-group" style="margin-top:15px;">
                            <label>Password</label>
                            <input type="password" name="password" class="form-control" required minlength="6">
                            <div class="form-help">Minimum 6 characters</div>
                        </div>
                    </div>
                    
                    <button type="submit" name="add_guardian" class="btn btn-success">Add Account</button>
                </form>
            </div>
        </div>
        
        <!-- SWITCH LOGIN FORM (if switching) -->
        <?php if ($switch): ?>
        <div class="card">
            <div class="card-header">
                <h2>Switch Login for <?= htmlspecialchars($switch['name']) ?></h2>
                <span class="badge">Current: <?= ucfirst($switch['login_email_type']) ?></span>
            </div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="guardian_id" value="<?= $switch['id'] ?>">
                    
                    <p style="margin-bottom:20px; padding:15px; background:#f8fafd; border-left:4px solid #f59e0b;">
                        <strong>Current Login:</strong> <?= $switch['name'] ?> (<?= $switch['email'] ?>) - <?= ucfirst($switch['login_email_type']) ?>
                    </p>
                    
                    <h3 style="margin-bottom:15px;">Switch login to:</h3>
                    
                    <div class="radio-group" style="margin-bottom:20px;">
                        <?php if (!empty($switch['mother_name'])): ?>
                        <div class="radio-item">
                            <input type="radio" name="new_login_type" id="switch_mother" value="mother" required>
                            <label for="switch_mother">Mother: <?= $switch['mother_name'] ?> (<?= $switch['mother_email'] ?>)</label>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($switch['father_name'])): ?>
                        <div class="radio-item">
                            <input type="radio" name="new_login_type" id="switch_father" value="father" required>
                            <label for="switch_father">Father: <?= $switch['father_name'] ?> (<?= $switch['father_email'] ?>)</label>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($switch['guardian_name'])): ?>
                        <div class="radio-item">
                            <input type="radio" name="new_login_type" id="switch_guardian" value="guardian" required>
                            <label for="switch_guardian">Guardian: <?= $switch['guardian_name'] ?> (<?= $switch['guardian_email'] ?>)</label>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <div style="display: flex; gap: 10px;">
                        <button type="submit" name="switch_login" class="btn btn-warning">Switch Login</button>
                        <a href="manage_guardians.php" class="btn btn-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- EDIT FORM (if editing) -->
        <?php if ($edit): ?>
        <div class="card">
            <div class="card-header">
                <h2>Edit Account</h2>
                <span class="badge">ID: <?= $edit['id'] ?></span>
            </div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="guardian_id" value="<?= $edit['id'] ?>">
                    
                    <!-- Tabs -->
                    <div class="form-tabs">
                        <button type="button" class="tab-btn active" onclick="showTab('mother')">Mother</button>
                        <button type="button" class="tab-btn" onclick="showTab('father')">Father</button>
                        <button type="button" class="tab-btn" onclick="showTab('guardian')">Guardian</button>
                    </div>
                    
                    <!-- Mother Tab -->
                    <div id="mother-tab" class="tab-content active">
                        <div class="form-grid-3" style="grid-template-columns:1fr 1fr;">
                            <div class="form-group">
                                <label>Mother's Name</label>
                                <input type="text" name="mother_name" class="form-control" value="<?= htmlspecialchars($edit['mother_name'] ?? '') ?>">
                            </div>
                            <div class="form-group">
                                <label>Mother's Email</label>
                                <input type="email" name="mother_email" class="form-control" value="<?= htmlspecialchars($edit['mother_email'] ?? '') ?>">
                            </div>
                            <div class="form-group">
                                <label>Mother's Phone</label>
                                <input type="text" name="mother_phone" class="form-control" value="<?= htmlspecialchars($edit['mother_phone'] ?? '') ?>">
                            </div>
                        </div>
                    </div>
                    
                    <!-- Father Tab -->
                    <div id="father-tab" class="tab-content">
                        <div class="form-grid-3" style="grid-template-columns:1fr 1fr;">
                            <div class="form-group">
                                <label>Father's Name</label>
                                <input type="text" name="father_name" class="form-control" value="<?= htmlspecialchars($edit['father_name'] ?? '') ?>">
                            </div>
                            <div class="form-group">
                                <label>Father's Email</label>
                                <input type="email" name="father_email" class="form-control" value="<?= htmlspecialchars($edit['father_email'] ?? '') ?>">
                            </div>
                            <div class="form-group">
                                <label>Father's Phone</label>
                                <input type="text" name="father_phone" class="form-control" value="<?= htmlspecialchars($edit['father_phone'] ?? '') ?>">
                            </div>
                        </div>
                    </div>
                    
                    <!-- Guardian Tab -->
                    <div id="guardian-tab" class="tab-content">
                        <div class="form-grid-3" style="grid-template-columns:1fr 1fr;">
                            <div class="form-group">
                                <label>Guardian's Name</label>
                                <input type="text" name="guardian_name" class="form-control" value="<?= htmlspecialchars($edit['guardian_name'] ?? '') ?>">
                            </div>
                            <div class="form-group">
                                <label>Relationship</label>
                                <input type="text" name="guardian_relationship" class="form-control" value="<?= htmlspecialchars($edit['guardian_relationship'] ?? '') ?>">
                            </div>
                            <div class="form-group">
                                <label>Guardian's Email</label>
                                <input type="email" name="guardian_email" class="form-control" value="<?= htmlspecialchars($edit['guardian_email'] ?? '') ?>">
                            </div>
                            <div class="form-group">
                                <label>Guardian's Phone</label>
                                <input type="text" name="guardian_phone" class="form-control" value="<?= htmlspecialchars($edit['guardian_phone'] ?? '') ?>">
                            </div>
                        </div>
                    </div>
                    
                    <!-- Login Selection -->
                    <div style="background: #f8fafd; padding: 20px; margin: 20px 0; border-radius: 4px;">
                        <h3 style="color:#0b1a33; margin-bottom:15px;">Login Account</h3>
                        
                        <div class="radio-group">
                            <?php if (!empty($edit['mother_name'])): ?>
                            <div class="radio-item">
                                <input type="radio" name="login_email_type" id="edit_mother" value="mother" <?= $edit['login_email_type'] == 'mother' ? 'checked' : '' ?>>
                                <label for="edit_mother">Mother's email</label>
                            </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($edit['father_name'])): ?>
                            <div class="radio-item">
                                <input type="radio" name="login_email_type" id="edit_father" value="father" <?= $edit['login_email_type'] == 'father' ? 'checked' : '' ?>>
                                <label for="edit_father">Father's email</label>
                            </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($edit['guardian_name'])): ?>
                            <div class="radio-item">
                                <input type="radio" name="login_email_type" id="edit_guardian" value="guardian" <?= $edit['login_email_type'] == 'guardian' ? 'checked' : '' ?>>
                                <label for="edit_guardian">Guardian's email</label>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="form-group" style="margin-top:15px;">
                            <label>New Password (leave blank to keep current)</label>
                            <input type="password" name="password" class="form-control" placeholder="••••••••">
                        </div>
                    </div>
                    
                    <div style="display: flex; gap: 10px;">
                        <button type="submit" name="edit_guardian" class="btn btn-primary">Update Account</button>
                        <a href="manage_guardians.php" class="btn btn-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- GUARDIANS LIST -->
        <div class="card">
            <div class="card-header">
                <h2>Accounts</h2>
                <span class="badge"><?= $guardians->num_rows ?> registered</span>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Login Account</th>
                                <th>Mother</th>
                                <th>Father</th>
                                <th>Guardian</th>
                                <th>Children</th>
                                <th>Registered</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($guardians->num_rows > 0): ?>
                                <?php while ($g = $guardians->fetch_assoc()): 
                                    $login_class = $g['login_email_type'];
                                ?>
                                <tr>
                                    <td>
                                        <strong><?= htmlspecialchars($g['name']) ?></strong>
                                        <div style="font-size:12px; color:#5a6f8c;"><?= htmlspecialchars($g['email']) ?></div>
                                        <div style="margin-top:3px;">
                                            <span class="login-badge <?= $login_class ?>" style="font-size:10px; padding:2px 6px;">
                                                Login: <?= ucfirst($g['login_email_type']) ?>
                                            </span>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if (!empty($g['mother_name'])): ?>
                                            <div style="font-weight:500; color:#0b1a33;"><?= htmlspecialchars($g['mother_name']) ?></div>
                                            <div style="font-size:11px; color:#ec4899;"><?= htmlspecialchars($g['mother_email'] ?? '') ?></div>
                                            <div style="font-size:11px; color:#5a6f8c;"><?= htmlspecialchars($g['mother_phone'] ?? '') ?></div>
                                        <?php else: ?>
                                            <span style="color:#5a6f8c; font-style:italic; font-size:12px;">Not provided</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($g['father_name'])): ?>
                                            <div style="font-weight:500; color:#0b1a33;"><?= htmlspecialchars($g['father_name']) ?></div>
                                            <div style="font-size:11px; color:#3b82f6;"><?= htmlspecialchars($g['father_email'] ?? '') ?></div>
                                            <div style="font-size:11px; color:#5a6f8c;"><?= htmlspecialchars($g['father_phone'] ?? '') ?></div>
                                        <?php else: ?>
                                            <span style="color:#5a6f8c; font-style:italic; font-size:12px;">Not provided</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($g['guardian_name'])): ?>
                                            <div style="font-weight:500; color:#0b1a33;"><?= htmlspecialchars($g['guardian_name']) ?></div>
                                            <div style="font-size:11px; color:#f59e0b;"><?= htmlspecialchars($g['guardian_email'] ?? '') ?></div>
                                            <div style="font-size:11px; color:#5a6f8c;"><?= htmlspecialchars($g['guardian_phone'] ?? '') ?></div>
                                            <?php if (!empty($g['guardian_relationship'])): ?>
                                                <div style="font-size:10px; color:#5a6f8c;">(<?= htmlspecialchars($g['guardian_relationship']) ?>)</div>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span style="color:#5a6f8c; font-style:italic; font-size:12px;">Not provided</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="text-align:center;">
                                        <span class="child-count"><?= $g['child_count'] ?></span>
                                    </td>
                                    <td class="date-text"><?= date('M d, Y', strtotime($g['created_at'])) ?></td>
                                    <td>
                                        <div class="action-group">
                                            <a href="?edit=<?= $g['id'] ?>" class="action-btn btn-edit">Edit</a>
                                            <a href="?switch=<?= $g['id'] ?>" class="action-btn btn-switch">Switch Login</a>
                                            <a href="?delete=<?= $g['id'] ?>" class="action-btn btn-delete" onclick="return confirm('Delete this family account? This action cannot be undone.')">Delete</a>
                                        </div>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" style="text-align: center; padding: 40px; color: #5a6f8c;">No family accounts found</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
<?php $conn->close(); ?>