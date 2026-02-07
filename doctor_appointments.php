<?php
session_start();
require_once __DIR__ . "/config/db.php";

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'doctor') {
    header("Location: index.php");
    exit();
}

$doctor_id = $_SESSION['user_id'];

// Fetch all appointments for this doctor
$stmt = $conn->prepare("
    SELECT a.appointment_id, a.child_id, a.appointment_date, a.appointment_time, a.status, a.notes,
           c.first_name AS child_first, c.last_name AS child_last,
           g.name AS guardian_name
    FROM appointments a
    JOIN children c ON a.child_id = c.child_id
    JOIN guardians g ON c.guardian_id = g.id
    WHERE a.doctor_id = ?
    ORDER BY a.appointment_date ASC, a.appointment_time ASC
");

$stmt->bind_param("i", $doctor_id);
$stmt->execute();
$result = $stmt->get_result();
$appointments = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>

<!DOCTYPE html>
<html>
<head>
    <title>My Appointments</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="dashboard-body">

<!-- Sidebar -->
<div class="sidebar">
    <h2>Doctor Panel</h2>
    <nav>
        <ul>
            <li><a href="doctor_dashboard.php">Dashboard</a></li>
            <li><a href="doctor_appointments.php" class="active">My Appointments</a></li>
            <li><a href="doctor_patients.php">My Patients</a></li>
            <li><a href="doctor_profile.php">Profile</a></li>
            <li><a href="logout.php">Logout</a></li>
        </ul>
    </nav>
</div>

<!-- Main content -->
<div class="dashboard-main">
    <h1>My Appointments</h1>

    <div class="card">
        <table class="appointments-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Child Name</th>
                    <th>Guardian</th>
                    <th>Date</th>
                    <th>Time</th>
                    <th>Status</th>
                    <th>Notes</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($appointments): ?>
                    <?php $count = 1; foreach ($appointments as $appt): ?>
                        <tr>
                            <td><?= $count++; ?></td>
                            <td><?= htmlspecialchars($appt['child_first'] . ' ' . $appt['child_last']); ?></td>
                            <?= htmlspecialchars($appt['guardian_name']); ?>
                            <td><?= date('d M Y', strtotime($appt['appointment_date'])); ?></td>
                            <td><?= date('h:i A', strtotime($appt['appointment_time'])); ?></td>
                            <td>
                                <?php
                                    $status = $appt['status'];
                                    $badgeColor = $status === 'scheduled' ? '#2f80ed' :
                                                  ($status === 'completed' ? '#28a745' :
                                                  ($status === 'cancelled' ? '#eb5757' : '#555'));
                                ?>
                                <span style="padding:5px 10px; border-radius:5px; color:#fff; background:<?= $badgeColor; ?>;">
                                    <?= ucfirst($status); ?>
                                </span>
                            </td>
                            <td><?= htmlspecialchars($appt['notes']); ?></td>
                            <td>
                                <a href="doctor_ehr.php?child_id=<?= $appt['child_id']; ?>" class="reschedule-btn">View EHR</a>
                                <?php if ($appt['status'] === 'scheduled'): ?>
                                    <a href="update_appointment.php?id=<?= $appt['appointment_id']; ?>" class="cancel-btn">Update</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="8">No appointments found.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

</body>
</html>
