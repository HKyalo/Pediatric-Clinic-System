<?php
// Set session to last 24 hours 
ini_set('session.cookie_lifetime', 86400); // 24 hours in seconds
ini_set('session.gc_maxlifetime', 86400);   // 24 hours in seconds
session_set_cookie_params(86400); // Also set cookie params
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
$is_first_child = isset($_GET['first_time']);

// ============================================
// HANDLE FORM SUBMISSION
// ============================================
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    if (empty($_POST['first_name']) || empty($_POST['last_name']) || 
        empty($_POST['date_of_birth']) || empty($_POST['gender'])) {
        $error = "Please fill in all required fields";
    } else {
        
        $insert_query = $conn->prepare("INSERT INTO children 
            (guardian_id, first_name, last_name, date_of_birth, gender)
            VALUES (?, ?, ?, ?, ?)");
        
        $insert_query->bind_param("issss",
            $guardian_id,
            $_POST['first_name'],
            $_POST['last_name'],
            $_POST['date_of_birth'],
            $_POST['gender']
        );
        
        if ($insert_query->execute()) {
            $child_id = $conn->insert_id;
            $insert_query->close();
            
            $_SESSION['selected_child_id'] = $child_id;
            header("Location: child_dashboard.php?welcome=1");
            exit();
        } else {
            $error = "Failed to add child. Please try again.";
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <title>Add Child - PCASS</title>
    <style>
        /* ===== ADD CHILD PAGE STYLES ===== */
        /* ===== ADD CHILD PAGE STYLES ===== */
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: 'Segoe UI', Arial, Helvetica, sans-serif;
    min-height: 100vh;
    background-image: url('reception.png');
    background-size: cover;
    background-position: center;
    background-repeat: no-repeat;
    background-attachment: fixed;
    position: relative;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px;
}

/* Dark overlay for better readability */
body::before {
    content: '';
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
    z-index: 0;
}

.add-child-card {
    position: relative;
    z-index: 1;
    max-width: 650px;
    width: 100%;
    background: rgba(11, 26, 51, 0.1);
    backdrop-filter: blur(100px);
    border-radius: 24px;
    overflow: hidden;
    box-shadow: 0 25px 50px rgba(0, 0, 0, 0.3);
    border: 1px solid rgba(255, 255, 255, 0.2);
    transition: transform 0.3s;
}

.add-child-card:hover {
    transform: translateY(-5px);
}


.header h1 {
    font-size: 32px;
    font-weight: 800;
    margin-bottom: 8px;
    color: white;
    letter-spacing: -0.5px;
}

.header p {
    color: rgba(255, 255, 255, 0.7);
    font-size: 14px;
}

/* Divider */
.divider {
    width: 60px;
    height: 3px;
    background: #ffd966;
    margin: 15px auto 0;
    border-radius: 3px;
}

/* Content Area */
.content {
    padding: 40px;
}

/* Welcome Message - Glass style */
.welcome-box {
    background: rgba(255, 255, 255, 0.1);
    border-radius: 16px;
    padding: 25px;
    margin-bottom: 30px;
    text-align: center;
    border: 1px solid rgba(255, 255, 255, 0.15);
}

.welcome-box h2 {
    color: white;
    font-size: 24px;
    margin-bottom: 8px;
    font-weight: 700;
}

.welcome-box p {
    color: rgba(255, 255, 255, 0.8);
    font-size: 15px;
}

/* Form Title */
.form-title {
    color: white;
    font-size: 20px;
    font-weight: 700;
    margin-bottom: 25px;
    padding-bottom: 10px;
    border-bottom: 2px solid rgba(255, 255, 255, 0.2);
}

/* Two Column Grid */
.grid-2 {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
    margin-bottom: 20px;
}

/* Form Elements */
.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    font-weight: 600;
    color: white;
    font-size: 13px;
    margin-bottom: 6px;
}

.required {
    color: #ffd966;
    margin-left: 2px;
}

.optional {
    color: rgba(255, 255, 255, 0.6);
    font-weight: 400;
    font-size: 12px;
    margin-left: 5px;
}

.form-control {
    width: 100%;
    padding: 12px 14px;
    border: none;
    border-radius: 12px;
    font-size: 14px;
    font-family: inherit;
    background: rgba(255, 255, 255, 0.9);
    transition: all 0.3s;
    color: #0b1a33;
}

.form-control:focus {
    outline: none;
    background: white;
    box-shadow: 0 0 0 3px rgba(255, 217, 102, 0.3);
}

.form-control::placeholder {
    color: #8a9bb0;
    font-size: 13px;
}

select.form-control {
    cursor: pointer;
    background: rgba(255, 255, 255, 0.9);
}

select.form-control option {
    color: #0b1a33;
}

/* Example text styling */
.example {
    color: rgba(255, 255, 255, 0.5);
    font-size: 11px;
    margin-top: 5px;
}

/* Note Box - Glass style */
.note-box {
    background: rgba(255, 255, 255, 0.08);
    border-left: 4px solid #ffd966;
    padding: 15px;
    border-radius: 12px;
    margin: 25px 0;
    font-size: 13px;
    color: rgba(255, 255, 255, 0.8);
}

/* Button */
.btn-add {
    background: #ffd966;
    color: #0b1a33;
    border: none;
    padding: 14px;
    border-radius: 12px;
    font-size: 16px;
    font-weight: 700;
    cursor: pointer;
    width: 100%;
    transition: all 0.3s;
    margin-top: 10px;
}

.btn-add:hover {
    background: #ffcd38;
    transform: scale(1.02);
}

/* Back Link */
.back-link {
    text-align: center;
    margin-top: 20px;
}

.back-link a {
    color: rgba(255, 255, 255, 0.7);
    text-decoration: none;
    font-size: 14px;
    transition: all 0.2s;
}

.back-link a:hover {
    color: #ffd966;
    text-decoration: underline;
}

/* Error Message */
.error {
    background: rgba(220, 38, 38, 0.9);
    color: white;
    padding: 12px 16px;
    border-radius: 12px;
    margin-bottom: 25px;
    font-size: 14px;
    text-align: center;
    backdrop-filter: blur(4px);
}

/* Responsive */
@media (max-width: 600px) {
    .grid-2 {
        grid-template-columns: 1fr;
        gap: 15px;
    }
    
    .content {
        padding: 25px;
    }
    
    .header {
        padding: 25px;
    }
    
    .header h1 {
        font-size: 28px;
    }
    
    .welcome-box h2 {
        font-size: 20px;
    }
}
    </style>
</head>
<body>

<div class="add-child-card">
    
    
    <!-- ===== MAIN CONTENT ===== -->
    <div class="content">
        
        <!-- Welcome Message (only for first child) -->
        <?php if ($is_first_child): ?>
            <div class="welcome-box">
                <h2>Welcome!</h2>
                <p>Let's add your first child's information</p>
            </div>
        <?php else: ?>
            <div class="form-title">Add Another Child</div>
        <?php endif; ?>
        
        <!-- Error Message -->
        <?php if (!empty($error)): ?>
            <div class="error">⚠️ <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <form method="POST">
            
            <!-- First Name & Last Name Row -->
            <div class="grid-2">
                <div class="form-group">
                    <label>First Name <span class="required">*</span></label>
                    <input type="text" name="first_name" class="form-control" 
                           placeholder="eg. Christine"
                           value="<?= htmlspecialchars($_POST['first_name'] ?? '') ?>" required>
                </div>
                
                <div class="form-group">
                    <label>Last Name <span class="required">*</span></label>
                    <input type="text" name="last_name" class="form-control" 
                           placeholder="eg. Johnson"
                           value="<?= htmlspecialchars($_POST['last_name'] ?? '') ?>" required>
                </div>
            </div>
            
            <!-- Date of Birth & Gender Row -->
            <div class="grid-2">
                <div class="form-group">
                    <label>Date of Birth <span class="required">*</span></label>
                    <input type="date" name="date_of_birth" class="form-control" 
                           required max="<?= date('Y-m-d') ?>"
                           value="<?= htmlspecialchars($_POST['date_of_birth'] ?? '') ?>">
                    <div class="example">dd/mm/yyyy</div>
                </div>
                
                <div class="form-group">
                    <label>Gender <span class="required">*</span></label>
                    <select name="gender" class="form-control" required>
                        <option value="">-- Select --</option>
                        <option value="male" <?= (($_POST['gender'] ?? '') === 'male') ? 'selected' : '' ?>>Male</option>
                        <option value="female" <?= (($_POST['gender'] ?? '') === 'female') ? 'selected' : '' ?>>Female</option>
                    </select>
                </div>
            </div>
            
            <!-- Submit Button -->
            <button type="submit" class="btn-add">ADD CHILD</button>
            
            <!-- Back Link (for subsequent children) -->
            <?php if (!$is_first_child): ?>
                <div class="back-link">
                    <a href="child_dashboard.php">← Back to Dashboard</a>
                </div>
            <?php endif; ?>
        </form>
    </div>
</div>

</body>
</html>