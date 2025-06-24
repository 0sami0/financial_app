<?php
session_start();
require 'db.php';
if(!isset($_SESSION['user_id'])) { header('Location: index.php'); exit; }
$user_id = $_SESSION['user_id'];

// Process form submission to set/update the monthly budget
if($_SERVER['REQUEST_METHOD'] === 'POST'){
    $monthly_budget = floatval($_POST['monthly_budget']);
    $stmt = $conn->prepare("SELECT id FROM budgets WHERE user_id = ?");
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $stmt->store_result();
    if($stmt->num_rows > 0){
        // Update existing budget
        $stmt_update = $conn->prepare("UPDATE budgets SET monthly_budget = ? WHERE user_id = ?");
        $stmt_update->bind_param('di', $monthly_budget, $user_id);
        $stmt_update->execute();
    }else{
        // Insert new budget
        $stmt_insert = $conn->prepare("INSERT INTO budgets (user_id, monthly_budget) VALUES (?, ?)");
        $stmt_insert->bind_param('id', $user_id, $monthly_budget);
        $stmt_insert->execute();
    }
    header("Location: budget.php");
    exit;
}

// Retrieve current budget if available
$stmt = $conn->prepare("SELECT monthly_budget FROM budgets WHERE user_id = ?");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$result = $stmt->get_result();
$budget = $result->fetch_assoc();
$current_budget = $budget ? floatval($budget['monthly_budget']) : 0;

// Calculate total expenses for the current month (only 'charge' transactions)
$firstDay = date('Y-m-01');
$lastDay = date('Y-m-t');
$stmt_exp = $conn->prepare("SELECT COALESCE(SUM(amount), 0) as total_expenses FROM transactions WHERE user_id = ? AND type = 'charge' AND DATE(created_at) BETWEEN ? AND ?");
$stmt_exp->bind_param('iss', $user_id, $firstDay, $lastDay);
$stmt_exp->execute();
$res_exp = $stmt_exp->get_result();
$row_exp = $res_exp->fetch_assoc();
$total_expenses = floatval($row_exp['total_expenses']);

// Calculate budget usage percentage
$usage_percent = $current_budget > 0 ? ($total_expenses / $current_budget) * 100 : 0;
$page_title = "Budget - Fluss Money Management";
include 'header.php';
?>
<main class="container">
    <div class="card" style="max-width: 600px; margin: 2rem auto;">
        <h2>Monthly Budget Overview</h2>
        <?php if($current_budget > 0): ?>
            <p>Your monthly budget: <strong>MAD <?php echo number_format($current_budget, 2); ?></strong></p>
            <p>Total expenses for <?php echo date('F Y'); ?>: <strong>MAD <?php echo number_format($total_expenses, 2); ?></strong></p>
            <p>Budget usage: <strong><?php echo number_format($usage_percent, 1); ?>%</strong></p>
            <?php if($usage_percent >= 100): ?>
                <p style="color: var(--danger);">You have exceeded your budget. Please review your expenses.</p>
            <?php elseif($usage_percent >= 80): ?>
                <p style="color: var(--warning);">You're close to your budget limit. Consider adjusting your spending.</p>
            <?php else: ?>
                <p style="color: var(--success);">Great job! You are within your budget.</p>
            <?php endif; ?>
        <?php else: ?>
            <p>You haven't set a monthly budget yet.</p>
        <?php endif; ?>
        <hr>
        <h3><?php echo $current_budget > 0 ? "Update" : "Set"; ?> Your Monthly Budget</h3>
        <form method="post">
            <div class="form-group">
                <label class="form-label" for="monthly_budget">Monthly Budget (MAD)</label>
                <input type="number" step="0.01" name="monthly_budget" id="monthly_budget" class="form-input" value="<?php echo $current_budget > 0 ? $current_budget : ''; ?>" required>
            </div>
            <div style="display: flex; gap: 1rem; margin-top: 1rem;">
                <button type="submit" class="btn btn-primary"><?php echo $current_budget > 0 ? "Update Budget" : "Set Budget"; ?></button>
                <a href="dashboard.php" class="btn btn-danger">Back to Dashboard</a>
            </div>
        </form>
    </div>
</main>
<?php include 'footer.php'; ?>
