<?php
$portal_mode = true;
require_once __DIR__ . "/auth/auth.php";
check_user_login();
require_once __DIR__ . "/includes/user-profile.php";

$page_title = "لینک‌های کاربردی";
$db = neal_profile_db();
$db->exec("CREATE TABLE IF NOT EXISTS quick_links (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    title TEXT NOT NULL,
    url TEXT NOT NULL,
    description TEXT NOT NULL DEFAULT '',
    category TEXT NOT NULL DEFAULT 'عمومی',
    icon TEXT NOT NULL DEFAULT '🔗',
    is_active INTEGER NOT NULL DEFAULT 1,
    sort_order INTEGER NOT NULL DEFAULT 0,
    created_by INTEGER NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
)");
$links = $db->query("SELECT id,title,url,description,category,icon FROM quick_links WHERE is_active=1 ORDER BY category COLLATE NOCASE ASC, sort_order ASC, title COLLATE NOCASE ASC, id DESC")->fetchAll(PDO::FETCH_ASSOC);
function qlu_h($value) { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
$categories = [];
foreach ($links as $link) {
    $category = trim((string)$link['category']) ?: 'عمومی';
    $categories[$category][] = $link;
}
?>
<?php include __DIR__ . "/includes/header.php"; ?>
<style>
.ql-user{max-width:1240px;margin:0 auto;padding:8px 4px 38px;color:#1d2939;font-family:Vazirmatn,Tahoma,sans-serif}
.ql-user-hero{display:flex;align-items:center;justify-content:space-between;gap:18px;padding:28px 30px;border-radius:22px;background:linear-gradient(125deg,#102a43,#1e4970);color:#fff;box-shadow:0 14px 32px rgba(16,42,67,.14);margin-bottom:22px}.ql-user-hero h1{font-size:24px;margin:0 0 8px}.ql-user-hero p{font-size:12px;line-height:2;margin:0;color:#d5e3f1}.ql-user-hero-icon{width:70px;height:70px;display:grid;place-items:center;border:1px solid rgba(255,255,255,.25);background:rgba(255,255,255,.09);border-radius:20px;font-size:32px;flex-shrink:0}
.ql-user-summary{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:14px 18px;margin-bottom:18px;background:#fff;border:1px solid #e5eaf1;border-radius:14px;color:#667085;font-size:11px}.ql-user-summary strong{color:#17365d;font-size:15px}
.ql-user-category{margin-bottom:22px;background:#fff;border:1px solid #e5eaf1;border-radius:18px;box-shadow:0 5px 18px rgba(16,24,40,.035);overflow:hidden}.ql-user-category-head{display:flex;align-items:center;justify-content:space-between;gap:10px;padding:17px 21px;background:#f8fafc;border-bottom:1px solid #e7edf4}.ql-user-category-head h2{font-size:14px;margin:0;color:#17365d}.ql-user-category-head span{font-size:10px;color:#98a2b3}
.ql-user-table-wrap{overflow-x:auto}.ql-user-table{width:100%;border-collapse:separate;border-spacing:0;min-width:560px}.ql-user-table th{background:#fff;color:#667085;font-size:10px;font-weight:700;text-align:right;padding:13px 17px;border-bottom:1px solid #e7edf4;white-space:nowrap}.ql-user-table td{padding:15px 17px;border-bottom:1px solid #eef1f5;font-size:11px;color:#475467;vertical-align:middle}.ql-user-table tbody tr:last-child td{border-bottom:0}.ql-user-table tbody tr:hover{background:#fbfdff}.ql-user-link{display:flex;align-items:center;gap:12px;min-width:210px}.ql-user-link-icon{width:42px;height:42px;display:grid;place-items:center;border-radius:13px;background:#edf4fb;font-size:20px;flex-shrink:0}.ql-user-link strong{display:block;color:#17365d;font-size:12px}.ql-user-link small{display:block;color:#98a2b3;font-size:9px;margin-top:4px;max-width:330px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.ql-user-description{line-height:1.9;min-width:150px;max-width:400px}.ql-open-link{display:inline-flex;align-items:center;justify-content:center;gap:8px;white-space:nowrap;text-decoration:none;background:#edf4fb;color:#1c527c;border:1px solid #d9e8f5;border-radius:10px;padding:9px 12px;font:600 10px Vazirmatn,Tahoma,sans-serif;transition:.15s}.ql-open-link:hover{background:#dcebf8;border-color:#b8d4eb}.ql-empty-user{text-align:center;background:#fff;border:1px solid #e5eaf1;border-radius:18px;padding:55px 20px;color:#667085;box-shadow:0 5px 18px rgba(16,24,40,.035)}.ql-empty-user b{display:block;font-size:35px;margin-bottom:12px}.ql-empty-user strong{display:block;color:#17365d;font-size:15px;margin-bottom:6px}.ql-empty-user p{font-size:11px;margin:0}
@media(max-width:650px){.ql-user-hero{padding:21px;border-radius:17px}.ql-user-hero h1{font-size:20px}.ql-user-hero-icon{width:53px;height:53px;font-size:24px}.ql-user-summary{align-items:flex-start;flex-direction:column}.ql-user-category-head{padding:14px 16px}}
</style>
<div class="ql-user">
<section class="ql-user-hero"><div><h1>🔗 لینک‌های کاربردی شرکت</h1><p>دسترسی سریع به سامانه‌ها و وب‌سایت‌های موردنیاز همکاران NEAL Pharmed.<br>هر لینک در یک زبانه جدید مرورگر باز می‌شود.</p></div><div class="ql-user-hero-icon">🌐</div></section>
<div class="ql-user-summary"><span>سامانه‌ها و منابع در دسترس شما</span><span>تعداد لینک‌های فعال: <strong><?php echo count($links); ?></strong></span></div>
<?php if ($links): foreach ($categories as $category => $items): ?>
<section class="ql-user-category"><div class="ql-user-category-head"><h2>📁 <?php echo qlu_h($category); ?></h2><span><?php echo count($items); ?> لینک</span></div>
<div class="ql-user-table-wrap"><table class="ql-user-table"><thead><tr><th>نام سامانه / لینک</th><th>توضیحات</th><th>دسترسی</th></tr></thead><tbody>
<?php foreach ($items as $item): ?><tr><td><div class="ql-user-link"><span class="ql-user-link-icon"><?php echo qlu_h($item['icon']); ?></span><div><strong><?php echo qlu_h($item['title']); ?></strong><small title="<?php echo qlu_h($item['url']); ?>"><?php echo qlu_h($item['url']); ?></small></div></div></td><td><div class="ql-user-description"><?php echo qlu_h($item['description'] ?: '—'); ?></div></td><td><a class="ql-open-link" href="<?php echo qlu_h($item['url']); ?>" target="_blank" rel="noopener noreferrer">باز کردن لینک <span aria-hidden="true">↗</span></a></td></tr><?php endforeach; ?>
</tbody></table></div></section>
<?php endforeach; else: ?><div class="ql-empty-user"><b>🔗</b><strong>هنوز لینکی برای نمایش ثبت نشده است</strong><p>پس از ثبت لینک‌ها توسط مدیر سیستم، فهرست آن‌ها در این بخش نمایش داده می‌شود.</p></div><?php endif; ?>
</div>
<?php include __DIR__ . "/includes/footer.php"; ?>
