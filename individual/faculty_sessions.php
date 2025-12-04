<?php
require_once 'includes/auth.php';
require_once 'includes/database.php';
redirectIfNotLoggedIn();

if (!isFaculty()) {
    header('Location: student_dashboard.php');
    exit();
}

// Handle session creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_session'])) {
    if (!validateCSRFToken($_POST['csrf_token'])) {
        die('Invalid CSRF token');
    }
    
    $course_id = intval($_POST['course_id']);
    $session_date = $_POST['session_date'];
    $start_time = $_POST['start_time'];
    $end_time = $_POST['end_time'];
    $session_type = $_POST['session_type'];
    $generate_code = isset($_POST['generate_code']);
    
    // Verify faculty owns the course
    $stmt = $pdo->prepare("SELECT id FROM courses WHERE id = ? AND faculty_id = ?");
    $stmt->execute([$course_id, $_SESSION['user_id']]);
    
    if (!$stmt->fetch()) {
        $_SESSION['error'] = "Invalid course selection";
        header('Location: faculty_sessions.php');
        exit();
    }
    
    // Generate attendance code if requested
    $attendance_code = null;
    if ($generate_code) {
        $attendance_code = strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
    }
    
    // Create session
    $stmt = $pdo->prepare("
        INSERT INTO sessions (course_id, session_date, start_time, end_time, session_type, attendance_code, created_by) 
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    
    try {
        $stmt->execute([$course_id, $session_date, $start_time, $end_time, $session_type, $attendance_code, $_SESSION['user_id']]);
        $session_id = $pdo->lastInsertId();
        
        // Auto-mark enrolled students as absent initially
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
        
        $_SESSION['success'] = "Session created successfully!" . 
            ($attendance_code ? " Attendance code: $attendance_code" : "");
    } catch (PDOException $e) {
        error_log("Session creation failed: " . $e->getMessage());
        $_SESSION['error'] = "Failed to create session";
    }
    
    header('Location: faculty_sessions.php');
    exit();
}

// Handle manual attendance marking
if (isset($_GET['mark_attendance']) && isset($_GET['session_id'])) {
    if (!validateCSRFToken($_GET['csrf_token'] ?? '')) {
        die('Invalid CSRF token');
    }
    
    $session_id = intval($_GET['session_id']);
    $student_id = intval($_GET['student_id']);
    $status = $_GET['status'];
    
    // Verify faculty owns the session
    $stmt = $pdo->prepare("
        SELECT s.id FROM sessions s
        JOIN courses c ON s.course_id = c.id
        WHERE s.id = ? AND c.faculty_id = ?
    ");
    $stmt->execute([$session_id, $_SESSION['user_id']]);
    
    if ($stmt->fetch()) {
        $stmt = $pdo->prepare("
            UPDATE attendance 
            SET status = ?, marked_by = 'faculty', marked_at = NOW()
            WHERE session_id = ? AND student_id = ?
        ");
        $stmt->execute([$status, $session_id, $student_id]);
        
        $_SESSION['success'] = "Attendance marked as " . $status;
    } else {
        $_SESSION['error'] = "Invalid session or insufficient permissions";
    }
    
    header('Location: faculty_sessions.php');
    exit();
}

// Get faculty's courses
$courses_stmt = $pdo->prepare("SELECT * FROM courses WHERE faculty_id = ? ORDER BY course_name");
$courses_stmt->execute([$_SESSION['user_id']]);
$courses = $courses_stmt->fetchAll();

// Get upcoming sessions
$sessions_stmt = $pdo->prepare("
    SELECT s.*, c.course_code, c.course_name 
    FROM sessions s 
    JOIN courses c ON s.course_id = c.id 
    WHERE s.created_by = ? AND s.session_date >= CURDATE() 
    ORDER BY s.session_date, s.start_time
    LIMIT 20
");
$sessions_stmt->execute([$_SESSION['user_id']]);
$sessions = $sessions_stmt->fetchAll();

// Get recent sessions for attendance management
$recent_sessions_stmt = $pdo->prepare("
    SELECT s.*, c.course_code, c.course_name,
           COUNT(a.id) as total_students,
           SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) as present_count,
           SUM(CASE WHEN a.status = 'late' THEN 1 ELSE 0 END) as late_count,
           SUM(CASE WHEN a.status = 'absent' THEN 1 ELSE 0 END) as absent_count
    FROM sessions s 
    JOIN courses c ON s.course_id = c.id
    LEFT JOIN attendance a ON s.id = a.session_id
    WHERE s.created_by = ? AND s.session_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    GROUP BY s.id
    ORDER BY s.session_date DESC, s.start_time DESC
    LIMIT 10
");
$recent_sessions_stmt->execute([$_SESSION['user_id']]);
$recent_sessions = $recent_sessions_stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Session Management - Ashesi Portal</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .attendance-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
            margin: 1rem 0;
        }
        
        .attendance-stat {
            background: white;
            padding: 1rem;
            border-radius: var(--radius-sm);
            border: 1px solid var(--border-color);
            text-align: center;
        }
        
        .attendance-stat.present { border-left: 4px solid var(--success-green); }
        .attendance-stat.late { border-left: 4px solid var(--warning-orange); }
        .attendance-stat.absent { border-left: 4px solid var(--error-red); }
        
        .session-details {
            background: var(--light-bg);
            padding: 1rem;
            border-radius: var(--radius-sm);
            margin: 1rem 0;
        }
        
        .attendance-actions {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }
        
        .code-display {
            background: var(--light-red);
            padding: 0.75rem;
            border-radius: var(--radius-sm);
            font-family: monospace;
            font-size: 1.1rem;
            letter-spacing: 2px;
            text-align: center;
            margin: 1rem 0;
        }
        
        .attendance-table {
            width: 100%;
            border-collapse: collapse;
            margin: 1rem 0;
        }
        
        .attendance-table th, .attendance-table td {
            padding: 0.75rem;
            border-bottom: 1px solid var(--border-color);
            text-align: left;
        }
        
        .attendance-table th {
            background: var(--light-bg);
            font-weight: 600;
        }
        
        .status-badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
        }
        
        .status-present {
            background: rgba(34,197,94,0.1);
            color: var(--success-green);
            border: 1px solid rgba(34,197,94,0.2);
        }
        
        .status-late {
            background: rgba(245,158,11,0.1);
            color: var(--warning-orange);
            border: 1px solid rgba(245,158,11,0.2);
        }
        
        .status-absent {
            background: rgba(239,68,68,0.1);
            color: var(--error-red);
            border: 1px solid rgba(239,68,68,0.2);
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
                <a href="faculty_dashboard.php" class="nav-item">
                    <span class="icon">📊</span>
                    <span class="label">Dashboard</span>
                </a>
                <a href="faculty_sessions.php" class="nav-item active">
                    <span class="icon">📅</span>
                    <span class="label">Sessions</span>
                </a>
                <a href="attendance_reports.php" class="nav-item">
                    <span class="icon">📈</span>
                    <span class="label">Reports</span>
                </a>
                <button class="nav-item" data-modal-open="createSessionModal">
                    <span class="icon">➕</span>
                    <span class="label">New Session</span>
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
                    <input type="text" placeholder="Search sessions...">
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

                <!-- Page Header -->
                <div style="margin-bottom: 2rem;">
                    <h1>Session & Attendance Management</h1>
                    <p class="muted">Create class sessions and manage student attendance</p>
                    <button class="btn primary" data-modal-open="createSessionModal" style="margin-top: 1rem;">
                        Create New Session
                    </button>
                </div>

                <!-- Recent Sessions with Attendance -->
                <div style="margin-bottom: 2rem;">
                    <h2 style="margin-bottom: 1rem;">Recent Sessions</h2>
                    
                    <?php if (empty($recent_sessions)): ?>
                        <div class="card" style="padding: 2rem; text-align: center;">
                            <p class="muted">No recent sessions found</p>
                            <button class="btn primary" data-modal-open="createSessionModal" style="margin-top: 1rem;">
                                Create Your First Session
                            </button>
                        </div>
                    <?php else: ?>
                        <div class="courses-grid">
                            <?php foreach ($recent_sessions as $session): ?>
                                <div class="course-card">
                                    <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 1rem;">
                                        <div>
                                            <h3 style="margin: 0;"><?php echo htmlspecialchars($session['course_name']); ?></h3>
                                            <p class="muted"><?php echo htmlspecialchars($session['course_code']); ?></p>
                                        </div>
                                        <span class="badge <?php echo $session['session_type']; ?>">
                                            <?php echo ucfirst($session['session_type']); ?>
                                        </span>
                                    </div>
                                    
                                    <div class="session-details">
                                        <p style="margin: 0.5rem 0;">
                                            <strong>Date:</strong> <?php echo date('F j, Y', strtotime($session['session_date'])); ?>
                                        </p>
                                        <p style="margin: 0.5rem 0;">
                                            <strong>Time:</strong> <?php echo date('g:i A', strtotime($session['start_time'])); ?> - 
                                            <?php echo date('g:i A', strtotime($session['end_time'])); ?>
                                        </p>
                                        
                                        <?php if ($session['attendance_code']): ?>
                                            <div class="code-display">
                                                <strong>Attendance Code:</strong><br>
                                                <code><?php echo htmlspecialchars($session['attendance_code']); ?></code>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <!-- Attendance Statistics -->
                                    <div class="attendance-stats">
                                        <div class="attendance-stat present">
                                            <small>Present</small>
                                            <h4 style="margin: 0.25rem 0; color: var(--success-green);">
                                                <?php echo $session['present_count'] ?? 0; ?>
                                            </h4>
                                        </div>
                                        <div class="attendance-stat late">
                                            <small>Late</small>
                                            <h4 style="margin: 0.25rem 0; color: var(--warning-orange);">
                                                <?php echo $session['late_count'] ?? 0; ?>
                                            </h4>
                                        </div>
                                        <div class="attendance-stat absent">
                                            <small>Absent</small>
                                            <h4 style="margin: 0.25rem 0; color: var(--error-red);">
                                                <?php echo $session['absent_count'] ?? 0; ?>
                                            </h4>
                                        </div>
                                    </div>
                                    
                                    <div class="attendance-actions" style="margin-top: 1rem;">
                                        <a href="view_attendance.php?session_id=<?php echo $session['id']; ?>" 
                                           class="btn primary small">
                                            View Attendance
                                        </a>
                                        <a href="attendance_report.php?session_id=<?php echo $session['id']; ?>" 
                                           class="btn outline small">
                                            Generate Report
                                        </a>
                                        <?php if ($session['session_date'] == date('Y-m-d')): ?>
                                            <button class="btn outline small" 
                                                    onclick="showAttendanceModal(<?php echo $session['id']; ?>)">
                                                Mark Attendance
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Upcoming Sessions -->
                <?php if (!empty($sessions)): ?>
                    <div style="margin-bottom: 2rem;">
                        <h2 style="margin-bottom: 1rem;">Upcoming Sessions</h2>
                        <div class="card">
                            <div style="padding: 1rem;">
                                <table class="attendance-table">
                                    <thead>
                                        <tr>
                                            <th>Course</th>
                                            <th>Date</th>
                                            <th>Time</th>
                                            <th>Type</th>
                                            <th>Attendance Code</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($sessions as $session): ?>
                                            <tr>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($session['course_code']); ?></strong><br>
                                                    <small><?php echo htmlspecialchars($session['course_name']); ?></small>
                                                </td>
                                                <td><?php echo date('M j, Y', strtotime($session['session_date'])); ?></td>
                                                <td>
                                                    <?php echo date('g:i A', strtotime($session['start_time'])); ?><br>
                                                    <small>to <?php echo date('g:i A', strtotime($session['end_time'])); ?></small>
                                                </td>
                                                <td>
                                                    <span class="badge <?php echo $session['session_type']; ?>">
                                                        <?php echo ucfirst($session['session_type']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php if ($session['attendance_code']): ?>
                                                        <code><?php echo htmlspecialchars($session['attendance_code']); ?></code>
                                                    <?php else: ?>
                                                        <span class="muted">No code</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <div style="display: flex; gap: 0.5rem;">
                                                        <a href="view_attendance.php?session_id=<?php echo $session['id']; ?>" 
                                                           class="btn primary small">Manage</a>
                                                        <button class="btn outline small" 
                                                                onclick="copyToClipboard('<?php echo $session['attendance_code'] ?? ''; ?>')">
                                                            Copy Code
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <!-- Create Session Modal -->
    <div class="modal" id="createSessionModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 style="margin: 0;">Create New Session</h3>
                <button class="modal-close" data-modal-close>&times;</button>
            </div>
            <div class="modal-body">
                <form method="POST" action="faculty_sessions.php">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    
                    <div class="form-group">
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
                    
                    <div class="form-group">
                        <label class="form-label">Session Date</label>
                        <input type="date" name="session_date" class="form-input" required 
                               min="<?php echo date('Y-m-d'); ?>"
                               value="<?php echo date('Y-m-d'); ?>">
                    </div>
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                        <div>
                            <label class="form-label">Start Time</label>
                            <input type="time" name="start_time" class="form-input" required>
                        </div>
                        <div>
                            <label class="form-label">End Time</label>
                            <input type="time" name="end_time" class="form-input" required>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Session Type</label>
                        <select name="session_type" class="form-select" required>
                            <option value="lecture">Lecture</option>
                            <option value="lab">Lab</option>
                            <option value="tutorial">Tutorial</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label style="display: flex; align-items: center; gap: 0.5rem;">
                            <input type="checkbox" name="generate_code" checked>
                            <span>Generate attendance code for students</span>
                        </label>
                        <small class="muted">Students will use this code to mark their attendance</small>
                    </div>
                    
                    <button type="submit" name="create_session" class="btn primary" style="width: 100%;">
                        Create Session
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Quick Attendance Modal -->
    <div class="modal" id="quickAttendanceModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 style="margin: 0;">Mark Attendance</h3>
                <button class="modal-close" data-modal-close>&times;</button>
            </div>
            <div class="modal-body">
                <div id="attendanceContent">
                    <!-- Content will be loaded via AJAX -->
                    <div style="text-align: center; padding: 2rem;">
                        <div class="loading"></div>
                        <p>Loading attendance data...</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="script.js"></script>
    <script>
    function showAttendanceModal(sessionId) {
        const modal = document.getElementById('quickAttendanceModal');
        const content = document.getElementById('attendanceContent');
        
        // Show loading state
        content.innerHTML = `
            <div style="text-align: center; padding: 2rem;">
                <div class="loading"></div>
                <p>Loading attendance data...</p>
            </div>
        `;
        
        // Show modal
        modal.classList.add('active');
        document.body.style.overflow = 'hidden';
        
        // Load attendance data via AJAX
        fetch(`get_attendance.php?session_id=${sessionId}`)
            .then(response => response.text())
            .then(html => {
                content.innerHTML = html;
            })
            .catch(error => {
                content.innerHTML = `
                    <div class="alert error">
                        Failed to load attendance data. Please try again.
                    </div>
                `;
            });
    }
    
    function copyToClipboard(text) {
        if (!text) {
            alert('No attendance code available');
            return;
        }
        
        navigator.clipboard.writeText(text).then(() => {
            const buttons = document.querySelectorAll('[onclick*="copyToClipboard"]');
            buttons.forEach(btn => {
                if (btn.textContent.includes('Copy')) {
                    const original = btn.textContent;
                    btn.textContent = 'Copied!';
                    setTimeout(() => {
                        btn.textContent = original;
                    }, 2000);
                }
            });
        });
    }
    
    function markAttendance(sessionId, studentId, status) {
        const csrfToken = '<?php echo generateCSRFToken(); ?>';
        
        fetch(`faculty_sessions.php?mark_attendance=1&session_id=${sessionId}&student_id=${studentId}&status=${status}&csrf_token=${csrfToken}`)
            .then(response => {
                if (response.ok) {
                    location.reload();
                } else {
                    alert('Failed to mark attendance');
                }
            })
            .catch(error => {
                alert('Network error. Please try again.');
            });
    }
    </script>
</body>
</html>