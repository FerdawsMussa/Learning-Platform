<?php
// dash_creator.php
// Expected variables from dashboard.php: $user_id, $pdo

// 1. Fetch Total Content Count
$contentCountStmt = $pdo->prepare("SELECT COUNT(*) FROM content WHERE uploaded_by = ?");
$contentCountStmt->execute([$user_id]);
$totalContent = $contentCountStmt->fetchColumn();

// 2. Fetch Total Views & Downloads
// (Joining the content table to aggregate analytics specific to this creator's content)
$viewsStmt = $pdo->prepare("SELECT COUNT(*) FROM views v JOIN content c ON v.content_id = c.id WHERE c.uploaded_by = ?");
$viewsStmt->execute([$user_id]);
$totalViews = $viewsStmt->fetchColumn();

$dlStmt = $pdo->prepare("SELECT COUNT(*) FROM downloads d JOIN content c ON d.content_id = c.id WHERE c.uploaded_by = ?");
$dlStmt->execute([$user_id]);
$totalDownloads = $dlStmt->fetchColumn();

// 3. Fetch recent content uploaded by this user
$recentStmt = $pdo->prepare("SELECT id, title, category, level, file_path, created_at FROM content WHERE uploaded_by = ? ORDER BY created_at DESC LIMIT 10");
$recentStmt->execute([$user_id]);
$myContent = $recentStmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="row">
    <!-- Content Studio: Upload Form -->
    <div class="col-lg-5 mb-4 animate-up">
        <div class="glass-panel p-4 h-100 d-flex flex-column">
            <h2 class="h5 border-bottom pb-2 mb-3" style="border-color: var(--border-light) !important;">Content Studio</h2>
            <form action="api/upload.php" method="POST" enctype="multipart/form-data" class="flex-grow-1 d-flex flex-column">
                <div class="mb-3">
                    <label class="form-label text-secondary small">Material Title</label>
                    <input type="text" class="form-control" name="title" required>
                </div>
                
                <div class="mb-3">
                    <label class="form-label text-secondary small">Description</label>
                    <textarea class="form-control" name="description" rows="2" placeholder="Brief context about this material..." required></textarea>
                </div>
                
                <div class="row mb-3">
                    <div class="col-6">
                        <label class="form-label text-secondary small">Category</label>
                        <select class="form-select" name="category">
                            <option>Programming</option>
                            <option>Design</option>
                            <option>Business</option>
                        </select>
                    </div>
                    <div class="col-6">
                        <label class="form-label text-secondary small">Level</label>
                        <select class="form-select" name="level">
                            <option>Beginner</option>
                            <option>Intermediate</option>
                            <option>Advanced</option>
                        </select>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label text-secondary small">Upload File (PDF/Video)</label>
                    <input class="form-control" type="file" name="file" required>
                </div>
                
                <button type="submit" class="btn btn-neon w-100 mt-auto rounded-pill py-2">Upload Material</button>
            </form>
        </div>
    </div>

    <!-- Engagement Stats & Content Management -->
    <div class="col-lg-7 mb-4 animate-up" style="animation-delay:0.1s;">
        <div class="glass-panel p-4 h-100">
            <h2 class="h5 border-bottom pb-2 mb-3" style="border-color: var(--border-light) !important;">Analytics & Management</h2>
            
            <div class="row mb-4">
                <div class="col-md-4">
                    <div class="p-3 rounded" style="background: rgba(0, 229, 153, 0.05); border: 1px solid rgba(0, 229, 153, 0.2);">
                        <div class="small text-secondary mb-1">Total Views</div>
                        <div class="h3 mb-0 text-gradient"><?= number_format($totalViews) ?></div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="p-3 rounded" style="background: rgba(62, 139, 255, 0.05); border: 1px solid rgba(62, 139, 255, 0.2);">
                        <div class="small text-secondary mb-1">Downloads</div>
                        <div class="h3 mb-0 text-white"><?= number_format($totalDownloads) ?></div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="p-3 rounded" style="background: rgba(255, 193, 7, 0.05); border: 1px solid rgba(255, 193, 7, 0.2);">
                        <div class="small text-secondary mb-1">Uploaded Items</div>
                        <div class="h3 mb-0 text-white"><?= number_format($totalContent) ?> <span class="fs-6 text-warning">📚</span></div>
                    </div>
                </div>
            </div>

            <h3 class="h6 mb-3 text-secondary">My Content</h3>
            <div class="table-responsive">
                <table class="table glass-table mb-0">
                    <thead>
                        <tr>
                            <th>Title</th>
                            <th>Category</th>
                            <th>Level</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(empty($myContent)): ?>
                        <tr><td colspan="4" class="text-center text-secondary py-3">No content uploaded yet.</td></tr>
                        <?php else: ?>
                            <?php foreach($myContent as $content): ?>
                            <tr>
                                <td class="text-truncate" style="max-width: 150px;" title="<?= htmlspecialchars($content['title']) ?>"><?= htmlspecialchars($content['title']) ?></td>
                                <td><span class="badge" style="background: rgba(62, 139, 255, 0.1); color: var(--accent-blue);"><?= htmlspecialchars($content['category']) ?></span></td>
                                <td class="text-secondary small"><?= htmlspecialchars($content['level']) ?></td>
                                <td>
                                    <!-- Triggers Edit Modal via JS -->
                                    <button class="btn btn-sm text-warning" onclick="openEditModal(<?= $content['id'] ?>, '<?= htmlspecialchars(addslashes($content['title'])) ?>', '<?= htmlspecialchars(addslashes($content['category'])) ?>')" title="Edit">✏️</button>
                                    <!-- Triggers delete visually before jumping to endpoint -->
                                    <a href="api/delete_content.php?id=<?= $content['id'] ?>" class="btn btn-sm text-danger" onclick="return confirm('Are you sure you want to permanently delete this material?');" title="Delete">🗑️</a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Edit Content Modal -->
<div class="modal fade" id="editContentModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content" style="background: var(--bg-dark); border: 1px solid var(--border-light);">
      <div class="modal-header border-bottom-0">
        <h5 class="modal-title text-white">Edit Material</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form action="api/edit_content.php" method="POST">
          <div class="modal-body">
              <input type="hidden" name="content_id" id="editContentId">
              <div class="mb-3">
                  <label class="form-label text-secondary small">Title</label>
                  <input type="text" class="form-control" name="title" id="editContentTitle" required>
              </div>
              <div class="mb-3">
                  <label class="form-label text-secondary small">Category</label>
                  <select class="form-select" name="category" id="editContentCategory">
                      <option>Programming</option>
                      <option>Design</option>
                      <option>Business</option>
                  </select>
              </div>
          </div>
          <div class="modal-footer border-top-0">
              <button type="submit" class="btn btn-neon w-100">Save Changes</button>
          </div>
      </form>
    </div>
  </div>
</div>

<script>
function openEditModal(id, title, category) {
    document.getElementById('editContentId').value = id;
    document.getElementById('editContentTitle').value = title;
    document.getElementById('editContentCategory').value = category;
    
    var editModal = new bootstrap.Modal(document.getElementById('editContentModal'));
    editModal.show();
}
</script>
