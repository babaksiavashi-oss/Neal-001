<?php
require_once __DIR__ . "/user-profile.php";
$umdb=neal_profile_db(); $umname=$_SESSION['user_username']??'';
$um=neal_get_user_profile($umdb,$umname);
if(!$um){$um=['fullname'=>$_SESSION['user_fullname']??$umname,'username'=>$umname,'personnel_code'=>'','department'=>'','avatar'=>'default'];}
$umav=neal_avatar_url($um['avatar']);
$s=$umdb->prepare("SELECT COUNT(*) FROM service_requests WHERE requester_username=?");$s->execute([$umname]);$umcount=(int)$s->fetchColumn();
?>
<div class="sidebar user-sidebar">
<div class="user-sidebar-profile">
<div class="user-sidebar-avatar-wrap"><img src="<?php echo htmlspecialchars($umav,ENT_QUOTES,'UTF-8'); ?>" class="user-sidebar-avatar" alt=""><span class="user-sidebar-online"></span></div>
<div class="user-sidebar-name"><?php echo htmlspecialchars($um['fullname'],ENT_QUOTES,'UTF-8'); ?></div>
<div class="user-sidebar-username">@<?php echo htmlspecialchars($um['username'],ENT_QUOTES,'UTF-8'); ?></div>
<div class="user-sidebar-meta">
<?php if($um['personnel_code']!==''): ?><span><svg viewBox="0 0 24 24"><circle cx="9.5" cy="7" r="3.5"/><path d="M3 21v-1a4 4 0 0 1 4-4h5a4 4 0 0 1 4 4v1"/></svg><?php echo htmlspecialchars($um['personnel_code'],ENT_QUOTES,'UTF-8'); ?></span><?php endif; ?>
<?php if($um['department']!==''): ?><span><svg viewBox="0 0 24 24"><path d="M3 21h18M5 21V5l7-3 7 3v16"/></svg><?php echo htmlspecialchars($um['department'],ENT_QUOTES,'UTF-8'); ?></span><?php endif; ?>
</div>
<div class="user-sidebar-ticket-count"><div><strong><?php echo $umcount; ?></strong><span>درخواست ثبت‌شده</span></div><a href="profile.php" title="پروفایل من"><svg viewBox="0 0 24 24"><circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/></svg></a></div>
</div>
<nav class="user-sidebar-nav">
<a href="portal.php"><svg viewBox="0 0 24 24"><path d="m3 10 9-7 9 7M5 9v11h14V9M9 20v-6h6v6"/></svg><span>خانه</span></a>
<a href="service-request.php"><svg viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/><rect x="3" y="3" width="18" height="18" rx="4"/></svg><span>درخواست جدید</span></a>
<a href="my-tickets.php"><svg viewBox="0 0 24 24"><path d="M5 4h14a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2Z"/><path d="M8 9h8M8 13h6"/></svg><span>درخواست‌های من</span><?php if($umcount>0): ?><b><?php echo $umcount; ?></b><?php endif; ?></a>
<a href="profile.php"><svg viewBox="0 0 24 24"><circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/></svg><span>پروفایل من</span></a>
</nav>
<div class="user-sidebar-divider"></div>
<a href="/neal/logout.php" class="user-sidebar-logout"><svg viewBox="0 0 24 24"><path d="M10 17l5-5-5-5M15 12H3M21 19V5a2 2 0 0 0-2-2h-5"/></svg><span>خروج از حساب</span></a>
</div>