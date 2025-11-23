<?php
require_once 'includes/auth.php';
require_once 'includes/database.php';
redirectIfNotLoggedIn();

if (!isFaculty()) {
    header('Location: dashboard.php');
    exit();
}

// Handle course creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['course_code'])) {
    $course_code = trim($_POST['course_code']);
    $course_name = trim($_POST['course_name']);
    $description = trim($_POST['description']);
    $faculty_id = $_SESSION['user_id'];
    
    $errors = [];
    
    if (empty($course_code) || empty($course_name)) {
        $errors[] = "Course code and name are required";
    }
    
    if (empty($errors)) {
        $stmt = $pdo->prepare("INSERT INTO courses (course_code, course_name, description, faculty_id) VALUES (?, ?, ?, ?)");
        if ($stmt->execute([$course_code, $course_name, $description, $faculty_id])) {
            $success = "Course created successfully!";
        } else {
            $errors[] = "Failed to create course";
        }
    }
}

// Handle request approval/rejection
if (isset($_GET['action']) && isset($_GET['enrollment_id'])) {
    $enrollment_id = $_GET['enrollment_id'];
    $action = $_GET['action'];
    
    if (in_array($action, ['approve', 'reject'])) {
        $status = $action === 'approve' ? 'approved' : 'rejected';
        $stmt = $pdo->prepare("UPDATE enrollments SET status = ? WHERE id = ?");
        $stmt->execute([$status, $enrollment_id]);
        
        header('Location: faculty_dashboard.php');
        exit();
    }
}

// Get faculty's courses
$courses_stmt = $pdo->prepare("SELECT * FROM courses WHERE faculty_id = ?");
$courses_stmt->execute([$_SESSION['user_id']]);
$courses = $courses_stmt->fetchAll();

// Get pending requests for faculty's courses
$requests_stmt = $pdo->prepare("
    SELECT e.id as enrollment_id, e.status, c.course_code, c.course_name, u.name as student_name, u.email as student_email
    FROM enrollments e
    JOIN courses c ON e.course_id = c.id
    JOIN users u ON e.student_id = u.id
    WHERE c.faculty_id = ? AND e.status = 'pending'
");
$requests_stmt->execute([$_SESSION['user_id']]);
$pending_requests = $requests_stmt->fetchAll();

// Get stats
$total_courses = count($courses);
$total_requests = count($pending_requests);
$approved_students_stmt = $pdo->prepare("
    SELECT COUNT(DISTINCT student_id) as count 
    FROM enrollments e 
    JOIN courses c ON e.course_id = c.id 
    WHERE c.faculty_id = ? AND e.status = 'approved'
");
$approved_students_stmt->execute([$_SESSION['user_id']]);
$approved_students = $approved_students_stmt->fetch()['count'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Faculty Dashboard - Ashesi Portal</title>
  <link rel="stylesheet" href="style.css" />
</head>
<body>
  <div class="app">
    <aside class="sidebar">
      <div class="brand">
        <div class="brand-mark">A</div>
        <div class="brand-text">
          <strong>Ashesi</strong>
          <small>Portal</small>
        </div>
      </div>
      <nav class="nav">
        <button class="nav-item active" onclick="location.href='faculty_dashboard.php'"><span class="icon">🏠</span><span class="label">Dashboard</span></button>
        <button class="nav-item logout" onclick="location.href='logout.php'"><span class="icon">🚪</span><span class="label">Logout</span></button>
      </nav>
    </aside>

    <div class="main">
      <header class="topbar">
        <h1>Welcome, Prof. <?php echo htmlspecialchars($_SESSION['name']); ?> 👋</h1>
        <div class="top-actions">
          <div class="profile">
            <div class="avatar"><?php echo strtoupper(substr($_SESSION['name'], 0, 1)); ?></div>
            <div class="profile-name">Prof. <?php echo htmlspecialchars($_SESSION['name']); ?></div>
          </div>
        </div>
      </header>

      <main class="content">
        <?php if (isset($success)): ?>
          <div class="success" style="margin: 1rem;"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        
        <?php if (isset($errors)): ?>
          <div class="error" style="margin: 1rem;">
            <?php foreach ($errors as $error): ?>
              <p><?php echo htmlspecialchars($error); ?></p>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>

        <section class="stats-grid">
          <div class="stat large">
            <small>Total Courses</small>
            <h3><?php echo $total_courses; ?></h3>
            <p class="muted">Courses you are teaching</p>
          </div>
          <div class="stat">
            <small>Pending Requests</small>
            <h3><?php echo $total_requests; ?></h3>
          </div>
          <div class="stat">
            <small>Enrolled Students</small>
            <h3><?php echo $approved_students; ?></h3>
          </div>
        </section>

        <section class="section">
          <div class="section-header">
            <h2>Create New Course</h2>
          </div>
          <form method="POST" class="course-form" style="max-width: 500px;">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
              <div>
                <label style="display: block; margin-bottom: 0.5rem; font-weight: 600;">Course Code</label>
                <input type="text" name="course_code" required style="width: 100%; padding: 0.5rem; border: 1px solid #ddd; border-radius: 8px;">
              </div>
              <div>
                <label style="display: block; margin-bottom: 0.5rem; font-weight: 600;">Course Name</label>
                <input type="text" name="course_name" required style="width: 100%; padding: 0.5rem; border: 1px solid #ddd; border-radius: 8px;">
              </div>
            </div>
            <div style="margin-bottom: 1rem;">
              <label style="display: block; margin-bottom: 0.5rem; font-weight: 600;">Description</label>
              <textarea name="description" rows="3" style="width: 100%; padding: 0.5rem; border: 1px solid #ddd; border-radius: 8px;"></textarea>
            </div>
            <button type="submit" class="btn primary">Create Course</button>
          </form>
        </section>

        <section class="section">
          <div class="section-header">
            <h2>Your Courses</h2>
          </div>
          <?php if (empty($courses)): ?>
            <p class="muted">You haven't created any courses yet.</p>
          <?php else: ?>
            <div class="courses-grid large">
              <?php foreach ($courses as $course): ?>
                <article class="course-card">
                  <h3><?php echo htmlspecialchars($course['course_name']); ?></h3>
                  <p class="muted"><?php echo htmlspecialchars($course['course_code']); ?></p>
                  <p style="margin: 0.5rem 0; font-size: 0.9rem;"><?php echo htmlspecialchars($course['description']); ?></p>
                  <div class="course-meta">
                    <span class="badge lecture">Active</span>
                  </div>
                </article>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </section>

        <section class="section">
          <div class="section-header">
            <h2>Student Join Requests</h2>
            <div class="muted">Approve or reject enrollment requests</div>
          </div>
          <?php if (empty($pending_requests)): ?>
            <p class="muted">No pending requests.</p>
          <?php else: ?>
            <div class="courses-grid">
              <?php foreach ($pending_requests as $request): ?>
                <article class="course-card">
                  <h3><?php echo htmlspecialchars($request['course_name']); ?></h3>
                  <p class="muted"><?php echo htmlspecialchars($request['course_code']); ?></p>
                  <p><strong>Student:</strong> <?php echo htmlspecialchars($request['student_name']); ?></p>
                  <p class="muted"><?php echo htmlspecialchars($request['student_email']); ?></p>
                  <div class="card-actions">
                    <a href="faculty_dashboard.php?action=approve&enrollment_id=<?php echo $request['enrollment_id']; ?>" 
                       class="btn primary">Approve</a>
                    <a href="faculty_dashboard.php?action=reject&enrollment_id=<?php echo $request['enrollment_id']; ?>" 
                       class="btn outline">Reject</a>
                  </div>
                </article>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </section>
      </main>
    </div>
  </div>

  <script src="script.js"></script>
</body>
</html>