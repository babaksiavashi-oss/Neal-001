<?php
require_once __DIR__ . "/user-profile.php";
$umdb = neal_profile_db();
$umname = $_SESSION['user_username'] ?? '';
$um = neal_get_user_profile($umdb, $umname);
if (!$um) {
    $um = [
        'fullname' => $_SESSION['user_fullname'] ?? $umname,
        'username' => $umname,
        'personnel_code' => '',
        'department' => '',
        'avatar' => 'default'
    ];
}
$umav = neal_avatar_url($um['avatar']);
$umChatUnread = 0;
try {
    $umChatActorId = (int)($_SESSION['user_id'] ?? 0);
    if ($umChatActorId !== 0) {
        $umChatStmt = $umdb->prepare('SELECT COUNT(*) FROM chat_messages WHERE recipient_id = ? AND read_at IS NULL');
        $umChatStmt->execute([$umChatActorId]);
        $umChatUnread = (int)$umChatStmt->fetchColumn();
    }
} catch (Throwable $e) {
    $umChatUnread = 0;
}

$umcount = 0;
try {
    $s = $umdb->prepare("SELECT COUNT(*) FROM service_requests WHERE requester_username=?");
    $s->execute([$umname]);
    $umcount = (int)$s->fetchColumn();
} catch (Throwable $e) {
    $umcount = 0;
}
?>
<nav class="neal-user-nav" aria-label="منوی کاربری">
<style>
.neal-user-nav{direction:rtl;display:flex;align-items:center;gap:4px;width:100%;min-height:54px;padding:7px 22px;background:rgba(255,255,255,.96);border-bottom:1px solid #e7ebf0;box-shadow:0 1px 8px rgba(16,24,40,.04);position:relative;z-index:1000;font-family:Vazirmatn,Tahoma,sans-serif}
.neal-user-brand{display:inline-flex;align-items:center;gap:8px;margin-left:14px;padding:9px 12px;color:#102a43!important;text-decoration:none;font-size:14px;font-weight:800;white-space:nowrap}
.neal-user-brand small{color:#98a2b3;font-size:10px;font-weight:500}
.neal-user-nav>a,.neal-user-group>button{border:0;background:transparent;text-decoration:none;color:#475467;font:inherit;font-size:13px;padding:9px 12px;border-radius:9px;cursor:pointer;white-space:nowrap;transition:.16s ease}
.neal-user-nav>a:hover,.neal-user-group:hover>button,.neal-user-group:focus-within>button{background:#f4f6f8;color:#102a43}
.neal-user-group{position:relative}
.neal-user-dropdown{position:absolute;right:0;top:calc(100% + 5px);min-width:230px;background:#fff;border:1px solid #e7e9ee;border-radius:13px;box-shadow:0 16px 36px rgba(16,24,40,.13);padding:7px;opacity:0;visibility:hidden;transform:translateY(-5px);transition:.16s ease}
.neal-user-group:hover .neal-user-dropdown,.neal-user-group:focus-within .neal-user-dropdown{opacity:1;visibility:visible;transform:translateY(0)}
.neal-user-dropdown a{display:flex;align-items:center;gap:9px;padding:10px 11px;border-radius:8px;color:#344054;text-decoration:none;font-size:12px}
.neal-user-dropdown a:hover{background:#f5f7fa;color:#102a43}
.neal-user-section{padding:7px 11px 4px;color:#98a2b3;font-size:9px;font-weight:700}
.neal-user-profile{margin-right:auto;display:flex;align-items:center;gap:10px;padding:4px 7px 4px 4px;border-radius:11px}
.neal-user-profile:hover{background:#f7f8fa}
.neal-user-profile img{width:36px;height:36px;border-radius:50%;object-fit:cover;border:2px solid #eef1f5}
.neal-user-avatar-wrap{display:flex;flex-direction:column;align-items:center;gap:5px;flex-shrink:0}.neal-user-status{max-width:150px;padding:3px 8px;border-radius:999px;background:#f4f7fb;border:1px solid #e6ebf2;color:#667085;font-size:9px;line-height:1.4;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.neal-user-identity{display:flex;flex-direction:column;align-items:flex-start;min-width:0;max-width:230px}.neal-user-identity strong{font-size:11px;color:#475467;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.neal-user-identity small{font-size:9px;color:#98a2b3;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:230px;margin-top:2px}
.neal-user-logout{color:#b42318!important}
@media(max-width:800px){.neal-user-nav{overflow-x:auto;padding:7px 12px}.neal-user-dropdown{position:fixed;right:12px;top:62px}}
.neal-chat-unread-badge{display:inline-flex;align-items:center;justify-content:center;min-width:19px;height:19px;padding:0 5px;margin-right:5px;border-radius:999px;background:#e02424;color:#fff;font-family:inherit;font-size:10px;font-weight:800;line-height:1;font-variant-numeric:tabular-nums;box-shadow:0 0 0 2px #fff;vertical-align:middle}.neal-chat-unread-badge[hidden]{display:none!important}.neal-chat-link{display:inline-flex!important;align-items:center}
</style>
<a class="neal-user-brand" href="portal.php">NEAL <small>پورتال کارکنان</small></a>
<a href="portal.php">خانه</a>
<a class="neal-chat-link" href="chat.php">💬 پیام‌رسان همکاران<span class="neal-chat-unread-badge" data-unread-chat-badge aria-label="پیام خوانده‌نشده" <?php echo $umChatUnread > 0 ? "" : "hidden"; ?>><?php echo $umChatUnread > 99 ? "99+" : $umChatUnread; ?></span></a>
<div class="neal-user-group">
<button type="button">خدمات⌄</button>
<div class="neal-user-dropdown">
<div class="neal-user-section">خدمات فناوری اطلاعات</div>
<a href="service-request.php">🛠 درخواست جدید</a>
<a href="my-tickets.php">📋 درخواست‌های من<?php if($umcount>0): ?> (<?php echo $umcount; ?>)<?php endif; ?></a>
</div>
</div>
<div class="neal-user-group">
<button type="button">اطلاع‌رسانی⌄</button>
<div class="neal-user-dropdown">
<div class="neal-user-section">اطلاع‌رسانی شرکت</div>
<a href="announcements-list.php">📢 اطلاعیه‌های شرکت</a>
<a href="announcements-list.php">📅 مناسبت‌ها و رویدادها</a>
</div>
</div>
<div class="neal-user-group">
<button type="button">حساب کاربری⌄</button>
<div class="neal-user-dropdown">
<div class="neal-user-section">پروفایل و حساب</div>
<a href="profile.php">👤 پروفایل من</a>
<a class="neal-user-logout" href="/neal/logout.php">↪ خروج از حساب</a>
</div>
</div>
<div class="neal-user-profile">
<div class="neal-user-avatar-wrap"><?php if(trim($um['user_status']??'')!==''): ?><span class="neal-user-status"><?php echo htmlspecialchars($um['user_status'],ENT_QUOTES,'UTF-8'); ?></span><?php endif; ?><img src="<?php echo htmlspecialchars($umav,ENT_QUOTES,'UTF-8'); ?>" alt=""></div>
<div class="neal-user-identity"><strong><?php echo htmlspecialchars($um['fullname'],ENT_QUOTES,'UTF-8'); ?></strong><?php if(trim($um['user_status']??'')!==''): ?><small><?php echo htmlspecialchars($um['user_status'],ENT_QUOTES,'UTF-8'); ?></small><?php endif; ?></div>
</div>
</nav>
<script>
(function(){
    var badge=document.querySelector('[data-unread-chat-badge]');
    if(!badge)return;
    function refreshUnread(){
        fetch('/neal/chat-unread.php',{credentials:'same-origin',cache:'no-store',headers:{'Accept':'application/json'}})
        .then(function(response){if(!response.ok)throw new Error('Unread count unavailable');return response.json();})
        .then(function(data){
            if(!data || !data.ok)return;
            var count=Number(data.unread_count)||0;
            badge.textContent=count>99?'99+':String(count);
            badge.hidden=count<1;
            badge.setAttribute('aria-label',count+' پیام خوانده‌نشده');
        }).catch(function(){});
    }
    refreshUnread();
    window.setInterval(refreshUnread,10000);
})();
</script>