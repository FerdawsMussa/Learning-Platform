<?php
require_once '../api/session_helper.php';
start_role_session();
require_once '../api/db.php';

// Protect route
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}

$view = $_GET['view'] ?? 'overview';

// 1. Overview Stats - Deduplicated by Email
$total_students = $pdo->query("SELECT COUNT(DISTINCT TRIM(LOWER(email))) FROM users WHERE role = 'student'")->fetchColumn();
$total_instructors = $pdo->query("SELECT COUNT(DISTINCT TRIM(LOWER(email))) FROM users WHERE role = 'instructor'")->fetchColumn();
$total_content_courses = $pdo->query("SELECT COUNT(*) FROM (SELECT id, user_id AS creator_id, title, description, file_path AS thumbnail_path, category, generic_value AS level, meta_text AS tags, is_resource, is_deleted, deleted_at, created_at FROM content WHERE record_type = 'course') content_courses WHERE is_deleted = 0")->fetchColumn();
$pending_count = $pdo->query("SELECT COUNT(DISTINCT TRIM(LOWER(email))) FROM users WHERE role = 'instructor' AND is_approved = 0")->fetchColumn();

// Fetch categories from DB
try {
    $categories_list = $pdo->query("SELECT * FROM categories ORDER BY name ASC")->fetchAll();
} catch (PDOException $e) {
    // If table doesn't exist, use default list
    $categories_list = [];
}

// Fetch admin settings & notifications
try {
    $settings_stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND role = 'admin'");
    $settings_stmt->execute([$_SESSION['user_id']]);
    $admin_data = $settings_stmt->fetch(PDO::FETCH_ASSOC);

    // Initialize admin prefs with all necessary fields
    $admin_prefs = [
        'full_name' => $admin_data['full_name'] ?? $_SESSION['username'] ?? 'Admin',
        'last_name' => $admin_data['last_name'] ?? '',
        'email' => $admin_data['email'] ?? '',
        'profile_pic' => $admin_data['profile_pic'] ?? '',
        'theme_mode' => $admin_data['theme_mode'] ?? 'dark',
        'notifications_enabled' => $admin_data['notifications_enabled'] ?? 1
    ];
} catch (PDOException $e) {
    $admin_prefs = [
        'full_name' => $_SESSION['username'] ?? 'Admin',
        'last_name' => '',
        'email' => '',
        'bio' => 'System Administrator',
        'profile_pic' => '',
        'theme_mode' => 'dark',
        'notifications_enabled' => 1
    ];
}

// --- NEW: POST HANDLER FOR ADMIN PROFILE (Matches Student/Instructor Logic) ---
$profile_error = '';
$profile_success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_admin_profile'])) {
    $full_name_input = trim($_POST['full_name'] ?? '');
    $last_name_input = trim($_POST['last_name'] ?? '');
    $bio_input = trim($_POST['bio'] ?? '');
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    $params = [];
    $update_parts = [];

    // 1. Basic Info
    // (full_name is now readonly in UI and skipped in update)
    $update_parts[] = "last_name = ?";
    $params[] = $last_name_input;

    // 2. Password Update with Verification
    if (!empty($new_password)) {
        // Fetch current hashed password
        $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user_data = $stmt->fetch();

        if (!$user_data || !password_verify($current_password, $user_data['password'])) {
            $profile_error = "Current password is incorrect!";
        } elseif ($new_password !== $confirm_password) {
            $profile_error = "New passwords do not match!";
        } else {
            $update_parts[] = "password = ?";
            $params[] = password_hash($new_password, PASSWORD_DEFAULT);
        }
    }

    // 3. Profile Picture Upload
    if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] === UPLOAD_ERR_OK) {
        $file_tmp = $_FILES['profile_pic']['tmp_name'];
        $file_name = time() . '_' . basename($_FILES['profile_pic']['name']);
        $upload_dir = '../assets/uploads/profile_pics/';
        if (!is_dir($upload_dir))
            mkdir($upload_dir, 0777, true);

        $dest_path = $upload_dir . $file_name;
        if (move_uploaded_file($file_tmp, $dest_path)) {
            $update_parts[] = "profile_pic = ?";
            $params[] = 'assets/uploads/profile_pics/' . $file_name; // Store relative to root
        }
    }

    if (empty($profile_error) && !empty($update_parts)) {
        $query = "UPDATE users SET " . implode(", ", $update_parts) . " WHERE id = ?";
        $params[] = $_SESSION['user_id'];
        try {
            $stmt = $pdo->prepare($query);
            $stmt->execute($params);
            $profile_success = "Profile updated successfully!";
            // Refresh local prefs
            header("Location: admin.php?view=settings&success=1");
            exit;
        } catch (PDOException $e) {
            $profile_error = "Update failed: " . $e->getMessage();
        }
    }
}

// Category Management POST Handler
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['category_action'])) {
    $action = $_POST['category_action'];
    if ($action === 'add') {
        $name = trim($_POST['category_name'] ?? '');
        if (!empty($name)) {
            try {
                $stmt = $pdo->prepare("INSERT IGNORE INTO categories (name) VALUES (?)");
                $stmt->execute([$name]);
                header("Location: admin.php?view=settings&success=added");
                exit;
            } catch (PDOException $e) {
                $profile_error = $e->getMessage();
            }
        }
    } elseif ($action === 'delete') {
        $id = $_POST['category_id'] ?? null;
        if ($id) {
            try {
                $stmt = $pdo->prepare("DELETE FROM categories WHERE id = ?");
                $stmt->execute([$id]);
                header("Location: admin.php?view=settings&success=deleted");
                exit;
            } catch (PDOException $e) {
                $profile_error = $e->getMessage();
            }
        }
    }
}

if (isset($_GET['success']))
    $profile_success = "Profile updated successfully!";

$unread_notifs = 0;
$recent_notifs = [];
try {
    $unread_notifs = $pdo->query("SELECT COUNT(*) FROM notifications WHERE target_role = 'admin' AND is_read = 0")->fetchColumn();
    // Fetch recent notifications with joined user role info
    $recent_notifs = $pdo->query("
        SELECT n.*, u.role as actual_role, u.id as actual_user_id
        FROM notifications n
        LEFT JOIN users u ON (n.user_id = u.id OR JSON_EXTRACT(n.meta, '$.instructor_id') = u.id OR JSON_EXTRACT(n.meta, '$.student_id') = u.id)
        WHERE n.target_role = 'admin' 
        ORDER BY n.created_at DESC LIMIT 5
    ")->fetchAll();
} catch (PDOException $e) {
    // Table might not exist yet
}

// 2. Data Fetching based on view
$display_data = [];

if ($view === 'overview') {
    $stmt = $pdo->query("
        SELECT u.* FROM users u
        INNER JOIN (SELECT MIN(id) as min_id FROM users WHERE role = 'instructor' AND is_approved = 0 GROUP BY TRIM(LOWER(full_name)), TRIM(LOWER(email))) dedup
        ON u.id = dedup.min_id
        ORDER BY u.created_at DESC
    ");
    $pending_creators = $stmt->fetchAll();

    // Fetch pending category requests
    try {
        $stmt_cat = $pdo->query("
            SELECT n.*, u.full_name as instructor_name, u.email as instructor_email 
            FROM notifications n 
            JOIN users u ON n.user_id = u.id 
            WHERE n.type = 'category_request' AND n.target_role = 'admin'
            ORDER BY n.created_at DESC
        ");
        $pending_category_requests = $stmt_cat->fetchAll();
    } catch (PDOException $e) {
        $pending_category_requests = [];
    }

    // Fetch pending course approvals
    try {
        $stmt_courses = $pdo->query("
            SELECT n.*, u.full_name as instructor_name, u.email as instructor_email,
                   c.title as course_title, c.category as course_category, c.id as course_id, c.is_resource
            FROM notifications n
            JOIN users u ON n.user_id = u.id
            JOIN content c ON JSON_UNQUOTE(JSON_EXTRACT(n.meta, '$.course_id')) = c.id
            WHERE n.type = 'course_approval' AND n.target_role = 'admin'
            ORDER BY n.created_at DESC
        ");
        $pending_course_approvals = $stmt_courses->fetchAll();
    } catch (PDOException $e) {
        $pending_course_approvals = [];
    }
} elseif ($view === 'system_overview') {
    // Aggregated User List — deduplicated by email via subquery
    $users_stmt = $pdo->query("
        SELECT u.id, u.role, u.full_name, u.email, u.created_at FROM users u
        INNER JOIN (SELECT MIN(id) as min_id FROM users WHERE role != 'admin' GROUP BY TRIM(LOWER(full_name)), TRIM(LOWER(email))) dedup
        ON u.id = dedup.min_id
        ORDER BY u.created_at DESC LIMIT 20
    ");
    $system_users = $users_stmt->fetchAll();

    // Activity Logs
    $logs = [];
    try {
        $log_stmt = $pdo->query("SELECT * FROM system_logs ORDER BY created_at DESC LIMIT 15");
        $logs = $log_stmt->fetchAll();
    } catch (PDOException $e) { /* ignore if table missing */
    }

} elseif ($view === 'students') {
    $stmt = $pdo->query("
        SELECT u.*,
        (SELECT COUNT(*) FROM (SELECT user_id AS student_id, item_id AS lesson_id, metric_value AS status, last_activity_at AS last_sync FROM progress WHERE record_type = 'lesson_progress') lp WHERE lp.student_id = u.id AND lp.status = 'completed') as completed_progress_lessons
        FROM users u
        INNER JOIN (SELECT MIN(id) as min_id FROM users WHERE role = 'student' GROUP BY TRIM(LOWER(full_name)), TRIM(LOWER(email))) dedup
        ON u.id = dedup.min_id
        ORDER BY u.created_at DESC
    ");
    $students = $stmt->fetchAll();

    // Process student counts in PHP or with JSON helpers
    foreach ($students as &$s) {
        $c_arr = json_decode($s['courses'] ?? '[]', true);
        $s['enroll_count'] = count($c_arr);
    }

    // Calculate Metrics
    $active_24h = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'student' AND last_login >= NOW() - INTERVAL 1 DAY")->fetchColumn();
    $inactive_7d = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'student' AND (last_login < NOW() - INTERVAL 7 DAY OR last_login IS NULL)")->fetchColumn();

    // Average progress: (sum of completed units / (total students * hypothetical 10 units avg))
    $avg_comp = $pdo->query("SELECT AVG(c) FROM (SELECT COUNT(*) as c FROM (SELECT user_id AS student_id, item_id AS lesson_id, metric_value AS status, last_activity_at AS last_sync FROM progress WHERE record_type = 'lesson_progress') progress_lesson_progress group by student_id) as t")->fetchColumn() ?: 0;
    $avg_progress = round((float) $avg_comp, 1);
} elseif ($view === 'instructors') {
    $stmt = $pdo->query("
        SELECT u.* FROM users u
        INNER JOIN (SELECT MIN(id) as min_id FROM users WHERE role = 'instructor' GROUP BY TRIM(LOWER(full_name)), TRIM(LOWER(email))) dedup
        ON u.id = dedup.min_id
        ORDER BY u.created_at DESC
    ");
    $instructors = $stmt->fetchAll();

    foreach ($instructors as &$i) {
        $c_arr = json_decode($i['courses'] ?? '[]', true);
        $i['course_count'] = count($c_arr);
        $meta = json_decode($i['meta'] ?? '{}', true);

        $cats = [];
        if (!empty($meta['category'])) $cats[] = $meta['category'];
        if (!empty($meta['approved_categories'])) {
            $cats = array_unique(array_merge($cats, $meta['approved_categories']));
        }
        $i['category'] = !empty($cats) ? implode(', ', $cats) : 'Unassigned';
    }
} elseif ($view === 'content_courses') {
    $stmt = $pdo->query("
        SELECT c.*, u.full_name as instructor_name
        FROM (SELECT id, user_id AS creator_id, title, description, file_path AS thumbnail_path, category, generic_value AS level, meta_text AS tags, is_resource, is_deleted, deleted_at, created_at FROM content WHERE record_type = 'course') c
        JOIN users u ON c.creator_id = u.id
        WHERE c.is_deleted = 0
        ORDER BY c.created_at DESC
    ");
    $content_courses = $stmt->fetchAll();

    foreach ($content_courses as &$c) {
        // Count students who have this course ID in their JSON array
        $st = $pdo->prepare("SELECT COUNT(*) FROM users WHERE role = 'student' AND JSON_CONTAINS(courses, CAST(? AS CHAR))");
        $st->execute([$c['id']]);
        $c['student_count'] = $st->fetchColumn();
    }
} elseif ($view === 'content_resources') {
    $stmt = $pdo->query("
        SELECT c.*, u.full_name as instructor_name
        FROM (SELECT id, user_id AS creator_id, title, file_path, meta_text AS file_type, category, is_deleted, deleted_at, created_at FROM content WHERE record_type = 'resource') c
        JOIN users u ON c.creator_id = u.id
        WHERE c.is_deleted = 0
        ORDER BY c.created_at DESC
    ");
    $content_resources = $stmt->fetchAll();
} elseif ($view === 'progress_streaks') {
    $progress_streaks = $pdo->query("SELECT s.*, u.full_name, u.role FROM (SELECT user_id, metric_value AS streak_count, last_activity_at AS last_login_date FROM progress WHERE record_type = 'streak') s JOIN users u ON s.user_id = u.id ORDER BY s.streak_count DESC")->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>JU Learn - Admin Dashboard</title>

    <!-- Bootstrap 5 for Layout & Modals -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <style>
        :root {
            --accent-purple: #00e599;
        }

        /* Re-mapped to global green for admin branding */
        .stat-card-admin {
            background: rgba(0, 229, 153, 0.05);
            border: 1px solid rgba(0, 229, 153, 0.2);
        }

        /* Override dashboard.css for native scrolling like instructor dashboard */
        body.dashboard-body {
            display: block !important;
            height: auto !important;
            overflow: visible !important;
        }

        .sidebar {
            width: 250px;
            position: fixed;
            top: 0;
            bottom: 0;
            left: 0;
            z-index: 1000;
            overflow-y: auto;
            overflow-x: hidden;
            background: rgba(10, 14, 18, 0.95);
            border-right: 1px solid var(--border-light);
        }

        .sidebar::-webkit-scrollbar { width: 5px; }
        .sidebar::-webkit-scrollbar-track { background: transparent; }
        .sidebar::-webkit-scrollbar-thumb { background: rgba(255, 255, 255, 0.05); border-radius: 10px; }
        .sidebar::-webkit-scrollbar-thumb:hover { background: var(--accent-green); }

        .main-content {
            margin-left: 250px;
            min-height: 100vh;
            overflow-y: visible !important;
        }

        @media (max-width: 768px) {
            .main-content {
                margin-left: 0 !important;
            }
        }

        .sidebar-nav-item {
            color: #94a3b8 !important;
            padding: 12px 20px !important;
            border-radius: 12px !important;
            margin: 4px 15px;
            display: flex;
            align-items: center;
            gap: 12px;
            transition: 0.2s;
            text-decoration: none !important;
            font-size: 0.9rem;
        }

        .sidebar-nav-item:hover {
            background: rgba(255, 255, 255, 0.05);
            color: white !important;
        }

        .sidebar-nav-item.active {
            background: rgba(0, 229, 153, 0.1) !important;
            color: var(--accent-green) !important;
            font-weight: 600;
        }

        .nav-link {
            transition: 0.3s;
        }

        .hover-glass:hover {
            background: rgba(255, 255, 255, 0.05);
            color: white !important;
        }

        .text-purple {
            color: var(--accent-green);
        }

        .btn-delete {
            color: #ff6384;
            border-color: rgba(255, 99, 132, 0.3);
            background: rgba(255, 99, 132, 0.05);
        }

        .btn-delete:hover {
            background: #ff6384;
            color: white;
        }

        .clickable-name {
            color: var(--accent-green);
            cursor: pointer;
            text-decoration: underline;
            text-underline-offset: 4px;
        }

        .clickable-name:hover {
            color: #50ffc8;
        }

        /* Sync Bootstrap with Glassmorphism */
        .modal-content {
            background: var(--bg-dark);
            border: 1px solid var(--border-light);
            backdrop-filter: blur(15px);
        }

        .modal-header,
        .modal-footer {
            border-color: var(--border-light);
        }

        .profile-label {
            color: var(--text-secondary);
            font-size: 0.8rem;
            text-transform: uppercase;
            margin-bottom: 2px;
        }

        .profile-value {
            color: var(--text-primary);
            margin-bottom: 15px;
            font-weight: 500;
        }

        .social-link {
            padding: 8px 15px;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 8px;
            color: var(--text-primary);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 0.9rem;
            border: 1px solid var(--border-light);
        }

        .social-link:hover {
            background: rgba(255, 255, 255, 0.1);
            border-color: var(--accent-green);
            color: white;
        }

        /* Notifications & Settings */
        .notif-badge {
            position: absolute;
            top: -2px;
            right: -2px;
            background: #ff6384;
            color: white;
            font-size: 0.65rem;
            padding: 2px 5px;
            border-radius: 10px;
            border: 2px solid var(--bg-dark);
            min-width: 18px;
            text-align: center;
        }

        .notification-item.unread {
            border-left: 3px solid var(--accent-green) !important;
        }

        .notification-item p {
            color: var(--text-primary) !important;
        }

        .notification-item:hover {
            background: rgba(255, 255, 255, 0.05) !important;
        }

        .settings-card {
            padding: 2rem;
            margin-bottom: 24px;
        }

        .text-decoration-none {
            text-decoration: none !important;
            color: inherit !important;
        }

        .stat-card-admin:hover {
            border-color: var(--accent-green);
            transform: translateY(-5px);
            transition: all 0.3s ease;
        }

        .theme-opt {
            cursor: pointer;
            padding: 10px;
            border-radius: 12px;
            border: 2px solid transparent;
            transition: 0.3s;
        }

        .theme-opt:hover {
            background: rgba(255, 255, 255, 0.05);
        }

        .theme-opt.active {
            border-color: var(--accent-green);
            background: rgba(0, 229, 153, 0.05);
        }

        .theme-preview {
            height: 80px;
            border-radius: 8px;
            margin-bottom: 10px;
        }

        .toast-container {
            position: fixed;
            bottom: 24px;
            right: 24px;
            z-index: 1060;
        }

        .light-mode {
            --bg-dark: #f8fafc;
            --text-primary: #1e293b;
            --text-secondary: #64748b;
            --border-light: rgba(0, 0, 0, 0.1);
            --card-glass: rgba(255, 255, 255, 0.8);
        }

        .light-mode .glass-panel {
            background: white;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        }

        .light-mode .sidebar {
            background: white;
            border-right: 1px solid rgba(0, 0, 0, 0.1);
        }

        .light-mode .topbar {
            background: rgba(255, 255, 255, 0.8);
            border-bottom: 1px solid rgba(0, 0, 0, 0.1);
        }
    </style>
</head>

<body class="dashboard-body <?= $admin_prefs['theme_mode'] === 'light' ? 'light-mode' : '' ?>">

    <div class="bg-gradient-spot glow-green"></div>
    <div class="bg-gradient-spot glow-blue"></div>

    <!-- Mobile Dashboard Topbar (hidden on desktop) -->
    <div class="dash-mobile-topbar" id="dashMobileTopbar">
        <a href="../index.php" class="dash-mobile-brand">
            <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none"
                stroke="var(--accent-green)" stroke-width="2">
                <path d="M22 10v6M2 10l10-5 10 5-10 5z"></path>
                <path d="M6 12v5c3 3 9 3 12 0v-5"></path>
            </svg>
            JU<span class="text-accent">Admin</span>
        </a>
        <button class="dash-hamburger" id="dashHamburger" aria-label="Open sidebar" aria-expanded="false">
            <span></span><span></span><span></span>
        </button>
    </div>

    <!-- Sidebar overlay (mobile) -->
    <div class="dash-sidebar-overlay" id="dashSidebarOverlay"></div>

    <!-- Sidebar -->
    <aside class="sidebar" id="dashSidebar">
        <div class="p-4 border-bottom border-secondary" style="border-bottom-color: var(--border-light) !important;">
            <div class="d-flex align-items-center gap-2">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
                    stroke="var(--accent-green)" stroke-width="2">
                    <path d="M22 10v6M2 10l10-5 10 5-10 5z"></path>
                    <path d="M6 12v5c3 3 9 3 12 0v-5"></path>
                </svg>
                <strong style="font-size: 1.2rem; letter-spacing: -0.5px;">JU<span
                        class="text-accent">Admin</span></strong>
            </div>
            <div class="mt-2 small text-uppercase" style="text-color: white"> Management Portal</div>
        </div>

        <ul class="nav flex-column px-3 gap-1 mt-4">
            <li class="nav-item">
                <a href="admin.php?view=overview" class="sidebar-nav-item <?= $view === 'overview' ? 'active' : '' ?>">
                    <svg width="18" height="18" class="me-2" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                        stroke-width="2">
                        <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
                        <line x1="3" y1="9" x2="21" y2="9"></line>
                        <line x1="9" y1="21" x2="9" y2="9"></line>
                    </svg>
                    Overview
                </a>
            </li>
        </ul>

        <!-- CATEGORY: USERS -->
        <div class="px-3 mt-4 mb-2 small text-uppercase fw-bold " style="letter-spacing: 1px; color:white">Users</div>
        <ul class="nav flex-column px-3 gap-1">
            <li class="nav-item">
                <a href="admin.php?view=students" class="sidebar-nav-item <?= $view === 'students' ? 'active' : '' ?>">
                    <svg width="18" height="18" class="me-2" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                        stroke-width="2">
                        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                        <circle cx="9" cy="7" r="4"></circle>
                    </svg>
                    Students
                </a>
            </li>
            <li class="nav-item">
                <a href="admin.php?view=instructors"
                    class="sidebar-nav-item <?= $view === 'instructors' ? 'active' : '' ?>">
                    <svg width="18" height="18" class="me-2" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                        stroke-width="2">
                        <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                        <circle cx="8.5" cy="7" r="4"></circle>
                    </svg>
                    Instructors
                </a>
            </li>
        </ul>

        <!-- CATEGORY: CONTENT -->
        <div class="px-3 mt-4 mb-2 small text-uppercase fw-bold " style="letter-spacing: 1px; color:white">Content</div>
        <ul class="nav flex-column px-3 gap-1">
            <li class="nav-item">
                <a href="admin.php?view=content_courses"
                    class="sidebar-nav-item <?= $view === 'content_courses' ? 'active' : '' ?>">
                    <svg width="18" height="18" class="me-2" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                        stroke-width="2">
                        <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"></path>
                        <path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"></path>
                    </svg>
                    Courses
                </a>
            </li>
            <li class="nav-item">
                <a href="admin.php?view=content_resources"
                    class="sidebar-nav-item <?= $view === 'content_resources' ? 'active' : '' ?>">
                    Resources
                </a>
            </li>
        </ul>

        <div class="px-3 mt-4 mb-2 small text-uppercase fw-bold " style="letter-spacing: 1px; color:white">System</div>
        <ul class="nav flex-column px-3 gap-1">
            <li class="nav-item">
                <a href="admin.php?view=system_overview"
                    class="sidebar-nav-item <?= $view === 'system_overview' ? 'active' : '' ?>">
                    <svg width="18" height="18" class="me-2" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                        stroke-width="2">
                        <path d="M4 22h14a2 2 0 0 0 2-2V7.5L14.5 2H6a2 2 0 0 0-2 2v4"></path>
                        <polyline points="14 2 14 8 20 8"></polyline>
                        <path d="M2 15h10"></path>
                        <path d="M9 18l3-3-3-3"></path>
                    </svg>
                    System Overview
                </a>
            </li>
            <li class="nav-item">
                <a href="admin.php?view=settings" class="sidebar-nav-item <?= $view === 'settings' ? 'active' : '' ?>">
                    <svg width="18" height="18" class="me-2" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                        stroke-width="2">
                        <circle cx="12" cy="12" r="3"></circle>
                        <path
                            d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z">
                        </path>
                    </svg>
                    Settings
                </a>
            </li>
        </ul>
        <div class="mt-auto p-4 border-top border-secondary" style="border-top-color: var(--border-light) !important;">
            <a href="../api/logout.php?role=admin"
                class="btn btn-outline-danger w-100 btn-sm rounded-pill font-monospace"
                style="border-color: rgba(220,53,69,0.5);">Logout</a>
        </div>
    </aside>

    <!-- Main Content -->
    <main class="main-content">
        <header class="topbar">
            <div class="flex-between gap-3">
                <div class="d-flex align-items-center gap-3">
                    <!-- Notification Bell -->
                    <div class="dropdown">
                        <button class="btn-glass position-relative border-0" type="button" data-bs-toggle="dropdown"
                            aria-expanded="false" style="padding: 8px;">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                stroke-width="2">
                                <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path>
                                <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
                            </svg>
                            <?php if ($unread_notifs > 0): ?>
                                <span class="notif-badge"><?= $unread_notifs ?></span>
                            <?php endif; ?>
                        </button>
                        <div class="dropdown-menu dropdown-menu-end glass-panel border-light p-0 animate-up"
                            style="width: 300px; background: var(--bg-dark);">
                            <div
                                class="p-3 border-bottom border-light d-flex justify-content-between align-items-center">
                                <h6 class="mb-0 small fw-bold text-uppercase">Notifications</h6>
                                <button class="btn btn-link btn-sm text-accent text-decoration-none p-0"
                                    onclick="clearAllNotifications()" style="font-size: 0.75rem;">Clear All</button>
                            </div>
                            <div class="notif-scroll" style="max-height: 350px; overflow-y: auto;">
                                <?php if (empty($recent_notifs)): ?>
                                    <div class="p-4 text-center text-muted small">No new notifications</div>
                                <?php else: ?>
                                    <?php foreach ($recent_notifs as $n): 
                                        $target_id = $n['actual_user_id'] ?? null;
                                        $target_role = ($n['actual_role'] === 'instructor') ? 'creator' : 'student';
                                        $n_type = $n['type'] ?? '';
                                        $req_cat = ($n_type === 'category_request') ? (json_decode($n['meta'] ?? '{}', true)['category'] ?? '') : '';
                                    ?>
                                        <div class="p-3 border-bottom border-light notification-item <?= $n['is_read'] ? '' : 'unread' ?>"
                                            onclick="showProfile(<?= $target_id ?? 'null' ?>, '<?= $target_role ?>', true, <?= $n['id'] ?>, '<?= $n_type ?>', '<?= addslashes($req_cat) ?>')"
                                            style="cursor: pointer; background: <?= $n['is_read'] ? 'transparent' : 'rgba(255,255,255,0.02)' ?>;">
                                            <div class="d-flex gap-3">
                                                <div class="mt-1">
                                                    <svg width="14" height="14" class="text-accent" viewBox="0 0 24 24"
                                                        fill="none" stroke="currentColor" stroke-width="2">
                                                        <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                                                        <circle cx="8.5" cy="7" r="4"></circle>
                                                        <polyline points="17 11 19 13 23 9"></polyline>
                                                    </svg>
                                                </div>
                                                <div style="flex:1;">
                                                    <div class="d-flex justify-content-between align-items-start">
                                                        <p class="small mb-1" style="flex:1;">
                                                            <?= htmlspecialchars($n['message']) ?>
                                                        </p>
                                                        <button class="btn btn-link btn-sm p-0 ms-2 text-muted hover-white"
                                                            onclick="dismissNotification(<?= $n['id'] ?>, event)"
                                                            title="Dismiss">
                                                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none"
                                                                stroke="currentColor" stroke-width="2">
                                                                <line x1="18" y1="6" x2="6" y2="18"></line>
                                                                <line x1="6" y1="6" x2="18" y2="18"></line>
                                                            </svg>
                                                        </button>
                                                    </div>
                                                    <p class="text-muted mb-0" style="font-size: 0.7rem;">
                                                        <?= date('M d, g:i A', strtotime($n['created_at'])) ?>
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="wallet-badge glass-panel"
                        style="color: var(--accent-green); border-color: rgba(0, 229, 153, 0.3);">
                        <span class="network-dot"
                            style="background: var(--accent-green); box-shadow: 0 0 8px var(--accent-green);"></span>
                        <?= htmlspecialchars($_SESSION['username'] ?? 'Admin') ?>
                    </div>
                </div>
            </div>
        </header>

        <div class="dashboard-container">
            <!-- VIEW: SYSTEM OVERVIEW (New Unified View) -->
            <?php if ($view === 'system_overview'): ?>
                <h2 class="page-title">Management Control Center</h2>

                <!-- Unified User List (Full Width) -->
                <div class="glass-panel data-table-wrapper mb-4 animate-up">
                    <div class="d-flex justify-content-between align-items-center p-3 border-bottom"
                        style="border-color: var(--border-light) !important;">
                        <h3 style="font-size: 1.1rem; font-weight: 600; margin: 0;">Platform Accounts</h3>
                        <div class="badge-glass" style="background: rgba(0, 229, 153, 0.1); color: var(--accent-green);">
                            Recent Users
                        </div>
                    </div>

                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Identifier</th>
                                <th>Role</th>
                                <th>Joined Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($system_users as $u): ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center gap-3">
                                            <div class="rounded-circle d-flex justify-content-center align-items-center"
                                                style="width:32px;height:32px;background:rgba(255,255,255,0.05);border:1px solid var(--border-light);font-weight:600;font-size:0.8rem;color:var(--accent-green);">
                                                <?= strtoupper(substr($u['full_name'], 0, 1)) ?>
                                            </div>
                                            <div>
                                                <div class="clickable-name"
                                                    onclick="showProfile(<?= $u['id'] ?>, '<?= strtolower($u['role']) === 'instructor' ? 'creator' : 'student' ?>')">
                                                    <?= htmlspecialchars($u['full_name']) ?>
                                                </div>
                                                <div style="font-size: 0.75rem; color: var(--text-secondary);">
                                                    <?= htmlspecialchars($u['email']) ?>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if (strtolower($u['role']) === 'student'): ?>
                                            <span class="badge-glass"
                                                style="background:rgba(0,180,216,0.1); color:#00b4d8; font-size:0.75rem;">Student</span>
                                        <?php elseif (strtolower($u['role']) === 'admin'): ?>
                                            <span class="badge-glass"
                                                style="background:rgba(0,229,153,0.1); color:var(--accent-green); font-size:0.75rem;">Admin</span>
                                        <?php else: ?>
                                            <span class="badge-glass"
                                                style="background:rgba(155,81,224,0.1); color:#9b51e0; font-size:0.75rem;">Instructor</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="color: var(--text-secondary); font-size: 0.85rem;">
                                        <?= date('M d, Y', strtotime($u['created_at'])) ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Activity Log (Now Below) -->
                <div class="animate-up" style="animation-delay: 0.1s;">
                    <div class="glass-panel p-4">
                        <h3 class="h5 border-bottom pb-2 mb-4" style="border-color: var(--border-light) !important;">
                            Live Activity Feed</h3>

                        <div class="d-flex flex-column gap-3">
                            <?php if (empty($logs)): ?>
                                <div class="text-center text-muted small py-4">
                                    <div class="mb-2">⚠️</div>
                                    No activity recorded yet for this platform.
                                </div>
                            <?php else:
                                foreach ($logs as $log):
                                    $can_restore = false;
                                    $restore_type = '';
                                    if (($log['event_type'] === 'delete_course' || $log['event_type'] === 'delete_resource') && $log['target_id']) {
                                        $time_diff = time() - strtotime($log['created_at']);
                                        if ($time_diff <= 3600) { // 1 hour
                                            $can_restore = true;
                                            $restore_type = ($log['event_type'] === 'delete_course') ? 'course' : 'resource';
                                        }
                                    }
                                    ?>
                                    <div class="d-flex justify-content-between align-items-center border-bottom pb-3"
                                        style="border-color: var(--border-light) !important; border-style: dashed !important;">
                                        <div class="d-flex gap-3 align-items-start">
                                            <?php
                                            $icon_color = 'var(--accent-green)';
                                            if ($log['event_type'] == 'login')
                                                $icon_color = '#00e599';
                                            if ($log['event_type'] == 'sync')
                                                $icon_color = '#3e8bff';
                                            if ($log['event_type'] == 'upload')
                                                $icon_color = '#9b51e0';
                                            if (strpos($log['event_type'], 'delete') !== false)
                                                $icon_color = '#ff6384';
                                            ?>
                                            <div class="mt-1"
                                                style="color: <?= $icon_color ?>; background: rgba(255,255,255,0.05); padding: 6px; border-radius: 8px;">
                                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                                    stroke-width="2">
                                                    <circle cx="12" cy="12" r="10"></circle>
                                                    <polyline points="12 6 12 12 16 14"></polyline>
                                                </svg>
                                            </div>
                                            <div>
                                                <div style="font-size: 0.85rem; font-weight: 500;">
                                                    <?= htmlspecialchars($log['description']) ?>
                                                </div>
                                                <div class="text-muted" style="font-size: 0.75rem; margin-top: 2px;">
                                                    <?= htmlspecialchars(date('M d, g:i A', strtotime($log['created_at']))) ?>
                                                    • <span class="text-uppercase"
                                                        style="letter-spacing: 0.5px; opacity: 0.7;"><?= htmlspecialchars($log['user_role'] ?? 'auto') ?></span>
                                                </div>
                                            </div>
                                        </div>

                                        <?php if ($can_restore): ?>
                                            <button onclick="restoreContent(<?= $log['target_id'] ?>, '<?= $restore_type ?>')"
                                                class="btn-glass btn-sm"
                                                style="color: var(--accent-green); border-color: var(--accent-green); font-size: 0.75rem; padding: 4px 12px;">
                                                Restore
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; endif; ?>
                        </div>
                    </div>
                </div>


                <!-- VIEW: APPROVAL HUB -->
            <?php elseif ($view === 'overview'): ?>
                <h2 class="page-title">System Overview</h2>

                <div class="overview-grid" style="margin-bottom: 2rem;">
                    <a href="?view=students" class="text-decoration-none">
                        <div class="stat-card glass-panel animate-up stat-card-admin">
                            <div class="stat-card-title">Total Students</div>
                            <div class="stat-card-value"><?= $total_students ?></div>
                        </div>
                    </a>
                    <a href="?view=instructors" class="text-decoration-none">
                        <div class="stat-card glass-panel animate-up animate-delay-1 stat-card-admin">
                            <div class="stat-card-title">Instructors</div>
                            <div class="stat-card-value"><?= $total_instructors ?></div>
                        </div>
                    </a>
                    <a href="?view=content_courses" class="text-decoration-none">
                        <div class="stat-card glass-panel animate-up animate-delay-2 stat-card-admin">
                            <div class="stat-card-title">Total Courses</div>
                            <div class="stat-card-value"><?= $total_content_courses ?></div>
                        </div>
                    </a>
                    <a href="?view=overview" class="text-decoration-none">
                        <div class="stat-card glass-panel animate-up animate-delay-3"
                            style="border-color: var(--accent-green);">
                            <div class="stat-card-title">Pending Approvals</div>
                            <div class="stat-card-value" style="color: var(--accent-green);"><?= $pending_count ?></div>
                        </div>
                    </a>
                </div>

                <div class="glass-panel data-table-wrapper">
                    <div
                        style="padding: 1.5rem; border-bottom: 1px solid var(--border-light); display: flex; justify-content: space-between; align-items: center;">
                        <h3 style="font-size: 1.1rem; font-weight: 600;">Pending Applications</h3>
                    </div>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Applied Date</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pending_creators as $creator): ?>
                                <tr>
                                    <td><span class="clickable-name"
                                            onclick="showProfile(<?= $creator['id'] ?>, 'creator')"><?= htmlspecialchars($creator['full_name']) ?></span>
                                    </td>
                                    <td><?= htmlspecialchars($creator['email']) ?></td>
                                    <td style="color: var(--text-secondary);">
                                        <?= date('M d, Y', strtotime($creator['created_at'])) ?>
                                    </td>
                                    <td>
                                        <button onclick="approve(<?= $creator['id'] ?>)" class="btn-glass"
                                            style="color: var(--accent-green); border-color: var(--accent-green);">Approve</button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($pending_creators)): ?>
                                <tr>
                                    <td colspan="4" style="text-align: center; padding: 2rem; color: var(--text-secondary);">No
                                        pending approvals.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <div class="glass-panel data-table-wrapper mt-4">
                    <div
                        style="padding: 1.5rem; border-bottom: 1px solid var(--border-light); display: flex; justify-content: space-between; align-items: center;">
                        <h3 style="font-size: 1.1rem; font-weight: 600;">Expertise Expansion Requests</h3>
                    </div>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Instructor</th>
                                <th>Email</th>
                                <th>Requested Category</th>
                                <th>Date Requested</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pending_category_requests as $req): 
                                $meta_req = json_decode($req['meta'] ?? '{}', true);
                                $req_cat = $meta_req['category'] ?? '';
                            ?>
                                <tr>
                                    <td><span class="clickable-name"
                                            onclick="showProfile(<?= $req['user_id'] ?>, 'creator', true, <?= $req['id'] ?>, 'category_request', '<?= addslashes($req_cat) ?>')"><?= htmlspecialchars($req['instructor_name']) ?></span>
                                    </td>
                                    <td><?= htmlspecialchars($req['instructor_email']) ?></td>
                                    <td><span class="badge-glass bg-info bg-opacity-10 text-info"><?= htmlspecialchars($req_cat) ?></span></td>
                                    <td style="color: var(--text-secondary);">
                                        <?= date('M d, Y', strtotime($req['created_at'])) ?>
                                    </td>
                                    <td>
                                        <button onclick="approveCategory(<?= $req['id'] ?>, <?= $req['user_id'] ?>, '<?= addslashes($req_cat) ?>')" class="btn-glass"
                                            style="color: var(--accent-green); border-color: var(--accent-green);">Approve</button>
                                        <button onclick="declineCategory(<?= $req['id'] ?>, <?= $req['user_id'] ?>, '<?= addslashes($req_cat) ?>')" class="btn-glass"
                                            style="color: #ff6384; border-color: rgba(255, 99, 132, 0.3); margin-left: 5px;">Decline</button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($pending_category_requests)): ?>
                                <tr>
                                    <td colspan="5" style="text-align: center; padding: 2rem; color: var(--text-secondary);">No
                                        pending category expansion requests.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <div class="glass-panel data-table-wrapper mt-4">
                    <div style="padding: 1.5rem; border-bottom: 1px solid var(--border-light); display: flex; justify-content: space-between; align-items: center;">
                        <h3 style="font-size: 1.1rem; font-weight: 600;">📋 Content Approval Requests</h3>
                        <span class="badge bg-warning text-dark"><?= count($pending_course_approvals) ?> Pending</span>
                    </div>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Content Title</th>
                                <th>Instructor</th>
                                <th>Category</th>
                                <th>Submitted</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pending_course_approvals as $apr): 
                                $is_res = ($apr['is_resource'] == 1);
                            ?>
                                <tr>
                                    <td>
                                        <span class="badge bg-primary me-2" style="font-size: 0.6rem; vertical-align: middle;"><?= $is_res ? 'RESOURCE' : 'COURSE' ?></span>
                                        <span class="clickable-name" onclick="showProfile(<?= $apr['course_id'] ?>, '<?= $is_res ? 'resource' : 'course' ?>')"><?= htmlspecialchars($apr['course_title']) ?></span>
                                    </td>
                                    <td><span class="clickable-name" onclick="showProfile(<?= $apr['user_id'] ?>, 'creator')"><?= htmlspecialchars($apr['instructor_name']) ?></span></td>
                                    <td><span class="badge-glass"><?= htmlspecialchars($apr['course_category']) ?></span></td>
                                    <td style="color: var(--text-secondary);"><?= date('M d, Y', strtotime($apr['created_at'])) ?></td>
                                    <td>
                                        <button onclick="approveCourse(<?= $apr['id'] ?>, <?= $apr['course_id'] ?>, <?= $apr['user_id'] ?>)" class="btn-glass"
                                            style="color: var(--accent-green); border-color: var(--accent-green);">Approve</button>
                                        <button onclick="declineCourse(<?= $apr['id'] ?>, <?= $apr['course_id'] ?>, <?= $apr['user_id'] ?>)" class="btn-glass btn-delete" style="margin-left: 5px;">Decline</button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($pending_course_approvals)): ?>
                                <tr>
                                    <td colspan="5" style="text-align: center; padding: 2rem; color: var(--text-secondary);">No pending course submissions.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- VIEW: STUDENTS -->
            <?php elseif ($view === 'students'): ?>
                <h2 class="page-title">Student Engagement</h2>

                <div class="overview-grid mb-4">
                    <div class="stat-card glass-panel animate-up">
                        <div class="stat-card-title">Active (Last 24h)</div>
                        <div class="stat-card-value text-accent"><?= $active_24h ?></div>
                    </div>
                    <div class="stat-card glass-panel animate-up" style="animation-delay: 0.1s;">
                        <div class="stat-card-title">Inactive (> 7 Days)</div>
                        <div class="stat-card-value text-danger"><?= $inactive_7d ?></div>
                    </div>
                    <div class="stat-card glass-panel animate-up" style="animation-delay: 0.2s;">
                        <div class="stat-card-title">Avg. Lesson Progress</div>
                        <div class="stat-card-value"><?= $avg_progress ?></div>
                    </div>
                </div>

                <div class="glass-panel data-table-wrapper">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Enrolled</th>
                                <th>Progress (Units)</th>
                                <th>Joined</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($students as $s): ?>
                                <tr>
                                    <td><span class="clickable-name"
                                            onclick="showProfile(<?= $s['id'] ?>, 'student')"><?= htmlspecialchars($s['full_name']) ?></span>
                                    </td>
                                    <td><?= htmlspecialchars($s['email']) ?></td>
                                    <td><span class="badge-glass"><?= $s['enroll_count'] ?> Courses</span></td>
                                    <td>
                                        <div class="small mb-1"><?= $s['completed_progress_lessons'] ?> completed</div>
                                        <div class="progress bg-secondary bg-opacity-20"
                                            style="height: 4px; border-radius: 4px;">
                                            <div class="progress-bar bg-success"
                                                style="width: <?= min(100, $s['completed_progress_lessons'] * 10) ?>%"></div>
                                        </div>
                                    </td>
                                    <td style="color: var(--text-secondary);"><?= date('M d, Y', strtotime($s['created_at'])) ?>
                                    </td>
                                    <td>
                                        <button onclick="deleteUser(<?= $s['id'] ?>, 'student')"
                                            class="btn-glass btn-delete">Deactivate</button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- VIEW: SETTINGS (Now Synced with Student Style) -->
            <?php elseif ($view === 'settings'): ?>
                <h2 class="page-title">Profile & Platform Identity</h2>

                <div class="row g-4">
                    <!-- Left Column: Identity Card -->
                    <div class="col-lg-4">
                        <div class="glass-panel p-5 text-center h-100 d-flex flex-column align-items-center">
                            <div class="position-relative mb-4">
                                <?php if (!empty($admin_prefs['profile_pic'])): ?>
                                    <img src="../<?= htmlspecialchars($admin_prefs['profile_pic']) ?>"
                                        class="rounded-circle shadow-lg border border-success border-opacity-25"
                                        style="width: 120px; height: 120px; object-fit: cover;">
                                <?php else: ?>
                                    <div class="rounded-circle bg-success shadow-lg d-flex align-items-center justify-content-center text-dark fw-bold display-4"
                                        style="width: 120px; height: 120px;">
                                        <?= strtoupper(substr($admin_prefs['full_name'], 0, 1)) ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <h3 class="fw-bold mb-1"><?= htmlspecialchars($admin_prefs['full_name']) ?></h3>
                            <p class="text-secondary mb-4"><?= htmlspecialchars($admin_prefs['email']) ?></p>


                        </div>
                    </div>

                    <!-- Right Column: Settings Form -->
                    <div class="col-lg-8">
                        <div class="glass-panel p-4 p-md-5">
                            <h5 class="fw-bold mb-4 border-bottom border-light border-opacity-10 pb-3">Account Security &
                                Details</h5>

                            <?php if (!empty($profile_error)): ?>
                                <div class="alert alert-danger alert-dismissible fade show bg-danger bg-opacity-10 border-0 text-danger mb-4"
                                    role="alert">
                                    <?= htmlspecialchars($profile_error) ?>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                </div>
                            <?php endif; ?>

                            <?php if (!empty($profile_success)): ?>
                                <div class="alert alert-success alert-dismissible fade show bg-success bg-opacity-10 border-0 text-success mb-4"
                                    role="alert">
                                    <?= htmlspecialchars($profile_success) ?>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                </div>
                            <?php endif; ?>

                            <form method="POST" action="admin.php?view=settings" enctype="multipart/form-data">
                                <input type="hidden" name="update_admin_profile" value="1">
                                <div class="row g-4">
                                    <div class="col-md-6 text-start">
                                        <label class="small text-secondary mb-2">Display Name (Verified)</label>
                                        <input type="text" name="full_name"
                                            class="form-control border-secondary bg-dark text-white-50 p-3"
                                            value="<?= htmlspecialchars($admin_prefs['full_name']) ?>" readonly>
                                    </div>
                                    <div class="col-md-6 text-start">
                                        <label class="small text-secondary mb-2">Last Name</label>
                                        <input type="text" name="last_name"
                                            class="form-control border-secondary bg-dark text-white p-3"
                                            value="<?= htmlspecialchars($admin_prefs['last_name']) ?>"
                                            placeholder="Admin Surname">
                                    </div>

                                    <div class="col-12 text-start">
                                        <label class="small text-secondary mb-2">Current Password (Required for Changes)</label>
                                        <div style="position:relative;">
                                            <input type="password" id="admin_current_pw" name="current_password"
                                                class="form-control border-secondary bg-dark text-white p-3"
                                                placeholder="••••••••">
                                            <span onclick="togglePassword('admin_current_pw',this)" style="position:absolute;right:14px;top:50%;transform:translateY(-50%);cursor:pointer;color:rgba(255,255,255,0.4);">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                                            </span>
                                        </div>
                                        <hr class="border-light border-opacity-10 my-3">
                                    </div>

                                    <div class="col-md-6 text-start">
                                        <label class="small text-secondary mb-2">New Password (Optional)</label>
                                        <div style="position:relative;">
                                            <input type="password" id="admin_new_pw" name="new_password"
                                                class="form-control border-secondary bg-dark text-white p-3"
                                                placeholder="••••••••">
                                            <span onclick="togglePassword('admin_new_pw',this)" style="position:absolute;right:14px;top:50%;transform:translateY(-50%);cursor:pointer;color:rgba(255,255,255,0.4);">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                                            </span>
                                        </div>
                                    </div>
                                    <div class="col-md-6 text-start">
                                        <label class="small text-secondary mb-2">Confirm New Password</label>
                                        <div style="position:relative;">
                                            <input type="password" id="admin_confirm_pw" name="confirm_password"
                                                class="form-control border-secondary bg-dark text-white p-3"
                                                placeholder="••••••••">
                                            <span onclick="togglePassword('admin_confirm_pw',this)" style="position:absolute;right:14px;top:50%;transform:translateY(-50%);cursor:pointer;color:rgba(255,255,255,0.4);">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                                            </span>
                                        </div>
                                    </div>

                                    <div class="col-12 text-start">
                                        <label class="small text-secondary mb-2">Update Profile Picture</label>
                                        <input type="file" name="profile_pic"
                                            class="form-control border-secondary bg-dark text-white p-3">
                                    </div>

                                    <div class="col-12 mt-4">
                                        <button type="submit" class="btn-glass w-100 py-3 rounded-pill"
                                            style="color: var(--accent-green); border-color: var(--accent-green);">Save
                                            Profile Updates</button>
                                    </div>
                                </div>
                            </form>
                        </div>

                        <!-- Category Infrastructure -->
                        <div class="glass-panel p-4 p-md-5 mt-4">
                            <div class="d-flex align-items-center gap-3 mb-4">
                                <div class="p-2 rounded-circle bg-success bg-opacity-10 text-success">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                        stroke-width="2">
                                        <path
                                            d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z">
                                        </path>
                                    </svg>
                                </div>
                                <div>
                                    <h5 class="fw-bold mb-0">Category Management</h5>
                                    <p class="text-secondary small mb-0">Manage categories available for both <span
                                            class="text-white">Courses</span> and <span class="text-white">Resources</span>.
                                    </p>
                                </div>
                            </div>

                            <form method="POST" class="mb-4">
                                <input type="hidden" name="category_action" value="add">
                                <div class="input-group">
                                    <input type="text" name="category_name"
                                        class="form-control border-secondary bg-dark text-white p-3"
                                        placeholder="New Category Name (e.g. Data Science)" required>
                                    <button type="submit" class="btn btn-success px-4 fw-bold">Add Category</button>
                                </div>
                            </form>

                            <div class="d-flex flex-wrap gap-2 pt-3" id="category-tags-container">
                                <?php if (empty($categories_list)): ?>
                                    <div class="w-100 text-center py-4 border border-dashed border-secondary rounded-4">
                                        <span class="text-secondary small italic">No categories defined. Add your first taxonomy
                                            above.</span>
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($categories_list as $cat): ?>
                                        <div class="badge bg-dark bg-opacity-50 border border-secondary p-2 px-3 d-flex align-items-center gap-3 rounded-pill hover-border-danger transition-03"
                                            style="cursor: default; border-style: solid !important;">
                                            <span class="text-white small fw-bold"
                                                style="letter-spacing: 0.5px;"><?= htmlspecialchars($cat['name']) ?></span>
                                            <form method="POST" class="m-0 d-flex align-items-center"
                                                onsubmit="return confirm('Permanently remove category \'<?= addslashes($cat['name']) ?>\'?')">
                                                <input type="hidden" name="category_action" value="delete">
                                                <input type="hidden" name="category_id" value="<?= $cat['id'] ?>">
                                                <button type="submit" class="btn-close btn-close-white p-0 m-0"
                                                    style="width: 0.5rem; height: 0.5rem; background-size: 0.5rem;"
                                                    aria-label="Delete"></button>
                                            </form>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- VIEW: INSTRUCTORS -->
            <?php elseif ($view === 'instructors'): ?>
                <h2 class="page-title">Instructor Management</h2>
                <div class="glass-panel data-table-wrapper">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Courses</th>
                                <th>Category</th>
                                <th>Joined</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($instructors as $i): ?>
                                <tr>
                                    <td>
                                        <span class="clickable-name"
                                            onclick="showProfile(<?= $i['id'] ?>, 'creator')"><?= htmlspecialchars($i['full_name']) ?></span>
                                        <?= $i['is_approved'] ? '' : '<span style="color:var(--accent-green); font-size:0.7rem; margin-left:5px;">(Pending)</span>' ?>
                                    </td>
                                    <td><span class="badge-glass"><?= $i['course_count'] ?> Courses</span></td>
                                    <td><span class="badge-glass"><?= htmlspecialchars($i['category']) ?></span></td>
                                    <td style="color: var(--text-secondary);">
                                        <?= date('M d, Y', strtotime($i['created_at'])) ?>
                                        <?php if ($i['banned_until'] && strtotime($i['banned_until']) > time()): ?>
                                            <br><small class="text-danger fw-bold">Banned until <?= date('M d, Y', strtotime($i['banned_until'])) ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (!$i['is_approved']): ?>
                                            <button onclick="approve(<?= $i['id'] ?>)" class="btn-glass"
                                                style="color: var(--accent-green); border-color: var(--accent-green);">Approve</button>
                                        <?php endif; ?>
                                        
                                        <?php if ($i['banned_until'] && strtotime($i['banned_until']) > time()): ?>
                                            <button onclick="unbanUser(<?= $i['id'] ?>, 'instructor')"
                                                class="btn-glass" style="color: #3b82f6; border-color: #3b82f6;">Unban</button>
                                        <?php else: ?>
                                            <button onclick="deleteUser(<?= $i['id'] ?>, 'creator')"
                                                class="btn-glass btn-delete">Ban</button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- VIEW: COURSES -->
            <?php elseif ($view === 'content_courses'): ?>
                <h2 class="page-title">Course Oversight</h2>
                <div class="glass-panel data-table-wrapper">
                    <div style="padding: 1rem 1.5rem; border-bottom: 1px solid var(--border-light);">
                        <select id="courseCategoryFilter" class="form-select bg-dark text-light border-secondary"
                            style="width: auto; display: inline-block;" onchange="filterCourses()">
                            <option value="all">All Categories</option>
                            <?php foreach ($categories_list as $cl): ?>
                                <option value="<?= htmlspecialchars($cl['name']) ?>"><?= htmlspecialchars($cl['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <table class="data-table" id="coursesTable">
                        <thead>
                            <tr>
                                <th>Title</th>
                                <th>Instructor</th>
                                <th>Category</th>
                                <th>Enrolled</th>
                                <th>Posted Date</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($content_courses as $c): ?>
                                <tr data-category="<?= htmlspecialchars($c['category']) ?>">
                                    <td><span class="clickable-name"
                                            onclick="showProfile(<?= $c['id'] ?>, 'course')"><?= htmlspecialchars($c['title']) ?></span>
                                    </td>
                                    <td><span class="clickable-name"
                                            onclick="showProfile(<?= $c['creator_id'] ?>, 'creator')"><?= htmlspecialchars($c['instructor_name']) ?></span>
                                    </td>
                                    <td><span class="badge-glass"><?= htmlspecialchars($c['category']) ?></span></td>
                                    <td><span class="badge-glass"><?= $c['student_count'] ?> Students</span></td>
                                    <td style="color: var(--text-secondary);"><?= date('M d, Y', strtotime($c['created_at'])) ?>
                                    </td>
                                    <td>
                                        <button onclick="deleteContent(<?= $c['id'] ?>, 'course')"
                                            class="btn-glass btn-delete">Delete</button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- VIEW: RESOURCES -->
            <?php elseif ($view === 'content_resources'): ?>
                <h2 class="page-title">Resource Oversight</h2>
                <div class="glass-panel data-table-wrapper">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Title</th>
                                <th>Instructor</th>
                                <th>Category</th>
                                <th>Uploaded</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($content_resources as $r): ?>
                                <tr>
                                    <td><?= htmlspecialchars($r['title']) ?></td>
                                    <td><span class="clickable-name"
                                            onclick="showProfile(<?= $r['creator_id'] ?>, 'creator')"><?= htmlspecialchars($r['instructor_name']) ?></span>
                                    </td>
                                    <td><span class="badge-glass"><?= htmlspecialchars($r['category']) ?></span></td>
                                    <td style="color: var(--text-secondary);"><?= date('M d, Y', strtotime($r['created_at'])) ?>
                                    </td>
                                    <td>
                                        <button onclick="deleteContent(<?= $r['id'] ?>, 'resource')"
                                            class="btn-glass btn-delete">Delete</button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>


            <?php endif; ?>
        </div>
    </main>

    <!-- Toast Container -->
    <div class="toast-container" id="toastContainer"></div>

    <!-- Profile Detail Modal -->
    <div class="modal fade" id="profileModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitle">Profile Details</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                        aria-label="Close"></button>
                </div>
                <div class="modal-body" id="modalBody">
                    <!-- Loaded via JS -->
                    <div class="text-center p-4">
                        <div class="spinner-border text-success" role="status"></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary rounded-pill" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap & JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Mobile Sidebar Logic (Synced with dashboard.php student behavior)
        const hamburger = document.getElementById('dashHamburger');
        const sidebar = document.getElementById('dashSidebar');
        const overlay = document.getElementById('dashSidebarOverlay');

        function openSidebar() {
            sidebar.classList.add('mobile-open');
            overlay.classList.add('open');
            hamburger.classList.add('open');
            hamburger.setAttribute('aria-expanded', 'true');
            document.body.style.overflow = 'hidden';
        }

        function closeSidebar() {
            sidebar.classList.remove('mobile-open');
            overlay.classList.remove('open');
            hamburger.classList.remove('open');
            hamburger.setAttribute('aria-expanded', 'false');
            document.body.style.overflow = '';
        }

        if (hamburger) {
            hamburger.addEventListener('click', () => {
                if (sidebar.classList.contains('mobile-open')) closeSidebar();
                else openSidebar();
            });
        }
        if (overlay) overlay.addEventListener('click', closeSidebar);

        sidebar.querySelectorAll('.sidebar-nav-item').forEach(link => {
            link.addEventListener('click', closeSidebar);
        });

        let profileModal;
        document.addEventListener('DOMContentLoaded', () => {
            const modalEl = document.getElementById('profileModal');
            if (modalEl) profileModal = new bootstrap.Modal(modalEl);
        });

        function showProfile(id, role, isFromNotif = false, notifId = null, notifType = null, extraData = null) {
            if (!id) return;
            if (!profileModal) {
                const modalEl = document.getElementById('profileModal');
                if (modalEl) profileModal = new bootstrap.Modal(modalEl);
            }
            document.getElementById('modalBody').innerHTML = '<div class="text-center p-4"><div class="spinner-border text-success" role="status"></div></div>';
            if (profileModal) profileModal.show();

            fetch(`../api/get_user_details.php?id=${id}&role=${role}`)
                .then(r => r.json())
                .then(res => {
                    if (!res.success) {
                        document.getElementById('modalBody').innerHTML = `<div class="alert alert-danger">${res.message}</div>`;
                        return;
                    }
                    const data = res.data;
                    const iMeta = data.meta || {};
                    let html = `
                        <div class="row">
                            <div class="col-md-12">
                                <div class="profile-label">Full Name</div>
                                <div class="profile-value h5 text-success">${data.full_name}</div>
                                
                                <div class="profile-label">Email Address</div>
                                <div class="profile-value">${data.email}</div>
                                
                                <div class="profile-label">Joined On</div>
                                <div class="profile-value">${new Date(data.created_at).toLocaleDateString()}</div>
                    `;

                    if (role === 'student') {
                        html += `
                            <div class="profile-label">Enrolled Courses (${data.enroll_count})</div>
                            <div class="profile-value">
                                ${data.content_courses && data.content_courses.length > 0
                                ? data.content_courses.map(c => `<div class="badge-glass mb-1">${c}</div>`).join(' ')
                                : '<span class="text-muted italic">Not enrolled in any content_courses yet.</span>'}
                            </div>
                        `;
                    } else if (role === 'creator') {
                        const cats = data.all_categories && data.all_categories.length > 0 ? data.all_categories : (data.meta && data.meta.category ? [data.meta.category] : []);
                        html += `
                            <div class="profile-label">Approved Categories</div>
                            <div class="profile-value d-flex flex-wrap gap-2">
                                ${cats.length > 0 ? cats.map(c => `<span class="badge-glass" style="color:var(--accent-green);border-color:rgba(0,229,153,0.3);">${c}</span>`).join('') : '<span class="text-muted small">Unassigned</span>'}
                            </div>

                            <div class="profile-label">Biography / Description</div>
                            <div class="profile-value text-secondary small" style="line-height: 1.6;">${data.bio || 'No bio provided.'}</div>
                            
                            <div class="profile-label">Social & Professional Links</div>
                            <div class="d-flex flex-wrap gap-2 mt-2">
                                ${iMeta.linkedin ? `<a href="${iMeta.linkedin}" target="_blank" class="social-link">LinkedIn</a>` : ''}
                                ${iMeta.github ? `<a href="${iMeta.github}" target="_blank" class="social-link">GitHub</a>` : ''}
                                ${iMeta.facebook ? `<a href="${iMeta.facebook}" target="_blank" class="social-link">Facebook</a>` : ''}
                                ${iMeta.twitter ? `<a href="${iMeta.twitter}" target="_blank" class="social-link">Twitter</a>` : ''}
                                ${iMeta.portfolio ? `<a href="${iMeta.portfolio}" target="_blank" class="social-link">Website</a>` : ''}
                                ${(!iMeta.linkedin && !iMeta.github && !iMeta.portfolio && !iMeta.facebook && !iMeta.twitter) ? '<span class="text-muted small">No social links provided.</span>' : ''}
                            </div>
                        `;

                        if (notifType === 'category_request' && extraData) {
                            html += `
                                <div class="mt-4 pt-4 border-top border-light">
                                    <div class="profile-label text-warning mb-2">Expertise Expansion Request</div>
                                    <div class="small text-white-50 mb-3">This instructor has requested approval to teach in the <strong>${extraData}</strong> domain.</div>
                                    <div class="d-flex gap-2">
                                        <button onclick="approveCategory(${notifId}, ${id}, '${extraData.replace(/'/g, "\\'")}')" class="btn btn-success rounded-pill flex-grow-1">Approve Domain</button>
                                        <button onclick="declineCategory(${notifId}, ${id}, '${extraData.replace(/'/g, "\\'")}')" class="btn btn-outline-danger rounded-pill flex-grow-1">Decline</button>
                                    </div>
                                </div>
                            `;
                        } else if (data.is_approved == 0) {
                            html += `
                                <div class="mt-4 pt-4 border-top border-light d-flex gap-2">
                                    <button onclick="approve(${data.id})" class="btn btn-success rounded-pill flex-grow-1">Approve Request</button>
                                    <button onclick="deleteUser(${data.id}, 'creator')" class="btn btn-outline-danger rounded-pill flex-grow-1">Decline</button>
                                </div>
                            `;
                        }
                    } else if (role === 'course' || role === 'resource') {
                        const iMeta = data.instructor_meta || {};
                        html = `
                            <div class="row">
                                <div class="col-md-12">
                                    <div class="profile-label">${role === 'course' ? 'Course' : 'Resource'} Title</div>
                                    <div class="profile-value h5 text-success">${data.title}</div>
                                    
                                    <div class="profile-label">Category & Level</div>
                                    <div class="profile-value"><span class="badge-glass">${data.category || 'N/A'}</span> <span class="badge bg-secondary">${data.level || 'All Levels'}</span></div>
                                    
                                    <div class="profile-label">Description</div>
                                    <div class="profile-value text-secondary small mb-4" style="line-height: 1.6;">${data.description || 'No description provided.'}</div>
                                    
                                    <div class="p-3 rounded-4 mb-4" style="background: rgba(255,255,255,0.03); border: 1px solid var(--border-light);">
                                        <div class="profile-label mt-0">Instructor Profile</div>
                                        <div class="d-flex align-items-center gap-3 mb-2">
                                            <div class="bg-success rounded-circle d-flex align-items-center justify-content-center text-dark fw-bold" style="width: 40px; height: 40px; font-size: 0.9rem;">
                                                ${data.instructor_name.charAt(0).toUpperCase()}
                                            </div>
                                            <div>
                                                <div class="fw-bold text-white">${data.instructor_name}</div>
                                                <div class="small text-secondary">${data.instructor_email}</div>
                                            </div>
                                        </div>
                                        <div class="small text-secondary mb-3">${data.instructor_bio || 'No bio available.'}</div>
                                        <div class="d-flex flex-wrap gap-2">
                                            ${iMeta.linkedin ? `<a href="${iMeta.linkedin}" target="_blank" class="social-link px-2 py-1" style="font-size: 0.7rem;">LinkedIn</a>` : ''}
                                            ${iMeta.github ? `<a href="${iMeta.github}" target="_blank" class="social-link px-2 py-1" style="font-size: 0.7rem;">GitHub</a>` : ''}
                                            ${iMeta.portfolio ? `<a href="${iMeta.portfolio}" target="_blank" class="social-link px-2 py-1" style="font-size: 0.7rem;">Website</a>` : ''}
                                        </div>
                                    </div>

                                    <div class="mt-4">
                                        <a href="../view_content.php?id=${data.id}&type=${role}&role=admin" target="_blank" class="btn btn-outline-success btn-sm rounded-pill w-100">
                                            Preview Entire Course Content (New Tab)
                                        </a>
                                    </div>
                                </div>
                            </div>
                        `;
                    }

                    if (role !== 'course' && role !== 'resource') html += `</div></div>`;
                    document.getElementById('modalBody').innerHTML = html;

                    let titleText = 'Profile Details';
                    if (role === 'student') titleText = 'Student Profile';
                    else if (role === 'creator') titleText = 'Instructor Profile';
                    else if (role === 'course') titleText = 'Course Details';
                    else if (role === 'resource') titleText = 'Resource Details';
                    document.getElementById('modalTitle').innerText = titleText;
                });
        }

        function approve(id) {
            showConfirm("Approve Instructor?", "This will allow the instructor to upload content.", () => {
                const formData = new FormData();
                formData.append('action', 'approve');
                formData.append('role_type', 'creator');
                formData.append('user_id', id);

                fetch('../api/admin_actions.php', {
                    method: 'POST',
                    body: formData
                }).then(() => location.reload());
            });
        }

        function approveCategory(notifId, instructorId, category) {
            showConfirm("Approve Domain?", `Approve the requested category: ${category}?`, () => {
                const formData = new FormData();
                formData.append('action', 'approve_category');
                formData.append('notif_id', notifId);
                formData.append('instructor_id', instructorId);
                formData.append('category', category);

                fetch('../api/admin_actions.php', {
                    method: 'POST',
                    body: formData
                }).then(() => location.reload());
            });
        }

        function declineCategory(notifId, instructorId, category) {
            showConfirm("Decline Domain?", `Decline the requested category: ${category}?`, () => {
                const formData = new FormData();
                formData.append('action', 'decline_category');
                formData.append('notif_id', notifId);
                formData.append('instructor_id', instructorId);
                formData.append('category', category);

                fetch('../api/admin_actions.php', {
                    method: 'POST',
                    body: formData
                }).then(() => location.reload());
            });
        }
    </script>

    <!-- ── CUSTOM CONFIRM MODAL ────────────────────────────────────────── -->
    <div class="modal fade" id="confirmModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-sm">
            <div class="modal-content glass-panel border-light" style="background: rgba(15, 23, 42, 0.95); backdrop-filter: blur(20px); border-radius: 24px;">
                <div class="modal-body text-center p-4">
                    <div class="mb-3 text-warning">
                        <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>
                    </div>
                    <h5 class="fw-bold text-white mb-2" id="confirmTitle">Are you sure?</h5>
                    <p class="text-secondary small mb-4" id="confirmMessage">This action cannot be undone.</p>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-outline-secondary flex-grow-1 rounded-pill" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-danger flex-grow-1 rounded-pill" id="confirmActionBtn">Confirm</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ── CUSTOM TOAST ──────────────────────────────────────────────── -->
    <div class="toast-container position-fixed bottom-0 end-0 p-3" style="z-index: 1100;">
        <div id="adminToast" class="toast align-items-center text-white bg-dark border-0 rounded-4 shadow-lg" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="d-flex p-2">
                <div class="toast-body" id="toastMessage" style="font-weight: 500;"></div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>
        </div>
    </div>

    <script>
        const confirmModal = new bootstrap.Modal(document.getElementById('confirmModal'));
        const adminToast = new bootstrap.Toast(document.getElementById('adminToast'));

        function showConfirm(title, message, onConfirm) {
            document.getElementById('confirmTitle').innerText = title;
            document.getElementById('confirmMessage').innerText = message;
            const btn = document.getElementById('confirmActionBtn');
            
            // Clean up old listeners
            const newBtn = btn.cloneNode(true);
            btn.parentNode.replaceChild(newBtn, btn);
            
            newBtn.addEventListener('click', () => {
                onConfirm();
                confirmModal.hide();
            });
            confirmModal.show();
        }

        function showToast(message, isError = false) {
            const toastEl = document.getElementById('adminToast');
            toastEl.classList.remove('bg-success', 'bg-danger', 'bg-dark');
            toastEl.classList.add(isError ? 'bg-danger' : 'bg-success');
            document.getElementById('toastMessage').innerText = message;
            adminToast.show();
        }

        function approveCourse(notifId, courseId, instructorId) {
            showConfirm("Approve Submission?", "This will make the course visible to all students immediately.", () => {
                const formData = new FormData();
                formData.append('action', 'approve_course');
                formData.append('notif_id', notifId);
                formData.append('course_id', courseId);
                formData.append('instructor_id', instructorId);

                fetch('../api/admin_actions.php', {
                    method: 'POST',
                    body: formData
                }).then(r => r.json()).then(data => {
                    if(data.success) {
                        showToast("Course approved successfully!");
                        setTimeout(() => location.reload(), 1500);
                    } else {
                        showToast(data.message, true);
                    }
                });
            });
        }

        function declineCourse(notifId, courseId, instructorId) {
            showConfirm("Decline Submission?", "The course will be moved to deleted status and instructor will be notified.", () => {
                const formData = new FormData();
                formData.append('action', 'decline_course');
                formData.append('notif_id', notifId);
                formData.append('course_id', courseId);
                formData.append('instructor_id', instructorId);

                fetch('../api/admin_actions.php', {
                    method: 'POST',
                    body: formData
                }).then(r => r.json()).then(data => {
                    if(data.success) {
                        showToast("Course submission declined.");
                        setTimeout(() => location.reload(), 1500);
                    } else {
                        showToast(data.message, true);
                    }
                });
            });
        }

        function deleteUser(id, role) {
            let actionText = role === 'student' ? 'deactivate' : 'ban';
            showConfirm(`Confirm ${actionText}?`, `Are you sure you want to ${actionText} this ${role}? ${role === 'instructor' ? 'They will be restricted for 3 months.' : ''}`, () => {
                const formData = new FormData();
                formData.append('action', 'delete_user');
                formData.append('role_type', role);
                formData.append('user_id', id);

                fetch('../api/admin_actions.php', {
                    method: 'POST',
                    body: formData
                }).then(r => r.json()).then(data => {
                    if(data.success) {
                        showToast(`${role} ${actionText}ed successfully.`);
                        setTimeout(() => location.reload(), 1500);
                    } else {
                        showToast(data.message, true);
                    }
                });
            });
        }

        function unbanUser(id, role) {
            showConfirm(`Unban Instructor?`, `Are you sure you want to restore access for this instructor?`, () => {
                const formData = new FormData();
                formData.append('action', 'unban_user');
                formData.append('user_id', id);

                fetch('../api/admin_actions.php', {
                    method: 'POST',
                    body: formData
                }).then(r => r.json()).then(data => {
                    if(data.success) {
                        showToast(`Instructor unbanned successfully.`);
                        setTimeout(() => location.reload(), 1500);
                    } else {
                        showToast(data.message, true);
                    }
                });
            });
        }

        function deleteContent(id, type) {
            showConfirm(`Delete ${type}?`, `Are you sure you want to PERMANENTLY delete this ${type}? This cannot be reversed.`, () => {
                const formData = new FormData();
                formData.append('action', 'delete_' + type);
                formData.append(type + '_id', id);

                fetch('../api/admin_actions.php', {
                    method: 'POST',
                    body: formData
                }).then(r => r.json()).then(data => {
                    if(data.success) {
                        showToast(`${type} deleted successfully.`);
                        setTimeout(() => location.reload(), 1500);
                    } else {
                        showToast(data.message, true);
                    }
                });
            });
        }

        function restoreContent(id, type) {
            showConfirm(`Restore ${type}?`, `This will bring the ${type} back from the deleted archives.`, () => {
                const formData = new FormData();
                formData.append('action', 'restore_' + type);
                formData.append(type + '_id', id);

                fetch('../api/admin_actions.php', {
                    method: 'POST',
                    body: formData
                }).then(r => r.json()).then(data => {
                    if(data.success) {
                        showToast(`${type} restored successfully.`);
                        setTimeout(() => location.reload(), 1500);
                    } else {
                        showToast(data.message, true);
                    }
                });
            });
        }

        function openModal(id, role) {
            const modal = new bootstrap.Modal(document.getElementById('profileModal'));
            modal.show();
            document.getElementById('modalBody').innerHTML = '<div class="text-center p-5"><div class="spinner-border text-success" role="status"></div></div>';

            fetch('../api/admin_actions.php?get_profile=' + id + '&role=' + role)
                .then(r => r.json())
                .then(data => {
                    if (!data.success) {
                        document.getElementById('modalBody').innerHTML = `<div class="alert alert-danger">${data.message}</div>`;
                        return;
                    }
                    
                    data = data.data; 
                    
                    let html = '';
                    if (role !== 'course' && role !== 'resource') {
                        html = `
                            <div class="d-flex align-items-center gap-4 mb-4 pb-4 border-bottom border-light border-opacity-10">
                                ${data.profile_pic ? `<img src="${data.profile_pic}" class="rounded-circle shadow" style="width: 80px; height: 80px; object-fit: cover;">` : `<div class="rounded-circle bg-success bg-opacity-20 d-flex align-items-center justify-content-center text-success display-6 fw-bold" style="width: 80px; height: 80px;">${data.full_name[0].toUpperCase()}</div>`}
                                <div>
                                    <div class="profile-label">Full Name</div>
                                    <div class="profile-value h5 text-success mb-0">${data.full_name}</div>
                                    <div class="text-secondary small">${data.email}</div>
                                </div>
                            </div>
                            <div class="ps-1">
                        `;
                    }

                    if (role === 'instructor' || role === 'creator') {
                        html += `
                            <div class="profile-label">Teaching Category</div>
                            <div class="profile-value"><span class="badge-glass">${(data.meta && data.meta.category) ? data.meta.category : 'General Instruction'}</span></div>

                            <div class="profile-label">Biography / Description</div>
                            <div class="profile-value text-secondary small" style="line-height: 1.6;">${data.bio || 'No bio provided.'}</div>
                            
                            <div class="profile-label">Social & Professional Links</div>
                            <div class="d-flex flex-wrap gap-2 mt-2">
                                ${data.meta && data.meta.linkedin ? `<a href="${data.meta.linkedin}" target="_blank" class="social-link px-2 py-1" style="font-size: 0.7rem;">LinkedIn</a>` : ''}
                                ${data.meta && data.meta.github ? `<a href="${data.meta.github}" target="_blank" class="social-link px-2 py-1" style="font-size: 0.7rem;">GitHub</a>` : ''}
                                ${data.meta && data.meta.portfolio ? `<a href="${data.meta.portfolio}" target="_blank" class="social-link px-2 py-1" style="font-size: 0.7rem;">Website</a>` : ''}
                                ${(!data.meta || (!data.meta.linkedin && !data.meta.github && !data.meta.portfolio)) ? '<span class="text-muted small">No social links provided.</span>' : ''}
                            </div>
                        `;
                    } else if (role === 'course' || role === 'resource') {
                        const iMeta = data.instructor_meta || {};
                        html = `
                            <div class="row">
                                <div class="col-md-12">
                                    <div class="profile-label">${role === 'course' ? 'Course' : 'Resource'} Title</div>
                                    <div class="profile-value h5 text-success mb-2">${data.title}</div>
                                    
                                    <div class="profile-label">Category & Level</div>
                                    <div class="profile-value"><span class="badge-glass">${data.category || 'N/A'}</span> <span class="badge bg-secondary">${data.level || 'All Levels'}</span></div>
                                    
                                    <div class="profile-label">Description</div>
                                    <div class="profile-value text-secondary small mb-4" style="line-height: 1.6;">${data.description || 'No description provided.'}</div>
                                    
                                    <div class="p-3 rounded-4" style="background: rgba(255,255,255,0.03); border: 1px solid var(--border-light);">
                                        <div class="profile-label mt-0">Instructor Information</div>
                                        <div class="d-flex align-items-center gap-3 mb-2">
                                            ${data.instructor_pic ? `<img src="${data.instructor_pic}" class="rounded-circle shadow" style="width: 40px; height: 40px; object-fit: cover;">` : `<div class="rounded-circle bg-success bg-opacity-20 d-flex align-items-center justify-content-center text-success fw-bold" style="width: 40px; height: 40px;">${data.instructor_name[0].toUpperCase()}</div>`}
                                            <div>
                                                <div class="text-white small fw-bold">${data.instructor_name}</div>
                                                <div class="text-secondary small" style="font-size: 0.7rem;">${data.instructor_email}</div>
                                            </div>
                                        </div>
                                        <div class="d-flex flex-wrap gap-2 mt-2">
                                            ${iMeta.linkedin ? `<a href="${iMeta.linkedin}" target="_blank" class="social-link px-2 py-1" style="font-size: 0.7rem;">LinkedIn</a>` : ''}
                                            ${iMeta.github ? `<a href="${iMeta.github}" target="_blank" class="social-link px-2 py-1" style="font-size: 0.7rem;">GitHub</a>` : ''}
                                            ${iMeta.portfolio ? `<a href="${iMeta.portfolio}" target="_blank" class="social-link px-2 py-1" style="font-size: 0.7rem;">Website</a>` : ''}
                                        </div>
                                    </div>

                                    <div class="mt-4">
                                        <a href="../view_content.php?id=${data.id}&type=${role}&role=admin" target="_blank" class="btn btn-outline-success btn-sm rounded-pill w-100">
                                            Preview Entire Course Content (New Tab)
                                        </a>
                                    </div>
                                </div>
                            </div>
                        `;
                    }

                    if (role !== 'course' && role !== 'resource') html += `</div>`;
                    document.getElementById('modalBody').innerHTML = html;
                    document.getElementById('modalTitle').innerText = (role === 'course' || role === 'resource') ? 'Content Details' : (role === 'instructor' || role === 'creator' ? 'Instructor Profile' : 'Student Profile');
                });
        }

        // --- New Features JS ---

        // Notification Polling
        let lastNotifCount = <?= $unread_notifs ?? 0 ?>;
        function checkNotifications() {
            fetch('../api/get_notifications.php')
                .then(r => r.json())
                .then(res => {
                    if (res.success && res.unread_count > lastNotifCount) {
                        showToast("New Notification", false); 
                        const badge = document.querySelector('.notif-badge');
                        if (badge) badge.innerText = res.unread_count;
                        else {
                            const btn = document.querySelector('.dropdown button');
                            if (btn) {
                                const span = document.createElement('span');
                                span.className = 'notif-badge';
                                span.innerText = res.unread_count;
                                btn.appendChild(span);
                            }
                        }
                    }
                    lastNotifCount = res.unread_count;
                });
        }
        setInterval(checkNotifications, 30000); 

        function clearAllNotifications() {
            showConfirm("Clear All?", "Mark all notifications as read?", () => {
                fetch('../api/admin_actions.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'action=clear_all_notifs'
                }).then(() => {
                    showToast("All notifications cleared.");
                    setTimeout(() => location.reload(), 500);
                });
            });
        }

        function dismissNotification(id, event) {
            if (event) event.stopPropagation();
            const formData = new FormData();
            formData.append('action', 'dismiss_notif');
            formData.append('notif_id', id);

            fetch('../api/admin_actions.php', {
                method: 'POST',
                body: formData
            }).then(() => location.reload());
        }

        function filterCourses() {
            const selected = document.getElementById('courseCategoryFilter').value;
            const rows = document.querySelectorAll('#coursesTable tbody tr');
            rows.forEach(row => {
                if (selected === 'all' || row.dataset.category === selected) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        }

        function togglePassword(id, el) {
            const input = document.getElementById(id);
            const isPass = input.type === 'password';
            input.type = isPass ? 'text' : 'password';
            el.innerHTML = isPass
                ? '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line></svg>'
                : '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>';
        }

        // ── Mobile Sidebar Logic ──
        const dashHamburger = document.getElementById('dashHamburger');
        const dashSidebar = document.getElementById('dashSidebar');
        const dashOverlay = document.getElementById('dashSidebarOverlay');

        function toggleDashSidebar() {
            if (!dashSidebar || !dashHamburger || !dashOverlay) return;
            const isOpen = dashSidebar.classList.contains('mobile-open');
            if (isOpen) {
                dashSidebar.classList.remove('mobile-open');
                dashHamburger.classList.remove('open');
                dashOverlay.classList.remove('open');
                dashHamburger.setAttribute('aria-expanded', 'false');
                document.body.style.overflow = ''; 
            } else {
                dashSidebar.classList.add('mobile-open');
                dashHamburger.classList.add('open');
                dashOverlay.classList.add('open');
                dashHamburger.setAttribute('aria-expanded', 'true');
                document.body.style.overflow = 'hidden'; 
            }
        }

        if (dashHamburger) {
            dashHamburger.addEventListener('click', toggleDashSidebar);
        }
        if (dashOverlay) {
            dashOverlay.addEventListener('click', toggleDashSidebar);
        }
    </script>
</body>
</html>