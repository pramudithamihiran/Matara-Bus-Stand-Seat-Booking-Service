<?php
// ============ SESSION START ============
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ============ CLEAR ALL COOKIES ============
// Remember me cookie එක ඉවත් කරන්න
if (isset($_COOKIE['user_email'])) {
    setcookie('user_email', '', time() - 3600, '/');
}
if (isset($_COOKIE['admin_email'])) {
    setcookie('admin_email', '', time() - 3600, '/');
}

// ============ CLEAR SESSION ============
// සියලුම සෙසන් දත්ත ඉවත් කිරීම
$_SESSION = array();

// Session cookie එක delete කිරීම
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Session එක විනාශ කිරීම
session_destroy();

// Session ID එක regenerate කිරීම
session_regenerate_id(true);

// ============ REDIRECT WITH MESSAGE ============
// JavaScript alert එකක් සමඟ redirect කිරීම
echo "<!DOCTYPE html>
<html>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Logging Out...</title>
    <link href='https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap' rel='stylesheet'>
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #00255a 0%, #001533 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            margin: 0;
            padding: 20px;
        }
        .logout-box {
            background: white;
            padding: 40px;
            border-radius: 24px;
            text-align: center;
            max-width: 400px;
            width: 100%;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            animation: fadeIn 0.5s ease;
        }
        .logout-box .icon {
            font-size: 60px;
            color: #28a745;
            margin-bottom: 15px;
        }
        .logout-box h2 {
            color: #003580;
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 5px;
        }
        .logout-box p {
            color: #888;
            font-size: 14px;
            margin-bottom: 20px;
        }
        .logout-box .spinner {
            width: 40px;
            height: 40px;
            border: 4px solid #e0e0e0;
            border-top: 4px solid #003580;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin: 0 auto;
        }
        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body>
    <div class='logout-box'>
        <div class='icon'>
            <i class='fas fa-check-circle'></i>
        </div>
        <h2>Logged Out Successfully!</h2>
        <p>You have been logged out of your account.</p>
        <div class='spinner'></div>
        <p style='margin-top:15px; font-size:13px; color:#aaa;'>
            <i class='fas fa-clock'></i> Redirecting to home page...
        </p>
    </div>
    
    <script>
        // Font Awesome CDN
        var link = document.createElement('link');
        link.rel = 'stylesheet';
        link.href = 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css';
        document.head.appendChild(link);
        
        // Redirect after 2 seconds
        setTimeout(function() {
            window.location.href = 'index.php';
        }, 2000);
    </script>
</body>
</html>";

exit();
?>