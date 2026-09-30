<?php
include 'config.php';
require_once 'tab_auth.php';

// ============ TAB AUTHENTICATION CHECK ============
requireTabAuth('admin_login.php');

// ============ CHECK ADMIN LOGIN ============
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true || $_SESSION['role'] !== 'super_admin') {
    header("Location: admin_login.php");
    exit();
}

// ============ GET ALL BUSES ============
$sql = "SELECT * FROM buses ORDER BY id DESC";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Buses - Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: 'Poppins', sans-serif; 
            background: #f0f4f8; 
            padding-top: 80px;
            padding-bottom: 40px;
        }
        
        .container { 
            max-width: 1200px; 
            margin: 20px auto; 
            padding: 0 15px; 
        }
        
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .page-header h2 {
            color: #003580;
            font-weight: 700;
            font-size: 24px;
        }
        
        .page-header h2 i {
            margin-right: 10px;
        }
        
        .page-header .btn-add {
            background: #28a745;
            color: white;
            padding: 10px 25px;
            border-radius: 10px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s;
        }
        
        .page-header .btn-add:hover {
            background: #1e7e34;
            transform: translateY(-2px);
        }
        
        .page-header .btn-add i {
            margin-right: 6px;
        }
        
        .table-responsive {
            overflow-x: auto;
            background: white;
            border-radius: 16px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.06);
            padding: 20px;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }
        
        th {
            background: linear-gradient(135deg, #003580, #004d99);
            color: white;
            padding: 14px 12px;
            font-weight: 600;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            text-align: center;
        }
        
        td {
            padding: 12px;
            border-bottom: 1px solid #eef2f7;
            text-align: center;
            vertical-align: middle;
        }
        
        tr:hover td {
            background: #f8faff;
        }
        
        .status-badge {
            padding: 4px 14px;
            border-radius: 30px;
            font-size: 11px;
            font-weight: 600;
            display: inline-block;
        }
        
        .status-active {
            background: #d4edda;
            color: #155724;
        }
        
        .status-blocked {
            background: #f8d7da;
            color: #721c24;
        }
        
        .action-link {
            margin: 0 4px;
            text-decoration: none;
            font-weight: 500;
        }
        
        .action-link.edit {
            color: #fd7e14;
        }
        
        .action-link.delete {
            color: #dc3545;
        }
        
        .action-link.block {
            color: #007bff;
        }
        
        .action-link:hover {
            text-decoration: underline;
        }
        
        .no-data {
            text-align: center;
            padding: 40px;
            color: #888;
        }
        
        .no-data i {
            font-size: 50px;
            color: #ddd;
            margin-bottom: 10px;
        }
        
        @media (max-width: 768px) {
            .page-header {
                flex-direction: column;
                text-align: center;
            }
            th, td {
                font-size: 11px;
                padding: 8px 6px;
            }
        }
    </style>
</head>
<body>

<?php include 'header.php'; ?>

<div class="container">
    
    <div class="page-header">
        <h2><i class="fas fa-bus"></i> All Buses</h2>
        <a href="add_bus.php" class="btn-add">
            <i class="fas fa-plus"></i> Add New Bus
        </a>
    </div>
    
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Bus Name</th>
                    <th>Number</th>
                    <th>Route</th>
                    <th>Start</th>
                    <th>End</th>
                    <th>Time</th>
                    <th>Seats</th>
                    <th>Type</th>
                    <th>Owner</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($result && $result->num_rows > 0): ?>
                    <?php while($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td><?= $row['id'] ?></td>
                            <td><strong><?= htmlspecialchars($row['bus_name']) ?></strong></td>
                            <td><?= htmlspecialchars($row['bus_number']) ?></td>
                            <td><?= htmlspecialchars($row['route_category'] ?? 'N/A') ?></td>
                            <td><?= htmlspecialchars($row['start_location'] ?? 'N/A') ?></td>
                            <td><?= htmlspecialchars($row['end_location'] ?? 'N/A') ?></td>
                            <td><?= $row['departure_time'] ?? 'N/A' ?></td>
                            <td><?= $row['seat_capacity'] ?? 45 ?></td>
                            <td><?= htmlspecialchars($row['bus_type'] ?? 'Normal') ?></td>
                            <td><?= htmlspecialchars($row['owner_username']) ?></td>
                            <td>
                                <span class="status-badge status-<?= strtolower($row['status'] ?? 'active') ?>">
                                    <?= ucfirst($row['status'] ?? 'Active') ?>
                                </span>
                            </td>
                            <td>
                                <a href='update_bus.php?id=<?= $row['id'] ?>' class="action-link edit">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <a href='#' onclick="confirmDelete(<?= $row['id'] ?>)" class="action-link delete">
                                    <i class="fas fa-trash"></i>
                                </a>
                                <a href='#' onclick="openBlockModal(<?= $row['id'] ?>)" class="action-link block">
                                    <i class="fas fa-calendar-times"></i>
                                </a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="12" class="no-data">
                            <i class="fas fa-bus"></i>
                            <p>No buses found in the system.</p>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    
    <div style="text-align: center; margin-top: 20px;">
        <a href="admin_dashboard.php" class="btn" style="background:#003580;color:white;padding:10px 25px;border-radius:10px;text-decoration:none;font-weight:600;">
            <i class="fas fa-arrow-left"></i> Back to Dashboard
        </a>
    </div>
    
</div>

<!-- ===== BLOCK MODAL ===== -->
<div class="modal-overlay" id="blockModal" style="
    display: none;
    position: fixed;
    top: 0; left: 0;
    width: 100%; height: 100%;
    background: rgba(0,0,0,0.5);
    backdrop-filter: blur(5px);
    z-index: 999;
    justify-content: center;
    align-items: center;
">
    <div class="modal-box" style="
        background: white;
        padding: 35px;
        border-radius: 20px;
        max-width: 400px;
        width: 90%;
        box-shadow: 0 20px 60px rgba(0,0,0,0.3);
    ">
        <h3 style="font-size: 20px; color: #003580; margin-bottom: 20px; text-align: center;">
            <i class="fas fa-calendar-times" style="color:#007bff;"></i> Block Bus Date Range
        </h3>
        <form action="block_bus_action.php" method="POST">
            <input type="hidden" name="bus_id" id="modal_bus_id">
            
            <label style="font-size: 13px; font-weight: 500; display: block; margin-top: 12px; color: #333;">Start Date:</label>
            <input type="date" name="start_date" id="start_date" required style="
                width: 100%;
                padding: 10px 14px;
                border-radius: 10px;
                border: 2px solid #e0e0e0;
                font-family: 'Poppins', sans-serif;
                margin-top: 4px;
                box-sizing: border-box;
            " min="<?= date('Y-m-d') ?>">
            
            <label style="font-size: 13px; font-weight: 500; display: block; margin-top: 12px; color: #333;">End Date:</label>
            <input type="date" name="end_date" id="end_date" required style="
                width: 100%;
                padding: 10px 14px;
                border-radius: 10px;
                border: 2px solid #e0e0e0;
                font-family: 'Poppins', sans-serif;
                margin-top: 4px;
                box-sizing: border-box;
            " min="<?= date('Y-m-d') ?>">
            
            <button type="submit" name="block_bus" style="
                width: 100%;
                margin-top: 15px;
                padding: 12px;
                background: #003580;
                color: white;
                border: none;
                border-radius: 10px;
                font-weight: 600;
                cursor: pointer;
                font-family: 'Poppins', sans-serif;
            ">
                <i class="fas fa-lock"></i> Confirm Block
            </button>
            <button type="button" onclick="closeBlockModal()" style="
                width: 100%;
                margin-top: 8px;
                padding: 10px;
                background: #e9ecef;
                color: #333;
                border: none;
                border-radius: 10px;
                cursor: pointer;
                font-family: 'Poppins', sans-serif;
                font-weight: 600;
            ">
                <i class="fas fa-times"></i> Cancel
            </button>
        </form>
    </div>
</div>

<script>
// ============ BLOCK MODAL ============
function openBlockModal(id) {
    document.getElementById('modal_bus_id').value = id;
    document.getElementById('blockModal').style.display = 'flex';
}

function closeBlockModal() {
    document.getElementById('blockModal').style.display = 'none';
}

// Close modal when clicking outside
document.getElementById('blockModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeBlockModal();
    }
});

// ============ CONFIRM DELETE ============
function confirmDelete(id) {
    if (confirm('Are you sure you want to delete this bus? This action cannot be undone!')) {
        window.location.href = 'admin_dashboard.php?delete_bus=' + id;
    }
}

// Set min date for date inputs
document.addEventListener('DOMContentLoaded', function() {
    const today = new Date().toISOString().split('T')[0];
    const startDate = document.getElementById('start_date');
    const endDate = document.getElementById('end_date');
    if (startDate) startDate.min = today;
    if (endDate) endDate.min = today;
});
</script>

</body>
</html>