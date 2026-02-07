<?php

die("System setup already completed.");

// setup_admin.php
// This file is used ONLY ONCE during system deployment

$conn = new mysqli("localhost", "root", "", "PediaLink", 3307);

if ($conn->connect_error) {
    die("Database connection failed");
}

// Admin credentials (initial system admin)
$full_name = "System Administrator";
$email = "admin@pedialink.com";
$password = "admin123"; // initial password
$hashed_password = password_hash($password, PASSWORD_DEFAULT);

// Check if admin already exists
$check = $conn->prepare("SELECT admin_id FROM admins WHERE email = ?");
$check->bind_param("s", $email);
$check->execute();
$check->store_result();

if ($check->num_rows > 0) {
    die("Admin account already exists. Setup is disabled.");
}

$check->close();

// Insert admin
$stmt = $conn->prepare(
    "INSERT INTO admins (full_name, email, password)
     VALUES (?, ?, ?)"
);
$stmt->bind_param("sss", $full_name, $email, $hashed_password);

if ($stmt->execute()) {
    echo "<h2>Admin account created successfully</h2>";
    echo "<p>Email: admin@pedialink.com</p>";
    echo "<p>Password: admin123</p>";
    echo "<p><strong>Please disable this file immediately.</strong></p>";
} else {
    echo "Error creating admin account.";
}

$stmt->close();
$conn->close();
