<?php
require_once 'api/session_helper.php';
start_role_session();
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>JU Learn</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <?php if(basename($_SERVER['PHP_SELF']) == 'index.php'): ?>
        <link rel="stylesheet" href="assets/css/landing_extra.css">
    <?php endif; ?>
    <script>
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => {
                navigator.serviceWorker.register('/lastfyp/service_worker.js')
                .then(registration => console.log('SW Registered'))
                .catch(err => console.error('SW Failed', err));
            });
        }
    </script>
</head>
<body>
    <div class="bg-gradient-spot glow-green"></div>
    <div class="bg-gradient-spot glow-blue"></div>

    <nav class="navbar flex-between">
        <div class="logo d-flex align-items-center gap-2" style="flex: 1;">
            <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="var(--accent-green)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M22 10v6M2 10l10-5 10 5-10 5z"></path>
                <path d="M6 12v5c3 3 9 3 12 0v-5"></path>
            </svg>
            <strong style="font-size: 1.35rem; letter-spacing: -0.5px; font-weight: 800;">JU<span class="text-accent">Learn</span></strong>
        </div>
        <ul class="nav-links" id="navLinks" style="flex: 2; justify-content: center; display: flex; gap: 1.5rem; align-items: center;">
            <li><a href="index.php#home" class="nav-link-item <?= ($current_page == 'index.php' || $current_page == '') ? 'active-link' : '' ?>" data-section="home">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path><polyline points="9 22 9 12 15 12 15 22"></polyline></svg> Home
            </a></li>
            <li><a href="index.php#about" class="nav-link-item" data-section="about">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><path d="M12 16v-4"></path><path d="M12 8h.01"></path></svg> About
            </a></li>
            <li><a href="index.php#content_courses" class="nav-link-item" data-section="content_courses">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"></path><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"></path></svg> Course
            </a></li>
            <li><a href="index.php#content_resources" class="nav-link-item" data-section="content_resources">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><line x1="9" y1="3" x2="9" y2="21"></line></svg> Resources
            </a></li>
            <li><a href="contact.php" class="nav-link-item <?= $current_page == 'contact.php' ? 'active-link' : '' ?>">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path></svg> Contact
            </a></li>
        </ul>
        <div class="nav-actions d-flex" style="flex: 1.2; justify-content: flex-end; align-items: center; gap: 1rem;">
            <a href="login.php" class="btn-outline-neon card-hover" style="font-weight: 500; padding: 8px 16px; font-size: 0.85rem; border-radius: 100px; border-color: var(--border-light); color: var(--text-secondary); text-align: center;">Log In</a>
            <!-- Hamburger - shown only on mobile via CSS -->
            <button class="hamburger-btn" id="hamburgerBtn" aria-label="Open menu" aria-expanded="false">
                <span></span><span></span><span></span>
            </button>
        </div>
    </nav>

    <!-- Mobile overlay -->
    <div class="mobile-nav-overlay" id="mobileNavOverlay"></div>

    <!-- Mobile Navigation Drawer -->
    <div class="mobile-nav-drawer" id="mobileNavDrawer" role="navigation" aria-label="Mobile navigation">
        <button class="drawer-close" id="drawerClose" aria-label="Close menu">✕</button>
        <div style="margin-bottom: 1rem; padding-bottom: 1rem; border-bottom: 1px solid var(--border-light); display: flex; align-items: center; gap: 10px;">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="var(--accent-green)" stroke-width="2"><path d="M22 10v6M2 10l10-5 10 5-10 5z"></path><path d="M6 12v5c3 3 9 3 12 0v-5"></path></svg>
            <strong style="font-size: 1.2rem; font-weight: 800;">JU<span class="text-accent">Learn</span></strong>
        </div>
        <a href="index.php#home" class="mobile-nav-link <?= $current_page == 'index.php' ? 'active-link' : '' ?>">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path><polyline points="9 22 9 12 15 12 15 22"></polyline></svg> Home
        </a>
        <a href="index.php#about" class="mobile-nav-link <?= $current_page == 'about.php' ? 'active-link' : '' ?>">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><path d="M12 16v-4"></path><path d="M12 8h.01"></path></svg> About
        </a>
        <a href="index.php#content_courses" class="mobile-nav-link <?= $current_page == 'content_courses.php' ? 'active-link' : '' ?>">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"></path><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"></path></svg> Courses
        </a>
        <a href="index.php#content_resources" class="mobile-nav-link <?= $current_page == 'content_resources.php' ? 'active-link' : '' ?>">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><line x1="9" y1="3" x2="9" y2="21"></line></svg> Resources
        </a>
        <a href="contact.php" class="mobile-nav-link <?= $current_page == 'contact.php' ? 'active-link' : '' ?>">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path></svg> Contact
        </a>
        <div style="margin-top: auto; padding-top: 1.5rem; border-top: 1px solid var(--border-light);">
            <a href="login.php" class="btn-neon w-100" style="justify-content: center; padding: 12px; font-size: 0.95rem;">Log In</a>
        </div>
    </div>

    <script>
    (function() {
        const btn = document.getElementById('hamburgerBtn');
        const drawer = document.getElementById('mobileNavDrawer');
        const overlay = document.getElementById('mobileNavOverlay');
        const close = document.getElementById('drawerClose');

        function openDrawer() {
            drawer.classList.add('open');
            overlay.classList.add('open');
            btn.classList.add('open');
            btn.setAttribute('aria-expanded', 'true');
            document.body.style.overflow = 'hidden';
        }
        function closeDrawer() {
            drawer.classList.remove('open');
            overlay.classList.remove('open');
            btn.classList.remove('open');
            btn.setAttribute('aria-expanded', 'false');
            document.body.style.overflow = '';
        }

        btn.addEventListener('click', openDrawer);
        close.addEventListener('click', closeDrawer);
        overlay.addEventListener('click', closeDrawer);

        // Close on any link click inside drawer
        drawer.querySelectorAll('a').forEach(a => a.addEventListener('click', closeDrawer));

        // Active Link Handling for Single Page Navigation
        const navLinks = document.querySelectorAll('.nav-link-item');
        const sections = ['home', 'about', 'content_courses', 'content_resources'];

        function updateActiveLink() {
            let current = '';
            
            // Check scroll position
            sections.forEach(id => {
                const section = document.getElementById(id);
                if (section) {
                    const sectionTop = section.offsetTop;
                    const sectionHeight = section.offsetHeight;
                    if (window.pageYOffset >= sectionTop - 100) {
                        current = id;
                    }
                }
            });

            if (current) {
                navLinks.forEach(link => {
                    link.classList.remove('active-link');
                    if (link.getAttribute('data-section') === current) {
                        link.classList.add('active-link');
                    }
                });
            }
        }

        window.addEventListener('scroll', updateActiveLink);
        window.addEventListener('load', updateActiveLink);

        navLinks.forEach(link => {
            link.addEventListener('click', function(e) {
                const sectionId = this.getAttribute('data-section');
                if (sectionId) {
                    navLinks.forEach(l => l.classList.remove('active-link'));
                    this.classList.add('active-link');
                }
            });
        });
    })();
    </script>

    <?php if (isset($_SESSION['user_id']) && $_SESSION['role'] === 'student'): ?>
    <script>
        // Track time spent on website (Heartbeat every 60 seconds)
        setInterval(() => {
            fetch('api/heartbeat.php').catch(e => console.log('Heartbeat failed'));
        }, 60000);
    </script>
    <?php endif; ?>
