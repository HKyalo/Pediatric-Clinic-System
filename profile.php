<?php
session_start();
require_once __DIR__ . "/config/db.php";

// ============================================
// SECURITY CHECK
// ============================================
if (!isset($_SESSION['guardian_id'])) {
    header("Location: index.php");
    exit();
}

$guardian_id = $_SESSION['guardian_id'];
$message = "";
$message_type = "";
$selected_child_id = $_SESSION['selected_child_id'] ?? null;  

// to get notification count
$unread_count = 0;
if ($guardian_id && $selected_child_id) {
    $result = $conn->query("SELECT COUNT(*) as count FROM notifications WHERE guardian_id = $guardian_id AND child_id = $selected_child_id AND is_read = 0");
    $unread_count = $result->fetch_assoc()['count'];
}

// ============================================
// HANDLE CONTACT UPDATE
// ============================================
if (isset($_POST['update_contacts'])) {
    $update_query = $conn->prepare("
        UPDATE guardians SET 
            mother_email = ?, mother_phone = ?,
            father_email = ?, father_phone = ?,
            guardian_email = ?, guardian_phone = ?
        WHERE id = ?
    ");
    
    $update_query->bind_param("ssssssi", 
        $_POST['mother_email'], $_POST['mother_phone'],
        $_POST['father_email'], $_POST['father_phone'],
        $_POST['guardian_email'], $_POST['guardian_phone'],
        $guardian_id
    );
    
    if ($update_query->execute()) {
        $message = "Contact information updated successfully!";
        $message_type = "success";
    } else {
        $message = "Error updating contact information.";
        $message_type = "error";
    }
    $update_query->close();
}

// ============================================
// HANDLE PASSWORD CHANGE
// ============================================
if (isset($_POST['change_password'])) {
    
    // Get current password from database
    $password_query = $conn->prepare("SELECT password FROM guardians WHERE id = ?");
    $password_query->bind_param("i", $guardian_id);
    $password_query->execute();
    $current_hash = $password_query->get_result()->fetch_assoc()['password'];
    $password_query->close();
    
    // Verify current password
    if (password_verify($_POST['current_password'], $current_hash)) {
        
        // Check new password validity
        if ($_POST['new_password'] === $_POST['confirm_password']) {
            
            if (strlen($_POST['new_password']) >= 6) {
                
                $new_hash = password_hash($_POST['new_password'], PASSWORD_DEFAULT);
                $update_query = $conn->prepare("UPDATE guardians SET password = ? WHERE id = ?");
                $update_query->bind_param("si", $new_hash, $guardian_id);
                
                if ($update_query->execute()) {
                    $message = "Password changed successfully!";
                    $message_type = "success";
                } else {
                    $message = "Error changing password.";
                    $message_type = "error";
                }
                $update_query->close();
                
            } else {
                $message = "Password must be at least 6 characters long.";
                $message_type = "error";
            }
        } else {
            $message = "New passwords do not match.";
            $message_type = "error";
        }
    } else {
        $message = "Current password is incorrect.";
        $message_type = "error";
    }
}

// ============================================
// HANDLE ADD CHILD
// ============================================
if (isset($_POST['add_child'])) {
    
    $insert_query = $conn->prepare("
        INSERT INTO children 
        (guardian_id, first_name, last_name, gender, date_of_birth) 
        VALUES (?, ?, ?, ?, ?)
    ");
    
    $insert_query->bind_param("issss", 
        $guardian_id,
        $_POST['first_name'],
        $_POST['last_name'],
        $_POST['gender'],
        $_POST['date_of_birth']
    );
    
    if ($insert_query->execute()) {
        $message = "Child added successfully!";
        $message_type = "success";
    } else {
        $message = "Error adding child.";
        $message_type = "error";
    }
    $insert_query->close();
}

// ============================================
// FETCH GUARDIAN DATA
// ============================================
$guardian_query = $conn->query("SELECT * FROM guardians WHERE id = $guardian_id");
$guardian = $guardian_query->fetch_assoc();

// ============================================
// FETCH CHILDREN DATA
// ============================================
$children_query = $conn->query("
    SELECT * FROM children 
    WHERE guardian_id = $guardian_id 
    ORDER BY date_of_birth
");
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD HTML 4.01 Strict//EN">
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <title>Profile - PCASS</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        /* ===== PROFILE PAGE STYLES ===== */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: Arial, sans-serif;
            background: #f0f4fc;
        }
        
        .wrapper {
            display: flex;
            min-height: 100vh;
        }
        
        .main {
            margin-left: 260px;
            padding: 30px;
            background: #f0f4fc;
            flex: 1;
        }
        
        /* Page Header */
        .page-header {
            margin-bottom: 25px;
        }
        
        .page-header h1 {
            color: #0b1a33;
            font-size: 28px;
            margin-bottom: 5px;
        }
        
        /* Messages */
        .alert {
            padding: 15px 20px;
            border-radius: 6px;
            margin-bottom: 25px;
            font-weight: 600;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border-left: 4px solid #28a745;
        }
        
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border-left: 4px solid #dc3545;
        }
        
        /* Grid Layout */
        .profile-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 25px;
            margin-bottom: 25px;
        }
        
        /* Cards */
        .card {
            background: white;
            border-radius: 8px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(11,26,51,0.1);
            border-left: 4px solid #0b1a33;
            margin-bottom: 25px;
        }
        
        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .card-header h2 {
            color: #0b1a33;
            font-size: 20px;
            margin: 0;
        }
        
        .card h3 {
            color: #0b1a33;
            font-size: 18px;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #e2e8f0;
        }
        
        /* Info Rows */
        .info-row {
            display: flex;
            padding: 12px 0;
            border-bottom: 1px solid #f1f5f9;
        }
        
        .info-label {
            font-weight: 600;
            color: #5a6f8c;
            width: 120px;
        }
        
        .info-value {
            color: #1e293b;
            flex: 1;
        }
        
        .info-value.readonly {
            color: #5a6f8c;
            font-style: italic;
        }
        
        /* Forms */
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 6px;
            font-weight: 600;
            color: #1e3a5f;
            font-size: 14px;
        }
        
        .form-control {
            width: 100%;
            padding: 10px 12px;
            border: 1.5px solid #e2e8f0;
            border-radius: 6px;
            font-size: 14px;
            font-family: inherit;
        }
        
        .form-control:focus {
            outline: none;
            border-color: #0b1a33;
        }
        
        /* Buttons */
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            font-size: 14px;
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
            background: #e2e8f0;
            color: #1e293b;
        }
        
        .btn-secondary:hover {
            background: #cbd5e1;
        }
        
        /* Toggle Form */
        .toggle-form {
            display: none;
            background: #f8fafd;
            padding: 20px;
            border-radius: 6px;
            margin-top: 15px;
            border-left: 4px solid #0b1a33;
        }
        
        .toggle-form.show {
            display: block;
        }
        
        /* Children Grid */
        .children-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
            gap: 15px;
            margin-top: 15px;
        }
        
        .child-card {
            background: #f8fafd;
            border-radius: 8px;
            padding: 15px;
            border-left: 4px solid #0b1a33;
        }
        
        .child-card h4 {
            color: #0b1a33;
            margin-bottom: 8px;
            font-size: 16px;
        }
        
        .child-card p {
            margin: 4px 0;
            font-size: 13px;
            color: #5a6f8c;
        }
        
        .child-card p strong {
            color: #1e293b;
        }
        
        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 40px;
            color: #5a6f8c;
        }
        
        /* Help Text */
        .help-text {
            font-size: 12px;
            color: #5a6f8c;
            margin-top: 4px;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .profile-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
<div class="wrapper">
    
    <!-- ===== SIDEBAR ===== -->
    <div class="sidebar">
        <div class="sidebar-header">
            <h2>PCASS</h2>
            <p>Family Portal</p>
        </div>
        <div class="nav">
            <ul>
                <li><a href="child_dashboard.php">Dashboard</a></li>
                <li><a href="appointments.php">Appointments</a></li>
                <li><a href="medical-history.php">Medical History</a></li>
                <li><a href="notifications.php"> Notifications
                    <?php if ($unread_count > 0): ?>
                        <span style="background:#dc2626; color:white; padding:2px 8px; border-radius:12px; font-size:11px; margin-left:8px;"><?= $unread_count ?></span>
                    <?php endif; ?>
                </a></li>
                <li><a href="profile.php" class="active">Profile</a></li>
                <li><a href="logout.php">Logout</a></li>
            </ul>
        </div>
    </div>
    
    <!-- ===== MAIN CONTENT ===== -->
    <div class="main">
        
        <div class="page-header">
            <h1>👤 My Profile</h1>
        </div>
        
        <!-- Message Display -->
        <?php if ($message): ?>
            <div class="alert alert-<?= $message_type ?>">
                <?= $message_type == 'success' ? '✓' : '⚠' ?> <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>
        
        <!-- Two Column Grid -->
        <div class="profile-grid">
            
            <!-- LEFT: Family Information (Read-Only) -->
            <div class="card">
                <h3>Family Information</h3>
                
                <?php if (!empty($guardian['mother_name'])): ?>
                <div class="info-row">
                    <span class="info-label">Mother:</span>
                    <span class="info-value readonly"><?= htmlspecialchars($guardian['mother_name']) ?></span>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($guardian['father_name'])): ?>
                <div class="info-row">
                    <span class="info-label">Father:</span>
                    <span class="info-value readonly"><?= htmlspecialchars($guardian['father_name']) ?></span>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($guardian['guardian_name'])): ?>
                <div class="info-row">
                    <span class="info-label">Guardian:</span>
                    <span class="info-value readonly"><?= htmlspecialchars($guardian['guardian_name']) ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Relationship:</span>
                    <span class="info-value readonly"><?= htmlspecialchars($guardian['guardian_relationship']) ?></span>
                </div>
                <?php endif; ?>
                
                <div class="info-row">
                    <span class="info-label">Login Email:</span>
                    <span class="info-value"><?= htmlspecialchars($guardian['email']) ?></span>
                </div>
            </div>
            
            <!-- RIGHT: Contact Information (Editable) -->
            <div class="card">
                <h3>Contact Information</h3>
                
                <form method="POST">
                    
                    <?php if (!empty($guardian['mother_name'])): ?>
                    <div class="form-group">
                        <label>Mother's Email</label>
                        <input type="email" name="mother_email" class="form-control" 
                               value="<?= htmlspecialchars($guardian['mother_email'] ?? '') ?>">
                    </div>
                    
                    <div class="form-group">
                        <label>Mother's Phone</label>
                        <input type="tel" name="mother_phone" class="form-control" 
                               value="<?= htmlspecialchars($guardian['mother_phone'] ?? '') ?>">
                    </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($guardian['father_name'])): ?>
                    <div class="form-group">
                        <label>Father's Email</label>
                        <input type="email" name="father_email" class="form-control" 
                               value="<?= htmlspecialchars($guardian['father_email'] ?? '') ?>">
                    </div>
                    
                    <div class="form-group">
                        <label>Father's Phone</label>
                        <input type="tel" name="father_phone" class="form-control" 
                               value="<?= htmlspecialchars($guardian['father_phone'] ?? '') ?>">
                    </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($guardian['guardian_name'])): ?>
                    <div class="form-group">
                        <label>Guardian's Email</label>
                        <input type="email" name="guardian_email" class="form-control" 
                               value="<?= htmlspecialchars($guardian['guardian_email'] ?? '') ?>">
                    </div>
                    
                    <div class="form-group">
                        <label>Guardian's Phone</label>
                        <input type="tel" name="guardian_phone" class="form-control" 
                               value="<?= htmlspecialchars($guardian['guardian_phone'] ?? '') ?>">
                    </div>
                    <?php endif; ?>
                    
                    <button type="submit" name="update_contacts" class="btn btn-primary">
                        💾 Save Contact Information
                    </button>
                </form>
            </div>
        </div>
        
        <!-- ===== PASSWORD SECTION ===== -->
        <div class="card">
            <div class="card-header">
                <h2>Change Password</h2>
                <button onclick="toggleForm('password-form')" class="btn btn-secondary">
                    Change Password
                </button>
            </div>
            
            <div id="password-form" class="toggle-form">
                <form method="POST">
                    <div class="form-group">
                        <label>Current Password</label>
                        <input type="password" name="current_password" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label>New Password (minimum 6 characters)</label>
                        <input type="password" name="new_password" class="form-control" required minlength="6">
                    </div>
                    
                    <div class="form-group">
                        <label>Confirm New Password</label>
                        <input type="password" name="confirm_password" class="form-control" required minlength="6">
                    </div>
                    
                    <button type="submit" name="change_password" class="btn btn-primary">
                        Update Password
                    </button>
                </form>
            </div>
        </div>
        
        <!-- ===== CHILDREN SECTION ===== -->
        <div class="card">
            <div class="card-header">
                <h2>My Children</h2>
                <button onclick="toggleForm('child-form')" class="btn btn-success">
                    + Add Child
                </button>
            </div>
            
            <div id="child-form" class="toggle-form">
                <form method="POST">
                    <div class="form-group">
                        <label>First Name</label>
                        <input type="text" name="first_name" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Last Name</label>
                        <input type="text" name="last_name" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Date of Birth</label>
                        <input type="date" name="date_of_birth" class="form-control" 
                               max="<?= date('Y-m-d') ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Gender</label>
                        <select name="gender" class="form-control" required>
                            <option value="">-- Select Gender --</option>
                            <option value="Male">Male</option>
                            <option value="Female">Female</option>
                        </select>
                    </div>
                    
                    <button type="submit" name="add_child" class="btn btn-primary">
                        Add Child
                    </button>
                </form>
            </div>
            
            <!-- Children List -->
            <?php if ($children_query->num_rows > 0): ?>
                <div class="children-grid">
                    <?php while ($child = $children_query->fetch_assoc()): 
                        $age = (new DateTime())->diff(new DateTime($child['date_of_birth']))->y;
                    ?>
                    <div class="child-card">
                        <h4><?= htmlspecialchars($child['first_name'] . ' ' . $child['last_name']) ?></h4>
                        <p><strong>Age:</strong> <?= $age ?> years</p>
                        <p><strong>Gender:</strong> <?= htmlspecialchars($child['gender']) ?></p>
                        <p><strong>DOB:</strong> <?= date('M j, Y', strtotime($child['date_of_birth'])) ?></p>
                    </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <p>No children added yet.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
function toggleForm(formId) {
    document.getElementById(formId).classList.toggle('show');
}
</script>

</body>
</html>
<?php $conn->close(); ?>