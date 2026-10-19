<?php
declare(strict_types=1);
require_once __DIR__ . '/common.php';
$actor = neal_docs_actor();
$db = neal_docs_db();
$folderId = max(0, (int)($_GET['folder'] ?? 0));
if ($actor['type'] === 'admin') {
    $stmt = $db->prepare("SELECT id,name,parent_id,folder_type,owner_user_id FROM document_folders WHERE id=? AND is_deleted=0");
    $stmt->execute([$folderId]);
    $current = $folderId ? $stmt->fetch() : null;
    $foldersStmt = $db->prepare("SELECT id,name,parent_id,folder_type,owner_user_id FROM document_folders WHERE is_deleted=0 AND ".($folderId ? "parent_id=?" : "parent_id IS NULL")." ORDER BY name");
    $foldersStmt->execute($folderId ? [$folderId] : []);
} else {
    $active = $db->prepare('SELECT 1 FROM users WHERE id=? AND is_active=1');
    $active->execute([$actor['id']]);
    if (!$active->fetchColumn()) { http_response_code(403); exit('حساب کاربری غیرفعال است.'); }
    if ($folderId) {
        $stmt = $db->prepare("SELECT id,name,parent_id,folder_type,owner_user_id FROM document_folders WHERE id=? AND is_deleted=0");
        $stmt->execute([$folderId]); $current = $stmt->fetch();
        if (!$current || (!neal_docs_can($db,$actor,(int)$current['id'],null,'can_view') && !($current['folder_type']==='personal' && (int)$current['owner_user_id']===(int)$actor['id']))) {
            http_response_code(403); exit('به این پوشه دسترسی ندارید.');
        }
    } else { $current = null; }
    $foldersStmt = $db->prepare("SELECT f.id,f.name,f.parent_id,f.folder_type,f.owner_user_id FROM document_folders f
      WHERE f.is_deleted=0 AND ".($folderId ? "f.parent_id=?" : "f.parent_id IS NULL")."
      AND ( (f.folder_type='personal' AND f.owner_user_id=?) OR EXISTS(SELECT 1 FROM document_permissions p WHERE p.folder_id=f.id AND p.user_id=? AND p.can_view=1) )
      ORDER BY f.name");
    $foldersStmt->execute($folderId ? [$folderId,$actor['id'],$actor['id']] : [$actor['id'],$actor['id']]);
}
if ($folderId && !$current) { http_response_code(404); exit('پوشه پیدا نشد.'); }
$folders = $foldersStmt->fetchAll();
if ($folderId && $actor['type']==='user' && $current['folder_type']==='personal' && (int)$current['owner_user_id']===(int)$actor['id']) {
    $filesStmt = $db->prepare("SELECT id,display_name,file_size,updated_at FROM document_files WHERE folder_id=? AND is_deleted=0 ORDER BY display_name");
    $filesStmt->execute([$folderId]);
} elseif ($folderId && $actor['type']==='user') {
    $filesStmt = $db->prepare("SELECT f.id,f.display_name,f.file_size,f.updated_at FROM document_files f WHERE f.folder_id=? AND f.is_deleted=0 AND EXISTS(SELECT 1 FROM document_permissions p WHERE p.file_id=f.id AND p.user_id=? AND p.can_view=1) ORDER BY f.display_name");
    $filesStmt->execute([$folderId,$actor['id']]);
} elseif ($folderId) {
    $filesStmt = $db->prepare("SELECT id,display_name,file_size,updated_at FROM document_files WHERE folder_id=? AND is_deleted=0 ORDER BY display_name");
    $filesStmt->execute([$folderId]);
} else { $filesStmt = $db->query("SELECT id,display_name,file_size,updated_at FROM document_files WHERE 1=0"); }
$files = $filesStmt->fetchAll();
$canCreate = $actor['type']==='admin' || !$current || ($current['folder_type']==='personal' && (int)$current['owner_user_id']===(int)$actor['id']) || neal_docs_can($db,$actor,$folderId,null,'can_upload');
?><!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>اسناد | NEAL Pharmed</title><style>body{font:15px Tahoma;background:#f3f6fa;color:#182538;max-width:1000px;margin:28px auto;padding:0 16px}.card{background:#fff;border:1px solid #dce4ef;border-radius:14px;padding:20px;margin:14px 0}a{color:#176b87;text-decoration:none}li{padding:9px;border-bottom:1px solid #edf0f5}.muted{color:#66758a;font-size:12px}input,button{font:inherit;padding:9px;border:1px solid #ccd5e2;border-radius:8px}button{background:#176b87;color:white}</style></head><body><h1>اسناد و فایل‌ها</h1><p class="muted">مرور پوشه‌ها فعال است؛ آپلود، دانلود و ویرایش پس از تکمیل اتصال امن ذخیره‌سازی و ONLYOFFICE فعال می‌شود.</p>
<section class="card"><h2><?= $current ? neal_docs_h($current['name']) : 'پوشه‌های اصلی' ?></h2><?php if($current && $current['parent_id']):?><p><a href="?folder=<?=(int)$current['parent_id']?>">← پوشه والد</a></p><?php elseif($current):?><p><a href="index.php">← ریشه</a></p><?php endif;?>
<?php if($canCreate):?><form method="post" action="folder-create.php"><input type="hidden" name="_csrf" value="<?=neal_docs_h(neal_docs_csrf_token())?>"><input type="hidden" name="parent_id" value="<?=$folderId?>"><label>پوشه جدید <input name="name" maxlength="120" required></label> <button type="submit">ایجاد پوشه</button></form><?php endif;?>
<ul><?php foreach($folders as $f):?><li><a href="?folder=<?=(int)$f['id']?>">📁 <?=neal_docs_h($f['name'])?></a></li><?php endforeach;?></ul><?php if(!$folders):?><p class="muted">پوشه قابل مشاهده‌ای وجود ندارد.</p><?php endif;?></section>
<section class="card"><h2>فایل‌ها</h2><?php if(!$files):?><p class="muted">در این پوشه فایلی برای نمایش وجود ندارد.</p><?php else:?><ul><?php foreach($files as $f):?><li><?=neal_docs_h($f['display_name'])?> — <?=number_format((int)$f['file_size'])?> بایت</li><?php endforeach;?></ul><?php endif;?></section></body></html>
