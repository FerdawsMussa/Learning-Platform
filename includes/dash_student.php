<?php
// includes/dash_student.php

// Data fetching helper for Streaks
$stmt = $pdo->prepare("SELECT metric_value AS streak_count FROM progress WHERE record_type = 'streak' AND user_id = ?");
$stmt->execute([$user_id]);
$streak_data = $stmt->fetch();
$streak = $streak_data ? $streak_data['streak_count'] : 0;

// Fetch Certificates
$cert_stmt = $pdo->prepare("SELECT c.*, co.title as course_title, co.file_path as thumbnail_path FROM (SELECT id, user_id AS student_id, course_id, metric_value AS cert_id, created_at AS issued_at FROM progress WHERE record_type = 'certificate') c JOIN (SELECT id, title, file_path FROM content WHERE record_type = 'course') co ON c.course_id = co.id WHERE c.student_id = ? ORDER BY c.issued_at DESC");
$cert_stmt->execute([$user_id]);
$my_progress_certificates = $cert_stmt->fetchAll();

// Fetch User's content_courses array first
$u_stmt = $pdo->prepare("SELECT courses FROM users WHERE id = ?");
$u_stmt->execute([$user_id]);
$enrolled_json = $u_stmt->fetchColumn() ?: '[]';
$enrolled_ids = json_decode($enrolled_json, true) ?: [];

$enrolled_content_courses = [];
if (!empty($enrolled_ids)) {
    $placeholders = implode(',', array_fill(0, count($enrolled_ids), '?'));
    $enroll_stmt = $pdo->prepare("
        SELECT c.*, u.full_name as instructor,
        (SELECT COUNT(*) FROM progress WHERE record_type = 'lesson' AND course_id = c.id) as total_progress_lessons,
        (SELECT COUNT(*) FROM (SELECT item_id AS lesson_id FROM progress WHERE record_type = 'lesson_progress' AND user_id = ? AND metric_value = 'completed') lp 
         JOIN (SELECT id, course_id FROM progress WHERE record_type = 'lesson') l ON lp.lesson_id = l.id 
         WHERE l.course_id = c.id) as completed_progress_lessons,
        cert.cert_id
        FROM (SELECT id, user_id AS creator_id, title, description, file_path AS thumbnail_path, category, generic_value AS level, meta_text AS tags, is_resource, is_deleted, deleted_at, created_at, is_approved FROM content WHERE record_type = 'course') c 
        JOIN users u ON c.creator_id = u.id
        LEFT JOIN (SELECT course_id, metric_value as cert_id FROM progress WHERE record_type = 'certificate' AND user_id = ?) cert ON cert.course_id = c.id
        WHERE c.id IN ($placeholders) AND c.is_approved = 1
    ");
    $enroll_stmt->execute(array_merge([$user_id, $user_id], $enrolled_ids));
    $enrolled_content_courses = $enroll_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Sort manually to match the order in JSON (most recent first) or by ID
    usort($enrolled_content_courses, function($a, $b) use ($enrolled_ids) {
        return array_search($b['id'], $enrolled_ids) - array_search($a['id'], $enrolled_ids);
    });
}

// Fetch Library Courses and Resources with average ratings
$library_stmt = $pdo->query("
    SELECT c.*, u.full_name as instructor, 
           AVG(f.rating) as avg_rating, 
           COUNT(f.id) as review_count
    FROM (SELECT id, user_id AS creator_id, title, description, file_path AS thumbnail_path, category, generic_value AS level, meta_text AS tags, is_resource, is_deleted, deleted_at, created_at, record_type, is_approved FROM content WHERE record_type IN ('course', 'resource')) c 
    LEFT JOIN users u ON c.creator_id = u.id AND u.role = 'instructor'
    LEFT JOIN (SELECT id, JSON_UNQUOTE(JSON_EXTRACT(meta, '$.course_id')) as course_id, JSON_UNQUOTE(JSON_EXTRACT(meta, '$.rating')) as rating FROM notifications WHERE type = 'feedback') f ON c.id = f.course_id
    WHERE c.is_deleted = 0 AND c.is_approved = 1
    GROUP BY c.id
    ORDER BY c.created_at DESC
");
$library_all_content = $library_stmt->fetchAll(PDO::FETCH_ASSOC);
$library_courses = array_filter($library_all_content, fn($c) => $c['record_type'] === 'course');
$library_resources = array_filter($library_all_content, fn($c) => $c['record_type'] === 'resource');

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
$default_resource_icon = '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>';

// User Profile Info
$stmt_user = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt_user->execute([$user_id]);
$user_data = $stmt_user->fetch();
$field = $user_data['bio'] ?: 'Active Learner';
$first_letter = strtoupper(substr($user_name, 0, 1));

?>

<!-- Local style override for absolute button visibility -->
<style>
    /* Target all possible button states to ensure text is ALWAYS visible (black) on hover */
    .dashboard-section .btn:hover, 
    .dashboard-section .btn-neon:hover, 
    .dashboard-section .btn-success:hover, 
    .dashboard-section .btn-outline-success:hover,
    .dashboard-section .btn-outline-light:hover {
        color: #000 !important;
        background-color: var(--accent-green) !important;
        border-color: var(--accent-green) !important;
        opacity: 1 !important;
    }

    /* Ensure icons and text inside buttons also turn black on hover */
    .dashboard-section .btn:hover *,
    .dashboard-section .btn:hover svg,
    .dashboard-section .btn:hover i {
        color: #000 !important;
        stroke: #000 !important;
        fill: transparent !important;
    }
    
    .dashboard-section .btn:hover svg[fill="currentColor"] {
        fill: #000 !important;
    }

    .skills-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
        gap: 1.5rem;
        width: 100%;
    }

    .skill-card {
        background: var(--bg-card);
        border: 1px solid var(--border-light);
        border-radius: var(--radius-md);
        padding: 1.5rem;
        display: flex;
        align-items: flex-start;
        gap: 1rem;
        transition: all 0.3s ease;
        position: relative;
        overflow: hidden;
        text-decoration: none !important;
    }

    .skill-card:hover {
        transform: translateY(-5px);
        background: var(--bg-card-hover);
        border-color: var(--accent-green);
        box-shadow: 0 10px 25px rgba(0, 229, 153, 0.1);
    }

    .skill-icon-wrap {
        width: 48px;
        height: 48px;
        border-radius: 12px;
        background: rgba(0, 229, 153, 0.1);
        display: flex;
        align-items: center;
        justify-content: center;
        color: var(--accent-green);
        flex-shrink: 0;
    }

    .skill-info h3 {
        font-size: 1.1rem;
        margin-bottom: 5px;
        color: var(--text-primary);
        line-height: 1.3;
    }

    .skill-category {
        font-size: 0.75rem;
        color: var(--text-secondary);
        margin-bottom: 12px;
    }

    .difficulty-badge {
        display: inline-block;
        padding: 4px 10px;
        border-radius: 100px;
        font-size: 0.65rem;
        font-weight: 700;
        text-transform: uppercase;
        border: 1px solid currentColor;
    }
</style>

<div class="container-fluid p-0">
    <!-- OVERVIEW SECTION -->
    <div id="section-overview" class="dashboard-section active">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h3 class="fw-bold mb-0">Learning Overview</h3>
                <p class="text-secondary small">Track your academic progress_streaks and recent modules.</p>
            </div>
            <div class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 px-3 py-2 rounded-pill">
                <span class="me-1">🔥</span> <?= $streak ?> Day Streak
            </div>
        </div>

        <?php if (isset($enroll_success)): ?>
            <div class="alert alert-success alert-dismissible fade show bg-success bg-opacity-10 border-0 text-success mb-4" role="alert">
                <?= $enroll_success ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="row g-4 mb-5">
            <!-- Featured Course / Current Progress -->
            <div class="col-lg-8">
                <div class="glass-container p-4 p-md-5 h-100 position-relative overflow-hidden">
                    <div class="bg-gradient-spot glow-green" style="width: 300px; height: 300px; top: -150px; right: -150px; opacity: 0.1;"></div>
                    
                    <?php if(!empty($enrolled_content_courses)): 
                        $first = $enrolled_content_courses[0];
                        $total = $first['total_progress_lessons'] ?: 1;
                        $comp = $first['completed_progress_lessons'] ?: 0;
                        $perc = round(($comp / $total) * 100);
                    ?>
                        <div class="position-relative z-1">
                            <span class="badge bg-success bg-opacity-25 text-success mb-3 px-3 py-2">CURRENTLY LEARNING</span>
                            <h2 class="display-6 fw-bold mb-3"><?= htmlspecialchars($first['title']) ?></h2>
                            <p class="text-white-50 mb-4 col-md-10"><?= htmlspecialchars(substr($first['description'] ?? '', 0, 150)) ?>...</p>
                            
                            <div class="progress mb-4" style="height: 8px; background: rgba(0,0,0,0.2); max-width: 400px;">
                                <div class="progress-bar" style="width: <?= $perc ?>%; background: var(--accent-green); box-shadow: 0 0 15px var(--accent-green-glow);"></div>
                            </div>

                            <div class="d-flex gap-3">
                                <a href="student_viewer.php?id=<?= $first['id'] ?>&type=course&role=student" class="btn btn-neon px-4 py-3 fw-bold">Resume Module ➜</a>
                                <?php if ($perc >= 100 && $first['cert_id']): ?>
                                    <a href="view_certificate.php?id=<?= $first['cert_id'] ?>" target="_blank" class="btn btn-success px-4 py-3">🎓 View Certificate</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php else: ?>
                        <h2 class="fw-bold mb-3">Begin Your Journey</h2>
                        <p class="text-white-50 small mb-4">You haven't enrolled in any content_courses yet. Browse our professional library to begin.</p>
                        <a href="#library" onclick="switchTab('library')" class="btn btn-neon px-4 py-2">Explore Library</a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Mini Stats -->
            <div class="col-lg-4">
                <div class="glass-container p-4 h-100 d-flex flex-column gap-3">
                    <div class="stat-card-mini d-flex align-items-center gap-3" onclick="switchTab('rewards')" style="cursor:pointer">
                        <div class="p-3 rounded-4" style="background: rgba(59, 130, 246, 0.1); color: var(--accent-blue);">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 10v6M2 10l10-5 10 5-10 5z"></path><path d="M6 12v5c3 3 9 3 12 0v-5"></path></svg>
                        </div>
                        <div>
                            <div class="h5 mb-0 fw-bold"><?= count($my_progress_certificates) ?></div>
                            <div class="small text-secondary">Earned Certificates</div>
                        </div>
                    </div>
                    <div class="stat-card-mini d-flex align-items-center gap-3">
                        <div class="p-3 rounded-4" style="background: rgba(239, 68, 68, 0.1); color: #ef4444;">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z"></path></svg>
                        </div>
                        <div>
                            <div class="h5 mb-0 fw-bold"><?= $streak ?> Days</div>
                            <div class="small text-secondary">Check-in Streak</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Enrolled Preview -->
        <h4 class="fw-bold mb-4">Most Recent Activity</h4>
        <div class="row g-4">
            <?php if(count($enrolled_content_courses) == 0): ?>
                <div class="col-12 text-center py-5 opacity-50">No active content_courses yet. Enroll from the library below!</div>
            <?php else: foreach(array_slice($enrolled_content_courses, 0, 3) as $c): ?>
                <div class="col-md-4">
                    <div class="glass-container p-4">
                        <h6 class="fw-bold mb-2 text-truncate"><?= htmlspecialchars($c['title']) ?></h6>
                        <?php 
                        $total = $c['total_progress_lessons'] ?: 1;
                        $comp = $c['completed_progress_lessons'] ?: 0;
                        $perc = round(($comp / $total) * 100);
                        ?>
                        <div class="d-flex justify-content-between small text-secondary mb-2">
                            <span>Progress</span>
                            <span><?= $perc ?>%</span>
                        </div>
                        <div class="progress mb-3" style="height: 6px; background: rgba(255,255,255,0.05);">
                            <div class="progress-bar bg-success" style="width: <?= $perc ?>%;"></div>
                        </div>
                        <?php if ($perc >= 100 && $c['cert_id']): ?>
                            <div class="d-flex gap-2 mt-2">
                                <a href="view_certificate.php?id=<?= $c['cert_id'] ?>" target="_blank" class="btn btn-sm btn-success flex-grow-1">🎓 View Certificate</a>
                                <a href="student_viewer.php?id=<?= $c['id'] ?>&type=course" class="btn btn-sm btn-outline-light border-opacity-25 flex-grow-1">View Course</a>
                            </div>
                        <?php else: ?>
                            <a href="student_viewer.php?id=<?= $c['id'] ?>&type=course" class="btn btn-sm btn-neon w-100 mt-2">View Course</a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; endif; ?>
        </div>
    </div>
</div>

<!-- MY COURSES SECTION -->
<div id="section-my-content_courses" class="dashboard-section">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="fw-bold mb-0">My Enrolled Courses</h3>
        <span class="text-white-50 small"><?= count($enrolled_content_courses) ?> Courses Total</span>
    </div>

    <div class="row g-4">
        <?php if(empty($enrolled_content_courses)): ?>
            <div class="col-12 text-center py-5">
                <p class="text-secondary">You haven't enrolled in any content_courses yet.</p>
                <a href="#library" onclick="switchTab('library')" class="btn btn-neon rounded-pill">Find a Course</a>
            </div>
        <?php endif; ?>
        <?php foreach($enrolled_content_courses as $c): ?>
            <div class="col-md-6 col-lg-4">
                <div class="glass-container h-100" style="overflow: hidden;">
                    <img src="<?= $c['thumbnail_path'] ?: 'assets/img/course-placeholder.jpg' ?>" class="w-100" style="height: 160px; object-fit: cover; opacity: 0.8;">
                    <div class="p-4">
                        <div class="small text-accent mb-2 fw-bold" style="color: var(--accent-green); font-size: 0.75rem;"><?= strtoupper($c['category'] ?? 'General') ?></div>
                        <h5 class="fw-bold mb-3 text-truncate"><?= htmlspecialchars($c['title']) ?></h5>
                        
                        <div class="course-desc-container flex-grow-1 mb-3">
                            <p class="small text-secondary mb-0 description-text" id="desc-my-<?= $c['id'] ?>" style="display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; line-height: 1.5; font-size: 0.8rem;">
                                <?= htmlspecialchars($c['description'] ?? 'No description available.') ?>
                            </p>
                            <?php if(strlen($c['description'] ?? '') > 60): ?>
                                <a href="javascript:void(0)" onclick="toggleMyDesc(<?= $c['id'] ?>, this)" class="text-accent small fw-bold text-decoration-none" style="font-size: 0.7rem; color: var(--accent-green);">See More</a>
                            <?php endif; ?>
                        </div>

                        <div class="d-flex align-items-center gap-2 mb-3" style="cursor: pointer;" onclick="showProfile(<?= $c['creator_id'] ?>, 'creator')">
                             <div class="rounded-circle bg-secondary bg-opacity-25 d-flex align-items-center justify-content-center text-white-50" style="width: 24px; height: 24px; font-size: 0.65rem; font-weight: bold;">
                                <?= strtoupper(substr($c['instructor'], 0, 1)) ?>
                             </div>
                             <span class="small text-white-50"><?= htmlspecialchars($c['instructor']) ?></span>
                        </div>

                        <?php 
                        $total = $c['total_progress_lessons'] ?: 1;
                        $comp = $c['completed_progress_lessons'] ?: 0;
                        $perc = round(($comp / $total) * 100);
                        ?>
                        <div class="progress mb-3" style="height: 5px; background: rgba(0,0,0,0.2);">
                            <div class="progress-bar bg-success" style="width: <?= $perc ?>%;"></div>
                        </div>
                        <div class="mt-4">
                            <?php if ($perc >= 100 && $c['cert_id']): ?>
                                <div class="d-flex gap-2 flex-wrap">
                                    <a href="view_certificate.php?id=<?= $c['cert_id'] ?>" target="_blank" class="btn btn-sm btn-success px-3 flex-grow-1">🎓 View Certificate</a>
                                    <a href="student_viewer.php?id=<?= $c['id'] ?>&type=course" class="btn btn-sm btn-outline-light border-opacity-25 px-3 flex-grow-1">View Course</a>
                                </div>
                            <?php else: ?>
                                <a href="student_viewer.php?id=<?= $c['id'] ?>&type=course" class="btn btn-sm btn-neon w-100">View Course</a>
                            <?php endif; ?>
                            <div class="small text-secondary mt-2 text-end"><?= $perc ?>% Complete</div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- STUDY LIBRARY SECTION -->
<div id="section-library" class="dashboard-section">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
        <div>
            <h3 class="fw-bold mb-0">Experience Library</h3>
            <p class="text-secondary small mb-0">Browse high-quality content uploaded by instructors.</p>
        </div>
    </div>

    <!-- Styled Search Bar (matching landing page) -->
    <div class="search-wrapper mb-5" style="max-width: 520px; margin-left: 0;">
        <svg class="search-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <circle cx="11" cy="11" r="8"></circle>
            <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
        </svg>
        <input type="text" id="librarySearchInput" class="search-input" placeholder="Search courses by name or category..." onkeyup="filterLibrary()" style="font-size: 0.95rem; padding: 0.9rem 1.2rem 0.9rem 3.2rem;">
    </div>

    <!-- Courses Section -->
    <div class="mb-5">
        <div class="d-flex align-items-center gap-3 mb-4">
            <h5 class="mb-0 text-accent fw-bold">Educational Courses</h5>
            <div class="flex-grow-1 border-bottom border-light opacity-10"></div>
        </div>
        <div class="row g-4" id="library-courses-container">
            <?php foreach($library_courses as $c): 
                $is_enrolled = in_array($c['id'], $enrolled_ids);
            ?>
                <div class="col-md-6 col-lg-4 course-card-library" data-title="<?= strtolower($c['title']) ?>" data-category="<?= strtolower($c['category'] ?? '') ?>">
                    <div class="glass-container h-100 d-flex flex-column overflow-hidden transition-3">
                        <img src="<?= $c['thumbnail_path'] ?: 'assets/img/course-placeholder.jpg' ?>" class="w-100" style="height: 140px; object-fit: cover;">
                        <div class="p-4 flex-grow-1 d-flex flex-column">
                            <div class="d-flex justify-content-between mb-2">
                                 <span class="small text-info fw-bold"><?= strtoupper($c['category'] ?? 'IT') ?></span>
                                 <span class="small text-warning">
                                    <?= $c['avg_rating'] ? '★ ' . number_format($c['avg_rating'], 1) : '★ New' ?>
                                 </span>
                            </div>
                            <h5 class="fw-bold mb-2 fs-6"><?= htmlspecialchars($c['title']) ?></h5>
                            
                            <div class="course-desc-container flex-grow-1 mb-3">
                                <p class="small text-secondary mb-0 description-text" id="desc-<?= $c['id'] ?>" style="display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; line-height: 1.5;">
                                    <?= htmlspecialchars($c['description'] ?? 'No description available.') ?>
                                </p>
                                <?php if(strlen($c['description'] ?? '') > 100): ?>
                                    <a href="javascript:void(0)" onclick="toggleDesc(<?= $c['id'] ?>, this)" class="text-accent small fw-bold text-decoration-none" style="font-size: 0.75rem; color: var(--accent-green);">See More</a>
                                <?php endif; ?>
                            </div>
                            
                            <div class="d-flex align-items-center gap-2 mb-3" style="cursor: pointer;" onclick="showProfile(<?= $c['creator_id'] ?>, 'creator')">
                                 <div class="rounded-circle bg-secondary bg-opacity-25 d-flex align-items-center justify-content-center text-white-50" style="width: 24px; height: 24px; font-size: 0.65rem; font-weight: bold;">
                                    <?= strtoupper(substr($c['instructor'], 0, 1)) ?>
                                 </div>
                                 <span class="small text-white-50"><?= htmlspecialchars($c['instructor']) ?></span>
                            </div>
    
                            <?php if($is_enrolled): ?>
                                <a href="student_viewer.php?id=<?= $c['id'] ?>&type=course&role=student" class="btn btn-sm btn-neon w-100 mt-auto">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="margin-right:5px;vertical-align:middle;"><polygon points="5 3 19 12 5 21 5 3"></polygon></svg>
                                    View Course
                                </a>
                            <?php else: ?>
                                <div id="enroll-container-<?= $c['id'] ?>">
                                    <button type="button" onclick="enrollCourse(<?= $c['id'] ?>, this)" class="btn btn-sm btn-outline-success w-100 mt-auto border-success text-success">
                                        Enroll Now
                                    </button>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Resources Section -->
    <div class="mb-5 mt-5">
        <div class="d-flex align-items-center gap-3 mb-4">
            <h5 class="mb-0 text-warning fw-bold">Learning Resources</h5>
            <div class="flex-grow-1 border-bottom border-light opacity-10"></div>
        </div>
        <div class="skills-grid p-0" id="library-resources-container">
            <?php foreach($library_resources as $c): 
                $catIcon = $categories_icons[$c['category']] ?? $default_resource_icon;
            ?>
                <div class="skill-card resource-card-library" data-title="<?= strtolower($c['title']) ?>" data-category="<?= strtolower($c['category'] ?? '') ?>" style="background: var(--bg-card); border: 1px solid var(--border-light);">
                    <div class="skill-icon-wrap" style="background: rgba(255, 193, 7, 0.1); color: #ffc107;">
                        <?= $catIcon ?>
                    </div>
                    <div class="skill-info flex-grow-1">
                        <h3 class="h6 mb-1 text-white fw-bold"><?= htmlspecialchars($c['title']) ?></h3>
                        <div class="skill-category small text-secondary mb-2"><?= htmlspecialchars($c['category']) ?></div>
                        <div class="d-flex align-items-center justify-content-between">
                            <span class="difficulty-badge" style="color: #ffc107; background: rgba(255, 193, 7, 0.1); border-color: rgba(255, 193, 7, 0.3); font-size: 0.6rem;"><?= htmlspecialchars($c['level'] ?: 'Beginner') ?></span>
                            <div class="ms-auto text-warning">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                                <span class="small fw-bold">View</span>
                            </div>
                        </div>
                    </div>
                    <a href="student_viewer.php?id=<?= $c['id'] ?>&type=resource&role=student" class="stretched-link"></a>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<script>
async function enrollCourse(courseId, btn) {
    const originalText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Enrolling...';

    const formData = new FormData();
    formData.append('enroll_now', courseId);
    formData.append('ajax', '1');

    try {
        const response = await fetch('dashboard.php', {
            method: 'POST',
            body: formData
        });
        const data = await response.json();

        if (data.success) {
            // Transform button to "Start Now"
            const container = document.getElementById('enroll-container-' + courseId);
            container.innerHTML = `
                <a href="student_viewer.php?id=${courseId}&type=course&enrolled=1&role=student" class="btn btn-sm btn-neon w-100 mt-auto animate-up">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="margin-right:5px;vertical-align:middle;"><polygon points="5 3 19 12 5 21 5 3"></polygon></svg>
                    Start Now
                </a>
            `;
        } else {
            alert('Enrollment failed: ' + data.message);
            btn.innerHTML = originalText;
            btn.disabled = false;
        }
    } catch (error) {
        console.error('Error:', error);
        alert('An error occurred during enrollment.');
        btn.innerHTML = originalText;
        btn.disabled = false;
    }
}
</script>

<!-- ACHIEVEMENTS SECTION -->
<div id="section-rewards" class="dashboard-section">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="fw-bold mb-0">My Certificates</h3>
        <p class="text-secondary small mb-0">Official documentation of your learning achievements.</p>
    </div>

    <div class="row g-4">
        <?php if(empty($my_progress_certificates)): ?>
            <div class="col-12 text-center py-5">
                <div class="display-1 opacity-10 mb-4">🎓</div>
                <p class="text-secondary">No progress_certificates earned yet. Complete a full course to receive your first one!</p>
                <a href="#library" onclick="switchTab('library')" class="btn btn-outline-success rounded-pill px-4">Browse Library</a>
            </div>
        <?php else: foreach($my_progress_certificates as $cert): ?>
            <div class="col-md-6 col-lg-4">
                <div class="glass-container h-100 overflow-hidden d-flex flex-column" style="border-top: 4px solid #00e599;">
                    <div class="p-4 flex-grow-1">
                        <div class="small text-success fw-bold mb-2">VALID CREDENTIAL</div>
                        <h5 class="fw-bold mb-3"><?= htmlspecialchars($cert['course_title']) ?></h5>
                        <p class="small text-secondary mb-4">ID: <?= htmlspecialchars($cert['cert_id']) ?></p>
                        <div class="d-flex justify-content-between align-items-center mt-auto">
                            <span class="small text-white-50">Issued: <?= date('M d, Y', strtotime($cert['issued_at'])) ?></span>
                            <a href="view_certificate.php?id=<?= $cert['cert_id'] ?>" target="_blank" class="btn btn-sm btn-neon">View / Download</a>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; endif; ?>
    </div>
</div>

<!-- PROFILE SECTION -->
<div id="section-profile" class="dashboard-section">
    <div class="row g-4">
        <div class="col-lg-4">
            <div class="glass-container p-5 text-center h-100 d-flex flex-column align-items-center">
                <div class="position-relative mb-4">
                    <?php if(!empty($user_data['profile_pic'])): ?>
                        <img src="<?= htmlspecialchars($user_data['profile_pic']) ?>" class="rounded-circle shadow-lg border border-success border-opacity-25" style="width: 120px; height: 120px; object-fit: cover;">
                    <?php else: ?>
                        <div class="rounded-circle bg-success shadow-lg d-flex align-items-center justify-content-center text-dark fw-bold display-4" style="width: 120px; height: 120px;">
                            <?= $first_letter ?>
                        </div>
                    <?php endif; ?>
                </div>
                <h3 class="fw-bold mb-1"><?= htmlspecialchars($user_name) ?></h3>
                <p class="text-secondary mb-0"><?= htmlspecialchars($user_data['email'] ?? '') ?></p>
            </div>
        </div>
        <div class="col-lg-8">
            <div class="glass-container p-4 p-md-5">
                <h5 class="fw-bold mb-4 border-bottom border-light border-opacity-10 pb-3">Account Security & Details</h5>
                
                <?php if(!empty($profile_success)): ?>
                    <div class="alert alert-success alert-dismissible fade show bg-success bg-opacity-10 border-0 text-success mb-4" role="alert">
                        <?= $profile_success ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <form method="POST" action="dashboard.php#profile" enctype="multipart/form-data">
                    <input type="hidden" name="update_profile" value="1">
                    <div class="row g-4">
                        <div class="col-md-6 text-start">
                             <label class="small text-secondary mb-2">Display Name (Verified)</label>
                             <input type="text" class="form-control border-secondary bg-dark text-white-50 p-3" value="<?= htmlspecialchars($user_name) ?>" readonly>
                        </div>
                        <div class="col-md-6 text-start">
                             <label class="small text-secondary mb-2">Last Name</label>
                             <input type="text" name="last_name" class="form-control border-secondary bg-dark text-white p-3" value="<?= htmlspecialchars($user_data['last_name'] ?? '') ?>" placeholder="Enter your last name">
                        </div>

                        <div class="col-12 text-start">
                             <label class="small text-secondary mb-2">Current Password (Required for any changes)</label>
                             <div style="position:relative;">
                                 <input type="password" id="stu_current_pw" name="current_password" class="form-control border-secondary bg-dark text-white p-3" placeholder="••••••••">
                                 <span onclick="togglePassword('stu_current_pw',this)" style="position:absolute;right:14px;top:50%;transform:translateY(-50%);cursor:pointer;color:rgba(255,255,255,0.4);">
                                     <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                                 </span>
                             </div>
                             <hr class="border-light border-opacity-10 my-3">
                        </div>

                        <div class="col-md-6 text-start">
                             <label class="small text-secondary mb-2">New Password (Optional)</label>
                             <div style="position:relative;">
                                 <input type="password" id="stu_new_pw" name="new_password" class="form-control border-secondary bg-dark text-white p-3" placeholder="••••••••">
                                 <span onclick="togglePassword('stu_new_pw',this)" style="position:absolute;right:14px;top:50%;transform:translateY(-50%);cursor:pointer;color:rgba(255,255,255,0.4);">
                                     <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                                 </span>
                             </div>
                        </div>
                        <div class="col-md-6 text-start">
                             <label class="small text-secondary mb-2">Confirm New Password</label>
                             <div style="position:relative;">
                                 <input type="password" id="stu_confirm_pw" name="confirm_password" class="form-control border-secondary bg-dark text-white p-3" placeholder="••••••••">
                                 <span onclick="togglePassword('stu_confirm_pw',this)" style="position:absolute;right:14px;top:50%;transform:translateY(-50%);cursor:pointer;color:rgba(255,255,255,0.4);">
                                     <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                                 </span>
                             </div>
                        </div>

                        <div class="col-12 text-start">
                             <label class="small text-secondary mb-2">Update Profile Picture</label>
                             <input type="file" name="profile_pic" class="form-control border-secondary bg-dark text-white p-3">
                        </div>

                        <div class="col-12 mt-4">
                             <button type="submit" class="btn-glass w-100 py-3 rounded-pill" style="color: var(--accent-green); border-color: var(--accent-green);">Save Profile Updates</button>
                        </div>
                    </div>
                </form>


            </div>
        </div>
    </div>
</div>

</div>

<script>
function filterLibrary() {
    const input = document.getElementById('librarySearchInput');
    const filter = input.value.toLowerCase();
    const courseCards = document.getElementsByClassName('course-card-library');
    const resourceCards = document.getElementsByClassName('resource-card-library');

    const filterCards = (cards) => {
        for (let i = 0; i < cards.length; i++) {
            const title = cards[i].getAttribute('data-title') || "";
            const category = cards[i].getAttribute('data-category') || "";
            
            if (title.includes(filter) || category.includes(filter)) {
                cards[i].style.display = "";
            } else {
                cards[i].style.display = "none";
            }
        }
    };

    filterCards(courseCards);
    filterCards(resourceCards);
}
</script>
