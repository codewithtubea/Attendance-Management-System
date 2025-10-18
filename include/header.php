<?php
// Make sure session is started
if(session_status() === PHP_SESSION_NONE){
    session_start();
}

// Get user name for greeting
$name = isset($_SESSION['name']) ? $_SESSION['name'] : "Guest";
$role = isset($_SESSION['role']) ? $_SESSION['role'] : "";
?>

<header class="site-header">
    <div class="header-left">
        <h1>Attendance System</h1>
    </div>
    <div class="header-right">
        <?php if($name !== "Guest"): ?>
            <span class="greeting">Welcome, <?php echo htmlspecialchars($name); ?> (<?php echo ucfirst($role); ?>)</span>
            <a href="../php/logout.php" class="btn-logout">Logout</a>
        <?php else: ?>
            <a href="../php/login.php" class="btn-login">Login</a>
            <a href="../php/register.php" class="btn-register">Register</a>
        <?php endif; ?>
    </div>
</header>

<link rel="stylesheet" href="../css/style.css">
