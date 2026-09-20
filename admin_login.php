<?php
include 'config.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ============ REDIRECT IF ALREADY LOGGED IN ============
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header("Location: admin_dashboard.php");
    exit();
}
if (isset($_SESSION['conductor_logged_in']) && $_SESSION['conductor_logged_in'] === true) {
    header("Location: conductor_dashboard.php");
    exit();
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
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['role'] = 'super_admin';
            $_SESSION['admin_username'] = 'Main Admin';
            $_SESSION['bus_name'] = 'All Buses System';
            header("Location: admin_dashboard.php");
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
                $_SESSION['admin_logged_in'] = true;
                $_SESSION['role'] = 'bus_owner';
                $_SESSION['owner_bus_id'] = $row['id']; 
                $_SESSION['admin_username'] = $row['owner_username'];
                $_SESSION['bus_name'] = $row['bus_name'];
                
                header("Location: admin_dashboard.php");
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
                    $_SESSION['conductor_logged_in'] = true;
                    $_SESSION['conductor_id'] = $row['id'];
                    $_SESSION['conductor_name'] = $row['conductor_name'] ?? $row['bus_name'];
                    $_SESSION['bus_id'] = $row['id'];
                    $_SESSION['bus_name'] = $row['bus_name'];
                    $_SESSION['role'] = 'conductor';
                    
                    header("Location: conductor_dashboard.php");
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
    <title>Admin Login - Matara Bus</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: 'Poppins', sans-serif; 
            background: linear-gradient(135deg, #00255a 0%, #001533 100%); 
            min-height: 100vh;
            padding-top: 80px;
        }
        
        .main-wrapper { 
            display: flex; 
            justify-content: center; 
            align-items: center; 
            min-height: calc(100vh - 120px); 
            padding: 20px; 
        }
        
        .login-box { 
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            padding: 40px; 
            border-radius: 24px; 
            box-shadow: 0 20px 60px rgba(0,0,0,0.3); 
            width: 100%; 
            max-width: 420px; 
            border: 1px solid rgba(255,255,255,0.2);
            text-align: center;
        }
        
        .login-box .icon-header {
            text-align: center;
            font-size: 50px;
            color: #ffb700;
            margin-bottom: 5px;
        }
        
        .login-box h2 { 
            color: #003580; 
            font-size: 24px; 
            font-weight: 700;
            text-align: center; 
            margin-bottom: 5px;
        }
        
        .login-box .subtitle {
            text-align: center;
            color: #888;
            font-size: 14px;
            margin-bottom: 25px;
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
            margin-bottom: 15px;
        }
        
        .form-group label {
            font-weight: 600;
            font-size: 13px;
            color: #333;
            display: block;
            margin-bottom: 4px;
        }
        
        .form-group label i {
            color: #003580;
            margin-right: 6px;
        }
        
        input { 
            width: 100%; 
            padding: 12px 16px; 
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
            border-color: #003580;
            background: #fff;
            box-shadow: 0 0 0 4px rgba(0,53,128,0.08);
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
            font-size: 15px;
        }
        
        .btn-login { 
            background: linear-gradient(135deg, #003580, #004d99);
            color: white; 
        }
        
        .btn-login:hover { 
            background: linear-gradient(135deg, #00255a, #003580);
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0,53,128,0.3);
        }
        
        .btn i {
            margin-right: 8px;
        }
        
        .back-link { 
            display: inline-block; 
            margin-top: 20px; 
            color: #888; 
            text-decoration: none; 
            font-size: 13px; 
            font-weight: 500;
            transition: 0.2s;
        }
        
        .back-link:hover { 
            color: #003580; 
            text-decoration: underline; 
        }
        
        .login-footer {
            margin-top: 20px;
            padding-top: 15px;
            border-top: 1px solid #eee;
            font-size: 12px;
            color: #aaa;
        }
        
        .login-footer .role-info {
            font-size: 11px;
            color: #ccc;
        }
        
        @media (max-width: 480px) {
            .login-box {
                padding: 25px 20px;
            }
        }
    </style>
</head>
<body>

<?php include 'header.php'; ?>

<div class="main-wrapper">
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
        
        <form method="POST">
            <div class="form-group">
                <label><i class="fas fa-user"></i> Username</label>
                <input type="text" name="username" placeholder="Enter your username" required>
            </div>
            
            <div class="form-group">
                <label><i class="fas fa-lock"></i> Password</label>
                <input type="password" name="password" placeholder="Enter your password" required>
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

</body>
</html>