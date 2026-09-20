<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }

// ============ CHECK USER ROLE ============
$is_user_logged_in = isset($_SESSION['user_logged_in']) && $_SESSION['user_logged_in'] === true;
$is_admin_logged_in = isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
$is_conductor_logged_in = isset($_SESSION['conductor_logged_in']) && $_SESSION['conductor_logged_in'] === true;

// Get user name based on role
$display_name = '';
if ($is_conductor_logged_in) {
    $display_name = $_SESSION['conductor_name'] ?? 'Conductor';
} elseif ($is_admin_logged_in) {
    $display_name = $_SESSION['admin_username'] ?? 'Admin';
} elseif ($is_user_logged_in) {
    $display_name = $_SESSION['user_name'] ?? 'User';
}
?>

<!-- ===== HEADER SECTION ===== -->
<nav style="
    background: rgba(255, 255, 255, 0.98);
    padding: 12px 40px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    z-index: 1000;
    box-shadow: 0 4px 20px rgba(0,0,0,0.08);
    backdrop-filter: blur(10px);
    box-sizing: border-box;
    font-family: 'Poppins', sans-serif;
    border-bottom: 1px solid rgba(0,0,0,0.05);
">
    
    <!-- ===== LOGO ===== -->
    <a href="index.php" style="
        font-weight: 700;
        font-size: 18px;
        color: #003580;
        display: flex;
        align-items: center;
        gap: 10px;
        text-decoration: none;
    ">
        <i class="fas fa-bus" style="color: #ffb700;"></i> 
        Matara Bus Station <span style="color: #ffb700;">Seat Booking</span>
    </a>

    <!-- ===== MOBILE MENU TOGGLE ===== -->
    <button onclick="toggleMobileMenu()" style="
        display: none;
        background: none;
        border: none;
        font-size: 24px;
        color: #003580;
        cursor: pointer;
    " id="menuToggle">
        <i class="fas fa-bars"></i>
    </button>

    <!-- ===== NAVIGATION LINKS ===== -->
    <div style="
        display: flex;
        align-items: center;
        gap: 25px;
    " id="navLinks">
        
        <!-- Home -->
        <a href="index.php" style="
            text-decoration: none;
            color: <?= basename($_SERVER['PHP_SELF']) == 'index.php' ? '#ffb700' : '#333' ?>;
            font-weight: <?= basename($_SERVER['PHP_SELF']) == 'index.php' ? '700' : '500' ?>;
            font-size: 14px;
            transition: all 0.3s;
            padding: 5px 0;
            border-bottom: <?= basename($_SERVER['PHP_SELF']) == 'index.php' ? '3px solid #ffb700' : '3px solid transparent' ?>;
        " onmouseover="this.style.color='#ffb700'" onmouseout="this.style.color='<?= basename($_SERVER['PHP_SELF']) == 'index.php' ? '#ffb700' : '#333' ?>'">
            <i class="fas fa-home"></i> Home
        </a>
        
        <!-- ===== My Bookings - හැමෝටම ===== -->
        <a href="my_bookings.php" style="
            text-decoration: none;
            color: <?= basename($_SERVER['PHP_SELF']) == 'my_bookings.php' ? '#ffb700' : '#333' ?>;
            font-weight: <?= basename($_SERVER['PHP_SELF']) == 'my_bookings.php' ? '700' : '500' ?>;
            font-size: 14px;
            transition: all 0.3s;
            padding: 5px 0;
            border-bottom: <?= basename($_SERVER['PHP_SELF']) == 'my_bookings.php' ? '3px solid #ffb700' : '3px solid transparent' ?>;
        " onmouseover="this.style.color='#ffb700'" onmouseout="this.style.color='<?= basename($_SERVER['PHP_SELF']) == 'my_bookings.php' ? '#ffb700' : '#333' ?>'">
            <i class="fas fa-ticket-alt"></i> My Bookings
        </a>
        
        <!-- Admin Dashboard - Admin/Owner පමණක් -->
        <?php if ($is_admin_logged_in && !$is_conductor_logged_in): ?>
        <a href="admin_dashboard.php" style="
            text-decoration: none;
            color: <?= basename($_SERVER['PHP_SELF']) == 'admin_dashboard.php' ? '#ffb700' : '#333' ?>;
            font-weight: <?= basename($_SERVER['PHP_SELF']) == 'admin_dashboard.php' ? '700' : '500' ?>;
            font-size: 14px;
            transition: all 0.3s;
            padding: 5px 0;
            border-bottom: <?= basename($_SERVER['PHP_SELF']) == 'admin_dashboard.php' ? '3px solid #ffb700' : '3px solid transparent' ?>;
        " onmouseover="this.style.color='#ffb700'" onmouseout="this.style.color='<?= basename($_SERVER['PHP_SELF']) == 'admin_dashboard.php' ? '#ffb700' : '#333' ?>'">
            <i class="fas fa-tachometer-alt"></i> Dashboard
        </a>
        <?php endif; ?>
        
        <!-- Conductor Dashboard - Conductor පමණක් -->
        <?php if ($is_conductor_logged_in): ?>
        <a href="conductor_dashboard.php" style="
            text-decoration: none;
            color: <?= basename($_SERVER['PHP_SELF']) == 'conductor_dashboard.php' ? '#ffb700' : '#333' ?>;
            font-weight: <?= basename($_SERVER['PHP_SELF']) == 'conductor_dashboard.php' ? '700' : '500' ?>;
            font-size: 14px;
            transition: all 0.3s;
            padding: 5px 0;
            border-bottom: <?= basename($_SERVER['PHP_SELF']) == 'conductor_dashboard.php' ? '3px solid #ffb700' : '3px solid transparent' ?>;
        " onmouseover="this.style.color='#ffb700'" onmouseout="this.style.color='<?= basename($_SERVER['PHP_SELF']) == 'conductor_dashboard.php' ? '#ffb700' : '#333' ?>'">
            <i class="fas fa-bus"></i> Dashboard
        </a>
        <?php endif; ?>
        
        <!-- Contact -->
        <a href="contact.php" style="
            text-decoration: none;
            color: <?= basename($_SERVER['PHP_SELF']) == 'contact.php' ? '#ffb700' : '#333' ?>;
            font-weight: <?= basename($_SERVER['PHP_SELF']) == 'contact.php' ? '700' : '500' ?>;
            font-size: 14px;
            transition: all 0.3s;
            padding: 5px 0;
            border-bottom: <?= basename($_SERVER['PHP_SELF']) == 'contact.php' ? '3px solid #ffb700' : '3px solid transparent' ?>;
        " onmouseover="this.style.color='#ffb700'" onmouseout="this.style.color='<?= basename($_SERVER['PHP_SELF']) == 'contact.php' ? '#ffb700' : '#333' ?>'">
            <i class="fas fa-envelope"></i> Contact
        </a>
        
        <!-- ===== USER SECTION ===== -->
        <?php if ($is_user_logged_in || $is_admin_logged_in || $is_conductor_logged_in): ?>
            <div style="
                display: flex;
                align-items: center;
                gap: 12px;
                border-left: 2px solid #e8f0fe;
                padding-left: 20px;
            ">
                <!-- User Avatar -->
                <div style="
                    width: 35px;
                    height: 35px;
                    border-radius: 50%;
                    background: <?= $is_conductor_logged_in ? 'linear-gradient(135deg, #ffb700, #f5a623)' : ($is_admin_logged_in ? 'linear-gradient(135deg, #e63946, #c62828)' : 'linear-gradient(135deg, #003580, #004d99)') ?>;
                    color: white;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    font-weight: 600;
                    font-size: 14px;
                ">
                    <?= strtoupper(substr($display_name, 0, 1)) ?>
                </div>
                
                <div style="display: flex; flex-direction: column; line-height: 1.3;">
                    <span style="font-size: 13px; color: #888;">
                        <?php if ($is_conductor_logged_in): ?>
                            <i class="fas fa-user-tie"></i> Conductor
                        <?php elseif ($is_admin_logged_in): ?>
                            <i class="fas fa-user-shield"></i> Admin
                        <?php else: ?>
                            <i class="fas fa-user"></i> Welcome
                        <?php endif; ?>
                    </span>
                    <span style="font-size: 14px; color: #003580; font-weight: 600;">
                        <?= htmlspecialchars($display_name) ?>
                        <?php if ($is_admin_logged_in && $_SESSION['role'] === 'super_admin'): ?>
                            <span style="font-size: 10px; background: #ffb700; color: #003580; padding: 1px 8px; border-radius: 10px; font-weight: 700;">Super</span>
                        <?php endif; ?>
                        <?php if ($is_conductor_logged_in): ?>
                            <span style="font-size: 10px; background: #ffb700; color: #003580; padding: 1px 8px; border-radius: 10px; font-weight: 700;">Conductor</span>
                        <?php endif; ?>
                    </span>
                </div>
                
                <a href="logout.php" style="
                    background: #e63946;
                    color: white;
                    padding: 7px 18px;
                    border-radius: 8px;
                    text-decoration: none;
                    font-size: 12px;
                    font-weight: 600;
                    transition: all 0.3s;
                    border: none;
                    cursor: pointer;
                " onmouseover="this.style.background='#c62828'; this.style.transform='translateY(-2px)'" onmouseout="this.style.background='#e63946'; this.style.transform='translateY(0)'">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
            
        <?php else: ?>
            <!-- ===== LOGIN BUTTONS (Guest) ===== -->
            <div style="display: flex; align-items: center; gap: 10px;">
                <a href="login.php" style="
                    background: linear-gradient(135deg, #28a745, #1e7e34);
                    color: white;
                    padding: 8px 18px;
                    border-radius: 8px;
                    text-decoration: none;
                    font-size: 13px;
                    font-weight: 600;
                    transition: all 0.3s;
                    box-shadow: 0 4px 15px rgba(40,167,69,0.25);
                " onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 8px 25px rgba(40,167,69,0.35)'" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 4px 15px rgba(40,167,69,0.25)'">
                    <i class="fas fa-user"></i> Login
                </a>
                <a href="admin_login.php" style="
                    background: transparent;
                    color: #003580;
                    padding: 8px 16px;
                    border-radius: 8px;
                    text-decoration: none;
                    font-size: 13px;
                    font-weight: 600;
                    transition: all 0.3s;
                    border: 2px solid #003580;
                " onmouseover="this.style.background='#003580'; this.style.color='white'; this.style.transform='translateY(-2px)'" onmouseout="this.style.background='transparent'; this.style.color='#003580'; this.style.transform='translateY(0)'">
                    <i class="fas fa-user-shield"></i> Admin
                </a>
            </div>
        <?php endif; ?>
    </div>
</nav>

<!-- ===== RESPONSIVE CSS ===== -->
<style>
    @media (max-width: 768px) {
        nav { padding: 12px 20px !important; flex-wrap: wrap; }
        #menuToggle { display: block !important; }
        #navLinks { display: none !important; flex-direction: column !important; width: 100% !important; gap: 8px !important; padding: 15px 0 5px 0 !important; border-top: 1px solid rgba(0,0,0,0.05) !important; margin-top: 10px !important; }
        #navLinks.show { display: flex !important; }
        #navLinks > a { width: 100% !important; text-align: center !important; padding: 10px !important; border-bottom: none !important; }
        #navLinks > div { width: 100% !important; flex-direction: column !important; border-left: none !important; padding-left: 0 !important; gap: 8px !important; padding-top: 10px !important; border-top: 1px solid #eee !important; }
        #navLinks > div a { width: 100% !important; text-align: center !important; }
        nav .logo { font-size: 16px !important; }
    }
    @media (max-width: 480px) {
        nav { padding: 10px 15px !important; }
        nav .logo { font-size: 14px !important; }
        nav .logo i { font-size: 16px !important; }
        #navLinks > div { gap: 5px !important; }
        #navLinks > div a { font-size: 12px !important; padding: 6px 12px !important; }
    }
</style>

<script>
    function toggleMobileMenu() {
        const navLinks = document.getElementById('navLinks');
        navLinks.classList.toggle('show');
        const toggle = document.getElementById('menuToggle');
        if (navLinks.classList.contains('show')) {
            toggle.innerHTML = '<i class="fas fa-times"></i>';
        } else {
            toggle.innerHTML = '<i class="fas fa-bars"></i>';
        }
    }
    document.addEventListener('DOMContentLoaded', function() {
        const links = document.querySelectorAll('#navLinks a');
        links.forEach(link => {
            link.addEventListener('click', function() {
                const navLinks = document.getElementById('navLinks');
                if (window.innerWidth <= 768) {
                    navLinks.classList.remove('show');
                    document.getElementById('menuToggle').innerHTML = '<i class="fas fa-bars"></i>';
                }
            });
        });
    });
</script>