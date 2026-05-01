<?php
ob_start();
require_once 'api/session_helper.php';
require_once 'api/db.php';
require_once 'api/certificate_engine.php';

// Detect requested role to prioritize session
$requested_role = $_GET['role'] ?? null;
$roles_to_check = ['admin', 'instructor', 'student'];

if ($requested_role && in_array($requested_role, $roles_to_check)) {
    // Put requested role at the front of the queue
    $roles_to_check = array_diff($roles_to_check, [$requested_role]);
    array_unshift($roles_to_check, $requested_role);
}

$viewer_role = null;
$roles_to_check = ['admin', 'instructor', 'student'];

// Prioritize the role passed in the URL if it exists
if ($requested_role && in_array($requested_role, $roles_to_check)) {
    $roles_to_check = array_diff($roles_to_check, [$requested_role]);
    array_unshift($roles_to_check, $requested_role);
}

foreach ($roles_to_check as $role) {
    $sname = 'JU_' . strtoupper($role) . '_SESS';
    // Only attempt to start the session if the cookie for this role exists
    if (isset($_COOKIE[$sname])) {
        start_role_session($role);
        if (isset($_SESSION['user_id'])) {
            $viewer_role = $role;
            break;
        }
        session_write_close();
    }
}

// Fallback: If no cookie-based session found, try starting a default student session 
// just in case session_start was already called or we need to check the standard way
if (!$viewer_role) {
    start_role_session();
    if (isset($_SESSION['user_id'])) {
        $viewer_role = $_SESSION['role'] ?? 'student';
    }
}

if (!$viewer_role) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$id = $_GET['id'] ?? '';
$type = $_GET['type'] ?? 'course';
$lid  = $_GET['lid'] ?? ''; // target lesson id
$fid  = $_GET['fid'] ?? ''; // target file id for resources

if (empty($id)) {
    die("Course ID missing.");
}

$content = null;
$progress_lessons = [];
$active_lesson = null;

// Fetch Course Data
if ($type === 'course') {
    $stmt = $pdo->prepare("SELECT c.*, u.full_name as instructor_name, u.profile_pic as instructor_pic 
        FROM (SELECT id, user_id AS creator_id, title, description, file_path AS thumbnail_path, category, generic_value AS level, meta_text AS tags, is_resource, is_deleted, deleted_at, created_at, is_approved FROM content WHERE record_type = 'course') c 
        JOIN users u ON c.creator_id = u.id
        WHERE c.id = ?");
    $stmt->execute([$id]);
    $content = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($content) {
        $lstmt = $pdo->prepare("SELECT * FROM progress WHERE record_type = 'lesson' AND course_id = ? ORDER BY order_number ASC");
        $lstmt->execute([$content['id']]);
        $progress_lessons = $lstmt->fetchAll(PDO::FETCH_ASSOC);
        
        if ($lid !== '') {
            foreach ($progress_lessons as $l) {
                if ($l['id'] == $lid) { $active_lesson = $l; break; }
            }
        }
        if (!$active_lesson && count($progress_lessons) > 0) {
            $active_lesson = $progress_lessons[0];
        }

        // Fetch resources for the active lesson
        if ($active_lesson) {
            $rstmt = $pdo->prepare("SELECT id, title, file_path, generic_value as type, order_number FROM progress WHERE record_type = 'lesson_resource' AND item_id = ? ORDER BY order_number ASC");
            $rstmt->execute([$active_lesson['id']]);
            $active_lesson['resources'] = $rstmt->fetchAll(PDO::FETCH_ASSOC);
        }
    }
} else {
    // Shared resource viewing
    $stmt = $pdo->prepare("SELECT r.*, u.full_name as instructor_name, u.profile_pic as instructor_pic
        FROM (SELECT id, user_id AS creator_id, title, file_path, generic_value AS file_type, category, is_deleted, deleted_at, created_at, is_approved FROM content WHERE record_type = 'resource') r
        JOIN users u ON r.creator_id = u.id
        WHERE r.id = ?");
    $stmt->execute([$id]);
    $content = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($content) {
        $rstmt = $pdo->prepare("SELECT id, title, file_path, generic_value as type FROM content WHERE record_type = 'resource_file' AND parent_id = ? ORDER BY id ASC");
        $rstmt->execute([$content['id']]);
        $content['files'] = $rstmt->fetchAll(PDO::FETCH_ASSOC);

        if ($fid !== '') {
            foreach ($content['files'] as $f) {
                if ($f['id'] == $fid) { $active_file = $f; break; }
            }
        }
        if (!isset($active_file) && count($content['files']) > 0) {
            $active_file = $content['files'][0];
        }
    }
}

if (!$content) {
    die("Content not found.");
}

// Security: Approval Check
if (isset($content['is_approved']) && $content['is_approved'] == 0) {
    $is_owner = ($viewer_role === 'instructor' && $_SESSION['user_id'] == $content['creator_id']);
    $is_admin = ($viewer_role === 'admin');
    
    if (!$is_owner && !$is_admin) {
        die("<h3>Content Pending Approval</h3><p>This course or resource is currently being reviewed by administrators and is not yet available for public viewing.</p><a href='index.php'>Back to Home</a>");
    }
}

// Check Lesson Completion Status
$is_completed = false;
if ($active_lesson) {
    $cstmt = $pdo->prepare("SELECT metric_value AS status FROM progress WHERE record_type = 'lesson_progress' AND user_id = ? AND item_id = ?");
    $cstmt->execute([$user_id, $active_lesson['id']]);
    $prog = $cstmt->fetch();
    $is_completed = ($prog && $prog['status'] === 'completed');
}

// --- NEW: COURSE COMPLETION LOGIC ---
$course_completed = false;
if ($type === 'course' && !empty($progress_lessons)) {
    $total_progress_lessons = count($progress_lessons);
    $comp_stmt = $pdo->prepare("SELECT COUNT(*) FROM progress WHERE record_type = 'lesson_progress' AND user_id = ? AND metric_value = 'completed' AND item_id IN (SELECT id FROM progress WHERE record_type = 'lesson' AND course_id = ?)");
    $comp_stmt->execute([$user_id, $id]);
    $completed_count = $comp_stmt->fetchColumn();
    
    if ($completed_count >= $total_progress_lessons) {
        $course_completed = true;
    }
}

// Check if feedback already provided
$feedback_exists = false;
if ($course_completed) {
    // Automatically issue certificate if not already done
    issue_course_certificate($user_id, $id);

    $fcheck = $pdo->prepare("SELECT id FROM notifications WHERE type = 'feedback' AND JSON_EXTRACT(meta, '$.course_id') = ? AND JSON_EXTRACT(meta, '$.student_id') = ?");
    $fcheck->execute([$id, $user_id]);
    $feedback_exists = $fcheck->fetch();
}

// Fetch all feedback for this course
$all_reviews = [];
if ($type === 'course') {
    $rstmt = $pdo->prepare("
        SELECT f.*, u.full_name as student_name 
        FROM (
            SELECT 
                JSON_UNQUOTE(JSON_EXTRACT(meta, '$.course_id')) AS course_id, 
                JSON_UNQUOTE(JSON_EXTRACT(meta, '$.student_id')) AS student_id, 
                JSON_UNQUOTE(JSON_EXTRACT(meta, '$.rating')) AS rating, 
                message AS comment, 
                JSON_UNQUOTE(JSON_EXTRACT(meta, '$.reply')) AS reply, 
                created_at 
            FROM notifications WHERE type = 'feedback'
        ) f
        JOIN users u ON f.student_id = u.id
        WHERE f.course_id = ?
        ORDER BY f.created_at DESC
    ");
    $rstmt->execute([$id]);
    $all_reviews = $rstmt->fetchAll(PDO::FETCH_ASSOC);
}

// Handle Mark Complete
if (isset($_POST['mark_complete']) && $active_lesson) {
    $upd = $pdo->prepare("UPDATE progress SET metric_value = 'completed', last_activity_at = NOW() WHERE record_type = 'lesson_progress' AND user_id = ? AND item_id = ?");
    $upd->execute([$user_id, $active_lesson['id']]);
    if ($upd->rowCount() === 0) {
        $stmt = $pdo->prepare("INSERT INTO progress (record_type, user_id, item_id, metric_value) VALUES ('lesson_progress', ?, ?, 'completed')");
        $stmt->execute([$user_id, $active_lesson['id']]);
    }
    
    header("Location: student_viewer.php?id=$id&type=$type&lid=" . $active_lesson['id'] . "&role=$viewer_role");
    exit;
}

// Handle Feedback Submission
if (isset($_POST['submit_feedback']) && $course_completed) {
    $rating = (int)$_POST['rating'];
    $comment = trim($_POST['comment'] ?? '');
    
    try {
        $stmt = $pdo->prepare("INSERT INTO notifications (type, user_id, target_role, message, meta) VALUES ('feedback', (SELECT user_id FROM content WHERE id = ?), 'instructor', ?, JSON_OBJECT('course_id', ?, 'student_id', ?, 'rating', ?, 'reply', null))");
        $stmt->execute([$id, $comment, $id, $user_id, $rating]);
        header("Location: student_viewer.php?id=$id&type=$type&feedback=success&role=$viewer_role");
        exit;
    } catch (PDOException $e) {
        header("Location: student_viewer.php?id=$id&type=$type&feedback=error&role=$viewer_role");
        exit;
    }
}

// Intercept PRG Statuses
$feedback_success = (isset($_GET['feedback']) && $_GET['feedback'] === 'success') ? "Thank you for your feedback!" : null;
$feedback_error = (isset($_GET['feedback']) && $_GET['feedback'] === 'error') ? "Submission failed: Already submitted or error occurred." : null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($content['title']) ?> | JU Student</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/dashboard.css">
    <link rel="manifest" href="manifest.json">
    <style>
        :root {
            --accent-green: #00e599;
            --accent-blue: #3b82f6;
            --bg-dark: #0a0e12;
            --glass-bg: rgba(255, 255, 255, 0.03);
            --border-light: rgba(255, 255, 255, 0.1);
        }

        body {
            background-color: var(--bg-dark);
            color: #f1f5f9;
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            overflow: hidden;
            height: 100vh;
        }

        .lms-wrapper {
            display: flex;
            height: 100vh;
            width: 100vw;
        }

        .lms-sidebar {
            width: 320px;
            background: rgba(10, 14, 18, 0.98);
            border-right: 1px solid var(--border-light);
            display: flex;
            flex-direction: column;
            flex-shrink: 0;
            z-index: 100;
        }

        .sidebar-branding-unified {
            padding: 24px;
            border-bottom: 1px solid var(--border-light);
        }

        .sidebar-curriculum {
            flex-grow: 1;
            overflow-y: auto;
        }

        .curriculum-header {
            padding: 20px 24px 10px;
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            color: rgba(255,255,255,0.3);
            font-weight: 700;
        }

        .lesson-item {
            display: flex;
            padding: 16px 24px;
            color: #94a3b8;
            text-decoration: none !important;
            border-bottom: 1px solid rgba(255, 255, 255, 0.02);
            transition: 0.2s;
            gap: 12px;
        }
        .lesson-item:hover { background: rgba(255, 255, 255, 0.03); color: white; }
        .lesson-item.active {
            background: rgba(0, 229, 153, 0.08);
            color: var(--accent-green);
            border-left: 3px solid var(--accent-green);
        }

        .lms-main {
            flex-grow: 1;
            display: flex;
            flex-direction: column;
            min-width: 0;
            overflow-y: auto;
            position: relative;
        }

        .top-navbar {
            height: 70px;
            background: rgba(10, 14, 18, 0.8);
            backdrop-filter: blur(10px);
            border-bottom: 1px solid var(--border-light);
            display: flex;
            align-items: center;
            padding: 0 30px;
            justify-content: space-between;
            position: sticky;
            top: 0;
            z-index: 10;
        }

        .player-viewport {
            padding: 40px;
            max-width: 1200px;
            margin: 0 auto;
            width: 100%;
        }

        .glass-container {
            background: var(--glass-bg);
            border: 1px solid var(--border-light);
            border-radius: 24px;
            backdrop-filter: blur(20px);
            overflow: hidden;
            margin-bottom: 40px;
        }

        .media-viewer {
            background: #000;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            min-height: 480px;
        }

        video, .image-view { width: 100%; max-height: 75vh; }

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

        .btn-action {
            background: rgba(255,255,255,0.05);
            border: 1px solid var(--border-light);
            color: white;
            padding: 8px 20px;
            border-radius: 10px;
            font-size: 0.85rem;
            text-decoration: none;
            transition: 0.2s;
        }
        .btn-action:hover { border-color: var(--accent-green); color: var(--accent-green); }

        /* Success Label Animation */
        .completed-badge {
            background: rgba(0, 229, 153, 0.1);
            color: var(--accent-green);
            border: 1px solid var(--accent-green);
            animation: pulseSuccess 2s infinite;
        }
        @keyframes pulseSuccess {
            0% { transform: scale(1); box-shadow: 0 0 0 0 rgba(0, 229, 153, 0.4); }
            70% { transform: scale(1.05); box-shadow: 0 0 0 10px rgba(0, 229, 153, 0); }
            100% { transform: scale(1); box-shadow: 0 0 0 0 rgba(0, 229, 153, 0); }
        }

        /* ── Cache Corner Notification (matches login.php toast style) ── */
        .ju-cache-notification {
            position: fixed;
            bottom: -200px;
            right: 20px;
            z-index: 10001;
            border-radius: var(--radius-sm, 12px);
            font-size: 0.9rem;
            transition: bottom 0.45s cubic-bezier(.34,1.56,.64,1);
            box-shadow: 0 8px 30px rgba(0,0,0,0.4);
            backdrop-filter: blur(14px);
            padding: 16px 20px;
            min-width: 280px;
            max-width: 340px;
            background: rgba(0, 229, 153, 0.08);
            border: 1px solid rgba(0, 229, 153, 0.35);
            color: var(--accent-green, #00e599);
        }
        .ju-cache-notification.show   { bottom: 20px; }
        .ju-cache-notification.error  { background: rgba(255,99,132,0.08); border-color: rgba(255,99,132,0.35); color: #ffb3c1; }
        .ju-cache-notification.warning{ background: rgba(245,158,11,0.08); border-color: rgba(245,158,11,0.35); color: #fcd34d; }
        .ju-cn-header { display: flex; align-items: center; gap: 10px; font-weight: 700; margin-bottom: 8px; font-size: 0.95rem; }
        .ju-cn-bar-track { height: 4px; background: rgba(255,255,255,0.1); border-radius: 2px; overflow: hidden; margin-top: 10px; }
        .ju-cn-bar { height: 100%; background: var(--accent-green, #00e599); width: 0%; transition: width 0.28s ease; box-shadow: 0 0 8px var(--accent-green); }
        .ju-cn-sub { font-size: 0.78rem; opacity: 0.65; margin-top: 4px; }

        /* \u2500\u2500 Sync Banner & Toast \u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500 */
        .ju-sync-banner {
            position: fixed; top: 0; left: 0; right: 0; z-index: 9998;
            padding: 10px 20px; display: none;
            align-items: center; gap: 12px;
            font-size: 0.82rem; font-weight: 500;
            backdrop-filter: blur(14px);
        }
        .ju-sync-banner.state-offline { background: rgba(245,158,11,0.15); border-bottom: 1px solid rgba(245,158,11,0.3); color: #f59e0b; }
        .ju-sync-banner.state-pending { background: rgba(0,229,153,0.1); border-bottom: 1px solid rgba(0,229,153,0.3); color: #00e599; justify-content: space-between; }
        .ju-sync-banner.state-syncing { background: rgba(59,130,246,0.12); border-bottom: 1px solid rgba(59,130,246,0.3); color: #3b82f6; }
        .ju-sync-btn { background: var(--accent-green); color: #000; border: none; padding: 5px 18px; border-radius: 20px; cursor: pointer; font-weight: 700; font-size: 0.78rem; }
        .ju-spinner { width: 13px; height: 13px; border: 2px solid currentColor; border-top-color: transparent; border-radius: 50%; display: inline-block; animation: ju-spin 0.65s linear infinite; }
        @keyframes ju-spin { to { transform: rotate(360deg); } }
        .ju-toast {
            position: fixed; bottom: -100px; right: 20px; z-index: 10000;
            padding: 15px 22px; border-radius: var(--radius-sm, 12px); max-width: 340px;
            font-size: 0.9rem; font-weight: 500;
            box-shadow: 0 5px 20px rgba(0,0,0,0.35);
            backdrop-filter: blur(12px);
            transition: bottom 0.45s cubic-bezier(.34,1.56,.64,1);
        }
        .ju-toast.show    { bottom: 80px; }
        .ju-toast.type-success { background: rgba(0,229,153,0.1); border: 1px solid rgba(0,229,153,0.4); color: var(--accent-green, #00e599); }
        .ju-toast.type-error   { background: rgba(255,99,132,0.1); border: 1px solid rgba(255,99,132,0.4); color: #ffb3c1; }

        @media (max-width: 992px) {
            .lms-sidebar {
                position: fixed;
                left: -100%;
                top: 0;
                bottom: 0;
                transition: left 0.3s ease;
                z-index: 1000;
                width: 280px;
                display: flex;
            }
            .lms-sidebar.open {
                left: 0;
            }
            .player-viewport {
                padding: 15px;
            }
            .top-navbar {
                padding: 0 15px;
            }
            .lms-overlay {
                display: none;
                position: fixed;
                inset: 0;
                background: rgba(0,0,0,0.5);
                z-index: 999;
                backdrop-filter: blur(2px);
            }
            .lms-overlay.show {
                display: block;
            }
        }
    </style>
</head>
<body>

    <div class="lms-overlay" id="lmsOverlay"></div>

    <!-- ── Corner Cache Notification ─────────────────────────────────────── -->
    <div id="ju-cache-notification" class="ju-cache-notification">
        <div class="ju-cn-header">📦 <span id="ju-cn-title">Preparing Offline Mode</span></div>
        <div id="ju-cn-sub" class="ju-cn-sub">Downloading course resources...</div>
        <div class="ju-cn-bar-track"><div id="ju-cn-bar" class="ju-cn-bar"></div></div>
    </div>

    <!-- ── Sync Status Banner ─────────────────────────────────────────────── -->
    <div id="ju-sync-banner" class="ju-sync-banner" style="display:none;"></div>

    <!-- ── Toast Notification ────────────────────────────────────────────── -->
    <div id="ju-toast" class="ju-toast"></div>

    <div class="lms-wrapper">
        
        <!-- SIDEBAR -->
        <aside class="lms-sidebar">
            <div class="sidebar-branding-unified">
                <div class="d-flex align-items-center gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="var(--accent-green)" stroke-width="2">
                        <path d="M22 10v6M2 10l10-5 10 5-10 5z"></path>
                        <path d="M6 12v5c3 3 9 3 12 0v-5"></path>
                    </svg>
                    <strong style="font-size: 1.2rem; letter-spacing: -0.5px; color: white;">JU<span style="color: var(--accent-green);">Learn</span></strong>
                </div>
                <div class="mt-2 small text-uppercase fw-bold" style="color: rgba(255,255,255,0.4); font-size: 0.6rem; letter-spacing: 1px;">Player Center</div>
            </div>

            <div class="sidebar-curriculum">
                <div class="curriculum-header">Course Content</div>
                <?php if ($type === 'course'): ?>
                    <?php foreach ($progress_lessons as $index => $l): ?>
                        <a href="?id=<?= $id ?>&type=course&lid=<?= $l['id'] ?>&role=<?= $viewer_role ?>" data-lid="<?= $l['id'] ?>" class="lesson-item <?= ($active_lesson && $active_lesson['id'] == $l['id']) ? 'active' : '' ?>">
                            <div class="flex-grow-1">
                                <div class="small opacity-50 mb-1" style="font-size: 0.6rem;"><?= $index + 1 ?>. <?= strtoupper($l['type'] ?? 'Lesson') ?></div>
                                <div class="fw-bold small"><?= htmlspecialchars($l['title']) ?></div>
                            </div>
                            <?php 
                            $check = $pdo->prepare("SELECT metric_value AS status FROM progress WHERE record_type = 'lesson_progress' AND user_id = ? AND item_id = ? AND metric_value = 'completed'");
                            $check->execute([$user_id, $l['id']]);
                            if ($check->fetch()) echo "<span>✅</span>";
                            ?>
                        </a>
                    <?php endforeach; ?>
                <?php elseif ($type === 'resource'): ?>
                    <?php foreach ($content['files'] as $index => $f): ?>
                        <a href="?id=<?= $id ?>&type=resource&fid=<?= $f['id'] ?>&role=<?= $viewer_role ?>" class="lesson-item <?= (isset($active_file) && $active_file['id'] == $f['id']) ? 'active' : '' ?>">
                            <div class="fw-bold fs-6 flex-grow-1">
                                <div class="small opacity-50 mb-1" style="font-size: 0.65rem;"><?= sprintf('%02d', $index + 1) ?> • <?= strtoupper($f['type']) ?></div>
                                <?= htmlspecialchars($f['title']) ?>
                            </div>
                        </a>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            
            <div class="mt-auto p-4 border-top border-light border-opacity-10 d-flex flex-column gap-2">
                <?php
                $dashboard_link = 'dashboard.php';
                if (isset($viewer_role)) {
                    if ($viewer_role === 'admin') $dashboard_link = 'dashboards/admin.php';
                    elseif ($viewer_role === 'instructor') $dashboard_link = 'instructor_dashboard.php';
                }
                ?>
                <a href="<?= $dashboard_link ?>" class="btn btn-outline-success border-success text-success w-100 py-2 rounded-3 small text-decoration-none text-center">Dashboard</a>
            </div>
        </aside>

        <!-- MAIN -->
        <main class="lms-main">
            <header class="top-navbar">
                <div class="d-flex align-items-center gap-3">
                    <button class="btn btn-link text-white d-lg-none p-0" id="lmsHamburger" aria-label="Toggle Menu">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="3" y1="12" x2="21" y2="12"></line>
                            <line x1="3" y1="6" x2="21" y2="6"></line>
                            <line x1="3" y1="18" x2="21" y2="18"></line>
                        </svg>
                    </button>
                    <h5 class="mb-0 text-white fw-bold me-3 d-none d-md-block"><?= htmlspecialchars($content['title']) ?></h5>
                    <h5 class="mb-0 text-white fw-bold me-3 d-md-none text-truncate" style="max-width: 150px;"><?= htmlspecialchars($content['title']) ?></h5>
                    <div class="d-flex align-items-center gap-2 ps-3 border-start border-light border-opacity-10" 
                         style="cursor: pointer;" 
                         onclick="showProfile(<?= $content['creator_id'] ?>, 'creator')">
                        <?php if (!empty($content['instructor_pic'])): ?>
                            <img src="<?= htmlspecialchars($content['instructor_pic']) ?>" class="rounded-circle shadow-sm" style="width: 32px; height: 32px; object-fit: cover;">
                        <?php else: ?>
                            <div class="rounded-circle bg-success bg-opacity-20 d-flex align-items-center justify-content-center text-success fw-bold" style="width: 32px; height: 32px; font-size: 0.8rem;">
                                <?= strtoupper(substr($content['instructor_name'], 0, 1)) ?>
                            </div>
                        <?php endif; ?>
                        <div class="d-none d-md-block">
                            <div class="text-secondary small fw-bold" style="font-size: 0.65rem; text-transform: uppercase; letter-spacing: 0.5px;">Instructor</div>
                            <div class="text-white small fw-bold"><?= htmlspecialchars($content['instructor_name']) ?></div>
                        </div>
                    </div>
                </div>
                <div class="d-flex gap-3">
                    <?php if ($course_completed): ?>
                         <div class="badge bg-success bg-opacity-25 text-success border border-success p-2 px-3 rounded-pill fw-bold">🎓 COMPLETED</div>
                    <?php endif; ?>
                </div>
            </header>

            <div class="player-viewport">
                <div class="glass-container">
                    <?php if ($type === 'course' && $active_lesson): ?>
                        <!-- ── Course Lesson Mode ── -->
                        <?php if (empty($active_lesson['resources'])): ?>
                            <div class="media-viewer p-5 text-center">
                                <div class="display-3 opacity-25 mb-3">📄</div>
                                <h4 class="text-white-50 fw-bold">Lesson Instructions</h4>
                                <p class="text-secondary small">Read the content below to proceed.</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($active_lesson['resources'] as $res): ?>
                                <div class="resource-block mb-4 border-bottom border-light border-opacity-5 pb-4">
                                    <?php if (!empty($res['title'])): ?>
                                        <div class="px-4 pt-4 pb-2 text-accent small fw-bold text-uppercase" style="letter-spacing:1px;">Material: <?= htmlspecialchars($res['title']) ?></div>
                                    <?php endif; ?>
                                    
                                    <div class="media-viewer">
                                        <?php
                                        $fpath = $res['file_path'];
                                        $ext   = strtolower(pathinfo($fpath, PATHINFO_EXTENSION));
                                        $ltype = $res['type'];
                                        $is_img = in_array($ext, ['jpg','jpeg','png','webp','gif']);
                                        ?>
                                        <?php if ($ltype === 'video'): ?>
                                            <video controls class="w-100 rounded-4 shadow-lg"><source src="<?= htmlspecialchars($fpath) ?>"></video>
                                        <?php elseif ($is_img || $ltype === 'image'): ?>
                                            <img src="<?= htmlspecialchars($fpath) ?>" class="image-view rounded-4 shadow-lg" alt="Lesson Resource">
                                        <?php elseif (in_array($ext, ['pptx', 'ppt', 'docx', 'doc', 'xlsx', 'xls'])): ?>
                                            <?php 
                                            $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? "https" : "http";
                                            $host = $_SERVER['HTTP_HOST'];
                                            $is_local = (in_array($host, ['localhost', '127.0.0.1']) || strpos($host, '192.168.') === 0);
                                            
                                            $full_url = $protocol . "://" . $host . str_replace(basename($_SERVER['PHP_SELF']), '', $_SERVER['PHP_SELF']) . $fpath;
                                            $viewer_url = "https://view.officeapps.live.com/op/embed.aspx?src=" . urlencode($full_url);
                                            ?>
                                            <?php if ($is_local): ?>
                                                <div class="p-5 text-center w-100 bg-dark bg-opacity-25 rounded-4 border border-warning border-opacity-10">
                                                    <div class="display-4 mb-3">💻</div>
                                                    <h5 class="text-white">Local Development Detected</h5>
                                                    <p class="small text-secondary mb-4 mx-auto" style="max-width: 400px;">
                                                        The online document viewer requires a public URL to fetch your file. 
                                                        On <b>localhost</b>, please download the file to view it.
                                                    </p>
                                                    <a href="<?= htmlspecialchars($fpath) ?>" download class="btn btn-warning px-4 rounded-pill fw-bold text-dark">Download & View File</a>
                                                </div>
                                            <?php else: ?>
                                                <div class="ratio ratio-16x9">
                                                    <iframe src="<?= $viewer_url ?>" frameborder="0" class="rounded-4 shadow-lg" style="background: #fff;"></iframe>
                                                </div>
                                                <div class="mt-2 text-center">
                                                    <a href="<?= htmlspecialchars($fpath) ?>" download class="btn btn-sm btn-link text-accent">Download Original File</a>
                                                </div>
                                            <?php endif; ?>
                                        <?php elseif ($ltype === 'pdf' || $ext === 'pdf'): ?>
                                            <iframe src="<?= htmlspecialchars($fpath) ?>" style="width:100%; height:750px; border:none; background:#fff;" class="rounded-4 shadow-lg"></iframe>
                                        <?php else: ?>
                                            <div class="p-5 text-center w-100 bg-dark bg-opacity-25 rounded-4">
                                                <div class="display-3 opacity-25 mb-3">📂</div>
                                                <h5 class="text-white"><?= htmlspecialchars($res['title']) ?></h5>
                                                <p class="small text-secondary mb-3">Online preview not available for this file type.</p>
                                                <a href="<?= htmlspecialchars($fpath) ?>" download class="btn btn-neon px-4 rounded-pill">Download Attachment</a>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>

                    <?php elseif ($type === 'resource' && isset($active_file)): ?>
                        <!-- ── Standalone Resource Mode (Sidebar Style) ── -->
                        <div class="resource-block">
                            <div class="px-4 pt-4 pb-2 text-accent small fw-bold text-uppercase" style="letter-spacing:1px;">Resource Material: <?= htmlspecialchars($active_file['title']) ?></div>
                            <div class="media-viewer">
                                <?php
                                $fpath = $active_file['file_path'];
                                $ext   = strtolower(pathinfo($fpath, PATHINFO_EXTENSION));
                                $ltype = $active_file['type'];
                                $is_img = in_array($ext, ['jpg','jpeg','png','webp','gif']);
                                ?>
                                <?php if ($ltype === 'video'): ?>
                                    <video controls class="w-100 rounded-4 shadow-lg"><source src="<?= htmlspecialchars($fpath) ?>"></video>
                                <?php elseif ($is_img || $ltype === 'image'): ?>
                                    <img src="<?= htmlspecialchars($fpath) ?>" class="image-view rounded-4 shadow-lg" alt="Resource">
                                <?php elseif ($ltype === 'pdf' || $ext === 'pdf'): ?>
                                    <iframe src="<?= htmlspecialchars($fpath) ?>" style="width:100%; height:750px; border:none; background:#fff;" class="rounded-4 shadow-lg"></iframe>
                                <?php elseif (in_array($ext, ['pptx', 'ppt', 'docx', 'doc', 'xlsx', 'xls'])): ?>
                                    <?php 
                                    $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? "https" : "http";
                                    $full_url = $protocol . "://" . $_SERVER['HTTP_HOST'] . str_replace(basename($_SERVER['PHP_SELF']), '', $_SERVER['PHP_SELF']) . $fpath;
                                    $viewer_url = "https://view.officeapps.live.com/op/embed.aspx?src=" . urlencode($full_url);
                                    ?>
                                    <div class="ratio ratio-16x9">
                                        <iframe src="<?= $viewer_url ?>" frameborder="0" class="rounded-4 shadow-lg" style="background: #fff;"></iframe>
                                    </div>
                                <?php else: ?>
                                    <div class="p-5 text-center w-100 bg-dark bg-opacity-25 rounded-4">
                                        <div class="display-3 opacity-25 mb-3">📂</div>
                                        <h5 class="text-white"><?= htmlspecialchars($active_file['title']) ?></h5>
                                        <p class="small text-secondary mb-3">Online preview not available.</p>
                                        <a href="<?= htmlspecialchars($fpath) ?>" download class="btn btn-neon px-4 rounded-pill">Download Attachment</a>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                    <?php else: ?>
                        <!-- Welcome / Placeholder -->
                        <div class="p-5 text-center py-5">
                            <h1 class="display-6 fw-bold">Welcome to <?= htmlspecialchars($content['title']) ?></h1>
                            <p class="text-secondary">Please select a lesson from the menu to begin.</p>
                        </div>
                    <?php endif; ?>
                </div>

                <?php if ($active_lesson): ?>
                    <div class="glass-container p-5">
                        <div class="d-flex justify-content-between align-items-start mb-4">
                            <div>
                                <h2 class="fw-bold mb-1"><?= htmlspecialchars($active_lesson['title']) ?></h2>
                                <p class="text-white-50">Module <?= $active_lesson['order_number'] ?></p>
                            </div>
                            <form method="POST" id="mark-complete-form">
                                <input type="hidden" name="mark_complete" value="1">
                                <?php if ($is_completed): ?>
                                    <button type="button" class="btn btn-outline-success border-success text-success disabled rounded-pill px-4">✓ Completed</button>
                                <?php else: ?>
                                    <button type="button" id="mark-complete-btn" class="btn-neon px-4"
                                        onclick="handleMarkComplete(<?= (int)$active_lesson['id'] ?>, <?= (int)$id ?>, document.getElementById('mark-complete-form'))">
                                        Mark as Complete +50 XP
                                    </button>
                                <?php endif; ?>
                            </form>
                        </div>
                        <div class="text-secondary" style="line-height: 1.8;">
                             <?= nl2br(htmlspecialchars($active_lesson['description'] ?? 'No text description available for this lesson.')) ?>
                        </div>
                    </div>
                <?php endif; ?>

                <div id="feedback-section">
                <?php if ($course_completed && !$feedback_exists): ?>
                    <div class="glass-container p-5 border-success border-opacity-25" style="background: rgba(0, 229, 153, 0.05);">
                        <div class="text-center mb-5">
                            <div class="display-4 mb-3">🎓 Congratulations!</div>
                            <h3 class="fw-bold text-white">You've finished the entire course!</h3>
                            <p class="text-secondary">Share your thoughts with the instructor to help others learn better.</p>
                        </div>
                        <form method="POST" class="max-width-600 mx-auto" id="feedback-form"
                              onsubmit="return handleFeedbackSubmit(<?= (int)$id ?>, this, event)">
                            <div class="mb-4 text-center">
                                <label class="h5 fw-bold mb-3 d-block">Overall Rating</label>
                                <div class="btn-group w-100" role="group">
                                    <?php for($i=1; $i<=5; $i++): ?>
                                        <input type="radio" class="btn-check" name="rating" id="rate<?= $i ?>" value="<?= $i ?>" required>
                                        <label class="btn btn-outline-success py-3" for="rate<?= $i ?>"><?= $i ?> ★</label>
                                    <?php endfor; ?>
                                </div>
                            </div>
                            <div class="mb-4">
                                <label class="small text-white-50 mb-2">Lesson Feedback or Review (Optional)</label>
                                <textarea name="comment" rows="4" class="form-control bg-dark border-0 text-white p-3 rounded-4" placeholder="What did you like the most?"></textarea>
                            </div>
                            <button type="submit" name="submit_feedback" class="btn btn-neon w-100 py-3 rounded-pill fw-bold">Submit Review &amp; Claim +100 XP Bonus</button>
                        </form>
                    </div>
                <?php elseif ($feedback_exists): ?>
                    <div class="glass-container p-5 text-center">
                        <div class="text-success h4 mb-2">✓ Feedback Submitted</div>
                        <p class="text-white-50 mb-0">Your review helps improve JU Learn for everyone. Thank you for being part of the community!</p>
                    </div>
                <?php endif; ?>

                <!-- Student Reviews Section -->
                <?php if ($type === 'course' && !empty($all_reviews)): ?>
                    <div class="mt-5 mb-5">
                        <h4 class="fw-bold text-white mb-4">Student Reviews (<?= count($all_reviews) ?>)</h4>
                        <div class="row g-4">
                            <?php foreach ($all_reviews as $review): ?>
                                <div class="col-12">
                                    <div class="glass-container p-4">
                                        <div class="d-flex justify-content-between align-items-start mb-2">
                                            <div>
                                                <div class="fw-bold text-white"><?= htmlspecialchars($review['student_name']) ?></div>
                                                <div class="text-warning small">
                                                    <?= str_repeat('★', (int)$review['rating']) ?><?= str_repeat('☆', 5 - (int)$review['rating']) ?>
                                                </div>
                                            </div>
                                            <div class="text-white-50 small"><?= date('M d, Y', strtotime($review['created_at'])) ?></div>
                                        </div>
                                        <p class="text-secondary mb-0">"<?= htmlspecialchars($review['comment'] ?: 'No written review.') ?>"</p>
                                        
                                        <?php if (!empty($review['reply'])): ?>
                                            <div class="mt-3 p-3 rounded-4" style="background: rgba(0, 229, 153, 0.05); border-left: 3px solid var(--accent-green);">
                                                <div class="small fw-bold text-accent mb-1">Instructor Response:</div>
                                                <div class="text-white-50 small">"<?= htmlspecialchars($review['reply']) ?>"</div>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
                </div>

            </div>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

    <!-- ── Offline Course Data Injection ─────────────────────────────── -->
    <script>
    const COURSE_ID = <?= (int)$id ?>;
    const ACTIVE_LESSON_ID = <?= $active_lesson ? (int)$active_lesson['id'] : 0 ?>;
    const JUST_ENROLLED = <?= isset($_GET['enrolled']) && $_GET['enrolled'] === '1' ? 'true' : 'false' ?>;
    const COURSE_CACHE_URLS = <?php
        $cache_urls = [];
        if ($type === 'course') {
            foreach ($progress_lessons as $l) {
                // Cache the lesson viewer page
                $cache_urls[] = "student_viewer.php?id={$id}&type=course&lid={$l['id']}";
                // Cache the media file if it exists and is a real URL
                if (!empty($l['file_path'])) {
                    $cache_urls[] = $l['file_path'];
                }
            }
        }
        echo json_encode(array_values(array_unique($cache_urls)));
    ?>;
    </script>

    <!-- ── Offline Manager ───────────────────────────────────────────── -->
    <script src="assets/js/offline_manager.js"></script>

    <!-- ── Service Worker Registration ──────────────────────────────── -->
    <script>
    if ('serviceWorker' in navigator) {
        navigator.serviceWorker
            .register('/lastfyp/service_worker.js', { scope: '/lastfyp/' })
            .then(reg => console.log('[SW] Registered, scope:', reg.scope))
            .catch(err => console.warn('[SW] Registration failed:', err));
    }
    </script>


    <!-- Profile Detail Modal (Matches Admin Style) -->
    <div class="modal fade" id="profileModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content glass-panel border-light" style="background: rgba(10, 11, 16, 0.95); backdrop-filter: blur(20px);">
                <div class="modal-header border-bottom border-light border-opacity-10">
                    <h5 class="modal-title fw-bold text-white" id="modalTitle">Profile Details</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="modalBody" style="color: #f1f5f9;">
                    <div class="text-center p-4">
                        <div class="spinner-border text-success" role="status"></div>
                    </div>
                </div>
                <div class="modal-footer border-top border-light border-opacity-10">
                    <button type="button" class="btn btn-secondary rounded-pill px-4" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <style>
        .profile-label { font-size: 0.7rem; text-transform: uppercase; letter-spacing: 1px; color: rgba(255,255,255,0.4); margin-bottom: 4px; margin-top: 15px; font-weight: 700; }
        .profile-value { color: #fff; font-weight: 500; }
        .badge-glass { background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); padding: 4px 10px; border-radius: 6px; font-size: 0.8rem; display: inline-block; }
        .social-link { background: var(--accent-green); color: #000; padding: 4px 12px; border-radius: 20px; text-decoration: none; font-size: 0.75rem; font-weight: 700; transition: 0.2s; }
        .social-link:hover { opacity: 0.8; transform: translateY(-2px); }
    </style>

    <script>
        function showProfile(id, role) {
            const modal = new bootstrap.Modal(document.getElementById('profileModal'));
            modal.show();
            document.getElementById('modalBody').innerHTML = '<div class="text-center p-5"><div class="spinner-border text-success" role="status"></div></div>';

            fetch('api/admin_actions.php?get_profile=' + id + '&role=' + role)
                .then(r => r.json())
                .then(data => {
                    if (!data.success) {
                        document.getElementById('modalBody').innerHTML = `<div class="alert alert-danger">${data.message}</div>`;
                        return;
                    }
                    
                    data = data.data; // Extract the actual profile object
                    
                    let html = '';
                    if (role !== 'course') {
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

                    if (role === 'creator') {
                        html += `
                            <div class="profile-label">Teaching Category</div>
                            <div class="profile-value"><span class="badge-glass">${(data.meta && data.meta.category) ? data.meta.category : 'Professional Instructor'}</span></div>

                            <div class="profile-label">Biography / Description</div>
                            <div class="profile-value text-secondary small" style="line-height: 1.6;">${data.bio || 'No bio provided.'}</div>
                            
                            <div class="profile-label">Social & Professional Links</div>
                            <div class="d-flex flex-wrap gap-2 mt-2">
                                ${data.meta && data.meta.linkedin ? `<a href="${data.meta.linkedin}" target="_blank" class="social-link">LinkedIn</a>` : ''}
                                ${data.meta && data.meta.github ? `<a href="${data.meta.github}" target="_blank" class="social-link">GitHub</a>` : ''}
                                ${data.meta && data.meta.facebook ? `<a href="${data.meta.facebook}" target="_blank" class="social-link">Facebook</a>` : ''}
                                ${data.meta && data.meta.twitter ? `<a href="${data.meta.twitter}" target="_blank" class="social-link">Twitter</a>` : ''}
                                ${data.meta && data.meta.portfolio ? `<a href="${data.meta.portfolio}" target="_blank" class="social-link">Website</a>` : ''}
                                ${(!data.meta || (!data.meta.linkedin && !data.meta.github && !data.meta.portfolio && !data.meta.facebook && !data.meta.twitter)) ? '<span class="text-muted small">No social links provided.</span>' : ''}
                            </div>
                        `;
                    }

                    if (role !== 'course') html += `</div>`;
                    document.getElementById('modalBody').innerHTML = html;
                    document.getElementById('modalTitle').innerText = role === 'creator' ? 'Instructor Profile' : 'Student Profile';
                });
        }

        const lmsHamburger = document.getElementById('lmsHamburger');
        const lmsSidebar = document.querySelector('.lms-sidebar');
        const lmsOverlay = document.getElementById('lmsOverlay');

        function toggleLmsSidebar() {
            if (!lmsSidebar || !lmsHamburger || !lmsOverlay) return;
            lmsSidebar.classList.toggle('open');
            lmsOverlay.classList.toggle('show');
            document.body.style.overflow = lmsSidebar.classList.contains('open') ? 'hidden' : '';
        }

        if (lmsHamburger) {
            lmsHamburger.addEventListener('click', toggleLmsSidebar);
        }
        if (lmsOverlay) {
            lmsOverlay.addEventListener('click', toggleLmsSidebar);
        }
    </script>
</body>
</html>
