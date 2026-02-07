<?php
session_start();
require 'includes/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'doctor') {
    header("Location: index.php");
    exit();
}

$doctor_id = $_SESSION['user_id'];

if (!isset($_GET['child_id'])) {
    die("Invalid patient.");
}

$doctor_id = $_SESSION['doctor_id'];
$child_id = $_GET['child_id'];

/* =====================
   CHILD DETAILS
===================== */
$childStmt = $conn->prepare("
    SELECT first_name, last_name, gender, date_of_birth
    FROM children
    WHERE child_id = ?
");
$childStmt->bind_param("i", $child_id);
$childStmt->execute();
$child = $childStmt->get_result()->fetch_assoc();

/* =====================
   ADD MEDICAL RECORD
===================== */
if (isset($_POST['add_record'])) {
    $diagnosis = $_POST['diagnosis'];
    $treatment = $_POST['treatment'];
    $notes = $_POST['notes'];

    $stmt = $conn->prepare("
        INSERT INTO medical_records 
        (child_id, doctor_id, diagnosis, treatment, notes)
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->bind_param("iisss", $child_id, $doctor_id, $diagnosis, $treatment, $notes);
    $stmt->execute();
}

/* =====================
   ADD VACCINATION
===================== */
if (isset($_POST['add_vaccine'])) {
    $vaccine_id = $_POST['vaccine_id'];
    $date_given = $_POST['date_given'];

    $stmt = $conn->prepare("
        INSERT INTO vaccination_records 
        (child_id, doctor_id, vaccine_id, date_administered)
        VALUES (?, ?, ?, ?)
    ");
    $stmt->bind_param("iiis", $child_id, $doctor_id, $vaccine_id, $date_given);
    $stmt->execute();
}

/* =====================
   FETCH MEDICAL RECORDS
===================== */
$records = $conn->prepare("
    SELECT diagnosis, treatment, notes, created_at
    FROM medical_records
    WHERE child_id = ?
    ORDER BY created_at DESC
");
$records->bind_param("i", $child_id);
$records->execute();
$recordsResult = $records->get_result();

/* =====================
   FETCH VACCINATIONS
===================== */
$vaccinations = $conn->prepare("
    SELECT v.name, vr.date_administered
    FROM vaccination_records vr
    JOIN vaccines v ON vr.vaccine_id = v.vaccine_id
    WHERE vr.child_id = ?
");
$vaccinations->bind_param("i", $child_id);
$vaccinations->execute();
$vaccResult = $vaccinations->get_result();

/* =====================
   AVAILABLE VACCINES
===================== */
$vaccineList = $conn->query("SELECT vaccine_id, name FROM vaccines");
?>

<!DOCTYPE html>
<html>
<head>
    <title>Patient EHR</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>

<h2>Electronic Health Record</h2>

<!-- PATIENT INFO -->
<div class="dashboard-section">
    <h3>Patient Information</h3>
    <p><strong>Name:</strong> <?= $child['first_name'].' '.$child['last_name']; ?></p>
    <p><strong>Gender:</strong> <?= $child['gender']; ?></p>
    <p><strong>Date of Birth:</strong> <?= $child['date_of_birth']; ?></p>
</div>

<!-- MEDICAL HISTORY -->
<div class="dashboard-section">
    <h3>Medical History</h3>

    <table class="data-table">
        <thead>
            <tr>
                <th>Diagnosis</th>
                <th>Treatment</th>
                <th>Notes</th>
                <th>Date</th>
            </tr>
        </thead>
        <tbody>
        <?php if ($recordsResult->num_rows > 0): ?>
            <?php while ($row = $recordsResult->fetch_assoc()): ?>
                <tr>
                    <td><?= $row['diagnosis']; ?></td>
                    <td><?= $row['treatment']; ?></td>
                    <td><?= $row['notes']; ?></td>
                    <td><?= $row['created_at']; ?></td>
                </tr>
            <?php endwhile; ?>
        <?php else: ?>
            <tr><td colspan="4">No medical records yet.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>

    <h4>Add Medical Record</h4>
    <form method="POST">
        <input name="diagnosis" class="form-control" placeholder="Diagnosis" required>
        <input name="treatment" class="form-control" placeholder="Treatment" required>
        <textarea name="notes" class="form-control" placeholder="Notes"></textarea>
        <button name="add_record" class="btn btn-primary">Save Record</button>
    </form>
</div>

<!-- VACCINATION HISTORY -->
<div class="dashboard-section">
    <h3>Vaccination History</h3>

    <table class="data-table">
        <thead>
            <tr>
                <th>Vaccine</th>
                <th>Date Administered</th>
            </tr>
        </thead>
        <tbody>
        <?php if ($vaccResult->num_rows > 0): ?>
            <?php while ($v = $vaccResult->fetch_assoc()): ?>
                <tr>
                    <td><?= $v['name']; ?></td>
                    <td><?= $v['date_administered']; ?></td>
                </tr>
            <?php endwhile; ?>
        <?php else: ?>
            <tr><td colspan="2">No vaccines administered.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>

    <h4>Administer Vaccine</h4>
    <form method="POST">
        <select name="vaccine_id" class="form-control" required>
            <option value="">Select Vaccine</option>
            <?php while ($vac = $vaccineList->fetch_assoc()): ?>
                <option value="<?= $vac['vaccine_id']; ?>">
                    <?= $vac['name']; ?>
                </option>
            <?php endwhile; ?>
        </select>
        <input type="date" name="date_given" class="form-control" required>
        <button name="add_vaccine" class="btn btn-success">Administer</button>
    </form>
</div>

</body>
</html>
