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
// HANDLE ADD APPOINTMENT
// ============================================
if (isset($_POST['add_appointment'])) {
    $child_id = $_POST['child_id'];
    $doctor_id = $_POST['doctor_id'];
    $appointment_date = $_POST['appointment_date'];
    $appointment_time = $_POST['appointment_time'];
    $notes = $_POST['notes'] ?? '';
    
    // Check if slot is available
    $check = $conn->prepare("SELECT appointment_id FROM appointments WHERE doctor_id = ? AND appointment_date = ? AND appointment_time = ? AND status != 'Cancelled'");
    $check->bind_param("iss", $doctor_id, $appointment_date, $appointment_time);
    $check->execute();
    
    if ($check->get_result()->num_rows > 0) {
        $message = "This time slot is already booked!";
        $msg_type = "error";
    } else {
        $stmt = $conn->prepare("INSERT INTO appointments (child_id, doctor_id, appointment_date, appointment_time, status, notes) VALUES (?, ?, ?, ?, 'Pending', ?)");
        $stmt->bind_param("iisss", $child_id, $doctor_id, $appointment_date, $appointment_time, $notes);
        
        if ($stmt->execute()) {
            $message = "Appointment scheduled successfully!";
            $msg_type = "success";
        } else {
            $message = "Error scheduling appointment.";
            $msg_type = "error";
        }
        $stmt->close();
    }
    $check->close();
}

// ============================================
// HANDLE RESCHEDULE APPOINTMENT
// ============================================
if (isset($_POST['reschedule_appointment'])) {
    $id = $_POST['appointment_id'];
    $new_date = $_POST['new_date'];
    $new_time = $_POST['new_time'];
    
    // Get doctor_id for this appointment
    $apt = $conn->query("SELECT doctor_id FROM appointments WHERE appointment_id = $id")->fetch_assoc();
    $doctor_id = $apt['doctor_id'];
    
    // Check if new slot is available
    $check = $conn->prepare("SELECT appointment_id FROM appointments WHERE doctor_id = ? AND appointment_date = ? AND appointment_time = ? AND appointment_id != ? AND status != 'Cancelled'");
    $check->bind_param("issi", $doctor_id, $new_date, $new_time, $id);
    $check->execute();
    
    if ($check->get_result()->num_rows > 0) {
        $message = "This time slot is already booked!";
        $msg_type = "error";
    } else {
        $stmt = $conn->prepare("UPDATE appointments SET appointment_date = ?, appointment_time = ?, status = 'Pending' WHERE appointment_id = ?");
        $stmt->bind_param("ssi", $new_date, $new_time, $id);
        
        if ($stmt->execute()) {
            $message = "Appointment rescheduled successfully!";
            $msg_type = "success";
        } else {
            $message = "Error rescheduling appointment.";
            $msg_type = "error";
        }
        $stmt->close();
    }
    $check->close();
}

// ============================================
// HANDLE STATUS UPDATE
// ============================================
if (isset($_GET['update_status'])) {
    $id = $_GET['update_status'];
    $status = $_GET['status'];
    
    $stmt = $conn->prepare("UPDATE appointments SET status = ? WHERE appointment_id = ?");
    $stmt->bind_param("si", $status, $id);
    
    if ($stmt->execute()) {
        $message = "Appointment status updated to $status";
        $msg_type = "success";
    } else {
        $message = "Error updating status";
        $msg_type = "error";
    }
    $stmt->close();
}

// ============================================
// HANDLE DELETE APPOINTMENT
// ============================================
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    
    $stmt = $conn->prepare("DELETE FROM appointments WHERE appointment_id = ?");
    $stmt->bind_param("i", $id);
    
    if ($stmt->execute()) {
        $message = "Appointment deleted";
        $msg_type = "success";
    } else {
        $message = "Error deleting";
        $msg_type = "error";
    }
    $stmt->close();
}

// ============================================
// GET DATA FOR RESCHEDULE MODAL
// ============================================
$reschedule = null;
if (isset($_GET['reschedule'])) {
    $id = $_GET['reschedule'];
    $reschedule = $conn->query("SELECT * FROM appointments WHERE appointment_id = $id")->fetch_assoc();
}

// ============================================
// GET DATA
// ============================================

// All appointments with guardian type info
$appointments = $conn->query("
    SELECT a.*, 
           c.first_name as child_first, c.last_name as child_last,
           g.name as guardian_name, 
           g.mother_name, g.father_name, g.guardian_name,
           g.login_email_type,
           d.full_name as doctor_name, d.specialization, d.doctor_role
    FROM appointments a
    LEFT JOIN children c ON a.child_id = c.child_id
    LEFT JOIN guardians g ON c.guardian_id = g.id
    LEFT JOIN doctors d ON a.doctor_id = d.doctor_id
    ORDER BY a.appointment_date DESC, a.appointment_time DESC
");

// Stats
$total = $conn->query("SELECT COUNT(*) as c FROM appointments")->fetch_assoc()['c'];
$pending = $conn->query("SELECT COUNT(*) as c FROM appointments WHERE status = 'Pending'")->fetch_assoc()['c'];
$confirmed = $conn->query("SELECT COUNT(*) as c FROM appointments WHERE status = 'Confirmed'")->fetch_assoc()['c'];
$cancelled = $conn->query("SELECT COUNT(*) as c FROM appointments WHERE status = 'Cancelled'")->fetch_assoc()['c'];
$completed = $conn->query("SELECT COUNT(*) as c FROM appointments WHERE status = 'Completed'")->fetch_assoc()['c'];

// Get today's date
$today = date('Y-m-d');

// Get all children for dropdown
$children = $conn->query("
    SELECT c.child_id, c.first_name, c.last_name, g.name as guardian_name 
    FROM children c
    LEFT JOIN guardians g ON c.guardian_id = g.id
    ORDER BY c.first_name
");

// Get all doctors for dropdown
$doctors = $conn->query("SELECT doctor_id, full_name, doctor_role, specialization FROM doctors WHERE status = 'Active' ORDER BY full_name");
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD HTML 4.01 Strict//EN">
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <title>Manage Appointments - PCASS</title>
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
        .form-grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
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
        
        .btn-success {
            background: #10b981;
            color: white;
        }
        
        .btn-success:hover {
            background: #059669;
        }
        
        .btn-primary {
            background: #0b1a33;
            color: white;
        }
        
        .btn-primary:hover {
            background: #1e3a5f;
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .btn-secondary:hover {
            background: #5a6268;
        }
        
        /* Status badges */
        .status-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
        }
        
        .status-badge.pending {
            background: #fff3cd;
            color: #856404;
        }
        
        .status-badge.confirmed {
            background: #d4edda;
            color: #155724;
        }
        
        .status-badge.completed {
            background: #d1ecf1;
            color: #0c5460;
        }
        
        .status-badge.cancelled {
            background: #f8d7da;
            color: #721c24;
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
        
        .date-cell {
            font-weight: 600;
            color: #0b1a33;
        }
        
        .date-cell.today {
            color: #3b82f6;
        }
        
        .time-cell {
            font-size: 12px;
            color: #5a6f8c;
        }
        
        .guardian-name {
            font-weight: 500;
            color: #0b1a33;
        }
        
        .guardian-type {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 10px;
            font-weight: 600;
            margin-top: 3px;
        }
        
        .guardian-type.mother {
            background: #fce7f3;
            color: #9d174d;
        }
        
        .guardian-type.father {
            background: #e6f0ff;
            color: #1e40af;
        }
        
        .guardian-type.guardian {
            background: #fff3cd;
            color: #856404;
        }
        
        .doctor-type {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 10px;
            font-weight: 600;
            margin-left: 5px;
        }
        
        .doctor-type.immunization {
            background: #e6f0ff;
            color: #0b1a33;
        }
        
        .doctor-type.specialist {
            background: #f3e8ff;
            color: #6b21a8;
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
            white-space: nowrap;
        }
        
        .btn-confirm {
            background: #10b981;
        }
        
        .btn-confirm:hover {
            background: #059669;
        }
        
        .btn-complete {
            background: #10b981;
        }
        
        .btn-complete:hover {
            background: #059669;
        }
        
        .btn-cancel {
            background: #f59e0b;
        }
        
        .btn-cancel:hover {
            background: #d97706;
        }
        
        .btn-reschedule {
            background: #3b82f6;
        }
        
        .btn-reschedule:hover {
            background: #2563eb;
        }
        
        .btn-delete {
            background: #dc3545;
        }
        
        .btn-delete:hover {
            background: #c82333;
        }
        
        .no-actions {
            color: #5a6f8c;
            font-size: 11px;
            font-style: italic;
            text-align: center;
        }
        
        /* Modal */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
        }
        
        .modal-content {
            background: white;
            margin: 10% auto;
            padding: 30px;
            border-radius: 8px;
            width: 90%;
            max-width: 500px;
            border-left: 4px solid #3b82f6;
        }
        
        .modal-header {
            font-size: 18px;
            font-weight: 700;
            margin-bottom: 20px;
            color: #0b1a33;
        }
        
        .modal-close {
            float: right;
            font-size: 24px;
            font-weight: bold;
            cursor: pointer;
            color: #5a6f8c;
        }
        
        .modal-close:hover {
            color: #0b1a33;
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
                <li><a href="manage_appointments.php" class="active">Manage Appointments</a></li>
                <li><a href="manage_vaccines.php">Manage Vaccines</a></li>
                <li><a href="admin_reports.php">Reports</a></li>
                <li><a href="logout.php">Logout</a></li>
            </ul>
        </div>
    </div>
    
    <!-- MAIN CONTENT -->
    <div class="main-content">
        
        <div class="page-header">
            <h1>Manage Appointments</h1>
            <p>Schedule and manage all appointments</p>
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
                <span class="label">Total</span>
            </div>
            <div class="stat-mini">
                <span class="num"><?= $pending ?></span>
                <span class="label">Pending</span>
            </div>
            <div class="stat-mini">
                <span class="num"><?= $confirmed ?></span>
                <span class="label">Confirmed</span>
            </div>
            <div class="stat-mini">
                <span class="num"><?= $completed ?></span>
                <span class="label">Completed</span>
            </div>
            <div class="stat-mini">
                <span class="num"><?= $cancelled ?></span>
                <span class="label">Cancelled</span>
            </div>
        </div>
        
        <!-- ADD APPOINTMENT FORM -->
        <div class="add-card">
            <div class="card-header">
                <h2>Schedule New Appointment</h2>
            </div>
            <div class="card-body">
                <form method="POST">
                    <div class="form-grid-2">
                        <div class="form-group">
                            <label>Select Child *</label>
                            <select name="child_id" class="form-control" required>
                                <option value="">-- Select Child --</option>
                                <?php while ($c = $children->fetch_assoc()): ?>
                                    <option value="<?= $c['child_id'] ?>">
                                        <?= htmlspecialchars($c['first_name'] . ' ' . $c['last_name']) ?> 
                                        (Guardian: <?= htmlspecialchars($c['guardian_name'] ?? 'N/A') ?>)
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label>Select Doctor *</label>
                            <select name="doctor_id" class="form-control" required>
                                <option value="">-- Select Doctor --</option>
                                <?php while ($d = $doctors->fetch_assoc()): 
                                    $type = $d['doctor_role'] == 'specialist' ? 'Specialist' : 'Immunization';
                                ?>
                                    <option value="<?= $d['doctor_id'] ?>">
                                        Dr. <?= htmlspecialchars($d['full_name']) ?> (<?= $type ?>)
                                        <?php if (!empty($d['specialization'])): ?> - <?= htmlspecialchars($d['specialization']) ?><?php endif; ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-grid-2">
                        <div class="form-group">
                            <label>Date *</label>
                            <input type="date" name="appointment_date" class="form-control" min="<?= date('Y-m-d') ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label>Time *</label>
                            <input type="time" name="appointment_time" class="form-control" required>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Notes (Optional)</label>
                        <textarea name="notes" class="form-control" rows="2" placeholder="Any special instructions..."></textarea>
                    </div>
                    
                    <button type="submit" name="add_appointment" class="btn btn-success">Schedule Appointment</button>
                </form>
            </div>
        </div>
        
        <!-- Appointments List -->
        <div class="card">
            <div class="card-header">
                <h2>All Appointments</h2>
                <span class="badge"><?= $appointments->num_rows ?> total</span>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <?php if ($appointments->num_rows > 0): ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>Date & Time</th>
                                    <th>Child</th>
                                    <th>Guardian/Parent</th>
                                    <th>Doctor</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $appointments->data_seek(0);
                                while ($a = $appointments->fetch_assoc()): 
                                    $is_today = ($a['appointment_date'] == $today);
                                    $doctor_type = $a['doctor_role'] ?? 'immunization';
                                    $type_class = $doctor_type == 'specialist' ? 'specialist' : 'immunization';
                                    $type_label = $doctor_type == 'specialist' ? 'Specialist' : 'Immunization';
                                    
                                    // Determine guardian type
                                    $guardian_type = '';
                                    $guardian_type_class = '';
                                    $guardian_display_name = $a['guardian_name'] ?? 'N/A';
                                    
                                    if ($a['login_email_type'] == 'mother' && !empty($a['mother_name'])) {
                                        $guardian_type = 'Mother';
                                        $guardian_type_class = 'mother';
                                        $guardian_display_name = $a['mother_name'];
                                    } elseif ($a['login_email_type'] == 'father' && !empty($a['father_name'])) {
                                        $guardian_type = 'Father';
                                        $guardian_type_class = 'father';
                                        $guardian_display_name = $a['father_name'];
                                    } elseif (!empty($a['guardian_name'])) {
                                        $guardian_type = 'Guardian';
                                        $guardian_type_class = 'guardian';
                                        $guardian_display_name = $a['guardian_name'];
                                    }
                                ?>
                                <tr>
                                    <td>
                                        <div class="date-cell <?= $is_today ? 'today' : '' ?>">
                                            <?= date('M d, Y', strtotime($a['appointment_date'])) ?>
                                        </div>
                                        <div class="time-cell">
                                            <?= date('g:i A', strtotime($a['appointment_time'])) ?>
                                        </div>
                                    </td>
                                    <td>
                                        <strong><?= htmlspecialchars($a['child_first'] . ' ' . $a['child_last']) ?></strong>
                                    </td>
                                    <td>
                                        <div class="guardian-name"><?= htmlspecialchars($guardian_display_name) ?></div>
                                        <?php if ($guardian_type): ?>
                                            <span class="guardian-type <?= $guardian_type_class ?>">
                                                <?= $guardian_type ?>
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div>Dr. <?= htmlspecialchars($a['doctor_name'] ?? 'Not assigned') ?></div>
                                        <div>
                                            <span class="doctor-type <?= $type_class ?>">
                                                <?= $type_label ?>
                                            </span>
                                            <?php if (!empty($a['specialization'])): ?>
                                                <span style="font-size:11px; color:#5a6f8c;"> - <?= htmlspecialchars($a['specialization']) ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="status-badge <?= strtolower($a['status']) ?>">
                                            <?= $a['status'] ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($a['status'] == 'Pending'): ?>
                                            <div class="action-group">
                                                <a href="?update_status=<?= $a['appointment_id'] ?>&status=Confirmed" 
                                                   class="action-btn btn-confirm" 
                                                   onclick="return confirm('Confirm this appointment?')">
                                                    Confirm
                                                </a>
                                                <a href="?update_status=<?= $a['appointment_id'] ?>&status=Cancelled" 
                                                   class="action-btn btn-cancel" 
                                                   onclick="return confirm('Cancel this appointment?')">
                                                    Cancel
                                                </a>
                                                <a href="?reschedule=<?= $a['appointment_id'] ?>" 
                                                   class="action-btn btn-reschedule">
                                                    Reschedule
                                                </a>
                                                <a href="?delete=<?= $a['appointment_id'] ?>" 
                                                   class="action-btn btn-delete" 
                                                   onclick="return confirm('Delete this appointment? This action cannot be undone.')">
                                                    Delete
                                                </a>
                                            </div>
                                            
                                        <?php elseif ($a['status'] == 'Confirmed'): ?>
                                            <div class="action-group">
                                                <a href="?update_status=<?= $a['appointment_id'] ?>&status=Completed" 
                                                   class="action-btn btn-complete" 
                                                   onclick="return confirm('Mark as completed?')">
                                                    Complete
                                                </a>
                                                <a href="?update_status=<?= $a['appointment_id'] ?>&status=Cancelled" 
                                                   class="action-btn btn-cancel" 
                                                   onclick="return confirm('Cancel this appointment?')">
                                                    Cancel
                                                </a>
                                                <a href="?reschedule=<?= $a['appointment_id'] ?>" 
                                                   class="action-btn btn-reschedule">
                                                    Reschedule
                                                </a>
                                                <a href="?delete=<?= $a['appointment_id'] ?>" 
                                                   class="action-btn btn-delete" 
                                                   onclick="return confirm('Delete this appointment? This action cannot be undone.')">
                                                    Delete
                                                </a>
                                            </div>
                                            
                                        <?php elseif ($a['status'] == 'Completed' || $a['status'] == 'Cancelled'): ?>
                                            <div class="no-actions">—</div>
                                            
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div style="text-align: center; padding: 60px; color: #5a6f8c;">
                            No appointments found.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Reschedule Modal -->
        <?php if ($reschedule): ?>
        <div id="rescheduleModal" class="modal" style="display: block;">
            <div class="modal-content">
                <a href="manage_appointments.php" class="modal-close">&times;</a>
                <div class="modal-header">Reschedule Appointment</div>
                
                <form method="POST">
                    <input type="hidden" name="appointment_id" value="<?= $reschedule['appointment_id'] ?>">
                    
                    <div class="form-group">
                        <label>New Date *</label>
                        <input type="date" name="new_date" class="form-control" 
                               value="<?= $reschedule['appointment_date'] ?>" 
                               min="<?= date('Y-m-d') ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label>New Time *</label>
                        <input type="time" name="new_time" class="form-control" 
                               value="<?= $reschedule['appointment_time'] ?>" required>
                    </div>
                    
                    <div style="display: flex; gap: 10px; margin-top: 20px;">
                        <button type="submit" name="reschedule_appointment" class="btn btn-primary">Update Schedule</button>
                        <a href="manage_appointments.php" class="btn btn-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Information Box -->
        <div class="info-box">
            <h3>Appointment Management</h3>
            <p>Schedule new appointments for any guardian/child</p>
            <p><strong>Pending:</strong> Confirm, Cancel, Reschedule, or Delete</p>
            <p><strong>Confirmed:</strong> Complete, Cancel, Reschedule, or Delete</p>
            <p><strong>Completed/Cancelled:</strong> No actions available</p>
            <p>Guardian/Parent column shows the relationship (Mother/Father/Guardian)</p>
        </div>
        
    </div>
</div>
</body>
</html>
<?php $conn->close(); ?>