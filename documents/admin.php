<?php
declare(strict_types=1);
require_once __DIR__ . '/common.php';
$actor = neal_docs_require_admin();
$db = neal_docs_db();
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    neal_docs_csrf_check();
    $name = trim((string)($_POST['name'] ?? ''));
    $parentId = (int)($_POST['parent_id'] ?? 0);
    if ($name === '' || mb_strlen($name) > 120 || str_contains($name, '/') || str_contains($name, chr(92))) {
        $error = 'نام پوشه معتبر نیست.';
    } else {
        try {
            if ($parentId > 0) {
                $check = $db->prepare("SELECT id FROM document_folders WHERE id=? AND folder_type='shared' AND is_deleted=0");
                $check->execute([$parentId]);
                if (!$check->fetchColumn()) throw new RuntimeException('پوشه والد معتبر نیست.');
            }
            $q = $db->prepare("INSERT INTO document_folders(name,parent_id,folder_type,created_by_admin_id) VALUES(?,?,'shared',?)");
            $q->execute([$name,$parentId ?: null,$actor['id']]);
            neal_docs_audit($db,$actor,'folder.create','folder',(int)$db->lastInsertId(),'success','shared folder created');
            header('Location: admin.php?created=1'); exit;
        } catch (Throwable $e) {
            error_log('NEAL documents admin: '.$e->getMessage());
            $error = $e instanceof RuntimeException ? $e->getMessage() : 'ساخت پوشه انجام نشد.';
        }
    }
}
$folders = $db->query("SELECT id,name,parent_id FROM document_folders WHERE folder_type='shared' AND is_deleted=0 ORDER BY name")->fetchAll();
$token = neal_docs_csrf_token();
?><!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>مدیریت اسناد</title>
<style>body{font:16px Tahoma;background:#f3f6fa;color:#182538;max-width:900px;margin:30px auto;padding:0 16px}section{background:#fff;padding:22px;border:1px solid #dce4ef;border-radius:14px;margin:16px 0}input,select,button{font:inherit;padding:10px;border-radius:8px;border:1px solid #ccd5e2}button{background:#176b87;color:#fff;cursor:pointer}li{padding:8px}.msg{padding:12px;background:#e8f6ee;border-radius:8px}.err{padding:12px;background:#fff0f0;border-radius:8px}</style></head><body>
<h1>مدیریت اسناد مشترک</h1><p>بخش آزمایشی مدیریت اسناد؛ هنوز به منوی اصلی پرتال متصل نشده است.</p>
<?php if(isset($_GET['created'])):?><p class="msg">پوشه ساخته شد.</p><?php endif;?><?php if($error!==''):?><p class="err"><?=neal_docs_h($error)?></p><?php endif;?>
<section><h2>ساخت پوشه مشترک یا زیرپوشه</h2><form method="post"><input type="hidden" name="_csrf" value="<?=neal_docs_h($token)?>"><p><label>نام پوشه <input name="name" maxlength="120" required></label></p><p><label>پوشه والد <select name="parent_id"><option value="0">ریشه پوشه‌های مشترک</option><?php foreach($folders as $f):?><option value="<?=(int)$f['id']?>"><?=neal_docs_h($f['name'])?></option><?php endforeach;?></select></label></p><button type="submit">ایجاد پوشه</button></form></section>
<section><h2>پوشه‌ها</h2><ul><?php foreach($folders as $f):?><li><?=neal_docs_h($f['name'])?> <span>(شناسه <?=(int)$f['id']?>)</span></li><?php endforeach;?></ul></section></body></html>
