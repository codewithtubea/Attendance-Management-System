<?php
require_once 'includes/auth.php';
require_once 'includes/database.php';
redirectIfNotLoggedIn();

if (!isStudent()) {
    header('Location: faculty_dashboard.php');
    exit();
}

// Validate CSRF token
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !validateCSRFToken($_POST['csrf_token'] ?? '')) {
    die('Invalid request');
}

$course_id = intval($_POST['course_id']);
$student_id = $_SESSION['user_id'];

// Validate course exists
$stmt = $pdo->prepare("SELECT id, course_code, course_name FROM courses WHERE id = ?");
$stmt->execute([$course_id]);
$course = $stmt->fetch();

if (!$course) {
    $_SESSION['error'] = "Course not found";
    header('Location: student_dashboard.php');
    exit();
}

// Check if request already exists
$stmt = $pdo->prepare("SELECT id FROM enrollments WHERE student_id = ? AND course_id = ?");
$stmt->execute([$student_id, $course_id]);

if ($stmt->fetch()) {
    $_SESSION['error'] = "You have already requested to join this course.";
} else {
    // Create enrollment request
    $stmt = $pdo->prepare("INSERT INTO enrollments (student_id, course_id, status) VALUES (?, ?, 'pending')");
    
    try {
        if ($stmt->execute([$student_id, $course_id])) {
            $_SESSION['success'] = "Join request sent successfully for {$course['course_code']}! Waiting for faculty approval.";
        } else {
            $_SESSION['error'] = "Failed to send join request.";
        }
    } catch (PDOException $e) {
        error_log("Enrollment request failed: " . $e->getMessage());
        $_SESSION['error'] = "Failed to send join request.";
    }
}

header('Location: student_dashboard.php');
exit();
?>