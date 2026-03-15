<?php
// Set session to last 24 hours 
ini_set('session.cookie_lifetime', 86400); // 24 hours in seconds
ini_set('session.gc_maxlifetime', 86400);   // 24 hours in seconds
session_set_cookie_params(86400); // Also set cookie params
session_start();
require_once __DIR__ . "/config/db.php";

// ============================================
// SECURITY CHECK - Only logged-in guardians
// ============================================
if (!isset($_SESSION['guardian_id'])) {
    header("Location: index.php");
    exit();
}

$guardian_id = $_SESSION['guardian_id'];

// ============================================
// HANDLE CHILD SELECTION
// ============================================
if (isset($_GET['child_id'])) {
    $child_id = intval($_GET['child_id']);
    
    // Verify this child belongs to this guardian
    $verify_query = $conn->prepare("
        SELECT child_id 
        FROM children 
        WHERE child_id = ? AND guardian_id = ?
    ");
    $verify_query->bind_param("ii", $child_id, $guardian_id);
    $verify_query->execute();
    
    if ($verify_query->get_result()->num_rows > 0) {
        $_SESSION['selected_child_id'] = $child_id;
        header("Location: child_dashboard.php");
        exit();
    }
    $verify_query->close();
}

// ============================================
// FETCH ALL CHILDREN FOR THIS GUARDIAN
// ============================================
$children_query = $conn->prepare("
    SELECT child_id, first_name, last_name, date_of_birth, gender 
    FROM children 
    WHERE guardian_id = ? 
    ORDER BY first_name
");
$children_query->bind_param("i", $guardian_id);
$children_query->execute();
$children = $children_query->get_result()->fetch_all(MYSQLI_ASSOC);
$children_query->close();

// ============================================
// HELPER FUNCTION TO CALCULATE AGE
// ============================================
function calculate_age($birth_date) {
    $birth = new DateTime($birth_date);
    $today = new DateTime();
    $age = $today->diff($birth);
    return $age->y;
}
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD HTML 4.01 Strict//EN" "http://www.w3.org/TR/html4/strict.dtd">
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <title>Select Child - PCASS</title>
    <style>
        /* ===== SELECT CHILD PAGE STYLES ===== */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: Arial, sans-serif;
            background: #0b1a33;
            padding: 40px;
        }
        
        .select-container {
            max-width: 1000px;
            margin: 0 auto;
        }
        
        /* Header */
        .page-header {
            text-align: center;
            margin-bottom: 40px;
        }
        
        .page-header h1 {
            color: white;
            font-size: 36px;
            margin-bottom: 10px;
        }
        
        .page-header p {
            color: #a3c6ff;
            font-size: 16px;
        }
        
        /* Children Grid - Fixed 3 columns */
        .children-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 30px;
            margin-bottom: 40px;
        }
        
        /* Child Card */
        .child-card {
            background: white;
            border-radius: 16px;
            padding: 30px 20px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
            text-decoration: none;
            display: block;
            border-left: 4px solid #0b1a33;
        }
        
        .child-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 8px 30px rgba(255,255,255,0.15);
        }
        
        /* Avatar */
        .child-avatar {
            width: 110px;
            height: 110px;
            border-radius: 50%;
            background: #0b1a33;
            margin: 0 auto 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 42px;
            font-weight: 700;
            border: 3px solid #ffd966;
        }
        
        .child-avatar.female {
            background: #1e3a5f;
        }
        
        /* Child Info */
        .child-name {
            font-size: 22px;
            font-weight: 700;
            color: #0b1a33;
            margin-bottom: 8px;
        }
        
        .child-details {
            color: #5a6f8c;
            font-size: 14px;
            margin-bottom: 12px;
        }
        
        /* Gender Badge */
        .gender-badge {
            display: inline-block;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
        }
        
        .gender-badge.male {
            background: #e6f0ff;
            color: #0b1a33;
            border: 1px solid #b8d1ff;
        }
        
        .gender-badge.female {
            background: #fce7f3;
            color: #9d174d;
            border: 1px solid #fbcfe8;
        }
        
        /* Logout Link */
        .logout-section {
            text-align: center;
            margin-top: 30px;
        }
        
        .logout-link {
            color: #a3c6ff;
            text-decoration: none;
            font-size: 14px;
            padding: 8px 20px;
            border: 1px solid #1e3a5f;
            border-radius: 6px;
            transition: all 0.2s;
        }
        
        .logout-link:hover {
            color: white;
            background: #1e3a5f;
            border-color: #ffd966;
        }
        
        /* Empty State */
        .empty-state {
            background: white;
            border-radius: 16px;
            padding: 60px;
            text-align: center;
            border-left: 4px solid #0b1a33;
        }
        
        .empty-state p {
            color: #5a6f8c;
            font-size: 16px;
            margin-bottom: 20px;
        }
        
        .add-child-btn {
            display: inline-block;
            background: #0b1a33;
            color: white;
            text-decoration: none;
            padding: 12px 30px;
            border-radius: 8px;
            font-weight: 600;
            transition: background 0.2s;
        }
        
        .add-child-btn:hover {
            background: #1e3a5f;
        }
    </style>
</head>
<body>

<div class="select-container">
    
    <!-- ===== PAGE HEADER ===== -->
    <div class="page-header">
        <h1>Select Child</h1>
        <p>Who are you here for today?</p>
    </div>
    
    <!-- ===== CHILDREN GRID ===== -->
    <?php if (empty($children)): ?>
        
        <!-- Empty State - No Children -->
        <div class="empty-state">
            <p>You haven't added any children yet.</p>
            <a href="add_child.php" class="add-child-btn">➕ Add Your First Child</a>
        </div>
        
    <?php else: ?>
        
        <div class="children-grid">
            <?php foreach ($children as $child): 
                $age = calculate_age($child['date_of_birth']);
                $initials = strtoupper(substr($child['first_name'], 0, 1) . substr($child['last_name'], 0, 1));
                $is_female = strtolower($child['gender']) === 'female';
                $gender_class = $is_female ? 'female' : 'male';
            ?>
            
            <a href="?child_id=<?= $child['child_id'] ?>" class="child-card">
                <!-- Avatar with Initials -->
                <div class="child-avatar <?= $is_female ? 'female' : '' ?>">
                    <?= $initials ?>
                </div>
                
                <!-- Child Name -->
                <div class="child-name">
                    <?= htmlspecialchars($child['first_name'] . ' ' . $child['last_name']) ?>
                </div>
                
                <!-- Age -->
                <div class="child-details">
                    <?= $age ?> years old
                </div>
                
                <!-- Gender Badge -->
                <div class="gender-badge <?= $gender_class ?>">
                    <?= ucfirst($child['gender']) ?>
                </div>
            </a>
            
            <?php endforeach; ?>
        </div>
        
    <?php endif; ?>
    
    <!-- ===== LOGOUT LINK ===== -->
    <div class="logout-section">
        <a href="logout.php" class="logout-link">← Logout</a>
    </div>
</div>

</body>
</html>