<?php
require_once __DIR__ . "/auth/auth.php";
check_login();
require_once __DIR__ . "/includes/user-profile.php";

$page_title = "مدیریت لینک‌های کاربردی";
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

if (empty($_SESSION['neal_quick_links_csrf'])) {
    $_SESSION['neal_quick_links_csrf'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['neal_quick_links_csrf'];

function ql_h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}
function ql_csrf_ok($token) {
    return isset($_SESSION['neal_quick_links_csrf']) && is_string($token)
        && hash_equals($_SESSION['neal_quick_links_csrf'], $token);
}
function ql_valid_url($url) {
    if (!filter_var($url, FILTER_VALIDATE_URL)) return false;
    $scheme = strtolower((string)parse_url($url, PHP_URL_SCHEME));
    return in_array($scheme, ['http', 'https'], true);
}

$message = '';
$message_type = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!ql_csrf_ok($_POST['csrf_token'] ?? '')) {
        $message = 'درخواست معتبر نیست. صفحه را تازه‌سازی کرده و دوباره تلاش کنید.';
        $message_type = 'error';
    } else {
        $action = (string)($_POST['action'] ?? '');
        $id = (int)($_POST['id'] ?? 0);

        if ($action === 'save') {
            $title = trim((string)($_POST['title'] ?? ''));
            $url = trim((string)($_POST['url'] ?? ''));
            $description = trim((string)($_POST['description'] ?? ''));
            $category = trim((string)($_POST['category'] ?? 'عمومی'));
            $icon = trim((string)($_POST['icon'] ?? '🔗'));
            $sortOrder = filter_var($_POST['sort_order'] ?? 0, FILTER_VALIDATE_INT);
            $active = isset($_POST['is_active']) ? 1 : 0;

            if ($title === '' || mb_strlen($title) > 120) {
                $message = 'عنوان لینک را وارد کنید (حداکثر ۱۲۰ نویسه).';
                $message_type = 'error';
            } elseif (!ql_valid_url($url)) {
                $message = 'آدرس معتبر با شروع http:// یا https:// وارد کنید.';
                $message_type = 'error';
            } elseif (mb_strlen($url) > 2048 || mb_strlen($description) > 300 || mb_strlen($category) > 80 || mb_strlen($icon) > 16) {
                $message = 'طول یکی از فیلدها بیش از حد مجاز است.';
                $message_type = 'error';
            } else {
                $sortOrder = $sortOrder === false ? 0 : (int)$sortOrder;
                if ($id > 0) {
                    $stmt = $db->prepare("UPDATE quick_links SET title=?, url=?, description=?, category=?, icon=?, is_active=?, sort_order=?, updated_at=CURRENT_TIMESTAMP WHERE id=?");
                    $stmt->execute([$title, $url, $description, $category ?: 'عمومی', $icon ?: '🔗', $active, $sortOrder, $id]);
                    $message = 'لینک با موفقیت ویرایش شد.';
                } else {
                    $stmt = $db->prepare("INSERT INTO quick_links (title,url,description,category,icon,is_active,sort_order,created_by) VALUES (?,?,?,?,?,?,?,?)");
                    $stmt->execute([$title, $url, $description, $category ?: 'عمومی', $icon ?: '🔗', $active, $sortOrder, (int)($_SESSION['admin_id'] ?? 0)]);
                    $message = 'لینک کاربردی جدید با موفقیت ثبت شد.';
                }
            }
        } elseif ($action === 'toggle' && $id > 0) {
            $stmt = $db->prepare("UPDATE quick_links SET is_active=CASE WHEN is_active=1 THEN 0 ELSE 1 END, updated_at=CURRENT_TIMESTAMP WHERE id=?");
            $stmt->execute([$id]);
            $message = 'وضعیت نمایش لینک تغییر کرد.';
        } elseif ($action === 'delete' && $id > 0) {
            $stmt = $db->prepare("DELETE FROM quick_links WHERE id=?");
            $stmt->execute([$id]);
            $message = 'لینک حذف شد.';
        } else {
            $message = 'عملیات درخواستی معتبر نیست.';
            $message_type = 'error';
        }
    }
}

$edit = null;
if (isset($_GET['edit'])) {
    $stmt = $db->prepare("SELECT * FROM quick_links WHERE id=?");
    $stmt->execute([(int)$_GET['edit']]);
    $edit = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}
$links = $db->query("SELECT * FROM quick_links ORDER BY sort_order ASC, title COLLATE NOCASE ASC, id DESC")->fetchAll(PDO::FETCH_ASSOC);
$activeCount = count(array_filter($links, function($item) { return (int)$item['is_active'] === 1; }));
?>
<?php include __DIR__ . "/includes/header.php"; ?>
<style>
.ql-admin{max-width:1440px;margin:0 auto;padding:8px 4px 34px;color:#1d2939;font-family:Vazirmatn,Tahoma,sans-serif}
.ql-hero{display:flex;align-items:center;justify-content:space-between;gap:18px;padding:26px 30px;border-radius:22px;background:linear-gradient(125deg,#102a43,#1e4970);color:#fff;box-shadow:0 14px 32px rgba(16,42,67,.14);margin-bottom:22px}
.ql-hero h1{font-size:23px;margin:0 0 7px}.ql-hero p{font-size:12px;line-height:1.9;margin:0;color:#d5e3f1}.ql-hero-mark{width:66px;height:66px;display:grid;place-items:center;border:1px solid rgba(255,255,255,.25);background:rgba(255,255,255,.09);border-radius:19px;font-size:30px;flex-shrink:0}
.ql-stats{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px;margin-bottom:20px}.ql-stat{padding:17px 20px;background:#fff;border:1px solid #e7edf4;border-radius:16px;box-shadow:0 4px 15px rgba(16,24,40,.035)}.ql-stat small{display:block;color:#667085;font-size:11px}.ql-stat strong{display:block;font-size:25px;color:#17365d;margin-top:4px}
.ql-layout{display:grid;grid-template-columns:minmax(300px,.8fr) minmax(0,1.5fr);gap:18px;align-items:start}.ql-panel{background:#fff;border:1px solid #e5eaf1;border-radius:18px;box-shadow:0 5px 18px rgba(16,24,40,.035);overflow:hidden}.ql-panel-head{padding:18px 21px;border-bottom:1px solid #edf0f4;display:flex;align-items:center;justify-content:space-between;gap:10px}.ql-panel-head h2{font-size:15px;margin:0;color:#17365d}.ql-panel-head span{font-size:10px;color:#98a2b3}.ql-form{padding:21px}.ql-form label{display:block;font-size:11px;color:#344054;font-weight:600;margin:0 0 7px}.ql-form input[type=text],.ql-form input[type=url],.ql-form input[type=number],.ql-form textarea{display:block;width:100%;border:1px solid #d0d5dd;border-radius:10px;padding:11px 12px;font:12px Vazirmatn,Tahoma,sans-serif;background:#fff;color:#101828;outline:none;margin-bottom:15px;transition:border-color .15s,box-shadow .15s}.ql-form textarea{resize:vertical;min-height:78px;line-height:1.9}.ql-form input:focus,.ql-form textarea:focus{border-color:#5285b8;box-shadow:0 0 0 3px rgba(82,133,184,.12)}.ql-form-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px}.ql-check{display:flex!important;align-items:center;gap:8px;font-weight:400!important;margin:2px 0 17px!important}.ql-check input{accent-color:#1d5b8f}.ql-actions{display:flex;gap:8px;flex-wrap:wrap}.ql-btn{display:inline-flex;align-items:center;justify-content:center;gap:7px;border:0;border-radius:10px;padding:10px 15px;text-decoration:none;cursor:pointer;font:600 11px Vazirmatn,Tahoma,sans-serif}.ql-btn-primary{background:#173f65;color:#fff}.ql-btn-secondary{background:#f2f4f7;color:#475467}.ql-btn-danger{background:#fff0ef;color:#b42318}.ql-alert{padding:12px 15px;border-radius:12px;margin:0 0 17px;font-size:11px}.ql-alert.success{background:#ecfdf3;color:#027a48;border:1px solid #abefc6}.ql-alert.error{background:#fef3f2;color:#b42318;border:1px solid #fecdca}
.ql-table-wrap{overflow-x:auto}.ql-table{width:100%;border-collapse:separate;border-spacing:0;min-width:680px}.ql-table th{background:#f8fafc;color:#667085;font-size:10px;font-weight:700;text-align:right;padding:13px 12px;border-bottom:1px solid #e7edf4;white-space:nowrap}.ql-table td{padding:13px 12px;border-bottom:1px solid #eef1f5;font-size:11px;color:#344054;vertical-align:middle}.ql-table tbody tr:hover{background:#fbfcfe}.ql-table tbody tr:last-child td{border-bottom:0}.ql-link-title{display:flex;align-items:center;gap:10px;min-width:180px}.ql-icon{width:37px;height:37px;display:grid;place-items:center;border-radius:12px;background:#edf4fb;font-size:18px;flex-shrink:0}.ql-link-title strong{display:block;color:#17365d;font-size:11px}.ql-link-title small{display:block;color:#98a2b3;font-size:9px;max-width:260px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;margin-top:3px}.ql-category{display:inline-flex;background:#f2f4f7;color:#475467;border-radius:999px;padding:5px 9px;font-size:9px;white-space:nowrap}.ql-status{display:inline-flex;border-radius:999px;padding:5px 9px;font-size:9px}.ql-status.on{background:#ecfdf3;color:#027a48}.ql-status.off{background:#f2f4f7;color:#667085}.ql-row-actions{display:flex;align-items:center;gap:5px;flex-wrap:wrap}.ql-row-actions .ql-btn{padding:7px 9px;font-size:10px}.ql-empty{text-align:center;padding:40px 20px;color:#98a2b3;font-size:11px}.ql-empty b{display:block;font-size:26px;margin-bottom:8px}
@media(max-width:1050px){.ql-layout{grid-template-columns:1fr}.ql-form{max-width:none}}@media(max-width:600px){.ql-hero{padding:20px;border-radius:16px}.ql-hero h1{font-size:19px}.ql-hero-mark{width:50px;height:50px;font-size:23px}.ql-stats{gap:9px}.ql-stat{padding:13px}.ql-stat strong{font-size:21px}.ql-form-grid{grid-template-columns:1fr;gap:0}.ql-panel-head{padding:15px}.ql-form{padding:16px}}
</style>
<div class="ql-admin">
<section class="ql-hero"><div><h1>🔗 مدیریت لینک‌های کاربردی</h1><p>لینک‌های پرکاربرد شرکت را ثبت، مرتب‌سازی و مدیریت کنید. کاربران فقط لینک‌های فعال را مشاهده می‌کنند.</p></div><div class="ql-hero-mark">🌐</div></section>
<?php if ($message !== ''): ?><div class="ql-alert <?php echo ql_h($message_type); ?>"><?php echo ql_h($message); ?></div><?php endif; ?>
<div class="ql-stats"><div class="ql-stat"><small>مجموع لینک‌های ثبت‌شده</small><strong><?php echo count($links); ?></strong></div><div class="ql-stat"><small>لینک‌های قابل مشاهده برای کاربران</small><strong><?php echo $activeCount; ?></strong></div></div>
<div class="ql-layout">
<section class="ql-panel">
<div class="ql-panel-head"><h2><?php echo $edit ? '✏️ ویرایش لینک' : '＋ افزودن لینک جدید'; ?></h2><span>مدیریت محتوا</span></div>
<form class="ql-form" method="post" action="quick-links.php">
<input type="hidden" name="csrf_token" value="<?php echo ql_h($csrf); ?>"><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?php echo (int)($edit['id'] ?? 0); ?>">
<label for="ql-title">عنوان لینک</label><input id="ql-title" type="text" name="title" maxlength="120" required value="<?php echo ql_h($edit['title'] ?? ''); ?>" placeholder="مثلاً: اتوماسیون اداری">
<label for="ql-url">آدرس کامل سایت</label><input id="ql-url" type="url" name="url" maxlength="2048" required value="<?php echo ql_h($edit['url'] ?? ''); ?>" placeholder="https://intranet.example.local">
<label for="ql-description">توضیح کوتاه</label><textarea id="ql-description" name="description" maxlength="300" placeholder="کاربرد این سامانه یا لینک را بنویسید"><?php echo ql_h($edit['description'] ?? ''); ?></textarea>
<div class="ql-form-grid"><div><label for="ql-category">دسته‌بندی</label><input id="ql-category" type="text" name="category" maxlength="80" value="<?php echo ql_h($edit['category'] ?? 'عمومی'); ?>" placeholder="مثلاً: سامانه‌های داخلی"></div><div><label for="ql-icon">آیکون یا ایموجی</label><input id="ql-icon" type="text" name="icon" maxlength="16" value="<?php echo ql_h($edit['icon'] ?? '🔗'); ?>" placeholder="🔗"></div></div>
<div class="ql-form-grid"><div><label for="ql-sort">ترتیب نمایش</label><input id="ql-sort" type="number" name="sort_order" value="<?php echo (int)($edit['sort_order'] ?? 0); ?>"></div><div style="display:flex;align-items:end"><label class="ql-check"><input type="checkbox" name="is_active" value="1" <?php echo !isset($edit['is_active']) || (int)$edit['is_active'] === 1 ? 'checked' : ''; ?>> برای کاربران نمایش داده شود</label></div></div>
<div class="ql-actions"><button class="ql-btn ql-btn-primary" type="submit"><?php echo $edit ? 'ذخیره تغییرات' : '＋ ثبت لینک'; ?></button><?php if ($edit): ?><a class="ql-btn ql-btn-secondary" href="quick-links.php">لغو ویرایش</a><?php endif; ?></div>
</form></section>
<section class="ql-panel"><div class="ql-panel-head"><h2>📋 فهرست لینک‌ها</h2><span><?php echo count($links); ?> مورد</span></div>
<?php if ($links): ?><div class="ql-table-wrap"><table class="ql-table"><thead><tr><th>عنوان / آدرس</th><th>دسته‌بندی</th><th>وضعیت</th><th>ترتیب</th><th>عملیات</th></tr></thead><tbody>
<?php foreach ($links as $item): ?><tr><td><div class="ql-link-title"><span class="ql-icon"><?php echo ql_h($item['icon']); ?></span><div><strong><?php echo ql_h($item['title']); ?></strong><small title="<?php echo ql_h($item['url']); ?>"><?php echo ql_h($item['url']); ?></small></div></div></td><td><span class="ql-category"><?php echo ql_h($item['category']); ?></span></td><td><span class="ql-status <?php echo (int)$item['is_active'] === 1 ? 'on' : 'off'; ?>"><?php echo (int)$item['is_active'] === 1 ? 'فعال' : 'غیرفعال'; ?></span></td><td><?php echo (int)$item['sort_order']; ?></td><td><div class="ql-row-actions"><a class="ql-btn ql-btn-secondary" href="quick-links.php?edit=<?php echo (int)$item['id']; ?>">ویرایش</a><form method="post" action="quick-links.php" onsubmit="return confirm('این لینک حذف شود؟');"><input type="hidden" name="csrf_token" value="<?php echo ql_h($csrf); ?>"><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?php echo (int)$item['id']; ?>"><button class="ql-btn ql-btn-secondary" type="submit"><?php echo (int)$item['is_active'] === 1 ? 'غیرفعال‌سازی' : 'فعال‌سازی'; ?></button></form><form method="post" action="quick-links.php" onsubmit="return confirm('حذف این لینک قابل بازگشت نیست. ادامه می‌دهید؟');"><input type="hidden" name="csrf_token" value="<?php echo ql_h($csrf); ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?php echo (int)$item['id']; ?>"><button class="ql-btn ql-btn-danger" type="submit">حذف</button></form></div></td></tr><?php endforeach; ?>
</tbody></table></div><?php else: ?><div class="ql-empty"><b>🔗</b>هنوز لینکی ثبت نشده است.<br>از فرم کنار صفحه اولین لینک کاربردی را اضافه کنید.</div><?php endif; ?>
</section></div></div>
<?php include __DIR__ . "/includes/footer.php"; ?>
