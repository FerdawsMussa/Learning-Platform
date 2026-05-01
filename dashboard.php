<?php
require_once 'api/session_helper.php';
start_role_session();
require_once 'api/db.php';

// Access Control
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id']; 
$user_role = $_SESSION['role'] ?? 'student'; 

if ($user_role === 'student' || $user_role === 'instructor' || $user_role === 'admin') {
    $stmt = $pdo->prepare("SELECT full_name FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user_name = $stmt->fetchColumn() ?: 'Guest User';
} else {
    $user_name = $_SESSION['user_name'] ?? 'Guest User';
}

// Normalize role
if ($user_role === 'creator') {
    $_SESSION['role'] = 'instructor';
    $user_role = 'instructor';
}

// --- NEW: DAILY STREAK TRACKING LOGIC ---
if ($user_role === 'student') {
    $today = date('Y-m-d');
    $yesterday = date('Y-m-d', strtotime('-1 day'));

    $stmt = $pdo->prepare("SELECT metric_value AS streak_count, last_activity_at AS last_login_date FROM progress WHERE record_type = 'streak' AND user_id = ?");
    $stmt->execute([$user_id]);
    $streak_data = $stmt->fetch();

    if ($streak_data) {
        $last_login = $streak_data['last_login_date'];
        $current_streak = $streak_data['streak_count'];

        if ($last_login !== $today) {
            if ($last_login === $yesterday) {
                // Consecutive day
                $new_streak = $current_streak + 1;
            } else {
                // Missed a day, reset to 1
                $new_streak = 1;
            }
            $upd = $pdo->prepare("UPDATE progress SET metric_value = ?, last_activity_at = ? WHERE record_type = 'streak' AND user_id = ?");
            $upd->execute([$new_streak, $today, $user_id]);
        }
    } else {
        // Initial setup for streak row if it doesn't exist
        $pdo->prepare("INSERT INTO progress (record_type, user_id, metric_value, last_activity_at) VALUES ('streak', ?, 1, ?)")
            ->execute([$user_id, $today]);
    }
}
// --- END STREAK LOGIC ---

$profile_error = '';
$profile_success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_account'])) {
    $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
    if ($stmt->execute([$user_id])) {
        session_destroy();
        header("Location: login.php?msg=account_deleted");
        exit;
    } else {
        $profile_error = "Account deletion failed.";
    }
}

// Handle Profile Form Submission via Dashboard
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    // Profile updates now all target the unified 'users' table
    $last_name_input = trim($_POST['last_name'] ?? '');
    $email_input = trim($_POST['email'] ?? ''); // Not currently used for update but standardizing names
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $current_password = $_POST['current_password'] ?? '';

    $params = [];
    $update_parts = [];

    // 1. Password Update with Verification
    if (!empty($new_password)) {
        // Fetch current password
        $stmt_check = $pdo->prepare("SELECT password FROM users WHERE id = ?");
        $stmt_check->execute([$user_id]);
        $curr = $stmt_check->fetch();

        if (!$curr || !password_verify($current_password, $curr['password'])) {
            $profile_error = "Current password is incorrect!";
        } elseif ($new_password !== $confirm_password) {
            $profile_error = "New passwords do not match!";
        } else {
            $update_parts[] = "password = ?";
            $params[] = password_hash($new_password, PASSWORD_DEFAULT);
        }
    }

    // 2. Last Name Update (Student only)
    if ($user_role === 'student' && !empty($last_name_input)) {
        $update_parts[] = "last_name = ?";
        $params[] = $last_name_input;
    }

    // 3. Profile Picture Upload
    if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] === UPLOAD_ERR_OK) {
        $file_tmp = $_FILES['profile_pic']['tmp_name'];
        $file_name = time() . '_' . basename($_FILES['profile_pic']['name']);
        $upload_dir = 'assets/uploads/profile_pics/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        $dest_path = $upload_dir . $file_name;

        if (move_uploaded_file($file_tmp, $dest_path)) {
            $update_parts[] = "profile_pic = ?";
            $params[] = $dest_path;
        } else {
            $profile_error = "Failed to upload image.";
        }
    }

    if (empty($profile_error) && !empty($update_parts)) {
        $query = "UPDATE users SET " . implode(", ", $update_parts) . " WHERE id = ?";
        $params[] = $user_id;

        try {
            $stmt = $pdo->prepare($query);
            $stmt->execute($params);
            $profile_success = "Profile updated successfully!";
        } catch (PDOException $e) {
            $profile_error = "Update failed: " . $e->getMessage();
        }
    } else if (empty($profile_error)) {
        $profile_error = "No changes detected.";
    }
}

// --- NEW: ENROLLMENT HANDLER (JSON Based) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['enroll_now'])) {
    $course_id = (int)$_POST['enroll_now'];
    $is_ajax = isset($_POST['ajax']);
    
    try {
        // Verify course is approved before enrollment
        $check_stmt = $pdo->prepare("SELECT is_approved FROM content WHERE id = ? AND record_type = 'course'");
        $check_stmt->execute([$course_id]);
        $course_status = $check_stmt->fetch();

        if (!$course_status || (int)$course_status['is_approved'] !== 1) {
            if ($is_ajax) {
                echo json_encode(['success' => false, 'message' => 'This course is pending approval and cannot be enrolled in yet.']);
                exit;
            }
            header("Location: dashboard.php?error=not_approved");
            exit;
        }

        // Fetch current courses
        $stmt = $pdo->prepare("SELECT courses FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $current_courses = json_decode($stmt->fetchColumn() ?: '[]', true);
        
        if (!in_array($course_id, $current_courses)) {
            $current_courses[] = $course_id;
            $new_courses_json = json_encode($current_courses);
            $upd = $pdo->prepare("UPDATE users SET courses = ? WHERE id = ?");
            $upd->execute([$new_courses_json, $user_id]);
            
            if ($is_ajax) {
                echo json_encode(['success' => true, 'message' => 'Enrolled successfully']);
                exit;
            }
            
            // enrolled=1 signals the viewer to trigger full course caching
            header("Location: student_viewer.php?id=$course_id&type=course&enrolled=1");
            exit;
        } else {
            if ($is_ajax) {
                echo json_encode(['success' => true, 'message' => 'Already enrolled']);
                exit;
            }
            // Already enrolled — just open the course
            header("Location: student_viewer.php?id=$course_id&type=course");
            exit;
        }
    } catch (PDOException $e) {
        if ($is_ajax) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            exit;
        }
        $enroll_error = "Enrollment failed: " . $e->getMessage();
    }
}
// --- END ENROLLMENT HANDLER ---
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | JU Learn</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/dashboard.css">
    <link rel="stylesheet" href="assets/css/landing_extra.css">
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
        }

        .sidebar {
            width: 280px;
            background: rgba(10, 14, 18, 0.98);
            border-right: 1px solid var(--border-light);
            position: fixed;
            height: 100vh;
            z-index: 1000;
            display: flex;
            flex-direction: column;
            transition: 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .sidebar-brand-unified {
            padding: 24px;
            border-bottom: 1px solid var(--border-light);
        }

        .brand-logo-text {
            font-weight: 800;
            font-size: 1.25rem;
            letter-spacing: -1px;
            color: white;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .brand-logo-text span { color: var(--accent-green); }

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

        .main-content {
            margin-left: 280px;
            padding: 40px;
            min-height: 100vh;
            transition: 0.3s;
        }

        .dashboard-section { display: none; }
        .dashboard-section.active { display: block; animation: fadeIn 0.4s ease; }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .stat-card-mini {
            background: var(--glass-bg);
            border: 1px solid var(--border-light);
            border-radius: 16px;
            padding: 20px;
            backdrop-filter: blur(10px);
            transition: 0.3s;
        }
        .stat-card-mini:hover { border-color: var(--accent-green); transform: translateY(-3px); }

        .btn-neon {
            background: var(--accent-green);
            color: #000 !important;
            font-weight: 700;
            border: none;
            padding: 10px 24px;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0, 229, 153, 0.2);
            transition: 0.3s;
            text-decoration: none;
        }
        .btn-neon:hover { 
            transform: scale(1.02); 
            box-shadow: 0 6px 20px rgba(0, 229, 153, 0.4);
            color: #000 !important;
        }

        .btn-outline-success:hover {
            color: #000 !important;
        }

        /* Aggressive button visibility fix */
        .main-content .btn-neon, 
        .main-content .btn-success,
        .main-content .btn-neon:hover,
        .main-content .btn-success:hover,
        .main-content .btn-outline-success:hover,
        .main-content .btn-outline-neon:hover {
            color: #000 !important;
            text-decoration: none !important;
        }

        .btn-outline-success:hover {
            background-color: var(--accent-green) !important;
            border-color: var(--accent-green) !important;
        }

        .main-content .btn svg {
            stroke: currentColor !important;
        }

        @media (max-width: 992px) {
            .sidebar { left: -280px; }
            .sidebar.mobile-open { left: 0; }
            .main-content { margin-left: 0; padding: 20px; padding-top: 80px; }
            .dash-mobile-topbar { display: flex !important; }
        }
    </style>
    <script>
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

        function toggleMyDesc(id, el) {
            const p = document.getElementById('desc-my-' + id);
            if (p.style.display === '-webkit-box') {
                p.style.display = 'block';
                el.innerText = 'See Less';
            } else {
                p.style.display = '-webkit-box';
                el.innerText = 'See More';
            }
        }
    </script>
</head>
<body>

    <div class="bg-gradient-spot glow-green float" style="top: -10%; right: -10%; animation-duration: 8s;"></div>
    <div class="bg-gradient-spot glow-blue float" style="bottom: -10%; left: -10%; animation-duration: 12s;"></div>

    <!-- Mobile Topbar -->
    <div class="dash-mobile-topbar d-none" id="dashMobileTopbar" style="height: 70px; background: rgba(10,14,18,0.98); border-bottom: 1px solid var(--border-light); display: flex; align-items: center; padding: 0 20px; position: fixed; top: 0; width: 100%; z-index: 1001; justify-content: space-between;">
        <a href="index.php" class="brand-logo-text">
            <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="var(--accent-green)" stroke-width="2">
                <path d="M22 10v6M2 10l10-5 10 5-10 5z"></path>
                <path d="M6 12v5c3 3 9 3 12 0v-5"></path>
            </svg>
            JU<span>Learn</span>
        </a>
        <button class="btn btn-outline-light border-0 fs-3" id="dashHamburger">☰</button>
    </div>

    <!-- SIDEBAR -->
    <aside class="sidebar" id="dashSidebar">
        <!-- SYNCED BRANDING BLOCK -->
        <div class="sidebar-brand-unified">
            <div class="d-flex align-items-center gap-2">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="var(--accent-green)" stroke-width="2">
                    <path d="M22 10v6M2 10l10-5 10 5-10 5z"></path>
                    <path d="M6 12v5c3 3 9 3 12 0v-5"></path>
                </svg>
                <strong style="font-size: 1.2rem; letter-spacing: -0.5px; color: white;">JU<span style="color: var(--accent-green);">Learn</span></strong>
            </div>
            <div class="mt-2 small text-uppercase fw-bold" style="color: rgba(255,255,255,0.4); font-size: 0.65rem; letter-spacing: 1px;">
                <?= ucfirst($user_role) ?> Dashboard
            </div>
        </div>

        <nav class="nav flex-column py-4">
            <a href="#overview" class="nav-link active" onclick="switchTab('overview')">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path></svg>
                Overview
            </a>
            <a href="#my-content_courses" class="nav-link" onclick="switchTab('my-content_courses')">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"></path><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"></path></svg>
                My Courses
            </a>
            <a href="#library" class="nav-link" onclick="switchTab('library')">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                Study Library
            </a>
            <a href="#rewards" class="nav-link" onclick="switchTab('rewards')">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="8" r="7"></circle><polyline points="8.21 13.89 7 23 12 20 17 23 15.79 13.88"></polyline></svg>
                Achievements
            </a>
            <a href="#profile" class="nav-link" onclick="switchTab('profile')">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
                Profile & Security
            </a>
        </nav>

        <div class="mt-auto p-4 border-top border-secondary" style="border-top-color: var(--border-light) !important;">
            <a href="api/logout.php" class="btn btn-outline-danger w-100 btn-sm rounded-pill font-monospace" style="border-color: rgba(220,53,69,0.5);">Logout</a>
        </div>
    </aside>

    <!-- CONTENT -->
    <main class="main-content">
        
        <header class="d-flex justify-content-between align-items-center mb-5">
            <div id="welcome-header">
                <!-- Greeting removed as requested -->
            </div>
        </header>

        <?php
        if ($user_role === 'student') include 'includes/dash_student.php';
        else if ($user_role === 'instructor') echo "<script>window.location.href='instructor_dashboard.php';</script>";
        else include 'includes/dash_admin.php';
        ?>

    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function switchTab(id) {
            // Update active link
            document.querySelectorAll('.nav-link').forEach(link => {
                link.classList.remove('active');
                if (link.getAttribute('href') === '#' + id) link.classList.add('active');
            });

            // Update visible section
            document.querySelectorAll('.dashboard-section').forEach(sec => {
                sec.classList.remove('active');
                if (sec.id === 'section-' + id) sec.classList.add('active');
            });

            // Mobile sidebar hide
            document.getElementById('dashSidebar').classList.remove('mobile-open');
        }

        // Mobile Hamburger
        document.getElementById('dashHamburger').onclick = () => {
            document.getElementById('dashSidebar').classList.toggle('mobile-open');
        };

        // Handle initial Hash
        window.addEventListener('DOMContentLoaded', () => {
            const hash = window.location.hash.substring(1) || 'overview';
            switchTab(hash);
        });

        function togglePassword(id, el) {
            const input = document.getElementById(id);
            const isPass = input.type === 'password';
            input.type = isPass ? 'text' : 'password';
            el.innerHTML = isPass
                ? '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line></svg>'
                : '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>';
        }

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
                    
                    data = data.data; // Correctly access the nested data object
                    
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
    </script>

    <!-- Profile Detail Modal -->
    <div class="modal fade" id="profileModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content glass-panel border-light" style="background: rgba(10, 11, 16, 0.95); backdrop-filter: blur(20px);">
                <div class="modal-header border-bottom border-light border-opacity-10">
                    <h5 class="modal-title fw-bold text-white" id="modalTitle">Profile Details</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="modalBody" style="color: #f1f5f9;">
                    <!-- Content injected by showProfile() -->
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
</body>
</html>