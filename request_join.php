<?php
require_once 'includes/auth.php';
require_once 'includes/database.php';
redirectIfNotLoggedIn();

if (!isStudent()) {
    header('Location: dashboard.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['course_id'])) {
    $course_id = $_POST['course_id'];
    $student_id = $_SESSION['user_id'];
    
    // Check if request already exists
    $stmt = $pdo->prepare("SELECT id FROM enrollments WHERE student_id = ? AND course_id = ?");
    $stmt->execute([$student_id, $course_id]);
    
    if (!$stmt->fetch()) {
        $stmt = $pdo->prepare("INSERT INTO enrollments (student_id, course_id, status) VALUES (?, ?, 'pending')");
        if ($stmt->execute([$student_id, $course_id])) {
            $_SESSION['success'] = "Join request sent successfully!";
        } else {
            $_SESSION['error'] = "Failed to send join request.";
        }
    } else {
        $_SESSION['error'] = "You have already requested to join this course.";
    }
}

header('Location: student_dashboard.php');
exit();
?>