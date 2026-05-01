<?php
require_once 'api/session_helper.php';
start_role_session();
require_once 'api/db.php';

// Access Control
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'instructor') {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// Fetch Instructor Data
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND role = 'instructor'");
$stmt->execute([$user_id]);
$instructor = $stmt->fetch(PDO::FETCH_ASSOC);

// Decode meta and other JSON
$instructor_meta = json_decode($instructor['meta'] ?? '{}', true);
$instructor_content_courses = json_decode($instructor['courses'] ?? '[]', true);
$instructor_content_resources = json_decode($instructor['resources'] ?? '[]', true);
$instructor_category = $instructor_meta['category'] ?? '';

$approved_categories = $instructor_meta['approved_categories'] ?? [];
if (!empty($instructor_category) && !in_array($instructor_category, $approved_categories)) {
    array_unshift($approved_categories, $instructor_category);
}

if (!$instructor) {
    header("Location: api/logout.php");
    exit;
}

// Stats
$stats = [];
$stmt = $pdo->prepare("SELECT COUNT(*) FROM (SELECT id, user_id AS creator_id, title, description, file_path AS thumbnail_path, category, generic_value AS level, meta_text AS tags, is_resource, is_deleted, deleted_at, created_at FROM content WHERE record_type = 'course') content_courses WHERE creator_id = ? AND is_deleted = 0");
$stmt->execute([$user_id]);
$content_courses_count = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM (SELECT id, user_id AS creator_id, title, file_path, generic_value AS file_type, category, is_deleted, deleted_at, created_at FROM content WHERE record_type = 'resource') content_resources WHERE creator_id = ?");
$stmt->execute([$user_id]);
$content_resources_count = $stmt->fetchColumn();

$stats['total_content'] = $content_courses_count + $content_resources_count;

// Count students who have enrolled in any of this instructor's content_courses
// Instructor's course IDs are in $instructor_content_courses
if (!empty($instructor_content_courses)) {
    $placeholders = implode(',', array_fill(0, count($instructor_content_courses), '?'));
    $stmt = $pdo->prepare("SELECT COUNT(DISTINCT id) FROM users WHERE role = 'student' AND ( " . 
        implode(" OR ", array_map(fn($id) => "JSON_CONTAINS(courses, CAST($id AS CHAR))", $instructor_content_courses)) . 
        " )");
    $stmt->execute();
    $stats['total_students'] = $stmt->fetchColumn();
    
    // Weekly New Students (Proxy: Students created in last 7 days enrolled in these courses)
    $stmt = $pdo->prepare("SELECT COUNT(DISTINCT id) FROM users WHERE role = 'student' AND created_at >= NOW() - INTERVAL 7 DAY AND ( " . 
        implode(" OR ", array_map(fn($id) => "JSON_CONTAINS(courses, CAST($id AS CHAR))", $instructor_content_courses)) . 
        " )");
    $stmt->execute();
    $stats['weekly_new_students'] = $stmt->fetchColumn();
} else {
    $stats['total_students'] = 0;
    $stats['weekly_new_students'] = 0;
}

// Weekly Content Uploads (These should be checked regardless of student count)
$stmt = $pdo->prepare("SELECT COUNT(*) FROM content WHERE record_type = 'course' AND user_id = ? AND created_at >= NOW() - INTERVAL 7 DAY AND is_deleted = 0");
$stmt->execute([$user_id]);
$stats['weekly_courses'] = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM content WHERE record_type = 'resource' AND user_id = ? AND created_at >= NOW() - INTERVAL 7 DAY AND is_deleted = 0");
$stmt->execute([$user_id]);
$stats['weekly_resources'] = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM (SELECT id, JSON_UNQUOTE(JSON_EXTRACT(meta, '$.course_id')) AS course_id, JSON_UNQUOTE(JSON_EXTRACT(meta, '$.student_id')) AS student_id, JSON_UNQUOTE(JSON_EXTRACT(meta, '$.rating')) AS rating, message AS comment, JSON_UNQUOTE(JSON_EXTRACT(meta, '$.reply')) AS reply, created_at FROM notifications WHERE type = 'feedback') f JOIN content c ON f.course_id = c.id WHERE c.user_id = ? AND c.record_type = 'course'");
$stmt->execute([$user_id]);
$stats['pending_feedback'] = $stmt->fetchColumn();

// Fetch Content (Merge Courses and Resources)
$stmt = $pdo->prepare("SELECT id, title, category, thumbnail_path, level, is_resource, created_at, is_approved, 'course' as actual_type FROM (SELECT id, user_id AS creator_id, title, description, file_path AS thumbnail_path, category, generic_value AS level, meta_text AS tags, is_resource, is_deleted, deleted_at, created_at, is_approved FROM content WHERE record_type = 'course') content_courses WHERE creator_id = ? AND is_deleted = 0");
$stmt->execute([$user_id]);
$myCourses = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare("SELECT id, title, category, file_path as thumbnail_path, generic_value as level, 1 as is_resource, created_at, is_approved, 'resource' as actual_type FROM (SELECT id, user_id AS creator_id, title, file_path, generic_value, category, is_deleted, deleted_at, created_at, is_approved FROM content WHERE record_type = 'resource') content_resources WHERE creator_id = ?");
$stmt->execute([$user_id]);
$myResources = $stmt->fetchAll(PDO::FETCH_ASSOC);

$myContent = array_merge($myCourses, $myResources);
usort($myContent, function($a, $b) {
    return strtotime($b['created_at']) - strtotime($a['created_at']);
});

// Fetch dynamic categories
try {
    $db_categories = $pdo->query("SELECT name FROM categories ORDER BY name ASC")->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    $db_categories = [];
}

$categories_icons = [
    'Programming & Development' => '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="16 18 22 12 16 6"></polyline><polyline points="8 6 2 12 8 18"></polyline></svg>',
    'Web Development' => '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="2" y1="12" x2="22" y2="12"></line><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"></path></svg>',
    'UI/UX & Design' => '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="13.5" cy="6.5" r=".5"></circle><circle cx="17.5" cy="10.5" r=".5"></circle><circle cx="8.5" cy="7.5" r=".5"></circle><circle cx="6.5" cy="12.5" r=".5"></circle><path d="M12 2C6.5 2 2 6.5 2 12s4.5 10 10 10c.926 0 1.648-.746 1.648-1.688 0-.437-.18-.835-.437-1.125-.29-.289-.438-.652-.438-1.125a1.64 1.64 0 0 1 1.668-1.668h1.996c3.051 0 5.555-2.503 5.555-5.554C21.965 6.012 17.461 2 12 2z"></path></svg>',
    'Data & Analytics' => '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="20" x2="18" y2="10"></line><line x1="12" y1="20" x2="12" y2="4"></line><line x1="6" y1="20" x2="6" y2="14"></line></svg>',
    'AI & Machine Learning' => '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="4" y="4" width="16" height="16" rx="2" ry="2"></rect><rect x="9" y="9" width="6" height="6"></rect><line x1="9" y1="1" x2="9" y2="4"></line><line x1="15" y1="1" x2="15" y2="4"></line><line x1="9" y1="20" x2="9" y2="23"></line><line x1="15" y1="20" x2="15" y2="23"></line><line x1="20" y1="9" x2="23" y2="9"></line><line x1="20" y1="14" x2="23" y2="14"></line><line x1="1" y1="9" x2="4" y2="9"></line><line x1="1" y1="14" x2="4" y2="14"></line></svg>',
    'Cybersecurity' => '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path></svg>',
    'Mobile App Development' => '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="5" y="2" width="14" height="20" rx="2" ry="2"></rect><line x1="12" y1="18" x2="12.01" y2="18"></line></svg>',
    'Soft Skills' => '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>',
    'Business & Career Skills' => '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="7" width="20" height="14" rx="2" ry="2"></rect><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"></path></svg>',
    'Tools & Technologies' => '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"></path></svg>'
];
$categories_list = !empty($db_categories) ? $db_categories : array_keys($categories_icons);
$default_resource_icon = '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Instructor Dashboard | JU Learn</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/dashboard.css">
    <link rel="stylesheet" href="assets/css/landing_extra.css">
    <style>
        :root {
            --accent-green: #00e599;
            --accent-blue: #3b82f6;
            --accent-purple: #8b5cf6;
            --bg-dark: #0a0e12;
            --bg-card: #111827;
            --glass-bg: rgba(255, 255, 255, 0.03);
            --border-light: rgba(255, 255, 255, 0.1);
        }

        body {
            background-color: var(--bg-dark);
            color: #f1f5f9;
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            overflow-x: hidden;
        }

        .text-accent { color: var(--accent-green); }
        .bg-accent { background-color: var(--accent-green) !important; }
        
        .section-view { display: none; }
        .section-view.active { display: block; animation: fadeIn 0.4s ease; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }

        .sidebar { 
            width: 250px; 
            background: rgba(10, 14, 18, 0.98); 
            border-right: 1px solid var(--border-light); 
            position: fixed; 
            height: 100vh; 
            transition: 0.3s; 
            z-index: 1000; 
            overflow-y: auto;
            overflow-x: hidden;
        }
        
        .sidebar::-webkit-scrollbar { width: 5px; }
        .sidebar::-webkit-scrollbar-track { background: transparent; }
        .sidebar::-webkit-scrollbar-thumb { background: rgba(255, 255, 255, 0.05); border-radius: 10px; }
        .sidebar::-webkit-scrollbar-thumb:hover { background: var(--accent-green); }
        .main-content { margin-left: 250px; min-height: 100vh; transition: 0.3s; }
        
        .stat-card { cursor: pointer; transition: 0.3s; }
        .stat-card:hover { transform: translateY(-5px); border-color: var(--accent-green); }
        
        .btn-link { text-decoration: none; transition: 0.2s; }
        .btn-link:hover { transform: scale(1.1); opacity: 0.8; }
        
        .toast-msg {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: var(--accent-blue);
            color: white;
            padding: 12px 24px;
            border-radius: 8px;
            box-shadow: 0 10px 20px rgba(0,0,0,0.3);
            z-index: 9999;
            transform: translateY(100px);
            transition: 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            opacity: 0;
        }
        .toast-msg.show {
            transform: translateY(0);
            opacity: 1;
        }
        
        .nav-link { 
            color: #94a3b8 !important; 
            padding: 12px 20px !important;
            border-radius: 12px !important;
            margin: 4px 15px;
            display: flex;
            align-items: center;
            gap: 12px;
            transition: 0.2s;
            border: 1px solid transparent;
            cursor: pointer;
        }

        .nav-link:hover {
            background: rgba(255,255,255,0.05);
            color: white !important;
        }

        .nav-link.active {
            background: rgba(0, 229, 153, 0.1) !important;
            color: var(--accent-green) !important;
            border-color: rgba(0, 229, 153, 0.2);
            font-weight: 600;
        }

        .content-card {
            background: var(--glass-bg);
            border: 1px solid var(--border-light);
            border-radius: 16px;
            overflow: hidden;
            transition: 0.3s;
        }
        .content-card:hover { border-color: var(--accent-green); }
        .card-preview { height: 150px; object-fit: cover; width: 100%; background: #1e293b; border-bottom: 1px solid var(--border-light); }

        .btn-neon {
            background: var(--accent-green);
            color: #0d1117;
            font-weight: 700;
            border: none;
            padding: 10px 24px;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0, 229, 153, 0.2);
            transition: 0.3s;
        }
        .btn-neon:hover { transform: scale(1.02); box-shadow: 0 6px 20px rgba(0, 229, 153, 0.4); }

        .modal-content { background: #1e293b; border: 1px solid var(--border-light); border-radius: 20px; }
        .form-control, .form-select { background: rgba(0,0,0,0.2); border: 1px solid var(--border-light); color: white; border-radius: 10px; padding: 12px; }
        .form-control:focus, .form-select:focus { background: rgba(0,0,0,0.3); border-color: var(--accent-green); box-shadow: none; color: white; }

        @media (max-width: 992px) {
            .sidebar { left: -250px; }
            .sidebar.mobile-open { left: 0; }
            .main-content { margin-left: 0; }
            .dash-mobile-topbar { display: flex !important; }
        }
    </style>
</head>
<body class="dashboard-body">

    <?php if (empty($instructor_category)): ?>
    <!-- Modal: CATEGORY SELECTION (Forced) -->
    <div class="modal fade show" id="categoryModal" tabindex="-1" style="display: block; background: rgba(0,0,0,0.8);" data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header border-bottom border-secondary">
                    <h5 class="modal-title text-accent">Select Your Expertise</h5>
                </div>
                <div class="modal-body p-4">
                    <p class="text-secondary small mb-4">Welcome to JU Learn! Please select your primary teaching category. You will only be able to upload courses within this category.</p>
                    <form id="setCategoryForm" onsubmit="handleSetCategory(event)">
                        <input type="hidden" name="action" value="set_category">
                        <div class="mb-4">
                            <label class="form-label text-secondary small">Primary Category</label>
                            <select name="category" class="form-select" required>
                                <option value="" disabled selected>Select a category...</option>
                                <?php foreach($categories_list as $cat): ?>
                                    <option value="<?= $cat ?>"><?= $cat ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-neon w-100" id="btn-submit-category">Set Category</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Mobile Dashboard Topbar (hidden on desktop) -->
    <div class="dash-mobile-topbar" id="dashMobileTopbar">
        <div class="dash-mobile-brand">
            <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="var(--accent-green)" stroke-width="2">
                <path d="M22 10v6M2 10l10-5 10 5-10 5z"></path>
                <path d="M6 12v5c3 3 9 3 12 0v-5"></path>
            </svg>
            JU<span class="text-accent">Learn</span>
        </div>
        <button class="dash-hamburger" id="instHamburger" aria-label="Open sidebar" aria-expanded="false">
            <span></span><span></span><span></span>
        </button>
    </div>

    <!-- Sidebar overlay (mobile) -->
    <div class="dash-sidebar-overlay" id="instSidebarOverlay"></div>

    <!-- Global Navigation: Role-based Sidebar -->
    <aside class="sidebar" id="instSidebar">
        <div class="p-4 border-bottom border-secondary" style="border-bottom-color: var(--border-light) !important;">
            <div class="d-flex align-items-center gap-2">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="var(--accent-green)" stroke-width="2">
                    <path d="M22 10v6M2 10l10-5 10 5-10 5z"></path>
                    <path d="M6 12v5c3 3 9 3 12 0v-5"></path>
                </svg>
                <strong style="font-size: 1.2rem; letter-spacing: -0.5px; color: white;">JU<span style="color: var(--accent-green);">Learn</span></strong>
            </div>
            <div class="mt-2 small text-uppercase" style="color: white;"> Instructor Studio</div>
        </div>

        <ul class="nav flex-column px-3 gap-1 mt-4">
            <li class="nav-item">
                <a class="sidebar-nav-item active" data-section="overview" onclick="showSection('overview', this)" style="cursor:pointer">
                    <svg width="18" height="18" class="me-2" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><line x1="3" y1="9" x2="21" y2="9"></line><line x1="9" y1="21" x2="9" y2="9"></line></svg> Overview
                </a>
            </li>
        </ul>

        <div class="px-3 mt-4 mb-2 small text-uppercase fw-bold " style="letter-spacing: 1px; color:white">Content Management</div>
        <ul class="nav flex-column px-3 gap-1">
            <li class="nav-item">
                <a class="sidebar-nav-item" data-section="my-content" onclick="showSection('my-content', this)" style="cursor:pointer">
                    <svg width="18" height="18" class="me-2" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"></path><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"></path></svg> My Content
                </a>
            </li>
            <li class="nav-item">
                <a class="sidebar-nav-item" data-section="students" onclick="showSection('students', this)" style="cursor:pointer">
                    <svg width="18" height="18" class="me-2" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="7" r="4"></circle><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path></svg> Student
                </a>
            </li>
        </ul>

        <div class="px-3 mt-4 mb-2 small text-uppercase fw-bold " style="letter-spacing: 1px; color:white">Interaction</div>
        <ul class="nav flex-column px-3 gap-1">
            <li class="nav-item">
                <a class="sidebar-nav-item" data-section="feedback" onclick="showSection('feedback', this)" style="cursor:pointer">
                    <svg width="18" height="18" class="me-2" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg> Feedback Center
                </a>
            </li>
        </ul>

        <div class="px-3 mt-4 mb-2 small text-uppercase fw-bold " style="letter-spacing: 1px; color:white">System</div>
        <ul class="nav flex-column px-3 gap-1">
            <li class="nav-item">
                <a class="sidebar-nav-item" data-section="settings" onclick="showSection('settings', this)" style="cursor:pointer">
                    <svg width="18" height="18" class="me-2" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9h.09a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path></svg> Studio Settings
                </a>
            </li>
        </ul>

        <div class="mt-auto p-4 border-top border-secondary" style="border-top-color: var(--border-light) !important;">
            <a href="api/logout.php" class="btn btn-outline-danger w-100 btn-sm rounded-pill font-monospace" style="border-color: rgba(220,53,69,0.5);">Logout</a>
        </div>
    </aside>

    <main class="main-content p-4 p-md-5">
        <div class="d-flex justify-content-between align-items-center mb-5">
            <div>
                <h1 class="h2 fw-bold text-white mb-1" id="page-title">Dashboard Overview</h1>
                <p class="text-secondary mb-0" id="page-desc">Knowledge is the only asset that grows when shared.</p>
            </div>
            <button class="btn btn-neon" data-bs-toggle="modal" data-bs-target="#createModal">
                + Create Content
            </button>
        </div>

        <!-- Sections -->
        <div id="sections-container">
            
            <!-- OVERVIEW -->
            <section id="overview" class="section-view active">
                <div class="row g-4 mb-5">
                    <div class="col-md-4">
                        <div class="stat-card glass-panel p-4 animate-up" onclick="showSection('my-content', document.querySelector('[data-section=\'my-content\']'))" style="background: rgba(0, 229, 153, 0.05); border: 1px solid rgba(0, 229, 153, 0.2);">
                            <div class="text-secondary text-uppercase small fw-bold mb-2" style="letter-spacing: 1px;">Total Modules</div>
                            <div class="display-6 fw-bold text-white"><?= $stats['total_content'] ?></div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="stat-card glass-panel p-4 animate-up" style="animation-delay: 0.1s; background: rgba(0, 229, 153, 0.05); border: 1px solid rgba(0, 229, 153, 0.2);" onclick="showSection('students', document.querySelector('[data-section=\'students\']'))">
                            <div class="text-secondary text-uppercase small fw-bold mb-2" style="letter-spacing: 1px;">Active Students</div>
                            <div class="display-6 fw-bold text-white"><?= $stats['total_students'] ?></div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="stat-card glass-panel p-4 animate-up" style="animation-delay: 0.2s; border-color: var(--accent-green);" onclick="showSection('feedback', document.querySelector('[data-section=\'feedback\']'))">
                            <div class="text-secondary text-uppercase small fw-bold mb-2" style="letter-spacing: 1px;">Student Feedback</div>
                            <div class="display-6 fw-bold text-accent"><?= $stats['pending_feedback'] ?></div>
                        </div>
                    </div>
                </div>

                <div class="glass-panel p-4">
                    <div class="mb-4">
                        <h5 class="mb-0">Growth Center</h5>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <div class="p-4 rounded-4 border border-secondary bg-opacity-10 bg-white h-100 transition-03 hover-border-warning text-center">
                                <div class="avatar-md bg-warning bg-opacity-10 text-warning rounded-circle d-flex align-items-center justify-content-center mx-auto mb-3" style="width: 50px; height: 50px;">
                                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M13 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"></path><polyline points="13 2 13 9 20 9"></polyline></svg>
                                </div>
                                <h3 class="fw-bold mb-1 text-white"><?= $stats['weekly_new_students'] ?></h3>
                                <p class="text-secondary small mb-0">Students Joined This Week</p>
                                <div class="mt-2 small text-warning">+<?= $stats['weekly_new_students'] > 0 ? round(($stats['weekly_new_students'] / max(1, $stats['total_students'])) * 100, 1) : 0 ?>% Increase</div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="p-4 rounded-4 border border-secondary bg-opacity-10 bg-white h-100 transition-03 hover-border-primary text-center">
                                <div class="avatar-md bg-primary bg-opacity-10 text-primary rounded-circle d-flex align-items-center justify-content-center mx-auto mb-3" style="width: 50px; height: 50px;">
                                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"></path><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"></path></svg>
                                </div>
                                <h3 class="fw-bold mb-1 text-white"><?= $stats['weekly_courses'] ?></h3>
                                <p class="text-secondary small mb-0">New Courses This Week</p>
                                <div class="mt-2 small text-primary">Academic Curriculum</div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="p-4 rounded-4 border border-secondary bg-opacity-10 bg-white h-100 transition-03 hover-border-warning text-center">
                                <div class="avatar-md bg-warning bg-opacity-10 text-warning rounded-circle d-flex align-items-center justify-content-center mx-auto mb-3" style="width: 50px; height: 50px;">
                                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M13 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"></path><polyline points="13 2 13 9 20 9"></polyline></svg>
                                </div>
                                <h3 class="fw-bold mb-1 text-white"><?= $stats['weekly_resources'] ?></h3>
                                <p class="text-secondary small mb-0">New Resources This Week</p>
                                <div class="mt-2 small text-warning">Supporting Materials</div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- MY CONTENT -->
            <section id="my-content" class="section-view">
                <?php if (empty($myCourses) && empty($myResources)): ?>
                    <div class="col-12 text-center py-5 glass-panel border-dashed">
                        <p class="text-secondary h5">No content found. Start by creating one!</p>
                    </div>
                <?php else: ?>
                    
                    <?php if (!empty($myCourses)): ?>
                        <div class="d-flex align-items-center gap-3 mb-4">
                            <h5 class="mb-0 text-accent">My Courses</h5>
                            <div class="flex-grow-1 border-bottom border-light opacity-10"></div>
                        </div>
                        <div class="row g-4 mb-5">
                            <?php foreach ($myCourses as $c): ?>
                            <div class="col-md-6 col-xl-4">
                                <div class="content-card h-100 position-relative" style="transition: transform 0.2s, box-shadow 0.2s;">
                                    <a href="view_content.php?id=<?= $c['id'] ?>&type=course&role=instructor" class="stretched-link"></a>
                                    <img src="<?= $c['thumbnail_path'] ?: 'assets/img/course-placeholder.jpg' ?>" class="card-preview" alt="">
                                    <div class="p-4 position-relative z-1">
                                        <div class="d-flex gap-2 mb-3">
                                            <?php if ($c['is_approved']): ?>
                                                <span class="badge bg-primary">Course</span>
                                            <?php else: ?>
                                                <span class="badge bg-warning text-dark">Pending Approval</span>
                                            <?php endif; ?>
                                            <span class="badge border border-secondary text-secondary"><?= htmlspecialchars($c['level']) ?></span>
                                        </div>
                                        <h5 class="text-white mb-3 text-truncate"><?= htmlspecialchars($c['title']) ?></h5>
                                        <div class="d-flex gap-2 pt-3 border-top border-secondary position-relative" style="z-index: 5;">
                                            <button class="btn btn-sm btn-outline-success flex-grow-1" onclick="manageCurriculum(<?= $c['id'] ?>, '<?= addslashes($c['title']) ?>')">Curriculum</button>
                                            <button class="btn btn-sm btn-link text-danger p-0" onclick="deleteItem(<?= $c['id'] ?>, 'course')" title="Delete Course">
                                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($myResources)): ?>
                        <div class="d-flex align-items-center gap-3 mb-4 mt-5">
                            <h5 class="mb-0 text-warning">My Resources</h5>
                            <div class="flex-grow-1 border-bottom border-light opacity-10"></div>
                        </div>
                        <div class="skills-grid p-0 mb-5">
                            <?php foreach ($myResources as $c): 
                                $catIcon = $categories_icons[$c['category']] ?? $default_resource_icon;
                            ?>
                            <div class="skill-card m-0" style="background: var(--bg-card); border: 1px solid var(--border-light);">
                                <a href="view_content.php?id=<?= $c['id'] ?>&type=resource&role=instructor" class="stretched-link"></a>
                                <div class="skill-icon-wrap" style="background: rgba(255, 193, 7, 0.1); color: #ffc107;">
                                    <?= $catIcon ?>
                                </div>
                                <div class="skill-info flex-grow-1">
                                    <h3 class="h6 mb-1 text-white"><?= htmlspecialchars($c['title']) ?></h3>
                                    <div class="skill-category small text-secondary mb-2"><?= htmlspecialchars($c['category']) ?></div>
                                    <div class="d-flex align-items-center justify-content-between position-relative" style="z-index: 5;">
                                        <?php if ($c['is_approved']): ?>
                                            <span class="difficulty-badge" style="color: #ffc107; background: rgba(255, 193, 7, 0.1); border-color: rgba(255, 193, 7, 0.3);"><?= htmlspecialchars($c['level'] ?: 'Beginner') ?></span>
                                        <?php else: ?>
                                            <span class="badge bg-warning text-dark py-1 px-2" style="font-size: 0.7rem;">Pending Approval</span>
                                        <?php endif; ?>
                                        <div class="ms-auto d-flex gap-2">
                                            <button class="btn btn-sm btn-link text-accent p-0" onclick="openEditResource(<?= $c['id'] ?>, '<?= addslashes($c['title']) ?>', '<?= $c['level'] ?>')" title="Edit Resource">
                                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 1 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>
                                            </button>
                                            <button class="btn btn-sm btn-link text-danger p-0" onclick="deleteItem(<?= $c['id'] ?>, 'resource')" title="Delete Resource">
                                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                <?php endif; ?>
            </section>

            <!-- STUDENTS -->
            <section id="students" class="section-view">
                <div class="glass-panel p-0 overflow-hidden">
                    <table class="table table-dark table-hover mb-0">
                        <thead class="bg-black">
                            <tr>
                                <th class="ps-4">Student</th>
                                <th>Subject/Course</th>
                                <th>Email</th>
                                <th class="text-end pe-4">Applied</th>
                            </tr>
                        </thead>
                        <tbody id="student-roster-body">
                            <!-- Populated by JS -->
                            <tr><td colspan="4" class="text-center py-5 text-secondary">Loading roster...</td></tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <!-- FEEDBACK -->
            <section id="feedback" class="section-view">
                <div id="feedback-list">
                    <!-- Populated by JS -->
                    <p class="text-center py-5 text-secondary">Loading feedback...</p>
                </div>
            </section>

            <!-- SETTINGS -->
            <section id="settings" class="section-view">
                <div class="row">
                    <div class="col-lg-8">
                        <form id="settingsForm" class="glass-panel p-4" enctype="multipart/form-data">
                            <input type="hidden" name="action" value="update_settings">
                            <h5 class="mb-4">Studio Identity</h5>
                            
                            <div class="mb-4 text-center">
                                <?php if(!empty($instructor['profile_pic'])): ?>
                                    <img src="<?= htmlspecialchars($instructor['profile_pic']) ?>" class="rounded-circle shadow-lg border border-success border-opacity-25 mb-3" style="width: 120px; height: 120px; object-fit: cover;">
                                <?php endif; ?>
                                <div class="mt-2">
                                    <label class="form-label text-secondary small">Update Profile Picture</label>
                                    <input type="file" name="profile_pic" class="form-control border-secondary bg-dark text-white p-2">
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label text-secondary small">Display Name (Verified)</label>
                                <input type="text" class="form-control border-secondary bg-dark text-white-50 p-3" value="<?= htmlspecialchars($instructor['full_name']) ?>" readonly>
                            </div>

                            <div class="mb-3">
                                <label class="form-label text-secondary small">Bio / About Me</label>
                                <textarea name="bio" class="form-control border-secondary bg-dark text-white p-3" rows="4"><?= htmlspecialchars($instructor['bio']) ?></textarea>
                            </div>

                            <h6 class="mt-5 text-accent fw-bold">Security & Access</h6>
                            <div class="mb-4">
                                <label class="form-label text-secondary small">Current Password (Required for any changes)</label>
                                <div style="position:relative;">
                                    <input type="password" id="inst_current_pw" name="current_password" class="form-control border-secondary bg-dark text-white p-3" placeholder="••••••••">
                                    <span onclick="togglePassword('inst_current_pw',this)" style="position:absolute;right:14px;top:50%;transform:translateY(-50%);cursor:pointer;color:rgba(255,255,255,0.4);">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                                    </span>
                                </div>
                                <hr class="border-light border-opacity-10 my-3">
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label text-secondary small">New Password (Optional)</label>
                                    <div style="position:relative;">
                                        <input type="password" id="inst_new_pw" name="new_password" class="form-control border-secondary bg-dark text-white p-3" placeholder="••••••••">
                                        <span onclick="togglePassword('inst_new_pw',this)" style="position:absolute;right:14px;top:50%;transform:translateY(-50%);cursor:pointer;color:rgba(255,255,255,0.4);">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                                        </span>
                                    </div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label text-secondary small">Confirm New Password</label>
                                    <div style="position:relative;">
                                        <input type="password" id="inst_confirm_pw" name="confirm_password" class="form-control border-secondary bg-dark text-white p-3" placeholder="••••••••">
                                        <span onclick="togglePassword('inst_confirm_pw',this)" style="position:absolute;right:14px;top:50%;transform:translateY(-50%);cursor:pointer;color:rgba(255,255,255,0.4);">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <h6 class="mt-5 text-accent fw-bold">Socials & Professional Portfolio</h6>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label text-secondary small">LinkedIn</label>
                                    <input type="url" name="linkedin" class="form-control border-secondary bg-dark text-white p-3" value="<?= htmlspecialchars($instructor_meta['linkedin'] ?? '') ?>">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label text-secondary small">GitHub</label>
                                    <input type="url" name="github" class="form-control border-secondary bg-dark text-white p-3" value="<?= htmlspecialchars($instructor_meta['github'] ?? '') ?>">
                                </div>
                            </div>
                            <div class="mb-4">
                                <label class="form-label text-secondary small">Portfolio Site / Personal Link</label>
                                <input type="url" name="portfolio" class="form-control border-secondary bg-dark text-white p-3" value="<?= htmlspecialchars($instructor_meta['portfolio'] ?? '') ?>" placeholder="https://yourwebsite.com">
                            </div>
                            <button type="submit" class="btn-glass w-100 py-3 rounded-pill mt-3" style="color: var(--accent-green); border-color: var(--accent-green);" id="btn-save-settings">Save All Changes</button>
                        </form>

                        <!-- Expertise Expansion -->
                        <div class="glass-panel p-4 mt-4 border border-info border-opacity-25">
                            <h5 class="mb-3 text-info">Expertise Expansion</h5>
                            
                            <div class="mb-3 p-3 bg-dark bg-opacity-50 rounded border border-secondary border-opacity-50">
                                <span class="text-secondary small d-block mb-2">Approved Categories:</span>
                                <?php if (!empty($approved_categories)): ?>
                                    <div class="d-flex flex-wrap gap-2">
                                        <?php foreach($approved_categories as $ac): ?>
                                            <span class="badge bg-info bg-opacity-10 text-info border border-info border-opacity-25 px-3 py-2 rounded-pill"><?= htmlspecialchars($ac) ?></span>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <span class="text-white-50 small">No approved categories yet.</span>
                                <?php endif; ?>
                            </div>

                            <p class="small text-secondary mb-3">Request approval to teach in additional categories. Once approved by an Admin, you can create courses in these domains.</p>
                            <form id="requestCategoryForm" class="d-flex gap-2">
                                <select name="requested_category" class="form-select border-secondary bg-dark text-white" required>
                                    <option value="" disabled selected>Select new domain...</option>
                                    <?php foreach($categories_list as $cat): ?>
                                        <?php if (!in_array($cat, $approved_categories)): ?>
                                            <option value="<?= htmlspecialchars($cat) ?>"><?= htmlspecialchars($cat) ?></option>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </select>
                                <button type="submit" class="btn btn-outline-info" style="white-space: nowrap;">Request Approval</button>
                            </form>
                        </div>
                    </div>
                </div>
            </section>

        </div>
    </main>

    <!-- Modal: CREATE CONTENT (Revamped) -->
    <div class="modal fade" id="createModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header border-bottom border-secondary">
                    <h5 class="modal-title">New Learning Hub</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <!-- Step 1: Type Selection -->
                    <div id="step-1-selection">
                        <h6 class="text-center mb-4 text-secondary">What would you like to create?</h6>
                        <div class="row g-4 justify-content-center">
                            <div class="col-md-5">
                                <button type="button" class="btn btn-outline-primary w-100 py-5 d-flex flex-column align-items-center" onclick="showCreateForm('course')">
                                    <span class="display-4 mb-3">🎓</span>
                                    <span class="h5 fw-bold">Multi-Lesson Course</span>
                                    <span class="small text-white-50 mt-2">Build a structured curriculum</span>
                                </button>
                            </div>
                            <div class="col-md-5">
                                <button type="button" class="btn btn-outline-warning w-100 py-5 d-flex flex-column align-items-center" onclick="showCreateForm('resource')">
                                    <span class="display-4 mb-3">📃</span>
                                    <span class="h5 fw-bold">Standalone Resource</span>
                                    <span class="small text-white-50 mt-2">Upload a quick file or document</span>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Step 2: Course Form -->
                    <form id="createCourseForm" style="display: none;" method="post" enctype="multipart/form-data" onsubmit="handleCreateCourse(event)">
                        <input type="hidden" name="action" value="create_course_dynamic">
                        <button type="button" class="btn btn-sm btn-outline-secondary mb-4" onclick="backToStep1()">← Back</button>
                        
                        <div class="mb-3">
                            <label class="form-label text-secondary small">Course Title</label>
                            <input type="text" name="title" class="form-control" required placeholder="e.g. Master JavaScript in 24h">
                        </div>
                        <div class="mb-3">
                            <label class="form-label text-secondary small">Summary</label>
                            <textarea name="description" class="form-control" rows="3" placeholder="Briefly describe what students will learn..."></textarea>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label text-secondary small">Category</label>
                                <?php if(count($approved_categories) > 1): ?>
                                    <select name="category" class="form-select" required>
                                        <?php foreach($approved_categories as $cat): ?>
                                            <option value="<?= htmlspecialchars($cat) ?>"><?= htmlspecialchars($cat) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="form-text text-white-50">Select from your approved domains.</div>
                                <?php else: ?>
                                    <input type="text" name="category" class="form-control" value="<?= htmlspecialchars($instructor_category) ?>" readonly required>
                                    <div class="form-text text-white-50">Locked to your profile expertise.</div>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label text-secondary small">Level</label>
                                <select name="level" class="form-select">
                                    <option>Beginner</option>
                                    <option>Intermediate</option>
                                    <option>Advanced</option>
                                </select>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label text-secondary small">Thumbnail Image</label>
                            <input type="file" name="thumbnail" class="form-control" accept="image/*">
                        </div>
                        
                        <hr class="border-secondary my-4">
                        <h6 class="text-accent mb-3">Curriculum (Add Lessons)</h6>
                        <div id="dynamic-progress_lessons-container">
                            <!-- Lesson rows injected here -->
                        </div>
                        <button type="button" class="btn btn-outline-success btn-sm mb-4" onclick="addLessonRow()">+ Add Lesson</button>

                        <div class="modal-footer border-0 px-0 pb-0">
                            <button type="submit" class="btn btn-neon w-100" id="btn-submit-course">Publish Course</button>
                        </div>
                    </form>

                    <!-- Step 2: Resource Form -->
                    <form id="createResourceForm" style="display: none;" method="post" enctype="multipart/form-data" onsubmit="handleCreateResource(event)">
                        <input type="hidden" name="action" value="create_resource_dynamic">
                        <button type="button" class="btn btn-sm btn-outline-secondary mb-4" onclick="backToStep1()">← Back</button>

                        <div class="mb-3">
                            <label class="form-label text-secondary small">Resource Title</label>
                            <input type="text" name="title" class="form-control" required placeholder="e.g. Python Cheat Sheet">
                        </div>
                        <div class="mb-3">
                            <label class="form-label text-secondary small">Category</label>
                            <?php if(count($approved_categories) > 1): ?>
                                <select name="category" class="form-select" required>
                                    <?php foreach($approved_categories as $cat): ?>
                                        <option value="<?= htmlspecialchars($cat) ?>"><?= htmlspecialchars($cat) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="form-text text-white-50">Select from your approved domains.</div>
                            <?php else: ?>
                                <input type="text" name="category" class="form-control" value="<?= htmlspecialchars($instructor_category) ?>" readonly required>
                                <div class="form-text text-white-50">Locked to your profile expertise.</div>
                            <?php endif; ?>
                        </div>
                        <div class="mb-3">
                            <label class="form-label text-secondary small">Difficulty Level</label>
                            <select name="level" class="form-select">
                                <option>Beginner</option>
                                <option>Intermediate</option>
                                <option>Advanced</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label text-secondary small">Upload Files (Multiple)</label>
                            <input type="file" name="resource_files[]" class="form-control" accept="*/*" multiple required>
                            <div class="form-text text-white-50">All file types are supported. Automatic detection enabled.</div>
                        </div>

                        <div class="modal-footer border-0 px-0 pb-0">
                            <button type="submit" class="btn btn-neon w-100" id="btn-submit-resource">Publish Resource</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal: EDIT RESOURCE -->
    <div class="modal fade" id="editResourceModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header border-bottom border-secondary">
                    <h5 class="modal-title">Edit Resource Hub</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form id="editResourceForm" onsubmit="handleUpdateResource(event)">
                    <div class="modal-body p-4">
                        <input type="hidden" name="action" value="update_resource">
                        <input type="hidden" name="resource_id" id="edit-res-id">
                        
                        <div class="mb-3">
                            <label class="form-label text-secondary small">Resource Title</label>
                            <input type="text" name="title" id="edit-res-title" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label text-secondary small">Difficulty Level</label>
                            <select name="level" id="edit-res-level" class="form-select">
                                <option>Beginner</option>
                                <option>Intermediate</option>
                                <option>Advanced</option>
                            </select>
                        </div>
                        <hr class="border-secondary my-4">
                        <h6 class="text-accent mb-3">Manage Collection</h6>
                        <div class="mb-3">
                            <label class="form-label text-secondary small">Add New Files</label>
                            <input type="file" name="resource_files[]" class="form-control" accept="*/*" multiple>
                            <div class="form-text text-white-50">New files will be merged into the existing collection.</div>
                        </div>
                        
                        <div id="edit-res-files-list" class="mt-4">
                            <!-- Files populated by JS -->
                        </div>
                    </div>
                    <div class="modal-footer border-0 p-4 pt-0">
                        <button type="submit" class="btn btn-neon w-100" id="btn-update-res">Save Resource Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal: CURRICULUM -->
    <div class="modal fade" id="curriculumModal" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="curTitle">Lesson Management</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="row">
                        <div class="col-lg-4">
                            <form id="lessonForm" class="p-3 bg-dark rounded border border-secondary mb-4">
                                <input type="hidden" name="action" value="add_lesson">
                                <input type="hidden" name="course_id" id="target_course_id">
                                <h6 class="text-accent mb-4">Add Content Step</h6>
                                
                                <div class="mb-3">
                                    <label class="form-label small">Step Title</label>
                                    <input type="text" name="title" class="form-control" required placeholder="1. Introduction">
                                </div>
                                <input type="hidden" name="type" id="l_typeSelector" value="pdf">
                                <div id="l_fileWrap" class="mb-3">
                                    <label class="form-label small">File Uploads</label>
                                    <div id="l_selectedFilesList" class="mb-2 d-flex flex-column gap-2"></div>
                                    <button type="button" class="btn btn-sm btn-outline-light w-100 py-2 rounded-3 border-dashed" onclick="document.getElementById('l_fileInput_hidden').click()">
                                        <span class="me-1">+</span> Choose File
                                    </button>
                                    <input type="file" id="l_fileInput_hidden" class="d-none" multiple onchange="handleCumulativeFiles(this, 'l_selectedFilesList', 'l_typeSelector')">
                                    <div class="form-text text-white-50">You can choose multiple files one after another.</div>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label small">Context / Video Link / Text Body</label>
                                    <textarea name="content_url" class="form-control" rows="4" placeholder="YouTube link, instructions, or article content..."></textarea>
                                </div>
                                <button type="submit" class="btn btn-success w-100">Add Lesson</button>
                            </form>
                        </div>
                        <div class="col-lg-8">
                            <h6 class="mb-4 d-flex justify-content-between">
                                Curriculum Hierarchy
                                <span class="badge bg-secondary" id="lesson-count">0 Lessons</span>
                            </h6>
                            <div id="progress_lessons-list" class="pe-2" style="max-height: 500px; overflow-y: auto;">
                                <!-- Dynamic AJAX -->
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Navigation Logic
        function showSection(targetId, el) {
            document.querySelectorAll('.section-view').forEach(s => s.classList.remove('active'));
            document.getElementById(targetId).classList.add('active');
            
            document.querySelectorAll('.sidebar-nav-item').forEach(l => l.classList.remove('active'));
            if(el) el.classList.add('active');
            else document.querySelector(`[data-section="${targetId}"]`)?.classList.add('active');

            const titles = {
                'overview': 'Dashboard Overview',
                'my-content': 'My Content',
                'students': 'Student Roster',
                'feedback': 'Feedback Center',
                'settings': 'Studio Settings'
            };
            const descs = {
                'overview': 'Welcome back to your teaching command center.',
                'my-content': 'Your knowledge library. Manage and organize your content_courses.',
                'students': 'Tracking every active learner across your modules.',
                'feedback': 'Real-time student queries and course ratings.',
                'settings': 'Manage your personal identity and socials.'
            };
            document.getElementById('page-title').innerText = titles[targetId];
            document.getElementById('page-desc').innerText = descs[targetId];

            if(targetId === 'students') loadRoster();
            if(targetId === 'feedback') loadFeedback();

            // Mobile sidebar close on click
            if(window.innerWidth < 992) {
                document.getElementById('instSidebar').classList.remove('mobile-open');
                document.getElementById('instSidebarOverlay').classList.remove('open');
            }
        }

        async function handleSetCategory(e) {
            e.preventDefault();
            const btn = document.getElementById('btn-submit-category');
            btn.disabled = true;
            btn.innerText = 'Saving...';
            
            const formData = new FormData(e.target);
            try {
                const res = await fetch('api/instructor_v2_actions.php', { method: 'POST', body: formData });
                const data = await res.json();
                if(data.success) {
                    location.reload();
                } else {
                    showToast('Error: ' + data.message);
                    btn.disabled = false;
                    btn.innerText = 'Set Category';
                }
            } catch(err) {
                showToast('Server Error.');
                btn.disabled = false;
                btn.innerText = 'Set Category';
            }
        }

        function toggleLessonFields() {
            const type = document.getElementById('l_typeSelector').value;
            const wrap = document.getElementById('l_fileWrap');
            if(type === 'text') wrap.classList.add('d-none');
            else wrap.classList.remove('d-none');
        }

        async function manageCurriculum(courseId, title) {
            document.getElementById('target_course_id').value = courseId;
            document.getElementById('curTitle').innerText = "Curriculum: " + title;
            await loadLessons(courseId);
            new bootstrap.Modal('#curriculumModal').show();
        }

        async function loadLessons(courseId) {
            const list = document.getElementById('progress_lessons-list');
            list.innerHTML = '<div class="text-center py-5"><div class="spinner-border text-success"></div></div>';
            
            try {
                const res = await fetch(`api/instructor_v2_actions.php?action=get_progress_lessons&course_id=${courseId}`);
                
                // First check if response is OK
                if (!res.ok) {
                    const text = await res.text();
                    throw new Error(`Server returned ${res.status}: ${text.substring(0, 100)}`);
                }

                const data = await res.json();

                // Handle API error object
                if (!Array.isArray(data)) {
                    list.innerHTML = `<div class="text-center text-danger py-4">Error: ${data.error || data.message || 'Unknown error'}</div>`;
                    return;
                }

                if (data.length === 0) {
                    list.innerHTML = '<div class="text-center text-secondary py-5">No progress_lessons added yet.</div>';
                    document.getElementById('lesson-count').innerText = "0 Lessons";
                    return;
                }

                document.getElementById('lesson-count').innerText = data.length + " Lessons";
                list.innerHTML = data.map((l, i) => `
                    <div class="lesson-step p-4 mb-3 bg-dark bg-opacity-50 rounded-4 border border-secondary shadow-sm">
                        <div class="d-flex justify-content-between align-items-center">
                            <div style="flex:1; min-width:0;">
                                <div class="small text-uppercase fw-bold mb-1" style="color:#00e599; letter-spacing: 0.5px;">
                                    Lesson ${l.order_number || (i+1)} &nbsp;·&nbsp; ${(l.type||'').toUpperCase()}
                                </div>
                                <h6 class="mb-0 text-white text-truncate fw-bold">${l.title}</h6>
                                ${l.content ? `<p class="small text-secondary mt-2 mb-0 text-truncate-2">${l.content}</p>` : ''}
                                
                                <div class="mt-3 d-flex flex-wrap gap-2">
                                    ${(l.resources || []).map(r => `
                                        <a href="${r.file_path}" target="_blank" class="btn btn-xs btn-outline-secondary py-1 px-2 d-flex align-items-center gap-1" style="font-size:0.7rem;">
                                            <span>${r.type === 'video' ? '🎬' : r.type === 'pdf' ? '📄' : r.type === 'image' ? '🖼️' : '📎'}</span>
                                            <span class="text-truncate" style="max-width: 100px;">${r.title}</span>
                                            ${r.file_size ? `<span class="opacity-50 ms-1" style="font-size: 0.6rem;">(${r.file_size})</span>` : ''}
                                        </a>
                                    `).join('')}
                                </div>
                            </div>
                            <button class="btn btn-sm btn-outline-danger ms-3 rounded-circle" onclick="deleteLesson(${l.id})" title="Delete lesson">🗑️</button>
                        </div>
                    </div>
                `).join('');
            } catch (e) {
                list.innerHTML = `<div class="text-danger p-3 text-center">
                    <div class="mb-2">⚠️ Error loading progress_lessons</div>
                    <div class="small opacity-75">${e.message}</div>
                </div>`;
                console.error('Curriculum Load Error:', e);
            }
        }

        async function loadRoster() {
            const body = document.getElementById('student-roster-body');
            body.innerHTML = '<tr><td colspan="4" class="text-center py-5">Loading...</td></tr>';
            try {
                const res = await fetch('api/instructor_v2_actions.php?action=get_roster');
                const data = await res.json();
                if (data.length === 0) {
                    body.innerHTML = '<tr><td colspan="4" class="text-center py-5 text-secondary">No students enrolled yet.</td></tr>';
                    return;
                }
                body.innerHTML = data.map(s => `
                    <tr class="align-middle">
                        <td class="ps-4">
                            <div class="d-flex align-items-center gap-3">
                                <div class="avatar-sm bg-primary bg-opacity-10 text-primary rounded-circle d-flex align-items-center justify-content-center fw-bold" style="width:32px; height:32px;">
                                    ${s.student_name.charAt(0)}
                                </div>
                                <span class="fw-bold">${s.student_name}</span>
                            </div>
                        </td>
                        <td><span class="text-accent small">${s.course_title}</span></td>
                        <td><span class="text-secondary small">${s.email}</span></td>
                        <td class="text-end pe-4 small text-secondary">${new Date(s.enrolled_at).toLocaleDateString()}</td>
                    </tr>
                `).join('');
            } catch (e) {
                body.innerHTML = '<tr><td colspan="4" class="text-center py-3 text-danger">Error fetching roster.</td></tr>';
            }
        }

        function unenrollStudent(studentId, courseId, studentName) {
            showConfirm(`Are you sure you want to remove ${studentName} from this course?`, async () => {
                const formData = new FormData();
                formData.append('action', 'unenroll_student');
                formData.append('student_id', studentId);
                formData.append('course_id', courseId);
                
                try {
                    const res = await fetch('api/instructor_v2_actions.php', { method: 'POST', body: formData });
                    const data = await res.json();
                    
                    if(data.success) {
                        showToast('Student removed successfully.', 'success');
                        loadRoster();
                    } else {
                        showToast('Error: ' + data.message, 'error');
                    }
                } catch(e) {
                    showToast('Connection error.', 'error');
                }
            });
        }

        async function loadFeedback() {
            const list = document.getElementById('feedback-list');
            list.innerHTML = '<div class="text-center py-5">Loading...</div>';
            try {
                const res = await fetch('api/instructor_v2_actions.php?action=get_feedback');
                const data = await res.json();
                if (data.length === 0) {
                    list.innerHTML = '<p class="text-center py-5 text-secondary h6">No feedback received yet.</p>';
                    return;
                }
                list.innerHTML = data.map(f => {
                    const stars = '★'.repeat(f.rating) + '☆'.repeat(5 - f.rating);
                    return `
                        <div class="glass-panel p-4 mb-3 border border-secondary shadow-sm">
                            <div class="d-flex justify-content-between mb-3">
                                <div>
                                    <h6 class="text-white mb-1">
                                        <span class="text-accent">${f.student_name}</span> rated <span class="text-info">${f.course_title}</span>
                                    </h6>
                                    <div class="mb-2" style="color: #ffc107; letter-spacing: 2px;">${stars}</div>
                                    <div class="small text-secondary">${new Date(f.created_at).toLocaleString()}</div>
                                </div>
                                ${!f.reply ? '<span class="badge bg-warning text-dark align-self-start">Pending</span>' : '<span class="badge bg-success align-self-start">Replied</span>'}
                            </div>
                            <div class="p-3 bg-dark bg-opacity-50 rounded italic border-start border-accent border-4 mb-3">
                                "${f.comment || 'No written review provided.'}"
                            </div>
                            ${f.reply ? `
                                <div class="ms-4 p-3 border-start border-secondary mb-2">
                                    <div class="small fw-bold text-accent mb-1">Your Reply:</div>
                                    <div class="text-secondary small">${f.reply}</div>
                                </div>
                            ` : `
                                <button class="btn btn-sm btn-outline-accent" onclick="openReplyModal(${f.id}, '${(f.comment || '').replace(/'/g, "\\'")}')">
                                    ${f.reply ? 'Update Reply' : 'Optional Reply'}
                                </button>
                            `}
                        </div>
                    `;
                }).join('');
            } catch (e) {
                list.innerHTML = '<p class="text-center py-5 text-danger">Error fetching feedback.</p>';
            }
        }

        // Form Handlers
        async function submitAjax(formId) {
            const form = document.getElementById(formId);
            if (!form) return;
            form.addEventListener('submit', async (e) => {
                e.preventDefault();
                const btn = e.target.querySelector('button[type="submit"]');
                if (!btn) return;
                const originalText = btn.innerText;
                btn.disabled = true;
                btn.innerText = 'Saving...';

                try {
                    const formData = new FormData(form);
                    const res = await fetch('api/instructor_v2_actions.php', { method: 'POST', body: formData });
                    
                    let data;
                    const rawText = await res.text();
                    try {
                        data = JSON.parse(rawText);
                    } catch(jsonErr) {
                        console.error('Non-JSON response:', rawText);
                        const lowerText = rawText.toLowerCase();
                        if (lowerText.includes('post content-length exceeded') || lowerText.includes('exceeds the limit')) {
                            showToast('The file you are trying to upload is too large for the server. Please try a smaller file or contact admin to increase the upload limit.');
                        } else {
                            showToast('Server Error: Malformed response.\n\nResponse snippet: ' + rawText.substring(0, 200));
                        }
                        btn.disabled = false; btn.innerText = originalText;
                        return;
                    }

                    if (data.success) {
                        if (formId === 'lessonForm') {
                            form.reset();
                            // Clear cumulative files for single lesson
                            if (fileManagers['single_lesson']) {
                                fileManagers['single_lesson'] = new DataTransfer();
                            }
                            const list = document.getElementById('l_selectedFilesList');
                            if (list) list.innerHTML = '';
                            const actualInput = document.getElementById('actual_input_single_lesson');
                            if (actualInput) actualInput.remove();

                            const courseId = document.getElementById('target_course_id').value;
                            await loadLessons(courseId);
                        } else {
                            showToast('Saved successfully!');
                            location.reload();
                        }
                    } else {
                        showToast('Error: ' + data.message);
                    }
                } catch (err) {
                    console.error('Submit Error:', err);
                    showToast('System Error: ' + err.message + '\nPlease check your network or server logs.');
                }
                btn.disabled = false;
                btn.innerText = originalText;
            });
        }

        submitAjax('lessonForm');
        submitAjax('settingsForm');

        // Multi-Step Modal Logic
        function showCreateForm(type) {
            document.getElementById('step-1-selection').style.display = 'none';
            if(type === 'course') {
                document.getElementById('createCourseForm').style.display = 'block';
                if(document.querySelectorAll('.dynamic-lesson-row').length === 0) addLessonRow();
            } else {
                document.getElementById('createResourceForm').style.display = 'block';
            }
        }

        function backToStep1() {
            document.getElementById('createCourseForm').style.display = 'none';
            document.getElementById('createResourceForm').style.display = 'none';
            document.getElementById('step-1-selection').style.display = 'block';
        }

        let lessonCount = 0;
        function addLessonRow() {
            lessonCount++;
            const container = document.getElementById('dynamic-progress_lessons-container');
            const row = document.createElement('div');
            row.className = 'dynamic-lesson-row bg-dark p-3 rounded mb-3 border border-secondary';
            row.innerHTML = `
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="badge bg-primary">Lesson ${lessonCount}</span>
                    <button type="button" class="btn-close btn-close-white" onclick="this.closest('.dynamic-lesson-row').remove()"></button>
                </div>
                <div class="row g-2">
                    <div class="col-md-6 mb-2">
                        <label class="small text-secondary">Title</label>
                        <input type="text" name="progress_lessons[${lessonCount}][title]" class="form-control form-control-sm" required>
                        <input type="hidden" name="progress_lessons[${lessonCount}][type]" value="pdf">
                    </div>
                    <div class="col-12 mb-2 file-wrap-${lessonCount}">
                        <label class="small text-secondary">Attach Files</label>
                        <div id="fileList_${lessonCount}" class="mb-2 d-flex flex-column gap-2"></div>
                        <button type="button" class="btn btn-xs btn-outline-secondary w-100 py-1" onclick="document.getElementById('fileInput_${lessonCount}').click()">+ Add File</button>
                        <input type="file" id="fileInput_${lessonCount}" class="d-none" multiple onchange="handleCumulativeFiles(this, 'fileList_${lessonCount}', null, ${lessonCount})">
                    </div>
                    <div class="col-12 mb-2">
                        <label class="small text-secondary">External Link/Text Content</label>
                        <textarea name="progress_lessons[${lessonCount}][content]" class="form-control form-control-sm" rows="1" placeholder="Optional external URL or full text"></textarea>
                    </div>
                </div>
            `;
            container.appendChild(row);
        }

        function toggleLessonFile(selectEl, idx) {
            const fileWrap = document.querySelector('.file-wrap-' + idx);
            if(selectEl.value === 'text') fileWrap.style.display = 'none';
            else fileWrap.style.display = 'block';
        }

        const fileManagers = {}; // Store DataTransfer objects by ID

        function handleCumulativeFiles(input, listId, typeSelectorId, lessonIdx = null) {
            if (!input.files || input.files.length === 0) return;
            
            const managerId = lessonIdx !== null ? `lesson_${lessonIdx}` : 'single_lesson';
            if (!fileManagers[managerId]) fileManagers[managerId] = new DataTransfer();
            
            const manager = fileManagers[managerId];
            const list = document.getElementById(listId);
            
            Array.from(input.files).forEach(file => {
                manager.items.add(file);
                
                const ext = file.name.split('.').pop().toLowerCase();
                const type = detectTypeFromExt(ext);
                const icon = type === 'video' ? '🎬' : (type === 'pdf' ? '📄' : (type === 'doc' ? '📎' : '📁'));
                
                const item = document.createElement('div');
                item.className = 'p-2 bg-dark bg-opacity-50 rounded border border-secondary d-flex justify-content-between align-items-center animate-fade-in';
                item.innerHTML = `
                    <div class="d-flex align-items-center gap-2">
                        <span class="small">${icon}</span>
                        <div class="small text-truncate" style="max-width: 150px;">${file.name}</div>
                        <span class="badge bg-secondary" style="font-size: 0.6rem;">${type.toUpperCase()}</span>
                    </div>
                    <button type="button" class="btn-close btn-close-white" style="font-size: 0.5rem;" onclick="removeFileFromManager('${managerId}', '${file.name}', this)"></button>
                `;
                list.appendChild(item);
            });

            // Update the hidden input that will be sent with the form
            const targetInputName = lessonIdx !== null ? `lesson_files_${lessonIdx}[]` : 'files[]';
            
            // We'll create or update a hidden input in the form to carry the aggregated files
            let actualInput = document.getElementById(`actual_input_${managerId}`);
            if (!actualInput) {
                actualInput = document.createElement('input');
                actualInput.type = 'file';
                actualInput.name = targetInputName;
                actualInput.multiple = true;
                actualInput.id = `actual_input_${managerId}`;
                actualInput.className = 'd-none';
                input.form.appendChild(actualInput);
            }
            actualInput.files = manager.files;

            // Auto-detect type for the FIRST file if a selector is provided
            if (manager.files.length > 0) {
                const firstExt = manager.files[0].name.split('.').pop().toLowerCase();
                const detected = detectTypeFromExt(firstExt);
                
                if (typeSelectorId) {
                    const selector = document.getElementById(typeSelectorId);
                    if (selector) {
                        selector.value = (detected === 'doc' ? 'pdf' : detected); // Map doc to pdf/document for UI
                        if (typeof toggleLessonFields === 'function') toggleLessonFields();
                    }
                } else if (lessonIdx !== null) {
                    const selector = document.querySelector(`select[name="progress_lessons[${lessonIdx}][type]"]`);
                    if (selector) {
                        selector.value = (detected === 'doc' ? 'pdf' : (detected === 'video' ? 'video' : 'pdf'));
                        toggleLessonFile(selector, lessonIdx);
                    }
                }
            }
            
            input.value = ''; // Reset the chooser so it can trigger on the same file again
        }

        function removeFileFromManager(managerId, fileName, btnEl) {
            const manager = fileManagers[managerId];
            if (!manager) return;
            
            const newDataTransfer = new DataTransfer();
            Array.from(manager.files).forEach(file => {
                if (file.name !== fileName) newDataTransfer.items.add(file);
            });
            fileManagers[managerId] = newDataTransfer;
            
            // Update the actual input
            const actualInput = document.getElementById(`actual_input_${managerId}`);
            if (actualInput) actualInput.files = newDataTransfer.files;
            
            // Remove from UI
            btnEl.closest('div').remove();
        }

        function detectTypeFromExt(ext) {
            const videos = ['mp4', 'webm', 'mkv', 'avi', 'mov'];
            const pdfs = ['pdf'];
            const docs = ['doc', 'docx', 'ppt', 'pptx', 'xls', 'xlsx', 'txt'];
            if (videos.includes(ext)) return 'video';
            if (pdfs.includes(ext)) return 'pdf';
            if (docs.includes(ext)) return 'doc';
            return 'file';
        }

        function autoDetectType(input, idx) {
            if (!input.files || input.files.length === 0) return;
            const file = input.files[0];
            const ext = file.name.split('.').pop().toLowerCase();
            const typeSelect = document.querySelector(`select[name="progress_lessons[${idx}][type]"]`);
            
            const videoExts = ['mp4', 'webm', 'mkv', 'avi', 'mov'];
            const pdfExts = ['pdf'];
            const docExts = ['doc', 'docx', 'ppt', 'pptx', 'xls', 'xlsx', 'txt'];
            
            let detected = 'text';
            if (videoExts.includes(ext)) detected = 'video';
            else if (pdfExts.includes(ext)) detected = 'pdf';
            else if (docExts.includes(ext)) detected = 'doc';
            
            if (typeSelect) {
                typeSelect.value = detected;
                toggleLessonFile(typeSelect, idx);
            }
        }

        function autoDetectSingleLesson(input) {
            if (!input.files || input.files.length === 0) return;
            const file = input.files[0];
            const ext = file.name.split('.').pop().toLowerCase();
            const typeSelect = document.getElementById('l_typeSelector');
            
            const videoExts = ['mp4', 'webm', 'mkv', 'avi', 'mov'];
            const pdfExts = ['pdf'];
            const docExts = ['doc', 'docx', 'ppt', 'pptx', 'xls', 'xlsx', 'txt'];
            
            let detected = 'text';
            if (videoExts.includes(ext)) detected = 'video';
            else if (pdfExts.includes(ext)) detected = 'pdf';
            else if (docExts.includes(ext)) detected = 'doc';
            
            if (typeSelect) {
                typeSelect.value = detected;
                toggleLessonFields();
            }
        }

        async function handleCreateCourse(e) {
            e.preventDefault();
            const form = e.target;
            const btn = document.getElementById('btn-submit-course');
            const originalText = btn.innerText;
            btn.disabled = true;
            btn.innerText = 'Creating Course...';

            try {
                const formData = new FormData(form);
                const res = await fetch('api/instructor_v2_actions.php', { method: 'POST', body: formData });
                
                let data;
                const rawText = await res.text();
                try {
                    data = JSON.parse(rawText);
                } catch(jsonErr) {
                    console.error('Non-JSON response:', rawText);
                    const lowerText = rawText.toLowerCase();
                    if (lowerText.includes('post content-length exceeded') || lowerText.includes('exceeds the limit')) {
                        showToast('Your course data is too large for the server. Try uploading smaller files or contact admin to increase limits.');
                    } else {
                        showToast('Server Error: Malformed response.\n\nResponse snippet: ' + rawText.substring(0, 200));
                    }
                    btn.disabled = false; btn.innerText = originalText;
                    return;
                }

                if (data.success) {
                    window.location.href = 'view_content.php?id=' + data.course_id + '&type=course';
                } else {
                    showToast('Error: ' + data.message);
                    btn.disabled = false;
                    btn.innerText = originalText;
                }
            } catch (err) {
                console.error(err);
                showToast('System Error. Please check your connection.');
                btn.disabled = false; btn.innerText = originalText;
            }
        }

        async function handleCreateResource(e) {
            e.preventDefault();
            const form = e.target;
            const btn = document.getElementById('btn-submit-resource');
            const originalText = btn.innerText;
            btn.disabled = true;
            btn.innerText = 'Publishing Resource...';

            try {
                const formData = new FormData(form);
                const res = await fetch('api/instructor_v2_actions.php', { method: 'POST', body: formData });
                
                let data;
                const rawText = await res.text();
                try {
                    data = JSON.parse(rawText);
                } catch(jsonErr) {
                    console.error('Non-JSON response:', rawText);
                    if (rawText.toLowerCase().includes('post content-length exceeded')) {
                        showToast('The resource file is too large for the server. Please try a smaller file.');
                    } else {
                        showToast('Server Error: Management API returned an invalid response. Check console.');
                    }
                    btn.disabled = false; btn.innerText = originalText;
                    return;
                }

                if (data.success) {
                    window.location.href = 'view_content.php?id=' + data.resource_id + '&type=resource';
                } else {
                    showToast('Error: ' + data.message);
                    btn.disabled = false;
                    btn.innerText = originalText;
                }
            } catch (err) {
                console.error(err);
                showToast('System Error. Please check your connection.');
                btn.disabled = false; btn.innerText = originalText;
            }
        }


        function openReplyModal(id, message) {
            document.getElementById('reply_feedback_id').value = id;
            document.getElementById('feedback_orig_text').innerText = message;
            new bootstrap.Modal('#replyModal').show();
        }

        async function deleteItem(id, type) {
            showConfirm('Are you sure? This will remove the content and all its related materials.', () => {
                const formData = new FormData();
                formData.append('action', 'delete_content');
                formData.append('id', id);
                formData.append('type', type);
                fetch('api/instructor_v2_actions.php', { method: 'POST', body: formData })
                .then(res => res.json())
                .then(data => {
                    if(data.success) location.reload();
                    else showToast('Error: ' + data.message, 'error');
                });
            });
        }

        async function deleteLesson(id) {
            showConfirm('Are you sure you want to delete this lesson?', () => {
                const formData = new FormData();
                formData.append('action', 'delete_lesson');
                formData.append('id', id);
                fetch('api/instructor_v2_actions.php', { method: 'POST', body: formData })
                .then(res => res.json())
                .then(data => {
                    if(data.success) {
                        const courseId = document.getElementById('target_course_id').value;
                        loadLessons(courseId);
                    }
                    else showToast('Error: ' + data.message, 'error');
                });
            });
        }

        // Mobile Sidebar Control
        const hamburger = document.getElementById('instHamburger');
        const sidebar   = document.getElementById('instSidebar');
        const overlay   = document.getElementById('instSidebarOverlay');
        
        hamburger.addEventListener('click', () => {
            sidebar.classList.toggle('mobile-open');
            overlay.classList.toggle('open');
        });
        overlay.addEventListener('click', () => {
            sidebar.classList.remove('mobile-open');
            overlay.classList.remove('open');
        });
    </script>
    
    <!-- Modal: Custom Confirm -->
    <div class="modal fade" id="confirmModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered modal-sm">
            <div class="modal-content border-danger border-opacity-50" style="background: var(--bg-card);">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title text-danger">Confirm Action</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-center pt-2 pb-4">
                    <p id="confirmModalMsg" class="text-white-50 mb-4">Are you sure?</p>
                    <div class="d-flex justify-content-center gap-3">
                        <button type="button" class="btn btn-outline-secondary px-4" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-danger px-4" id="confirmModalBtn">Confirm</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- Modal: Reply to Feedback -->
    <div class="modal fade" id="replyModal" tabindex="-1">
        <div class="modal-dialog">
            <form id="replyForm" class="modal-content">
                <input type="hidden" name="action" value="reply_feedback">
                <input type="hidden" name="feedback_id" id="reply_feedback_id">
                <div class="modal-header">
                    <h5 class="modal-title">Reply to Feedback</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="p-3 bg-dark bg-opacity-50 rounded mb-3 small text-secondary">
                        <strong class="text-white">Query:</strong> <span id="feedback_orig_text"></span>
                    </div>
                    <label class="form-label small text-secondary">Your Response</label>
                    <textarea name="reply" class="form-control" rows="5" required placeholder="Write your helpful response here..."></textarea>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-accent w-100">Send Response</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Wire up Reply Form
        const replyForm = document.getElementById('replyForm');
        replyForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const btn = e.target.querySelector('button[type="submit"]');
            btn.disabled = true;
            btn.innerText = 'Sending...';
            
            try {
                const formData = new FormData(e.target);
                const res = await fetch('api/instructor_v2_actions.php', { method: 'POST', body: formData });
                const data = await res.json();
                if(data.success) {
                    bootstrap.Modal.getInstance(document.getElementById('replyModal')).hide();
                    loadFeedback();
                } else {
                    showToast('Error: ' + data.message);
                }
            } catch(e) {
                showToast('Connection failure.');
            }
            btn.disabled = false;
            btn.innerText = 'Send Response';
        });

        // Settings Handler
        document.getElementById('settingsForm').onsubmit = async (e) => {
            e.preventDefault();
            const btn = document.getElementById('btn-save-settings');
            btn.disabled = true;
            btn.innerText = 'Baking changes...';
            
            try {
                const formData = new FormData(e.target);
                const res = await fetch('api/instructor_v2_actions.php', { method: 'POST', body: formData });
                const data = await res.json();
                if(data.success) {
                    showToast('Profile updated successfully!', 'success');
                    location.reload();
                } else {
                    showToast('Error: ' + data.message, 'error');
                }
            } catch(err) {
                showToast('Update failed. Check connection.', 'error');
            }
            btn.disabled = false;
            btn.innerText = 'Save All Changes';
        };

        // Category Request Form Handler
        const requestCatForm = document.getElementById('requestCategoryForm');
        if (requestCatForm) {
            requestCatForm.onsubmit = async (e) => {
                e.preventDefault();
                const btn = e.target.querySelector('button');
                const orig = btn.innerText;
                btn.disabled = true; btn.innerText = 'Sending...';
                
                const formData = new FormData(e.target);
                formData.append('action', 'request_new_category');
                
                try {
                    const res = await fetch('api/instructor_v2_actions.php', { method: 'POST', body: formData });
                    const data = await res.json();
                    if(data.success) {
                        showToast('Category approval request sent to Admin!', 'success');
                        e.target.reset();
                    } else {
                        showToast('Error: ' + data.message, 'error');
                    }
                } catch(err) {
                    showToast('Connection error.', 'error');
                }
                btn.disabled = false; btn.innerText = orig;
            };
        }

        function deleteAccount() {
            showConfirm('ARE YOU ABSOLUTELY SURE? This will permanently remove your instructor account and all access. This is IRREVERSIBLE.', () => {
                const formData = new FormData();
                formData.append('action', 'delete_instructor_account');
                fetch('api/instructor_v2_actions.php', { method: 'POST', body: formData })
                .then(res => res.json())
                .then(data => {
                    if(data.success) window.location.href = 'login.php?msg=account_deleted';
                    else showToast('Error: ' + data.message, 'error');
                });
            });
        }

        function showToast(msg, type = 'info') {
            const toast = document.createElement('div');
            toast.className = 'toast-msg';
            if(type === 'error') {
                toast.style.background = '#dc3545';
                toast.style.color = 'white';
            } else if(type === 'success') {
                toast.style.background = 'var(--accent-green)';
                toast.style.color = '#0d1117';
            }
            toast.innerText = msg;
            document.body.appendChild(toast);
            setTimeout(() => { toast.classList.add('show'); }, 10);
            setTimeout(() => {
                toast.classList.remove('show');
                setTimeout(() => toast.remove(), 300);
            }, 3000);
        }

        function showConfirm(msg, onConfirm) {
            document.getElementById('confirmModalMsg').innerText = msg;
            const btn = document.getElementById('confirmModalBtn');
            const newBtn = btn.cloneNode(true);
            btn.parentNode.replaceChild(newBtn, btn);
            
            const modalInstance = new bootstrap.Modal(document.getElementById('confirmModal'));
            newBtn.addEventListener('click', () => {
                modalInstance.hide();
                onConfirm();
            });
            modalInstance.show();
        }

        // --- Resource Editing ---
        const editResModal = new bootstrap.Modal(document.getElementById('editResourceModal'));
        
        async function openEditResource(id, title, level) {
            document.getElementById('edit-res-id').value = id;
            document.getElementById('edit-res-title').value = title;
            document.getElementById('edit-res-level').value = level;
            
            // Fetch current files
            const listContainer = document.getElementById('edit-res-files-list');
            listContainer.innerHTML = '<p class="text-center text-secondary small">Fetching files...</p>';
            
            editResModal.show();
            
            try {
                const res = await fetch(`api/instructor_v2_actions.php?action=get_resource_files&resource_id=${id}`);
                const data = await res.json();
                
                if(data.success) {
                    if(data.files.length === 0) {
                        listContainer.innerHTML = '<p class="text-center text-secondary small italic">No files in this collection.</p>';
                    } else {
                        listContainer.innerHTML = '<h6 class="small text-secondary mb-3">Existing Files:</h6>';
                        data.files.forEach(f => {
                            const div = document.createElement('div');
                            div.className = 'd-flex align-items-center justify-content-between p-2 mb-2 rounded bg-white bg-opacity-5 border border-light';
                            div.innerHTML = `
                                <div class="text-truncate small me-3" style="max-width: 200px;">
                                    <span class="me-2">${getFileIcon(f.type)}</span>
                                    ${f.title}
                                </div>
                                <button type="button" class="btn btn-xs btn-outline-danger" onclick="deleteResourceFile(this, ${f.id})">🗑️</button>
                            `;
                            listContainer.appendChild(div);
                        });
                    }
                }
            } catch(e) {
                listContainer.innerHTML = '<p class="text-danger small">Failed to load files.</p>';
            }
        }

        function getFileIcon(type) {
            switch(type) {
                case 'video': return '🎬';
                case 'pdf': return '📕';
                case 'image': return '🖼️';
                default: return '📄';
            }
        }

        async function deleteResourceFile(btn, fileId) {
            showConfirm('Delete this file from the collection?', async () => {
            try {
                const formData = new FormData();
                formData.append('action', 'delete_resource_file');
                formData.append('file_id', fileId);
                
                const res = await fetch('api/instructor_v2_actions.php', { method: 'POST', body: formData });
                const data = await res.json();
                
                if(data.success) {
                    btn.closest('div').remove();
                } else {
                    showToast(data.message, 'error');
                }
            } catch(e) {
                showToast('Connection error.', 'error');
            }
            });
        }

        async function handleUpdateResource(e) {
            e.preventDefault();
            const btn = document.getElementById('btn-update-res');
            const originalText = btn.innerText;
            btn.innerText = 'Updating...';
            btn.disabled = true;
            
            try {
                const formData = new FormData(e.target);
                const res = await fetch('api/instructor_v2_actions.php', { method: 'POST', body: formData });
                const data = await res.json();
                
                if(data.success) {
                    showToast('Resource updated successfully!');
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showToast('Server Error: ' + data.message);
                }
            } catch(e) {
                console.error(e);
                showToast('Connection error: ' + e.message);
            } finally {
                btn.innerText = originalText;
                btn.disabled = false;
            }
        }

        async function handleCategoryRequest(e) {
            e.preventDefault();
            const btn = document.getElementById('btn-request-cat');
            const originalText = btn.innerText;
            btn.innerText = 'Sending...';
            btn.disabled = true;

            try {
                const formData = new FormData(e.target);
                formData.append('action', 'request_new_category');
                const res = await fetch('api/instructor_v2_actions.php', { method: 'POST', body: formData });
                const data = await res.json();

                if(data.success) {
                    showToast('Request sent to admin!');
                    e.target.reset();
                } else {
                    showToast(data.message);
                }
            } catch(e) {
                showToast('Connection error.');
            } finally {
                btn.innerText = originalText;
                btn.disabled = false;
            }
        }

        function togglePassword(id, el) {
            const input = document.getElementById(id);
            const isPass = input.type === 'password';
            input.type = isPass ? 'text' : 'password';
            el.innerHTML = isPass
                ? '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line></svg>'
                : '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>';
        }
    </script>

</body>
</html>
