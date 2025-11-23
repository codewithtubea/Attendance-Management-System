<?php
require_once 'includes/auth.php';
require_once 'includes/database.php';
redirectIfNotLoggedIn();

if (!isStudent()) {
    header('Location: dashboard.php');
    exit();
}

// Get student's enrolled courses
$enrolled_stmt = $pdo->prepare("
    SELECT c.*, u.name as faculty_name 
    FROM enrollments e 
    JOIN courses c ON e.course_id = c.id 
    JOIN users u ON c.faculty_id = u.id 
    WHERE e.student_id = ? AND e.status = 'approved'
");
$enrolled_stmt->execute([$_SESSION['user_id']]);
$enrolled_courses = $enrolled_stmt->fetchAll();

// Get pending requests
$pending_stmt = $pdo->prepare("
    SELECT c.*, u.name as faculty_name 
    FROM enrollments e 
    JOIN courses c ON e.course_id = c.id 
    JOIN users u ON c.faculty_id = u.id 
    WHERE e.student_id = ? AND e.status = 'pending'
");
$pending_stmt->execute([$_SESSION['user_id']]);
$pending_courses = $pending_stmt->fetchAll();

// Get available courses (courses not yet requested/enrolled)
$available_stmt = $pdo->prepare("
    SELECT c.*, u.name as faculty_name 
    FROM courses c 
    JOIN users u ON c.faculty_id = u.id 
    WHERE c.id NOT IN (
        SELECT course_id FROM enrollments WHERE student_id = ?
    )
");
$available_stmt->execute([$_SESSION['user_id']]);
$available_courses = $available_stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Ashesi Portal — Student Dashboard</title>
<link rel="stylesheet" href="style.css?v=<?php echo time(); ?>" />
</head>
<body>
  <div class="app">
    <!-- SIDEBAR -->
    <aside class="sidebar" id="sidebar">
      <div class="brand">
        <div class="brand-mark">A</div>
        <div class="brand-text">
          <strong>Ashesi</strong>
          <small>Portal</small>
        </div>
      </div>

      <nav class="nav">
       <a href="student_dashboard.php" class="nav-item active">
  <span class="icon">🏠</span><span class="label">Dashboard</span>
</a>
<a href="courses.php" class="nav-item">
  <span class="icon">📚</span><span class="label">Courses</span>
</a>
<button class="nav-item" onclick="alert('Attendance feature coming soon')">
  <span class="icon">📝</span><span class="label">Attendance</span>
</button>
      </nav>

      <div class="sidebar-footer">
       <button class="nav-item logout" onclick="location.href='logout.php'">
          <span class="icon">🚪</span><span class="label">Logout</span>
        </button>
      </div>

      <button class="collapse-btn" id="collapseBtn" aria-label="Toggle sidebar">◀</button>
    </aside>

    <!-- MAIN -->
    <main class="main">
      <header class="topbar">
        <div class="search">
          <input id="searchInput" placeholder="Search courses or sessions..." />
        </div>
        <div class="top-actions">
          <button id="notifBtn" class="icon-btn" title="Notifications">🔔</button>
          <div class="profile">
            <div class="avatar"><?php echo strtoupper(substr($_SESSION['name'], 0, 1)); ?></div>
            <div class="profile-name"><?php echo htmlspecialchars($_SESSION['name']); ?></div>
          </div>
        </div>
      </header>

      <section class="content" id="home">
        <!-- HERO -->
        <section class="hero">
          <div class="hero-left">
            <h1>Welcome back, <?php echo htmlspecialchars($_SESSION['name']); ?> 👋</h1>
            <p class="muted">Manage your courses and track enrollment requests.</p>
            <div class="hero-actions">
              <a class="btn primary" href="courses.php">View Courses</a>
              <button class="btn outline" id="reportIssueBtn">Available Courses</button>
            </div>
          </div>

          <div class="hero-right">
            <div class="progress-card">
              <div class="progress-header">
                <div>
                  <small class="muted">Enrollment Rate</small>
                  <h2 id="attendancePct">
                    <?php 
                    $total_courses = count($available_courses) + count($enrolled_courses) + count($pending_courses);
                    $enrolled_count = count($enrolled_courses);
                    $rate = $total_courses > 0 ? round(($enrolled_count / $total_courses) * 100) : 0;
                    echo $rate . '%';
                    ?>
                  </h2>
                </div>
              </div>
              <div class="progress-visual" aria-hidden="true">
                <svg viewBox="0 0 36 36" class="circular-chart">
                  <path class="circle-bg"
                        d="M18 2.0845
                           a 15.9155 15.9155 0 0 1 0 31.831
                           a 15.9155 15.9155 0 0 1 0 -31.831"/>
                  <path id="progressBar" class="circle"
                        stroke-dasharray="<?php echo $rate; ?>,100"
                        d="M18 2.0845
                           a 15.9155 15.9155 0 0 1 0 31.831
                           a 15.9155 15.9155 0 0 1 0 -31.831"/>
                </svg>
              </div>
            </div>
          </div>
        </section>

        <!-- STATS CARDS -->
        <section class="stats-grid">
          <div class="stat large">
            <small>Total Available Courses</small>
            <h3 id="totalSessions"><?php echo count($available_courses); ?></h3>
            <p class="muted">Courses you can request to join</p>
          </div>
          <div class="stat">
            <small>Enrolled</small>
            <h3 id="attendedCount"><?php echo count($enrolled_courses); ?></h3>
          </div>
          <div class="stat">
            <small>Pending</small>
            <h3 id="missedCount"><?php echo count($pending_courses); ?></h3>
          </div>
          <div class="stat">
            <small>All Courses</small>
            <h3 id="upcomingCount"><?php echo count($available_courses) + count($enrolled_courses) + count($pending_courses); ?></h3>
          </div>
        </section>

        <!-- ENROLLED COURSES -->
        <section class="section recent">
          <div class="section-header">
            <h2>Your Enrolled Courses</h2>
            <div class="muted">Courses you are currently enrolled in</div>
          </div>

          <?php if (empty($enrolled_courses)): ?>
            <p class="muted">You are not enrolled in any courses yet.</p>
          <?php else: ?>
            <div class="recent-scroll" id="recentScroll">
              <?php foreach ($enrolled_courses as $course): ?>
                <div class="session-card">
                  <strong><?php echo htmlspecialchars($course['course_name']); ?></strong>
                  <div class="muted"><?php echo htmlspecialchars($course['course_code']); ?></div>
                  <div class="meta">
                    <div class="badges">
                      <span class="badge lecture">Approved</span>
                    </div>
                    <div><small class="muted">Prof. <?php echo htmlspecialchars($course['faculty_name']); ?></small></div>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </section>

        <!-- PENDING REQUESTS -->
        <section class="section recent">
          <div class="section-header">
            <h2>Pending Requests</h2>
            <div class="muted">Waiting for faculty approval</div>
          </div>

          <?php if (empty($pending_courses)): ?>
            <p class="muted">No pending enrollment requests.</p>
          <?php else: ?>
            <div class="recent-scroll">
              <?php foreach ($pending_courses as $course): ?>
                <div class="session-card">
                  <strong><?php echo htmlspecialchars($course['course_name']); ?></strong>
                  <div class="muted"><?php echo htmlspecialchars($course['course_code']); ?></div>
                  <div class="meta">
                    <div class="badges">
                      <span class="badge lab">Pending</span>
                    </div>
                    <div><small class="muted">Prof. <?php echo htmlspecialchars($course['faculty_name']); ?></small></div>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </section>
      </section>
    </main>
  </div>

  <!-- AVAILABLE COURSES MODAL -->
  <div class="modal" id="coursesModal" aria-hidden="true">
    <div class="modal-inner" role="dialog" aria-modal="true" style="max-width: 600px;">
      <button class="close" id="closeCoursesModal">✕</button>
      <h3>Available Courses</h3>
      <div class="courses-list">
        <?php if (empty($available_courses)): ?>
          <p class="muted">No available courses at the moment.</p>
        <?php else: ?>
          <?php foreach ($available_courses as $course): ?>
            <div class="course-card" style="margin-bottom: 1rem; padding: 1rem;">
              <h4><?php echo htmlspecialchars($course['course_name']); ?></h4>
              <p class="muted"><?php echo htmlspecialchars($course['course_code']); ?> • Prof. <?php echo htmlspecialchars($course['faculty_name']); ?></p>
              <p><?php echo htmlspecialchars($course['description']); ?></p>
              <form method="POST" action="request_join.php" style="margin-top: 0.5rem;">
                <input type="hidden" name="course_id" value="<?php echo $course['id']; ?>">
                <button type="submit" class="btn primary">Request to Join</button>
              </form>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <script src="script.js"></script>
  <script>
  // Modal functionality for available courses
  document.addEventListener('DOMContentLoaded', function() {
    const reportBtn = document.getElementById('reportIssueBtn');
    const modal = document.getElementById('coursesModal');
    const closeModal = document.getElementById('closeCoursesModal');

    reportBtn?.addEventListener('click', () => {
      modal.style.display = 'flex';
      modal.setAttribute('aria-hidden', 'false');
    });

    closeModal?.addEventListener('click', () => {
      modal.style.display = 'none';
      modal.setAttribute('aria-hidden','true');
    });

    window.addEventListener('click', (ev) => { 
      if (ev.target === modal) {
        modal.style.display = 'none';
        modal.setAttribute('aria-hidden','true');
      }
    });
  });
  </script>
</body>
</html>