<?php
// dashboard.php - User dashboard
session_start();
require 'db.php';
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}
$user_id = $_SESSION['user_id'];

// Fetch transactions
$transactions = [];
$res = $conn->query("SELECT * FROM transactions WHERE user_id = $user_id");
while ($row = $res->fetch_assoc()) {
    $transactions[] = $row;
}
// Fetch debts
$debts = [];
$res = $conn->query("SELECT * FROM debts WHERE user_id = $user_id");
while ($row = $res->fetch_assoc()) {
    $debts[] = $row;
}
// Fetch investments
$investments = [];
$res = $conn->query("SELECT * FROM investments WHERE user_id = $user_id");
while ($row = $res->fetch_assoc()) {
    $investments[] = $row;
}
// Fetch goals
$goals = [];
$res = $conn->query("SELECT * FROM goals WHERE user_id = $user_id");
while ($row = $res->fetch_assoc()) {
    $goals[] = $row;
}

// Calculate balance
$income = 0;
$charges = 0;
foreach ($transactions as $t) {
    if ($t['type'] === 'income') $income += $t['amount'];
    else $charges += $t['amount'];
}
$balance = $income - $charges;
// Calculate total debt
$total_debt = 0;
foreach ($debts as $d) {
    $total_debt += $d['amount'];
}
// Calculate investments (active only)
$active_investments = 0;
$realized_investments = 0;
foreach ($investments as $inv) {
    if ($inv['status'] === 'active') $active_investments += $inv['amount'];
    else $realized_investments += $inv['amount'];
}
$networth = $balance - $total_debt + $active_investments;
// Calculate net average change of total transactions
$transaction_count = count($transactions);
$net_change = $transaction_count > 0 ? ($balance / $transaction_count) : 0;

// NEW: Calculate daily average and projected gains
$firstDate = null;
$lastDate = null;
foreach ($transactions as $t) {
    $time = strtotime($t['created_at']);
    if (!$firstDate || $time < $firstDate) $firstDate = $time;
    if (!$lastDate || $time > $lastDate) $lastDate = $time;
}
$days = ($firstDate && $lastDate) ? max(1, ceil(($lastDate - $firstDate) / 86400)) : 1;
$daily_avg = $balance / $days;
$projection_small = $daily_avg * 30;   // projection for 1 month
$projection_medium = $daily_avg * 90;  // projection for 3 months

// NEW: Calculate budget related variables
$stmt = $conn->prepare("SELECT monthly_budget FROM budgets WHERE user_id = ?");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$result = $stmt->get_result();
$budget = $result->fetch_assoc();
$current_budget = $budget ? floatval($budget['monthly_budget']) : 0;

$firstDay = date('Y-m-01');
$lastDay = date('Y-m-t');
$stmt_exp = $conn->prepare("SELECT COALESCE(SUM(amount), 0) as total_expenses FROM transactions WHERE user_id = ? AND type = 'charge' AND DATE(created_at) BETWEEN ? AND ?");
$stmt_exp->bind_param('iss', $user_id, $firstDay, $lastDay);
$stmt_exp->execute();
$res_exp = $stmt_exp->get_result();
$row_exp = $res_exp->fetch_assoc();
$total_expenses = floatval($row_exp['total_expenses']);
$remaining_budget = $current_budget - $total_expenses;
$usage_percent = $current_budget > 0 ? ($total_expenses / $current_budget) * 100 : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1">
    <meta name="theme-color" content="#1a1a1a">
    <title>Fluss Dashboard</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="assets/js/app.js" defer></script>
</head>
<body>
    <?php if (isset($_GET['deleted'])): ?>
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                showNotification('Item successfully deleted', 'success');
            });
        </script>
    <?php endif; ?>
    <?php
    $page_title = "Fluss Dashboard";
    include 'header.php';
    ?>
<main class="container">
    <div class="stats-grid">
        <div class="stat-card">
            <h3>Current Balance (MAD)</h3>
            <div class="value">MAD <?php echo number_format($balance,2); ?></div>
        </div>
        <div class="stat-card">
            <h3>Net Worth (MAD)</h3>
            <div class="value">MAD <?php echo number_format($networth,2); ?></div>
        </div>
        <div class="stat-card">
            <h3>Total Income (MAD)</h3>
            <div class="value income">MAD <?php echo number_format($income,2); ?></div>
        </div>
        <div class="stat-card">
            <h3>Total Expenses (MAD)</h3>
            <div class="value expense">MAD <?php echo number_format($charges,2); ?></div>
        </div>
        <div class="stat-card">
            <h3>Projected Gain (1 Month, MAD)</h3>
            <div class="value">MAD <?php echo number_format($projection_small,2); ?></div>
        </div>
        <div class="stat-card">
            <h3>Projected Gain (3 Months, MAD)</h3>
            <div class="value">MAD <?php echo number_format($projection_medium,2); ?></div>
        </div>
        <!-- NEW: Budget stat card inserted next to monthly projections -->
        <div class="stat-card">
            <h3>Budget Status</h3>
            <?php if($current_budget > 0): ?>
                <div class="value"><?php echo number_format($remaining_budget,2); ?> MAD Left</div>
                <p><?php echo number_format($usage_percent,1); ?>% used</p>
            <?php else: ?>
                <div class="value">Not Set</div>
            <?php endif; ?>
        </div>
        
        <!-- NEW: Savings Rate Stat Card -->
        <div class="stat-card">
            <h3>Savings Rate</h3>
            <?php 
                $savings_rate = $income > 0 ? (($income - $charges) / $income) * 100 : 0;
            ?>
            <div class="value" data-format="percentage"><?php echo number_format($savings_rate, 1); ?>%</div>
            <p>of income saved</p>
        </div>

        <!-- NEW: Debt-to-Income Ratio Stat Card -->
        <div class="stat-card">
            <h3>Debt-to-Income Ratio</h3>
            <?php 
                $total_debt = 0;
                foreach ($debts as $d) {
                    $total_debt += $d['amount'];
                }
                $debt_to_income = $income > 0 ? ($total_debt / $income) * 100 : 0;
            ?>
            <div class="value" data-format="percentage"><?php echo number_format($debt_to_income, 1); ?>%</div>
            <p>of income in debt</p>
        </div>
    </div>

    <div class="card">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
            <h2>Transactions</h2>
            <a href="add_transaction.php" class="btn btn-primary">Add Transaction</a>
        </div>
        <div class="table-container">
            <table>                    <thead>
                    <tr>
                        <th>Date & Time</th>
                        <th>Type</th>
                        <th>Amount</th>
                        <th>Description</th>
                        <th>Actions</th>
                    </tr>
                    </thead>
                    <tbody>                        <?php foreach ($transactions as $t): ?>
                    <tr>
                        <td><?php echo date('M j, Y g:i A', strtotime($t['created_at'])); ?></td>
                        <td><?php echo htmlspecialchars($t['type']); ?></td>
                        <td class="<?php echo $t['type'] === 'income' ? 'income' : 'expense'; ?>">
                            MAD <?php echo number_format($t['amount'],2); ?>
                        </td>
                        <td><?php echo htmlspecialchars($t['description']); ?></td>
                        <td>
                            <div style="display: flex; gap: 0.5rem;">
                                <a href="add_transaction.php?id=<?php echo $t['id']; ?>" class="btn btn-primary">Edit</a>                                    <button class="btn btn-danger" 
                                        onclick="showDeleteConfirmation('delete_transaction.php?id=<?php echo $t['id']; ?>', 'transaction')">
                                    Delete
                                </button>
                            </div>
                        </td>
                    </tr>                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                <h2>Debts</h2>
                <a href="add_debt.php" class="btn btn-primary">Add Debt</a>
            </div>
            <div class="table-container">
                <table>
                    <thead>
                    <tr>
                        <th>Amount</th>
                        <th>Description</th>
                        <th>Interest Rate (%)</th>
                        <th>1-Year Projection</th>
                        <th>Actions</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($debts as $d): ?>
<tr>                            <td class="expense">MAD <?php echo number_format($d['amount'],2); ?></td>
                        <td><?php echo htmlspecialchars($d['description']); ?></td>
                        <td><?php echo isset($d['interest_rate']) ? number_format($d['interest_rate'],2) : '0.00'; ?>%</td>
                        <td>
                            <?php
                            $rate = isset($d['interest_rate']) ? $d['interest_rate'] : 0;
                            $projection = $d['amount'] * pow(1 + $rate/100, 1);
                            echo 'MAD ' . number_format($projection,2);
                            ?>
                        </td>
                        <td>
                            <div style="display: flex; gap: 0.5rem;">
                                <a href="add_debt.php?id=<?php echo $d['id']; ?>" class="btn btn-primary">Edit</a>                                    <button class="btn btn-danger" 
                                        onclick="showDeleteConfirmation('delete_debt.php?id=<?php echo $d['id']; ?>', 'debt')">
                                    Delete
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>        </div>
        
        <div class="card">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                <h2>Investments</h2>
                <a href="add_investment.php" class="btn btn-primary">Add Investment</a>
            </div>
            <div class="table-container">
                <table>
                    <thead>
                    <tr>
                        <th>Amount</th>
                        <th>Description</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($investments as $inv): ?>
                        <?php if($inv['status'] !== 'active') continue; ?>
                    <tr>                            <td>MAD <?php echo number_format($inv['amount'],2); ?></td>
                        <td><?php echo htmlspecialchars($inv['description']); ?></td>
                        <td><span class="badge" style="background: var(--success); padding: 0.25rem 0.5rem; border-radius: 0.25rem;"><?php echo htmlspecialchars($inv['status']); ?></span></td>
                        <td>
                            <div style="display: flex; gap: 0.5rem;">
                                <a href="add_investment.php?id=<?php echo $inv['id']; ?>" class="btn btn-primary">Edit</a>                                    <button class="btn btn-danger" 
                                        onclick="showDeleteConfirmation('delete_investment.php?id=<?php echo $inv['id']; ?>', 'investment')">
                                    Delete
                                </button>
                                <a href="realize_investment.php?id=<?php echo $inv['id']; ?>" 
                                   class="btn btn-primary" 
                                   style="background-color: var(--success);">Add to Balance</a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                <h2>Financial Goals</h2>
                <a href="add_goal.php" class="btn btn-primary">Add Goal</a>
            </div>
            <div class="table-container">
                <table>
                    <thead>
                    <tr>
                        <th>Amount</th>
                        <th>Description</th>
                        <th>Priority</th>
                        <th>Progress</th>
                        <th>Actions</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($goals as $goal): ?>
                        <?php 
                        // Compute progress from current balance and goal target amount.
                        $prog = ($goal['amount'] > 0) ? min(100, ($balance / $goal['amount']) * 100) : 0;
                        ?>
                        <tr>
                            <td>MAD <?php echo number_format($goal['amount'], 2); ?></td>
                            <td><?php echo htmlspecialchars($goal['description']); ?></td>
                            <td>
                                <span class="badge" style="background-color: <?php echo $goal['priority'] === 'High' ? 'var(--danger)' : ($goal['priority'] === 'Medium' ? 'var(--warning)' : 'var(--success)'); ?>; padding: 0.25rem 0.5rem; border-radius: 0.25rem;">
                                    <?php echo htmlspecialchars($goal['priority']); ?>
                                </span>
                            </td>
                            <td>
                                <div style="background: var(--bg-secondary); border-radius: 0.25rem; overflow: hidden;">
                                    <div style="width: <?php echo $prog; ?>%; background: var(--accent-primary); height: 1rem;"></div>
                                </div>
                                <small><?php echo round($prog); ?>%</small>
                            </td>
                            <td>
                                <div style="display: flex; gap: 0.5rem;">
                                    <a href="add_goal.php?id=<?php echo $goal['id']; ?>" class="btn btn-primary">Edit</a>
                                    <button class="btn btn-danger" 
                                            onclick="showDeleteConfirmation('delete_goal.php?id=<?php echo $goal['id']; ?>', 'goal')">
                                        Delete
                                    </button>
                                    <?php if ($prog >= 100): ?>
                                        <a href="achieve_goal.php?id=<?php echo $goal['id']; ?>" class="btn btn-success">Achieve Goal</a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                <h2>Financial Overview</h2>                <div class="btn-group">
                    <button class="btn btn-primary" onclick="updateChartPeriod('all')">All Time</button>
                    <button class="btn btn-primary" onclick="updateChartPeriod('year')">Year</button>
                    <button class="btn btn-primary" onclick="updateChartPeriod('month')">Month</button>
                    <button class="btn btn-primary" onclick="updateChartPeriod('week')">Week</button>
                </div>
            </div>
            <div class="chart-container" style="position: relative; height: 300px; margin-bottom: 2rem;">
                <canvas id="balanceChart"></canvas>
            </div>        </div>

        <?php /* Temporarily hidden Income vs Expenses chart
        <div class="card">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                <h2>Income vs Expenses</h2>                <div class="btn-group">
                    <button class="btn btn-primary" onclick="updatePieChartPeriod('all')">All Time</button>
                    <button class="btn btn-primary" onclick="updatePieChartPeriod('year')">Year</button>
                    <button class="btn btn-primary" onclick="updatePieChartPeriod('month')">Month</button>
                </div>
            </div>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem;">
                <div class="chart-container" style="position: relative; height: 300px;">
                    <canvas id="categoryPieChart"></canvas>
                </div>
                <div class="chart-container" style="position: relative; height: 300px;">
                    <canvas id="trendChart"></canvas>
                </div>
            </div>
        </div>
        */ ?>

    <!-- NEW: Embedded Budget Overview Section -->
    <?php
        // Fetch budget details for the current user
        $stmt = $conn->prepare("SELECT monthly_budget FROM budgets WHERE user_id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $budget = $result->fetch_assoc();
        $current_budget = $budget ? floatval($budget['monthly_budget']) : 0;

        $firstDay = date('Y-m-01');
        $lastDay = date('Y-m-t');
        $stmt_exp = $conn->prepare("SELECT COALESCE(SUM(amount), 0) as total_expenses FROM transactions WHERE user_id = ? AND type = 'charge' AND DATE(created_at) BETWEEN ? AND ?");
        $stmt_exp->bind_param("iss", $user_id, $firstDay, $lastDay);
        $stmt_exp->execute();
        $res_exp = $stmt_exp->get_result();
        $row_exp = $res_exp->fetch_assoc();
        $total_expenses = floatval($row_exp['total_expenses']);

        $usage_percent = $current_budget > 0 ? ($total_expenses / $current_budget) * 100 : 0;
    ?>
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
    </div>

    <!-- NEW: Export Data Card -->
    <div class="card" style="max-width: 600px; margin: 2rem auto; text-align: center;">
        <h2>Export Your Data</h2>
        <p>Download all your transactions for further analysis.</p>
        <a href="export_data.php" class="btn btn-primary">Download CSV</a>
    </div>

</main>
<?php include 'footer.php'; ?>
</body>
</html>
