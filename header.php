<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?php echo isset($page_title) ? $page_title : 'Fluss'; ?></title>
    <meta name="description" content="Fluss - Smart and professional money management solution">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="assets/js/form_validation.js" defer></script>
    <!-- ...existing head elements... -->
</head>
<body>
    <nav class="navbar">
        <div class="navbar-content">
            <a href="dashboard.php" class="logo">Fluss</a>
            <?php if(isset($_SESSION['user_id'])): ?>
                <div>
                    <a href="dashboard.php" class="btn btn-primary">Dashboard</a>
                    <a href="budget.php" class="btn btn-primary">Budget</a> <!-- NEW: Budget link added -->
                    <a href="logout.php" class="btn btn-primary">Logout</a>
                </div>
            <?php else: ?>
                <div>
                    <a href="index.php" class="btn btn-primary">Login</a>
                </div>
            <?php endif; ?>
        </div>
    </nav>
    <!-- ...existing header content... -->
