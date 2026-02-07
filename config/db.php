<?php
$host = "localhost";
$user = "root";
$password = "";
$database = "PediaLink";
$port = 3307; 

$conn = new mysqli($host, $user, $password, $database, $port);

if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}
?>
