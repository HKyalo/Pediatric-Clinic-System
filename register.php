<?php
// register.php

session_start();

// Connect to MySQL
$conn = new mysqli("localhost", "root", "", "PediaLink", 3307); // Port 3307 if changed in XAMPP

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Guardian data
    $guardian_name = $conn->real_escape_string($_POST['guardian_name']);
    $guardian_email = $conn->real_escape_string($_POST['guardian_email']);
    $guardian_phone = $conn->real_escape_string($_POST['guardian_phone']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    // Child data
    $child_first_name = $conn->real_escape_string($_POST['child_first_name']);
$child_last_name  = $conn->real_escape_string($_POST['child_last_name']);
$child_dob        = $_POST['child_dob'];
$child_gender     = $_POST['child_gender'];


    // Check passwords match
    if ($password !== $confirm_password) {
        $message = "Passwords do not match!";
    } else {
        // Hash password
        $password_hashed = password_hash($password, PASSWORD_DEFAULT);

        // Insert guardian
        $stmt = $conn->prepare("INSERT INTO guardians (name, email, phone, password) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssss", $guardian_name, $guardian_email, $guardian_phone, $password_hashed);

        if ($stmt->execute()) {
            $guardian_id = $stmt->insert_id; // Get the inserted guardian ID

            // Insert child linked to guardian
           $stmt2 = $conn->prepare(
    "INSERT INTO children (guardian_id, first_name, last_name, gender, date_of_birth)
     VALUES (?, ?, ?, ?, ?)"
);

$stmt2->bind_param(
    "issss",
    $guardian_id,
    $child_first_name,
    $child_last_name,
    $child_gender,
    $child_dob
);

            if ($stmt2->execute()) {
                $message = "Registration successful! You can now <a href='index.php'>login</a>.";
            } else {
                $message = "Child registration failed: " . $stmt2->error;
            }

            $stmt2->close();
        } else {
            $message = "Guardian registration failed: " . $stmt->error;
        }

        $stmt->close();
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Guardian Registration - PA-EHR System</title>
<link rel="stylesheet" href="assets\css\style.css">
</head>
<body>
<header>
  <div class="container header-container">
    <h1 class="logo">PA-EHR System</h1>
    <nav>
      <a href="index.php">Home</a>
    </nav>
  </div>
</header>

<section class="registration">
  <div class="container registration-content">
    <div class="registration-card">
      <h2>Guardian & Child Registration</h2>

      <?php if($message != ""): ?>
        <p style="color:red;"><?= $message ?></p>
      <?php endif; ?>

      <form action="" method="POST">
        <h3>Guardian Details</h3>
        <input type="text" name="guardian_name" placeholder="Full Name" required>
        <input type="email" name="guardian_email" placeholder="Email Address" required>
        <input type="tel" name="guardian_phone" placeholder="Phone Number" required>
        <input type="password" name="password" placeholder="Password" required>
        <input type="password" name="confirm_password" placeholder="Confirm Password" required>

        <h3>Child Details</h3>
        <input type="text" name="child_first_name" placeholder="Child First Name" required>
        <input type="text" name="child_last_name" placeholder="Child Last Name" required>
        <input type="date" name="child_dob" placeholder="Date of Birth" required>
        <select name="child_gender" required>
          <option value="">Select Gender</option>
          <option value="Male">Male</option>
          <option value="Female">Female</option>
          <option value="Other">Other</option>
        </select>

        <button type="submit">Register</button>
      </form>
      <p class="login-link">Already registered? <a href="index.php">Login here</a></p>
    </div>
  </div>
</section>

<footer>
  &copy; 2026 PA-EHR System
</footer>
</body>
</html>
