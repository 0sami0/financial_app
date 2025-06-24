<?php
// add_goal.php - Add or edit a goal
session_start();
require 'db.php';
require 'csrf.php'; // NEW
if (!isset($_SESSION['user_id'])) { header('Location: index.php'); exit; }
$user_id = intval($_SESSION['user_id']);

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$amount = '';
$description = '';
$priority = 'Medium';
$progress = 0;
if ($id) {
    $stmt = $conn->prepare('SELECT * FROM goals WHERE id = ? AND user_id = ?');
    $stmt->bind_param('ii', $id, $user_id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) {
        $amount = $row['amount'];
        $description = $row['description'];
        $priority = isset($row['priority']) ? $row['priority'] : 'Medium';
        $progress = isset($row['progress']) ? $row['progress'] : 0;
    }
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !csrf_validate_token($_POST['csrf_token'])) { // NEW
        die('Invalid CSRF token.');
    }
    $amount = floatval($_POST['amount']);
    $description = $_POST['description'];
    $priority = $_POST['priority'];
    $progress = floatval($_POST['progress']);
    if ($id) {
        $stmt = $conn->prepare('UPDATE goals SET amount=?, description=?, priority=?, progress=? WHERE id=? AND user_id=?');
        $stmt->bind_param('dssiii', $amount, $description, $priority, $progress, $id, $user_id);
        $stmt->execute();
    } else {
        $stmt = $conn->prepare('INSERT INTO goals (user_id, amount, description, priority, progress) VALUES (?, ?, ?, ?, ?)');
        $stmt->bind_param('idssi', $user_id, $amount, $description, $priority, $progress);
        $stmt->execute();
    }
    header('Location: dashboard.php');
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
    <title><?php echo $id ? 'Edit' : 'Add'; ?> Financial Goal - Fluss</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <nav class="navbar">
        <div class="navbar-content">
            <a href="dashboard.php" class="logo">Fluss</a>
            <a href="dashboard.php" class="btn btn-primary">Back to Dashboard</a>
        </div>
    </nav>

    <div class="container">
        <div class="card" style="max-width: 500px; margin: 2rem auto;">
            <h2 style="margin-bottom: 1.5rem;"><?php echo $id ? 'Edit' : 'Add'; ?> Financial Goal</h2>
            
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?php echo csrf_generate_token(); ?>"> <!-- NEW -->
                <div class="form-group">
                    <label class="form-label">Target Amount</label>
                    <input type="number" 
                           step="0.01" 
                           name="amount" 
                           value="<?php echo htmlspecialchars($amount); ?>" 
                           class="form-input"
                           placeholder="0.00"
                           required>
                </div>

                <div class="form-group">
                    <label class="form-label">Goal Description</label>
                    <input type="text" 
                           name="description" 
                           value="<?php echo htmlspecialchars($description); ?>" 
                           class="form-input"
                           placeholder="e.g., Emergency Fund, New Car, etc."
                           required>
                </div>

                <div class="form-group">
                    <label class="form-label">Priority</label>
                    <select name="priority" class="form-input">
                        <option value="High" <?php echo $priority === 'High' ? 'selected' : ''; ?>>High</option>
                        <option value="Medium" <?php echo $priority === 'Medium' ? 'selected' : ''; ?>>Medium</option>
                        <option value="Low" <?php echo $priority === 'Low' ? 'selected' : ''; ?>>Low</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Progress (%)</label>
                    <input type="number" 
                           step="1" 
                           name="progress" 
                           value="<?php echo htmlspecialchars($progress); ?>" 
                           class="form-input"
                           placeholder="0"
                           required>
                </div>

                <div style="display: flex; gap: 1rem; margin-top: 2rem;">
                    <button type="submit" class="btn btn-primary">Save Goal</button>
                    <a href="dashboard.php" class="btn btn-danger">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
