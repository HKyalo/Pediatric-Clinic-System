<?php
session_start();
require_once __DIR__ . "/config/db.php";

// Security check
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'doctor') {
    header("Location: index.php");
    exit();
}

$doctor_id = $_SESSION['user_id'];
$message = "";
$msg_type = "";
$active_tab = $_GET['tab'] ?? 'profile'; // profile, password, blocks

// Clinic hours 
$CLINIC_OPEN_TIME = "07:00";
$CLINIC_CLOSE_TIME = "18:00";

// ============================================
// HANDLE PASSWORD CHANGE - UPDATED WITH STRENGTH VALIDATION
// ============================================
if (isset($_POST['change_password'])) {
    $current = $_POST['current_password'];
    $new = $_POST['new_password'];
    $confirm = $_POST['confirm_password'];
    
    // Get current password
    $result = $conn->query("SELECT password FROM doctors WHERE doctor_id = $doctor_id");
    $hash = $result->fetch_assoc()['password'];
    
    if (!password_verify($current, $hash)) {
        $message = "Current password is incorrect.";
        $msg_type = "error";
    }
    // Password strength validation
    elseif (strlen($new) < 8) {
        $message = "Password must be at least 8 characters long.";
        $msg_type = "error";
    }
    elseif (!preg_match('/[A-Z]/', $new)) {
        $message = "Password must contain at least one uppercase letter.";
        $msg_type = "error";
    }
    elseif (!preg_match('/[a-z]/', $new)) {
        $message = "Password must contain at least one lowercase letter.";
        $msg_type = "error";
    }
    elseif (!preg_match('/[0-9]/', $new)) {
        $message = "Password must contain at least one number.";
        $msg_type = "error";
    }
    elseif (!preg_match('/[!@#$%^&*()_+\-=\[\]{};\':"\\\\|,.<>\/?]/', $new)) {
        $message = "Password must contain at least one special character (!@#$%^&*).";
        $msg_type = "error";
    }
    elseif ($new !== $confirm) {
        $message = "New passwords do not match.";
        $msg_type = "error";
    } else {
        $new_hash = password_hash($new, PASSWORD_DEFAULT);
        $conn->query("UPDATE doctors SET password = '$new_hash' WHERE doctor_id = $doctor_id");
        $message = "Password changed successfully!";
        $msg_type = "success";
    }
}

// ============================================
// HANDLE BLOCK SLOT (with clinic hours validation)
// ============================================
if (isset($_POST['block_slot'])) {
    $block_date = $_POST['block_date'];
    $block_time = $_POST['block_time'];
    $reason = $_POST['reason'] ?? '';
    
    // Validate time is within clinic hours
    if ($block_time < $CLINIC_OPEN_TIME || $block_time > $CLINIC_CLOSE_TIME) {
        $message = "You can only block slots within clinic hours (7:00 AM - 6:00 PM).";
        $msg_type = "error";
    } else {
        // Check if already blocked
        $check = $conn->prepare("SELECT block_id FROM blocked_slots WHERE doctor_id = ? AND block_date = ? AND block_time = ?");
        $check->bind_param("iss", $doctor_id, $block_date, $block_time);
        $check->execute();
        
        if ($check->get_result()->num_rows > 0) {
            $message = "This slot is already blocked!";
            $msg_type = "error";
        } else {
            $stmt = $conn->prepare("INSERT INTO blocked_slots (doctor_id, block_date, block_time, reason) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("isss", $doctor_id, $block_date, $block_time, $reason);
            
            if ($stmt->execute()) {
                $message = "Slot blocked successfully!";
                $msg_type = "success";
            } else {
                $message = "Error blocking slot.";
                $msg_type = "error";
            }
            $stmt->close();
        }
    }
}

// ============================================
// HANDLE UNBLOCK SLOT
// ============================================
if (isset($_GET['unblock'])) {
    $block_id = $_GET['unblock'];
    $conn->query("DELETE FROM blocked_slots WHERE block_id = $block_id AND doctor_id = $doctor_id");
    $message = "Slot unblocked.";
    $msg_type = "success";
}

// ============================================
// GET DOCTOR DATA (view only)
// ============================================
$doctor = $conn->query("SELECT * FROM doctors WHERE doctor_id = $doctor_id")->fetch_assoc();

// Get blocked slots
$blocked = $conn->query("SELECT * FROM blocked_slots WHERE doctor_id = $doctor_id ORDER BY block_date DESC, block_time DESC");
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Doctor Profile - PCASS</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <script src="assets/js/script.js"></script>
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body { background:#f0f4fc; font-family:Arial; }
        .wrapper { display:flex; }
        .main { margin-left:260px; padding:30px; flex:1; }
        
        h1 { color:#0b1a33; margin-bottom:20px; }
        
        .tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            border-bottom: 2px solid #e2e8f0;
            padding-bottom: 10px;
        }
        
        .tab {
            padding: 10px 20px;
            text-decoration: none;
            color: #5a6f8c;
            font-weight: 600;
            border-radius: 4px 4px 0 0;
        }
        
        .tab.active {
            color: #0b1a33;
            border-bottom: 3px solid #0b1a33;
        }
        
        .card {
            background: white;
            border-radius: 8px;
            padding: 25px;
            margin-bottom: 25px;
            border-left: 4px solid #0b1a33;
        }
        
        .card h2 {
            color: #0b1a33;
            font-size: 18px;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #e2e8f0;
        }
        
        .info-row {
            display: flex;
            padding: 12px 0;
            border-bottom: 1px solid #f0f4fc;
        }
        
        .info-label {
            font-weight: 600;
            color: #5a6f8c;
            width: 150px;
        }
        
        .info-value {
            color: #1e293b;
            flex: 1;
        }
        
        .badge {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .badge.immunization {
            background: #e6f0ff;
            color: #0b1a33;
        }
        
        .badge.specialist {
            background: #f3e8ff;
            color: #6b21a8;
        }
        
        .badge.active {
            background: #d4edda;
            color: #155724;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-group label {
            display: block;
            font-weight: 600;
            color: #1e3a5f;
            margin-bottom: 5px;
        }
        
        .form-control {
            width: 100%;
            padding: 10px;
            border: 1px solid #e2e8f0;
            border-radius: 4px;
        }
        
        /* Password Strength Styles */
        .password-strength {
            margin-top: 15px;
            margin-bottom: 15px;
            padding: 15px;
            background: #f8fafd;
            border-radius: 6px;
            border: 1px solid #e2e8f0;
        }
        
        .strength-item {
            color: #5a6f8c;
            margin: 8px 0;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 13px;
            transition: color 0.2s ease;
        }
        
        .strength-item.valid {
            color: #10b981;
        }
        
        .strength-item.invalid {
            color: #ef4444;
        }
        
        .strength-item span {
            font-size: 14px;
            width: 18px;
            height: 18px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
        }
        
        .strength-item.valid span::before {
            content: "✓";
        }
        
        .strength-item.invalid span::before {
            content: "○";
        }
        
        .password-hint {
            font-size: 11px;
            color: #5a6f8c;
            margin-top: 10px;
            padding-top: 10px;
            border-top: 1px solid #e2e8f0;
            font-style: italic;
        }
        
        button:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        .btn:disabled {
            background: #5a6f8c;
            opacity: 0.6;
            cursor: not-allowed;
        }
        
        .btn:disabled:hover {
            background: #5a6f8c;
        }
        
        .btn {
            background: #0b1a33;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 600;
        }
        
        .btn-success {
            background: #10b981;
        }
        
        .btn-danger {
            background: #dc3545;
            padding: 5px 10px;
            font-size: 12px;
            text-decoration: none;
            display: inline-block;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
        }
        
        th {
            background: #0b1a33;
            color: white;
            padding: 10px;
            text-align: left;
        }
        
        td {
            padding: 10px;
            border-bottom: 1px solid #e2e8f0;
        }
        
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
        }
        
        .alert.success {
            background: #d4edda;
            color: #155724;
            border-left: 4px solid #28a745;
        }
        
        .alert.error {
            background: #f8d7da;
            color: #721c24;
            border-left: 4px solid #dc3545;
        }
        
        .note {
            background: #fff3cd;
            padding: 10px 15px;
            border-left: 4px solid #ffc107;
            font-size: 13px;
            color: #856404;
            margin-bottom: 20px;
        }
        
        .small {
            font-size: 12px;
            color: #5a6f8c;
            margin-top: 3px;
        }
    </style>
</head>
<body>
<div class="wrapper">
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-header">
            <h2>PCASS</h2>
            <p><?= $doctor['doctor_role'] == 'specialist' ? 'Specialist' : 'Immunization Doctor' ?></p>
        </div>
        <div class="nav">
            <ul>
                <li><a href="<?= $doctor['doctor_role'] == 'specialist' ? 'doctor_specialist_dashboard.php' : 'doctor_immunization_dashboard.php' ?>">Dashboard</a></li>
                <li><a href="<?= $doctor['doctor_role'] == 'specialist' ? 'doctor_specialist_appointments.php' : 'doctor_immunization_appointments.php' ?>">My Appointments</a></li>
                <li><a href="<?= $doctor['doctor_role'] == 'specialist' ? 'doctor_specialist_patients.php' : 'doctor_immunization_patients.php' ?>">My Patients</a></li>
                <li><a href="doctor_profile.php" class="active">Profile</a></li>
                <li><a href="logout.php">Logout</a></li>
            </ul>
        </div>
    </div>
    
    <!-- Main Content -->
    <div class="main">
        <h1>Profile & Settings</h1>
        
        <?php if ($message): ?>
            <div class="alert <?= $msg_type ?>"><?= $message ?></div>
        <?php endif; ?>
        
        <!-- Tabs -->
        <div class="tabs">
            <a href="?tab=profile" class="tab <?= $active_tab == 'profile' ? 'active' : '' ?>">Profile</a>
            <a href="?tab=password" class="tab <?= $active_tab == 'password' ? 'active' : '' ?>">Change Password</a>
            <a href="?tab=blocks" class="tab <?= $active_tab == 'blocks' ? 'active' : '' ?>">Block Slots</a>
        </div>
        
        <!-- Profile Tab (VIEW ONLY) -->
        <?php if ($active_tab == 'profile'): ?>
        <div class="card">
            <h2>My Information</h2>
            
            <div class="info-row">
                <span class="info-label">Full Name</span>
                <span class="info-value"><strong><?= htmlspecialchars($doctor['full_name']) ?></strong></span>
            </div>
            
            <div class="info-row">
                <span class="info-label">Email</span>
                <span class="info-value"><?= htmlspecialchars($doctor['email']) ?></span>
            </div>
            
            <div class="info-row">
                <span class="info-label">Phone</span>
                <span class="info-value"><?= htmlspecialchars($doctor['phone'] ?? 'Not provided') ?></span>
            </div>
            
            <div class="info-row">
                <span class="info-label">Role</span>
                <span class="info-value">
                    <span class="badge <?= $doctor['doctor_role'] ?>">
                        <?= ucfirst($doctor['doctor_role']) ?>
                    </span>
                </span>
            </div>
            
            <div class="info-row">
                <span class="info-label">Specialization</span>
                <span class="info-value"><?= htmlspecialchars($doctor['specialization'] ?? 'General') ?></span>
            </div>
            
            <div class="info-row">
                <span class="info-label">License Number</span>
                <span class="info-value"><?= htmlspecialchars($doctor['license_number'] ?? 'Not provided') ?></span>
            </div>
            
            <div class="info-row">
                <span class="info-label">Qualifications</span>
                <span class="info-value"><?= htmlspecialchars($doctor['qualification'] ?? 'Not provided') ?></span>
            </div>
            
            <div class="info-row">
                <span class="info-label">Experience</span>
                <span class="info-value"><?= $doctor['years_of_experience'] ?? 0 ?> years</span>
            </div>
            
            <div class="info-row">
                <span class="info-label">Employment Type</span>
                <span class="info-value"><?= $doctor['employment_type'] ?? 'Full-Time' ?></span>
            </div>
            
            <div class="info-row">
                <span class="info-label">Status</span>
                <span class="info-value">
                    <span class="badge <?= strtolower($doctor['status'] ?? 'active') ?>">
                        <?= $doctor['status'] ?? 'Active' ?>
                    </span>
                </span>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Password Tab (CAN EDIT) - UPDATED WITH STRENGTH VALIDATION -->
        <?php if ($active_tab == 'password'): ?>
        <div class="card">
            <h2>Change Password</h2>
            <form method="POST" onsubmit="return validatePassword(
                document.getElementById('new_password').value,
                document.getElementById('confirm_password').value
            )">
                <div class="form-group">
                    <label>Current Password</label>
                    <input type="password" name="current_password" id="current_password" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label>New Password</label>
                    <input type="password" name="new_password" id="new_password" class="form-control" 
                           required minlength="8" onkeyup="checkPasswordStrength(this.value)">
                </div>
                
                <div class="form-group">
                    <label>Confirm New Password</label>
                    <input type="password" name="confirm_password" id="confirm_password" class="form-control" required minlength="8">
                </div>
                
                <!-- Password Strength Indicator -->
                <div class="password-strength">
                    <div id="req-length" class="strength-item invalid">
                        <span></span> At least 8 characters
                    </div>
                    <div id="req-upper" class="strength-item invalid">
                        <span></span> At least 1 uppercase letter (A-Z)
                    </div>
                    <div id="req-lower" class="strength-item invalid">
                        <span></span> At least 1 lowercase letter (a-z)
                    </div>
                    <div id="req-number" class="strength-item invalid">
                        <span></span> At least 1 number (0-9)
                    </div>
                    <div id="req-special" class="strength-item invalid">
                        <span></span> At least 1 special character (!@#$%^&*)
                    </div>
                    <div class="password-hint">
                        Use a strong password with a mix of characters
                    </div>
                </div>
                
                <button type="submit" name="change_password" id="password-btn" class="btn" disabled>
                    Update Password
                </button>
            </form>
        </div>
        <?php endif; ?>
        
        <!-- Block Slots Tab (CAN EDIT with clinic hours) -->
        <?php if ($active_tab == 'blocks'): ?>
        <div class="card">
            <h2>Block Unavailable Slots</h2>
            <p style="margin-bottom:15px; color:#5a6f8c;">
                Block times when you are not available for appointments.<br>
                <strong>Clinic hours:</strong> <?= date('g:i A', strtotime($CLINIC_OPEN_TIME)) ?> - <?= date('g:i A', strtotime($CLINIC_CLOSE_TIME)) ?>
            </p>
            <form method="POST">
                <div class="form-group">
                    <label>Date</label>
                    <input type="date" name="block_date" class="form-control" min="<?= date('Y-m-d') ?>" required>
                </div>
                <div class="form-group">
                    <label>Time</label>
                    <input type="time" name="block_time" class="form-control" 
                           min="<?= $CLINIC_OPEN_TIME ?>" max="<?= $CLINIC_CLOSE_TIME ?>" 
                           step="1800" required>
                    <div class="small">Only within clinic hours (<?= date('g:i A', strtotime($CLINIC_OPEN_TIME)) ?> - <?= date('g:i A', strtotime($CLINIC_CLOSE_TIME)) ?>)</div>
                </div>
                <div class="form-group">
                    <label>Reason (optional)</label>
                    <input type="text" name="reason" class="form-control" placeholder="e.g., Staff meeting, Conference, Personal">
                </div>
                <button type="submit" name="block_slot" class="btn btn-success">Block This Slot</button>
            </form>
        </div>
        
        <div class="card">
            <h2>Currently Blocked Slots</h2>
            <?php if ($blocked->num_rows > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Time</th>
                            <th>Reason</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($b = $blocked->fetch_assoc()): ?>
                        <tr>
                            <td><?= date('M d, Y', strtotime($b['block_date'])) ?></td>
                            <td><?= date('g:i A', strtotime($b['block_time'])) ?></td>
                            <td><?= htmlspecialchars($b['reason'] ?: '-') ?></td>
                            <td>
                                <a href="?tab=blocks&unblock=<?= $b['block_id'] ?>" class="btn-danger" style="padding:5px 10px; color:white; text-decoration:none;" onclick="return confirm('Unblock this slot?')">Unblock</a>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p style="color:#5a6f8c;">No blocked slots.</p>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        
    </div>
</div>

<script>
// Initialize password validation when page loads
window.addEventListener('DOMContentLoaded', function() {
    // Only initialize if on password tab and fields exist
    if (document.getElementById('new_password')) {
        // Check if our script.js functions are available
        if (typeof initPasswordValidation === 'function') {
            initPasswordValidation('new_password', 'confirm_password', 'password-btn');
        } else {
            console.warn('Password validation functions not loaded. Make sure script.js is included.');
            
            // Fallback: enable button anyway
            const passwordBtn = document.getElementById('password-btn');
            if (passwordBtn) {
                passwordBtn.disabled = false;
            }
        }
    }
});
</script>

</body>
</html>
<?php $conn->close(); ?>