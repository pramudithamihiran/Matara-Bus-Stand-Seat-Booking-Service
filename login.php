<?php
include 'config.php';
if (session_status() === PHP_SESSION_NONE) { session_start(); }

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
            
            $stmt = $conn->prepare("INSERT INTO users (username, full_name, contact_number, email, password, role) VALUES (?, ?, ?, ?, ?, 'user')");
            $stmt->bind_param("sssss", $user, $full_name, $contact, $email, $hashed_password);
            
            if ($stmt->execute()) {
                $_SESSION['user_id'] = $stmt->insert_id;
                $_SESSION['user_name'] = $full_name;
                $_SESSION['user_email'] = $email;
                $_SESSION['user_logged_in'] = true;
                $_SESSION['role'] = 'user';
                
                $location = ($redirect_bus_id > 0) ? "select_seats.php?bus_id=$redirect_bus_id&date=$redirect_date" : "dashboard.php";
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
            
            // ✅ Plain text සහ Hashed දෙකම check කරන්න
            $password_valid = false;
            
            // 1. Hashed password check
            if (password_verify($pass, $row['password'])) {
                $password_valid = true;
            }
            // 2. Plain text password check (old users - තාවකාලික)
            elseif ($pass === $row['password']) {
                $password_valid = true;
                // ✅ පැරණි password එක hash කරලා update කරන්න
                $hashed = password_hash($pass, PASSWORD_DEFAULT);
                $update_stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
                $update_stmt->bind_param("si", $hashed, $row['id']);
                $update_stmt->execute();
                $update_stmt->close();
            }
            
            if ($password_valid) {
                $_SESSION['user_id'] = $row['id'];
                $_SESSION['user_name'] = $row['full_name'];
                $_SESSION['user_email'] = $row['email'];
                $_SESSION['user_logged_in'] = true;
                $_SESSION['role'] = $row['role'];
                
                if (isset($_POST['remember'])) {
                    setcookie('user_email', $row['email'], time() + (86400 * 30), "/");
                }
                
                if ($row['role'] === 'admin' || $row['role'] === 'owner') {
                    header("Location: admin_dashboard.php");
                } else {
                    $url = ($redirect_bus_id > 0) ? "select_seats.php?bus_id=$redirect_bus_id&date=$redirect_date" : "dashboard.php";
                    header("Location: $url");
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
        
        .auth-box { 
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            padding: 40px; 
            border-radius: 24px; 
            box-shadow: 0 20px 60px rgba(0,0,0,0.3); 
            width: 100%; 
            max-width: 420px; 
            border: 1px solid rgba(255,255,255,0.2);
        }
        
        .auth-box .icon-header {
            text-align: center;
            font-size: 50px;
            color: #ffb700;
            margin-bottom: 5px;
        }
        
        .auth-box h2 { 
            color: #003580; 
            font-size: 24px; 
            font-weight: 700;
            text-align: center; 
            margin-bottom: 5px;
        }
        
        .auth-box .subtitle {
            text-align: center;
            color: #888;
            font-size: 14px;
            margin-bottom: 25px;
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
            margin-bottom: 15px;
        }
        
        .form-group label {
            display: block;
            font-weight: 600;
            font-size: 13px;
            color: #333;
            margin-bottom: 4px;
        }
        
        .form-group label i {
            color: #003580;
            margin-right: 6px;
        }
        
        input { 
            width: 100%; 
            padding: 13px 16px; 
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
        
        .btn-reg { 
            background: linear-gradient(135deg, #ffb700, #f5a623);
            color: #003580; 
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
            color: #003580;
            text-decoration: underline;
        }
        
        .owner-portal-link { 
            display: block; 
            text-align: center; 
            margin-top: 15px; 
            color: #003580; 
            font-weight: 600; 
            text-decoration: none; 
            border: 2px solid #003580; 
            padding: 10px; 
            border-radius: 10px; 
            transition: 0.3s;
        }
        
        .owner-portal-link:hover { 
            background: #003580; 
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
        
        .divider::before {
            margin-right: 15px;
        }
        
        .divider::after {
            margin-left: 15px;
        }
        
        @media (max-width: 480px) {
            .auth-box {
                padding: 25px 20px;
            }
        }
    </style>
</head>
<body>

<?php include 'header.php'; ?>

<div class="main-wrapper">
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
            <form method="POST">
                <input type="hidden" name="redirect_bus_id" value="<?= $redirect_bus_id ?>">
                <input type="hidden" name="date" value="<?= $redirect_date ?>">
                
                <div class="form-group">
                    <label><i class="fas fa-user"></i> Username or Email</label>
                    <input type="text" name="username" placeholder="Enter your username or email" required>
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-lock"></i> Password</label>
                    <div class="password-wrapper">
                        <input type="password" name="password" id="login_password" placeholder="Enter your password" required>
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
        </div>

        <!-- ===== REGISTER SECTION ===== -->
        <div id="register-section" style="display: none;">
            <form method="POST">
                <input type="hidden" name="redirect_bus_id" value="<?= $redirect_bus_id ?>">
                <input type="hidden" name="date" value="<?= $redirect_date ?>">
                
                <div class="form-group">
                    <label><i class="fas fa-user"></i> Username <span style="color:#dc3545;">*</span></label>
                    <input type="text" name="username" placeholder="Choose a username" required>
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-user-circle"></i> Full Name <span style="color:#dc3545;">*</span></label>
                    <input type="text" name="full_name" placeholder="Enter your full name" required>
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-phone"></i> Contact Number <span style="color:#dc3545;">*</span></label>
                    <input type="text" name="contact" placeholder="Enter your phone number" required>
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-envelope"></i> Email Address <span style="color:#dc3545;">*</span></label>
                    <input type="email" name="email" placeholder="Enter your email" required>
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-lock"></i> Password <span style="color:#dc3545;">*</span></label>
                    <div class="password-wrapper">
                        <input type="password" name="password" id="reg_password" placeholder="Min 6 characters" required>
                        <button type="button" class="toggle-password" onclick="togglePassword('reg_password', this)">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-check-circle"></i> Confirm Password <span style="color:#dc3545;">*</span></label>
                    <input type="password" name="confirm_password" placeholder="Confirm your password" required>
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
</script>

</body>
</html>