<?php
include 'config.php';
require_once 'tab_auth.php';

// Admin/Owner check
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: admin_login.php");
    exit();
}

$owner_bus_id = $_SESSION['owner_bus_id'] ?? 0;
$is_super_admin = ($_SESSION['role'] === 'super_admin');
$success_msg = '';
$error_msg = '';

// ============ ADD UNAVAILABLE DATE(S) ============
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_date'])) {
    $bus_id = $is_super_admin ? intval($_POST['bus_id']) : $owner_bus_id;
    $start_date = $_POST['start_date'];
    $end_date = !empty($_POST['end_date']) ? $_POST['end_date'] : $start_date;
    $reason = trim($_POST['reason'] ?? '');
    
    if (empty($start_date)) {
        $error_msg = "Please select a start date!";
    } elseif ($end_date < $start_date) {
        $error_msg = "End date must be after start date!";
    } else {
        $current = strtotime($start_date);
        $end = strtotime($end_date);
        $added_count = 0;
        $skipped_count = 0;
        
        while ($current <= $end) {
            $date_to_add = date('Y-m-d', $current);
            
            // Check if already exists
            $check_sql = "SELECT id FROM bus_unavailable_dates WHERE bus_id = ? AND unavailable_date = ?";
            $check_stmt = $conn->prepare($check_sql);
            $check_stmt->bind_param("is", $bus_id, $date_to_add);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            
            if ($check_result->num_rows == 0) {
                $insert_sql = "INSERT INTO bus_unavailable_dates (bus_id, unavailable_date, reason) VALUES (?, ?, ?)";
                $insert_stmt = $conn->prepare($insert_sql);
                $insert_stmt->bind_param("iss", $bus_id, $date_to_add, $reason);
                $insert_stmt->execute();
                $insert_stmt->close();
                $added_count++;
            } else {
                $skipped_count++;
            }
            $check_stmt->close();
            
            $current = strtotime('+1 day', $current);
        }
        
        $success_msg = "$added_count date(s) added successfully!";
        if ($skipped_count > 0) {
            $success_msg .= " ($skipped_count already existed)";
        }
    }
}

// ============ DELETE UNAVAILABLE DATE ============
if (isset($_GET['delete'])) {
    $delete_id = intval($_GET['delete']);
    $sql = "DELETE FROM bus_unavailable_dates WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $delete_id);
    $stmt->execute();
    $stmt->close();
    $success_msg = "Date removed successfully!";
}

// ============ GET UNAVAILABLE DATES ============
if ($is_super_admin) {
    $sql = "SELECT bud.*, b.bus_name, b.bus_number 
            FROM bus_unavailable_dates bud 
            JOIN buses b ON bud.bus_id = b.id 
            ORDER BY bud.unavailable_date DESC";
    $result = $conn->query($sql);
} else {
    $sql = "SELECT bud.*, b.bus_name, b.bus_number 
            FROM bus_unavailable_dates bud 
            JOIN buses b ON bud.bus_id = b.id 
            WHERE bud.bus_id = ? 
            ORDER BY bud.unavailable_date DESC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $owner_bus_id);
    $stmt->execute();
    $result = $stmt->get_result();
}

// ============ GET ALL BUSES (Super Admin) ============
$all_buses = [];
if ($is_super_admin) {
    $buses_result = $conn->query("SELECT id, bus_name, bus_number FROM buses ORDER BY bus_name");
    while ($row = $buses_result->fetch_assoc()) {
        $all_buses[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Bus Unavailable Days</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Poppins', sans-serif; background: #f0f4f8; padding-top: 80px; padding-bottom: 40px; }
        .container { max-width: 1100px; margin: 20px auto; padding: 0 15px; }
        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; flex-wrap: wrap; gap: 15px; }
        .page-header h2 { color: #003580; font-weight: 700; font-size: 24px; }
        .page-header h2 i { margin-right: 10px; color: #dc3545; }
        .card { background: white; border-radius: 16px; padding: 25px; box-shadow: 0 2px 12px rgba(0,0,0,0.06); margin-bottom: 20px; }
        .card h3 { color: #003580; font-size: 18px; margin-bottom: 15px; }
        .form-row { display: grid; grid-template-columns: <?= $is_super_admin ? '1fr 1fr 1fr 2fr auto' : '1fr 1fr 2fr auto' ?>; gap: 15px; align-items: end; }
        .form-group label { display: block; font-weight: 600; font-size: 13px; color: #333; margin-bottom: 5px; }
        .form-group input, .form-group select { width: 100%; padding: 12px 15px; border: 2px solid #e0e0e0; border-radius: 10px; font-family: 'Poppins', sans-serif; font-size: 14px; outline: none; }
        .form-group input:focus, .form-group select:focus { border-color: #003580; box-shadow: 0 0 0 4px rgba(0,53,128,0.08); }
        .btn-add { background: linear-gradient(135deg, #28a745, #1e7e34); color: white; padding: 12px 25px; border: none; border-radius: 10px; font-weight: 600; cursor: pointer; font-family: 'Poppins', sans-serif; white-space: nowrap; }
        .alert { padding: 14px 20px; border-radius: 12px; margin-bottom: 20px; font-weight: 500; display: flex; align-items: center; gap: 10px; }
        .alert-success { background: #d4edda; color: #155724; border-left: 4px solid #28a745; }
        .alert-danger { background: #f8d7da; color: #721c24; border-left: 4px solid #dc3545; }
        table { width: 100%; border-collapse: collapse; }
        table thead { background: #003580; color: white; }
        table th, table td { padding: 12px 15px; text-align: left; font-size: 14px; }
        table tbody tr { border-bottom: 1px solid #eee; }
        table tbody tr:hover { background: #f8f9fa; }
        .btn-delete { background: #dc3545; color: white; padding: 6px 14px; border-radius: 6px; text-decoration: none; font-size: 12px; font-weight: 600; }
        .date-badge { background: #fce4e4; color: #c0392b; padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; display: inline-block; }
        .no-data { text-align: center; padding: 40px; color: #888; }
        .no-data i { font-size: 40px; color: #ddd; margin-bottom: 10px; display: block; }
        .btn-back { background: #6c757d; color: white; padding: 10px 20px; border-radius: 10px; text-decoration: none; font-weight: 600; display: inline-block; }
        @media (max-width: 768px) { .form-row { grid-template-columns: 1fr; } }
    </style>
</head>
<body>

<?php include 'header.php'; ?>

<div class="container">
    <div class="page-header">
        <h2><i class="fas fa-calendar-times"></i> Manage Bus Unavailable Days</h2>
        <a href="admin_dashboard.php" class="btn-back"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
    </div>

    <?php if ($success_msg): ?>
        <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($success_msg) ?></div>
    <?php endif; ?>
    <?php if ($error_msg): ?>
        <div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error_msg) ?></div>
    <?php endif; ?>

    <div class="card">
        <h3><i class="fas fa-plus-circle" style="color:#28a745;"></i> Add Unavailable Date(s)</h3>
        <form method="POST">
            <div class="form-row">
                <?php if ($is_super_admin): ?>
                    <div class="form-group">
                        <label>Select Bus *</label>
                        <select name="bus_id" required>
                            <option value="">-- Select Bus --</option>
                            <?php foreach ($all_buses as $bus): ?>
                                <option value="<?= $bus['id'] ?>"><?= htmlspecialchars($bus['bus_name']) ?> (<?= htmlspecialchars($bus['bus_number']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endif; ?>
                
                <div class="form-group">
                    <label>Start Date *</label>
                    <input type="date" name="start_date" min="<?= date('Y-m-d') ?>" required>
                </div>
                <div class="form-group">
                    <label>End Date (Optional)</label>
                    <input type="date" name="end_date" min="<?= date('Y-m-d') ?>">
                </div>
                <div class="form-group">
                    <label>Reason</label>
                    <input type="text" name="reason" placeholder="Service, Holiday...">
                </div>
                <div class="form-group">
                    <button type="submit" name="add_date" class="btn-add"><i class="fas fa-plus"></i> Add</button>
                </div>
            </div>
        </form>
    </div>

    <div class="card">
        <h3><i class="fas fa-list" style="color:#dc3545;"></i> Unavailable Dates List</h3>
        <?php if ($result && $result->num_rows > 0): ?>
            <div style="overflow-x:auto;">
                <table>
                    <thead>
                        <tr><th>#</th><th>Bus</th><th>Date</th><th>Reason</th><th>Action</th></tr>
                    </thead>
                    <tbody>
                        <?php $i = 1; while ($row = $result->fetch_assoc()): ?>
                            <tr>
                                <td><?= $i++ ?></td>
                                <td><strong><?= htmlspecialchars($row['bus_name']) ?></strong><br><small style="color:#888;"><?= htmlspecialchars($row['bus_number']) ?></small></td>
                                <td><span class="date-badge"><i class="fas fa-calendar"></i> <?= date('d M Y', strtotime($row['unavailable_date'])) ?></span></td>
                                <td><?= htmlspecialchars($row['reason'] ?? 'N/A') ?></td>
                                <td><a href="?delete=<?= $row['id'] ?>" class="btn-delete" onclick="return confirm('Remove this date?')"><i class="fas fa-trash"></i> Remove</a></td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="no-data">
                <i class="fas fa-calendar-check"></i>
                <h4>No Unavailable Dates</h4>
                <p>All dates are available for booking.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

</body>
</html>