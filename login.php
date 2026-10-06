<?php
require_once __DIR__ . "/config/config.php";
require_once __DIR__ . "/auth/auth.php";
require_once __DIR__ . "/includes/user-profile.php";

$db = new PDO("sqlite:" . __DIR__ . "/data/neal.db");
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

if (isset($_SESSION['admin_id'])) {
    header("Location: /neal/");
    exit;
}
if (isset($_SESSION['user_id'])) {
    header("Location: /neal/portal.php");
    exit;
}

$error = "";
$timeout = isset($_GET['timeout']) && $_GET['timeout'] == "1";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login_type = $_POST['login_type'] ?? "";
    $username   = trim($_POST['username'] ?? "");
    $password   = $_POST['password'] ?? "";

    if ($username === "" || $password === "") {
        $error = "نام کاربری و رمز عبور را وارد کنید.";
    } elseif ($login_type === "admin") {
        $stmt = $db->prepare("SELECT * FROM admins WHERE username = ? LIMIT 1");
        $stmt->execute([$username]);
        $admin = $stmt->fetch();

        if ($admin && password_verify($password, $admin['password'])) {
            session_regenerate_id(true);
            unset(
                $_SESSION['user_id'], $_SESSION['user_username'],
                $_SESSION['user_fullname'], $_SESSION['user_personnel_code'],
                $_SESSION['user_department'], $_SESSION['user_department_name'],
                $_SESSION['user_phone'], $_SESSION['user_last_activity']
            );
            $_SESSION['admin_id'] = $admin['id'];
            $_SESSION['username'] = $admin['username'];
            $_SESSION['role'] = $admin['role'] ?? "admin";
            $_SESSION['last_activity'] = time();
            header("Location: /neal/");
            exit;
        }
        $error = "نام کاربری یا رمز عبور مدیریت / کارشناس صحیح نیست.";
    } elseif ($login_type === "user") {
        $stmt = $db->prepare("
            SELECT u.*, d.name AS department_name
            FROM users u
            LEFT JOIN departments d ON d.code = u.department
            WHERE u.username = ? AND u.is_active = 1
            LIMIT 1
        ");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            session_regenerate_id(true);
            unset($_SESSION['admin_id'], $_SESSION['username'], $_SESSION['role'], $_SESSION['last_activity']);
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_username'] = $user['username'];
            $_SESSION['user_fullname'] = $user['fullname'];
            $_SESSION['user_personnel_code'] = $user['personnel_code'];
            $_SESSION['user_department'] = $user['department'];
            $_SESSION['user_department_name'] = $user['department_name'];
            $_SESSION['user_phone'] = $user['phone'];
            $_SESSION['user_last_activity'] = time();
            header("Location: /neal/portal.php");
            exit;
        }
        $error = "نام کاربری یا رمز عبور کاربر صحیح نیست.";
    } else {
        $error = "نوع ورود نامعتبر است.";
    }
}

/* ---------------------------------------------------------
   Public approved colleague messages
   --------------------------------------------------------- */
$login_messages = [];
try {
    $stmt = $db->query("
        SELECT id, fullname, username, avatar, login_message, login_message_submitted_at
        FROM users
        WHERE is_active = 1
          AND login_message_status = 'approved'
          AND TRIM(COALESCE(login_message,'')) <> ''
        ORDER BY id ASC
    ");
    $login_messages = $stmt->fetchAll();
} catch (Throwable $e) {
    $login_messages = [];
}

foreach ($login_messages as &$item) {
    $item['avatar_url'] = neal_avatar_url($item['avatar'] ?? 'default');
}
unset($item);

/* ---------------------------------------------------------
   Announcements: safe empty state until the module exists.
   --------------------------------------------------------- */
$announcements = [];
try {
    $tableExists = (int)$db->query("
        SELECT COUNT(*) FROM sqlite_master
        WHERE type='table' AND name='announcements'
    ")->fetchColumn();

    if ($tableExists) {
        $stmt = $db->query("
            SELECT id, title, body, published_at, expires_at
            FROM announcements
            WHERE is_active = 1
              AND (published_at IS NULL OR published_at <= CURRENT_TIMESTAMP)
              AND (expires_at IS NULL OR expires_at = '' OR expires_at > CURRENT_TIMESTAMP)
            ORDER BY COALESCE(published_at, created_at) DESC, id DESC
            LIMIT 3
        ");
        $announcements = $stmt->fetchAll();
    }
} catch (Throwable $e) {
    $announcements = [];
}

function login_h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function login_jalali_date($datetime) {
    if (!$datetime) return '';
    $timestamp = strtotime($datetime);
    if (!$timestamp) return '';
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
    return sprintf('%04d/%02d/%02d',$jy,$jm,$jd);
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>ورود به سامانه NEAL</title>
<style>
@font-face{font-family:'Vazirmatn';src:url('/neal/fonts/Vazirmatn-Regular.woff2') format('woff2');font-weight:400;font-style:normal;font-display:swap}
@font-face{font-family:'Vazirmatn';src:url('/neal/fonts/Vazirmatn-Medium.woff2') format('woff2');font-weight:500;font-style:normal;font-display:swap}
@font-face{font-family:'Vazirmatn';src:url('/neal/fonts/Vazirmatn-SemiBold.woff2') format('woff2');font-weight:600;font-style:normal;font-display:swap}
@font-face{font-family:'Vazirmatn';src:url('/neal/fonts/Vazirmatn-Bold.woff2') format('woff2');font-weight:700;font-style:normal;font-display:swap}
*{box-sizing:border-box}
body{margin:0;min-height:100vh;padding:28px 18px;background:radial-gradient(circle at top right,#eef5ff 0,#f5f7fa 42%,#edf1f5 100%);font-family:'Vazirmatn',Tahoma,Arial,sans-serif;color:#1f2937}
.login-wrapper{width:100%;max-width:1180px;margin:0 auto}
.brand-logo{text-align:center;margin-bottom:10px}.brand-logo img{height:76px;width:auto;max-width:180px;object-fit:contain}
.header{text-align:center;margin-bottom:22px}.header h1{margin:0 0 5px;font-size:25px;color:#17365d}.header p{margin:0;color:#667085;font-size:11px}
.login-main{display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1fr);gap:18px;align-items:stretch}
.login-column{display:flex;flex-direction:column;gap:18px}
.login-card{background:rgba(255,255,255,.96);border:1px solid #e4e7ec;border-radius:18px;padding:22px;box-shadow:0 10px 28px rgba(16,24,40,.06);position:relative;overflow:hidden}
.login-card:before{content:"";position:absolute;top:0;right:0;left:0;height:3px;background:#dfe7f1}.user-card:before{background:#3b82f6}
.card-title{display:flex;align-items:center;gap:11px;margin-bottom:7px}.card-icon{width:42px;height:42px;min-width:42px;border-radius:12px;display:flex;align-items:center;justify-content:center;background:#eef4ff;color:#17365d}.card-icon svg{width:23px;height:23px;fill:none;stroke:currentColor;stroke-width:1.8;stroke-linecap:round;stroke-linejoin:round}
.login-card h2{margin:0;font-size:15px;color:#17365d}.description{color:#667085;font-size:10px;line-height:1.9;margin-bottom:15px}
.form-group{margin-bottom:11px}.form-group label{display:block;margin-bottom:5px;font-size:10px;font-weight:600;color:#344054}.form-group input{width:100%;padding:9px 11px;border:1px solid #d0d5dd;border-radius:9px;font-family:inherit;font-size:11px;outline:none;background:#fff;transition:.18s}.form-group input:focus{border-color:#7b9bd1;box-shadow:0 0 0 3px rgba(49,89,166,.09)}
.login-button{width:100%;border:0;border-radius:9px;padding:10px;font-family:inherit;font-size:11px;font-weight:600;cursor:pointer;color:#fff;background:#17365d;transition:.18s}.login-button:hover{background:#0f2948;transform:translateY(-1px)}
.user-card .card-icon{background:#eff6ff;color:#2563eb}.user-card .login-button{background:#2563eb}.user-card .login-button:hover{background:#1d4ed8}
.info-card{min-height:0}.info-head{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:13px}.info-title{display:flex;align-items:center;gap:9px}.info-icon{width:36px;height:36px;border-radius:10px;display:flex;align-items:center;justify-content:center;background:#eef4ff;color:#3159a6}.info-icon svg{width:19px;height:19px;fill:none;stroke:currentColor;stroke-width:1.7;stroke-linecap:round;stroke-linejoin:round}.info-title strong{display:block;font-size:13px;color:#101828}.info-title small{display:block;color:#98a2b3;font-size:9px;margin-top:2px}.info-count{font-size:9px;color:#667085;background:#f2f4f7;border-radius:999px;padding:5px 8px}
.message-stage{position:relative;min-height:118px}.public-message{display:none;align-items:center;gap:12px;opacity:0;transform:translateY(5px);transition:opacity .35s ease,transform .35s ease}.public-message.active{display:flex;opacity:1;transform:translateY(0)}.message-avatar{width:58px;height:58px;min-width:58px;border-radius:16px;overflow:hidden;background:#f2f4f7;border:1px solid #eaecf0}.message-avatar img{width:100%;height:100%;object-fit:cover}.message-content{min-width:0}.message-content p{margin:0;color:#344054;font-size:11px;line-height:2;display:-webkit-box;-webkit-line-clamp:3;-webkit-box-orient:vertical;overflow:hidden}.message-person{display:flex;align-items:center;gap:5px;margin-top:8px;color:#667085;font-size:9px}.message-person strong{color:#344054;font-size:10px}.message-date{color:#98a2b3}
.message-progress{height:3px;border-radius:99px;background:#edf2f7;overflow:hidden;margin-top:9px}.message-progress span{display:block;height:100%;width:0;background:#5b7fbd;transition:width linear}
.message-empty{min-height:118px;display:flex;flex-direction:column;align-items:center;justify-content:center;text-align:center;color:#98a2b3;gap:6px}.message-empty svg{width:27px;height:27px;fill:none;stroke:currentColor;stroke-width:1.6;stroke-linecap:round;stroke-linejoin:round}.message-empty strong{font-size:10px;color:#667085}.message-empty span{font-size:9px}
.announcement-list{display:flex;flex-direction:column;gap:8px}.announcement-item{padding:10px 11px;border:1px solid #eaecf0;border-radius:10px;background:#fafbfc}.announcement-item strong{display:block;color:#1d2939;font-size:10px}.announcement-item p{margin:4px 0 0;color:#667085;font-size:9px;line-height:1.8;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}.announcement-meta{display:block;margin-top:5px;color:#98a2b3;font-size:8px}.announcement-empty{min-height:118px;display:flex;flex-direction:column;align-items:center;justify-content:center;text-align:center;color:#98a2b3;gap:6px}.announcement-empty svg{width:27px;height:27px;fill:none;stroke:currentColor;stroke-width:1.6;stroke-linecap:round;stroke-linejoin:round}.announcement-empty strong{font-size:10px;color:#667085}.announcement-empty span{font-size:9px}.announcement-view{display:inline-flex;align-items:center;gap:5px;margin-top:9px;color:#3159a6;text-decoration:none;font-size:9px;font-weight:600}.announcement-view svg{width:13px;height:13px;fill:none;stroke:currentColor;stroke-width:1.7}
.error,.timeout{padding:10px 12px;border-radius:9px;margin-bottom:14px;font-size:10px;line-height:1.8}.error{background:#fef3f2;color:#b42318;border:1px solid #fecdca}.timeout{background:#fff7e6;color:#b54708;border:1px solid #fedf89;text-align:center}
.footer{text-align:center;margin-top:17px;color:#98a2b3;font-size:9px}
@media(max-width:800px){body{padding:20px 13px}.login-main{grid-template-columns:1fr}.login-column{gap:14px}.brand-logo img{height:68px}.header h1{font-size:22px}.login-card{padding:19px}}
@media(max-width:480px){body{padding:14px 10px}.login-card{padding:16px;border-radius:15px}.message-avatar{width:50px;height:50px;min-width:50px}.header{margin-bottom:15px}}
</style>
</head>
<body>
<div class="login-wrapper">

<div class="brand-logo"><img src="/neal/images/logo.png" alt="NEAL Pharmed"></div>

<div class="header">
<h1>سامانه NEAL</h1>
<p>پورتال داخلی NEAL Pharmed</p>
</div>

<?php if ($timeout): ?><div class="timeout">به دلیل عدم فعالیت، نشست شما منقضی شده است. لطفاً مجدداً وارد شوید.</div><?php endif; ?>
<?php if ($error !== ""): ?><div class="error"><?php echo login_h($error); ?></div><?php endif; ?>

<div class="login-main">

<div class="login-column">
<div class="login-card user-card">
<div class="card-title">
<div class="card-icon"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="8" r="3.2"/><path d="M5.5 20C6.1 15.9 8.2 13.8 12 13.8c3.8 0 5.9 2.1 6.5 6.2"/></svg></div>
<h2>ورود کاربران</h2>
</div>
<div class="description">ورود کارکنان برای ثبت درخواست، مشاهده درخواست‌ها و استفاده از خدمات پورتال.</div>
<form method="post">
<input type="hidden" name="login_type" value="user">
<div class="form-group"><label>نام کاربری</label><input type="text" name="username" autocomplete="username" required></div>
<div class="form-group"><label>رمز عبور</label><input type="password" name="password" autocomplete="current-password" required></div>
<button type="submit" class="login-button">ورود به پورتال</button>
</form>
</div>

<div class="login-card">
<div class="card-title">
<div class="card-icon"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3l8 3v5.5c0 5-3.2 8.8-8 10.5-4.8-1.7-8-5.5-8-10.5V6l8-3Z"/><circle cx="12" cy="9" r="2.1"/><path d="M8.4 16c.5-2.1 1.7-3.3 3.6-3.3s3.1 1.2 3.6 3.3"/></svg></div>
<h2>ورود مدیریت و کارشناسان</h2>
</div>
<div class="description">ورود مدیران و کارشناسان فناوری اطلاعات برای مدیریت سامانه و رسیدگی به درخواست‌ها.</div>
<form method="post">
<input type="hidden" name="login_type" value="admin">
<div class="form-group"><label>نام کاربری</label><input type="text" name="username" autocomplete="username" required></div>
<div class="form-group"><label>رمز عبور</label><input type="password" name="password" autocomplete="current-password" required></div>
<button type="submit" class="login-button">ورود به بخش مدیریت</button>
</form>
</div>
</div>

<div class="login-column">

<div class="login-card info-card">
<div class="info-head">
<div class="info-title">
<div class="info-icon"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M20 11.5a7.5 7.5 0 0 1-8 7.45A8.6 8.6 0 0 1 5.8 17L3 18l1-3.2A7.5 7.5 0 1 1 20 11.5Z"/><path d="M8 11h.01M12 11h.01M16 11h.01"/></svg></div>
<div><strong>پیام همکاران</strong><small>پیام‌های تأییدشده همکاران</small></div>
</div>
<span class="info-count"><?php echo count($login_messages); ?> پیام</span>
</div>

<?php if($login_messages): ?>
<div class="message-stage" id="messageStage">
<?php foreach($login_messages as $index=>$item): ?>
<div class="public-message<?php echo $index===0?' active':''; ?>" data-message-index="<?php echo $index; ?>">
<div class="message-avatar"><img src="<?php echo login_h($item['avatar_url']); ?>" alt=""></div>
<div class="message-content">
<p><?php echo nl2br(login_h($item['login_message'])); ?></p>
<div class="message-person"><strong><?php echo login_h($item['fullname']); ?></strong><span>·</span><span class="message-date"><?php echo login_h(login_jalali_date($item['login_message_submitted_at'])); ?></span></div>
</div>
</div>
<?php endforeach; ?>
</div>
<div class="message-progress"><span id="messageProgress"></span></div>
<?php else: ?>
<div class="message-empty">
<svg viewBox="0 0 24 24"><path d="M20 11.5a7.5 7.5 0 0 1-8 7.45A8.6 8.6 0 0 1 5.8 17L3 18l1-3.2A7.5 7.5 0 1 1 20 11.5Z"/></svg>
<strong>هنوز پیام عمومی تأییدشده‌ای ثبت نشده است.</strong>
<span>این بخش با پیام‌های تأییدشده همکاران تکمیل می‌شود.</span>
</div>
<?php endif; ?>
</div>

<div class="login-card info-card">
<div class="info-head">
<div class="info-title">
<div class="info-icon"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 4h12a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2Z"/><path d="M8 8h8M8 12h8M8 16h5"/></svg></div>
<div><strong>اطلاعیه‌های شرکت</strong><small>آخرین اطلاعیه‌های NEAL Pharmed</small></div>
</div>
</div>

<?php if($announcements): ?>
<div class="announcement-list">
<?php foreach($announcements as $announcement): ?>
<div class="announcement-item">
<strong><?php echo login_h($announcement['title']); ?></strong>
<p><?php echo login_h(strip_tags($announcement['body'])); ?></p>
<span class="announcement-meta"><?php echo login_h(login_jalali_date($announcement['published_at'])); ?></span>
</div>
<?php endforeach; ?>
</div>
<a href="/neal/announcements.php" class="announcement-view">مشاهده همه اطلاعیه‌ها <svg viewBox="0 0 24 24"><path d="M5 12h14"/><path d="m13 6 6 6-6 6"/></svg></a>
<?php else: ?>
<div class="announcement-empty">
<svg viewBox="0 0 24 24"><path d="M6 4h12a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2Z"/><path d="M8 8h8M8 12h8M8 16h5"/></svg>
<strong>اطلاعیه‌ای برای نمایش وجود ندارد.</strong>
<span>اطلاعیه‌های شرکت پس از فعال شدن بخش مدیریت در اینجا نمایش داده می‌شوند.</span>
</div>
<?php endif; ?>
</div>

</div>
</div>

<div class="footer">NEAL Pharmed Pharmaceutical Company</div>
</div>

<?php if($login_messages): ?>
<script>
(function(){
    var items=[].slice.call(document.querySelectorAll('.public-message'));
    var progress=document.getElementById('messageProgress');
    if(!items.length)return;

    var order=items.map(function(_,i){return i;});
    var current=-1;
    var duration=60000;

    function shuffle(array){
        for(var i=array.length-1;i>0;i--){
            var j=Math.floor(Math.random()*(i+1));
            var t=array[i];array[i]=array[j];array[j]=t;
        }
        return array;
    }

    function next(){
        if(order.length===0){
            order=items.map(function(_,i){return i;});
            shuffle(order);
            if(order.length>1 && order[0]===current){
                var t=order[0];order[0]=order[1];order[1]=t;
            }
        }
        var nextIndex=order.shift();
        items.forEach(function(item,i){
            item.classList.toggle('active',i===nextIndex);
        });
        current=nextIndex;
        if(progress){
            progress.style.transition='none';
            progress.style.width='0%';
            void progress.offsetWidth;
            progress.style.transition='width '+duration+'ms linear';
            progress.style.width='100%';
        }
    }

    order=shuffle(order);
    next();
    setInterval(next,duration);
})();
</script>
<?php endif; ?>
</body>
</html>