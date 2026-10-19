<?php
declare(strict_types=1);
require_once __DIR__ . '/common.php';
$actor = neal_docs_require_admin();
$db = neal_docs_db();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    neal_docs_csrf_check();
    $name = trim((string)($_POST['name'] ?? ''));
    if ($name !== '' && mb_strlen($name) <= 120 && !str_contains($name, '/') && !str_contains($name, chr(92))) {
        $q = $db->prepare("INSERT INTO document_folders(name,folder_type,created_by_admin_id) VALUES(?,'shared',?)");
        $q->execute([$name,$actor['id']]);
        neal_docs_audit($db,$actor,'folder.create','folder',(int)$db->lastInsertId(),'success');
        header('Location: admin.php'); exit;
    }
}
$folders = $db->query("SELECT id,name FROM document_folders WHERE folder_type='shared' AND is_deleted=0 ORDER BY name")->fetchAll();
$token = neal_docs_csrf_token();
?><!doctype html><html lang="fa" dir="rtl"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>مدیریت اسناد</title>
<style>body{font:16px Tahoma;background:#f3f6fa;color:#182538;max-width:900px;margin:30px auto;padding:0 16px}section{background:white;padding:22px;border:1px solid #dce4ef;border-radius:14px;margin:16px 0}input,button{font:inherit;padding:10px;border-radius:8px;border:1px solid #ccd5e2}button{background:#176b87;color:white;cursor:pointer}li{padding:8px}</style>
<h1>مدیریت اسناد مشترک</h1><p>صفحه آزمایشی مدیر؛ هنوز به منوی اصلی متصل نشده است.</p>
<section><h2>ساخت پوشه مشترک</h2><form method="post"><input type="hidden" name="_csrf" value="<?=neal_docs_h($token)?>"><label>نام پوشه <input name="name" maxlength="120" required></label> <button>ایجاد</button></form></section>
<section><h2>پوشه‌ها</h2><ul><?php foreach($folders as $f):?><li><?=neal_docs_h($f['name'])?> (<?= (int)$f['id']?>)</li><?php endforeach;?></ul></section></html>
