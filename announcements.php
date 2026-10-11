<?php
require_once __DIR__ . "/config/config.php";
require_once __DIR__ . "/auth/auth.php";
require_once __DIR__ . "/includes/user-profile.php";

check_login();

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

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['csrf_token'];
$error = "";
$success = "";

function ann_h($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

function ann_datetime_local($v) {
    if (!$v) return '';
    return date('Y-m-d\TH:i', strtotime($v));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($csrf, $_POST['csrf_token'] ?? '')) {
        $error = 'درخواست نامعتبر است. صفحه را تازه‌سازی کنید.';
    } else {
        $action = $_POST['action'] ?? '';
        $id = (int)($_POST['id'] ?? 0);

        try {
            if ($action === 'save') {
                $title = trim($_POST['title'] ?? '');
                $body = trim($_POST['body'] ?? '');
                $published = trim($_POST['published_at'] ?? '');
                $expires = trim($_POST['expires_at'] ?? '');
                $active = isset($_POST['is_active']) ? 1 : 0;

                if ($title === '') {
                    throw new RuntimeException('عنوان اطلاعیه را وارد کنید.');
                }
                if ($body === '') {
                    throw new RuntimeException('متن اطلاعیه را وارد کنید.');
                }

                $publishedDb = $published !== '' ? date('Y-m-d H:i:s', strtotime($published)) : null;
                $expiresDb = $expires !== '' ? date('Y-m-d H:i:s', strtotime($expires)) : null;

                if ($id > 0) {
                    $stmt = $db->prepare("UPDATE announcements
                        SET title=?, body=?, is_active=?, published_at=?, expires_at=?, updated_at=CURRENT_TIMESTAMP
                        WHERE id=?");
                    $stmt->execute([$title, $body, $active, $publishedDb, $expiresDb, $id]);
                    $success = 'اطلاعیه با موفقیت ویرایش شد.';
                } else {
                    $createdBy = $_SESSION['admin_id'] ?? null;
                    $stmt = $db->prepare("INSERT INTO announcements
                        (title, body, is_active, published_at, expires_at, created_by)
                        VALUES (?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$title, $body, $active, $publishedDb, $expiresDb, $createdBy]);
                    $success = 'اطلاعیه جدید ثبت شد.';
                }
            } elseif ($action === 'toggle' && $id > 0) {
                $stmt = $db->prepare("UPDATE announcements SET is_active = CASE WHEN is_active=1 THEN 0 ELSE 1 END, updated_at=CURRENT_TIMESTAMP WHERE id=?");
                $stmt->execute([$id]);
                $success = 'وضعیت اطلاعیه تغییر کرد.';
            } elseif ($action === 'delete' && $id > 0) {
                $stmt = $db->prepare("DELETE FROM announcements WHERE id=?");
                $stmt->execute([$id]);
                $success = 'اطلاعیه حذف شد.';
            }
        } catch (Throwable $e) {
            $error = $e instanceof RuntimeException ? $e->getMessage() : 'عملیات انجام نشد.';
        }
    }
}

$edit = null;
if (isset($_GET['edit'])) {
    $stmt = $db->prepare("SELECT * FROM announcements WHERE id=?");
    $stmt->execute([(int)$_GET['edit']]);
    $edit = $stmt->fetch() ?: null;
}

$announcements = $db->query("SELECT * FROM announcements ORDER BY COALESCE(published_at, created_at) DESC, id DESC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>اطلاعیه‌های شرکت | NEAL</title>
<style>
@font-face{font-family:Vazirmatn;src:url('/neal/fonts/Vazirmatn-Regular.woff2') format('woff2');font-weight:400}
@font-face{font-family:Vazirmatn;src:url('/neal/fonts/Vazirmatn-SemiBold.woff2') format('woff2');font-weight:600}
*{box-sizing:border-box}body{margin:0;background:#f5f7fa;color:#1f2937;font-family:Vazirmatn,Tahoma,sans-serif}.wrap{max-width:1100px;margin:28px auto;padding:0 18px}.top{display:flex;justify-content:space-between;align-items:center;margin-bottom:18px}.top h1{margin:0;color:#17365d;font-size:22px}.top a{color:#3159a6;text-decoration:none;font-size:11px}.panel{background:#fff;border:1px solid #e4e7ec;border-radius:16px;padding:20px;box-shadow:0 6px 20px rgba(16,24,40,.05);margin-bottom:18px}.panel h2{margin:0 0 15px;font-size:15px;color:#17365d}.grid{display:grid;grid-template-columns:1fr 1fr;gap:12px}.field label{display:block;font-size:10px;font-weight:600;color:#344054;margin-bottom:5px}.field input,.field textarea{width:100%;border:1px solid #d0d5dd;border-radius:9px;padding:9px 10px;font:inherit;font-size:11px}.field textarea{min-height:125px;resize:vertical}.full{grid-column:1/-1}.check{display:flex;align-items:center;gap:7px;font-size:10px;color:#344054;margin-top:9px}.actions{display:flex;gap:8px;margin-top:14px}.btn{border:0;border-radius:9px;padding:9px 16px;font:inherit;font-size:10px;font-weight:600;cursor:pointer;text-decoration:none}.primary{background:#3159a6;color:#fff}.muted{background:#f2f4f7;color:#344054}.danger{background:#fef3f2;color:#b42318}.notice{padding:10px 12px;border-radius:9px;font-size:10px;margin-bottom:14px}.ok{background:#ecfdf3;color:#067647}.err{background:#fef3f2;color:#b42318}.table-wrap{overflow:auto}.list{width:100%;border-collapse:collapse;min-width:720px}.list th,.list td{text-align:right;padding:11px 9px;border-bottom:1px solid #eaecf0;font-size:10px;vertical-align:top}.list th{color:#667085;background:#f9fafb}.list td strong{font-size:11px;color:#1d2939}.badge{display:inline-block;padding:4px 7px;border-radius:999px;font-size:8px}.on{background:#ecfdf3;color:#067647}.off{background:#f2f4f7;color:#667085}.row-actions{display:flex;gap:5px;flex-wrap:wrap}.row-actions form{display:inline}.empty{text-align:center;padding:28px;color:#98a2b3;font-size:10px}@media(max-width:700px){.grid{grid-template-columns:1fr}.full{grid-column:auto}.top{align-items:flex-start;gap:10px;flex-direction:column}}
</style>
</head>
<body>
<div class="wrap">
<div class="top"><h1>اطلاعیه‌های شرکت</h1><a href="/neal/">بازگشت به پنل مدیریت</a></div>
<?php if($success): ?><div class="notice ok"><?php echo ann_h($success); ?></div><?php endif; ?>
<?php if($error): ?><div class="notice err"><?php echo ann_h($error); ?></div><?php endif; ?>

<div class="panel">
<h2><?php echo $edit ? 'ویرایش اطلاعیه' : 'ثبت اطلاعیه جدید'; ?></h2>
<form method="post">
<input type="hidden" name="csrf_token" value="<?php echo ann_h($csrf); ?>">
<input type="hidden" name="action" value="save">
<input type="hidden" name="id" value="<?php echo (int)($edit['id'] ?? 0); ?>">
<div class="grid">
<div class="field full"><label>عنوان اطلاعیه</label><input name="title" required value="<?php echo ann_h($edit['title'] ?? ''); ?>"></div>
<div class="field full"><label>متن اطلاعیه</label><textarea name="body" required><?php echo ann_h($edit['body'] ?? ''); ?></textarea></div>
<div class="field"><label>تاریخ و زمان انتشار</label><input type="datetime-local" name="published_at" value="<?php echo ann_h(ann_datetime_local($edit['published_at'] ?? '')); ?>"></div>
<div class="field"><label>تاریخ و زمان پایان نمایش</label><input type="datetime-local" name="expires_at" value="<?php echo ann_h(ann_datetime_local($edit['expires_at'] ?? '')); ?>"></div>
</div>
<label class="check"><input type="checkbox" name="is_active" value="1" <?php echo (!$edit || (int)$edit['is_active']===1)?'checked':''; ?>> اطلاعیه فعال باشد</label>
<div class="actions"><button class="btn primary" type="submit"><?php echo $edit?'ذخیره تغییرات':'ثبت اطلاعیه'; ?></button><?php if($edit): ?><a class="btn muted" href="/neal/announcements.php">انصراف</a><?php endif; ?></div>
</form>
</div>

<div class="panel">
<h2>فهرست اطلاعیه‌ها</h2>
<div class="table-wrap">
<?php if($announcements): ?>
<table class="list"><thead><tr><th>عنوان</th><th>انتشار</th><th>پایان</th><th>وضعیت</th><th>عملیات</th></tr></thead><tbody>
<?php foreach($announcements as $a): ?>
<tr>
<td><strong><?php echo ann_h($a['title']); ?></strong><div style="color:#667085;margin-top:4px;line-height:1.8"><?php echo ann_h(mb_strimwidth(preg_replace('/\s+/u',' ',strip_tags($a['body'])),0,100,'…','UTF-8')); ?></div></td>
<td><?php echo ann_h($a['published_at'] ?: 'بلافاصله'); ?></td>
<td><?php echo ann_h($a['expires_at'] ?: 'بدون پایان'); ?></td>
<td><span class="badge <?php echo (int)$a['is_active']===1?'on':'off'; ?>"><?php echo (int)$a['is_active']===1?'فعال':'غیرفعال'; ?></span></td>
<td><div class="row-actions"><a class="btn muted" href="?edit=<?php echo (int)$a['id']; ?>">ویرایش</a>
<form method="post"><input type="hidden" name="csrf_token" value="<?php echo ann_h($csrf); ?>"><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?php echo (int)$a['id']; ?>"><button class="btn muted" type="submit"><?php echo (int)$a['is_active']===1?'غیرفعال':'فعال'; ?></button></form>
<form method="post" onsubmit="return confirm('این اطلاعیه حذف شود؟');"><input type="hidden" name="csrf_token" value="<?php echo ann_h($csrf); ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?php echo (int)$a['id']; ?>"><button class="btn danger" type="submit">حذف</button></form></div></td>
</tr>
<?php endforeach; ?>
</tbody></table>
<?php else: ?><div class="empty">هنوز اطلاعیه‌ای ثبت نشده است.</div><?php endif; ?>
</div>
</div>
</div>
</body>
</html>