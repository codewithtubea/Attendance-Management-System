<?php
require_once 'includes/auth.php';
require_once 'includes/database.php';
redirectIfNotLoggedIn();

if (!isStudent()) {
    header('Location: faculty_dashboard.php');
    exit();
}

$student_id = $_SESSION['user_id'];

// Get enrolled courses
$courses_stmt = $pdo->prepare("
    SELECT c.id, c.course_code, c.course_name 
    FROM enrollments e
    JOIN courses c ON e.course_id = c.id
    WHERE e.student_id = ? AND e.status = 'approved'
    ORDER BY c.course_name
");
$courses_stmt->execute([$student_id]);
$courses = $courses_stmt->fetchAll();

// Get selected course
$selected_course_id = $_GET['course_id'] ?? ($courses[0]['id'] ?? null);

// Get attendance summary for selected course
if ($selected_course_id) {
    $summary_stmt = $pdo->prepare("
        SELECT 
            COUNT(DISTINCT s.id) as total_sessions,
            COUNT(a.id) as attended_sessions,
            SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) as present_count,
            SUM(CASE WHEN a.status = 'late' THEN 1 ELSE 0 END) as late_count,
            SUM(CASE WHEN a.status = 'absent' THEN 1 ELSE 0 END) as absent_count,
            ROUND((SUM(CASE WHEN a.status = 'present' THEN 1 
                           WHEN a.status = 'late' THEN 0.5 
                           ELSE 0 END) / COUNT(DISTINCT s.id)) * 100, 2) as attendance_rate
        FROM enrollments e
        JOIN courses c ON e.course_id = c.id
        LEFT JOIN sessions s ON c.id = s.course_id
        LEFT JOIN attendance a ON s.id = a.session_id AND a.student_id = e.student_id
        WHERE e.student_id = ? AND c.id = ? AND e.status = 'approved'
    ");
    $summary_stmt->execute([$student_id, $selected_course_id]);
    $attendance_summary = $summary_stmt->fetch();
    
    // Get detailed attendance records
    $details_stmt = $pdo->prepare("
        SELECT 
            s.session_date,
            s.start_time,
            s.end_time,
            s.session_type,
            a.status,
            a.marked_at,
            a.marked_by
        FROM attendance a
        JOIN sessions s ON a.session_id = s.id
        JOIN courses c ON s.course_id = c.id
        WHERE a.student_id = ? AND c.id = ?
        ORDER BY s.session_date DESC, s.start_time DESC
    ");
    $details_stmt->execute([$student_id, $selected_course_id]);
    $attendance_details = $details_stmt->fetchAll();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Attendance - Ashesi Portal</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .attendance-summary {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin: 2rem 0;
        }
        
        .summary-card {
            background: white;
            padding: 1.5rem;
            border-radius: var(--radius-md);
            box-shadow: var(--shadow-sm);
            text-align: center;
        }
        
        .rate-card {
            background: linear-gradient(135deg, var(--light-red), white);
            border-left: 4px solid var(--primary-red);
        }
        
        .course-selector {
            background: white;
            padding: 1.5rem;
            border-radius: var(--radius-md);
            box-shadow: var(--shadow-sm);
            margin-bottom: 2rem;
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
                <a href="student_dashboard.php" class="nav-item">
                    <span class="icon">📊</span>
                    <span class="label">Dashboard</span>
                </a>
                <a href="courses.php" class="nav-item">
                    <span class="icon">📚</span>
                    <span class="label">My Courses</span>
                </a>
                <a href="mark_attendance.php" class="nav-item">
                    <span class="icon">✓</span>
                    <span class="label">Mark Attendance</span>
                </a>
                <a href="student_attendance_report.php" class="nav-item active">
                    <span class="icon">📈</span>
                    <span class="label">My Attendance</span>
                </a>
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
                    <input type="text" placeholder="Search attendance...">
                </div>
                <div class="top-actions">
                    <div class="profile">
                        <div class="avatar"><?php echo strtoupper(substr($_SESSION['name'], 0, 1)); ?></div>
                        <div class="profile-name"><?php echo htmlspecialchars($_SESSION['name']); ?></div>
                    </div>
                </div>
            </header>

            <div class="content">
                <h1 style="margin-bottom: 0.5rem;">My Attendance Report</h1>
                <p class="muted" style="margin-bottom: 2rem;">View your attendance records across courses</p>

                <!-- Course Selector -->
                <div class="course-selector">
                    <label class="form-label">Select Course</label>
                    <div style="display: flex; gap: 1rem; align-items: center; flex-wrap: wrap;">
                        <select id="courseSelect" class="form-select" style="flex: 1; min-width: 200px;">
                            <?php foreach ($courses as $course): ?>
                                <option value="<?php echo $course['id']; ?>" 
                                        <?php echo $selected_course_id == $course['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($course['course_code'] . ' - ' . $course['course_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <button onclick="window.location.href = 'student_attendance_report.php?course_id=' + document.getElementById('courseSelect').value" 
                                class="btn primary">
                            View Report
                        </button>
                    </div>
                </div>

                <?php if ($selected_course_id && isset($attendance_summary)): ?>
                    <?php 
                    $course_info = array_filter($courses, function($c) use ($selected_course_id) {
                        return $c['id'] == $selected_course_id;
                    });
                    $course_info = reset($course_info);
                    ?>
                    
                    <!-- Course Header -->
                    <div style="background: var(--light-bg); padding: 1.5rem; border-radius: var(--radius-md); margin-bottom: 2rem;">
                        <h2 style="margin-bottom: 0.5rem;"><?php echo htmlspecialchars($course_info['course_code'] . ' - ' . $course_info['course_name']); ?></h2>
                        <p class="muted">Your attendance performance in this course</p>
                    </div>

                    <!-- Attendance Summary -->
                    <div class="attendance-summary">
                        <div class="summary-card rate-card">
                            <small>Attendance Rate</small>
                            <h2 style="margin: 0.5rem 0; color: var(--primary-red);">
                                <?php echo $attendance_summary['attendance_rate'] ?? 0; ?>%
                            </h2>
                        </div>
                        
                        <div class="summary-card">
                            <small>Total Sessions</small>
                            <h2 style="margin: 0.5rem 0;"><?php echo $attendance_summary['total_sessions'] ?? 0; ?></h2>
                        </div>
                        
                        <div class="summary-card">
                            <small>Present</small>
                            <h2 style="margin: 0.5rem 0; color: var(--success-green);"><?php echo $attendance_summary['present_count'] ?? 0; ?></h2>
                        </div>
                        
                        <div class="summary-card">
                            <small>Late</small>
                            <h2 style="margin: 0.5rem 0; color: var(--warning-orange);"><?php echo $attendance_summary['late_count'] ?? 0; ?></h2>
                        </div>
                        
                        <div class="summary-card">
                            <small>Absent</small>
                            <h2 style="margin: 0.5rem 0; color: var(--error-red);"><?php echo $attendance_summary['absent_count'] ?? 0; ?></h2>
                        </div>
                    </div>

                    <!-- Progress Bar -->
                    <?php if ($attendance_summary['total_sessions'] > 0): ?>
                        <div style="background: white; padding: 1.5rem; border-radius: var(--radius-md); box-shadow: var(--shadow-sm); margin: 2rem 0;">
                            <h3 style="margin-bottom: 1rem;">Attendance Progress</h3>
                            <div style="display: flex; align-items: center; gap: 1rem; margin-bottom: 0.5rem;">
                                <div style="flex: 1; height: 10px; background: var(--light-bg); border-radius: 5px; overflow: hidden;">
                                    <?php
                                    $present_percent = ($attendance_summary['present_count'] / $attendance_summary['total_sessions']) * 100;
                                    $late_percent = ($attendance_summary['late_count'] / $attendance_summary['total_sessions']) * 100;
                                    $absent_percent = ($attendance_summary['absent_count'] / $attendance_summary['total_sessions']) * 100;
                                    ?>
                                    <div style="width: <?php echo $present_percent; ?>%; height: 100%; background: var(--success-green); float: left;"></div>
                                    <div style="width: <?php echo $late_percent; ?>%; height: 100%; background: var(--warning-orange); float: left;"></div>
                                    <div style="width: <?php echo $absent_percent; ?>%; height: 100%; background: var(--error-red); float: left;"></div>
                                </div>
                                <strong><?php echo $attendance_summary['attendance_rate']; ?>%</strong>
                            </div>
                            <div style="display: flex; justify-content: space-between; font-size: 0.85rem; color: var(--text-light);">
                                <span>0%</span>
                                <span>50%</span>
                                <span>100%</span>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Detailed Records -->
                    <div class="card">
                        <div style="padding: 1.5rem;">
                            <h3 style="margin-bottom: 1rem;">Attendance History</h3>
                            
                            <?php if (empty($attendance_details)): ?>
                                <p class="muted" style="text-align: center; padding: 2rem;">No attendance records found for this course.</p>
                            <?php else: ?>
                                <table class="attendance-table">
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Time</th>
                                            <th>Type</th>
                                            <th>Status</th>
                                            <th>Marked By</th>
                                            <th>Time Marked</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($attendance_details as $record): ?>
                                            <tr>
                                                <td><?php echo date('F j, Y', strtotime($record['session_date'])); ?></td>
                                                <td>
                                                    <?php echo date('g:i A', strtotime($record['start_time'])); ?> - 
                                                    <?php echo date('g:i A', strtotime($record['end_time'])); ?>
                                                </td>
                                                <td>
                                                    <span class="badge <?php echo $record['session_type']; ?>">
                                                        <?php echo ucfirst($record['session_type']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="status-badge status-<?php echo $record['status']; ?>">
                                                        <?php echo ucfirst($record['status']); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo ucfirst($record['marked_by']); ?></td>
                                                <td>
                                                    <?php echo $record['marked_at'] ? date('g:i A', strtotime($record['marked_at'])) : 'N/A'; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Export Options -->
                    <div style="display: flex; gap: 1rem; margin-top: 2rem; flex-wrap: wrap;">
                        <button onclick="window.print()" class="btn outline">
                            Print Report
                        </button>
                        <a href="export_student_report.php?course_id=<?php echo $selected_course_id; ?>&format=pdf" 
                           class="btn outline">
                            Download as PDF
                        </a>
                    </div>
                <?php elseif (empty($courses)): ?>
                    <div class="card" style="padding: 3rem; text-align: center;">
                        <p class="muted" style="font-size: 1.1rem;">You are not enrolled in any courses yet.</p>
                        <a href="student_dashboard.php" class="btn primary" style="margin-top: 1rem;">
                            Browse Available Courses
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <script src="script.js"></script>
</body>
</html>