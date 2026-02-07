<?php
session_start();
require_once __DIR__ . "/config/db.php";

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'doctor') {
    header("Location: index.php");
    exit();
}

$doctor_id = $_SESSION['user_id'];

// Fetch unique patients for this doctor
$stmt = $conn->prepare("
    SELECT DISTINCT c.child_id, c.first_name, c.last_name, c.gender, c.date_of_birth,
           g.name AS guardian_name, g.phone AS guardian_phone
    FROM appointments a
    JOIN children c ON a.child_id = c.child_id
    JOIN guardians g ON c.guardian_id = g.id
    WHERE a.doctor_id = ?
    ORDER BY c.first_name ASC
");
$stmt->bind_param("i", $doctor_id);
$stmt->execute();
$result = $stmt->get_result();
$patients = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>

<!DOCTYPE html>
<html>
<head>
    <title>My Patients</title>
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
            <li><a href="doctor_patients.php" class="active">My Patients</a></li>
            <li><a href="doctor_profile.php">Profile</a></li>
            <li><a href="logout.php">Logout</a></li>
        </ul>
    </nav>
</div>

<!-- Main content -->
<div class="dashboard-main">
    <h1>My Patients</h1>

    <?php if ($patients): ?>
        <div class="card">
            <table class="appointments-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Child Name</th>
                        <th>Gender</th>
                        <th>Date of Birth</th>
                        <th>Guardian</th>
                        <th>Guardian Phone</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $count = 1; foreach ($patients as $patient): ?>
                        <tr>
                            <td><?= $count++; ?></td>
                            <td><?= htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']); ?></td>
                            <td><?= htmlspecialchars(ucfirst($patient['gender'])); ?></td>
                            <td><?= date('d M Y', strtotime($patient['date_of_birth'])); ?></td>
                            <td><?= htmlspecialchars($patient['guardian_name']); ?></td>
                            <td><?= htmlspecialchars($patient['guardian_phone']); ?></td>
                            <td>
                                <a href="doctor_ehr.php?child_id=<?= $patient['child_id']; ?>" class="reschedule-btn">View EHR</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <p>No patients assigned yet.</p>
    <?php endif; ?>
</div>

</body>
</html>
