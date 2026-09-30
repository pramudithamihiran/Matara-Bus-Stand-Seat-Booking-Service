<?php
include 'config.php';
require_once 'tab_auth.php';

requireTabAuth('admin_login.php');

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true || $_SESSION['role'] !== 'super_admin') {
    header("Location: admin_login.php");
    exit();
}

$error = '';
$success = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $bus_name      = trim($_POST['bus_name']);
    $bus_number    = trim($_POST['bus_no']);
    $departure_time= trim($_POST['time']);
    $route_category= trim($_POST['route']);
    $owner_username= trim($_POST['owner_username']);
    $owner_password= trim($_POST['owner_password']);
    $owner_email   = trim($_POST['owner_email']);
    $contact_no    = trim($_POST['contact_no']);
    $start_location= trim($_POST['start_loc']);
    $end_location  = trim($_POST['end_loc']);
    $seat_capacity = intval($_POST['seat_capacity']);
    
    $conductor_username = trim($_POST['conductor_username']);
    $conductor_password = trim($_POST['conductor_password']);
    $conductor_name     = trim($_POST['conductor_name']);
    
    if (empty($bus_name) || empty($bus_number) || empty($owner_username) || empty($owner_password)) {
        $error = "Please fill all required fields!";
    } elseif (empty($conductor_username) || empty($conductor_password) || empty($conductor_name)) {
        $error = "Please fill all conductor fields!";
    } elseif (!filter_var($owner_email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address!";
    } elseif (strlen($owner_password) < 6) {
        $error = "Owner password must be at least 6 characters long!";
    } elseif (strlen($conductor_password) < 6) {
        $error = "Conductor password must be at least 6 characters long!";
    } else {
        $check_sql = "SELECT id FROM buses WHERE bus_number = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("s", $bus_number);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            $error = "This Bus Number already exists!";
        } else {
            $check_con_sql = "SELECT id FROM buses WHERE conductor_username = ?";
            $check_con_stmt = $conn->prepare($check_con_sql);
            $check_con_stmt->bind_param("s", $conductor_username);
            $check_con_stmt->execute();
            $check_con_result = $check_con_stmt->get_result();
            
            if ($check_con_result->num_rows > 0) {
                $error = "Conductor username already exists!";
            } else {
                $hashed_owner_password = password_hash($owner_password, PASSWORD_DEFAULT);
                $hashed_conductor_password = password_hash($conductor_password, PASSWORD_DEFAULT);
                
                $sql = "INSERT INTO buses (bus_name, bus_number, owner_username, owner_password, owner_email, contact_no, route_category, start_location, end_location, departure_time, seat_capacity, conductor_username, conductor_password, conductor_name, status) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active')";
                
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("ssssssssssisss", 
                    $bus_name, 
                    $bus_number, 
                    $owner_username, 
                    $hashed_owner_password, 
                    $owner_email, 
                    $contact_no, 
                    $route_category, 
                    $start_location, 
                    $end_location, 
                    $departure_time, 
                    $seat_capacity, 
                    $conductor_username,
                    $hashed_conductor_password,
                    $conductor_name
                );
                
                if ($stmt->execute()) {
                    $bus_id = $stmt->insert_id;
                    $success = "Bus added successfully! Bus ID: " . $bus_id;
                    echo "<script>
                            setTimeout(function() {
                                window.location='admin_dashboard.php';
                            }, 2000);
                          </script>";
                } else {
                    $error = "Database error: " . $conn->error;
                }
                $stmt->close();
            }
            $check_con_stmt->close();
        }
        $check_stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add New Bus - Admin Panel</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body { 
            font-family: 'Poppins', sans-serif; 
            background: linear-gradient(135deg, #0f0022 0%, #1a0033 50%, #0a0018 100%) !important;
            background-attachment: fixed !important;
            min-height: 100vh;
            padding: 20px;
            padding-top: 90px;
            padding-bottom: 40px;
            color: #e0e0e0;
        }
        
        .page-wrapper {
            max-width: 650px;
            margin: 0 auto 40px;
            padding: 0 15px;
        }
        
        .form-container {
            background: rgba(22, 22, 22, 0.85);
            backdrop-filter: blur(20px);
            padding: 40px;
            border-radius: 24px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.5);
            border: 1px solid rgba(255, 183, 0, 0.15);
        }
        
        .form-header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .form-header h2 {
            color: #ffffff;
            font-weight: 700;
            font-size: 22px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .form-header h2 i {
            color: #ffb700;
            margin-right: 10px;
        }
        
        .form-header p {
            color: #b0b0b0;
            font-size: 13px;
            margin-top: 5px;
        }
        
        /* ===== ALERTS ===== */
        .alert {
            padding: 14px 18px;
            border-radius: 12px;
            margin-bottom: 20px;
            font-weight: 500;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .alert-danger {
            background: rgba(220, 53, 69, 0.15);
            color: #ff6b6b;
            border-left: 4px solid #dc3545;
            border: 1px solid rgba(220, 53, 69, 0.3);
        }
        
        .alert-success {
            background: rgba(40, 167, 69, 0.15);
            color: #4ade80;
            border-left: 4px solid #28a745;
            border: 1px solid rgba(40, 167, 69, 0.3);
        }
        
        /* ===== FORM LABELS ===== */
        label {
            display: block;
            margin-top: 18px;
            font-weight: 600;
            font-size: 12px;
            color: #c9c9c9;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }
        
        label i {
            color: #ffb700;
            margin-right: 6px;
        }
        
        label .required {
            color: #ff6b6b;
            margin-left: 3px;
        }
        
        /* ===== INPUTS ===== */
        input, select {
            width: 100%;
            padding: 12px 16px;
            margin-top: 6px;
            border: 2px solid #2a2a2a;
            border-radius: 12px;
            font-family: 'Poppins', sans-serif;
            font-size: 14px;
            outline: none;
            transition: all 0.3s ease;
            background: #0a0a0a;
            color: #e0e0e0;
            box-sizing: border-box;
            color-scheme: dark;
        }
        
        input:focus, select:focus {
            border-color: #ffb700;
            background: #0a0a0a;
            box-shadow: 0 0 0 4px rgba(255, 183, 0, 0.1);
        }
        
        input::placeholder {
            color: #666;
            font-size: 13px;
        }
        
        select option {
            background: #161616;
            color: #e0e0e0;
        }
        
        /* ===== ROW LAYOUT ===== */
        .row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }
        
        /* ===== SECTION DIVIDER ===== */
        .section-divider {
            border-top: 2px dashed rgba(255, 183, 0, 0.2);
            margin: 28px 0 18px 0;
            padding-top: 18px;
            color: #ffb700;
            font-weight: 700;
            font-size: 13px;
            display: flex;
            align-items: center;
            gap: 10px;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }
        
        .section-divider i {
            color: #ffb700;
        }
        
        /* ===== SUBMIT BUTTON ===== */
        .btn {
            background: linear-gradient(135deg, #ffb700, #f5a623);
            color: #0f0022;
            border: none;
            padding: 16px;
            width: 100%;
            border-radius: 14px;
            margin-top: 28px;
            cursor: pointer;
            font-weight: 700;
            font-size: 15px;
            text-transform: uppercase;
            letter-spacing: 1px;
            transition: all 0.3s ease;
            font-family: 'Poppins', sans-serif;
            box-shadow: 0 4px 15px rgba(255, 183, 0, 0.3);
        }
        
        .btn:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(255, 183, 0, 0.5);
        }
        
        .btn:disabled {
            opacity: 0.7;
            cursor: not-allowed;
            transform: none !important;
        }
        
        .btn i {
            margin-right: 8px;
        }
        
        /* ===== BACK LINK ===== */
        .back-link {
            display: block;
            text-align: center;
            margin-top: 18px;
            color: #b0b0b0;
            text-decoration: none;
            font-size: 13px;
            font-weight: 500;
            transition: 0.2s;
        }
        
        .back-link:hover {
            color: #ffb700;
            text-decoration: underline;
        }
        
        /* ===== PASSWORD STRENGTH ===== */
        .password-strength {
            height: 4px;
            border-radius: 4px;
            margin-top: 8px;
            background: rgba(255, 255, 255, 0.1);
            transition: all 0.3s ease;
            overflow: hidden;
        }
        
        .password-strength-bar {
            height: 100%;
            width: 0%;
            border-radius: 4px;
            transition: all 0.3s ease;
        }
        
        /* ===== RESPONSIVE ===== */
        @media (max-width: 576px) {
            .row {
                grid-template-columns: 1fr;
                gap: 0;
            }
            .form-container {
                padding: 25px 20px;
            }
            .page-wrapper {
                margin-top: 80px;
            }
            .form-header h2 {
                font-size: 18px;
            }
        }
    </style>
</head>
<body>

<?php include 'header.php'; ?>

<div class="page-wrapper">
    <div class="form-container">
        
        <div class="form-header">
            <h2><i class="fas fa-plus-circle"></i> Add New Bus</h2>
            <p>Fill in the details to add a bus, owner and conductor account</p>
        </div>
        
        <?php if ($error): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" action="add_bus.php" id="addBusForm">
            
            <!-- ===== BUS DETAILS ===== -->
            <label><i class="fas fa-bus"></i> Bus Name / Service <span class="required">*</span></label>
            <input type="text" name="bus_name" required placeholder="e.g. KEILY SUPER COACH">
            
            <div class="row">
                <div>
                    <label><i class="fas fa-id-card"></i> Registration No <span class="required">*</span></label>
                    <input type="text" name="bus_no" required placeholder="e.g. NB-7048">
                </div>
                <div>
                    <label><i class="fas fa-clock"></i> Departure Time <span class="required">*</span></label>
                    <input type="time" name="time" required>
                </div>
            </div>
            
            <div class="row">
                <div>
                    <label><i class="fas fa-route"></i> Start From <span class="required">*</span></label>
                    <input type="text" name="start_loc" required placeholder="e.g. Matara">
                </div>
                <div>
                    <label><i class="fas fa-flag-checkered"></i> End At <span class="required">*</span></label>
                    <input type="text" name="end_loc" required placeholder="e.g. Tangalle">
                </div>
            </div>
            
            <label><i class="fas fa-road"></i> Route Category <span class="required">*</span></label>
            <select name="route" required>
                <option value="">-- Choose a Road --</option>
                <option value="Kataragama">Kataragama Road</option>
                <option value="Hakmana">Hakmana Road</option>
                <option value="Deniyaya">Deniyaya Road</option>
                <option value="Colombo">Colombo Road</option>
            </select>
            
            <label><i class="fas fa-chair"></i> Seat Capacity <span class="required">*</span></label>
            <input type="number" name="seat_capacity" required min="10" max="70" value="54">
            
            <!-- ===== OWNER DETAILS ===== -->
            <div class="section-divider">
                <i class="fas fa-user-shield"></i> Create Owner Account
            </div>
            
            <div class="row">
                <div>
                    <label><i class="fas fa-user"></i> Owner Username <span class="required">*</span></label>
                    <input type="text" name="owner_username" required placeholder="e.g. hiran_owner">
                </div>
                <div>
                    <label><i class="fas fa-lock"></i> Owner Password <span class="required">*</span></label>
                    <input type="password" name="owner_password" id="owner_password" required placeholder="Min 6 characters">
                    <div class="password-strength">
                        <div class="password-strength-bar" id="ownerStrengthBar"></div>
                    </div>
                </div>
            </div>
            
            <label><i class="fas fa-envelope"></i> Owner Email <span class="required">*</span></label>
            <input type="email" name="owner_email" required placeholder="owner@gmail.com">
            
            <label><i class="fas fa-phone"></i> Contact Number <span class="required">*</span></label>
            <input type="text" name="contact_no" required placeholder="0714575896">
            
            <!-- ===== CONDUCTOR DETAILS ===== -->
            <div class="section-divider">
                <i class="fas fa-user-tie"></i> Create Conductor Account
            </div>
            
            <div class="row">
                <div>
                    <label><i class="fas fa-user"></i> Conductor Username <span class="required">*</span></label>
                    <input type="text" name="conductor_username" required placeholder="e.g. conductor1">
                </div>
                <div>
                    <label><i class="fas fa-lock"></i> Conductor Password <span class="required">*</span></label>
                    <input type="password" name="conductor_password" id="conductor_password" required placeholder="Min 6 characters">
                    <div class="password-strength">
                        <div class="password-strength-bar" id="conductorStrengthBar"></div>
                    </div>
                </div>
            </div>
            
            <label><i class="fas fa-user-tie"></i> Conductor Full Name <span class="required">*</span></label>
            <input type="text" name="conductor_name" required placeholder="e.g. S. Perera">
            
            <button type="submit" class="btn" id="submitBtn">
                <span id="btnText"><i class="fas fa-plus"></i> ADD BUS TO SYSTEM</span>
                <span id="btnSpinner" style="display:none;">
                    <i class="fas fa-spinner fa-spin"></i> Processing...
                </span>
            </button>
            
            <a href="admin_dashboard.php" class="back-link">
                <i class="fas fa-arrow-left"></i> Cancel and Go Back
            </a>
        </form>
    </div>
</div>

<script>
// Password Strength Meter for Owner
document.getElementById('owner_password').addEventListener('input', function() {
    const password = this.value;
    const bar = document.getElementById('ownerStrengthBar');
    let strength = 0;
    
    if (password.length >= 6) strength += 20;
    if (password.length >= 8) strength += 20;
    if (password.match(/[a-z]+/)) strength += 20;
    if (password.match(/[A-Z]+/)) strength += 20;
    if (password.match(/[0-9]+/)) strength += 20;
    
    bar.style.width = strength + '%';
    if (strength < 40) bar.style.background = '#e74c3c';
    else if (strength < 70) bar.style.background = '#f39c12';
    else bar.style.background = '#4ade80';
});

// Password Strength Meter for Conductor
document.getElementById('conductor_password').addEventListener('input', function() {
    const password = this.value;
    const bar = document.getElementById('conductorStrengthBar');
    let strength = 0;
    
    if (password.length >= 6) strength += 20;
    if (password.length >= 8) strength += 20;
    if (password.match(/[a-z]+/)) strength += 20;
    if (password.match(/[A-Z]+/)) strength += 20;
    if (password.match(/[0-9]+/)) strength += 20;
    
    bar.style.width = strength + '%';
    if (strength < 40) bar.style.background = '#e74c3c';
    else if (strength < 70) bar.style.background = '#f39c12';
    else bar.style.background = '#4ade80';
});

// Form Submit Loading
document.getElementById('addBusForm').addEventListener('submit', function() {
    document.getElementById('btnText').style.display = 'none';
    document.getElementById('btnSpinner').style.display = 'inline';
    document.getElementById('submitBtn').disabled = true;
});
</script>

</body>
</html>