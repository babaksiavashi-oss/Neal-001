<?php
declare(strict_types=1);
require_once __DIR__ . '/common.php';

$actor = neal_docs_actor();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); header('Allow: POST'); exit('روش درخواست مجاز نیست.'); }
neal_docs_csrf_check();
$db = neal_docs_db();
$folderId = (int)($_POST['folder_id'] ?? 0);
if ($folderId < 1) { http_response_code(400); exit('پوشه معتبر نیست.'); }

try {
    $q = $db->prepare('SELECT id,name,folder_type,owner_user_id FROM document_folders WHERE id=? AND is_deleted=0');
    $q->execute([$folderId]); $folder = $q->fetch();
    if (!$folder) { http_response_code(404); exit('پوشه پیدا نشد.'); }
    $owns = $actor['type']==='user' && $folder['folder_type']==='personal' && (int)$folder['owner_user_id']===(int)$actor['id'];
    if (!$owns && !neal_docs_can($db,$actor,$folderId,null,'can_upload')) { neal_docs_audit($db,$actor,'file.upload','folder',$folderId,'denied'); http_response_code(403); exit('مجوز آپلود در این پوشه را ندارید.'); }
    if ($actor['type']==='user') {
        $active=$db->prepare('SELECT 1 FROM users WHERE id=? AND is_active=1'); $active->execute([$actor['id']]);
        if (!$active->fetchColumn()) { http_response_code(403); exit('حساب کاربری غیرفعال است.'); }
    }
    if (!isset($_FILES['document']) || !is_array($_FILES['document'])) { http_response_code(400); exit('فایلی انتخاب نشده است.'); }
    $f=$_FILES['document'];
    if (($f['error'] ?? UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK || !is_uploaded_file((string)$f['tmp_name'])) { http_response_code(400); exit('آپلود فایل ناموفق بود.'); }
    $size=(int)$f['size'];
    if ($size<1 || $size>100*1024*1024) { http_response_code(413); exit('حداکثر اندازهٔ فایل ۱۰۰ مگابایت است.'); }
    $original=trim(basename(str_replace('\\','/',(string)$f['name'])));
    $extension=strtolower(pathinfo($original,PATHINFO_EXTENSION));
    $allowed=['docx','xlsx','pptx','odt','ods','odp','pdf','txt','csv','rtf'];
    if (!in_array($extension,$allowed,true)) { http_response_code(415); exit('نوع فایل پشتیبانی نمی‌شود.'); }
    $display=preg_replace('/[\x00-\x1F\x7F]/u','',$original);
    if ($display==='' || mb_strlen($display)>240) { http_response_code(400); exit('نام فایل معتبر نیست.'); }
    $mime=(new finfo(FILEINFO_MIME_TYPE))->file((string)$f['tmp_name']) ?: 'application/octet-stream';
    $storageId=bin2hex(random_bytes(24)).'.'.$extension;
    $body=file_get_contents((string)$f['tmp_name']);
    if ($body===false) throw new RuntimeException('خواندن فایل موقت ناموفق بود.');
    $remote=neal_docs_storage_request('POST','/files',$body,['Content-Type: application/octet-stream','X-Document-Key: '.$storageId]);
    if ($remote['status']!==200) throw new RuntimeException('ذخیره‌سازی فایل خطا داد (HTTP '.$remote['status'].').');
    $db->beginTransaction();
    $ins=$db->prepare('INSERT INTO document_files(folder_id,display_name,storage_id,extension,mime_type,file_size,created_by_admin_id,created_by_user_id) VALUES(?,?,?,?,?,?,?,?)');
    $ins->execute([$folderId,$display,$storageId,$extension,$mime,$size,$actor['type']==='admin'?$actor['id']:null,$actor['type']==='user'?$actor['id']:null]);
    $fileId=(int)$db->lastInsertId();
    neal_docs_audit($db,$actor,'file.upload','file',$fileId,'success','bytes='.$size);
    $db->commit();
    header('Location: index.php?folder='.$folderId.'&uploaded=1'); exit;
} catch (Throwable $e) {
    if ($db->inTransaction()) $db->rollBack();
    error_log('NEAL document upload: '.$e->getMessage());
    http_response_code($e instanceof RuntimeException ? 400 : 500);
    exit($e instanceof RuntimeException ? neal_docs_h($e->getMessage()) : 'آپلود انجام نشد.');
}
