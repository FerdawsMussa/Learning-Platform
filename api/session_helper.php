<?php
/**
 * JU Learn - Role-Specific Session Manager
 * Allows multiple roles (Admin, Student, Instructor) to be logged in 
 * simultaneously in different tabs by using unique session names.
 */

function start_role_session($manual_role = null) {
    $role = 'student'; // Default

    if ($manual_role) {
        $role = $manual_role;
    } else {
        // If session already has a role, use it (Consistency)
        if (session_status() !== PHP_SESSION_NONE && isset($_SESSION['role'])) {
            $role = $_SESSION['role'];
        } else {
            $current_script = basename($_SERVER['PHP_SELF']);
            
            if (strpos($_SERVER['PHP_SELF'], 'admin') !== false) {
                $role = 'admin';
            } elseif (strpos($_SERVER['PHP_SELF'], 'instructor') !== false || strpos($_SERVER['PHP_SELF'], 'creator') !== false) {
                $role = 'instructor';
            }
        }
    }

    // Set unique session name for each role
    $session_name = 'JU_' . strtoupper($role) . '_SESS';
    
    if (session_status() === PHP_SESSION_NONE) {
        session_name($session_name);
        session_start();
    }
}

// Optionally initialize right away if this file is included
// start_role_session();
?>
