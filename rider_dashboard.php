<?php
session_start();
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'rider') {
    header('Location: rider_login.php');
    exit;
}

$page_title = "Rider Dashboard - Rimbunan Cafe";
include 'config/database.php';

// Handle delivery status update
if (isset($_POST['update_delivery'])) {
    $order_id = $_POST['order_id'];
    $action = $_POST['action'];
    
    if ($action === 'accept') {
        $stmt = $pdo->prepare("UPDATE orders SET delivery_status = 1 WHERE order_id = ?");
        $stmt->execute([$order_id]);
    } elseif ($action === 'delivered') {
        // Mark as delivered and clear rider assignment for future availability
        $stmt = $pdo->prepare("UPDATE orders SET order_status = 'Delivered', delivery_status = 2 WHERE order_id = ?");
        $stmt->execute([$order_id]);
        
        // Clear staff assignment for this order to allow new assignments
        $stmt = $pdo->prepare("UPDATE staff SET orders_id = NULL, status_updated = NULL, assigned_rider_id = NULL WHERE orders_id = ?");
        $stmt->execute([$order_id]);
    }
}

// Handle availability toggle
if (isset($_POST['toggle_availability'])) {
    $new_status = $_POST['availability_status'];
    $stmt = $pdo->prepare("UPDATE rider SET rider_status = ? WHERE rider_id = ?");
    $stmt->execute([$new_status, $_SESSION['user_id']]);
}

// Handle profile update
if (isset($_POST['update_profile'])) {
    $username = $_POST['username'];
    $email = $_POST['email'];
    $phone = $_POST['phone'];
    $vehicle = $_POST['vehicle'];
    $password = $_POST['password'];
    
    if (!empty($password)) {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE rider SET rider_username = ?, rider_email = ?, rider_phonenumber = ?, rider_vehicleinfo = ?, rider_password = ? WHERE rider_id = ?");
        $stmt->execute([$username, $email, $phone, $vehicle, $hashed_password, $_SESSION['user_id']]);
    } else {
        $stmt = $pdo->prepare("UPDATE rider SET rider_username = ?, rider_email = ?, rider_phonenumber = ?, rider_vehicleinfo = ? WHERE rider_id = ?");
        $stmt->execute([$username, $email, $phone, $vehicle, $_SESSION['user_id']]);
    }
    
    $_SESSION['username'] = $username;
}

// Get rider info
$stmt = $pdo->prepare("SELECT * FROM rider WHERE rider_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$rider = $stmt->fetch();

// Get assigned orders with payment method
$stmt = $pdo->prepare("SELECT o.*, c.cust_username, c.cust_address, c.cust_phonenumber, o.payment_method,
                      GROUP_CONCAT(CONCAT(p.product_name, ' (', od.qty, ')') SEPARATOR ', ') as items
                      FROM orders o 
                      LEFT JOIN customer c ON o.cust_id = c.cust_id
                      LEFT JOIN order_details od ON o.order_id = od.orders_id 
                      LEFT JOIN product p ON od.product_id = p.product_id 
                      WHERE o.rider_id = ? AND o.order_status IN ('In Delivery', 'Preparing')
                      GROUP BY o.order_id 
                      ORDER BY o.order_date DESC");
$stmt->execute([$_SESSION['user_id']]);
$orders = $stmt->fetchAll();

// Calculate earnings
$today = date('Y-m-d');
$week_start = date('Y-m-d', strtotime('monday this week'));
$month_start = date('Y-m-01');

// Daily, Weekly, Monthly Completed Orders Count
// Daily completed orders
$stmt = $pdo->prepare("SELECT COUNT(*) as daily_completed FROM orders WHERE rider_id = ? AND DATE(order_date) = ? AND order_status = 'Delivered'");
$stmt->execute([$_SESSION['user_id'], $today]);
$daily_completed = $stmt->fetch()['daily_completed'];

// Weekly completed orders
$stmt = $pdo->prepare("SELECT COUNT(*) as weekly_completed FROM orders WHERE rider_id = ? AND DATE(order_date) >= ? AND order_status = 'Delivered'");
$stmt->execute([$_SESSION['user_id'], $week_start]);
$weekly_completed = $stmt->fetch()['weekly_completed'];

// Monthly completed orders
$stmt = $pdo->prepare("SELECT COUNT(*) as monthly_completed FROM orders WHERE rider_id = ? AND DATE(order_date) >= ? AND order_status = 'Delivered'");
$stmt->execute([$_SESSION['user_id'], $month_start]);
$monthly_completed = $stmt->fetch()['monthly_completed'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <link rel="stylesheet" href="css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>

<div class="dashboard">
    <div class="dashboard-header">
        <div class="container">
            <div class="dashboard-nav">
                <div class="logo">🛵 Rider Dashboard</div>
                <div class="nav-actions">
                    <button class="btn btn-secondary" onclick="refreshPage()" style="margin-right: 1rem;">🔄 Refresh</button>
                    <a href="rider_reports.php" class="btn btn-secondary" style="margin-right: 1rem;">📊 Reports</a>
                    <span>Welcome, <?php echo $_SESSION['username']; ?>!</span>
                    <a href="logout.php" class="btn btn-danger">Logout</a>
                </div>
            </div>
        </div>
    </div>
    
    <div class="container">
        <div class="dashboard-content">
            <div class="dashboard-tabs">
                <button class="tab-btn active" onclick="showTab('orders')">📦 My Deliveries</button>
                <button class="tab-btn" onclick="showTab('reports')">📊 Completed Orders</button>
                <button class="tab-btn" onclick="showTab('statistics')">📊 Statistics</button>
            </div>
            
            <!-- Availability Toggle -->
            <div style="background: white; padding: 1.5rem; border-radius: 15px; box-shadow: 0 5px 15px rgba(0,0,0,0.1); margin-bottom: 2rem;">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <h3 style="margin: 0; color: #8B4513;">🚦 Availability Status</h3>
                        <p style="margin: 0.5rem 0 0 0; color: #666;">Toggle your availability to receive new delivery assignments</p>
                    </div>
                    <form method="POST" style="display: flex; align-items: center; gap: 1rem;">
                        <span style="font-weight: 500; color: <?php echo $rider['rider_status'] == 1 ? '#28a745' : '#dc3545'; ?>;">
                            <?php echo $rider['rider_status'] == 1 ? '🟢 Available' : '🔴 Unavailable'; ?>
                        </span>
                        <label class="switch" style="position: relative; display: inline-block; width: 60px; height: 34px;">
                            <input type="hidden" name="availability_status" value="<?php echo $rider['rider_status'] == 1 ? 0 : 1; ?>">
                            <input type="checkbox" <?php echo $rider['rider_status'] == 1 ? 'checked' : ''; ?> onchange="this.form.submit()" style="opacity: 0; width: 0; height: 0;">
                            <span style="position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: <?php echo $rider['rider_status'] == 1 ? '#28a745' : '#ccc'; ?>; transition: .4s; border-radius: 34px; <?php echo $rider['rider_status'] == 1 ? '' : 'background-color: #ccc;'; ?>">
                                <span style="position: absolute; content: ''; height: 26px; width: 26px; left: <?php echo $rider['rider_status'] == 1 ? '30px' : '4px'; ?>; bottom: 4px; background-color: white; transition: .4s; border-radius: 50%;"></span>
                            </span>
                        </label>
                        <input type="hidden" name="toggle_availability" value="1">
                    </form>
                </div>
            </div>
            
            <!-- Orders Tab -->
            <div id="orders" class="tab-content active">
                <h2>Assigned Deliveries</h2>
                
                <?php if (empty($orders)): ?>
                    <div style="background: white; padding: 2rem; border-radius: 15px; text-align: center; box-shadow: 0 5px 15px rgba(0,0,0,0.1);">
                        <h3>No deliveries assigned</h3>
                        <p>You will see your assigned deliveries here when staff assigns them to you.</p>
                    </div>
                <?php else: ?>
                    <div style="background: white; border-radius: 15px; overflow: hidden; box-shadow: 0 5px 15px rgba(0,0,0,0.1);">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Order ID</th>
                                    <th>Customer</th>
                                    <th>Items</th>
                                    <th>Payment</th>
                                    <th>Total</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($orders as $order): ?>
                                    <tr>
                                        <td>#<?php echo $order['order_id']; ?></td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($order['cust_username']); ?></strong><br>
                                            <small>📞 <?php echo htmlspecialchars($order['cust_phonenumber']); ?></small><br>
                                            <small>📍 <?php echo htmlspecialchars($order['cust_address']); ?></small>
                                        </td>
                                        <td><?php echo htmlspecialchars($order['items']); ?></td>
                                        <td>
                                            <span class="status-badge <?php echo ($order['payment_method'] ?? 'cod') === 'cod' ? 'status-pending' : 'status-completed'; ?>">
                                                <?php echo ($order['payment_method'] ?? 'cod') === 'cod' ? '💵 COD' : '📱 QR Paid'; ?>
                                            </span>
                                        </td>
                                        <td>RM <?php echo number_format($order['total_price'], 2); ?></td>
                                        <td>
                                            <span class="status-badge status-<?php echo strtolower(str_replace(' ', '', $order['order_status'])); ?>">
                                                <?php echo $order['order_status']; ?>
                                            </span>
                                            <?php if ($order['delivery_status'] == 1): ?>
                                                <br><small style="color: #28a745;">✓ Accepted</small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <form method="POST" style="display: inline-block;">
                                                <input type="hidden" name="order_id" value="<?php echo $order['order_id']; ?>">
                                                
                                                <?php if ($order['delivery_status'] == 0): ?>
                                                    <button type="submit" name="update_delivery" value="accept" class="btn btn-primary" style="font-size: 0.875rem; margin-bottom: 0.5rem;">
                                                        ✅ Accept Delivery
                                                    </button>
                                                    <input type="hidden" name="action" value="accept">
                                                <?php elseif ($order['delivery_status'] == 1 && $order['order_status'] !== 'Delivered'): ?>
                                                    <button type="submit" name="update_delivery" value="delivered" class="btn btn-primary" style="font-size: 0.875rem;">
                                                        📦 Mark as Delivered
                                                    </button>
                                                    <input type="hidden" name="action" value="delivered">
                                                <?php else: ?>
                                                    <span style="color: #28a745; font-weight: 500;">✓ Completed</span>
                                                <?php endif; ?>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Reports Tab -->
            <div id="reports" class="tab-content">
                <h2>📊 Completed Orders Report</h2>
                
                <!-- Completed Orders Cards -->
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.5rem; margin-bottom: 2rem;">
                    <div style="background: linear-gradient(135deg, #dc3545, #c82333); color: white; padding: 2rem; border-radius: 15px; text-align: center; box-shadow: 0 5px 15px rgba(220,53,69,0.3);">
                        <div style="font-size: 2.5rem; margin-bottom: 1rem;">📅</div>
                        <h3 style="margin-bottom: 1rem;">Daily Completed</h3>
                        <p style="font-size: 1.5rem; font-weight: 600; margin: 0;">
                            <?php echo $daily_completed; ?>
                        </p>
                        <small>Orders completed today</small>
                    </div>
                    <div style="background: linear-gradient(135deg, #fd7e14, #e55a00); color: white; padding: 2rem; border-radius: 15px; text-align: center; box-shadow: 0 5px 15px rgba(253,126,20,0.3);">
                        <div style="font-size: 2.5rem; margin-bottom: 1rem;">📊</div>
                        <h3 style="margin-bottom: 1rem;">Weekly Completed</h3>
                        <p style="font-size: 1.5rem; font-weight: 600; margin: 0;">
                            <?php echo $weekly_completed; ?>
                        </p>
                        <small>Orders completed this week</small>
                    </div>
                    <div style="background: linear-gradient(135deg, #6f42c1, #5a32a3); color: white; padding: 2rem; border-radius: 15px; text-align: center; box-shadow: 0 5px 15px rgba(111,66,193,0.3);">
                        <div style="font-size: 2.5rem; margin-bottom: 1rem;">📈</div>
                        <h3 style="margin-bottom: 1rem;">Monthly Completed</h3>
                        <p style="font-size: 1.5rem; font-weight: 600; margin: 0;">
                            <?php echo $monthly_completed; ?>
                        </p>
                        <small>Orders completed this month</small>
                    </div>
                    <div style="background: linear-gradient(135deg, #28a745, #20c997); color: white; padding: 2rem; border-radius: 15px; text-align: center; box-shadow: 0 5px 15px rgba(40,167,69,0.3);">
                        <div style="font-size: 2.5rem; margin-bottom: 1rem;">✅</div>
                        <h3 style="margin-bottom: 1rem;">Total Completed</h3>
                        <?php
                        $stmt = $pdo->prepare("SELECT COUNT(*) as total_completed FROM orders WHERE rider_id = ? AND order_status = 'Delivered'");
                        $stmt->execute([$_SESSION['user_id']]);
                        $total_completed = $stmt->fetch()['total_completed'];
                        ?>
                        <p style="font-size: 1.5rem; font-weight: 600; margin: 0;">
                            <?php echo $total_completed; ?>
                        </p>
                        <small>All time completed orders</small>
                    </div>
                </div>
                
                <!-- Performance Summary -->
                <div style="background: white; padding: 2rem; border-radius: 15px; box-shadow: 0 5px 15px rgba(0,0,0,0.1);">
                    <h3 style="color: #8B4513; margin-bottom: 2rem; text-align: center;">📊 Performance Summary</h3>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.5rem;">
                        <div style="text-align: center; padding: 1.5rem; background: #e8f5e8; border-radius: 10px; border: 2px solid #c3e6cb;">
                            <div style="font-size: 2rem; margin-bottom: 0.5rem;">📅</div>
                            <h4 style="color: #155724; margin-bottom: 1rem;">Today's Progress</h4>
                            <p style="font-size: 1.25rem; font-weight: 600; color: #856404; margin-bottom: 0.5rem;">
                                <?php echo $daily_completed; ?> completed
                            </p>
                            <small style="color: #155724;">Deliveries finished today</small>
                        </div>
                        <div style="text-align: center; padding: 1.5rem; background: #fff3cd; border-radius: 10px; border: 2px solid #ffeaa7;">
                            <div style="font-size: 2rem; margin-bottom: 0.5rem;">📊</div>
                            <h4 style="color: #856404; margin-bottom: 1rem;">Weekly Progress</h4>
                            <p style="font-size: 1.25rem; font-weight: 600; color: #0c5460; margin-bottom: 0.5rem;">
                                <?php echo $weekly_completed; ?> completed
                            </p>
                            <small style="color: #856404;">Deliveries this week</small>
                        </div>
                        <div style="text-align: center; padding: 1.5rem; background: #d1ecf1; border-radius: 10px; border: 2px solid #bee5eb;">
                            <div style="font-size: 2rem; margin-bottom: 0.5rem;">📈</div>
                            <h4 style="color: #0c5460; margin-bottom: 1rem;">Monthly Progress</h4>
                            <p style="font-size: 1.25rem; font-weight: 600; color: #0c5460; margin-bottom: 0.5rem;">
                                <?php echo $monthly_completed; ?> completed
                            </p>
                            <small style="color: #0c5460;">Deliveries this month</small>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Statistics Tab -->
            <div id="statistics" class="tab-content">
                <h2>📊 Delivery Statistics & History</h2>
                
                <?php
                // Get delivery statistics
                $stmt = $pdo->prepare("SELECT 
                    COUNT(*) as total_deliveries,
                    COUNT(CASE WHEN order_status = 'Delivered' THEN 1 END) as completed_deliveries,
                    COUNT(CASE WHEN order_status = 'In Delivery' THEN 1 END) as in_progress_deliveries,
                    AVG(total_price) as avg_order_value,
                    SUM(total_price * 0.1) as total_earnings
                    FROM orders 
                    WHERE rider_id = ?");
                $stmt->execute([$_SESSION['user_id']]);
                $stats = $stmt->fetch();
                
                // Get delivery history
                $stmt = $pdo->prepare("SELECT o.*, c.cust_username, c.cust_address, c.cust_phonenumber, o.payment_method,
                                      GROUP_CONCAT(CONCAT(p.product_name, ' (', od.qty, ')') SEPARATOR ', ') as items
                                      FROM orders o 
                                      LEFT JOIN customer c ON o.cust_id = c.cust_id
                                      LEFT JOIN order_details od ON o.order_id = od.orders_id 
                                      LEFT JOIN product p ON od.product_id = p.product_id 
                                      WHERE o.rider_id = ?
                                      GROUP BY o.order_id 
                                      ORDER BY o.order_date DESC
                                      LIMIT 20");
                $stmt->execute([$_SESSION['user_id']]);
                $delivery_history = $stmt->fetchAll();
                ?>
                
                <!-- Statistics Cards -->
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.5rem; margin-bottom: 2rem;">
                    <div style="background: linear-gradient(135deg, #17a2b8, #138496); color: white; padding: 1.5rem; border-radius: 15px; text-align: center; box-shadow: 0 5px 15px rgba(23,162,184,0.3);">
                        <div style="font-size: 2rem; margin-bottom: 0.5rem;">📦</div>
                        <h4 style="margin-bottom: 0.5rem;">Total Deliveries</h4>
                        <p style="font-size: 1.5rem; font-weight: 600; margin: 0;"><?php echo $stats['total_deliveries'] ?? 0; ?></p>
                    </div>
                    <div style="background: linear-gradient(135deg, #28a745, #20c997); color: white; padding: 1.5rem; border-radius: 15px; text-align: center; box-shadow: 0 5px 15px rgba(40,167,69,0.3);">
                        <div style="font-size: 2rem; margin-bottom: 0.5rem;">✅</div>
                        <h4 style="margin-bottom: 0.5rem;">Completed</h4>
                        <p style="font-size: 1.5rem; font-weight: 600; margin: 0;"><?php echo $stats['completed_deliveries'] ?? 0; ?></p>
                    </div>
                    <div style="background: linear-gradient(135deg, #ffc107, #e0a800); color: white; padding: 1.5rem; border-radius: 15px; text-align: center; box-shadow: 0 5px 15px rgba(255,193,7,0.3);">
                        <div style="font-size: 2rem; margin-bottom: 0.5rem;">🚚</div>
                        <h4 style="margin-bottom: 0.5rem;">In Progress</h4>
                        <p style="font-size: 1.5rem; font-weight: 600; margin: 0;"><?php echo $stats['in_progress_deliveries'] ?? 0; ?></p>
                    </div>
                    <div style="background: linear-gradient(135deg, #8B4513, #A0522D); color: white; padding: 1.5rem; border-radius: 15px; text-align: center; box-shadow: 0 5px 15px rgba(139,69,19,0.3);">
                        <div style="font-size: 2rem; margin-bottom: 0.5rem;">💰</div>
                        <h4 style="margin-bottom: 0.5rem;">Total Earned</h4>
                        <p style="font-size: 1.5rem; font-weight: 600; margin: 0;">RM <?php echo number_format($stats['total_earnings'] ?? 0, 2); ?></p>
                    </div>
                </div>
                
                <!-- Average Order Value -->
                <div style="background: white; padding: 1.5rem; border-radius: 15px; box-shadow: 0 5px 15px rgba(0,0,0,0.1); margin-bottom: 2rem; text-align: center;">
                    <h4 style="color: #8B4513; margin-bottom: 1rem;">📈 Average Order Value</h4>
                    <p style="font-size: 1.25rem; font-weight: 600; color: #28a745; margin: 0;">
                        RM <?php echo number_format($stats['avg_order_value'] ?? 0, 2); ?>
                    </p>
                </div>
                
                <!-- Delivery History -->
                <div style="background: white; border-radius: 15px; overflow: hidden; box-shadow: 0 5px 15px rgba(0,0,0,0.1);">
                    <div style="background: #8B4513; color: white; padding: 1rem;">
                        <h3 style="margin: 0;">📋 Recent Delivery History</h3>
                    </div>
                    
                    <?php if (empty($delivery_history)): ?>
                        <div style="padding: 2rem; text-align: center; color: #666;">
                            <div style="font-size: 3rem; margin-bottom: 1rem;">📦</div>
                            <h4>No delivery history yet</h4>
                            <p>Your completed deliveries will appear here</p>
                        </div>
                    <?php else: ?>
                        <div style="max-height: 400px; overflow-y: auto;">
                            <table class="data-table" style="margin: 0;">
                                <thead>
                                    <tr>
                                        <th>Order ID</th>
                                        <th>Date</th>
                                        <th>Customer</th>
                                        <th>Items</th>
                                        <th>Payment</th>
                                        <th>Total</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($delivery_history as $order): ?>
                                        <tr>
                                            <td>#<?php echo $order['order_id']; ?></td>
                                            <td><?php echo date('M d, Y', strtotime($order['order_date'])); ?></td>
                                            <td>
                                                <strong><?php echo htmlspecialchars($order['cust_username']); ?></strong><br>
                                                <small>📍 <?php echo htmlspecialchars(substr($order['delivery_address'] ?? $order['cust_address'], 0, 30)) . (strlen($order['delivery_address'] ?? $order['cust_address']) > 30 ? '...' : ''); ?></small>
                                            </td>
                                            <td style="max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                                <?php echo htmlspecialchars($order['items']); ?>
                                            </td>
                                            <td>
                                                <span class="status-badge <?php echo ($order['payment_method'] ?? 'cod') === 'cod' ? 'status-pending' : 'status-completed'; ?>">
                                                    <?php echo ($order['payment_method'] ?? 'cod') === 'cod' ? '💵 COD' : '📱 QR'; ?>
                                                </span>
                                            </td>
                                            <td>RM <?php echo number_format($order['total_price'], 2); ?></td>
                                            <td>
                                                <span class="status-badge status-<?php echo strtolower(str_replace(' ', '', $order['order_status'])); ?>">
                                                    <?php echo $order['order_status']; ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="js/main.js"></script>

</body>
</html>