<?php
declare(strict_types=1);
require_once __DIR__ . '/auth/auth.php';
check_login();
require_once __DIR__ . '/includes/useful-files-common.php';
$page_title = 'مدیریت فایل‌های کاربردی';
$db = neal_useful_files_db();
$departments = neal_useful_files_departments($db);
if (empty($_SESSION['neal_uf_csrf'])) $_SESSION['neal_uf_csrf'] = bin2hex(random_bytes(32));
$csrf = (string)$_SESSION['neal_uf_csrf'];
$message = ''; $kind = 'success'; $edit = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($csrf, (string)($_POST['csrf_token'] ?? ''))) {
        $message='درخواست معتبر نیست؛ صفحه را تازه‌سازی کنید.'; $kind='error';
    } else {
        $action=(string)($_POST['action'] ?? ''); $id=(int)($_POST['id'] ?? 0);
        try {
            if ($action === 'save') {
                $title=trim((string)($_POST['title'] ?? ''));
                $description=trim((string)($_POST['description'] ?? ''));
                $department=trim((string)($_POST['department'] ?? ''));
                $sortRaw=trim((string)($_POST['sort_order'] ?? '0'));
                $sort=preg_match('/^-?\d+$/',$sortRaw) ? (int)$sortRaw : 0;
                $active=isset($_POST['is_active'])?1:0;
                if ($title==='' || mb_strlen($title)>160) throw new RuntimeException('عنوان را وارد کنید (حداکثر ۱۶۰ نویسه).');
                if (mb_strlen($description)>1000) throw new RuntimeException('توضیحات حداکثر ۱۰۰۰ نویسه است.');
                if (!in_array($department,$departments,true)) throw new RuntimeException('یک دپارتمان معتبر انتخاب کنید.');
                $old=null;
                if ($id>0) { $s=$db->prepare('SELECT * FROM useful_files WHERE id=?');$s->execute([$id]);$old=$s->fetch();if(!$old)throw new RuntimeException('رکورد موردنظر پیدا نشد.'); }
                $upload=null;
                if (isset($_FILES['file']) && (int)($_FILES['file']['error'] ?? UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_NO_FILE) $upload=neal_uf_validate_upload($_FILES['file']);
                if (!$old && !$upload) throw new RuntimeException('برای فایل جدید، انتخاب فایل الزامی است.');
                $stored=$old['stored_name']??''; $original=$old['original_name']??''; $mime=$old['mime_type']??''; $size=(int)($old['file_size']??0);
                if ($upload) {
                    $dir=neal_useful_files_dir();
                    if (!is_dir($dir) && !@mkdir($dir,0700,true) && !is_dir($dir)) throw new RuntimeException('پوشه امن ذخیره فایل ساخته نشد.');
                    @chmod($dir,0700);
                    $stored=bin2hex(random_bytes(24)).'.'.$upload['extension'];
                    if (!move_uploaded_file($upload['tmp_name'],$dir.'/'.$stored)) throw new RuntimeException('ذخیره فایل روی سرور ناموفق بود.');
                    @chmod($dir.'/'.$stored,0600);
                    $original=$upload['original_name'];$mime=$upload['mime_type'];$size=$upload['file_size'];
                }
                try {
                    if ($id>0) {
                        $s=$db->prepare('UPDATE useful_files SET title=?,description=?,department=?,original_name=?,stored_name=?,mime_type=?,file_size=?,is_active=?,sort_order=?,updated_at=CURRENT_TIMESTAMP WHERE id=?');
                        $s->execute([$title,$description,$department,$original,$stored,$mime,$size,$active,$sort,$id]);
                    } else {
                        $s=$db->prepare('INSERT INTO useful_files(title,description,department,original_name,stored_name,mime_type,file_size,is_active,sort_order,created_by) VALUES(?,?,?,?,?,?,?,?,?,?)');
                        $s->execute([$title,$description,$department,$original,$stored,$mime,$size,$active,$sort,(int)($_SESSION['admin_id']??0)]);
                    }
                } catch (Throwable $e) { if($upload && isset($dir,$stored) && is_file($dir.'/'.$stored)) @unlink($dir.'/'.$stored); throw $e; }
                if ($upload && $old && $old['stored_name']!==$stored) { $oldPath=neal_useful_files_dir().'/'.basename((string)$old['stored_name']);if(is_file($oldPath))@unlink($oldPath); }
                $message=$id>0?'اطلاعات فایل ذخیره شد.':'فایل کاربردی ثبت شد.';
            } elseif ($action==='toggle' && $id>0) {
                $s=$db->prepare('UPDATE useful_files SET is_active=CASE WHEN is_active=1 THEN 0 ELSE 1 END,updated_at=CURRENT_TIMESTAMP WHERE id=?');$s->execute([$id]);$message='وضعیت نمایش تغییر کرد.';
            } elseif ($action==='delete' && $id>0) {
                $s=$db->prepare('SELECT stored_name FROM useful_files WHERE id=?');$s->execute([$id]);$old=$s->fetch();
                $s=$db->prepare('DELETE FROM useful_files WHERE id=?');$s->execute([$id]);
                if($old){$path=neal_useful_files_dir().'/'.basename((string)$old['stored_name']);if(is_file($path))@unlink($path);}
                $message='فایل و اطلاعات آن حذف شد.';
            } else throw new RuntimeException('عملیات معتبر نیست.');
        } catch (Throwable $e) { $message=$e instanceof RuntimeException?$e->getMessage():'عملیات انجام نشد؛ گزارش خطای سرور را بررسی کنید.';$kind='error'; }
    }
}
if (isset($_GET['edit'])) { $s=$db->prepare('SELECT * FROM useful_files WHERE id=?');$s->execute([(int)$_GET['edit']]);$edit=$s->fetch()?:null; }
$items=$db->query('SELECT * FROM useful_files ORDER BY sort_order,id DESC')->fetchAll();
$activeCount=count(array_filter($items,fn($x)=>(int)$x['is_active']===1));
?>
<?php include __DIR__.'/includes/header.php'; ?>
<style>
.uf{max-width:1450px;margin:0 auto;padding:8px 4px 38px;font-family:Vazirmatn,Tahoma,sans-serif;color:#1d2939}.uf-hero{display:flex;justify-content:space-between;align-items:center;gap:18px;padding:25px 29px;border-radius:22px;background:linear-gradient(125deg,#102a43,#24577c);color:#fff;margin-bottom:20px}.uf-hero h1{font-size:23px;margin:0 0 7px}.uf-hero p{font-size:12px;line-height:2;color:#d5e3f1;margin:0}.uf-mark{font-size:30px;width:65px;height:65px;display:grid;place-items:center;border:1px solid #ffffff44;background:#ffffff12;border-radius:19px}.uf-stats{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px;margin-bottom:18px}.uf-stat,.uf-panel{background:#fff;border:1px solid #e5eaf1;border-radius:17px;box-shadow:0 5px 18px #10182808;overflow:hidden}.uf-stat{padding:15px 19px}.uf-stat small{display:block;color:#667085;font-size:11px}.uf-stat strong{font-size:24px;color:#17365d}.uf-layout{display:grid;grid-template-columns:minmax(300px,.75fr) minmax(0,1.5fr);gap:17px;align-items:start}.uf-head{padding:16px 19px;border-bottom:1px solid #edf0f4;display:flex;justify-content:space-between;align-items:center;gap:10px}.uf-head h2{font-size:14px;margin:0;color:#17365d}.uf-head small{color:#98a2b3;font-size:10px}.uf-form{padding:20px}.uf-form label{display:block;font-size:11px;font-weight:600;margin:0 0 7px;color:#344054}.uf-form input[type=text],.uf-form input[type=number],.uf-form input[type=file],.uf-form select,.uf-form textarea{box-sizing:border-box;width:100%;padding:10px 11px;border:1px solid #d0d5dd;border-radius:10px;background:#fff;font:12px Vazirmatn,Tahoma,sans-serif;margin-bottom:14px}.uf-form textarea{min-height:82px;resize:vertical}.uf-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px}.uf-check{display:flex!important;align-items:center;gap:8px;margin:4px 0 16px!important}.uf-check input{accent-color:#175782}.uf-btn{border:0;border-radius:9px;padding:8px 12px;display:inline-flex;align-items:center;justify-content:center;gap:6px;font:600 11px Vazirmatn,Tahoma,sans-serif;text-decoration:none;cursor:pointer}.uf-primary{background:#173f65;color:#fff}.uf-muted{background:#f2f4f7;color:#475467}.uf-danger{background:#fff0ef;color:#b42318}.uf-alert{padding:12px 14px;border-radius:11px;margin-bottom:16px;font-size:11px}.uf-alert.success{background:#ecfdf3;color:#027a48}.uf-alert.error{background:#fef3f2;color:#b42318}.uf-table-wrap{overflow-x:auto}.uf-table{width:100%;border-collapse:collapse;min-width:790px}.uf-table th{background:#f8fafc;color:#667085;font-size:10px;text-align:right;padding:12px;border-bottom:1px solid #e7edf4;white-space:nowrap}.uf-table td{padding:12px;border-bottom:1px solid #eef1f5;font-size:11px;vertical-align:middle}.uf-filetitle strong{display:block;color:#17365d}.uf-filetitle small{display:block;color:#98a2b3;font-size:10px;margin-top:4px}.uf-tag{display:inline-block;background:#edf4fb;color:#24577c;border-radius:999px;padding:5px 9px;font-size:10px;white-space:nowrap}.uf-status{display:inline-block;border-radius:999px;padding:4px 8px;font-size:10px}.uf-on{background:#ecfdf3;color:#027a48}.uf-off{background:#f2f4f7;color:#667085}.uf-actions{display:flex;gap:5px;flex-wrap:wrap}.uf-empty{text-align:center;padding:34px;color:#98a2b3;font-size:12px}
@media(max-width:1050px){.uf-layout{grid-template-columns:1fr}}@media(max-width:600px){.uf-hero{padding:19px}.uf-hero h1{font-size:19px}.uf-mark{width:50px;height:50px}.uf-grid{grid-template-columns:1fr;gap:0}.uf-form{padding:15px}}
</style>
<div class="uf"><section class="uf-hero"><div><h1>📁 مدیریت فایل‌های کاربردی</h1><p>فرم‌ها و فایل‌های موردنیاز کارکنان را بارگذاری کنید و برای هر فایل دپارتمان مربوطه را مشخص کنید.<br>فایل‌ها به‌صورت امن خارج از مسیر عمومی وب ذخیره می‌شوند.</p></div><div class="uf-mark">📂</div></section>
<?php if($message!==''):?><div class="uf-alert <?php echo neal_uf_h($kind);?>"><?php echo neal_uf_h($message);?></div><?php endif;?>
<div class="uf-stats"><div class="uf-stat"><small>مجموع فایل‌ها</small><strong><?php echo count($items);?></strong></div><div class="uf-stat"><small>فایل‌های فعال برای کاربران</small><strong><?php echo $activeCount;?></strong></div></div>
<div class="uf-layout"><section class="uf-panel"><div class="uf-head"><h2><?php echo $edit?'✏️ ویرایش فایل':'＋ افزودن فایل جدید';?></h2><small>حداکثر حجم هر فایل ۲۰ مگابایت</small></div>
<form class="uf-form" method="post" enctype="multipart/form-data" action="useful-files.php"><input type="hidden" name="csrf_token" value="<?php echo neal_uf_h($csrf);?>"><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?php echo (int)($edit['id']??0);?>">
<label for="uf-title">عنوان فایل یا فرم</label><input id="uf-title" type="text" name="title" maxlength="160" required value="<?php echo neal_uf_h($edit['title']??'');?>" placeholder="مثلاً: فرم درخواست مرخصی">
<label for="uf-department">دپارتمان مربوطه</label><select id="uf-department" name="department" required><option value="">دپارتمان را انتخاب کنید</option><?php foreach($departments as $dep):?><option value="<?php echo neal_uf_h($dep);?>" <?php echo (($edit['department']??'')===$dep)?'selected':'';?>><?php echo neal_uf_h($dep);?></option><?php endforeach;?></select>
<label for="uf-description">توضیحات</label><textarea id="uf-description" name="description" maxlength="1000" placeholder="کاربرد فایل یا شرایط استفاده"><?php echo neal_uf_h($edit['description']??'');?></textarea>
<label for="uf-file"><?php echo $edit?'جایگزینی فایل (اختیاری)':'انتخاب فایل';?></label><input id="uf-file" type="file" name="file" accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.jpg,.jpeg,.png,.gif,.webp,.txt,.csv" <?php echo $edit?'':'required';?>>
<?php if($edit):?><p style="font-size:10px;color:#667085;margin:-5px 0 13px">فایل فعلی: <?php echo neal_uf_h($edit['original_name']);?></p><?php endif;?>
<div class="uf-grid"><div><label for="uf-sort">ترتیب نمایش</label><input id="uf-sort" type="number" name="sort_order" value="<?php echo (int)($edit['sort_order']??0);?>"></div><div><label class="uf-check"><input type="checkbox" name="is_active" value="1" <?php echo !isset($edit['is_active'])||(int)$edit['is_active']===1?'checked':'';?>> برای کاربران نمایش داده شود</label></div></div>
<div class="uf-actions"><button class="uf-btn uf-primary" type="submit"><?php echo $edit?'ذخیره تغییرات':'＋ ثبت فایل';?></button><?php if($edit):?><a class="uf-btn uf-muted" href="useful-files.php">لغو</a><?php endif;?></div></form></section>
<section class="uf-panel"><div class="uf-head"><h2>فهرست فایل‌ها</h2><small><?php echo count($items);?> مورد</small></div><?php if($items):?><div class="uf-table-wrap"><table class="uf-table"><thead><tr><th>عنوان</th><th>دپارتمان</th><th>حجم</th><th>وضعیت</th><th>عملیات</th></tr></thead><tbody>
<?php foreach($items as $item):?><tr><td><div class="uf-filetitle"><strong><?php echo neal_uf_h($item['title']);?></strong><small><?php echo neal_uf_h($item['original_name']);?></small></div></td><td><span class="uf-tag"><?php echo neal_uf_h($item['department']);?></span></td><td><?php echo number_format((int)$item['file_size']/1048576,2);?> MB</td><td><span class="uf-status <?php echo (int)$item['is_active']===1?'uf-on':'uf-off';?>"><?php echo (int)$item['is_active']===1?'فعال':'غیرفعال';?></span></td><td><div class="uf-actions"><a class="uf-btn uf-muted" href="useful-files.php?edit=<?php echo (int)$item['id'];?>">ویرایش</a><form method="post" action="useful-files.php" onsubmit="return confirm('وضعیت نمایش این فایل تغییر کند؟')"><input type="hidden" name="csrf_token" value="<?php echo neal_uf_h($csrf);?>"><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?php echo (int)$item['id'];?>"><button class="uf-btn uf-muted"><?php echo (int)$item['is_active']===1?'غیرفعال‌سازی':'فعال‌سازی';?></button></form><form method="post" action="useful-files.php" onsubmit="return confirm('فایل برای همیشه حذف شود؟')"><input type="hidden" name="csrf_token" value="<?php echo neal_uf_h($csrf);?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?php echo (int)$item['id'];?>"><button class="uf-btn uf-danger">حذف</button></form></div></td></tr><?php endforeach;?>
</tbody></table></div><?php else:?><div class="uf-empty">هنوز فایلی ثبت نشده است.</div><?php endif;?></section></div></div>
<?php include __DIR__.'/includes/footer.php';?>
