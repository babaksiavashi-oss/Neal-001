<?php
declare(strict_types=1);

require_once __DIR__ . '/auth/auth.php';
require_once __DIR__ . '/includes/chat-common.php';

if (!is_user_logged_in()) {
    neal_chat_json(['ok' => false, 'error' => 'برای استفاده از پیام‌رسان ابتدا وارد حساب کاربری شوید.'], 401);
}

$token = (string)($_SESSION['neal_chat_csrf'] ?? '');
$postedToken = (string)($_POST['csrf'] ?? '');
if ($token === '' || $postedToken === '' || !hash_equals($token, $postedToken)) {
    neal_chat_json(['ok' => false, 'error' => 'نشست امنیتی منقضی شده است؛ صفحه را تازه‌سازی کنید.'], 403);
}

$myId = (int)($_SESSION['user_id'] ?? 0);
if ($myId <= 0) {
    neal_chat_json(['ok' => false, 'error' => 'حساب کاربری معتبر نیست.'], 401);
}

try {
    $db = neal_chat_db();
    $meStmt = $db->prepare('SELECT id, username, fullname, is_active FROM users WHERE id = ? LIMIT 1');
    $meStmt->execute([$myId]);
    $me = $meStmt->fetch();
    if (!$me || (int)$me['is_active'] !== 1) {
        neal_chat_json(['ok' => false, 'error' => 'حساب کاربری غیرفعال است.'], 403);
    }

    $now = time();
    neal_chat_cleanup_expired($db);
    $presence = $db->prepare('INSERT INTO chat_presence(user_id, last_seen) VALUES(?, ?) ON CONFLICT(user_id) DO UPDATE SET last_seen=excluded.last_seen');
    $presence->execute([$myId, $now]);

    $action = (string)($_POST['action'] ?? '');

    $getPeer = static function (PDO $db, int $peerId, int $myId): array {
        if ($peerId <= 0 || $peerId === $myId) {
            neal_chat_json(['ok' => false, 'error' => 'همکار انتخاب‌شده معتبر نیست.'], 400);
        }
        $stmt = $db->prepare('SELECT id, username, fullname, is_active, COALESCE(NULLIF(avatar, \'\'), \'default\') AS avatar, COALESCE(user_status, \'\') AS user_status FROM users WHERE id=? AND is_active=1 LIMIT 1');
        $stmt->execute([$peerId]);
        $peer = $stmt->fetch();
        if (!$peer) {
            neal_chat_json(['ok' => false, 'error' => 'این همکار فعال نیست یا وجود ندارد.'], 404);
        }
        return $peer;
    };

    if ($action === 'contacts') {
        $stmt = $db->prepare("
            SELECT
                u.id,
                u.username,
                COALESCE(NULLIF(TRIM(u.fullname), ''), u.username, 'بدون نام') AS fullname,
                COALESCE(NULLIF(u.avatar, ''), 'default') AS avatar,
                COALESCE(u.user_status, '') AS user_status,
                (SELECT p.last_seen FROM chat_presence p WHERE p.user_id = u.id LIMIT 1) AS last_seen,
                (SELECT m.body FROM chat_messages m
                    WHERE (m.sender_id = :me1 AND m.recipient_id = u.id)
                       OR (m.sender_id = u.id AND m.recipient_id = :me2)
                    ORDER BY m.id DESC LIMIT 1) AS last_body,
                (SELECT m.id FROM chat_messages m
                    WHERE (m.sender_id = :me3 AND m.recipient_id = u.id)
                       OR (m.sender_id = u.id AND m.recipient_id = :me4)
                    ORDER BY m.id DESC LIMIT 1) AS last_id,
                (SELECT m.created_at FROM chat_messages m
                    WHERE (m.sender_id = :me5 AND m.recipient_id = u.id)
                       OR (m.sender_id = u.id AND m.recipient_id = :me6)
                    ORDER BY m.id DESC LIMIT 1) AS last_at,
                (SELECT COUNT(*) FROM chat_messages m
                    WHERE m.sender_id = u.id AND m.recipient_id = :me7 AND m.read_at IS NULL) AS unread_count
            FROM users u
            WHERE u.is_active = 1 AND u.id <> :me8
            ORDER BY fullname COLLATE NOCASE ASC, u.id ASC
        ");
        $stmt->execute([
            ':me1' => $myId, ':me2' => $myId, ':me3' => $myId, ':me4' => $myId,
            ':me5' => $myId, ':me6' => $myId, ':me7' => $myId, ':me8' => $myId
        ]);
        $contacts = [];
        foreach ($stmt->fetchAll() as $row) {
            $avatar = neal_avatar_url((string)$row['avatar']);
            $lastSeen = (int)($row['last_seen'] ?? 0);
            $contacts[] = [
                'id' => (int)$row['id'],
                'username' => (string)$row['username'],
                'fullname' => (string)$row['fullname'],
                'avatar' => '/neal/' . ltrim($avatar, '/'),
                'user_status' => (string)$row['user_status'],
                'online' => $lastSeen >= ($now - 90),
                'last_body' => ((string)($row['last_body'] ?? '') !== '' ? (string)$row['last_body'] : (!empty($row['last_id']) ? '📎 فایل پیوست' : '')),
                'last_id' => (int)($row['last_id'] ?? 0),
                'last_at' => (string)($row['last_at'] ?? ''),
                'unread_count' => (int)$row['unread_count']
            ];
        }
        neal_chat_json(['ok' => true, 'contacts' => $contacts, 'server_time' => $now]);
    }

    if ($action === 'messages') {
        $peerId = (int)($_POST['peer_id'] ?? 0);
        $peer = $getPeer($db, $peerId, $myId);
        $afterId = max(0, (int)($_POST['after_id'] ?? 0));

        $read = $db->prepare('UPDATE chat_messages SET read_at = COALESCE(read_at, ?) WHERE sender_id = ? AND recipient_id = ? AND read_at IS NULL');
        $read->execute([$now, $peerId, $myId]);

        $baseSelect = "
            SELECT
                m.id, m.sender_id, m.recipient_id, m.body, m.created_at, m.read_at,
                a.id AS attachment_id, a.original_name, a.mime_type, a.file_size,
                a.expires_at, a.stored_name, a.deleted_at
            FROM chat_messages m
            LEFT JOIN chat_attachments a ON a.message_id = m.id
            WHERE ((m.sender_id = ? AND m.recipient_id = ?) OR (m.sender_id = ? AND m.recipient_id = ?))
        ";
        if ($afterId > 0) {
            $stmt = $db->prepare($baseSelect . ' AND m.id > ? ORDER BY m.id ASC LIMIT 100');
            $stmt->execute([$myId, $peerId, $peerId, $myId, $afterId]);
            $rows = $stmt->fetchAll();
        } else {
            $stmt = $db->prepare($baseSelect . ' ORDER BY m.id DESC LIMIT 100');
            $stmt->execute([$myId, $peerId, $peerId, $myId]);
            $rows = array_reverse($stmt->fetchAll());
        }

        $messages = [];
        foreach ($rows as $row) {
            $attachment = null;
            if ($row['attachment_id'] !== null) {
                $expiresAt = (int)$row['expires_at'];
                $available = !empty($row['stored_name']) && $expiresAt > $now;
                $attachment = [
                    'id' => (int)$row['attachment_id'],
                    'name' => (string)$row['original_name'],
                    'mime_type' => (string)$row['mime_type'],
                    'size' => (int)$row['file_size'],
                    'expires_at' => $expiresAt,
                    'available' => $available,
                    'expired' => !$available
                ];
            }
            $messages[] = [
                'id' => (int)$row['id'],
                'sender_id' => (int)$row['sender_id'],
                'body' => (string)$row['body'],
                'created_at' => (string)$row['created_at'],
                'read' => $row['read_at'] !== null,
                'attachment' => $attachment
            ];
        }
        neal_chat_json([
            'ok' => true,
            'peer' => [
                'id' => (int)$peer['id'],
                'username' => (string)$peer['username'],
                'fullname' => (string)($peer['fullname'] ?: $peer['username']),
                'avatar' => '/neal/' . ltrim(neal_avatar_url((string)$peer['avatar']), '/'),
                'user_status' => (string)$peer['user_status']
            ],
            'messages' => $messages,
            'server_time' => $now
        ]);
    }

    if ($action === 'send') {
        $peerId = (int)($_POST['peer_id'] ?? 0);
        $getPeer($db, $peerId, $myId);
        $body = trim((string)($_POST['body'] ?? ''));
        if ($body === '') {
            neal_chat_json(['ok' => false, 'error' => 'متن پیام خالی است.'], 400);
        }
        if (neal_chat_text_length($body) > 2000) {
            neal_chat_json(['ok' => false, 'error' => 'حداکثر طول پیام ۲۰۰۰ نویسه است.'], 400);
        }
        $stmt = $db->prepare('INSERT INTO chat_messages(sender_id, recipient_id, body) VALUES(?, ?, ?)');
        $stmt->execute([$myId, $peerId, $body]);
        neal_chat_json(['ok' => true, 'message_id' => (int)$db->lastInsertId()]);
    }

    if ($action === 'upload') {
        $peerId = (int)($_POST['peer_id'] ?? 0);
        $getPeer($db, $peerId, $myId);
        if (!isset($_FILES['attachment']) || !is_array($_FILES['attachment'])) {
            neal_chat_json(['ok' => false, 'error' => 'ابتدا یک فایل انتخاب کنید.'], 400);
        }
        try {
            $file = neal_chat_validate_upload($_FILES['attachment']);
        } catch (RuntimeException $e) {
            neal_chat_json(['ok' => false, 'error' => $e->getMessage()], 400);
        }

        $dir = neal_chat_attachment_dir();
        if (!is_dir($dir) && !@mkdir($dir, 0700, true) && !is_dir($dir)) {
            neal_chat_json(['ok' => false, 'error' => 'پوشه امن ذخیره فایل روی سرور ایجاد نشد.'], 500);
        }
        @chmod($dir, 0700);
        $storedName = bin2hex(random_bytes(24)) . '.' . $file['extension'];
        $target = $dir . '/' . $storedName;
        if (!move_uploaded_file($file['tmp_name'], $target)) {
            neal_chat_json(['ok' => false, 'error' => 'ذخیره فایل روی سرور ناموفق بود.'], 500);
        }
        @chmod($target, 0600);

        $expiresAt = $now + (10 * 24 * 60 * 60);
        try {
            $db->beginTransaction();
            $messageStmt = $db->prepare('INSERT INTO chat_messages(sender_id, recipient_id, body) VALUES(?, ?, ?)');
            $messageStmt->execute([$myId, $peerId, '']);
            $messageId = (int)$db->lastInsertId();
            $attachmentStmt = $db->prepare('INSERT INTO chat_attachments(message_id, original_name, stored_name, mime_type, file_size, expires_at) VALUES(?, ?, ?, ?, ?, ?)');
            $attachmentStmt->execute([$messageId, $file['original_name'], $storedName, $file['mime_type'], $file['file_size'], $expiresAt]);
            $attachmentId = (int)$db->lastInsertId();
            $db->commit();
        } catch (Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            @unlink($target);
            throw $e;
        }

        neal_chat_json([
            'ok' => true,
            'message_id' => $messageId,
            'attachment_id' => $attachmentId,
            'expires_at' => $expiresAt,
            'notice' => 'فایل شما ارسال شد. این فایل تا ۱۰ روز روی سرور نگهداری می‌شود و سپس به‌صورت خودکار حذف خواهد شد؛ دریافت‌کننده باید آن را در سیستم خود ذخیره کند.'
        ]);
    }

    neal_chat_json(['ok' => false, 'error' => 'درخواست نامعتبر است.'], 400);
} catch (Throwable $e) {
    error_log('[Neal chat-api] ' . get_class($e) . ': ' . $e->getMessage());
    neal_chat_json(['ok' => false, 'error' => 'انجام عملیات پیام‌رسان ناموفق بود. لطفاً دوباره تلاش کنید.'], 500);
}
