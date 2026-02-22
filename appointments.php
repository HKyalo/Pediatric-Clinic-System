<?php
session_start();
require_once __DIR__ . "/config/db.php";

// Only allow logged-in guardians to access this page
if (!isset($_SESSION['guardian_id']) || $_SESSION['user_type'] !== 'guardian') {
    header("Location: index.php");
    exit();
}

$guardian_id = $_SESSION['guardian_id'];
$guardian_name = $_SESSION['guardian_name'] ?? 'Guardian';
$selected_child_id = $_SESSION['selected_child_id'] ?? null;

// to get notification count
$unread_count = 0;
if ($guardian_id && $selected_child_id) {
    $result = $conn->query("SELECT COUNT(*) as count FROM notifications WHERE guardian_id = $guardian_id AND child_id = $selected_child_id AND is_read = 0");
    $unread_count = $result->fetch_assoc()['count'];
}

// Clinic operating hours and appointment slot duration
$CLINIC_OPEN_TIME = "07:00";   // Clinic opens at 7:00 AM
$CLINIC_CLOSE_TIME = "18:00";  // Clinic closes at 6:00 PM
$APPOINTMENT_SLOT_MINUTES = 90; // Each appointment lasts 30 minutes

$error_message = "";
$success_message = "";

// Get all children belonging to this guardian
$children_query = $conn->prepare("
    SELECT child_id, first_name, last_name 
    FROM children 
    WHERE guardian_id = ? 
    ORDER BY first_name
");
$children_query->bind_param("i", $guardian_id);
$children_query->execute();
$children = $children_query->get_result();
$children_query->close();

// ============================================
// HANDLE APPOINTMENT TYPE SELECTION & DOCTOR FILTERING
// ============================================

$appointment_type = $_GET['type'] ?? 'immunization'; // Default to immunization

// Get doctors based on role
if ($appointment_type === 'specialist') {
    $doctors_query = $conn->query("
        SELECT doctor_id, full_name, specialization
        FROM doctors
        WHERE status = 'active' AND doctor_role = 'specialist'
        ORDER BY full_name
    ");
    $appointment_type_label = "Specialist Review";
    $appointment_type_icon = "👨‍⚕️";
} else {
    $doctors_query = $conn->query("
        SELECT doctor_id, full_name, specialization
        FROM doctors
        WHERE status = 'active' AND doctor_role = 'immunization'
        ORDER BY full_name
    ");
    $appointment_type_label = "Immunization / Well-child Visit";
    $appointment_type_icon = "💉";
}

// Handle booking a new appointment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['book_appointment'])) {
    
    $doctor_id = $_POST['doctor_id'] ?? null;
    $appointment_date = $_POST['appointment_date'] ?? null;
    $appointment_time = $_POST['appointment_time'] ?? null;
    $appointment_type = $_POST['appointment_type'] ?? 'immunization';
    $notes = $_POST['notes'] ?? '';

    // Check if all required fields are filled
    if (!$doctor_id || !$appointment_date || !$appointment_time) {
        $error_message = "Please fill in all required fields.";
    } 
    // Make sure selected time is within clinic hours
    elseif ($appointment_time < $CLINIC_OPEN_TIME || $appointment_time > $CLINIC_CLOSE_TIME) {
        $error_message = "Please select a time within clinic hours.";
    } else {
        
        // Verify the child actually belongs to this guardian
        $verify_query = $conn->prepare("
            SELECT child_id 
            FROM children 
            WHERE child_id = ? AND guardian_id = ?
        ");
        $verify_query->bind_param("ii", $selected_child_id, $guardian_id);
        $verify_query->execute();
        $verify_result = $verify_query->get_result();
        
        if ($verify_result->num_rows === 0) {
            $error_message = "Invalid child selection.";
        } else {
            
            // Check if this time slot is already taken for this doctor
            $check_query = $conn->prepare("
                SELECT appointment_id 
                FROM appointments 
                WHERE doctor_id = ? 
                AND appointment_date = ? 
                AND appointment_time = ? 
                AND status != 'Cancelled'
            ");
            $check_query->bind_param("iss", $doctor_id, $appointment_date, $appointment_time);
            $check_query->execute();
            $check_result = $check_query->get_result();
            
            if ($check_result->num_rows > 0) {
                $error_message = "This time slot is already booked. Please choose a different time.";
            } else {
                
                // ===== NEW: Check if doctor has blocked this slot =====
                $blocked_check = $conn->prepare("
                    SELECT block_id FROM blocked_slots 
                    WHERE doctor_id = ? AND block_date = ? AND block_time = ?
                ");
                $blocked_check->bind_param("iss", $doctor_id, $appointment_date, $appointment_time);
                $blocked_check->execute();
                if ($blocked_check->get_result()->num_rows > 0) {
                    $error_message = "This time slot is not available. Please choose another time.";
                } else {
                    
                    // All checks passed, insert the new appointment
                    $insert_query = $conn->prepare("
                        INSERT INTO appointments 
                        (child_id, doctor_id, appointment_date, appointment_time, status, notes)
                        VALUES (?, ?, ?, ?, 'Pending', ?)
                    ");
                    $insert_query->bind_param("iisss", $selected_child_id, $doctor_id, $appointment_date, $appointment_time, $notes);
                    
                    if ($insert_query->execute()) {
                        header("Location: appointments.php?success=1");
                        exit();
                    } else {
                        $error_message = "Error booking appointment. Please try again.";
                    }
                    $insert_query->close();
                }
                $blocked_check->close();
            }
            $check_query->close();
        }
        $verify_query->close();
    }
}

// Handle rescheduling an existing appointment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reschedule_appointment'])) {
    
    $appointment_id = $_POST['appointment_id'] ?? null;
    $new_date = $_POST['new_date'] ?? null;
    $new_time = $_POST['new_time'] ?? null;

    // Check if all fields are filled
    if (!$appointment_id || !$new_date || !$new_time) {
        $error_message = "Please fill in all fields to reschedule.";
    } else {
        
        // Verify this appointment belongs to this guardian's child
        $verify_query = $conn->prepare("
            SELECT a.doctor_id
            FROM appointments a
            JOIN children c ON a.child_id = c.child_id
            WHERE a.appointment_id = ? AND c.guardian_id = ?
        ");
        $verify_query->bind_param("ii", $appointment_id, $guardian_id);
        $verify_query->execute();
        $verify_result = $verify_query->get_result();
        
        if ($verify_result->num_rows === 0) {
            $error_message = "Invalid appointment.";
        } else {
            
            $appointment_data = $verify_result->fetch_assoc();
            $doctor_id = $appointment_data['doctor_id'];
            
            // Check if the new time slot is available (excluding this appointment)
            $check_query = $conn->prepare("
                SELECT appointment_id 
                FROM appointments 
                WHERE doctor_id = ? 
                AND appointment_date = ? 
                AND appointment_time = ? 
                AND appointment_id != ? 
                AND status != 'Cancelled'
            ");
            $check_query->bind_param("issi", $doctor_id, $new_date, $new_time, $appointment_id);
            $check_query->execute();
            $check_result = $check_query->get_result();
            
            if ($check_result->num_rows > 0) {
                $error_message = "This time slot is already booked. Please choose a different time.";
            } else {
                
                // ===== NEW: Check if doctor has blocked this slot =====
                $blocked_check = $conn->prepare("
                    SELECT block_id FROM blocked_slots 
                    WHERE doctor_id = ? AND block_date = ? AND block_time = ?
                ");
                $blocked_check->bind_param("iss", $doctor_id, $new_date, $new_time);
                $blocked_check->execute();
                if ($blocked_check->get_result()->num_rows > 0) {
                    $error_message = "This time slot is not available. Please choose another time.";
                } else {
                    
                    // Update the appointment with new date and time
                    $update_query = $conn->prepare("
                        UPDATE appointments 
                        SET appointment_date = ?, appointment_time = ?, status = 'Pending'
                        WHERE appointment_id = ?
                    ");
                    $update_query->bind_param("ssi", $new_date, $new_time, $appointment_id);
                    
                    if ($update_query->execute()) {
                        header("Location: appointments.php?rescheduled=1");
                        exit();
                    } else {
                        $error_message = "Error rescheduling appointment.";
                    }
                    $update_query->close();
                }
                $blocked_check->close();
            }
            $check_query->close();
        }
        $verify_query->close();
    }
}

// Handle cancellation of an appointment
if (isset($_GET['cancel']) && is_numeric($_GET['cancel'])) {
    
    $appointment_id = $_GET['cancel'];
    
    // Verify this appointment belongs to this guardian's child
    $verify_query = $conn->prepare("
        SELECT a.appointment_id 
        FROM appointments a
        JOIN children c ON a.child_id = c.child_id
        WHERE a.appointment_id = ? AND c.guardian_id = ?
    ");
    $verify_query->bind_param("ii", $appointment_id, $guardian_id);
    $verify_query->execute();
    $verify_result = $verify_query->get_result();
    
    if ($verify_result->num_rows > 0) {
        
        // Mark the appointment as cancelled (don't delete it)
        $cancel_query = $conn->prepare("
            UPDATE appointments 
            SET status = 'Cancelled' 
            WHERE appointment_id = ?
        ");
        $cancel_query->bind_param("i", $appointment_id);
        $cancel_query->execute();
        $cancel_query->close();
        
        header("Location: appointments.php?cancelled=1");
        exit();
    }
    $verify_query->close();
}

// Get all appointments for the selected child
$appointments_query = $conn->prepare("
    SELECT 
        a.appointment_id,
        a.appointment_date,
        a.appointment_time,
        a.status,
        a.notes,
        CONCAT(c.first_name, ' ', c.last_name) AS child_name,
        d.full_name AS doctor_name,
        d.specialization,
        d.doctor_role
    FROM appointments a
    JOIN children c ON a.child_id = c.child_id
    JOIN doctors d ON a.doctor_id = d.doctor_id
    WHERE c.guardian_id = ? AND a.child_id = ?
    ORDER BY a.appointment_date DESC, a.appointment_time DESC
");
$appointments_query->bind_param("ii", $guardian_id, $selected_child_id);
$appointments_query->execute();
$appointments = $appointments_query->get_result();
$appointments_query->close();

// Set success messages based on URL parameters
if (isset($_GET['success'])) {
    $success_message = "Appointment booked successfully!";
}
if (isset($_GET['cancelled'])) {
    $success_message = "Appointment cancelled successfully!";
}
if (isset($_GET['rescheduled'])) {
    $success_message = "Appointment rescheduled successfully!";
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <title>Appointments - PCASS</title>
    <style>
        /* ===== ALL STYLES IN ONE PLACE ===== */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; background: #f0f4fc; }
        
        /* Layout */
        .appointments-wrapper { display: flex; min-height: 100vh; }
        .appointments-content { margin-left: 260px; padding: 30px; background: #f0f4fc; flex: 1; }
        
        /* Sidebar - matches style.css */
        .sidebar { width: 260px; background: #0b1a33; color: white; position: fixed; height: 100vh; overflow-y: auto; }
        .sidebar-header { padding: 30px 20px; border-bottom: 1px solid #1e3a5f; }
        .sidebar-header h2 { font-size: 24px; margin-bottom: 5px; color: white; }
        .sidebar-header p { font-size: 13px; color: #a3c6ff; }
        .sidebar .nav ul { list-style: none; padding: 20px 0; }
        .sidebar .nav ul li a { display: block; padding: 14px 20px; color: #b8d1ff; text-decoration: none; border-left: 4px solid transparent; }
        .sidebar .nav ul li a:hover { background: #1e3a5f; border-left-color: #ffd966; color: white; }
        .sidebar .nav ul li a.active { background: #1e3a5f; border-left-color: #ffd966; color: white; font-weight: 600; }
        .sidebar .nav ul li a::before { content: '●'; margin-right: 12px; font-size: 8px; color: #ffd966; }
        
        /* Header */
        .page-header { margin-bottom: 25px; }
        .page-header h1 { color: #0b1a33; font-size: 28px; margin-bottom: 5px; }
        .page-header p { color: #5a6f8c; }
        
        /* Messages */
        .alert { padding: 15px 20px; border-radius: 6px; margin-bottom: 25px; font-weight: 600; }
        .alert-success { background: #d4edda; color: #155724; border-left: 4px solid #28a745; }
        .alert-error { background: #f8d7da; color: #721c24; border-left: 4px solid #dc3545; }
        
        /* Cards */
        .appointment-card { background: white; border-radius: 8px; padding: 25px; margin-bottom: 30px; box-shadow: 0 2px 10px rgba(11,26,51,0.1); border-left: 4px solid #0b1a33; }
        .appointment-card h2 { color: #0b1a33; font-size: 20px; margin-bottom: 20px; padding-bottom: 10px; border-bottom: 2px solid #e2e8f0; }
        
        /* Appointment Type Tabs */
        .type-tabs { display: flex; gap: 15px; margin-bottom: 25px; }
        .type-tab { 
            flex: 1; 
            padding: 15px 20px; 
            background: white; 
            border-left: 4px solid #e2e8f0; 
            text-decoration: none; 
            color: #5a6f8c;
            text-align: center;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.2s;
        }
        .type-tab:hover { background: #e6f0ff; }
        .type-tab.active { 
            border-left-color: #0b1a33; 
            color: #0b1a33; 
            background: #f0f4fc;
            box-shadow: 0 2px 8px rgba(11,26,51,0.1);
        }
        .type-icon { font-size: 24px; display: block; margin-bottom: 5px; }
        
        /* Form */
        .form-row { margin-bottom: 20px; }
        .form-row label { display: block; font-weight: 600; color: #1e3a5f; margin-bottom: 5px; font-size: 14px; }
        .form-control { width: 100%; padding: 12px; border: 1px solid #e2e8f0; border-radius: 6px; font-size: 14px; }
        .form-control:focus { outline: none; border-color: #0b1a33; }
        .form-hint { color: #5a6f8c; font-size: 12px; margin-top: 5px; }
        
        /* Buttons */
        .btn-primary { background: #0b1a33; color: white; border: none; padding: 12px 30px; border-radius: 6px; font-size: 16px; font-weight: 600; cursor: pointer; }
        .btn-primary:hover { background: #1e3a5f; }
        .btn-action { padding: 5px 10px; border: none; border-radius: 4px; font-size: 12px; font-weight: 600; cursor: pointer; margin-right: 5px; color: white; }
        .btn-reschedule { background: #17a2b8; }
        .btn-reschedule:hover { background: #138496; }
        .btn-cancel { background: #dc3545; }
        .btn-cancel:hover { background: #c82333; }
        
        /* Table */
        .appointments-table { width: 100%; border-collapse: collapse; font-size: 14px; }
        .appointments-table th { background: #0b1a33; color: white; padding: 12px; text-align: left; font-weight: 600; }
        .appointments-table td { padding: 12px; border-bottom: 1px solid #e2e8f0; }
        .appointments-table tr:hover { background: #f8fafd; }
        
        /* Status badges */
        .status-badge { display: inline-block; padding: 4px 10px; border-radius: 4px; font-size: 12px; font-weight: 600; }
        .status-pending { background: #fff3cd; color: #856404; }
        .status-confirmed { background: #d4edda; color: #155724; }
        .status-cancelled { background: #f8d7da; color: #721c24; }
        .status-completed { background: #d1ecf1; color: #0c5460; }
        
        /* Role badge */
        .role-badge { 
            display: inline-block; 
            padding: 2px 8px; 
            border-radius: 12px; 
            font-size: 10px; 
            font-weight: 600; 
            margin-left: 5px;
            background: #e6f0ff;
            color: #0b1a33;
        }
        .role-badge.specialist { background: #f3e8ff; color: #6b21a8; }
        
        /* Modal */
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); }
        .modal-content { background: white; margin: 10% auto; padding: 30px; border-radius: 8px; width: 90%; max-width: 500px; border-left: 4px solid #0b1a33; }
        .modal-header { font-size: 20px; font-weight: 700; margin-bottom: 20px; color: #0b1a33; }
        .modal-close { float: right; font-size: 28px; font-weight: bold; cursor: pointer; color: #5a6f8c; }
        .modal-close:hover { color: #0b1a33; }
        
        /* Empty state */
        .empty-state { text-align: center; padding: 40px; color: #5a6f8c; }
        
        /* Doctor type indicator */
        .doctor-type { font-size: 12px; color: #5a6f8c; margin-top: 2px; }
        
        /* Status message styles */
        .status-message { 
            display: inline-block; 
            padding: 4px 10px; 
            border-radius: 4px; 
            font-size: 12px; 
            font-weight: 600; 
        }
        .status-message-completed { background: #d1ecf1; color: #0c5460; }
        .status-message-cancelled { background: #f8d7da; color: #721c24; }
        .status-message-confirmed { background: #d4edda; color: #155724; }
        .status-message-past { background: #e2e8f0; color: #4a5568; }
        .status-message-default { color: #5a6f8c; font-size: 12px; }
    </style>
</head>
<body>

<div class="appointments-wrapper">
    
    <!-- Sidebar navigation - Updated to match your simplified menu -->
    <div class="sidebar">
        <div class="sidebar-header">
            <h2>PCASS</h2>
            <p>Child and Guardian Portal</p>
        </div>
        <div class="nav">
            <ul>
                <li><a href="child_dashboard.php">Dashboard</a></li>
                <li><a href="appointments.php" class="active">Appointments</a></li>
                <li><a href="medical-history.php">Medical History</a></li>
                <li><a href="notifications.php"> Notifications
                    <?php if ($unread_count > 0): ?>
                        <span style="background:#dc2626; color:white; padding:2px 8px; border-radius:12px; font-size:11px; margin-left:8px;"><?= $unread_count ?></span>
                    <?php endif; ?>
                </a></li>
                <li><a href="profile.php">Profile</a></li>
                <li><a href="logout.php">Logout</a></li>
            </ul>
        </div>
    </div>
    
    <!-- Main content area -->
    <div class="appointments-content">
        
        <div class="page-header">
            <h1>Appointments</h1>
            <p>Welcome, <?= htmlspecialchars($guardian_name); ?>!</p>
        </div>
        
        <!-- Display success or error messages -->
        <?php if ($success_message): ?>
            <div class="alert alert-success">✓ <?= htmlspecialchars($success_message); ?></div>
        <?php endif; ?>
        
        <?php if ($error_message): ?>
            <div class="alert alert-error">⚠️ <?= htmlspecialchars($error_message); ?></div>
        <?php endif; ?>
        
        <!-- Appointment Type Tabs -->
        <div class="type-tabs">
            <a href="?type=immunization" class="type-tab <?= $appointment_type == 'immunization' ? 'active' : '' ?>">
                <span class="type-icon">💉</span>
                Immunization / Well-child
            </a>
            <a href="?type=specialist" class="type-tab <?= $appointment_type == 'specialist' ? 'active' : '' ?>">
                <span class="type-icon">👨‍⚕️</span>
                Specialist Review
            </a>
        </div>
        
        <!-- Booking form -->
        <div class="appointment-card">
            <h2><?= $appointment_type_icon ?> Book <?= $appointment_type_label ?></h2>
            
            <form method="POST">
                <input type="hidden" name="appointment_type" value="<?= $appointment_type ?>">
                
                <div class="form-row">
                    <label>Select Doctor *</label>
                    <select name="doctor_id" class="form-control" required>
                        <option value="">-- Select Doctor --</option>
                        <?php if ($doctors_query && $doctors_query->num_rows > 0): ?>
                            <?php while ($doctor = $doctors_query->fetch_assoc()): ?>
                                <option value="<?= $doctor['doctor_id']; ?>">
                                    Dr. <?= htmlspecialchars($doctor['full_name']); ?>
                                    <?php if ($doctor['specialization']): ?>
                                        - <?= htmlspecialchars($doctor['specialization']); ?>
                                    <?php endif; ?>
                                </option>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <option value="">No doctors available for this type</option>
                        <?php endif; ?>
                    </select>
                </div>
                
                <div class="form-row">
                    <label>Appointment Date *</label>
                    <input type="date" name="appointment_date" class="form-control" 
                           min="<?= date('Y-m-d'); ?>" required>
                </div>
                
                <div class="form-row">
                    <label>Appointment Time *</label>
                    <input type="time" name="appointment_time" class="form-control" 
                           min="<?= $CLINIC_OPEN_TIME ?>" max="<?= $CLINIC_CLOSE_TIME ?>" 
                           step="<?= $APPOINTMENT_SLOT_MINUTES * 60 ?>" required>
                    <div class="form-hint">
                        Clinic hours: <?= date('g:i A', strtotime($CLINIC_OPEN_TIME)) ?> - <?= date('g:i A', strtotime($CLINIC_CLOSE_TIME)) ?>
                    </div>
                </div>
                
                <div class="form-row">
                    <label>Notes (Optional)</label>
                    <textarea name="notes" class="form-control" rows="3" 
                              placeholder="Any specific concerns or reasons for the visit..."></textarea>
                </div>
                
                <button type="submit" name="book_appointment" class="btn-primary">
                    Book Appointment
                </button>
            </form>
        </div>
        
        <!-- List of existing appointments -->
        <div class="appointment-card">
            <h2>My Appointments</h2>
            
            <?php if ($appointments->num_rows > 0): ?>
                <table class="appointments-table">
                    <thead>
                        <tr>
                            <th>Child</th>
                            <th>Doctor</th>
                            <th>Date</th>
                            <th>Time</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($appointment = $appointments->fetch_assoc()): 
                            // Determine if appointment can be modified
                            // ONLY show actions for Pending appointments that are not in the past
                            $can_modify = (
                                $appointment['status'] === 'Pending' && 
                                strtotime($appointment['appointment_date']) >= strtotime(date('Y-m-d'))
                            );
                            
                            // Get role badge class
                            $role_class = ($appointment['doctor_role'] ?? 'immunization') == 'specialist' ? 'specialist' : '';
                            $role_text = ($appointment['doctor_role'] ?? 'immunization') == 'specialist' ? 'Specialist' : 'Immunization';
                            
                            // Check if appointment is in the past
                            $is_past = strtotime($appointment['appointment_date']) < strtotime(date('Y-m-d'));
                        ?>
                            <tr>
                                <td><?= htmlspecialchars($appointment['child_name']); ?></td>
                                <td>
                                    Dr. <?= htmlspecialchars($appointment['doctor_name']); ?>
                                    <span class="role-badge <?= $role_class ?>"><?= $role_text ?></span>
                                </td>
                                <td><?= date('M d, Y', strtotime($appointment['appointment_date'])); ?></td>
                                <td><?= date('g:i A', strtotime($appointment['appointment_time'])); ?></td>
                                <td>
                                    <span class="status-badge status-<?= strtolower($appointment['status']); ?>">
                                        <?= htmlspecialchars($appointment['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($can_modify): ?>
                                        <!-- Only PENDING and future appointments show Reschedule and Cancel buttons -->
                                        <button class="btn-action btn-reschedule" 
                                                onclick="openRescheduleModal(<?= $appointment['appointment_id']; ?>, '<?= $appointment['appointment_date']; ?>', '<?= $appointment['appointment_time']; ?>')">
                                            Reschedule
                                        </button>
                                        <a href="?cancel=<?= $appointment['appointment_id']; ?>" 
                                           class="btn-action btn-cancel" 
                                           onclick="return confirm('Are you sure you want to cancel this appointment?')">
                                            Cancel
                                        </a>
                                    <?php else: ?>
                                        <!-- Show different messages based on status -->
                                        <?php if ($appointment['status'] === 'Completed'): ?>
                                            <span class="status-message status-message-completed">✓ Completed</span>
                                        <?php elseif ($appointment['status'] === 'Cancelled'): ?>
                                            <span class="status-message status-message-cancelled">✗ Cancelled</span>
                                        <?php elseif ($appointment['status'] === 'Confirmed'): ?>
                                            <span class="status-message status-message-confirmed">✓ Confirmed - Contact clinic to reschedule</span>
                                        <?php elseif ($is_past && $appointment['status'] === 'Pending'): ?>
                                            <span class="status-message status-message-past">⏰ Past appointment - Please rebook</span>
                                        <?php elseif ($is_past): ?>
                                            <span class="status-message status-message-past">⏰ Past appointment</span>
                                        <?php else: ?>
                                            <span class="status-message-default">No actions available</span>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="empty-state">
                    <p>No appointments found. Book your first appointment above!</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Reschedule modal popup -->
<div id="rescheduleModal" class="modal">
    <div class="modal-content">
        <span class="modal-close" onclick="closeRescheduleModal()">&times;</span>
        <div class="modal-header">🔄 Reschedule Appointment</div>
        
        <form method="POST">
            <input type="hidden" id="reschedule_id" name="appointment_id">
            
            <div class="form-row">
                <label>New Date *</label>
                <input type="date" id="new_date" name="new_date" class="form-control" 
                       min="<?= date('Y-m-d'); ?>" required>
            </div>
            
            <div class="form-row">
                <label>New Time *</label>
                <input type="time" id="new_time" name="new_time" class="form-control" 
                       min="<?= $CLINIC_OPEN_TIME ?>" max="<?= $CLINIC_CLOSE_TIME ?>" 
                       step="<?= $APPOINTMENT_SLOT_MINUTES * 60 ?>" required>
                <div class="form-hint">
                    Clinic hours: <?= date('g:i A', strtotime($CLINIC_OPEN_TIME)) ?> - <?= date('g:i A', strtotime($CLINIC_CLOSE_TIME)) ?>
                </div>
            </div>
            
            <button type="submit" name="reschedule_appointment" class="btn-primary">
                ✓ Confirm Reschedule
            </button>
        </form>
    </div>
</div>

<script>
// Open the reschedule modal with current appointment details
function openRescheduleModal(appointmentId, currentDate, currentTime) {
    document.getElementById('reschedule_id').value = appointmentId;
    document.getElementById('new_date').value = currentDate;
    document.getElementById('new_time').value = currentTime;
    document.getElementById('rescheduleModal').style.display = 'block';
}

// Close the reschedule modal
function closeRescheduleModal() {
    document.getElementById('rescheduleModal').style.display = 'none';
}

// Close modal when clicking outside
window.onclick = function(event) {
    const modal = document.getElementById('rescheduleModal');
    if (event.target == modal) {
        closeRescheduleModal();
    }
}
</script>

</body>
</html>
<?php $conn->close(); ?>