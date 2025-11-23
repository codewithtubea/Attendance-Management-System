<?php
require_once 'includes/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    
    $errors = [];
    
    if (empty($email) || empty($password)) {
        $errors[] = "Email and password are required";
    }
    
    if (empty($errors)) {
        $stmt = $pdo->prepare("SELECT id, name, email, password, role FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password'])) {
            session_start();
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['name'] = $user['name'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['role'] = $user['role'];
            
            // Redirect directly to the appropriate dashboard
          if ($user && password_verify($password, $user['password'])) {
    // Remove the session_start() since it's already started
    // session_start();  // COMMENT THIS OUT OR REMOVE IT
    
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['name'] = $user['name'];
    $_SESSION['email'] = $user['email'];
    $_SESSION['role'] = $user['role'];
    
    // Redirect based on role
    if ($user['role'] === 'faculty') {
        header('Location: faculty_dashboard.php');
    } else {
        header('Location: student_dashboard.php');
    }
    exit();

}
        } else {
            $errors[] = "Invalid email or password";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Ashesi Portal — Login</title>
  <link rel="stylesheet" href="style.css" />
  <style>
    .password-container {
      position: relative;
    }
    .toggle-password {
      position: absolute;
      right: 12px;
      top: 50%;
      transform: translateY(-50%);
      background: none;
      border: none;
      cursor: pointer;
      color: #666;
    }
  </style>
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
        <button class="nav-item active">
          <span class="icon">🔐</span>
          <span class="label">Login</span>
        </button>
        <button class="nav-item" onclick="location.href='register.php'">
          <span class="icon">👤</span>
          <span class="label">Register</span>
        </button>
      </nav>
    </aside>

    <main class="main">
      <header class="topbar">
        <div class="search"><input placeholder="Search..." disabled /></div>
        <div class="top-actions">
          <div class="profile">
            <div class="avatar">?</div>
          </div>
        </div>
      </header>

      <div class="content">
        <section class="section">
          <div class="section-header">
            <h2>Login to Ashesi Portal</h2>
            <div class="muted">Enter your credentials to access your dashboard</div>
          </div>

          <div class="courses-grid large" style="max-width: 500px; margin: 0 auto;">
            <div class="course-card">
              <?php if (!empty($errors)): ?>
                <div style="background: #ffe9ea; color: #c8102e; padding: 12px; border-radius: 8px; margin-bottom: 1rem;">
                  <?php foreach ($errors as $error): ?>
                    <p style="margin: 0;"><?php echo htmlspecialchars($error); ?></p>
                  <?php endforeach; ?>
                </div>
              <?php endif; ?>

              <form method="POST" action="login.php">
                <div style="margin-bottom: 1rem;">
                  <label style="display: block; margin-bottom: 0.5rem; font-weight: 600;">Email Address</label>
                  <input type="email" name="email" required 
                         style="width: 100%; padding: 12px; border: 1px solid #e6e6e9; border-radius: 8px;"
                         value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                </div>
                
                <div style="margin-bottom: 1.5rem;">
                  <label style="display: block; margin-bottom: 0.5rem; font-weight: 600;">Password</label>
                  <div class="password-container">
                    <input type="password" name="password" id="password" required 
                           style="width: 100%; padding: 12px; border: 1px solid #e6e6e9; border-radius: 8px; padding-right: 40px;">
                    <button type="button" class="toggle-password" onclick="togglePassword()">👁️</button>
                  </div>
                </div>
                
                <button type="submit" class="btn primary" style="width: 100%;">Login to Portal</button>
              </form>

              <div style="text-align: center; margin-top: 1.5rem; padding-top: 1.5rem; border-top: 1px solid #e6e6e9;">
                <p class="muted">Don't have an account?</p>
                <button onclick="location.href='register.php'" class="btn outline" style="width: 100%;">
                  Create New Account
                </button>
              </div>
            </div>
          </div>
        </section>
      </div>
    </main>
  </div>

  <script>
  function togglePassword() {
    const passwordInput = document.getElementById('password');
    const toggleButton = document.querySelector('.toggle-password');
    
    if (passwordInput.type === 'password') {
      passwordInput.type = 'text';
      toggleButton.textContent = '🔒';
    } else {
      passwordInput.type = 'password';
      toggleButton.textContent = '👁️';
    }
  }
  </script>
</body>
</html>