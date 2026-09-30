<?php
include 'config.php';
require_once 'tab_auth.php';

// ============ REDIRECT PARAMETERS ============
$redirect_bus_id = isset($_REQUEST['redirect_bus_id']) ? intval($_REQUEST['redirect_bus_id']) : 0;
$redirect_date = isset($_REQUEST['date']) ? htmlspecialchars($_REQUEST['date']) : '';

// ============ REGISTRATION LOGIC ============
if (isset($_POST['register'])) {
    $user = trim($_POST['username']);
    $full_name = trim($_POST['full_name']);
    $contact = trim($_POST['contact']);
    $email = trim($_POST['email']);
    $pass = $_POST['password'];
    $confirm_pass = $_POST['confirm_password'];
    $error = '';

    if (empty($user) || empty($full_name) || empty($contact) || empty($email) || empty($pass)) {
        $error = "Please fill all fields!";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address!";
    } elseif (strlen($pass) < 6) {
        $error = "Password must be at least 6 characters!";
    } elseif ($pass !== $confirm_pass) {
        $error = "Passwords do not match!";
    } else {
        $check_stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
        $check_stmt->bind_param("s", $user);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            $error = "Username already exists! Please choose a different username.";
        } else {
            $hashed_password = password_hash($pass, PASSWORD_DEFAULT);
            
            $stmt = $conn->prepare("INSERT INTO users (username, full_name, contact_number, email, password) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("sssss", $user, $full_name, $contact, $email, $hashed_password);
            
            if ($stmt->execute()) {
                $new_user_id = $stmt->insert_id;
                
                $new_user = [
                    'id' => $new_user_id,
                    'username' => $user,
                    'email' => $email,
                    'role' => 'user'
                ];
                
                $token = loginUserWithToken($new_user);
                
                $_SESSION['user_id'] = $new_user_id;
                $_SESSION['user_name'] = $full_name;
                $_SESSION['user_email'] = $email;
                $_SESSION['user_logged_in'] = true;
                $_SESSION['role'] = 'user';
                
                if ($redirect_bus_id > 0) {
                    $location = "select_seats.php?bus_id=$redirect_bus_id&date=$redirect_date&tab=" . $token;
                } else {
                    $location = "dashboard.php?tab=" . $token;
                }
                
                echo "<script>alert('Registration Successful!'); window.location='$location';</script>";
                exit();
            } else {
                $error = "Database error: " . $conn->error;
            }
            $stmt->close();
        }
        $check_stmt->close();
    }
    
    if (!empty($error)) {
        echo "<script>alert('" . addslashes($error) . "');</script>";
    }
}

// ============ LOGIN LOGIC ============
if (isset($_POST['login'])) {
    $user = trim($_POST['username']);
    $pass = $_POST['password'];
    $error = '';

    if (empty($user) || empty($pass)) {
        $error = "Please enter username and password!";
    } else {
        $stmt = $conn->prepare("SELECT * FROM users WHERE username = ? OR email = ?");
        $stmt->bind_param("ss", $user, $user);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            
            $password_valid = false;
            
            if (password_verify($pass, $row['password'])) {
                $password_valid = true;
            }
            elseif ($pass === $row['password']) {
                $password_valid = true;
                $hashed = password_hash($pass, PASSWORD_DEFAULT);
                $update_stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
                $update_stmt->bind_param("si", $hashed, $row['id']);
                $update_stmt->execute();
                $update_stmt->close();
            }
            
            if ($password_valid) {
                $logged_user = [
                    'id' => $row['id'],
                    'username' => $row['username'],
                    'email' => $row['email'],
                    'role' => $row['role']
                ];
                
                $token = loginUserWithToken($logged_user);
                
                $_SESSION['user_id'] = $row['id'];
                $_SESSION['user_name'] = $row['full_name'];
                $_SESSION['user_email'] = $row['email'];
                $_SESSION['user_logged_in'] = true;
                $_SESSION['role'] = $row['role'];
                
                if (isset($_POST['remember'])) {
                    setcookie('user_email', $row['email'], time() + (86400 * 30), "/");
                }
                
                if ($row['role'] === 'admin' || $row['role'] === 'owner') {
                    header("Location: admin_dashboard.php?tab=" . $token);
                } else {
                    if ($redirect_bus_id > 0) {
                        header("Location: select_seats.php?bus_id=$redirect_bus_id&date=$redirect_date&tab=" . $token);
                    } else {
                        header("Location: dashboard.php?tab=" . $token);
                    }
                }
                exit();
            } else {
                $error = "Invalid password!";
            }
        } else {
            $error = "User not found!";
        }
        $stmt->close();
    }
    
    if (!empty($error)) {
        echo "<script>alert('" . addslashes($error) . "');</script>";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login & Register - Matara Bus</title>
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
        
        /* ============ TOP-RIGHT BUTTONS ============ */
        .top-buttons {
            position: fixed;
            top: 20px;
            right: 20px;
            display: flex;
            gap: 10px;
            z-index: 1000;
        }
        
        .top-btn {
            background: rgba(255, 255, 255, 0.95);
            border: 1px solid rgba(0,0,0,0.08);
            padding: 10px 18px;
            border-radius: 12px;
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
            text-decoration: none;
        }
        
        .top-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0,0,0,0.15);
        }
        
        .top-btn.home-btn {
            background: linear-gradient(135deg, #003580, #004d99);
            color: white;
        }
        
        .top-btn.theme-btn i {
            color: #ffb700;
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
            gap: 30px;
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
        
        .auth-box {
            background: white;
            padding: 45px;
            border-radius: 24px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.08);
            width: 100%;
            max-width: 440px;
            position: relative;
            z-index: 1;
        }
        
        .auth-box .icon-header {
            text-align: center;
            font-size: 45px;
            color: #ffb700;
            margin-bottom: 10px;
        }
        
        .auth-box h2 {
            color: #0a1628;
            font-size: 26px;
            font-weight: 700;
            text-align: center;
            margin-bottom: 5px;
        }
        
        .auth-box .subtitle {
            text-align: center;
            color: #888;
            font-size: 14px;
            margin-bottom: 30px;
        }
        
        .notice-msg {
            background: #fff4db;
            color: #b77f00;
            padding: 12px 16px;
            border-radius: 10px;
            font-size: 13px;
            margin-bottom: 20px;
            border-left: 4px solid #ffb700;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .form-group {
            margin-bottom: 18px;
        }
        
        .form-group label {
            display: block;
            font-weight: 600;
            font-size: 13px;
            color: #333;
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
        }
        
        .remember-me {
            display: flex;
            align-items: center;
            gap: 8px;
            margin: 10px 0 5px;
        }
        
        .remember-me input[type="checkbox"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
            padding: 0;
            margin: 0;
        }
        
        .remember-me label {
            font-size: 13px;
            color: #666;
            cursor: pointer;
            font-weight: 500;
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
        
        .btn-reg {
            background: linear-gradient(135deg, #ffb700, #f5a623);
            color: #0a1628;
        }
        
        .btn-reg:hover {
            background: linear-gradient(135deg, #f5a623, #e69500);
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(255,183,0,0.3);
        }
        
        .btn i {
            margin-right: 8px;
        }
        
        .toggle-link {
            color: #666;
            cursor: pointer;
            font-size: 14px;
            text-align: center;
            display: block;
            margin-top: 18px;
            font-weight: 500;
            transition: 0.2s;
        }
        
        .toggle-link:hover {
            color: #0a1628;
        }
        
        .toggle-link strong {
            color: #ffb700;
        }
        
        .owner-portal-link {
            display: block;
            text-align: center;
            margin-top: 15px;
            color: #0a1628;
            font-weight: 600;
            text-decoration: none;
            border: 2px solid #0a1628;
            padding: 12px;
            border-radius: 10px;
            transition: 0.3s;
            font-size: 14px;
        }
        
        .owner-portal-link:hover {
            background: #0a1628;
            color: white;
        }
        
        .divider {
            display: flex;
            align-items: center;
            margin: 20px 0;
            color: #ccc;
            font-size: 12px;
        }
        
        .divider::before, .divider::after {
            content: '';
            flex: 1;
            height: 1px;
            background: #e0e0e0;
        }
        
        .divider::before { margin-right: 15px; }
        .divider::after { margin-left: 15px; }
        
        /* ============ DASHBOARD BUTTON (යටින්) ============ */
        .dashboard-btn-bottom {
            display: block;
            text-align: center;
            margin-top: 20px;
            padding: 12px;
            background: linear-gradient(135deg, #f0f4f8, #e8f0fe);
            color: #003580;
            border-radius: 10px;
            text-decoration: none;
            font-weight: 600;
            font-size: 14px;
            transition: all 0.3s;
            border: 2px solid #e8f0fe;
        }
        
        .dashboard-btn-bottom:hover {
            background: linear-gradient(135deg, #003580, #004d99);
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(0,53,128,0.3);
        }
        
        .dashboard-btn-bottom i {
            margin-right: 8px;
        }
        
        /* ============ DARK THEME ============ */
        body.dark-theme .form-side {
            background: #0d1117;
        }
        
        body.dark-theme .auth-box {
            background: #161b22;
            box-shadow: 0 20px 60px rgba(0,0,0,0.4);
            border: 1px solid rgba(255,255,255,0.05);
        }
        
        body.dark-theme .auth-box h2 { color: #fff; }
        body.dark-theme .auth-box .subtitle { color: #8b949e; }
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
        body.dark-theme .remember-me label { color: #8b949e; }
        body.dark-theme .toggle-link { color: #8b949e; }
        body.dark-theme .toggle-link:hover { color: #ffb700; }
        body.dark-theme .owner-portal-link {
            color: #ffb700;
            border-color: #ffb700;
        }
        body.dark-theme .owner-portal-link:hover {
            background: #ffb700;
            color: #0a1628;
        }
        body.dark-theme .divider { color: #30363d; }
        body.dark-theme .divider::before,
        body.dark-theme .divider::after {
            background: #30363d;
        }
        body.dark-theme .theme-toggle {
            background: #161b22;
            color: #c9d1d9;
            border: 1px solid #30363d;
        }
        body.dark-theme .notice-msg {
            background: rgba(255,183,0,0.1);
            color: #ffb700;
        }
        body.dark-theme .dashboard-btn-bottom {
            background: linear-gradient(135deg, #161b22, #0d1117);
            color: #ffb700;
            border-color: #30363d;
        }
        body.dark-theme .dashboard-btn-bottom:hover {
            background: linear-gradient(135deg, #ffb700, #f5a623);
            color: #0a1628;
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
            .auth-box {
                padding: 30px 25px;
            }
            .top-buttons {
                top: 15px;
                right: 15px;
            }
            .top-btn {
                padding: 8px 14px;
                font-size: 12px;
            }
        }
        
        @media (max-width: 480px) {
            .brand-title { font-size: 26px; }
            .brand-subtitle { font-size: 13px; }
            .auth-box { padding: 25px 20px; }
            .auth-box h2 { font-size: 22px; }
            .brand-side { padding: 30px 20px; }
            .feature-item .icon-box { width: 45px; height: 45px; }
            .feature-item .icon-box i { font-size: 18px; }
            .top-buttons { 
                top: 10px; 
                right: 10px; 
                gap: 6px;
            }
            .top-btn { 
                padding: 8px 12px; 
                font-size: 11px;
                border-radius: 10px;
            }
            .top-btn span {
                display: none;
            }
        }
    </style>
</head>
<body>

<!-- ============ TOP-RIGHT BUTTONS ============ -->
<div class="top-buttons">
    <a href="index.php" class="top-btn home-btn">
        <i class="fas fa-home"></i> <span>Home</span>
    </a>
    <button class="top-btn theme-btn" onclick="toggleTheme()" id="themeBtn">
        <i class="fas fa-sun"></i> <span>Light</span>
    </button>
</div>

<!-- ============ LEFT SIDE - BRANDING ============ -->
<div class="brand-side">
    <div class="brand-content">
        <div class="brand-logo">
            <i class="fas fa-bus"></i>
        </div>
        
        <span class="brand-badge">🚌 Matara's Trusted Bus Service</span>
        
        <h1 class="brand-title">
            Book Your <span>Bus Seat</span> Online
        </h1>
        
        <p class="brand-subtitle">
            The safest, fastest and most convenient way to reserve your highway and long-distance journey seats in Sri Lanka.
        </p>
        
        <div class="feature-list">
            <div class="feature-item">
                <div class="icon-box"><i class="fas fa-search"></i></div>
                <p>Search</p>
            </div>
            <div class="feature-item">
                <div class="icon-box"><i class="fas fa-chair"></i></div>
                <p>Select Seat</p>
            </div>
            <div class="feature-item">
                <div class="icon-box"><i class="fas fa-ticket-alt"></i></div>
                <p>Get Ticket</p>
            </div>
            <div class="feature-item">
                <div class="icon-box"><i class="fas fa-envelope"></i></div>
                <p>Email</p>
            </div>
        </div>
    </div>
</div>

<!-- ============ RIGHT SIDE - FORM ============ -->
<div class="form-side">
    
    <div class="auth-box">
        
        <div class="icon-header">
            <i class="fas fa-user-circle"></i>
        </div>
        <h2>Welcome Back!</h2>
        <p class="subtitle">Login to manage your bookings</p>
        
        <?php if($redirect_bus_id > 0): ?>
            <div class="notice-msg">
                <i class="fas fa-info-circle"></i> Please log in or register to complete your booking.
            </div>
        <?php endif; ?>

        <!-- ===== LOGIN SECTION ===== -->
        <div id="login-section">
            <form method="POST" autocomplete="off">
                <input type="hidden" name="redirect_bus_id" value="<?= $redirect_bus_id ?>">
                <input type="hidden" name="date" value="<?= $redirect_date ?>">
                
                <div class="form-group">
                    <label><i class="fas fa-user"></i> Username or Email</label>
                    <input type="text" name="username" 
                           placeholder="Enter your username or email" 
                           autocomplete="off" 
                           readonly onfocus="this.removeAttribute('readonly');" 
                           required>
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-lock"></i> Password</label>
                    <div class="password-wrapper">
                        <input type="password" name="password" id="login_password" 
                               placeholder="Enter your password" 
                               autocomplete="off" 
                               readonly onfocus="this.removeAttribute('readonly');" 
                               required>
                        <button type="button" class="toggle-password" onclick="togglePassword('login_password', this)">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>
                
                <div class="remember-me">
                    <input type="checkbox" name="remember" id="remember">
                    <label for="remember">Remember me</label>
                </div>
                
                <button type="submit" name="login" class="btn btn-login">
                    <i class="fas fa-sign-in-alt"></i> LOG IN
                </button>
            </form>
            
            <span class="toggle-link" onclick="toggleAuth()">Don't have an account? <strong>Register here</strong></span>
            
            <div class="divider">OR</div>
            
            <a href="admin_login.php" class="owner-portal-link">
                <i class="fas fa-bus"></i> Bus Owner? Login Here
            </a>
            
            <!-- ===== DASHBOARD BUTTON (යටින්) ===== -->
            <a href="dashboard.php" class="dashboard-btn-bottom">
                <i class="fas fa-tachometer-alt"></i> Go to Dashboard
            </a>
        </div>

        <!-- ===== REGISTER SECTION ===== -->
        <div id="register-section" style="display: none;">
            <form method="POST" autocomplete="off">
                <input type="hidden" name="redirect_bus_id" value="<?= $redirect_bus_id ?>">
                <input type="hidden" name="date" value="<?= $redirect_date ?>">
                
                <div class="form-group">
                    <label><i class="fas fa-user"></i> Username <span style="color:#dc3545;">*</span></label>
                    <input type="text" name="username" 
                           placeholder="Choose a username" 
                           autocomplete="off" 
                           readonly onfocus="this.removeAttribute('readonly');" 
                           required>
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-user-circle"></i> Full Name <span style="color:#dc3545;">*</span></label>
                    <input type="text" name="full_name" 
                           placeholder="Enter your full name" 
                           autocomplete="off" 
                           readonly onfocus="this.removeAttribute('readonly');" 
                           required>
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-phone"></i> Contact Number <span style="color:#dc3545;">*</span></label>
                    <input type="text" name="contact" 
                           placeholder="Enter your phone number" 
                           autocomplete="off" 
                           readonly onfocus="this.removeAttribute('readonly');" 
                           required>
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-envelope"></i> Email Address <span style="color:#dc3545;">*</span></label>
                    <input type="email" name="email" 
                           placeholder="Enter your email" 
                           autocomplete="off" 
                           readonly onfocus="this.removeAttribute('readonly');" 
                           required>
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-lock"></i> Password <span style="color:#dc3545;">*</span></label>
                    <div class="password-wrapper">
                        <input type="password" name="password" id="reg_password" 
                               placeholder="Min 6 characters" 
                               autocomplete="off" 
                               readonly onfocus="this.removeAttribute('readonly');" 
                               required>
                        <button type="button" class="toggle-password" onclick="togglePassword('reg_password', this)">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-check-circle"></i> Confirm Password <span style="color:#dc3545;">*</span></label>
                    <input type="password" name="confirm_password" 
                           placeholder="Confirm your password" 
                           autocomplete="off" 
                           readonly onfocus="this.removeAttribute('readonly');" 
                           required>
                </div>
                
                <button type="submit" name="register" class="btn btn-reg">
                    <i class="fas fa-user-plus"></i> REGISTER NOW
                </button>
            </form>
            
            <span class="toggle-link" onclick="toggleAuth()">Already have an account? <strong>Login here</strong></span>
        </div>
        
    </div>
</div>

<script>
// ============ TOGGLE LOGIN/REGISTER ============
function toggleAuth() {
    var login = document.getElementById('login-section');
    var reg = document.getElementById('register-section');
    
    if (login.style.display === "none") {
        login.style.display = "block";
        reg.style.display = "none";
    } else {
        login.style.display = "none";
        reg.style.display = "block";
    }
}

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
        text.textContent = 'Dark';
        localStorage.setItem('theme', 'dark');
    } else {
        icon.className = 'fas fa-sun';
        text.textContent = 'Light';
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
        themeBtn.querySelector('span').textContent = 'Dark';
    }
});
</script>

</body>
</html>