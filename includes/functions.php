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
        ['id' => 1, 'name' => 'LIVE Host',   'description' => 'TikTok LIVE host invitation', 'badge_color' => 'danger'],
        ['id' => 2, 'name' => 'Creator',     'description' => 'Standard TikTok creator invitation', 'badge_color' => 'primary'],
        ['id' => 3, 'name' => 'Affiliate',   'description' => 'TikTok affiliate invitation', 'badge_color' => 'success'],
        ['id' => 4, 'name' => 'Shop Seller', 'description' => 'TikTok Shop seller invitation', 'badge_color' => 'warning'],
        ['id' => 5, 'name' => 'Agency',      'description' => 'Agency-managed invitation', 'badge_color' => 'info'],
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
