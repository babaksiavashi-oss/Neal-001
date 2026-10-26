<?php
declare(strict_types=1);
require_once __DIR__ . '/common.php';
$actor = neal_docs_actor();
$db = neal_docs_db();
$folderId = max(0, (int)($_GET['folder'] ?? 0));
if ($actor['type'] === 'admin') {
    $current = null;
    if ($folderId) {
        $stmt = $db->prepare("SELECT id,name,parent_id,folder_type,owner_user_id,created_by_user_id FROM document_folders WHERE id=? AND is_deleted=0");
        $stmt->execute([$folderId]); $current = $stmt->fetch();
        if (!$current) { http_response_code(404); exit('پوشه پیدا نشد.'); }
    }
    $sql = "SELECT f.id,f.name,f.parent_id,f.folder_type,f.owner_user_id,f.created_by_user_id,
      (SELECT COUNT(*) FROM document_files x WHERE x.folder_id=f.id AND x.is_deleted=0) AS file_count,
      (SELECT COUNT(*) FROM document_folders c WHERE c.parent_id=f.id AND c.is_deleted=0) AS child_count
      FROM document_folders f WHERE f.is_deleted=0 AND " . ($folderId ? "f.parent_id=?" : "f.parent_id IS NULL") . " ORDER BY f.name";
    $foldersStmt=$db->prepare($sql); $foldersStmt->execute($folderId?[$folderId]:[]);
} else {
    $active=$db->prepare('SELECT 1 FROM users WHERE id=? AND is_active=1'); $active->execute([$actor['id']]);
    if (!$active->fetchColumn()) { http_response_code(403); exit('حساب کاربری غیرفعال است.'); }
    $current=null;
    if ($folderId) {
        $s=$db->prepare("SELECT id,name,parent_id,folder_type,owner_user_id,created_by_user_id FROM document_folders WHERE id=? AND is_deleted=0");
        $s->execute([$folderId]); $current=$s->fetch();
        if (!$current || (!neal_docs_can($db,$actor,$folderId,null,'can_view') && !($current['folder_type']==='personal' && (int)$current['owner_user_id']===(int)$actor['id']))) {
            http_response_code(403); exit('به این پوشه دسترسی ندارید.');
        }
    }
    $sql="SELECT f.id,f.name,f.parent_id,f.folder_type,f.owner_user_id,f.created_by_user_id,
      (SELECT COUNT(*) FROM document_files x WHERE x.folder_id=f.id AND x.is_deleted=0) AS file_count,
      (SELECT COUNT(*) FROM document_folders c WHERE c.parent_id=f.id AND c.is_deleted=0) AS child_count
      FROM document_folders f WHERE f.is_deleted=0 AND ".($folderId?"f.parent_id=?":"f.parent_id IS NULL")."
      AND ((f.folder_type='personal' AND f.owner_user_id=?) OR EXISTS(
        SELECT 1 FROM document_permissions p WHERE p.folder_id=f.id AND p.user_id=? AND p.can_view=1))
      ORDER BY f.name";
    $foldersStmt=$db->prepare($sql); $foldersStmt->execute($folderId?[$folderId,$actor['id'],$actor['id']]:[$actor['id'],$actor['id']]);
}
$folders=$foldersStmt->fetchAll();
$files=[];
if ($folderId) {
    $s=$db->prepare("SELECT id,display_name,file_size,updated_at,folder_id,extension,created_by_user_id FROM document_files WHERE folder_id=? AND is_deleted=0 ORDER BY display_name");
    $s->execute([$folderId]);
    $owns=$actor['type']==='user' && $current['folder_type']==='personal' && (int)$current['owner_user_id']===(int)$actor['id'];
    foreach($s->fetchAll() as $file) {
        if($actor['type']==='admin' || $owns || neal_docs_can($db,$actor,$folderId,(int)$file['id'],'can_view')) $files[]=$file;
    }
}
$ownsPersonal=$current && $actor['type']==='user' && $current['folder_type']==='personal' && (int)$current['owner_user_id']===(int)$actor['id'];
$canCreate=$actor['type']==='admin' || (!$current) || $ownsPersonal || neal_docs_can($db,$actor,$folderId,null,'can_upload');
$canUpload=$current && ($actor['type']==='admin' || $ownsPersonal || neal_docs_can($db,$actor,$folderId,null,'can_upload'));
$parentHref=$current ? ($current['parent_id'] ? 'documents/index.php?folder='.(int)$current['parent_id'] : 'documents/index.php') : '';
$page_title='مدیریت اسناد';
$page_base_href='/neal/';
$portal_mode=$actor['type']==='user';
require dirname(__DIR__).'/includes/header.php';
?>
<style>
.docs-page{--docs-ink:#172b4d;--docs-muted:#6b7a90;--docs-line:#e5eaf2;--docs-blue:#1769aa;--docs-soft:#f4f8fc;max-width:1480px;margin:0 auto;padding:8px 26px 34px;color:var(--docs-ink);font-family:Vazirmatn,Tahoma,sans-serif}
.docs-hero{position:relative;overflow:hidden;display:flex;align-items:center;justify-content:space-between;gap:20px;padding:28px 30px;border-radius:22px;background:linear-gradient(120deg,#102a43 0%,#1769aa 65%,#2a9d9f 120%);color:#fff;box-shadow:0 16px 36px rgba(16,42,67,.16)}
.docs-hero:after{content:"";position:absolute;width:270px;height:270px;border:1px solid rgba(255,255,255,.13);border-radius:50%;left:-70px;top:-150px;box-shadow:0 0 0 32px rgba(255,255,255,.035),0 0 0 68px rgba(255,255,255,.025)}
.docs-hero-copy{position:relative;z-index:1}.docs-eyebrow{font-size:11px;letter-spacing:.04em;color:#c5e5f7;font-weight:700}.docs-hero h1{font-size:25px;margin:8px 0 7px;font-weight:800}.docs-hero p{font-size:12px;color:#e1eff9;margin:0;line-height:1.9;max-width:650px}.docs-hero-actions{display:flex;gap:9px;flex-wrap:wrap;position:relative;z-index:1}
.docs-btn{display:inline-flex;align-items:center;justify-content:center;gap:7px;padding:10px 15px;border-radius:11px;border:1px solid transparent;text-decoration:none;font:700 12px Vazirmatn,Tahoma,sans-serif;cursor:pointer;transition:.18s ease;white-space:nowrap}.docs-btn:hover{transform:translateY(-1px)}.docs-btn-primary{background:#fff;color:#164e7a}.docs-btn-glass{background:rgba(255,255,255,.1);border-color:rgba(255,255,255,.28);color:#fff}.docs-btn-soft{background:#edf6fd;color:#145b8f;border-color:#d7eaf8}.docs-btn-danger{background:#fff1f0;color:#b42318;border-color:#ffd6d2}.docs-btn-small{padding:7px 10px;font-size:11px}
.docs-breadcrumb{display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin:18px 2px;color:var(--docs-muted);font-size:12px}.docs-breadcrumb a{color:var(--docs-blue);text-decoration:none;font-weight:700}.docs-breadcrumb span{color:#a0acbb}
.docs-layout{display:grid;grid-template-columns:minmax(0,1.6fr) minmax(290px,.8fr);gap:18px;align-items:start}.docs-panel{background:#fff;border:1px solid var(--docs-line);border-radius:18px;box-shadow:0 4px 18px rgba(23,43,77,.035);overflow:hidden}.docs-panel-head{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:19px 21px;border-bottom:1px solid #edf1f6}.docs-panel-head h2{font-size:15px;margin:0;font-weight:800}.docs-panel-head p{font-size:11px;color:var(--docs-muted);margin:5px 0 0}.docs-count{display:inline-flex;align-items:center;justify-content:center;min-width:28px;height:28px;padding:0 8px;border-radius:9px;background:#edf6fd;color:#1769aa;font-size:11px;font-weight:800}
.docs-folder-list,.docs-file-list{padding:8px 14px 14px}.docs-folder-row,.docs-file-row{display:flex;align-items:center;gap:13px;padding:13px 8px;border-bottom:1px solid #f0f3f7}.docs-folder-row:last-child,.docs-file-row:last-child{border-bottom:0}.docs-folder-icon,.docs-file-icon{width:44px;height:44px;flex:0 0 44px;display:grid;place-items:center;border-radius:13px;background:#fff6df;color:#b78116;font-size:21px}.docs-file-icon{background:#edf6fd;color:#1769aa}.docs-item-main{min-width:0;flex:1}.docs-item-main a{font-size:12px;font-weight:800;color:#243b53;text-decoration:none;overflow-wrap:anywhere}.docs-item-main a:hover{color:var(--docs-blue)}.docs-item-meta{display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-top:5px;color:#8996a8;font-size:10px}.docs-item-actions{display:flex;gap:6px;align-items:center;flex-wrap:wrap}.docs-pill{display:inline-flex;padding:4px 8px;border-radius:999px;background:#f1f5f9;color:#667085;font-size:10px;font-weight:700}.docs-pill-shared{background:#e9f8f1;color:#087443}.docs-empty{padding:36px 20px;text-align:center;color:var(--docs-muted)}.docs-empty-icon{display:grid;place-items:center;margin:0 auto 12px;width:54px;height:54px;border-radius:18px;background:#f2f6fb;font-size:25px}.docs-empty strong{display:block;color:#344054;font-size:13px;margin-bottom:6px}.docs-empty p{font-size:11px;line-height:1.9;margin:0}
.docs-form{padding:20px}.docs-form label{display:block;font-size:11px;font-weight:700;color:#475467;margin-bottom:8px}.docs-form input[type=text],.docs-form input[type=file],.docs-form select{box-sizing:border-box;width:100%;padding:11px 12px;border:1px solid #dce4ee;border-radius:10px;background:#fbfcfe;color:#243b53;font:12px Vazirmatn,Tahoma,sans-serif;outline:none}.docs-form input:focus,.docs-form select:focus{border-color:#68a9d7;box-shadow:0 0 0 3px #e7f3fc}.docs-form .field{margin-bottom:15px}.docs-form .hint{font-size:10px;color:#8996a8;line-height:1.8;margin-top:6px}.docs-form .docs-btn{width:100%}.docs-alert{padding:12px 15px;border-radius:12px;margin:14px 0;font-size:12px}.docs-alert-success{background:#eaf8f0;color:#087443;border:1px solid #c9ecd8}.docs-alert-error{background:#fff1f0;color:#b42318;border:1px solid #ffd6d2}
.docs-upload-note{display:flex;gap:10px;padding:14px 16px;background:#f6faff;border:1px dashed #d0e4f5;border-radius:13px;margin-top:16px}.docs-upload-note span{font-size:20px}.docs-upload-note p{font-size:10px;color:#6b7a90;line-height:1.9;margin:0}
@media(max-width:950px){.docs-layout{grid-template-columns:1fr}.docs-hero{align-items:flex-start;flex-direction:column}.docs-hero-actions{width:100%}.docs-hero h1{font-size:22px}}
@media(max-width:600px){.docs-page{padding:4px 12px 24px}.docs-hero{padding:22px 18px;border-radius:16px}.docs-hero-actions .docs-btn{flex:1}.docs-panel-head{padding:15px}.docs-folder-list,.docs-file-list{padding:6px 10px 10px}.docs-folder-row,.docs-file-row{align-items:flex-start;gap:9px}.docs-folder-icon,.docs-file-icon{width:37px;height:37px;flex-basis:37px;font-size:18px}.docs-item-actions{width:100%;margin-top:8px}.docs-folder-row,.docs-file-row{flex-wrap:wrap}.docs-item-main{flex-basis:calc(100% - 55px)}.docs-item-actions{padding-right:46px}.docs-btn-small{padding:7px 9px}}
</style>
<main class="docs-page">
  <section class="docs-hero">
    <div class="docs-hero-copy">
      <div class="docs-eyebrow">NEAL PHARMED · DOCUMENT CENTER</div>
      <h1>مرکز اسناد و فایل‌های سازمانی</h1>
      <p>دسترسی امن به اسناد شرکت، مدیریت فایل‌ها و همکاری روی مستندات؛ بر اساس مجوزهای تعریف‌شده برای هر کاربر.</p>
    </div>
    <div class="docs-hero-actions">
      <?php if($actor['type']==='admin'): ?><a class="docs-btn docs-btn-primary" href="documents/admin.php">⚙ مدیریت پوشه‌ها و دسترسی‌ها</a><?php endif; ?>
      <a class="docs-btn docs-btn-glass" href="<?= $actor['type']==='admin' ? 'index.php' : 'portal.php' ?>">⌂ بازگشت به خانه</a>
    </div>
  </section>
  <nav class="docs-breadcrumb" aria-label="مسیر پوشه">
    <a href="documents/index.php">مرکز اسناد</a>
    <?php if($current): ?><span>‹</span><span><?=neal_docs_h((string)$current['name'])?></span><?php endif; ?>
  </nav>
  <?php if(isset($_GET['created'])): ?><div class="docs-alert docs-alert-success">پوشه با موفقیت ایجاد شد.</div><?php endif; ?>
  <?php if(isset($_GET['uploaded'])): ?><div class="docs-alert docs-alert-success">فایل با موفقیت بارگذاری شد.</div><?php endif; ?>
  <div class="docs-layout">
    <section class="docs-panel">
      <div class="docs-panel-head"><div><h2><?= $current ? 'محتوای پوشه: '.neal_docs_h((string)$current['name']) : 'پوشه‌های قابل دسترس' ?></h2><p><?= $current ? 'پوشه‌ها و فایل‌های موجود در این مسیر' : 'برای ورود به هر پوشه روی نام آن کلیک کنید.' ?></p></div><span class="docs-count"><?=count($folders)?></span></div>
      <?php if($current && $current['parent_id']): ?><div style="padding:12px 22px 0"><a class="docs-btn docs-btn-soft docs-btn-small" href="documents/index.php?folder=<?=(int)$current['parent_id']?>">↶ پوشه والد</a></div><?php elseif($current): ?><div style="padding:12px 22px 0"><a class="docs-btn docs-btn-soft docs-btn-small" href="documents/index.php">↶ پوشه‌های اصلی</a></div><?php endif; ?>
      <div class="docs-folder-list">
        <?php foreach($folders as $f): ?>
        <div class="docs-folder-row">
          <div class="docs-folder-icon">📁</div>
          <div class="docs-item-main"><a href="documents/index.php?folder=<?=(int)$f['id']?>"><?=neal_docs_h((string)$f['name'])?></a><div class="docs-item-meta"><span class="docs-pill <?=($f['folder_type']==='shared'?'docs-pill-shared':'')?>"><?=($f['folder_type']==='shared'?'پوشه مشترک':'پوشه شخصی')?></span><span><?= (int)$f['child_count'] ?> زیرپوشه</span><span><?= (int)$f['file_count'] ?> فایل</span></div></div>
          <?php $ownsFolder=$actor['type']==='user' && (($f['folder_type']==='personal' && (int)$f['owner_user_id']===(int)$actor['id']) || (int)($f['created_by_user_id']??0)===(int)$actor['id']); ?><?php if($actor['type']==='admin' || $ownsFolder): ?><div class="docs-item-actions"><?php if($actor['type']==='admin'): ?><a class="docs-btn docs-btn-soft docs-btn-small" href="documents/admin.php">مدیریت</a><?php endif; ?><a class="docs-btn docs-btn-soft docs-btn-small" href="documents/permissions.php?folder_id=<?=(int)$f['id']?>">مدیریت دسترسی</a></div><?php endif; ?>
        </div>
        <?php endforeach; ?>
        <?php if(!$folders): ?><div class="docs-empty"><div class="docs-empty-icon">🗂️</div><strong><?= $actor['type']==='admin' ? 'هنوز پوشه‌ای در این مسیر نیست' : 'پوشه‌ای برای نمایش در دسترس نیست' ?></strong><p><?= $actor['type']==='admin' ? 'از بخش مدیریت، یک پوشه مشترک ایجاد کنید و مجوز مشاهده را برای کاربران موردنظر تعیین کنید.' : 'اگر انتظار دارید پوشه‌ای را ببینید، از مدیر سامانه بخواهید دسترسی مشاهده را برای شما فعال کند.' ?></p><?php if($actor['type']==='admin'): ?><a class="docs-btn docs-btn-primary" href="documents/admin.php">ایجاد پوشه مشترک</a><?php endif; ?></div><?php endif; ?>
      </div>
    </section>
    <aside>
      <?php if($canCreate): ?>
      <section class="docs-panel">
        <div class="docs-panel-head"><div><h2>ساخت پوشه جدید</h2><p><?= $actor['type']==='admin' ? 'پوشه مشترک یا زیرپوشه سازمانی' : 'ساخت پوشه در فضای مجاز شما' ?></p></div><span class="docs-count">＋</span></div>
        <form class="docs-form" method="post" action="documents/folder-create.php">
          <input type="hidden" name="_csrf" value="<?=neal_docs_h(neal_docs_csrf_token())?>">
          <input type="hidden" name="parent_id" value="<?=$folderId?>">
          <div class="field"><label for="docs-folder-name">نام پوشه</label><input id="docs-folder-name" type="text" name="name" maxlength="120" required placeholder="مثلاً دستورالعمل‌های کیفیت"></div>
          <button class="docs-btn docs-btn-primary" type="submit">＋ ایجاد پوشه</button>
        </form>
      </section>
      <?php endif; ?>
      <?php if($canUpload): ?>
      <section class="docs-panel" style="margin-top:18px">
        <div class="docs-panel-head"><div><h2>بارگذاری سند</h2><p>افزودن فایل به پوشه فعلی</p></div><span class="docs-count">↑</span></div>
        <form class="docs-form" method="post" action="documents/upload.php" enctype="multipart/form-data">
          <input type="hidden" name="_csrf" value="<?=neal_docs_h(neal_docs_csrf_token())?>">
          <input type="hidden" name="folder_id" value="<?=$folderId?>">
          <div class="field"><label for="docs-file">انتخاب فایل</label><input id="docs-file" type="file" name="document" required accept=".docx,.xlsx,.pptx,.odt,.ods,.odp,.pdf,.txt,.csv,.rtf"><div class="hint">فرمت‌های مجاز مطابق تنظیمات سامانه؛ حداکثر ۱۰۰ مگابایت.</div></div>
          <button class="docs-btn docs-btn-primary" type="submit">↑ بارگذاری فایل</button>
        </form>
      </section>
      <?php endif; ?>
      <div class="docs-upload-note"><span>🔐</span><p><strong>دسترسی کنترل‌شده</strong><br>مشاهده، دانلود و ویرایش آنلاین بر اساس مجوزهای پوشه یا مجوز اختصاصی فایل کنترل می‌شود.</p></div>
    </aside>
  </div>
  <?php if($current): ?>
  <section class="docs-panel" style="margin-top:18px">
    <div class="docs-panel-head"><div><h2>فایل‌های این پوشه</h2><p>فایل‌های قابل مشاهده در مسیر فعلی</p></div><span class="docs-count"><?=count($files)?></span></div>
    <div class="docs-file-list">
      <?php foreach($files as $f): $fileOwns=$ownsPersonal || ($actor['type']==='user' && (int)($f['created_by_user_id']??0)===(int)$actor['id']); $canView=$actor['type']==='admin'||$fileOwns||neal_docs_can($db,$actor,$folderId,(int)$f['id'],'can_view'); $canDownload=$actor['type']==='admin'||$fileOwns||neal_docs_can($db,$actor,$folderId,(int)$f['id'],'can_download'); $canEdit=$actor['type']==='admin'||$fileOwns||neal_docs_can($db,$actor,$folderId,(int)$f['id'],'can_edit'); ?>
      <div class="docs-file-row">
        <div class="docs-file-icon">📄</div>
        <div class="docs-item-main"><a><?=neal_docs_h((string)$f['display_name'])?></a><div class="docs-item-meta"><span><?=strtoupper(neal_docs_h((string)$f['extension']))?></span><span><?=number_format((int)$f['file_size'])?> بایت</span><span>به‌روزرسانی: <?=neal_docs_h((string)$f['updated_at'])?></span></div></div>
        <div class="docs-item-actions">
          <?php if($canView && strtolower((string)$f['extension'])==='pdf'): ?><a class="docs-btn docs-btn-primary docs-btn-small" href="documents/view.php?id=<?=(int)$f['id']?>" target="_blank" rel="noopener">◉ مشاهده PDF</a><?php endif; ?>
          <?php if($canDownload): ?><a class="docs-btn docs-btn-soft docs-btn-small" href="documents/download.php?id=<?=(int)$f['id']?>">↓ دانلود</a><?php endif; ?>
          <?php if($canEdit && in_array(strtolower((string)$f['extension']),['docx','xlsx','pptx','odt','ods','odp','txt','csv','rtf'],true)): ?><a class="docs-btn docs-btn-primary docs-btn-small" href="documents/onlyoffice-edit.php?id=<?=(int)$f['id']?>">✎ ویرایش آنلاین</a><?php endif; ?>
          <?php if($actor['type']==='admin' || $fileOwns): ?><a class="docs-btn docs-btn-soft docs-btn-small" href="documents/permissions.php?file_id=<?=(int)$f['id']?>">مدیریت دسترسی</a><?php endif; ?><?php if($actor['type']==='admin'): ?><details style="position:relative"><summary class="docs-btn docs-btn-soft docs-btn-small" style="list-style:none">تغییر نام</summary><form method="post" action="documents/admin.php" style="display:flex;gap:5px;align-items:center;padding:8px;background:#fff;border:1px solid #e5eaf2;border-radius:10px;margin-top:5px"><input type="hidden" name="_csrf" value="<?=neal_docs_h(neal_docs_csrf_token())?>"><input type="hidden" name="action" value="rename_file"><input type="hidden" name="file_id" value="<?=(int)$f['id']?>"><input type="text" name="name" value="<?=neal_docs_h((string)$f['display_name'])?>" required maxlength="255" style="max-width:230px;font:11px Vazirmatn,Tahoma,sans-serif;padding:7px;border:1px solid #dce4ee;border-radius:7px"><button class="docs-btn docs-btn-primary docs-btn-small" type="submit">ذخیره</button></form></details><form method="post" action="documents/admin.php" onsubmit="return confirm('این فایل از فهرست اسناد حذف شود؟')"><input type="hidden" name="_csrf" value="<?=neal_docs_h(neal_docs_csrf_token())?>"><input type="hidden" name="action" value="delete_file"><input type="hidden" name="file_id" value="<?=(int)$f['id']?>"><button class="docs-btn docs-btn-danger docs-btn-small" type="submit">حذف</button></form><?php endif; ?>
          <?php if($actor['type']==='user' && $fileOwns): ?><details style="position:relative"><summary class="docs-btn docs-btn-soft docs-btn-small" style="list-style:none">تغییر نام</summary><form method="post" action="documents/user-manage.php" style="display:flex;gap:5px;align-items:center;padding:8px;background:#fff;border:1px solid #e5eaf2;border-radius:10px;margin-top:5px"><input type="hidden" name="_csrf" value="<?=neal_docs_h(neal_docs_csrf_token())?>"><input type="hidden" name="action" value="rename_file"><input type="hidden" name="file_id" value="<?=(int)$f['id']?>"><input type="text" name="name" value="<?=neal_docs_h((string)$f['display_name'])?>" required maxlength="255" style="max-width:230px;font:11px Vazirmatn,Tahoma,sans-serif;padding:7px;border:1px solid #dce4ee;border-radius:7px"><button class="docs-btn docs-btn-primary docs-btn-small" type="submit">ذخیره</button></form></details><form method="post" action="documents/user-manage.php" onsubmit="return confirm('این فایل و نسخه‌های ذخیره‌شده آن حذف شوند؟')"><input type="hidden" name="_csrf" value="<?=neal_docs_h(neal_docs_csrf_token())?>"><input type="hidden" name="action" value="delete_file"><input type="hidden" name="file_id" value="<?=(int)$f['id']?>"><button class="docs-btn docs-btn-danger docs-btn-small" type="submit">حذف</button></form><?php endif; ?>
        </div>
      </div>
      <?php endforeach; ?>
      <?php if(!$files): ?><div class="docs-empty"><div class="docs-empty-icon">📄</div><strong>هنوز فایلی در این پوشه نیست</strong><p>در صورت داشتن مجوز بارگذاری، می‌توانید از بخش کناری یک سند اضافه کنید.</p></div><?php endif; ?>
    </div>
  </section>
  <?php endif; ?>
</main>
<?php require dirname(__DIR__).'/includes/footer.php'; ?>
