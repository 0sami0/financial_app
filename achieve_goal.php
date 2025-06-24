<?php
session_start();
require 'db.php';
if (!isset($_SESSION['user_id'])) { header('Location: index.php'); exit; }
$user_id = intval($_SESSION['user_id']);

$goal_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if (!$goal_id) {
    header('Location: dashboard.php');
    exit;
}

// Fetch the selected goal
$stmt = $conn->prepare('SELECT * FROM goals WHERE id = ? AND user_id = ?');
$stmt->bind_param('ii', $goal_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();
if (!$goal = $result->fetch_assoc()) {
    header('Location: dashboard.php');
    exit;
}

$goal_amount = floatval($goal['amount']);
$goal_desc = $goal['description'];

// Compute user's current balance
$stmt = $conn->prepare("SELECT COALESCE(SUM(CASE WHEN type='income' THEN amount ELSE -amount END), 0) as balance FROM transactions WHERE user_id = ?");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$res = $stmt->get_result();
$row = $res->fetch_assoc();
$current_balance = floatval($row['balance']);

// Ensure there is enough balance
if ($current_balance < $goal_amount) {
    header('Location: dashboard.php?error=insufficient_balance');
    exit;
}

// Insert a transaction deducting the goal amount
$desc = "Goal Achieved: " . $goal_desc;
$stmt = $conn->prepare('INSERT INTO transactions (user_id, type, amount, description) VALUES (?, "charge", ?, ?)');
$stmt->bind_param('ids', $user_id, $goal_amount, $desc);
$stmt->execute();

// Delete the goal as it has been achieved
$stmt = $conn->prepare('DELETE FROM goals WHERE id = ? AND user_id = ?');
$stmt->bind_param('ii', $goal_id, $user_id);
$stmt->execute();

header('Location: dashboard.php');
exit;
?>
