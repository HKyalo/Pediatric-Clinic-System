<?php
session_start();

// Connect to MySQL with port 3307
$conn = new mysqli("localhost", "root", "", "PediaLink", 3307);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

if (isset($_POST['login_guardian'])) {
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    
    if (empty($email) || empty($password)) {
        header("Location: index.php?error=empty_fields");
        exit();
    }
    
    // Query guardians table: id, name, email, phone, password
    $stmt = $conn->prepare("SELECT id, email, password, name FROM guardians WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $guardian = $result->fetch_assoc();
        
        if (password_verify($password, $guardian['password'])) {
            // FIXED: Using 'id' not 'guardian_id'
            $_SESSION['guardian_id'] = $guardian['id'];  // ✓ CORRECT
            $_SESSION['guardian_email'] = $guardian['email'];
            $_SESSION['guardian_name'] = $guardian['name'];
            $_SESSION['user_type'] = 'guardian';
            
            header("Location: child_dashboard.php");
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