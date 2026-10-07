<?php
function neal_profile_db() {
    $db = new PDO("sqlite:" . __DIR__ . "/../data/neal.db");
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    return $db;
}

function neal_ensure_profile_columns($db) {
    $cols = $db->query("PRAGMA table_info(users)")->fetchAll();
    $names = [];
    foreach ($cols as $col) $names[$col['name'] ?? ''] = true;

    if (!isset($names['avatar'])) {
        $db->exec("ALTER TABLE users ADD COLUMN avatar TEXT NOT NULL DEFAULT 'default'");
    }

    if (!isset($names['user_status'])) {
        $db->exec("ALTER TABLE users ADD COLUMN user_status TEXT NOT NULL DEFAULT ''");
    }
}

function neal_allowed_builtin_avatars() {
    $allowed = ['default'];
    foreach (['male','female'] as $g) {
        for ($i = 1; $i <= 10; $i++) {
            $allowed[] = $g . '_' . str_pad((string)$i, 2, '0', STR_PAD_LEFT);
        }
    }
    return $allowed;
}

function neal_is_custom_avatar($avatar) {
    return (bool)preg_match('/^custom_[a-f0-9]{32}\\.(jpg|jpeg|png)$/i', (string)$avatar);
}

function neal_custom_avatar_filename($avatar) {
    return neal_is_custom_avatar($avatar) ? basename($avatar) : '';
}

function neal_avatar_url($avatar) {
    if (neal_is_custom_avatar($avatar)) {
        return 'images/avatars/' . basename($avatar);
    }

    $allowed = neal_allowed_builtin_avatars();
    return 'images/avatars/' . (in_array($avatar, $allowed, true) ? $avatar : 'default') . '.svg';
}

function neal_delete_custom_avatar($avatar) {
    if (!neal_is_custom_avatar($avatar)) return;

    $path = __DIR__ . '/../images/avatars/' . basename($avatar);
    if (is_file($path)) @unlink($path);
}

function neal_get_user_profile($db, $username) {
    if ($username === '') return null;

    neal_ensure_profile_columns($db);

    $s = $db->prepare("SELECT id,username,fullname,personnel_code,department,phone,is_active,
        COALESCE(NULLIF(avatar,''),'default') avatar,
        COALESCE(user_status,'') user_status
        FROM users WHERE username=? LIMIT 1");
    $s->execute([$username]);
    $u = $s->fetch();

    if (!$u) return null;

    $allowed = neal_allowed_builtin_avatars();
    if (!in_array($u['avatar'], $allowed, true) && !neal_is_custom_avatar($u['avatar'])) {
        $u['avatar'] = 'default';
    }

    return $u;
}
?>