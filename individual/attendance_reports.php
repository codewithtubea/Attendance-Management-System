<?php
require_once 'includes/auth.php';
require_once 'includes/database.php';
redirectIfNotLoggedIn();

if (!isFaculty()) {
    header('Location: student_dashboard.php');
    exit();
}

// Get date range (default: last 30 days)
$start_date = $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
$end_date = $_GET['end_date'] ?? date('Y-m-d');

// Get courses
$courses_stmt = $pdo->prepare("SELECT * FROM courses WHERE faculty_id = ? ORDER BY course_name");
$courses_stmt->execute([$_SESSION['user_id']]);
$courses = $courses_stmt->fetchAll();

// Get selected course
$selected_course_id = $_GET['course_id'] ?? null;

// Get attendance summary
$where_clause = "c.faculty_id = ? AND s.session_date BETWEEN ? AND ?";
$params = [$_SESSION['user_id'], $start_date, $end_date];

if ($selected_course_id) {
    $where_clause .= " AND c.id = ?";
    $params[] = $selected_course_id;
}

$summary_stmt = $pdo->prepare("
    SELECT 
        c.course_code,
        c.course_name,
        COUNT(DISTINCT s.id) as total_sessions,
        COUNT(DISTINCT e.student_id) as total_students,
        COUNT(a.id) as total_attendance_records,
        SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) as present_count,
        SUM(CASE WHEN a.status = 'late' THEN 1 ELSE 0 END) as late_count,
        SUM(CASE WHEN a.status = 'absent' THEN 1 ELSE 0 END) as absent_count,
        ROUND(AVG(CASE 
            WHEN a.status = 'present' THEN 1
            WHEN a.status = 'late' THEN 0.5
            ELSE 0 
        END) * 100, 2) as avg_attendance_rate
    FROM courses c
    LEFT JOIN sessions s ON c.id = s.course_id
    LEFT JOIN enrollments e ON c.id = e.course_id AND e.status = 'approved'
    LEFT JOIN attendance a ON s.id = a.session_id AND e.student_id = a.student_id
    WHERE $where_clause
    GROUP BY c.id
    ORDER BY c.course_name
");
$summary_stmt->execute($params);
$attendance_summary = $summary_stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance Reports - Ashesi Portal</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .date-filter {
            background: white;
            padding: 1.5rem;
            border-radius: var(--radius-md);
            box-shadow: var(--shadow-sm);
            margin-bottom: 2rem;
        }
        
        .filter-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            align-items: end;
        }
        
        .summary-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
            margin: 2rem 0;
        }
        
        .summary-card {
            background: white;
            padding: 1.5rem;
            border-radius: var(--radius-md);
            box-shadow: var(--shadow-sm);
        }
        
        .attendance-chart {
            background: white;
            padding: 1.5rem;
            border-radius: var(--radius-md);
            box-shadow: var(--shadow-sm);
            margin: 2rem 0;
        }
        
        .chart-placeholder {
            height: 300px;
            background: var(--light-bg);
            border-radius: var(--radius-sm);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--text-light);
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
                <a href="faculty_sessions.php" class="nav-item">
                    <span class="icon">📅</span>
                    <span class="label">Sessions</span>
                </a>
                <a href="attendance_reports.php" class="nav-item active">
                    <span class="icon">📈</span>
                    <span class="label">Reports</span>
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
                    <input type="text" placeholder="Search reports...">
                </div>
                <div class="top-actions">
                    <div class="profile">
                        <div class="avatar"><?php echo strtoupper(substr($_SESSION['name'], 0, 1)); ?></div>
                        <div class="profile-name">Prof. <?php echo htmlspecialchars($_SESSION['name']); ?></div>
                    </div>
                </div>
            </header>

            <div class="content">
                <h1 style="margin-bottom: 0.5rem;">Attendance Reports</h1>
                <p class="muted" style="margin-bottom: 2rem;">View attendance statistics and generate reports</p>

                <!-- Date Filter -->
                <div class="date-filter">
                    <form method="GET" action="attendance_reports.php">
                        <div class="filter-grid">
                            <div>
                                <label class="form-label">Course</label>
                                <select name="course_id" class="form-select">
                                    <option value="">All Courses</option>
                                    <?php foreach ($courses as $course): ?>
                                        <option value="<?php echo $course['id']; ?>" 
                                                <?php echo $selected_course_id == $course['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($course['course_code'] . ' - ' . $course['course_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label class="form-label">Start Date</label>
                                <input type="date" name="start_date" class="form-input" 
                                       value="<?php echo htmlspecialchars($start_date); ?>">
                            </div>
                            <div>
                                <label class="form-label">End Date</label>
                                <input type="date" name="end_date" class="form-input" 
                                       value="<?php echo htmlspecialchars($end_date); ?>">
                            </div>
                            <div>
                                <button type="submit" class="btn primary" style="width: 100%;">
                                    Generate Report
                                </button>
                            </div>
                        </div>
                    </form>
                </div>

                <!-- Summary Statistics -->
                <?php if (!empty($attendance_summary)): ?>
                    <div class="summary-grid">
                        <?php 
                        $overall_stats = [
                            'total_sessions' => 0,
                            'total_students' => 0,
                            'present_count' => 0,
                            'late_count' => 0,
                            'absent_count' => 0
                        ];
                        
                        foreach ($attendance_summary as $summary) {
                            $overall_stats['total_sessions'] += $summary['total_sessions'];
                            $overall_stats['total_students'] += $summary['total_students'];
                            $overall_stats['present_count'] += $summary['present_count'];
                            $overall_stats['late_count'] += $summary['late_count'];
                            $overall_stats['absent_count'] += $summary['absent_count'];
                        }
                        
                        $total_records = $overall_stats['present_count'] + $overall_stats['late_count'] + $overall_stats['absent_count'];
                        $overall_rate = $total_records > 0 ? 
                            round((($overall_stats['present_count'] + ($overall_stats['late_count'] * 0.5)) / $total_records) * 100) : 0;
                        ?>
                        
                        <div class="summary-card">
                            <small>Overall Attendance Rate</small>
                            <h2 style="margin: 0.5rem 0; color: var(--primary-red);"><?php echo $overall_rate; ?>%</h2>
                            <p class="muted">Across all courses</p>
                        </div>
                        
                        <div class="summary-card">
                            <small>Total Sessions</small>
                            <h2 style="margin: 0.5rem 0;"><?php echo $overall_stats['total_sessions']; ?></h2>
                            <p class="muted">Sessions conducted</p>
                        </div>
                        
                        <div class="summary-card">
                            <small>Active Students</small>
                            <h2 style="margin: 0.5rem 0;"><?php echo $overall_stats['total_students']; ?></h2>
                            <p class="muted">Enrolled students</p>
                        </div>
                    </div>

                    <!-- Course-wise Breakdown -->
                    <div class="card" style="margin: 2rem 0;">
                        <div style="padding: 1.5rem;">
                            <h3 style="margin-bottom: 1rem;">Course-wise Attendance</h3>
                            <table class="attendance-table">
                                <thead>
                                    <tr>
                                        <th>Course</th>
                                        <th>Sessions</th>
                                        <th>Students</th>
                                        <th>Present</th>
                                        <th>Late</th>
                                        <th>Absent</th>
                                        <th>Rate</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($attendance_summary as $summary): ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo htmlspecialchars($summary['course_code']); ?></strong><br>
                                                <small><?php echo htmlspecialchars($summary['course_name']); ?></small>
                                            </td>
                                            <td><?php echo $summary['total_sessions']; ?></td>
                                            <td><?php echo $summary['total_students']; ?></td>
                                            <td style="color: var(--success-green);"><?php echo $summary['present_count']; ?></td>
                                            <td style="color: var(--warning-orange);"><?php echo $summary['late_count']; ?></td>
                                            <td style="color: var(--error-red);"><?php echo $summary['absent_count']; ?></td>
                                            <td>
                                                <strong style="color: var(--primary-red);">
                                                    <?php echo $summary['avg_attendance_rate'] ?? 0; ?>%
                                                </strong>
                                            </td>
                                            <td>
                                                <a href="course_report.php?course_id=<?php echo array_search($summary['course_code'], array_column($courses, 'course_code')); ?>" 
                                                   class="btn primary small">
                                                    View Details
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Chart Placeholder -->
                    <div class="attendance-chart">
                        <h3 style="margin-bottom: 1rem;">Attendance Trends</h3>
                        <div class="chart-placeholder">
                            <div style="text-align: center;">
                                <div style="font-size: 3rem; margin-bottom: 1rem;">📊</div>
                                <p>Attendance chart visualization</p>
                                <small class="muted">(Chart integration available in premium version)</small>
                            </div>
                        </div>
                    </div>

                    <!-- Export Options -->
                    <div style="background: white; padding: 1.5rem; border-radius: var(--radius-md); box-shadow: var(--shadow-sm);">
                        <h3 style="margin-bottom: 1rem;">Export Reports</h3>
                        <div style="display: flex; gap: 1rem; flex-wrap: wrap;">
                            <a href="export_report.php?type=summary&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>&course_id=<?php echo $selected_course_id; ?>&format=csv" 
                               class="btn primary">
                                Export Summary as CSV
                            </a>
                            <a href="export_report.php?type=detailed&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>&course_id=<?php echo $selected_course_id; ?>&format=pdf" 
                               class="btn outline">
                                Export Detailed as PDF
                            </a>
                            <button onclick="window.print()" class="btn outline">
                                Print Report
                            </button>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="card" style="padding: 3rem; text-align: center;">
                        <p class="muted" style="font-size: 1.1rem;">No attendance data found for the selected period.</p>
                        <p class="muted">Try adjusting the date range or create sessions first.</p>
                        <a href="faculty_sessions.php" class="btn primary" style="margin-top: 1rem;">
                            Create Sessions
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <script src="script.js"></script>
</body>
</html>