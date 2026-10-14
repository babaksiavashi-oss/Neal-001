<?php
declare(strict_types=1);
require_once __DIR__.'/auth/auth.php';
require_once __DIR__.'/includes/useful-files-common.php';
$isAdmin=is_admin_logged_in();$isUser=is_user_logged_in();
if(!$isAdmin&&!$isUser){http_response_code(401);exit('برای دریافت فایل ابتدا وارد حساب کاربری شوید.');}
$id=(int)($_GET['id']??0);if($id<=0){http_response_code(400);exit('درخواست دریافت فایل معتبر نیست.');}
try{
$db=neal_useful_files_db();$s=$db->prepare('SELECT * FROM useful_files WHERE id=? LIMIT 1');$s->execute([$id]);$file=$s->fetch();
if(!$file){http_response_code(404);exit('فایل موردنظر پیدا نشد.');}
if(!$isAdmin&&(int)$file['is_active']!==1){http_response_code(404);exit('فایل در دسترس نیست.');}
$stored=basename((string)$file['stored_name']);$path=neal_useful_files_dir().'/'.$stored;
if($stored===''||!is_file($path)||!is_readable($path)){http_response_code(410);exit('فایل روی سرور موجود نیست.');}
$name=neal_uf_clean_name((string)$file['original_name']);$mime=(string)$file['mime_type'];
$allowed=['application/pdf','application/msword','application/vnd.openxmlformats-officedocument.wordprocessingml.document','application/vnd.ms-excel','application/vnd.openxmlformats-officedocument.spreadsheetml.sheet','application/vnd.ms-powerpoint','application/vnd.openxmlformats-officedocument.presentationml.presentation','image/jpeg','image/png','image/gif','image/webp','text/plain','text/csv'];
if(!in_array($mime,$allowed,true))$mime='application/octet-stream';
while(ob_get_level()>0)ob_end_clean();
header('Content-Type: '.$mime);header('Content-Length: '.(string)filesize($path));header('Content-Disposition: attachment; filename="download"; filename*=UTF-8\'\''.rawurlencode($name));header('X-Content-Type-Options: nosniff');header('Cache-Control: private, no-store, no-cache, must-revalidate');header('Pragma: no-cache');readfile($path);exit;
}catch(Throwable $e){error_log('[Neal useful-file-download] '.get_class($e).': '.$e->getMessage());http_response_code(500);exit('دریافت فایل ناموفق بود.');}
