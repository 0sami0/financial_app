<?php
session_start();
require 'db.php';
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}
$user_id = $_SESSION['user_id'];
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="transactions.csv"');

$output = fopen('php://output', 'w');
fputcsv($output, array('ID','User ID','Type','Amount','Description','Created At'));

$res = $conn->query("SELECT id, user_id, type, amount, description, created_at FROM transactions WHERE user_id = $user_id");
while ($row = $res->fetch_assoc()) {
    fputcsv($output, $row);
}
fclose($output);
exit;
?>
