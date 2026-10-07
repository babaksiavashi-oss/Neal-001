<?php

/*
 * =========================================================
 * NEAL Portal - Notification Center
 * =========================================================
 */

function neal_notifications_db()
{
    $db = new PDO(
        "sqlite:" . __DIR__ . "/../data/neal.db",
        null,
        null,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );

    $db->exec("
        CREATE TABLE IF NOT EXISTS notifications (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT NOT NULL,
            type TEXT NOT NULL DEFAULT 'system',
            title TEXT NOT NULL,
            body TEXT NOT NULL DEFAULT '',
            link TEXT NOT NULL DEFAULT '',
            source_key TEXT NOT NULL,
            is_read INTEGER NOT NULL DEFAULT 0,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            read_at TEXT NULL
        )
    ");

    $db->exec("
        CREATE UNIQUE INDEX IF NOT EXISTS idx_notifications_source
        ON notifications(username, source_key)
    ");

    $db->exec("
        CREATE INDEX IF NOT EXISTS idx_notifications_user_read
        ON notifications(username, is_read, created_at)
    ");

    return $db;
}

function neal_notification_add($db, $username, $type, $title, $body, $link, $source_key, $created_at = null)
{
    if ($username === '' || $source_key === '') {
        return;
    }

    $stmt = $db->prepare("
        INSERT OR IGNORE INTO notifications
        (username, type, title, body, link, source_key, created_at)
        VALUES (?, ?, ?, ?, ?, ?, COALESCE(?, CURRENT_TIMESTAMP))
    ");

    $stmt->execute([
        $username,
        $type,
        $title,
        $body,
        $link,
        $source_key,
        $created_at
    ]);
}

function neal_sync_notifications($db, $username)
{
    if ($username === '') {
        return;
    }

    $now = date('Y-m-d H:i:s');

    /*
     * اطلاعیه‌های منتشرشده شرکت
     */
    try {
        $stmt = $db->prepare("
            SELECT id, title, body, published_at, created_at
            FROM announcements
            WHERE is_active = 1
              AND published_at <= ?
              AND (expires_at IS NULL OR expires_at = '' OR expires_at > ?)
            ORDER BY published_at DESC
            LIMIT 50
        ");
        $stmt->execute([$now, $now]);
        $rows = $stmt->fetchAll();
    } catch (Throwable $e) {
        $rows = false;
    }

    if ($rows) {
        foreach ($rows as $row) {
            neal_notification_add(
                $db,
                $username,
                'announcement',
                'اطلاعیه جدید شرکت',
                $row['title'],
                'announcements-list.php',
                'announcement:' . (int)$row['id'] . ':' . ($row['published_at'] ?? $row['created_at']),
                $row['published_at'] ?: $row['created_at']
            );
        }
    }

    /*
     * نتیجه بررسی پیام عمومی کاربر
     */
    try {
        $stmt = $db->prepare("
            SELECT login_message, login_message_status,
                   login_message_rejection_reason,
                   login_message_reviewed_at
            FROM users
            WHERE username = ?
            LIMIT 1
        ");
        $stmt->execute([$username]);
        $profile = $stmt->fetch();
    } catch (Throwable $e) {
        $profile = false;
    }

    if ($profile && !empty($profile['login_message_reviewed_at'])) {
        if ($profile['login_message_status'] === 'approved') {
            neal_notification_add(
                $db,
                $username,
                'message',
                'پیام شما تأیید شد',
                'پیام شما برای نمایش در صفحه ورود تأیید شده است.',
                'profile.php',
                'login-message:approved:' . $profile['login_message_reviewed_at'],
                $profile['login_message_reviewed_at']
            );
        } elseif ($profile['login_message_status'] === 'rejected') {
            $reason = trim((string)($profile['login_message_rejection_reason'] ?? ''));
            $body = 'پیام شما برای نمایش در صفحه ورود تأیید نشد.';
            if ($reason !== '') {
                $body .= ' دلیل: ' . $reason;
            }

            neal_notification_add(
                $db,
                $username,
                'message',
                'پیام شما رد شد',
                $body,
                'profile.php',
                'login-message:rejected:' . $profile['login_message_reviewed_at'],
                $profile['login_message_reviewed_at']
            );
        }
    }

    /*
     * تغییر وضعیت درخواست‌های خدمات
     * فقط تغییرات ۳۰ روز اخیر وارد مرکز اعلان می‌شوند.
     */
    try {
        $stmt = $db->prepare("
            SELECT id, tracking_number, subject, status, updated_at
            FROM service_requests
            WHERE requester_username = ?
              AND updated_at >= ?
            ORDER BY updated_at DESC
            LIMIT 100
        ");
        $stmt->execute([
            $username,
            date('Y-m-d H:i:s', time() - (30 * 86400))
        ]);
        $tickets = $stmt->fetchAll();
    } catch (Throwable $e) {
        $tickets = [];
    }

    $status_labels = [
        'new'          => 'جدید',
        'assigned'     => 'ارجاع شده',
        'in_progress'  => 'در حال بررسی',
        'waiting_user' => 'منتظر پاسخ شما',
        'resolved'     => 'حل شده',
        'closed'       => 'بسته شده',
        'cancelled'    => 'لغو شده'
    ];

    foreach ($tickets as $ticket) {
        $status = $status_labels[$ticket['status']] ?? $ticket['status'];

        neal_notification_add(
            $db,
            $username,
            'ticket',
            'به‌روزرسانی درخواست خدمات',
            'درخواست «' . ($ticket['subject'] ?: 'بدون عنوان') . '» اکنون در وضعیت «' . $status . '» قرار دارد.',
            'my-tickets.php',
            'ticket:' . (int)$ticket['id'] . ':' . ($ticket['status'] ?? '') . ':' . ($ticket['updated_at'] ?? ''),
            $ticket['updated_at']
        );
    }

    /*
     * مناسبت‌ها و رویدادهای نزدیک
     */
    try {
        $stmt = $db->prepare("
            SELECT id, title, body, event_date
            FROM occasions
            WHERE is_active = 1
              AND event_date IS NOT NULL
              AND event_date <> ''
              AND event_date >= ?
              AND event_date <= ?
            ORDER BY event_date ASC
            LIMIT 50
        ");
        $stmt->execute([
            $now,
            date('Y-m-d H:i:s', time() + (30 * 86400))
        ]);
        $occasions = $stmt->fetchAll();
    } catch (Throwable $e) {
        $occasions = [];
    }

    foreach ($occasions as $occasion) {
        neal_notification_add(
            $db,
            $username,
            'occasion',
            'مناسبت پیش رو',
            $occasion['title'],
            'announcements-list.php',
            'occasion:' . (int)$occasion['id'] . ':' . $occasion['event_date'],
            $occasion['event_date']
        );
    }
}

function neal_notification_unread_count($db, $username)
{
    if ($username === '') {
        return 0;
    }

    $stmt = $db->prepare("
        SELECT COUNT(*)
        FROM notifications
        WHERE username = ?
          AND is_read = 0
    ");
    $stmt->execute([$username]);

    return (int)$stmt->fetchColumn();
}
