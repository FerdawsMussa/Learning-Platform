<?php
require_once 'api/session_helper.php';
start_role_session();
require_once 'api/db.php';

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    if ($_SESSION['role'] === 'admin')
        header("Location: dashboards/admin.php");
    elseif ($_SESSION['role'] === 'instructor')
        header("Location: instructor_dashboard.php");
    else
        header("Location: dashboard.php");
    exit;
}

$course_id = $_GET['course_id'] ?? '';

// Fetch categories for instructor signup
try {
    $categories_list = $pdo->query("SELECT * FROM categories ORDER BY name ASC")->fetchAll();
} catch (PDOException $e) {
    $categories_list = [];
}

// Handle signup form directly
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name_input = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $role = $_POST['role'] ?? 'student';

    // Split Name Logic: Everything after the space is the last name
    $name_parts = explode(' ', $full_name_input);
    if (count($name_parts) > 1) {
        $last_name = array_pop($name_parts);
        $full_name = implode(' ', $name_parts);
    } else {
        $full_name = $full_name_input;
        $last_name = '';
    }

    // Instructor specific fields
    $linkedin = trim($_POST['linkedin'] ?? '');
    $github = trim($_POST['github'] ?? '');
    $portfolio = trim($_POST['portfolio'] ?? '');
    $teaching_category = trim($_POST['teaching_category'] ?? 'Unassigned');

    if (empty($full_name) || empty($email) || empty($password)) {
        header("Location: signup.php?error=emptyfields&role=$role");
        exit;
    }

    if ($role === 'instructor') {
        if (empty($linkedin) || empty($github)) {
            header("Location: signup.php?error=emptyfields&role=instructor");
            exit;
        }
        if (!filter_var($linkedin, FILTER_VALIDATE_URL) || !str_contains($linkedin, 'linkedin.com')) {
            header("Location: signup.php?error=invalid_linkedin&role=instructor");
            exit;
        }
        if (!filter_var($github, FILTER_VALIDATE_URL) || !str_contains($github, 'github.com')) {
            header("Location: signup.php?error=invalid_github&role=instructor");
            exit;
        }
    }

    if (strlen($password) < 8 || !preg_match('/[^a-zA-Z\d]/', $password)) {
        header("Location: signup.php?error=weakpassword&role=$role");
        exit;
    }

    try {
        $pdo->beginTransaction();

        // Check for existing user in unified table
        $stmt = $pdo->prepare("SELECT id FROM `users` WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $pdo->rollBack();
            header("Location: signup.php?error=emailexists&role=$role");
            exit;
        }

        // Insert new user
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        if ($role === 'student') {
            $insert = $pdo->prepare("INSERT INTO `users` (full_name, last_name, email, password, role, is_approved, courses) VALUES (?, ?, ?, ?, 'student', 1, '[]')");
            $insert->execute([$full_name, $last_name, $email, $hashed_password]);
            $user_id = $pdo->lastInsertId();

            // Initialize progress_streaks
            $streak_init = $pdo->prepare("INSERT INTO progress (record_type, user_id, metric_value, last_activity_at) VALUES ('progress_streaks', ?, 0, NULL)");
            $streak_init->execute([$user_id]);
        } else {
            $is_approved = 0; // Set to pending (0) for admin approval
            $meta_data = [
                'linkedin' => $linkedin,
                'github' => $github,
                'portfolio' => $portfolio,
                'category' => $teaching_category
            ];
            $insert = $pdo->prepare("INSERT INTO `users` (full_name, last_name, email, password, role, is_approved, meta, courses, resources) VALUES (?, ?, ?, ?, 'instructor', ?, ?, '[]', '[]')");
            $insert->execute([$full_name, $last_name, $email, $hashed_password, $is_approved, json_encode($meta_data)]);
            $user_id = $pdo->lastInsertId();

            // Notify Admin - Use meta instead of user_id to avoid FK issues if needed, or ensure user_id is NULL for broadcast
            // Since target_role is 'admin', user_id should be NULL (broadcast) or a specific Admin ID.
            // We store the instructor's ID in the meta for the admin to see.
            $meta_notif = json_encode(['instructor_id' => $user_id]);
            $notif = $pdo->prepare("INSERT INTO `notifications` (target_role, message, type, meta) VALUES ('admin', ?, 'alert', ?)");
            $notif->execute(["New instructor application: $full_name $last_name ($email)", $meta_notif]);
        }

        $pdo->commit();

        // Redirect to login page instead of auto-login
        if ($role === 'student') {
            // Handle Auto-Enrollment in DB before redirecting
            $target_course = $_POST['course_id'] ?? '';
            if (!empty($target_course)) {
                $enroll_stmt = $pdo->prepare("UPDATE users SET courses = JSON_ARRAY_APPEND(courses, '$', ?) WHERE id = ?");
                $enroll_stmt->execute([$target_course, $user_id]);
            }
            header("Location: login.php?success=registered");
        } else {
            header("Location: login.php?success=registered_pending");
        }
        exit;

    } catch (PDOException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $logMsg = date('[Y-m-d H:i:s] ') . "Signup Error: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine() . "\n";
        $logMsg .= "POST Data: " . json_encode(array_diff_key($_POST, ['password' => ''])) . "\n";
        file_put_contents('error_log.txt', $logMsg, FILE_APPEND);
        header("Location: signup.php?error=sqlerror&role=$role");
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>JU Learn - Sign Up</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .login-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            padding: 40px 20px;
        }

        .login-card {
            width: 100%;
            max-width: 550px;
            padding: 40px;
            text-align: center;
            z-index: 2;
        }

        /* Tabs Styling */
        .tabs-container {
            display: flex;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid var(--border-light);
            border-radius: 100px;
            padding: 5px;
            margin-bottom: 30px;
            position: relative;
        }

        .tab-btn {
            flex: 1;
            padding: 12px;
            border-radius: 100px;
            border: none;
            background: transparent;
            color: var(--text-secondary);
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            z-index: 2;
            font-size: 0.9rem;
        }

        .tab-btn.active {
            color: #000;
        }

        .tab-slider {
            position: absolute;
            top: 5px;
            left: 5px;
            width: calc(50% - 5px);
            height: calc(100% - 10px);
            background: var(--accent-green);
            border-radius: 100px;
            transition: all 0.4s cubic-bezier(0.18, 0.89, 0.32, 1.28);
            z-index: 1;
        }

        .instructor-only {
            display: none;
            opacity: 0;
            transform: translateY(10px);
            transition: all 0.4s ease;
        }

        .instructor-only.show {
            display: block;
            opacity: 1;
            transform: translateY(0);
        }

        .login-input {
            width: 100%;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid var(--border-light);
            color: var(--text-primary);
            padding: 14px 16px;
            border-radius: 12px;
            margin-bottom: 20px;
            outline: none;
            transition: border-color 0.2s;
            font-size: 0.95rem;
        }

        .login-input:focus {
            border-color: var(--accent-green);
            box-shadow: 0 0 10px rgba(0, 229, 153, 0.2);
        }

        select.login-input {
            color: white !important;
            cursor: pointer;
        }

        select.login-input option {
            background: #1a1b22;
            color: white;
            padding: 10px;
        }

        .login-label {
            display: block;
            text-align: left;
            margin-bottom: 8px;
            color: var(--text-secondary);
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 1.2px;
            font-weight: 600;
        }

        .form-error-msg {
            color: #ffb3c1;
            font-size: 0.75rem;
            text-align: left;
            margin-top: -15px;
            margin-bottom: 15px;
            display: none;
        }

        .section-divider {
            color: var(--accent-green);
            font-size: 0.8rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            margin: 20px 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .section-divider::after {
            content: '';
            flex: 1;
            height: 1px;
            background: var(--border-light);
        }

        .toast-notification {
            position: fixed;
            bottom: -100px;
            right: 20px;
            background: rgba(255, 99, 132, 0.1);
            border: 1px solid rgba(255, 99, 132, 0.4);
            color: #ffb3c1;
            padding: 15px 25px;
            border-radius: 12px;
            font-size: 0.9rem;
            z-index: 9999;
            transition: bottom 0.5s ease-in-out;
            backdrop-filter: blur(10px);
        }

        .toast-notification.show {
            bottom: 20px;
        }
    </style>
</head>

<body>
    <div class="bg-gradient-spot glow-green float" style="animation-duration: 8s;"></div>
    <div class="bg-gradient-spot glow-blue float" style="top: auto; bottom: 0; animation-duration: 12s; animation-delay: 1s;"></div>

    <div class="login-container">
        <div class="glass-panel login-card animate-up">
            <h2 style="font-size: 2.2rem; margin-bottom: 8px;">Join JU Learn</h2>
            <p id="sub-title" style="color: var(--text-secondary); margin-bottom: 30px;">Start your learning journey today.</p>

            <div class="tabs-container">
                <div class="tab-slider" id="tabSlider"></div>
                <button type="button" class="tab-btn active" onclick="switchTab('student')">Student</button>
                <button type="button" class="tab-btn" onclick="switchTab('instructor')">Instructor</button>
            </div>

            <form action="signup.php" method="POST" id="signupForm">
                <input type="hidden" name="course_id" value="<?= htmlspecialchars($course_id) ?>">
                <input type="hidden" name="role" id="roleInput" value="student">

                <div style="display: flex; gap: 15px;">
                    <div style="flex: 1;">
                        <label class="login-label">Full Name</label>
                        <input type="text" class="login-input" name="full_name" required>
                    </div>
                </div>

                <label class="login-label">Email Address</label>
                <input type="email" class="login-input" name="email" required>

                <div style="display: flex; gap: 15px;">
                    <div style="flex: 1;">
                        <label class="login-label">Password</label>
                        <div style="position: relative;">
                            <input type="password" class="login-input" id="password" name="password" required>
                            <span onclick="togglePassword('password', this)" style="position: absolute; right: 12px; top: 12px; cursor: pointer; color: var(--text-secondary); opacity: 0.7;">
                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                            </span>
                        </div>
                    </div>
                    <div style="flex: 1;">
                        <label class="login-label">Confirm</label>
                        <div style="position: relative;">
                            <input type="password" class="login-input" id="confirm_password" required>
                            <span onclick="togglePassword('confirm_password', this)" style="position: absolute; right: 12px; top: 12px; cursor: pointer; color: var(--text-secondary); opacity: 0.7;">
                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                            </span>
                        </div>
                    </div>
                </div>
                <div id="password_hint" style="text-align: left; margin-top: -15px; margin-bottom: 20px; display: none;">
                    <small style="color: #ffb3c1; font-size: 0.75rem;">Hint: Min 8 characters and at least one special character required.</small>
                </div>
                <div class="form-error-msg" id="password_err">Weak password or mismatch.</div>

                <!-- Instructor Only Fields -->
                <div id="instructorFields" class="instructor-only">
                    <div class="section-divider">Professional Profiles</div>
                    
                    <label class="login-label">LinkedIn URL</label>
                    <input type="url" class="login-input" name="linkedin" id="linkedin">
                    
                    <label class="login-label">GitHub URL</label>
                    <input type="url" class="login-input" name="github" id="github">

                    <label class="login-label">Teaching Category</label>
                    <select class="login-input" name="teaching_category" id="teaching_category">
                        <option value="" disabled selected>Select your expertise...</option>
                        <?php foreach ($categories_list as $cat): ?>
                            <option value="<?= htmlspecialchars($cat['name']) ?>"><?= htmlspecialchars($cat['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    
                    <label class="login-label">Portfolio (Optional)</label>
                    <input type="url" class="login-input" name="portfolio">
                </div>

                <button type="submit" class="btn-neon w-100" style="padding: 16px; font-size: 1.1rem; margin-top: 10px;" id="submitBtn">Create Account</button>
            </form>

            <div style="margin-top: 25px; font-size: 0.95rem; color: var(--text-secondary);">
                Already have an account? <a href="login.php" style="color: var(--accent-green); text-decoration: none; font-weight: 700;">Log In</a>
            </div>

            <div style="margin-top: 20px; font-size: 0.85rem; color: var(--text-secondary);">
                <a href="index.php" style="color: var(--text-secondary); text-decoration: none; opacity: 0.7;">← Back to Home</a>
            </div>
        </div>
    </div>

    <div id="toast" class="toast-notification">Notification</div>

    <script>
        const roleInput = document.getElementById('roleInput');
        const instructorFields = document.getElementById('instructorFields');
        const tabSlider = document.getElementById('tabSlider');
        const subTitle = document.getElementById('sub-title');
        const tabBtns = document.querySelectorAll('.tab-btn');
        const form = document.getElementById('signupForm');

        function switchTab(role) {
            roleInput.value = role;
            
            // UI Updates
            if (role === 'student') {
                tabSlider.style.transform = 'translateX(0)';
                instructorFields.classList.remove('show');
                setTimeout(() => instructorFields.style.display = 'none', 400);
                subTitle.innerText = "Start your learning journey today.";
                document.getElementById('linkedin').required = false;
                document.getElementById('github').required = false;
                document.getElementById('teaching_category').required = false;
            } else {
                tabSlider.style.transform = 'translateX(100%)';
                instructorFields.style.display = 'block';
                setTimeout(() => instructorFields.classList.add('show'), 10);
                subTitle.innerText = "Share your knowledge with the world.";
                document.getElementById('linkedin').required = true;
                document.getElementById('github').required = true;
                document.getElementById('teaching_category').required = true;
            }

            tabBtns.forEach(btn => {
                if (btn.innerText.toLowerCase() === role) btn.classList.add('active');
                else btn.classList.remove('active');
            });
        }

        form.addEventListener('submit', (e) => {
            const pass = document.getElementById('password').value;
            const conf = document.getElementById('confirm_password').value;
            const err = document.getElementById('password_err');

            if (pass.length < 8 || !/[^a-zA-Z\d]/.test(pass)) {
                err.innerText = "Password must be 8+ chars and contain a special char.";
                err.style.display = 'block';
                e.preventDefault();
                return;
            }

            if (pass !== conf) {
                err.innerText = "Passwords do not match!";
                err.style.display = 'block';
                e.preventDefault();
                return;
            }

            if (roleInput.value === 'instructor') {
                const li = document.getElementById('linkedin').value;
                const gh = document.getElementById('github').value;
                if (!li.includes('linkedin.com')) {
                    showToast("Valid LinkedIn URL required.");
                    e.preventDefault();
                } else if (!gh.includes('github.com')) {
                    showToast("Valid GitHub URL required.");
                    e.preventDefault();
                }
            }
        });

        const passInput = document.getElementById('password');
        const passHint = document.getElementById('password_hint');
        
        passInput.addEventListener('input', () => {
            const val = passInput.value;
            const hasSpecial = /[^a-zA-Z\d]/.test(val);
            if (val.length > 0 && (val.length < 8 || !hasSpecial)) {
                passHint.style.display = 'block';
            } else {
                passHint.style.display = 'none';
            }
        });

        function togglePassword(id, el) {
            const input = document.getElementById(id);
            const isPass = input.type === "password";
            input.type = isPass ? "text" : "password";
            el.innerHTML = isPass 
                ? '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line></svg>'
                : '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>';
        }

        function showToast(msg) {
            const toast = document.getElementById('toast');
            toast.innerText = msg;
            toast.classList.add('show');
            setTimeout(() => toast.classList.remove('show'), 5000);
        }

        const urlParams = new URLSearchParams(window.location.search);
        const errType = urlParams.get('error');
        const savedRole = urlParams.get('role');
        if (savedRole) {
            switchTab(savedRole);
        } else {
            switchTab('student');
        }

        if (errType) {
            let msg = "System error.";
            if (errType === 'emptyfields') msg = "Please fill in all fields.";
            if (errType === 'emailexists') msg = "Email already in use.";
            if (errType === 'weakpassword') msg = "Password too weak.";
            if (errType === 'invalid_linkedin') msg = "LinkedIn URL must be valid.";
            if (errType === 'invalid_github') msg = "GitHub URL must be valid.";
            showToast(msg);
            window.history.replaceState({}, document.title, "signup.php");
        }
    </script>
</body>

</html>