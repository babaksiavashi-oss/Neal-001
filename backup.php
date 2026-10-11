<?php
declare(strict_types=1);

require_once __DIR__ . '/auth/auth.php';
check_login();

$page_title = 'پشتیبان‌گیری';

$backupRoot = '/usr/local/backups/neal';
$jobsDir    = $backupRoot . '/jobs';
$filesDir   = $backupRoot . '/files';

if (!is_dir($backupRoot)) {
    @mkdir($backupRoot, 0750, true);
}
if (!is_dir($jobsDir)) {
    @mkdir($jobsDir, 0750, true);
}
if (!is_dir($filesDir)) {
    @mkdir($filesDir, 0750, true);
}

if (empty($_SESSION['backup_csrf'])) {
    $_SESSION['backup_csrf'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['backup_csrf'];

function backup_h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function backup_persian_number($value): string
{
    return strtr((string)$value, [
        '0'=>'۰','1'=>'۱','2'=>'۲','3'=>'۳','4'=>'۴',
        '5'=>'۵','6'=>'۶','7'=>'۷','8'=>'۸','9'=>'۹'
    ]);
}

function backup_jalali_date(?string $datetime): string
{
    if (!$datetime) {
        return '-';
    }

    $timestamp = strtotime($datetime);
    if ($timestamp === false) {
        return $datetime;
    }

    $gy = (int)date('Y', $timestamp);
    $gm = (int)date('n', $timestamp);
    $gd = (int)date('j', $timestamp);

    $gdm = [0,31,59,90,120,151,181,212,243,273,304,334];

    if ($gy > 1600) {
        $jy = 979;
        $gy -= 1600;
    } else {
        $jy = 0;
        $gy -= 621;
    }

    $gy2 = ($gm > 2) ? $gy + 1 : $gy;

    $days =
        (365 * $gy)
        + (int)(($gy2 + 3) / 4)
        - (int)(($gy2 + 99) / 100)
        + (int)(($gy2 + 399) / 400)
        - 80 + $gd + $gdm[$gm - 1];

    $jy += 33 * (int)($days / 12053);
    $days %= 12053;
    $jy += 4 * (int)($days / 1461);
    $days %= 1461;

    if ($days > 365) {
        $jy += (int)(($days - 1) / 365);
        $days = ($days - 1) % 365;
    }

    if ($days < 186) {
        $jm = 1 + (int)($days / 31);
        $jd = 1 + ($days % 31);
    } else {
        $jm = 7 + (int)(($days - 186) / 30);
        $jd = 1 + (($days - 186) % 30);
    }

    return sprintf('%04d/%02d/%02d - %s', $jy, $jm, $jd, date('H:i', $timestamp));
}

function backup_format_size(int $bytes): string
{
    if ($bytes < 1024) return $bytes . ' B';
    if ($bytes < 1024 * 1024) return number_format($bytes / 1024, 1) . ' KB';
    if ($bytes < 1024 * 1024 * 1024) return number_format($bytes / 1048576, 1) . ' MB';
    return number_format($bytes / 1073741824, 2) . ' GB';
}

$backups = [];

foreach (glob($filesDir . '/*.zip') ?: [] as $zip) {
    $filename = basename($zip);
    $metaFile = $jobsDir . '/' . preg_replace('/\.zip$/i', '', $filename) . '.json';

    $meta = [];
    if (is_file($metaFile)) {
        $raw = @file_get_contents($metaFile);
        $decoded = json_decode((string)$raw, true);
        if (is_array($decoded)) {
            $meta = $decoded;
        }
    }

    $mtime = @filemtime($zip) ?: time();

    $backups[] = [
        'filename' => $filename,
        'type' => $meta['type'] ?? 'unknown',
        'type_label' => $meta['type_label'] ?? 'پشتیبان',
        'size' => (int)(@filesize($zip) ?: 0),
        'created_at' => $meta['created_at'] ?? date('Y-m-d H:i:s', $mtime),
        'job_id' => $meta['job_id'] ?? preg_replace('/\.zip$/i', '', $filename),
    ];
}

usort($backups, static function ($a, $b) {
    return strcmp($b['created_at'], $a['created_at']);
});

$typeLabels = [
    'files' => 'فقط فایل‌های سایت',
    'database' => 'فقط دیتابیس',
    'full' => 'پشتیبان کامل'
];

require_once __DIR__ . '/includes/header.php';
?>

<div class="backup-page">

    <div class="backup-hero">
        <div class="backup-hero-icon">
            <svg viewBox="0 0 24 24" aria-hidden="true">
                <path d="M12 3v11"></path>
                <path d="m8 7 4-4 4 4"></path>
                <path d="M5 13v5a3 3 0 0 0 3 3h8a3 3 0 0 0 3-3v-5"></path>
            </svg>
        </div>
        <div>
            <h1>پشتیبان‌گیری</h1>
            <p>از فایل‌های سامانه، دیتابیس یا کل سامانه یک نسخه ZIP امن تهیه کنید.</p>
        </div>
    </div>

    <div class="backup-note">
        <span class="note-icon">🔐</span>
        <div>
            <strong>پشتیبان‌ها در مسیر عمومی وب ذخیره نمی‌شوند.</strong>
            <p>فایل‌ها در فضای خصوصی سرور نگهداری شده و دانلود فقط برای مدیر واردشده امکان‌پذیر است.</p>
        </div>
    </div>

    <div class="backup-cards">

        <div class="backup-card">
            <div class="backup-card-icon files-icon">
                <svg viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M4 5a2 2 0 0 1 2-2h7l5 5v11a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2z"></path>
                    <path d="M13 3v6h6"></path>
                    <path d="M8 13h8M8 17h6"></path>
                </svg>
            </div>
            <h2>فایل‌های سایت</h2>
            <p>تمام فایل‌های PHP، CSS، JavaScript، تصاویر و سایر فایل‌های پروژه، بدون دیتابیس زنده.</p>
            <button type="button" class="backup-action" data-type="files">
                <span>ایجاد پشتیبان</span>
                <span>→</span>
            </button>
        </div>

        <div class="backup-card">
            <div class="backup-card-icon database-icon">
                <svg viewBox="0 0 24 24" aria-hidden="true">
                    <ellipse cx="12" cy="5" rx="7" ry="3"></ellipse>
                    <path d="M5 5v7c0 1.66 3.13 3 7 3s7-1.34 7-3V5"></path>
                    <path d="M5 12v7c0 1.66 3.13 3 7 3s7-1.34 7-3v-7"></path>
                </svg>
            </div>
            <h2>فقط دیتابیس</h2>
            <p>یک نسخه سازگار از <code>neal.db</code> تهیه می‌شود تا اطلاعات سامانه به‌صورت مستقل قابل نگهداری باشد.</p>
            <button type="button" class="backup-action" data-type="database">
                <span>ایجاد پشتیبان</span>
                <span>→</span>
            </button>
        </div>

        <div class="backup-card featured">
            <div class="featured-badge">پیشنهادی</div>
            <div class="backup-card-icon full-icon">
                <svg viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M12 3 5 6v5c0 4.6 2.9 8.8 7 10 4.1-1.2 7-5.4 7-10V6z"></path>
                    <path d="M9 12h6M12 9v6"></path>
                </svg>
            </div>
            <h2>پشتیبان کامل</h2>
            <p>همه فایل‌های سامانه به‌همراه یک نسخه سازگار از دیتابیس در یک فایل ZIP.</p>
            <button type="button" class="backup-action primary" data-type="full">
                <span>ایجاد پشتیبان کامل</span>
                <span>→</span>
            </button>
        </div>

    </div>

    <div id="backupProgress" class="backup-progress-card" hidden>
        <div class="progress-header">
            <div>
                <h2 id="progressTitle">در حال تهیه پشتیبان</h2>
                <p id="progressStage">در حال آماده‌سازی...</p>
            </div>
            <button type="button" id="closeProgress" class="close-progress" hidden aria-label="بستن">×</button>
        </div>

        <div class="progress-content">
            <div class="progress-ring-wrap">
                <svg class="progress-ring" viewBox="0 0 120 120">
                    <circle class="progress-ring-bg" cx="60" cy="60" r="50"></circle>
                    <circle id="progressCircle" class="progress-ring-value" cx="60" cy="60" r="50"></circle>
                </svg>
                <div class="progress-percent" id="progressPercent">۰٪</div>
            </div>

            <div class="progress-details">
                <strong id="progressMessage">در حال شروع عملیات...</strong>
                <span id="progressFile">لطفاً تا پایان عملیات صفحه را نبندید.</span>
            </div>
        </div>

        <div id="progressResult" class="progress-result" hidden></div>
    </div>

    <div class="backup-list-card">
        <div class="backup-list-header">
            <div>
                <h2>آخرین پشتیبان‌ها</h2>
                <p>فایل‌های ایجادشده روی سرور</p>
            </div>
            <span class="backup-count"><?php echo backup_h(backup_persian_number(count($backups))); ?> فایل</span>
        </div>

        <?php if (!$backups): ?>
            <div class="backup-empty">
                <div class="empty-icon">📦</div>
                <h3>هنوز پشتیبانی ایجاد نشده است</h3>
                <p>از یکی از گزینه‌های بالا برای ایجاد اولین پشتیبان استفاده کنید.</p>
            </div>
        <?php else: ?>
            <div class="backup-table-wrap">
                <table class="backup-table">
                    <thead>
                        <tr>
                            <th>نوع</th>
                            <th>نام فایل</th>
                            <th>حجم</th>
                            <th>تاریخ و ساعت</th>
                            <th>عملیات</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($backups as $backup): ?>
                        <tr>
                            <td>
                                <span class="backup-type <?php echo backup_h($backup['type']); ?>">
                                    <?php echo backup_h($backup['type_label']); ?>
                                </span>
                            </td>
                            <td>
                                <div class="filename-cell">
                                    <span class="zip-icon">ZIP</span>
                                    <span dir="ltr"><?php echo backup_h($backup['filename']); ?></span>
                                </div>
                            </td>
                            <td><?php echo backup_h(backup_format_size($backup['size'])); ?></td>
                            <td dir="ltr"><?php echo backup_h(backup_persian_number(backup_jalali_date($backup['created_at']))); ?></td>
                            <td>
                                <div class="backup-actions">
                                    <a class="table-action download-action"
                                       href="backup-download.php?file=<?php echo rawurlencode($backup['filename']); ?>"
                                       title="دانلود">
                                        <svg viewBox="0 0 24 24"><path d="M12 3v12"></path><path d="m7 10 5 5 5-5"></path><path d="M5 21h14"></path></svg>
                                        <span>دانلود</span>
                                    </a>
                                    <button type="button"
                                            class="table-action delete-action delete-backup"
                                            data-file="<?php echo backup_h($backup['filename']); ?>"
                                            title="حذف">
                                        <svg viewBox="0 0 24 24"><path d="M4 7h16"></path><path d="M10 11v6M14 11v6"></path><path d="m9 7 .7-2h4.6l.7 2"></path><path d="M6 7l1 14h10l1-14"></path></svg>
                                        <span>حذف</span>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

</div>

<div id="backupToast" class="backup-toast" hidden></div>

<style>
.backup-page{max-width:1500px;margin:0 auto;padding-bottom:35px}
.backup-hero{display:flex;align-items:center;gap:18px;margin-bottom:22px}
.backup-hero-icon{width:62px;height:62px;border-radius:16px;background:#0b1f3a;color:#fff;display:flex;align-items:center;justify-content:center;box-shadow:0 8px 22px rgba(11,31,58,.15)}
.backup-hero-icon svg{width:31px;height:31px;fill:none;stroke:currentColor;stroke-width:1.8;stroke-linecap:round;stroke-linejoin:round}
.backup-hero h1{margin:0 0 7px;color:#0b1f3a;font-size:29px}
.backup-hero p{margin:0;color:#718096;font-size:14px}
.backup-note{display:flex;align-items:flex-start;gap:13px;background:#f7fafc;border:1px solid #e5eaf0;border-radius:12px;padding:15px 18px;margin-bottom:24px}
.note-icon{font-size:22px}
.backup-note strong{color:#0b1f3a;font-size:13px}
.backup-note p{margin:5px 0 0;color:#718096;font-size:12px}
.backup-cards{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:18px;margin-bottom:25px}
.backup-card{position:relative;background:#fff;border:1px solid #e5eaf0;border-radius:15px;padding:24px;box-shadow:0 4px 14px rgba(0,0,0,.035);transition:.2s}
.backup-card:hover{transform:translateY(-2px);box-shadow:0 8px 22px rgba(0,0,0,.07)}
.backup-card.featured{border-color:#b9cde0;box-shadow:0 7px 24px rgba(11,31,58,.08)}
.featured-badge{position:absolute;top:14px;left:14px;background:#eaf3fb;color:#1769aa;border-radius:20px;padding:5px 10px;font-size:11px;font-weight:700}
.backup-card-icon{width:54px;height:54px;border-radius:14px;display:flex;align-items:center;justify-content:center;margin-bottom:17px}
.backup-card-icon svg{width:28px;height:28px;fill:none;stroke:currentColor;stroke-width:1.8;stroke-linecap:round;stroke-linejoin:round}
.files-icon{background:#edf6ff;color:#1769aa}.database-icon{background:#eef8f2;color:#16805a}.full-icon{background:#f4efff;color:#6b46c1}
.backup-card h2{margin:0 0 9px;color:#0b1f3a;font-size:18px}
.backup-card p{min-height:51px;margin:0 0 19px;color:#718096;font-size:13px;line-height:1.9}
.backup-card code{direction:ltr;display:inline-block}
.backup-action{width:100%;border:1px solid #dbe3ea;background:#fff;color:#0b1f3a;border-radius:9px;padding:11px 14px;font-family:inherit;font-size:13px;font-weight:700;display:flex;align-items:center;justify-content:space-between;cursor:pointer;transition:.2s}
.backup-action:hover{background:#f5f8fb;border-color:#b9c8d6}
.backup-action.primary{background:#0b1f3a;color:#fff;border-color:#0b1f3a}.backup-action.primary:hover{background:#17385d}
.backup-progress-card{background:#fff;border:1px solid #e1e8ef;border-radius:15px;padding:22px 25px;margin-bottom:25px;box-shadow:0 5px 18px rgba(0,0,0,.05)}
.progress-header{display:flex;justify-content:space-between;align-items:flex-start;border-bottom:1px solid #edf1f5;padding-bottom:16px}
.progress-header h2{margin:0 0 5px;color:#0b1f3a;font-size:18px}.progress-header p{margin:0;color:#718096;font-size:13px}
.close-progress{border:0;background:#f3f5f7;color:#667085;width:34px;height:34px;border-radius:8px;font-size:24px;cursor:pointer}
.progress-content{display:flex;align-items:center;justify-content:center;gap:35px;padding:27px 10px}
.progress-ring-wrap{position:relative;width:150px;height:150px;flex:0 0 150px}
.progress-ring{width:150px;height:150px;transform:rotate(-90deg)}
.progress-ring-bg{fill:none;stroke:#edf1f5;stroke-width:10}
.progress-ring-value{fill:none;stroke:#1769aa;stroke-width:10;stroke-linecap:round;stroke-dasharray:314.159;stroke-dashoffset:314.159;transition:stroke-dashoffset .35s ease}
.progress-percent{position:absolute;inset:0;display:flex;align-items:center;justify-content:center;color:#0b1f3a;font-size:27px;font-weight:800}
.progress-details{display:flex;flex-direction:column;gap:8px}.progress-details strong{color:#0b1f3a;font-size:16px}.progress-details span{color:#718096;font-size:13px}
.progress-result{border-radius:11px;padding:16px;text-align:center;font-size:14px;font-weight:700}
.progress-result.success{background:#edf9f3;color:#087443;border:1px solid #c8eedb}.progress-result.error{background:#fff1f1;color:#b42318;border:1px solid #f5caca}
.backup-list-card{background:#fff;border:1px solid #e5eaf0;border-radius:15px;box-shadow:0 4px 14px rgba(0,0,0,.035);overflow:hidden}
.backup-list-header{display:flex;justify-content:space-between;align-items:center;padding:21px 23px;border-bottom:1px solid #edf1f5}
.backup-list-header h2{margin:0 0 5px;color:#0b1f3a;font-size:18px}.backup-list-header p{margin:0;color:#718096;font-size:12px}
.backup-count{background:#f3f6f9;color:#667085;padding:6px 10px;border-radius:20px;font-size:12px;font-weight:700}
.backup-table-wrap{overflow-x:auto}.backup-table{width:100%;border-collapse:collapse;min-width:820px}
.backup-table th{text-align:right;background:#fafbfd;color:#667085;font-size:12px;padding:13px 16px;border-bottom:1px solid #edf1f5;white-space:nowrap}
.backup-table td{padding:15px 16px;border-bottom:1px solid #f0f2f4;color:#475467;font-size:12px;vertical-align:middle}.backup-table tbody tr:last-child td{border-bottom:0}.backup-table tbody tr:hover{background:#fbfcfd}
.backup-type{display:inline-block;padding:6px 10px;border-radius:7px;font-weight:700;font-size:11px}.backup-type.files{background:#edf6ff;color:#1769aa}.backup-type.database{background:#eef8f2;color:#16805a}.backup-type.full{background:#f4efff;color:#6b46c1}
.filename-cell{display:flex;align-items:center;gap:9px}.zip-icon{font-size:9px;font-weight:800;background:#0b1f3a;color:#fff;border-radius:5px;padding:4px 5px;letter-spacing:.3px}
.backup-actions{display:flex;gap:7px}.table-action{border-radius:7px;padding:7px 9px;display:inline-flex;align-items:center;gap:5px;text-decoration:none;font-family:inherit;font-size:11px;font-weight:700;cursor:pointer}
.table-action svg{width:15px;height:15px;fill:none;stroke:currentColor;stroke-width:1.8;stroke-linecap:round;stroke-linejoin:round}
.download-action{background:#edf6ff;color:#1769aa;border:1px solid #d6e9fa}.delete-action{background:#fff2f2;color:#c0392b;border:1px solid #f4d4d1}
.backup-empty{text-align:center;padding:55px 25px}.empty-icon{width:65px;height:65px;border-radius:50%;background:#f5f7f9;display:flex;align-items:center;justify-content:center;font-size:29px;margin:0 auto 15px}.backup-empty h3{margin:0 0 8px;color:#344054}.backup-empty p{margin:0;color:#98a2b3;font-size:13px}
.backup-toast{position:fixed;left:25px;bottom:25px;background:#0b1f3a;color:#fff;padding:12px 16px;border-radius:9px;box-shadow:0 8px 25px rgba(0,0,0,.18);z-index:9999;font-size:13px}
@media(max-width:1000px){.backup-cards{grid-template-columns:1fr}.backup-card p{min-height:auto}}
@media(max-width:700px){.backup-page{padding:0 2px 25px}.backup-hero h1{font-size:24px}.progress-content{flex-direction:column;gap:18px;text-align:center}.backup-actions{flex-wrap:wrap}}
</style>

<script>
(function () {
    const csrf = <?php echo json_encode($csrf, JSON_UNESCAPED_UNICODE); ?>;
    const progressBox = document.getElementById('backupProgress');
    const progressCircle = document.getElementById('progressCircle');
    const progressPercent = document.getElementById('progressPercent');
    const progressTitle = document.getElementById('progressTitle');
    const progressStage = document.getElementById('progressStage');
    const progressMessage = document.getElementById('progressMessage');
    const progressFile = document.getElementById('progressFile');
    const progressResult = document.getElementById('progressResult');
    const closeProgress = document.getElementById('closeProgress');
    const circumference = 2 * Math.PI * 50;
    let pollTimer = null;
    let activeJob = null;

    function faNumber(value) {
        return String(value).replace(/\d/g, d => '۰۱۲۳۴۵۶۷۸۹'[d]);
    }

    function setProgress(percent) {
        percent = Math.max(0, Math.min(100, Number(percent) || 0));
        progressCircle.style.strokeDashoffset = String(circumference - (circumference * percent / 100));
        progressPercent.textContent = faNumber(Math.round(percent)) + '٪';
    }

    function showProgress() {
        progressBox.hidden = false;
        progressResult.hidden = true;
        closeProgress.hidden = true;
        setProgress(0);
        progressBox.scrollIntoView({behavior:'smooth', block:'center'});
    }

    function finishUi(success, message) {
        if (pollTimer) {
            clearTimeout(pollTimer);
            pollTimer = null;
        }

        setProgress(success ? 100 : 0);
        progressResult.hidden = false;
        progressResult.className = 'progress-result ' + (success ? 'success' : 'error');
        progressResult.innerHTML = success
            ? '✓ پشتیبان با موفقیت ایجاد شد. <a href="backup-download.php?file=' + encodeURIComponent(activeJob.filename || '') + '" style="color:inherit;text-decoration:underline">دانلود پشتیبان</a>'
            : '✕ ' + message;

        progressTitle.textContent = success ? 'پشتیبان آماده است' : 'عملیات پشتیبان‌گیری ناموفق بود';
        progressStage.textContent = success ? 'عملیات با موفقیت به پایان رسید.' : 'خطا در اجرای عملیات';
        progressMessage.textContent = success ? 'فایل ZIP آماده استفاده است.' : 'پشتیبان ایجاد نشد.';
        progressFile.textContent = success ? (activeJob.filename || '') : message;
        closeProgress.hidden = false;
    }

    async function pollStatus() {
        if (!activeJob) return;

        try {
            const response = await fetch('backup-status.php?job=' + encodeURIComponent(activeJob.job_id) + '&csrf=' + encodeURIComponent(csrf), {cache:'no-store'});
            const data = await response.json();

            if (!data.ok) {
                finishUi(false, data.message || 'خطا در دریافت وضعیت عملیات.');
                return;
            }

            const job = data.job || {};
            activeJob = job;

            setProgress(job.progress || 0);
            progressStage.textContent = job.stage_label || 'در حال انجام...';
            progressMessage.textContent = job.message || 'در حال پردازش...';
            progressFile.textContent = job.current_file || '';

            if (job.status === 'success') {
                finishUi(true, job.message || 'انجام شد.');
                return;
            }

            if (job.status === 'error') {
                finishUi(false, job.message || 'خطای ناشناخته.');
                return;
            }

            pollTimer = setTimeout(pollStatus, 700);
        } catch (error) {
            finishUi(false, 'ارتباط با سرویس وضعیت پشتیبان‌گیری برقرار نشد.');
        }
    }

    async function startBackup(type) {
        showProgress();
        progressTitle.textContent = 'در حال تهیه پشتیبان';
        progressStage.textContent = 'در حال قرار دادن عملیات در صف...';
        progressMessage.textContent = 'در حال شروع...';
        progressFile.textContent = '';

        try {
            const body = new URLSearchParams();
            body.set('type', type);
            body.set('csrf', csrf);

            const response = await fetch('backup-create.php', {
                method: 'POST',
                headers: {'Content-Type':'application/x-www-form-urlencoded;charset=UTF-8'},
                body: body.toString()
            });

            const data = await response.json();

            if (!data.ok) {
                finishUi(false, data.message || 'شروع عملیات ممکن نبود.');
                return;
            }

            activeJob = {job_id:data.job_id};
            pollStatus();
        } catch (error) {
            finishUi(false, 'ارتباط با سرور برای شروع پشتیبان‌گیری برقرار نشد.');
        }
    }

    document.querySelectorAll('.backup-action').forEach(function (button) {
        button.addEventListener('click', function () {
            const type = button.dataset.type;
            const labels = {
                files: 'فایل‌های سایت',
                database: 'دیتابیس',
                full: 'پشتیبان کامل'
            };
            if (confirm('پشتیبان «' + labels[type] + '» ایجاد شود؟')) {
                startBackup(type);
            }
        });
    });

    closeProgress.addEventListener('click', function () {
        progressBox.hidden = true;
        window.location.reload();
    });

    document.querySelectorAll('.delete-backup').forEach(function (button) {
        button.addEventListener('click', async function () {
            const file = button.dataset.file;
            if (!confirm('آیا از حذف این پشتیبان مطمئن هستید؟\n\n' + file)) {
                return;
            }

            button.disabled = true;

            try {
                const body = new URLSearchParams();
                body.set('file', file);
                body.set('csrf', csrf);

                const response = await fetch('backup-delete.php', {
                    method: 'POST',
                    headers: {'Content-Type':'application/x-www-form-urlencoded;charset=UTF-8'},
                    body: body.toString()
                });

                const data = await response.json();

                if (!data.ok) {
                    alert(data.message || 'حذف انجام نشد.');
                    button.disabled = false;
                    return;
                }

                window.location.reload();
            } catch (error) {
                alert('ارتباط با سرور برای حذف پشتیبان برقرار نشد.');
                button.disabled = false;
            }
        });
    });
})();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
