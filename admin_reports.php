<?php
session_start();

// Check if user is logged in as admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'admin') {
    header("Location: index.php");
    exit();
}

// Connect to database
$conn = new mysqli("localhost", "root", "", "PediaLink", 3307);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$admin_name = $_SESSION['name'];

// Get comprehensive statistics
$total_patients = $conn->query("SELECT COUNT(*) as count FROM children")->fetch_assoc()['count'];
$total_guardians = $conn->query("SELECT COUNT(*) as count FROM guardians")->fetch_assoc()['count'];
$total_doctors = $conn->query("SELECT COUNT(*) as count FROM doctors")->fetch_assoc()['count'];
$total_appointments = $conn->query("SELECT COUNT(*) as count FROM appointments")->fetch_assoc()['count'];

// Appointments by status
$appointment_stats = $conn->query("
    SELECT status, COUNT(*) as count
    FROM appointments
    GROUP BY status
");

// Appointments by month (last 6 months)
$monthly_appointments = $conn->query("
    SELECT DATE_FORMAT(appointment_date, '%Y-%m') as month, COUNT(*) as count
    FROM appointments
    WHERE appointment_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
    GROUP BY month
    ORDER BY month ASC
");

// Top doctors by appointments
$top_doctors = $conn->query("
    SELECT d.full_name, COUNT(a.appointment_id) as appointment_count
    FROM doctors d
    LEFT JOIN appointments a ON d.doctor_id = a.doctor_id
    GROUP BY d.doctor_id
    ORDER BY appointment_count DESC
    LIMIT 5
");

// Gender distribution
$gender_stats = $conn->query("
    SELECT gender, COUNT(*) as count
    FROM children
    GROUP BY gender
");

// Age distribution
$age_distribution = $conn->query("
    SELECT 
        CASE 
            WHEN TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) < 1 THEN '0-1 years'
            WHEN TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) BETWEEN 1 AND 5 THEN '1-5 years'
            WHEN TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) BETWEEN 6 AND 12 THEN '6-12 years'
            WHEN TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) BETWEEN 13 AND 18 THEN '13-18 years'
        END as age_group,
        COUNT(*) as count
    FROM children
    GROUP BY age_group
");

// Vaccination completion rate
$total_vaccines = $conn->query("SELECT COUNT(*) as count FROM vaccines")->fetch_assoc()['count'];
$completed_vaccinations = $conn->query("SELECT COUNT(*) as count FROM vaccination_records WHERE status = 'Completed'")->fetch_assoc()['count'];
$pending_vaccinations = $conn->query("SELECT COUNT(*) as count FROM vaccination_records WHERE status = 'Pending'")->fetch_assoc()['count'];

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Reports - PediaLink</title>
<link rel="stylesheet" href="assets/css/style.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<style>
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
    margin-bottom: 30px;
}
.stat-box {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 25px;
    border-radius: 8px;
    text-align: center;
}
.stat-box:nth-child(2) {
    background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
}
.stat-box:nth-child(3) {
    background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
}
.stat-box:nth-child(4) {
    background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);
}
.stat-number {
    font-size: 3em;
    font-weight: bold;
    margin: 10px 0;
}
.stat-label {
    font-size: 1.1em;
    opacity: 0.9;
}
.two-column-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
    margin-bottom: 20px;
}
@media (max-width: 968px) {
    .two-column-grid {
        grid-template-columns: 1fr;
    }
}
.report-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 15px;
}
.report-table th,
.report-table td {
    padding: 10px;
    text-align: left;
    border-bottom: 1px solid #ddd;
}
.report-table th {
    background-color: #f8f9fa;
    font-weight: bold;
}
.print-btn {
    background-color: #007bff;
    color: white;
    padding: 10px 20px;
    border: none;
    border-radius: 5px;
    cursor: pointer;
    float: right;
    margin-bottom: 20px;
}
.print-btn:hover {
    background-color: #0056b3;
}
</style>
</head>
<body class="dashboard-body">

<aside class="sidebar">
  <h2>Admin Panel</h2>
  <nav>
    <ul>
      <li><a href="admin_dashboard.php">Dashboard</a></li>
      <li><a href="manage_doctors.php">Manage Doctors</a></li>
      <li><a href="manage_guardians.php">Manage Guardians</a></li>
      <li><a href="manage_children.php">Manage Children</a></li>
      <li><a href="manage_appointments.php">Manage Appointments</a></li>
      <li><a href="manage_vaccines.php">Manage Vaccines</a></li>
      <li><a href="admin_reports.php" class="active">Reports</a></li>
      <li><a href="logout.php">Logout</a></li>
    </ul>
  </nav>
</aside>

<main class="dashboard-main">
  <h1>System Reports & Analytics</h1>
  <button class="print-btn" onclick="window.print()">🖨️ Print Report</button>

  <!-- Overall Statistics -->
  <div class="stats-grid">
    <div class="stat-box">
      <div class="stat-label">Total Patients</div>
      <div class="stat-number"><?php echo $total_patients; ?></div>
    </div>
    <div class="stat-box">
      <div class="stat-label">Total Guardians</div>
      <div class="stat-number"><?php echo $total_guardians; ?></div>
    </div>
    <div class="stat-box">
      <div class="stat-label">Total Doctors</div>
      <div class="stat-number"><?php echo $total_doctors; ?></div>
    </div>
    <div class="stat-box">
      <div class="stat-label">Total Appointments</div>
      <div class="stat-number"><?php echo $total_appointments; ?></div>
    </div>
  </div>

  <!-- Charts Row -->
  <div class="two-column-grid">
    
    <!-- Appointments by Status -->
    <div class="card">
      <h3>Appointments by Status</h3>
      <canvas id="statusChart" height="200"></canvas>
    </div>

    <!-- Gender Distribution -->
    <div class="card">
      <h3>Patient Gender Distribution</h3>
      <canvas id="genderChart" height="200"></canvas>
    </div>

  </div>

  <!-- Monthly Trends -->
  <div class="card">
    <h3>Appointment Trends (Last 6 Months)</h3>
    <canvas id="trendChart" height="100"></canvas>
  </div>

  <!-- Two Column Grid -->
  <div class="two-column-grid">
    
    <!-- Top Doctors -->
    <div class="card">
      <h3>Top 5 Doctors by Appointments</h3>
      <table class="report-table">
        <thead>
          <tr>
            <th>Doctor</th>
            <th>Appointments</th>
          </tr>
        </thead>
        <tbody>
          <?php while ($doctor = $top_doctors->fetch_assoc()): ?>
            <tr>
              <td>Dr. <?php echo htmlspecialchars($doctor['full_name']); ?></td>
              <td><?php echo $doctor['appointment_count']; ?></td>
            </tr>
          <?php endwhile; ?>
        </tbody>
      </table>
    </div>

    <!-- Age Distribution -->
    <div class="card">
      <h3>Patient Age Distribution</h3>
      <table class="report-table">
        <thead>
          <tr>
            <th>Age Group</th>
            <th>Count</th>
          </tr>
        </thead>
        <tbody>
          <?php while ($age = $age_distribution->fetch_assoc()): ?>
            <tr>
              <td><?php echo htmlspecialchars($age['age_group']); ?></td>
              <td><?php echo $age['count']; ?></td>
            </tr>
          <?php endwhile; ?>
        </tbody>
      </table>
    </div>

  </div>

  <!-- Vaccination Stats -->
  <div class="card">
    <h3>Vaccination Coverage</h3>
    <p><strong>Total Vaccines in Schedule:</strong> <?php echo $total_vaccines; ?></p>
    <p><strong>Completed Vaccinations:</strong> <?php echo $completed_vaccinations; ?></p>
    <p><strong>Pending Vaccinations:</strong> <?php echo $pending_vaccinations; ?></p>
    <?php if ($total_vaccines > 0): 
        $completion_rate = round(($completed_vaccinations / ($completed_vaccinations + $pending_vaccinations)) * 100);
    ?>
      <div style="background-color: #e0e0e0; border-radius: 10px; overflow: hidden; height: 30px; margin-top: 10px;">
        <div style="background: linear-gradient(90deg, #43e97b 0%, #38f9d7 100%); height: 100%; width: <?php echo $completion_rate; ?>%; display: flex; align-items: center; justify-content: center; color: white; font-weight: bold;">
          <?php echo $completion_rate; ?>%
        </div>
      </div>
    <?php endif; ?>
  </div>

</main>

<script>
// Appointments by Status Chart
const statusCtx = document.getElementById('statusChart').getContext('2d');
new Chart(statusCtx, {
    type: 'pie',
    data: {
        labels: [
            <?php 
            $appointment_stats->data_seek(0);
            $labels = [];
            $counts = [];
            while ($stat = $appointment_stats->fetch_assoc()) {
                $labels[] = "'" . $stat['status'] . "'";
                $counts[] = $stat['count'];
            }
            echo implode(',', $labels);
            ?>
        ],
        datasets: [{
            data: [<?php echo implode(',', $counts); ?>],
            backgroundColor: ['#ffc107', '#28a745', '#dc3545', '#17a2b8']
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false
    }
});

// Gender Distribution Chart
const genderCtx = document.getElementById('genderChart').getContext('2d');
new Chart(genderCtx, {
    type: 'doughnut',
    data: {
        labels: [
            <?php 
            $gender_stats->data_seek(0);
            $genderLabels = [];
            $genderCounts = [];
            while ($gender = $gender_stats->fetch_assoc()) {
                $genderLabels[] = "'" . $gender['gender'] . "'";
                $genderCounts[] = $gender['count'];
            }
            echo implode(',', $genderLabels);
            ?>
        ],
        datasets: [{
            data: [<?php echo implode(',', $genderCounts); ?>],
            backgroundColor: ['#4facfe', '#f093fb', '#43e97b']
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false
    }
});

// Monthly Trend Chart
const trendCtx = document.getElementById('trendChart').getContext('2d');
new Chart(trendCtx, {
    type: 'line',
    data: {
        labels: [
            <?php 
            $monthly_appointments->data_seek(0);
            $monthLabels = [];
            $monthCounts = [];
            while ($month = $monthly_appointments->fetch_assoc()) {
                $monthLabels[] = "'" . $month['month'] . "'";
                $monthCounts[] = $month['count'];
            }
            echo implode(',', $monthLabels);
            ?>
        ],
        datasets: [{
            label: 'Appointments',
            data: [<?php echo implode(',', $monthCounts); ?>],
            borderColor: '#667eea',
            backgroundColor: 'rgba(102, 126, 234, 0.1)',
            tension: 0.3,
            fill: true
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                display: false
            }
        }
    }
});
</script>

</body>
</html>