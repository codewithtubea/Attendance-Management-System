<?php
require_once 'includes/auth.php';
require_once 'includes/database.php';
redirectIfNotLoggedIn();

if (!isFaculty()) {
    header('Location: student_dashboard.php');
    exit();
}

// Handle course creation
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['create_course'])) {
        if (!validateCSRFToken($_POST['csrf_token'])) {
            die('Invalid CSRF token');
        }
        
        $course_code = trim($_POST['course_code']);
        $course_name = trim($_POST['course_name']);
        $description = trim($_POST['description']);
        $faculty_id = $_SESSION['user_id'];
        
        $errors = [];
        
        // Validation
        if (empty($course_code) || empty($course_name)) {
            $errors[] = "Course code and name are required";
        }
        
        if (strlen($course_code) > 20) {
            $errors[] = "Course code must be 20 characters or less";
        }
        
        if (empty($errors)) {
            try {
                $stmt = $pdo->prepare("INSERT INTO courses (course_code, course_name, description, faculty_id) VALUES (?, ?, ?, ?)");
                $stmt->execute([$course_code, $course_name, $description, $faculty_id]);
                $_SESSION['success'] = "Course created successfully!";
                header('Location: faculty_dashboard.php');
                exit();
            } catch (PDOException $e) {
                if ($e->getCode() == 23000) {
                    $errors[] = "Course code already exists";
                } else {
                    $errors[] = "Failed to create course";
                    error_log("Course creation failed: " . $e->getMessage());
                }
            }
        }
    }
    
    // Handle attendance code setting
    if (isset($_POST['set_attendance_code'])) {
        if (!validateCSRFToken($_POST['csrf_token'])) {
            die('Invalid CSRF token');
        }
        
        $course_id = intval($_POST['course_id']);
        $attendance_code = trim($_POST['attendance_code']);
        
        // Verify faculty owns the course
        $stmt = $pdo->prepare("SELECT id FROM courses WHERE id = ? AND faculty_id = ?");
        $stmt->execute([$course_id, $_SESSION['user_id']]);
        
        if ($stmt->fetch()) {
            // Update or create session for today with this code
            $today = date('Y-m-d');
            $start_time = '08:00:00';
            $end_time = '23:59:59';
            
            // Check if session exists for today
            $stmt = $pdo->prepare("
                SELECT id FROM sessions 
                WHERE course_id = ? AND session_date = ? 
                LIMIT 1
            ");
            $stmt->execute([$course_id, $today]);
            
            if ($stmt->fetch()) {
                // Update existing session
                $stmt = $pdo->prepare("
                    UPDATE sessions 
                    SET attendance_code = ?, start_time = ?, end_time = ?
                    WHERE course_id = ? AND session_date = ?
                ");
                $stmt->execute([$attendance_code, $start_time, $end_time, $course_id, $today]);
            } else {
                // Create new session
                $stmt = $pdo->prepare("
                    INSERT INTO sessions (course_id, session_date, start_time, end_time, session_type, attendance_code, created_by) 
                    VALUES (?, ?, ?, ?, 'lecture', ?, ?)
                ");
                $stmt->execute([$course_id, $today, $start_time, $end_time, $attendance_code, $_SESSION['user_id']]);
                
                // Auto-mark enrolled students as absent initially
                $session_id = $pdo->lastInsertId();
                $enrolled_stmt = $pdo->prepare("
                    SELECT student_id FROM enrollments 
                    WHERE course_id = ? AND status = 'approved'
                ");
                $enrolled_stmt->execute([$course_id]);
                $enrolled_students = $enrolled_stmt->fetchAll();
                
                foreach ($enrolled_students as $student) {
                    $attendance_stmt = $pdo->prepare("
                        INSERT INTO attendance (session_id, student_id, status, marked_by) 
                        VALUES (?, ?, 'absent', 'system')
                    ");
                    $attendance_stmt->execute([$session_id, $student['student_id']]);
                }
            }
            
            $_SESSION['success'] = "Attendance code '$attendance_code' set for today!";
        } else {
            $_SESSION['error'] = "Invalid course selection";
        }
        
        header('Location: faculty_dashboard.php');
        exit();
    }
}

// Handle request approval/rejection
if (isset($_GET['action']) && isset($_GET['enrollment_id'])) {
    if (!validateCSRFToken($_GET['csrf_token'] ?? '')) {
        die('Invalid CSRF token');
    }
    
    $enrollment_id = intval($_GET['enrollment_id']);
    $action = $_GET['action'];
    
    if (in_array($action, ['approve', 'reject'])) {
        $status = $action === 'approve' ? 'approved' : 'rejected';
        
        // Verify faculty owns the course
        $stmt = $pdo->prepare("
            SELECT c.id FROM enrollments e
            JOIN courses c ON e.course_id = c.id
            WHERE e.id = ? AND c.faculty_id = ?
        ");
        $stmt->execute([$enrollment_id, $_SESSION['user_id']]);
        
        if ($stmt->fetch()) {
            $stmt = $pdo->prepare("UPDATE enrollments SET status = ? WHERE id = ?");
            $stmt->execute([$status, $enrollment_id]);
            $_SESSION['success'] = "Request {$action}d successfully!";
        } else {
            $_SESSION['error'] = "Invalid request or insufficient permissions";
        }
        
        header('Location: faculty_dashboard.php');
        exit();
    }
}

// Get faculty's courses
$courses_stmt = $pdo->prepare("SELECT * FROM courses WHERE faculty_id = ? ORDER BY course_name");
$courses_stmt->execute([$_SESSION['user_id']]);
$courses = $courses_stmt->fetchAll();

// Get pending requests (FIXED SQL - removed enrolled_at from ORDER BY since it's now added)
$requests_stmt = $pdo->prepare("
    SELECT e.id as enrollment_id, e.status, c.course_code, c.course_name, u.name as student_name, u.email as student_email
    FROM enrollments e
    JOIN courses c ON e.course_id = c.id
    JOIN users u ON e.student_id = u.id
    WHERE c.faculty_id = ? AND e.status = 'pending'
    ORDER BY e.id DESC
");
$requests_stmt->execute([$_SESSION['user_id']]);
$pending_requests = $requests_stmt->fetchAll();

// Get stats
$total_courses = count($courses);
$total_requests = count($pending_requests);

$approved_students_stmt = $pdo->prepare("
    SELECT COUNT(DISTINCT student_id) as count 
    FROM enrollments e 
    JOIN courses c ON e.course_id = c.id 
    WHERE c.faculty_id = ? AND e.status = 'approved'
");
$approved_students_stmt->execute([$_SESSION['user_id']]);
$approved_students = $approved_students_stmt->fetch()['count'];

// Get today's attendance codes for courses
$today_codes_stmt = $pdo->prepare("
    SELECT c.id, c.course_code, c.course_name, s.attendance_code
    FROM courses c
    LEFT JOIN sessions s ON c.id = s.course_id AND s.session_date = CURDATE()
    WHERE c.faculty_id = ?
    ORDER BY c.course_name
");
$today_codes_stmt->execute([$_SESSION['user_id']]);
$today_codes = $today_codes_stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Faculty Dashboard - Ashesi Portal</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .attendance-code-section {
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
            padding: 1.5rem;
            border-radius: var(--radius-md);
            margin: 2rem 0;
            border: 1px solid var(--border-color);
        }
        
        .code-input-group {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 1rem;
            align-items: end;
        }
        
        .code-display {
            background: white;
            padding: 1rem;
            border-radius: var(--radius-sm);
            border: 2px solid var(--primary-red);
            font-family: monospace;
            font-size: 1.5rem;
            font-weight: bold;
            text-align: center;
            letter-spacing: 2px;
            margin-top: 0.5rem;
        }
        
        .today-codes-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1rem;
            margin-top: 1rem;
        }
        
        .code-card {
            background: white;
            padding: 1.25rem;
            border-radius: var(--radius-sm);
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow-sm);
        }
        
        .code-card.active {
            border-left: 4px solid var(--primary-red);
        }
    </style>
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
                <a href="faculty_dashboard.php" class="nav-item active">
                    <span class="icon">📊</span>
                    <span class="label">Dashboard</span>
                </a>
                <a href="faculty_sessions.php" class="nav-item">
                    <span class="icon">📅</span>
                    <span class="label">Sessions</span>
                </a>
                <button class="nav-item" data-modal-open="createCourseModal">
                    <span class="icon">➕</span>
                    <span class="label">New Course</span>
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
                    <input type="text" placeholder="Search courses...">
                </div>
                <div class="top-actions">
                    <div class="profile">
                        <div class="avatar"><?php echo strtoupper(substr($_SESSION['name'], 0, 1)); ?></div>
                        <div class="profile-name">Prof. <?php echo htmlspecialchars($_SESSION['name']); ?></div>
                    </div>
                </div>
            </header>

            <div class="content">
                <!-- Success/Error Messages -->
                <?php if (isset($_SESSION['success'])): ?>
                    <div class="alert success">
                        <?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($_SESSION['error'])): ?>
                    <div class="alert error">
                        <?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?>
                    </div>
                <?php endif; ?>

                <!-- Welcome Section -->
                <div style="margin-bottom: 2rem;">
                    <h1>Welcome, Prof. <?php echo htmlspecialchars($_SESSION['name']); ?></h1>
                    <p class="muted">Manage your courses, student enrollments, and attendance</p>
                </div>

                <!-- Stats Grid -->
                <div class="stats-grid">
                    <div class="stat large">
                        <small>Total Courses</small>
                        <h3><?php echo $total_courses; ?></h3>
                        <p class="muted">Courses you are teaching</p>
                        <button class="btn primary small" data-modal-open="createCourseModal" style="margin-top: 1rem;">
                            Create New Course
                        </button>
                    </div>
                    <div class="stat">
                        <small>Pending Requests</small>
                        <h3><?php echo $total_requests; ?></h3>
                        <p class="muted">Awaiting approval</p>
                    </div>
                    <div class="stat">
                        <small>Enrolled Students</small>
                        <h3><?php echo $approved_students; ?></h3>
                        <p class="muted">Total enrolled</p>
                    </div>
                </div>

                <!-- Attendance Code Management -->
                <div class="attendance-code-section">
                    <h2 style="margin-bottom: 1rem;">Set Today's Attendance Code</h2>
                    <p class="muted" style="margin-bottom: 1.5rem;">
                        Set a numeric code (e.g., 2450) for each course. Students will use this code to mark attendance.
                    </p>
                    
                    <form method="POST" action="faculty_dashboard.php">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        
                        <div class="code-input-group">
                            <div>
                                <label class="form-label">Select Course</label>
                                <select name="course_id" class="form-select" required>
                                    <option value="">Choose a course</option>
                                    <?php foreach ($courses as $course): ?>
                                        <option value="<?php echo $course['id']; ?>">
                                            <?php echo htmlspecialchars($course['course_code'] . ' - ' . $course['course_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label class="form-label">Attendance Code</label>
                                <input type="text" name="attendance_code" class="form-input" required
                                       placeholder="e.g., 2450" maxlength="6"
                                       pattern="[0-9]*" title="Enter numbers only (e.g., 2450)">
                                <small class="muted">4-6 digit number</small>
                            </div>
                        </div>
                        
                        <button type="submit" name="set_attendance_code" class="btn primary" style="margin-top: 1rem;">
                            Set Attendance Code
                        </button>
                    </form>
                    
                    <!-- Display Today's Codes -->
                    <?php if (!empty($today_codes)): ?>
                        <div style="margin-top: 2rem;">
                            <h3 style="margin-bottom: 1rem;">Today's Attendance Codes</h3>
                            <div class="today-codes-grid">
                                <?php foreach ($today_codes as $code): ?>
                                    <div class="code-card <?php echo $code['attendance_code'] ? 'active' : ''; ?>">
                                        <h4 style="margin-bottom: 0.5rem;">
                                            <?php echo htmlspecialchars($code['course_code']); ?>
                                        </h4>
                                        <p class="muted" style="margin-bottom: 0.5rem; font-size: 0.9rem;">
                                            <?php echo htmlspecialchars($code['course_name']); ?>
                                        </p>
                                        <?php if ($code['attendance_code']): ?>
                                            <div class="code-display">
                                                <?php echo htmlspecialchars($code['attendance_code']); ?>
                                            </div>
                                            <div style="text-align: center; margin-top: 0.5rem;">
                                                <small class="muted">Students use this code to mark attendance</small>
                                            </div>
                                        <?php else: ?>
                                            <div style="padding: 1rem; background: var(--light-bg); border-radius: var(--radius-sm); text-align: center;">
                                                <p class="muted" style="margin: 0;">No attendance code set for today</p>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Pending Requests -->
                <?php if (!empty($pending_requests)): ?>
                    <div style="margin: 2rem 0;">
                        <h2 style="margin-bottom: 1rem;">Student Join Requests</h2>
                        <div class="card">
                            <div style="padding: 1rem;">
                                <div class="table">
                                    <table style="width: 100%;">
                                        <thead>
                                            <tr>
                                                <th>Student</th>
                                                <th>Email</th>
                                                <th>Course</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($pending_requests as $request): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($request['student_name']); ?></td>
                                                    <td><?php echo htmlspecialchars($request['student_email']); ?></td>
                                                    <td>
                                                        <strong><?php echo htmlspecialchars($request['course_code']); ?></strong><br>
                                                        <small class="muted"><?php echo htmlspecialchars($request['course_name']); ?></small>
                                                    </td>
                                                    <td>
                                                        <div style="display: flex; gap: 0.5rem;">
                                                            <a href="faculty_dashboard.php?action=approve&enrollment_id=<?php echo $request['enrollment_id']; ?>&csrf_token=<?php echo generateCSRFToken(); ?>" 
                                                               class="btn primary small">Approve</a>
                                                            <a href="faculty_dashboard.php?action=reject&enrollment_id=<?php echo $request['enrollment_id']; ?>&csrf_token=<?php echo generateCSRFToken(); ?>" 
                                                               class="btn outline small">Reject</a>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- My Courses -->
                <div style="margin: 2rem 0;">
                    <h2 style="margin-bottom: 1rem;">My Courses</h2>
                    <?php if (empty($courses)): ?>
                        <div class="card" style="padding: 2rem; text-align: center;">
                            <p class="muted">You haven't created any courses yet.</p>
                            <button class="btn primary" data-modal-open="createCourseModal" style="margin-top: 1rem;">
                                Create Your First Course
                            </button>
                        </div>
                    <?php else: ?>
                        <div class="courses-grid">
                            <?php foreach ($courses as $course): ?>
                                <div class="course-card">
                                    <h3><?php echo htmlspecialchars($course['course_name']); ?></h3>
                                    <p class="muted"><?php echo htmlspecialchars($course['course_code']); ?></p>
                                    <p style="margin: 1rem 0 0.5rem; font-size: 0.9rem; color: var(--text-light);">
                                        <?php echo htmlspecialchars(substr($course['description'], 0, 100)); ?>...
                                    </p>
                                    <div style="margin-top: 1.5rem;">
                                        <a href="faculty_sessions.php?course_id=<?php echo $course['id']; ?>" 
                                           class="btn primary small">Manage Sessions</a>
                                        <button onclick="setCodeForCourse(<?php echo $course['id']; ?>)" 
                                                class="btn outline small">Set Attendance Code</button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <!-- Create Course Modal -->
    <div class="modal" id="createCourseModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 style="margin: 0;">Create New Course</h3>
                <button class="modal-close" data-modal-close>&times;</button>
            </div>
            <div class="modal-body">
                <form method="POST" action="faculty_dashboard.php">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    
                    <div class="form-group">
                        <label class="form-label">Course Code</label>
                        <input type="text" name="course_code" class="form-input" required
                               placeholder="CSCI101" maxlength="20">
                        <small class="muted">Unique course identifier (max 20 characters)</small>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Course Name</label>
                        <input type="text" name="course_name" class="form-input" required
                               placeholder="Introduction to Computer Science" maxlength="100">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Description</label>
                        <textarea name="description" class="form-textarea" rows="3"
                                  placeholder="Brief description of the course"></textarea>
                    </div>
                    
                    <button type="submit" name="create_course" class="btn primary" style="width: 100%;">
                        Create Course
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Quick Set Code Modal -->
    <div class="modal" id="quickSetCodeModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 style="margin: 0;">Set Attendance Code</h3>
                <button class="modal-close" data-modal-close>&times;</button>
            </div>
            <div class="modal-body">
                <form method="POST" action="faculty_dashboard.php" id="quickCodeForm">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="course_id" id="quickCourseId">
                    
                    <div class="form-group">
                        <label class="form-label">Attendance Code</label>
                        <input type="text" name="attendance_code" class="form-input" required
                               placeholder="e.g., 2450" maxlength="6"
                               pattern="[0-9]*" title="Enter numbers only (e.g., 2450)">
                        <small class="muted">4-6 digit number for students to use</small>
                    </div>
                    
                    <button type="submit" name="set_attendance_code" class="btn primary" style="width: 100%;">
                        Set Code for Today
                    </button>
                </form>
            </div>
        </div>
    </div>

   <script src="script.js"></script>
<script>
// Fix for modal opening
document.addEventListener('DOMContentLoaded', function() {
    // Fix for create course modal
    const createCourseBtn = document.querySelector('[data-modal-open="createCourseModal"]');
    const createCourseModal = document.getElementById('createCourseModal');
    const closeCourseModal = createCourseModal?.querySelector('.modal-close');
    
    if (createCourseBtn && createCourseModal) {
        createCourseBtn.addEventListener('click', function() {
            createCourseModal.classList.add('active');
            document.body.style.overflow = 'hidden';
        });
        
        closeCourseModal?.addEventListener('click', function() {
            createCourseModal.classList.remove('active');
            document.body.style.overflow = '';
        });
        
        // Close on outside click
        createCourseModal.addEventListener('click', function(e) {
            if (e.target === this) {
                this.classList.remove('active');
                document.body.style.overflow = '';
            }
        });
    }
    
    // Fix for quick set code modal
    const quickSetModal = document.getElementById('quickSetCodeModal');
    const closeQuickModal = quickSetModal?.querySelector('.modal-close');
    
    if (quickSetModal && closeQuickModal) {
        closeQuickModal.addEventListener('click', function() {
            quickSetModal.classList.remove('active');
            document.body.style.overflow = '';
        });
        
        quickSetModal.addEventListener('click', function(e) {
            if (e.target === this) {
                this.classList.remove('active');
                document.body.style.overflow = '';
            }
        });
    }
});

function setCodeForCourse(courseId) {
    document.getElementById('quickCourseId').value = courseId;
    const modal = document.getElementById('quickSetCodeModal');
    if (modal) {
        modal.classList.add('active');
        document.body.style.overflow = 'hidden';
    }
}

function copyCodeToClipboard(code) {
    if (!code) return;
    
    navigator.clipboard.writeText(code).then(() => {
        // Show success message
        alert('Attendance code copied to clipboard! Share with students.');
    });
}
</script>

</body>
</html>