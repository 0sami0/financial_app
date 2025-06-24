<?php
// add_investment.php - Add or edit an investment
session_start();
require 'db.php';
require 'csrf.php'; // NEW
if (!isset($_SESSION['user_id'])) { header('Location: index.php'); exit; }
$user_id = $_SESSION['user_id'];

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$amount = '';
$description = '';
$status = 'active';
if ($id) {
    $stmt = $conn->prepare('SELECT * FROM investments WHERE id = ? AND user_id = ?');
    $stmt->bind_param('ii', $id, $user_id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) {
        $amount = $row['amount'];
        $description = $row['description'];
        $status = $row['status'];
    }
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !csrf_validate_token($_POST['csrf_token'])) { // NEW
        die('Invalid CSRF token.');
    }
    $amount = floatval($_POST['amount']);
    $description = $_POST['description'];
    $status = $_POST['status'];
    if ($id) {
        $stmt = $conn->prepare('UPDATE investments SET amount=?, description=?, status=? WHERE id=? AND user_id=?');
        $stmt->bind_param('dssii', $amount, $description, $status, $id, $user_id);
        $stmt->execute();
    } else {
        $stmt = $conn->prepare('INSERT INTO investments (user_id, amount, description, status) VALUES (?, ?, ?, ?)');
        $stmt->bind_param('idss', $user_id, $amount, $description, $status);
        $stmt->execute();
    }
    header('Location: dashboard.php');
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
    <title><?php echo $id ? 'Edit' : 'Add'; ?> Investment - Fluss</title>
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
            <h2 style="margin-bottom: 1.5rem;"><?php echo $id ? 'Edit' : 'Add'; ?> Investment</h2>
            
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?php echo csrf_generate_token(); ?>"> <!-- NEW -->
                <div class="form-group">
                    <label class="form-label">Amount</label>
                    <input type="number" 
                           step="0.01" 
                           name="amount" 
                           value="<?php echo htmlspecialchars($amount); ?>" 
                           class="form-input"
                           required>
                </div>

                <div class="form-group">
                    <label class="form-label">Description</label>
                    <input type="text" 
                           name="description" 
                           value="<?php echo htmlspecialchars($description); ?>" 
                           class="form-input"
                           required>
                </div>

                <div class="form-group">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-input">
                        <option value="active" <?php if($status=='active')echo'selected';?>>Active Investment</option>
                        <option value="realized" <?php if($status=='realized')echo'selected';?>>Realized Investment</option>
                    </select>
                </div>

                <div style="display: flex; gap: 1rem; margin-top: 2rem;">
                    <button type="submit" class="btn btn-primary">Save Investment</button>
                    <a href="dashboard.php" class="btn btn-danger">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
