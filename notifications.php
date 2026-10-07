<?php

$portal_mode = true;

require_once "auth/auth.php";
require_once "includes/notifications.php";

check_user_login();

$page_title = "مرکز اعلان‌ها";

$username = $_SESSION['user_username'] ?? '';

$db = neal_notifications_db();
neal_sync_notifications($db, $username);

if (empty($_SESSION['neal_notification_csrf'])) {
    $_SESSION['neal_notification_csrf'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['neal_notification_csrf'];

function notification_h($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function notification_jalali_date($datetime)
{
    if (!$datetime) {
        return '';
    }

    $timestamp = strtotime($datetime);

    if (!$timestamp) {
        return '';
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
        + floor(($gy2 + 3) / 4)
        - floor(($gy2 + 99) / 100)
        + floor(($gy2 + 399) / 400)
        - 80
        + $gd
        + $gdm[$gm - 1];

    $jy += 33 * floor($days / 12053);
    $days %= 12053;

    $jy += 4 * floor($days / 1461);
    $days %= 1461;

    if ($days > 365) {
        $jy += floor(($days - 1) / 365);
        $days = ($days - 1) % 365;
    }

    if ($days < 186) {
        $jm = 1 + floor($days / 31);
        $jd = 1 + ($days % 31);
    } else {
        $jm = 7 + floor(($days - 186) / 30);
        $jd = 1 + (($days - 186) % 30);
    }

    return sprintf(
        '%04d/%02d/%02d - %s',
        $jy,
        $jm,
        $jd,
        date('H:i', $timestamp)
    );
}

function notification_icon($type)
{
    switch ($type) {
        case 'announcement':
            return '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 10v4a2 2 0 0 0 2 2h1l1.5 4h2L9.2 16H12l5 3V5l-5 3H6a2 2 0 0 0-2 2Zm14-2.5v9a2.5 2.5 0 0 0 0-9Z"/></svg>';

        case 'message':
            return '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 5h14a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H9l-4 3v-3H5a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2Zm3 4h8v2H8V9Zm0 4h5v2H8v-2Z"/></svg>';

        case 'ticket':
            return '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 6a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v3a2 2 0 0 0 0 6v3a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2v-3a2 2 0 0 0 0-6V6Zm5 2h6v2H9V8Zm0 4h6v2H9v-2Z"/></svg>';

        case 'occasion':
            return '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 3h12v3h3v15H3V6h3V3Zm2 2v3h8V5H8Zm-3 6v8h14v-8H5Zm3 2h8v2H8v-2Z"/></svg>';

        default:
            return '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3a7 7 0 0 0-7 7v4l-2 3h18l-2-3v-4a7 7 0 0 0-7-7Zm0 18a3 3 0 0 0 2.8-2H9.2A3 3 0 0 0 12 21Z"/></svg>';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($csrf, $_POST['csrf'] ?? '')) {
        http_response_code(403);
        exit('درخواست نامعتبر است.');
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'read_all') {
        $stmt = $db->prepare("
            UPDATE notifications
            SET is_read = 1,
                read_at = CURRENT_TIMESTAMP
            WHERE username = ?
              AND is_read = 0
        ");
        $stmt->execute([$username]);

        header("Location: notifications.php");
        exit;
    }

    if ($action === 'read_one') {
        $id = (int)($_POST['id'] ?? 0);

        if ($id > 0) {
            $stmt = $db->prepare("
                UPDATE notifications
                SET is_read = 1,
                    read_at = CURRENT_TIMESTAMP
                WHERE id = ?
                  AND username = ?
            ");
            $stmt->execute([$id, $username]);
        }

        $link = trim($_POST['link'] ?? '');

        if ($link !== '' && preg_match('/^[a-zA-Z0-9._?=&\-\/]+$/', $link)) {
            header("Location: " . $link);
        } else {
            header("Location: notifications.php");
        }
        exit;
    }
}

$filter = $_GET['filter'] ?? 'all';

$where = "WHERE username = ?";
$params = [$username];

if ($filter === 'unread') {
    $where .= " AND is_read = 0";
} elseif ($filter === 'read') {
    $where .= " AND is_read = 1";
}

$stmt = $db->prepare("
    SELECT id, type, title, body, link, is_read, created_at
    FROM notifications
    $where
    ORDER BY is_read ASC, created_at DESC, id DESC
    LIMIT 100
");
$stmt->execute($params);
$notifications = $stmt->fetchAll();

$unread = neal_notification_unread_count($db, $username);

$stmt = $db->prepare("SELECT COUNT(*) FROM notifications WHERE username = ?");
$stmt->execute([$username]);
$total = (int)$stmt->fetchColumn();

$stmt = $db->prepare("SELECT COUNT(*) FROM notifications WHERE username = ? AND is_read = 1");
$stmt->execute([$username]);
$read_count = (int)$stmt->fetchColumn();

require_once "includes/header.php";
?>

<style>
.notification-page{direction:rtl;max-width:1120px;margin:0 auto;padding:26px 20px 40px;font-family:Vazirmatn,Tahoma,sans-serif}
.notification-hero{display:flex;align-items:center;justify-content:space-between;gap:18px;margin-bottom:20px}
.notification-hero-main{display:flex;align-items:center;gap:14px}
.notification-hero-icon{width:52px;height:52px;border-radius:16px;background:#eef4ff;display:flex;align-items:center;justify-content:center}
.notification-hero-icon svg{width:27px;height:27px;fill:#3159a6}
.notification-hero h1{margin:0;color:#101828;font-size:22px}
.notification-hero p{margin:5px 0 0;color:#667085;font-size:11px}
.notification-read-all{border:0;background:#f2f4f7;color:#344054;padding:10px 14px;border-radius:10px;font:inherit;font-size:11px;cursor:pointer}
.notification-read-all:hover{background:#e4e7ec}
.notification-stats{display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin-bottom:18px}
.notification-stat{background:#fff;border:1px solid #eaecf0;border-radius:14px;padding:14px 16px}
.notification-stat span{display:block;color:#667085;font-size:10px}
.notification-stat strong{display:block;color:#101828;font-size:20px;margin-top:4px}
.notification-filters{display:flex;gap:7px;margin-bottom:14px}
.notification-filter{padding:8px 13px;border-radius:9px;background:#f2f4f7;color:#667085;text-decoration:none;font-size:11px}
.notification-filter:hover{background:#e4e7ec}
.notification-filter.active{background:#102a43;color:#fff}
.notification-list{display:flex;flex-direction:column;gap:10px}
.notification-item{display:flex;align-items:flex-start;gap:14px;background:#fff;border:1px solid #eaecf0;border-radius:15px;padding:15px;transition:.16s ease}
.notification-item:hover{border-color:#d0d5dd;box-shadow:0 7px 20px rgba(16,24,40,.06)}
.notification-item.unread{border-right:3px solid #3159a6;background:#fbfdff}
.notification-icon{width:42px;height:42px;flex:0 0 42px;border-radius:12px;background:#f2f4f7;display:flex;align-items:center;justify-content:center}
.notification-icon svg{width:21px;height:21px;fill:#475467}
.notification-item[data-type="announcement"] .notification-icon{background:#eef4ff}
.notification-item[data-type="announcement"] .notification-icon svg{fill:#3159a6}
.notification-item[data-type="message"] .notification-icon{background:#ecfdf3}
.notification-item[data-type="message"] .notification-icon svg{fill:#027a48}
.notification-item[data-type="ticket"] .notification-icon{background:#fff4cc}
.notification-item[data-type="ticket"] .notification-icon svg{fill:#9a6700}
.notification-item[data-type="occasion"] .notification-icon{background:#fdf2fa}
.notification-item[data-type="occasion"] .notification-icon svg{fill:#c11574}
.notification-content{min-width:0;flex:1}
.notification-title{display:flex;align-items:center;gap:8px;flex-wrap:wrap}
.notification-title strong{font-size:13px;color:#101828}
.notification-new{padding:3px 7px;border-radius:999px;background:#eef4ff;color:#3159a6;font-size:8px}
.notification-body{margin-top:6px;color:#667085;font-size:11px;line-height:1.9}
.notification-meta{margin-top:7px;color:#98a2b3;font-size:9px}
.notification-actions{display:flex;align-items:center;gap:6px}
.notification-open{border:0;background:#f2f4f7;color:#344054;border-radius:8px;padding:7px 10px;font:inherit;font-size:10px;cursor:pointer}
.notification-open:hover{background:#e4e7ec}
.notification-empty{background:#fff;border:1px solid #eaecf0;border-radius:16px;padding:70px 20px;text-align:center;color:#98a2b3}
.notification-empty svg{width:48px;height:48px;fill:#d0d5dd;margin-bottom:10px}
.notification-empty strong{display:block;color:#667085;font-size:13px}
.notification-empty span{display:block;margin-top:5px;font-size:10px}
@media(max-width:700px){
 .notification-hero{align-items:flex-start;flex-direction:column}
 .notification-stats{grid-template-columns:1fr}
 .notification-item{padding:12px}
 .notification-actions{display:none}
}
</style>

<div class="notification-page">

    <div class="notification-hero">
        <div class="notification-hero-main">
            <div class="notification-hero-icon">
                <svg viewBox="0 0 24 24"><path d="M12 3a7 7 0 0 0-7 7v4l-2 3h18l-2-3v-4a7 7 0 0 0-7-7Zm0 18a3 3 0 0 0 2.8-2H9.2A3 3 0 0 0 12 21Z"/></svg>
            </div>
            <div>
                <h1>مرکز اعلان‌ها</h1>
                <p>همه پیام‌ها و رویدادهای مهم حساب کاربری شما در یکجا</p>
            </div>
        </div>

        <?php if ($unread > 0): ?>
            <form method="post">
                <input type="hidden" name="csrf" value="<?php echo notification_h($csrf); ?>">
                <input type="hidden" name="action" value="read_all">
                <button class="notification-read-all" type="submit">✓ همه را خوانده‌شده کن</button>
            </form>
        <?php endif; ?>
    </div>

    <div class="notification-stats">
        <div class="notification-stat">
            <span>همه اعلان‌ها</span>
            <strong><?php echo $total; ?></strong>
        </div>
        <div class="notification-stat">
            <span>خوانده‌نشده</span>
            <strong><?php echo $unread; ?></strong>
        </div>
        <div class="notification-stat">
            <span>خوانده‌شده</span>
            <strong><?php echo $read_count; ?></strong>
        </div>
    </div>

    <div class="notification-filters">
        <a class="notification-filter <?php echo $filter === 'all' ? 'active' : ''; ?>" href="notifications.php">همه</a>
        <a class="notification-filter <?php echo $filter === 'unread' ? 'active' : ''; ?>" href="notifications.php?filter=unread">خوانده‌نشده</a>
        <a class="notification-filter <?php echo $filter === 'read' ? 'active' : ''; ?>" href="notifications.php?filter=read">خوانده‌شده</a>
    </div>

    <?php if ($notifications): ?>

        <div class="notification-list">
            <?php foreach ($notifications as $item): ?>
                <div class="notification-item <?php echo (int)$item['is_read'] === 0 ? 'unread' : ''; ?>" data-type="<?php echo notification_h($item['type']); ?>">

                    <div class="notification-icon">
                        <?php echo notification_icon($item['type']); ?>
                    </div>

                    <div class="notification-content">
                        <div class="notification-title">
                            <strong><?php echo notification_h($item['title']); ?></strong>
                            <?php if ((int)$item['is_read'] === 0): ?>
                                <span class="notification-new">جدید</span>
                            <?php endif; ?>
                        </div>

                        <div class="notification-body">
                            <?php echo nl2br(notification_h($item['body'])); ?>
                        </div>

                        <div class="notification-meta">
                            <?php echo notification_h(notification_jalali_date($item['created_at'])); ?>
                        </div>
                    </div>

                    <?php if (trim($item['link']) !== '' || (int)$item['is_read'] === 0): ?>
                        <div class="notification-actions">
                            <form method="post">
                                <input type="hidden" name="csrf" value="<?php echo notification_h($csrf); ?>">
                                <input type="hidden" name="action" value="read_one">
                                <input type="hidden" name="id" value="<?php echo (int)$item['id']; ?>">
                                <input type="hidden" name="link" value="<?php echo notification_h($item['link']); ?>">
                                <button class="notification-open" type="submit">
                                    <?php echo (int)$item['is_read'] === 0 ? 'مشاهده' : 'باز کردن'; ?>
                                </button>
                            </form>
                        </div>
                    <?php endif; ?>

                </div>
            <?php endforeach; ?>
        </div>

    <?php else: ?>

        <div class="notification-empty">
            <svg viewBox="0 0 24 24"><path d="M12 3a7 7 0 0 0-7 7v4l-2 3h18l-2-3v-4a7 7 0 0 0-7-7Zm0 18a3 3 0 0 0 2.8-2H9.2A3 3 0 0 0 12 21Z"/></svg>
            <strong>اعلانی برای نمایش وجود ندارد</strong>
            <span>وقتی اتفاق مهمی برای حساب شما رخ دهد، اینجا نمایش داده می‌شود.</span>
        </div>

    <?php endif; ?>

</div>

<?php include "includes/footer.php"; ?>
