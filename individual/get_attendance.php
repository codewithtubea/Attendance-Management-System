<?php
require_once 'includes/auth.php';
require_once 'includes/database.php';
redirectIfNotLoggedIn();

if (!isFaculty()) {
    die('Access denied');
}

if (!isset($_GET['session_id'])) {
    die('Session ID required');
}

$session_id = intval($_GET['session_id']);

// Verify faculty owns the session
$stmt = $pdo->prepare("
    SELECT s.*, c.course_code, c.course_name 
    FROM sessions s
    JOIN courses c ON s.course_id = c.id
    WHERE s.id = ? AND c.faculty_id = ?
");
$stmt->execute([$session_id, $_SESSION['user_id']]);
$session = $stmt->fetch();

if (!$session) {
    die('Session not found or access denied');
}

// Get enrolled students and their attendance
$stmt = $pdo->prepare("
    SELECT u.id, u.name, u.email, a.status, a.marked_at
    FROM enrollments e
    JOIN users u ON e.student_id = u.id
    LEFT JOIN attendance a ON a.student_id = u.id AND a.session_id = ?
    WHERE e.course_id = ? AND e.status = 'approved'
    ORDER BY u.name
");
$stmt->execute([$session_id, $session['course_id']]);
$students = $stmt->fetchAll();
?>

<h4><?php echo htmlspecialchars($session['course_code'] . ' - ' . $session['course_name']); ?></h4>
<p class="muted">Session: <?php echo date('F j, Y', strtotime($session['session_date'])); ?></p>

<?php if ($session['attendance_code']): ?>
    <div class="code-display" style="margin: 1rem 0;">
        <strong>Attendance Code:</strong><br>
        <code><?php echo htmlspecialchars($session['attendance_code']); ?></code>
    </div>
<?php endif; ?>

<table class="attendance-table">
    <thead>
        <tr>
            <th>Student</th>
            <th>Email</th>
            <th>Current Status</th>
            <th>Marked At</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($students as $student): ?>
            <tr>
                <td><?php echo htmlspecialchars($student['name']); ?></td>
                <td><?php echo htmlspecialchars($student['email']); ?></td>
                <td>
                    <?php if ($student['status']): ?>
                        <span class="status-badge status-<?php echo $student['status']; ?>">
                            <?php echo ucfirst($student['status']); ?>
                        </span>
                    <?php else: ?>
                        <span class="status-badge status-absent">Absent</span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php echo $student['marked_at'] ? date('g:i A', strtotime($student['marked_at'])) : 'Not marked'; ?>
                </td>
                <td>
                    <div style="display: flex; gap: 0.25rem;">
                        <button onclick="markAttendance(<?php echo $session_id; ?>, <?php echo $student['id']; ?>, 'present')" 
                                class="btn primary small">
                            Present
                        </button>
                        <button onclick="markAttendance(<?php echo $session_id; ?>, <?php echo $student['id']; ?>, 'late')" 
                                class="btn outline small">
                            Late
                        </button>
                        <button onclick="markAttendance(<?php echo $session_id; ?>, <?php echo $student['id']; ?>, 'absent')" 
                                class="btn outline small">
                            Absent
                        </button>
                    </div>
                </td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>