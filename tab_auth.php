<?php
// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Login user with a unique tab token
 */
function loginUserWithToken($user) {
    $token = bin2hex(random_bytes(32));
    
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['email'] = $user['email'];
    $_SESSION['role'] = $user['role'];
    $_SESSION['login_time'] = time();
    $_SESSION['tab_token'] = $token;
    
    return $token;
}

/**
 * Check if current tab has valid token
 */
function isTabAuthenticated() {
    $has_login = (
        (isset($_SESSION['user_logged_in']) && $_SESSION['user_logged_in'] === true) ||
        (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) ||
        (isset($_SESSION['conductor_logged_in']) && $_SESSION['conductor_logged_in'] === true)
    );
    
    if (!$has_login) return false;
    if (!isset($_SESSION['tab_token'])) return false;
    
    // Check cookie token
    $cookie_token = $_COOKIE['tab_token'] ?? '';
    return $cookie_token === $_SESSION['tab_token'];
}

/**
 * Require tab authentication
 */
function requireTabAuth($login_url = 'admin_login.php') {
    $url_token = $_GET['tab'] ?? '';
    $session_token = $_SESSION['tab_token'] ?? '';
    
    if (!empty($url_token) && $url_token === $session_token) {
        setcookie('tab_token', $url_token, 0, '/');
        $_COOKIE['tab_token'] = $url_token;
        
        $clean_url = strtok($_SERVER['REQUEST_URI'], '?');
        header("Location: " . $clean_url);
        exit();
    }
    
    if (isTabAuthenticated()) {
        return true;
    }
    
    showTabLoginPrompt($login_url);
    exit();
}

/**
 * Show login prompt
 */
function showTabLoginPrompt($login_url) {
    $user_login = 'login.php';
    $admin_login = 'admin_login.php';
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>Session Not Found</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
        <style>
            body { background: linear-gradient(135deg, #667eea, #764ba2); min-height: 100vh; display: flex; align-items: center; justify-content: center; font-family: 'Segoe UI', sans-serif; }
            .card-box { background: white; border-radius: 25px; padding: 50px 40px; max-width: 450px; text-align: center; box-shadow: 0 25px 50px rgba(0,0,0,0.3); }
            .lock-icon { font-size: 70px; color: #667eea; margin-bottom: 20px; }
        </style>
    </head>
    <body>
        <div class="card-box">
            <div class="lock-icon"><i class="fas fa-lock"></i></div>
            <h3 class="mb-3">Session Not Found</h3>
            <p class="text-muted mb-4">This tab is not logged in.<br>Please login to continue.</p>
            <a href="<?php echo $admin_login; ?>" class="btn btn-primary w-100 mb-2"><i class="fas fa-user-shield"></i> Admin Login</a>
            <a href="<?php echo $user_login; ?>" class="btn btn-outline-secondary w-100"><i class="fas fa-user"></i> Passenger Login</a>
        </div>
    </body>
    </html>
    <?php
}
?>