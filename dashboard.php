<?php 
include 'config.php';
require_once 'tab_auth.php';

// ============ CHECK URL TOKEN & SET COOKIE ============
$url_token = $_GET['tab'] ?? '';
$session_token = $_SESSION['tab_token'] ?? '';

if (!empty($url_token) && $url_token === $session_token) {
    setcookie('tab_token', $url_token, 0, '/');
    $_COOKIE['tab_token'] = $url_token;
    $clean_url = strtok($_SERVER['REQUEST_URI'], '?');
    header("Location: " . $clean_url);
    exit();
}

// ============ CHECK IF USER IS LOGGED IN ============
$is_logged_in = isset($_SESSION['user_logged_in']) && $_SESSION['user_logged_in'] === true;

if ($is_logged_in) {
    if (!isTabAuthenticated()) {
        unset($_SESSION['user_logged_in']);
        unset($_SESSION['user_id']);
        unset($_SESSION['user_name']);
        unset($_SESSION['user_email']);
        $is_logged_in = false;
    }
}

$user_name = $is_logged_in ? ($_SESSION['user_name'] ?? 'User') : 'Guest';

// ============ STATIC ROUTES ============
$routes = [
    ['name' => 'Kataragama', 'icon' => 'fa-route', 'color' => '#e74c3c', 'gradient' => 'linear-gradient(135deg, #e74c3c, #c0392b)', 'desc' => 'Scenic route to Kataragama'],
    ['name' => 'Hakmana', 'icon' => 'fa-road', 'color' => '#2ecc71', 'gradient' => 'linear-gradient(135deg, #2ecc71, #27ae60)', 'desc' => 'Beautiful journey to Hakmana'],
    ['name' => 'Deniyaya', 'icon' => 'fa-mountain', 'color' => '#3498db', 'gradient' => 'linear-gradient(135deg, #3498db, #2980b9)', 'desc' => 'Mountain route to Deniyaya'],
    ['name' => 'Colombo', 'icon' => 'fa-city', 'color' => '#f39c12', 'gradient' => 'linear-gradient(135deg, #f39c12, #e67e22)', 'desc' => 'Highway to Colombo']
];

$total_buses_sql = "SELECT COUNT(*) as total FROM buses WHERE status = 'active'";
$total_buses_result = $conn->query($total_buses_sql);
$total_buses = $total_buses_result->fetch_assoc()['total'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Matara Bus</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body { 
            font-family: 'Poppins', sans-serif; 
            background: linear-gradient(135deg, #0f0022 0%, #1a0033 50%, #0a0018 100%) !important;
            background-attachment: fixed !important;
            padding-top: 90px;
            padding-bottom: 40px;
            min-height: 100vh;
            color: #e0e0e0;
        }
        
        .main-wrapper {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px 20px 40px;
        }
        
        /* ===== WELCOME SECTION - DEEP PURPLE GLASS ===== */
        .welcome-section {
            background: linear-gradient(135deg, #1a0033 0%, #2d1b4e 50%, #1a0033 100%);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 183, 0, 0.2);
            color: white;
            padding: 30px 40px;
            border-radius: 24px;
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.4), 0 1px 2px rgba(255, 183, 0, 0.1) inset;
        }
        
        .welcome-section .greeting h1 { font-size: 28px; font-weight: 700; margin-bottom: 5px; color: #ffffff; }
        .welcome-section .greeting h1 i { color: #ffb700; margin-right: 10px; }
        .welcome-section .greeting p { font-size: 14px; opacity: 0.85; color: #d0d0d0; }
        .welcome-section .stats { display: flex; gap: 30px; }
        .welcome-section .stats .stat-item { text-align: center; }
        .welcome-section .stats .stat-item .number { font-size: 28px; font-weight: 700; color: #ffb700; }
        .welcome-section .stats .stat-item .label { font-size: 12px; opacity: 0.8; text-transform: uppercase; letter-spacing: 0.5px; color: #d0d0d0; }
        .welcome-section .user-actions { display: flex; gap: 10px; flex-wrap: wrap; }
        
        /* ===== BUTTONS ===== */
        .btn { padding: 10px 25px; border-radius: 10px; text-decoration: none; font-weight: 600; font-size: 14px; transition: all 0.3s; display: inline-block; }
        
        .btn-light { 
            background: linear-gradient(135deg, #ffb700, #f5a623);
            color: #0f0022; 
            box-shadow: 0 4px 15px rgba(255, 183, 0, 0.3);
        }
        .btn-light:hover { 
            transform: translateY(-2px); 
            box-shadow: 0 8px 25px rgba(255, 183, 0, 0.5);
        }
        
        .btn-outline-light { 
            background: rgba(255, 183, 0, 0.1);
            color: #ffb700; 
            border: 2px solid rgba(255, 183, 0, 0.4); 
        }
        .btn-outline-light:hover { 
            background: rgba(255, 183, 0, 0.2);
            border-color: #ffb700;
            transform: translateY(-2px); 
        }
        
        .btn i { margin-right: 6px; }
        
        /* ===== SECTION TITLE ===== */
        .section-title { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 10px; }
        .section-title h2 { font-size: 24px; font-weight: 700; color: #ffffff; }
        .section-title h2 i { color: #ffb700; margin-right: 10px; }
        .section-title .sub-text { color: #b0b0b0; font-size: 14px; }
        
        /* ===== ROUTE CARDS - DEEP PURPLE GLASS ===== */
        .route-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 25px; padding: 10px 0; }
        
        .route-card { 
            background: rgba(22, 22, 22, 0.85);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 183, 0, 0.15);
            padding: 35px 25px 25px; 
            border-radius: 20px; 
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.4);
            transition: all 0.4s; 
            cursor: pointer; 
            text-decoration: none; 
            color: #ffffff; 
            display: block; 
            position: relative; 
            overflow: hidden;
            animation: fadeInUp 0.6s ease forwards; 
            opacity: 0; 
        }
        
        .route-card:nth-child(1) { animation-delay: 0.05s; }
        .route-card:nth-child(2) { animation-delay: 0.1s; }
        .route-card:nth-child(3) { animation-delay: 0.15s; }
        .route-card:nth-child(4) { animation-delay: 0.2s; }
        
        @keyframes fadeInUp { 
            from { opacity: 0; transform: translateY(30px); } 
            to { opacity: 1; transform: translateY(0); } 
        }
        
        .route-card::before { 
            content: ''; 
            position: absolute; 
            top: 0; 
            left: 0; 
            right: 0; 
            height: 5px; 
            background: var(--route-color, #ffb700); 
            transition: height 0.3s ease; 
        }
        
        .route-card:hover { 
            transform: translateY(-12px) scale(1.02); 
            box-shadow: 0 20px 50px rgba(255, 183, 0, 0.15);
            border-color: rgba(255, 183, 0, 0.4);
        }
        
        .route-card:hover::before { height: 8px; }
        
        .route-card .icon-wrapper { 
            width: 70px; 
            height: 70px; 
            border-radius: 50%; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            margin: 0 auto 18px; 
            background: var(--gradient); 
            transition: all 0.3s ease;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.3);
        }
        
        .route-card:hover .icon-wrapper { transform: scale(1.1) rotate(-5deg); }
        .route-card .icon-wrapper i { font-size: 30px; color: white; }
        .route-card h3 { font-size: 18px; font-weight: 700; color: #ffffff; margin: 0 0 3px; line-height: 1.3; }
        .route-card .route-desc { font-size: 13px; color: #b0b0b0; margin-bottom: 12px; }
        
        .route-card .bus-count { 
            display: inline-flex; 
            align-items: center; 
            gap: 6px; 
            background: rgba(255, 183, 0, 0.15);
            color: #ffb700; 
            padding: 4px 16px; 
            border-radius: 20px; 
            font-size: 13px; 
            font-weight: 600;
            border: 1px solid rgba(255, 183, 0, 0.3);
        }
        
        .route-card .bus-count i { font-size: 12px; }
        
        .route-card .arrow-icon { 
            position: absolute; 
            bottom: 15px; 
            right: 20px; 
            color: rgba(255, 183, 0, 0.3); 
            font-size: 16px; 
            transition: all 0.3s ease; 
        }
        
        .route-card:hover .arrow-icon { color: #ffb700; transform: translateX(5px); }
        
        /* ===== QUICK LINKS - DEEP PURPLE GLASS ===== */
        .quick-links { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-top: 30px; }
        
        .quick-link { 
            background: rgba(22, 22, 22, 0.85);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 183, 0, 0.15);
            padding: 20px; 
            border-radius: 16px; 
            text-align: center; 
            text-decoration: none; 
            color: #ffffff; 
            transition: all 0.3s ease; 
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.4);
        }
        
        .quick-link:hover { 
            transform: translateY(-5px); 
            box-shadow: 0 15px 40px rgba(255, 183, 0, 0.15);
            border-color: rgba(255, 183, 0, 0.4);
        }
        
        .quick-link i { font-size: 30px; color: #ffb700; margin-bottom: 10px; }
        .quick-link h4 { font-size: 14px; font-weight: 600; color: #ffffff; }
        .quick-link p { font-size: 12px; color: #b0b0b0; margin: 0; }
        
        /* ===== LIVE TRACKING LINK - spgps.lk ===== */
        .quick-link.tracking {
            border-left: 4px solid #28a745;
        }
        .quick-link.tracking i {
            color: #28a745;
        }
        
        /* ===== NO ROUTES ===== */
        .no-routes { 
            text-align: center; 
            padding: 60px 20px; 
            background: rgba(22, 22, 22, 0.85);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 183, 0, 0.15);
            border-radius: 20px; 
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.4);
        }
        .no-routes i { font-size: 60px; color: rgba(255, 183, 0, 0.3); margin-bottom: 15px; }
        .no-routes h3 { color: #ffffff; margin-bottom: 5px; }
        .no-routes p { color: #b0b0b0; font-size: 14px; }
        
        /* ===== RESPONSIVE ===== */
        @media (max-width: 768px) {
            .welcome-section { padding: 25px 20px; flex-direction: column; text-align: center; }
            .welcome-section .stats { gap: 20px; }
            .welcome-section .user-actions { flex-direction: column; width: 100%; }
            .welcome-section .user-actions .btn { text-align: center; }
            .route-grid { grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 18px; }
            .route-card { padding: 25px 18px 20px; }
            .route-card .icon-wrapper { width: 60px; height: 60px; }
            .route-card .icon-wrapper i { font-size: 24px; }
            .section-title { flex-direction: column; text-align: center; }
            .quick-links { grid-template-columns: repeat(2, 1fr); }
        }
        
        @media (max-width: 480px) {
            .route-grid { grid-template-columns: 1fr 1fr; gap: 12px; }
            .route-card { padding: 20px 12px 16px; }
            .route-card h3 { font-size: 14px; }
            .route-card .icon-wrapper { width: 45px; height: 45px; }
            .route-card .icon-wrapper i { font-size: 18px; }
            .route-card .bus-count { font-size: 10px; padding: 2px 10px; }
            .route-card .route-desc { font-size: 10px; }
            .welcome-section .greeting h1 { font-size: 22px; }
            .welcome-section .stats .stat-item .number { font-size: 22px; }
            .quick-links { grid-template-columns: 1fr 1fr; gap: 10px; }
            .quick-link { padding: 15px; }
            .quick-link i { font-size: 22px; }
            .quick-link h4 { font-size: 12px; }
        }
    </style>
</head>
<body>

<?php include 'header.php'; ?>

<div class="main-wrapper">
    
    <!-- ===== WELCOME SECTION ===== -->
    <div class="welcome-section">
        <div class="greeting">
            <h1><i class="fas fa-user-circle"></i> Welcome, <?= htmlspecialchars($user_name) ?>!</h1>
            <p><i class="fas fa-bus"></i> Find and book your bus seat easily</p>
        </div>
        <div class="stats">
            <div class="stat-item">
                <div class="number"><?= $total_buses ?></div>
                <div class="label">Total Buses</div>
            </div>
            <div class="stat-item">
                <div class="number">4</div>
                <div class="label">Routes</div>
            </div>
        </div>
        <div class="user-actions">
            <?php if ($is_logged_in): ?>
                <a href="my_bookings.php" class="btn btn-light">
                    <i class="fas fa-ticket-alt"></i> My Bookings
                </a>
                <a href="logout.php" class="btn btn-outline-light">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            <?php else: ?>
                <a href="login.php" class="btn btn-light">
                    <i class="fas fa-sign-in-alt"></i> Login
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- ===== ROUTES SECTION ===== -->
    <div class="section-title">
        <h2><i class="fas fa-route"></i> Available Routes</h2>
        <span class="sub-text">Select a route to book your seat</span>
    </div>

    <div class="route-grid">
        <?php foreach ($routes as $route): ?>
            <a href="bus_list.php?route_name=<?= urlencode($route['name']) ?>" class="route-card" style="--route-color: <?= $route['color'] ?>; --gradient: <?= $route['gradient'] ?>">
                <div class="icon-wrapper">
                    <i class="fas <?= $route['icon'] ?>"></i>
                </div>
                <h3><?= htmlspecialchars($route['name']) ?></h3>
                <p class="route-desc"><?= htmlspecialchars($route['desc']) ?></p>
                <span class="bus-count">
                    <i class="fas fa-bus"></i> Buses Available
                </span>
                <span class="arrow-icon">
                    <i class="fas fa-arrow-right"></i>
                </span>
            </a>
        <?php endforeach; ?>
    </div>

    <!-- ===== QUICK LINKS ===== -->
    <div class="quick-links">
        <a href="my_bookings.php" class="quick-link">
            <i class="fas fa-ticket-alt"></i>
            <h4>My Bookings</h4>
            <p>View your bookings</p>
        </a>
        <!-- ===== LIVE TRACKING - spgps.lk ===== -->
        <a href="https://spgps.lk/" target="_blank" class="quick-link tracking">
            <i class="fas fa-map-marked-alt"></i>
            <h4>Live Tracking</h4>
            <p>Track your bus live</p>
        </a>
        <a href="contact.php" class="quick-link">
            <i class="fas fa-envelope"></i>
            <h4>Contact Us</h4>
            <p>Get support</p>
        </a>
        <a href="index.php" class="quick-link">
            <i class="fas fa-home"></i>
            <h4>Home</h4>
            <p>Back to home</p>
        </a>
    </div>
    
</div>

</body>
</html>