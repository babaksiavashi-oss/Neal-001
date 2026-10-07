<?php
$portal_mode = true;
require_once "auth/auth.php";
check_user_login();
require_once "includes/user-profile.php";

$page_title = "پروفایل من";
$db = neal_profile_db();
$username = $_SESSION['user_username'] ?? '';
$user = neal_get_user_profile($db, $username);

if (!$user) {
    http_response_code(404);
    exit('کاربر یافت نشد.');
}

if (empty($_SESSION['profile_csrf'])) {
    $_SESSION['profile_csrf'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['profile_csrf'];

$msg = '';
$type = '';

$valid = neal_allowed_builtin_avatars();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($csrf, (string)($_POST['csrf'] ?? ''))) {
        $msg = 'درخواست نامعتبر است. صفحه را تازه‌سازی کنید.';
        $type = 'error';
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'save_status') {
            $status = trim((string)($_POST['user_status'] ?? ''));
            if (mb_strlen($status, 'UTF-8') > 120) {
                $msg = 'وضعیت شما حداکثر می‌تواند ۱۲۰ کاراکتر باشد.';
                $type = 'error';
            } else {
                $s = $db->prepare("UPDATE users SET user_status=? WHERE id=?");
                $s->execute([$status, $user['id']]);
                $user['user_status'] = $status;
                $msg = 'وضعیت شما با موفقیت ذخیره شد.';
                $type = 'success';
            }

        } elseif ($action === 'upload_avatar') {
            if (!isset($_FILES['avatar_file']) || $_FILES['avatar_file']['error'] !== UPLOAD_ERR_OK) {
                $msg = 'فایل تصویر دریافت نشد.';
                $type = 'error';
            } elseif ((int)$_FILES['avatar_file']['size'] > 5 * 1024 * 1024) {
                $msg = 'حجم تصویر نباید بیشتر از ۵ مگابایت باشد.';
                $type = 'error';
            } elseif (!is_uploaded_file($_FILES['avatar_file']['tmp_name'])) {
                $msg = 'فایل آپلودشده معتبر نیست.';
                $type = 'error';
            } else {
                $info = @getimagesize($_FILES['avatar_file']['tmp_name']);
                $mime = $info['mime'] ?? '';
                $allowedMimes = [
                    'image/jpeg' => 'jpg',
                    'image/png' => 'png'
                ];

                if (!$info || !isset($allowedMimes[$mime])) {
                    $msg = 'فقط تصویر JPG/JPEG یا PNG قابل قبول است.';
                    $type = 'error';
                } elseif ((int)$info[0] < 80 || (int)$info[1] < 80) {
                    $msg = 'اندازه تصویر باید حداقل ۸۰×۸۰ پیکسل باشد.';
                    $type = 'error';
                } else {
                    $dir = __DIR__ . '/images/avatars';
                    if (!is_dir($dir) && !@mkdir($dir, 0750, true)) {
                        $msg = 'پوشه ذخیره تصویر قابل ایجاد نیست.';
                        $type = 'error';
                    } else {
                        try {
                            $token = bin2hex(random_bytes(16));
                            $filename = 'custom_' . $token . '.' . $allowedMimes[$mime];
                            $destination = $dir . '/' . $filename;

                            if (!move_uploaded_file($_FILES['avatar_file']['tmp_name'], $destination)) {
                                throw new RuntimeException('upload failed');
                            }

                            @chmod($destination, 0640);

                            $oldAvatar = $user['avatar'];
                            $s = $db->prepare("UPDATE users SET avatar=? WHERE id=?");
                            $s->execute([$filename, $user['id']]);

                            if (neal_is_custom_avatar($oldAvatar)) {
                                neal_delete_custom_avatar($oldAvatar);
                            }

                            $user['avatar'] = $filename;
                            $msg = 'تصویر پروفایل با موفقیت آپلود و فعال شد.';
                            $type = 'success';
                        } catch (Throwable $e) {
                            if (isset($destination) && is_file($destination)) @unlink($destination);
                            $msg = 'آپلود تصویر انجام نشد. لطفاً دوباره تلاش کنید.';
                            $type = 'error';
                        }
                    }
                }
            }

        } elseif ($action === 'remove_avatar') {
            $oldAvatar = $user['avatar'];
            if (neal_is_custom_avatar($oldAvatar)) {
                neal_delete_custom_avatar($oldAvatar);
            }

            $s = $db->prepare("UPDATE users SET avatar='default' WHERE id=?");
            $s->execute([$user['id']]);
            $user['avatar'] = 'default';
            $msg = 'تصویر اختصاصی حذف شد و تصویر پیش‌فرض فعال شد.';
            $type = 'success';

        } elseif ($action === 'save_avatar') {
            $a = trim((string)($_POST['avatar'] ?? ''));
            if (!in_array($a, $valid, true)) {
                $msg = 'آواتار انتخاب‌شده معتبر نیست.';
                $type = 'error';
            } else {
                $oldAvatar = $user['avatar'];
                $s = $db->prepare("UPDATE users SET avatar=? WHERE id=?");
                $s->execute([$a, $user['id']]);

                if (neal_is_custom_avatar($oldAvatar)) {
                    neal_delete_custom_avatar($oldAvatar);
                }

                $user['avatar'] = $a;
                $msg = 'آواتار پروفایل با موفقیت تغییر کرد.';
                $type = 'success';
            }
        }
    }
}

$q = $db->prepare("SELECT COUNT(*) total,
    SUM(CASE WHEN status IN ('assigned','in_progress') THEN 1 ELSE 0 END) active,
    SUM(CASE WHEN status='waiting_user' THEN 1 ELSE 0 END) waiting,
    SUM(CASE WHEN status='closed' THEN 1 ELSE 0 END) closed
    FROM service_requests WHERE requester_username=?");
$q->execute([$username]);
$st = $q->fetch();

function ph($v) {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

$av = neal_avatar_url($user['avatar']);
$isCustom = neal_is_custom_avatar($user['avatar']);
?>
<?php include "includes/header.php"; ?>

<style>
.profile-extra-card{margin-top:18px}
.profile-extra-card form{margin:0}
.profile-extra-heading{display:flex;align-items:center;justify-content:space-between;gap:15px;margin-bottom:14px}
.profile-extra-heading h2{margin:0;font-size:18px}
.profile-extra-heading p{margin:5px 0 0;color:#7b8494;font-size:13px}
.profile-status-form{display:flex;gap:12px;align-items:flex-end}
.profile-status-field{flex:1}
.profile-status-field textarea{width:100%;min-height:74px;resize:vertical;border:1px solid #dfe3ea;border-radius:12px;padding:11px 13px;font-family:inherit;font-size:14px;box-sizing:border-box}
.profile-upload-row{display:flex;align-items:center;gap:12px;flex-wrap:wrap}
.profile-file-label,.profile-action-button{display:inline-flex;align-items:center;justify-content:center;gap:7px;border:0;border-radius:10px;padding:10px 15px;font-family:inherit;font-size:13px;cursor:pointer;text-decoration:none}
.profile-file-label{background:#f2f4f7;color:#293241}
.profile-action-button{background:#1f2937;color:#fff}
.profile-action-button.danger{background:#fff0f0;color:#b42318}
.profile-file-label input{display:none}
.profile-upload-note{font-size:12px;color:#8992a2}
.profile-status-above-avatar{margin-bottom:10px;text-align:center;color:#5e6878;font-size:12px;max-width:180px;margin-left:auto;margin-right:auto;line-height:1.7}
.profile-status-above-avatar strong{display:block;color:#303846;font-size:13px;margin-bottom:2px}
@media(max-width:700px){.profile-status-form{display:block}.profile-status-form button{margin-top:10px}.profile-upload-row{align-items:flex-start}}
</style>

<div class="profile-page">
<div class="profile-hero">
<div>
<div class="profile-eyebrow"><span class="profile-eyebrow-dot"></span>حساب کاربری</div>
<h1>پروفایل من</h1>
<p>اطلاعات حساب، وضعیت و تصویر پروفایل خود را مدیریت کنید.</p>
</div>
<a href="portal.php" class="profile-back-link">بازگشت به خانه <svg viewBox="0 0 24 24"><path d="M5 12h14"/><path d="m13 6 6 6-6 6"/></svg></a>
</div>

<?php if($msg): ?>
<div class="profile-message profile-message-<?php echo ph($type); ?>">
<svg viewBox="0 0 24 24"><path d="m5 12 4 4L19 6"/></svg>
<?php echo ph($msg); ?>
</div>
<?php endif; ?>

<div class="profile-layout">

<section class="profile-card profile-card-main">
<div class="profile-card-heading">
<div><h2>انتخاب آواتار</h2><p>تصویر مورد نظر خود را برای حساب کاربری انتخاب کنید.</p></div>
<span class="profile-pill">۲۱ انتخاب</span>
</div>

<div class="profile-current">
<div class="profile-current-avatar">
<img id="profilePreview" src="<?php echo ph($av); ?>" alt="">
<span class="profile-avatar-status"></span>
</div>
<div class="profile-current-info">
<strong><?php echo ph($user['fullname']); ?></strong>
<span>@<?php echo ph($user['username']); ?></span>
<small><?php echo $user['user_status'] !== '' ? ph($user['user_status']) : 'هنوز وضعیتی برای خود ثبت نکرده‌اید.'; ?></small>
</div>
</div>

<form method="post" id="avatarForm">
<input type="hidden" name="csrf" value="<?php echo ph($csrf); ?>">
<input type="hidden" name="action" value="save_avatar">
<input type="hidden" name="avatar" id="selectedAvatar" value="<?php echo ph($user['avatar']); ?>">

<div class="avatar-group">
<div class="avatar-group-title"><span class="avatar-group-icon"><svg viewBox="0 0 24 24"><circle cx="12" cy="7" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/></svg></span>تصویرهای پروفایل<small>۲۰ انتخاب</small></div>
<div class="avatar-grid">
<?php foreach(['male','female'] as $g) for($i=1;$i<=10;$i++): $id=$g.'_'.str_pad((string)$i,2,'0',STR_PAD_LEFT); ?>
<button type="button" class="avatar-option <?php echo $user['avatar']===$id?'is-selected':''; ?>" data-avatar="<?php echo $id; ?>"><img src="<?php echo ph(neal_avatar_url($id)); ?>" alt=""><span class="avatar-check"><svg viewBox="0 0 24 24"><path d="m5 12 4 4L19 6"/></svg></span></button>
<?php endfor; ?>
</div>
</div>

<div class="avatar-default-row">
<button type="button" class="avatar-default-option <?php echo $user['avatar']==='default'?'is-selected':''; ?>" data-avatar="default">
<img src="images/avatars/default.svg" alt=""><span>تصویر پیش‌فرض<small>خنثی</small></span>
<span class="avatar-check"><svg viewBox="0 0 24 24"><path d="m5 12 4 4L19 6"/></svg></span>
</button>
</div>

<div class="profile-save-row">
<div class="profile-selection-note">تغییرات پس از ذخیره اعمال می‌شود.</div>
<button class="profile-save-button" type="submit"><svg viewBox="0 0 24 24"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2Z"/><path d="M17 21v-6H7v6"/></svg>ذخیره آواتار</button>
</div>
</form>

<section class="profile-card profile-extra-card">
<div class="profile-extra-heading">
<div><h2>وضعیت من</h2><p>این متن در کنار نام شما و بالای آواتار نمایش داده می‌شود.</p></div>
</div>
<form method="post" class="profile-status-form">
<input type="hidden" name="csrf" value="<?php echo ph($csrf); ?>">
<input type="hidden" name="action" value="save_status">
<div class="profile-status-field">
<textarea name="user_status" maxlength="120" placeholder="مثلاً: در جلسه هستم، بعداً پاسخ می‌دهم..."><?php echo ph($user['user_status']); ?></textarea>
</div>
<button class="profile-action-button" type="submit">ذخیره وضعیت</button>
</form>
</section>

<section class="profile-card profile-extra-card">
<div class="profile-extra-heading">
<div><h2>تصویر اختصاصی</h2><p>JPG/JPEG یا PNG، حداکثر ۵ مگابایت و حداقل ۸۰×۸۰ پیکسل.</p></div>
</div>
<form method="post" enctype="multipart/form-data">
<input type="hidden" name="csrf" value="<?php echo ph($csrf); ?>">
<input type="hidden" name="action" value="upload_avatar">
<div class="profile-upload-row">
<label class="profile-file-label">انتخاب تصویر
<input type="file" name="avatar_file" accept=".jpg,.jpeg,.png,image/jpeg,image/png" required>
</label>
<button class="profile-action-button" type="submit">آپلود و فعال‌سازی</button>
<?php if($isCustom): ?>
</form>
<form method="post">
<input type="hidden" name="csrf" value="<?php echo ph($csrf); ?>">
<input type="hidden" name="action" value="remove_avatar">
<button class="profile-action-button danger" type="submit">حذف تصویر اختصاصی</button>
<?php endif; ?>
</form>
<span class="profile-upload-note">تصویر فعلی شما بلافاصله پس از آپلود جایگزین می‌شود.</span>
</div>
</form>
</section>

</section>

<aside class="profile-side-column">
<section class="profile-card profile-identity-card">
<?php if($user['user_status']!==''): ?>
<div class="profile-status-above-avatar"><strong>وضعیت</strong><?php echo ph($user['user_status']); ?></div>
<?php endif; ?>
<div class="identity-avatar"><img id="identityPreview" src="<?php echo ph($av); ?>" alt=""></div>
<div class="identity-name"><?php echo ph($user['fullname']); ?></div>
<div class="identity-username">@<?php echo ph($user['username']); ?></div>
<div class="identity-status"><span></span>حساب فعال</div>
<div class="identity-details">
<div><span>کد پرسنلی</span><strong><?php echo ph($user['personnel_code']?:'—'); ?></strong></div>
<div><span>واحد سازمانی</span><strong><?php echo ph($user['department']); ?></strong></div>
<div><span>شماره تماس</span><strong><?php echo ph($user['phone']?:'—'); ?></strong></div>
</div>
</section>

<section class="profile-card profile-stats-card">
<div class="profile-card-heading compact"><div><h2>فعالیت من</h2><p>خلاصه درخواست‌های شما</p></div></div>
<div class="profile-stat-list">
<?php foreach([['کل درخواست‌ها','total','stat-blue'],['در حال بررسی','active','stat-green'],['منتظر پاسخ','waiting','stat-orange'],['بسته‌شده','closed','stat-purple']] as $x): ?>
<a href="my-tickets.php"><span class="profile-stat-icon <?php echo $x[2]; ?>"><svg viewBox="0 0 24 24"><path d="M5 4h14a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2Z"/></svg></span><span class="profile-stat-label"><?php echo $x[0]; ?></span><strong><?php echo (int)($st[$x[1]]??0); ?></strong></a>
<?php endforeach; ?>
</div>
</section>
</aside>

</div>
</div>

<script>
document.querySelectorAll('[data-avatar]').forEach(function(b){
  b.addEventListener('click',function(){
    var v=this.dataset.avatar;
    document.getElementById('selectedAvatar').value=v;
    document.querySelectorAll('[data-avatar]').forEach(function(x){x.classList.remove('is-selected')});
    this.classList.add('is-selected');
    var src=v==='default'?'images/avatars/default.svg':'images/avatars/'+v+'.svg';
    document.getElementById('profilePreview').src=src;
    document.getElementById('identityPreview').src=src;
  });
});
</script>
<?php include "includes/footer.php"; ?>