<?php
require_once __DIR__ . "/config/config.php";
require_once __DIR__ . "/includes/user-profile.php";
$db = new PDO("sqlite:" . __DIR__ . "/data/neal.db");
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
$db->exec("CREATE TABLE IF NOT EXISTS announcements (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    title TEXT NOT NULL,
    body TEXT NOT NULL DEFAULT '',
    is_active INTEGER NOT NULL DEFAULT 1,
    published_at DATETIME NULL,
    expires_at DATETIME NULL,
    created_by INTEGER NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
)");
$stmt=$db->query("SELECT id,title,body,published_at FROM announcements
WHERE is_active=1
AND (published_at IS NULL OR published_at<=CURRENT_TIMESTAMP)
AND (expires_at IS NULL OR expires_at='' OR expires_at>CURRENT_TIMESTAMP)
ORDER BY COALESCE(published_at,created_at) DESC,id DESC");
$items=$stmt->fetchAll();
function pub_h($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
function pub_date($v){if(!$v)return ''; $t=strtotime($v); return $t?date('Y/m/d H:i',$t):'';}
?>
<!DOCTYPE html><html lang="fa" dir="rtl"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>اطلاعیه‌های شرکت | NEAL</title>
<style>
@font-face{font-family:Vazirmatn;src:url('/neal/fonts/Vazirmatn-Regular.woff2') format('woff2')}@font-face{font-family:Vazirmatn;src:url('/neal/fonts/Vazirmatn-SemiBold.woff2') format('woff2');font-weight:600}
*{box-sizing:border-box}body{margin:0;background:#f5f7fa;color:#1f2937;font-family:Vazirmatn,Tahoma,sans-serif}.wrap{max-width:850px;margin:35px auto;padding:0 16px}.head{text-align:center;margin-bottom:24px}.head img{height:65px}.head h1{color:#17365d;font-size:22px;margin:9px 0 4px}.head p{color:#98a2b3;font-size:10px;margin:0}.item{background:#fff;border:1px solid #e4e7ec;border-radius:15px;padding:19px;margin-bottom:13px;box-shadow:0 5px 18px rgba(16,24,40,.04)}.item h2{margin:0;color:#17365d;font-size:15px}.date{color:#98a2b3;font-size:9px;margin-top:5px}.body{color:#475467;font-size:11px;line-height:2.1;margin-top:13px;white-space:pre-wrap}.back{display:inline-block;margin-bottom:15px;color:#3159a6;text-decoration:none;font-size:10px}.empty{text-align:center;background:#fff;border:1px solid #e4e7ec;border-radius:15px;padding:45px 20px;color:#98a2b3;font-size:11px}
</style></head><body><div class="wrap"><div class="head"><img src="/neal/images/logo.png" alt="NEAL Pharmed"><h1>اطلاعیه‌های شرکت</h1><p>آخرین اطلاعیه‌های NEAL Pharmed</p></div><a class="back" href="/neal/login.php">بازگشت به صفحه ورود</a>
<?php if($items): foreach($items as $item): ?><article class="item"><h2><?php echo pub_h($item['title']); ?></h2><?php if($item['published_at']): ?><div class="date"><?php echo pub_h(pub_date($item['published_at'])); ?></div><?php endif; ?><div class="body"><?php echo pub_h($item['body']); ?></div></article><?php endforeach; else: ?><div class="empty">در حال حاضر اطلاعیه‌ای برای نمایش وجود ندارد.</div><?php endif; ?>
</div></body></html>