<?php
$portal_mode=true;
require_once "auth/auth.php";
check_user_login();
require_once "includes/user-profile.php";
$page_title="پروفایل من";
$db=neal_profile_db();
$username=$_SESSION['user_username']??'';
$user=neal_get_user_profile($db,$username);
if(!$user){http_response_code(404);exit('کاربر یافت نشد.');}
$msg=''; $type='';
$valid=['default'];
foreach(['male','female'] as $g) for($i=1;$i<=10;$i++) $valid[]=$g.'_'.str_pad((string)$i,2,'0',STR_PAD_LEFT);
if($_SERVER['REQUEST_METHOD']==='POST'){
  $a=trim($_POST['avatar']??'');
  if(!in_array($a,$valid,true)){ $msg='آواتار انتخاب‌شده معتبر نیست.';$type='error'; }
  else { $s=$db->prepare("UPDATE users SET avatar=? WHERE id=?");$s->execute([$a,$user['id']]);$user['avatar']=$a;$msg='آواتار پروفایل با موفقیت تغییر کرد.';$type='success'; }
}
$q=$db->prepare("SELECT COUNT(*) total,SUM(CASE WHEN status IN ('assigned','in_progress') THEN 1 ELSE 0 END) active,SUM(CASE WHEN status='waiting_user' THEN 1 ELSE 0 END) waiting,SUM(CASE WHEN status='closed' THEN 1 ELSE 0 END) closed FROM service_requests WHERE requester_username=?");
$q->execute([$username]);$st=$q->fetch();
function ph($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
$av=neal_avatar_url($user['avatar']);
?>
<?php include "includes/header.php"; ?>
<div class="profile-page">
<div class="profile-hero"><div><div class="profile-eyebrow"><span class="profile-eyebrow-dot"></span>حساب کاربری</div><h1>پروفایل من</h1><p>اطلاعات حساب و تصویر پروفایل خود را مدیریت کنید.</p></div><a href="portal.php" class="profile-back-link">بازگشت به خانه <svg viewBox="0 0 24 24"><path d="M5 12h14"/><path d="m13 6 6 6-6 6"/></svg></a></div>
<?php if($msg): ?><div class="profile-message profile-message-<?php echo ph($type); ?>"><svg viewBox="0 0 24 24"><path d="m5 12 4 4L19 6"/></svg><?php echo ph($msg); ?></div><?php endif; ?>
<div class="profile-layout">
<section class="profile-card profile-card-main">
<div class="profile-card-heading"><div><h2>انتخاب آواتار</h2><p>تصویر مورد نظر خود را برای حساب کاربری انتخاب کنید.</p></div><span class="profile-pill">۲۱ انتخاب</span></div>
<div class="profile-current"><div class="profile-current-avatar"><img id="profilePreview" src="<?php echo ph($av); ?>" alt=""><span class="profile-avatar-status"></span></div><div class="profile-current-info"><strong><?php echo ph($user['fullname']); ?></strong><span>@<?php echo ph($user['username']); ?></span><small>روی آواتار مورد علاقه کلیک کنید و سپس ذخیره را بزنید.</small></div></div>
<form method="post" id="avatarForm"><input type="hidden" name="avatar" id="selectedAvatar" value="<?php echo ph($user['avatar']); ?>">
<div class="avatar-group"><div class="avatar-group-title"><span class="avatar-group-icon"><svg viewBox="0 0 24 24"><circle cx="12" cy="7" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/></svg></span>تصویرهای پروفایل<small>۲۰ انتخاب</small></div><div class="avatar-grid">
<?php foreach(['male','female'] as $g) for($i=1;$i<=10;$i++): $id=$g.'_'.str_pad((string)$i,2,'0',STR_PAD_LEFT); ?>
<button type="button" class="avatar-option <?php echo $user['avatar']===$id?'is-selected':''; ?>" data-avatar="<?php echo $id; ?>"><img src="<?php echo ph(neal_avatar_url($id)); ?>" alt=""><span class="avatar-check"><svg viewBox="0 0 24 24"><path d="m5 12 4 4L19 6"/></svg></span></button>
<?php endfor; ?></div></div>
<div class="avatar-default-row"><button type="button" class="avatar-default-option <?php echo $user['avatar']==='default'?'is-selected':''; ?>" data-avatar="default"><img src="images/avatars/default.svg" alt=""><span>تصویر پیش‌فرض<small>خنثی</small></span><span class="avatar-check"><svg viewBox="0 0 24 24"><path d="m5 12 4 4L19 6"/></svg></span></button></div>
<div class="profile-save-row"><div class="profile-selection-note">تغییرات پس از ذخیره اعمال می‌شود.</div><button class="profile-save-button" type="submit"><svg viewBox="0 0 24 24"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2Z"/><path d="M17 21v-6H7v6"/></svg>ذخیره آواتار</button></div>
</form></section>
<aside class="profile-side-column">
<section class="profile-card profile-identity-card"><div class="identity-avatar"><img id="identityPreview" src="<?php echo ph($av); ?>" alt=""></div><div class="identity-name"><?php echo ph($user['fullname']); ?></div><div class="identity-username">@<?php echo ph($user['username']); ?></div><div class="identity-status"><span></span>حساب فعال</div><div class="identity-details"><div><span>کد پرسنلی</span><strong><?php echo ph($user['personnel_code']?:'—'); ?></strong></div><div><span>واحد سازمانی</span><strong><?php echo ph($user['department']); ?></strong></div><div><span>شماره تماس</span><strong><?php echo ph($user['phone']?:'—'); ?></strong></div></div></section>
<section class="profile-card profile-stats-card"><div class="profile-card-heading compact"><div><h2>فعالیت من</h2><p>خلاصه درخواست‌های شما</p></div></div><div class="profile-stat-list">
<?php foreach([['کل درخواست‌ها','total','stat-blue'],['در حال بررسی','active','stat-green'],['منتظر پاسخ','waiting','stat-orange'],['بسته‌شده','closed','stat-purple']] as $x): ?><a href="my-tickets.php"><span class="profile-stat-icon <?php echo $x[2]; ?>"><svg viewBox="0 0 24 24"><path d="M5 4h14a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2Z"/></svg></span><span class="profile-stat-label"><?php echo $x[0]; ?></span><strong><?php echo (int)($st[$x[1]]??0); ?></strong></a><?php endforeach; ?>
</div></section></aside></div></div>
<script>
document.querySelectorAll('[data-avatar]').forEach(function(b){b.addEventListener('click',function(){var v=this.dataset.avatar;document.getElementById('selectedAvatar').value=v;document.querySelectorAll('[data-avatar]').forEach(function(x){x.classList.remove('is-selected')});this.classList.add('is-selected');var src=v==='default'?'images/avatars/default.svg':'images/avatars/'+v+'.svg';document.getElementById('profilePreview').src=src;document.getElementById('identityPreview').src=src;});});
</script>
<?php include "includes/footer.php"; ?>