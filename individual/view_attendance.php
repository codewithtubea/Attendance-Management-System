<?php
require_once 'includes/auth.php';
require_once 'includes/database.php';
redirectIfNotLoggedIn();

if (!isFaculty()) {
    header('Location: student_dashboard.php');
    exit();
}

if (!isset($_GET['session_id'])) {
    header('Location: faculty_sessions.php');
    exit();
}

$session_id = intval($_GET['session_id']);

// Get session details
$stmt = $pdo->prepare("
    SELECT s.*, c.course_code, c.course_name 
    FROM sessions s
    JOIN courses c ON s.course_id = c.id
    WHERE s.id = ? AND c.faculty_id = ?
");
$stmt->execute([$session_id, $_SESSION['user_id']]);
$session = $stmt->fetch();

if (!$session) {
    $_SESSION['error'] = "Session not found or access denied";
    header('Location: faculty_sessions.php');
    exit();
}

// Get attendance records
$stmt = $pdo->prepare("
    SELECT a.*, u.name, u.email,
           CASE 
               WHEN a.status = 'present' THEN 1
               WHEN a.status = 'late' THEN 0.5
               ELSE 0 
           END as attendance_score
    FROM attendance a
    JOIN users u ON a.student_id = u.id
    WHERE a.session_id = ?
    ORDER BY a.status, u.name
");
$stmt->execute([$session_id]);
$attendance_records = $stmt->fetchAll();

// Calculate statistics
$total_students = count($attendance_records);
$present_count = 0;
$late_count = 0;
$absent_count = 0;
$attendance_rate = 0;

foreach ($attendance_records as $record) {
    switch ($record['status']) {
        case 'present': $present_count++; break;
        case 'late': $late_count++; break;
        case 'absent': $absent_count++; break;
    }
}

if ($total_students > 0) {
    $attendance_rate = round((($present_count + ($late_count * 0.5)) / $total_students) * 100);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance Details - Ashesi Portal</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .session-header {
            background: linear-gradient(135deg, var(--primary-red), var(--accent-red));
            color: white;
            padding: 2rem;
            border-radius: var(--radius-md);
            margin-bottom: 2rem;
        }
        
        .stats-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
            margin: 2rem 0;
        }
        
        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: var(--radius-sm);
            text-align: center;
            box-shadow: var(--shadow-sm);
        }
        
        .stat-card.attendance-rate {
            background: linear-gradient(135deg, var(--light-red), white);
            border-left: 4px solid var(--primary-red);
        }
        
        .export-buttons {
            display: flex;
            gap: 1rem;
            margin: 1rem 0;
            flex-wrap: wrap;
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
                <a href="attendance_reports.php" class="nav-item">
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
                    <input type="text" placeholder="Search students...">
                </div>
                <div class="top-actions">
                    <div class="profile">
                        <div class="avatar"><?php echo strtoupper(substr($_SESSION['name'], 0, 1)); ?></div>
                        <div class="profile-name">Prof. <?php echo htmlspecialchars($_SESSION['name']); ?></div>
                    </div>
                </div>
            </header>

            <div class="content">
                <!-- Back button -->
                <a href="faculty_sessions.php" class="btn outline" style="margin-bottom: 1rem;">
                    ← Back to Sessions
                </a>

                <!-- Session Header -->
                <div class="session-header">
                    <h1 style="color: white; margin-bottom: 0.5rem;">
                        <?php echo htmlspecialchars($session['course_code'] . ' - ' . $session['course_name']); ?>
                    </h1>
                    <p style="opacity: 0.9; margin-bottom: 0.5rem;">
                        <?php echo date('F j, Y', strtotime($session['session_date'])); ?> • 
                        <?php echo date('g:i A', strtotime($session['start_time'])); ?> - 
                        <?php echo date('g:i A', strtotime($session['end_time'])); ?>
                    </p>
                    <p style="opacity: 0.9;">
                        Session Type: <?php echo ucfirst($session['session_type']); ?>
                        <?php if ($session['attendance_code']): ?>
                            • Attendance Code: <code style="background: rgba(255,255,255,0.2); padding: 0.25rem 0.5rem; border-radius: 4px;">
                                <?php echo htmlspecialchars($session['attendance_code']); ?>
                            </code>
                        <?php endif; ?>
                    </p>
                </div>

                <!-- Statistics -->
                <div class="stats-cards">
                    <div class="stat-card attendance-rate">
                        <small>Attendance Rate</small>
                        <h3 style="margin: 0.5rem 0; color: var(--primary-red);"><?php echo $attendance_rate; ?>%</h3>
                    </div>
                    <div class="stat-card">
                        <small>Present</small>
                        <h3 style="margin: 0.5rem 0; color: var(--success-green);"><?php echo $present_count; ?></h3>
                    </div>
                    <div class="stat-card">
                        <small>Late</small>
                        <h3 style="margin: 0.5rem 0; color: var(--warning-orange);"><?php echo $late_count; ?></h3>
                    </div>
                    <div class="stat-card">
                        <small>Absent</small>
                        <h3 style="margin: 0.5rem 0; color: var(--error-red);"><?php echo $absent_count; ?></h3>
                    </div>
                </div>

                <!-- Export Options -->
                <div class="export-buttons">
                    <button onclick="window.print()" class="btn outline">
                        Print Report
                    </button>
                    <a href="export_attendance.php?session_id=<?php echo $session_id; ?>&format=csv" 
                       class="btn outline">
                        Export as CSV
                    </a>
                    <a href="export_attendance.php?session_id=<?php echo $session_id; ?>&format=pdf" 
                       class="btn outline">
                        Export as PDF
                    </a>
                </div>

                <!-- Attendance Table -->
                <div class="card">
                    <div style="padding: 1.5rem;">
                        <h3 style="margin-bottom: 1rem;">Attendance Records</h3>
                        
                        <?php if (empty($attendance_records)): ?>
                            <p class="muted" style="text-align: center; padding: 2rem;">No attendance records found.</p>
                        <?php else: ?>
                            <table class="attendance-table">
                                <thead>
                                    <tr>
                                        <th>Student Name</th>
                                        <th>Email</th>
                                        <th>Status</th>
                                        <th>Marked By</th>
                                        <th>Time</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($attendance_records as $record): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($record['name']); ?></td>
                                            <td><?php echo htmlspecialchars($record['email']); ?></td>
                                            <td>
                                                <span class="status-badge status-<?php echo $record['status']; ?>">
                                                    <?php echo ucfirst($record['status']); ?>
                                                </span>
                                            </td>
                                            <td><?php echo ucfirst($record['marked_by']); ?></td>
                                            <td>
                                                <?php echo $record['marked_at'] ? date('M j, g:i A', strtotime($record['marked_at'])) : 'Not marked'; ?>
                                            </td>
                                            <td>
                                                <div style="display: flex; gap: 0.25rem;">
                                                    <?php if ($record['status'] !== 'present'): ?>
                                                        <form method="POST" action="update_attendance.php" style="display: inline;">
                                                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                                            <input type="hidden" name="attendance_id" value="<?php echo $record['id']; ?>">
                                                            <input type="hidden" name="status" value="present">
                                                            <button type="submit" class="btn primary small">Mark Present</button>
                                                        </form>
                                                    <?php endif; ?>
                                                    <?php if ($record['status'] !== 'late'): ?>
                                                        <form method="POST" action="update_attendance.php" style="display: inline;">
                                                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                                            <input type="hidden" name="attendance_id" value="<?php echo $record['id']; ?>">
                                                            <input type="hidden" name="status" value="late">
                                                            <button type="submit" class="btn outline small">Mark Late</button>
                                                        </form>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script src="script.js"></script>
</body>
</html>