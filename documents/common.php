<?php
declare(strict_types=1);

/* Shared primitives for the staged NEAL documents module.
 * Not connected to the live navigation yet.
 */
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

function neal_docs_actor(): array
{
    $adminId = (int)($_SESSION['admin_id'] ?? 0);
    $userId = (int)($_SESSION['user_id'] ?? 0);
    if (($adminId > 0) === ($userId > 0)) {
        http_response_code(403);
        exit('دسترسی غیرمجاز است.');
    }
    return $adminId > 0
        ? ['type' => 'admin', 'id' => $adminId]
        : ['type' => 'user', 'id' => $userId];
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
    if (empty($_SESSION['neal_docs_csrf'])) {
        $_SESSION['neal_docs_csrf'] = bin2hex(random_bytes(32));
    }
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
    $stmt = $db->prepare('INSERT INTO document_audit_log
        (admin_id,user_id,action,target_type,target_id,outcome,details)
        VALUES (:admin_id,:user_id,:action,:target_type,:target_id,:outcome,:details)');
    $stmt->execute([
        ':admin_id' => $actor['type'] === 'admin' ? $actor['id'] : null,
        ':user_id' => $actor['type'] === 'user' ? $actor['id'] : null,
        ':action' => substr($action, 0, 80),
        ':target_type' => $targetType,
        ':target_id' => $targetId,
        ':outcome' => $outcome,
        ':details' => substr($details, 0, 500),
    ]);
}

/* Deny by default. Direct file ACL overrides the folder ACL. */
function neal_docs_can(PDO $db, array $actor, int $folderId, ?int $fileId, string $permission): bool
{
    $columns = ['can_view','can_download','can_upload','can_edit','can_delete'];
    if (!in_array($permission, $columns, true)) {
        return false;
    }

    if ($actor['type'] === 'admin') {
        $stmt = $db->prepare('SELECT 1 FROM admins WHERE id = ? AND is_active = 1');
        $stmt->execute([$actor['id']]);
        return (bool)$stmt->fetchColumn();
    }

    $stmt = $db->prepare('SELECT 1 FROM users WHERE id = ? AND is_active = 1');
    $stmt->execute([$actor['id']]);
    if (!$stmt->fetchColumn()) {
        return false;
    }

    if ($fileId !== null) {
        $stmt = $db->prepare("SELECT {$permission} FROM document_permissions WHERE file_id = ? AND user_id = ? LIMIT 1");
        $stmt->execute([$fileId, $actor['id']]);
        $direct = $stmt->fetchColumn();
        if ($direct !== false) {
            return (int)$direct === 1;
        }
    }

    $stmt = $db->prepare("SELECT {$permission} FROM document_permissions WHERE folder_id = ? AND user_id = ? LIMIT 1");
    $stmt->execute([$folderId, $actor['id']]);
    return (int)$stmt->fetchColumn() === 1;
}

function neal_docs_h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
