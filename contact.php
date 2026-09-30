<?php
include 'config.php';
require_once 'tab_auth.php';

$message_status = "";
$name = $email = $message = "";
$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // ============ GET AND SANITIZE INPUT ============
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $message = trim($_POST['message']);
    
    // ============ VALIDATION ============
    if (empty($name)) {
        $error = "Please enter your name!";
    } elseif (empty($email)) {
        $error = "Please enter your email!";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address!";
    } elseif (empty($message)) {
        $error = "Please enter your message!";
    } elseif (strlen($message) < 10) {
        $error = "Message must be at least 10 characters long!";
    }
    
    // ============ INSERT INTO DATABASE ============
    if (empty($error)) {
        $sql = "INSERT INTO contact_messages (name, email, message, created_at) VALUES (?, ?, ?, NOW())";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sss", $name, $email, $message);
        
        if ($stmt->execute()) {
            $message_status = "success";
            $name = $email = $message = "";
        } else {
            $error = "Database error: Could not save message. Please try again later.";
        }
        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact Us - Matara Bus</title>
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
        
        /* ===== PAGE HEADER ===== */
        .page-header {
            text-align: center;
            padding: 30px 20px 10px;
        }
        
        .page-header h1 {
            font-size: 32px;
            font-weight: 700;
            color: #ffffff;
        }
        
        .page-header h1 i {
            color: #ffb700;
            margin-right: 10px;
        }
        
        .page-header p {
            color: #b0b0b0;
            font-size: 15px;
        }
        
        /* ===== CONTACT CONTAINER ===== */
        .contact-container { 
            max-width: 1100px; 
            margin: 20px auto; 
            display: grid; 
            grid-template-columns: 1fr 1.5fr; 
            gap: 40px; 
            padding: 0 20px; 
        }
        
        /* ===== INFO SECTION ===== */
        .info-section { 
            background: linear-gradient(135deg, #1a0033, #2d1b4e);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 183, 0, 0.2);
            color: white; 
            padding: 40px; 
            border-radius: 24px; 
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.4), 0 1px 2px rgba(255, 183, 0, 0.1) inset;
        }
        
        .info-section .info-icon {
            font-size: 50px;
            color: #ffb700;
            margin-bottom: 15px;
            text-shadow: 0 0 20px rgba(255, 183, 0, 0.5);
        }
        
        .info-section h2 {
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 5px;
            color: #ffffff;
        }
        
        .info-section .subtitle {
            font-size: 14px;
            opacity: 0.8;
            margin-bottom: 25px;
            color: #d0d0d0;
        }
        
        .info-item {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 12px 0;
            border-bottom: 1px solid rgba(255, 183, 0, 0.15);
        }
        
        .info-item:last-child { border-bottom: none; }
        
        .info-item i {
            font-size: 18px;
            color: #ffb700;
            width: 25px;
            text-align: center;
        }
        
        .info-item .info-text { font-size: 14px; color: #e0e0e0; }
        
        .info-item .info-text strong {
            display: block;
            font-size: 13px;
            opacity: 0.8;
            font-weight: 400;
            color: #b0b0b0;
        }
        
        /* ===== SOCIAL LINKS ===== */
        .social-links {
            display: flex;
            gap: 15px;
            margin-top: 25px;
            padding-top: 20px;
            border-top: 1px solid rgba(255, 183, 0, 0.15);
        }
        
        .social-links a {
            color: #ffb700;
            font-size: 20px;
            transition: all 0.3s;
            width: 45px;
            height: 45px;
            border-radius: 50%;
            background: rgba(255, 183, 0, 0.1);
            border: 1px solid rgba(255, 183, 0, 0.3);
            display: flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
        }
        
        .social-links a:hover {
            background: linear-gradient(135deg, #ffb700, #f5a623);
            color: #0f0022;
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(255, 183, 0, 0.4);
        }
        
        /* ===== FORM SECTION ===== */
        .form-section { 
            background: rgba(22, 22, 22, 0.85);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 183, 0, 0.15);
            padding: 40px; 
            border-radius: 24px; 
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.4);
        }
        
        .form-section h2 {
            font-size: 22px;
            font-weight: 700;
            color: #ffffff;
            margin-bottom: 5px;
        }
        
        .form-section .form-sub {
            color: #b0b0b0;
            font-size: 14px;
            margin-bottom: 25px;
        }
        
        .form-group { margin-bottom: 18px; }
        
        .form-group label {
            display: block;
            font-weight: 600;
            font-size: 13px;
            color: #c9c9c9;
            margin-bottom: 5px;
        }
        
        .form-group label i {
            color: #ffb700;
            margin-right: 6px;
        }
        
        .form-group label .required {
            color: #dc3545;
            margin-left: 3px;
        }
        
        .form-group input,
        .form-group textarea { 
            width: 100%; 
            padding: 14px 18px; 
            border: 2px solid #2a2a2a; 
            border-radius: 12px; 
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
            font-size: 14px;
            transition: all 0.3s ease;
            background: #0a0a0a;
            color: #e0e0e0;
            outline: none;
        }
        
        .form-group input:focus,
        .form-group textarea:focus {
            border-color: #ffb700;
            background: #0a0a0a;
            box-shadow: 0 0 0 4px rgba(255, 183, 0, 0.1);
        }
        
        .form-group input::placeholder,
        .form-group textarea::placeholder {
            color: #666;
        }
        
        .form-group textarea {
            resize: vertical;
            min-height: 120px;
        }
        
        .form-group .error-text {
            color: #ff6b6b;
            font-size: 12px;
            margin-top: 4px;
            display: none;
        }
        
        .form-group.error input,
        .form-group.error textarea { border-color: #dc3545; }
        
        .form-group.error .error-text { display: block; }
        
        /* ===== SUBMIT BUTTON ===== */
        .btn-submit { 
            background: linear-gradient(135deg, #ffb700, #f5a623);
            color: #0f0022; 
            padding: 16px; 
            border: none; 
            border-radius: 12px; 
            font-weight: 700; 
            cursor: pointer; 
            width: 100%; 
            transition: all 0.3s;
            font-family: 'Poppins', sans-serif;
            font-size: 16px;
            text-transform: uppercase;
            letter-spacing: 1px;
            box-shadow: 0 4px 15px rgba(255, 183, 0, 0.3);
        }
        
        .btn-submit:hover { 
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(255, 183, 0, 0.5);
        }
        
        .btn-submit i { margin-right: 8px; }
        
        /* ===== ALERTS ===== */
        .alert {
            padding: 15px 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            animation: slideDown 0.5s ease;
        }
        
        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .alert-success {
            background: rgba(40, 167, 69, 0.15);
            color: #4ade80;
            border-left: 4px solid #28a745;
            border: 1px solid rgba(40, 167, 69, 0.3);
        }
        
        .alert-success i { font-size: 20px; color: #28a745; }
        
        .alert-danger {
            background: rgba(220, 53, 69, 0.15);
            color: #ff6b6b;
            border-left: 4px solid #dc3545;
            border: 1px solid rgba(220, 53, 69, 0.3);
        }
        
        .alert-danger i { font-size: 20px; color: #dc3545; }
        
        /* ===== MAP SECTION ===== */
        .map-section {
            grid-column: 1 / -1;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.4);
            border: 1px solid rgba(255, 183, 0, 0.15);
        }
        
        .map-section iframe {
            width: 100%;
            height: 300px;
            border: none;
            filter: invert(90%) hue-rotate(180deg);
        }
        
        /* ===== RESPONSIVE ===== */
        @media (max-width: 768px) { 
            .contact-container { 
                grid-template-columns: 1fr; 
                gap: 25px;
            }
            .info-section { order: 2; }
            .form-section { order: 1; }
            .map-section { order: 3; }
            .page-header h1 { font-size: 26px; }
        }
        
        @media (max-width: 480px) {
            .info-section, .form-section { padding: 25px 20px; }
            .social-links { justify-content: center; }
        }
    </style>
</head>
<body>

<?php include 'header.php'; ?>

<div class="page-header">
    <h1><i class="fas fa-envelope"></i> Contact Us</h1>
    <p>We'd love to hear from you. Drop us a message and we'll respond as soon as possible.</p>
</div>

<div class="contact-container">
    
    <!-- ===== INFO SECTION ===== -->
    <div class="info-section">
        <div class="info-icon">
            <i class="fas fa-bus"></i>
        </div>
        <h2>Matara Bus Station</h2>
        <p class="subtitle">Your trusted bus service provider</p>
        
        <div class="info-item">
            <i class="fas fa-map-marker-alt"></i>
            <div class="info-text">
                Main Bus Stand, Matara
                <strong>Sri Lanka</strong>
            </div>
        </div>
        
        <div class="info-item">
            <i class="fas fa-phone"></i>
            <div class="info-text">
                +94 41 222 3344
                <strong>Monday - Sunday, 8AM - 8PM</strong>
            </div>
        </div>
        
        <div class="info-item">
            <i class="fas fa-envelope"></i>
            <div class="info-text">
                support@matarabus.lk
                <strong>Email us anytime</strong>
            </div>
        </div>
        
        <div class="info-item">
            <i class="fas fa-clock"></i>
            <div class="info-text">
                24/7 Online Booking
                <strong>Book your seats anytime</strong>
            </div>
        </div>
        
        <div class="social-links">
            <a href="#" aria-label="Facebook"><i class="fab fa-facebook-f"></i></a>
            <a href="#" aria-label="Twitter"><i class="fab fa-twitter"></i></a>
            <a href="#" aria-label="Instagram"><i class="fab fa-instagram"></i></a>
            <a href="#" aria-label="YouTube"><i class="fab fa-youtube"></i></a>
            <a href="#" aria-label="WhatsApp"><i class="fab fa-whatsapp"></i></a>
        </div>
    </div>
    
    <!-- ===== FORM SECTION ===== -->
    <div class="form-section">
        <h2><i class="fas fa-paper-plane" style="color:#ffb700;"></i> Send Message</h2>
        <p class="form-sub">Fill in the form below and we'll get back to you.</p>
        
        <?php if ($message_status == "success"): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <div>
                    <strong>Message Sent!</strong>
                    <p style="margin:0; font-size:13px; font-weight:400;">Thank you for contacting us. We will respond within 24 hours.</p>
                </div>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i>
                <div>
                    <strong>Error!</strong>
                    <p style="margin:0; font-size:13px; font-weight:400;"><?= htmlspecialchars($error) ?></p>
                </div>
            </div>
        <?php endif; ?>
        
        <form action="contact.php" method="POST" id="contactForm" autocomplete="off">
            
            <!-- ===== FULL NAME ===== -->
            <div class="form-group <?= (!empty($error) && empty($name)) ? 'error' : '' ?>">
                <label><i class="fas fa-user"></i> Full Name <span class="required">*</span></label>
                <input type="text" 
                       name="name" 
                       placeholder="Enter your full name" 
                       value="<?= htmlspecialchars($name) ?>" 
                       autocomplete="off" 
                       autocorrect="off" 
                       autocapitalize="off" 
                       spellcheck="false" 
                       readonly onfocus="this.removeAttribute('readonly');" 
                       required>
                <div class="error-text">Please enter your name</div>
            </div>
            
            <!-- ===== EMAIL ===== -->
            <div class="form-group <?= (!empty($error) && empty($email)) ? 'error' : '' ?>">
                <label><i class="fas fa-envelope"></i> Email Address <span class="required">*</span></label>
                <input type="text" 
                       name="email" 
                       placeholder="Enter your email address" 
                       value="<?= htmlspecialchars($email) ?>" 
                       autocomplete="off" 
                       autocorrect="off" 
                       autocapitalize="off" 
                       spellcheck="false" 
                       inputmode="email" 
                       readonly onfocus="this.removeAttribute('readonly');" 
                       required>
                <div class="error-text">Please enter a valid email</div>
            </div>
            
            <!-- ===== MESSAGE ===== -->
            <div class="form-group <?= (!empty($error) && empty($message)) ? 'error' : '' ?>">
                <label><i class="fas fa-comment"></i> Message <span class="required">*</span></label>
                <textarea name="message" 
                          placeholder="Write your message here..." 
                          autocomplete="off" 
                          autocorrect="off" 
                          autocapitalize="off" 
                          spellcheck="false" 
                          readonly onfocus="this.removeAttribute('readonly');" 
                          required><?= htmlspecialchars($message) ?></textarea>
                <div class="error-text">Please enter your message (min 10 characters)</div>
            </div>
            
            <button type="submit" class="btn-submit">
                <i class="fas fa-paper-plane"></i> SEND MESSAGE
            </button>
        </form>
    </div>
    
    <!-- ===== MAP SECTION ===== -->
    <div class="map-section">
        <iframe 
            src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d126997.2957804275!2d80.48798424863281!3d5.944409000000006!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x3ae13bd5005c18cf%3A0x8a482dba5670f7f6!2sMatara%2C%20Sri%20Lanka!5e0!3m2!1sen!2sus!4v1700000000000" 
            allowfullscreen="" 
            loading="lazy" 
            referrerpolicy="no-referrer-when-downgrade">
        </iframe>
    </div>
    
</div>

<script>
// ============ CLIENT SIDE VALIDATION ============
document.getElementById('contactForm').addEventListener('submit', function(e) {
    const name = document.querySelector('input[name="name"]');
    const email = document.querySelector('input[name="email"]');
    const message = document.querySelector('textarea[name="message"]');
    let hasError = false;
    
    document.querySelectorAll('.form-group').forEach(el => el.classList.remove('error'));
    
    if (name.value.trim() === '') {
        name.closest('.form-group').classList.add('error');
        hasError = true;
    }
    
    const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (email.value.trim() === '' || !emailPattern.test(email.value)) {
        email.closest('.form-group').classList.add('error');
        hasError = true;
    }
    
    if (message.value.trim() === '' || message.value.trim().length < 10) {
        message.closest('.form-group').classList.add('error');
        hasError = true;
    }
    
    if (hasError) {
        e.preventDefault();
        const firstError = document.querySelector('.form-group.error');
        if (firstError) {
            firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
            firstError.querySelector('input, textarea').focus();
        }
    }
});

// ============ REAL TIME VALIDATION ============
document.querySelectorAll('.form-group input, .form-group textarea').forEach(input => {
    input.addEventListener('blur', function() {
        const group = this.closest('.form-group');
        if (this.value.trim() === '') {
            group.classList.add('error');
        } else {
            group.classList.remove('error');
        }
    });
    
    input.addEventListener('input', function() {
        const group = this.closest('.form-group');
        if (this.value.trim() !== '') {
            group.classList.remove('error');
        }
    });
});
</script>

</body>
</html>