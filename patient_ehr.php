<?php
session_start();

// Check if user is logged in as doctor
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'doctor') {
    header("Location: index.php");
    exit();
}

// Connect to database
$conn = new mysqli("localhost", "root", "", "PediaLink", 3307);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$doctor_id = $_SESSION['user_id'];
$doctor_name = $_SESSION['name'];
$child_id = $_GET['id'] ?? 0;
$message = "";
$message_type = "";

// Handle Add Medical Record
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_record'])) {
    $record_date = $_POST['record_date'];
    $weight = $_POST['weight'];
    $temperature = $_POST['temperature'];
    $heart_rate = $_POST['heart_rate'];
    $respiratory_rate = $_POST['respiratory_rate'];
    $oxygen_saturation = $_POST['oxygen_saturation'];
    $complaint = $conn->real_escape_string($_POST['complaint']);
    $investigations = $conn->real_escape_string($_POST['investigations']);
    $diagnosis = $conn->real_escape_string($_POST['diagnosis']);
    
    $stmt = $conn->prepare("INSERT INTO medical_records (child_id, doctor_id, record_date, weight, temperature, heart_rate, respiratory_rate, oxygen_saturation, complaint, investigations, diagnosis) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("iisddiiisss", $child_id, $doctor_id, $record_date, $weight, $temperature, $heart_rate, $respiratory_rate, $oxygen_saturation, $complaint, $investigations, $diagnosis);
    
    if ($stmt->execute()) {
        $message = "Medical record added successfully!";
        $message_type = "success";
    } else {
        $message = "Error adding record: " . $stmt->error;
        $message_type = "error";
    }
    $stmt->close();
}

// Handle Add Prescription
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_prescription'])) {
    $medication_name = $conn->real_escape_string($_POST['medication_name']);
    $dosage = $conn->real_escape_string($_POST['dosage']);
    $start_date = $_POST['start_date'];
    $end_date = !empty($_POST['end_date']) ? $_POST['end_date'] : null;
    $notes = $conn->real_escape_string($_POST['prescription_notes']);
    
    $stmt = $conn->prepare("INSERT INTO prescriptions (child_id, doctor_id, medication_name, dosage, start_date, end_date, notes) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("iisssss", $child_id, $doctor_id, $medication_name, $dosage, $start_date, $end_date, $notes);
    
    if ($stmt->execute()) {
        $message = "Prescription added successfully!";
        $message_type = "success";
    } else {
        $message = "Error adding prescription: " . $stmt->error;
        $message_type = "error";
    }
    $stmt->close();
}

// Handle Add Growth Record
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_growth'])) {
    $record_date = $_POST['growth_date'];
    $height = $_POST['height'];
    $weight = $_POST['growth_weight'];
    $head_circumference = $_POST['head_circumference'];
    $bmi = $_POST['bmi'];
    $notes = $conn->real_escape_string($_POST['growth_notes']);
    
    $stmt = $conn->prepare("INSERT INTO growth_records (child_id, record_date, height, weight, head_circumference, bmi, notes) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("isdddds", $child_id, $record_date, $height, $weight, $head_circumference, $bmi, $notes);
    
    if ($stmt->execute()) {
        $message = "Growth record added successfully!";
        $message_type = "success";
    } else {
        $message = "Error adding growth record: " . $stmt->error;
        $message_type = "error";
    }
    $stmt->close();
}

// Handle Mark Vaccination as Completed
if (isset($_GET['complete_vaccine'])) {
    $vaccine_id = $_GET['complete_vaccine'];
    $date_administered = date('Y-m-d');
    
    // Check if record exists
    $check = $conn->prepare("SELECT vaccination_record_id FROM vaccination_records WHERE child_id = ? AND vaccine_id = ?");
    $check->bind_param("ii", $child_id, $vaccine_id);
    $check->execute();
    $result = $check->get_result();
    
    if ($result->num_rows > 0) {
        // Update existing record
        $stmt = $conn->prepare("UPDATE vaccination_records SET status = 'Completed', date_administered = ?, administered_by = ? WHERE child_id = ? AND vaccine_id = ?");
        $stmt->bind_param("siii", $date_administered, $doctor_id, $child_id, $vaccine_id);
    } else {
        // Create new record
        $stmt = $conn->prepare("INSERT INTO vaccination_records (child_id, vaccine_id, date_administered, administered_by, status) VALUES (?, ?, ?, ?, 'Completed')");
        $stmt->bind_param("iisi", $child_id, $vaccine_id, $date_administered, $doctor_id);
    }
    
    if ($stmt->execute()) {
        $message = "Vaccination marked as completed!";
        $message_type = "success";
    }
    $stmt->close();
    $check->close();
}

// Get child information
$stmt = $conn->prepare("
    SELECT c.*, g.name as guardian_name, g.phone as guardian_phone, g.email as guardian_email
    FROM children c
    LEFT JOIN guardians g ON c.guardian_id = g.id
    WHERE c.child_id = ?
");
$stmt->bind_param("i", $child_id);
$stmt->execute();
$child = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$child) {
    header("Location: doctor_dashboard.php");
    exit();
}

// Calculate age
$dob = new DateTime($child['date_of_birth']);
$now = new DateTime();
$age = $now->diff($dob)->y;
$months = $now->diff($dob)->m;

// Get medical records
$medical_records = $conn->query("
    SELECT mr.*, d.full_name as doctor_name
    FROM medical_records mr
    LEFT JOIN doctors d ON mr.doctor_id = d.doctor_id
    WHERE mr.child_id = $child_id
    ORDER BY mr.record_date DESC
    LIMIT 10
");

// Get prescriptions
$prescriptions = $conn->query("
    SELECT p.*, d.full_name as doctor_name
    FROM prescriptions p
    LEFT JOIN doctors d ON p.doctor_id = d.doctor_id
    WHERE p.child_id = $child_id
    ORDER BY p.start_date DESC
");

// Get growth records
$growth_records = $conn->query("
    SELECT * FROM growth_records
    WHERE child_id = $child_id
    ORDER BY record_date DESC
");

// Get vaccination records
$vaccination_query = "
    SELECT v.vaccine_id, v.vaccine_name, v.recommended_age, 
           vr.status, vr.date_administered, vr.vaccination_record_id
    FROM vaccines v
    LEFT JOIN vaccination_records vr ON v.vaccine_id = vr.vaccine_id AND vr.child_id = $child_id
    ORDER BY v.vaccine_id ASC
";
$vaccinations = $conn->query($vaccination_query);

// Count vaccination completion
$total_vaccines = $conn->query("SELECT COUNT(*) as count FROM vaccines")->fetch_assoc()['count'];
$completed_vaccines = $conn->query("SELECT COUNT(*) as count FROM vaccination_records WHERE child_id = $child_id AND status = 'Completed'")->fetch_assoc()['count'];
$vaccination_percentage = $total_vaccines > 0 ? round(($completed_vaccines / $total_vaccines) * 100) : 0;

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Patient EHR - <?php echo htmlspecialchars($child['first_name'] . ' ' . $child['last_name']); ?></title>
<link rel="stylesheet" href="assets/css/style.css">
<style>
.message {
    padding: 15px;
    margin-bottom: 20px;
    border-radius: 5px;
    text-align: center;
}
.message.success {
    background-color: #d4edda;
    color: #155724;
}
.message.error {
    background-color: #f8d7da;
    color: #721c24;
}
.patient-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 25px;
    border-radius: 8px;
    margin-bottom: 20px;
}
.patient-header h2 {
    margin: 0 0 15px 0;
}
.patient-details-grid {
    display: grid;
    grid-template-columns: 1fr 1fr 1fr;
    gap: 20px;
    margin-top: 15px;
}
@media (max-width: 968px) {
    .patient-details-grid {
        grid-template-columns: 1fr;
    }
}
.medical-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 15px;
}
.medical-table th,
.medical-table td {
    padding: 10px;
    text-align: left;
    border-bottom: 1px solid #ddd;
}
.medical-table th {
    background-color: #f8f9fa;
    font-weight: bold;
}
.medical-table tr:hover {
    background-color: #f5f5f5;
}
.form-section {
    background-color: #f8f9fa;
    padding: 20px;
    border-radius: 8px;
    margin-top: 15px;
}
.two-column {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 15px;
}
.three-column {
    display: grid;
    grid-template-columns: 1fr 1fr 1fr;
    gap: 15px;
}
@media (max-width: 768px) {
    .two-column, .three-column {
        grid-template-columns: 1fr;
    }
}
.action-btn {
    padding: 8px 16px;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    text-decoration: none;
    display: inline-block;
    font-size: 0.85em;
    color: white;
}
.btn-complete {
    background-color: #28a745;
}
.btn-complete:hover {
    background-color: #218838;
}
.status-completed {
    background-color: #d4edda;
    color: #155724;
    padding: 4px 8px;
    border-radius: 3px;
    font-size: 0.85em;
    font-weight: bold;
}
.status-pending {
    background-color: #fff3cd;
    color: #856404;
    padding: 4px 8px;
    border-radius: 3px;
    font-size: 0.85em;
    font-weight: bold;
}
.tab-buttons {
    display: flex;
    gap: 10px;
    margin-bottom: 20px;
    flex-wrap: wrap;
}
.tab-btn {
    padding: 12px 20px;
    border: none;
    background-color: #e9ecef;
    cursor: pointer;
    border-radius: 5px;
    font-weight: bold;
    transition: all 0.3s;
}
.tab-btn:hover {
    background-color: #dee2e6;
}
.tab-btn.active {
    background-color: #007bff;
    color: white;
}
.tab-content {
    display: none;
}
.tab-content.active {
    display: block;
}
.vitals-box {
    background-color: #f8f9fa;
    padding: 15px;
    border-radius: 5px;
    margin: 10px 0;
}
.vaccination-progress {
    background-color: #e0e0e0;
    border-radius: 10px;
    overflow: hidden;
    height: 30px;
    margin: 15px 0;
}
.vaccination-progress-bar {
    background: linear-gradient(90deg, #43e97b 0%, #38f9d7 100%);
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: bold;
    transition: width 0.3s;
}
.record-card {
    border-left: 4px solid #007bff;
    padding: 15px;
    margin-bottom: 15px;
    background-color: #f8f9fa;
    border-radius: 5px;
}
</style>
<script>
function showTab(tabName) {
    // Hide all tabs
    const tabs = document.querySelectorAll('.tab-content');
    tabs.forEach(tab => tab.classList.remove('active'));
    
    // Remove active from all buttons
    const buttons = document.querySelectorAll('.tab-btn');
    buttons.forEach(btn => btn.classList.remove('active'));
    
    // Show selected tab
    document.getElementById(tabName).classList.add('active');
    event.target.classList.add('active');
}
</script>
</head>
<body class="dashboard-body">

<aside class="sidebar">
  <h2>Doctor Panel</h2>
  <nav>
    <ul>
      <li><a href="doctor_dashboard.php">Dashboard</a></li>
      <li><a href="doctor_patients.php">My Patients</a></li>
      <li><a href="logout.php">Logout</a></li>
    </ul>
  </nav>
</aside>

<main class="dashboard-main">
  <p><a href="doctor_dashboard.php">← Back to Dashboard</a></p>

  <?php if ($message): ?>
    <div class="message <?php echo $message_type; ?>">
      <?php echo htmlspecialchars($message); ?>
    </div>
  <?php endif; ?>

  <!-- Patient Header -->
  <div class="patient-header">
    <h2>📋 Electronic Health Record</h2>
    <h1><?php echo htmlspecialchars($child['first_name'] . ' ' . $child['last_name']); ?></h1>
    <div class="patient-details-grid">
      <div>
        <p><strong>Age:</strong> <?php echo $age; ?> years <?php echo $months; ?> months</p>
        <p><strong>Gender:</strong> <?php echo htmlspecialchars($child['gender']); ?></p>
        <p><strong>DOB:</strong> <?php echo date('F d, Y', strtotime($child['date_of_birth'])); ?></p>
      </div>
      <div>
        <p><strong>Guardian:</strong> <?php echo htmlspecialchars($child['guardian_name']); ?></p>
        <p><strong>Phone:</strong> <?php echo htmlspecialchars($child['guardian_phone']); ?></p>
        <p><strong>Email:</strong> <?php echo htmlspecialchars($child['guardian_email']); ?></p>
      </div>
      <div>
        <p><strong>Vaccination Progress:</strong></p>
        <div class="vaccination-progress">
          <div class="vaccination-progress-bar" style="width: <?php echo $vaccination_percentage; ?>%;">
            <?php echo $completed_vaccines; ?>/<?php echo $total_vaccines; ?> (<?php echo $vaccination_percentage; ?>%)
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Tab Navigation -->
  <div class="tab-buttons">
    <button class="tab-btn active" onclick="showTab('overview')">Overview</button>
    <button class="tab-btn" onclick="showTab('add-record')">➕ Add Medical Record</button>
    <button class="tab-btn" onclick="showTab('prescriptions')">💊 Prescriptions</button>
    <button class="tab-btn" onclick="showTab('add-prescription')">➕ Add Prescription</button>
    <button class="tab-btn" onclick="showTab('growth')">📈 Growth Records</button>
    <button class="tab-btn" onclick="showTab('add-growth')">➕ Add Growth Record</button>
    <button class="tab-btn" onclick="showTab('vaccinations')">💉 Vaccinations</button>
  </div>

  <!-- Overview Tab -->
  <div id="overview" class="tab-content active">
    <div class="card">
      <h3>📋 Medical Records History</h3>
      <?php if ($medical_records->num_rows > 0): ?>
        <?php while ($record = $medical_records->fetch_assoc()): ?>
          <div class="record-card">
            <h4>Visit Date: <?php echo date('F d, Y', strtotime($record['record_date'])); ?></h4>
            <p><strong>Doctor:</strong> Dr. <?php echo htmlspecialchars($record['doctor_name']); ?></p>
            
            <div class="vitals-box">
              <strong>Vitals:</strong> 
              Weight: <?php echo $record['weight']; ?>kg | 
              Temp: <?php echo $record['temperature']; ?>°C | 
              HR: <?