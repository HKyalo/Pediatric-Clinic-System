<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>PA-EHR System</title>
<link rel="stylesheet" href="assets\css\style.css">
</head>
<body>
<header>
  <div class="container header-container">
    <h1 class="logo">PediaLink</h1>
    <nav>
      <a href="#login">Login</a>
      <a href="register.php">Register</a>
    </nav>
  </div>
</header>

<section class="hero">
  <div class="container hero-content">
    <div class="hero-half hero-text">
      <h2>Manage your child’s health efficiently</h2>
      <p>Register children, schedule appointments, track medical history, and receive notifications — all in one secure platform for guardians, doctors, and admins.</p>
    </div>
<div class="hero-half hero-login" id="login">

  <!-- ===== Guardian / Child Login ===== -->
  <h3>Guardian / Child Login</h3>
  <form method="POST" action="login_guardian.php">
    <input type="email" name="email" placeholder="Guardian Email" required>
    <input type="password" name="password" placeholder="Password" required>
    <button type="submit" name="login_guardian">Login</button>
  </form>
  <p>New guardian? <a href="register.php">Register here</a></p>

  <hr style="margin:20px 0;">

  <!-- ===== Staff Login ===== -->
  <h3>Staff Login</h3>
  <form method="POST" action="login_staff.php">
    <input type="email" name="email" placeholder="Email" required>
    <input type="password" name="password" placeholder="Password" required>
    <select name="role" required>
      <option value="">Login as</option>
      <option value="doctor">Doctor</option>
      <option value="admin">Admin</option>
    </select>
    <button type="submit" name="login_staff">Login</button>
  </form>

</div>

  </div>
</section>

<section class="features">
  <div class="container">
    <h2>System Features</h2>
    <div class="feature-cards">
      <div class="card">
        <h3>Appointment Scheduling</h3>
        <p>Book, reschedule, and cancel appointments easily for your child.</p>
      </div>
      <div class="card">
        <h3>Electronic Health Records</h3>
        <p>Doctors can securely update and track your child’s medical history.</p>
      </div>
      <div class="card">
        <h3>Notifications</h3>
        <p>Receive reminders for upcoming appointments and important updates.</p>
      </div>
    </div>
  </div>
</section>

<footer>
  &copy; 2026 PA-EHR System
</footer>
<script src="script.js"></script>
</body>
</html>
