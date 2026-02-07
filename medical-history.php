<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['guardian_id'])) {
    header("Location: index.php");
    exit();
}

// Connect to database
$conn = new mysqli("localhost", "root", "", "PediaLink", 3307);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$guardian_id = $_SESSION['guardian_id'];

// Get child info
$stmt = $conn->prepare("SELECT child_id, first_name, last_name, gender, date_of_birth FROM children WHERE guardian_id = ? LIMIT 1");
$stmt->bind_param("i", $guardian_id);
$stmt->execute();
$child_result = $stmt->get_result();

if ($child_result->num_rows === 0) {
    header("Location: child_dashboard.php");
    exit();
}

$child = $child_result->fetch_assoc();
$child_id = $child['child_id'];
$child_name = $child['first_name'] . ' ' . $child['last_name'];

// Calculate age
$dob = new DateTime($child['date_of_birth']);
$now = new DateTime();
$age = $now->diff($dob)->y;

$stmt->close();

// Get guardian info
$stmt = $conn->prepare("SELECT name, phone FROM guardians WHERE id = ?");
$stmt->bind_param("i", $guardian_id);
$stmt->execute();
$guardian = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Get latest vitals (if medical_records table exists)
$latest_vitals = null;
$vitals_check = $conn->query("SHOW TABLES LIKE 'medical_records'");
if ($vitals_check->num_rows > 0) {
    $stmt = $conn->prepare("SELECT * FROM medical_records WHERE child_id = ? ORDER BY record_date DESC LIMIT 1");
    $stmt->bind_param("i", $child_id);
    $stmt->execute();
    $vitals_result = $stmt->get_result();
    if ($vitals_result->num_rows > 0) {
        $latest_vitals = $vitals_result->fetch_assoc();
    }
    $stmt->close();
}

// Get growth records (if growth_records table exists)
$growth_records = null;
$growth_check = $conn->query("SHOW TABLES LIKE 'growth_records'");
if ($growth_check->num_rows > 0) {
    $stmt = $conn->prepare("SELECT * FROM growth_records WHERE child_id = ? ORDER BY record_date DESC LIMIT 10");
    $stmt->bind_param("i", $child_id);
    $stmt->execute();
    $growth_records = $stmt->get_result();
    $stmt->close();
}

// Get medications/prescriptions (if prescriptions table exists)
$prescriptions = null;
$prescription_check = $conn->query("SHOW TABLES LIKE 'prescriptions'");
if ($prescription_check->num_rows > 0) {
    $stmt = $conn->prepare("SELECT p.*, d.full_name as doctor_name 
                            FROM prescriptions p 
                            LEFT JOIN doctors d ON p.doctor_id = d.doctor_id 
                            WHERE p.child_id = ? 
                            ORDER BY p.start_date DESC");
    $stmt->bind_param("i", $child_id);
    $stmt->execute();
    $prescriptions = $stmt->get_result();
    $stmt->close();
}

// Get past illnesses (if illnesses table exists)
$illnesses = null;
$illness_check = $conn->query("SHOW TABLES LIKE 'illnesses'");
if ($illness_check->num_rows > 0) {
    $stmt = $conn->prepare("SELECT i.*, d.full_name as doctor_name 
                            FROM illnesses i 
                            LEFT JOIN doctors d ON i.doctor_id = d.doctor_id 
                            WHERE i.child_id = ? 
                            ORDER BY i.diagnosis_date DESC");
    $stmt->bind_param("i", $child_id);
    $stmt->execute();
    $illnesses = $stmt->get_result();
    $stmt->close();
}

// Get allergies (if allergies table exists)
$allergies = null;
$allergy_check = $conn->query("SHOW TABLES LIKE 'allergies'");
if ($allergy_check->num_rows > 0) {
    $stmt = $conn->prepare("SELECT * FROM allergies WHERE child_id = ? ORDER BY date_noted DESC");
    $stmt->bind_param("i", $child_id);
    $stmt->execute();
    $allergies = $stmt->get_result();
    $stmt->close();
}

// Get lab results (if lab_results table exists)
$lab_results = null;
$lab_check = $conn->query("SHOW TABLES LIKE 'lab_results'");
if ($lab_check->num_rows > 0) {
    $stmt = $conn->prepare("SELECT l.*, d.full_name as doctor_name 
                            FROM lab_results l 
                            LEFT JOIN doctors d ON l.doctor_id = d.doctor_id 
                            WHERE l.child_id = ? 
                            ORDER BY l.test_date DESC");
    $stmt->bind_param("i", $child_id);
    $stmt->execute();
    $lab_results = $stmt->get_result();
    $stmt->close();
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Patient EHR - PA-EHR System</title>
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="dashboard-body">

<aside class="sidebar">
  <h2>PA-EHR Dashboard</h2>
  <nav>
    <ul>
      <li><a href="child_dashboard.php">Dashboard</a></li>
      <li><a href="appointments.php">Appointments</a></li>
      <li><a href="medical-history.php">Medical History</a></li>
      <li><a href="vaccinations.php">Vaccinations</a></li>
      <li><a href="notifications.php">Notifications</a></li>
      <li><a href="profile.php">Profile</a></li>
      <li><a href="logout.php">Logout</a></li>
    </ul>
  </nav>
</aside>

<main class="dashboard-main">
  <h1>My Electronic Health Record (EHR)</h1>

  <!-- ===== PATIENT SUMMARY ===== -->
  <div class="card child-details-card">
    <h3>Patient Summary</h3>
    <div class="details-grid">
      <div class="details-half">
        <h4>Child Details</h4>
        <p><strong>Name:</strong> <?php echo htmlspecialchars($child_name); ?></p>
        <p><strong>Age:</strong> <?php echo $age; ?> years</p>
        <p><strong>Gender:</strong> <?php echo htmlspecialchars($child['gender']); ?></p>
        <p><strong>Date of Birth:</strong> <?php echo date('d M Y', strtotime($child['date_of_birth'])); ?></p>
      </div>
      <div class="details-half">
        <h4>Guardian Details</h4>
        <p><strong>Name:</strong> <?php echo htmlspecialchars($guardian['name']); ?></p>
        <p><strong>Phone:</strong> <?php echo htmlspecialchars($guardian['phone']); ?></p>
        <p><strong>Relationship:</strong> Guardian</p>
      </div>
    </div>
  </div>

  <!-- ===== CURRENT CONSULTATION ===== -->
  <div class="card">
    <h3>Current Clinical Encounter</h3>

    <!-- Triage Assessment -->
    <div class="ehr-section">
      <h4>Triage Assessment</h4>
      <?php if ($latest_vitals): ?>
        <table class="medical-table">
          <thead>
            <tr><th>Parameter</th><th>Value</th><th>Unit</th></tr>
          </thead>
          <tbody>
            <?php if (isset($latest_vitals['weight'])): ?>
              <tr><td>Weight</td><td><?php echo $latest_vitals['weight']; ?></td><td>kg</td></tr>
            <?php endif; ?>
            <?php if (isset($latest_vitals['temperature'])): ?>
              <tr><td>Temperature</td><td><?php echo $latest_vitals['temperature']; ?></td><td>°C</td></tr>
            <?php endif; ?>
            <?php if (isset($latest_vitals['heart_rate'])): ?>
              <tr><td>Heart Rate</td><td><?php echo $latest_vitals['heart_rate']; ?></td><td>bpm</td></tr>
            <?php endif; ?>
            <?php if (isset($latest_vitals['respiratory_rate'])): ?>
              <tr><td>Respiratory Rate</td><td><?php echo $latest_vitals['respiratory_rate']; ?></td><td>breaths/min</td></tr>
            <?php endif; ?>
            <?php if (isset($latest_vitals['oxygen_saturation'])): ?>
              <tr><td>Oxygen Saturation</td><td><?php echo $latest_vitals['oxygen_saturation']; ?></td><td>%</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      <?php else: ?>
        <p>No vitals recorded yet. Will be added by healthcare provider during visits.</p>
      <?php endif; ?>
    </div>

    <!-- Presenting Complaint -->
    <div class="ehr-section">
      <h4>Presenting Complaint</h4>
      <?php if ($latest_vitals && isset($latest_vitals['complaint'])): ?>
        <p><?php echo htmlspecialchars($latest_vitals['complaint']); ?></p>
      <?php else: ?>
        <p>No current complaint recorded.</p>
      <?php endif; ?>
    </div>

    <!-- Investigations / Test Results -->
    <div class="ehr-section">
      <h4>Investigations / Test Results</h4>
      <?php if ($latest_vitals && isset($latest_vitals['investigations'])): ?>
        <p><?php echo nl2br(htmlspecialchars($latest_vitals['investigations'])); ?></p>
      <?php else: ?>
        <p>No investigations recorded for current visit.</p>
      <?php endif; ?>
    </div>

    <!-- Clinical Assessment & Diagnosis -->
    <div class="ehr-section">
      <h4>Clinical Assessment & Diagnosis</h4>
      <?php if ($latest_vitals && isset($latest_vitals['diagnosis'])): ?>
        <p><?php echo htmlspecialchars($latest_vitals['diagnosis']); ?></p>
      <?php else: ?>
        <p>No diagnosis recorded yet.</p>
      <?php endif; ?>
    </div>
  </div>

  <!-- ===== GROWTH & DEVELOPMENT ===== -->
  <div class="card">
    <h3>Growth & Development</h3>
    <table class="medical-table">
      <thead>
        <tr>
          <th>Date</th><th>Height (cm)</th><th>Weight (kg)</th><th>Head Circumference (cm)</th><th>BMI</th><th>Development Notes</th>
        </tr>
      </thead>
      <tbody>
        <?php if ($growth_records && $growth_records->num_rows > 0): ?>
          <?php while ($growth = $growth_records->fetch_assoc()): ?>
            <tr>
              <td><?php echo date('d M Y', strtotime($growth['record_date'])); ?></td>
              <td><?php echo $growth['height'] ?? '-'; ?></td>
              <td><?php echo $growth['weight'] ?? '-'; ?></td>
              <td><?php echo $growth['head_circumference'] ?? '-'; ?></td>
              <td><?php echo $growth['bmi'] ?? '-'; ?></td>
              <td><?php echo htmlspecialchars($growth['notes'] ?? '-'); ?></td>
            </tr>
          <?php endwhile; ?>
        <?php else: ?>
          <tr><td colspan="6" style="text-align: center;">No growth records yet. Will be updated by healthcare provider.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <!-- ===== TREATMENT & MEDICATIONS ===== -->
  <div class="card">
    <h3>Treatment & Medications</h3>
    <table class="medical-table">
      <thead>
        <tr><th>Medication</th><th>Dosage & Frequency</th><th>Start Date</th><th>End Date</th><th>Prescribing Doctor</th><th>Notes</th></tr>
      </thead>
      <tbody>
        <?php if ($prescriptions && $prescriptions->num_rows > 0): ?>
          <?php while ($rx = $prescriptions->fetch_assoc()): ?>
            <tr>
              <td><?php echo htmlspecialchars($rx['medication_name']); ?></td>
              <td><?php echo htmlspecialchars($rx['dosage']); ?></td>
              <td><?php echo date('Y-m-d', strtotime($rx['start_date'])); ?></td>
              <td><?php echo isset($rx['end_date']) ? date('Y-m-d', strtotime($rx['end_date'])) : 'Ongoing'; ?></td>
              <td>Dr. <?php echo htmlspecialchars($rx['doctor_name']); ?></td>
              <td><?php echo htmlspecialchars($rx['notes'] ?? '-'); ?></td>
            </tr>
          <?php endwhile; ?>
        <?php else: ?>
          <tr><td colspan="6" style="text-align: center;">No medications prescribed yet.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <!-- ===== PAST ILLNESSES & CHRONIC CONDITIONS ===== -->
  <div class="card">
    <h3>Past Illnesses / Chronic Conditions</h3>
    <table class="medical-table">
      <thead>
        <tr><th>Date</th><th>Condition</th><th>Treatment / Management</th><th>Outcome / Notes</th><th>Doctor</th></tr>
      </thead>
      <tbody>
        <?php if ($illnesses && $illnesses->num_rows > 0): ?>
          <?php while ($illness = $illnesses->fetch_assoc()): ?>
            <tr>
              <td><?php echo date('Y-m-d', strtotime($illness['diagnosis_date'])); ?></td>
              <td><?php echo htmlspecialchars($illness['condition_name']); ?></td>
              <td><?php echo htmlspecialchars($illness['treatment']); ?></td>
              <td><?php echo htmlspecialchars($illness['outcome'] ?? $illness['notes']); ?></td>
              <td>Dr. <?php echo htmlspecialchars($illness['doctor_name']); ?></td>
            </tr>
          <?php endwhile; ?>
        <?php else: ?>
          <tr><td colspan="5" style="text-align: center;">No past illnesses recorded.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <!-- ===== ALLERGIES ===== -->
  <div class="card">
    <h3>Allergies</h3>
    <table class="medical-table">
      <thead>
        <tr><th>Allergen</th><th>Type</th><th>Severity</th><th>Date Noted</th><th>Reaction</th></tr>
      </thead>
      <tbody>
        <?php if ($allergies && $allergies->num_rows > 0): ?>
          <?php while ($allergy = $allergies->fetch_assoc()): ?>
            <tr>
              <td><?php echo htmlspecialchars($allergy['allergen']); ?></td>
              <td><?php echo htmlspecialchars($allergy['allergy_type']); ?></td>
              <td><?php echo htmlspecialchars($allergy['severity']); ?></td>
              <td><?php echo date('Y-m-d', strtotime($allergy['date_noted'])); ?></td>
              <td><?php echo htmlspecialchars($allergy['reaction']); ?></td>
            </tr>
          <?php endwhile; ?>
        <?php else: ?>
          <tr><td colspan="5" style="text-align: center;">No allergies recorded.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <!-- ===== LAB RESULTS ===== -->
  <div class="card">
    <h3>Lab Results / Reports</h3>
    <table class="medical-table">
      <thead>
        <tr><th>Date</th><th>Test Name</th><th>Result / Summary</th><th>Doctor</th><th>Attachment</th></tr>
      </thead>
      <tbody>
        <?php if ($lab_results && $lab_results->num_rows > 0): ?>
          <?php while ($lab = $lab_results->fetch_assoc()): ?>
            <tr>
              <td><?php echo date('Y-m-d', strtotime($lab['test_date'])); ?></td>
              <td><?php echo htmlspecialchars($lab['test_name']); ?></td>
              <td><?php echo htmlspecialchars($lab['result_summary']); ?></td>
              <td>Dr. <?php echo htmlspecialchars($lab['doctor_name']); ?></td>
              <td><?php echo isset($lab['attachment']) ? htmlspecialchars($lab['attachment']) : '-'; ?></td>
            </tr>
          <?php endwhile; ?>
        <?php else: ?>
          <tr><td colspan="5" style="text-align: center;">No lab results yet.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

</main>
</body>
</html>