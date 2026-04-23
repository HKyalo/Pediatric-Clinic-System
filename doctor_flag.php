<?php
session_start();
require_once __DIR__ . "/config/db.php";

// Security check
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'doctor' || $_SESSION['doctor_role'] !== 'immunization') {
    header("Location: index.php");
    exit();
}

$doctor_id = $_SESSION['user_id'];
$child_id = $_GET['child_id'] ?? 0;

// ============================================
// AJAX HANDLER - MUST BE FIRST
// ============================================
if (isset($_GET['ajax'])) {
    header('Content-Type: application/json');
    
    $doc_id = $_GET['doctor_id'] ?? 0;
    $app_date = $_GET['appointment_date'] ?? '';
    
    $clinic_open = '07:00';
    $clinic_close = '18:00';
    $slot_minutes = 90;
    
    $all_slots = [];
    $start = strtotime($clinic_open);
    $end = strtotime($clinic_close);
    
    while ($start < $end) {
        $all_slots[] = date('H:i:s', $start);
        $start = strtotime("+$slot_minutes minutes", $start);
    }
    
    if ($app_date == date('Y-m-d')) {
        $current_time = date('H:i:s');
        $all_slots = array_filter($all_slots, function($slot) use ($current_time) {
            return $slot >= $current_time;
        });
        $all_slots = array_values($all_slots);
    }
    
    $booked = [];
    $booked_query = $conn->prepare("SELECT appointment_time FROM appointments WHERE doctor_id = ? AND appointment_date = ? AND status != 'Cancelled'");
    $booked_query->bind_param("is", $doc_id, $app_date);
    $booked_query->execute();
    $result = $booked_query->get_result();
    while ($row = $result->fetch_assoc()) {
        $booked[] = $row['appointment_time'];
    }
    $booked_query->close();
    
    $blocked = [];
    $blocked_query = $conn->prepare("SELECT block_time FROM blocked_slots WHERE doctor_id = ? AND block_date = ?");
    $blocked_query->bind_param("is", $doc_id, $app_date);
    $blocked_query->execute();
    $result = $blocked_query->get_result();
    while ($row = $result->fetch_assoc()) {
        $blocked[] = $row['block_time'];
    }
    $blocked_query->close();
    
    $available = array_diff($all_slots, $booked, $blocked);
    
    $formatted = [];
    foreach ($available as $slot) {
        $time_obj = DateTime::createFromFormat('H:i:s', $slot);
        $formatted[] = [
            'value' => $slot,
            'display' => $time_obj->format('g:i A')
        ];
    }
    
    echo json_encode(['success' => true, 'slots' => $formatted]);
    exit();
}

// Get child details
$child = $conn->query("SELECT * FROM children WHERE child_id = $child_id")->fetch_assoc();
if (!$child) {
    header("Location: doctor_immunization_patients.php");
    exit();
}

// Get specialists
$specialists = $conn->query("SELECT doctor_id, full_name, specialization FROM doctors WHERE doctor_role = 'specialist' AND status = 'Active' ORDER BY full_name");

$show_booking = false;
$assigned_specialist = null;
$selected_specialist_name = '';

// Handle flag submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_flag'])) {
    $flag_type = $_POST['flag_type'];
    $reason = $_POST['reason'];
    $notes = $_POST['notes'];
    $assigned_to = $_POST['assigned_to'] ?? null;
    
    $conn->begin_transaction();
    
    try {
        $stmt = $conn->prepare("INSERT INTO flags (child_id, flagged_by, assigned_to, flag_type, reason, notes, status, created_at) VALUES (?, ?, ?, ?, ?, ?, 'new', NOW())");
        $stmt->bind_param("iiisss", $child_id, $doctor_id, $assigned_to, $flag_type, $reason, $notes);
        
        if ($stmt->execute()) {
            $flag_id = $conn->insert_id;
            
            if (!empty($child['guardian_id'])) {
                $child_name = $child['first_name'] . ' ' . $child['last_name'];
                $title = ucfirst($flag_type) . ' Concern for ' . $child_name;
                $message = "During an immunization checkup, our doctor has identified that $child_name needs to be reviewed by a specialist.\n\nReason: $reason";
                
                $notif_stmt = $conn->prepare("
                    INSERT INTO notifications (guardian_id, child_id, notification_type, title, message, related_id, is_read, created_at) 
                    VALUES (?, ?, 'flag', ?, ?, ?, 0, NOW())
                ");
                $notif_stmt->bind_param("iissi", $child['guardian_id'], $child_id, $title, $message, $flag_id);
                $notif_stmt->execute();
            }
            
            $conn->commit();
            $show_booking = true;
            $assigned_specialist = $assigned_to;
            
            $spec_query = $conn->query("SELECT full_name FROM doctors WHERE doctor_id = $assigned_to");
            $selected_specialist_name = $spec_query->fetch_assoc()['full_name'] ?? 'Specialist';
            
        } else {
            throw new Exception("Error");
        }
    } catch (Exception $e) {
        $conn->rollback();
        $flag_error = "Error flagging child.";
    }
}

// Handle booking submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['book_appointment'])) {
    $specialist_id = $_POST['specialist_id'];
    $appointment_date = $_POST['appointment_date'];
    $appointment_time = $_POST['appointment_time'];
    $flag_id = $_POST['flag_id'];
    
    $check = $conn->prepare("SELECT appointment_id FROM appointments WHERE doctor_id = ? AND appointment_date = ? AND appointment_time = ? AND status != 'Cancelled'");
    $check->bind_param("iss", $specialist_id, $appointment_date, $appointment_time);
    $check->execute();
    
    if ($check->get_result()->num_rows > 0) {
        $booking_error = "Time slot already booked. Please choose another.";
    } else {
        $insert = $conn->prepare("INSERT INTO appointments (child_id, doctor_id, appointment_date, appointment_time, status, notes) VALUES (?, ?, ?, ?, 'Pending', ?)");
        $notes = "Referral from immunization doctor. Flag ID: $flag_id";
        $insert->bind_param("iisss", $child_id, $specialist_id, $appointment_date, $appointment_time, $notes);
        
        if ($insert->execute()) {
            $booking_success = "Appointment booked successfully! Parent has been notified.";
            $show_booking = false;
        } else {
            $booking_error = "Error booking appointment.";
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Flag Child for Review</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body { background:#f0f4fc; font-family:Arial; }
        .wrapper { display:flex; min-height:100vh; }
        .main { margin-left:260px; padding:30px; flex:1; }
        
        .sidebar { width:260px; background:#0b1a33; color:white; position:fixed; height:100vh; overflow-y:auto; }
        .sidebar-header { padding:30px 20px; border-bottom:1px solid #1e3a5f; }
        .sidebar-header h2 { font-size:24px; margin-bottom:5px; color:white; }
        .sidebar-header p { font-size:13px; color:#a3c6ff; }
        .sidebar .nav ul { list-style:none; padding:20px 0; }
        .sidebar .nav ul li a { display:block; padding:14px 20px; color:#b8d1ff; text-decoration:none; border-left:4px solid transparent; }
        .sidebar .nav ul li a:hover { background:#1e3a5f; border-left-color:#ffd966; color:white; }
        .sidebar .nav ul li a::before { content:'●'; margin-right:12px; font-size:8px; color:#ffd966; }
        
        .back-link { display:inline-block; margin-bottom:20px; color:#0b1a33; text-decoration:none; }
        .back-link:hover { text-decoration:underline; }
        
        .child-header { background:white; padding:25px; margin-bottom:25px; border-left:4px solid #0b1a33; }
        .child-name { font-size:24px; font-weight:700; color:#0b1a33; }
        .parent-badge { background:#e6f0ff; padding:5px 10px; border-radius:4px; font-size:13px; color:#0b1a33; margin-top:10px; display:inline-block; }
        
        .alert { padding:15px; margin-bottom:20px; border-radius:6px; }
        .alert.success { background:#d4edda; color:#155724; border-left:4px solid #28a745; }
        .alert.error { background:#f8d7da; color:#721c24; border-left:4px solid #dc3545; }
        
        /* Success Message Box */
        .success-box { 
            background:#d4edda; 
            padding:20px; 
            margin-bottom:20px; 
            border-radius:6px; 
            border-left:4px solid #28a745;
            text-align:center;
        }
        .success-box h3 { color:#155724; margin-bottom:10px; }
        .success-box p { color:#155724; margin-bottom:15px; }
        
        /* Two Option Buttons */
        .option-buttons {
            display: flex;
            gap: 20px;
            justify-content: center;
            margin: 20px 0;
        }
        .option-btn {
            padding: 12px 25px;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            border: none;
            font-size: 14px;
            transition: all 0.2s;
        }
        .option-btn.book {
            background: #0b1a33;
            color: white;
        }
        .option-btn.book:hover {
            background: #1e3a5f;
            transform: scale(1.02);
        }
        .option-btn.later {
            background: #6c757d;
            color: white;
        }
        .option-btn.later:hover {
            background: #5a6268;
            transform: scale(1.02);
        }
        
        .booking-options { background:#f0fdf4; padding:25px; margin-bottom:20px; border-left:4px solid #10b981; border-radius:4px; }
        .booking-options h3 { color:#0b1a33; margin-bottom:15px; }
        
        .info-box { background:#e6f0ff; padding:15px; border-left:4px solid #0b1a33; margin-bottom:20px; }
        
        .card { background:white; padding:25px; border-left:4px solid #0b1a33; margin-bottom:20px; }
        .card-header { margin-bottom:20px; }
        .card-header h2 { color:#0b1a33; font-size:20px; }
        
        .form-group { margin-bottom:20px; }
        .form-group label { display:block; margin-bottom:5px; color:#1e3a5f; font-weight:600; }
        .form-control { width:100%; padding:12px; border:1px solid #e2e8f0; border-radius:4px; }
        select.form-control { background:white; }
        textarea.form-control { min-height:100px; }
        
        .btn { background:#0b1a33; color:white; padding:12px 25px; border:none; border-radius:6px; cursor:pointer; font-weight:600; }
        .btn-secondary { background:#6c757d; margin-left:10px; }
        .btn-success { background:#10b981; }
        .btn-success:hover { background:#059669; }
        
        .slots-container { margin-top:10px; }
        .slots-grid { display:grid; grid-template-columns:repeat(4,1fr); gap:10px; margin-top:10px; }
        .slot-btn { background:#e6f0ff; border:1px solid #b8d1ff; padding:10px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; color:#0b1a33; text-align:center; transition:all 0.2s; }
        .slot-btn:hover { background:#0b1a33; color:white; border-color:#0b1a33; }
        .slot-btn.selected { background:#ffd966; color:#0b1a33; border-color:#ffd966; }
        .loading-slots { padding:15px; text-align:center; background:#f8fafd; border-radius:6px; color:#5a6f8c; }
        .no-slots { padding:15px; text-align:center; background:#fff3cd; border-radius:6px; color:#856404; }
        .error-slots { padding:15px; text-align:center; background:#fee2e2; border-radius:6px; color:#dc2626; }
        .slot-hint { font-size:12px; color:#5a6f8c; margin-top:5px; }
        
        .back-link-choices { display:inline-block; margin-top:15px; color:#0b1a33; text-decoration:none; }
    </style>
</head>
<body>
<div class="wrapper">
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-header">
            <h2>PCASS</h2>
            <p>Immunization Doctor</p>
        </div>
        <div class="nav">
            <ul>
                <li><a href="doctor_immunization_dashboard.php">Dashboard</a></li>
                <li><a href="doctor_immunization_appointments.php">My Appointments</a></li>
                <li><a href="doctor_immunization_patients.php">My Patients</a></li>
                <li><a href="doctor_profile.php">Profile</a></li>
                <li><a href="logout.php">Logout</a></li>
            </ul>
        </div>
    </div>
    
    <div class="main">
        <a href="doctor_immunization_patients.php" class="back-link">← Back to Patients</a>
        
        <!-- Child Header -->
        <div class="child-header">
            <div class="child-name"><?= htmlspecialchars($child['first_name'] . ' ' . $child['last_name']) ?></div>
            <?php if (!empty($child['guardian_name'])): ?>
            <div class="parent-badge">Parent: <?= htmlspecialchars($child['guardian_name']) ?></div>
            <?php endif; ?>
        </div>
        
        <?php if (isset($flag_error)): ?>
        <div class="alert error"><?= $flag_error ?></div>
        <?php endif; ?>
        
        <!-- SUCCESS MESSAGE WITH CHOICES (shown after successful flag) -->
        <?php if ($show_booking && $assigned_specialist && !isset($_POST['book_appointment'])): ?>
        <div class="success-box">
            <h3>✓ Flag Submitted Successfully</h3>
            <p>Parent has been notified that a specialist review is needed.</p>
            <p><strong>What would you like to do next?</strong></p>
            
            <div class="option-buttons">
                <button onclick="showBookingForm()" class="option-btn book">Book Appointment for Parent</button>
                <a href="doctor_immunization_patients.php" class="option-btn later">Done - Parent Will Book Later</a>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- BOOKING FORM (shown when doctor clicks "Book Appointment for Parent") -->
        <div id="bookingFormContainer" style="display:none;">
            <div class="booking-options">
                <h3>Book Specialist Appointment</h3>
                <form method="POST" id="bookingForm">
                    <input type="hidden" name="specialist_id" id="specialist_id" value="<?= $assigned_specialist ?>">
                    <input type="hidden" name="flag_id" value="<?= $flag_id ?? '' ?>">
                    
                    <div class="form-group">
                        <label>Specialist</label>
                        <input type="text" class="form-control" value="Dr. <?= htmlspecialchars($selected_specialist_name) ?>" readonly disabled style="background:#f8fafd;">
                    </div>
                    
                    <div class="form-group">
                        <label>Appointment Date</label>
                        <input type="date" name="appointment_date" id="appointment_date" class="form-control" min="<?= date('Y-m-d') ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Available Time Slots</label>
                        <div id="slots_container" class="slots-container">
                            <div class="loading-slots">Select a date to see available slots</div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Selected Time</label>
                        <input type="time" name="appointment_time" id="appointment_time" class="form-control" readonly style="background:#f8fafd;" required>
                        <div class="slot-hint">Click on an available slot above to select</div>
                    </div>
                    
                    <div style="display:flex; gap:10px; margin-top:20px;">
                        <button type="submit" name="book_appointment" class="btn btn-success">Confirm Booking</button>
                        <button type="button" onclick="hideBookingForm()" class="btn btn-secondary">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Booking result messages -->
        <?php if (isset($booking_success)): ?>
        <div class="alert success"><?= $booking_success ?></div>
        <?php endif; ?>
        
        <?php if (isset($booking_error)): ?>
        <div class="alert error"><?= $booking_error ?></div>
        <?php endif; ?>
        
        <!-- FLAG FORM (only if not already flagged) -->
        <?php if (!$show_booking && !isset($booking_success)): ?>
        <div class="info-box">
            <strong>Flag for Specialist Review</strong>
            <p style="margin-top:5px;">Use this to refer a child to a specialist when you notice growth concerns, developmental delays, or any other issues that need expert review.</p>
        </div>
        
        <div class="card">
            <div class="card-header">
                <h2>Flag Child for Review</h2>
            </div>
            <form method="POST">
                <div class="form-group">
                    <label>Flag Type *</label>
                    <select name="flag_type" class="form-control" required>
                        <option value="">-- Select Type --</option>
                        <option value="growth">Growth Concern</option>
                        <option value="milestone">Developmental Milestone Delay</option>
                        <option value="vaccine">Vaccine Concern</option>
                        <option value="multiple">Multiple Concerns</option>
                        <option value="other">Other</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Reason for Flag *</label>
                    <input type="text" name="reason" class="form-control" placeholder="Brief summary (e.g., Weight dropping, not walking)" required>
                </div>
                
                <div class="form-group">
                    <label>Detailed Notes</label>
                    <textarea name="notes" class="form-control" placeholder="Add any relevant details, observations, or concerns..."></textarea>
                </div>
                
                <div class="form-group">
                    <label>Assign to Specialist *</label>
                    <select name="assigned_to" class="form-control" required>
                        <option value="">-- Select Specialist --</option>
                        <?php while ($spec = $specialists->fetch_assoc()): ?>
                        <option value="<?= $spec['doctor_id'] ?>">
                            Dr. <?= htmlspecialchars($spec['full_name']) ?> (<?= htmlspecialchars($spec['specialization'] ?: 'Specialist') ?>)
                        </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                
                <button type="submit" name="submit_flag" class="btn">Submit Flag</button>
                <a href="doctor_immunization_patients.php" class="btn btn-secondary">Cancel</a>
            </form>
        </div>
        <?php endif; ?>
        
    </div>
</div>

<script>
function showBookingForm() {
    document.getElementById('bookingFormContainer').style.display = 'block';
    // Scroll to the booking form
    document.getElementById('bookingFormContainer').scrollIntoView({ behavior: 'smooth' });
}

function hideBookingForm() {
    document.getElementById('bookingFormContainer').style.display = 'none';
}

$(document).ready(function() {
    $('#appointment_date').on('change', function() {
        var doctorId = $('#specialist_id').val();
        var appointmentDate = $(this).val();
        var container = $('#slots_container');
        
        if (doctorId && appointmentDate) {
            container.html('<div class="loading-slots">Loading available time slots...</div>');
            
            $.ajax({
                url: window.location.pathname + '?ajax=1',
                type: 'GET',
                data: {
                    doctor_id: doctorId,
                    appointment_date: appointmentDate
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success && response.slots.length > 0) {
                        var html = '<div class="slots-grid">';
                        for (var i = 0; i < response.slots.length; i++) {
                            html += '<div class="slot-btn" data-time="' + response.slots[i].value + '" onclick="selectSlot(this)">' + response.slots[i].display + '</div>';
                        }
                        html += '</div>';
                        container.html(html);
                    } else if (response.success && response.slots.length === 0) {
                        container.html('<div class="no-slots">No available slots for this date. Please choose another date.</div>');
                    } else {
                        container.html('<div class="error-slots">Error loading slots. Please try again.</div>');
                    }
                },
                error: function() {
                    container.html('<div class="error-slots">Could not load available slots. Please check your connection.</div>');
                }
            });
        } else {
            container.html('<div class="loading-slots">Select a date to see available slots</div>');
        }
    });
});

function selectSlot(element) {
    var timeValue = $(element).data('time');
    var formattedTime = timeValue.substring(0, 5);
    $('#appointment_time').val(formattedTime);
    
    $('.slot-btn').removeClass('selected');
    $(element).addClass('selected');
    $('#appointment_time').css('background', '#e6f0ff');
}
</script>

</body>
</html>