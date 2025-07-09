<?php
// Debug script to check order data and identify reporting issues
session_start();
include 'config/database.php';

echo "<h1>Order Management Debug Report</h1>";
echo "<p>Generated on: " . date('Y-m-d H:i:s') . "</p>";

// Check current date and timezone
echo "<h2>System Information</h2>";
echo "<p>Server Date: " . date('Y-m-d H:i:s') . "</p>";
echo "<p>Server Timezone: " . date_default_timezone_get() . "</p>";

// Check all orders for today
$today = date('Y-m-d');
echo "<h2>Orders for Today ($today)</h2>";

$stmt = $pdo->prepare("SELECT order_id, order_date, order_status, total_price, cust_id, rider_id FROM orders WHERE DATE(order_date) = ? ORDER BY order_date DESC");
$stmt->execute([$today]);
$today_orders = $stmt->fetchAll();

if (empty($today_orders)) {
    echo "<p style='color: red;'>❌ No orders found for today</p>";
} else {
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th>Order ID</th><th>Date/Time</th><th>Status</th><th>Total</th><th>Customer ID</th><th>Rider ID</th></tr>";
    foreach ($today_orders as $order) {
        echo "<tr>";
        echo "<td>#{$order['order_id']}</td>";
        echo "<td>{$order['order_date']}</td>";
        echo "<td>{$order['order_status']}</td>";
        echo "<td>RM " . number_format($order['total_price'], 2) . "</td>";
        echo "<td>{$order['cust_id']}</td>";
        echo "<td>" . ($order['rider_id'] ?? 'Not assigned') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
}

// Check delivered orders for today
echo "<h2>Delivered Orders for Today</h2>";
$stmt = $pdo->prepare("SELECT COUNT(*) as count FROM orders WHERE DATE(order_date) = ? AND order_status = 'Delivered'");
$stmt->execute([$today]);
$delivered_today = $stmt->fetch()['count'];
echo "<p>Delivered orders today: <strong>$delivered_today</strong></p>";

// Check all orders by status
echo "<h2>Orders by Status (All Time)</h2>";
$stmt = $pdo->prepare("SELECT order_status, COUNT(*) as count FROM orders GROUP BY order_status");
$stmt->execute();
$status_counts = $stmt->fetchAll();

echo "<table border='1' style='border-collapse: collapse;'>";
echo "<tr><th>Status</th><th>Count</th></tr>";
foreach ($status_counts as $status) {
    echo "<tr><td>{$status['order_status']}</td><td>{$status['count']}</td></tr>";
}
echo "</table>";

// Check rider assignments
echo "<h2>Rider Assignment Status</h2>";
$stmt = $pdo->prepare("SELECT r.rider_username, COUNT(o.order_id) as assigned_orders 
                      FROM rider r 
                      LEFT JOIN orders o ON r.rider_id = o.rider_id 
                      GROUP BY r.rider_id");
$stmt->execute();
$rider_stats = $stmt->fetchAll();

echo "<table border='1' style='border-collapse: collapse;'>";
echo "<tr><th>Rider</th><th>Assigned Orders</th></tr>";
foreach ($rider_stats as $rider) {
    echo "<tr><td>{$rider['rider_username']}</td><td>{$rider['assigned_orders']}</td></tr>";
}
echo "</table>";

// Check recent order updates
echo "<h2>Recent Orders (Last 24 hours)</h2>";
$stmt = $pdo->prepare("SELECT order_id, order_date, order_status, total_price FROM orders WHERE order_date >= DATE_SUB(NOW(), INTERVAL 24 HOUR) ORDER BY order_date DESC");
$stmt->execute();
$recent_orders = $stmt->fetchAll();

if (empty($recent_orders)) {
    echo "<p style='color: orange;'>⚠️ No orders in the last 24 hours</p>";
} else {
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th>Order ID</th><th>Date/Time</th><th>Status</th><th>Total</th></tr>";
    foreach ($recent_orders as $order) {
        echo "<tr>";
        echo "<td>#{$order['order_id']}</td>";
        echo "<td>{$order['order_date']}</td>";
        echo "<td>{$order['order_status']}</td>";
        echo "<td>RM " . number_format($order['total_price'], 2) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
}

echo "<h2>Recommendations</h2>";
echo "<ul>";
echo "<li>✅ Check if orders are being placed with correct timestamps</li>";
echo "<li>✅ Verify order status updates are working properly</li>";
echo "<li>✅ Ensure rider assignments are being saved correctly</li>";
echo "<li>✅ Test the complete order flow from placement to delivery</li>";
echo "</ul>";
?>