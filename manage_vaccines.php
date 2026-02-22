<?php
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
// HANDLE ADD VACCINE
// ============================================
if (isset($_POST['add_vaccine'])) {
    $name = $_POST['vaccine_name'];
    $dose_number = $_POST['dose_number'];
    $recommended_age = $_POST['recommended_age'];
    $min_age_weeks = $_POST['min_age_weeks'] ?: null;
    $max_age_weeks = $_POST['max_age_weeks'] ?: null;
    $interval_weeks = $_POST['interval_weeks'] ?: null;
    $description = $_POST['description'];
    
    $stmt = $conn->prepare("INSERT INTO vaccines (vaccine_name, dose_number, recommended_age, min_age_weeks, max_age_weeks, interval_weeks, description) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("sisiiss", $name, $dose_number, $recommended_age, $min_age_weeks, $max_age_weeks, $interval_weeks, $description);
    
    if ($stmt->execute()) {
        $message = "Vaccine added successfully!";
        $msg_type = "success";
    } else {
        $message = "Error adding vaccine";
        $msg_type = "error";
    }
    $stmt->close();
}

// ============================================
// HANDLE EDIT VACCINE
// ============================================
if (isset($_POST['edit_vaccine'])) {
    $id = $_POST['vaccine_id'];
    $name = $_POST['vaccine_name'];
    $dose_number = $_POST['dose_number'];
    $recommended_age = $_POST['recommended_age'];
    $min_age_weeks = $_POST['min_age_weeks'] ?: null;
    $max_age_weeks = $_POST['max_age_weeks'] ?: null;
    $interval_weeks = $_POST['interval_weeks'] ?: null;
    $description = $_POST['description'];
    
    $stmt = $conn->prepare("UPDATE vaccines SET vaccine_name=?, dose_number=?, recommended_age=?, min_age_weeks=?, max_age_weeks=?, interval_weeks=?, description=? WHERE vaccine_id=?");
    $stmt->bind_param("sisiissi", $name, $dose_number, $recommended_age, $min_age_weeks, $max_age_weeks, $interval_weeks, $description, $id);
    
    if ($stmt->execute()) {
        $message = "Vaccine updated successfully!";
        $msg_type = "success";
    } else {
        $message = "Error updating vaccine";
        $msg_type = "error";
    }
    $stmt->close();
}

// ============================================
// HANDLE DELETE VACCINE
// ============================================
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    
    // Check if vaccine has been administered to any child
    $check = $conn->query("SELECT COUNT(*) as c FROM vaccination_records WHERE vaccine_id = $id")->fetch_assoc()['c'];
    if ($check > 0) {
        $message = "Cannot delete - vaccine has been administered to children";
        $msg_type = "error";
    } else {
        $conn->query("DELETE FROM vaccines WHERE vaccine_id = $id");
        $message = "Vaccine deleted successfully!";
        $msg_type = "success";
    }
}

// ============================================
// GET ALL VACCINES
// ============================================
$vaccines = $conn->query("SELECT * FROM vaccines ORDER BY min_age_weeks, vaccine_id");

// Get vaccine for editing
$edit = null;
if (isset($_GET['edit'])) {
    $edit = $conn->query("SELECT * FROM vaccines WHERE vaccine_id = {$_GET['edit']}")->fetch_assoc();
}

// Stats
$total = $conn->query("SELECT COUNT(*) as c FROM vaccines")->fetch_assoc()['c'];
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD HTML 4.01 Strict//EN">
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <title>Manage Vaccines - PCASS</title>
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
        
        /* Add/Edit Form Card */
        .form-card {
            background: white;
            border-radius: 4px;
            margin-bottom: 30px;
            box-shadow: 0 2px 10px rgba(11,26,51,0.07);
            border-left: 4px solid #10b981;
            overflow: hidden;
        }
        
        .form-card .card-header {
            background: #f8fafd;
            padding: 15px 24px;
            border-bottom: 1px solid #e8edf5;
        }
        
        .form-card .card-header h2 {
            color: #0b1a33;
            font-size: 16px;
            font-weight: 700;
            margin: 0;
        }
        
        .form-card .card-body {
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
        
        textarea.form-control {
            min-height: 80px;
            resize: vertical;
        }
        
        .form-help {
            font-size: 11px;
            color: #5a6f8c;
            margin-top: 3px;
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
            vertical-align: middle;
        }
        
        tr:hover td {
            background: #f8fafd;
        }
        
        .dose-badge {
            display: inline-block;
            background: #e6f0ff;
            color: #0b1a33;
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 600;
        }
        
        .age-weeks {
            font-size: 12px;
            color: #5a6f8c;
        }
        
        /* Action buttons */
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
        }
        
        .btn-edit {
            background: #0b1a33;
        }
        
        .btn-edit:hover {
            background: #1e3a5f;
        }
        
        .btn-delete {
            background: #dc3545;
        }
        
        .btn-delete:hover {
            background: #c82333;
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
                <li><a href="manage_children.php">Manage Children</a></li>
                <li><a href="manage_appointments.php">Manage Appointments</a></li>
                <li><a href="manage_vaccines.php" class="active">Manage Vaccines</a></li>
                <li><a href="admin_reports.php">Reports</a></li>
                <li><a href="logout.php">Logout</a></li>
            </ul>
        </div>
    </div>
    
    <!-- MAIN CONTENT -->
    <div class="main-content">
        
        <div class="page-header">
            <h1>Manage Vaccines</h1>
            <p>Add, edit, and manage the vaccine catalogue</p>
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
                <span class="label">Total Vaccines</span>
            </div>
        </div>
        
        <!-- Add/Edit Form -->
        <div class="form-card">
            <div class="card-header">
                <h2><?= $edit ? 'Edit Vaccine' : 'Add New Vaccine' ?></h2>
            </div>
            <div class="card-body">
                <form method="POST">
                    <?php if ($edit): ?>
                        <input type="hidden" name="vaccine_id" value="<?= $edit['vaccine_id'] ?>">
                    <?php endif; ?>
                    
                    <div class="form-grid-2">
                        <div class="form-group">
                            <label>Vaccine Name *</label>
                            <input type="text" name="vaccine_name" class="form-control" 
                                   value="<?= $edit['vaccine_name'] ?? '' ?>" 
                                   placeholder="e.g., BCG, OPV 1" required>
                        </div>
                        
                        <div class="form-group">
                            <label>Dose Number *</label>
                            <input type="number" name="dose_number" class="form-control" 
                                   value="<?= $edit['dose_number'] ?? '' ?>" 
                                   placeholder="e.g., 0, 1, 2, 3" required>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Recommended Age (Text) *</label>
                        <input type="text" name="recommended_age" class="form-control" 
                               value="<?= $edit['recommended_age'] ?? '' ?>" 
                               placeholder="e.g., At birth, 6 weeks, 9 months" required>
                    </div>
                    
                    <div class="form-grid-3">
                        <div class="form-group">
                            <label>Min Age (weeks)</label>
                            <input type="number" name="min_age_weeks" class="form-control" 
                                   value="<?= $edit['min_age_weeks'] ?? '' ?>" 
                                   placeholder="e.g., 0, 6, 36">
                        </div>
                        
                        <div class="form-group">
                            <label>Max Age (weeks)</label>
                            <input type="number" name="max_age_weeks" class="form-control" 
                                   value="<?= $edit['max_age_weeks'] ?? '' ?>" 
                                   placeholder="e.g., 4, 10, 40">
                        </div>
                        
                        <div class="form-group">
                            <label>Interval (weeks)</label>
                            <input type="number" name="interval_weeks" class="form-control" 
                                   value="<?= $edit['interval_weeks'] ?? '' ?>" 
                                   placeholder="e.g., 4">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Description</label>
                        <textarea name="description" class="form-control" 
                                  placeholder="e.g., Protects against tuberculosis"><?= $edit['description'] ?? '' ?></textarea>
                    </div>
                    
                    <div style="display: flex; gap: 10px;">
                        <button type="submit" name="<?= $edit ? 'edit_vaccine' : 'add_vaccine' ?>" class="btn btn-success">
                            <?= $edit ? 'Update Vaccine' : 'Add Vaccine' ?>
                        </button>
                        
                        <?php if ($edit): ?>
                            <a href="manage_vaccines.php" class="btn btn-secondary">Cancel</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Vaccines List -->
        <div class="card">
            <div class="card-header">
                <h2>Vaccine Catalogue</h2>
                <span class="badge"><?= $vaccines->num_rows ?> vaccines</span>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <?php if ($vaccines->num_rows > 0): ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Vaccine Name</th>
                                    <th>Dose</th>
                                    <th>Recommended Age</th>
                                    <th>Age Range (weeks)</th>
                                    <th>Description</th>
                                    <th>Date Added</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($v = $vaccines->fetch_assoc()): ?>
                                <tr>
                                    <td><?= $v['vaccine_id'] ?></td>
                                    <td><strong><?= htmlspecialchars($v['vaccine_name']) ?></strong></td>
                                    <td>
                                        <span class="dose-badge">Dose <?= $v['dose_number'] ?></span>
                                    </td>
                                    <td><?= htmlspecialchars($v['recommended_age']) ?></td>
                                    <td>
                                        <?php if ($v['min_age_weeks']): ?>
                                            <?= $v['min_age_weeks'] ?> - <?= $v['max_age_weeks'] ?? '?' ?> weeks
                                            <?php if ($v['interval_weeks']): ?>
                                                <div class="age-weeks">Interval: <?= $v['interval_weeks'] ?> weeks</div>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span style="color:#5a6f8c;">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= htmlspecialchars($v['description'] ?? '') ?></td>
                                    <td><?= date('M d, Y', strtotime($v['created_at'])) ?></td>
                                    <td>
                                        <div class="action-group">
                                            <a href="?edit=<?= $v['vaccine_id'] ?>" class="action-btn btn-edit">Edit</a>
                                            <a href="?delete=<?= $v['vaccine_id'] ?>" class="action-btn btn-delete" 
                                               onclick="return confirm('Delete <?= htmlspecialchars($v['vaccine_name']) ?>? This cannot be undone if the vaccine has been given to children.')">
                                                Delete
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div style="text-align: center; padding: 60px; color: #5a6f8c;">
                            No vaccines in catalogue. Add your first vaccine above!
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