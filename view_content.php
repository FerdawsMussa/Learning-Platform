<?php
require_once 'api/session_helper.php';
require_once 'api/db.php';

// Detect requested role to prioritize session
$requested_role = $_GET['role'] ?? null;
$roles_to_check = ['admin', 'instructor', 'student'];

if ($requested_role && in_array($requested_role, $roles_to_check)) {
    // Put requested role at the front of the queue
    $roles_to_check = array_diff($roles_to_check, [$requested_role]);
    array_unshift($roles_to_check, $requested_role);
}

$viewer_role = null;
foreach ($roles_to_check as $role) {
    start_role_session($role);
    if (isset($_SESSION['user_id'])) {
        $viewer_role = $role;
        break;
    }
    session_write_close();
}

if (!$viewer_role) {
    header("Location: login.php");
    exit;
}

$id = $_GET['id'] ?? '';
$type = $_GET['type'] ?? 'course';
$lid  = $_GET['lid'] ?? ''; // target lesson id
$fid  = $_GET['fid'] ?? ''; // target file id for resources

if (empty($id)) {
    die("Content ID missing.");
}

$content = null;
$progress_lessons = [];
$active_lesson = null;

if ($type === 'course') {
    $stmt = $pdo->prepare("SELECT * FROM (SELECT id, user_id AS creator_id, title, description, file_path AS thumbnail_path, category, generic_value AS level, meta_text AS tags, is_resource, is_deleted, deleted_at, created_at, is_approved FROM content WHERE record_type = 'course') content_courses WHERE id = ?");
    $stmt->execute([$id]);
    $content = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($content) {
        $lstmt = $pdo->prepare("SELECT id, course_id, item_id AS module_id, title, content_type AS type, content_text AS content, file_path, file_size, order_number, created_at FROM progress WHERE record_type = 'lesson' AND course_id = ? ORDER BY order_number ASC");
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
    $stmt = $pdo->prepare("SELECT * FROM (SELECT id, user_id AS creator_id, title, file_path as thumbnail_path, file_size, generic_value AS file_type, category, is_deleted, deleted_at, created_at, is_approved FROM content WHERE record_type = 'resource') content_resources WHERE id = ?");
    $stmt->execute([$id]);
    $content = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($content) {
        $rstmt = $pdo->prepare("SELECT id, title, file_path, file_size, generic_value as type FROM content WHERE record_type = 'resource_file' AND parent_id = ? ORDER BY id ASC");
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
if ($content['is_approved'] == 0) {
    $is_owner = ($viewer_role === 'instructor' && $_SESSION['user_id'] == $content['creator_id']);
    $is_admin = ($viewer_role === 'admin');
    
    if (!$is_owner && !$is_admin) {
        die("<h3>Content Pending Approval</h3><p>This course or resource is currently being reviewed by administrators and is not yet available for public viewing.</p><a href='index.php'>Back to Home</a>");
    }
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($content['title']) ?> | JU Learn</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/dashboard.css">
    <style>
        :root {
            --accent-green: #00e599;
            --accent-blue: #3b82f6;
            --bg-dark: #0f172a;
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

        /* Sidebar Styling */
        .lms-sidebar {
            width: 300px;
            background: rgba(10, 14, 18, 0.98);
            border-right: 1px solid var(--border-light);
            display: flex;
            flex-direction: column;
            flex-shrink: 0;
            z-index: 100;
        }

        .sidebar-branding {
            padding: 25px;
            border-bottom: 1px solid var(--border-light);
        }

        .brand-logo {
            font-weight: 800;
            font-size: 1.5rem;
            letter-spacing: -1px;
            color: white;
            text-decoration: none;
            display: block;
        }

        .brand-logo span {
            color: var(--accent-green);
        }

        .sidebar-curriculum {
            flex-grow: 1;
            overflow-y: auto;
            scrollbar-width: thin;
            scrollbar-color: rgba(255,255,255,0.1) transparent;
        }
        .sidebar-curriculum::-webkit-scrollbar { width: 5px; }
        .sidebar-curriculum::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.1); border-radius: 10px; }

        .curriculum-header {
            padding: 20px 25px 10px;
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            color: #64748b;
            font-weight: 700;
        }

        .lesson-item {
            display: flex;
            padding: 16px 25px;
            color: #94a3b8;
            text-decoration: none !important;
            border-bottom: 1px solid rgba(255, 255, 255, 0.03);
            transition: 0.3s;
            align-items: flex-start;
            gap: 15px;
        }
        .lesson-item:hover {
            background: rgba(255, 255, 255, 0.04);
            color: white;
        }
        .lesson-item.active {
            background: rgba(0, 229, 153, 0.08);
            color: var(--accent-green);
            border-left: 4px solid var(--accent-green);
        }

        /* Main Content Container */
        .lms-main {
            flex-grow: 1;
            display: flex;
            flex-direction: column;
            min-width: 0;
            overflow-y: auto;
            position: relative;
        }

        .top-navbar {
            height: 75px;
            background: rgba(10, 14, 18, 0.5);
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
            padding: 30px;
            max-width: 1100px;
            margin: 0 auto;
            width: 100%;
        }

        .glass-container {
            background: var(--glass-bg);
            border: 1px solid var(--border-light);
            border-radius: 20px;
            backdrop-filter: blur(15px);
            overflow: hidden;
            box-shadow: 0 20px 40px rgba(0,0,0,0.3);
            margin-bottom: 40px;
        }

        .media-viewer {
            background: #000;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            min-height: 400px;
        }

        video, .image-view {
            width: 100%;
            max-height: 75vh;
            display: block;
        }

        .lesson-detail-card {
            padding: 40px;
        }

        /* Abstract Glow Background */
        .bg-glow {
            position: fixed;
            width: 60vw;
            height: 60vw;
            border-radius: 50%;
            filter: blur(180px);
            opacity: 0.15;
            z-index: -1;
            pointer-events: none;
        }
        .glow-green { top: -20%; right: -10%; background: var(--accent-green); }
        .glow-blue { bottom: -20%; left: -10%; background: var(--accent-blue); }

        .btn-action {
            background: rgba(255,255,255,0.05);
            border: 1px solid var(--border-light);
            color: #f1f5f9;
            border-radius: 10px;
            padding: 10px 20px;
            font-size: 0.9rem;
            font-weight: 600;
            transition: 0.3s;
            text-decoration: none;
        }
        .btn-action:hover { background: rgba(0, 229, 153, 0.1); color: var(--accent-green); border-color: var(--accent-green); }

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

    <div class="bg-glow glow-green"></div>
    <div class="bg-glow glow-blue"></div>

    <div class="lms-wrapper">
        
        <!-- SIDEBAR -->
        <aside class="lms-sidebar">
            <div class="sidebar-branding">
                <a href="index.php" class="brand-logo">JU<span>Learn</span></a>
                <div class="text-white-50 small mt-1 fw-bold" style="font-size: 0.65rem; letter-spacing: 1px; text-transform: uppercase;">Course Player</div>
            </div>

            <div class="sidebar-curriculum">
                <div class="curriculum-header">Curriculum Overview</div>
                <?php if ($type === 'course'): ?>
                    <?php foreach ($progress_lessons as $index => $l): ?>
                        <a href="?id=<?= $id ?>&type=course&lid=<?= $l['id'] ?>&role=<?= $viewer_role ?>" class="lesson-item <?= ($active_lesson && $active_lesson['id'] == $l['id']) ? 'active' : '' ?>">
                            <div class="fw-bold fs-6 flex-grow-1">
                                <div class="small opacity-50 mb-1" style="font-size: 0.65rem;">
                                    <?= sprintf('%02d', $index + 1) ?> • <?= ucfirst($l['type']) ?>
                                    <?php if(!empty($l['file_size'])): ?> • <?= $l['file_size'] ?><?php endif; ?>
                                </div>
                                <?= htmlspecialchars($l['title']) ?>
                            </div>
                        </a>
                    <?php endforeach; ?>
                <?php elseif ($type === 'resource'): ?>
                    <?php foreach ($content['files'] as $index => $f): ?>
                        <a href="?id=<?= $id ?>&type=resource&fid=<?= $f['id'] ?>&role=<?= $viewer_role ?>" class="lesson-item <?= (isset($active_file) && $active_file['id'] == $f['id']) ? 'active' : '' ?>">
                            <div class="fw-bold fs-6 flex-grow-1">
                                <div class="small opacity-50 mb-1" style="font-size: 0.65rem;">
                                    <?= sprintf('%02d', $index + 1) ?> • <?= strtoupper($f['type']) ?>
                                    <?php if(!empty($f['file_size'])): ?> • <?= $f['file_size'] ?><?php endif; ?>
                                </div>
                                <?= htmlspecialchars($f['title']) ?>
                            </div>
                        </a>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <div class="mt-auto p-4 border-top border-light border-opacity-10 d-flex flex-column gap-2">
                <?php
                $dashboard_link = 'dashboard.php'; // Default student dashboard
                if (isset($viewer_role)) {
                    if ($viewer_role === 'admin') $dashboard_link = 'dashboards/admin.php';
                    elseif ($viewer_role === 'instructor') $dashboard_link = 'instructor_dashboard.php';
                }
                ?>
                <a href="<?= $dashboard_link ?>" class="btn btn-outline-success border-success text-success w-100 py-2 rounded-3 small text-decoration-none text-center">Back to Dashboard</a>
            </div>
        </aside>

        <!-- MAIN CONTENT -->
        <main class="lms-main">
            <header class="top-navbar">
                <div class="d-flex align-items-center gap-4">
                    <button class="btn btn-link text-white d-lg-none p-0" id="lmsHamburger" aria-label="Toggle Menu">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="3" y1="12" x2="21" y2="12"></line>
                            <line x1="3" y1="6" x2="21" y2="6"></line>
                            <line x1="3" y1="18" x2="21" y2="18"></line>
                        </svg>
                    </button>
                    <h5 class="mb-0 text-white fw-bold d-none d-md-block"><?= htmlspecialchars($content['title']) ?></h5>
                    <h5 class="mb-0 text-white fw-bold d-md-none text-truncate" style="max-width: 200px;"><?= htmlspecialchars($content['title']) ?></h5>
                </div>
            </header>

            <div class="player-viewport">
                
                <!-- Media Section -->
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
                                            <div class="mt-2 text-center">
                                                <a href="<?= htmlspecialchars($fpath) ?>" download class="btn btn-sm btn-link text-accent">Download Original File</a>
                                            </div>
                                        <?php else: ?>
                                            <div class="p-5 text-center w-100 bg-dark bg-opacity-25 rounded-4">
                                                <div class="display-3 opacity-25 mb-3">📂</div>
                                                <h5 class="text-white"><?= htmlspecialchars($res['title']) ?></h5>
                                                <p class="small text-secondary mb-3">Online preview not available for this file type.</p>
                                                <a href="<?= htmlspecialchars($fpath) ?>" download class="btn btn-neon px-4 rounded-pill">
                                                    Download Attachment <?= !empty($res['file_size']) ? '(' . $res['file_size'] . ')' : '' ?>
                                                </a>
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
                                        <?php if ($viewer_role !== 'instructor'): ?>
                                            <a href="<?= htmlspecialchars($fpath) ?>" download class="btn btn-neon px-4 rounded-pill">Download Attachment</a>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if ($viewer_role !== 'instructor' && in_array($ext, ['pptx', 'ppt', 'docx', 'doc', 'xlsx', 'xls'])): ?>
                                    <div class="mt-2 text-center">
                                        <a href="<?= htmlspecialchars($fpath) ?>" download class="btn btn-sm btn-link text-accent">Download Original File</a>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                    <?php else: ?>
                        <!-- Introduction screen if no lesson active -->
                        <div class="p-5 text-center d-flex flex-column align-items-center justify-content-center" style="min-height: 450px;">
                            <?php if($type == 'resource' && (empty($content['thumbnail_path']) || $content['thumbnail_path'] == 'MULTIPLE')): ?>
                                <div class="rounded-4 mb-4 shadow-lg d-flex align-items-center justify-content-center" 
                                     style="max-width: 500px; width: 100%; height: 280px; background: linear-gradient(135deg, #3b82f6 0%, #1e3a8a 100%); border: 1px solid var(--border-light);">
                                    <div class="text-center">
                                        <span class="display-1">📂</span>
                                        <div class="small text-white-50 mt-2 fw-bold text-uppercase" style="letter-spacing: 2px;">Library Resource</div>
                                    </div>
                                </div>
                            <?php else: ?>
                                <img src="<?= $content['thumbnail_path'] ?: 'assets/img/course-placeholder.jpg' ?>" 
                                     class="rounded-4 mb-4 shadow-lg" 
                                     style="max-width: 500px; width: 100%; height: 280px; object-fit: cover; border: 1px solid var(--border-light);">
                            <?php endif; ?>
                            <h1 class="display-6 fw-bold text-white"><?= htmlspecialchars($content['title']) ?></h1>
                            <p class="text-secondary mt-3 fs-5">Please select a lesson from the left side to begin.</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Active Lesson Info -->
                <?php if ($active_lesson): ?>
                    <div class="glass-container lesson-detail-card">
                        <div class="d-flex justify-content-between align-items-center mb-4 pb-4 border-bottom border-light border-opacity-10">
                            <div>
                                <h1 class="h2 fw-bold text-white mb-2"><?= htmlspecialchars($active_lesson['title']) ?></h1>
                                <div class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 px-3 py-2">
                                    Module <?= $active_lesson['order_number'] ?> Content
                                </div>
                            </div>
                            <?php if (!empty($active_lesson['file_path'])): ?>
                                <a href="<?= htmlspecialchars($active_lesson['file_path']) ?>" download class="btn-action">⬇ Download Lesson File</a>
                            <?php endif; ?>
                        </div>

                        <div class="lesson-body text-secondary" style="line-height: 1.9; font-size: 1.1rem;">
                            <?php if (!empty($active_lesson['content'])): ?>
                                <?= nl2br(htmlspecialchars($active_lesson['content'])) ?>
                            <?php else: ?>
                                <p class="fst-italic opacity-50">No textual content provided for this session.</p>
                            <?php endif; ?>
                        </div>
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
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
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
