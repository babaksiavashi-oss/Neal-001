<?php
require_once __DIR__ . "/user-profile.php";
$umdb=neal_profile_db(); $umname=$_SESSION['user_username']??'';
$um=neal_get_user_profile($umdb,$umname);
if(!$um){$um=['fullname'=>$_SESSION['user_fullname']??$umname,'username'=>$umname,'personnel_code'=>'','department'=>'','avatar'=>'default'];}
$umav=neal_avatar_url($um['avatar']);
$s=$umdb->prepare("SELECT COUNT(*) FROM service_requests WHERE requester_username=?");$s->execute([$umname]);$umcount=(int)$s->fetchColumn();
?>
<style>
.neal-user-nav{direction:rtl;display:flex;align-items:center;gap:6px;padding:9px 18px;background:#fff;border-bottom:1px solid #e8ebf0;position:relative;z-index:1000;font-family:Vazirmatn,Tahoma,sans-serif}
.neal-user-nav a{text-decoration:none;color:#344054;font-size:14px;padding:10px 13px;border-radius:10px;white-space:nowrap}
.neal-user-nav a:hover{background:#f4f6f8;color:#111827}
.neal-user-brand{font-weight:700;color:#172033!important;margin-left:10px}
.neal-user-group{position:relative}
.neal-user-group>button{border:0;background:transparent;color:#344054;font:inherit;font-size:14px;padding:10px 13px;border-radius:10px;cursor:pointer}
.neal-user-group:hover>button{background:#f4f6f8}
.neal-user-dropdown{position:absolute;right:0;top:calc(100% + 7px);min-width:225px;background:#fff;border:1px solid #e7e9ee;border-radius:14px;box-shadow:0 14px 35px rgba(16,24,40,.12);padding:8px;opacity:0;visibility:hidden;transform:translateY(-5px);transition:.16s ease}
.neal-user-group:hover .neal-user-dropdown,.neal-user-group:focus-within .neal-user-dropdown{opacity:1;visibility:visible;transform:translateY(0)}
.neal-user-dropdown a{display:block;padding:11px 12px}
.neal-user-profile{margin-right:auto;display:flex;align-items:center;gap:9px;padding:4px 8px}
.neal-user-profile img{width:36px;height:36px;border-radius:50%;object-fit:cover;border:2px solid #eef1f5}
.neal-user-profile span{font-size:12px;color:#667085}
.neal-user-logout{color:#b42318!important}
@media(max-width:800px){.neal-user-nav{overflow-x:auto}.neal-user-dropdown{position:fixed;right:12px;top:62px}}
</style>
<nav class="neal-user-nav">
<a class="neal-user-brand" href="portal.php">NEAL</a>
<a href="portal.php">خانه</a>
<div class="neal-user-group">
<button type="button">خدمات⌄</button>
<div class="neal-user-dropdown">
<a href="service-request.php">🛠 درخواست جدید</a>
<a href="my-tickets.php">📋 درخواست‌های من<?php if($umcount>0): ?> (<?php echo $umcount; ?>)<?php endif; ?></a>
</div>
</div>
<div class="neal-user-group">
<button type="button">اطلاع‌رسانی⌄</button>
<div class="neal-user-dropdown">
<a href="announcements-list.php">📢 اطلاعیه‌های شرکت</a>
<a href="announcements-list.php">📅 مناسبت‌ها و اطلاعیه‌ها</a>
</div>
</div>
<div class="neal-user-group">
<button type="button">حساب کاربری⌄</button>
<div class="neal-user-dropdown">
<a href="profile.php">👤 پروفایل من</a>
<a class="neal-user-logout" href="/neal/logout.php">↪ خروج از حساب</a>
</div>
</div>
<div class="neal-user-profile">
<img src="<?php echo htmlspecialchars($umav,ENT_QUOTES,'UTF-8'); ?>" alt="">
<span><?php echo htmlspecialchars($um['fullname'],ENT_QUOTES,'UTF-8'); ?></span>
</div>
</nav>