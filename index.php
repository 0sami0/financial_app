<?php
// index.php - Login and Registration
session_start();
require 'db.php';
require 'csrf.php';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if(!isset($_POST['csrf_token']) || !csrf_validate_token($_POST['csrf_token'])) {
        $error = 'Invalid CSRF token.';
    } else {
        $username = trim($_POST['username']);
        $password = $_POST['password'];
        if (isset($_POST['register'])) {
            // Register
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare('INSERT INTO users (username, password) VALUES (?, ?)');
            $stmt->bind_param('ss', $username, $hash);
            if ($stmt->execute()) {
                session_regenerate_id(true);
                $_SESSION['user_id'] = $stmt->insert_id;
                header('Location: dashboard.php');
                exit;
            } else {
                $error = 'Username already exists.';
            }
        } else {
            // Login
            $stmt = $conn->prepare('SELECT id, password FROM users WHERE username = ?');
            $stmt->bind_param('s', $username);
            $stmt->execute();
            $stmt->store_result();
            if ($stmt->num_rows > 0) {
                $stmt->bind_result($id, $hash);
                $stmt->fetch();
                if (password_verify($password, $hash)) {
                    session_regenerate_id(true);
                    $_SESSION['user_id'] = $id;
                    header('Location: dashboard.php');
                    exit;
                } else {
                    $error = 'Invalid password.';
                }
            } else {
                $error = 'User not found.';
            }
        }
    }
}
$page_title = "Fluss - Login or Register";
include 'header.php';
?>
<main class="container">
    <section class="card" style="max-width: 400px; margin: 100px auto;">
        <h2 style="text-align: center; margin-bottom: 2rem;">Welcome to Fluss</h2>
        <?php if ($error): ?>
            <div class="alert alert-danger"> <?php echo $error; ?> </div>
        <?php endif; ?>
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?php echo csrf_generate_token(); ?>">
            <div class="form-group">
                <label class="form-label" for="username">Username</label>
                <input type="text" id="username" name="username" class="form-input" required>
            </div>
            <div class="form-group">
                <label class="form-label" for="password">Password</label>
                <input type="password" id="password" name="password" class="form-input" required>
            </div>
            <div class="btn-group" style="justify-content: center;">
                <button type="submit" name="login" class="btn btn-primary">Login</button>
                <button type="submit" name="register" class="btn btn-primary" style="background-color: var(--success);">Register</button>
            </div>
        </form>
    </section>
</main>
<?php include 'footer.php'; ?>
