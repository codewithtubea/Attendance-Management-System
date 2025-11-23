<?php
session_start();
require_once 'includes/auth.php';
require_once 'includes/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $role = $_POST['role'];
    
    $errors = [];
    
    if (empty($name) || empty($email) || empty($password)) {
        $errors[] = "All fields are required";
    }
    
    if ($password !== $confirm_password) {
        $errors[] = "Passwords do not match";
    }
    
    if (strlen($password) < 6) {
        $errors[] = "Password must be at least 6 characters";
    }
    
    // Ashesi email validation
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format";
    } elseif (!preg_match('/@ashesi\.edu\.gh$/', $email)) {
        $errors[] = "Please use an Ashesi University email address (@ashesi.edu.gh)";
    }
    
    // Check if email already exists in database
    if (empty($errors)) {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $errors[] = "Email already registered. Please login instead.";
        }
    }
    
    if (empty($errors)) {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        $stmt = $pdo->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)");
        if ($stmt->execute([$name, $email, $hashed_password, $role])) {
            header('Location: login.php?success=Registration successful! Please login.');
            exit();
        } else {
            $errors[] = "Registration failed. Please try again.";
        }
    }
}

$success = '';
if (isset($_GET['success'])) {
    $success = $_GET['success'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Ashesi Portal — Register</title>
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
    .email-hint {
      font-size: 0.8rem;
      color: #666;
      margin-top: 0.25rem;
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
        <button class="nav-item" onclick="location.href='login.php'">
          <span class="icon">🔐</span>
          <span class="label">Login</span>
        </button>
        <button class="nav-item active">
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
            <h2>Create Account</h2>
            <div class="muted">Join the Ashesi Portal community</div>
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

              <?php if ($success): ?>
                <div style="background: #e8f5e8; color: #2e7d32; padding: 12px; border-radius: 8px; margin-bottom: 1rem;">
                  <p style="margin: 0;"><?php echo htmlspecialchars($success); ?></p>
                </div>
              <?php endif; ?>

              <form method="POST" action="register.php" onsubmit="return validateForm()">
                <div style="margin-bottom: 1rem;">
                  <label style="display: block; margin-bottom: 0.5rem; font-weight: 600;">Full Name</label>
                  <input type="text" name="name" id="name" required 
                         style="width: 100%; padding: 12px; border: 1px solid #e6e6e9; border-radius: 8px;"
                         value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>">
                </div>
                
                <div style="margin-bottom: 1rem;">
                  <label style="display: block; margin-bottom: 0.5rem; font-weight: 600;">Email Address</label>
                  <input type="email" name="email" id="email" required
                         style="width: 100%; padding: 12px; border: 1px solid #e6e6e9; border-radius: 8px;"
                         value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>"
                         placeholder="example@ashesi.edu.gh">
                  <div class="email-hint">Must be an Ashesi University email address</div>
                </div>
                
                <div style="margin-bottom: 1rem;">
                  <label style="display: block; margin-bottom: 0.5rem; font-weight: 600;">Password</label>
                  <div class="password-container">
                    <input type="password" name="password" id="password" required minlength="6"
                           style="width: 100%; padding: 12px; border: 1px solid #e6e6e9; border-radius: 8px; padding-right: 40px;">
                    <button type="button" class="toggle-password" onclick="togglePassword('password')">👁️</button>
                  </div>
                </div>
                
                <div style="margin-bottom: 1rem;">
                  <label style="display: block; margin-bottom: 0.5rem; font-weight: 600;">Confirm Password</label>
                  <div class="password-container">
                    <input type="password" name="confirm_password" id="confirm_password" required
                           style="width: 100%; padding: 12px; border: 1px solid #e6e6e9; border-radius: 8px; padding-right: 40px;">
                    <button type="button" class="toggle-password" onclick="togglePassword('confirm_password')">👁️</button>
                  </div>
                </div>
                
                <div style="margin-bottom: 1.5rem;">
                  <label style="display: block; margin-bottom: 0.5rem; font-weight: 600;">I am a:</label>
                  <select name="role" required style="width: 100%; padding: 12px; border: 1px solid #e6e6e9; border-radius: 8px;">
                    <option value="student" <?php echo (isset($_POST['role']) && $_POST['role'] === 'student') ? 'selected' : ''; ?>>Student</option>
                    <option value="faculty" <?php echo (isset($_POST['role']) && $_POST['role'] === 'faculty') ? 'selected' : ''; ?>>Faculty Member</option>
                  </select>
                </div>
                
                <button type="submit" class="btn primary" style="width: 100%;">Create Account</button>
              </form>

              <div style="text-align: center; margin-top: 1.5rem; padding-top: 1.5rem; border-top: 1px solid #e6e6e9;">
                <p class="muted">Already have an account?</p>
                <button onclick="location.href='login.php'" class="btn outline" style="width: 100%;">
                  Login to Existing Account
                </button>
              </div>
            </div>
          </div>
        </section>
      </div>
    </main>
  </div>

  <script>
  function togglePassword(fieldId) {
    const passwordInput = document.getElementById(fieldId);
    const toggleButton = passwordInput.parentNode.querySelector('.toggle-password');
    
    if (passwordInput.type === 'password') {
      passwordInput.type = 'text';
      toggleButton.textContent = '🔒';
    } else {
      passwordInput.type = 'password';
      toggleButton.textContent = '👁️';
    }
  }

  function validateForm() {
    const password = document.getElementById('password').value;
    const confirmPassword = document.getElementById('confirm_password').value;
    const email = document.getElementById('email').value;
    
    if (password.length < 6) {
      alert('Password must be at least 6 characters long');
      return false;
    }
    
    if (password !== confirmPassword) {
      alert('Passwords do not match');
      return false;
    }
    
    // Ashesi email validation
    if (!email.includes('@ashesi.edu.gh')) {
      alert('Please use an Ashesi University email address (@ashesi.edu.gh)');
      return false;
    }
    
    return true;
  }
  </script>
</body>
</html>