<?php
require_once 'includes/database.php';

session_start();
if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $role = $_POST['role'];
    
    // Validate input
    if (empty($name) || empty($email) || empty($password) || empty($confirm_password)) {
        $errors[] = "All fields are required";
    }
    
    if ($password !== $confirm_password) {
        $errors[] = "Passwords do not match";
    }
    
    if (strlen($password) < 8) {
        $errors[] = "Password must be at least 8 characters long";
    }
    
    if (!preg_match('/[A-Z]/', $password)) {
        $errors[] = "Password must contain at least one uppercase letter";
    }
    
    if (!preg_match('/[a-z]/', $password)) {
        $errors[] = "Password must contain at least one lowercase letter";
    }
    
    if (!preg_match('/[0-9]/', $password)) {
        $errors[] = "Password must contain at least one number";
    }
    
    // Email validation
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format";
    }
    
    if (!preg_match('/@ashesi\.edu\.gh$/', $email)) {
        $errors[] = "Please use an Ashesi University email address (@ashesi.edu.gh)";
    }
    
    // Role validation
    if (!in_array($role, ['student', 'faculty'])) {
        $errors[] = "Invalid role selected";
    }
    
    if (empty($errors)) {
        // Check if email already exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        
        if ($stmt->fetch()) {
            $errors[] = "Email already registered. Please login instead.";
        } else {
            // Hash password
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            // Insert user
            $stmt = $pdo->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)");
            
            try {
                if ($stmt->execute([$name, $email, $hashed_password, $role])) {
                    $_SESSION['success'] = "Registration successful! You can now login.";
                    header('Location: login.php');
                    exit();
                }
            } catch (PDOException $e) {
                error_log("Registration failed: " . $e->getMessage());
                $errors[] = "Registration failed. Please try again.";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Ashesi Portal</title>
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
                <a href="login.php" class="nav-item">
                    <span class="icon">🔐</span>
                    <span class="label">Login</span>
                </a>
                <a href="register.php" class="nav-item active">
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
                        <h2 style="text-align: center; margin-bottom: 0.5rem;">Create Account</h2>
                        <p class="muted" style="text-align: center; margin-bottom: 2rem;">
                            Join the Ashesi Portal community
                        </p>

                        <?php if (!empty($errors)): ?>
                            <div class="alert error">
                                <?php foreach ($errors as $error): ?>
                                    <p><?php echo htmlspecialchars($error); ?></p>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <form method="POST" action="register.php">
                            <div class="form-group">
                                <label class="form-label">Full Name</label>
                                <input type="text" name="name" class="form-input" required
                                       value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>">
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Email Address</label>
                                <input type="email" name="email" class="form-input" required
                                       value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>"
                                       placeholder="user@ashesi.edu.gh">
                                <small class="muted">Must be an Ashesi University email</small>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Password</label>
                                <div style="position: relative;">
                                    <input type="password" name="password" id="password" class="form-input" required
                                           minlength="8" style="padding-right: 50px;">
                                    <button type="button" class="toggle-password" 
                                            style="position: absolute; right: 12px; top: 50%; transform: translateY(-50%); background: none; border: none; color: var(--text-light); cursor: pointer;">
                                        Show
                                    </button>
                                </div>
                                <small class="muted">Minimum 8 characters with uppercase, lowercase, and number</small>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Confirm Password</label>
                                <div style="position: relative;">
                                    <input type="password" name="confirm_password" id="confirm_password" class="form-input" required
                                           style="padding-right: 50px;">
                                    <button type="button" class="toggle-password" 
                                            style="position: absolute; right: 12px; top: 50%; transform: translateY(-50%); background: none; border: none; color: var(--text-light); cursor: pointer;">
                                        Show
                                    </button>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">I am a:</label>
                                <select name="role" class="form-select" required>
                                    <option value="student" <?php echo (isset($_POST['role']) && $_POST['role'] === 'student') ? 'selected' : ''; ?>>Student</option>
                                    <option value="faculty" <?php echo (isset($_POST['role']) && $_POST['role'] === 'faculty') ? 'selected' : ''; ?>>Faculty Member</option>
                                </select>
                            </div>
                            
                            <button type="submit" class="btn primary" style="width: 100%; margin: 1.5rem 0;">
                                Create Account
                            </button>
                        </form>

                        <div style="text-align: center; padding-top: 1.5rem; border-top: 1px solid var(--border-color);">
                            <p class="muted">Already have an account?</p>
                            <a href="login.php" class="btn outline" style="width: 100%; margin-top: 0.5rem;">
                                Login to Existing Account
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