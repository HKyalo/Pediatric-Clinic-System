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
            (guardian_id, first_name, last_name, date_of_birth, gender, blood_type)
            VALUES (?, ?, ?, ?, ?, ?)");
        
        $blood_type = !empty($_POST['blood_type']) ? $_POST['blood_type'] : null;
        
        $insert_query->bind_param("isssss",
            $guardian_id,
            $_POST['first_name'],
            $_POST['last_name'],
            $_POST['date_of_birth'],
            $_POST['gender'],
            $blood_type
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
<!DOCTYPE html PUBLIC "-//W3C//DTD HTML 4.01 Strict//EN" "http://www.w3.org/TR/html4/strict.dtd">
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <title>Add Child - PCASS</title>
    <style>
        /* ===== ADD CHILD PAGE STYLES ===== */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: Arial, sans-serif;
            background: #0b1a33;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .add-child-card {
            max-width: 650px;
            width: 100%;
            background: white;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }
        
        /* Header - Dark Blue */
        .header {
            background: #0b1a33;
            color: white;
            padding: 30px;
            text-align: center;
        }
        
        .header h1 {
            font-size: 32px;
            margin-bottom: 5px;
            color: white;
        }
        
        .header p {
            color: #a3c6ff;
            font-size: 14px;
        }
        
        /* Content Area */
        .content {
            padding: 40px;
        }
        
        /* Welcome Message */
        .welcome-box {
            background: #e6f0ff;
            border: 2px solid #0b1a33;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 30px;
            text-align: center;
        }
        
        .welcome-box h2 {
            color: #0b1a33;
            font-size: 22px;
            margin-bottom: 8px;
            font-weight: 700;
        }
        
        .welcome-box p {
            color: #1e3a5f;
            font-size: 15px;
        }
        
        .welcome-icon {
            font-size: 48px;
            margin-bottom: 15px;
        }
        
        /* Form Title */
        .form-title {
            color: #0b1a33;
            font-size: 18px;
            font-weight: 700;
            margin-bottom: 25px;
            padding-bottom: 10px;
            border-bottom: 2px solid #e2e8f0;
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
            color: #1e3a5f;
            font-size: 14px;
            margin-bottom: 6px;
        }
        
        .required {
            color: #ef4444;
            margin-left: 2px;
        }
        
        .optional {
            color: #5a6f8c;
            font-weight: 400;
            font-size: 12px;
            margin-left: 5px;
        }
        
        .form-control {
            width: 100%;
            padding: 12px 14px;
            border: 2px solid #2b394b;
            border-radius: 8px;
            font-size: 14px;
            font-family: inherit;
            transition: border 0.2s;
        }
        
        .form-control:focus {
            outline: none;
            border-color: #0b1a33;
        }
        
        .form-control::placeholder {
            color: #7fa2da;
            font-size: 13px;
        }
        
        select.form-control {
            cursor: pointer;
            background: white;
        }
        
        /* Example text styling */
        .example {
            color: #a3c6ff;
            font-size: 12px;
            margin-top: 4px;
        }
        
        /* Note Box */
        .note-box {
            background: #fff3cd;
            border-left: 4px solid #f59e0b;
            padding: 15px;
            border-radius: 6px;
            margin: 25px 0;
            font-size: 13px;
            color: #713f12;
        }
        
        .note-box i {
            color: #f59e0b;
            margin-right: 5px;
        }
        
        /* Button */
        .btn-add {
            background: #0b1a33;
            color: white;
            border: none;
            padding: 15px;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
            width: 100%;
            transition: background 0.2s;
            margin-top: 10px;
        }
        
        .btn-add:hover {
            background: #1e3a5f;
        }
        
        /* Back Link */
        .back-link {
            text-align: center;
            margin-top: 20px;
        }
        
        .back-link a {
            color: #5a6f8c;
            text-decoration: none;
            font-size: 14px;
        }
        
        .back-link a:hover {
            color: #0b1a33;
            text-decoration: underline;
        }
        
        /* Error Message */
        .error {
            background: #fee2e2;
            color: #991b1b;
            padding: 12px 16px;
            border-radius: 6px;
            margin-bottom: 25px;
            border-left: 4px solid #ef4444;
            font-size: 14px;
        }
        
        /* Responsive */
        @media (max-width: 600px) {
            .grid-2 {
                grid-template-columns: 1fr;
            }
            
            .content {
                padding: 25px;
            }
            
            .header {
                padding: 25px;
            }
        }
    </style>
</head>
<body>

<div class="add-child-card">
    
    <!-- ===== HEADER ===== -->
    <div class="header">
        <h1>PCASS</h1>
        <p>Pediatric Clinic Appointment Scheduling System</p>
    </div>
    
    <!-- ===== MAIN CONTENT ===== -->
    <div class="content">
        
        <!-- Welcome Message (only for first child) -->
        <?php if ($is_first_child): ?>
            <div class="welcome-box">
                <div class="welcome-icon">👋</div>
                <h2>Welcome back!</h2>
                <p>Welcome to PCASS! Let's add your first child's information</p>
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
            
            <!-- Note -->
            <div class="note-box">
                <i>ℹ️</i> Allergies and medical conditions will be added by your doctor in the EHR system.
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