<?php
session_start();
require_once __DIR__ . "/config/db.php";

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'doctor') {
    header("Location: index.php");
    exit();
}

$doctor_id = $_SESSION['user_id'];

// Fetch doctor info
$stmt = $conn->prepare("SELECT * FROM doctors WHERE doctor_id = ?");
$stmt->bind_param("i", $doctor_id);
$stmt->execute();
$result = $stmt->get_result();
$doctor = $result->fetch_assoc();
$stmt->close();

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $password = trim($_POST['password']);

    if (!empty($password)) {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE doctors SET full_name=?, email=?, phone=?, password=? WHERE doctor_id=?");
        $stmt->bind_param("ssssi", $full_name, $email, $phone, $hashed_password, $doctor_id);
    } else {
        $stmt = $conn->prepare("UPDATE doctors SET full_name=?, email=?, phone=? WHERE doctor_id=?");
        $stmt->bind_param("sssi", $full_name, $email, $phone, $doctor_id);
    }

    if ($stmt->execute()) {
        $message = "Profile updated successfully!";
        $_SESSION['name'] = $full_name; // update session name
    } else {
        $message = "Error updating profile.";
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>My Profile</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="dashboard-body">

<!-- Sidebar -->
<div class="sidebar">
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
</div>

<!-- Main content -->
<div class="dashboard-main">
    <h1>My Profile</h1>

    <?php if (!empty($message)): ?>
        <div class="card">
            <p><?= htmlspecialchars($message); ?></p>
        </div>
    <?php endif; ?>

    <div class="profile-card">
        <form method="POST">
            <div class="profile-sections">
                <div class="profile-half">
                    <h3>Basic Info</h3>
                    <label>Full Name</label>
                    <input type="text" name="full_name" value="<?= htmlspecialchars($doctor['full_name']); ?>" required>

                    <label>Email</label>
                    <input type="email" name="email" value="<?= htmlspecialchars($doctor['email']); ?>" required>

                    <label>Phone</label>
                    <input type="text" name="phone" value="<?= htmlspecialchars($doctor['phone']); ?>" required>
                </div>

                <div class="profile-half">
                    <h3>Change Password</h3>
                    <label>New Password (leave blank to keep current)</label>
                    <input type="password" name="password">
                </div>
            </div>
            <button type="submit" name="update_profile">Update Profile</button>
        </form>
    </div>
</div>
</body>
</html>
