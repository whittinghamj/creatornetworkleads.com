<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireAdmin();

$db = getDB();
ensureMessageTemplatesSchema($db);

$id = getInt('id');
$isNew = ($id === 0);

$template = [
    'id' => 0,
    'title' => '',
    'category' => '',
    'content' => "Hi [firstname],\n\nI hope you're well. My name is [fullname] from [company].\n\nI'd love to connect regarding a collaboration opportunity.\n\nBest regards,\n[fullname]\n[email]\n[tel]",
    'is_published' => 1,
    'sort_order' => 0,
];

if (!$isNew) {
    $stmt = $db->prepare('SELECT * FROM message_templates WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    $existing = $stmt->fetch();
    if (!$existing) {
        flash('Message template not found.', 'danger');
        header('Location: /admin/message-templates.php');
        exit;
    }
    $template = array_merge($template, $existing);
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $template['title'] = postStr('title');
    $template['category'] = postStr('category');
    $template['content'] = postStr('content');
    $template['is_published'] = postStr('is_published') === '1' ? 1 : 0;
    $template['sort_order'] = (int)postStr('sort_order');

    if ($template['title'] === '') {
        $errors[] = 'Template title is required.';
    }
    if ($template['content'] === '') {
        $errors[] = 'Template content is required.';
    }

    if (empty($errors)) {
        if ($isNew) {
            $stmt = $db->prepare(
                'INSERT INTO message_templates (title, category, content, is_published, sort_order)
                 VALUES (?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                $template['title'],
                $template['category'] ?: null,
                $template['content'],
                $template['is_published'],
                $template['sort_order'],
            ]);
            flash('Message template created successfully.', 'success');
        } else {
            $stmt = $db->prepare(
                'UPDATE message_templates
                 SET title = ?, category = ?, content = ?, is_published = ?, sort_order = ?
                 WHERE id = ?'
            );
            $stmt->execute([
                $template['title'],
                $template['category'] ?: null,
                $template['content'],
                $template['is_published'],
                $template['sort_order'],
                $id,
            ]);
            flash('Message template updated successfully.', 'success');
        }

        header('Location: /admin/message-templates.php');
        exit;
    }
}

$pageTitle = $isNew ? 'Add Message Template' : 'Edit Message Template';
$activeNav = 'message-templates';
require __DIR__ . '/includes/header.php';
?>

<div class="d-flex align-items-center gap-3 mb-4">
    <a href="/admin/message-templates.php" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Back
    </a>
    <h5 class="fw-bold mb-0"><?= $isNew ? 'Add Message Template' : 'Edit Message Template' ?></h5>
</div>

<?php if (!empty($errors)): ?>
<div class="alert alert-danger">
    <ul class="mb-0 ps-3 small">
        <?php foreach ($errors as $err): ?>
            <li><?= e($err) ?></li>
        <?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>

<div class="card border-0 shadow-sm" style="border-radius:14px">
    <div class="card-header bg-white border-bottom fw-semibold py-3">Template Details</div>
    <div class="card-body p-4">
        <form method="POST" action="/admin/message-template-form.php<?= $isNew ? '' : '?id=' . $id ?>">
            <?= csrfField() ?>

            <div class="row g-3">
                <div class="col-md-8">
                    <label class="form-label small fw-semibold">Template Title</label>
                    <input type="text" name="title" class="form-control" value="<?= e($template['title']) ?>" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label small fw-semibold">Category</label>
                    <input type="text" name="category" class="form-control" value="<?= e((string)($template['category'] ?? '')) ?>" placeholder="e.g. Intro, Follow-up, Re-engagement">
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-semibold">Sort Order</label>
                    <input type="number" name="sort_order" class="form-control" value="<?= (int)$template['sort_order'] ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-semibold">Status</label>
                    <select name="is_published" class="form-select">
                        <option value="1" <?= (int)$template['is_published'] === 1 ? 'selected' : '' ?>>Published</option>
                        <option value="0" <?= (int)$template['is_published'] === 0 ? 'selected' : '' ?>>Draft</option>
                    </select>
                </div>

                <div class="col-12">
                    <label class="form-label small fw-semibold">Message Content</label>
                    <textarea id="templateContentInput" name="content" class="form-control" rows="12" required><?= e((string)$template['content']) ?></textarea>
                    <div class="form-text">
                        Supported tags: <code>[firstname]</code>, <code>[fullname]</code>, <code>[company]</code>, <code>[agency]</code>, <code>[email]</code>, <code>[tel]</code>
                    </div>
                </div>

                <div class="col-12">
                    <label class="form-label small fw-semibold">Live Preview (Tags Highlighted)</label>
                    <pre id="templateContentPreview" class="template-preview-pane mb-0"></pre>
                </div>

                <div class="col-12 pt-2">
                    <button type="submit" class="btn btn-danger">
                        <i class="bi bi-save me-1"></i><?= $isNew ? 'Create Template' : 'Save Changes' ?>
                    </button>
                    <a href="/admin/message-templates.php" class="btn btn-outline-secondary ms-2">Cancel</a>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
(function () {
    var input = document.getElementById('templateContentInput');
    var preview = document.getElementById('templateContentPreview');
    if (!input || !preview) {
        return;
    }

    function escapeHtml(str) {
        return str.replace(/[&<>"']/g, function (m) {
            return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'})[m];
        });
    }

    function renderPreview() {
        var raw = input.value || '';
        if (!raw.trim()) {
            preview.innerHTML = '<span class="text-muted">Template preview will appear here...</span>';
            return;
        }

        var escaped = escapeHtml(raw);
        escaped = escaped.replace(/\[(firstname|fullname|company|agency|email|tel)\]/gi, '<span class="template-tag-highlight">[$1]</span>');
        preview.innerHTML = escaped;
    }

    input.addEventListener('input', renderPreview);
    renderPreview();
})();
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>
