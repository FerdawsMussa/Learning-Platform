<?php
session_start();
require_once '../api/db.php';

// Route protection enabled
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'instructor') {
    header("Location: ../login.php");
    exit;
}

// Fetch basic analytics
$creator_id = $_SESSION['user_id'];
$course_count = 0;
$resource_count = 0;

$stmt = $pdo->prepare("SELECT COUNT(*) FROM (SELECT id, user_id AS creator_id, title, description, file_path AS thumbnail_path, category, generic_value AS level, meta_text AS tags, is_resource, is_deleted, deleted_at, created_at FROM content WHERE record_type = 'course') content_courses WHERE creator_id = ? AND is_deleted = 0");
$stmt->execute([$creator_id]);
$course_count = $stmt->fetchColumn();

$stmt2 = $pdo->prepare("SELECT COUNT(*) FROM (SELECT id, user_id AS creator_id, title, file_path, generic_value AS file_type, category, is_deleted, deleted_at, created_at FROM content WHERE record_type = 'resource') content_resources WHERE creator_id = ? AND is_deleted = 0");
$stmt2->execute([$creator_id]);
$resource_count = $stmt2->fetchColumn();

// Load 100 global skills for category filtering and group them
$raw_skills = [
    // 1. Programming
    ['c' => 'Programming & Development', 't' => 'HTML'], ['c' => 'Programming & Development', 't' => 'CSS'], ['c' => 'Programming & Development', 't' => 'JavaScript'], ['c' => 'Programming & Development', 't' => 'PHP'], ['c' => 'Programming & Development', 't' => 'Python'], ['c' => 'Programming & Development', 't' => 'Java'], ['c' => 'Programming & Development', 't' => 'C++'], ['c' => 'Programming & Development', 't' => 'C#'], ['c' => 'Programming & Development', 't' => 'TypeScript'], ['c' => 'Programming & Development', 't' => 'Go'],
    // 2. Web Dev
    ['c' => 'Web Development', 't' => 'Frontend Development'], ['c' => 'Web Development', 't' => 'Backend Development'], ['c' => 'Web Development', 't' => 'Full Stack Development'], ['c' => 'Web Development', 't' => 'Responsive Design'], ['c' => 'Web Development', 't' => 'REST API Development'], ['c' => 'Web Development', 't' => 'Web Security Basics'], ['c' => 'Web Development', 't' => 'Progressive Web Apps (PWA)'], ['c' => 'Web Development', 't' => 'Web Performance Optimization'], ['c' => 'Web Development', 't' => 'Cross-Browser Compatibility'], ['c' => 'Web Development', 't' => 'SEO Basics'],
    // 3. UI/UX
    ['c' => 'UI/UX & Design', 't' => 'UI Design'], ['c' => 'UI/UX & Design', 't' => 'UX Design'], ['c' => 'UI/UX & Design', 't' => 'Figma'], ['c' => 'UI/UX & Design', 't' => 'Adobe XD'], ['c' => 'UI/UX & Design', 't' => 'Photoshop'], ['c' => 'UI/UX & Design', 't' => 'Illustrator'], ['c' => 'UI/UX & Design', 't' => 'Wireframing'], ['c' => 'UI/UX & Design', 't' => 'Prototyping'], ['c' => 'UI/UX & Design', 't' => 'Design Systems'], ['c' => 'UI/UX & Design', 't' => 'User Research'],
    // 4. Data
    ['c' => 'Data & Analytics', 't' => 'Data Analysis'], ['c' => 'Data & Analytics', 't' => 'Data Visualization'], ['c' => 'Data & Analytics', 't' => 'Excel'], ['c' => 'Data & Analytics', 't' => 'Power BI'], ['c' => 'Data & Analytics', 't' => 'Tableau'], ['c' => 'Data & Analytics', 't' => 'SQL'], ['c' => 'Data & Analytics', 't' => 'Data Cleaning'], ['c' => 'Data & Analytics', 't' => 'Statistics Basics'], ['c' => 'Data & Analytics', 't' => 'Big Data Fundamentals'], ['c' => 'Data & Analytics', 't' => 'Data Interpretation'],
    // 5. AI
    ['c' => 'AI & Machine Learning', 't' => 'Machine Learning'], ['c' => 'AI & Machine Learning', 't' => 'Deep Learning'], ['c' => 'AI & Machine Learning', 't' => 'Natural Language Processing (NLP)'], ['c' => 'AI & Machine Learning', 't' => 'Computer Vision'], ['c' => 'AI & Machine Learning', 't' => 'TensorFlow'], ['c' => 'AI & Machine Learning', 't' => 'PyTorch'], ['c' => 'AI & Machine Learning', 't' => 'AI Fundamentals'], ['c' => 'AI & Machine Learning', 't' => 'Chatbot Development'], ['c' => 'AI & Machine Learning', 't' => 'Predictive Analytics'], ['c' => 'AI & Machine Learning', 't' => 'Model Evaluation'],
    // 6. Security
    ['c' => 'Cybersecurity', 't' => 'Ethical Hacking'], ['c' => 'Cybersecurity', 't' => 'Network Security'], ['c' => 'Cybersecurity', 't' => 'Cryptography'], ['c' => 'Cybersecurity', 't' => 'Cyber Threat Analysis'], ['c' => 'Cybersecurity', 't' => 'Penetration Testing'], ['c' => 'Cybersecurity', 't' => 'Security Auditing'], ['c' => 'Cybersecurity', 't' => 'Risk Management'], ['c' => 'Cybersecurity', 't' => 'Digital Forensics'], ['c' => 'Cybersecurity', 't' => 'Identity & Access Management'], ['c' => 'Cybersecurity', 't' => 'Secure Coding'],
    // 7. Mobile
    ['c' => 'Mobile App Development', 't' => 'Android Development'], ['c' => 'Mobile App Development', 't' => 'iOS Development'], ['c' => 'Mobile App Development', 't' => 'Flutter'], ['c' => 'Mobile App Development', 't' => 'React Native'], ['c' => 'Mobile App Development', 't' => 'Mobile UI Design'], ['c' => 'Mobile App Development', 't' => 'App Testing'], ['c' => 'Mobile App Development', 't' => 'Mobile Security'], ['c' => 'Mobile App Development', 't' => 'App Deployment'], ['c' => 'Mobile App Development', 't' => 'API Integration'], ['c' => 'Mobile App Development', 't' => 'Cross-Platform Development'],
    // 8. Soft
    ['c' => 'Soft Skills', 't' => 'Communication Skills'], ['c' => 'Soft Skills', 't' => 'Teamwork'], ['c' => 'Soft Skills', 't' => 'Problem Solving'], ['c' => 'Soft Skills', 't' => 'Critical Thinking'], ['c' => 'Soft Skills', 't' => 'Time Management'], ['c' => 'Soft Skills', 't' => 'Leadership'], ['c' => 'Soft Skills', 't' => 'Creativity'], ['c' => 'Soft Skills', 't' => 'Adaptability'], ['c' => 'Soft Skills', 't' => 'Decision Making'], ['c' => 'Soft Skills', 't' => 'Conflict Resolution'],
    // 9. Business
    ['c' => 'Business & Career Skills', 't' => 'Project Management'], ['c' => 'Business & Career Skills', 't' => 'Agile & Scrum'], ['c' => 'Business & Career Skills', 't' => 'Digital Marketing'], ['c' => 'Business & Career Skills', 't' => 'Content Writing'], ['c' => 'Business & Career Skills', 't' => 'Public Speaking'], ['c' => 'Business & Career Skills', 't' => 'Entrepreneurship'], ['c' => 'Business & Career Skills', 't' => 'Business Analysis'], ['c' => 'Business & Career Skills', 't' => 'Customer Service'], ['c' => 'Business & Career Skills', 't' => 'Sales Skills'], ['c' => 'Business & Career Skills', 't' => 'Negotiation'],
    // 10. Tools
    ['c' => 'Tools & Technologies', 't' => 'Git & GitHub'], ['c' => 'Tools & Technologies', 't' => 'Docker'], ['c' => 'Tools & Technologies', 't' => 'Kubernetes'], ['c' => 'Tools & Technologies', 't' => 'AWS (Cloud Computing)'], ['c' => 'Tools & Technologies', 't' => 'Firebase'], ['c' => 'Tools & Technologies', 't' => 'Linux'], ['c' => 'Tools & Technologies', 't' => 'DevOps Basics'], ['c' => 'Tools & Technologies', 't' => 'CI/CD'], ['c' => 'Tools & Technologies', 't' => 'Testing & Debugging'], ['c' => 'Tools & Technologies', 't' => 'Version Control']
];

$grouped_skills = [];
foreach($raw_skills as $s) {
    if(!isset($grouped_skills[$s['c']])) $grouped_skills[$s['c']] = [];
    $grouped_skills[$s['c']][] = $s['t'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>JU Learn - Creator Studio</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <style>
        .view-section { display: none; }
        .view-section.active-view { display: block; animation: fadeUp 0.4s ease forwards; }
        
        .stat-card {
            background: linear-gradient(145deg, rgba(255,255,255,0.05), rgba(255,255,255,0.01));
            border: 1px solid var(--border-light);
            border-radius: var(--radius-md);
            padding: 1.5rem;
            position: relative;
            overflow: hidden;
        }
        
        /* Analytics SVG Chart styling */
        .chart-container {
            width: 100%;
            height: 300px;
            background: rgba(255,255,255,0.02);
            border: 1px solid var(--border-light);
            border-radius: var(--radius-md);
            padding: 20px;
            position: relative;
        }
        .chart-line { stroke-dasharray: 1000; stroke-dashoffset: 1000; animation: drawLine 2s ease forwards; }
        @keyframes drawLine { to { stroke-dashoffset: 0; } }
    </style>
</head>
<body class="dashboard-body">

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
            JU<span class="text-accent">Creator</span>
        </a>
        <button class="dash-hamburger" id="dashHamburger" aria-label="Open sidebar" aria-expanded="false">
            <span></span><span></span><span></span>
        </button>
    </div>

    <!-- Sidebar overlay (mobile) -->
    <div class="dash-sidebar-overlay" id="dashSidebarOverlay"></div>

    <!-- Sidebar -->
    <aside class="sidebar" id="dashSidebar" style="border-right: 1px solid var(--border-light);">
        <div class="sidebar-header">
            <div style="width: 20px; height: 20px; background: var(--accent-green); border-radius: 4px; box-shadow: 0 0 10px var(--accent-green-glow);"></div>
            <strong style="color: var(--text-primary); font-size: 1.1rem;">JU Creator</strong>
        </div>
        <ul class="sidebar-nav" id="sidebar-menu">
            <li>
                <a href="#" class="sidebar-nav-item active" data-target="view-overview">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 3v18h18"/><path d="M18.7 8l-5.1 5.2-2.8-2.7L7 14.3"/></svg>
                    Overview
                </a>
            </li>
            <li>
                <a href="#" class="sidebar-nav-item" data-target="view-upload">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                    Upload Asset
                </a>
            </li>
            <li>
                <a href="#" class="sidebar-nav-item" data-target="view-course-builder">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect></svg>
                    Course Builder
                </a>
            </li>
            <li>
                <a href="#" class="sidebar-nav-item" data-target="view-analytics">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
                    Analytics
                </a>
            </li>
        </ul>
    </aside>

    <!-- Main Content -->
    <main class="main-content">
        <header class="topbar">
            <div class="flex-between gap-3">
                <div class="wallet-badge glass-panel" style="color: var(--accent-green); border-color: rgba(0, 229, 153, 0.3);">
                    <span class="network-dot" style="background: var(--accent-green); box-shadow: 0 0 8px var(--accent-green);"></span>
                    Studio Mode Active
                </div>
                <a href="../api/logout.php" class="btn-glass" style="color: var(--text-primary); border-color: var(--border-light);">Logout</a>
            </div>
        </header>

        <div class="dashboard-container">
            
            <!-- OVERVIEW VIEW -->
            <section id="view-overview" class="view-section active-view">
                <h2 class="page-title">Creator Overview</h2>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.5rem; margin-bottom: 2rem;">
                    <div class="stat-card">
                        <span style="color: var(--text-secondary); font-size: 0.9rem;">Total Courses Built</span>
                        <h3 style="font-size: 2.5rem; margin: 10px 0; color: var(--accent-green);"><?= htmlspecialchars($course_count) ?></h3>
                    </div>
                    <div class="stat-card">
                        <span style="color: var(--text-secondary); font-size: 0.9rem;">Standalone Resources</span>
                        <h3 style="font-size: 2.5rem; margin: 10px 0; color: var(--accent-green);"><?= htmlspecialchars($resource_count) ?></h3>
                    </div>
                    <div class="stat-card">
                        <span style="color: var(--text-secondary); font-size: 0.9rem;">Total Network Views</span>
                        <h3 style="font-size: 2.5rem; margin: 10px 0; color: var(--accent-green);">0</h3>
                    </div>
                </div>
                <div class="glass-panel" style="padding: 2rem;">
                    <h3 style="margin-bottom: 1rem;">Recent Activity</h3>
                    <p style="color: var(--text-secondary);">No recent asset uploads within the last 7 days.</p>
                </div>
            </section>

            <!-- UPLOAD ASSET VIEW -->
            <section id="view-upload" class="view-section">
                <h2 class="page-title">Direct Asset Upload</h2>
                <p style="color: var(--text-secondary); margin-bottom: 2rem;">Upload standalone PDFs, MP4s, or Presentations to the global network.</p>

                <div class="glass-panel" style="padding: 2.5rem; max-width: 800px; margin: 0 auto; border-top: 2px solid var(--accent-green);">
                    <form action="../api/upload.php" method="POST" enctype="multipart/form-data" style="display: flex; flex-direction: column; gap: 1.5rem;">
                        <input type="hidden" name="upload_type" value="resource">
                        
                        <div>
                            <label style="display: block; margin-bottom: 8px; color: var(--text-secondary);">Asset Title</label>
                            <input type="text" name="title" required style="width: 100%; padding: 12px; border-radius: 8px; background: rgba(0,0,0,0.2); border: 1px solid var(--border-light); color: #fff;">
                        </div>

                        <div style="display: flex; gap: 1.5rem;">
                            <div style="flex: 1;">
                                <label style="display: block; margin-bottom: 8px; color: var(--text-secondary);">Filter Category / Specific Skill</label>
                                <input type="hidden" name="category" id="resource_category" required>
                                
                                <div class="custom-select" style="position: relative; width: 100%;">
                                    <div id="custom-select-trigger" style="padding: 12px; border-radius: 8px; background: rgba(0,0,0,0.5); border: 1px solid var(--border-light); color: var(--text-secondary); cursor: pointer; display: flex; justify-content: space-between; align-items: center;" onclick="toggleCustomSelect()">
                                        <span id="custom-select-text">Choose a specific skill (e.g. Python, Figma)...</span>
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 9l6 6 6-6"/></svg>
                                    </div>
                                    <div id="custom-select-options" style="display: none; position: absolute; top: 100%; left: 0; right: 0; max-height: 280px; overflow-y: auto; background: var(--bg-card); border: 1px solid var(--accent-green); border-radius: 8px; z-index: 100; margin-top: 5px; box-shadow: 0 10px 40px rgba(0,0,0,0.8);">
                                        <?php foreach($grouped_skills as $catName => $skillsList): ?>
                                            <div style="padding: 10px 15px; font-size: 0.75rem; color: var(--accent-green); font-weight: bold; text-transform: uppercase; letter-spacing: 1px; background: rgba(0, 229, 153, 0.1); margin-top: 8px;"><?= htmlspecialchars($catName) ?></div>
                                            <?php foreach($skillsList as $sk): ?>
                                                <div class="skill-option" data-value="<?= htmlspecialchars($sk) ?>" style="padding: 10px 20px; color: var(--text-primary); cursor: pointer; font-size: 0.95rem; border-bottom: 1px solid rgba(255,255,255,0.02); transition: 0.2s;" onmouseover="this.style.background='var(--accent-green)'; this.style.color='#000'; this.style.paddingLeft='25px';" onmouseout="this.style.background='transparent'; this.style.color='var(--text-primary)'; this.style.paddingLeft='20px';">
                                                    <?= htmlspecialchars($sk) ?>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div>
                            <label style="display: block; margin-bottom: 8px; color: var(--text-secondary);">Attach File</label>
                            <div style="border: 2px dashed rgba(0, 229, 153, 0.4); padding: 3rem; text-align: center; border-radius: 8px; background: rgba(0, 229, 153, 0.05); cursor: pointer;" onclick="document.getElementById('fileUpload').click();">
                                <p style="color: var(--text-primary); margin-bottom: 0.5rem;">Click to inject file buffer</p>
                                <p style="color: var(--text-secondary); font-size: 0.85rem;">Valid Formats: .MP4, .PDF, .PPTX</p>
                                <input type="file" id="fileUpload" name="fileUpload" style="display: none;" onchange="document.getElementById('fileNameDisplay').textContent = this.files[0].name" required>
                                <p id="fileNameDisplay" style="margin-top: 1rem; color: var(--accent-green); font-weight: 600;"></p>
                            </div>
                        </div>

                        <button type="submit" class="btn-neon" style="color: #000; padding: 14px; text-align: center; border-radius: 100px; width: 100%; border: none;">
                            Publish Resource
                        </button>
                    </form>
                </div>
            </section>

            <!-- COURSE BUILDER VIEW -->
            <section id="view-course-builder" class="view-section">
                <h2 class="page-title">New Course Builder</h2>
                <div class="glass-panel" style="padding: 2.5rem; border-top: 2px solid var(--accent-green);">
                    <form action="../api/upload.php" method="POST" enctype="multipart/form-data" style="display: flex; flex-direction: column; gap: 1.5rem;">
                        <input type="hidden" name="upload_type" value="course">
                        
                        <div>
                            <label style="display: block; margin-bottom: 8px; color: var(--text-secondary);">Course Module Title</label>
                            <input type="text" name="title" required style="width: 100%; padding: 12px; border-radius: 8px; background: rgba(0,0,0,0.2); border: 1px solid var(--border-light); color: #fff;">
                        </div>
                        
                        <div>
                            <label style="display: block; margin-bottom: 8px; color: var(--text-secondary);">Description Outline</label>
                            <textarea name="description" rows="4" style="width: 100%; padding: 12px; border-radius: 8px; background: rgba(0,0,0,0.2); border: 1px solid var(--border-light); color: #fff;"></textarea>
                        </div>

                        <div>
                            <label style="display: block; margin-bottom: 8px; color: var(--text-secondary);">Thumbnail Poster (Cover Art)</label>
                            <input type="file" name="thumbnail" accept="image/jpeg, image/png" required style="background: rgba(0,0,0,0.2); padding: 10px; border-radius: 8px; width: 100%; border: 1px solid var(--border-light); color: #fff;">
                        </div>

                        <button type="submit" class="btn-neon" style="color: #000; padding: 14px; text-align: center; border-radius: 100px; width: 100%; border: none;">
                            Initialize Course Shell
                        </button>
                    </form>
                </div>
            </section>

            <!-- ANALYTICS VIEW -->
            <section id="view-analytics" class="view-section">
                <h2 class="page-title">30-Day Network Interrogation</h2>
                
                <div class="chart-container">
                    <!-- Inline SVG Line Chart (No external libraries required) -->
                    <svg viewBox="0 0 800 200" style="width:100%; height:100%;">
                        <!-- Grid lines -->
                        <line x1="0" y1="50" x2="800" y2="50" stroke="rgba(255,255,255,0.05)" stroke-width="1"/>
                        <line x1="0" y1="100" x2="800" y2="100" stroke="rgba(255,255,255,0.05)" stroke-width="1"/>
                        <line x1="0" y1="150" x2="800" y2="150" stroke="rgba(255,255,255,0.05)" stroke-width="1"/>
                        
                        <!-- Smooth Data Curve -->
                        <path class="chart-line" d="M0 180 Q 50 150 100 160 T 200 120 T 300 140 T 400 80 T 500 100 T 600 40 T 700 60 T 800 20" 
                            fill="none" stroke="var(--accent-green)" stroke-width="4" stroke-linecap="round"/>
                        
                        <!-- Area Gradient -->
                        <path d="M0 180 Q 50 150 100 160 T 200 120 T 300 140 T 400 80 T 500 100 T 600 40 T 700 60 T 800 20 L 800 200 L 0 200 Z" 
                            fill="url(#fade)" opacity="0.2"/>
                            
                        <defs>
                            <linearGradient id="fade" x1="0" y1="0" x2="0" y2="1">
                                <stop offset="0%" stop-color="var(--accent-green)"/>
                                <stop offset="100%" stop-color="transparent"/>
                            </linearGradient>
                        </defs>
                    </svg>
                </div>
            </section>

        </div>
    </main>

    <script>
        // Custom Select Logic
        function toggleCustomSelect() {
            const opts = document.getElementById('custom-select-options');
            opts.style.display = opts.style.display === 'block' ? 'none' : 'block';
        }

        document.querySelectorAll('.skill-option').forEach(opt => {
            opt.addEventListener('click', function() {
                const val = this.getAttribute('data-value');
                document.getElementById('resource_category').value = val;
                document.getElementById('custom-select-text').textContent = val;
                document.getElementById('custom-select-text').style.color = '#fff';
                document.getElementById('custom-select-options').style.display = 'none';
            });
        });

        document.addEventListener('click', function(e) {
            if (!e.target.closest('.custom-select')) {
                const opts = document.getElementById('custom-select-options');
                if(opts) opts.style.display = 'none';
            }
        });

        // Tab routing logic
        document.querySelectorAll('.sidebar-nav-item').forEach(item => {
            item.addEventListener('click', function(e) {
                e.preventDefault();
                
                // Remove active classes
                document.querySelectorAll('.sidebar-nav-item').forEach(nav => nav.classList.remove('active'));
                document.querySelectorAll('.view-section').forEach(sec => sec.classList.remove('active-view'));
                
                // Add active class to clicked
                this.classList.add('active');
                
                // Show target view
                const targetId = this.getAttribute('data-target');
                document.getElementById(targetId).classList.add('active-view');
                
                // Close sidebar on mobile
                if (window.innerWidth <= 768) {
                    const sidebar = document.getElementById('dashSidebar');
                    const overlay = document.getElementById('dashSidebarOverlay');
                    const hamburger = document.getElementById('dashHamburger');
                    
                    if (sidebar) sidebar.classList.remove('mobile-open');
                    if (overlay) overlay.classList.remove('open');
                    if (hamburger) hamburger.classList.remove('open');
                    document.body.style.overflow = '';
                }
            });
        });

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
