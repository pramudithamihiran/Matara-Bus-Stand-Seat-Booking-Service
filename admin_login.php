<?php
include 'config.php';
require_once 'tab_auth.php';

// ============ REDIRECT IF ALREADY LOGGED IN ============
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    if (isTabAuthenticated()) {
        header("Location: admin_dashboard.php");
        exit();
    }
}
if (isset($_SESSION['conductor_logged_in']) && $_SESSION['conductor_logged_in'] === true) {
    if (isTabAuthenticated()) {
        header("Location: conductor_dashboard.php");
        exit();
    }
}

$error = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $user = trim($_POST['username']);
    $pass = trim($_POST['password']);
    
    if (empty($user) || empty($pass)) {
        $error = "Please enter both username and password!";
    } else {
        // ============ SUPER ADMIN LOGIN ============
        $super_admin_username = 'admin';
        $super_admin_password = 'admin123';
        
        if ($user === $super_admin_username && $pass === $super_admin_password) {
            $admin_user = [
                'id' => 1,
                'username' => 'Main Admin',
                'email' => 'admin@bus.com',
                'role' => 'super_admin'
            ];
            
            $token = loginUserWithToken($admin_user);
            
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['role'] = 'super_admin';
            $_SESSION['admin_username'] = 'Main Admin';
            $_SESSION['bus_name'] = 'All Buses System';
            
            header("Location: admin_dashboard.php?tab=" . $token);
            exit();
        }
        
        // ============ BUS OWNER LOGIN ============
        $sql = "SELECT * FROM buses WHERE owner_username = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $user);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result && $result->num_rows > 0) {
            $row = $result->fetch_assoc();
            
            if (password_verify($pass, $row['owner_password'])) {
                $owner_user = [
                    'id' => $row['id'],
                    'username' => $row['owner_username'],
                    'email' => $row['owner_email'] ?? 'owner@bus.com',
                    'role' => 'bus_owner'
                ];
                
                $token = loginUserWithToken($owner_user);
                
                $_SESSION['admin_logged_in'] = true;
                $_SESSION['role'] = 'bus_owner';
                $_SESSION['owner_bus_id'] = $row['id']; 
                $_SESSION['admin_username'] = $row['owner_username'];
                $_SESSION['bus_name'] = $row['bus_name'];
                
                header("Location: admin_dashboard.php?tab=" . $token);
                exit();
            } else {
                $error = "Invalid credentials!";
            }
            $stmt->close();
        } else {
            // ============ CONDUCTOR LOGIN ============
            $sql = "SELECT * FROM buses WHERE conductor_username = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("s", $user);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result && $result->num_rows > 0) {
                $row = $result->fetch_assoc();
                
                if (password_verify($pass, $row['conductor_password'])) {
                    $conductor_user = [
                        'id' => $row['id'],
                        'username' => $row['conductor_name'] ?? $row['bus_name'],
                        'email' => 'conductor@bus.com',
                        'role' => 'conductor'
                    ];
                    
                    $token = loginUserWithToken($conductor_user);
                    
                    $_SESSION['conductor_logged_in'] = true;
                    $_SESSION['conductor_id'] = $row['id'];
                    $_SESSION['conductor_name'] = $row['conductor_name'] ?? $row['bus_name'];
                    $_SESSION['bus_id'] = $row['id'];
                    $_SESSION['bus_name'] = $row['bus_name'];
                    $_SESSION['role'] = 'conductor';
                    
                    header("Location: conductor_dashboard.php?tab=" . $token);
                    exit();
                } else {
                    $error = "Invalid credentials!";
                }
            } else {
                $error = "User not found!";
            }
            $stmt->close();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Portal Login - Matara Bus</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Poppins', sans-serif;
            min-height: 100vh;
            display: flex;
            overflow-x: hidden;
            background: #0a1628;
        }
        
        /* ============ LEFT SIDE - BRANDING ============ */
        .brand-side {
            flex: 1;
            background: linear-gradient(135deg, #0a1628 0%, #1a3a5c 50%, #0d2847 100%);
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            padding: 60px;
            position: relative;
            overflow: hidden;
        }
        
        .brand-side::before {
            content: '';
            position: absolute;
            width: 600px;
            height: 600px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(255,183,0,0.15) 0%, transparent 70%);
            top: -200px;
            right: -200px;
            animation: pulse 8s ease-in-out infinite;
        }
        
        .brand-side::after {
            content: '';
            position: absolute;
            width: 400px;
            height: 400px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(0,120,255,0.15) 0%, transparent 70%);
            bottom: -150px;
            left: -150px;
            animation: pulse 10s ease-in-out infinite reverse;
        }
        
        @keyframes pulse {
            0%, 100% { transform: scale(1); opacity: 0.5; }
            50% { transform: scale(1.1); opacity: 0.8; }
        }
        
        .brand-content {
            position: relative;
            z-index: 2;
            text-align: center;
            max-width: 500px;
        }
        
        .brand-logo {
            width: 120px;
            height: 120px;
            background: linear-gradient(135deg, #ffb700, #f5a623);
            border-radius: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 30px;
            box-shadow: 0 20px 60px rgba(255,183,0,0.3);
            animation: float 3s ease-in-out infinite;
        }
        
        @keyframes float {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-15px); }
        }
        
        .brand-logo i {
            font-size: 60px;
            color: #0a1628;
        }
        
        .brand-badge {
            display: inline-block;
            background: rgba(255,183,0,0.15);
            color: #ffb700;
            padding: 8px 20px;
            border-radius: 30px;
            font-size: 12px;
            font-weight: 600;
            letter-spacing: 1px;
            text-transform: uppercase;
            margin-bottom: 20px;
            border: 1px solid rgba(255,183,0,0.3);
        }
        
        .brand-title {
            font-size: 42px;
            font-weight: 700;
            color: white;
            line-height: 1.2;
            margin-bottom: 15px;
        }
        
        .brand-title span {
            color: #ffb700;
        }
        
        .brand-subtitle {
            font-size: 16px;
            color: rgba(255,255,255,0.6);
            line-height: 1.6;
            margin-bottom: 40px;
        }
        
        .feature-list {
            display: flex;
            justify-content: center;
            gap: 25px;
            flex-wrap: wrap;
        }
        
        .feature-item {
            text-align: center;
        }
        
        .feature-item .icon-box {
            width: 55px;
            height: 55px;
            border-radius: 15px;
            background: rgba(255,255,255,0.08);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 10px;
            border: 1px solid rgba(255,255,255,0.1);
            transition: all 0.3s;
        }
        
        .feature-item:hover .icon-box {
            background: rgba(255,183,0,0.2);
            transform: translateY(-5px);
        }
        
        .feature-item .icon-box i {
            font-size: 22px;
            color: #ffb700;
        }
        
        .feature-item p {
            font-size: 12px;
            color: rgba(255,255,255,0.7);
            font-weight: 500;
        }
        
        /* ============ RIGHT SIDE - FORM ============ */
        .form-side {
            flex: 1;
            background: #f0f4f8;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px;
            position: relative;
        }
        
        .theme-toggle {
            position: absolute;
            top: 25px;
            right: 25px;
            background: white;
            border: none;
            padding: 10px 18px;
            border-radius: 30px;
            cursor: pointer;
            font-family: 'Poppins', sans-serif;
            font-size: 13px;
            font-weight: 600;
            color: #333;
            display: flex;
            align-items: center;
            gap: 8px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            transition: all 0.3s;
            z-index: 10;
        }
        
        .theme-toggle:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0,0,0,0.12);
        }
        
        .theme-toggle i {
            color: #ffb700;
        }
        
        .login-box {
            background: white;
            padding: 45px;
            border-radius: 24px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.08);
            width: 100%;
            max-width: 440px;
            position: relative;
            z-index: 1;
        }
        
        .login-box .icon-header {
            text-align: center;
            font-size: 45px;
            color: #ffb700;
            margin-bottom: 10px;
        }
        
        .login-box h2 {
            color: #0a1628;
            font-size: 26px;
            font-weight: 700;
            text-align: center;
            margin-bottom: 5px;
        }
        
        .login-box .subtitle {
            text-align: center;
            color: #888;
            font-size: 14px;
            margin-bottom: 30px;
        }
        
        .error-msg {
            background: #fce4e4;
            color: #c0392b;
            padding: 12px 16px;
            border-radius: 10px;
            font-size: 13px;
            font-weight: 500;
            margin-bottom: 15px;
            border-left: 4px solid #c0392b;
            text-align: left;
        }
        
        .form-group {
            text-align: left;
            margin-bottom: 18px;
        }
        
        .form-group label {
            font-weight: 600;
            font-size: 13px;
            color: #333;
            display: block;
            margin-bottom: 6px;
        }
        
        .form-group label i {
            color: #0a1628;
            margin-right: 6px;
        }
        
        input {
            width: 100%;
            padding: 14px 16px;
            border: 2px solid #e0e0e0;
            border-radius: 12px;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
            font-size: 14px;
            transition: all 0.3s ease;
            background: #fafafa;
            outline: none;
        }
        
        input:focus {
            border-color: #0a1628;
            background: #fff;
            box-shadow: 0 0 0 4px rgba(10,22,40,0.08);
        }
        
        .password-wrapper {
            position: relative;
        }
        
        .password-wrapper input {
            padding-right: 45px;
        }
        
        .password-wrapper .toggle-password {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #888;
            background: none;
            border: none;
            font-size: 16px;
            transition: 0.2s;
        }
        
        .password-wrapper .toggle-password:hover {
            color: #0a1628;
        }
        
        .btn {
            width: 100%;
            padding: 15px;
            border: none;
            border-radius: 12px;
            cursor: pointer;
            font-weight: 700;
            margin-top: 10px;
            text-transform: uppercase;
            transition: all 0.3s;
            font-family: 'Poppins', sans-serif;
            font-size: 14px;
            letter-spacing: 0.5px;
        }
        
        .btn-login {
            background: linear-gradient(135deg, #0a1628, #1a3a5c);
            color: white;
        }
        
        .btn-login:hover {
            background: linear-gradient(135deg, #1a3a5c, #0a1628);
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(10,22,40,0.3);
        }
        
        .btn i {
            margin-right: 8px;
        }
        
        .back-link {
            display: block;
            text-align: center;
            margin-top: 20px;
            color: #888;
            text-decoration: none;
            font-size: 13px;
            font-weight: 500;
            transition: 0.2s;
        }
        
        .back-link:hover {
            color: #0a1628;
            text-decoration: underline;
        }
        
        .login-footer {
            margin-top: 20px;
            padding-top: 15px;
            border-top: 1px solid #eee;
            font-size: 12px;
            color: #aaa;
            text-align: center;
        }
        
        .login-footer .role-info {
            font-size: 11px;
            color: #aaa;
        }
        
        .login-footer .role-info i {
            color: #ffb700;
        }
        
        /* ============ DARK THEME ============ */
        body.dark-theme .form-side {
            background: #0d1117;
        }
        
        body.dark-theme .login-box {
            background: #161b22;
            box-shadow: 0 20px 60px rgba(0,0,0,0.4);
            border: 1px solid rgba(255,255,255,0.05);
        }
        
        body.dark-theme .login-box h2 { color: #fff; }
        body.dark-theme .login-box .subtitle { color: #8b949e; }
        body.dark-theme .form-group label { color: #c9d1d9; }
        body.dark-theme .form-group label i { color: #ffb700; }
        body.dark-theme input {
            background: #0d1117;
            border-color: #30363d;
            color: #c9d1d9;
        }
        body.dark-theme input:focus {
            border-color: #ffb700;
            background: #0d1117;
            box-shadow: 0 0 0 4px rgba(255,183,0,0.1);
        }
        body.dark-theme .back-link { color: #8b949e; }
        body.dark-theme .back-link:hover { color: #ffb700; }
        body.dark-theme .login-footer {
            border-top-color: #30363d;
            color: #8b949e;
        }
        body.dark-theme .theme-toggle {
            background: #161b22;
            color: #c9d1d9;
            border: 1px solid #30363d;
        }
        body.dark-theme .error-msg {
            background: rgba(220,53,69,0.1);
            color: #ff6b6b;
        }
        
        /* ============ RESPONSIVE ============ */
        @media (max-width: 900px) {
            body {
                flex-direction: column;
            }
            .brand-side {
                padding: 40px 25px;
                min-height: auto;
            }
            .brand-title { font-size: 32px; }
            .brand-subtitle { font-size: 14px; margin-bottom: 30px; }
            .brand-logo { width: 90px; height: 90px; }
            .brand-logo i { font-size: 45px; }
            .feature-list { gap: 20px; }
            .form-side {
                padding: 30px 20px;
                min-height: auto;
            }
            .login-box {
                padding: 30px 25px;
            }
        }
        
        @media (max-width: 480px) {
            .brand-title { font-size: 26px; }
            .brand-subtitle { font-size: 13px; }
            .login-box { padding: 25px 20px; }
            .login-box h2 { font-size: 22px; }
            .brand-side { padding: 30px 20px; }
            .feature-item .icon-box { width: 45px; height: 45px; }
            .feature-item .icon-box i { font-size: 18px; }
        }
    </style>
</head>
<body>

<!-- ============ LEFT SIDE - BRANDING ============ -->
<div class="brand-side">
    <div class="brand-content">
        <div class="brand-logo">
            <i class="fas fa-user-shield"></i>
        </div>
        
        <span class="brand-badge">🔐 Secure Portal Access</span>
        
        <h1 class="brand-title">
            Manage Your <span>Bus System</span> Easily
        </h1>
        
        <p class="brand-subtitle">
            Access the admin panel to manage buses, routes, schedules, and bookings. Owners and conductors can manage their daily operations here.
        </p>
        
        <div class="feature-list">
            <div class="feature-item">
                <div class="icon-box"><i class="fas fa-bus"></i></div>
                <p>Buses</p>
            </div>
            <div class="feature-item">
                <div class="icon-box"><i class="fas fa-route"></i></div>
                <p>Routes</p>
            </div>
            <div class="feature-item">
                <div class="icon-box"><i class="fas fa-ticket-alt"></i></div>
                <p>Bookings</p>
            </div>
            <div class="feature-item">
                <div class="icon-box"><i class="fas fa-chart-line"></i></div>
                <p>Reports</p>
            </div>
        </div>
    </div>
</div>

<!-- ============ RIGHT SIDE - FORM ============ -->
<div class="form-side">
    
    <!-- Theme Toggle -->
    <button class="theme-toggle" onclick="toggleTheme()" id="themeBtn">
        <i class="fas fa-sun"></i> <span>Light mode</span>
    </button>
    
    <div class="login-box">
        
        <div class="icon-header">
            <i class="fas fa-user-shield"></i>
        </div>
        <h2>Portal Login</h2>
        <p class="subtitle">Admin / Owner / Conductor Control Panel</p>
        
        <?php if (!empty($error)): ?>
            <div class="error-msg">
                <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" autocomplete="off">
            <div class="form-group">
                <label><i class="fas fa-user"></i> Username</label>
                <input type="text" name="username" 
                       placeholder="Enter your username" 
                       autocomplete="off" 
                       readonly onfocus="this.removeAttribute('readonly');" 
                       required>
            </div>
            
            <div class="form-group">
                <label><i class="fas fa-lock"></i> Password</label>
                <div class="password-wrapper">
                    <input type="password" name="password" id="admin_password" 
                           placeholder="Enter your password" 
                           autocomplete="off" 
                           readonly onfocus="this.removeAttribute('readonly');" 
                           required>
                    <button type="button" class="toggle-password" onclick="togglePassword('admin_password', this)">
                        <i class="fas fa-eye"></i>
                    </button>
                </div>
            </div>
            
            <button type="submit" class="btn btn-login">
                <i class="fas fa-sign-in-alt"></i> LOG IN SYSTEM
            </button>
        </form>
        
        <a href="index.php" class="back-link">
            <i class="fas fa-arrow-left"></i> Back to Homepage
        </a>
        
        <div class="login-footer">
            <div class="role-info">
                <i class="fas fa-info-circle"></i> 
                Owner / Conductor credentials provided by admin
            </div>
        </div>
    </div>
</div>

<script>
// ============ TOGGLE PASSWORD VISIBILITY ============
function togglePassword(inputId, button) {
    const input = document.getElementById(inputId);
    const icon = button.querySelector('i');
    
    if (input.type === "password") {
        input.type = "text";
        icon.className = "fas fa-eye-slash";
    } else {
        input.type = "password";
        icon.className = "fas fa-eye";
    }
}

// ============ THEME TOGGLE ============
function toggleTheme() {
    const body = document.body;
    const themeBtn = document.getElementById('themeBtn');
    const icon = themeBtn.querySelector('i');
    const text = themeBtn.querySelector('span');
    
    body.classList.toggle('dark-theme');
    
    if (body.classList.contains('dark-theme')) {
        icon.className = 'fas fa-moon';
        text.textContent = 'Dark mode';
        localStorage.setItem('theme', 'dark');
    } else {
        icon.className = 'fas fa-sun';
        text.textContent = 'Light mode';
        localStorage.setItem('theme', 'light');
    }
}

// ============ LOAD SAVED THEME ============
document.addEventListener('DOMContentLoaded', function() {
    const savedTheme = localStorage.getItem('theme');
    if (savedTheme === 'dark') {
        document.body.classList.add('dark-theme');
        const themeBtn = document.getElementById('themeBtn');
        themeBtn.querySelector('i').className = 'fas fa-moon';
        themeBtn.querySelector('span').textContent = 'Dark mode';
    }
});
</script>

</body>
</html>