<?php
session_start();
require_once __DIR__ . "/config/db.php";

// Security check - only immunization doctors
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'doctor' || $_SESSION['doctor_role'] !== 'immunization') {
    header("Location: index.php");
    exit();
}

$doctor_id = $_SESSION['user_id'];
$child_id = $_GET['child_id'] ?? 0;

// Get child details with guardian information
$child_query = "
    SELECT c.*, g.id as guardian_id, g.name as guardian_name 
    FROM children c 
    LEFT JOIN guardians g ON c.guardian_id = g.id 
    WHERE c.child_id = $child_id
";
$child = $conn->query($child_query)->fetch_assoc();

if (!$child) {
    header("Location: doctor_immunization_patients.php");
    exit();
}

// Get list of specialists for assignment
$specialists = $conn->query("SELECT doctor_id, full_name, specialization FROM doctors WHERE doctor_role = 'specialist' AND status = 'Active' ORDER BY full_name");

// Handle flag submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_flag'])) {
    $flag_type = $_POST['flag_type'];
    $reason = $_POST['reason'];
    $notes = $_POST['notes'];
    $assigned_to = $_POST['assigned_to'] ?: null;
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // Insert flag
        $stmt = $conn->prepare("INSERT INTO flags (child_id, flagged_by, assigned_to, flag_type, reason, notes, status, created_at) VALUES (?, ?, ?, ?, ?, ?, 'new', NOW())");
        $stmt->bind_param("iiisss", $child_id, $doctor_id, $assigned_to, $flag_type, $reason, $notes);
        
        if ($stmt->execute()) {
            $flag_id = $conn->insert_id;
            
            // ALWAYS send notification to guardian if they exist (mandatory)
            if (!empty($child['guardian_id'])) {
                $child_name = $child['first_name'] . ' ' . $child['last_name'];
                
                // Create title based on flag type
                $type_labels = [
                    'growth' => 'Growth Concern',
                    'milestone' => 'Developmental Milestone Review',
                    'vaccine' => 'Vaccine-Related Concern',
                    'multiple' => 'Multiple Concerns',
                    'other' => 'Specialist Review'
                ];
                $title = $type_labels[$flag_type] . ' for ' . $child_name;
                
                // Create message
                $message = "During an immunization checkup, our doctor has identified that " . $child_name . " needs to be reviewed by a specialist.\n\n";
                $message .= "Reason: " . $reason . "\n";
                if (!empty($notes)) {
                    $message .= "Additional Notes: " . $notes . "\n\n";
                }
                $message .= "A specialist will review your child's case";
                
                // Insert notification
                $notif_stmt = $conn->prepare("
                    INSERT INTO notifications 
                    (guardian_id, child_id, notification_type, title, message, related_id, is_read, created_at) 
                    VALUES (?, ?, 'flag', ?, ?, ?, 0, NOW())
                ");
                $notif_stmt->bind_param("iissi", $child['guardian_id'], $child_id, $title, $message, $flag_id);
                $notif_stmt->execute();
            }
            
            $conn->commit();
            $message = "Child flagged successfully for specialist review.";
            if (!empty($child['guardian_id'])) {
                $message .= " Parent has been notified.";
            } else {
                $message .= " Note: No parent/guardian is linked to this child.";
            }
            $msg_type = "success";
        } else {
            throw new Exception("Error creating flag");
        }
    } catch (Exception $e) {
        $conn->rollback();
        $message = "Error flagging child.";
        $msg_type = "error";
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Flag Child for Review</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body { background:#f0f4fc; font-family:Arial; }
        .wrapper { display:flex; }
        .main { margin-left:260px; padding:30px; flex:1; }
        
        .child-header { background:white; padding:25px; margin-bottom:25px; border-left:4px solid #0b1a33; }
        .child-name { font-size:24px; font-weight:700; color:#0b1a33; }
        
        .card { background:white; padding:25px; border-left:4px solid #0b1a33; }
        .card-header { margin-bottom:20px; }
        .card-header h2 { color:#0b1a33; font-size:20px; }
        
        .form-group { margin-bottom:20px; }
        .form-group label { display:block; margin-bottom:5px; color:#1e3a5f; font-weight:600; }
        .form-control { width:100%; padding:12px; border:1px solid #e2e8f0; border-radius:4px; }
        select.form-control { background:white; }
        textarea.form-control { min-height:100px; }
        
        .btn { background:#0b1a33; color:white; padding:12px 25px; border:none; border-radius:6px; cursor:pointer; }
        .btn-secondary { background:#6c757d; margin-left:10px; }
        
        .alert { padding:15px; margin-bottom:20px; border-radius:6px; }
        .alert.success { background:#d4edda; color:#155724; border-left:4px solid #28a745; }
        .alert.error { background:#f8d7da; color:#721c24; border-left:4px solid #dc3545; }
        
        .back-link { display:inline-block; margin-bottom:20px; color:#0b1a33; text-decoration:none; }
        
        .info-box { background:#e6f0ff; padding:15px; border-left:4px solid #0b1a33; margin-bottom:20px; }
        
        .parent-badge {
            background: #e6f0ff;
            padding: 5px 10px;
            border-radius: 4px;
            font-size: 13px;
            color: #0b1a33;
            margin-top: 10px;
        }
        
        .notification-badge {
            background: #d4edda;
            color: #155724;
            padding: 10px 15px;
            border-radius: 4px;
            font-size: 13px;
            margin-top: 15px;
            border-left: 3px solid #28a745;
        }
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
    
    <!-- Main Content -->
    <div class="main">
        <a href="doctor_immunization_patients.php" class="back-link">← Back to Patients</a>
        
        <div class="child-header">
            <div class="child-name"><?= htmlspecialchars($child['first_name'] . ' ' . $child['last_name']) ?></div>
            <?php if (!empty($child['guardian_name'])): ?>
            <div class="parent-badge">Parent: <?= htmlspecialchars($child['guardian_name']) ?></div>
            <?php else: ?>
            <div class="parent-badge" style="background:#fff3cd; color:#856404;">No parent/guardian linked</div>
            <?php endif; ?>
        </div>
        
        <?php if (isset($message)): ?>
        <div class="alert <?= $msg_type ?>"><?= $message ?></div>
        <?php endif; ?>
        
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
                    <label>Assign to Specialist (optional)</label>
                    <select name="assigned_to" class="form-control">
                        <option value="">-- select specialist --</option>
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
    </div>
</div>
</body>
</html>