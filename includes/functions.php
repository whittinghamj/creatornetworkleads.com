<?php
/**
 * General helper functions
 */

// ---------------------------------------------------------------------------
// CSRF
// ---------------------------------------------------------------------------

function csrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrfField(): string
{
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') . '">';
}

function verifyCsrf(): void
{
    $token = $_POST['csrf_token'] ?? '';
    if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        http_response_code(403);
        die('Invalid security token. Please go back and try again.');
    }
}

// ---------------------------------------------------------------------------
// Output escaping
// ---------------------------------------------------------------------------

function e(string $v): string
{
    return htmlspecialchars($v, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

// ---------------------------------------------------------------------------
// Flash messages
// ---------------------------------------------------------------------------

function flash(string $message, string $type = 'success'): void
{
    $_SESSION['flash'] = ['message' => $message, 'type' => $type];
}

function flashHtml(): string
{
    if (empty($_SESSION['flash'])) {
        return '';
    }
    $f    = $_SESSION['flash'];
    unset($_SESSION['flash']);
    $type = in_array($f['type'], ['success', 'danger', 'warning', 'info'], true) ? $f['type'] : 'info';
    return '<div class="alert alert-' . $type . ' alert-dismissible fade show mb-3" role="alert">'
        . e($f['message'])
        . '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>'
        . '</div>';
}

// ---------------------------------------------------------------------------
// Regions
// ---------------------------------------------------------------------------

function getRegionName(string $code): string
{
    $regions = defined('REGIONS') ? REGIONS : [];
    return $regions[strtolower($code)] ?? strtoupper($code);
}

// ---------------------------------------------------------------------------
// Invitation types
// ---------------------------------------------------------------------------

function getInvitationTypes(): array
{
    return [
        ['id' => 1, 'name' => 'Regular', 'description' => 'Standard invitation tier', 'badge_color' => 'primary'],
        ['id' => 2, 'name' => 'Premium', 'description' => 'Priority invitation tier', 'badge_color' => 'warning'],
        ['id' => 3, 'name' => 'Elite',   'description' => 'Top invitation tier', 'badge_color' => 'success'],
    ];
}

function invitationTypeName(?int $id): string
{
    if ($id === null) {
        return '—';
    }
    foreach (getInvitationTypes() as $t) {
        if ((int)$t['id'] === $id) {
            return $t['name'];
        }
    }
    return 'Unknown';
}

function invitationTypeBadge(?int $id): string
{
    if ($id === null) {
        return '<span class="badge bg-secondary">None</span>';
    }
    foreach (getInvitationTypes() as $t) {
        if ((int)$t['id'] === $id) {
            return '<span class="badge bg-' . e($t['badge_color']) . '">' . e($t['name']) . '</span>';
        }
    }
    return '<span class="badge bg-secondary">Unknown</span>';
}

// ---------------------------------------------------------------------------
// Status badges
// ---------------------------------------------------------------------------

function statusBadge(string $status): string
{
    $map = [
        'active'   => 'success',
        'inactive' => 'secondary',
        'pending'  => 'warning',
        'unknown'  => 'secondary',
        'invited'  => 'info',
        'accepted' => 'success',
        'rejected' => 'danger',
        'yes'      => 'success',
        'no'       => 'secondary',
    ];
    $color = $map[strtolower($status)] ?? 'secondary';
    return '<span class="badge bg-' . $color . '">' . e(ucfirst($status)) . '</span>';
}

// ---------------------------------------------------------------------------
// Avatar helper
// ---------------------------------------------------------------------------

function avatarImg(?string $url, string $name = '?', string $size = '40'): string
{
    $initials = strtoupper(mb_substr(trim($name) ?: '?', 0, 1));
    $fs       = round(intval($size) / 2.2);
    $fallback = '<span class="avatar-fallback rounded-circle d-inline-flex align-items-center justify-content-center fw-semibold text-white"'
        . ' style="width:' . $size . 'px;height:' . $size . 'px;background:#6366f1;font-size:' . $fs . 'px;flex-shrink:0">'
        . $initials . '</span>';

    if ($url) {
        return '<img src="' . e($url) . '" alt="' . e($name) . '" class="rounded-circle"'
            . ' width="' . $size . '" height="' . $size . '" style="object-fit:cover;flex-shrink:0"'
            . ' onerror="this.replaceWith(document.getElementById(\'av-fb-' . md5($url) . '\'))">'
            . '<span id="av-fb-' . md5($url) . '" class="avatar-fallback rounded-circle d-none d-inline-flex align-items-center justify-content-center fw-semibold text-white"'
            . ' style="width:' . $size . 'px;height:' . $size . 'px;background:#6366f1;font-size:' . $fs . 'px;flex-shrink:0">'
            . $initials . '</span>';
    }
    return $fallback;
}

// ---------------------------------------------------------------------------
// Pagination
// ---------------------------------------------------------------------------

function buildPagination(int $total, int $perPage, int $current, string $baseUrl): array
{
    $totalPages  = max(1, (int)ceil($total / $perPage));
    $currentPage = max(1, min($current, $totalPages));
    return [
        'total'       => $total,
        'per_page'    => $perPage,
        'current'     => $currentPage,
        'total_pages' => $totalPages,
        'offset'      => ($currentPage - 1) * $perPage,
        'base_url'    => $baseUrl,
    ];
}

function paginationHtml(array $p): string
{
    if ($p['total_pages'] <= 1) {
        return '';
    }
    $html  = '<nav aria-label="Page navigation"><ul class="pagination pagination-sm mb-0">';
    $prev  = $p['current'] - 1;
    $next  = $p['current'] + 1;
    $sep   = strpos($p['base_url'], '?') !== false ? '&' : '?';

    $html .= '<li class="page-item' . ($p['current'] <= 1 ? ' disabled' : '') . '">'
        . '<a class="page-link" href="' . ($p['current'] > 1 ? $p['base_url'] . $sep . 'page=' . $prev : '#') . '">«</a></li>';

    $start = max(1, $p['current'] - 2);
    $end   = min($p['total_pages'], $p['current'] + 2);
    for ($i = $start; $i <= $end; $i++) {
        $html .= '<li class="page-item' . ($i === $p['current'] ? ' active' : '') . '">'
            . '<a class="page-link" href="' . $p['base_url'] . $sep . 'page=' . $i . '">' . $i . '</a></li>';
    }

    $html .= '<li class="page-item' . ($p['current'] >= $p['total_pages'] ? ' disabled' : '') . '">'
        . '<a class="page-link" href="' . ($p['current'] < $p['total_pages'] ? $p['base_url'] . $sep . 'page=' . $next : '#') . '">»</a></li>';

    $html .= '</ul></nav>';
    return $html;
}

// ---------------------------------------------------------------------------
// Input helpers
// ---------------------------------------------------------------------------

function postStr(string $key, string $default = ''): string
{
    return trim((string)($_POST[$key] ?? $default));
}

function getStr(string $key, string $default = ''): string
{
    return trim((string)($_GET[$key] ?? $default));
}

function getInt(string $key, int $default = 0): int
{
    return (int)($_GET[$key] ?? $default);
}

// ---------------------------------------------------------------------------
// Packages + Daily Lead Assignment
// ---------------------------------------------------------------------------

function ensurePackagesSchema(PDO $db): void
{
    static $done = false;
    if ($done) {
        return;
    }

    $db->exec(
        "CREATE TABLE IF NOT EXISTS packages (
            id                int(11) unsigned NOT NULL AUTO_INCREMENT,
            created_at        datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at        datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            name              varchar(100) NOT NULL,
            description       text DEFAULT NULL,
            leads_per_day     int(11) NOT NULL DEFAULT 0,
            price_per_month   decimal(10,2) NOT NULL DEFAULT 0.00,
            PRIMARY KEY (id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
    );

    $colStmt = $db->prepare(
        'SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
    );
    $colStmt->execute(['users', 'package_id']);
    if ((int)$colStmt->fetchColumn() === 0) {
        $db->exec('ALTER TABLE users ADD COLUMN package_id int(11) unsigned DEFAULT NULL AFTER notes');
    }

    $idxStmt = $db->prepare(
        'SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?'
    );
    $idxStmt->execute(['users', 'idx_users_package_id']);
    if ((int)$idxStmt->fetchColumn() === 0) {
        $db->exec('ALTER TABLE users ADD INDEX idx_users_package_id (package_id)');
    }

    $db->exec(
        "CREATE TABLE IF NOT EXISTS customer_daily_lead_assignments (
            id              int(11) unsigned NOT NULL AUTO_INCREMENT,
            user_id         int(11) unsigned NOT NULL,
            assign_date     date NOT NULL,
            assigned_count  int(11) NOT NULL DEFAULT 0,
            created_at      datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at      datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_user_day (user_id, assign_date),
            KEY idx_assign_date (assign_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
    );

    $done = true;
}

function getPackages(PDO $db): array
{
    ensurePackagesSchema($db);
    return $db->query('SELECT * FROM packages ORDER BY price_per_month ASC, name ASC')->fetchAll();
}

function packageNameById(array $packages, ?int $packageId): string
{
    if (!$packageId) {
        return 'None';
    }

    foreach ($packages as $package) {
        if ((int)$package['id'] === (int)$packageId) {
            return (string)$package['name'];
        }
    }

    return 'Unknown';
}

function assignAvailableLeadsForCustomer(PDO $db, int $customerId): array
{
    ensurePackagesSchema($db);

    $today = date('Y-m-d');
    $userStmt = $db->prepare(
        'SELECT u.id, u.name, u.role, u.status, u.package_id, p.name AS package_name, p.leads_per_day
         FROM users u
         LEFT JOIN packages p ON p.id = u.package_id
         WHERE u.id = ?
         LIMIT 1'
    );
    $userStmt->execute([$customerId]);
    $user = $userStmt->fetch();

    if (!$user || $user['role'] !== 'customer' || $user['status'] !== 'active') {
        return ['assigned' => 0, 'remaining' => 0, 'reason' => 'User is not an active customer.'];
    }

    $dailyLimit = (int)($user['leads_per_day'] ?? 0);
    if ($dailyLimit <= 0) {
        return ['assigned' => 0, 'remaining' => 0, 'reason' => 'No package or leads/day is zero.'];
    }

    $db->beginTransaction();
    try {
        $todayStmt = $db->prepare(
            'SELECT assigned_count FROM customer_daily_lead_assignments WHERE user_id = ? AND assign_date = ? FOR UPDATE'
        );
        $todayStmt->execute([$customerId, $today]);
        $alreadyAssigned = (int)($todayStmt->fetchColumn() ?: 0);

        $remaining = max(0, $dailyLimit - $alreadyAssigned);
        if ($remaining === 0) {
            $db->commit();
            return ['assigned' => 0, 'remaining' => 0, 'reason' => 'Daily limit already reached.'];
        }

        $leadStmt = $db->prepare(
            "SELECT id
             FROM creators
             WHERE assigned_customer IS NULL
               AND backstage_status = 'Available'
             ORDER BY id ASC
             LIMIT ?"
        );
        $leadStmt->bindValue(1, $remaining, PDO::PARAM_INT);
        $leadStmt->execute();
        $leadIds = array_map('intval', array_column($leadStmt->fetchAll(), 'id'));

        if (empty($leadIds)) {
            $db->commit();
            return ['assigned' => 0, 'remaining' => $remaining, 'reason' => 'No available unassigned leads.'];
        }

        $placeholders = implode(',', array_fill(0, count($leadIds), '?'));
        $updateSql = "UPDATE creators SET assigned_customer = ? WHERE id IN ($placeholders)";
        $updateStmt = $db->prepare($updateSql);
        $bind = array_merge([$customerId], $leadIds);
        $updateStmt->execute($bind);
        $assignedNow = (int)$updateStmt->rowCount();

        $logStmt = $db->prepare(
            'INSERT INTO customer_daily_lead_assignments (user_id, assign_date, assigned_count)
             VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE assigned_count = assigned_count + VALUES(assigned_count)'
        );
        $logStmt->execute([$customerId, $today, $assignedNow]);

        $db->commit();

        return [
            'assigned' => $assignedNow,
            'remaining' => max(0, $remaining - $assignedNow),
            'reason' => $assignedNow > 0 ? 'Assigned successfully.' : 'No leads assigned.',
        ];
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        return ['assigned' => 0, 'remaining' => 0, 'reason' => 'Assignment failed: ' . $e->getMessage()];
    }
}

function assignAvailableLeadsForAllCustomers(PDO $db): array
{
    ensurePackagesSchema($db);

    $customers = $db->query(
        "SELECT id, name
         FROM users
         WHERE role = 'customer'
           AND status = 'active'
           AND package_id IS NOT NULL
         ORDER BY id ASC"
    )->fetchAll();

    $results = [];
    $totalAssigned = 0;

    foreach ($customers as $customer) {
        $result = assignAvailableLeadsForCustomer($db, (int)$customer['id']);
        $result['user_id'] = (int)$customer['id'];
        $result['name'] = (string)$customer['name'];
        $totalAssigned += (int)$result['assigned'];
        $results[] = $result;
    }

    return [
        'total_assigned' => $totalAssigned,
        'customer_count' => count($customers),
        'results' => $results,
    ];
}
