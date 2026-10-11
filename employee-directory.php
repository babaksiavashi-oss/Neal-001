<?php
$portal_mode = false;
require_once "auth/auth.php";

if (!isset($_SESSION['admin_id']) && !isset($_SESSION['user_id'])) {
    header("Location: /neal/login.php");
    exit;
}

$portal_mode = isset($_SESSION['user_id']) && !isset($_SESSION['admin_id']);
require_once "includes/user-profile.php";

$page_title = "دایرکتوری کارکنان";
$db = neal_profile_db();

function employee_h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$search = trim((string)($_GET['q'] ?? ''));
if (strlen($search) > 100) {
    $search = substr($search, 0, 100);
}
$selectedDepartment = trim((string)($_GET['department'] ?? ''));

$departments = [];
try {
    $departmentQuery = $db->query("
        SELECT DISTINCT
            u.department AS department_code,
            COALESCE(NULLIF(d.name, ''), u.department) AS department_name
        FROM users u
        LEFT JOIN departments d ON d.code = u.department
        WHERE u.is_active = 1
          AND TRIM(COALESCE(u.department, '')) <> ''
        ORDER BY department_name COLLATE NOCASE
    ");
    $departments = $departmentQuery->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $departments = [];
}

$where = ["u.is_active = 1"];
$params = [];

if ($search !== '') {
    $where[] = "(
        u.fullname LIKE ? COLLATE NOCASE
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
        u.username,
        u.fullname,
        u.personnel_code,
        u.department,
        u.phone,
        COALESCE(NULLIF(u.avatar, ''), 'default') AS avatar,
        COALESCE(u.user_status, '') AS user_status,
        COALESCE(NULLIF(d.name, ''), u.department, 'بدون واحد') AS department_name
    FROM users u
    LEFT JOIN departments d ON d.code = u.department
    WHERE " . implode(" AND ", $where) . "
    ORDER BY u.fullname COLLATE NOCASE ASC
";

$employees = [];
$errorMessage = '';
try {
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $errorMessage = 'دریافت فهرست کارکنان انجام نشد. لطفاً دوباره تلاش کنید.';
}
?>
<?php include "includes/header.php"; ?>

<main class="employee-directory-page">
    <section class="employee-directory-hero">
        <div class="employee-directory-hero-copy">
            <span class="employee-directory-eyebrow"><span></span>ارتباطات داخلی NEAL</span>
            <h1>دایرکتوری کارکنان</h1>
            <p>همکاران خود را بر اساس نام، واحد سازمانی، کد پرسنلی یا شماره تماس پیدا کنید.</p>
        </div>
        <div class="employee-directory-total">
            <span class="employee-directory-total-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg>
            </span>
            <span><strong><?php echo number_format(count($employees)); ?></strong><small>کارمند فعال در این فهرست</small></span>
        </div>
    </section>

    <section class="employee-directory-filter-card">
        <form method="get" action="employee-directory.php" class="employee-directory-filters">
            <label class="employee-directory-search">
                <span class="employee-directory-filter-label">جستجوی همکار</span>
                <span class="employee-directory-search-field">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/></svg>
                    <input type="search" name="q" value="<?php echo employee_h($search); ?>" placeholder="نام، کد پرسنلی، نام کاربری یا تلفن">
                </span>
            </label>
            <label class="employee-directory-department">
                <span class="employee-directory-filter-label">واحد سازمانی</span>
                <select name="department">
                    <option value="">همه واحدها</option>
                    <?php foreach ($departments as $department): ?>
                        <option value="<?php echo employee_h($department['department_code']); ?>" <?php echo $selectedDepartment === (string)$department['department_code'] ? 'selected' : ''; ?>>
                            <?php echo employee_h($department['department_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <div class="employee-directory-filter-actions">
                <button type="submit" class="employee-directory-search-button">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/></svg>
                    جستجو
                </button>
                <?php if ($search !== '' || $selectedDepartment !== ''): ?>
                    <a class="employee-directory-reset-button" href="employee-directory.php">پاک کردن فیلترها</a>
                <?php endif; ?>
            </div>
        </form>
        <div class="employee-directory-result-line">
            <span><?php echo number_format(count($employees)); ?> همکار پیدا شد</span>
            <?php if ($search !== ''): ?><span>عبارت جستجو: «<?php echo employee_h($search); ?>»</span><?php endif; ?>
        </div>
    </section>

    <?php if ($errorMessage !== ''): ?>
        <div class="employee-directory-empty employee-directory-error">
            <strong><?php echo employee_h($errorMessage); ?></strong>
        </div>
    <?php elseif (count($employees) > 0): ?>
        <section class="employee-directory-grid" aria-label="فهرست کارکنان">
            <?php foreach ($employees as $employee): ?>
                <?php
                    $avatarUrl = neal_avatar_url($employee['avatar']);
                    $phone = trim((string)($employee['phone'] ?? ''));
                    $phoneHref = preg_replace('/[^0-9+]/', '', $phone);
                ?>
                <article class="employee-directory-card">
                    <div class="employee-directory-card-top">
                        <div class="employee-directory-avatar">
                            <img src="<?php echo employee_h($avatarUrl); ?>" alt="<?php echo employee_h($employee['fullname']); ?>" loading="lazy">
                        </div>
                        <span class="employee-directory-active"><span></span>فعال</span>
                    </div>
                    <div class="employee-directory-identity">
                        <h2><?php echo employee_h($employee['fullname']); ?></h2>
                        <span class="employee-directory-username">@<?php echo employee_h($employee['username']); ?></span>
                        <?php if (trim((string)$employee['user_status']) !== ''): ?>
                            <p class="employee-directory-user-status"><?php echo employee_h($employee['user_status']); ?></p>
                        <?php endif; ?>
                    </div>
                    <div class="employee-directory-details">
                        <div class="employee-directory-detail">
                            <span class="employee-directory-detail-icon department-icon" aria-hidden="true">
                                <svg viewBox="0 0 24 24"><rect x="3" y="7" width="18" height="14" rx="2"/><path d="M8 7V4h8v3M8 12h.01M12 12h.01M16 12h.01M8 16h.01M12 16h.01M16 16h.01"/></svg>
                            </span>
                            <span><small>واحد سازمانی</small><strong><?php echo employee_h($employee['department_name']); ?></strong></span>
                        </div>
                        <div class="employee-directory-detail">
                            <span class="employee-directory-detail-icon code-icon" aria-hidden="true">
                                <svg viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="M7 9h4M7 13h2M15 9h2M14 13h3"/></svg>
                            </span>
                            <span><small>کد پرسنلی</small><strong><?php echo employee_h($employee['personnel_code'] ?: 'ثبت نشده'); ?></strong></span>
                        </div>
                        <div class="employee-directory-detail">
                            <span class="employee-directory-detail-icon phone-icon" aria-hidden="true">
                                <svg viewBox="0 0 24 24"><path d="M22 16.92v3a2 2 0 0 1-2.18 2A19.8 19.8 0 0 1 11.2 18a19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.09 3.18 2 2 0 0 1 4.08 1h3a2 2 0 0 1 2 1.72c.12.9.34 1.78.65 2.62a2 2 0 0 1-.45 2.11L8 8.73a16 16 0 0 0 6 6l1.28-1.28a2 2 0 0 1 2.11-.45c.84.31 1.72.53 2.62.65A2 2 0 0 1 22 16.92Z"/></svg>
                            </span>
                            <span><small>شماره تماس</small>
                                <?php if ($phone !== '' && $phoneHref !== ''): ?>
                                    <a href="tel:<?php echo employee_h($phoneHref); ?>"><?php echo employee_h($phone); ?></a>
                                <?php else: ?>
                                    <strong>ثبت نشده</strong>
                                <?php endif; ?>
                            </span>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
        </section>
    <?php else: ?>
        <section class="employee-directory-empty">
            <span class="employee-directory-empty-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/></svg>
            </span>
            <h2>همکاری پیدا نشد</h2>
            <p>عبارت جستجو یا واحد سازمانی را تغییر دهید.</p>
            <a href="employee-directory.php">نمایش همه کارکنان</a>
        </section>
    <?php endif; ?>
</main>

<?php include "includes/footer.php"; ?>
