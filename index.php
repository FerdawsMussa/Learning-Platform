<?php
require_once 'api/db.php';



// Fetch all content_courses for the browse page
// Fetch content_courses with average rating from the new content_course_feedback table
$content_courses_stmt = $pdo->query("
    SELECT c.*, cc.full_name AS instructor_name, 
           AVG(f.rating) as avg_rating, 
           COUNT(f.id) as review_count 
    FROM (SELECT id, user_id AS creator_id, title, description, file_path AS thumbnail_path, category, generic_value AS level, meta_text AS tags, is_resource, is_deleted, deleted_at, created_at, is_approved FROM content WHERE record_type = 'course') c 
    LEFT JOIN users cc ON c.creator_id = cc.id 
    LEFT JOIN (SELECT id, JSON_UNQUOTE(JSON_EXTRACT(meta, '$.course_id')) as course_id, JSON_UNQUOTE(JSON_EXTRACT(meta, '$.rating')) as rating FROM notifications WHERE type = 'feedback') f ON c.id = f.course_id
    WHERE c.is_deleted = 0 AND c.is_approved = 1
    GROUP BY c.id 
    ORDER BY c.created_at DESC
");
$all_content_courses = $content_courses_stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch all resources
$resources_stmt = $pdo->query("
    SELECT c.*, cc.full_name AS instructor_name
    FROM content c
    LEFT JOIN users cc ON c.user_id = cc.id
    WHERE c.record_type = 'resource' AND c.is_deleted = 0 AND c.is_approved = 1
    ORDER BY c.created_at DESC
");
$all_resources = $resources_stmt->fetchAll(PDO::FETCH_ASSOC);

// Map resources to the format expected by the frontend (previously $raw_skills)
$raw_skills = [];
foreach ($all_resources as $res) {
    $raw_skills[] = [
        't' => $res['title'],
        'c' => $res['category'] ?: 'General',
        'd' => $res['generic_value'] ?: 'Beginner', // Using generic_value for difficulty/level
        'id' => $res['id'],
        'file_path' => $res['file_path'],
        'tags' => $res['meta_text'] ?? ''
    ];
}


// Fetch live platform stats
$platform_stats = ['courses' => 0, 'students' => 0, 'resources' => 0];
try {
    $platform_stats['courses'] = $pdo->query("SELECT COUNT(*) FROM content WHERE record_type = 'course' AND is_deleted = 0 AND is_approved = 1")->fetchColumn();
    $platform_stats['students'] = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'student'")->fetchColumn();
    $platform_stats['resources'] = $pdo->query("SELECT COUNT(*) FROM content WHERE record_type = 'resource' AND is_deleted = 0 AND is_approved = 1")->fetchColumn();
} catch (PDOException $e) {
    // Graceful fallback to legacy static values
    $platform_stats = ['courses' => 20, 'students' => 18, 'resources' => 17];
}




// Data strictly parsed from user requirements
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

$categories = [];
try {
    $db_cats = $pdo->query("SELECT name FROM categories ORDER BY name ASC")->fetchAll(PDO::FETCH_COLUMN);
    foreach ($db_cats as $cat_name) {
        $categories[$cat_name] = $categories_icons[$cat_name] ?? '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"></path></svg>';
    }
} catch (PDOException $e) {
    // Fallback to defaults
    $categories = $categories_icons;
}
if (empty($categories))
    $categories = $categories_icons;



// Difficulty Color Mapping
$diff_colors = [
    'Beginner' => '#3b82f6', // blue
    'Intermediate' => '#f59e0b', // orange
    'Advanced' => '#ef4444' // red
];

require_once 'includes/header.php';
?>

<main>
    <!-- == HERO SECTION == -->
    <div id="home">

        <section class="hero-section">
            <span class="badge-glass animate-pulse"
                style="font-size: 1.15rem; padding: 10px 24px; font-weight: 500;">Learn Without Limits</span>
            <h1 class="hero-title animate-on-scroll stagger-1">
                <span class="text-accent">Download</span>, Learn, <span class="hero-animated-gradient">Progress.</span>
            </h1>
            <p class="hero-subtitle animate-on-scroll stagger-2">
                E-COURSE PLATFORM. Seamlessly download, learn offline, and sync your progress locally.
            </p>
            <div class="animate-on-scroll stagger-3" style="display: flex; gap: 15px; z-index: 2;">
                <a href="signup.php" class="btn-neon card-hover">Get Started <span>→</span></a>
            </div>
        </section>

        <!-- Stats Section from the JU Learns Image -->
        <section class="stats-grid">
            <div class="glass-panel stat-card animate-on-scroll stagger-1 card-hover">
                <div class="stat-icon float">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                        <polyline points="14 2 14 8 20 8"></polyline>
                        <line x1="16" y1="13" x2="8" y2="13"></line>
                        <line x1="16" y1="17" x2="8" y2="17"></line>
                        <polyline points="10 9 9 9 8 9"></polyline>
                    </svg>
                </div>
                <h3 id="stat-courses"><?= number_format($platform_stats['courses']) ?></h3>
                <p>Courses</p>
            </div>
            <div class="glass-panel stat-card animate-on-scroll stagger-2 card-hover">
                <div class="stat-icon float" style="animation-delay: 0.5s;">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                        <circle cx="9" cy="7" r="4"></circle>
                        <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                        <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                    </svg>
                </div>
                <h3 id="stat-students"><?= number_format($platform_stats['students']) ?></h3>
                <p>Students</p>
            </div>
            <div class="glass-panel stat-card animate-on-scroll stagger-3 card-hover">
                <div class="stat-icon float" style="animation-delay: 1s;">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
                        <line x1="3" y1="9" x2="21" y2="9"></line>
                        <line x1="9" y1="21" x2="9" y2="9"></line>
                    </svg>
                </div>
                <h3 id="stat-resources"><?= number_format($platform_stats['resources']) ?></h3>
                <p>Resources</p>
            </div>
            <div class="glass-panel stat-card animate-on-scroll stagger-4 card-hover">
                <div class="stat-icon float" style="animation-delay: 1.5s;">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"></circle>
                        <polyline points="12 6 12 12 16 14"></polyline>
                    </svg>
                </div>
                <h3>24/7</h3>
                <p>Availability</p>
            </div>
        </section>



    </div>

    <!-- == ABOUT SECTION == -->
    <div id="about">

        <!-- About Header -->
        <div style="text-align: center; padding: 6rem 20px 2rem; position: relative;">
            <div class="bg-gradient-spot glow-blue"
                style="width: 400px; height: 400px; top: -100px; left: 50%; transform: translateX(-50%); opacity: 0.15;">
            </div>
            <h1 class="animate-up" style="font-size: 3.5rem; font-weight: 800; margin-bottom: 1rem;">About <span
                    class="text-gradient">JU Learns</span></h1>
            <p class="animate-up animate-delay-1"
                style="color: var(--text-secondary); max-width: 700px; margin: 0 auto; font-size: 1.15rem; line-height: 1.8;">
                Organized learning materials, offline access, and progress tracking — designed by students, for
                students.
            </p>
        </div>

        <!-- Who We Are Section -->
        <section class="about-section who-we-are-grid">
            <div class="animate-up">
                <span class="section-label">Who We Are</span>
                <h2 class="section-title">A Platform Built from Real Student Struggles</h2>
                <p style="color: var(--text-secondary); line-height: 1.8; margin-bottom: 1.5rem;">
                    JU Learns was born from a simple observation: students struggle to find organized, accessible
                    learning materials in one place. As IT students ourselves, we experienced firsthand the frustration
                    of unstable internet, scattered notes across multiple messaging apps, and no clear way to track what
                    we've learned.
                </p>
                <p style="color: var(--text-secondary); line-height: 1.8; margin-bottom: 1.5rem;">
                    Our team came together to solve this problem. Under the guidance of our advisors, we designed a
                    platform that puts learners first — providing a centralized space where learners can access
                    organized academic materials, download content for offline study, and monitor their learning journey
                    through an intuitive dashboard.
                </p>
                <p style="color: var(--text-secondary); line-height: 1.8;">
                    Our platform is designed for students seeking structured learning materials and a seamless
                    academic experience, bridging the gap between traditional study and digital accessibility.
                </p>
            </div>

            <div class="stat-list animate-up animate-delay-1">
                <div class="stat-list-card">
                    <div class="stat-icon"
                        style="background: rgba(0, 229, 255, 0.15); color: var(--accent-cyan); border: 1px solid rgba(0, 229, 255, 0.3);">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                            stroke-width="2">
                            <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                            <circle cx="9" cy="7" r="4"></circle>
                            <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                            <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                        </svg>
                    </div>
                    <div class="stat-content">
                        <h4 id="about-stat-students"><?= number_format($platform_stats['students']) ?>+ Students</h4>
                        <p>Active learners on the platform</p>
                    </div>
                </div>

                <div class="stat-list-card">
                    <div class="stat-icon"
                        style="background: rgba(0, 229, 153, 0.15); color: var(--accent-green); border: 1px solid var(--accent-green-glow);">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                            stroke-width="2">
                            <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"></path>
                            <path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"></path>
                        </svg>
                    </div>
                    <div class="stat-content">
                        <h4 id="about-stat-courses"><?= number_format($platform_stats['courses']) ?>+ Courses</h4>
                        <p>Across multiple categories</p>
                    </div>
                </div>

                <div class="stat-list-card">
                    <div class="stat-icon"
                        style="background: rgba(62, 139, 255, 0.15); color: var(--accent-blue); border: 1px solid rgba(62, 139, 255, 0.3);">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                            stroke-width="2">
                            <path d="M5 12.55a11 11 0 0 1 14.08 0"></path>
                            <path d="M1.42 9a16 16 0 0 1 21.16 0"></path>
                            <path d="M8.53 16.11a6 6 0 0 1 6.95 0"></path>
                            <line x1="12" y1="20" x2="12.01" y2="20"></line>
                        </svg>
                    </div>
                    <div class="stat-content">
                        <h4>Offline Access</h4>
                        <p>Learn without internet connectivity</p>
                    </div>
                </div>

                <div class="stat-list-card">
                    <div class="stat-icon"
                        style="background: rgba(13, 148, 136, 0.15); color: var(--accent-teal); border: 1px solid rgba(13, 148, 136, 0.3);">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                            stroke-width="2">
                            <circle cx="12" cy="8" r="7"></circle>
                            <polyline points="8.21 13.89 7 23 12 20 17 23 15.79 13.88"></polyline>
                        </svg>
                    </div>
                    <div class="stat-content">
                        <h4>Rewards System</h4>
                        <p>Get Certified</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Mission & Vision -->
        <section class="about-section" style="text-align: center;">
            <span class="section-label animate-up">Our Purpose</span>
            <h2 class="section-title animate-up">Mission & Vision</h2>

            <div class="mission-grid">
                <div class="mission-card animate-up animate-delay-1" style="text-align: left;">
                    <div class="mission-icon"
                        style="background: linear-gradient(135deg, var(--accent-blue), var(--accent-green));">
                        <svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2">
                            <circle cx="12" cy="12" r="10"></circle>
                            <circle cx="12" cy="12" r="6"></circle>
                            <circle cx="12" cy="12" r="2"></circle>
                        </svg>
                    </div>
                    <h3 style="font-size: 1.5rem; margin-bottom: 1rem;">Our Mission</h3>
                    <p style="color: var(--text-secondary); line-height: 1.8;">
                        To empower learners with flexible, accessible, and organized educational content_resources that
                        support continuous learning regardless of internet connectivity.
                    </p>
                </div>

                <div class="mission-card animate-up animate-delay-2" style="text-align: left;">
                    <div class="mission-icon"
                        style="background: linear-gradient(135deg, var(--accent-cyan), var(--accent-blue));">
                        <svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2">
                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                            <circle cx="12" cy="12" r="3"></circle>
                        </svg>
                    </div>
                    <h3 style="font-size: 1.5rem; margin-bottom: 1rem;">Our Vision</h3>
                    <p style="color: var(--text-secondary); line-height: 1.8;">
                        To become a trusted learning companion that helps students build knowledge, track growth, and
                        achieve their academic goals through simple, effective technology.
                    </p>
                </div>
            </div>
        </section>

        <!-- What We Do -->
        <section class="about-section" style="text-align: center; margin-bottom: 4rem;">
            <span class="section-label animate-up">What We Do</span>
            <h2 class="section-title animate-up">A Complete Learning Environment</h2>
            <p style="color: var(--text-secondary); margin: 0 auto; max-width: 600px;" class="animate-up">
                We provide a web-based learning environment built around the real needs of students and instructors.
            </p>

            <div class="env-grid">
                <div class="env-card animate-up animate-delay-1">
                    <div class="env-icon" style="background: rgba(0, 229, 153, 0.1); color: var(--accent-green);">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                            stroke-width="2">
                            <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
                            <line x1="3" y1="9" x2="21" y2="9"></line>
                            <line x1="9" y1="21" x2="9" y2="9"></line>
                        </svg>
                    </div>
                    <h4 style="margin-bottom: 0.8rem;">Organized Content</h4>
                    <p style="font-size: 0.85rem; color: var(--text-secondary);">Categorized content_courses, notes, and
                        materials — easy to find, easy to follow.</p>
                </div>

                <div class="env-card animate-up animate-delay-2">
                    <div class="env-icon" style="background: rgba(0, 229, 255, 0.1); color: var(--accent-cyan);">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                            stroke-width="2">
                            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                            <polyline points="7 10 12 15 17 10"></polyline>
                            <line x1="12" y1="15" x2="12" y2="3"></line>
                        </svg>
                    </div>
                    <h4 style="margin-bottom: 0.8rem;">Offline Downloads</h4>
                    <p style="font-size: 0.85rem; color: var(--text-secondary);">Download content and study anywhere,
                        even without an internet connection.</p>
                </div>

                <div class="env-card animate-up animate-delay-3">
                    <div class="env-icon" style="background: rgba(62, 139, 255, 0.1); color: var(--accent-blue);">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                            stroke-width="2">
                            <line x1="18" y1="20" x2="18" y2="10"></line>
                            <line x1="12" y1="20" x2="12" y2="4"></line>
                            <line x1="6" y1="20" x2="6" y2="14"></line>
                        </svg>
                    </div>
                    <h4 style="margin-bottom: 0.8rem;">Progress Tracking</h4>
                    <p style="font-size: 0.85rem; color: var(--text-secondary);">Dashboards that show completed topics,
                        progress_streaks, and achievements.</p>
                </div>

                <div class="env-card animate-up animate-delay-4">
                    <div class="env-icon" style="background: rgba(13, 148, 136, 0.1); color: var(--accent-teal);">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                            stroke-width="2">
                            <circle cx="12" cy="8" r="7"></circle>
                            <polyline points="8.21 13.89 7 23 12 20 17 23 15.79 13.88"></polyline>
                        </svg>
                    </div>
                    <h4 style="margin-bottom: 0.8rem;">Points & Badges</h4>
                    <p style="font-size: 0.85rem; color: var(--text-secondary);">Gamified rewards that motivate
                        consistent, goal-driven learning.</p>
                </div>

                <div class="env-card animate-up animate-delay-5">
                    <div class="env-icon" style="background: rgba(0, 229, 153, 0.1); color: var(--accent-green);">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                            stroke-width="2">
                            <path d="M22 10v6M2 10l10-5 10 5-10 5z"></path>
                        </svg>
                    </div>
                    <h4 style="margin-bottom: 0.8rem;">Academic Excellence</h4>
                    <p style="font-size: 0.85rem; color: var(--text-secondary);">Access curated materials from top
                        academic performers and certified university resources.</p>
                </div>


            </div>
        </section>

        <!-- Why It Matters -->
        <section class="about-section learning-matters animate-up">
            <span class="section-label">Why It Matters</span>
            <h2 class="section-title" style="margin-bottom: 2rem;">Learning Should Never Stop</h2>
            <p style="color: var(--text-secondary); line-height: 1.8; max-width: 800px; margin: 0 auto 1.5rem;">
                In regions where internet is expensive or unreliable, digital learning often fails. Our platform ensures
                that a weak connection never means the end of learning. By combining offline access with structured
                content and progress tracking, we make self-directed learning practical and achievable.
            </p>
            <p style="color: var(--text-secondary); line-height: 1.8; max-width: 800px; margin: 0 auto 3rem;">
                We are committed to continuous improvement, user-centered design, and educational accessibility. JU
                Learns is more than a project — it's our contribution to making education work for every student,
                everywhere.
            </p>
            <div style="display: flex; gap: 15px; justify-content: center;">
                <!-- <a href="content_courses.php" class="btn-neon" style="background: linear-gradient(90deg, var(--accent-blue), var(--accent-green)); color: #fff; padding: 12px 28px;">Explore Courses ➜</a>
            <a href="signup.php" class="btn-outline-neon" style="padding: 12px 28px; color: var(--accent-cyan); border-color: var(--accent-cyan);">Get Started Free</a> -->
            </div>
        </section>




    </div>

    <!-- == COURSES SECTION == -->
    <div id="content_courses" style="max-width: 1200px; margin: 0 auto; padding: 4rem 20px;">


        <!-- Search Area -->
        <div class="search-wrapper animate-on-scroll stagger-1">
            <svg class="search-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                stroke-width="2">
                <circle cx="11" cy="11" r="8"></circle>
                <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
            </svg>
            <input type="text" id="courseSearch" class="search-input" placeholder="Search content_courses...">
        </div>

        <div class="filter-tabs animate-on-scroll stagger-2">
            <a href="#" class="filter-tab filter-btn active" data-filter="all">All Recommended</a>
            <a href="#" class="filter-tab filter-btn" data-filter="web programming">Web Programming</a>
            <a href="#" class="filter-tab filter-btn" data-filter="mobile programming">Mobile Programming</a>
            <a href="#" class="filter-tab filter-btn" data-filter="ui design">UI Design</a>
            <a href="#" class="filter-tab filter-btn" data-filter="backend development">Backend Development</a>
            <a href="#" class="filter-tab filter-btn" data-filter="adobe illustrator">Adobe Illustrator</a>
        </div>

        <div class="course-grid-3">
            <?php foreach ($all_content_courses as $i => $course):
                $level = isset($course['level']) ? $course['level'] : 'Beginner';
                
                // User requirement: make the tag in course what the admin sets
                $course_tags_arr = !empty($course['tags']) ? explode(',', $course['tags']) : [];
                $badge_text = !empty($course_tags_arr) ? trim($course_tags_arr[0]) : $level;

                // Color mapping strictly to Neon Green, Cyan, Deep Blue, and Teal as requested
                $bg_color = '#00E599'; // Neon Green for Beginner default
                $lvl_lower = strtolower($level);
                if ($lvl_lower === 'intermediate') {
                    $bg_color = '#00e5ff'; // Cyan
                } elseif ($lvl_lower === 'advanced') {
                    $bg_color = '#0284c7'; // Deep Blue
                } elseif ($lvl_lower === 'trending') {
                    $bg_color = '#0d9488'; // Teal
                }
                $bg_grad = "linear-gradient(135deg, " . $bg_color . "CC, " . $bg_color . ")";

                // Real category directly from database (no overriding)
                $cat = isset($course['category']) ? $course['category'] : 'web programming';
                ?>
                <div class="custom-card card-hover animate-on-scroll stagger-3"
                    data-category="<?= htmlspecialchars($cat) ?>">
                    <!-- Card Top with Gradients -->
                    <?php
                    $thumb = !empty($course['thumbnail_path']) ? ltrim($course['thumbnail_path'], '/') : '';
                    $has_thumb = !empty($thumb) && file_exists($thumb);
                    $card_bg = $has_thumb ? "url('{$thumb}') center/cover no-repeat" : $bg_grad;
                    ?>
                    <div
                        style="height: 180px; position:relative; background: <?= $card_bg ?>; display:flex; align-items:center; padding: 20px;">

                        <?php if ($has_thumb): ?>
                            <!-- Overlay to ensure text readability -->
                            <div style="position: absolute; inset: 0; background: rgba(0,0,0,0.4); z-index: 1;"></div>
                        <?php endif; ?>

                        <!-- Badge -->
                        <span
                            style="position: absolute; top: 12px; left: 12px; background: white; color: black; padding: 4px 12px; border-radius: 20px; font-size: 0.75rem; font-weight: 700; box-shadow: 0 4px 10px rgba(0,0,0,0.1); z-index: 2;">
                            <?= $badge_text ?>
                        </span>



                        <h2
                            style="font-size:1.3rem; max-width: 85%; color: #fff; line-height: 1.3; text-transform: uppercase; font-weight: 800; margin: 0; text-shadow: 0 2px 4px rgba(0,0,0,0.2); z-index: 2;">
                            <?= htmlspecialchars(strtoupper(isset($course['title']) ? $course['title'] : 'COURSE')) ?>
                        </h2>
                    </div>

                    <!-- Card Bottom -->
                    <div style="padding: 1.5rem; display: flex; flex-direction: column; flex-grow: 1;">
                        <h3 class="course-title"
                            style="font-size: 1.1rem; font-weight: 700; margin-bottom: 0.5rem; text-transform: uppercase; color: var(--text-primary);">
                            <?= htmlspecialchars($course['title']) ?>
                        </h3>

                        <div
                            style="color: var(--text-secondary); display: flex; align-items: center; gap: 8px; font-size: 0.85rem; margin-bottom: 0.5rem; font-weight: 500;">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor" stroke="none">
                                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                                <circle cx="12" cy="7" r="4"></circle>
                            </svg>
                            <?= htmlspecialchars($course['instructor_name'] ?? 'Instructor Name') ?>
                        </div>

                        <?php if (!empty($course['tags'])): ?>
                            <div class="d-flex flex-wrap gap-1 mb-3">
                                <?php foreach (explode(',', $course['tags']) as $tag): ?>
                                    <span class="badge bg-secondary bg-opacity-25 text-white-50 fw-normal" style="font-size: 0.65rem; padding: 2px 8px; border-radius: 4px;">
                                        #<?= htmlspecialchars(trim($tag)) ?>
                                    </span>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <div class="course-desc-container" style="margin-bottom: 1rem;">
                            <p id="desc-<?= $course['id'] ?>"
                                style="font-size: 0.85rem; color: var(--text-secondary); margin-bottom: 0.5rem; line-height: 1.6; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;">
                                <?= htmlspecialchars($course['description'] ?? 'Master the principles of ' . $course['title'] . ' with expert-led instructions.') ?>
                            </p>
                            <?php if (strlen($course['description'] ?? '') > 100): ?>
                                <a href="javascript:void(0)" onclick="toggleDesc(<?= $course['id'] ?>, this)"
                                    class="text-accent small fw-bold text-decoration-none"
                                    style="font-size: 0.75rem; color: var(--accent-green); position: relative; z-index: 10;">See More</a>
                            <?php endif; ?>
                        </div>

                        <div
                            style="color: #fbbf24; font-size: 0.8rem; margin-bottom: 1rem; display: flex; align-items: center; gap: 5px;">
                            <span style="letter-spacing: 2px;">
                                <?php
                                $rating = round($course['avg_rating'] ?: 5);
                                echo str_repeat('★', $rating) . str_repeat('☆', 5 - $rating);
                                ?>
                            </span>
                            <span
                                style="color: var(--text-secondary); font-size: 0.75rem;">(<?= $course['review_count'] ?>)</span>
                        </div>

                        <div style="margin-top: auto; position: relative; z-index: 5;">
                            <a href="signup.php?course_id=<?= $course['id'] ?>" class="btn-neon stretched-link"
                                style="color:#000; padding: 10px 20px; font-size:0.85rem; display:block; text-align:center; border-color:transparent; background-color: var(--accent-green); border-radius: 100px; font-weight: 600; transition: transform 0.2s;">Enroll
                                Now</a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>

            <?php if (empty($all_content_courses)): ?>
                <div style="grid-column: 1/-1; text-align: center; color: var(--text-secondary); padding: 4rem;">
                    No content_courses found in the database.
                </div>
            <?php endif; ?>
        </div>

    </div>

    <!-- == RESOURCES SECTION == -->
    <div id="content_resources">

        <section class="skills-header">
            <h1 class="animate-up" style="font-size: 3rem; margin-bottom: 1rem;">
                Resources <span class="text-accent">Directory</span>
            </h1>
            <p class="animate-up animate-delay-1"
                style="color: var(--text-secondary); max-width: 600px; margin: 0 auto;">
                Explore over 100+ professional competencies categorised by industry domains to accelerate your learning
                path.
            </p>

            <div class="search-wrapper animate-up animate-delay-2">
                <svg class="search-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                    stroke-width="2">
                    <circle cx="11" cy="11" r="8"></circle>
                    <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                </svg>
                <input type="text" id="searchInput" class="search-input">
            </div>

            <div class="filter-container animate-up animate-delay-3" id="filterContainer">
                <button class="filter-btn active" data-filter="all">All Resources</button>
                <?php foreach ($categories as $name => $icon): ?>
                    <button class="filter-btn"
                        data-filter="<?= htmlspecialchars($name) ?>"><?= htmlspecialchars($name) ?></button>
                <?php endforeach; ?>
            </div>
        </section>

        <div class="skills-grid" id="skillsGrid">
            <?php foreach ($raw_skills as $index => $skill): ?>
                <?php
                $catName = $skill['c'];
                $svgIcon = $categories[$catName];
                $diffColor = $diff_colors[$skill['d']];
                ?>
                <div class="skill-card animate-up" data-category="<?= htmlspecialchars($catName) ?>"
                    data-title="<?= htmlspecialchars(strtolower($skill['t'])) ?>"
                    style="animation-delay: <?= ($index % 10) * 0.05 ?>s">
                    <div class="skill-icon-wrap">
                        <?= $svgIcon ?>
                    </div>
                    <div class="skill-info">
                        <h3><?= htmlspecialchars($skill['t']) ?></h3>
                        <div class="skill-category"><?= htmlspecialchars($catName) ?></div>
                        
                        <?php if (!empty($skill['tags'])): ?>
                            <div class="d-flex flex-wrap gap-1 mb-2">
                                <?php foreach (explode(',', $skill['tags']) as $tag): ?>
                                    <span style="font-size: 0.6rem; color: var(--accent-green); opacity: 0.8;">
                                        #<?= htmlspecialchars(trim($tag)) ?>
                                    </span>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                        <span class="difficulty-badge" style="color: <?= $diffColor ?>; background: <?= $diffColor ?>22;">
                            <?= htmlspecialchars($skill['d']) ?>
                        </span>
                        
                        <div style="margin-top: 1rem; position: relative; z-index: 5;">
                             <a href="signup.php?resource_id=<?= $skill['id'] ?>" class="btn-neon stretched-link" style="font-size: 0.7rem; padding: 4px 12px; border-radius: 100px; background-color: var(--accent-green); color: #000; font-weight: 600; display: inline-block; text-decoration: none;">Read Content</a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

    </div>

</main>
<script>

    document.addEventListener('DOMContentLoaded', () => {
        const tabs = document.querySelectorAll('.filter-tab');
        const searchInput = document.getElementById('courseSearch');
        const container = document.querySelector('.course-grid-3');

        // Use efficient Javascript array filtering
        const allCards = Array.from(document.querySelectorAll('.custom-card'));

        // Build the "No content_courses found" state message dynamically
        let noResultsMsg = document.createElement('div');
        noResultsMsg.id = 'no-results-msg';
        noResultsMsg.style.cssText = 'grid-column: 1/-1; text-align: center; color: var(--text-secondary); padding: 4rem; display: none; font-size: 1.2rem;';
        noResultsMsg.innerText = 'No content_courses found';
        container.appendChild(noResultsMsg);

        let debounceTimer;

        function applyFilters() {
            const activeTab = document.querySelector('.filter-tab.active');
            const filterCategory = activeTab ? activeTab.dataset.filter : 'all';
            const searchTerm = searchInput.value.toLowerCase().trim();

            // Efficient .filter() methodology 
            const filteredCards = allCards.filter(card => {
                const cardCategory = card.dataset.category || '';
                const courseTitle = card.querySelector('.course-title').innerHTML.toLowerCase().replace(/<[^>]*>?/gm, ''); // strip potential html for clean match

                const matchesCategory = (filterCategory === 'all' || cardCategory === filterCategory);
                const matchesSearch = courseTitle.includes(searchTerm);

                return matchesCategory && matchesSearch;
            });

            // Clear previous results before showing new ones
            container.innerHTML = '';

            // Dynamically update the DOM container
            if (filteredCards.length > 0) {
                noResultsMsg.style.display = 'none';
                filteredCards.forEach(card => {
                    // Ensure cards are fully visible (overriding any historical filtered-out CSS configs)
                    card.classList.remove('filtered-out');
                    card.style.display = 'flex';

                    // Optional: Highlight matched text
                    const titleElement = card.querySelector('.course-title');
                    const originalText = titleElement.innerText;
                    if (searchTerm !== "") {
                        // Find bounds for case-insensitive match
                        const index = originalText.toLowerCase().indexOf(searchTerm);
                        if (index >= 0) {
                            const matchedText = originalText.substring(index, index + searchTerm.length);
                            const beforeText = originalText.substring(0, index);
                            const afterText = originalText.substring(index + searchTerm.length);
                            titleElement.innerHTML = `${beforeText}<span style="background-color: rgba(0, 229, 153, 0.3); color: var(--accent-green); padding: 0 2px; border-radius: 4px;">${matchedText}</span>${afterText}`;
                        }
                    } else {
                        titleElement.innerHTML = originalText;
                    }

                    container.appendChild(card);
                });
            } else {
                noResultsMsg.style.display = 'block';
                container.appendChild(noResultsMsg);
            }
        }

        tabs.forEach(tab => {
            tab.addEventListener('click', (e) => {
                e.preventDefault();
                tabs.forEach(t => t.classList.remove('active'));
                tab.classList.add('active');
                applyFilters();
            });
        });

        // Debouncing event listener for optimal performance
        if (searchInput) {
            searchInput.addEventListener('input', () => {
                clearTimeout(debounceTimer);
                debounceTimer = setTimeout(() => {
                    applyFilters();
                }, 300);
            });
        }
    });

</script>
<script>

    document.addEventListener('DOMContentLoaded', () => {
        const searchInput = document.getElementById('searchInput');
        const filterBtns = document.querySelectorAll('.filter-btn');
        const skillCards = document.querySelectorAll('.skill-card');

        let currentFilter = 'all';
        let currentSearch = '';

        function runFilters() {
            skillCards.forEach(card => {
                const title = card.getAttribute('data-title');
                const category = card.getAttribute('data-category');

                const matchesSearch = title.includes(currentSearch);
                const matchesFilter = currentFilter === 'all' || category === currentFilter;

                if (matchesSearch && matchesFilter) {
                    card.style.display = 'flex';
                } else {
                    card.style.display = 'none';
                }
            });
        }

        searchInput.addEventListener('input', (e) => {
            currentSearch = e.target.value.toLowerCase();
            runFilters();
        });

        filterBtns.forEach(btn => {
            btn.addEventListener('click', () => {
                // Update active state
                filterBtns.forEach(b => b.classList.remove('active'));
                btn.classList.add('active');

                // Update filter and run
                currentFilter = btn.getAttribute('data-filter');
                runFilters();
            });
        });
    });

    function toggleDesc(id, el) {
        const p = document.getElementById('desc-' + id);
        if (p.style.display === '-webkit-box') {
            p.style.display = 'block';
            el.innerText = 'See Less';
        } else {
            p.style.display = '-webkit-box';
            el.innerText = 'See More';
        }
    }
</script>
<script>
    // Live Stats Updates
    function updateLiveStats() {
        fetch('api/get_platform_stats.php')
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success') {
                    const stats = data.data;

                    // Helper to animate count if needed, or just update
                    const updateEl = (id, val, suffix = '') => {
                        const el = document.getElementById(id);
                        if (el) {
                            const formatted = new Intl.NumberFormat().format(val);
                            el.innerText = formatted + suffix;
                        }
                    };

                    updateEl('stat-courses', stats.courses);
                    updateEl('stat-students', stats.students);
                    updateEl('stat-resources', stats.resources);
                    updateEl('about-stat-students', stats.students, '+ Students');
                    updateEl('about-stat-courses', stats.courses, '+ Courses');
                }
            })
            .catch(err => console.error('Stats fetch failed', err));
    }

    // Update every 30 seconds
    setInterval(updateLiveStats, 30000);
</script>
<?php require_once 'includes/footer.php'; ?>