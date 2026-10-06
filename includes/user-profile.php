<?php
function neal_profile_db() {
    $db = new PDO("sqlite:" . __DIR__ . "/../data/neal.db");
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    neal_ensure_profile_columns($db);
    return $db;
}

function neal_ensure_profile_columns($db) {
    $cols = $db->query("PRAGMA table_info(users)")->fetchAll(PDO::FETCH_ASSOC);
    $names = [];
    foreach ($cols as $col) $names[$col['name'] ?? ''] = true;

    if (!isset($names['avatar'])) {
        $db->exec("ALTER TABLE users ADD COLUMN avatar TEXT NOT NULL DEFAULT 'default'");
    }
    if (!isset($names['login_message'])) {
        $db->exec("ALTER TABLE users ADD COLUMN login_message TEXT NOT NULL DEFAULT ''");
    }
    if (!isset($names['login_message_status'])) {
        $db->exec("ALTER TABLE users ADD COLUMN login_message_status TEXT NOT NULL DEFAULT 'none'");
    }
    if (!isset($names['login_message_rejection_reason'])) {
        $db->exec("ALTER TABLE users ADD COLUMN login_message_rejection_reason TEXT NOT NULL DEFAULT ''");
    }
    if (!isset($names['login_message_submitted_at'])) {
        $db->exec("ALTER TABLE users ADD COLUMN login_message_submitted_at TEXT NULL");
    }
    if (!isset($names['login_message_reviewed_at'])) {
        $db->exec("ALTER TABLE users ADD COLUMN login_message_reviewed_at TEXT NULL");
    }
    if (!isset($names['login_message_reviewed_by'])) {
        $db->exec("ALTER TABLE users ADD COLUMN login_message_reviewed_by INTEGER NULL");
    }
}

function neal_allowed_builtin_avatars() {
    $allowed = ['default'];
    foreach (['male','female'] as $g) {
        for ($i=1; $i<=10; $i++) {
            $allowed[] = $g.'_'.str_pad((string)$i,2,'0',STR_PAD_LEFT);
        }
    }
    return $allowed;
}

function neal_is_custom_avatar($avatar) {
    return is_string($avatar) && preg_match('/^custom:[a-f0-9]{32}\.(?:jpg|jpeg|png)$/i', $avatar) === 1;
}

function neal_custom_avatar_filename($avatar) {
    return neal_is_custom_avatar($avatar) ? substr($avatar, 7) : null;
}

function neal_avatar_url($avatar) {
    if (neal_is_custom_avatar($avatar)) {
        return 'images/avatars/' . rawurlencode(neal_custom_avatar_filename($avatar));
    }
    $allowed = neal_allowed_builtin_avatars();
    return 'images/avatars/'.(in_array($avatar,$allowed,true)?$avatar:'default').'.svg';
}

function neal_delete_custom_avatar($avatar) {
    $filename = neal_custom_avatar_filename($avatar);
    if (!$filename) return;
    $path = __DIR__ . '/../images/avatars/' . $filename;
    if (is_file($path)) @unlink($path);
}

function neal_get_user_profile($db, $username) {
    if ($username === '') return null;
    neal_ensure_profile_columns($db);
    $s=$db->prepare("SELECT id,username,fullname,personnel_code,department,phone,is_active,
        COALESCE(NULLIF(avatar,''),'default') avatar,
        COALESCE(login_message,'') login_message,
        COALESCE(login_message_status,'none') login_message_status,
        COALESCE(login_message_rejection_reason,'') login_message_rejection_reason,
        login_message_submitted_at, login_message_reviewed_at
        FROM users WHERE username=? LIMIT 1");
    $s->execute([$username]);
    $u=$s->fetch();
    if (!$u) return null;

    if (!neal_is_custom_avatar($u['avatar']) && !in_array($u['avatar'], neal_allowed_builtin_avatars(), true)) {
        $u['avatar']='default';
    }
    return $u;
}
?>