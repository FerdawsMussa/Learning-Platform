<?php
session_start();
require_once 'api/db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];
$error = '';
$success = '';

// All users are in the unified 'users' table
$table = 'users';
$name_field = 'full_name'; 

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_name = trim($_POST['name'] ?? '');
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (empty($new_name)) {
        $error = "Name cannot be empty.";
    } else {
        // Update basic info
        $update_query = "UPDATE $table SET $name_field = ?";
        $params = [$new_name];

        // Specific fields per role
        if ($role === 'instructor' || $role === 'creator') { // Handle both legacy and new role name
            if (isset($_POST['bio'])) {
                $update_query .= ", bio = ?";
                $params[] = trim($_POST['bio']);
            }
        }

        // Add password update if requested
        if (!empty($new_password)) {
            if ($new_password !== $confirm_password) {
                $error = "Passwords do not match!";
            } elseif (strlen($new_password) < 8 || !preg_match('/[^a-zA-Z\d]/', $new_password)) {
                $error = "Password must be at least 8 characters long and contain at least one special character.";
            } else {
                $update_query .= ", password = ?";
                $params[] = password_hash($new_password, PASSWORD_DEFAULT);
            }
        }

        if (empty($error)) {
            $update_query .= " WHERE id = ?";
            $params[] = $user_id;

            try {
                $stmt = $pdo->prepare($update_query);
                $stmt->execute($params);
                $_SESSION['user_name'] = $new_name; // update session name
                $success = "Profile updated successfully!";
            } catch (PDOException $e) {
                $error = "Failed to update profile. " . $e->getMessage();
            }
        }
    }
}

// Fetch current data
$stmt = $pdo->prepare("SELECT * FROM $table WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

?>
<?php require_once 'includes/header.php'; ?>

<main style="min-height: 80vh; padding: 4rem 20px;">
    <div style="max-width: 600px; margin: 0 auto;">
        
        <div class="glass-panel" style="padding: 3rem;">
            <div style="text-align: center; margin-bottom: 2rem;">
                <div style="width: 80px; height: 80px; border-radius: 50%; background: var(--accent-green); color: black; display: flex; align-items: center; justify-content: center; font-size: 2rem; font-weight: bold; margin: 0 auto 1rem auto;">
                    <?= strtoupper(substr($user[$name_field], 0, 1)) ?>
                </div>
                <h2 style="font-size: 2rem; text-transform: uppercase;">Profile Management</h2>
                <p style="color: var(--text-secondary); text-transform: capitalize;"><?= htmlspecialchars($role) ?> Account</p>
            </div>

            <?php if ($error): ?>
                <div style="background: rgba(2ef, 68, 68, 0.1); border-left: 4px solid #ef4444; color: #ef4444; padding: 1rem; margin-bottom: 2rem; border-radius: 4px;">
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div style="background: rgba(0, 229, 153, 0.1); border-left: 4px solid var(--accent-green); color: var(--accent-green); padding: 1rem; margin-bottom: 2rem; border-radius: 4px;">
                    <?= htmlspecialchars($success) ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="profile.php">
                <div style="margin-bottom: 1.5rem;">
                    <label style="display: block; color: var(--text-secondary); margin-bottom: 0.5rem; font-size: 0.9rem;">
                        <?= $role === 'admin' ? 'Username' : 'Full Name' ?>
                    </label>
                    <input type="text" name="name" value="<?= htmlspecialchars($user[$name_field]) ?>" required style="width: 100%; padding: 12px; background: rgba(255,255,255,0.05); border: 1px solid var(--border-light); border-radius: 8px; color: white; outline: none; transition: 0.3s;" onfocus="this.style.borderColor='var(--accent-green)'" onblur="this.style.borderColor='var(--border-light)'">
                </div>

                <?php /* current_field removed from schema */ ?>

                <?php if ($role === 'creator'): ?>
                <div style="margin-bottom: 1.5rem;">
                    <label style="display: block; color: var(--text-secondary); margin-bottom: 0.5rem; font-size: 0.9rem;">Instructor Bio</label>
                    <textarea name="bio" rows="4" style="width: 100%; padding: 12px; background: rgba(255,255,255,0.05); border: 1px solid var(--border-light); border-radius: 8px; color: white; outline: none;"><?= htmlspecialchars($user['bio'] ?? '') ?></textarea>
                </div>
                <?php endif; ?>

                <hr style="border: 0; border-top: 1px solid var(--border-light); margin: 2rem 0;">
                <h3 style="font-size: 1.2rem; margin-bottom: 1.5rem;">Update Password</h3>

                <div style="margin-bottom: 1.5rem;">
                    <label style="display: block; color: var(--text-secondary); margin-bottom: 0.5rem; font-size: 0.9rem;">New Password (leave blank to keep current)</label>
                    <div style="position: relative;">
                        <input type="password" name="new_password" id="new_password" style="width: 100%; padding: 12px; background: rgba(255,255,255,0.05); border: 1px solid var(--border-light); border-radius: 8px; color: white; outline: none;">
                        <span onclick="togglePassword('new_password', this)" style="position: absolute; right: 12px; top: 12px; cursor: pointer; color: var(--text-secondary); opacity: 0.7;">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                        </span>
                    </div>
                </div>

                <div style="margin-bottom: 1.5rem;">
                    <label style="display: block; color: var(--text-secondary); margin-bottom: 0.5rem; font-size: 0.9rem;">Confirm New Password</label>
                    <div style="position: relative;">
                        <input type="password" name="confirm_password" id="confirm_password" style="width: 100%; padding: 12px; background: rgba(255,255,255,0.05); border: 1px solid var(--border-light); border-radius: 8px; color: white; outline: none;">
                        <span onclick="togglePassword('confirm_password', this)" style="position: absolute; right: 12px; top: 12px; cursor: pointer; color: var(--text-secondary); opacity: 0.7;">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                        </span>
                    </div>
                </div>
                <div id="password_hint" style="text-align: left; margin-bottom: 20px; margin-top: -15px; display: none;">
                    <small style="color: #ffb3c1; font-size: 0.75rem;">Hint: Min 8 characters and at least one special character required.</small>
                </div>

                <button type="submit" class="btn-neon" style="width: 100%; text-align: center; border: none; font-size: 1rem; cursor: pointer;">Save Changes</button>
            </form>

        </div>
    </div>
</main>

<script>
const passInput = document.getElementById('new_password');
const passHint = document.getElementById('password_hint');
if (passInput) {
    passInput.addEventListener('input', () => {
        const val = passInput.value;
        const hasSpecial = /[^a-zA-Z\d]/.test(val);
        if (val.length > 0 && (val.length < 8 || !hasSpecial)) {
            passHint.style.display = 'block';
        } else {
            passHint.style.display = 'none';
        }
    });
}

function togglePassword(id, el) {
    const input = document.getElementById(id);
    const isPass = input.type === "password";
    input.type = isPass ? "text" : "password";
    el.innerHTML = isPass 
        ? '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line></svg>'
        : '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>';
}
</script>
<?php require_once 'includes/footer.php'; ?>
