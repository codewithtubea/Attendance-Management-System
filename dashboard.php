<?php
session_start();
require_once "include/config.php";
include "include/header.php";

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];
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

        <?php if($role == 'student'): ?>
            <h2>Your Attendance</h2>
            <table class="attendance-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Course</th>
                        <th>Session Type</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                <?php
                $stmt = $conn->prepare("
                    SELECT s.session_date, c.course_name, s.session_type, a.status
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
                    </tr>
                <?php endwhile; ?>
                </tbody>
            </table>

        <?php elseif($role == 'faculty'): ?>
            <h2>Manage Sessions</h2>
            <a href="attendance_history.php" class="btn">Go to Attendance</a>
        <?php endif; ?>
    </main>
</div>

<script src="../js/script.js"></script>
