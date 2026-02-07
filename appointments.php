<?php
session_start();

if (!isset($_SESSION['guardian_id']) || $_SESSION['user_type'] !== 'guardian') {
    header("Location: index.php");
    exit();
}

$conn = new mysqli("localhost", "root", "", "PediaLink", 3307);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$guardian_id = $_SESSION['guardian_id'];
$guardian_name = $_SESSION['guardian_name'] ?? 'Guardian';

$error_message = "";
$success_message = "";

// Fetch children
$childStmt = $conn->prepare("SELECT child_id, first_name, last_name FROM children WHERE guardian_id = ? ORDER BY first_name");
$childStmt->bind_param("i", $guardian_id);
$childStmt->execute();
$children = $childStmt->get_result();
$childStmt->close();

// Fetch doctors from doctors table
$doctors = $conn->query("
    SELECT doctor_id, full_name, specialization
    FROM doctors
    WHERE status = 'active'
    ORDER BY full_name
");

// Book appointment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['book_appointment'])) {
    $child_id   = $_POST['child_id'] ?? null;
    $doctor_id  = $_POST['doctor_id'] ?? null;
    $date       = $_POST['appointment_date'] ?? null;
    $time       = $_POST['appointment_time'] ?? null;
    $notes      = $_POST['notes'] ?? '';

    if (!$child_id || !$doctor_id || !$date || !$time) {
        $error_message = "Please fill in all required fields.";
    } else {
        // Verify child belongs to guardian
        $verifyChild = $conn->prepare("SELECT child_id FROM children WHERE child_id = ? AND guardian_id = ?");
        $verifyChild->bind_param("ii", $child_id, $guardian_id);
        $verifyChild->execute();
        $childResult = $verifyChild->get_result();
        
        if ($childResult->num_rows === 0) {
            $error_message = "Invalid child selection.";
            $verifyChild->close();
        } else {
            $verifyChild->close();
            
            // Check if slot is taken
            $checkStmt = $conn->prepare("
                SELECT appointment_id 
                FROM appointments 
                WHERE doctor_id = ? AND appointment_date = ? AND appointment_time = ? AND status != 'Cancelled'
            ");
            $checkStmt->bind_param("iss", $doctor_id, $date, $time);
            $checkStmt->execute();
            $result = $checkStmt->get_result();
            
            if ($result->num_rows > 0) {
                $error_message = "This time slot is already booked. Please choose a different time.";
                $checkStmt->close();
            } else {
                $checkStmt->close();
                
                // Insert appointment
                $stmt = $conn->prepare("
                    INSERT INTO appointments (child_id, doctor_id, appointment_date, appointment_time, status, notes)
                    VALUES (?, ?, ?, ?, 'Pending', ?)
                ");
                $stmt->bind_param("iisss", $child_id, $doctor_id, $date, $time, $notes);
                
                if ($stmt->execute()) {
                    $stmt->close();
                    header("Location: appointments.php?success=1");
                    exit();
                } else {
                    $error_message = "Error booking appointment: " . $stmt->error;
                    $stmt->close();
                }
            }
        }
    }
}

// Handle cancellation
if (isset($_GET['cancel']) && is_numeric($_GET['cancel'])) {
    $appointment_id = $_GET['cancel'];
    
    $verifyStmt = $conn->prepare("
        SELECT a.appointment_id 
        FROM appointments a
        JOIN children c ON a.child_id = c.child_id
        WHERE a.appointment_id = ? AND c.guardian_id = ?
    ");
    $verifyStmt->bind_param("ii", $appointment_id, $guardian_id);
    $verifyStmt->execute();
    $verify_result = $verifyStmt->get_result();
    
    if ($verify_result->num_rows > 0) {
        $cancelStmt = $conn->prepare("UPDATE appointments SET status = 'Cancelled' WHERE appointment_id = ?");
        $cancelStmt->bind_param("i", $appointment_id);
        $cancelStmt->execute();
        $cancelStmt->close();
        
        header("Location: appointments.php?cancelled=1");
        exit();
    }
    $verifyStmt->close();
}

// Fetch appointments
$appointmentsStmt = $conn->prepare("
    SELECT 
        a.appointment_id,
        a.appointment_date,
        a.appointment_time,
        a.status,
        a.notes,
        CONCAT(c.first_name, ' ', c.last_name) AS child_name,
        d.full_name AS doctor_name,
        d.specialization
    FROM appointments a
    JOIN children c ON a.child_id = c.child_id
    JOIN doctors d ON a.doctor_id = d.doctor_id
    WHERE c.guardian_id = ?
    ORDER BY a.appointment_date DESC, a.appointment_time DESC
");
$appointmentsStmt->bind_param("i", $guardian_id);
$appointmentsStmt->execute();
$appointments = $appointmentsStmt->get_result();
$appointmentsStmt->close();

if (isset($_GET['success'])) {
    $success_message = "Appointment booked successfully!";
}
if (isset($_GET['cancelled'])) {
    $success_message = "Appointment cancelled successfully!";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Appointments - PediaLink</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .message {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
            font-weight: bold;
        }
        .success { 
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .form-section {
            background: #f9f9f9;
            padding: 25px;
            border-radius: 8px;
            margin-bottom: 30px;
        }
        label { 
            display: block; 
            margin-top: 15px;
            margin-bottom: 5px;
            font-weight: bold;
            color: #555;
        }
        select, input[type="date"], input[type="time"], textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
        }
        textarea {
            resize: vertical;
            min-height: 80px;
        }
        .btn-primary {
            margin-top: 20px;
            padding: 12px 30px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            font-weight: bold;
        }
        .btn-primary:hover {
            opacity: 0.9;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
            transition: all 0.3s ease;
        }
        .appointments-table { 
            width: 100%; 
            border-collapse: collapse; 
            margin-top: 20px;
        }
        .appointments-table th,
        .appointments-table td { 
            border: 1px solid #ddd; 
            padding: 12px; 
            text-align: left;
        }
        .appointments-table th { 
            background-color: #f8f9fa;
            font-weight: bold;
        }
        .appointments-table tr:hover {
            background-color: #f5f5f5;
        }
        .status-badge {
            padding: 5px 10px;
            border-radius: 3px;
            font-size: 12px;
            font-weight: bold;
        }
        .status-pending {
            background-color: #fff3cd;
            color: #856404;
        }
        .status-confirmed {
            background-color: #d4edda;
            color: #155724;
        }
        .status-cancelled {
            background-color: #f8d7da;
            color: #721c24;
        }
        .btn-cancel {
            padding: 5px 10px;
            background-color: #dc3545;
            color: white;
            border: none;
            border-radius: 3px;
            cursor: pointer;
            font-size: 12px;
        }
        .btn-cancel:hover {
            background-color: #c82333;
        }
        .no-data {
            text-align: center;
            padding: 30px;
            color: #666;
        }
        .card h3 {
            margin-top: 0;
        }
    </style>
</head>
<body class="dashboard-body">

<aside class="sidebar" id="sidebar">
  <h2>PediaLink Dashboard</h2>
  <nav>
    <ul>
      <li><a href="child_dashboard.php">Dashboard</a></li>
      <li><a href="appointments.php" class="active">Appointments</a></li>
      <li><a href="medical-history.php">Medical History</a></li>
      <li><a href="vaccinations.php">Vaccinations</a></li>
      <li><a href="notifications.php">Notifications</a></li>
      <li><a href="profile.php">Profile</a></li>
      <li><a href="logout.php">Logout</a></li>
    </ul>
  </nav>
</aside>

<main class="dashboard-main">
    <h1>Appointments</h1>
    <p>Welcome, <?= htmlspecialchars($guardian_name); ?>!</p>
    
    <?php if ($success_message): ?>
        <div class="message success"><?= htmlspecialchars($success_message); ?></div>
    <?php endif; ?>

    <?php if ($error_message): ?>
        <div class="message error"><?= htmlspecialchars($error_message); ?></div>
    <?php endif; ?>

    <div class="card">
        <h3>📅 Book New Appointment</h3>

        <form method="POST" class="form-section">
            <label>Select Child *</label>
            <select name="child_id" required>
                <option value="">-- Select Child --</option>
                <?php 
                $children->data_seek(0);
                while ($c = $children->fetch_assoc()): 
                ?>
                    <option value="<?= $c['child_id']; ?>">
                        <?= htmlspecialchars($c['first_name'] . ' ' . $c['last_name']); ?>
                    </option>
                <?php endwhile; ?>
            </select>

            <label>Select Doctor *</label>
            <select name="doctor_id" required>
                <option value="">-- Select Doctor --</option>
                <?php 
                if ($doctors && $doctors->num_rows > 0):
                    while ($d = $doctors->fetch_assoc()): 
                ?>
                    <option value="<?= $d['doctor_id']; ?>">
                        Dr. <?= htmlspecialchars($d['full_name']); ?>
                        <?php if ($d['specialization']): ?>
                            - <?= htmlspecialchars($d['specialization']); ?>
                        <?php endif; ?>
                    </option>
                <?php 
                    endwhile;
                else:
                ?>
                    <option value="">No doctors available</option>
                <?php endif; ?>
            </select>

            <label>Appointment Date *</label>
            <input type="date" name="appointment_date" min="<?= date('Y-m-d'); ?>" required>

            <label>Appointment Time *</label>
            <input type="time" name="appointment_time" required>

            <label>Notes (Optional)</label>
            <textarea name="notes" placeholder="Any specific concerns or reasons for the visit..."></textarea>

            <button type="submit" name="book_appointment" class="btn-primary">📅 Book Appointment</button>
        </form>
    </div>

    <div class="card">
        <h3>📋 My Appointments</h3>

        <?php if ($appointments->num_rows > 0): ?>
            <table class="appointments-table">
                <thead>
                    <tr>
                        <th>Child</th>
                        <th>Doctor</th>
                        <th>Specialization</th>
                        <th>Date</th>
                        <th>Time</th>
                        <th>Status</th>
                        <th>Notes</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($a = $appointments->fetch_assoc()): ?>
                        <tr>
                            <td><?= htmlspecialchars($a['child_name']); ?></td>
                            <td>Dr. <?= htmlspecialchars($a['doctor_name']); ?></td>
                            <td><?= htmlspecialchars($a['specialization'] ?: 'N/A'); ?></td>
                            <td><?= date('M d, Y', strtotime($a['appointment_date'])); ?></td>
                            <td><?= date('g:i A', strtotime($a['appointment_time'])); ?></td>
                            <td>
                                <span class="status-badge status-<?= strtolower($a['status']); ?>">
                                    <?= htmlspecialchars($a['status']); ?>
                                </span>
                            </td>
                            <td><?= htmlspecialchars($a['notes'] ?: '-'); ?></td>
                            <td>
                                <?php if ($a['status'] !== 'Cancelled' && strtotime($a['appointment_date']) >= strtotime(date('Y-m-d'))): ?>
                                    <button class="btn-cancel" onclick="return confirm('Are you sure you want to cancel this appointment?') && (window.location.href='appointments.php?cancel=<?= $a['appointment_id']; ?>')">
                                        Cancel
                                    </button>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p class="no-data">No appointments found. Book your first appointment above!</p>
        <?php endif; ?>
    </div>

</main>

</body>
</html>

<?php
$conn->close();
?>