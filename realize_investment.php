<?php
// realize_investment.php
session_start();
require 'db.php';
if (!isset($_SESSION['user_id'])) { header('Location: index.php'); exit; }
$user_id = $_SESSION['user_id'];
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($id) {
    // Get investment
    $stmt = $conn->prepare('SELECT * FROM investments WHERE id = ? AND user_id = ? AND status = "active"');
    $stmt->bind_param('ii', $id, $user_id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) {
        // Add to balance as income transaction
        $stmt2 = $conn->prepare('INSERT INTO transactions (user_id, type, amount, description) VALUES (?, "income", ?, ?)');
        $desc = 'Realized investment: ' . $row['description'];
        $stmt2->bind_param('ids', $user_id, $row['amount'], $desc);
        $stmt2->execute();
        // Mark investment as realized
        $stmt3 = $conn->prepare('UPDATE investments SET status = "realized" WHERE id = ? AND user_id = ?');
        $stmt3->bind_param('ii', $id, $user_id);
        $stmt3->execute();
    }
}
header('Location: dashboard.php');
exit;
