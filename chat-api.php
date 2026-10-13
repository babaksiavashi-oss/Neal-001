<?php
declare(strict_types=1);

require_once __DIR__ . '/auth/auth.php';
require_once __DIR__ . '/includes/chat-common.php';

$isAdmin = is_admin_logged_in();
$isUser = is_user_logged_in();
if (!$isAdmin && !$isUser) {
    neal_chat_json(['ok' => false, 'error' => 'برای استفاده از پیام‌رسان ابتدا وارد حساب کاربری شوید.'], 401);
}

$contentLength = (int)($_SERVER['CONTENT_LENGTH'] ?? 0);
if (!isset($_POST['action']) && $contentLength > 0) {
    neal_chat_json(['ok' => false, 'error' => 'درخواست فایل از محدودیت post_max_size در تنظیمات PHP بیشتر است؛ محدودیت آپلود سرور باید حداقل ۱۲ مگابایت باشد.'], 413);
}

$token = (string)($_SESSION['neal_chat_csrf'] ?? '');
$postedToken = (string)($_POST['csrf'] ?? '');
if ($token === '' || $postedToken === '' || !hash_equals($token, $postedToken)) {
    neal_chat_json(['ok' => false, 'error' => 'نشست امنیتی منقضی شده است؛ صفحه را تازه‌سازی کنید.'], 403);
}

// Users use positive IDs; administrators/IT experts use negative IDs.
// This keeps IDs unique without changing or losing existing user-to-user messages.
$myId = $isAdmin ? -(int)($_SESSION['admin_id'] ?? 0) : (int)($_SESSION['user_id'] ?? 0);
if ($myId === 0) {
    neal_chat_json(['ok' => false, 'error' => 'حساب کاربری معتبر نیست.'], 401);
}

try {
    $db = neal_chat_db();
    if ($isAdmin) {
        $meStmt = $db->prepare('SELECT id, username, username AS fullname, role, is_active FROM admins WHERE id = ? LIMIT 1');
        $meStmt->execute([abs($myId)]);
    } else {
        $meStmt = $db->prepare('SELECT id, username, fullname, is_active FROM users WHERE id = ? LIMIT 1');
        $meStmt->execute([$myId]);
    }
    $me = $meStmt->fetch();
    if (!$me || (int)$me['is_active'] !== 1) {
        neal_chat_json(['ok' => false, 'error' => 'حساب کاربری غیرفعال است.'], 403);
    }

    $now = time();
    neal_chat_cleanup_expired($db);
    $presence = $db->prepare('INSERT INTO chat_presence_all(actor_id, last_seen) VALUES(?, ?) ON CONFLICT(actor_id) DO UPDATE SET last_seen=excluded.last_seen');
    $presence->execute([$myId, $now]);

    $action = (string)($_POST['action'] ?? '');

    $getPeer = static function (PDO $db, int $peerId, int $myId): array {
        if ($peerId === 0 || $peerId === $myId) {
            neal_chat_json(['ok' => false, 'error' => 'همکار انتخاب‌شده معتبر نیست.'], 400);
        }
        if ($peerId < 0) {
            $stmt = $db->prepare("SELECT id, username, fullname, role, is_active, 'default' AS avatar, '' AS user_status FROM admins WHERE id=? AND is_active=1 LIMIT 1");
            $stmt->execute([abs($peerId)]);
            $peer = $stmt->fetch();
            if ($peer) {
                $peer['id'] = -abs((int)$peer['id']);
                $peer['user_status'] = 'مدیریت / کارشناس فناوری اطلاعات';
            }
        } else {
            $stmt = $db->prepare("SELECT id, username, fullname, is_active, COALESCE(NULLIF(avatar, ''), 'default') AS avatar, COALESCE(user_status, '') AS user_status FROM users WHERE id=? AND is_active=1 LIMIT 1");
            $stmt->execute([$peerId]);
            $peer = $stmt->fetch();
        }
        if (!$peer) {
            neal_chat_json(['ok' => false, 'error' => 'این همکار فعال نیست یا وجود ندارد.'], 404);
        }
        return $peer;
    };

    if ($action === 'contacts') {
        $people = [];
        $usersStmt = $db->prepare("SELECT id, username, COALESCE(NULLIF(TRIM(fullname), ''), username, 'بدون نام') AS fullname, COALESCE(NULLIF(avatar, ''), 'default') AS avatar, COALESCE(user_status, '') AS user_status FROM users WHERE is_active=1");
        $usersStmt->execute();
        foreach ($usersStmt->fetchAll() as $row) {
            $id = (int)$row['id'];
            if ($id === $myId) continue;
            $people[] = [
                'id' => $id,
                'username' => (string)$row['username'],
                'fullname' => (string)$row['fullname'],
                'avatar' => '/neal/' . ltrim(neal_avatar_url((string)$row['avatar']), '/'),
                'user_status' => (string)$row['user_status']
            ];
        }

        $adminsStmt = $db->prepare("SELECT id, username, username AS fullname, COALESCE(role, 'admin') AS role FROM admins WHERE is_active=1");
        $adminsStmt->execute();
        foreach ($adminsStmt->fetchAll() as $row) {
            $id = -(int)$row['id'];
            if ($id === $myId) continue;
            $roleLabel = ['expert' => 'کارشناس فناوری اطلاعات', 'manager' => 'مدیریت', 'admin' => 'مدیر سامانه'];
            $people[] = [
                'id' => $id,
                'username' => (string)$row['username'],
                'fullname' => (string)$row['fullname'],
                'avatar' => '/neal/images/avatars/default.svg',
                'user_status' => $roleLabel[(string)$row['role']] ?? 'مدیریت / کارشناس فناوری اطلاعات'
            ];
        }

        $lastStmt = $db->prepare("SELECT id, body, created_at FROM chat_messages WHERE (sender_id=? AND recipient_id=?) OR (sender_id=? AND recipient_id=?) ORDER BY id DESC LIMIT 1");
        $countStmt = $db->prepare("SELECT COUNT(*) FROM chat_messages WHERE (sender_id=? AND recipient_id=?) OR (sender_id=? AND recipient_id=?)");
        $unreadStmt = $db->prepare("SELECT COUNT(*) FROM chat_messages WHERE sender_id=? AND recipient_id=? AND read_at IS NULL");
        $presenceStmt = $db->prepare('SELECT last_seen FROM chat_presence_all WHERE actor_id=? LIMIT 1');

        foreach ($people as &$person) {
            $peerId = (int)$person['id'];
            $lastStmt->execute([$myId, $peerId, $peerId, $myId]);
            $last = $lastStmt->fetch();
            $countStmt->execute([$myId, $peerId, $peerId, $myId]);
            $messageCount = (int)$countStmt->fetchColumn();
            $unreadStmt->execute([$peerId, $myId]);
            $unreadCount = (int)$unreadStmt->fetchColumn();
            $presenceStmt->execute([$peerId]);
            $lastSeen = (int)($presenceStmt->fetchColumn() ?: 0);
            $person['online'] = $lastSeen >= ($now - 90);
            $person['last_body'] = $last ? ((string)$last['body'] !== '' ? (string)$last['body'] : '📎 فایل پیوست') : '';
            $person['last_id'] = (int)($last['id'] ?? 0);
            $person['last_at'] = (string)($last['created_at'] ?? '');
            $person['message_count'] = $messageCount;
            $person['unread_count'] = $unreadCount;
        }
        unset($person);
        neal_chat_json(['ok' => true, 'contacts' => $people, 'server_time' => $now]);
    }

    if ($action === 'messages') {
        $peerId = (int)($_POST['peer_id'] ?? 0);
        $peer = $getPeer($db, $peerId, $myId);
        $afterId = max(0, (int)($_POST['after_id'] ?? 0));
        $beforeId = max(0, (int)($_POST['before_id'] ?? 0));

        $read = $db->prepare('UPDATE chat_messages SET read_at = COALESCE(read_at, ?) WHERE sender_id = ? AND recipient_id = ? AND read_at IS NULL');
        $read->execute([$now, $peerId, $myId]);

        $baseSelect = "
            SELECT m.id, m.sender_id, m.recipient_id, m.body, m.created_at, m.read_at,
                a.id AS attachment_id, a.original_name, a.mime_type, a.file_size,
                a.expires_at, a.stored_name, a.deleted_at
            FROM chat_messages m
            LEFT JOIN chat_attachments a ON a.message_id = m.id
            WHERE ((m.sender_id = ? AND m.recipient_id = ?) OR (m.sender_id = ? AND m.recipient_id = ?))
        ";
        if ($beforeId > 0) {
            $stmt = $db->prepare($baseSelect . ' AND m.id < ? ORDER BY m.id DESC LIMIT 101');
            $stmt->execute([$myId, $peerId, $peerId, $myId, $beforeId]);
            $rows = $stmt->fetchAll();
            $hasOlder = count($rows) > 100;
            if ($hasOlder) array_pop($rows);
            $rows = array_reverse($rows);
        } elseif ($afterId > 0) {
            $stmt = $db->prepare($baseSelect . ' AND m.id > ? ORDER BY m.id ASC LIMIT 100');
            $stmt->execute([$myId, $peerId, $peerId, $myId, $afterId]);
            $rows = $stmt->fetchAll();
            $hasOlder = false;
        } else {
            $stmt = $db->prepare($baseSelect . ' ORDER BY m.id DESC LIMIT 101');
            $stmt->execute([$myId, $peerId, $peerId, $myId]);
            $rows = $stmt->fetchAll();
            $hasOlder = count($rows) > 100;
            if ($hasOlder) array_pop($rows);
            $rows = array_reverse($rows);
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
                'avatar' => ((int)$peer['id'] < 0) ? '/neal/images/avatars/default.svg' : '/neal/' . ltrim(neal_avatar_url((string)$peer['avatar']), '/'),
                'user_status' => (string)($peer['user_status'] ?? '')
            ],
            'messages' => $messages,
            'has_older' => $hasOlder,
            'server_time' => $now
        ]);
    }

    if ($action === 'send') {
        $peerId = (int)($_POST['peer_id'] ?? 0);
        $getPeer($db, $peerId, $myId);
        $body = trim((string)($_POST['body'] ?? ''));
        if ($body === '') neal_chat_json(['ok' => false, 'error' => 'متن پیام خالی است.'], 400);
        if (neal_chat_text_length($body) > 2000) neal_chat_json(['ok' => false, 'error' => 'حداکثر طول پیام ۲۰۰۰ نویسه است.'], 400);
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
