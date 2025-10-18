<?php
session_start();
require_once "include/config.php";

if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'student'){
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
include "include/header.php";

// Fetch attendance summary
$summary_sql = "
    SELECT 
        COUNT(*) AS total_sessions,
        SUM(CASE WHEN status='present' THEN 1 ELSE 0 END) AS attended,
        SUM(CASE WHEN status='absent' THEN 1 ELSE 0 END) AS missed,
        SUM(CASE WHEN session_type='lab' THEN 1 ELSE 0 END) AS lab_sessions,
        SUM(CASE WHEN session_type='practical' THEN 1 ELSE 0 END) AS practical_sessions
    FROM attendance a
    JOIN sessions s ON a.session_id = s.session_id
    WHERE a.student_id = ?
";

$stmt = $conn->prepare($summary_sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$summary = $stmt->get_result()->fetch_assoc();
?>

<div class="dashboard-container">
    <aside class="sidebar">
        <h2>Attendance System</h2>
        <ul>
            <li><a href="dashboard.php">Dashboard</a></li>
            <li><a href="courses.php">Courses</a></li>
            <li><a href="attendance.php">Attendance</a></li>
            <li><a href="logout.php">Logout</a></li>
        </ul>
    </aside>

    <main class="main-content">
        <h1>Welcome, <?php echo $_SESSION['name']; ?></h1>

        <!-- Attendance Summary Cards -->
        <div class="summary-cards">
            <div class="card">
                <h3>Total Sessions</h3>
                <p><?php echo $summary['total_sessions']; ?></p>
            </div>
            <div class="card">
                <h3>Attended</h3>
                <p><?php echo $summary['attended']; ?></p>
            </div>
            <div class="card">
                <h3>Missed</h3>
                <p><?php echo $summary['missed']; ?></p>
            </div>
            <div class="card">
                <h3>Lab Sessions</h3>
                <p><?php echo $summary['lab_sessions']; ?></p>
            </div>
            <div class="card">
                <h3>Practical Sessions</h3>
                <p><?php echo $summary['practical_sessions']; ?></p>
            </div>
        </div>

        <!-- Expandable Full Attendance Table -->
        <button id="toggleAttendance" class="btn">View Full Attendance</button>
        <div id="fullAttendance" style="display:none;">
            <table class="attendance-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Course</th>
                        <th>Session Type</th>
                        <th>Status</th>
                        <th>Notes</th>
                    </tr>
                </thead>
                <tbody>
                <?php
                $stmt = $conn->prepare("
                    SELECT s.session_date, c.course_name, s.session_type, a.status, s.notes
                    FROM attendance a
                    JOIN sessions s ON a.session_id = s.session_id
                    JOIN courses c ON s.course_id = c.course_id
                    WHERE a.student_id = ?
                    ORDER BY s.session_date DESC
                ");
                $stmt->bind_param("i", $user_id);
                $stmt->execute();
                $result = $stmt->get_result();
                while($row = $result->fetch_assoc()):
                ?>
                    <tr>
                        <td><?php echo $row['session_date']; ?></td>
                        <td><?php echo $row['course_name']; ?></td>
                        <td><?php echo ucfirst($row['session_type']); ?></td>
                        <td><?php echo ucfirst($row['status']); ?></td>
                        <td><?php echo $row['notes'] ?: "-"; ?></td>
                    </tr>
                <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </main>
</div>

<link rel="stylesheet" href="../css/style.css">
<script src="../js/script.js"></script>

<script>
// Toggle full attendance table
document.getElementById('toggleAttendance').addEventListener('click', function(){
    const table = document.getElementById('fullAttendance');
    if(table.style.display === 'none'){
        table.style.display = 'block';
        this.textContent = "Hide Full Attendance";
    } else {
        table.style.display = 'none';
        this.textContent = "View Full Attendance";
    }
});
</script>
