<?php
require_once 'includes/auth.php';
require_once 'includes/database.php';
redirectIfNotLoggedIn();

if (!isStudent()) {
    header('Location: faculty_dashboard.php');
    exit();
}

// Initialize variables
$error = '';
$success = '';
$attendance_code = '';
$session_info = null;

// Check for code in URL (for QR code scanning)
if (isset($_GET['code'])) {
    $attendance_code = strtoupper(trim($_GET['code']));
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        die('Invalid CSRF token');
    }
    
    $attendance_code = trim($_POST['attendance_code']);
    $student_id = $_SESSION['user_id'];
    
    if (empty($attendance_code)) {
        $error = 'Please enter an attendance code';
    } elseif (!preg_match('/^[0-9]{4,6}$/', $attendance_code)) {
        $error = 'Invalid code format. Please enter 4-6 numbers (e.g., 2450)';
    } else {
        // Find active session with this code for TODAY
        $stmt = $pdo->prepare("
            SELECT s.*, c.course_name, c.course_code 
            FROM sessions s
            JOIN courses c ON s.course_id = c.id
            WHERE s.attendance_code = ? 
            AND s.session_date = CURDATE()
            AND s.course_id IN (
                SELECT course_id FROM enrollments 
                WHERE student_id = ? AND status = 'approved'
            )
        ");
        $stmt->execute([$attendance_code, $student_id]);
        $session = $stmt->fetch();
        
        if ($session) {
            // Check if already marked attendance
            $check_stmt = $pdo->prepare("
                SELECT id, status FROM attendance 
                WHERE session_id = ? AND student_id = ?
            ");
            $check_stmt->execute([$session['id'], $student_id]);
            $existing = $check_stmt->fetch();
            
            if ($existing) {
                // Already marked attendance
                if ($existing['status'] === 'present' || $existing['status'] === 'late') {
                    $error = 'You have already marked attendance for this session';
                } else {
                    // Update from absent to present
                    $start_time = strtotime($session['start_time']);
                    $current_time = time();
                    $late_threshold = 15 * 60; // 15 minutes in seconds
                    
                    $status = ($current_time - $start_time) > $late_threshold ? 'late' : 'present';
                    
                    $update_stmt = $pdo->prepare("
                        UPDATE attendance 
                        SET status = ?, marked_by = 'student', marked_at = NOW() 
                        WHERE session_id = ? AND student_id = ?
                    ");
                    
                    if ($update_stmt->execute([$status, $session['id'], $student_id])) {
                        $success = $status === 'late' 
                            ? 'Attendance updated successfully (marked as late)' 
                            : 'Attendance updated successfully';
                        $session_info = $session;
                        $attendance_code = '';
                    } else {
                        $error = 'Failed to update attendance';
                    }
                }
            } else {
                // First time marking attendance
                // Determine if late (more than 15 minutes after start)
                $start_time = strtotime($session['start_time']);
                $current_time = time();
                $late_threshold = 15 * 60; // 15 minutes in seconds
                
                $status = ($current_time - $start_time) > $late_threshold ? 'late' : 'present';
                
                // Mark attendance
                $stmt = $pdo->prepare("
                    INSERT INTO attendance (session_id, student_id, status, marked_by, marked_at) 
                    VALUES (?, ?, ?, 'student', NOW())
                ");
                
                if ($stmt->execute([$session['id'], $student_id, $status])) {
                    $success = $status === 'late' 
                        ? 'Attendance marked successfully (marked as late)' 
                        : 'Attendance marked successfully';
                    $session_info = $session;
                    $attendance_code = ''; // Clear input
                } else {
                    $error = 'Failed to mark attendance';
                }
            }
        } else {
            $error = 'Invalid attendance code. Check that:';
            $error .= '<ul style="margin: 0.5rem 0 0 1.5rem;">';
            $error .= '<li>The code is correct (e.g., 2450)</li>';
            $error .= '<li>You are enrolled in the course</li>';
            $error .= '<li>The code is for today\'s session</li>';
            $error .= '<li>The session exists</li>';
            $error .= '</ul>';
        }
    }
}

// Get today's sessions for the student
$stmt = $pdo->prepare("
    SELECT s.*, c.course_name, c.course_code 
    FROM sessions s
    JOIN courses c ON s.course_id = c.id
    WHERE s.session_date = CURDATE()
    AND s.course_id IN (
        SELECT course_id FROM enrollments 
        WHERE student_id = ? AND status = 'approved'
    )
    ORDER BY s.start_time
    LIMIT 5
");
$stmt->execute([$_SESSION['user_id']]);
$today_sessions = $stmt->fetchAll();

// Get recent attendance
$stmt = $pdo->prepare("
    SELECT a.*, c.course_name, c.course_code, s.session_date, s.session_type 
    FROM attendance a
    JOIN sessions s ON a.session_id = s.id
    JOIN courses c ON s.course_id = c.id
    WHERE a.student_id = ?
    ORDER BY a.marked_at DESC
    LIMIT 10
");
$stmt->execute([$_SESSION['user_id']]);
$recent_attendance = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mark Attendance - Ashesi Portal</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .attendance-instructions {
            background: linear-gradient(135deg, var(--light-red), white);
            padding: 1.5rem;
            border-radius: var(--radius-md);
            margin: 2rem 0;
            border: 1px solid var(--border-color);
        }
        
        .instruction-steps {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-top: 1rem;
        }
        
        .step {
            background: white;
            padding: 1rem;
            border-radius: var(--radius-sm);
            text-align: center;
            border: 1px solid var(--border-color);
        }
        
        .step-number {
            display: inline-block;
            width: 30px;
            height: 30px;
            background: var(--primary-red);
            color: white;
            border-radius: 50%;
            line-height: 30px;
            font-weight: bold;
            margin-bottom: 0.5rem;
        }
        
        .code-input {
            font-size: 1.5rem !important;
            text-align: center !important;
            letter-spacing: 5px !important;
            font-weight: bold !important;
        }
        
        .today-code {
            background: var(--light-red);
            padding: 0.75rem;
            border-radius: var(--radius-sm);
            font-family: monospace;
            font-size: 1.25rem;
            letter-spacing: 2px;
            text-align: center;
            margin: 0.5rem 0;
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
                <a href="mark_attendance.php" class="nav-item active">
                    <span class="icon">✓</span>
                    <span class="label">Mark Attendance</span>
                </a>
                <a href="student_attendance_report.php" class="nav-item">
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
                    <input type="text" placeholder="Search sessions...">
                </div>
                <div class="top-actions">
                    <div class="profile">
                        <div class="avatar"><?php echo strtoupper(substr($_SESSION['name'], 0, 1)); ?></div>
                        <div class="profile-name"><?php echo htmlspecialchars($_SESSION['name']); ?></div>
                    </div>
                </div>
            </header>

            <div class="content">
                <!-- Instructions -->
                <div class="attendance-instructions">
                    <h2 style="margin-bottom: 0.5rem;">Mark Your Attendance</h2>
                    <p class="muted">Use the attendance code provided by your faculty during class</p>
                    
                    <div class="instruction-steps">
                        <div class="step">
                            <div class="step-number">1</div>
                            <p>Get the attendance code from your faculty (e.g., 2450)</p>
                        </div>
                        <div class="step">
                            <div class="step-number">2</div>
                            <p>Enter the code in the field below</p>
                        </div>
                        <div class="step">
                            <div class="step-number">3</div>
                            <p>Click "Mark My Attendance"</p>
                        </div>
                        <div class="step">
                            <div class="step-number">4</div>
                            <p>Receive confirmation of successful attendance</p>
                        </div>
                    </div>
                </div>

                <!-- Attendance Form -->
                <div class="card" style="margin-bottom: 2rem;">
                    <div style="padding: 2rem;">
                        <h2 style="margin-bottom: 0.5rem; text-align: center;">Enter Attendance Code</h2>
                        <p class="muted" style="text-align: center; margin-bottom: 1.5rem;">
                            Codes are usually 4-6 numbers (e.g., 2450)
                        </p>

                        <?php if ($success): ?>
                            <div class="alert success">
                                <div style="font-size: 2rem; text-align: center; margin-bottom: 1rem;">✅</div>
                                <h3 style="text-align: center; margin-bottom: 0.5rem;">Success!</h3>
                                <p style="text-align: center;"><?php echo htmlspecialchars($success); ?></p>
                                <?php if ($session_info): ?>
                                    <div style="text-align: center; margin-top: 1rem; padding: 1rem; background: rgba(34,197,94,0.1); border-radius: var(--radius-sm);">
                                        <p style="margin: 0; font-weight: 500;">
                                            Course: <?php echo htmlspecialchars($session_info['course_code']); ?><br>
                                            Session: <?php echo htmlspecialchars($session_info['course_name']); ?><br>
                                            <small>Date: <?php echo date('F j, Y', strtotime($session_info['session_date'])); ?></small>
                                        </p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>

                        <?php if ($error): ?>
                            <div class="alert error">
                                <div style="font-size: 2rem; text-align: center; margin-bottom: 1rem;">❌</div>
                                <h3 style="text-align: center; margin-bottom: 0.5rem;">Unable to Mark Attendance</h3>
                                <div><?php echo $error; ?></div>
                            </div>
                        <?php endif; ?>

                        <form method="POST" action="mark_attendance.php">
                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                            
                            <div class="form-group">
                                <label class="form-label">Attendance Code</label>
                                <input type="text" name="attendance_code" class="form-input code-input" required
                                       value="<?php echo htmlspecialchars($attendance_code); ?>"
                                       placeholder="2450" 
                                       maxlength="6"
                                       pattern="[0-9]*"
                                       title="Enter numbers only (e.g., 2450)"
                                       autocomplete="off"
                                       autofocus>
                                <small class="muted" style="text-align: center; display: block;">
                                    Enter the code shown by your faculty
                                </small>
                            </div>
                            
                            <button type="submit" class="btn primary" style="width: 100%; padding: 1rem; font-size: 1.1rem;">
                                📱 Mark My Attendance
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Today's Sessions with Codes -->
                <?php if (!empty($today_sessions)): ?>
                    <div style="margin-bottom: 2rem;">
                        <h3 style="margin-bottom: 1rem;">Today's Sessions</h3>
                        <div class="courses-grid">
                            <?php foreach ($today_sessions as $session): ?>
                                <div class="course-card">
                                    <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 1rem;">
                                        <div>
                                            <h3 style="margin: 0;"><?php echo htmlspecialchars($session['course_code']); ?></h3>
                                            <p class="muted"><?php echo htmlspecialchars($session['course_name']); ?></p>
                                        </div>
                                        <span class="badge <?php echo $session['session_type']; ?>">
                                            <?php echo ucfirst($session['session_type']); ?>
                                        </span>
                                    </div>
                                    
                                    <div style="margin: 1rem 0;">
                                        <p><strong>Time:</strong> <?php echo date('g:i A', strtotime($session['start_time'])); ?> - 
                                           <?php echo date('g:i A', strtotime($session['end_time'])); ?></p>
                                        
                                        <?php if ($session['attendance_code']): ?>
                                            <div class="today-code">
                                                <small>Attendance Code:</small><br>
                                                <strong><?php echo htmlspecialchars($session['attendance_code']); ?></strong>
                                            </div>
                                            <p class="muted" style="text-align: center; font-size: 0.9rem;">
                                                Use this code to mark attendance
                                            </p>
                                        <?php else: ?>
                                            <p class="muted" style="text-align: center;">No attendance code set yet</p>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <!-- Check if already attended -->
                                    <?php 
                                    $check_stmt = $pdo->prepare("SELECT status FROM attendance WHERE session_id = ? AND student_id = ?");
                                    $check_stmt->execute([$session['id'], $_SESSION['user_id']]);
                                    $attendance_status = $check_stmt->fetch();
                                    ?>
                                    
                                    <?php if ($attendance_status): ?>
                                        <div style="text-align: center; padding: 0.75rem; background: rgba(34,197,94,0.1); border-radius: var(--radius-sm);">
                                            <span class="badge approved">✓ Attendance Marked</span>
                                            <p style="margin: 0.5rem 0 0; font-size: 0.9rem;">
                                                Status: <?php echo ucfirst($attendance_status['status']); ?>
                                            </p>
                                        </div>
                                    <?php elseif ($session['attendance_code']): ?>
                                        <div style="text-align: center; margin-top: 1rem;">
                                            <button onclick="document.querySelector('.code-input').value = '<?php echo $session['attendance_code']; ?>'; 
                                                         document.querySelector('.code-input').focus();" 
                                                    class="btn primary small">
                                                Use This Code
                                            </button>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Recent Attendance -->
                <?php if (!empty($recent_attendance)): ?>
                    <div>
                        <h3 style="margin-bottom: 1rem;">Recent Attendance</h3>
                        <div class="card">
                            <div style="padding: 1rem;">
                                <div class="table">
                                    <table style="width: 100%;">
                                        <thead>
                                            <tr>
                                                <th>Course</th>
                                                <th>Date</th>
                                                <th>Time</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($recent_attendance as $record): ?>
                                                <tr>
                                                    <td>
                                                        <strong><?php echo htmlspecialchars($record['course_code']); ?></strong><br>
                                                        <small class="muted"><?php echo htmlspecialchars($record['course_name']); ?></small>
                                                    </td>
                                                    <td><?php echo date('M j', strtotime($record['session_date'])); ?></td>
                                                    <td><?php echo date('g:i A', strtotime($record['marked_at'])); ?></td>
                                                    <td>
                                                        <?php if ($record['status'] === 'present'): ?>
                                                            <span class="badge approved">Present</span>
                                                        <?php elseif ($record['status'] === 'late'): ?>
                                                            <span class="badge pending">Late</span>
                                                        <?php else: ?>
                                                            <span class="badge" style="background: rgba(239,68,68,0.1); color: var(--error-red); border-color: rgba(239,68,68,0.2);">Absent</span>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <script src="script.js"></script>
    <script>
    // Auto-focus on code input
    document.addEventListener('DOMContentLoaded', function() {
        const codeInput = document.querySelector('.code-input');
        if (codeInput) {
            codeInput.focus();
            
            // Only allow numbers
            codeInput.addEventListener('input', function() {
                this.value = this.value.replace(/[^0-9]/g, '');
            });
        }
        
        // Auto-fill code when clicking "Use This Code"
        document.querySelectorAll('button').forEach(btn => {
            if (btn.textContent.includes('Use This Code')) {
                btn.addEventListener('click', function() {
                    // Also scroll to the form
                    document.querySelector('.code-input').scrollIntoView({ behavior: 'smooth' });
                });
            }
        });
    });
    </script>
</body>
</html>