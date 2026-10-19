<?php
declare(strict_types=1);
require_once __DIR__ . '/common.php';
$actor = neal_docs_actor();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit('روش درخواست مجاز نیست.');
}
neal_docs_csrf_check();
$db = neal_docs_db();
$name = trim((string)($_POST['name'] ?? ''));
$parentId = max(0, (int)($_POST['parent_id'] ?? 0));
if ($name === '' || mb_strlen($name) > 120 || str_contains($name, '/') || str_contains($name, chr(92)) || preg_match('/[\x00-\x1F\x7F]/u', $name)) {
    http_response_code(400);
    exit('نام پوشه معتبر نیست.');
}
try {
    $db->beginTransaction();
    if ($actor['type'] === 'user') {
        $active = $db->prepare('SELECT 1 FROM users WHERE id=? AND is_active=1');
        $active->execute([$actor['id']]);
        if (!$active->fetchColumn()) throw new RuntimeException('حساب کاربری غیرفعال است.');
    }
    if ($parentId > 0) {
        $q = $db->prepare('SELECT id,folder_type,owner_user_id FROM document_folders WHERE id=? AND is_deleted=0');
        $q->execute([$parentId]);
        $parent = $q->fetch();
        if (!$parent) throw new RuntimeException('پوشه والد پیدا نشد.');
        if ($actor['type'] === 'user') {
            $ownsPersonal = $parent['folder_type'] === 'personal' && (int)$parent['owner_user_id'] === (int)$actor['id'];
            $canUpload = neal_docs_can($db,$actor,$parentId,null,'can_upload');
            if (!$ownsPersonal && !$canUpload) throw new RuntimeException('مجوز ساخت زیرپوشه را ندارید.');
        }
        $type = $parent['folder_type'];
        $ownerId = $type === 'personal' ? (int)$parent['owner_user_id'] : null;
    } else {
        if ($actor['type'] === 'admin') {
            $type = 'shared'; $ownerId = null;
        } else {
            $type = 'personal'; $ownerId = (int)$actor['id'];
        }
    }
    if ($type === 'personal' && $actor['type'] === 'user' && $ownerId !== (int)$actor['id']) {
        throw new RuntimeException('دسترسی به فضای شخصی کاربر دیگر مجاز نیست.');
    }
    $dup = $db->prepare("SELECT 1 FROM document_folders WHERE name=? AND is_deleted=0 AND ((parent_id IS NULL AND ?=0) OR parent_id=?) AND folder_type=? AND COALESCE(owner_user_id,0)=COALESCE(?,0) LIMIT 1");
    $dup->execute([$name,$parentId,$parentId,$type,$ownerId]);
    if ($dup->fetchColumn()) throw new RuntimeException('پوشه‌ای با این نام در همین مسیر وجود دارد.');
    $adminCreator = $actor['type'] === 'admin' ? (int)$actor['id'] : null;
    $userCreator = $actor['type'] === 'user' ? (int)$actor['id'] : null;
    $ins = $db->prepare('INSERT INTO document_folders(name,parent_id,folder_type,owner_user_id,created_by_admin_id,created_by_user_id) VALUES(?,?,?,?,?,?)');
    $ins->execute([$name,$parentId ?: null,$type,$ownerId,$adminCreator,$userCreator]);
    $newId = (int)$db->lastInsertId();
    neal_docs_audit($db,$actor,'folder.create','folder',$newId,'success','folder type '.$type);
    $db->commit();
    header('Location: index.php?folder='.($parentId ?: $newId).'&created=1');
    exit;
} catch (Throwable $e) {
    if ($db->inTransaction()) $db->rollBack();
    error_log('NEAL document folder create: '.$e->getMessage());
    http_response_code($e instanceof RuntimeException ? 403 : 500);
    exit($e instanceof RuntimeException ? neal_docs_h($e->getMessage()) : 'ساخت پوشه انجام نشد.');
}
