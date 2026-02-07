<?php
session_start();

// Connect to MySQL with port 3307
$conn = new mysqli("localhost", "root", "", "PediaLink", 3307);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

if (isset($_POST['login_staff'])) {
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $role = $_POST['role'];
    
    if (empty($email) || empty($password) || empty($role)) {
        header("Location: index.php?error=empty_fields");
        exit();
    }
    
    // Determine table based on role
    if ($role === 'doctor') {
        $table = 'doctors';
        $id_field = 'doctor_id';
        $name_field = 'full_name';
    } elseif ($role === 'admin') {
        $table = 'admins';
        $id_field = 'admin_id';
        $name_field = 'full_name';
    } else {
        header("Location: index.php?error=invalid_role");
        exit();
    }
    
    // Prepare statement
    $stmt = $conn->prepare("SELECT $id_field, email, password, $name_field FROM $table WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        
        if (password_verify($password, $user['password'])) {
            // Create session
            $_SESSION['user_id'] = $user[$id_field];
            $_SESSION['email'] = $user['email'];
            $_SESSION['name'] = $user[$name_field];
            $_SESSION['user_type'] = $role;
            
            // Redirect based on role
            if ($role === 'doctor') {
                header("Location: doctor_dashboard.php");
            } else {
                header("Location: admin_dashboard.php");
            }
            exit();
        } else {
            header("Location: index.php?error=invalid_credentials");
            exit();
        }
    } else {
        header("Location: index.php?error=invalid_credentials");
        exit();
    }
    
    $stmt->close();
    $conn->close();
} else {
    header("Location: index.php");
    exit();
}
?>