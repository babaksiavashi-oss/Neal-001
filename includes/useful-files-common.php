<?php
declare(strict_types=1);

require_once __DIR__ . '/user-profile.php';

function neal_useful_files_db(): PDO {
    $db = neal_profile_db();
    $db->exec("CREATE TABLE IF NOT EXISTS useful_files (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        title TEXT NOT NULL,
        description TEXT NOT NULL DEFAULT '',
        department TEXT NOT NULL,
        original_name TEXT NOT NULL,
        stored_name TEXT NOT NULL,
        mime_type TEXT NOT NULL,
        file_size INTEGER NOT NULL DEFAULT 0,
        is_active INTEGER NOT NULL DEFAULT 1,
        sort_order INTEGER NOT NULL DEFAULT 0,
        created_by INTEGER NULL,
        created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
    )");
    $db->exec('CREATE INDEX IF NOT EXISTS idx_useful_files_department ON useful_files(department, is_active, sort_order)');
    return $db;
}

function neal_useful_files_departments(PDO $db): array {
    try {
        return $db->query("SELECT name FROM departments WHERE is_active=1 ORDER BY id")->fetchAll(PDO::FETCH_COLUMN);
    } catch (Throwable $e) {
        return [];
    }
}

function neal_useful_files_dir(): string {
    return dirname(dirname(dirname(__DIR__))) . '/neal-useful-files';
}

function neal_uf_h($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function neal_uf_clean_name(string $name): string {
    $name = basename(str_replace("\\", '/', $name));
    $name = preg_replace('/[\\r\\n\\0]+/', '', $name) ?? '';
    return trim(substr($name !== '' ? $name : 'file', 0, 180));
}

function neal_uf_validate_upload(array $file): array {
    if ((int)($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('آپلود فایل کامل نشد یا از محدودیت PHP بیشتر است.');
    }
    $size = (int)($file['size'] ?? 0);
    if ($size < 1 || $size > 20 * 1024 * 1024) {
        throw new RuntimeException('حجم فایل باید بیشتر از صفر و حداکثر ۲۰ مگابایت باشد.');
    }
    $tmp = (string)($file['tmp_name'] ?? '');
    if ($tmp === '' || !is_uploaded_file($tmp) || !class_exists('finfo')) {
        throw new RuntimeException('فایل آپلودشده معتبر نیست یا ماژول Fileinfo فعال نیست.');
    }
    $original = neal_uf_clean_name((string)($file['name'] ?? 'file'));
    $ext = strtolower(pathinfo($original, PATHINFO_EXTENSION));
    $types = [
        'pdf'=>'application/pdf','doc'=>'application/msword',
        'docx'=>'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'xls'=>'application/vnd.ms-excel',
        'xlsx'=>'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'ppt'=>'application/vnd.ms-powerpoint',
        'pptx'=>'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'jpg'=>'image/jpeg','jpeg'=>'image/jpeg','png'=>'image/png',
        'gif'=>'image/gif','webp'=>'image/webp','txt'=>'text/plain','csv'=>'text/csv'
    ];
    if (!isset($types[$ext])) {
        throw new RuntimeException('فرمت مجاز: PDF، Word، Excel، PowerPoint، تصویر، TXT و CSV.');
    }
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = strtolower((string)$finfo->file($tmp));
    $compatible = [$types[$ext]];
    if (in_array($ext, ['docx','xlsx','pptx'], true)) $compatible[] = 'application/zip';
    if (in_array($ext, ['doc','xls','ppt'], true)) $compatible = array_merge($compatible, ['application/octet-stream','application/x-ole-storage','application/cdfv2']);
    if ($ext === 'csv') $compatible[] = 'application/vnd.ms-excel';
    if (!in_array($mime, $compatible, true)) {
        throw new RuntimeException('نوع واقعی فایل با پسوند آن مطابقت ندارد.');
    }
    if (str_starts_with($ext, 'jpg') || in_array($ext, ['jpeg','png','gif','webp'], true)) {
        $info = @getimagesize($tmp);
        $expected = ['jpg'=>IMAGETYPE_JPEG,'jpeg'=>IMAGETYPE_JPEG,'png'=>IMAGETYPE_PNG,'gif'=>IMAGETYPE_GIF,'webp'=>IMAGETYPE_WEBP][$ext];
        if (!$info || (int)$info[2] !== $expected) throw new RuntimeException('محتوای تصویر معتبر نیست.');
    }
    return ['tmp_name'=>$tmp,'original_name'=>$original,'extension'=>$ext,'mime_type'=>$types[$ext],'file_size'=>$size];
}
