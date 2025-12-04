<?php
require_once 'includes/auth.php';
require_once 'includes/database.php';
redirectIfNotLoggedIn();

// Check if user is admin - STRICT CHECK
if (!isAdmin()) {
    // Log unauthorized access attempt
    error_log("Unauthorized admin access attempt by user_id: " . $_SESSION['user_id']);
    
    // Clear sensitive session data
    unset($_SESSION['csrf_token']);
    session_regenerate_id(true);
    
    // Redirect with error
    $_SESSION['error'] = "Unauthorized access. Admin privileges required.";
    header('Location: dashboard.php');
    exit();
}

// Validate session age (force re-login after 30 minutes)
if (isset($_SESSION['login_time']) && (time() - $_SESSION['login_time'] > 1800)) {
    session_destroy();
    header('Location: login.php?error=Session expired. Please login again.');
    exit();
}

// Handle admin actions with CSRF validation
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
        die('Invalid CSRF token. Security violation logged.');
    }
    
    // Sanitize all inputs
    $action = $_POST['action'] ?? '';
    $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
    $course_id = isset($_POST['course_id']) ? intval($_POST['course_id']) : 0;
    
    switch($action) {
        case 'change_role':
            $new_role = sanitizeInput($_POST['new_role']);
            if (in_array($new_role, ['student', 'faculty', 'admin'])) {
                $stmt = $pdo->prepare("UPDATE users SET role = ? WHERE id = ?");
                $stmt->execute([$new_role, $user_id]);
                
                // Log role change
                error_log("Admin {$_SESSION['user_id']} changed user $user_id role to $new_role");
                $_SESSION['success'] = "User role updated successfully!";
            }
            break;
            
        case 'reset_password':
            // Generate secure random password
            $new_password = bin2hex(random_bytes(8)); // 16 character random password
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            
            $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->execute([$hashed_password, $user_id]);
            
            // Log password reset (but not the actual password)
            error_log("Admin {$_SESSION['user_id']} reset password for user $user_id");
            $_SESSION['success'] = "Password reset successful! Temporary password: $new_password";
            break;
            
        case 'delete_course':
            // Verify course exists and get info for logging
            $stmt = $pdo->prepare("SELECT course_code, course_name FROM courses WHERE id = ?");
            $stmt->execute([$course_id]);
            $course = $stmt->fetch();
            
            if ($course) {
                $stmt = $pdo->prepare("DELETE FROM courses WHERE id = ?");
                $stmt->execute([$course_id]);
                
                // Log deletion
                error_log("Admin {$_SESSION['user_id']} deleted course: {$course['course_code']} - {$course['course_name']}");
                $_SESSION['success'] = "Course deleted successfully!";
            }
            break;
            
        case 'toggle_user_status':
            $status = $_POST['status'] === 'active' ? 'active' : 'suspended';
            $stmt = $pdo->prepare("UPDATE users SET status = ? WHERE id = ?");
            $stmt->execute([$status, $user_id]);
            
            error_log("Admin {$_SESSION['user_id']} set user $user_id status to $status");
            $_SESSION['success'] = "User status updated!";
            break;
    }
    
    header('Location: admin_dashboard.php');
    exit();
}

// Get all users with pagination
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

// Get total users for pagination
$total_users_stmt = $pdo->query("SELECT COUNT(*) as total FROM users");
$total_users = $total_users_stmt->fetch()['total'];
$total_pages = ceil($total_users / $limit);

// Get users with pagination
$users_stmt = $pdo->prepare("
    SELECT id, name, email, role, created_at, 
           (SELECT COUNT(*) FROM login_attempts WHERE user_id = users.id AND success = 0 AND attempt_time > DATE_SUB(NOW(), INTERVAL 1 DAY)) as failed_logins
    FROM users 
    ORDER BY created_at DESC 
    LIMIT ? OFFSET ?
");
$users_stmt->bindValue(1, $limit, PDO::PARAM_INT);
$users_stmt->bindValue(2, $offset, PDO::PARAM_INT);
$users_stmt->execute();
$users = $users_stmt->fetchAll();

// Get all courses
$courses_stmt = $pdo->query("
    SELECT c.*, u.name as faculty_name, 
           (SELECT COUNT(*) FROM enrollments WHERE course_id = c.id AND status = 'approved') as student_count
    FROM courses c 
    LEFT JOIN users u ON c.faculty_id = u.id 
    ORDER BY c.created_at DESC
");
$courses = $courses_stmt->fetchAll();

// Get system statistics
$stats = $pdo->query("
    SELECT 
        (SELECT COUNT(*) FROM users) as total_users,
        (SELECT COUNT(*) FROM users WHERE role = 'student') as total_students,
        (SELECT COUNT(*) FROM users WHERE role = 'faculty') as total_faculty,
        (SELECT COUNT(*) FROM users WHERE role = 'admin') as total_admins,
        (SELECT COUNT(*) FROM courses) as total_courses,
        (SELECT COUNT(*) FROM sessions WHERE session_date = CURDATE()) as today_sessions,
        (SELECT COUNT(*) FROM login_attempts WHERE attempt_time > DATE_SUB(NOW(), INTERVAL 24 HOUR) AND success = 0) as failed_logins_24h,
        (SELECT COUNT(*) FROM enrollments WHERE status = 'pending') as pending_enrollments
")->fetch();

// Get recent security events
$security_events = $pdo->query("
    SELECT * FROM login_attempts 
    WHERE success = 0 
    ORDER BY attempt_time DESC 
    LIMIT 10
")->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Ashesi Portal</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .security-alert {
            background: linear-gradient(135deg, #ff6b6b, #c92a2a);
            color: white;
            padding: 1rem;
            border-radius: var(--radius-md);
            margin-bottom: 1.5rem;
        }
        
        .admin-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }
        
        .admin-stat {
            background: white;
            padding: 1.5rem;
            border-radius: var(--radius-md);
            box-shadow: var(--shadow-sm);
            text-align: center;
        }
        
        .admin-stat.security {
            border-left: 4px solid #ff6b6b;
        }
        
        .admin-stat.warning {
            border-left: 4px solid #ffd93d;
        }
        
        .admin-stat.success {
            border-left: 4px solid #51cf66;
        }
        
        .tab-navigation {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 1.5rem;
            border-bottom: 2px solid var(--border-color);
            padding-bottom: 0.5rem;
        }
        
        .tab-btn {
            padding: 0.75rem 1.5rem;
            border: none;
            background: none;
            cursor: pointer;
            font-weight: 500;
            border-radius: var(--radius-sm) var(--radius-sm) 0 0;
            transition: all 0.3s;
        }
        
        .tab-btn.active {
            background: var(--primary-red);
            color: white;
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
        }
        
        .user-status-active { color: #51cf66; }
        .user-status-suspended { color: #ff6b6b; }
        .user-status-pending { color: #ffd93d; }
        
        .security-badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
            margin-left: 0.5rem;
        }
        
        .badge-warning { background: #fff3bf; color: #e67700; }
        .badge-danger { background: #ffc9c9; color: #c92a2a; }
        .badge-success { background: #d3f9d8; color: #2b8a3e; }
        
        .modal-confirm {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 2000;
            align-items: center;
            justify-content: center;
        }
        
        .modal-content {
            background: white;
            border-radius: var(--radius-md);
            max-width: 500px;
            width: 90%;
            box-shadow: var(--shadow-lg);
        }
        
        .export-options {
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
                    <small>Admin</small>
                </div>
            </div>
            
            <nav class="nav">
                <button class="nav-item active" data-tab="dashboard">
                    <span class="icon">📊</span>
                    <span class="label">Dashboard</span>
                </button>
                <button class="nav-item" data-tab="users">
                    <span class="icon">👥</span>
                    <span class="label">Users</span>
                </button>
                <button class="nav-item" data-tab="courses">
                    <span class="icon">📚</span>
                    <span class="label">Courses</span>
                </button>
                <button class="nav-item" data-tab="security">
                    <span class="icon">🔒</span>
                    <span class="label">Security</span>
                </button>
                <button class="nav-item" onclick="showExportModal()">
                    <span class="icon">📤</span>
                    <span class="label">Export</span>
                </button>
            </nav>
            
            <div class="sidebar-footer">
                <a href="logout.php" class="nav-item logout" onclick="return confirmLogout()">
                    <span class="icon">🚪</span>
                    <span class="label">Logout</span>
                </a>
            </div>
        </aside>

        <main class="main">
            <header class="topbar">
                <div class="search">
                    <input type="text" placeholder="Search users, courses..." id="adminSearch">
                </div>
                <div class="top-actions">
                    <button class="btn outline small" onclick="refreshPage()">
                        <span class="icon">🔄</span> Refresh
                    </button>
                    <div class="profile">
                        <div class="avatar">A</div>
                        <div class="profile-name">Admin</div>
                    </div>
                </div>
            </header>

            <div class="content">
                <!-- Security Warning if any -->
                <?php if ($stats['failed_logins_24h'] > 10): ?>
                    <div class="security-alert">
                        <strong>⚠️ Security Alert:</strong> $<?php echo $stats['failed_logins_24h']; ?> failed login attempts in last 24 hours.
                        <a href="#" onclick="showTab('security')" style="color: white; text-decoration: underline;">Review Security Logs</a>
                    </div>
                <?php endif; ?>

                <!-- Success/Error Messages -->
                <?php if (isset($_SESSION['success'])): ?>
                    <div class="alert success">
                        <?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?>
                    </div>
                <?php endif; ?>

                <!-- Tab Navigation -->
                <div class="tab-navigation">
                    <button class="tab-btn active" data-tab="dashboard">Dashboard</button>
                    <button class="tab-btn" data-tab="users">User Management</button>
                    <button class="tab-btn" data-tab="courses">Course Management</button>
                    <button class="tab-btn" data-tab="security">Security</button>
                </div>

                <!-- Dashboard Tab -->
                <div class="tab-content active" id="dashboard-tab">
                    <h1>Admin Dashboard</h1>
                    <p class="muted">System overview and administration</p>

                    <!-- Statistics -->
                    <div class="admin-stats">
                        <div class="admin-stat">
                            <small>Total Users</small>
                            <h3><?php echo $stats['total_users']; ?></h3>
                            <p class="muted">Registered users</p>
                        </div>
                        <div class="admin-stat warning">
                            <small>Pending Enrollments</small>
                            <h3><?php echo $stats['pending_enrollments']; ?></h3>
                            <p class="muted">Awaiting approval</p>
                        </div>
                        <div class="admin-stat">
                            <small>Today's Sessions</small>
                            <h3><?php echo $stats['today_sessions']; ?></h3>
                            <p class="muted">Active sessions</p>
                        </div>
                        <div class="admin-stat security">
                            <small>Failed Logins (24h)</small>
                            <h3><?php echo $stats['failed_logins_24h']; ?></h3>
                            <p class="muted">Security events</p>
                        </div>
                    </div>

                    <!-- Quick Actions -->
                    <div class="card" style="margin: 2rem 0;">
                        <div style="padding: 1.5rem;">
                            <h3 style="margin-bottom: 1rem;">Quick Actions</h3>
                            <div style="display: flex; gap: 1rem; flex-wrap: wrap;">
                                <button class="btn primary" onclick="showTab('users')">
                                    👥 Manage Users
                                </button>
                                <button class="btn outline" onclick="showTab('courses')">
                                    📚 View Courses
                                </button>
                                <button class="btn outline" onclick="showExportModal()">
                                    📤 Export Data
                                </button>
                                <button class="btn outline" onclick="showSystemLogs()">
                                    📋 System Logs
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Recent Activity -->
                    <div class="card">
                        <div style="padding: 1.5rem;">
                            <h3 style="margin-bottom: 1rem;">Recent Security Events</h3>
                            <?php if (empty($security_events)): ?>
                                <p class="muted">No security events in the last 24 hours.</p>
                            <?php else: ?>
                                <div class="table">
                                    <table style="width: 100%;">
                                        <thead>
                                            <tr>
                                                <th>Time</th>
                                                <th>Username</th>
                                                <th>IP Address</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($security_events as $event): ?>
                                                <tr>
                                                    <td><?php echo date('H:i', strtotime($event['attempt_time'])); ?></td>
                                                    <td><?php echo htmlspecialchars($event['username']); ?></td>
                                                    <td><code><?php echo htmlspecialchars($event['ip_address']); ?></code></td>
                                                    <td>
                                                        <span class="security-badge badge-danger">
                                                            Failed Login
                                                        </span>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Users Tab -->
                <div class="tab-content" id="users-tab">
                    <h2>User Management</h2>
                    <p class="muted">Manage system users and permissions</p>

                    <!-- User Filter -->
                    <div style="display: flex; gap: 1rem; margin: 1rem 0; flex-wrap: wrap;">
                        <select class="form-select" style="flex: 1; min-width: 200px;" onchange="filterUsers(this.value)">
                            <option value="">All Roles</option>
                            <option value="student">Students</option>
                            <option value="faculty">Faculty</option>
                            <option value="admin">Admins</option>
                        </select>
                        <input type="text" class="form-input" placeholder="Search users..." style="flex: 2;" 
                               onkeyup="searchUsers(this.value)">
                    </div>

                    <!-- Users Table -->
                    <div class="card">
                        <div style="padding: 1rem;">
                            <div class="table">
                                <table style="width: 100%;">
                                    <thead>
                                        <tr>
                                            <th>Name</th>
                                            <th>Email</th>
                                            <th>Role</th>
                                            <th>Joined</th>
                                            <th>Failed Logins</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody id="usersTableBody">
                                        <?php foreach ($users as $user): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($user['name']); ?></td>
                                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                                <td>
                                                    <form method="POST" class="role-form" style="display: inline;">
                                                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                        <input type="hidden" name="action" value="change_role">
                                                        <select name="new_role" onchange="this.form.submit()" 
                                                                class="role-select" data-user-id="<?php echo $user['id']; ?>">
                                                            <option value="student" <?php echo $user['role'] === 'student' ? 'selected' : ''; ?>>Student</option>
                                                            <option value="faculty" <?php echo $user['role'] === 'faculty' ? 'selected' : ''; ?>>Faculty</option>
                                                            <option value="admin" <?php echo $user['role'] === 'admin' ? 'selected' : ''; ?>>Admin</option>
                                                        </select>
                                                    </form>
                                                </td>
                                                <td><?php echo date('M j, Y', strtotime($user['created_at'])); ?></td>
                                                <td>
                                                    <?php if ($user['failed_logins'] > 3): ?>
                                                        <span class="security-badge badge-danger">
                                                            <?php echo $user['failed_logins']; ?> attempts
                                                        </span>
                                                    <?php elseif ($user['failed_logins'] > 0): ?>
                                                        <span class="security-badge badge-warning">
                                                            <?php echo $user['failed_logins']; ?> attempts
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="security-badge badge-success">None</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <div style="display: flex; gap: 0.5rem;">
                                                        <form method="POST" style="display: inline;">
                                                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                                            <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                            <input type="hidden" name="action" value="reset_password">
                                                            <button type="submit" class="btn outline small" 
                                                                    onclick="return confirm('Reset password for <?php echo htmlspecialchars($user['name']); ?>?')">
                                                                Reset Password
                                                            </button>
                                                        </form>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Pagination -->
                            <?php if ($total_pages > 1): ?>
                                <div style="text-align: center; margin-top: 1rem;">
                                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                        <a href="admin_dashboard.php?page=<?php echo $i; ?>" 
                                           class="btn <?php echo $i == $page ? 'primary' : 'outline'; ?> small">
                                            <?php echo $i; ?>
                                        </a>
                                    <?php endfor; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Courses Tab -->
                <div class="tab-content" id="courses-tab">
                    <h2>Course Management</h2>
                    <p class="muted">Manage all courses in the system</p>

                    <div class="card">
                        <div style="padding: 1rem;">
                            <div class="table">
                                <table style="width: 100%;">
                                    <thead>
                                        <tr>
                                            <th>Code</th>
                                            <th>Name</th>
                                            <th>Faculty</th>
                                            <th>Students</th>
                                            <th>Created</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($courses as $course): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($course['course_code']); ?></td>
                                                <td><?php echo htmlspecialchars($course['course_name']); ?></td>
                                                <td><?php echo htmlspecialchars($course['faculty_name'] ?? 'N/A'); ?></td>
                                                <td><?php echo $course['student_count']; ?></td>
                                                <td><?php echo date('M j, Y', strtotime($course['created_at'])); ?></td>
                                                <td>
                                                    <div style="display: flex; gap: 0.5rem;">
                                                        <a href="course_details.php?id=<?php echo $course['id']; ?>" 
                                                           class="btn outline small">View</a>
                                                        <form method="POST" style="display: inline;">
                                                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                                            <input type="hidden" name="course_id" value="<?php echo $course['id']; ?>">
                                                            <input type="hidden" name="action" value="delete_course">
                                                            <button type="submit" class="btn outline small" 
                                                                    onclick="return confirm('Delete course <?php echo htmlspecialchars($course['course_code']); ?>? This action cannot be undone.')">
                                                                Delete
                                                            </button>
                                                        </form>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Security Tab -->
                <div class="tab-content" id="security-tab">
                    <h2>Security Monitoring</h2>
                    <p class="muted">Monitor system security and access logs</p>

                    <div class="card">
                        <div style="padding: 1.5rem;">
                            <h3 style="margin-bottom: 1rem;">Security Settings</h3>
                            
                            <div style="display: grid; gap: 1.5rem;">
                                <div>
                                    <h4>Login Security</h4>
                                    <div style="display: flex; gap: 1rem; align-items: center; flex-wrap: wrap;">
                                        <label>
                                            <input type="checkbox" checked disabled>
                                            Enable brute force protection (5 attempts)
                                        </label>
                                        <label>
                                            <input type="checkbox" checked disabled>
                                            Require strong passwords
                                        </label>
                                        <label>
                                            <input type="checkbox">
                                            Enable 2-factor authentication
                                        </label>
                                    </div>
                                </div>
                                
                                <div>
                                    <h4>Session Security</h4>
                                    <div style="display: flex; gap: 1rem; align-items: center;">
                                        <label>
                                            Session timeout:
                                            <select class="form-select" style="width: 150px;">
                                                <option>15 minutes</option>
                                                <option selected>30 minutes</option>
                                                <option>1 hour</option>
                                                <option>4 hours</option>
                                            </select>
                                        </label>
                                        <label>
                                            <input type="checkbox" checked>
                                            Force logout on password change
                                        </label>
                                    </div>
                                </div>
                                
                                <div>
                                    <button class="btn primary" onclick="runSecurityScan()">
                                        🔍 Run Security Scan
                                    </button>
                                    <button class="btn outline" onclick="clearOldLogs()">
                                        🗑️ Clear Old Logs
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Export Modal -->
    <div class="modal-confirm" id="exportModal">
        <div class="modal-content">
            <div style="padding: 1.5rem;">
                <h3 style="margin-bottom: 1rem;">Export Data</h3>
                <p class="muted" style="margin-bottom: 1.5rem;">Select data to export</p>
                
                <div class="export-options">
                    <button class="btn outline" onclick="exportData('users', 'csv')">
                        📄 Users (CSV)
                    </button>
                    <button class="btn outline" onclick="exportData('courses', 'csv')">
                        📄 Courses (CSV)
                    </button>
                    <button class="btn outline" onclick="exportData('enrollments', 'csv')">
                        📄 Enrollments (CSV)
                    </button>
                    <button class="btn outline" onclick="exportData('attendance', 'pdf')">
                        📊 Attendance (PDF)
                    </button>
                </div>
                
                <div style="display: flex; gap: 1rem; margin-top: 1.5rem;">
                    <button class="btn primary" onclick="exportAll()">
                        Export All Data
                    </button>
                    <button class="btn outline" onclick="closeExportModal()">
                        Cancel
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="script.js"></script>
    <script>
    // Tab switching
    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            showTab(this.dataset.tab);
        });
    });
    
    document.querySelectorAll('[data-tab]').forEach(btn => {
        btn.addEventListener('click', function() {
            showTab(this.dataset.tab);
        });
    });
    
    function showTab(tabId) {
        // Update tab buttons
        document.querySelectorAll('.tab-btn').forEach(btn => {
            btn.classList.toggle('active', btn.dataset.tab === tabId);
        });
        
        // Update nav items
        document.querySelectorAll('.nav-item').forEach(item => {
            item.classList.toggle('active', item.dataset.tab === tabId);
        });
        
        // Show tab content
        document.querySelectorAll('.tab-content').forEach(content => {
            content.classList.toggle('active', content.id === tabId + '-tab');
        });
    }
    
    // Search functionality
    function searchUsers(query) {
        const rows = document.querySelectorAll('#usersTableBody tr');
        query = query.toLowerCase();
        
        rows.forEach(row => {
            const text = row.textContent.toLowerCase();
            row.style.display = text.includes(query) ? '' : 'none';
        });
    }
    
    function filterUsers(role) {
        const rows = document.querySelectorAll('#usersTableBody tr');
        
        rows.forEach(row => {
            if (!role) {
                row.style.display = '';
                return;
            }
            
            const roleSelect = row.querySelector('.role-select');
            const currentRole = roleSelect ? roleSelect.value : '';
            row.style.display = currentRole === role ? '' : 'none';
        });
    }
    
    // Export functions
    function showExportModal() {
        document.getElementById('exportModal').style.display = 'flex';
    }
    
    function closeExportModal() {
        document.getElementById('exportModal').style.display = 'none';
    }
    
    function exportData(type, format) {
        window.location.href = `export_data.php?type=${type}&format=${format}`;
    }
    
    function exportAll() {
        if (confirm('Export all system data? This may take a moment.')) {
            window.location.href = 'export_data.php?type=all&format=zip';
        }
    }
    
    // Security functions
    function runSecurityScan() {
        alert('Security scan started. This will check for vulnerabilities and generate a report.');
        // In a real system, this would call an API endpoint
    }
    
    function clearOldLogs() {
        if (confirm('Clear logs older than 30 days?')) {
            fetch('clear_logs.php', { method: 'POST' })
                .then(response => response.json())
                .then(data => {
                    alert(data.message);
                    location.reload();
                });
        }
    }
    
    function refreshPage() {
        location.reload();
    }
    
    function confirmLogout() {
        return confirm('Logout from admin dashboard?');
    }
    
    // Auto-refresh session warning
    let sessionTimer;
    function startSessionTimer() {
        // Warn 5 minutes before session expires
        sessionTimer = setTimeout(() => {
            if (confirm('Your session will expire in 5 minutes. Extend session?')) {
                fetch('extend_session.php')
                    .then(() => startSessionTimer());
            }
        }, 25 * 60 * 1000); // 25 minutes
    }
    
    // Start timers on load
    document.addEventListener('DOMContentLoaded', function() {
        startSessionTimer();
        
        // Auto-focus search
        const searchInput = document.getElementById('adminSearch');
        if (searchInput) {
            searchInput.addEventListener('keyup', function(e) {
                if (e.key === 'Enter') {
                    searchUsers(this.value);
                }
            });
        }
    });
    
    // Prevent navigation away without saving
    window.addEventListener('beforeunload', function(e) {
        const roleForms = document.querySelectorAll('.role-form');
        let hasChanges = false;
        
        roleForms.forEach(form => {
            const select = form.querySelector('select');
            const originalValue = select.dataset.originalValue;
            if (select.value !== originalValue) {
                hasChanges = true;
            }
        });
        
        if (hasChanges) {
            e.preventDefault();
            e.returnValue = 'You have unsaved changes. Are you sure you want to leave?';
            return e.returnValue;
        }
    });
    
    // Store original values
    document.querySelectorAll('.role-select').forEach(select => {
        select.dataset.originalValue = select.value;
    });
    </script>
</body>
</html>