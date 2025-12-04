<?php
require_once 'includes/auth.php';
require_once 'includes/database.php';
redirectIfNotLoggedIn();

if (!isStudent()) {
    header('Location: faculty_dashboard.php');
    exit();
}

// Get enrolled courses
$stmt = $pdo->prepare("
    SELECT c.*, u.name as faculty_name 
    FROM enrollments e 
    JOIN courses c ON e.course_id = c.id 
    JOIN users u ON c.faculty_id = u.id 
    WHERE e.student_id = ? AND e.status = 'approved'
    ORDER BY c.course_name
");
$stmt->execute([$_SESSION['user_id']]);
$courses = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Courses - Ashesi Portal</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="app">
        <aside class="sidebar" id="sidebar">
            <div class="brand">
                <div class="brand-mark">A</div>
                <div class="brand-text">
                    <strong>Ashesi</strong>
                    <small>Portal</small>
                </div>
            </div>

            <nav class="nav">
                <a href="student_dashboard.php" class="nav-item">
                    <span class="icon">📊</span>
                    <span class="label">Dashboard</span>
                </a>
                <a href="courses.php" class="nav-item active">
                    <span class="icon">📚</span>
                    <span class="label">My Courses</span>
                </a>
                <button class="nav-item" data-modal-open="availableCoursesModal">
                    <span class="icon">➕</span>
                    <span class="label">Join Course</span>
                </button>
            </nav>

            <div class="sidebar-footer">
                <a href="logout.php" class="nav-item logout">
                    <span class="icon">🚪</span>
                    <span class="label">Logout</span>
                </a>
            </div>

            <button class="collapse-btn" id="collapseBtn" aria-label="Toggle sidebar">◀</button>
        </aside>

        <main class="main">
            <header class="topbar">
                <div class="search">
                    <input type="text" id="searchInput" placeholder="Search courses...">
                </div>
                <div class="top-actions">
                    <div class="profile">
                        <div class="avatar"><?php echo strtoupper(substr($_SESSION['name'], 0, 1)); ?></div>
                        <div class="profile-name"><?php echo htmlspecialchars($_SESSION['name']); ?></div>
                    </div>
                </div>
            </header>

            <div class="content">
                <h1 style="margin-bottom: 0.5rem;">My Courses</h1>
                <p class="muted" style="margin-bottom: 2rem;">Courses you are currently enrolled in</p>

                <?php if (empty($courses)): ?>
                    <div class="card" style="padding: 3rem; text-align: center;">
                        <p class="muted" style="font-size: 1.1rem; margin-bottom: 1.5rem;">You are not enrolled in any courses yet.</p>
                        <button class="btn primary" data-modal-open="availableCoursesModal">Browse Available Courses</button>
                    </div>
                <?php else: ?>
                    <div class="courses-grid">
                        <?php foreach ($courses as $course): ?>
                            <div class="course-card">
                                <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 1rem;">
                                    <div>
                                        <h3 style="margin: 0;"><?php echo htmlspecialchars($course['course_name']); ?></h3>
                                        <p class="muted" style="margin-top: 0.25rem;"><?php echo htmlspecialchars($course['course_code']); ?></p>
                                    </div>
                                    <span class="badge approved">Enrolled</span>
                                </div>
                                
                                <p style="margin: 1rem 0; color: var(--text-light); font-size: 0.95rem;">
                                    <?php echo htmlspecialchars($course['description']); ?>
                                </p>
                                
                                <div style="margin: 1rem 0; padding: 1rem; background: var(--light-bg); border-radius: var(--radius-sm);">
                                    <p style="margin: 0; font-size: 0.9rem;">
                                        <strong>Faculty:</strong> Prof. <?php echo htmlspecialchars($course['faculty_name']); ?>
                                    </p>
                                </div>
                                
                                <div style="display: flex; gap: 0.5rem; margin-top: 1.5rem;">
                                    <button class="btn outline small" onclick="alert('Course materials will be available soon')">
                                        View Materials
                                    </button>
                                    <button class="btn outline small" onclick="alert('Course information will be available soon')">
                                        Course Info
                                    </button>
                                    <a href="mark_attendance.php" class="btn outline small" style="text-decoration: none; display: inline-flex; align-items: center; justify-content: center;">
                                        Attendance
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <!-- Available Courses Modal -->
    <div class="modal" id="availableCoursesModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 style="margin: 0;">Join New Course</h3>
                <button class="modal-close" data-modal-close>&times;</button>
            </div>
            <div class="modal-body">
                <?php
                $available_stmt = $pdo->prepare("
                    SELECT c.*, u.name as faculty_name 
                    FROM courses c 
                    JOIN users u ON c.faculty_id = u.id 
                    WHERE c.id NOT IN (
                        SELECT course_id FROM enrollments WHERE student_id = ?
                    )
                    ORDER BY c.course_name
                ");
                $available_stmt->execute([$_SESSION['user_id']]);
                $available_courses = $available_stmt->fetchAll();
                
                if (empty($available_courses)): ?>
                    <p class="muted" style="text-align: center; padding: 2rem;">No available courses at the moment.</p>
                <?php else: ?>
                    <div style="max-height: 400px; overflow-y: auto;">
                        <?php foreach ($available_courses as $course): ?>
                            <div class="course-card" style="margin-bottom: 1rem;">
                                <h4 style="margin-bottom: 0.5rem;"><?php echo htmlspecialchars($course['course_name']); ?></h4>
                                <p class="muted" style="margin-bottom: 0.5rem;"><?php echo htmlspecialchars($course['course_code']); ?></p>
                                <p style="margin-bottom: 1rem; font-size: 0.9rem;"><?php echo htmlspecialchars($course['description']); ?></p>
                                <div style="display: flex; justify-content: space-between; align-items: center;">
                                    <small class="muted">Prof. <?php echo htmlspecialchars($course['faculty_name']); ?></small>
                                    <form method="POST" action="request_join.php" style="display: inline;">
                                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                        <input type="hidden" name="course_id" value="<?php echo $course['id']; ?>">
                                        <button type="submit" class="btn primary small">Request to Join</button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="script.js"></script>
</body>
</html>