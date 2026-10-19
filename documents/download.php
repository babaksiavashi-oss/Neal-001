<?php
declare(strict_types=1);
require_once __DIR__ . '/common.php';
$actor=neal_docs_actor();
$id=max(0,(int)($_GET['id']??0));
$db=neal_docs_db();
$q=$db->prepare('SELECT f.id,f.folder_id,f.display_name,f.storage_id,f.file_size,f.mime_type,f.is_deleted,d.folder_type,d.owner_user_id FROM document_files f JOIN document_folders d ON d.id=f.folder_id WHERE f.id=? AND f.is_deleted=0 AND d.is_deleted=0');
$q->execute([$id]); $file=$q->fetch();
if (!$file) { http_response_code(404); exit('فایل پیدا نشد.'); }
$owns=$actor['type']==='user' && $file['folder_type']==='personal' && (int)$file['owner_user_id']===(int)$actor['id'];
if (!$owns && !neal_docs_can($db,$actor,(int)$file['folder_id'],(int)$file['id'],'can_download')) {
    neal_docs_audit($db,$actor,'file.download','file',(int)$file['id'],'denied');
    http_response_code(403); exit('مجوز دانلود این فایل را ندارید.');
}
if ($actor['type']==='user') {
    $active=$db->prepare('SELECT 1 FROM users WHERE id=? AND is_active=1'); $active->execute([$actor['id']]);
    if (!$active->fetchColumn()) { http_response_code(403); exit('حساب کاربری غیرفعال است.'); }
}
try {
    $remote=neal_docs_storage_request('GET','/files/'.$file['storage_id']);
    if ($remote['status']!==200) throw new RuntimeException('دریافت فایل از مخزن ناموفق بود (HTTP '.$remote['status'].').');
    neal_docs_audit($db,$actor,'file.download','file',(int)$file['id'],'success');
    header('Content-Type: '.preg_replace('/[^a-zA-Z0-9!#$&^_.+-\/;]/','',(string)$file['mime_type']));
    header('Content-Length: '.strlen($remote['body']));
    header('Content-Disposition: attachment; filename="document-'.(int)$file['id'].'.'.preg_replace('/[^a-z0-9]/i','',(string)pathinfo($file['display_name'],PATHINFO_EXTENSION)).'"; filename*=UTF-8\'\''.rawurlencode($file['display_name']));
    header('Cache-Control: no-store');
    echo $remote['body'];
} catch (Throwable $e) {
    error_log('NEAL document download: '.$e->getMessage());
    http_response_code(502); exit('دریافت فایل انجام نشد.');
}
