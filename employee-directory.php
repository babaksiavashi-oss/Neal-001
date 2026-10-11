<?php
$portal_mode = false;
require_once __DIR__ . "/auth/auth.php";

if (!isset($_SESSION['admin_id']) && !isset($_SESSION['user_id'])) {
    header("Location: /neal/login.php");
    exit;
}

$portal_mode = isset($_SESSION['user_id']) && !isset($_SESSION['admin_id']);
require_once __DIR__ . "/includes/user-profile.php";

$page_title = "دایرکتوری کارکنان";
$db = neal_profile_db();

if (!function_exists('employee_h')) {
    function employee_h($value) {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

$search = trim((string)($_GET['q'] ?? ''));
if (function_exists('mb_substr')) {
    $search = mb_substr($search, 0, 100, 'UTF-8');
} else {
    $search = substr($search, 0, 100);
}
$selectedDepartment = trim((string)($_GET['department'] ?? ''));

$departments = [];
$employees = [];
$totalActive = 0;
$departmentCount = 0;
$errorMessage = '';

try {
    $totalActive = (int)$db->query("SELECT COUNT(*) FROM users WHERE is_active = 1")->fetchColumn();

    $departmentQuery = $db->query("
        SELECT DISTINCT
            u.department AS department_code,
            COALESCE(NULLIF(TRIM(d.name), ''), NULLIF(TRIM(u.department), ''), 'بدون واحد') AS department_name
        FROM users u
        LEFT JOIN departments d ON d.code = u.department
        WHERE u.is_active = 1
          AND TRIM(COALESCE(u.department, '')) <> ''
        ORDER BY department_name COLLATE NOCASE
    ");
    $departments = $departmentQuery->fetchAll(PDO::FETCH_ASSOC);
    $departmentCount = count($departments);

    $where = ["u.is_active = 1"];
    $params = [];

    if ($search !== '') {
        $where[] = "(
            COALESCE(u.fullname, '') LIKE ? COLLATE NOCASE
            OR COALESCE(u.username, '') LIKE ? COLLATE NOCASE
            OR COALESCE(u.personnel_code, '') LIKE ? COLLATE NOCASE
            OR COALESCE(u.department, '') LIKE ? COLLATE NOCASE
            OR COALESCE(d.name, '') LIKE ? COLLATE NOCASE
            OR COALESCE(u.phone, '') LIKE ? COLLATE NOCASE
        )";
        $term = '%' . $search . '%';
        array_push($params, $term, $term, $term, $term, $term, $term);
    }

    if ($selectedDepartment !== '') {
        $where[] = "u.department = ?";
        $params[] = $selectedDepartment;
    }

    $sql = "
        SELECT
            u.id,
            COALESCE(u.username, '') AS username,
            COALESCE(NULLIF(TRIM(u.fullname), ''), u.username, 'بدون نام') AS fullname,
            COALESCE(NULLIF(TRIM(u.personnel_code), ''), '') AS personnel_code,
            COALESCE(NULLIF(TRIM(u.department), ''), '') AS department,
            COALESCE(NULLIF(TRIM(u.phone), ''), '') AS phone,
            COALESCE(NULLIF(u.avatar, ''), 'default') AS avatar,
            COALESCE(NULLIF(TRIM(u.user_status), ''), '') AS user_status,
            COALESCE(NULLIF(TRIM(d.name), ''), NULLIF(TRIM(u.department), ''), 'بدون واحد') AS department_name
        FROM users u
        LEFT JOIN departments d ON d.code = u.department
        WHERE " . implode(" AND ", $where) . "
        ORDER BY fullname COLLATE NOCASE ASC, u.id ASC
    ";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $errorMessage = 'دریافت فهرست کارکنان انجام نشد. لطفاً اتصال پایگاه داده و ساختار جدول کاربران را بررسی کنید.';
}

$resultCount = count($employees);
$hasFilters = ($search !== '' || $selectedDepartment !== '');
$selectedDepartmentName = '';
if ($selectedDepartment !== '') {
    foreach ($departments as $department) {
        if ((string)$department['department_code'] === $selectedDepartment) {
            $selectedDepartmentName = (string)$department['department_name'];
            break;
        }
    }
}
?>
<?php include __DIR__ . "/includes/header.php"; ?>

<main class="employee-directory-page">
    <section class="employee-directory-hero">
        <div class="employee-directory-hero-content">
            <div class="employee-directory-breadcrumb">
                <span class="employee-directory-breadcrumb-mark" aria-hidden="true"></span>
                ارتباطات داخلی <span aria-hidden="true">/</span> منابع انسانی
            </div>
            <h1>دایرکتوری کارکنان</h1>
            <p>همکاران NEAL را سریع پیدا کنید؛ اطلاعات تماس و واحد سازمانی در یک نگاه.</p>
            <div class="employee-directory-hero-note">
                <span class="employee-directory-live-dot" aria-hidden="true"></span>
                فهرست همکاران فعال شرکت
            </div>
        </div>
        <div class="employee-directory-hero-art" aria-hidden="true">
            <div class="employee-directory-art-circle employee-directory-art-circle-one"></div>
            <div class="employee-directory-art-circle employee-directory-art-circle-two"></div>
            <div class="employee-directory-art-card">
                <svg viewBox="0 0 64 64"><circle cx="23" cy="21" r="10"></circle><path d="M5 53c0-10 8-17 18-17s18 7 18 17"></path><circle cx="45" cy="25" r="7"></circle><path d="M43 38c9-1 16 5 16 14"></path></svg>
                <span>NEAL TEAM</span>
            </div>
        </div>
    </section>

    <section class="employee-directory-stats" aria-label="خلاصه کارکنان">
        <article class="employee-directory-stat">
            <span class="employee-directory-stat-icon stat-icon-blue" aria-hidden="true">
                <svg viewBox="0 0 24 24"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
            </span>
            <span class="employee-directory-stat-copy"><small>کل کارکنان فعال</small><strong><?php echo number_format($totalActive); ?></strong></span>
        </article>
        <article class="employee-directory-stat">
            <span class="employee-directory-stat-icon stat-icon-teal" aria-hidden="true">
                <svg viewBox="0 0 24 24"><rect x="3" y="7" width="18" height="14" rx="2"></rect><path d="M8 7V4h8v3M8 12h.01M12 12h.01M16 12h.01M8 16h.01M12 16h.01M16 16h.01"></path></svg>
            </span>
            <span class="employee-directory-stat-copy"><small>واحدهای سازمانی</small><strong><?php echo number_format($departmentCount); ?></strong></span>
        </article>
        <article class="employee-directory-stat employee-directory-stat-highlight">
            <span class="employee-directory-stat-icon stat-icon-purple" aria-hidden="true">
                <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"></circle><path d="m20 20-4-4"></path></svg>
            </span>
            <span class="employee-directory-stat-copy"><small><?php echo $hasFilters ? 'نتیجه جستجو' : 'نمایش در فهرست'; ?></small><strong><?php echo number_format($resultCount); ?></strong></span>
        </article>
    </section>

    <section class="employee-directory-search-panel">
        <div class="employee-directory-panel-heading">
            <div>
                <span class="employee-directory-section-kicker">جستجوی هوشمند</span>
                <h2>همکار موردنظر خود را پیدا کنید</h2>
                <p>نام، نام کاربری، کد پرسنلی، واحد یا شماره تماس را وارد کنید.</p>
            </div>
            <span class="employee-directory-search-panel-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"></circle><path d="m20 20-4-4"></path></svg>
            </span>
        </div>
        <form method="get" action="/neal/employee-directory.php" class="employee-directory-filters">
            <label class="employee-directory-search-control">
                <span class="employee-directory-control-label">عبارت جستجو</span>
                <span class="employee-directory-input-wrap">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"></circle><path d="m20 20-4-4"></path></svg>
                    <input type="search" name="q" value="<?php echo employee_h($search); ?>" placeholder="مثلاً نام همکار یا شماره تماس" autocomplete="off">
                    <?php if ($search !== ''): ?><a class="employee-directory-input-clear" href="/neal/employee-directory.php<?php echo $selectedDepartment !== '' ? '?department=' . rawurlencode($selectedDepartment) : ''; ?>" aria-label="پاک کردن عبارت جستجو">×</a><?php endif; ?>
                </span>
            </label>
            <label class="employee-directory-department-control">
                <span class="employee-directory-control-label">فیلتر واحد سازمانی</span>
                <span class="employee-directory-select-wrap">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="7" width="18" height="14" rx="2"></rect><path d="M8 7V4h8v3"></path></svg>
                    <select name="department">
                        <option value="">تمام واحدهای سازمانی</option>
                        <?php foreach ($departments as $department): ?>
                            <option value="<?php echo employee_h($department['department_code']); ?>" <?php echo $selectedDepartment === (string)$department['department_code'] ? 'selected' : ''; ?>><?php echo employee_h($department['department_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <span class="employee-directory-select-chevron" aria-hidden="true">⌄</span>
                </span>
            </label>
            <div class="employee-directory-filter-actions">
                <button type="submit" class="employee-directory-search-button">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"></circle><path d="m20 20-4-4"></path></svg>
                    جستجو
                </button>
                <?php if ($hasFilters): ?><a class="employee-directory-reset-button" href="/neal/employee-directory.php">حذف فیلترها</a><?php endif; ?>
            </div>
        </form>
        <div class="employee-directory-results-meta">
            <span class="employee-directory-results-count"><strong><?php echo number_format($resultCount); ?></strong> همکار</span>
            <span class="employee-directory-results-separator"></span>
            <span>مرتب‌سازی بر اساس نام و نام خانوادگی</span>
            <?php if ($selectedDepartmentName !== ''): ?><span class="employee-directory-active-filter">واحد: <?php echo employee_h($selectedDepartmentName); ?></span><?php endif; ?>
        </div>
    </section>

    <?php if ($errorMessage !== ''): ?>
        <section class="employee-directory-state employee-directory-state-error">
            <span class="employee-directory-state-icon" aria-hidden="true">!</span>
            <h2>بارگذاری فهرست با مشکل مواجه شد</h2>
            <p><?php echo employee_h($errorMessage); ?></p>
        </section>
    <?php elseif ($resultCount > 0): ?>
        <section class="employee-directory-listing">
            <div class="employee-directory-listing-heading">
                <div><h2>همکاران ما</h2><p>برای تماس، روی شماره تلفن همکار کلیک کنید.</p></div>
                <span class="employee-directory-listing-count"><?php echo number_format($resultCount); ?> نفر</span>
            </div>
            <div class="employee-directory-grid" aria-label="فهرست کارکنان">
                <?php foreach ($employees as $employee): ?>
                    <?php
                    $avatarUrl = neal_avatar_url($employee['avatar']);
                    $phone = trim((string)$employee['phone']);
                    $phoneHref = preg_replace('/[^0-9+]/', '', $phone);
                    $initial = function_exists('mb_substr') ? mb_substr((string)$employee['fullname'], 0, 1, 'UTF-8') : substr((string)$employee['fullname'], 0, 1);
                    ?>
                    <article class="employee-directory-card">
                        <div class="employee-directory-card-accent"></div>
                        <div class="employee-directory-card-main">
                            <div class="employee-directory-person">
                                <div class="employee-directory-avatar">
                                    <img src="<?php echo employee_h($avatarUrl); ?>" alt="تصویر <?php echo employee_h($employee['fullname']); ?>" loading="lazy" onerror="this.onerror=null;this.src='/neal/images/avatars/default.svg';">
                                </div>
                                <div class="employee-directory-person-name">
                                    <h3><?php echo employee_h($employee['fullname']); ?></h3>
                                    <span class="employee-directory-username" dir="ltr"><?php echo $employee['username'] !== '' ? '@' . employee_h($employee['username']) : '—'; ?></span>
                                </div>
                                <span class="employee-directory-active"><i></i>فعال</span>
                            </div>
                            <?php if ($employee['user_status'] !== ''): ?>
                                <div class="employee-directory-user-status"><span aria-hidden="true">✦</span><?php echo employee_h($employee['user_status']); ?></div>
                            <?php endif; ?>
                            <div class="employee-directory-details">
                                <div class="employee-directory-detail">
                                    <span class="employee-directory-detail-icon department-icon" aria-hidden="true">
                                        <svg viewBox="0 0 24 24"><rect x="3" y="7" width="18" height="14" rx="2"></rect><path d="M8 7V4h8v3M8 12h.01M12 12h.01M16 12h.01M8 16h.01M12 16h.01M16 16h.01"></path></svg>
                                    </span>
                                    <span class="employee-directory-detail-copy"><small>واحد سازمانی</small><strong><?php echo employee_h($employee['department_name']); ?></strong></span>
                                </div>
                                <div class="employee-directory-detail">
                                    <span class="employee-directory-detail-icon code-icon" aria-hidden="true">
                                        <svg viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="14" rx="2"></rect><path d="M7 9h4M7 13h2M15 9h2M14 13h3"></path></svg>
                                    </span>
                                    <span class="employee-directory-detail-copy"><small>کد پرسنلی</small><strong dir="ltr"><?php echo $employee['personnel_code'] !== '' ? employee_h($employee['personnel_code']) : 'ثبت نشده'; ?></strong></span>
                                </div>
                            </div>
                            <div class="employee-directory-card-footer">
                                <span class="employee-directory-phone-label">
                                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M22 16.92v3a2 2 0 0 1-2.18 2A19.8 19.8 0 0 1 11.2 18a19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.09 3.18 2 2 0 0 1 4.08 1h3a2 2 0 0 1 2 1.72c.12.9.34 1.78.65 2.62a2 2 0 0 1-.45 2.11L8 8.73a16 16 0 0 0 6 6l1.28-1.28a2 2 0 0 1 2.11-.45c.84.31 1.72.53 2.62.65A2 2 0 0 1 22 16.92Z"></path></svg>
                                    شماره تماس
                                </span>
                                <?php if ($phone !== '' && $phoneHref !== ''): ?>
                                    <a class="employee-directory-phone" href="tel:<?php echo employee_h($phoneHref); ?>" dir="ltr"><?php echo employee_h($phone); ?></a>
                                <?php else: ?>
                                    <span class="employee-directory-no-phone">ثبت نشده</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>
    <?php else: ?>
        <section class="employee-directory-state">
            <span class="employee-directory-state-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"></circle><path d="m20 20-4-4"></path></svg>
            </span>
            <h2><?php echo $hasFilters ? 'همکاری با این مشخصات پیدا نشد' : 'هنوز کارمند فعالی ثبت نشده است'; ?></h2>
            <p><?php echo $hasFilters ? 'عبارت جستجو یا واحد سازمانی را تغییر دهید و دوباره تلاش کنید.' : 'پس از ثبت و فعال‌سازی کاربران، فهرست همکاران در این بخش نمایش داده می‌شود.'; ?></p>
            <?php if ($hasFilters): ?><a href="/neal/employee-directory.php">نمایش همه کارکنان</a><?php endif; ?>
        </section>
    <?php endif; ?>
</main>

<?php include __DIR__ . "/includes/footer.php"; ?>
