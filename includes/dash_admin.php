<?php
// dash_admin.php
// Get all users overview
$users_stmt = $pdo->query("SELECT id, role, full_name, email FROM users 
                           WHERE role != 'admin'
                           LIMIT 10");
$users = $users_stmt->fetchAll();

// Get system activity logs. Check if table exists first using try catch to avoid breaking.
$logs = [];
try {
    $log_stmt = $pdo->query("SELECT * FROM system_logs ORDER BY created_at DESC LIMIT 10");
    if($log_stmt) {
        $logs = $log_stmt->fetchAll();
    }
} catch (PDOException $e) {
    // Table doesn't exist yet, ignore
}

?>

<div class="row">

    <!-- User Management Table -->
    <div class="col-lg-7 mb-4 animate-up">
        <div class="glass-panel p-4 h-100">
            <div class="d-flex justify-content-between align-items-center mb-3 pb-2 border-bottom" style="border-color: var(--border-light) !important;">
                <h2 class="h5 mb-0">System Overview (Users)</h2>
                <span class="badge bg-primary rounded-pill">Manage</span>
            </div>
            
            <div class="table-responsive">
                <table class="table glass-table mb-0 align-middle">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($users as $u): ?>
                        <tr>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <div class="bg-secondary rounded-circle d-flex justify-content-center align-items-center" style="width:28px;height:28px;font-size:0.8rem;">
                                        <?= strtoupper(substr($u['full_name'], 0, 1)) ?>
                                    </div>
                                    <?= htmlspecialchars($u['full_name']) ?>
                                </div>
                            </td>
                            <td><span class="text-muted small"><?= htmlspecialchars($u['email']) ?></span></td>
                            <td>
                                <?php if(strtolower($u['role']) === 'student'): ?>
                                    <span class="badge rounded-pill" style="background:rgba(62,139,255,0.2);color:var(--accent-blue);">Student</span>
                                <?php else: ?>
                                    <span class="badge rounded-pill" style="background:rgba(155,81,224,0.2);color:var(--accent-purple);">Instructor</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <button class="btn btn-sm btn-outline-danger" style="padding: 2px 8px; font-size: 0.75rem;">Ban</button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        
                        <?php if(empty($users)): ?>
                            <tr><td colspan="4" class="text-center text-muted">No users found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- System Activity Log (Sync & Logins) -->
    <div class="col-lg-5 mb-4 animate-up" style="animation-delay: 0.1s;">
         <div class="glass-panel p-4 h-100">
            <h2 class="h5 border-bottom pb-2 mb-3" style="border-color: var(--border-light) !important;">System Activity Log</h2>
            
            <div class="d-flex flex-column gap-3">
                <?php if(empty($logs)): ?>
                    <div class="text-center text-muted small py-3">No recent activity logs available.</div>
                <?php else: foreach($logs as $log): ?>
                    <div class="d-flex gap-3 align-items-start border-bottom pb-2" style="border-color: var(--border-light) !important; border-style: dashed !important;">
                        <?php 
                            $icon_color = 'var(--text-secondary)';
                            if ($log['event_type'] == 'login') $icon_color = 'var(--accent-green)';
                            if ($log['event_type'] == 'sync') $icon_color = 'var(--accent-blue)';
                            if ($log['event_type'] == 'upload') $icon_color = 'var(--accent-purple)';
                        ?>
                        <div class="mt-1" style="color: <?= $icon_color ?>">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
                        </div>
                        <div>
                            <div class="small fw-semibold"><?= htmlspecialchars($log['description']) ?></div>
                            <div class="text-muted" style="font-size: 0.75rem;">
                                <?= htmlspecialchars(date('M d, g:i A', strtotime($log['created_at']))) ?> 
                                • <span class="text-uppercase"><?= htmlspecialchars($log['user_role'] ?? 'auto') ?></span>
                            </div>
                        </div>
                    </div>
                <?php endforeach; endif; ?>
            </div>
         </div>
    </div>
</div>
