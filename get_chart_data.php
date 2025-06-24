<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Not authenticated']);
    exit;
}

$user_id = $_SESSION['user_id'];
$period = isset($_GET['period']) ? $_GET['period'] : 'all';
$type = isset($_GET['type']) ? $_GET['type'] : 'balance';

function getDateRange($period) {
    $end = date('Y-m-d');
    switch($period) {
        case 'week':
            $start = date('Y-m-d', strtotime('-1 week'));
            break;
        case 'month':
            $start = date('Y-m-d', strtotime('-1 month'));
            break;
        case 'year':
            $start = date('Y-m-d', strtotime('-1 year'));
            break;
        case 'all':
            // Get the date of the first transaction
            global $conn, $user_id;
            $result = $conn->query("SELECT MIN(DATE(created_at)) as first_date FROM transactions WHERE user_id = $user_id");
            $row = $result->fetch_assoc();
            $start = $row['first_date'] ?: date('Y-m-d', strtotime('-1 month'));
            break;
        default:
            $start = date('Y-m-d', strtotime('-1 month'));
    }
    return [$start, $end];
}

header('Content-Type: application/json');

$data = [];
list($start_date, $end_date) = getDateRange($period);

// Debug info
error_log("Period: $period, Type: $type, Start: $start_date, End: $end_date");

switch($type) {
    case 'balance':
        // First get the starting balance
$query = "SELECT COALESCE(SUM(CASE WHEN type='income' THEN amount ELSE -amount END), 0) as starting_balance
          FROM transactions 
          WHERE user_id = ? AND created_at < ?";
$stmt = $conn->prepare($query);
$stmt->bind_param('is', $user_id, $start_date);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$starting_balance = floatval($row['starting_balance'] ?? 0);

// Then get daily changes
$query = "SELECT DATE(created_at) as date, 
                 COALESCE(SUM(CASE WHEN type='income' THEN amount ELSE -amount END), 0) as daily_change
          FROM transactions 
          WHERE user_id = ? AND DATE(created_at) BETWEEN ? AND ?
          GROUP BY DATE(created_at)
          ORDER BY date ASC";
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param('iss', $user_id, $start_date, $end_date);
        $stmt->execute();
        $result = $stmt->get_result();
          $running_balance = $starting_balance;
        $balance_data = [];
        
        // Add starting point
        $balance_data[] = [
            'date' => $start_date,
            'balance' => $running_balance
        ];
        
        while($row = $result->fetch_assoc()) {
            $running_balance += $row['daily_change'];
            $balance_data[] = [
                'date' => $row['date'],
                'balance' => $running_balance
            ];
        }
        $data = $balance_data;
        break;

    case 'categories':
        $query = "SELECT type, SUM(amount) as total
                 FROM transactions 
                 WHERE user_id = ? AND created_at BETWEEN ? AND ?
                 GROUP BY type";
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param('iss', $user_id, $start_date, $end_date);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $category_data = [
            'income' => 0,
            'charge' => 0
        ];
        while($row = $result->fetch_assoc()) {
            $category_data[$row['type']] = $row['total'];
        }
        $data = $category_data;
        break;

    case 'trend':
        $query = "SELECT DATE_FORMAT(created_at, '%Y-%m') as month,
                        SUM(CASE WHEN type='income' THEN amount ELSE 0 END) as income,
                        SUM(CASE WHEN type='charge' THEN amount ELSE 0 END) as expenses
                 FROM transactions 
                 WHERE user_id = ? AND created_at BETWEEN ? AND ?
                 GROUP BY DATE_FORMAT(created_at, '%Y-%m')
                 ORDER BY month";
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param('iss', $user_id, $start_date, $end_date);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $trend_data = [];
        while($row = $result->fetch_assoc()) {
            $trend_data[] = [
                'month' => $row['month'],
                'income' => $row['income'],
                'expenses' => $row['expenses']
            ];
        }
        $data = $trend_data;
        break;
}

// Debug data before sending
error_log("Data to be sent: " . json_encode($data));

// Ensure numbers are properly formatted
array_walk_recursive($data, function(&$item) {
    if (is_numeric($item)) {
        $item = floatval($item);
    }
});

echo json_encode($data, JSON_NUMERIC_CHECK);
