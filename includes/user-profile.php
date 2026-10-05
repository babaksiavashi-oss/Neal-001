<?php
function neal_profile_db() {
    $db = new PDO("sqlite:" . __DIR__ . "/../data/neal.db");
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    return $db;
}
function neal_ensure_avatar_column($db) {
    $cols = $db->query("PRAGMA table_info(users)")->fetchAll();
    foreach ($cols as $col) if (($col['name'] ?? '') === 'avatar') return;
    $db->exec("ALTER TABLE users ADD COLUMN avatar TEXT NOT NULL DEFAULT 'default'");
}
function neal_get_user_profile($db, $username) {
    if ($username === '') return null;
    neal_ensure_avatar_column($db);
    $s=$db->prepare("SELECT id,username,fullname,personnel_code,department,phone,is_active,COALESCE(NULLIF(avatar,''),'default') avatar FROM users WHERE username=? LIMIT 1");
    $s->execute([$username]); $u=$s->fetch();
    if (!$u) return null;
    $allowed=['default'];
    foreach(['male','female'] as $g) for($i=1;$i<=10;$i++) $allowed[]=$g.'_'.str_pad((string)$i,2,'0',STR_PAD_LEFT);
    if(!in_array($u['avatar'],$allowed,true)) $u['avatar']='default';
    return $u;
}
function neal_avatar_url($avatar) {
    $allowed=['default'];
    foreach(['male','female'] as $g) for($i=1;$i<=10;$i++) $allowed[]=$g.'_'.str_pad((string)$i,2,'0',STR_PAD_LEFT);
    return 'images/avatars/'.(in_array($avatar,$allowed,true)?$avatar:'default').'.svg';
}
?>