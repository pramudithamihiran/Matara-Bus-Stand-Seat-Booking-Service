<?php
include 'config.php';

// ============ CLEAR ALL COOKIES ============
if (isset($_COOKIE['user_email'])) {
    setcookie('user_email', '', time() - 3600, '/');
}
if (isset($_COOKIE['admin_email'])) {
    setcookie('admin_email', '', time() - 3600, '/');
}
if (isset($_COOKIE['tab_token'])) {
    setcookie('tab_token', '', time() - 3600, '/');
}

// ============ CLEAR SESSION ============
$_SESSION = array();

if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

session_destroy();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logging Out - Matara Bus</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Poppins', sans-serif;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            background: linear-gradient(135deg, #eef2ff 0%, #f0f4f8 50%, #fdf2f8 100%);
            background-attachment: fixed;
            padding: 20px;
            overflow: hidden;
            position: relative;
        }
        
        /* Animated Background Circles */
        body::before {
            content: '';
            position: fixed;
            width: 500px;
            height: 500px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(255,183,0,0.15) 0%, transparent 70%);
            top: -150px;
            right: -150px;
            animation: pulse 8s ease-in-out infinite;
        }
        
        body::after {
            content: '';
            position: fixed;
            width: 400px;
            height: 400px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(0,120,255,0.12) 0%, transparent 70%);
            bottom: -120px;
            left: -120px;
            animation: pulse 10s ease-in-out infinite reverse;
        }
        
        @keyframes pulse {
            0%, 100% { transform: scale(1); opacity: 0.5; }
            50% { transform: scale(1.1); opacity: 0.8; }
        }
        
        /* ============ LOGOUT CARD - GLASS EFFECT ============ */
        .logout-box {
            background: rgba(255, 255, 255, 0.85);
            backdrop-filter: blur(30px) saturate(180%);
            -webkit-backdrop-filter: blur(30px) saturate(180%);
            padding: 50px 40px;
            border-radius: 28px;
            text-align: center;
            max-width: 440px;
            width: 100%;
            box-shadow: 
                0 20px 60px rgba(0, 0, 0, 0.08),
                0 1px 2px rgba(255, 255, 255, 0.8) inset,
                0 -1px 2px rgba(0, 0, 0, 0.02) inset;
            border: 1px solid rgba(255, 255, 255, 0.7);
            animation: fadeInUp 0.6s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            z-index: 1;
        }
        
        @keyframes fadeInUp {
            from { 
                opacity: 0; 
                transform: translateY(30px) scale(0.95); 
            }
            to { 
                opacity: 1; 
                transform: translateY(0) scale(1); 
            }
        }
        
        /* ============ SUCCESS ICON ============ */
        .icon-wrapper {
            width: 100px;
            height: 100px;
            margin: 0 auto 25px;
            background: linear-gradient(135deg, #28a745, #1e7e34);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 
                0 15px 40px rgba(40, 167, 69, 0.3),
                0 1px 2px rgba(255, 255, 255, 0.5) inset;
            animation: scaleIn 0.6s cubic-bezier(0.34, 1.56, 0.64, 1) 0.2s both;
            position: relative;
        }
        
        .icon-wrapper::before {
            content: '';
            position: absolute;
            inset: -8px;
            border-radius: 50%;
            border: 3px solid rgba(40, 167, 69, 0.2);
            animation: ripple 2s ease-out infinite;
        }
        
        @keyframes ripple {
            0% { transform: scale(1); opacity: 0.6; }
            100% { transform: scale(1.3); opacity: 0; }
        }
        
        @keyframes scaleIn {
            from { transform: scale(0); }
            to { transform: scale(1); }
        }
        
        .icon-wrapper i {
            font-size: 50px;
            color: white;
            animation: checkmark 0.6s ease 0.5s both;
        }
        
        @keyframes checkmark {
            0% { transform: scale(0) rotate(-45deg); }
            50% { transform: scale(1.2) rotate(0deg); }
            100% { transform: scale(1) rotate(0deg); }
        }
        
        /* ============ TEXT ============ */
        .logout-box h2 {
            color: #0a1628;
            font-size: 26px;
            font-weight: 700;
            margin-bottom: 8px;
            letter-spacing: -0.5px;
            animation: fadeIn 0.5s ease 0.4s both;
        }
        
        .logout-box p {
            color: #666;
            font-size: 14px;
            margin-bottom: 30px;
            line-height: 1.6;
            animation: fadeIn 0.5s ease 0.5s both;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        /* ============ PROGRESS BAR ============ */
        .progress-wrapper {
            margin-top: 20px;
            animation: fadeIn 0.5s ease 0.7s both;
        }
        
        .progress-bar {
            width: 100%;
            height: 6px;
            background: rgba(0, 53, 128, 0.08);
            border-radius: 10px;
            overflow: hidden;
            position: relative;
        }
        
        .progress-fill {
            height: 100%;
            width: 0%;
            background: linear-gradient(90deg, #003580, #004d99, #ffb700);
            border-radius: 10px;
            animation: progress 2s cubic-bezier(0.4, 0, 0.2, 1) 1s forwards;
            position: relative;
            overflow: hidden;
        }
        
        .progress-fill::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.5), transparent);
            animation: shimmer 1.5s infinite;
        }
        
        @keyframes progress {
            from { width: 0%; }
            to { width: 100%; }
        }
        
        @keyframes shimmer {
            0% { transform: translateX(-100%); }
            100% { transform: translateX(100%); }
        }
        
        .progress-text {
            margin-top: 12px;
            font-size: 12px;
            color: #888;
            font-weight: 500;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        
        .progress-text i {
            color: #ffb700;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
        
        /* ============ REDIRECT BUTTON ============ */
        .redirect-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-top: 25px;
            padding: 12px 28px;
            background: linear-gradient(135deg, #003580, #004d99);
            color: white;
            border: none;
            border-radius: 12px;
            font-family: 'Poppins', sans-serif;
            font-size: 13px;
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.3s;
            box-shadow: 0 8px 25px rgba(0,53,128,0.25);
            animation: fadeIn 0.5s ease 0.9s both;
        }
        
        .redirect-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 30px rgba(0,53,128,0.35);
        }
        
        .redirect-btn i {
            font-size: 14px;
        }
        
        /* ============ DARK THEME ============ */
        body.dark-theme {
            background: linear-gradient(135deg, #0a1628 0%, #0d1117 50%, #1a1a2e 100%);
        }
        
        body.dark-theme .logout-box {
            background: rgba(22, 27, 34, 0.85);
            border: 1px solid rgba(255, 255, 255, 0.08);
            box-shadow: 
                0 20px 60px rgba(0, 0, 0, 0.5),
                0 1px 2px rgba(255, 255, 255, 0.05) inset;
        }
        
        body.dark-theme .logout-box h2 {
            color: #fff;
        }
        
        body.dark-theme .logout-box p {
            color: #8b949e;
        }
        
        body.dark-theme .progress-bar {
            background: rgba(255, 255, 255, 0.08);
        }
        
        body.dark-theme .progress-text {
            color: #8b949e;
        }
        
        /* ============ RESPONSIVE ============ */
        @media (max-width: 480px) {
            .logout-box {
                padding: 40px 25px;
            }
            .icon-wrapper {
                width: 85px;
                height: 85px;
            }
            .icon-wrapper i {
                font-size: 40px;
            }
            .logout-box h2 {
                font-size: 22px;
            }
            .logout-box p {
                font-size: 13px;
            }
        }
    </style>
</head>
<body>

<div class="logout-box">
    
    <!-- Success Icon -->
    <div class="icon-wrapper">
        <i class="fas fa-check"></i>
    </div>
    
    <!-- Text -->
    <h2>Logged Out Successfully!</h2>
    <p>You have been securely logged out of your account.<br>Thank you for using Matara Bus Seat Booking.</p>
    
    <!-- Progress Bar -->
    <div class="progress-wrapper">
        <div class="progress-bar">
            <div class="progress-fill"></div>
        </div>
        <div class="progress-text">
            <i class="fas fa-spinner"></i>
            <span>Redirecting to home page...</span>
        </div>
    </div>
    
    <!-- Manual Redirect Button -->
    <a href="index.php" class="redirect-btn">
        <i class="fas fa-home"></i> Go to Home Now
    </a>
    
</div>

<script>
// ============ AUTO REDIRECT ============
setTimeout(function() {
    window.location.href = 'index.php';
}, 3000);

// ============ DARK THEME SUPPORT ============
document.addEventListener('DOMContentLoaded', function() {
    const savedTheme = localStorage.getItem('theme');
    if (savedTheme === 'dark') {
        document.body.classList.add('dark-theme');
    }
});
</script>

</body>
</html>

<?php
exit();
?>