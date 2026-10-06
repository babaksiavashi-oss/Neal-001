<?php
$portal_mode=true;
require_once "auth/auth.php";
check_user_login();
require_once "includes/user-profile.php";

$page_title="پروفایل من";
$db=neal_profile_db();
$username=$_SESSION['user_username']??'';
$user=neal_get_user_profile($db,$username);

if(!$user){ http_response_code(404); exit('کاربر یافت نشد.'); }

if (empty($_SESSION['neal_profile_csrf'])) {
    $_SESSION['neal_profile_csrf'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['neal_profile_csrf'];

function ph($v){ return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8'); }

$msg=''; $type='';
$valid=neal_allowed_builtin_avatars();

function profile_csrf_ok($token) {
    return isset($_SESSION['neal_profile_csrf']) &&
        is_string($token) &&
        hash_equals($_SESSION['neal_profile_csrf'], $token);
}

if($_SERVER['REQUEST_METHOD']==='POST'){
    if (!profile_csrf_ok($_POST['csrf_token'] ?? '')) {
        $msg='درخواست نامعتبر است. صفحه را تازه‌سازی کرده و دوباره تلاش کنید.';
        $type='error';
    } else {
        $action=$_POST['action']??'';

        if($action==='save_avatar'){
            $a=trim($_POST['avatar']??'');
            if(!in_array($a,$valid,true)){
                $msg='آواتار انتخاب‌شده معتبر نیست.';
                $type='error';
            } else {
                if (neal_is_custom_avatar($user['avatar'])) neal_delete_custom_avatar($user['avatar']);
                $s=$db->prepare("UPDATE users SET avatar=? WHERE id=?");
                $s->execute([$a,$user['id']]);
                $user['avatar']=$a;
                $msg='آواتار پروفایل با موفقیت تغییر کرد.';
                $type='success';
            }
        }

        elseif($action==='upload_avatar'){
            if(!isset($_FILES['avatar_file']) || !is_array($_FILES['avatar_file'])){
                $msg='تصویری برای بارگذاری انتخاب نشده است.';
                $type='error';
            } else {
                $file=$_FILES['avatar_file'];
                if(($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK){
                    $map=[
                        UPLOAD_ERR_INI_SIZE=>'حجم تصویر بیشتر از حد مجاز سرور است.',
                        UPLOAD_ERR_FORM_SIZE=>'حجم تصویر بیشتر از حد مجاز فرم است.',
                        UPLOAD_ERR_PARTIAL=>'بارگذاری تصویر کامل نشده است.',
                        UPLOAD_ERR_NO_FILE=>'تصویری انتخاب نشده است.'
                    ];
                    $msg=$map[$file['error']??-1]??'بارگذاری تصویر ناموفق بود.';
                    $type='error';
                } elseif((int)$file['size']>5*1024*1024){
                    $msg='حجم تصویر باید حداکثر ۵ مگابایت باشد.';
                    $type='error';
                } elseif(!is_uploaded_file($file['tmp_name'])){
                    $msg='فایل بارگذاری‌شده معتبر نیست.';
                    $type='error';
                } else {
                    $finfo=new finfo(FILEINFO_MIME_TYPE);
                    $mime=$finfo->file($file['tmp_name']);
                    $mimeMap=['image/jpeg'=>'jpg','image/png'=>'png'];
                    $imageInfo=@getimagesize($file['tmp_name']);

                    if(!isset($mimeMap[$mime]) || !$imageInfo){
                        $msg='فقط تصویر JPG، JPEG یا PNG قابل استفاده است.';
                        $type='error';
                    } elseif((int)($imageInfo[0]??0)<80 || (int)($imageInfo[1]??0)<80){
                        $msg='ابعاد تصویر برای آواتار بسیار کوچک است.';
                        $type='error';
                    } else {
                        $dir=__DIR__.'/images/uploads/avatars';
                        if(!is_dir($dir) && !@mkdir($dir,0750,true)){
                            $msg='پوشه ذخیره تصویر قابل ایجاد نیست.';
                            $type='error';
                        } else {
                            $filename=bin2hex(random_bytes(16)).'.'.$mimeMap[$mime];
                            $target=$dir.'/'.$filename;

                            if(!move_uploaded_file($file['tmp_name'],$target)){
                                $msg='ذخیره تصویر روی سرور ناموفق بود.';
                                $type='error';
                            } else {
                                @chmod($target,0640);
                                if(neal_is_custom_avatar($user['avatar'])) neal_delete_custom_avatar($user['avatar']);
                                $stored='custom:'.$filename;
                                $s=$db->prepare("UPDATE users SET avatar=? WHERE id=?");
                                $s->execute([$stored,$user['id']]);
                                $user['avatar']=$stored;
                                $msg='تصویر شخصی شما با موفقیت بارگذاری شد.';
                                $type='success';
                            }
                        }
                    }
                }
            }
        }

        elseif($action==='remove_custom_avatar'){
            if(neal_is_custom_avatar($user['avatar'])){
                neal_delete_custom_avatar($user['avatar']);
                $s=$db->prepare("UPDATE users SET avatar='default' WHERE id=?");
                $s->execute([$user['id']]);
                $user['avatar']='default';
                $msg='تصویر شخصی حذف شد و آواتار پیش‌فرض فعال شد.';
                $type='success';
            }
        }

        elseif($action==='save_message'){
            $message=trim((string)($_POST['login_message']??''));
            if($message===''){
                $s=$db->prepare("UPDATE users SET login_message='',login_message_status='none',login_message_rejection_reason='',login_message_submitted_at=NULL WHERE id=?");
                $s->execute([$user['id']]);
                $user['login_message']='';
                $user['login_message_status']='none';
                $user['login_message_rejection_reason']='';
                $msg='پیام عمومی شما حذف شد.';
                $type='success';
            } elseif(mb_strlen($message)>300){
                $msg='پیام عمومی حداکثر ۳۰۰ کاراکتر باشد.';
                $type='error';
            } else {
                $s=$db->prepare("UPDATE users SET login_message=?,login_message_status='pending',login_message_rejection_reason='',login_message_submitted_at=CURRENT_TIMESTAMP,login_message_reviewed_at=NULL,login_message_reviewed_by=NULL WHERE id=?");
                $s->execute([$message,$user['id']]);
                $user['login_message']=$message;
                $user['login_message_status']='pending';
                $user['login_message_rejection_reason']='';
                $msg='پیام شما ثبت شد و برای بررسی مدیر ارسال گردید.';
                $type='success';
            }
        }
    }
}

$q=$db->prepare("SELECT COUNT(*) total,SUM(CASE WHEN status IN ('assigned','in_progress') THEN 1 ELSE 0 END) active,SUM(CASE WHEN status='waiting_user' THEN 1 ELSE 0 END) waiting,SUM(CASE WHEN status='closed' THEN 1 ELSE 0 END) closed FROM service_requests WHERE requester_username=?");
$q->execute([$username]);
$st=$q->fetch() ?: ['total'=>0,'active'=>0,'waiting'=>0,'closed'=>0];

$av=neal_avatar_url($user['avatar']);
$isCustom=neal_is_custom_avatar($user['avatar']);
$statusLabels=[
    'none'=>['label'=>'هنوز پیامی ثبت نشده','class'=>'neutral'],
    'pending'=>['label'=>'در انتظار تأیید مدیر','class'=>'pending'],
    'approved'=>['label'=>'در صفحه ورود نمایش داده می‌شود','class'=>'approved'],
    'rejected'=>['label'=>'نیازمند اصلاح و ارسال مجدد','class'=>'rejected']
];
$messageStatus=$statusLabels[$user['login_message_status']]??$statusLabels['none'];
?>
<?php include "includes/header.php"; ?>

<div class="profile-page">
<div class="profile-hero">
    <div>
        <div class="profile-eyebrow"><span class="profile-eyebrow-dot"></span>حساب کاربری</div>
        <h1>پروفایل من</h1>
        <p>اطلاعات حساب، تصویر پروفایل و پیام عمومی خود را مدیریت کنید.</p>
    </div>
    <a href="portal.php" class="profile-back-link">بازگشت به خانه <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14"/><path d="m13 6 6 6-6 6"/></svg></a>
</div>

<?php if($msg): ?>
<div class="profile-message profile-message-<?php echo ph($type); ?>">
<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m5 12 4 4L19 6"/></svg>
<?php echo ph($msg); ?>
</div>
<?php endif; ?>

<div class="profile-layout">
<section class="profile-card profile-card-main">

<div class="profile-card-heading">
    <div><h2>تصویر پروفایل</h2><p>یکی از آواتارهای داخلی را انتخاب کنید یا تصویر شخصی خودتان را بارگذاری کنید.</p></div>
    <span class="profile-pill">۲۱ آواتار داخلی</span>
</div>

<div class="profile-current">
    <div class="profile-current-avatar">
        <img id="profilePreview" src="<?php echo ph($av); ?>" alt="">
        <span class="profile-avatar-status"></span>
    </div>
    <div class="profile-current-info">
        <strong><?php echo ph($user['fullname']); ?></strong>
        <span>@<?php echo ph($user['username']); ?></span>
        <small><?php echo $isCustom ? 'تصویر شخصی فعال است.' : 'آواتار داخلی فعال است.'; ?></small>
    </div>
</div>

<form method="post" id="avatarForm">
<input type="hidden" name="csrf_token" value="<?php echo ph($csrf); ?>">
<input type="hidden" name="action" value="save_avatar">
<input type="hidden" name="avatar" id="selectedAvatar" value="<?php echo ph(in_array($user['avatar'],$valid,true)?$user['avatar']:''); ?>">

<div class="avatar-group">
<div class="avatar-group-title">
    <span class="avatar-group-icon"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="7" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/></svg></span>
    آواتارهای داخلی
    <small>۲۱ انتخاب</small>
</div>

<div class="avatar-grid">
<?php foreach(['male','female'] as $g) for($i=1;$i<=10;$i++): $id=$g.'_'.str_pad((string)$i,2,'0',STR_PAD_LEFT); ?>
<button type="button" class="avatar-option <?php echo $user['avatar']===$id?'is-selected':''; ?>" data-avatar="<?php echo ph($id); ?>">
<img src="<?php echo ph(neal_avatar_url($id)); ?>" alt="">
<span class="avatar-check"><svg viewBox="0 0 24 24"><path d="m5 12 4 4L19 6"/></svg></span>
</button>
<?php endfor; ?>
</div>
</div>

<div class="avatar-default-row">
<button type="button" class="avatar-default-option <?php echo $user['avatar']==='default'?'is-selected':''; ?>" data-avatar="default">
<img src="images/avatars/default.svg" alt="">
<span>تصویر پیش‌فرض<small>خنثی</small></span>
<span class="avatar-check"><svg viewBox="0 0 24 24"><path d="m5 12 4 4L19 6"/></svg></span>
</button>
</div>

<div class="profile-save-row">
<div class="profile-selection-note">برای فعال کردن آواتار داخلی، انتخاب خود را ذخیره کنید.</div>
<button class="profile-save-button" type="submit">
<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2Z"/><path d="M17 21v-6H7v6"/></svg>
ذخیره آواتار
</button>
</div>
</form>

<div class="profile-upload-box">
<div class="profile-upload-heading">
    <span class="profile-upload-icon"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 16V4"/><path d="m7 9 5-5 5 5"/><path d="M5 20h14"/></svg></span>
    <div><strong>تصویر شخصی</strong><small>JPG / JPEG / PNG · حداکثر ۵ مگابایت</small></div>
</div>

<form method="post" enctype="multipart/form-data" class="profile-upload-form">
<input type="hidden" name="csrf_token" value="<?php echo ph($csrf); ?>">
<input type="hidden" name="action" value="upload_avatar">
<label class="profile-file-label">
    <input type="file" name="avatar_file" accept=".jpg,.jpeg,.png,image/jpeg,image/png" required>
    <span class="profile-file-icon"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 5a2 2 0 0 1 2-2h8l6 6v10a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2Z"/><path d="M14 3v6h6"/><path d="m8 16 2.5-3 2 2 1.5-2 2 3"/></svg></span>
    <span class="profile-file-text"><strong>انتخاب تصویر</strong><small>تصویر مربع با کیفیت مناسب پیشنهاد می‌شود.</small></span>
</label>
<button class="profile-upload-button" type="submit">
<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 16V4"/><path d="m7 9 5-5 5 5"/><path d="M5 20h14"/></svg>
بارگذاری تصویر
</button>
</form>

<?php if($isCustom): ?>
<form method="post" class="profile-remove-form">
<input type="hidden" name="csrf_token" value="<?php echo ph($csrf); ?>">
<input type="hidden" name="action" value="remove_custom_avatar">
<button type="submit" class="profile-remove-button">
<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h16"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M6 7l1 14h10l1-14"/><path d="M9 7V4h6v3"/></svg>
حذف تصویر شخصی و بازگشت به پیش‌فرض
</button>
</form>
<?php endif; ?>
</div>

</section>

<aside class="profile-side-column">
<section class="profile-card profile-identity-card">
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
<a href="my-tickets.php"><span class="profile-stat-icon <?php echo $x[2]; ?>"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 4h14a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2Z"/></svg></span><span class="profile-stat-label"><?php echo $x[0]; ?></span><strong><?php echo (int)($st[$x[1]]??0); ?></strong></a>
<?php endforeach; ?>
</div>
</section>
</aside>
</div>

<section class="profile-card profile-message-card">
<div class="profile-card-heading">
<div>
    <div class="profile-message-title"><span class="profile-message-icon"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M20 11.5a7.5 7.5 0 0 1-8 7.45A8.6 8.6 0 0 1 5.8 17L3 18l1-3.2A7.5 7.5 0 1 1 20 11.5Z"/><path d="M8 11h.01M12 11h.01M16 11h.01"/></svg></span><h2>پیام عمومی من</h2></div>
    <p>یک جمله درباره کار، اهداف، همکاران یا NEAL بنویسید. پس از تأیید مدیر در صفحه ورود نمایش داده می‌شود.</p>
</div>
<div class="message-status-badge <?php echo ph($messageStatus['class']); ?>"><?php echo ph($messageStatus['label']); ?></div>
</div>

<form method="post" class="public-message-form">
<input type="hidden" name="csrf_token" value="<?php echo ph($csrf); ?>">
<input type="hidden" name="action" value="save_message">
<textarea name="login_message" maxlength="300" placeholder="مثلاً: با همکاری هم، هر روز یک قدم برای بهتر شدن NEAL برمی‌داریم."><?php echo ph($user['login_message']); ?></textarea>
<div class="public-message-footer">
<div class="public-message-hint"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 10v6"/><path d="M12 7h.01"/></svg><span>حداکثر ۳۰۰ کاراکتر · پیام قبل از انتشار توسط مدیر بررسی می‌شود.</span></div>
<button type="submit" class="profile-message-save"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2Z"/><path d="M17 21v-6H7v6"/></svg><?php echo $user['login_message']!==''?'ارسال برای بررسی':'ثبت پیام'; ?></button>
</div>
</form>

<?php if($user['login_message_status']==='rejected' && $user['login_message_rejection_reason']!==''): ?>
<div class="message-rejection-note"><strong>توضیح مدیر:</strong> <?php echo ph($user['login_message_rejection_reason']); ?></div>
<?php endif; ?>
</section>

</div>

<script>
(function(){
    var csrf=<?php echo json_encode($csrf,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); ?>;
    var currentBuiltIn=<?php echo json_encode(in_array($user['avatar'],$valid,true)?$user['avatar']:'',JSON_UNESCAPED_SLASHES); ?>;
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

    var fileInput=document.querySelector('.profile-file-label input[type=file]');
    var fileLabel=document.querySelector('.profile-file-text strong');
    if(fileInput){
        fileInput.addEventListener('change',function(){
            if(this.files && this.files[0]){
                fileLabel.textContent=this.files[0].name;
            }
        });
    }
})();
</script>

<?php include "includes/footer.php"; ?>