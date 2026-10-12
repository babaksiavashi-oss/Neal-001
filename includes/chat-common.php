<?php
declare(strict_types=1);

require_once __DIR__ . '/user-profile.php';

function neal_chat_db(): PDO {
    $db = new PDO('sqlite:' . __DIR__ . '/../data/neal.db');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $db->exec('PRAGMA foreign_keys = ON');
    neal_chat_ensure_schema($db);
    return $db;
}

function neal_chat_ensure_schema(PDO $db): void {
    $db->exec("
        CREATE TABLE IF NOT EXISTS chat_messages (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            sender_id INTEGER NOT NULL,
            recipient_id INTEGER NOT NULL,
            body TEXT NOT NULL DEFAULT '',
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            read_at INTEGER NULL,
            CHECK (sender_id <> recipient_id),
            CHECK (length(body) <= 2000)
        )
    ");
    $db->exec('CREATE INDEX IF NOT EXISTS idx_chat_messages_pair ON chat_messages(sender_id, recipient_id, id)');
    $db->exec('CREATE INDEX IF NOT EXISTS idx_chat_messages_unread ON chat_messages(recipient_id, sender_id, read_at)');
    $db->exec("
        CREATE TABLE IF NOT EXISTS chat_attachments (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            message_id INTEGER NOT NULL UNIQUE,
            original_name TEXT NOT NULL,
            stored_name TEXT NULL,
            mime_type TEXT NOT NULL,
            file_size INTEGER NOT NULL DEFAULT 0,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            expires_at INTEGER NOT NULL,
            deleted_at INTEGER NULL,
            FOREIGN KEY (message_id) REFERENCES chat_messages(id) ON DELETE CASCADE
        )
    ");
    $db->exec('CREATE INDEX IF NOT EXISTS idx_chat_attachments_expiry ON chat_attachments(expires_at, stored_name)');
    $db->exec("
        CREATE TABLE IF NOT EXISTS chat_presence (
            user_id INTEGER PRIMARY KEY,
            last_seen INTEGER NOT NULL,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )
    ");
}

function neal_chat_attachment_dir(): string {
    // Sibling of the public /neal webroot, not directly web-accessible.
    return dirname(dirname(__DIR__)) . '/neal-chat-attachments';
}

function neal_chat_cleanup_expired(PDO $db): int {
    $now = time();
    $stmt = $db->prepare('SELECT id, stored_name FROM chat_attachments WHERE expires_at <= ? AND stored_name IS NOT NULL');
    $stmt->execute([$now]);
    $expired = $stmt->fetchAll();
    $dir = neal_chat_attachment_dir();
    $cleaned = 0;

    foreach ($expired as $item) {
        $stored = basename((string)($item['stored_name'] ?? ''));
        if ($stored !== '' && is_file($dir . '/' . $stored)) {
            @unlink($dir . '/' . $stored);
        }
        $update = $db->prepare('UPDATE chat_attachments SET stored_name = NULL, deleted_at = ? WHERE id = ? AND expires_at <= ?');
        $update->execute([$now, (int)$item['id'], $now]);
        $cleaned += $update->rowCount();
    }

    return $cleaned;
}

function neal_chat_json(array $data, int $status = 200): never {
    http_response_code($status);
    header('Content-Type: application/json; charset=UTF-8');
    header('Cache-Control: no-store, no-cache, must-revalidate');
    header('X-Content-Type-Options: nosniff');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function neal_chat_text_length(string $value): int {
    if (function_exists('mb_strlen')) {
        return mb_strlen($value, 'UTF-8');
    }
    preg_match_all('/./us', $value, $matches);
    return count($matches[0] ?? []);
}

function neal_chat_clean_filename(string $name): string {
    $name = str_replace("\\", '/', $name);
    $name = basename($name);
    $name = str_replace(["\r", "\n", "\0"], '', $name);
    $name = trim($name);
    if ($name === '' || $name === '.' || $name === '..') {
        return 'attachment';
    }
    if (function_exists('mb_substr')) {
        return mb_substr($name, 0, 180, 'UTF-8');
    }
    return substr($name, 0, 180);
}

function neal_chat_validate_upload(array $file): array {
    $maxSize = 10 * 1024 * 1024;
    $error = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($error !== UPLOAD_ERR_OK) {
        $messages = [
            UPLOAD_ERR_INI_SIZE => 'حجم فایل از محدودیت تنظیم‌شده PHP بیشتر است.',
            UPLOAD_ERR_FORM_SIZE => 'حجم فایل بیشتر از حد مجاز است.',
            UPLOAD_ERR_PARTIAL => 'ارسال فایل کامل نشد؛ دوباره تلاش کنید.',
            UPLOAD_ERR_NO_FILE => 'فایلی انتخاب نشده است.',
            UPLOAD_ERR_NO_TMP_DIR => 'پوشه موقت آپلود روی سرور در دسترس نیست.',
            UPLOAD_ERR_CANT_WRITE => 'ذخیره فایل موقت روی سرور ناموفق بود.',
            UPLOAD_ERR_EXTENSION => 'آپلود فایل توسط تنظیمات PHP متوقف شد.'
        ];
        throw new RuntimeException($messages[$error] ?? 'ارسال فایل ناموفق بود.');
    }

    $size = (int)($file['size'] ?? 0);
    if ($size < 1 || $size > $maxSize) {
        throw new RuntimeException('حجم فایل باید بیشتر از صفر و حداکثر ۱۰ مگابایت باشد.');
    }
    $tmp = (string)($file['tmp_name'] ?? '');
    if ($tmp === '' || !is_uploaded_file($tmp)) {
        throw new RuntimeException('فایل آپلودشده معتبر نیست.');
    }

    $original = neal_chat_clean_filename((string)($file['name'] ?? 'attachment'));
    $extension = strtolower(pathinfo($original, PATHINFO_EXTENSION));
    $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf', 'xls', 'xlsx', 'csv'];
    if (!in_array($extension, $allowed, true)) {
        throw new RuntimeException('فقط تصاویر JPG/PNG/GIF/WEBP، فایل PDF و فایل‌های Excel با پسوند XLS/XLSX/CSV مجاز هستند.');
    }

    if (!class_exists('finfo')) {
        throw new RuntimeException('ماژول PHP Fileinfo فعال نیست؛ امکان بررسی امن نوع فایل وجود ندارد.');
    }
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = (string)$finfo->file($tmp);
    $sizeActual = filesize($tmp);
    if ($sizeActual === false || (int)$sizeActual !== $size) {
        throw new RuntimeException('خواندن اندازه واقعی فایل ناموفق بود.');
    }

    $imageMimes = [
        'jpg' => ['image/jpeg', IMAGETYPE_JPEG],
        'jpeg' => ['image/jpeg', IMAGETYPE_JPEG],
        'png' => ['image/png', IMAGETYPE_PNG],
        'gif' => ['image/gif', IMAGETYPE_GIF],
        'webp' => ['image/webp', IMAGETYPE_WEBP]
    ];
    if (isset($imageMimes[$extension])) {
        $info = @getimagesize($tmp);
        if (!$info || (int)($info[2] ?? 0) !== $imageMimes[$extension][1] || $mime !== $imageMimes[$extension][0]) {
            throw new RuntimeException('محتوای فایل با فرمت تصویر انتخاب‌شده مطابقت ندارد.');
        }
        $storedMime = $imageMimes[$extension][0];
    } elseif ($extension === 'pdf') {
        $handle = @fopen($tmp, 'rb');
        $signature = $handle ? fread($handle, 5) : '';
        if ($handle) fclose($handle);
        if ($signature !== '%PDF-' || $mime !== 'application/pdf') {
            throw new RuntimeException('محتوای فایل PDF معتبر نیست.');
        }
        $storedMime = 'application/pdf';
    } elseif ($extension === 'xls') {
        $handle = @fopen($tmp, 'rb');
        $signature = $handle ? fread($handle, 8) : '';
        if ($handle) fclose($handle);
        $validMime = in_array($mime, ['application/vnd.ms-excel', 'application/x-ole-storage', 'application/CDFV2', 'application/octet-stream'], true);
        if ($signature !== hex2bin('D0CF11E0A1B11AE1') || !$validMime) {
            throw new RuntimeException('محتوای فایل Excel قدیمی (XLS) معتبر نیست.');
        }
        $storedMime = 'application/vnd.ms-excel';
    } elseif ($extension === 'xlsx') {
        $handle = @fopen($tmp, 'rb');
        $signature = $handle ? fread($handle, 4) : '';
        if ($handle) fclose($handle);
        $validMime = in_array($mime, ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip', 'application/octet-stream'], true);
        if ($signature !== "PK\x03\x04" || !$validMime) {
            throw new RuntimeException('محتوای فایل Excel (XLSX) معتبر نیست.');
        }
        if (class_exists('ZipArchive')) {
            $zip = new ZipArchive();
            if ($zip->open($tmp) !== true) {
                throw new RuntimeException('ساختار فایل XLSX معتبر نیست.');
            }
            $hasWorkbook = $zip->locateName('xl/workbook.xml') !== false;
            $hasTypes = $zip->locateName('[Content_Types].xml') !== false;
            $hasMacros = $zip->locateName('xl/vbaProject.bin') !== false;
            $zip->close();
            if (!$hasWorkbook || !$hasTypes || $hasMacros) {
                throw new RuntimeException('فایل باید یک workbook استاندارد XLSX و بدون ماکرو باشد.');
            }
        }
        $storedMime = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
    } else {
        if (!in_array($mime, ['text/plain', 'text/csv', 'application/csv', 'application/vnd.ms-excel', 'application/octet-stream'], true)) {
            throw new RuntimeException('نوع واقعی فایل CSV معتبر نیست.');
        }
        $contents = file_get_contents($tmp);
        if ($contents === false || strpos($contents, "\0") !== false) {
            throw new RuntimeException('فایل CSV معتبر نیست یا شامل داده باینری است.');
        }
        $storedMime = 'text/csv; charset=UTF-8';
    }

    return [
        'tmp_name' => $tmp,
        'original_name' => $original,
        'extension' => $extension,
        'mime_type' => $storedMime,
        'file_size' => $size
    ];
}
