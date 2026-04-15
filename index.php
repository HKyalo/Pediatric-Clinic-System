<?php
// Set session to last 24 hours 
ini_set('session.cookie_lifetime', 86400); // 24 hours in seconds
ini_set('session.gc_maxlifetime', 86400);   // 24 hours in seconds
session_set_cookie_params(86400); // Also set cookie params
session_start();
require_once __DIR__ . "/config/db.php";

$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($email) || empty($password)) {
        $error = "Please enter both email and password";
    } else {
        
        // ============================================
        // STEP 1: Check Guardians Table
        // ============================================
        $stmt = $conn->prepare("SELECT id, name, email, password FROM guardians WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $guardian = $result->fetch_assoc();
            
            if (password_verify($password, $guardian['password'])) {
                $_SESSION['guardian_id'] = $guardian['id'];
                $_SESSION['guardian_name'] = $guardian['name'];
                $_SESSION['guardian_email'] = $guardian['email'];
                $_SESSION['user_type'] = 'guardian';
                
                $stmt->close();
                
                $childCheck = $conn->prepare("SELECT child_id FROM children WHERE guardian_id = ?");
                $childCheck->bind_param("i", $guardian['id']);
                $childCheck->execute();
                $children = $childCheck->get_result()->fetch_all(MYSQLI_ASSOC);
                $childCheck->close();
                
                if (count($children) === 0) {
                    header("Location: add_child.php?first_time=1");
                } elseif (count($children) === 1) {
                    $_SESSION['selected_child_id'] = $children[0]['child_id'];
                    header("Location: child_dashboard.php");
                } else {
                    header("Location: select_child.php");
                }
                exit();
            } else {
                $error = "Incorrect password";
            }
            $stmt->close();
        } else {
            $stmt->close();
            
            // ============================================
            // STEP 2: Check Doctors Table
            // ============================================
            $stmt = $conn->prepare("SELECT doctor_id, full_name, email, password, status, doctor_role FROM doctors WHERE email = ?");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $doctor = $result->fetch_assoc();
                
                if ($doctor['status'] !== 'Active') {
                    $error = "Your account has been deactivated. Please contact administration.";
                } elseif (password_verify($password, $doctor['password'])) {
                    $_SESSION['user_id'] = $doctor['doctor_id'];
                    $_SESSION['name'] = $doctor['full_name'];
                    $_SESSION['email'] = $doctor['email'];
                    $_SESSION['user_type'] = 'doctor';
                    $_SESSION['doctor_role'] = $doctor['doctor_role'] ?? 'immunization';
                    
                    $stmt->close();
                    
                    if ($_SESSION['doctor_role'] === 'specialist') {
                        header("Location: doctor_specialist_dashboard.php");
                    } else {
                        header("Location: doctor_immunization_dashboard.php");
                    }
                    exit();
                } else {
                    $error = "Incorrect password";
                }
                $stmt->close();
            } else {
                $stmt->close();
                
                // ============================================
                // STEP 3: Check Admins Table
                // ============================================
                $stmt = $conn->prepare("SELECT admin_id, full_name, email, password FROM admins WHERE email = ?");
                $stmt->bind_param("s", $email);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result->num_rows > 0) {
                    $admin = $result->fetch_assoc();
                    
                    if (password_verify($password, $admin['password'])) {
                        $_SESSION['admin_id'] = $admin['admin_id'];
                        $_SESSION['admin_name'] = $admin['full_name'];
                        $_SESSION['admin_email'] = $admin['email'];
                        $_SESSION['user_type'] = 'admin';
                        
                        $stmt->close();
                        header("Location: admin_dashboard.php");
                        exit();
                    } else {
                        $error = "Incorrect password";
                        $stmt->close();
                    }
                } else {
                    $stmt->close();
                    $error = "No account found with this email address";
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
    <title>PCASS - Login</title>
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
        
        /* Register link at top right */
        .top-register {
            position: absolute;
            top: 25px;
            right: 35px;
            z-index: 2;
        }
        
        .top-register a {
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
        
        .top-register a:hover {
            background: rgba(255, 255, 255, 0.3);
            text-decoration: none;
        }
        
        /* Centered glass card */
        .login-wrapper {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 100%;
            padding: 20px;
            z-index: 1;
        }
        
        .glass-card {
            max-width: 450px;
            margin: 0 auto;
            background: rgba(11, 26, 51, 0.1);
            backdrop-filter: blur(12px);
            border-radius: 24px;
            padding: 45px 40px;
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0);
            border: 1px solid rgba(255, 255, 255, 0.2);
            transition: transform 0.3s;
        }
        
       
        
        /* Logo/title area */
        .logo {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .logo h1 {
            font-size: 32px;
            font-weight: 800;
            color: white;
            letter-spacing: -0.5px;
        }
        
        .logo p {
            font-size: 14px;
            color: rgba(255, 255, 255, 0.7);
            margin-top: 8px;
        }
        
        .divider {
            width: 60px;
            height: 3px;
            background: #ffd966;
            margin: 15px auto 0;
            border-radius: 3px;
        }
        
        /* Form elements */
        .form-group {
            margin-bottom: 22px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: white;
            font-size: 14px;
        }
        
        .form-group input {
            width: 100%;
            padding: 14px 16px;
            border: none;
            border-radius: 12px;
            font-size: 15px;
            font-family: inherit;
            background: rgba(255, 255, 255, 0.9);
            transition: all 0.3s;
        }
        
        .form-group input:focus {
            outline: none;
            background: white;
            box-shadow: 0 0 0 3px rgba(255, 217, 102, 0.3);
        }
        
        .form-group input::placeholder {
            color: #8a9bb0;
        }
        
        /* Login button */
        .btn-login {
            width: 100%;
            padding: 14px;
            background: #ffd966;
            color: #0b1a33;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
            margin: 25px 0 20px;
            font-family: inherit;
            transition: all 0.3s;
        }
        
        .btn-login:hover {
            background: #ffcd38;
            transform: scale(1.02);
        }
        
        /* Register link inside card */
        .register-link {
            text-align: center;
            color: rgba(255, 255, 255, 0.7);
            font-size: 14px;
        }
        
        .register-link a {
            color: #ffd966;
            text-decoration: none;
            font-weight: 600;
        }
        
        .register-link a:hover {
            text-decoration: underline;
        }
        
        /* Error message */
        .error-message {
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
        @media (max-width: 550px) {
            .glass-card {
                padding: 35px 25px;
            }
            
            .logo h1 {
                font-size: 26px;
            }
            
            .top-register {
                top: 15px;
                right: 20px;
            }
        }
    </style>
</head>
<body>

<div class="top-register">
    <a href="register.php">Register</a>
</div>

<div class="login-wrapper">
    <div class="glass-card">
        <div class="logo">
            <h1>PCASS</h1>
            <p>Pediatric Clinic System</p>
            <div class="divider"></div>
        </div>
        
        <?php if ($error): ?>
            <div class="error-message"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <form method="post" action="">
            <div class="form-group">
                <label>Email Address</label>
                <input 
                    type="email" 
                    name="email" 
                    placeholder="your@email.com"
                    value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                    required
                >
            </div>
            
            <div class="form-group">
                <label>Password</label>
                <input 
                    type="password" 
                    name="password" 
                    placeholder="Enter your password"
                    required
                >
            </div>
            
            <button type="submit" class="btn-login">Sign In</button>
            
            <div class="register-link">
                Don't have an account? <a href="register.php">Create an account</a>
            </div>
        </form>
    </div>
</div>

</body>
</html>
<?php $conn->close(); ?>