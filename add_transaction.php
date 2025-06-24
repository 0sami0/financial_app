<?php
// add_transaction.php - Add or edit a transaction
session_start();
require 'db.php';
require 'csrf.php';
if (!isset($_SESSION['user_id'])) { header('Location: index.php'); exit; }
$user_id = $_SESSION['user_id'];
// Ensure $user_id is an integer
$user_id = intval($user_id);

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$type = 'income'; $amount = ''; $description = ''; $created_at = date('Y-m-d\TH:i');
if ($id) {
    $stmt = $conn->prepare('SELECT * FROM transactions WHERE id = ? AND user_id = ?');
    $stmt->bind_param('ii', $id, $user_id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) {
        $type = $row['type'];
        $amount = $row['amount'];
        $description = $row['description'];
        $created_at = date('Y-m-d\TH:i', strtotime($row['created_at']));
    }
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !csrf_validate_token($_POST['csrf_token'])) {
        die('Invalid CSRF token.');
    }
    $type = $_POST['type'];
    $amount = floatval($_POST['amount']);
    $description = $_POST['description'];
    $created_at = $_POST['created_at'] ? date('Y-m-d H:i:s', strtotime($_POST['created_at'])) : date('Y-m-d H:i:s');
    
    if ($id) {
        $stmt = $conn->prepare('UPDATE transactions SET type=?, amount=?, description=?, created_at=? WHERE id=? AND user_id=?');
        $stmt->bind_param('sdssii', $type, $amount, $description, $created_at, $id, $user_id);
        $stmt->execute();
    } else {
        $stmt = $conn->prepare('INSERT INTO transactions (user_id, type, amount, description, created_at) VALUES (?, ?, ?, ?, ?)');
        $stmt->bind_param('isdss', $user_id, $type, $amount, $description, $created_at);
        $stmt->execute();
    }
    header('Location: dashboard.php');
    exit;
}
$page_title = $id ? 'Edit Transaction - Fluss' : 'Add Transaction - Fluss';
include 'header.php';
?>
<main class="container">
    <div class="card" style="max-width: 500px; margin: 2rem auto;">
        <h2 style="margin-bottom: 1.5rem;"><?php echo $id ? 'Edit' : 'Add'; ?> Transaction</h2>
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?php echo csrf_generate_token(); ?>">
            <div class="form-group">
                <label class="form-label">Type</label>
                <select name="type" class="form-input">
                    <option value="income" <?php if($type=='income') echo 'selected'; ?>>Income</option>
                    <option value="charge" <?php if($type=='charge') echo 'selected'; ?>>Charge</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Amount</label>
                <input type="number" step="0.01" name="amount" value="<?php echo htmlspecialchars($amount); ?>" class="form-input" required>
            </div>
            <div class="form-group">
                <label class="form-label">Description</label>
                <input type="text" name="description" value="<?php echo htmlspecialchars($description); ?>" class="form-input" required>
            </div>
            <div class="form-group">
                <label class="form-label">Date &amp; Time</label>
                <input type="datetime-local" name="created_at" value="<?php echo htmlspecialchars($created_at); ?>" class="form-input" required>
            </div>
            <div style="display: flex; gap: 1rem; margin-top: 2rem;">
                <button type="submit" class="btn btn-primary">Save Transaction</button>
                <a href="dashboard.php" class="btn btn-danger">Cancel</a>
            </div>
        </form>
    </div>
</main>
<?php include 'footer.php'; ?>
