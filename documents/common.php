<?php
declare(strict_types=1);
require_once __DIR__ . '/../auth/auth.php';

function neal_docs_db(): PDO
{
    $path = dirname(__DIR__) . '/data/neal.db';
    if (!is_file($path) || !is_readable($path)) {
        throw new RuntimeException('پایگاه داده پرتال در دسترس نیست.');
    }
    $db = new PDO('sqlite:' . $path, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    $db->exec('PRAGMA foreign_keys = ON');
    return $db;
}

function neal_docs_config(): array
{
    $path = '/usr/local/etc/neal-documents.php';
    if (!is_file($path) || !is_readable($path)) {
        throw new RuntimeException('تنظیمات امن سرویس اسناد نصب نشده است.');
    }
    $config = require $path;
    if (!is_array($config) || empty($config['storage_url']) || empty($config['storage_token'])) {
        throw new RuntimeException('تنظیمات سرویس اسناد ناقص است.');
    }
    if (!function_exists('curl_init')) {
        throw new RuntimeException('افزونه cURL در PHP فعال نیست.');
    }
    return $config;
}

function neal_docs_actor(): array
{
    $adminId = (int)($_SESSION['admin_id'] ?? 0);
    $userId = (int)($_SESSION['user_id'] ?? 0);
    if (($adminId > 0) === ($userId > 0)) {
        http_response_code(403);
        exit('دسترسی غیرمجاز است.');
    }
    return $adminId > 0 ? ['type' => 'admin', 'id' => $adminId] : ['type' => 'user', 'id' => $userId];
}

function neal_docs_require_admin(): array
{
    $actor = neal_docs_actor();
    if ($actor['type'] !== 'admin') {
        http_response_code(403);
        exit('برای این عملیات دسترسی مدیر لازم است.');
    }
    return $actor;
}

function neal_docs_csrf_token(): string
{
    if (empty($_SESSION['neal_docs_csrf'])) $_SESSION['neal_docs_csrf'] = bin2hex(random_bytes(32));
    return (string)$_SESSION['neal_docs_csrf'];
}

function neal_docs_csrf_check(): void
{
    $sent = (string)($_POST['_csrf'] ?? '');
    $known = (string)($_SESSION['neal_docs_csrf'] ?? '');
    if ($sent === '' || $known === '' || !hash_equals($known, $sent)) {
        http_response_code(403);
        exit('درخواست معتبر نیست؛ صفحه را تازه‌سازی کنید.');
    }
}

function neal_docs_audit(PDO $db, array $actor, string $action, string $targetType, ?int $targetId, string $outcome, string $details = ''): void
{
    $stmt = $db->prepare('INSERT INTO document_audit_log (admin_id,user_id,action,target_type,target_id,outcome,details) VALUES (:admin_id,:user_id,:action,:target_type,:target_id,:outcome,:details)');
    $stmt->execute([
        ':admin_id' => $actor['type'] === 'admin' ? $actor['id'] : null,
        ':user_id' => $actor['type'] === 'user' ? $actor['id'] : null,
        ':action' => substr($action, 0, 80), ':target_type' => $targetType,
        ':target_id' => $targetId, ':outcome' => $outcome, ':details' => substr($details, 0, 500),
    ]);
}

/* Deny by default; a direct file ACL overrides its folder ACL for that permission. */
function neal_docs_can(PDO $db, array $actor, int $folderId, ?int $fileId, string $permission): bool
{
    $columns = ['can_view','can_download','can_upload','can_edit','can_delete'];
    if (!in_array($permission, $columns, true)) return false;
    if ($actor['type'] === 'admin') {
        $stmt = $db->prepare('SELECT 1 FROM admins WHERE id=? AND is_active=1');
        $stmt->execute([$actor['id']]);
        return (bool)$stmt->fetchColumn();
    }
    $stmt = $db->prepare('SELECT 1 FROM users WHERE id=? AND is_active=1');
    $stmt->execute([$actor['id']]);
    if (!$stmt->fetchColumn()) return false;
    if ($fileId !== null) {
        $stmt = $db->prepare("SELECT {$permission} FROM document_permissions WHERE file_id=? AND user_id=? LIMIT 1");
        $stmt->execute([$fileId, $actor['id']]);
        $direct = $stmt->fetchColumn();
        if ($direct !== false) return (int)$direct === 1;
    }
    $stmt = $db->prepare("SELECT {$permission} FROM document_permissions WHERE folder_id=? AND user_id=? LIMIT 1");
    $stmt->execute([$folderId, $actor['id']]);
    return (int)$stmt->fetchColumn() === 1;
}

function neal_docs_storage_request(string $method, string $path, ?string $body = null, array $extraHeaders = []): array
{
    $config = neal_docs_config();
    $base = rtrim((string)$config['storage_url'], '/');
    if (!preg_match('#^/(health|files(?:/[a-f0-9]{32,64}\.[A-Za-z0-9]+)?)$#', $path)) {
        throw new RuntimeException('مسیر سرویس ذخیره‌سازی نامعتبر است.');
    }
    $ch = curl_init($base . $path);
    $headers = array_merge(['Authorization: Bearer ' . $config['storage_token'], 'Expect:'], $extraHeaders);
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 180,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
    ]);
    if ($body !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    $result = curl_exec($ch);
    $error = curl_error($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $contentType = (string)curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    curl_close($ch);
    if ($result === false) throw new RuntimeException('ارتباط با سرویس ذخیره‌سازی برقرار نشد: ' . $error);
    return ['status' => $status, 'body' => $result, 'content_type' => $contentType];
}

function neal_docs_h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
