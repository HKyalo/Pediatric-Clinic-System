<?php
session_start();
require_once __DIR__ . "/config/db.php";

// ============================================
// SECURITY CHECK - Only logged-in guardians
// ============================================
if (!isset($_SESSION['guardian_id'])) {
    header("Location: index.php");
    exit();
}

$guardian_id = $_SESSION['guardian_id'];
$selected_child_id = $_SESSION['selected_child_id'] ?? null;  

// to get notification count
$unread_count = 0;
if ($guardian_id && $selected_child_id) {
    $result = $conn->query("SELECT COUNT(*) as count FROM notifications WHERE guardian_id = $guardian_id AND child_id = $selected_child_id AND is_read = 0");
    $unread_count = $result->fetch_assoc()['count'];
}

// ============================================
// GET GUARDIAN INFORMATION WITH RELATIONSHIP
// ============================================

// Get guardian details including which email type is used for login
$query = $conn->prepare("
    SELECT name, email, 
           mother_name, mother_email, mother_phone,
           father_name, father_email, father_phone,
           guardian_name, guardian_email, guardian_relationship, guardian_phone,
           login_email_type 
    FROM guardians 
    WHERE id = ?
");
$query->bind_param("i", $guardian_id);
$query->execute();
$guardian_data = $query->get_result()->fetch_assoc();
$query->close();

// Determine the relationship of the logged-in person
$login_type = $guardian_data['login_email_type'] ?? 'mother';
$relationship_display = '';

if ($login_type === 'mother') {
    $relationship_display = 'Mother';
    $guardian_name = $guardian_data['mother_name'] ?: $guardian_data['name'];
    $guardian_email = $guardian_data['mother_email'] ?: $guardian_data['email'];
    $guardian_phone = $guardian_data['mother_phone'] ?? 'Not provided';
} elseif ($login_type === 'father') {
    $relationship_display = 'Father';
    $guardian_name = $guardian_data['father_name'] ?: $guardian_data['name'];
    $guardian_email = $guardian_data['father_email'] ?: $guardian_data['email'];
    $guardian_phone = $guardian_data['father_phone'] ?? 'Not provided';
} else {
    $relationship_display = 'Guardian';
    $guardian_name = $guardian_data['guardian_name'] ?: $guardian_data['name'];
    $guardian_email = $guardian_data['guardian_email'] ?: $guardian_data['email'];
    $guardian_phone = $guardian_data['guardian_phone'] ?? 'Not provided';
}

// ============================================
// HANDLE CHILD SELECTION
// ============================================

$child_id = $_SESSION['selected_child_id'] ?? null;

// If no child selected, check how many children this guardian has
if (!$child_id) {
    $query = $conn->prepare("SELECT child_id FROM children WHERE guardian_id = ?");
    $query->bind_param("i", $guardian_id);
    $query->execute();
    $children = $query->get_result()->fetch_all(MYSQLI_ASSOC);
    $query->close();
    
    if (count($children) === 0) {
        $has_no_children = true;
        $child_data = null;
    } elseif (count($children) === 1) {
        // Auto-select the only child
        $child_id = $children[0]['child_id'];
        $_SESSION['selected_child_id'] = $child_id;
    } else {
        // Multiple children - redirect to selector
        header("Location: select_child.php");
        exit();
    }
}

// Get total child count for switch button
$query = $conn->prepare("SELECT COUNT(*) as total FROM children WHERE guardian_id = ?");
$query->bind_param("i", $guardian_id);
$query->execute();
$total_children = $query->get_result()->fetch_assoc()['total'];
$query->close();

// ============================================
// GET SELECTED CHILD'S DETAILS
// ============================================

if ($child_id) {
    $query = $conn->prepare("
        SELECT child_id, first_name, last_name, gender, date_of_birth 
        FROM children 
        WHERE child_id = ? AND guardian_id = ?
    ");
    $query->bind_param("ii", $child_id, $guardian_id);
    $query->execute();
    $result = $query->get_result();
    
    if ($result->num_rows === 0) {
        // Invalid child - clear session and redirect
        unset($_SESSION['selected_child_id']);
        header("Location: select_child.php");
        exit();
    }
    
    $child_data = $result->fetch_assoc();
    $query->close();
    
    // Calculate age
    $birth_date = new DateTime($child_data['date_of_birth']);
    $today = new DateTime();
    $child_age = $today->diff($birth_date)->y;
    $child_age_months = $today->diff($birth_date)->m + ($child_age * 12);
    
    $child_full_name = $child_data['first_name'] . ' ' . $child_data['last_name'];
    $child_dob = $child_data['date_of_birth'];
    $child_gender = $child_data['gender'];
    $has_no_children = false;
}

// ============================================
// GET NEXT APPOINTMENT (EXCLUDING COMPLETED)
// ============================================

$next_appointment = null;

if (!$has_no_children && $child_id) {
    $table_check = $conn->query("SHOW TABLES LIKE 'appointments'");
    
    if ($table_check->num_rows > 0) {
        $query = $conn->prepare("
            SELECT a.*, d.full_name as doctor_name 
            FROM appointments a 
            LEFT JOIN doctors d ON a.doctor_id = d.doctor_id 
            WHERE a.child_id = ? 
            AND a.appointment_date >= CURDATE() 
            AND a.status != 'completed' 
            AND a.status != 'cancelled'
            ORDER BY a.appointment_date ASC, a.appointment_time ASC 
            LIMIT 1
        ");
        $query->bind_param("i", $child_id);
        $query->execute();
        $result = $query->get_result();
        
        if ($result->num_rows > 0) {
            $next_appointment = $result->fetch_assoc();
        }
        $query->close();
    }
}

// ============================================
// GET NEXT DUE VACCINE
// ============================================

$next_vaccine = null;

if ($child_id) {
    // Get child's age in weeks
    $dob = new DateTime($child_data['date_of_birth']);
    $today = new DateTime();
    $age_weeks = floor($dob->diff($today)->days / 7);
    
    // Get all vaccines
    $vaccines = $conn->query("SELECT * FROM vaccines ORDER BY min_age_weeks");
    
    // Get vaccines already given
    $given = $conn->query("SELECT vaccine_id FROM vaccination_records WHERE child_id = $child_id");
    $given_ids = [];
    while ($row = $given->fetch_assoc()) {
        $given_ids[] = $row['vaccine_id'];
    }
    
    // Find next due vaccine
    while ($vax = $vaccines->fetch_assoc()) {
        if (!in_array($vax['vaccine_id'], $given_ids) && $age_weeks < $vax['min_age_weeks']) {
            $next_vaccine = $vax;
            break;
        }
    }
}

// Get vaccine count
$vaccine_count = 0;
if ($child_id) {
    $result = $conn->query("SELECT COUNT(*) as count FROM vaccination_records WHERE child_id = $child_id");
    $vaccine_count = $result->fetch_assoc()['count'];
}
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD HTML 4.01 Strict//EN" "http://www.w3.org/TR/html4/strict.dtd">
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <title>Child Dashboard - PCASS</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        /* Main layout */
        .dashboard-wrapper {
            display: flex;
            min-height: 100vh;
        }
        
        .child-dashboard {
            margin-left: 260px;
            padding: 30px;
            background: #f0f4fc;
            flex: 1;
        }
        
        /* Header */
        .dashboard-header {
            margin-bottom: 30px;
        }
        
        .dashboard-header h1 {
            color: #0b1a33;
            font-size: 28px;
            margin-bottom: 8px;
        }
        
        .dashboard-header p {
            color: #5a6f8c;
            font-size: 14px;
        }
        
        /* Relationship badge */
        .relationship-badge {
            display: inline-block;
            background: #e6f0ff;
            color: #0b1a33;
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            margin-left: 10px;
        }
        
        /* Switch button */
        .switch-child-btn {
            background: #0b1a33;
            color: white;
            padding: 10px 20px;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 600;
            display: inline-block;
            margin-bottom: 20px;
        }
        
        .switch-child-btn:hover {
            background: #1e3a5f;
        }
        
        /* Personal Details Card - Original style */
        .personal-card {
            background: white;
            border-radius: 8px;
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: 0 2px 10px rgba(11,26,51,0.1);
            border-left: 4px solid #0b1a33;
        }
        
        .personal-card h3 {
            color: #0b1a33;
            font-size: 18px;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #e2e8f0;
        }
        
        .details-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 25px;
        }
        
        .details-half h4 {
            color: #1e3a5f;
            font-size: 15px;
            margin-bottom: 15px;
            font-weight: 600;
        }
        
        .details-half p {
            margin-bottom: 10px;
            color: #1e293b;
            font-size: 14px;
        }
        
        .details-half p strong {
            color: #0b1a33;
            width: 120px;
            display: inline-block;
        }
        
        /* Info cards - Horizontal layout */
        .info-cards {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        
        .info-row {
            background: white;
            border-radius: 8px;
            padding: 20px 25px;
            border-left: 4px solid #0b1a33;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 20px;
        }
        
        .info-content {
            display: flex;
            align-items: center;
            gap: 25px;
            flex-wrap: wrap;
        }
        
        .info-icon {
            font-size: 24px;
            color: #0b1a33;
        }
        
        .info-text {
            display: flex;
            align-items: baseline;
            gap: 15px;
            flex-wrap: wrap;
        }
        
        .info-title {
            font-weight: 700;
            color: #0b1a33;
            font-size: 16px;
            min-width: 100px;
        }
        
        .info-details {
            color: #1e293b;
            font-size: 15px;
        }
        
        .info-badge {
            background: #fff3cd;
            color: #856404;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .info-action {
            background: #0b1a33;
            color: white;
            padding: 8px 20px;
            border-radius: 4px;
            text-decoration: none;
            font-size: 13px;
            font-weight: 600;
            margin-left: auto;
        }
        
        .info-action:hover {
            background: #1e3a5f;
        }
        
        .empty-message {
            color: #5a6f8c;
            font-style: italic;
        }
        
        /* Empty state */
        .empty-state {
            text-align: center;
            padding: 40px;
            background: white;
            border-radius: 8px;
            border-left: 4px solid #0b1a33;
        }
        
        .empty-state h3 {
            color: #0b1a33;
            margin-bottom: 10px;
        }
        
        .empty-state p {
            color: #5a6f8c;
        }
    </style>
</head>
<body>

<div class="dashboard-wrapper">
    
    <!-- UPDATED SIDEBAR - Simplified Navigation -->
    <div class="sidebar">
        <div class="sidebar-header">
            <h2>PCASS</h2>
            <p>Child and Guardian Portal</p>
        </div>
        <div class="nav">
            <ul>
                <li><a href="child_dashboard.php" class="active">Dashboard</a></li>
                <li><a href="appointments.php">Appointments</a></li>
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
    
    <!-- MAIN CONTENT -->
    <div class="child-dashboard">
        
        <?php if ($has_no_children): ?>
            
            <!-- No children -->
            <div class="empty-state">
                <h2>Welcome, <?= htmlspecialchars($guardian_name) ?>!</h2>
                <h3>No Children Registered</h3>
                <p>You haven't registered any children yet.</p>
            </div>
            
        <?php else: ?>
            
            <!-- Header with relationship badge -->
            <div class="dashboard-header">
                <h1>
                    <?= htmlspecialchars($child_full_name) ?>'s Dashboard
                    <span class="relationship-badge"><?= $relationship_display ?></span>
                </h1>
                <p>
                    <?= $relationship_display ?>: <?= htmlspecialchars($guardian_name) ?> | 
                    Contact: <?= htmlspecialchars($guardian_phone) ?>
                </p>
            </div>
            
            <!-- Switch button -->
            <?php if ($total_children > 1): ?>
                <div style="text-align: right; margin-bottom: 20px;">
                    <a href="select_child.php" class="switch-child-btn">Switch Child</a>
                </div>
            <?php endif; ?>
            
            <!-- PERSONAL DETAILS CARD - Original style -->
            <div class="personal-card">
                <h3>Personal Details</h3>
                <div class="details-grid">
                    <div class="details-half">
                        <h4>Child Information</h4>
                        <p><strong>Name:</strong> <?= htmlspecialchars($child_full_name) ?></p>
                        <p><strong>Date of Birth:</strong> <?= date('M d, Y', strtotime($child_dob)) ?> (<?= $child_age ?> years)</p>
                        <p><strong>Gender:</strong> <?= ucfirst($child_gender) ?></p>
                    </div>
                    
                    <div class="details-half">
                        <h4><?= $relationship_display ?> Information</h4>
                        <p><strong>Name:</strong> <?= htmlspecialchars($guardian_name) ?></p>
                        <p><strong>Phone:</strong> <?= htmlspecialchars($guardian_phone) ?></p>
                        <p><strong>Email:</strong> <?= htmlspecialchars($guardian_email) ?></p>
                    </div>
                </div>
            </div>
            
            <!-- INFO CARDS - Horizontal rows -->
            <div class="info-cards">
                
                <!-- NEXT APPOINTMENT ROW -->
                <div class="info-row">
                    <div class="info-content">
                        <span class="info-icon"></span>
                        <div class="info-text">
                            <span class="info-title">Next Appointment</span>
                            <?php if ($next_appointment): ?>
                                <span class="info-details">
                                    <?= date('M d, Y', strtotime($next_appointment['appointment_date'])) ?> at 
                                    <?= date('g:i A', strtotime($next_appointment['appointment_time'])) ?> with 
                                    Dr. <?= htmlspecialchars($next_appointment['doctor_name'] ?? 'Not assigned') ?>
                                </span>
                                <span class="info-badge"><?= $next_appointment['status'] ?></span>
                            <?php else: ?>
                                <span class="info-details empty-message">No upcoming appointments</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <a href="appointments.php" class="info-action">Manage</a>
                </div>
                
                <!-- NEXT VACCINE ROW -->
                <div class="info-row">
                    <div class="info-content">
                        <span class="info-icon"></span>
                        <div class="info-text">
                            <span class="info-title">Next Vaccine</span>
                            <?php if ($next_vaccine): ?>
                                <?php 
                                    $due_date = date('M d, Y', strtotime($child_dob . " + {$next_vaccine['min_age_weeks']} weeks"));
                                ?>
                                <span class="info-details">
                                    <?= htmlspecialchars($next_vaccine['vaccine_name']) ?> (Dose <?= $next_vaccine['dose_number'] ?>) 
                                    due by <?= $due_date ?>
                                </span>
                                <span class="info-badge">Due Soon</span>
                            <?php else: ?>
                                <span class="info-details empty-message">All vaccines up to date (<?= $vaccine_count ?> given)</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <a href="medical-history.php" class="info-action">View All</a>
                </div>
            </div>
            
        <?php endif; ?>
    </div>
</div>

</body>
</html>
<?php $conn->close(); ?>