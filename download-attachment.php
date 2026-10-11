<?php

require_once __DIR__ . '/auth/auth.php';
require_once __DIR__ . '/config/config.php';


// ============================================================
// Authentication
// ============================================================

if (is_admin_logged_in()) {

    $is_admin = true;
    $is_user = false;
    $current_admin_id = (int)$_SESSION['admin_id'];

} elseif (is_user_logged_in()) {

    $is_admin = false;
    $is_user = true;
    $current_username = (string)$_SESSION['user_username'];

} else {

    http_response_code(403);
    exit('دسترسی غیرمجاز است.');

}


// ============================================================
// Attachment ID
// ============================================================

$attachment_id = (int)($_GET['id'] ?? 0);

if ($attachment_id <= 0) {

    http_response_code(400);
    exit('شناسه فایل نامعتبر است.');

}


// ============================================================
// Database
// ============================================================

try {

    $db = new PDO(
        'sqlite:' . __DIR__ . '/data/neal.db'
    );

    $db->setAttribute(
        PDO::ATTR_ERRMODE,
        PDO::ERRMODE_EXCEPTION
    );

    $db->setAttribute(
        PDO::ATTR_DEFAULT_FETCH_MODE,
        PDO::FETCH_ASSOC
    );

} catch (PDOException $e) {

    http_response_code(500);
    exit('خطا در اتصال به پایگاه داده.');

}


// ============================================================
// Load attachment + ticket owner
// ============================================================

$stmt = $db->prepare("
    SELECT
        ta.*,
        sr.requester_username,
        sr.id AS ticket_id
    FROM ticket_attachments ta
    INNER JOIN service_requests sr
        ON sr.id = ta.request_id
    WHERE ta.id = ?
    LIMIT 1
");

$stmt->execute([
    $attachment_id
]);

$attachment = $stmt->fetch();


// ============================================================
// Attachment not found
// ============================================================

if (!$attachment) {

    http_response_code(404);
    exit('فایل مورد نظر پیدا نشد.');

}


// ============================================================
// Authorization
// ============================================================

if ($is_user) {

    if (
        (string)$attachment['requester_username']
        !== $current_username
    ) {

        http_response_code(403);
        exit('شما اجازه دریافت این فایل را ندارید.');

    }

}


// ============================================================
// Secure stored filename
// ============================================================

$stored_name = basename(
    (string)($attachment['stored_name'] ?? '')
);

if ($stored_name === '') {

    http_response_code(404);
    exit('مسیر فایل معتبر نیست.');

}


$file_path =
    __DIR__
    . '/data/ticket_attachments/'
    . $stored_name;


// ============================================================
// Physical file check
// ============================================================

if (!is_file($file_path)) {

    http_response_code(404);
    exit('فایل روی سرور پیدا نشد.');

}


// ============================================================
// File information
// ============================================================

$original_name = (string)(
    $attachment['original_name']
    ?? 'attachment'
);

$mime_type = (string)(
    $attachment['mime_type']
    ?? ''
);

if ($mime_type === '') {

    $mime_type = 'application/octet-stream';

}

$file_size = filesize($file_path);


// ============================================================
// Safe download filename
// ============================================================

$download_name = basename($original_name);

if ($download_name === '') {

    $download_name = 'attachment';

}


// RFC 5987 filename
$encoded_name = rawurlencode(
    $download_name
);


// ============================================================
// Headers
// ============================================================

while (ob_get_level() > 0) {
    ob_end_clean();
}

header(
    'Content-Type: '
    . $mime_type
);

header(
    'Content-Length: '
    . $file_size
);

header(
    'Content-Disposition: attachment; filename="download"; filename*=UTF-8\'\''
    . $encoded_name
);

header(
    'X-Content-Type-Options: nosniff'
);

header(
    'Cache-Control: private, no-store, no-cache, must-revalidate'
);

header(
    'Pragma: no-cache'
);


// ============================================================
// Send file
// ============================================================

readfile($file_path);

exit;
