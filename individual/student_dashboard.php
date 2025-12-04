<?php
require_once 'includes/auth.php';
require_once 'includes/database.php';
redirectIfNotLoggedIn();

if (!isStudent()) {
    header('Location: faculty_dashboard.php');
    exit();
}

// Get enrolled courses
$enrolled_stmt = $pdo->prepare("
    SELECT c.*, u.name as faculty_name 
    FROM enrollments e 
    JOIN courses c ON e.course_id = c.id 
    JOIN users u ON c.faculty_id = u.id 
    WHERE e.student_id = ? AND e.status = 'approved'
    ORDER BY c.course_name
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
    ORDER BY c.course_name
");
$pending_stmt->execute([$_SESSION['user_id']]);
$pending_courses = $pending_stmt->fetchAll();

// Get available courses
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

// Calculate enrollment rate
$total_enrollable = count($available_courses) + count($enrolled_courses) + count($pending_courses);
$enrollment_rate = $total_enrollable > 0 ? round((count($enrolled_courses) / $total_enrollable) * 100) : 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard - Ashesi Portal</title>
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
                <a href="student_dashboard.php" class="nav-item active">
                    <span class="icon">📊</span>
                    <span class="label">Dashboard</span>
                </a>
                <a href="courses.php" class="nav-item">
                    <span class="icon">📚</span>
                    <span class="label">My Courses</span>
                </a>
                <button class="nav-item" onclick="openAvailableCoursesModal()">
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
                <!-- Welcome Section -->
                <div class="card" style="margin-bottom: 2rem; background: linear-gradient(135deg, var(--primary-red), var(--accent-red)); color: white;">
                    <div style="padding: 2rem;">
                        <h1 style="color: white; margin-bottom: 0.5rem;">Welcome back, <?php echo htmlspecialchars($_SESSION['name']); ?></h1>
                        <p style="opacity: 0.9;">Manage your courses and track your progress</p>
                        <div style="display: flex; gap: 1rem; margin-top: 1.5rem;">
                            <a href="courses.php" class="btn" style="background: white; color: var(--primary-red);">View My Courses</a>
                            <button class="btn outline" data-modal-open="availableCoursesModal" style="border-color: white; color: white;">Join New Course</button>
                        </div>
                    </div>
                </div>

                <!-- Stats Grid -->
                <div class="stats-grid">
                    <div class="stat large">
                        <small>Enrollment Rate</small>
                        <h3><?php echo $enrollment_rate; ?>%</h3>
                        <p class="muted">Courses you are enrolled in</p>
                        <div style="margin-top: 1rem; height: 8px; background: var(--light-red); border-radius: 4px;">
                            <div style="width: <?php echo $enrollment_rate; ?>%; height: 100%; background: var(--primary-red); border-radius: 4px;"></div>
                        </div>
                    </div>
                    <div class="stat">
                        <small>Enrolled Courses</small>
                        <h3><?php echo count($enrolled_courses); ?></h3>
                        <p class="muted">Active courses</p>
                    </div>
                    <div class="stat">
                        <small>Pending Requests</small>
                        <h3><?php echo count($pending_courses); ?></h3>
                        <p class="muted">Awaiting approval</p>
                    </div>
                    <div class="stat">
                        <small>Available Courses</small>
                        <h3><?php echo count($available_courses); ?></h3>
                        <p class="muted">Can join</p>
                    </div>
                </div>

                <!-- Enrolled Courses -->
                <div style="margin: 2rem 0;">
                    <h2 style="margin-bottom: 1rem;">Enrolled Courses</h2>
                    <?php if (empty($enrolled_courses)): ?>
                        <div class="card" style="padding: 2rem; text-align: center;">
                            <p class="muted">You are not enrolled in any courses yet.</p>
                            <button class="btn primary" data-modal-open="availableCoursesModal" style="margin-top: 1rem;">Browse Available Courses</button>
                        </div>
                    <?php else: ?>
                        <div class="courses-grid">
                            <?php foreach ($enrolled_courses as $course): ?>
                                <div class="course-card">
                                    <h3><?php echo htmlspecialchars($course['course_name']); ?></h3>
                                    <p class="muted"><?php echo htmlspecialchars($course['course_code']); ?></p>
                                    <p style="margin: 1rem 0 0.5rem; font-size: 0.9rem; color: var(--text-light);">
                                        <?php echo htmlspecialchars(substr($course['description'], 0, 100)); ?>...
                                    </p>
                                    <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 1rem;">
                                        <span class="badge approved">Approved</span>
                                        <small class="muted">Prof. <?php echo htmlspecialchars($course['faculty_name']); ?></small>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Pending Requests -->
                <?php if (!empty($pending_courses)): ?>
                    <div style="margin: 2rem 0;">
                        <h2 style="margin-bottom: 1rem;">Pending Enrollment Requests</h2>
                        <div class="courses-grid">
                            <?php foreach ($pending_courses as $course): ?>
                                <div class="course-card">
                                    <h3><?php echo htmlspecialchars($course['course_name']); ?></h3>
                                    <p class="muted"><?php echo htmlspecialchars($course['course_code']); ?></p>
                                    <p style="margin: 1rem 0 0.5rem; font-size: 0.9rem; color: var(--text-light);">
                                        <?php echo htmlspecialchars(substr($course['description'], 0, 100)); ?>...
                                    </p>
                                    <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 1rem;">
                                        <span class="badge pending">Pending</span>
                                        <small class="muted">Prof. <?php echo htmlspecialchars($course['faculty_name']); ?></small>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <!-- Available Courses Modal - FIXED VERSION -->
<div class="modal" id="availableCoursesModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 style="margin: 0;">Available Courses to Join</h3>
            <button class="modal-close" onclick="closeAvailableCoursesModal()">&times;</button>
        </div>
        <div class="modal-body">
            <?php
            // Get available courses (courses not yet enrolled in)
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
                <p class="muted" style="text-align: center; padding: 2rem;">
                    No available courses at the moment.
                </p>
            <?php else: ?>
                <div style="max-height: 400px; overflow-y: auto; padding-right: 10px;">
                    <?php foreach ($available_courses as $course): ?>
                        <div class="course-card" style="margin-bottom: 1rem;">
                            <h4 style="margin-bottom: 0.5rem;"><?php echo htmlspecialchars($course['course_name']); ?></h4>
                            <p class="muted" style="margin-bottom: 0.5rem;">
                                <?php echo htmlspecialchars($course['course_code']); ?> • 
                                Prof. <?php echo htmlspecialchars($course['faculty_name']); ?>
                            </p>
                            <p style="margin-bottom: 1rem; font-size: 0.9rem;">
                                <?php echo htmlspecialchars($course['description']); ?>
                            </p>
                            <form method="POST" action="request_join.php" style="margin-top: 0.5rem;">
                                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                <input type="hidden" name="course_id" value="<?php echo $course['id']; ?>">
                                <button type="submit" class="btn primary small" style="width: 100%;">
                                    Request to Join This Course
                                </button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
// Fix for modal functionality
function openAvailableCoursesModal() {
    document.getElementById('availableCoursesModal').style.display = 'flex';
}

function closeAvailableCoursesModal() {
    document.getElementById('availableCoursesModal').style.display = 'none';
}

// Make the "Join Course" button in nav work
document.addEventListener('DOMContentLoaded', function() {
    // Fix for Join Course button in nav
    const joinCourseBtn = document.querySelector('.nav-item[onclick*="availableCourses"]');
    if (joinCourseBtn) {
        joinCourseBtn.onclick = function() {
            openAvailableCoursesModal();
        };
    }
    
    // Fix for Join New Course button
    const joinNewCourseBtn = document.querySelector('[data-modal-open="availableCoursesModal"]');
    if (joinNewCourseBtn) {
        joinNewCourseBtn.onclick = function() {
            openAvailableCoursesModal();
        };
    }
    
    // Close modal on outside click
    const modal = document.getElementById('availableCoursesModal');
    if (modal) {
        modal.addEventListener('click', function(e) {
            if (e.target === this) {
                closeAvailableCoursesModal();
            }
        });
    }
});
</script>
    <script src="script.js"></script>
</body>
</html>