<?php
declare(strict_types=1);

// This maintenance script is intentionally CLI-only.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../includes/chat-common.php';

try {
    $db = neal_chat_db();
    $deleted = neal_chat_cleanup_expired($db);
    fwrite(STDOUT, '[' . date('Y-m-d H:i:s') . '] expired chat attachments cleaned: ' . $deleted . PHP_EOL);
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, '[' . date('Y-m-d H:i:s') . '] cleanup failed: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
