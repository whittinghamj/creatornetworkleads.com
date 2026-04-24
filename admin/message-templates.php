<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireAdmin();

$db = getDB();
ensureMessageTemplatesSchema($db);

$templates = $db->query(
    'SELECT * FROM message_templates ORDER BY sort_order ASC, id DESC'
)->fetchAll();

$pageTitle = 'Message Templates';
$activeNav = 'message-templates';
require __DIR__ . '/includes/header.php';
?>

<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
    <div>
        <h5 class="fw-bold mb-0">Message Templates</h5>
        <p class="text-muted small mb-0"><?= number_format(count($templates)) ?> template(s)</p>
    </div>
    <a href="/admin/message-template-form.php" class="btn btn-danger btn-sm">
        <i class="bi bi-plus-circle me-1"></i>Add Template
    </a>
</div>

<div class="card border-0 shadow-sm mb-4" style="border-radius:14px">
    <div class="card-body p-3">
        <div class="small text-muted mb-1">Available tags (admin use when writing templates)</div>
        <div class="d-flex flex-wrap gap-2">
            <span class="badge bg-secondary">[firstname]</span>
            <span class="badge bg-secondary">[fullname]</span>
            <span class="badge bg-secondary">[company]</span>
            <span class="badge bg-secondary">[agency]</span>
            <span class="badge bg-secondary">[email]</span>
            <span class="badge bg-secondary">[tel]</span>
        </div>
    </div>
</div>

<div class="table-card mb-4">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Title</th>
                    <th>Category</th>
                    <th>Preview</th>
                    <th>Order</th>
                    <th>Status</th>
                    <th>Updated</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($templates as $t): ?>
                <tr>
                    <td class="text-muted small"><?= (int)$t['id'] ?></td>
                    <td class="fw-semibold"><?= e($t['title']) ?></td>
                    <td>
                        <?php if (trim((string)($t['category'] ?? '')) !== ''): ?>
                            <span class="badge bg-info text-dark"><?= e((string)$t['category']) ?></span>
                        <?php else: ?>
                            <span class="text-muted small">General</span>
                        <?php endif; ?>
                    </td>
                    <td class="small text-muted">
                        <?= e(mb_strimwidth(preg_replace('/\s+/', ' ', (string)$t['content']), 0, 110, '...')) ?>
                    </td>
                    <td><span class="badge bg-secondary"><?= (int)$t['sort_order'] ?></span></td>
                    <td>
                        <?php if ((int)$t['is_published'] === 1): ?>
                            <span class="badge bg-success">Published</span>
                        <?php else: ?>
                            <span class="badge bg-secondary">Draft</span>
                        <?php endif; ?>
                    </td>
                    <td class="small text-muted">
                        <?= !empty($t['updated_at']) ? e(date('d M Y H:i', strtotime((string)$t['updated_at']))) : '—' ?>
                    </td>
                    <td class="text-end">
                        <div class="d-flex gap-1 justify-content-end">
                            <a href="/admin/message-template-form.php?id=<?= (int)$t['id'] ?>"
                               class="btn btn-sm btn-outline-primary py-0 px-2" title="Edit">
                                <i class="bi bi-pencil"></i>
                            </a>
                            <a href="/admin/message-template-delete.php?id=<?= (int)$t['id'] ?>&csrf=<?= urlencode(csrfToken()) ?>"
                               class="btn btn-sm btn-outline-danger py-0 px-2 btn-confirm-delete"
                               data-label="<?= e($t['title']) ?>" title="Delete">
                                <i class="bi bi-trash"></i>
                            </a>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($templates)): ?>
                <tr><td colspan="8" class="text-center py-4 text-muted">No templates added yet.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
