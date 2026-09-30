<?php
include 'config.php';
require_once 'tab_auth.php';

requireTabAuth('admin_login.php');

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true || $_SESSION['role'] !== 'super_admin') {
    header("Location: admin_login.php");
    exit();
}

if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: admin_dashboard.php");
    exit();
}

$bus_id = intval($_GET['id']);

$sql = "SELECT * FROM buses WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $bus_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result && $result->num_rows > 0) {
    $bus = $result->fetch_assoc();
} else {
    echo "<script>alert('Bus not found!'); window.location='admin_dashboard.php';</script>";
    exit();
}
$stmt->close();

$success = '';
$error = '';

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_bus'])) {
    
    $bus_name = trim($_POST['bus_name']);
    $bus_number = trim($_POST['bus_number']);
    $start_location = trim($_POST['start_location']);
    $end_location = trim($_POST['end_location']);
    $route_category = trim($_POST['route_category']);
    $owner_username = trim($_POST['owner_username']);
    $owner_password = trim($_POST['owner_password']);
    $contact_no = trim($_POST['contact_no']);
    $status = trim($_POST['status']);
    $seat_capacity = intval($_POST['seat_capacity']);
    
    $conductor_username = trim($_POST['conductor_username']);
    $conductor_password = trim($_POST['conductor_password']);
    $conductor_name = trim($_POST['conductor_name']);
    
    if (empty($bus_name) || empty($bus_number) || empty($start_location) || empty($end_location)) {
        $error = "Please fill all required fields!";
    } elseif (empty($owner_username)) {
        $error = "Owner username is required!";
    } elseif (empty($contact_no)) {
        $error = "Contact number is required!";
    } elseif (empty($conductor_username) || empty($conductor_name)) {
        $error = "Conductor username and name are required!";
    } else {
        if (!empty($owner_password)) {
            $hashed_owner_password = password_hash($owner_password, PASSWORD_DEFAULT);
        } else {
            $hashed_owner_password = $bus['owner_password'];
        }
        
        if (!empty($conductor_password)) {
            $hashed_conductor_password = password_hash($conductor_password, PASSWORD_DEFAULT);
        } else {
            $hashed_conductor_password = $bus['conductor_password'];
        }
        
        $update_query = "UPDATE buses SET 
                         bus_name = ?, 
                         bus_number = ?, 
                         start_location = ?, 
                         end_location = ?, 
                         route_category = ?,
                         owner_username = ?, 
                         owner_password = ?,
                         contact_no = ?,
                         status = ?,
                         seat_capacity = ?,
                         conductor_username = ?,
                         conductor_password = ?,
                         conductor_name = ?
                         WHERE id = ?";
        
        $stmt = $conn->prepare($update_query);
        $stmt->bind_param("sssssssssisssi", 
            $bus_name, 
            $bus_number, 
            $start_location, 
            $end_location, 
            $route_category,
            $owner_username, 
            $hashed_owner_password,
            $contact_no,
            $status,
            $seat_capacity,
            $conductor_username,
            $hashed_conductor_password,
            $conductor_name,
            $bus_id
        );
        
        if ($stmt->execute()) {
            $success = "Bus updated successfully!";
            $stmt2 = $conn->prepare("SELECT * FROM buses WHERE id = ?");
            $stmt2->bind_param("i", $bus_id);
            $stmt2->execute();
            $result2 = $stmt2->get_result();
            $bus = $result2->fetch_assoc();
            $stmt2->close();
        } else {
            $error = "Error updating record: " . $conn->error;
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
    <title>Edit Bus - Matara Bus</title>
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
        
        .main-wrapper {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: calc(100vh - 100px);
            padding: 20px;
        }
        
        .form-container {
            background: rgba(22, 22, 22, 0.85);
            backdrop-filter: blur(20px);
            padding: 40px;
            border-radius: 24px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.5);
            border: 1px solid rgba(255, 183, 0, 0.15);
            width: 100%;
            max-width: 650px;
        }
        
        .form-container .icon-header {
            text-align: center;
            font-size: 50px;
            color: #ffb700;
            margin-bottom: 10px;
            filter: drop-shadow(0 0 20px rgba(255, 183, 0, 0.4));
        }
        
        .form-container h2 {
            color: #ffffff;
            font-size: 22px;
            font-weight: 700;
            text-align: center;
            margin-bottom: 5px;
            letter-spacing: 0.5px;
        }
        
        .form-container .subtitle {
            text-align: center;
            color: #b0b0b0;
            font-size: 14px;
            margin-bottom: 20px;
        }
        
        /* ===== BUS ID DISPLAY ===== */
        .bus-id-display {
            background: rgba(255, 183, 0, 0.1);
            border: 1px solid rgba(255, 183, 0, 0.3);
            padding: 10px 18px;
            border-radius: 10px;
            font-size: 13px;
            color: #ffb700;
            text-align: center;
            margin-bottom: 20px;
            font-weight: 500;
        }
        
        .bus-id-display i {
            margin-right: 6px;
        }
        
        .bus-id-display strong {
            font-weight: 700;
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
        
        .alert-success {
            background: rgba(40, 167, 69, 0.15);
            color: #4ade80;
            border-left: 4px solid #28a745;
            border: 1px solid rgba(40, 167, 69, 0.3);
        }
        
        .alert-danger {
            background: rgba(220, 53, 69, 0.15);
            color: #ff6b6b;
            border-left: 4px solid #dc3545;
            border: 1px solid rgba(220, 53, 69, 0.3);
        }
        
        .alert i {
            font-size: 18px;
        }
        
        /* ===== LABELS ===== */
        label {
            display: block;
            margin-top: 16px;
            font-weight: 600;
            color: #c9c9c9;
            font-size: 12px;
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
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
            font-size: 14px;
            outline: none;
            transition: all 0.3s ease;
            background: #0a0a0a;
            color: #e0e0e0;
            color-scheme: dark;
        }
        
        input:focus, select:focus {
            border-color: #ffb700;
            background: #0a0a0a;
            box-shadow: 0 0 0 4px rgba(255, 183, 0, 0.1);
        }
        
        input::placeholder {
            color: #666;
        }
        
        select option {
            background: #161616;
            color: #e0e0e0;
        }
        
        /* ===== ROW ===== */
        .row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }
        
        /* ===== SECTION DIVIDER ===== */
        .section-divider {
            border-top: 2px dashed rgba(255, 183, 0, 0.2);
            margin: 25px 0 15px 0;
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
        .btn-submit {
            width: 100%;
            padding: 16px;
            border: none;
            border-radius: 14px;
            cursor: pointer;
            font-weight: 700;
            background: linear-gradient(135deg, #ffb700, #f5a623);
            color: #0f0022;
            margin-top: 25px;
            font-size: 15px;
            text-transform: uppercase;
            letter-spacing: 1px;
            transition: all 0.3s ease;
            font-family: 'Poppins', sans-serif;
            box-shadow: 0 4px 15px rgba(255, 183, 0, 0.3);
        }
        
        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(255, 183, 0, 0.5);
        }
        
        .btn-submit i {
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
        
        .back-link i {
            margin-right: 5px;
        }
        
        /* ===== RESPONSIVE ===== */
        @media (max-width: 600px) {
            .form-container {
                padding: 25px 20px;
            }
            .row {
                grid-template-columns: 1fr;
                gap: 0;
            }
        }
    </style>
</head>
<body>

    <?php include 'header.php'; ?>

    <div class="main-wrapper">
        <div class="form-container">
            
            <div class="icon-header">
                <i class="fas fa-edit"></i>
            </div>
            <h2>Edit Bus Details</h2>
            <p class="subtitle">Update bus, owner and conductor information</p>
            
            <div class="bus-id-display">
                <i class="fas fa-bus"></i> Editing Bus ID: <strong>#<?= $bus['id'] ?></strong>
            </div>
            
            <?php if (!empty($success)): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="">
                
                <!-- ===== BUS DETAILS ===== -->
                <label><i class="fas fa-bus"></i> Bus / Service Name <span class="required">*</span></label>
                <input type="text" name="bus_name" value="<?= htmlspecialchars($bus['bus_name'] ?? '') ?>" required>
                
                <label><i class="fas fa-id-card"></i> Bus Number <span class="required">*</span></label>
                <input type="text" name="bus_number" value="<?= htmlspecialchars($bus['bus_number'] ?? '') ?>" required>
                
                <div class="row">
                    <div>
                        <label><i class="fas fa-map-marker-alt"></i> Start From <span class="required">*</span></label>
                        <input type="text" name="start_location" value="<?= htmlspecialchars($bus['start_location'] ?? '') ?>" required>
                    </div>
                    <div>
                        <label><i class="fas fa-flag-checkered"></i> End At <span class="required">*</span></label>
                        <input type="text" name="end_location" value="<?= htmlspecialchars($bus['end_location'] ?? '') ?>" required>
                    </div>
                </div>
                
                <label><i class="fas fa-road"></i> Route Category <span class="required">*</span></label>
                <select name="route_category" required>
                    <option value="Kataragama" <?= ($bus['route_category'] == 'Kataragama') ? 'selected' : '' ?>>Kataragama Road</option>
                    <option value="Hakmana" <?= ($bus['route_category'] == 'Hakmana') ? 'selected' : '' ?>>Hakmana Road</option>
                    <option value="Deniyaya" <?= ($bus['route_category'] == 'Deniyaya') ? 'selected' : '' ?>>Deniyaya Road</option>
                    <option value="Colombo" <?= ($bus['route_category'] == 'Colombo') ? 'selected' : '' ?>>Colombo Road</option>
                </select>
                
                <div class="row">
                    <div>
                        <label><i class="fas fa-chair"></i> Seat Capacity</label>
                        <input type="number" name="seat_capacity" value="<?= htmlspecialchars($bus['seat_capacity'] ?? 45) ?>" min="10" max="70">
                    </div>
                    <div>
                        <label><i class="fas fa-info-circle"></i> Status</label>
                        <select name="status">
                            <option value="active" <?= ($bus['status'] == 'active') ? 'selected' : '' ?>>Active</option>
                            <option value="blocked" <?= ($bus['status'] == 'blocked') ? 'selected' : '' ?>>Blocked</option>
                        </select>
                    </div>
                </div>
                
                <!-- ===== OWNER DETAILS ===== -->
                <div class="section-divider">
                    <i class="fas fa-user-shield"></i> Owner Account Details
                </div>
                
                <label><i class="fas fa-user"></i> Owner Username <span class="required">*</span></label>
                <input type="text" name="owner_username" value="<?= htmlspecialchars($bus['owner_username'] ?? '') ?>" required>
                
                <label><i class="fas fa-lock"></i> Owner Password <span style="font-size:11px; color:#888; font-weight:400; text-transform:none;">(Leave blank to keep current)</span></label>
                <input type="password" name="owner_password" placeholder="Enter new password to change">
                
                <label><i class="fas fa-phone"></i> Contact Number <span class="required">*</span></label>
                <input type="text" name="contact_no" value="<?= htmlspecialchars($bus['contact_no'] ?? '') ?>" required>
                
                <!-- ===== CONDUCTOR DETAILS ===== -->
                <div class="section-divider">
                    <i class="fas fa-user-tie"></i> Conductor Account Details
                </div>
                
                <label><i class="fas fa-user"></i> Conductor Username <span class="required">*</span></label>
                <input type="text" name="conductor_username" value="<?= htmlspecialchars($bus['conductor_username'] ?? '') ?>" required>
                
                <label><i class="fas fa-lock"></i> Conductor Password <span style="font-size:11px; color:#888; font-weight:400; text-transform:none;">(Leave blank to keep current)</span></label>
                <input type="password" name="conductor_password" placeholder="Enter new password to change">
                
                <label><i class="fas fa-user-tie"></i> Conductor Full Name <span class="required">*</span></label>
                <input type="text" name="conductor_name" value="<?= htmlspecialchars($bus['conductor_name'] ?? '') ?>" required>
                
                <button type="submit" name="update_bus" class="btn-submit">
                    <i class="fas fa-save"></i> SAVE CHANGES
                </button>
                
                <a href="admin_dashboard.php" class="back-link">
                    <i class="fas fa-arrow-left"></i> Cancel and Go Back
                </a>
            </form>
        </div>
    </div>

</body>
</html>