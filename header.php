<?php
// ============ LOAD CONFIG ============
include 'config.php';

// ============ CHECK USER ROLE ============
$is_user_logged_in = isset($_SESSION['user_logged_in']) && $_SESSION['user_logged_in'] === true;
$is_admin_logged_in = isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
$is_conductor_logged_in = isset($_SESSION['conductor_logged_in']) && $_SESSION['conductor_logged_in'] === true;

// ============ TAB TOKEN CHECK ============
if ($is_user_logged_in || $is_admin_logged_in || $is_conductor_logged_in) {
    if (!isTabAuthenticated()) {
        $is_user_logged_in = false;
        $is_admin_logged_in = false;
        $is_conductor_logged_in = false;
    }
}

// Get user name based on role
$display_name = '';
if ($is_conductor_logged_in) {
    $display_name = $_SESSION['conductor_name'] ?? 'Conductor';
} elseif ($is_admin_logged_in) {
    $display_name = $_SESSION['admin_username'] ?? 'Admin';
} elseif ($is_user_logged_in) {
    $display_name = $_SESSION['user_name'] ?? 'User';
}

$current_page = basename($_SERVER['PHP_SELF']);
?>

<!-- ===== GLOBAL BODY BACKGROUND - DEEP PURPLE DARK ===== -->
<style>
    html, body {
        background: linear-gradient(135deg, #0f0022 0%, #1a0033 50%, #0a0018 100%) !important;
        background-attachment: fixed !important;
        min-height: 100vh;
        color: #e0e0e0;
    }
    
    /* Custom Gold Scrollbar */
    ::-webkit-scrollbar {
        width: 10px;
        height: 10px;
    }
    ::-webkit-scrollbar-track {
        background: #0f0022;
    }
    ::-webkit-scrollbar-thumb {
        background: linear-gradient(180deg, #ffb700, #f5a623);
        border-radius: 10px;
        border: 2px solid #0f0022;
    }
    ::-webkit-scrollbar-thumb:hover {
        background: linear-gradient(180deg, #f5a623, #e69500);
    }
    
    /* Glass effect support */
    @supports (backdrop-filter: blur(20px)) {
        .glass-header {
            background: rgba(15, 0, 34, 0.75) !important;
            backdrop-filter: blur(30px) saturate(180%) !important;
            -webkit-backdrop-filter: blur(30px) saturate(180%) !important;
        }
    }
</style>

<!-- ===== DEEP PURPLE GOLD GLASS HEADER ===== -->
<nav class="glass-header" style="
    background: rgba(15, 0, 34, 0.85);
    backdrop-filter: blur(30px) saturate(180%);
    -webkit-backdrop-filter: blur(30px) saturate(180%);
    padding: 10px 24px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    position: fixed;
    top: 12px;
    left: 50%;
    transform: translateX(-50%);
    width: calc(100% - 32px);
    max-width: 1400px;
    z-index: 1000;
    box-shadow: 
        0 8px 32px rgba(0, 0, 0, 0.5),
        0 1px 2px rgba(255, 183, 0, 0.15) inset,
        0 -1px 2px rgba(0, 0, 0, 0.3) inset;
    box-sizing: border-box;
    font-family: 'Poppins', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
    border: 1px solid rgba(255, 183, 0, 0.2);
    border-radius: 22px;
    transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
">
    
    <!-- ===== LOGO ===== -->
    <a href="index.php" style="
        font-weight: 700;
        font-size: 16px;
        color: #ffffff;
        display: flex;
        align-items: center;
        gap: 10px;
        text-decoration: none;
        letter-spacing: -0.2px;
        transition: all 0.3s ease;
    " onmouseover="this.style.transform='scale(1.02)'" onmouseout="this.style.transform='scale(1)'">
        <div style="
            width: 36px;
            height: 36px;
            background: linear-gradient(135deg, #ffb700, #f5a623);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 
                0 4px 15px rgba(255, 183, 0, 0.4),
                0 1px 2px rgba(255, 255, 255, 0.3) inset;
        ">
            <i class="fas fa-bus" style="color: #0f0022; font-size: 16px;"></i>
        </div>
        <span>Matara Bus Station</span>
        <span style="color: #ffb700;">Seat Booking</span>
    </a>

    <!-- ===== MOBILE MENU TOGGLE ===== -->
    <button onclick="toggleMobileMenu()" style="
        display: none;
        background: rgba(255, 183, 0, 0.1);
        border: 1px solid rgba(255, 183, 0, 0.2);
        border-radius: 12px;
        width: 40px;
        height: 40px;
        font-size: 18px;
        color: #ffb700;
        cursor: pointer;
        transition: all 0.3s;
    " id="menuToggle">
        <i class="fas fa-bars"></i>
    </button>

    <!-- ===== NAVIGATION LINKS ===== -->
    <div style="
        display: flex;
        align-items: center;
        gap: 6px;
    " id="navLinks">
        
        <!-- Home -->
        <a href="index.php" style="
            text-decoration: none;
            color: <?= $current_page == 'index.php' ? '#ffb700' : '#b0b0b0' ?>;
            font-weight: <?= $current_page == 'index.php' ? '700' : '500' ?>;
            font-size: 13px;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            padding: 8px 14px;
            border-radius: 12px;
            background: <?= $current_page == 'index.php' ? 'rgba(255, 183, 0, 0.15)' : 'transparent' ?>;
        " onmouseover="this.style.background='rgba(255,183,0,0.1)'; this.style.color='#ffb700'; this.style.transform='translateY(-1px)'" onmouseout="this.style.background='<?= $current_page == 'index.php' ? 'rgba(255, 183, 0, 0.15)' : 'transparent' ?>'; this.style.color='<?= $current_page == 'index.php' ? '#ffb700' : '#b0b0b0' ?>'; this.style.transform='translateY(0)'">
            <i class="fas fa-home"></i> Home
        </a>
        
        <!-- Dashboard -->
        <a href="<?= $is_user_logged_in ? 'dashboard.php' : 'login.php' ?>" style="
            text-decoration: none;
            color: <?= $current_page == 'dashboard.php' ? '#ffb700' : '#b0b0b0' ?>;
            font-weight: <?= $current_page == 'dashboard.php' ? '700' : '500' ?>;
            font-size: 13px;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            padding: 8px 14px;
            border-radius: 12px;
            background: <?= $current_page == 'dashboard.php' ? 'rgba(255, 183, 0, 0.15)' : 'transparent' ?>;
        " onmouseover="this.style.background='rgba(255,183,0,0.1)'; this.style.color='#ffb700'; this.style.transform='translateY(-1px)'" onmouseout="this.style.background='<?= $current_page == 'dashboard.php' ? 'rgba(255, 183, 0, 0.15)' : 'transparent' ?>'; this.style.color='<?= $current_page == 'dashboard.php' ? '#ffb700' : '#b0b0b0' ?>'; this.style.transform='translateY(0)'">
            <i class="fas fa-tachometer-alt"></i> Dashboard
        </a>
        
        <!-- My Bookings -->
        <a href="<?= $is_user_logged_in ? 'my_bookings.php' : 'login.php' ?>" style="
            text-decoration: none;
            color: <?= $current_page == 'my_bookings.php' ? '#ffb700' : '#b0b0b0' ?>;
            font-weight: <?= $current_page == 'my_bookings.php' ? '700' : '500' ?>;
            font-size: 13px;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            padding: 8px 14px;
            border-radius: 12px;
            background: <?= $current_page == 'my_bookings.php' ? 'rgba(255, 183, 0, 0.15)' : 'transparent' ?>;
        " onmouseover="this.style.background='rgba(255,183,0,0.1)'; this.style.color='#ffb700'; this.style.transform='translateY(-1px)'" onmouseout="this.style.background='<?= $current_page == 'my_bookings.php' ? 'rgba(255, 183, 0, 0.15)' : 'transparent' ?>'; this.style.color='<?= $current_page == 'my_bookings.php' ? '#ffb700' : '#b0b0b0' ?>'; this.style.transform='translateY(0)'">
            <i class="fas fa-ticket-alt"></i> My Bookings
        </a>
        
        <!-- Admin Panel -->
        <?php if ($is_admin_logged_in && !$is_conductor_logged_in): ?>
        <a href="admin_dashboard.php" style="
            text-decoration: none;
            color: <?= $current_page == 'admin_dashboard.php' ? '#ffb700' : '#b0b0b0' ?>;
            font-weight: <?= $current_page == 'admin_dashboard.php' ? '700' : '500' ?>;
            font-size: 13px;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            padding: 8px 14px;
            border-radius: 12px;
            background: <?= $current_page == 'admin_dashboard.php' ? 'rgba(255, 183, 0, 0.15)' : 'transparent' ?>;
        " onmouseover="this.style.background='rgba(255,183,0,0.1)'; this.style.color='#ffb700'; this.style.transform='translateY(-1px)'" onmouseout="this.style.background='<?= $current_page == 'admin_dashboard.php' ? 'rgba(255, 183, 0, 0.15)' : 'transparent' ?>'; this.style.color='<?= $current_page == 'admin_dashboard.php' ? '#ffb700' : '#b0b0b0' ?>'; this.style.transform='translateY(0)'">
            <i class="fas fa-user-shield"></i> Admin Panel
        </a>
        <?php endif; ?>
        
        <!-- Conductor Panel -->
        <?php if ($is_conductor_logged_in): ?>
        <a href="conductor_dashboard.php" style="
            text-decoration: none;
            color: <?= $current_page == 'conductor_dashboard.php' ? '#ffb700' : '#b0b0b0' ?>;
            font-weight: <?= $current_page == 'conductor_dashboard.php' ? '700' : '500' ?>;
            font-size: 13px;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            padding: 8px 14px;
            border-radius: 12px;
            background: <?= $current_page == 'conductor_dashboard.php' ? 'rgba(255, 183, 0, 0.15)' : 'transparent' ?>;
        " onmouseover="this.style.background='rgba(255,183,0,0.1)'; this.style.color='#ffb700'; this.style.transform='translateY(-1px)'" onmouseout="this.style.background='<?= $current_page == 'conductor_dashboard.php' ? 'rgba(255, 183, 0, 0.15)' : 'transparent' ?>'; this.style.color='<?= $current_page == 'conductor_dashboard.php' ? '#ffb700' : '#b0b0b0' ?>'; this.style.transform='translateY(0)'">
            <i class="fas fa-bus"></i> Conductor Panel
        </a>
        <?php endif; ?>
        
        <!-- Contact -->
        <a href="contact.php" style="
            text-decoration: none;
            color: <?= $current_page == 'contact.php' ? '#ffb700' : '#b0b0b0' ?>;
            font-weight: <?= $current_page == 'contact.php' ? '700' : '500' ?>;
            font-size: 13px;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            padding: 8px 14px;
            border-radius: 12px;
            background: <?= $current_page == 'contact.php' ? 'rgba(255, 183, 0, 0.15)' : 'transparent' ?>;
        " onmouseover="this.style.background='rgba(255,183,0,0.1)'; this.style.color='#ffb700'; this.style.transform='translateY(-1px)'" onmouseout="this.style.background='<?= $current_page == 'contact.php' ? 'rgba(255, 183, 0, 0.15)' : 'transparent' ?>'; this.style.color='<?= $current_page == 'contact.php' ? '#ffb700' : '#b0b0b0' ?>'; this.style.transform='translateY(0)'">
            <i class="fas fa-envelope"></i> Contact
        </a>
        
        <!-- ===== USER SECTION ===== -->
        <?php if ($is_user_logged_in || $is_admin_logged_in || $is_conductor_logged_in): ?>
            <div style="
                display: flex;
                align-items: center;
                gap: 10px;
                border-left: 1px solid rgba(255, 183, 0, 0.2);
                padding-left: 16px;
                margin-left: 8px;
            ">
                <!-- User Avatar -->
                <div style="
                    width: 36px;
                    height: 36px;
                    border-radius: 50%;
                    background: <?= $is_conductor_logged_in ? 'linear-gradient(135deg, #ffb700, #f5a623)' : ($is_admin_logged_in ? 'linear-gradient(135deg, #e63946, #c62828)' : 'linear-gradient(135deg, #ffb700, #f5a623)') ?>;
                    color: #0f0022;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    font-weight: 700;
                    font-size: 14px;
                    box-shadow: 0 4px 12px rgba(255, 183, 0, 0.3);
                ">
                    <?= strtoupper(substr($display_name, 0, 1)) ?>
                </div>
                
                <div style="display: flex; flex-direction: column; line-height: 1.2;">
                    <span style="font-size: 11px; color: #888;">
                        <?php if ($is_conductor_logged_in): ?>
                            <i class="fas fa-user-tie"></i> Conductor
                        <?php elseif ($is_admin_logged_in): ?>
                            <i class="fas fa-user-shield"></i> <?= $_SESSION['role'] === 'super_admin' ? 'Super Admin' : 'Bus Owner' ?>
                        <?php else: ?>
                            <i class="fas fa-user"></i> Welcome
                        <?php endif; ?>
                    </span>
                    <span style="font-size: 13px; color: #ffb700; font-weight: 600;">
                        <?= htmlspecialchars($display_name) ?>
                    </span>
                </div>
                
                <a href="logout.php" style="
                    background: linear-gradient(135deg, #e63946, #c62828);
                    color: white;
                    padding: 7px 14px;
                    border-radius: 10px;
                    text-decoration: none;
                    font-size: 12px;
                    font-weight: 600;
                    transition: all 0.3s;
                    display: flex;
                    align-items: center;
                    gap: 5px;
                    box-shadow: 0 4px 12px rgba(230, 57, 70, 0.3);
                " onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 8px 20px rgba(230, 57, 70, 0.4)'" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 4px 12px rgba(230, 57, 70, 0.3)'">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
            
        <?php else: ?>
            <!-- ===== LOGIN BUTTON - GOLD ===== -->
            <a href="login.php" style="
                background: linear-gradient(135deg, #ffb700, #f5a623);
                color: #0f0022;
                padding: 9px 22px;
                border-radius: 12px;
                text-decoration: none;
                font-size: 13px;
                font-weight: 700;
                transition: all 0.3s;
                box-shadow: 0 4px 15px rgba(255, 183, 0, 0.35);
                display: flex;
                align-items: center;
                gap: 6px;
            " onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 8px 25px rgba(255, 183, 0, 0.5)'" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 4px 15px rgba(255, 183, 0, 0.35)'">
                <i class="fas fa-sign-in-alt"></i> Login
            </a>
        <?php endif; ?>
    </div>
</nav>

<!-- ===== RESPONSIVE CSS ===== -->
<style>
    @media (max-width: 900px) {
        nav { padding: 10px 18px !important; flex-wrap: wrap; }
        #menuToggle { display: flex !important; align-items: center; justify-content: center; }
        #navLinks { 
            display: none !important; 
            flex-direction: column !important; 
            width: 100% !important; 
            gap: 6px !important; 
            padding: 12px 0 5px 0 !important; 
            border-top: 1px solid rgba(255, 183, 0, 0.15) !important; 
            margin-top: 10px !important; 
        }
        #navLinks.show { display: flex !important; }
        #navLinks > a { 
            width: 100% !important; 
            text-align: center !important; 
            padding: 10px !important; 
            justify-content: center !important; 
        }
        #navLinks > div { 
            width: 100% !important; 
            flex-direction: column !important; 
            border-left: none !important; 
            padding-left: 0 !important; 
            gap: 8px !important; 
            padding-top: 10px !important; 
            margin-left: 0 !important;
            border-top: 1px solid rgba(255, 183, 0, 0.15) !important; 
        }
        #navLinks > div a { width: 100% !important; text-align: center !important; justify-content: center !important; }
        nav > a:first-child { font-size: 14px !important; }
    }
    @media (max-width: 480px) {
        nav { padding: 10px 15px !important; top: 8px !important; width: calc(100% - 20px) !important; }
        nav > a:first-child span:first-of-type { display: none; }
        nav > a:first-child { font-size: 15px !important; }
        #navLinks > div { gap: 6px !important; }
        #navLinks > div a { font-size: 12px !important; padding: 8px 12px !important; }
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
                if (window.innerWidth <= 900) {
                    navLinks.classList.remove('show');
                    document.getElementById('menuToggle').innerHTML = '<i class="fas fa-bars"></i>';
                }
            });
        });
    });
</script>