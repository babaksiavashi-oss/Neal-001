<?php
require_once "auth/auth.php";
check_login();
require_once "includes/user-profile.php";

$page_title = "مدیریت مناسبت‌ها";
$db = neal_profile_db();

$db->exec("CREATE TABLE IF NOT EXISTS occasions (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    title TEXT NOT NULL,
    body TEXT NOT NULL DEFAULT '',
    occasion_type TEXT NOT NULL DEFAULT 'general',
    event_date TEXT NOT NULL DEFAULT '',
    is_active INTEGER NOT NULL DEFAULT 1,
    created_by INTEGER NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
)");

if (empty($_SESSION['neal_occasions_csrf'])) {
    $_SESSION['neal_occasions_csrf'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['neal_occasions_csrf'];

function oh($v) {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}
function occasion_csrf_ok($token) {
    return isset($_SESSION['neal_occasions_csrf']) && is_string($token)
        && hash_equals($_SESSION['neal_occasions_csrf'], $token);
}

function oc_datetime_local($v) {
    if (!$v) return '';
    $ts = strtotime((string)$v);
    return $ts ? date('Y-m-d\\TH:i', $ts) : '';
}

$message = "";
$message_type = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!occasion_csrf_ok($_POST['csrf_token'] ?? '')) {
        $message = "درخواست نامعتبر است. صفحه را تازه‌سازی کرده و دوباره تلاش کنید.";
        $message_type = "error";
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'save') {
            $id = (int)($_POST['id'] ?? 0);
            $title = trim((string)($_POST['title'] ?? ''));
            $body = trim((string)($_POST['body'] ?? ''));
            $type = trim((string)($_POST['occasion_type'] ?? 'general'));
            $date = trim((string)($_POST['event_date'] ?? ''));
            $active = isset($_POST['is_active']) ? 1 : 0;

            $dateDb = null;
            if ($date !== '') {
                $dateTs = strtotime($date);
                $dateDb = $dateTs ? date('Y-m-d H:i:s', $dateTs) : $date;
            }

            if ($title === '') {
                $message = "عنوان مناسبت را وارد کنید.";
                $message_type = "error";
            } elseif (mb_strlen($title) > 150 || mb_strlen($body) > 500 || mb_strlen($date) > 30) {
                $message = "یکی از فیلدها بیش از حد مجاز طولانی است.";
                $message_type = "error";
            } elseif ($id > 0) {
                $s = $db->prepare("UPDATE occasions SET title=?, body=?, occasion_type=?, event_date=?, is_active=? WHERE id=?");
                $s->execute([$title, $body, $type ?: 'general', $dateDb ?? '', $active, $id]);
                $message = "مناسبت با موفقیت ویرایش شد.";
                $message_type = "success";
            } else {
                $s = $db->prepare("INSERT INTO occasions (title,body,occasion_type,event_date,is_active,created_by) VALUES (?,?,?,?,?,?)");
                $s->execute([$title, $body, $type ?: 'general', $dateDb ?? '', $active, (int)($_SESSION['admin_id'] ?? 0)]);
                $message = "مناسبت جدید با موفقیت ثبت شد.";
                $message_type = "success";
            }
        } elseif ($action === 'toggle') {
            $id = (int)($_POST['id'] ?? 0);
            $s = $db->prepare("UPDATE occasions SET is_active=CASE WHEN is_active=1 THEN 0 ELSE 1 END WHERE id=?");
            $s->execute([$id]);
            $message = "وضعیت مناسبت تغییر کرد.";
            $message_type = "success";
        } elseif ($action === 'delete') {
            $id = (int)($_POST['id'] ?? 0);
            $s = $db->prepare("DELETE FROM occasions WHERE id=?");
            $s->execute([$id]);
            $message = "مناسبت حذف شد.";
            $message_type = "success";
        }
    }
}

$edit = null;
if (isset($_GET['edit'])) {
    $s = $db->prepare("SELECT * FROM occasions WHERE id=?");
    $s->execute([(int)$_GET['edit']]);
    $edit = $s->fetch() ?: null;
}

$occasions = $db->query("SELECT * FROM occasions ORDER BY is_active DESC, id DESC")->fetchAll();
?>
<?php require_once "includes/header.php"; ?>

<div class="oc-page">
<div class="oc-head">
<div>
<h1>مدیریت مناسبت‌ها</h1>
<p>تولد همکاران، مناسبت‌های تقویمی و رویدادهای داخلی شرکت را مدیریت کنید.</p>
</div>
<a class="oc-back" href="/neal/">بازگشت به داشبورد</a>
</div>

<?php if ($message): ?>
<div class="oc-alert <?php echo $message_type === 'success' ? 'success' : 'error'; ?>"><?php echo oh($message); ?></div>
<?php endif; ?>

<div class="oc-grid">
<div class="oc-card">
<h2><?php echo $edit ? 'ویرایش مناسبت' : 'افزودن مناسبت جدید'; ?></h2>
<form method="post">
<input type="hidden" name="csrf_token" value="<?php echo oh($csrf); ?>">
<input type="hidden" name="action" value="save">
<input type="hidden" name="id" value="<?php echo (int)($edit['id'] ?? 0); ?>">

<label>عنوان مناسبت</label>
<input name="title" maxlength="150" required value="<?php echo oh($edit['title'] ?? ''); ?>" placeholder="مثلاً: تولد علی رضایی">

<label>نوع مناسبت</label>
<?php $type = $edit['occasion_type'] ?? 'general'; ?>
<div class="oc-types" role="radiogroup" aria-label="نوع مناسبت">
    <label class="oc-type <?php echo $type==='general'?'selected':''; ?>">
        <input type="radio" name="occasion_type" value="general" <?php echo $type==='general'?'checked':''; ?>>
        <span class="oc-type-icon general" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none"><path d="M12 3.5l1.45 4.15L17.5 9l-4.05 1.35L12 14.5l-1.45-4.15L6.5 9l4.05-1.35L12 3.5Z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/><path d="M18.5 13.5l.65 1.85L21 16l-1.85.65-.65 1.85-.65-1.85L16 16l1.85-.65.65-1.85Z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/></svg>
        </span>
        <span><b>عمومی</b><small>پیام عمومی</small></span>
    </label>

    <label class="oc-type <?php echo $type==='birthday'?'selected':''; ?>">
        <input type="radio" name="occasion_type" value="birthday" <?php echo $type==='birthday'?'checked':''; ?>>
        <span class="oc-type-icon birthday" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none"><path d="M5 12.5h14v5.2A1.3 1.3 0 0 1 17.7 19H6.3A1.3 1.3 0 0 1 5 17.7v-5.2Z" stroke="currentColor" stroke-width="1.7"/><path d="M4 12.5h16M8 12.5V10M12 12.5V9M16 12.5V10" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/><path d="M7 7.2c0-1.1.9-1.7 1.7-2.4M12 6.4c0-1.2.9-1.9 1.8-2.5M17 7.2c0-1.1-.9-1.7-1.7-2.4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
        </span>
        <span><b>تولد</b><small>جشن تولد همکار</small></span>
    </label>

    <label class="oc-type <?php echo $type==='calendar'?'selected':''; ?>">
        <input type="radio" name="occasion_type" value="calendar" <?php echo $type==='calendar'?'checked':''; ?>>
        <span class="oc-type-icon calendar" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none"><rect x="4" y="5.5" width="16" height="14" rx="2.2" stroke="currentColor" stroke-width="1.7"/><path d="M8 3.5v4M16 3.5v4M4 9.5h16M8 13h2M12 13h2M16 13h.1M8 16.5h2M12 16.5h2" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>
        </span>
        <span><b>مناسبت تقویمی</b><small>رویدادهای تقویمی</small></span>
    </label>

    <label class="oc-type <?php echo $type==='company'?'selected':''; ?>">
        <input type="radio" name="occasion_type" value="company" <?php echo $type==='company'?'checked':''; ?>>
        <span class="oc-type-icon company" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none"><path d="M5 20V5.5L13 3v17M13 20h6V9h-6M8 7.5h2M8 11h2M8 14.5h2M16 12h1.5M16 15h1.5M16 18h1.5" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/><path d="M3.5 20h17" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg>
        </span>
        <span><b>مناسبت شرکت</b><small>رویداد داخلی شرکت</small></span>
    </label>
</div>

<label>تاریخ و زمان نمایش</label>
<input type="datetime-local" name="event_date" value="<?php echo oh(oc_datetime_local($edit['event_date'] ?? '')); ?>">

<label>توضیح کوتاه</label>
<textarea name="body" maxlength="500" rows="4" placeholder="متن کوتاه مناسبت..."><?php echo oh($edit['body'] ?? ''); ?></textarea>

<label class="oc-check"><input type="checkbox" name="is_active" value="1" <?php echo !$edit || (int)$edit['is_active']===1 ? 'checked' : ''; ?>> نمایش در صفحه ورود</label>

<button class="oc-btn" type="submit"><?php echo $edit ? 'ذخیره تغییرات' : 'ثبت مناسبت'; ?></button>
<?php if ($edit): ?><a class="oc-cancel" href="occasions.php">انصراف از ویرایش</a><?php endif; ?>
</form>
</div>

<div class="oc-card">
<div class="oc-list-head"><h2>مناسبت‌های ثبت‌شده</h2><span><?php echo count($occasions); ?> مورد</span></div>
<?php if (!$occasions): ?>
<div class="oc-empty">هنوز مناسبتی ثبت نشده است.</div>
<?php else: ?>
<div class="oc-list">
<?php foreach ($occasions as $item): ?>
<div class="oc-item">
<div class="oc-item-main">
<div class="oc-item-title"><?php echo oh($item['title']); ?></div>
<div class="oc-item-meta"><?php echo oh($item['occasion_type']); ?><?php echo $item['event_date'] ? ' · '.oh($item['event_date']) : ''; ?></div>
<?php if ($item['body']): ?><div class="oc-item-body"><?php echo oh($item['body']); ?></div><?php endif; ?>
</div>
<div class="oc-actions">
<a href="occasions.php?edit=<?php echo (int)$item['id']; ?>">ویرایش</a>
<form method="post"><input type="hidden" name="csrf_token" value="<?php echo oh($csrf); ?>"><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?php echo (int)$item['id']; ?>"><button type="submit"><?php echo (int)$item['is_active']===1 ? 'عدم نمایش' : 'نمایش'; ?></button></form>
<form method="post" onsubmit="return confirm('این مناسبت حذف شود؟');"><input type="hidden" name="csrf_token" value="<?php echo oh($csrf); ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?php echo (int)$item['id']; ?>"><button class="danger" type="submit">حذف</button></form>
</div>
</div>
<?php endforeach; ?>
</div>
<?php endif; ?>
</div>
</div>
</div>

<style>
.oc-page{padding:6px 0 30px}.oc-head{display:flex;align-items:center;justify-content:space-between;gap:20px;margin-bottom:20px}.oc-head h1{margin:0 0 5px;color:#17365d;font-size:22px}.oc-head p{margin:0;color:#667085;font-size:11px}.oc-back{padding:9px 13px;border-radius:9px;background:#eef4ff;color:#3159a6;text-decoration:none;font-size:10px}.oc-grid{display:grid;grid-template-columns:360px minmax(0,1fr);gap:18px}.oc-card{background:#fff;border:1px solid #e4e7ec;border-radius:16px;padding:20px;box-shadow:0 8px 24px rgba(16,24,40,.05)}.oc-card h2{margin:0 0 16px;color:#17365d;font-size:14px}.oc-card label{display:block;margin:11px 0 5px;color:#344054;font-size:10px;font-weight:600}.oc-card input:not([type=checkbox]):not([type=radio]),.oc-card select,.oc-card textarea{width:100%;border:1px solid #d0d5dd;border-radius:9px;padding:9px 10px;font-family:inherit;font-size:10px;outline:none;background:#fff}.oc-card input[type=datetime-local]{direction:ltr;text-align:right}.oc-card textarea{resize:vertical;line-height:1.9}.oc-check{display:flex!important;align-items:center;gap:7px;font-weight:500!important}.oc-check input{margin:0}.oc-types{display:grid;grid-template-columns:1fr 1fr;gap:7px;margin-top:6px}.oc-type{position:relative;display:flex!important;align-items:center;gap:8px;margin:0!important;padding:9px;border:1px solid #e4e7ec;border-radius:11px;background:#fff;cursor:pointer;transition:.16s ease}.oc-type:hover{border-color:#b8c8e8;background:#f8faff}.oc-type.selected{border-color:#3159a6;background:#f3f7ff;box-shadow:0 0 0 2px rgba(49,89,166,.07)}.oc-type input{position:absolute;opacity:0;pointer-events:none}.oc-type-icon{width:31px;height:31px;min-width:31px;border-radius:9px;display:grid;place-items:center}.oc-type-icon svg{width:19px;height:19px}.oc-type-icon.general{background:#fff4d6;color:#b7791f}.oc-type-icon.birthday{background:#fff0f6;color:#d23c79}.oc-type-icon.calendar{background:#edf7ff;color:#2774b8}.oc-type-icon.company{background:#eef1ff;color:#4b55a8}.oc-type b{display:block;color:#344054;font-size:9px;line-height:1.5}.oc-type small{display:block;margin-top:1px;color:#98a2b3;font-size:7px;font-weight:400}.oc-type.selected b{color:#17365d}.oc-type.selected .oc-type-icon{transform:scale(1.04)}.oc-type:has(input:checked){border-color:#3159a6;background:#f3f7ff;box-shadow:0 0 0 2px rgba(49,89,166,.07)}.oc-type:has(input:checked) b{color:#17365d}.oc-btn{margin-top:14px;width:100%;border:0;border-radius:9px;padding:10px;background:#17365d;color:#fff;font-family:inherit;font-size:10px;font-weight:600;cursor:pointer}.oc-cancel{display:block;text-align:center;margin-top:9px;color:#667085;font-size:9px}.oc-list-head{display:flex;align-items:center;justify-content:space-between}.oc-list-head span{font-size:9px;color:#667085;background:#f2f4f7;padding:5px 8px;border-radius:999px}.oc-list{display:flex;flex-direction:column;gap:8px}.oc-item{display:flex;justify-content:space-between;gap:15px;padding:12px;border:1px solid #eaecf0;border-radius:11px;background:#fafbfc}.oc-item-title{font-size:11px;font-weight:600;color:#1d2939}.oc-item-meta{margin-top:4px;color:#98a2b3;font-size:8px}.oc-item-body{margin-top:5px;color:#667085;font-size:9px;line-height:1.8}.oc-actions{display:flex;align-items:center;gap:5px;white-space:nowrap}.oc-actions a,.oc-actions button{border:1px solid #d0d5dd;background:#fff;color:#344054;border-radius:7px;padding:6px 8px;font-family:inherit;font-size:8px;text-decoration:none;cursor:pointer}.oc-actions .danger{color:#b42318}.oc-alert{padding:10px 12px;border-radius:9px;margin-bottom:14px;font-size:10px}.oc-alert.success{background:#ecfdf3;color:#067647;border:1px solid #abefc6}.oc-alert.error{background:#fef3f2;color:#b42318;border:1px solid #fecdca}.oc-empty{text-align:center;padding:45px 15px;color:#98a2b3;font-size:10px}@media(max-width:850px){.oc-grid{grid-template-columns:1fr}.oc-head{align-items:flex-start;flex-direction:column}.oc-actions{flex-wrap:wrap}}@media(max-width:430px){.oc-types{grid-template-columns:1fr}}
</style>
<script>
(function(){
    function syncOccasionType(){
        document.querySelectorAll('.oc-type').forEach(function(card){
            var radio = card.querySelector('input[type="radio"]');
            card.classList.toggle('selected', !!radio && radio.checked);
        });
    }

    document.querySelectorAll('.oc-type').forEach(function(card){
        card.addEventListener('click', function(e){
            if (e.target.tagName.toLowerCase() !== 'input') {
                var radio = card.querySelector('input[type="radio"]');
                if (radio) {
                    radio.checked = true;
                    radio.dispatchEvent(new Event('change', {bubbles:true}));
                }
            }
        });
    });

    document.querySelectorAll('.oc-type input[type="radio"]').forEach(function(radio){
        radio.addEventListener('change', syncOccasionType);
    });

    syncOccasionType();
})();
</script>
<?php require_once "includes/footer.php"; ?>