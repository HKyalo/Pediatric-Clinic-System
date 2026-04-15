<?php
// Set session to last 24 hours 
ini_set('session.cookie_lifetime', 86400); // 24 hours in seconds
ini_set('session.gc_maxlifetime', 86400);   // 24 hours in seconds
session_set_cookie_params(86400); // Also set cookie params
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
<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <title>Register - PCASS</title>
    <style>
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
}

/* Dark overlay for better readability */
body::before {
    content: '';
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.4);
    z-index: 0;
}

/* Login link at top right */
.top-login {
    position: absolute;
    top: 25px;
    right: 35px;
    z-index: 2;
}

.top-login a {
    color: white;
    text-decoration: none;
    font-weight: 600;
    font-size: 15px;
    padding: 8px 18px;
    background: rgba(255, 255, 255, 0.15);
    border-radius: 30px;
    backdrop-filter: blur(5px);
    transition: all 0.3s;
}

.top-login a:hover {
    background: rgba(255, 255, 255, 0.3);
}

/* Main container */
.register-wrapper {
    position: relative;
    z-index: 1;
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 40px 20px;
}

/* Glass card - Dark version (matching index.php) */
.glass-card {
    max-width: 1200px;
    width: 100%;
    margin: 0 auto;
    background: rgba(11, 26, 51, 0.1);
    backdrop-filter: blur(100px);
    border-radius: 24px;
    padding: 45px 40px;
    box-shadow: 0 25px 50px rgba(0, 0, 0, 0.5);
    border: 1px solid rgba(255, 255, 255, 0.2);
    transition: transform 0.3s;
}

.glass-card:hover {
    transform: translateY(-5px);
}


.header h1 {
    font-size: 36px;
    font-weight: 800;
    color: white;
    margin-bottom: 8px;
}

.header p {
    color: rgba(255, 255, 255, 0.7);
    font-size: 14px;
}

.divider {
    width: 60px;
    height: 3px;
    background: #ffd966;
    margin: 15px auto 0;
    border-radius: 3px;
}

/* Form title - White text */
.form-title {
    text-align: center;
    margin-bottom: 30px;
}

.form-title h2 {
    color: white;
    font-size: 24px;
    margin-bottom: 5px;
}

.form-title p {
    color: rgba(255, 255, 255, 0.6);
    font-size: 14px;
}

/* Three Column Grid */
.grid-3 {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 25px;
    margin-bottom: 30px;
}

/* Detail Cards - Semi-transparent dark */
.detail-card {
    background: rgba(255, 255, 255, 0.08);
    padding: 20px;
    border-radius: 16px;
    border-left: 4px solid;
    backdrop-filter: blur(4px);
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
    color: white;
    font-size: 16px;
    font-weight: 700;
    margin-bottom: 20px;
    padding-bottom: 8px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.2);
}

/* Form Elements - White labels */
.form-group {
    margin-bottom: 15px;
}

.form-group label {
    display: block;
    font-weight: 600;
    color: white;
    font-size: 12px;
    text-transform: uppercase;
    margin-bottom: 4px;
}

.form-group input {
    width: 100%;
    padding: 12px 14px;
    border: none;
    border-radius: 12px;
    font-size: 14px;
    background: rgba(255, 255, 255, 0.9);
    transition: all 0.3s;
    color: #0b1a33;
}

.form-group input:focus {
    outline: none;
    background: white;
    box-shadow: 0 0 0 3px rgba(255, 217, 102, 0.3);
}

.form-group input::placeholder {
            color: #8a9bb0;
        }

/* Login Selection Box - Semi-transparent */
.login-box {
    background: rgba(255, 255, 255, 0.08);
    padding: 25px;
    border-radius: 16px;
    margin: 30px 0;
    border: 1px solid rgba(255, 255, 255, 0.15);
}

.login-box p {
    font-weight: 700;
    color: white;
    margin-bottom: 15px;
    font-size: 15px;
}

.login-box p span {
    color: #ffd966;
    margin-left: 3px;
}

.radio-group {
    display: flex;
    gap: 30px;
    flex-wrap: wrap;
}

.radio-item {
    display: flex;
    align-items: center;
    gap: 8px;
}

.radio-item input[type="radio"] {
    accent-color: #ffd966;
    width: 16px;
    height: 16px;
}

.radio-item label {
    color: white;
    font-size: 14px;
    cursor: pointer;
}

/* Password Box - Semi-transparent */
.password-box {
    background: rgba(255, 255, 255, 0.08);
    padding: 25px;
    border-radius: 16px;
    border-left: 4px solid #10b981;
    margin-bottom: 30px;
}

.password-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
}

/* Password Strength - Dark background for contrast */
.password-strength {
    margin-top: 15px;
    padding: 15px;
    background: rgba(0, 0, 0, 0.3);
    border-radius: 12px;
}

.strength-item {
    color: rgba(255, 255, 255, 0.7);
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
    color: rgba(255, 255, 255, 0.5);
    margin-top: 10px;
    padding-top: 10px;
    border-top: 1px solid rgba(255, 255, 255, 0.1);
    font-style: italic;
}

/* Button - Yellow accent (matching index) */
.btn-register {
    width: 100%;
    padding: 14px;
    background: #ffd966;
    color: #0b1a33;
    border: none;
    border-radius: 12px;
    font-size: 16px;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.3s;
}

.btn-register:hover {
    background: #ffcd38;
    transform: scale(1.02);
}

.btn-register:disabled {
    background: #5a6f8c;
    opacity: 0.6;
    cursor: not-allowed;
    transform: none;
}

/* Login Link */
.login-link {
    text-align: center;
    margin-top: 20px;
    color: rgba(255, 255, 255, 0.7);
    font-size: 14px;
}

.login-link a {
    color: #ffd966;
    text-decoration: none;
    font-weight: 600;
}

.login-link a:hover {
    text-decoration: underline;
}

/* Error Message */
.error {
    background: rgba(220, 38, 38, 0.9);
    color: white;
    padding: 12px 16px;
    border-radius: 12px;
    margin-bottom: 20px;
    font-size: 14px;
    text-align: center;
}

/* Responsive */
@media (max-width: 900px) {
    .glass-card {
        padding: 25px;
    }
    
    .grid-3 {
        grid-template-columns: 1fr;
        gap: 15px;
    }
    
    .radio-group {
        flex-direction: column;
        gap: 10px;
    }
    
    .password-grid {
        grid-template-columns: 1fr;
        gap: 15px;
    }
}

@media (max-width: 550px) {
    .glass-card {
        padding: 20px;
    }
    
    .header h1 {
        font-size: 28px;
    }
    
    .top-login {
        top: 15px;
        right: 20px;
    }
}
</style>
</head>
<body>

<div class="top-login">
    <a href="index.php">Sign In</a>
</div>

<div class="register-wrapper">
    <div class="glass-card">
        
        <div class="form-title">
            <h2>Create Account</h2>
            <p>Register to access your child's health records</p>
        </div>
        
        <!-- Error Message -->
        <?php if (!empty($error)): ?>
            <div class="error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <form method="POST">
            
            <!-- Three Column Layout - Mother, Father, Guardian -->
            <div class="grid-3">
                
                <!-- MOTHER -->
                <div class="detail-card mother">
                    <h3>Mother's Details</h3>
                    
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
                    <h3>Father's Details</h3>
                    
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
                    <h3>Guardian (if parents unavailable)</h3>
                    
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
            <button type="submit" id="register-btn" class="btn-register" disabled>Create Account</button>
            
            <div class="login-link">
                Already have an account? <a href="index.php">Sign In here</a>
            </div>
        </form>
    </div>
</div>

<script>
function checkPasswordStrength(password) {
    const minLength = password.length >= 8;
    const hasUpper = /[A-Z]/.test(password);
    const hasLower = /[a-z]/.test(password);
    const hasNumber = /[0-9]/.test(password);
    const hasSpecial = /[!@#$%^&*()_+\-=\[\]{};':"\\|,.<>\/?]/.test(password);
    
    document.getElementById('req-length').className = minLength ? 'strength-item valid' : 'strength-item invalid';
    document.getElementById('req-upper').className = hasUpper ? 'strength-item valid' : 'strength-item invalid';
    document.getElementById('req-lower').className = hasLower ? 'strength-item valid' : 'strength-item invalid';
    document.getElementById('req-number').className = hasNumber ? 'strength-item valid' : 'strength-item invalid';
    document.getElementById('req-special').className = hasSpecial ? 'strength-item valid' : 'strength-item invalid';
    
    const isValid = minLength && hasUpper && hasLower && hasNumber && hasSpecial;
    document.getElementById('register-btn').disabled = !isValid;
}

// Also validate password match
document.getElementById('confirm_password')?.addEventListener('keyup', function() {
    const password = document.getElementById('password').value;
    const confirm = this.value;
    const btn = document.getElementById('register-btn');
    
    if (password !== confirm && confirm.length > 0) {
        this.style.border = '2px solid #ef4444';
        btn.disabled = true;
    } else if (password !== confirm) {
        this.style.border = 'none';
    } else {
        this.style.border = '2px solid #10b981';
        // Re-check password strength
        checkPasswordStrength(document.getElementById('password').value);
    }
});
</script>

</body>
</html>
<?php $conn->close(); ?>