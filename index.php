<?php
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
                // Guardian login successful
                $_SESSION['guardian_id'] = $guardian['id'];
                $_SESSION['guardian_name'] = $guardian['name'];
                $_SESSION['guardian_email'] = $guardian['email'];
                $_SESSION['user_type'] = 'guardian';
                
                $stmt->close();
                
                // Check if they have children, redirect accordingly
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
                    // Doctor login successful
                    $_SESSION['user_id'] = $doctor['doctor_id'];
                    $_SESSION['name'] = $doctor['full_name'];
                    $_SESSION['email'] = $doctor['email'];
                    $_SESSION['user_type'] = 'doctor';
                    $_SESSION['doctor_role'] = $doctor['doctor_role'] ?? 'immunization';
                    
                    $stmt->close();
                    
                    // Redirect based on doctor role
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
                        // Admin login successful
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
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <title>PCASS</title>
    <style type="text/css">
        * {
            margin: 0;
            padding: 0;
        }
        
        body {
            font-family: Arial, Helvetica, sans-serif;
            background: #0b1a33;
            min-height: 100vh;
        }
        
        .top-bar {
            background: #0b1a33;
            padding: 20px 40px;
            text-align: right;
            border-bottom: 1px solid #1e3a5f;
        }
        
        .top-bar a {
            color: #ffffff;
            text-decoration: none;
            margin-left: 30px;
            font-size: 16px;
            font-weight: 600;
            opacity: 0.9;
        }
        
        .top-bar a:hover {
            opacity: 1;
            text-decoration: underline;
        }
        
        .main-content {
            display: table;
            width: 100%;
            height: calc(100vh - 80px);
        }
        
        .left-panel {
            display: table-cell;
            width: 50%;
            background: #0b1a33;
            padding: 60px 60px;
            color: white;
            vertical-align: top;
        }
        
        .right-panel {
            display: table-cell;
            width: 50%;
            background: #f0f4fc;
            padding: 60px 60px;
            vertical-align: top;
        }
        
        .system-title {
            font-size: 48px;
            font-weight: 800;
            margin-bottom: 15px;
            letter-spacing: -0.5px;
            color: #ffffff;
        }
        
        .system-subtitle {
            font-size: 20px;
            margin-bottom: 20px;
            color: #a3c6ff;
            font-weight: 400;
        }
        
        .system-description {
            font-size: 16px;
            line-height: 1.8;
            color: #b8d1ff;
            margin: 30px 0 20px;
            max-width: 500px;
        }
        
        .login-container {
            background: #ffffff;
            border-radius: 8px;
            padding: 40px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            max-width: 400px;
            margin: 0 auto;
        }
        
        .login-title {
            font-size: 28px;
            color: #0b1a33;
            margin-bottom: 10px;
            font-weight: 700;
        }
        
        .login-subtitle {
            font-size: 14px;
            color: #5a6f8c;
            margin-bottom: 30px;
            line-height: 1.6;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #1e3a5f;
            font-size: 14px;
        }
        
        .form-group input {
            width: 100%;
            padding: 12px 16px;
            border: 1px solid #d9e2ef;
            border-radius: 4px;
            font-size: 15px;
            font-family: Arial, Helvetica, sans-serif;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: #0b1a33;
        }
        
        .btn-login {
            width: 100%;
            padding: 14px;
            background: #0b1a33;
            color: white;
            border: none;
            border-radius: 4px;
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
            margin: 30px 0 20px;
            font-family: Arial, Helvetica, sans-serif;
        }
        
        .btn-login:hover {
            background: #1e3a5f;
        }
        
        .register-link {
            text-align: center;
            color: #5a6f8c;
            font-size: 14px;
        }
        
        .register-link a {
            color: #0b1a33;
            text-decoration: none;
            font-weight: 600;
        }
        
        .register-link a:hover {
            text-decoration: underline;
        }
        
        .error-message {
            background: #ffebee;
            color: #b71c1c;
            padding: 12px 16px;
            border-radius: 4px;
            margin-bottom: 20px;
            border-left: 4px solid #b71c1c;
            font-size: 14px;
        }
        
        .signin-heading {
            font-size: 32px;
            font-weight: 700;
            color: #ffffff;
            margin: 30px 0 15px;
        }
    </style>
</head>
<body>

<div class="top-bar">
    <a href="index.php">Login</a>
    <a href="register.php">Register</a>
</div>

<div class="main-content">
    <!-- Left Panel - Dark Blue -->
    <div class="left-panel">
        <div class="system-title">PEDIATRIC CLINIC APPOINTMENT SCHEDULING SYSTEM(PCASS)</div>
        
        <div style="font-size: 18px; color: #ffffff; margin-bottom: 15px;">
            Manage your child's health efficiently
        </div>
        
        <div class="system-description">
            Register children, schedule appointments, track medical history, and receive notifications-all in one platform for parents,guardians and clinic staff.
        </div>
    </div>
    
    <!-- Right Panel - Login Form -->
    <div class="right-panel">
        <div class="login-container">
            <div class="login-title">SIGN-IN TO PCASS</div>
            
            <?php if ($error): ?>
                <div class="error-message">⚠️ <?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            
            <form method="post" action="">
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input 
                        type="email" 
                        id="email" 
                        name="email" 
                        value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                        required
                    >
                </div>
                
                <div class="form-group">
                    <label for="password">Password</label>
                    <input 
                        type="password" 
                        id="password" 
                        name="password" 
                        required
                    >
                </div>
                
                <button type="submit" class="btn-login">LOGIN</button>
                
                <div class="register-link">
                    Don't have an account? <a href="register.php">Register here</a>
                </div>
            </form>
        </div>
    </div>
</div>

</body>
</html>
<?php $conn->close(); ?>