<?php
/**
 * appointments.php
 * 
 * Manages guardian appointment booking, rescheduling, and cancellation.
 * Features include:
 * - View existing appointments with status indicators
 * - Book new appointments with doctor selection
 * - Reschedule pending appointments
 * - Cancel appointments
 * - Automatic time slot availability checking
 * - Integration with notification system
 * 
 */

// ============================================
// SESSION CONFIGURATION
// ============================================

// Set timezone to ensure correct time comparisons
date_default_timezone_set('Africa/Nairobi');

// Set session to last 24 hours 
ini_set('session.cookie_lifetime', 86400); // 24 hours in seconds
ini_set('session.gc_maxlifetime', 86400);   // 24 hours in seconds
session_set_cookie_params(86400); // Also set cookie params

session_start();
require_once __DIR__ . "/config/db.php";

// ============================================
// SECURITY VALIDATION
// ============================================

/**
 * Verify user is logged in as a guardian, redirects to login page if not authenticated
 */
if (!isset($_SESSION['guardian_id']) || $_SESSION['user_type'] !== 'guardian') {
    header("Location: index.php");
    exit();
}

// Set session variables after successful validation
$guardian_id = $_SESSION['guardian_id'];
$guardian_name = $_SESSION['guardian_name'] ?? 'Guardian';
$selected_child_id = $_SESSION['selected_child_id'] ?? null;

// ============================================
// CLINIC CONFIGURATION CONSTANTS
// ============================================

/**
 * Clinic configuration constants
 */
define('CLINIC_OPEN_TIME', '07:00:00'); // Clinic opening time (24hr format with seconds)
define('CLINIC_CLOSE_TIME', '18:00:00'); // Clinic closing time (24hr format with seconds)
define('APPOINTMENT_SLOT_MINUTES', 90); // Duration of each appointment slot

// ============================================
// HELPER FUNCTIONS
// ============================================

/**
 * Convert time string to comparable format (seconds since midnight)
 */
function timeToSeconds($time) {
    if (strlen($time) == 5) {
        $time .= ':00'; // Add seconds if missing
    }
    $parts = explode(':', $time);
    return ($parts[0] * 3600) + ($parts[1] * 60) + ($parts[2] ?? 0);
}

/**
 * Check if a time slot is in the future
 */
function isFutureSlot($date, $time) {
    $slot_datetime = strtotime($date . ' ' . $time);
    $current_datetime = time();
    return $slot_datetime > $current_datetime;
}


// AJAX HANDLER FOR AVAILABLE TIME SLOTS


// Check if this is an AJAX request for available slots
if (isset($_GET['action']) && $_GET['action'] === 'get_available_slots') {
    header('Content-Type: application/json');
    
    $selected_doctor_id = (int)($_GET['doctor_id'] ?? 0);
    $selected_appointment_date = $_GET['appointment_date'] ?? '';
    $exclude_appointment_id = (int)($_GET['exclude_id'] ?? 0);
    
    if (!$selected_doctor_id || !$selected_appointment_date) {
        echo json_encode(['success' => false, 'message' => 'Missing doctor or date']);
        exit();
    }
    
    // Validate date format
    $date_timestamp = strtotime($selected_appointment_date);
    if (!$date_timestamp) {
        echo json_encode(['success' => false, 'message' => 'Invalid date format']);
        exit();
    }
    
    $formatted_date = date('Y-m-d', $date_timestamp);
    $current_date = date('Y-m-d');
    
    // Dynamically generate time slots based on clinic hours
    $all_time_slots = [];
    $slot_start_time = strtotime(CLINIC_OPEN_TIME);
    $slot_end_time = strtotime(CLINIC_CLOSE_TIME);
    $slot_duration_minutes = APPOINTMENT_SLOT_MINUTES;
    
    while ($slot_start_time < $slot_end_time) {
        $all_time_slots[] = date('H:i:s', $slot_start_time);
        $slot_start_time = strtotime("+{$slot_duration_minutes} minutes", $slot_start_time);
    }
    
    // Filter out past times for today - using timestamp comparison for accuracy
    if ($formatted_date == $current_date) {
        $current_timestamp = time();
        $all_time_slots = array_filter($all_time_slots, function($slot) use ($formatted_date, $current_timestamp) {
            $slot_timestamp = strtotime($formatted_date . ' ' . $slot);
            return $slot_timestamp > $current_timestamp;
        });
        // Re-index the array
        $all_time_slots = array_values($all_time_slots);
    }
    
    // Get slots that are already booked
    $booked_slots_query = $conn->prepare("
        SELECT appointment_time 
        FROM appointments 
        WHERE doctor_id = ? 
        AND appointment_date = ? 
        AND status != 'Cancelled'
        AND appointment_id != ?
    ");
    $booked_slots_query->bind_param("isi", $selected_doctor_id, $formatted_date, $exclude_appointment_id);
    $booked_slots_query->execute();
    $booked_slots_result = $booked_slots_query->get_result();
    
    $booked_slots_list = [];
    while ($row = $booked_slots_result->fetch_assoc()) {
        $booked_slots_list[] = $row['appointment_time'];
    }
    $booked_slots_query->close();
    
    // Get slots that are blocked by the doctor
    $blocked_slots_query = $conn->prepare("
        SELECT block_time 
        FROM blocked_slots 
        WHERE doctor_id = ? AND block_date = ?
    ");
    $blocked_slots_query->bind_param("is", $selected_doctor_id, $formatted_date);
    $blocked_slots_query->execute();
    $blocked_slots_result = $blocked_slots_query->get_result();
    
    $blocked_slots_list = [];
    while ($row = $blocked_slots_result->fetch_assoc()) {
        $blocked_slots_list[] = $row['block_time'];
    }
    $blocked_slots_query->close();
    
    // Calculate available slots(All slots-booked-blocked)
    $available_slots_list = array_diff($all_time_slots, $booked_slots_list, $blocked_slots_list);
    
    // Format slots for display
    $formatted_slots = [];
    foreach ($available_slots_list as $slot) {
        $time_object = DateTime::createFromFormat('H:i:s', $slot);
        $formatted_slots[] = [
            'value' => $slot,
            'display' => $time_object->format('g:i A')
        ];
    }
    
    echo json_encode([
        'success' => true,
        'available_slots' => $formatted_slots
    ]);
    exit();
}

// ============================================
// INITIALIZE VARIABLES
// ============================================

$error_message = "";
$success_message = "";

// ============================================
// DATABASE OPERATIONS WITH TRY-CATCH
// ============================================

try {
    $unread_count = 0;
    if ($guardian_id && $selected_child_id) {
        $unread_query = $conn->prepare("SELECT COUNT(*) as count FROM notifications WHERE guardian_id = ? AND child_id = ? AND is_read = 0");
        if (!$unread_query) {
            throw new Exception("Failed to prepare notification count query: " . $conn->error);
        }
        $unread_query->bind_param("ii", $guardian_id, $selected_child_id);
        $unread_query->execute();
        $unread_result = $unread_query->get_result();
        $unread_count = $unread_result->fetch_assoc()['count'];
        $unread_query->close();
    }

    $children_query = $conn->prepare("
        SELECT child_id, first_name, last_name 
        FROM children 
        WHERE guardian_id = ? 
        ORDER BY first_name
    ");
    if (!$children_query) {
        throw new Exception("Failed to prepare children query: " . $conn->error);
    }
    $children_query->bind_param("i", $guardian_id);
    $children_query->execute();
    $children_list = $children_query->get_result();
    $children_query->close();

} catch (Exception $e) {
    error_log("Database error in appointments.php: " . $e->getMessage());
    $error_message = "A system error occurred. Please try again later.";
}

// ============================================
// APPOINTMENT TYPE HANDLING
// ============================================

$appointment_type = $_GET['type'] ?? 'immunization';

try {
    if ($appointment_type === 'specialist') {
        $doctors_query = $conn->prepare("
            SELECT doctor_id, full_name, specialization
            FROM doctors
            WHERE status = 'active' AND doctor_role = 'specialist'
            ORDER BY full_name
        ");
        $appointment_type_label = "Specialist Review";
    } else {
        $doctors_query = $conn->prepare("
            SELECT doctor_id, full_name, specialization
            FROM doctors
            WHERE status = 'active' AND doctor_role = 'immunization'
            ORDER BY full_name
        ");
        $appointment_type_label = "Immunization";
    }
    
    if (!$doctors_query) {
        throw new Exception("Failed to prepare doctors query: " . $conn->error);
    }
    $doctors_query->execute();
    $doctors_list = $doctors_query->get_result();
    $doctors_query->close();
    
} catch (Exception $e) {
    error_log("Database error in appointments.php: " . $e->getMessage());
    $error_message = "Unable to load doctors list.";
    $doctors_list = null;
}

// ============================================
// BOOK APPOINTMENT HANDLER WITH NOTIFICATION
// ============================================

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['book_appointment'])) {
    
    try {
        $conn->begin_transaction();
        
        $selected_doctor_id = filter_input(INPUT_POST, 'doctor_id', FILTER_VALIDATE_INT);
        $appointment_date = $_POST['appointment_date'] ?? '';
        $appointment_time = $_POST['appointment_time'] ?? '';
        $appointment_notes = trim($_POST['notes'] ?? '');
        
        if (!$selected_doctor_id || !$appointment_date || !$appointment_time) {
            throw new Exception("Please fill in all required fields.");
        }
        
        // Convert time to consistent format (add seconds if missing)
        if (strlen($appointment_time) == 5) {
            $appointment_time .= ':00';
        }
        
        $clinic_open_seconds = timeToSeconds(CLINIC_OPEN_TIME);
        $clinic_close_seconds = timeToSeconds(CLINIC_CLOSE_TIME);
        $appointment_seconds = timeToSeconds($appointment_time);
        
        // Verify time is within clinic hours
        if ($appointment_seconds < $clinic_open_seconds || $appointment_seconds > $clinic_close_seconds) {
            throw new Exception("Please select a time within clinic hours.");
        }
        
        // STRICT TIME VALIDATION FOR BOOKING - Using timestamp comparison
        $appointment_timestamp = strtotime($appointment_date . ' ' . $appointment_time);
        $current_timestamp = time();
        
        if (!$appointment_timestamp) {
            throw new Exception("Invalid appointment date or time.");
        }
        
        if ($appointment_timestamp <= $current_timestamp) {
            throw new Exception("Cannot book an appointment at a time that has already passed. Please select a future time.");
        }

        $verify_child_query = $conn->prepare("
            SELECT child_id FROM children 
            WHERE child_id = ? AND guardian_id = ?
        ");
        if (!$verify_child_query) {
            throw new Exception("System error: " . $conn->error);
        }
        $verify_child_query->bind_param("ii", $selected_child_id, $guardian_id);
        $verify_child_query->execute();
        
        if ($verify_child_query->get_result()->num_rows === 0) {
            throw new Exception("Invalid child selection.");
        }
        $verify_child_query->close();
        
        $check_slot_query = $conn->prepare("
            SELECT appointment_id FROM appointments 
            WHERE doctor_id = ? AND appointment_date = ? 
            AND appointment_time = ? AND status != 'Cancelled'
        ");
        if (!$check_slot_query) {
            throw new Exception("System error: " . $conn->error);
        }
        $check_slot_query->bind_param("iss", $selected_doctor_id, $appointment_date, $appointment_time);
        $check_slot_query->execute();
        
        if ($check_slot_query->get_result()->num_rows > 0) {
            throw new Exception("This time slot is already booked. Please choose a different time.");
        }
        $check_slot_query->close();
        
        $check_blocked_query = $conn->prepare("
            SELECT block_id FROM blocked_slots 
            WHERE doctor_id = ? AND block_date = ? AND block_time = ?
        ");
        if (!$check_blocked_query) {
            throw new Exception("System error: " . $conn->error);
        }
        $check_blocked_query->bind_param("iss", $selected_doctor_id, $appointment_date, $appointment_time);
        $check_blocked_query->execute();
        
        if ($check_blocked_query->get_result()->num_rows > 0) {
            throw new Exception("This time slot is not available. Please choose another time.");
        }
        $check_blocked_query->close();
        
        $insert_appointment_query = $conn->prepare("
            INSERT INTO appointments 
            (child_id, doctor_id, appointment_date, appointment_time, status, notes)
            VALUES (?, ?, ?, ?, 'Pending', ?)
        ");
        if (!$insert_appointment_query) {
            throw new Exception("System error: " . $conn->error);
        }
        $insert_appointment_query->bind_param("iisss", $selected_child_id, $selected_doctor_id, $appointment_date, $appointment_time, $appointment_notes);
        
        if (!$insert_appointment_query->execute()) {
            throw new Exception("Failed to book appointment: " . $insert_appointment_query->error);
        }
        
        $new_appointment_id = $conn->insert_id;
        
        // ============================================
        // CREATE BOOKING CONFIRMATION NOTIFICATION
        // ============================================
       
        //getting child's full name
        $child_name_query = $conn->query("SELECT first_name, last_name FROM children WHERE child_id = $selected_child_id");
        $child_name_data = $child_name_query->fetch_assoc();
        $child_full_name = $child_name_data['first_name'] . ' ' . $child_name_data['last_name'];
        
        //getting doctor's full name
        $doctor_name_query = $conn->query("SELECT full_name FROM doctors WHERE doctor_id = $selected_doctor_id");
        $doctor_name_data = $doctor_name_query->fetch_assoc();
        $doctor_full_name = $doctor_name_data['full_name'];
        
        //create the notification message
        $confirm_title = "Appointment Confirmed";
        $confirm_message = "$child_full_name has an appointment with Dr. $doctor_full_name on " . date('l, F j, Y', strtotime($appointment_date)) . " at " . date('g:i A', strtotime($appointment_time)) . ".";
        
        //insert into notifications table
        $booking_notification = $conn->prepare("
            INSERT INTO notifications (guardian_id, child_id, notification_type, title, message, related_id, is_read, created_at) 
            VALUES (?, ?, 'appointment_confirmation', ?, ?, ?, 0, NOW())
        ");
        $booking_notification->bind_param("iissi", $guardian_id, $selected_child_id, $confirm_title, $confirm_message, $new_appointment_id);
        $booking_notification->execute();
        $booking_notification->close();
        
        $conn->commit();
        
        header("Location: appointments.php?success=1");
        exit();
        
    } catch (Exception $e) {
        $conn->rollback();
        $error_message = $e->getMessage();
        error_log("Appointment booking error: " . $e->getMessage());
    }
    
    if (isset($insert_appointment_query)) $insert_appointment_query->close();
}

// ============================================
// RESCHEDULE APPOINTMENT HANDLER WITH NOTIFICATION
// ============================================

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reschedule_appointment'])) {
    
    try {
        $conn->begin_transaction();
        
        $appointment_id = filter_input(INPUT_POST, 'appointment_id', FILTER_VALIDATE_INT);
        $new_appointment_date = $_POST['new_date'] ?? '';
        $new_appointment_time = $_POST['new_time'] ?? '';
        
        if (!$appointment_id || !$new_appointment_date || !$new_appointment_time) {
            throw new Exception("Please fill in all fields to reschedule.");
        }
        
        // Convert time to consistent format
        if (strlen($new_appointment_time) == 5) {
            $new_appointment_time .= ':00';
        }
        
        // STRICT TIME VALIDATION FOR RESCHEDULING - Using timestamp comparison
        $new_timestamp = strtotime($new_appointment_date . ' ' . $new_appointment_time);
        $current_timestamp = time();
        
        if (!$new_timestamp) {
            throw new Exception("Invalid appointment date or time.");
        }
        
        if ($new_timestamp <= $current_timestamp) {
            throw new Exception("Cannot reschedule to a time that has already passed. Please select a future time.");
        }

        $verify_ownership_query = $conn->prepare("
            SELECT a.doctor_id, a.child_id
            FROM appointments a
            JOIN children c ON a.child_id = c.child_id
            WHERE a.appointment_id = ? AND c.guardian_id = ?
        ");
        if (!$verify_ownership_query) {
            throw new Exception("System error: " . $conn->error);
        }
        $verify_ownership_query->bind_param("ii", $appointment_id, $guardian_id);
        $verify_ownership_query->execute();
        $verify_ownership_result = $verify_ownership_query->get_result();
        
        if ($verify_ownership_result->num_rows === 0) {
            throw new Exception("Invalid appointment.");
        }
        
        $appointment_data = $verify_ownership_result->fetch_assoc();
        $doctor_id = $appointment_data['doctor_id'];
        $child_id = $appointment_data['child_id'];
        $verify_ownership_query->close();
        
        $check_availability_query = $conn->prepare("
            SELECT appointment_id FROM appointments 
            WHERE doctor_id = ? AND appointment_date = ? 
            AND appointment_time = ? AND appointment_id != ? 
            AND status != 'Cancelled'
        ");
        if (!$check_availability_query) {
            throw new Exception("System error: " . $conn->error);
        }
        $check_availability_query->bind_param("issi", $doctor_id, $new_appointment_date, $new_appointment_time, $appointment_id);
        $check_availability_query->execute();
        
        if ($check_availability_query->get_result()->num_rows > 0) {
            throw new Exception("This time slot is already booked. Please choose a different time.");
        }
        $check_availability_query->close();
        
        $check_blocked_slot_query = $conn->prepare("
            SELECT block_id FROM blocked_slots 
            WHERE doctor_id = ? AND block_date = ? AND block_time = ?
        ");
        if (!$check_blocked_slot_query) {
            throw new Exception("System error: " . $conn->error);
        }
        $check_blocked_slot_query->bind_param("iss", $doctor_id, $new_appointment_date, $new_appointment_time);
        $check_blocked_slot_query->execute();
        
        if ($check_blocked_slot_query->get_result()->num_rows > 0) {
            throw new Exception("This time slot is not available. Please choose another time.");
        }
        $check_blocked_slot_query->close();
        
        $update_appointment_query = $conn->prepare("
            UPDATE appointments 
            SET appointment_date = ?, appointment_time = ?, status = 'Pending'
            WHERE appointment_id = ?
        ");
        if (!$update_appointment_query) {
            throw new Exception("System error: " . $conn->error);
        }
        $update_appointment_query->bind_param("ssi", $new_appointment_date, $new_appointment_time, $appointment_id);
        
        if (!$update_appointment_query->execute()) {
            throw new Exception("Failed to reschedule appointment.");
        }
        
        // ============================================
        // CREATE RESCHEDULE NOTIFICATION
        // ============================================
        
        $notification_query = $conn->prepare("
            SELECT 
                c.first_name, c.last_name,
                d.full_name as doctor_name
            FROM appointments a
            JOIN children c ON a.child_id = c.child_id
            JOIN doctors d ON a.doctor_id = d.doctor_id
            WHERE a.appointment_id = ?
        ");
        $notification_query->bind_param("i", $appointment_id);
        $notification_query->execute();
        $notification_result = $notification_query->get_result();
        $notification_data = $notification_result->fetch_assoc();
        $notification_query->close();
        
        $child_full_name = $notification_data['first_name'] . ' ' . $notification_data['last_name'];
        $doctor_full_name = $notification_data['doctor_name'];
        
        $formatted_date = date('l, F j, Y', strtotime($new_appointment_date));
        $formatted_time = date('g:i A', strtotime($new_appointment_time));
        
        //create the notification message
        $reschedule_title = "Appointment Rescheduled";
        $reschedule_message = "$child_full_name's appointment with Dr. $doctor_full_name has been rescheduled to $formatted_date at $formatted_time.";
        
        //insert to notifications table
        $reschedule_notification = $conn->prepare("
            INSERT INTO notifications (guardian_id, child_id, notification_type, title, message, related_id, is_read, created_at) 
            VALUES (?, ?, 'appointment_rescheduled', ?, ?, ?, 0, NOW())
        ");
        $reschedule_notification->bind_param("iissi", $guardian_id, $child_id, $reschedule_title, $reschedule_message, $appointment_id);
        $reschedule_notification->execute();
        $reschedule_notification->close();
        
        $conn->commit();
        
        header("Location: appointments.php?rescheduled=1");
        exit();
        
    } catch (Exception $e) {
        $conn->rollback();
        $error_message = $e->getMessage();
        error_log("Appointment rescheduling error: " . $e->getMessage());
    }
    
    if (isset($update_appointment_query)) $update_appointment_query->close();
}

// ============================================
// CANCEL APPOINTMENT HANDLER WITH NOTIFICATION
// ============================================

if (isset($_GET['cancel']) && is_numeric($_GET['cancel'])) {
    
    try {
        $conn->begin_transaction();
        
        $appointment_id = (int)$_GET['cancel'];
        
        $verify_cancel_query = $conn->prepare("
            SELECT a.appointment_id, a.appointment_date, a.appointment_time, a.child_id
            FROM appointments a
            JOIN children c ON a.child_id = c.child_id
            WHERE a.appointment_id = ? AND c.guardian_id = ?
        ");
        if (!$verify_cancel_query) {
            throw new Exception("System error: " . $conn->error);
        }
        $verify_cancel_query->bind_param("ii", $appointment_id, $guardian_id);
        $verify_cancel_query->execute();
        $cancel_result = $verify_cancel_query->get_result();
        
        if ($cancel_result->num_rows === 0) {
            throw new Exception("Invalid appointment.");
        }
        
        $appointment_details = $cancel_result->fetch_assoc();
        $verify_cancel_query->close();
        
        $cancel_appointment_query = $conn->prepare("
            UPDATE appointments 
            SET status = 'Cancelled' 
            WHERE appointment_id = ?
        ");
        if (!$cancel_appointment_query) {
            throw new Exception("System error: " . $conn->error);
        }
        $cancel_appointment_query->bind_param("i", $appointment_id);
        
        if (!$cancel_appointment_query->execute()) {
            throw new Exception("Failed to cancel appointment.");
        }
        
        // ============================================
        // CREATE CANCELLATION NOTIFICATION
        // ============================================
        //get child and doctor names
        $apt_details_query = $conn->prepare("
            SELECT c.first_name, c.last_name, d.full_name as doctor_name 
            FROM appointments a
            JOIN children c ON a.child_id = c.child_id
            JOIN doctors d ON a.doctor_id = d.doctor_id
            WHERE a.appointment_id = ?
        ");
        $apt_details_query->bind_param("i", $appointment_id);
        $apt_details_query->execute();
        $apt_details = $apt_details_query->get_result()->fetch_assoc();
        $apt_details_query->close();
        
        $child_full_name = $apt_details['first_name'] . ' ' . $apt_details['last_name'];
        $doctor_full_name = $apt_details['doctor_name'];
        
        //create the notification message
        $cancel_title = "Appointment Cancelled";
        $cancel_message = "$child_full_name's appointment with Dr. $doctor_full_name on " . date('l, F j, Y', strtotime($appointment_details['appointment_date'])) . " at " . date('g:i A', strtotime($appointment_details['appointment_time'])) . " has been cancelled.";
        
        //insert into notifications table
        $cancel_notification = $conn->prepare("
            INSERT INTO notifications (guardian_id, child_id, notification_type, title, message, related_id, is_read, created_at) 
            VALUES (?, ?, 'appointment_cancelled', ?, ?, ?, 0, NOW())
        ");
        $cancel_notification->bind_param("iissi", $guardian_id, $appointment_details['child_id'], $cancel_title, $cancel_message, $appointment_id);
        $cancel_notification->execute();
        $cancel_notification->close();
        
        $conn->commit();
        
        header("Location: appointments.php?cancelled=1");
        exit();
        
    } catch (Exception $e) {
        $conn->rollback();
        $error_message = $e->getMessage();
        error_log("Appointment cancellation error: " . $e->getMessage());
    }
    
    if (isset($cancel_appointment_query)) $cancel_appointment_query->close();
}

// ============================================
// FETCH APPOINTMENTS FOR DISPLAY
// ============================================

try {
    $appointments_query = $conn->prepare("
        SELECT 
            a.appointment_id,
            a.appointment_date,
            a.appointment_time,
            a.status,
            a.notes,
            a.doctor_id,
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
    
    if (!$appointments_query) {
        throw new Exception("Failed to prepare appointments query: " . $conn->error);
    }
    
    $appointments_query->bind_param("ii", $guardian_id, $selected_child_id);
    $appointments_query->execute();
    $appointments_list = $appointments_query->get_result();
    $appointments_query->close();
    
} catch (Exception $e) {
    error_log("Database error in appointments.php: " . $e->getMessage());
    $error_message = "Unable to load your appointments.";
    $appointments_list = null;
}

// ============================================
// SUCCESS MESSAGE HANDLING
// ============================================

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
        /* Your existing CSS remains exactly the same */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; background: #f0f4fc; }
        .appointments-wrapper { display: flex; min-height: 100vh; }
        .appointments-content { margin-left: 260px; padding: 30px; background: #f0f4fc; flex: 1; }
        .sidebar { width: 260px; background: #0b1a33; color: white; position: fixed; height: 100vh; overflow-y: auto; }
        .sidebar-header { padding: 30px 20px; border-bottom: 1px solid #1e3a5f; }
        .sidebar-header h2 { font-size: 24px; margin-bottom: 5px; color: white; }
        .sidebar-header p { font-size: 13px; color: #a3c6ff; }
        .sidebar .nav ul { list-style: none; padding: 20px 0; }
        .sidebar .nav ul li a { display: block; padding: 14px 20px; color: #b8d1ff; text-decoration: none; border-left: 4px solid transparent; }
        .sidebar .nav ul li a:hover { background: #1e3a5f; border-left-color: #ffd966; color: white; }
        .sidebar .nav ul li a.active { background: #1e3a5f; border-left-color: #ffd966; color: white; font-weight: 600; }
        .sidebar .nav ul li a::before { content: '●'; margin-right: 12px; font-size: 8px; color: #ffd966; }
        .page-header { margin-bottom: 25px; }
        .page-header h1 { color: #0b1a33; font-size: 28px; margin-bottom: 5px; }
        .page-header p { color: #5a6f8c; }
        .alert { padding: 15px 20px; border-radius: 6px; margin-bottom: 25px; font-weight: 600; }
        .alert-success { background: #d4edda; color: #155724; border-left: 4px solid #28a745; }
        .alert-error { background: #f8d7da; color: #721c24; border-left: 4px solid #dc3545; }
        .appointment-card { background: white; border-radius: 8px; padding: 25px; margin-bottom: 30px; box-shadow: 0 2px 10px rgba(11,26,51,0.1); border-left: 4px solid #0b1a33; }
        .appointment-card h2 { color: #0b1a33; font-size: 20px; margin-bottom: 20px; padding-bottom: 10px; border-bottom: 2px solid #e2e8f0; }
        .type-tabs { display: flex; gap: 15px; margin-bottom: 25px; }
        .type-tab { flex: 1; padding: 15px 20px; background: white; border-left: 4px solid #e2e8f0; text-decoration: none; color: #5a6f8c; text-align: center; border-radius: 8px; font-weight: 600; transition: all 0.2s; }
        .type-tab:hover { background: #e6f0ff; }
        .type-tab.active { border-left-color: #0b1a33; color: #0b1a33; background: #f0f4fc; box-shadow: 0 2px 8px rgba(11,26,51,0.1); }
        .form-row { margin-bottom: 20px; }
        .form-row label { display: block; font-weight: 600; color: #1e3a5f; margin-bottom: 5px; font-size: 14px; }
        .form-control { width: 100%; padding: 12px; border: 1px solid #e2e8f0; border-radius: 6px; font-size: 14px; }
        .form-control:focus { outline: none; border-color: #0b1a33; }
        .form-hint { color: #5a6f8c; font-size: 12px; margin-top: 5px; }
        .btn-primary { background: #0b1a33; color: white; border: none; padding: 12px 30px; border-radius: 6px; font-size: 16px; font-weight: 600; cursor: pointer; }
        .btn-primary:hover { background: #1e3a5f; }
        .btn-action { padding: 5px 10px; border: none; border-radius: 4px; font-size: 12px; font-weight: 600; cursor: pointer; margin-right: 5px; color: white; }
        .btn-reschedule { background: #17a2b8; }
        .btn-reschedule:hover { background: #138496; }
        .btn-cancel { background: #dc3545; }
        .btn-cancel:hover { background: #c82333; }
        .appointments-table { width: 100%; border-collapse: collapse; font-size: 14px; }
        .appointments-table th { background: #0b1a33; color: white; padding: 12px; text-align: left; font-weight: 600; }
        .appointments-table td { padding: 12px; border-bottom: 1px solid #e2e8f0; }
        .appointments-table tr:hover { background: #f8fafd; }
        .status-badge { display: inline-block; padding: 4px 10px; border-radius: 4px; font-size: 12px; font-weight: 600; }
        .status-pending { background: #fff3cd; color: #856404; }
        .status-confirmed { background: #d4edda; color: #155724; }
        .status-cancelled { background: #f8d7da; color: #721c24; }
        .status-completed { background: #d1ecf1; color: #0c5460; }
        .role-badge { display: inline-block; padding: 2px 8px; border-radius: 12px; font-size: 10px; font-weight: 600; margin-left: 5px; background: #e6f0ff; color: #0b1a33; }
        .role-badge.specialist { background: #f3e8ff; color: #6b21a8; }
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); }
        .modal-content { background: white; margin: 10% auto; padding: 30px; border-radius: 8px; width: 90%; max-width: 500px; border-left: 4px solid #0b1a33; }
        .modal-header { font-size: 20px; font-weight: 700; margin-bottom: 20px; color: #0b1a33; }
        .modal-close { float: right; font-size: 28px; font-weight: bold; cursor: pointer; color: #5a6f8c; }
        .modal-close:hover { color: #0b1a33; }
        .empty-state { text-align: center; padding: 40px; color: #5a6f8c; }
        .status-message { display: inline-block; padding: 4px 10px; border-radius: 4px; font-size: 12px; font-weight: 600; }
        .status-message-completed { background: #d1ecf1; color: #0c5460; }
        .status-message-cancelled { background: #f8d7da; color: #721c24; }
        .status-message-confirmed { background: #d4edda; color: #155724; }
        .status-message-past { background: #e2e8f0; color: #4a5568; }
        .status-message-default { color: #5a6f8c; font-size: 12px; }
        
        .available-slots-container {
            margin-top: 10px;
            margin-bottom: 20px;
        }
        
        .available-slots-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 10px;
            margin-top: 10px;
        }
        
        .slot-button {
            background: #e6f0ff;
            border: 1px solid #b8d1ff;
            padding: 12px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            color: #0b1a33;
            transition: all 0.2s;
            text-align: center;
        }
        
        .slot-button:hover {
            background: #0b1a33;
            color: white;
            border-color: #0b1a33;
        }
        
        .slot-button.selected {
            background: #ffd966;
            color: #0b1a33;
            border-color: #ffd966;
        }
        
        .loading-slots {
            padding: 15px;
            text-align: center;
            background: #f8fafd;
            border-radius: 8px;
            color: #5a6f8c;
        }
        
        .no-slots-message {
            padding: 15px;
            text-align: center;
            background: #fff3cd;
            border-radius: 8px;
            color: #856404;
        }
        
        .error-slots-message {
            padding: 15px;
            text-align: center;
            background: #fee2e2;
            border-radius: 8px;
            color: #dc2626;
        }
        
        .slot-info-message {
            font-size: 12px;
            color: #5a6f8c;
            margin-top: 8px;
        }
    </style>
</head>
<body>

<div class="appointments-wrapper">
    
    <!-- Sidebar navigation -->
    <div class="sidebar">
        <div class="sidebar-header">
            <h2>PCASS</h2>
            <p>Family Portal</p>
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
        
        <?php if ($success_message): ?>
            <div class="alert alert-success">✓ <?= htmlspecialchars($success_message); ?></div>
        <?php endif; ?>
        
        <?php if ($error_message): ?>
            <div class="alert alert-error">⚠️ <?= htmlspecialchars($error_message); ?></div>
        <?php endif; ?>
        
        <div class="type-tabs">
            <a href="?type=immunization" class="type-tab <?= $appointment_type == 'immunization' ? 'active' : '' ?>">
                Immunization
            </a>
            <a href="?type=specialist" class="type-tab <?= $appointment_type == 'specialist' ? 'active' : '' ?>">
                Specialist Review
            </a>
        </div>
        
        <!-- Booking form -->
        <div class="appointment-card">
            <h2>Book for <?= $appointment_type_label ?></h2>
            
            <form method="POST" id="bookingForm">
                <input type="hidden" name="appointment_type" value="<?= $appointment_type ?>">
                
                <div class="form-row">
                    <label>Select Doctor *</label>
                    <select name="doctor_id" id="doctorSelect" class="form-control" required>
                        <option value="">-- Select Doctor --</option>
                        <?php if ($doctors_list && $doctors_list->num_rows > 0): ?>
                            <?php while ($doctor = $doctors_list->fetch_assoc()): ?>
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
                    <input type="date" name="appointment_date" id="appointmentDate" class="form-control" 
                           min="<?= date('Y-m-d'); ?>" required>
                </div>
                
                <div class="form-row">
                    <label>Available Time Slots</label>
                    <div id="availableSlotsContainer" class="available-slots-container">
                        <div class="slot-info-message">Select a doctor and date to see available slots</div>
                    </div>
                </div>
                
                <div class="form-row">
                    <label>Appointment Time *</label>
                    <input type="time" name="appointment_time" id="appointmentTime" class="form-control" 
                           min="<?= date('H:i', strtotime(CLINIC_OPEN_TIME)) ?>" 
                           max="<?= date('H:i', strtotime(CLINIC_CLOSE_TIME)) ?>" 
                           step="<?= APPOINTMENT_SLOT_MINUTES * 60 ?>" required readonly style="background:#f8fafd;">
                    <div class="form-hint">
                        Click on an available slot above to select | Clinic hours: <?= date('g:i A', strtotime(CLINIC_OPEN_TIME)) ?> - <?= date('g:i A', strtotime(CLINIC_CLOSE_TIME)) ?>
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
            
            <?php if ($appointments_list && $appointments_list->num_rows > 0): ?>
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
                        <?php while ($appointment = $appointments_list->fetch_assoc()): 
                            // Determine if appointment can be modified (only future appointments)
                            $can_modify = false;

                            if ($appointment['status'] === 'Pending') {
                                // Combine date and time to check if appointment is in the future
                                $appointment_datetime = strtotime($appointment['appointment_date'] . ' ' . $appointment['appointment_time']);
                                $current_datetime = time();
    
                                if ($appointment_datetime > $current_datetime) {
                                    $can_modify = true;
                                }
                            }
                            $role_class = ($appointment['doctor_role'] ?? 'immunization') == 'specialist' ? 'specialist' : '';
                            $role_text = ($appointment['doctor_role'] ?? 'immunization') == 'specialist' ? 'Specialist' : 'Immunization';
    
                            // Check if appointment is in the past (including time for today)
                            $appointment_datetime = strtotime($appointment['appointment_date'] . ' ' . $appointment['appointment_time']);
                            $current_datetime = time();
                            $is_past = ($appointment_datetime < $current_datetime);
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
                                        <button class="btn-action btn-reschedule" 
                                                onclick="openRescheduleModal(<?= $appointment['appointment_id']; ?>, '<?= $appointment['appointment_date']; ?>', '<?= $appointment['appointment_time']; ?>', <?= $appointment['doctor_id']; ?>)">
                                            Reschedule
                                        </button>
                                        <a href="?cancel=<?= $appointment['appointment_id']; ?>" 
                                           class="btn-action btn-cancel" 
                                           onclick="return confirm('Are you sure you want to cancel this appointment?')">
                                            Cancel
                                        </a>
                                    <?php else: ?>
                                        <?php if ($appointment['status'] === 'Completed'): ?>
                                            <span class="status-message status-message-completed">Completed</span>
                                        <?php elseif ($appointment['status'] === 'Cancelled'): ?>
                                            <span class="status-message status-message-cancelled">Cancelled</span>
                                        <?php elseif ($appointment['status'] === 'Confirmed'): ?>
                                            <span class="status-message status-message-confirmed">Confirmed - Contact clinic to reschedule</span>
                                        <?php elseif ($is_past && $appointment['status'] === 'Pending'): ?>
                                            <span class="status-message status-message-past">Past appointment - Please rebook</span>
                                        <?php elseif ($is_past): ?>
                                            <span class="status-message status-message-past">Past appointment</span>
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
        <div class="modal-header">Reschedule Appointment</div>
        
        <form method="POST" id="rescheduleForm">
            <input type="hidden" id="reschedule_id" name="appointment_id">
            <input type="hidden" id="reschedule_doctor_id" name="reschedule_doctor_id">
            
            <div class="form-row">
                <label>New Date *</label>
                <input type="date" id="new_date" name="new_date" class="form-control" 
                       min="<?= date('Y-m-d'); ?>" required>
            </div>
            
            <div class="form-row">
                <label>Available Time Slots</label>
                <div id="rescheduleSlotsContainer" class="available-slots-container">
                    <div class="slot-info-message">Select a date first to see available slots</div>
                </div>
            </div>
            
            <div class="form-row">
                <label>New Time *</label>
                <input type="time" id="new_time" name="new_time" class="form-control" 
                       min="<?= date('H:i', strtotime(CLINIC_OPEN_TIME)) ?>" 
                       max="<?= date('H:i', strtotime(CLINIC_CLOSE_TIME)) ?>" 
                       step="<?= APPOINTMENT_SLOT_MINUTES * 60 ?>" required readonly style="background:#f8fafd;">
                <div class="form-hint">
                    Click on an available slot above to select | Clinic hours: <?= date('g:i A', strtotime(CLINIC_OPEN_TIME)) ?> - <?= date('g:i A', strtotime(CLINIC_CLOSE_TIME)) ?>
                </div>
            </div>
            
            <button type="submit" name="reschedule_appointment" class="btn-primary">
                Confirm Reschedule
            </button>
        </form>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
$(document).ready(function() {
    $('#doctorSelect, #appointmentDate').on('change', function() {
        fetchAvailableTimeSlots();
    });
});

function fetchAvailableTimeSlots() {
    var selectedDoctorId = $('#doctorSelect').val();
    var selectedAppointmentDate = $('#appointmentDate').val();
    var slotsContainer = $('#availableSlotsContainer');
    
    if (selectedDoctorId && selectedAppointmentDate) {
        slotsContainer.html('<div class="loading-slots">Loading available time slots...</div>');
        
        $.ajax({
            url: 'appointments.php?action=get_available_slots',
            type: 'GET',
            data: {
                doctor_id: selectedDoctorId,
                appointment_date: selectedAppointmentDate,
                exclude_id: 0
            },
            dataType: 'json',
            success: function(response) {
                if (response.success && response.available_slots.length > 0) {
                    var slotsHtml = '<div class="available-slots-grid">';
                    $.each(response.available_slots, function(index, slot) {
                        slotsHtml += '<button type="button" class="slot-button" data-time-value="' + slot.value + '" onclick="selectTimeSlot(\'' + slot.value + '\', \'' + slot.display + '\')">' + slot.display + '</button>';
                    });
                    slotsHtml += '</div>';
                    slotsContainer.html(slotsHtml);
                } else if (response.success && response.available_slots.length === 0) {
                    slotsContainer.html('<div class="no-slots-message">No available slots for this date. Please choose another date.</div>');
                } else {
                    slotsContainer.html('<div class="error-slots-message">Error loading slots. Please try again.</div>');
                }
            },
            error: function() {
                slotsContainer.html('<div class="error-slots-message">Could not load available slots. Please check your connection.</div>');
            }
        });
    } else if (selectedDoctorId && !selectedAppointmentDate) {
        slotsContainer.html('<div class="slot-info-message">Please select a date to see available slots</div>');
    } else if (!selectedDoctorId && selectedAppointmentDate) {
        slotsContainer.html('<div class="slot-info-message">Please select a doctor to see available slots</div>');
    } else {
        slotsContainer.html('<div class="slot-info-message">Select a doctor and date to see available slots</div>');
    }
}

function selectTimeSlot(timeValue, timeDisplay) {
    var formattedTime = timeValue.substring(0, 5);
    $('#appointmentTime').val(formattedTime);
    $('.slot-button').removeClass('selected');
    $('.slot-button[data-time-value="' + timeValue + '"]').addClass('selected');
    $('#appointmentTime').css('background', '#e6f0ff');
}

var currentRescheduleDoctorId = 0;
var currentRescheduleAppointmentId = 0;

function openRescheduleModal(appointmentId, currentDate, currentTime, doctorId) {
    currentRescheduleDoctorId = doctorId;
    currentRescheduleAppointmentId = appointmentId;
    
    document.getElementById('reschedule_id').value = appointmentId;
    document.getElementById('reschedule_doctor_id').value = doctorId;
    document.getElementById('new_date').value = currentDate;
    document.getElementById('new_time').value = currentTime;
    document.getElementById('rescheduleModal').style.display = 'block';
    
    $('#rescheduleSlotsContainer').html('<div class="slot-info-message">Select a new date to see available slots</div>');
    $('#new_time').val('');
}

$(document).on('change', '#new_date', function() {
    var newDate = $(this).val();
    var slotsContainer = $('#rescheduleSlotsContainer');
    
    if (newDate && currentRescheduleDoctorId) {
        slotsContainer.html('<div class="loading-slots">Loading available time slots...</div>');
        
        $.ajax({
            url: 'appointments.php?action=get_available_slots',
            type: 'GET',
            data: {
                doctor_id: currentRescheduleDoctorId,
                appointment_date: newDate,
                exclude_id: currentRescheduleAppointmentId
            },
            dataType: 'json',
            success: function(response) {
                if (response.success && response.available_slots.length > 0) {
                    var slotsHtml = '<div class="available-slots-grid">';
                    $.each(response.available_slots, function(index, slot) {
                        slotsHtml += '<button type="button" class="slot-button" data-time-value="' + slot.value + '" onclick="selectRescheduleTimeSlot(\'' + slot.value + '\', \'' + slot.display + '\')">' + slot.display + '</button>';
                    });
                    slotsHtml += '</div>';
                    slotsContainer.html(slotsHtml);
                } else if (response.success && response.available_slots.length === 0) {
                    slotsContainer.html('<div class="no-slots-message">No available slots for this date. Please choose another date.</div>');
                } else {
                    slotsContainer.html('<div class="error-slots-message">Error loading slots. Please try again.</div>');
                }
            },
            error: function() {
                slotsContainer.html('<div class="error-slots-message">Could not load available slots. Please check your connection.</div>');
            }
        });
    } else {
        slotsContainer.html('<div class="slot-info-message">Select a date to see available slots</div>');
    }
});

function selectRescheduleTimeSlot(timeValue, timeDisplay) {
    var formattedTime = timeValue.substring(0, 5);
    $('#new_time').val(formattedTime);
    $('.slot-button').removeClass('selected');
    $('.slot-button[data-time-value="' + timeValue + '"]').addClass('selected');
    $('#new_time').css('background', '#e6f0ff');
}

function closeRescheduleModal() {
    document.getElementById('rescheduleModal').style.display = 'none';
}

window.onclick = function(event) {
    var modal = document.getElementById('rescheduleModal');
    if (event.target == modal) {
        closeRescheduleModal();
    }
}
</script>

</body>
</html>
<?php $conn->close(); ?>