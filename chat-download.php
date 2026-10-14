<?php
declare(strict_types=1);

require_once __DIR__ . '/auth/auth.php';
require_once __DIR__ . '/includes/chat-common.php';

$isAdmin = is_admin_logged_in();
$isUser = is_user_logged_in();
if (!$isAdmin && !$isUser) {
    http_response_code(401);
    exit('برای دریافت فایل ابتدا وارد حساب کاربری شوید.');
}

// User IDs are positive; admin/IT IDs are negative, matching chat-api.php.
$myId = $isAdmin ? -(int)($_SESSION['admin_id'] ?? 0) : (int)($_SESSION['user_id'] ?? 0);
$attachmentId = (int)($_GET['id'] ?? 0);
if ($myId === 0 || $attachmentId <= 0) {
    http_response_code(400);
    exit('درخواست دریافت فایل معتبر نیست.');
}

try {
    $db = neal_chat_db();
    neal_chat_cleanup_expired($db);

    if ($isAdmin) {
        $userStmt = $db->prepare('SELECT id, is_active FROM admins WHERE id=? LIMIT 1');
        $userStmt->execute([abs($myId)]);
    } else {
        $userStmt = $db->prepare('SELECT id, is_active FROM users WHERE id=? LIMIT 1');
        $userStmt->execute([$myId]);
    }
    $user = $userStmt->fetch();
    if (!$user || (int)$user['is_active'] !== 1) {
        http_response_code(403);
        exit('حساب کاربری فعال نیست.');
    }

    $stmt = $db->prepare("
        SELECT a.*, m.sender_id, m.recipient_id
        FROM chat_attachments a
        INNER JOIN chat_messages m ON m.id = a.message_id
        WHERE a.id = ?
        LIMIT 1
    ");
    $stmt->execute([$attachmentId]);
    $attachment = $stmt->fetch();

    if (!$attachment) {
        http_response_code(404);
        exit('فایل مورد نظر پیدا نشد.');
    }
    if ((int)$attachment['sender_id'] !== $myId && (int)$attachment['recipient_id'] !== $myId) {
        http_response_code(403);
        exit('شما اجازه دریافت این فایل را ندارید.');
    }
    if ((int)$attachment['expires_at'] <= time() || empty($attachment['stored_name'])) {
        http_response_code(410);
        exit('مهلت ۱۰ روزه دریافت این فایل تمام شده و فایل از سرور حذف شده است.');
    }

    $stored = basename((string)$attachment['stored_name']);
    $path = neal_chat_attachment_dir() . '/' . $stored;
    if ($stored === '' || !is_file($path) || !is_readable($path)) {
        http_response_code(410);
        exit('فایل دیگر روی سرور موجود نیست.');
    }

    $name = neal_chat_clean_filename((string)$attachment['original_name']);
    $mime = (string)$attachment['mime_type'];
    $allowedMime = [
        'image/jpeg', 'image/png', 'image/gif', 'image/webp',
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.ms-powerpoint',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation'
    ];
    if (!in_array($mime, $allowedMime, true)) {
        $mime = 'application/octet-stream';
    }

    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    header('Content-Type: ' . $mime);
    header('Content-Length: ' . (string)filesize($path));
    header('Content-Disposition: attachment; filename="download"; filename*=UTF-8\'\'' . rawurlencode($name));
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: private, no-store, no-cache, must-revalidate');
    header('Pragma: no-cache');
    readfile($path);
    exit;
} catch (Throwable $e) {
    error_log('[Neal chat-download] ' . get_class($e) . ': ' . $e->getMessage());
    http_response_code(500);
    exit('دریافت فایل ناموفق بود.');
}
