<?php
require_once "auth/auth.php";
check_login();
require_once "includes/user-profile.php";

$page_title="مدیریت پیام‌های ورود";
$db=neal_profile_db();

if(empty($_SESSION['neal_messages_csrf'])){
    $_SESSION['neal_messages_csrf']=bin2hex(random_bytes(32));
}
$csrf=$_SESSION['neal_messages_csrf'];

function mh($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}

function jalali_message_date($datetime){
    if(!$datetime) return '—';
    $timestamp=strtotime($datetime);
    if(!$timestamp) return mh($datetime);
    $gy=(int)date('Y',$timestamp); $gm=(int)date('n',$timestamp); $gd=(int)date('j',$timestamp);
    $gdm=[0,31,59,90,120,151,181,212,243,273,304,334];
    if($gy>1600){$jy=979;$gy-=1600;}else{$jy=0;$gy-=621;}
    $gy2=$gm>2?$gy+1:$gy;
    $days=(365*$gy)+floor(($gy2+3)/4)-floor(($gy2+99)/100)+floor(($gy2+399)/400)-80+$gd+$gdm[$gm-1];
    $jy+=33*floor($days/12053); $days%=12053;
    $jy+=4*floor($days/1461); $days%=1461;
    if($days>365){$jy+=floor(($days-1)/365);$days=($days-1)%365;}
    if($days<186){$jm=1+floor($days/31);$jd=1+$days%31;}
    else{$jm=7+floor(($days-186)/30);$jd=1+($days-186)%30;}
    return sprintf('%04d/%02d/%02d · %s',$jy,$jm,$jd,date('H:i',$timestamp));
}

function messages_csrf_ok($token){
    return isset($_SESSION['neal_messages_csrf']) &&
        is_string($token) &&
        hash_equals($_SESSION['neal_messages_csrf'],$token);
}

$message=''; $message_type='';

if($_SERVER['REQUEST_METHOD']==='POST'){
    if(!messages_csrf_ok($_POST['csrf_token']??'')){
        $message='درخواست نامعتبر است. صفحه را تازه‌سازی کرده و دوباره تلاش کنید.';
        $message_type='error';
    }else{
        $action=$_POST['action']??'';
        $user_id=(int)($_POST['user_id']??0);

        if($user_id<=0){
            $message='کاربر انتخاب‌شده معتبر نیست.';
            $message_type='error';
        }elseif($action==='approve'){
            $s=$db->prepare("UPDATE users SET login_message_status='approved',login_message_rejection_reason='',login_message_reviewed_at=CURRENT_TIMESTAMP,login_message_reviewed_by=? WHERE id=? AND TRIM(COALESCE(login_message,''))<>''");
            $s->execute([(int)($_SESSION['admin_id']??0),$user_id]);
            $message=$s->rowCount()>0?'پیام با موفقیت تأیید شد و در صفحه ورود نمایش داده می‌شود.':'پیام قابل تأییدی برای این کاربر پیدا نشد.';
            $message_type=$s->rowCount()>0?'success':'error';
        }elseif($action==='reject'){
            $reason=trim((string)($_POST['reason']??''));
            if(mb_strlen($reason)>500)$reason=mb_substr($reason,0,500);
            $s=$db->prepare("UPDATE users SET login_message_status='rejected',login_message_rejection_reason=?,login_message_reviewed_at=CURRENT_TIMESTAMP,login_message_reviewed_by=? WHERE id=? AND TRIM(COALESCE(login_message,''))<>''");
            $s->execute([$reason,(int)($_SESSION['admin_id']??0),$user_id]);
            $message=$s->rowCount()>0?'پیام رد شد و دلیل آن برای کاربر ثبت گردید.':'پیام موردنظر پیدا نشد.';
            $message_type=$s->rowCount()>0?'success':'error';
        }elseif($action==='unpublish'){
            $s=$db->prepare("UPDATE users SET login_message_status='pending',login_message_reviewed_at=NULL,login_message_reviewed_by=NULL WHERE id=? AND login_message_status='approved'");
            $s->execute([$user_id]);
            $message=$s->rowCount()>0?'نمایش پیام متوقف شد و دوباره به وضعیت بررسی برگشت.':'پیام منتشرشده پیدا نشد.';
            $message_type=$s->rowCount()>0?'success':'error';
        }
    }
}

$counts=[];
foreach(['pending','approved','rejected'] as $status){
    $s=$db->prepare("SELECT COUNT(*) FROM users WHERE login_message_status=? AND TRIM(COALESCE(login_message,''))<>''");
    $s->execute([$status]); $counts[$status]=(int)$s->fetchColumn();
}

$s=$db->query("SELECT id,username,fullname,department,COALESCE(avatar,'default') avatar,login_message,login_message_status,login_message_rejection_reason,login_message_submitted_at,login_message_reviewed_at FROM users WHERE TRIM(COALESCE(login_message,''))<>'' AND login_message_status IN ('pending','approved','rejected') ORDER BY CASE login_message_status WHEN 'pending' THEN 1 WHEN 'approved' THEN 2 ELSE 3 END,COALESCE(login_message_submitted_at,'') DESC");
$messages=$s->fetchAll();
?>
<?php require_once "includes/header.php"; ?>

<div class="login-messages-page">
<div class="page-header">
<div>
<h1>مدیریت پیام‌های صفحه ورود</h1>
<p>پیام‌های عمومی همکاران را بررسی و مدیریت کنید.</p>
</div>
<a href="users.php" class="lm-back-link">
<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M19 12H5"/><path d="m11 18-6-6 6-6"/></svg>
بازگشت به کاربران
</a>
</div>

<?php if($message): ?>
<div class="lm-alert <?php echo $message_type==='success'?'success':'error'; ?>">
<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m5 12 4 4L19 6"/></svg>
<?php echo mh($message); ?>
</div>
<?php endif; ?>

<div class="lm-stats">
<div class="lm-stat pending"><span><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg></span><strong><?php echo $counts['pending']; ?></strong><small>در انتظار بررسی</small></div>
<div class="lm-stat approved"><span><svg viewBox="0 0 24 24"><path d="m5 12 4 4L19 6"/><circle cx="12" cy="12" r="9"/></svg></span><strong><?php echo $counts['approved']; ?></strong><small>نمایش داده می‌شود</small></div>
<div class="lm-stat rejected"><span><svg viewBox="0 0 24 24"><path d="M6 6l12 12M18 6 6 18"/><circle cx="12" cy="12" r="9"/></svg></span><strong><?php echo $counts['rejected']; ?></strong><small>ردشده</small></div>
</div>

<div class="lm-card">
<div class="lm-card-head">
<div><h2>پیام‌های همکاران</h2><p>فقط پیام‌های تأییدشده در صفحه ورود عمومی نمایش داده می‌شوند.</p></div>
</div>

<?php if(!$messages): ?>
<div class="lm-empty">
<div class="lm-empty-icon"><svg viewBox="0 0 24 24"><path d="M20 11.5a7.5 7.5 0 0 1-8 7.45A8.6 8.6 0 0 1 5.8 17L3 18l1-3.2A7.5 7.5 0 1 1 20 11.5Z"/></svg></div>
<strong>هنوز پیامی برای بررسی وجود ندارد</strong>
<span>وقتی یکی از همکاران پیام عمومی ثبت کند، اینجا نمایش داده می‌شود.</span>
</div>
<?php else: ?>

<div class="lm-list">
<?php foreach($messages as $item):
    $status=$item['login_message_status'];
    $statusLabel=$status==='pending'?'در انتظار بررسی':($status==='approved'?'تأیید شده':'رد شده');
    $statusClass=$status;
    $avatar=neal_avatar_url($item['avatar']);
?>
<article class="lm-item">
<div class="lm-item-top">
<div class="lm-person">
<div class="lm-avatar"><img src="<?php echo mh($avatar); ?>" alt=""></div>
<div><strong><?php echo mh($item['fullname']); ?></strong><span>@<?php echo mh($item['username']); ?><?php echo $item['department']?' · '.mh($item['department']):''; ?></span></div>
</div>
<span class="lm-status <?php echo mh($statusClass); ?>"><?php echo mh($statusLabel); ?></span>
</div>

<div class="lm-message"><?php echo nl2br(mh($item['login_message'])); ?></div>

<div class="lm-meta">
<span><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>ارسال: <?php echo mh(jalali_message_date($item['login_message_submitted_at'])); ?></span>
<?php if($item['login_message_reviewed_at']): ?><span>بررسی: <?php echo mh(jalali_message_date($item['login_message_reviewed_at'])); ?></span><?php endif; ?>
</div>

<?php if($status==='rejected' && $item['login_message_rejection_reason']!==''): ?>
<div class="lm-reason"><strong>دلیل رد:</strong> <?php echo mh($item['login_message_rejection_reason']); ?></div>
<?php endif; ?>

<div class="lm-actions">
<?php if($status!=='approved'): ?>
<form method="post"><input type="hidden" name="csrf_token" value="<?php echo mh($csrf); ?>"><input type="hidden" name="action" value="approve"><input type="hidden" name="user_id" value="<?php echo (int)$item['id']; ?>"><button class="lm-btn approve" type="submit"><svg viewBox="0 0 24 24"><path d="m5 12 4 4L19 6"/></svg>تأیید و انتشار</button></form>
<?php else: ?>
<form method="post"><input type="hidden" name="csrf_token" value="<?php echo mh($csrf); ?>"><input type="hidden" name="action" value="unpublish"><input type="hidden" name="user_id" value="<?php echo (int)$item['id']; ?>"><button class="lm-btn neutral" type="submit"><svg viewBox="0 0 24 24"><path d="M4 12s3-5 8-5 8 5 8 5-3 5-8 5-8-5-8-5Z"/><circle cx="12" cy="12" r="2"/></svg>توقف نمایش</button></form>
<?php endif; ?>

<form method="post" class="lm-reject-form">
<input type="hidden" name="csrf_token" value="<?php echo mh($csrf); ?>">
<input type="hidden" name="action" value="reject">
<input type="hidden" name="user_id" value="<?php echo (int)$item['id']; ?>">
<input type="text" name="reason" maxlength="500" placeholder="دلیل رد (اختیاری)">
<button class="lm-btn reject" type="submit"><svg viewBox="0 0 24 24"><path d="M6 6l12 12M18 6 6 18"/></svg>رد کردن</button>
</form>
</div>
</article>
<?php endforeach; ?>
</div>
<?php endif; ?>
</div>
</div>

<style>
.login-messages-page{padding-bottom:30px}
.login-messages-page .page-header{display:flex;align-items:center;justify-content:space-between;gap:20px}
.lm-back-link{display:inline-flex;align-items:center;gap:7px;text-decoration:none;padding:9px 13px;border-radius:10px;background:#f2f4f7;color:#344054;font-size:11px;font-weight:600}
.lm-back-link svg{width:16px;height:16px;fill:none;stroke:currentColor;stroke-width:1.8;stroke-linecap:round;stroke-linejoin:round}
.lm-alert{display:flex;align-items:center;gap:8px;padding:11px 13px;border-radius:10px;margin:15px 0;font-size:11px}
.lm-alert svg{width:17px;height:17px;fill:none;stroke:currentColor;stroke-width:1.8}
.lm-alert.success{background:#ecfdf3;color:#027a48;border:1px solid #abefc6}
.lm-alert.error{background:#fef3f2;color:#b42318;border:1px solid #fecdca}
.lm-stats{display:grid;grid-template-columns:repeat(3,1fr);gap:14px;margin:18px 0}
.lm-stat{border:1px solid #eaecf0;background:#fff;border-radius:14px;padding:15px;display:grid;grid-template-columns:42px 1fr;grid-template-rows:auto auto;column-gap:12px;align-items:center;box-shadow:0 4px 14px rgba(16,24,40,.04)}
.lm-stat span{grid-row:1/3;width:42px;height:42px;border-radius:12px;display:flex;align-items:center;justify-content:center}
.lm-stat svg{width:21px;height:21px;fill:none;stroke:currentColor;stroke-width:1.8;stroke-linecap:round;stroke-linejoin:round}
.lm-stat strong{font-size:20px;color:#101828}.lm-stat small{font-size:10px;color:#667085;margin-top:2px}
.lm-stat.pending span{background:#fff7e6;color:#b54708}.lm-stat.approved span{background:#ecfdf3;color:#027a48}.lm-stat.rejected span{background:#fef3f2;color:#b42318}
.lm-card{background:#fff;border:1px solid #eaecf0;border-radius:16px;box-shadow:0 5px 18px rgba(16,24,40,.04);padding:20px}
.lm-card-head{padding-bottom:14px;border-bottom:1px solid #f2f4f7}.lm-card-head h2{margin:0;color:#101828;font-size:16px}.lm-card-head p{margin:5px 0 0;color:#98a2b3;font-size:10px}
.lm-list{display:flex;flex-direction:column;gap:12px;margin-top:15px}
.lm-item{border:1px solid #eaecf0;border-radius:14px;padding:15px;background:linear-gradient(145deg,#fff,#fafbfc)}
.lm-item-top{display:flex;align-items:center;justify-content:space-between;gap:12px}
.lm-person{display:flex;align-items:center;gap:10px;min-width:0}.lm-avatar{width:42px;height:42px;border-radius:12px;overflow:hidden;background:#eef2f6;flex:0 0 42px}.lm-avatar img{width:100%;height:100%;object-fit:cover}.lm-person strong{display:block;font-size:12px;color:#1d2939}.lm-person span{display:block;font-size:9px;color:#98a2b3;margin-top:3px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.lm-status{padding:5px 9px;border-radius:999px;font-size:9px;font-weight:600;white-space:nowrap}.lm-status.pending{background:#fff7e6;color:#b54708}.lm-status.approved{background:#ecfdf3;color:#027a48}.lm-status.rejected{background:#fef3f2;color:#b42318}
.lm-message{margin:13px 0;padding:12px;border-radius:10px;background:#f8fafc;color:#344054;font-size:11px;line-height:2}
.lm-meta{display:flex;gap:15px;flex-wrap:wrap;color:#98a2b3;font-size:9px}.lm-meta span{display:inline-flex;align-items:center;gap:4px}.lm-meta svg{width:13px;height:13px;fill:none;stroke:currentColor;stroke-width:1.7}
.lm-reason{margin-top:10px;padding:9px 11px;border-radius:9px;background:#fff6f5;color:#912018;border:1px solid #fecdca;font-size:10px;line-height:1.8}
.lm-actions{display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-top:12px}.lm-actions form{display:flex;gap:6px;flex:0 0 auto}.lm-reject-form{flex:1 1 260px!important}
.lm-reject-form input{min-width:0;flex:1;border:1px solid #d0d5dd;border-radius:8px;padding:8px 9px;font-family:inherit;font-size:10px;outline:none}.lm-reject-form input:focus{border-color:#98a2b3}
.lm-btn{border:0;border-radius:8px;padding:8px 11px;display:inline-flex;align-items:center;gap:5px;font-family:inherit;font-size:10px;font-weight:600;cursor:pointer;white-space:nowrap}.lm-btn svg{width:15px;height:15px;fill:none;stroke:currentColor;stroke-width:1.8;stroke-linecap:round;stroke-linejoin:round}.lm-btn.approve{background:#12b76a;color:#fff}.lm-btn.approve:hover{background:#039855}.lm-btn.reject{background:#fef3f2;color:#b42318}.lm-btn.reject:hover{background:#fee4e2}.lm-btn.neutral{background:#f2f4f7;color:#344054}.lm-btn.neutral:hover{background:#e4e7ec}
.lm-empty{text-align:center;padding:55px 20px;color:#98a2b3}.lm-empty-icon{width:54px;height:54px;border-radius:16px;background:#f2f4f7;color:#667085;display:flex;align-items:center;justify-content:center;margin:0 auto 12px}.lm-empty-icon svg{width:25px;height:25px;fill:none;stroke:currentColor;stroke-width:1.7;stroke-linecap:round;stroke-linejoin:round}.lm-empty strong{display:block;color:#344054;font-size:12px}.lm-empty span{display:block;font-size:10px;margin-top:5px}
@media(max-width:700px){.lm-stats{grid-template-columns:1fr}.login-messages-page .page-header{align-items:flex-start;flex-direction:column}.lm-item-top{align-items:flex-start;flex-direction:column}.lm-status{align-self:flex-start}.lm-actions{align-items:stretch;flex-direction:column}.lm-actions form,.lm-reject-form{width:100%;flex-basis:auto!important}.lm-btn{justify-content:center;width:100%}}
</style>

<?php require_once "includes/footer.php"; ?>