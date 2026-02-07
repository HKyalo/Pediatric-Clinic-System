<?php
session_start();
require_once __DIR__ . "/config/db.php";

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'doctor') {
    header("Location: index.php");
    exit();
}

$doctor_id = $_SESSION['user_id'];

// Total patients (distinct children)
$patients = $conn->query("SELECT COUNT(DISTINCT child_id) AS total FROM appointments WHERE doctor_id=$doctor_id")->fetch_assoc();

// Today's appointments
$today = date('Y-m-d');
$todayAppointments = $conn->query("SELECT COUNT(*) AS total FROM appointments WHERE doctor_id=$doctor_id AND appointment_date='$today'")->fetch_assoc();

// Upcoming appointments
$upcoming = $conn->query("SELECT COUNT(*) AS total FROM appointments WHERE doctor_id=$doctor_id AND appointment_date > '$today'")->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Doctor Dashboard</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="dashboard-body">

    <!-- Sidebar -->
    <aside class="sidebar">
        <h2>Doctor Panel</h2>
        <nav>
            <ul>
                <li><a href="doctor_dashboard.php">Dashboard</a></li>
                <li><a href="doctor_appointments.php">My Appointments</a></li>
                <li><a href="doctor_patients.php">My Patients</a></li>
                <li><a href="doctor_profile.php">Profile</a></li>
                <li><a href="logout.php">Logout</a></li>
            </ul>
        </nav>
    </aside>

    <!-- Main Content -->
    <main class="dashboard-main">
        <h1>Welcome, Dr. <?= $_SESSION['name']; ?></h1>
        <p>Here’s a quick overview of your patients and appointments.</p>

        <div class="card">
            <h3>My Patients</h3>
            <p><?= $patients['total']; ?></p>
        </div>

        <div class="card">
            <h3>Today's Appointments</h3>
            <p><?= $todayAppointments['total']; ?></p>
        </div>

        <div class="card">
            <h3>Upcoming Appointments</h3>
            <p><?= $upcoming['total']; ?></p>
        </div>
    </main>

</body>
</html>
