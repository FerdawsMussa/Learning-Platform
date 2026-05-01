<?php
require_once 'session_helper.php';
// Try to find any active role session
$found_session = false;
foreach (['ADMIN', 'INSTRUCTOR', 'STUDENT'] as $r) {
    $sname = 'JU_' . $r . '_SESS';
    if (isset($_COOKIE[$sname])) {
        session_name($sname);
        session_start();
        if (isset($_SESSION['user_id'])) {
            $found_session = true;
            break;
        }
        session_write_close();
    }
}
if (!$found_session) {
    session_start(); // Fallback to default
}
require_once 'db.php';

// Protect Route - Basic authentication for all, but role-based for specific actions
if (!isset($_SESSION['user_id'])) {
    die(json_encode(['success' => false, 'message' => 'Unauthorized Access']));
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'];

// ── GET PROFILE HANDLER (Allow Students/Instructors/Admins) ──
if (isset($_GET['get_profile'])) {
    $target_id = (int)$_GET['get_profile'];
    $role = $_GET['role'] ?? 'student';

    try {
        if ($role === 'course') {
            $stmt = $pdo->prepare("SELECT * FROM (SELECT id, user_id AS creator_id, title, description, file_path AS thumbnail_path, category, generic_value AS level, meta_text AS tags, is_resource, is_deleted, deleted_at, created_at FROM content WHERE record_type = 'course') content_courses WHERE id = ?");
            $stmt->execute([$target_id]);
            $data = $stmt->fetch(PDO::FETCH_ASSOC);
        } else {
            // Fetch User/Instructor Profile
            $stmt = $pdo->prepare("SELECT id, full_name, last_name, email, role, profile_pic, bio, is_approved, created_at, courses, meta FROM users WHERE id = ?");
            $stmt->execute([$target_id]);
            $data = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($data) {
                // Fetch Instructor Meta from JSON column
                $data['meta'] = json_decode($data['meta'] ?: '{}', true);

                if ($data['role'] === 'instructor') {
                    $cats = [];
                    if (!empty($data['meta']['category'])) $cats[] = $data['meta']['category'];
                    if (!empty($data['meta']['approved_categories'])) {
                        $cats = array_unique(array_merge($cats, $data['meta']['approved_categories']));
                    }
                    $data['meta']['category'] = !empty($cats) ? implode(', ', $cats) : 'Unassigned';
                }

                // Enroll count for students
                if ($data['role'] === 'student') {
                    $courses = json_decode($data['courses'] ?: '[]', true);
                    $data['enroll_count'] = count($courses);
                    
                    if ($data['enroll_count'] > 0) {
                        $placeholders = implode(',', array_fill(0, count($courses), '?'));
                        $c_stmt = $pdo->prepare("SELECT title FROM content WHERE id IN ($placeholders) AND record_type = 'course'");
                        $c_stmt->execute($courses);
                        $data['content_courses'] = $c_stmt->fetchAll(PDO::FETCH_COLUMN);
                    } else {
                        $data['content_courses'] = [];
                    }
                }
            }
        }

        if ($data) {
            echo json_encode(['success' => true, 'data' => $data]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Profile not found.']);
        }
        exit;
    } catch (PDOException $e) {
        die(json_encode(['success' => false, 'message' => $e->getMessage()]));
    }
}

// ── ADMIN ONLY ACTIONS ──
if ($user_role !== 'admin') {
    die(json_encode(['success' => false, 'message' => 'Restricted to Administrators']));
}

$action = $_POST['action'] ?? '';
$target_role = $_POST['role_type'] ?? ''; 
$target_id = $_POST['user_id'] ?? null;

// Move notification actions ABOVE the role check since they don't depend on a target role
if ($action === 'clear_all_notifs') {
    try {
        $stmt = $pdo->prepare("DELETE FROM notifications WHERE target_role = 'admin'");
        $stmt->execute();
        echo json_encode(['success' => true]);
        exit;
    } catch (PDOException $e) {
        die(json_encode(['success' => false, 'message' => $e->getMessage()]));
    }
} elseif ($action === 'dismiss_notif') {
    $notif_id = $_POST['notif_id'] ?? null;
    if (!$notif_id) die('Missing ID');
    try {
        $stmt = $pdo->prepare("DELETE FROM notifications WHERE target_role = 'admin' WHERE id = ?");
        $stmt->execute([$notif_id]);
        echo json_encode(['success' => true]);
        exit;
    } catch (PDOException $e) {
        die(json_encode(['success' => false, 'message' => $e->getMessage()]));
    }
}

if ($action === 'create') {
    $full_name_input = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $password = password_hash(trim($_POST['password']), PASSWORD_DEFAULT);
    $mapped_role = ($target_role === 'creator') ? 'instructor' : 'student';

    // Split Name Logic: Everything after the space is the last name
    $name_parts = explode(' ', $full_name_input);
    if (count($name_parts) > 1) {
        $last_name = array_pop($name_parts);
        $full_name = implode(' ', $name_parts);
    } else {
        $full_name = $full_name_input;
        $last_name = '';
    }

    try {
        $stmt = $pdo->prepare("INSERT INTO users (full_name, last_name, email, password, role, is_approved, courses, resources) VALUES (?, ?, ?, ?, ?, ?, '[]', '[]')");
        $is_approved = 1; // Admin created users are pre-approved
        $stmt->execute([$full_name, $last_name, $email, $password, $mapped_role, $is_approved]);

        $logStmt = $pdo->prepare("INSERT INTO system_logs (event_type, user_id, user_role, description) VALUES ('create_user', ?, 'admin', ?)");
        $logStmt->execute([$_SESSION['user_id'], "Admin created a new $mapped_role: $email"]);

        header("Location: ../dashboards/admin.php?view=" . $target_role . "s&success=UserCreated");
        exit;
    } catch (PDOException $e) {
        die(json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]));
    }

} elseif ($action === 'delete_user') {
    if (!$target_id) die('Missing user ID.');

    try {
        if ($target_role === 'creator') {
            // Instead of deleting, ban for 3 months
            $stmt = $pdo->prepare("UPDATE users SET banned_until = DATE_ADD(NOW(), INTERVAL 3 MONTH) WHERE id = ? AND role = 'instructor'");
            $stmt->execute([$target_id]);
            
            $logStmt = $pdo->prepare("INSERT INTO system_logs (event_type, user_id, user_role, description) VALUES ('ban_user', ?, 'admin', ?)");
            $logStmt->execute([$_SESSION['user_id'], "Admin banned instructor ID: $target_id for 3 months"]);
        } else {
            // For students or others, keep existing logic or adapt
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$target_id]);

            $logStmt = $pdo->prepare("INSERT INTO system_logs (event_type, user_id, user_role, description) VALUES ('delete_user', ?, 'admin', ?)");
            $logStmt->execute([$_SESSION['user_id'], "Admin deleted $target_role ID: $target_id"]);
        }

        // Cleanup associated admin notifications if applicable
        if ($target_role === 'creator') {
            $notifCleanup = $pdo->prepare("DELETE FROM notifications WHERE user_id = ? AND target_role = 'admin'");
            $notifCleanup->execute([$target_id]);
        }

        echo json_encode(['success' => true]);
        exit;
    } catch (PDOException $e) {
        die(json_encode(['success' => false, 'message' => $e->getMessage()]));
    }


} elseif ($action === 'unban_user') {
    if (!$target_id) die('Missing user ID.');
    try {
        $stmt = $pdo->prepare("UPDATE users SET banned_until = NULL WHERE id = ?");
        $stmt->execute([$target_id]);
        
        $logStmt = $pdo->prepare("INSERT INTO system_logs (event_type, user_id, user_role, description) VALUES ('unban_user', ?, 'admin', ?)");
        $logStmt->execute([$_SESSION['user_id'], "Admin unbanned user ID: $target_id"]);

        echo json_encode(['success' => true]);
        exit;
    } catch (PDOException $e) {
        die(json_encode(['success' => false, 'message' => $e->getMessage()]));
    }

} elseif ($action === 'delete_course') {
    $course_id = $_POST['course_id'] ?? null;
    if (!$course_id) die('Missing course ID.');

    try {
        // Soft-Delete: Admin marks course as deleted
        $stmt = $pdo->prepare("UPDATE content SET is_deleted = 1, deleted_at = NOW() WHERE id = ?");
        $stmt->execute([$course_id]);

        $logStmt = $pdo->prepare("INSERT INTO system_logs (event_type, user_id, user_role, description, target_id, target_type) VALUES ('delete_course', ?, 'admin', ?, ?, 'course')");
        $logStmt->execute([$_SESSION['user_id'], "Admin deleted course ID: $course_id", $course_id]);

        echo json_encode(['success' => true]);
        exit;
    } catch (PDOException $e) {
        die(json_encode(['success' => false, 'message' => $e->getMessage()]));
    }

} elseif ($action === 'restore_course') {
    $course_id = $_POST['course_id'] ?? null;
    if (!$course_id) die('Missing course ID.');

    try {
        // Check if within 1 hour window
        $stmt = $pdo->prepare("SELECT deleted_at FROM (SELECT id, user_id AS creator_id, title, description, file_path AS thumbnail_path, category, generic_value AS level, meta_text AS tags, is_resource, is_deleted, deleted_at, created_at FROM content WHERE record_type = 'course') content_courses WHERE id = ? AND is_deleted = 1");
        $stmt->execute([$course_id]);
        $course = $stmt->fetch();

        if ($course && (time() - strtotime($course['deleted_at']) <= 3600)) {
            $stmt = $pdo->prepare("UPDATE content SET is_deleted = 0, deleted_at = NULL WHERE id = ?");
            $stmt->execute([$course_id]);

            $logStmt = $pdo->prepare("INSERT INTO system_logs (event_type, user_id, user_role, description) VALUES ('restore_course', ?, 'admin', ?)");
            $logStmt->execute([$_SESSION['user_id'], "Admin restored course ID: $course_id"]);

            echo json_encode(['success' => true, 'message' => 'Course restored successfully.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Restoration window has expired or course already active.']);
        }
        exit;
    } catch (PDOException $e) {
        die(json_encode(['success' => false, 'message' => $e->getMessage()]));
    }


} elseif ($action === 'delete_resource') {
    $resource_id = $_POST['resource_id'] ?? null;
    if (!$resource_id) die('Missing resource ID.');

    try {
        // Soft-Delete: Admin marks resource as deleted
        $stmt = $pdo->prepare("UPDATE content SET is_deleted = 1, deleted_at = NOW() WHERE id = ?");
        $stmt->execute([$resource_id]);

        $logStmt = $pdo->prepare("INSERT INTO system_logs (event_type, user_id, user_role, description, target_id, target_type) VALUES ('delete_resource', ?, 'admin', ?, ?, 'resource')");
        $logStmt->execute([$_SESSION['user_id'], "Admin deleted resource ID: $resource_id", $resource_id]);

        echo json_encode(['success' => true]);
        exit;
    } catch (PDOException $e) {
        die(json_encode(['success' => false, 'message' => $e->getMessage()]));
    }

} elseif ($action === 'restore_resource') {
    $resource_id = $_POST['resource_id'] ?? null;
    if (!$resource_id) die('Missing resource ID.');

    try {
        // Check if within 1 hour window
        $stmt = $pdo->prepare("SELECT deleted_at FROM (SELECT id, user_id AS creator_id, title, file_path, generic_value AS file_type, category, is_deleted, deleted_at, created_at FROM content WHERE record_type = 'resource') content_resources WHERE id = ? AND is_deleted = 1");
        $stmt->execute([$resource_id]);
        $resource = $stmt->fetch();

        if ($resource && (time() - strtotime($resource['deleted_at']) <= 3600)) {
            $stmt = $pdo->prepare("UPDATE content SET is_deleted = 0, deleted_at = NULL WHERE id = ?");
            $stmt->execute([$resource_id]);

            $logStmt = $pdo->prepare("INSERT INTO system_logs (event_type, user_id, user_role, description) VALUES ('restore_resource', ?, 'admin', ?)");
            $logStmt->execute([$_SESSION['user_id'], "Admin restored resource ID: $resource_id"]);

            echo json_encode(['success' => true, 'message' => 'Resource restored successfully.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Restoration window has expired.']);
        }
        exit;
    } catch (PDOException $e) {
        die(json_encode(['success' => false, 'message' => $e->getMessage()]));
    }


} elseif ($action === 'approve') {
    if ($target_role === 'creator' && $target_id) {
        try {
            $stmt = $pdo->prepare("UPDATE users SET is_approved = 1 WHERE id = ? AND role = 'instructor'");
            $stmt->execute([$target_id]);

            // Cleanup associated admin notification
            $notifCleanup = $pdo->prepare("DELETE FROM notifications WHERE user_id = ? AND target_role = 'admin'");
            $notifCleanup->execute([$target_id]);

            // Trigger Notification Category: Instructor Alert
            $notif = $pdo->prepare("INSERT INTO notifications (user_id, target_role, message) VALUES (?, 'instructor', ?)");
            $notif->execute([$target_id, "Your instructor account has been approved by the Admin. You can now upload content_courses and content_resources."]);

            $logStmt = $pdo->prepare("INSERT INTO system_logs (event_type, user_id, user_role, description, target_id, target_type) VALUES ('approve_creator', ?, 'admin', ?, ?, 'creator')");
            $logStmt->execute([$_SESSION['user_id'], "Admin approved instructor ID: $target_id", $target_id]);

            echo json_encode(['success' => true]);
            exit;
        } catch (PDOException $e) {
            die(json_encode(['success' => false, 'message' => $e->getMessage()]));
        }
    }
} elseif ($action === 'approve_category') {
    $notif_id = $_POST['notif_id'] ?? null;
    $instructor_id = $_POST['instructor_id'] ?? null;
    $category = $_POST['category'] ?? null;

    if ($notif_id && $instructor_id && $category) {
        try {
            // Update instructor's meta to include the approved category
            $stmt = $pdo->prepare("SELECT meta FROM users WHERE id = ?");
            $stmt->execute([$instructor_id]);
            $meta = json_decode($stmt->fetchColumn() ?: '{}', true);

            $approved = $meta['approved_categories'] ?? [];
            if (!in_array($category, $approved)) {
                $approved[] = $category;
            }
            $meta['approved_categories'] = $approved;

            $update = $pdo->prepare("UPDATE users SET meta = ? WHERE id = ?");
            $update->execute([json_encode($meta), $instructor_id]);

            // Notify instructor
            $notif = $pdo->prepare("INSERT INTO notifications (user_id, target_role, message) VALUES (?, 'instructor', ?)");
            $notif->execute([$instructor_id, "Your request to teach in the '$category' domain has been approved!"]);

            // Dismiss admin notification
            $dismiss = $pdo->prepare("DELETE FROM notifications WHERE id = ?");
            $dismiss->execute([$notif_id]);

            echo json_encode(['success' => true]);
            exit;
        } catch (PDOException $e) {
            die(json_encode(['success' => false, 'message' => $e->getMessage()]));
        }
    }
} elseif ($action === 'decline_category') {
    $notif_id = $_POST['notif_id'] ?? null;
    $instructor_id = $_POST['instructor_id'] ?? null;
    $category = $_POST['category'] ?? null;

    if ($notif_id && $instructor_id && $category) {
        try {
            // Notify instructor
            $notif = $pdo->prepare("INSERT INTO notifications (user_id, target_role, message) VALUES (?, 'instructor', ?)");
            $notif->execute([$instructor_id, "Your request to teach in the '$category' domain has been declined."]);

            // Dismiss admin notification
            $dismiss = $pdo->prepare("DELETE FROM notifications WHERE id = ?");
            $dismiss->execute([$notif_id]);

            echo json_encode(['success' => true]);
            exit;
        } catch (PDOException $e) {
            die(json_encode(['success' => false, 'message' => $e->getMessage()]));
        }
    }
} elseif ($action === 'approve_course') {
    $course_id  = $_POST['course_id']  ?? null;
    $notif_id   = $_POST['notif_id']   ?? null;
    $instructor_id = $_POST['instructor_id'] ?? null;

    if ($course_id) {
        try {
            $pdo->prepare("UPDATE content SET is_approved = 1 WHERE id = ?")->execute([$course_id]);

            // Fetch course info for notification
            $row = $pdo->prepare("SELECT title, is_resource FROM content WHERE id = ?");
            $row->execute([$course_id]);
            $content = $row->fetch();
            $title = $content['title'] ?? 'Your content';
            $label = (isset($content['is_resource']) && $content['is_resource'] == 1) ? 'resource' : 'course';

            // Notify instructor
            if ($instructor_id) {
                $pdo->prepare("INSERT INTO notifications (user_id, target_role, message) VALUES (?, 'instructor', ?)")
                    ->execute([$instructor_id, "Your $label \"$title\" has been approved and is now live!"]);
            }

            // Dismiss admin notification
            if ($notif_id) {
                $pdo->prepare("DELETE FROM notifications WHERE id = ?")->execute([$notif_id]);
            }

            echo json_encode(['success' => true]);
            exit;
        } catch (PDOException $e) {
            die(json_encode(['success' => false, 'message' => $e->getMessage()]));
        }
    }
} elseif ($action === 'decline_course') {
    $course_id     = $_POST['course_id']     ?? null;
    $notif_id      = $_POST['notif_id']      ?? null;
    $instructor_id = $_POST['instructor_id'] ?? null;

    if ($course_id) {
        try {
            // Fetch info before deleting
            $row = $pdo->prepare("SELECT title, is_resource FROM content WHERE id = ?");
            $row->execute([$course_id]);
            $content = $row->fetch();
            $title = $content['title'] ?? 'Your content';
            $label = (isset($content['is_resource']) && $content['is_resource'] == 1) ? 'resource' : 'course';

            // Soft-delete the course
            $pdo->prepare("UPDATE content SET is_deleted = 1, deleted_at = NOW() WHERE id = ?")->execute([$course_id]);

            // Notify instructor
            if ($instructor_id) {
                $pdo->prepare("INSERT INTO notifications (user_id, target_role, message) VALUES (?, 'instructor', ?)")
                    ->execute([$instructor_id, "Your $label \"$title\" was declined by the admin."]);
            }

            // Dismiss admin notification
            if ($notif_id) {
                $pdo->prepare("DELETE FROM notifications WHERE id = ?")->execute([$notif_id]);
            }

            echo json_encode(['success' => true]);
            exit;
        } catch (PDOException $e) {
            die(json_encode(['success' => false, 'message' => $e->getMessage()]));
        }
    }
}

header("Location: ../dashboards/admin.php");
exit;
