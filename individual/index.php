<?php
session_start();
if (isset($_SESSION['user_id'])) {
    require_once 'includes/auth.php';
    if (isFaculty()) {
        header('Location: faculty_dashboard.php');
    } elseif (isAdmin()) {
        header('Location: admin_dashboard.php');
    } else {
        header('Location: student_dashboard.php');
    }
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ashesi Portal - Attendance Management</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <style>
        .landing-page {
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
            min-height: 100vh;
        }
        
        .hero {
            padding: 4rem 2rem;
            text-align: center;
            background: linear-gradient(135deg, var(--primary-red), var(--accent-red));
            color: white;
            border-radius: 0 0 2rem 2rem;
        }
        
        .hero h1 {
            font-size: 3rem;
            font-weight: 700;
            margin-bottom: 1rem;
        }
        
        .hero p {
            font-size: 1.2rem;
            opacity: 0.9;
            max-width: 600px;
            margin: 0 auto 2rem;
        }
        
        .features {
            padding: 4rem 2rem;
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 2rem;
            margin-top: 3rem;
        }
        
        .feature-card {
            background: white;
            padding: 2rem;
            border-radius: var(--radius-md);
            box-shadow: var(--shadow-md);
            text-align: center;
            transition: var(--transition);
        }
        
        .feature-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-lg);
        }
        
        .feature-icon {
            font-size: 2.5rem;
            margin-bottom: 1rem;
            color: var(--primary-red);
        }
        
        .cta {
            padding: 4rem 2rem;
            background: var(--light-red);
            text-align: center;
        }
        
        .cta-content {
            max-width: 600px;
            margin: 0 auto;
        }
        
        .footer {
            padding: 2rem;
            text-align: center;
            background: var(--dark-bg);
            color: white;
        }
    </style>
</head>
<body class="landing-page">
    <div class="hero">
        <h1>Ashesi Portal</h1>
        <p>Streamline course management and attendance tracking for Ashesi University</p>
        <div style="display: flex; gap: 1rem; justify-content: center; margin-top: 2rem;">
            <a href="login.php" class="btn" style="background: white; color: var(--primary-red);">Login</a>
            <a href="register.php" class="btn outline" style="border-color: white; color: white;">Register</a>
        </div>
    </div>

    <div class="features">
        <h2 style="text-align: center; margin-bottom: 1rem;">Features</h2>
        <p style="text-align: center; color: var(--text-light); max-width: 600px; margin: 0 auto;">
            Everything you need for efficient course management
        </p>
        
        <div class="features-grid">
            <div class="feature-card">
                <div class="feature-icon">📚</div>
                <h3>Course Management</h3>
                <p>Create and manage courses with ease</p>
            </div>
            
            <div class="feature-card">
                <div class="feature-icon">👥</div>
                <h3>Student Enrollment</h3>
                <p>Handle enrollment requests efficiently</p>
            </div>
            
            <div class="feature-card">
                <div class="feature-icon">📊</div>
                <h3>Attendance Tracking</h3>
                <p>Monitor student participation</p>
            </div>
            
            <div class="feature-card">
                <div class="feature-icon">🔒</div>
                <h3>Secure Platform</h3>
                <p>Enterprise-grade security for your data</p>
            </div>
        </div>
    </div>

    <div class="cta">
        <div class="cta-content">
            <h2 style="margin-bottom: 1rem;">Ready to Get Started?</h2>
            <p style="margin-bottom: 2rem; color: var(--text-light);">
                Join hundreds of faculty and students at Ashesi University
            </p>
            <a href="register.php" class="btn primary" style="font-size: 1.1rem; padding: 1rem 2rem;">
                Create Your Account
            </a>
        </div>
    </div>

    <div class="footer">
        <p>&copy; 2024 Ashesi University. All rights reserved.</p>
        <p style="opacity: 0.8; margin-top: 0.5rem; font-size: 0.9rem;">
            Built with security and usability in mind
        </p>
    </div>

    <script src="landing.js"></script>
</body>
</html>