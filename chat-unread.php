<?php
declare(strict_types=1);

require_once __DIR__ . '/auth/auth.php';
require_once __DIR__ . '/includes/chat-common.php';

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('X-Content-Type-Options: nosniff');

$isAdmin = is_admin_logged_in();
$isUser = is_user_logged_in();
if (!$isAdmin && !$isUser) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'ورود لازم است.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$actorId = $isAdmin
    ? -(int)($_SESSION['admin_id'] ?? 0)
    : (int)($_SESSION['user_id'] ?? 0);

if ($actorId === 0) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'حساب کاربری معتبر نیست.'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $db = neal_chat_db();
    if ($isAdmin) {
        $check = $db->prepare('SELECT id FROM admins WHERE id = ? AND is_active = 1 LIMIT 1');
        $check->execute([abs($actorId)]);
    } else {
        $check = $db->prepare('SELECT id FROM users WHERE id = ? AND is_active = 1 LIMIT 1');
        $check->execute([$actorId]);
    }
    if (!$check->fetchColumn()) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'حساب کاربری فعال نیست.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $stmt = $db->prepare('SELECT COUNT(*) FROM chat_messages WHERE recipient_id = ? AND read_at IS NULL');
    $stmt->execute([$actorId]);
    echo json_encode([
        'ok' => true,
        'unread_count' => (int)$stmt->fetchColumn()
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'شمارش پیام‌های خوانده‌نشده ناموفق بود.'], JSON_UNESCAPED_UNICODE);
}
