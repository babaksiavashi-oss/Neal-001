<?php
$portal_mode=true;
require_once "auth/auth.php";
check_user_login();
require_once "includes/user-profile.php";
$page_title="پرتال خدمات فناوری اطلاعات";
$db=neal_profile_db();$username=$_SESSION['user_username']??'';
$user=neal_get_user_profile($db,$username);if(!$user){http_response_code(404);exit('کاربر یافت نشد.');}
$q=$db->prepare("SELECT COUNT(*) total,SUM(CASE WHEN status IN ('assigned','in_progress') THEN 1 ELSE 0 END) active,SUM(CASE WHEN status='waiting_user' THEN 1 ELSE 0 END) waiting,SUM(CASE WHEN status='closed' THEN 1 ELSE 0 END) closed FROM service_requests WHERE requester_username=?");$q->execute([$username]);$stats=$q->fetch();
function porth($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
$avatar=neal_avatar_url($user['avatar']);
?>
<?php include "includes/header.php"; ?>
<div class="user-portal-modern">
<section class="portal-hero-modern">
<div class="portal-hero-copy"><div class="portal-hero-eyebrow"><span></span>سامانه خدمات فناوری اطلاعات</div>
<h1>سلام، <?php echo porth($user['fullname']); ?> 👋</h1>
<p>همه چیز برای مدیریت و پیگیری درخواست‌های فناوری اطلاعات شما آماده است.</p>
<div class="portal-hero-actions"><a href="service-request.php" class="portal-hero-primary"><svg viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg>ثبت درخواست جدید</a><a href="my-tickets.php" class="portal-hero-secondary"><svg viewBox="0 0 24 24"><path d="M5 4h14a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2Z"/><path d="M8 9h8M8 13h6"/></svg>مشاهده درخواست‌ها</a></div></div>
<a href="profile.php" class="portal-hero-profile"><div class="portal-hero-avatar"><img src="<?php echo porth($avatar); ?>" alt=""><span></span></div><div><strong><?php echo porth($user['fullname']); ?></strong><small><?php echo porth($user['department']); ?></small><em>ویرایش پروفایل <svg viewBox="0 0 24 24"><path d="m9 18 6-6-6-6"/></svg></em></div></a>
</section>
<section class="portal-metrics">
<?php foreach([['total','کل درخواست‌ها','portal-metric-blue'],['active','در حال بررسی','portal-metric-green'],['waiting','منتظر پاسخ شما','portal-metric-orange'],['closed','بسته‌شده','portal-metric-purple']] as $m): ?>
<a href="my-tickets.php" class="portal-metric <?php echo $m[2]; ?>"><span class="portal-metric-icon"><svg viewBox="0 0 24 24"><path d="M5 4h14a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2Z"/><path d="M8 9h8M8 13h6"/></svg></span><span><small><?php echo $m[1]; ?></small><strong><?php echo (int)($stats[$m[0]]??0); ?></strong></span><svg class="portal-metric-arrow" viewBox="0 0 24 24"><path d="m9 18 6-6-6-6"/></svg></a>
<?php endforeach; ?>
</section>
<section class="portal-content-grid"><div class="portal-main-column"><div class="portal-section-modern">
<div class="portal-section-heading"><div><span class="portal-heading-kicker">خدمات پرکاربرد</span><h2>در چه زمینه‌ای به کمک نیاز دارید؟</h2></div><span class="portal-heading-badge">سریع و آسان</span></div>
<div class="portal-service-grid">
<?php $services=[['icon-blue','کامپیوتر و لپ‌تاپ','عیب‌یابی و مشکلات سیستم'],['icon-green','شبکه و اینترنت','اتصال و دسترسی شبکه'],['icon-orange','پرینتر و اسکنر','چاپ، اسکن و اتصال دستگاه'],['icon-purple','حساب و دسترسی','رمز، حساب و دسترسی سامانه‌ها'],['icon-cyan','ایمیل و نرم‌افزار','نصب، تنظیمات و مشکلات برنامه‌ها'],['icon-slate','سایر درخواست‌ها','هر مشکل دیگری که نیاز به IT دارد']];foreach($services as $s): ?>
<a href="service-request.php" class="portal-service-card"><span class="portal-service-icon <?php echo $s[0]; ?>"><svg viewBox="0 0 24 24"><rect x="4" y="4" width="16" height="16" rx="3"/><path d="M8 12h8M12 8v8"/></svg></span><strong><?php echo $s[1]; ?></strong><small><?php echo $s[2]; ?></small><svg class="portal-service-arrow" viewBox="0 0 24 24"><path d="m9 18 6-6-6-6"/></svg></a>
<?php endforeach; ?></div></div></div>
<aside class="portal-side-column"><div class="portal-side-card portal-side-tip"><div class="portal-side-card-icon"><svg viewBox="0 0 24 24"><path d="M9 18h6M10 22h4M8.5 15.5a7 7 0 1 1 7 0c-.8.5-1.5 1.3-1.5 2.5h-5c0-1.2-.7-2-1.5-2.5Z"/></svg></div><span>یک نکته کوچک</span><h3>اول درخواست را ثبت کنید</h3><p>کارشناسان فناوری اطلاعات پس از بررسی، پاسخ و آخرین وضعیت درخواست را در همین سامانه با شما به اشتراک می‌گذارند.</p></div>
<a href="profile.php" class="portal-side-profile"><div class="portal-side-profile-avatar"><img src="<?php echo porth($avatar); ?>" alt=""></div><div><small>پروفایل شما</small><strong>انتخاب آواتار و مشخصات</strong></div><svg viewBox="0 0 24 24"><path d="m9 18 6-6-6-6"/></svg></a></aside></section>
</div>
<?php include "includes/footer.php"; ?>