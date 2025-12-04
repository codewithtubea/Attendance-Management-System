<?php
require_once 'includes/database.php';

// Prevent session fixation
session_start();
if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    
    // Validate input
    if (empty($email) || empty($password)) {
        $errors[] = "Email and password are required";
    }
    
    if (empty($errors)) {
        // Prepare statement to prevent SQL injection
        $stmt = $pdo->prepare("SELECT id, name, email, password, role FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password'])) {
            // Regenerate session ID
            session_regenerate_id(true);
            
            // Set session variables
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['name'] = $user['name'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['login_time'] = time();
            
            // Generate CSRF token
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            
            // Set secure cookie parameters
            $cookieParams = session_get_cookie_params();
            session_set_cookie_params([
                'lifetime' => $cookieParams['lifetime'],
                'path' => '/',
                'domain' => $cookieParams['domain'],
                'secure' => isset($_SERVER['HTTPS']),
                'httponly' => true,
                'samesite' => 'Strict'
            ]);
            
           // Redirect based on role
if ($user['role'] === 'faculty') {
    header('Location: faculty_dashboard.php');
} elseif ($user['role'] === 'admin') {
    header('Location: admin_dashboard.php');
} else {
    header('Location: student_dashboard.php');
}
            exit();
        } else {
            $errors[] = "Invalid email or password";
            // Log failed login attempt
            error_log("Failed login attempt for email: $email");
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Ashesi Portal</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="app">
        <aside class="sidebar">
            <div class="brand">
                <div class="brand-mark">A</div>
                <div class="brand-text">
                    <strong>Ashesi</strong>
                    <small>Portal</small>
                </div>
            </div>
            <nav class="nav">
                <a href="login.php" class="nav-item active">
                    <span class="icon">🔐</span>
                    <span class="label">Login</span>
                </a>
                <a href="register.php" class="nav-item">
                    <span class="icon">👤</span>
                    <span class="label">Register</span>
                </a>
            </nav>
        </aside>

        <main class="main">
            <header class="topbar">
                <div class="search">
                    <input type="text" placeholder="Search..." disabled>
                </div>
                <div class="top-actions">
                    <div class="profile">
                        <div class="avatar">?</div>
                    </div>
                </div>
            </header>

            <div class="content">
                <div class="card" style="max-width: 400px; margin: 2rem auto;">
                    <div style="padding: 2rem;">
                        <h2 style="text-align: center; margin-bottom: 0.5rem;">Login to Ashesi Portal</h2>
                        <p class="muted" style="text-align: center; margin-bottom: 2rem;">
                            Enter your credentials to access your dashboard
                        </p>

                        <?php if (!empty($errors)): ?>
                            <div class="alert error">
                                <?php foreach ($errors as $error): ?>
                                    <p><?php echo htmlspecialchars($error); ?></p>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <form method="POST" action="login.php">
                            <div class="form-group">
                                <label class="form-label">Email Address</label>
                                <input type="email" name="email" class="form-input" required 
                                       value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>"
                                       placeholder="user@ashesi.edu.gh">
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Password</label>
                                <div style="position: relative;">
                                    <input type="password" name="password" id="password" class="form-input" required
                                           style="padding-right: 50px;">
                                    <button type="button" class="toggle-password" 
                                            style="position: absolute; right: 12px; top: 50%; transform: translateY(-50%); background: none; border: none; color: var(--text-light); cursor: pointer;">
                                        Show
                                    </button>
                                </div>
                            </div>
                            
                            <button type="submit" class="btn primary" style="width: 100%; margin: 1.5rem 0;">
                                Login to Portal
                            </button>
                        </form>

                        <div style="text-align: center; padding-top: 1.5rem; border-top: 1px solid var(--border-color);">
                            <p class="muted">Don't have an account?</p>
                            <a href="register.php" class="btn outline" style="width: 100%; margin-top: 0.5rem;">
                                Create New Account
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script src="script.js"></script>
</body>
</html>