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
<!DOCTYPE html>
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
            font-family: 'Segoe UI', Arial, Helvetica, sans-serif;
            min-height: 100vh;
            background-image: url('reception.png');
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            background-attachment: fixed;
            position: relative;
        }
        
        /* Dark overlay for better readability */
        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 0;
        }
        
        .select-container {
            position: relative;
            z-index: 1;
            max-width: 1000px;
            margin: 0 auto;
            padding: 40px 20px;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        
        /* Header */
        .page-header {
            text-align: center;
            margin-bottom: 50px;
        }
        
        .page-header h1 {
            color: white;
            font-size: 42px;
            font-weight: 800;
            margin-bottom: 10px;
            text-shadow: 0 2px 4px rgba(0,0,0,0.2);
        }
        
        .page-header p {
            color: rgba(255, 255, 255, 0.8);
            font-size: 18px;
        }
        
        /* Divider */
        .divider {
            width: 80px;
            height: 4px;
            background: #ffd966;
            margin: 20px auto 0;
            border-radius: 2px;
        }
        
        /* Children Grid - Fixed 3 columns */
        .children-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 30px;
            margin-bottom: 40px;
        }
        
        /* Child Card - Glass effect */
        .child-card {
            background: rgba(11, 26, 51, 0.1);
            backdrop-filter: blur(100px);
            border-radius: 20px;
            padding: 35px 20px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: block;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        .child-card:hover {
            transform: translateY(-8px);
            background: rgba(11, 26, 51, 0.85);
            border-color: #ffd966;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.3);
        }
        
        /* Add Child Card - Special style */
        .add-child-card {
            background: rgba(255, 255, 255, 0.08);
            backdrop-filter: blur(12px);
            border-radius: 20px;
            padding: 35px 20px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: block;
            border: 1px dashed rgba(255, 217, 102, 0.5);
        }
        
        .add-child-card:hover {
            transform: translateY(-8px);
            background: rgba(255, 217, 102, 0.15);
            border-color: #ffd966;
            border-style: solid;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.3);
        }
        
        .add-icon {
            width: 110px;
            height: 110px;
            border-radius: 50%;
            background: rgba(255, 217, 102, 0.15);
            margin: 0 auto 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #ffd966;
            font-size: 48px;
            font-weight: 700;
            border: 2px dashed #ffd966;
        }
        
        .add-child-card:hover .add-icon {
            background: rgba(255, 217, 102, 0.25);
            border: 2px solid #ffd966;
        }
        
        .add-text {
            font-size: 22px;
            font-weight: 700;
            color: #ffd966;
            margin-bottom: 8px;
        }
        
        .add-subtext {
            color: rgba(255, 255, 255, 0.6);
            font-size: 14px;
        }
        
        /* Avatar */
        .child-avatar {
            width: 110px;
            height: 110px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.15);
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
            background: rgba(236, 72, 153, 0.2);
            border-color: #ec4899;
        }
        
        .child-avatar.male {
            background: rgba(59, 130, 246, 0.2);
            border-color: #3b82f6;
        }
        
        /* Child Info */
        .child-name {
            font-size: 22px;
            font-weight: 700;
            color: white;
            margin-bottom: 8px;
        }
        
        .child-details {
            color: rgba(255, 255, 255, 0.7);
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
            background: rgba(59, 130, 246, 0.2);
            color: #93c5fd;
            border: 1px solid rgba(59, 130, 246, 0.3);
        }
        
        .gender-badge.female {
            background: rgba(236, 72, 153, 0.2);
            color: #f9a8d4;
            border: 1px solid rgba(236, 72, 153, 0.3);
        }
        
        /* Action Buttons Container */
        .action-buttons {
            display: flex;
            justify-content: center;
            gap: 20px;
            margin-top: 40px;
            flex-wrap: wrap;
        }
        
        /* Logout Link */
        .logout-link {
            color: rgba(255, 255, 255, 0.8);
            text-decoration: none;
            font-size: 14px;
            padding: 10px 25px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 30px;
            transition: all 0.2s;
            backdrop-filter: blur(5px);
        }
        
        .logout-link:hover {
            color: white;
            background: rgba(255, 217, 102, 0.2);
        }
        
        /* Empty State - Glass card */
        .empty-state {
            background: rgba(11, 26, 51, 0.75);
            backdrop-filter: blur(12px);
            border-radius: 20px;
            padding: 60px;
            text-align: center;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        .empty-state p {
            color: rgba(255, 255, 255, 0.8);
            font-size: 18px;
            margin-bottom: 25px;
        }
        
        .add-child-empty-btn {
            display: inline-block;
            background: #ffd966;
            color: #0b1a33;
            text-decoration: none;
            padding: 14px 35px;
            border-radius: 40px;
            font-weight: 700;
            font-size: 16px;
            transition: all 0.3s;
        }
        
        .add-child-empty-btn:hover {
            background: #ffcd38;
            transform: scale(1.05);
        }
        
        /* Responsive */
        @media (max-width: 800px) {
            .children-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 20px;
            }
        }
        
        @media (max-width: 550px) {
            .children-grid {
                grid-template-columns: 1fr;
            }
            
            .page-header h1 {
                font-size: 32px;
            }
            
            .child-avatar, .add-icon {
                width: 80px;
                height: 80px;
                font-size: 32px;
            }
            
            .child-name, .add-text {
                font-size: 18px;
            }
        }
    </style>
</head>
<body>

<div class="select-container">
    
    <!-- ===== PAGE HEADER ===== -->
    <div class="page-header">
        <h1>Select Child</h1>
        <p>Who are you here for today?</p>
        <div class="divider"></div>
    </div>
    
    <!-- ===== CHILDREN GRID ===== -->
    <?php if (empty($children)): ?>
        
        <!-- Empty State - No Children -->
        <div class="empty-state">
            <p>You haven't added any children yet.</p>
            <a href="add_child.php" class="add-child-empty-btn">+ Add Your First Child</a>
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
            
            <!-- ADD CHILD CARD - Appears as an extra card in the grid -->
            <a href="add_child.php" class="add-child-card">
                <div class="add-icon">+</div>
                <div class="add-text">Add Child</div>
                <div class="add-subtext">Register a new child</div>
            </a>
            
        </div>
        
    <?php endif; ?>
    
    <!-- ===== LOGOUT LINK ===== -->
    <div class="action-buttons">
        <a href="logout.php" class="logout-link">← Logout</a>
    </div>
</div>

</body>
</html>