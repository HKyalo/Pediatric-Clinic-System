<?php
session_start();
require_once __DIR__ . "/config/db.php";

// ============================================
// HANDLE REGISTRATION FORM SUBMISSION
// ============================================
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    if (empty($_POST['login_email'])) {
        $error = "Please select which email to use for login";
    } 
    elseif (empty($_POST['password']) || empty($_POST['confirm_password'])) {
        $error = "Please enter and confirm your password";
    } 
    elseif ($_POST['password'] !== $_POST['confirm_password']) {
        $error = "Passwords do not match";
    } 
    // UPDATED: Password strength validation
    elseif (strlen($_POST['password']) < 8) {
        $error = "Password must be at least 8 characters long";
    }
    elseif (!preg_match('/[A-Z]/', $_POST['password'])) {
        $error = "Password must contain at least one uppercase letter";
    }
    elseif (!preg_match('/[a-z]/', $_POST['password'])) {
        $error = "Password must contain at least one lowercase letter";
    }
    elseif (!preg_match('/[0-9]/', $_POST['password'])) {
        $error = "Password must contain at least one number";
    }
    elseif (!preg_match('/[!@#$%^&*()_+\-=\[\]{};\':"\\\\|,.<>\/?]/', $_POST['password'])) {
        $error = "Password must contain at least one special character (!@#$%^&*)";
    }
    else {
        
        $login_type = $_POST['login_email'];
        $login_email = null;
        $login_name = null;
        $login_phone = null;
        
        if ($login_type === 'mother' && !empty($_POST['mother_email'])) {
            $login_email = $_POST['mother_email'];
            $login_name = $_POST['mother_name'];
            $login_phone = $_POST['mother_phone'];
        } 
        elseif ($login_type === 'father' && !empty($_POST['father_email'])) {
            $login_email = $_POST['father_email'];
            $login_name = $_POST['father_name'];
            $login_phone = $_POST['father_phone'];
        } 
        elseif ($login_type === 'guardian' && !empty($_POST['guardian_email'])) {
            $login_email = $_POST['guardian_email'];
            $login_name = $_POST['guardian_name'];
            $login_phone = $_POST['guardian_phone'];
        }
        
        if (!$login_email) {
            $error = "Please provide an email for the selected login option";
        } else {
            
            $check_query = $conn->prepare("SELECT id FROM guardians WHERE email = ?");
            $check_query->bind_param("s", $login_email);
            $check_query->execute();
            
            if ($check_query->get_result()->num_rows > 0) {
                $error = "This email is already registered.";
                $check_query->close();
            } else {
                $check_query->close();
                
                $hashed_password = password_hash($_POST['password'], PASSWORD_DEFAULT);
                
               $insert_query = $conn->prepare("INSERT INTO guardians 
    (name, email, password, 
     mother_name, mother_email, mother_phone,
     father_name, father_email, father_phone,
     guardian_name, guardian_email, guardian_relationship, guardian_phone,
     login_email_type)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

$insert_query->bind_param("ssssssssssssss",
    $login_name, $login_email, $hashed_password,
    $_POST['mother_name'], $_POST['mother_email'], $_POST['mother_phone'],
    $_POST['father_name'], $_POST['father_email'], $_POST['father_phone'],
    $_POST['guardian_name'], $_POST['guardian_email'], $_POST['guardian_relationship'], $_POST['guardian_phone'],
    $login_type
);
                
                if ($insert_query->execute()) {
                    $guardian_id = $conn->insert_id;
                    $insert_query->close();
                    
                    $_SESSION['guardian_id'] = $guardian_id;
                    $_SESSION['guardian_name'] = $login_name;
                    $_SESSION['guardian_email'] = $login_email;
                    $_SESSION['user_type'] = 'guardian';
                    
                    header("Location: add_child.php?first_time=1");
                    exit();
                } else {
                    $error = "Registration failed.";
                }
            }
        }
    }
}
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD HTML 4.01 Strict//EN" "http://www.w3.org/TR/html4/strict.dtd">
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <title>Register - PCASS</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <script src="assets/js/script.js"></script>
    <style>
        /* ===== SIMPLE REGISTER PAGE STYLES ===== */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: Arial, sans-serif;
            background: #0b1a33;
            padding: 40px;
        }
        
        .register-box {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 5px 20px rgba(11,26,51,0.1);
        }
        
        /* Header - Dark Blue */
        .header {
            background: #0b1a33;
            color: white;
            padding: 30px 40px;
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
        
        .content h2 {
            color: #0b1a33;
            font-size: 24px;
            margin-bottom: 10px;
        }
        
        .content .subtitle {
            color: #5a6f8c;
            margin-bottom: 30px;
            padding-bottom: 15px;
            border-bottom: 1px solid #e2e8f0;
        }
        
        /* Three Column Grid */
        .grid-3 {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 25px;
            margin-bottom: 30px;
        }
        
        /* Detail Cards */
        .detail-card {
            background: #f8fafd;
            padding: 20px;
            border-radius: 8px;
            border-left: 4px solid;
        }
        
        .detail-card.mother {
            border-left-color: #ec4899;
        }
        
        .detail-card.father {
            border-left-color: #3b82f6;
        }
        
        .detail-card.guardian {
            border-left-color: #f59e0b;
        }
        
        .detail-card h3 {
            color: #0b1a33;
            font-size: 16px;
            font-weight: 700;
            margin-bottom: 20px;
            padding-bottom: 8px;
            border-bottom: 1px solid #e2e8f0;
        }
        
        /* Form Elements */
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-group label {
            display: block;
            font-weight: 600;
            color: #1e3a5f;
            font-size: 12px;
            text-transform: uppercase;
            margin-bottom: 4px;
        }
        
        .form-group input {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #0c1725;
            border-radius: 6px;
            font-size: 14px;
            transition: border 0.2s;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: #0b1a33;
        }
        
        .form-group input::placeholder {
            color: #7fa2da;
            font-size: 13px;
        }
        
        /* Login Selection Box */
        .login-box {
            background: #e6f0ff;
            padding: 25px;
            border-radius: 8px;
            margin: 30px 0;
            border: 1px solid #b8d1ff;
        }
        
        .login-box p {
            font-weight: 700;
            color: #0b1a33;
            margin-bottom: 15px;
            font-size: 15px;
        }
        
        .login-box p span {
            color: #ef4444;
            margin-left: 3px;
        }
        
        .radio-group {
            display: flex;
            gap: 30px;
        }
        
        .radio-item {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .radio-item input[type="radio"] {
            accent-color: #0b1a33;
            width: 16px;
            height: 16px;
        }
        
        .radio-item label {
            color: #1e293b;
            font-size: 14px;
            cursor: pointer;
        }
        
        /* Password Box */
        .password-box {
            background: #f8fafd;
            padding: 25px;
            border-radius: 8px;
            border-left: 4px solid #10b981;
            margin-bottom: 30px;
        }
        
        .password-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        
        /* Password Strength Styles (added inline for completeness) */
        .password-strength {
            margin-top: 15px;
            margin-bottom: 15px;
            padding: 15px;
            background: #ffffff;
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
        
        button:disabled:hover {
            background: #0b1a33;
        }
        
        /* Button */
        .btn-register {
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
        }
        
        .btn-register:hover {
            background: #1e3a5f;
        }
        
        .btn-register:disabled {
            background: #5a6f8c;
            opacity: 0.6;
            cursor: not-allowed;
        }
        
        .btn-register:disabled:hover {
            background: #5a6f8c;
        }
        
        /* Login Link */
        .login-link {
            text-align: center;
            margin-top: 20px;
            color: #5a6f8c;
            font-size: 14px;
        }
        
        .login-link a {
            color: #0b1a33;
            text-decoration: none;
            font-weight: 600;
        }
        
        .login-link a:hover {
            text-decoration: underline;
        }
        
        /* Error Message */
        .error {
            background: #fee2e2;
            color: #991b1b;
            padding: 12px 16px;
            border-radius: 6px;
            margin-bottom: 20px;
            border-left: 4px solid #ef4444;
            font-size: 14px;
        }
        
        /* Responsive */
        @media (max-width: 900px) {
            body {
                padding: 20px;
            }
            
            .grid-3 {
                grid-template-columns: 1fr;
            }
            
            .radio-group {
                flex-direction: column;
                gap: 10px;
            }
            
            .password-grid {
                grid-template-columns: 1fr;
            }
            
            .header {
                padding: 20px;
            }
            
            .content {
                padding: 20px;
            }
        }
    </style>
</head>
<body>

<div class="register-box">
    
    <!-- ===== HEADER ===== -->
    <div class="header">
        <h1>PCASS</h1>
        <p>Pediatric Clinic Appointment Scheduling System</p>
    </div>
    
    <!-- ===== MAIN CONTENT ===== -->
    <div class="content">
        
        <h2>Create Account</h2>
        <p class="subtitle">Register to access your child's health records</p>
        
        <!-- Error Message -->
        <?php if (!empty($error)): ?>
            <div class="error">⚠️ <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <form method="POST" onsubmit="return validatePassword(
            document.getElementById('password').value,
            document.getElementById('confirm_password').value
        )">
            
            <!-- Three Column Layout - Mother, Father, Guardian -->
            <div class="grid-3">
                
                <!-- MOTHER -->
                <div class="detail-card mother">
                    <h3>MOTHER'S DETAILS</h3>
                    
                    <div class="form-group">
                        <label>Full Name</label>
                        <input type="text" name="mother_name" 
                               placeholder="eg: Anna Lee"
                               value="<?= htmlspecialchars($_POST['mother_name'] ?? '') ?>">
                    </div>
                    
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" name="mother_email" 
                               placeholder="eg: anna@email.com"
                               value="<?= htmlspecialchars($_POST['mother_email'] ?? '') ?>">
                    </div>
                    
                    <div class="form-group">
                        <label>Phone Number</label>
                        <input type="tel" name="mother_phone" 
                               placeholder="eg: +254 719 345 678"
                               value="<?= htmlspecialchars($_POST['mother_phone'] ?? '') ?>">
                    </div>
                </div>
                
                <!-- FATHER -->
                <div class="detail-card father">
                    <h3>FATHER'S DETAILS</h3>
                    
                    <div class="form-group">
                        <label>Full Name</label>
                        <input type="text" name="father_name" 
                               placeholder="eg: Peter John"
                               value="<?= htmlspecialchars($_POST['father_name'] ?? '') ?>">
                    </div>
                    
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" name="father_email" 
                               placeholder="eg: peter@email.com"
                               value="<?= htmlspecialchars($_POST['father_email'] ?? '') ?>">
                    </div>
                    
                    <div class="form-group">
                        <label>Phone Number</label>
                        <input type="tel" name="father_phone" 
                               placeholder="eg: +254 719 345 678"
                               value="<?= htmlspecialchars($_POST['father_phone'] ?? '') ?>">
                    </div>
                </div>
                
                <!-- GUARDIAN -->
                <div class="detail-card guardian">
                    <h3>GUARDIAN (if parents unavailable)</h3>
                    
                    <div class="form-group">
                        <label>Full Name</label>
                        <input type="text" name="guardian_name" 
                               placeholder="eg: Sarah Zuri"
                               value="<?= htmlspecialchars($_POST['guardian_name'] ?? '') ?>">
                    </div>
                    
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" name="guardian_email" 
                               placeholder="eg: sarah@email.com"
                               value="<?= htmlspecialchars($_POST['guardian_email'] ?? '') ?>">
                    </div>
                    
                    <div class="form-group">
                        <label>Relationship</label>
                        <input type="text" name="guardian_relationship" 
                               placeholder="eg: Aunt"
                               value="<?= htmlspecialchars($_POST['guardian_relationship'] ?? '') ?>">
                    </div>
                    
                    <div class="form-group">
                        <label>Phone Number</label>
                        <input type="tel" name="guardian_phone" 
                               placeholder="eg: +254 719 345 678"
                               value="<?= htmlspecialchars($_POST['guardian_phone'] ?? '') ?>">
                    </div>
                </div>
            </div>
            
            <!-- LOGIN EMAIL SELECTION -->
            <div class="login-box">
                <p>Which email should be used for login? <span>*</span></p>
                
                <div class="radio-group">
                    <div class="radio-item">
                        <input type="radio" name="login_email" id="login_mother" value="mother" required>
                        <label for="login_mother">Mother's email</label>
                    </div>
                    <div class="radio-item">
                        <input type="radio" name="login_email" id="login_father" value="father" required>
                        <label for="login_father">Father's email</label>
                    </div>
                    <div class="radio-item">
                        <input type="radio" name="login_email" id="login_guardian" value="guardian" required>
                        <label for="login_guardian">Guardian's email</label>
                    </div>
                </div>
            </div>
            
            <!-- PASSWORD SECTION -->
            <div class="password-box">
                <div class="password-grid">
                    <div class="form-group">
                        <label>Password</label>
                        <input type="password" name="password" id="password"
                               placeholder="********" required minlength="8"
                               onkeyup="checkPasswordStrength(this.value)">
                    </div>
                    
                    <div class="form-group">
                        <label>Confirm Password</label>
                        <input type="password" name="confirm_password" id="confirm_password"
                               placeholder="********" required minlength="8">
                    </div>
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
            </div>
            
            <!-- SUBMIT BUTTON -->
            <button type="submit" id="register-btn" class="btn-register" disabled>CREATE ACCOUNT</button>
            
            <div class="login-link">
                Already have an account? <a href="index.php">Log in here</a>
            </div>
        </form>
    </div>
</div>

<script>
// Initialize password validation when page loads
window.addEventListener('DOMContentLoaded', function() {
    // Check if our script.js functions are available
    if (typeof initPasswordValidation === 'function') {
        initPasswordValidation('password', 'confirm_password', 'register-btn');
    } else {
        console.warn('Password validation functions not loaded. Make sure script.js is included.');
        
        // Fallback: enable button anyway
        document.getElementById('register-btn').disabled = false;
    }
});

// Manual validation as backup
document.querySelector('form').addEventListener('submit', function(e) {
    const password = document.getElementById('password').value;
    const confirm = document.getElementById('confirm_password').value;
    
    if (password.length < 8) {
        e.preventDefault();
        alert('Password must be at least 8 characters long');
        return false;
    }
    
    if (!/[A-Z]/.test(password)) {
        e.preventDefault();
        alert('Password must contain at least one uppercase letter');
        return false;
    }
    
    if (!/[a-z]/.test(password)) {
        e.preventDefault();
        alert('Password must contain at least one lowercase letter');
        return false;
    }
    
    if (!/[0-9]/.test(password)) {
        e.preventDefault();
        alert('Password must contain at least one number');
        return false;
    }
    
    if (!/[!@#$%^&*()_+\-=\[\]{};':"\\|,.<>\/?]/.test(password)) {
        e.preventDefault();
        alert('Password must contain at least one special character (!@#$%^&*)');
        return false;
    }
    
    if (password !== confirm) {
        e.preventDefault();
        alert('Passwords do not match!');
        return false;
    }
});
</script>

</body>
</html>