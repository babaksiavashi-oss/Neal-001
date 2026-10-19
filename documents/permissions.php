<?php
declare(strict_types=1);
require_once __DIR__ . '/common.php';
$actor = neal_docs_require_admin();
$db = neal_docs_db();
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    neal_docs_csrf_check();
    $userId = (int)($_POST['user_id'] ?? 0);
    $folderId = (int)($_POST['folder_id'] ?? 0);
    $checkUser = $db->prepare('SELECT id FROM users WHERE id = ? AND is_active = 1');
    $checkUser->execute([$userId]);
    $checkFolder = $db->prepare("SELECT id FROM document_folders WHERE id = ? AND folder_type = 'shared' AND is_deleted = 0");
    $checkFolder->execute([$folderId]);
    if ($checkUser->fetchColumn() && $checkFolder->fetchColumn()) {
        $values = [];
        foreach (['can_view','can_download','can_upload','can_edit','can_delete'] as $key) {
            $values[$key] = isset($_POST[$key]) ? 1 : 0;
        }
        if (!$values['can_view']) {
            foreach (['can_download','can_upload','can_edit','can_delete'] as $key) $values[$key] = 0;
        }
        $sql = "INSERT INTO document_permissions(folder_id,file_id,user_id,can_view,can_download,can_upload,can_edit,can_delete,granted_by_admin_id)
        VALUES(?,NULL,?,?,?,?,?,?,?)
        ON CONFLICT(folder_id,user_id) WHERE folder_id IS NOT NULL DO UPDATE SET
        can_view=excluded.can_view, can_download=excluded.can_download, can_upload=excluded.can_upload,
        can_edit=excluded.can_edit, can_delete=excluded.can_delete, granted_by_admin_id=excluded.granted_by_admin_id,
        updated_at=CURRENT_TIMESTAMP";
        $stmt = $db->prepare($sql);
        $stmt->execute([$folderId,$userId,$values['can_view'],$values['can_download'],$values['can_upload'],$values['can_edit'],$values['can_delete'],$actor['id']]);
        neal_docs_audit($db,$actor,'permission.save','permission',$folderId,'success','folder ACL saved for user '.$userId);
        $message = 'مجوزها ذخیره شدند.';
    } else {
        $message = 'کاربر یا پوشه انتخاب‌شده معتبر نیست.';
    }
}
$users = $db->query('SELECT id, username, fullname FROM users WHERE is_active = 1 ORDER BY username')->fetchAll();
$folders = $db->query("SELECT id, name FROM document_folders WHERE folder_type = 'shared' AND is_deleted = 0 ORDER BY name")->fetchAll();
?><!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>مجوزهای اسناد</title>
<style>body{font:16px Tahoma;background:#f3f6fa;color:#182538;max-width:900px;margin:30px auto;padding:0 16px}section{background:#fff;padding:22px;border:1px solid #dce4ef;border-radius:14px;margin:16px 0}label{display:inline-flex;gap:6px;margin:10px}select,button{font:inherit;padding:10px;border-radius:8px;border:1px solid #ccd5e2}button{background:#176b87;color:#fff;cursor:pointer}.msg{padding:12px;background:#e8f6ee;border-radius:8px}</style></head><body>
<h1>مجوزهای پوشه‌های مشترک</h1><?php if($message!==''):?><p class="msg"><?=neal_docs_h($message)?></p><?php endif;?>
<section><form method="post"><input type="hidden" name="_csrf" value="<?=neal_docs_h(neal_docs_csrf_token())?>">
<p><label>کاربر فعال <select name="user_id" required><?php foreach($users as $u):?><option value="<?=(int)$u['id']?>"><?=neal_docs_h(trim(($u['fullname']??'').' — '.($u['username']??''),' —'))?></option><?php endforeach;?></select></label></p>
<p><label>پوشه مشترک <select name="folder_id" required><?php foreach($folders as $f):?><option value="<?=(int)$f['id']?>"><?=neal_docs_h($f['name'])?></option><?php endforeach;?></select></label></p>
<?php foreach(['can_view'=>'مشاهده','can_download'=>'دانلود','can_upload'=>'آپلود','can_edit'=>'ویرایش','can_delete'=>'حذف'] as $key=>$label):?><label><input type="checkbox" name="<?=$key?>" value="1"><?=$label?></label><?php endforeach;?>
<p><button type="submit">ذخیره مجوزها</button></p></form></section></body></html>
