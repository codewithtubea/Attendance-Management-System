<?php
require_once 'includes/auth.php';
require_once 'includes/database.php';
redirectIfNotLoggedIn();

if (!isStudent()) {
    header('Location: dashboard.php');
    exit();
}

// Get enrolled courses for the student
$stmt = $pdo->prepare("
    SELECT c.*, u.name as faculty_name 
    FROM enrollments e 
    JOIN courses c ON e.course_id = c.id 
    JOIN users u ON c.faculty_id = u.id 
    WHERE e.student_id = ? AND e.status = 'approved'
");
$stmt->execute([$_SESSION['user_id']]);
$courses = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Ashesi Portal — Courses</title>
  <link rel="stylesheet" href="style.css" />
</head>
<body>
  <div class="app">
    <aside class="sidebar collapsed">
      <div class="brand">
        <div class="brand-mark">A</div>
        <div class="brand-text">
          <strong>Ashesi</strong>
          <small>Portal</small>
        </div>
      </div>
      <nav class="nav">
        <button class="nav-item" onclick="location.href='student_dashboard.php'"><span class="icon">🏠</span><span class="label">Dashboard</span></button>
        <button class="nav-item active"><span class="icon">📚</span><span class="label">Courses</span></button>
        <button class="nav-item logout" onclick="location.href='logout.php'"><span class="icon">🚪</span><span class="label">Logout</span></button>
      </nav>
    </aside>

    <main class="main">
      <header class="topbar">
        <div class="search"><input placeholder="Search courses..." /></div>
        <div class="top-actions">
          <div class="profile">
            <div class="avatar"><?php echo strtoupper(substr($_SESSION['name'], 0, 1)); ?></div>
            <div class="profile-name"><?php echo htmlspecialchars($_SESSION['name']); ?></div>
          </div>
        </div>
      </header>

      <div class="content" id="coursesPage">
        <section class="section">
          <div class="section-header">
            <h2>Your Enrolled Courses</h2>
            <div class="muted">Courses you are currently enrolled in</div>
          </div>

          <?php if (empty($courses)): ?>
            <div class="muted" style="text-align: center; padding: 2rem;">
              <p>You are not enrolled in any courses yet.</p>
              <a href="student_dashboard.php" class="btn primary" style="margin-top: 1rem;">Browse Available Courses</a>
            </div>
          <?php else: ?>
            <div class="courses-grid large">
              <?php foreach ($courses as $course): ?>
                <article class="course-card">
                  <h3><?php echo htmlspecialchars($course['course_name']); ?></h3>
                  <p class="muted">Prof. <?php echo htmlspecialchars($course['faculty_name']); ?></p>
                  <div class="course-meta">
                    <span class="badge lecture"><?php echo htmlspecialchars($course['course_code']); ?></span>
                    <span class="badge lab">Enrolled</span>
                  </div>
                  <p style="margin: 0.5rem 0; font-size: 0.9rem;"><?php echo htmlspecialchars($course['description']); ?></p>
                  <div class="card-actions">
                    <button class="btn primary">View Materials</button>
                    <button class="btn outline">Course Info</button>
                  </div>
                </article>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </section>
      </div>
    </main>
  </div>

  <script src="script.js"></script>
</body>
</html>