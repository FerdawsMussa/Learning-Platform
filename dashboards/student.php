<?php
session_start();
require_once '../api/db.php';

// Protect route - if not logged in or not a student, redirect
// Temporarily commented out for easy viewing during design
/*
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: ../login.php");
    exit;
}
*/

$user_id = $_SESSION['user_id'] ?? 1; // Default to 1 for demo purposes

// Fetch User info (Unified Table)
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND role = 'student'");
$stmt->execute([$user_id]);
$student = $stmt->fetch();

// Fetch Streaks (Replaces legacy Rewards)
$stmt = $pdo->prepare("SELECT * FROM progress WHERE record_type = 'streak' AND user_id = ?");
$stmt->execute([$user_id]);
$streak_data = $stmt->fetch();
$streak_count = $streak_data ? $streak_data['streak_count'] : 0;

// Fetch content_courses
$content_courses_stmt = $pdo->query("SELECT * FROM (SELECT id, user_id AS creator_id, title, description, file_path AS thumbnail_path, category, generic_value AS level, meta_text AS tags, is_resource, is_deleted, deleted_at, created_at, is_approved FROM content WHERE record_type = 'course') content_courses WHERE is_deleted = 0 AND is_approved = 1 ORDER BY created_at DESC LIMIT 3");
$recent_content_courses = $content_courses_stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>JU Learn - Student Dashboard</title>
    <!-- Offline CSS -->
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    
    <script>
        // PWA Service Worker Registration
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => {
                navigator.serviceWorker.register('/lastfyp/service_worker.js')
                .then(registration => {
                    console.log('SW Registered!', registration);
                })
                .catch(err => {
                    console.error('SW Registration Failed!', err);
                });
            });
        }

        // Offline Sync Logic
        function toggleOfflineSync(el) {
            el.classList.toggle('active');
            const isActive = el.classList.contains('active');
            
            if (isActive) {
                alert('Offline Sync Activated: Caching course metadata to IndexedDB and media via Cache API.');
                // Here we would typically trigger the service worker or IndexedDB wrapper 
                // cacheCourseData();
            } else {
                alert('Offline Sync Deactivated.');
            }
        }
    </script>
    <style>
        .toggle-switch.active { background: var(--accent-green); }
        .toggle-switch.active::before { transform: translateX(20px); }
        
        .badge-item {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: rgba(155, 81, 224, 0.1);
            border: 1px solid var(--accent-purple);
            color: var(--accent-purple);
            padding: 8px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
        }
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
            JU<span class="text-accent">Learn</span>
        </a>
        <button class="dash-hamburger" id="dashHamburger" aria-label="Open sidebar" aria-expanded="false">
            <span></span><span></span><span></span>
        </button>
    </div>

    <!-- Sidebar overlay (mobile) -->
    <div class="dash-sidebar-overlay" id="dashSidebarOverlay"></div>

    <!-- Sidebar -->
    <aside class="sidebar" id="dashSidebar">
        <div class="sidebar-header">
            <div style="width: 20px; height: 20px; background: var(--accent-green); border-radius: 4px;"></div>
            <strong style="color: var(--text-primary); font-size: 1.1rem;">JU Learn</strong>
        </div>

        <ul class="sidebar-nav">
            <li>
                <a href="#" class="sidebar-nav-item active">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect>
                    </svg>
                    Dashboard
                </a>
            </li>
            <li>
                <a href="../content_courses.php" class="sidebar-nav-item">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"></path><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"></path>
                    </svg>
                    My Courses
                </a>
            </li>
            <li>
                <a href="../content_courses.php#content_resources" class="sidebar-nav-item">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="17 8 12 3 7 8"></polyline><line x1="12" y1="3" x2="12" y2="15"></line>
                    </svg>
                    Resources
                </a>
            </li>
        </ul>

        <div style="margin-top: auto; padding: 1rem;">
            <div class="glass-panel" style="padding: 1rem; border-radius: 8px;">
                <p style="font-size: 0.8rem; color: var(--text-secondary); margin-bottom: 0.5rem;">Offline Sync</p>
                <div class="flex-between">
                    <span style="font-size: 0.85rem; color: var(--text-primary);">Active Local</span>
                    <div class="toggle-switch" onclick="toggleOfflineSync(this)"></div>
                </div>
            </div>
        </div>
    </aside>

    <!-- Main Content -->
    <main class="main-content">
        <!-- Top Navigation -->
        <header class="topbar">
            <div class="flex-between gap-3">
                <div class="wallet-badge glass-panel">
                    <span class="network-dot"></span>
                    <?= htmlspecialchars($student ? $student['full_name'] : "Guest Student") ?>
                </div>
                <a href="../login.php" class="btn-glass"
                    style="color: var(--accent-green); border-color: var(--accent-green);">
                    Logout
                </a>
            </div>
        </header>

        <!-- Dashboard Widgets -->
        <div class="dashboard-container">
            <h2 class="page-title">Learning Overview</h2>
            
            <!-- Engagement Section (Updated) -->
            <div class="glass-panel" style="margin-bottom: 2rem; padding: 1.5rem; display: flex; gap: 2rem; align-items: center; border-left: 4px solid var(--accent-green);">
                <div style="flex: 1; text-align: center;">
                    <div style="font-size: 3rem; margin-bottom: -5px;">🔥</div>
                    <div style="font-size: 2.5rem; font-weight: 800; color: var(--accent-green);"><?= $streak_count ?></div>
                    <div style="font-size: 0.8rem; text-transform: uppercase; letter-spacing: 1px; color: var(--text-secondary);">Day Streak</div>
                </div>
                <div style="flex: 3; border-left: 1px solid var(--border-light); padding-left: 2rem;">
                    <h3 style="font-size: 1.1rem; margin-bottom: 0.5rem;">Keep the momentum going!</h3>
                    <p style="font-size: 0.9rem; color: var(--text-secondary); margin-bottom: 1rem;">You've been active for <?= $streak_count ?> days in a row. Complete a lesson today to maintain your progress.</p>
                    <div class="progress-bar-container" style="height: 8px; background: rgba(255,255,255,0.05);">
                        <div class="progress-bar-fill" style="width: <?= min(100, ($streak_count / 7) * 100) ?>%; background: linear-gradient(90deg, var(--accent-green), #50ffc8); box-shadow: 0 0 10px rgba(0, 229, 153, 0.3);"></div>
                    </div>
                    <div style="font-size: 0.75rem; color: var(--text-muted); mt-2;">Weekly Goal: <?= $streak_count ?>/7 Days</div>
                </div>
            </div>

            <div class="balance-heading">
                <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="var(--accent-green)" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
                <span class="text-gradient">Active Learning</span> Dashboard
            </div>

            <!-- Stats Grid -->
            <div class="overview-grid">

                <div class="stat-card glass-panel animate-up animate-delay-1 card-hover">
                <div class="stat-card-title">Enrolled Courses</div>
                <div class="stat-card-value text-accent"><?= count(json_decode($student['content_courses'] ?? '[]', true)) ?></div>
            </div>
            
            <div class="stat-card glass-panel animate-up animate-delay-2 card-hover">
                <div class="stat-card-title">Daily Streak</div>
                <div class="stat-card-value"><?= $streak_count ?> Days</div>
            </div>
            
            <div class="stat-card glass-panel animate-up animate-delay-3 card-hover">
                <div class="stat-card-title">Offline Sync</div>
                <div class="d-flex align-items-center gap-2">
                    <div class="toggle-switch pulse-glow" onclick="toggleOfflineSync(this)"></div>
                    <span style="font-size: 0.85rem; color: var(--text-secondary);">Disabled</span>
                </div>
            </div>
            
            <div class="glass-panel chart-mockup animate-up animate-delay-4 card-hover" style="display: flex; flex-direction: column;">
                    <div style="display: flex; justify-content: space-between; width: 100%; margin-bottom: auto;">
                        <span class="stat-card-title">Activity (Offline & Online)</span>
                        <select style="background: transparent; border: 1px solid var(--border-light); color: var(--text-secondary); border-radius: 4px; padding: 2px 5px;">
                            <option>Last 7 Days</option>
                        </select>
                    </div>

                    <div style="display: flex; justify-content: space-between; align-items: flex-end; width: 100%; height: 120px; gap: 8px;">
                        <div class="chart-bar" style="height: 30%;"></div>
                        <div class="chart-bar" style="height: 50%;"></div>
                        <div class="chart-bar" style="height: 20%;"></div>
                        <div class="chart-bar" style="height: 80%;"></div>
                        <div class="chart-bar" style="height: 40%;"></div>
                        <div class="chart-bar" style="height: 60%;"></div>
                        <div class="chart-bar" style="height: 100%;"></div>
                        <div class="chart-bar" style="height: 70%;"></div>
                        <div class="chart-bar" style="height: 90%;"></div>
                    </div>
                </div>

            </div>

            <!-- Dynamic Courses Table -->
            <div class="glass-panel data-table-wrapper" style="margin-top: 2rem;">
                <div style="padding: 1.5rem; border-bottom: 1px solid var(--border-light); display: flex; justify-content: space-between; align-items: center;">
                    <h3 style="font-size: 1.1rem; font-weight: 600;">Recent Modules</h3>
                    <button class="btn-outline-neon">Sync Progress</button>
                </div>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Module Name</th>
                            <th>Status</th>
                            <th>Completion</th>
                            <th>Local Cache</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($recent_content_courses as $c): ?>
                        <tr>
                            <td>
                                <div style="display: flex; align-items: center; gap: 0.75rem;">
                                    <div style="width: 24px; height: 24px; background: rgba(0, 229, 153, 0.2); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: var(--accent-green);">
                                        <?= strtoupper(substr($c['title'], 0, 1)) ?>
                                    </div>
                                    <?= htmlspecialchars($c['title']) ?>
                                </div>
                            </td>
                            <td><span style="color: var(--accent-green);">In Progress</span></td>
                            <td>
                                <div class="progress-bar-container">
                                    <div class="progress-bar-fill" style="width: 80%;"></div>
                                </div>
                                <div style="font-size: 0.75rem; color: var(--text-secondary); margin-top: 4px;">80%</div>
                            </td>
                            <td><span class="badge-glass" style="margin:0; border-color: var(--accent-blue); color: var(--accent-blue);">IndexedDB</span></td>
                            <td><button class="btn-glass">Continue</button></td>
                        </tr>
                        <?php endforeach; ?>
                        
                        <?php if(empty($recent_content_courses)): ?>
                            <tr>
                                <td colspan="5" style="text-align: center; color: var(--text-secondary); padding: 2rem;">No content_courses available.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
        </div>
    </main>


    <script>
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