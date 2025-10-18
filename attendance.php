<?php
session_start();
require "include/config.php";
include "include/header.php";

if(!isset($_SESSION['user_id'])){ header("Location: login.php"); exit; }

$user_id=$_SESSION['user_id'];
$role=$_SESSION['role'];
?>

<div class="dashboard-container">
<aside class="sidebar">
    <h2>Attendance System</h2>
    <ul>
        <li><a href="dashboard.php">Dashboard</a></li>
        <li><a href="attendance.php">Attendance</a></li>
        <li><a href="logout.php">Logout</a></li>
    </ul>
</aside>

<main class="main-content">
<h1>Attendance</h1>

<?php if($role=="faculty"): ?>
<form method="POST">
    <label>Select Course:</label>
    <select name="course_id" required>
    <?php
    $stmt=$conn->prepare("SELECT course_id, course_name FROM courses WHERE instructor_id=?");
    $stmt->bind_param("i",$user_id);
    $stmt->execute();
    $courses=$stmt->get_result();
    while($c=$courses->fetch_assoc()) echo "<option value='{$c['course_id']}'>{$c['course_name']}</option>";
    ?>
    </select>
    <button type="submit" name="load_sessions">Load Sessions</button>
</form>

<?php
if(isset($_POST['load_sessions'])){
    $course_id=$_POST['course_id'];
    $stmt=$conn->prepare("SELECT * FROM sessions WHERE course_id=? ORDER BY session_date DESC");
    $stmt->bind_param("i",$course_id);
    $stmt->execute();
    $sessions=$stmt->get_result();

    while($s=$sessions->fetch_assoc()){
        echo "<div class='session-card'>";
        echo "<h3>{$s['session_date']} - ".ucfirst($s['session_type'])."</h3>";
        echo "<p>Notes: ".($s['notes'] ?: "No notes")."</p>";
        echo "</div>";
    }
}
?>

<?php else: ?>
<p>Students can view their dashboard for attendance records.</p>
<?php endif; ?>
</main>
</div>
<script src="js/script.js"></script>
<link rel
