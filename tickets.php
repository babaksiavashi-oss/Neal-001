<?php

require_once __DIR__ . '/auth/auth.php';
require_once __DIR__ . '/config/config.php';

check_login();

$db = new PDO('sqlite:' . __DIR__ . '/data/neal.db');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$currentAdminId = (int)$_SESSION['admin_id'];


/* =========================================================
   Labels
   ========================================================= */

$statusLabels = array(
    'new'          => 'جدید',
    'assigned'     => 'تخصیص داده شده',
    'in_progress'  => 'در حال بررسی',
    'waiting_user' => 'منتظر پاسخ کاربر',
    'resolved'     => 'حل شده',
    'closed'       => 'بسته شده',
    'cancelled'    => 'لغو شده'
);

$priorityLabels = array(
    'normal'    => 'عادی',
    'important' => 'مهم',
    'urgent'    => 'فوری',
    'critical'  => 'بحرانی'
);

$departmentLabels = array(
    'management'     => 'واحد مدیریت',
    'regulatory'     => 'واحد مسئول فنی و رگولاتوری',
    'administrative' => 'واحد اداری',
    'engineering'    => 'واحد فنی مهندسی',
    'finance'        => 'واحد مالی',
    'planning'       => 'واحد برنامه ریزی و انبار',
    'quality'        => 'واحد آزمایشگاه کنترل کیفیت',
    'commercial'     => 'واحد بازرگانی',
    'production'     => 'واحد تولید',
    'hse'            => 'واحد ایمنی و بهداشت'
);


/* =========================================================
   Helpers
   ========================================================= */

function jalaliDate($datetime)
{
    if (empty($datetime)) {
        return '-';
    }

    $timestamp = strtotime($datetime);

    if ($timestamp === false) {
        return $datetime;
    }

    $gy = (int)date('Y', $timestamp);
    $gm = (int)date('n', $timestamp);
    $gd = (int)date('j', $timestamp);

    $g_d_m = array(
        0,
        31,
        59,
        90,
        120,
        151,
        181,
        212,
        243,
        273,
        304,
        334
    );

    if ($gy > 1600) {
        $jy = 979;
        $gy -= 1600;
    } else {
        $jy = 0;
        $gy -= 621;
    }

    $gy2 = ($gm > 2) ? ($gy + 1) : $gy;

    $days = (365 * $gy)
        + (int)(($gy2 + 3) / 4)
        - (int)(($gy2 + 99) / 100)
        + (int)(($gy2 + 399) / 400)
        - 80
        + $gd
        + $g_d_m[$gm - 1];

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

    return sprintf(
        '%04d/%02d/%02d - %s',
        $jy,
        $jm,
        $jd,
        date('H:i', $timestamp)
    );
}


function persianNumber($number)
{
    return strtr(
        (string)$number,
        array(
            '0' => '۰',
            '1' => '۱',
            '2' => '۲',
            '3' => '۳',
            '4' => '۴',
            '5' => '۵',
            '6' => '۶',
            '7' => '۷',
            '8' => '۸',
            '9' => '۹'
        )
    );
}


/* =========================================================
   Current Queue
   ========================================================= */

$queue = isset($_GET['queue'])
    ? trim($_GET['queue'])
    : 'all';

$allowedQueues = array(
    'new',
    'active',
    'waiting',
    'attention',
    'closed',
    'all'
);

if (!in_array($queue, $allowedQueues, true)) {
    $queue = 'all';
}


/* =========================================================
   Filters
   ========================================================= */

$search = isset($_GET['search'])
    ? trim($_GET['search'])
    : '';

$status = isset($_GET['status'])
    ? trim($_GET['status'])
    : '';

$priority = isset($_GET['priority'])
    ? trim($_GET['priority'])
    : '';

$department = isset($_GET['department'])
    ? trim($_GET['department'])
    : '';


/* =========================================================
   Pagination
   ========================================================= */

$tickets_per_page = 10;
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($current_page < 1) {
    $current_page = 1;
}


/* =========================================================
   Queue Statistics
   ========================================================= */

$queueStats = array(
    'new'       => 0,
    'active'    => 0,
    'waiting'   => 0,
    'attention' => 0,
    'closed'    => 0,
    'all'       => 0
);


/*
 * کل درخواست‌ها
 */

$stmt = $db->query("
    SELECT COUNT(*) AS total
    FROM service_requests
    WHERE status NOT IN ('closed', 'resolved', 'cancelled')
");

$queueStats['all'] = (int)$stmt->fetchColumn();


/*
 * درخواست‌های جدید
 */

$stmt = $db->query("
    SELECT COUNT(*) AS total
    FROM service_requests
    WHERE status = 'new'
");

$queueStats['new'] = (int)$stmt->fetchColumn();


/*
 * درخواست‌های در حال اجرا
 */

$stmt = $db->query("
    SELECT COUNT(*) AS total
    FROM service_requests
    WHERE status IN ('assigned', 'in_progress')
");

$queueStats['active'] = (int)$stmt->fetchColumn();


/*
/*
 * منتظر پاسخ کاربر
 */

$queueStats['waiting'] = (int)$db->query("
    SELECT COUNT(*)
    FROM service_requests sr
    WHERE sr.status NOT IN ('closed', 'resolved', 'cancelled')
      AND EXISTS (
          SELECT 1
          FROM ticket_messages admin_msg
          WHERE admin_msg.request_id = sr.id
            AND admin_msg.is_internal = 0
            AND admin_msg.sender_admin_id IS NOT NULL
            AND NOT EXISTS (
                SELECT 1
                FROM ticket_messages later_msg
                WHERE later_msg.request_id = sr.id
                  AND later_msg.is_internal = 0
                  AND later_msg.sender_admin_id IS NULL
                  AND (
                      later_msg.created_at > admin_msg.created_at
                      OR (
                          later_msg.created_at = admin_msg.created_at
                          AND later_msg.id > admin_msg.id
                      )
                  )
            )
      )
")->fetchColumn();

/*
 * نیازمند توجه من
 *
 * منظور:
 * حداقل یک پیام قابل مشاهده برای این کارشناس
 * هنوز خوانده نشده باشد.
 */

$stmt = $db->prepare("
    SELECT COUNT(DISTINCT tm.request_id)
    FROM ticket_messages tm
    INNER JOIN service_requests sr
        ON sr.id = tm.request_id
    WHERE tm.is_internal = 0
      AND sr.status NOT IN ('closed', 'cancelled')
      AND sr.assigned_to = ?
      AND NOT EXISTS (
          SELECT 1
          FROM ticket_message_reads tmr
          WHERE tmr.message_id = tm.id
            AND tmr.admin_id = ?
      )
");

$stmt->execute(array(
    $currentAdminId,
    $currentAdminId
));

$queueStats['attention'] = (int)$stmt->fetchColumn();


/*
 * بسته شده
 */

$stmt = $db->query("
    SELECT COUNT(*) AS total
    FROM service_requests
    WHERE status IN ('closed', 'resolved', 'cancelled')
");

$queueStats['closed'] = (int)$stmt->fetchColumn();


/* =========================================================
   Main Ticket Query
   ========================================================= */

$sql = "
    SELECT
        sr.id,
        sr.tracking_number,
        sr.requester_username,
        sr.fullname,
        sr.department,
        sr.priority,
        sr.subject,
        sr.status,
        sr.created_at,
        sr.updated_at,
        sr.assigned_to,

        GROUP_CONCAT(
            DISTINCT rt.name
        ) AS request_types,

        a.username AS assigned_username,

        (
            SELECT MAX(tm.created_at)
            FROM ticket_messages tm
            WHERE tm.request_id = sr.id
              AND tm.is_internal = 0
        ) AS last_message_at,

        (
            SELECT COUNT(*)
            FROM ticket_messages tm2
            WHERE tm2.request_id = sr.id
              AND tm2.is_internal = 0
              AND NOT EXISTS (
                  SELECT 1
                  FROM ticket_message_reads tmr2
                  WHERE tmr2.message_id = tm2.id
                    AND tmr2.admin_id = ?
              )
        ) AS unread_count

    FROM service_requests sr

    LEFT JOIN service_request_types srt
        ON srt.request_id = sr.id

    LEFT JOIN request_types rt
        ON rt.id = srt.request_type_id

    LEFT JOIN admins a
        ON a.id = sr.assigned_to

    WHERE 1 = 1
";

$params = array(
    $currentAdminId
);


/* =========================================================
   Queue Filter
   ========================================================= */

switch ($queue) {

    case 'new':

        $sql .= "
            AND sr.status = 'new'
        ";

        break;


    case 'active':

        $sql .= "
            AND sr.status IN ('assigned', 'in_progress')
        ";

        break;


    case 'waiting':

        $sql .= "
            AND sr.status = 'waiting_user'
        ";

        break;


    case 'attention':

        $sql .= "
            AND sr.status NOT IN ('closed', 'cancelled')
            AND sr.assigned_to = ?

            AND EXISTS (
                SELECT 1
                FROM ticket_messages tm_attention
                WHERE tm_attention.request_id = sr.id
                  AND tm_attention.is_internal = 0
                  AND NOT EXISTS (
                      SELECT 1
                      FROM ticket_message_reads tmr_attention
                      WHERE tmr_attention.message_id = tm_attention.id
                        AND tmr_attention.admin_id = ?
                  )
            )
        ";

        $params[] = $currentAdminId;
        $params[] = $currentAdminId;

        break;


    case 'closed':

        $sql .= "
            AND sr.status IN ('closed', 'resolved', 'cancelled')
        ";

        break;


    case 'all':

    default:

        /*
         * In the normal/all view, keep closed tickets out of the list.
         * When searching or explicitly filtering by status, allow the
         * search/filter to return closed tickets as well.
         */
        if ($search === '' && $status === '') {
            $sql .= "
                AND sr.status NOT IN ('closed', 'resolved', 'cancelled')
            ";
        }

        break;
}


/* =========================================================
   Search Filter
   ========================================================= */

if ($search !== '') {

    $sql .= "
        AND (
            sr.tracking_number LIKE ?
            OR sr.subject LIKE ?
            OR sr.fullname LIKE ?
            OR sr.requester_username LIKE ?
        )
    ";

    $searchValue = '%' . $search . '%';

    $params[] = $searchValue;
    $params[] = $searchValue;
    $params[] = $searchValue;
    $params[] = $searchValue;
}


/* =========================================================
   Status Filter
   ========================================================= */

if ($status !== '' && isset($statusLabels[$status])) {

    $sql .= "
        AND sr.status = ?
    ";

    $params[] = $status;
}


/* =========================================================
   Priority Filter
   ========================================================= */

if ($priority !== '' && isset($priorityLabels[$priority])) {

    $sql .= "
        AND sr.priority = ?
    ";

    $params[] = $priority;
}


/* =========================================================
   Department Filter
   ========================================================= */

if ($department !== '' && isset($departmentLabels[$department])) {

    $sql .= "
        AND sr.department = ?
    ";

    $params[] = $department;
}


/* =========================================================
   Count + Pagination + Group / Sort
   ========================================================= */

/*
 * Count the same filtered result set before applying LIMIT/OFFSET.
 * The unread_count placeholder remains in the subquery, so the same
 * parameter list can safely be used for both queries.
 */
$countSql = "SELECT COUNT(*) FROM (" . $sql . " GROUP BY sr.id) AS ticket_rows";

$countStmt = $db->prepare($countSql);
$countStmt->execute($params);
$total_tickets = (int)$countStmt->fetchColumn();

$total_pages = max(1, (int)ceil($total_tickets / $tickets_per_page));

if ($current_page > $total_pages) {
    $current_page = $total_pages;
}

$offset = ($current_page - 1) * $tickets_per_page;

$sql .= "
    GROUP BY sr.id

    ORDER BY

        CASE sr.priority
            WHEN 'critical' THEN 1
            WHEN 'urgent' THEN 2
            WHEN 'important' THEN 3
            WHEN 'normal' THEN 4
            ELSE 5
        END ASC,

        CASE
            WHEN unread_count > 0 THEN 0
            ELSE 1
        END ASC,

        COALESCE(
            last_message_at,
            sr.updated_at,
            sr.created_at
        ) DESC,

        sr.id DESC

    LIMIT ? OFFSET ?
";

$mainParams = $params;
$mainParams[] = $tickets_per_page;
$mainParams[] = $offset;

$stmt = $db->prepare($sql);
$stmt->execute($mainParams);

$tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pagination_start = $total_tickets > 0 ? $offset + 1 : 0;
$pagination_end = min($offset + $tickets_per_page, $total_tickets);

$paginationParams = $_GET;
unset($paginationParams['page']);

$buildPageUrl = function ($page) use ($paginationParams) {
    $params = $paginationParams;
    $params['page'] = $page;
    return 'tickets.php?' . http_build_query($params);
};

/* =========================================================
   Page Title
   ========================================================= */

$page_title = 'مدیریت درخواست‌های پشتیبانی';

?>


<?php require_once __DIR__ . '/includes/header.php'; ?>


<style>

/* =========================================================
   Main Page
   ========================================================= */

.ticket-page {
    width: 100%;
}

.ticket-page-title {
    margin-bottom: 22px;
}

.ticket-page-title h1 {
    margin: 0 0 7px 0;
    font-size: 25px;
}

.ticket-page-title p {
    margin: 0;
    color: #666;
    font-size: 14px;
}


/* =========================================================
   Queue Navigation
   ========================================================= */

.ticket-queues {
    display: grid;
    grid-template-columns: repeat(6, 1fr);
    gap: 10px;
    margin-bottom: 20px;
}

.ticket-queue {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;

    min-height: 62px;

    padding: 10px 13px;

    background: #ffffff;

    border: 1px solid #dddddd;
    border-radius: 8px;

    color: #333;
    text-decoration: none;

    transition: .18s;
}

.ticket-queue:hover {
    border-color: #9fb4c8;
    background: #fafcfe;
}

.ticket-queue.active {
    border-color: #1f5f8b;
    background: #eef6fc;
    box-shadow: 0 2px 7px rgba(31,95,139,.10);
}

.ticket-queue-title {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.ticket-queue-title strong {
    font-size: 13px;
    color: #263238;
}

.ticket-queue-title span {
    font-size: 11px;
    color: #7a8691;
}

.ticket-queue-count {
    min-width: 30px;
    height: 30px;

    display: inline-flex;
    align-items: center;
    justify-content: center;

    border-radius: 50%;

    background: #f1f3f5;
    color: #34495e;

    font-size: 12px;
    font-weight: 700;
}

.ticket-queue.active .ticket-queue-count {
    background: #1f5f8b;
    color: #fff;
}

.ticket-queue.attention .ticket-queue-count {
    background: #ffe7e7;
    color: #a00000;
}


/* =========================================================
   Filters
   ========================================================= */

.ticket-filters {
    background: #ffffff;
    border: 1px solid #dddddd;
    border-radius: 8px;
    padding: 16px;
    margin-bottom: 20px;
}

.ticket-filters form {
    display: grid;
    grid-template-columns: 2fr 1fr 1fr 1.5fr auto auto;
    gap: 10px;
    align-items: end;
}

.ticket-filter-group {
    display: flex;
    flex-direction: column;
    gap: 5px;
}

.ticket-filter-group label {
    font-size: 13px;
    color: #555;
}

.ticket-filter-group input,
.ticket-filter-group select {
    width: 100%;
    box-sizing: border-box;

    min-height: 38px;

    border: 1px solid #cccccc;
    border-radius: 5px;

    padding: 7px 10px;

    font-family: inherit;
    font-size: 13px;

    background: #fff;
}

.ticket-filter-button {
    min-height: 38px;

    border: none;
    border-radius: 5px;

    padding: 0 18px;

    cursor: pointer;

    font-family: inherit;
    font-size: 13px;

    background: #1f5f8b;
    color: #fff;
}

.ticket-filter-button:hover {
    background: #174a6d;
}

.ticket-filter-reset {
    display: inline-flex;

    align-items: center;
    justify-content: center;

    min-height: 38px;

    padding: 0 16px;

    border-radius: 5px;
    border: 1px solid #cccccc;

    background: #f5f5f5;
    color: #444;

    text-decoration: none;

    font-size: 13px;
}


/* =========================================================
   Current Queue Header
   ========================================================= */

.ticket-current-queue {
    background: #ffffff;
    border: 1px solid #dddddd;
    border-radius: 8px;

    padding: 14px 17px;

    margin-bottom: 12px;

    display: flex;
    align-items: center;
    justify-content: space-between;

    gap: 15px;
}

.ticket-current-queue-title {
    display: flex;
    align-items: center;
    gap: 9px;
}

.ticket-current-queue-title strong {
    font-size: 15px;
    color: #263238;
}

.ticket-current-queue-title span {
    font-size: 12px;
    color: #7a8691;
}


/* =========================================================
   Table
   ========================================================= */

.ticket-table-box {
    background: #ffffff;

    border: 1px solid #dddddd;
    border-radius: 8px;

    overflow-x: auto;
}

.ticket-table {
    width: 100%;

    border-collapse: collapse;

    min-width: 1250px;
}

.ticket-table th {
    background: #f4f6f8;

    color: #444;

    font-size: 12px;
    font-weight: 700;

    padding: 12px 10px;

    border-bottom: 1px solid #dddddd;

    text-align: right;

    white-space: nowrap;
}

.ticket-table td {
    padding: 11px 10px;

    border-bottom: 1px solid #eeeeee;

    font-size: 12px;

    vertical-align: middle;
}

.ticket-table tr:last-child td {
    border-bottom: none;
}

.ticket-table tbody tr {
    transition: .15s;
}

.ticket-table tbody tr:hover td {
    background: #fafafa;
}


/* =========================================================
   Unread Row
   ========================================================= */

.ticket-table tbody tr.ticket-unread td {
    background: #fffdf2;
}

.ticket-table tbody tr.ticket-unread:hover td {
    background: #fff9df;
}

.ticket-unread-indicator {
    display: inline-flex;

    align-items: center;
    gap: 5px;

    margin-top: 5px;

    color: #a16207;

    font-size: 10px;
    font-weight: 700;
}

.ticket-unread-dot {
    width: 7px;
    height: 7px;

    border-radius: 50%;

    background: #e09b00;
}


/* =========================================================
   Ticket Number
   ========================================================= */

.ticket-number {
    font-weight: 700;
    white-space: nowrap;
    color: #174a6d;
}


/* =========================================================
   Requester
   ========================================================= */

.ticket-requester {
    white-space: nowrap;
    line-height: 1.8;
}

.ticket-requester strong {
    display: block;
    color: #333;
}

.ticket-requester small {
    color: #8a8a8a;
    font-size: 10px;
}


/* =========================================================
   Subject
   ========================================================= */

.ticket-subject {
    min-width: 180px;

    max-width: 290px;

    font-weight: 600;

    color: #333;

    line-height: 1.7;
}


/* =========================================================
   Assignment
   ========================================================= */

.ticket-assignee {
    white-space: nowrap;
}

.ticket-assignee.assigned {
    color: #334155;
    font-weight: 600;
}

.ticket-assignee.unassigned {
    color: #a00000;
    font-size: 11px;
}


/* =========================================================
   Last Activity
   ========================================================= */

.ticket-last-activity {
    white-space: nowrap;
    color: #56616b;
    font-size: 11px;
    line-height: 1.8;
}

.ticket-last-activity-label {
    color: #929ba3;
    font-size: 10px;
}


/* =========================================================
   Badges
   ========================================================= */

.ticket-badge {
    display: inline-block;

    padding: 4px 9px;

    border-radius: 12px;

    font-size: 10px;

    white-space: nowrap;
}


/* Status */

.status-new {
    background: #e8f1ff;
    color: #1859a5;
}

.status-assigned {
    background: #eee8ff;
    color: #5d35a5;
}

.status-in_progress {
    background: #fff4d8;
    color: #8a6100;
}

.status-waiting_user {
    background: #e8f5ff;
    color: #0369a1;
}

.status-resolved {
    background: #e4f7ea;
    color: #217a3a;
}

.status-closed {
    background: #eeeeee;
    color: #555;
}

.status-cancelled {
    background: #ffe7e7;
    color: #a00000;
}


/* Priority */

.priority-normal {
    background: #eeeeee;
    color: #555;
}

.priority-important {
    background: #fff4d8;
    color: #8a6100;
}

.priority-urgent {
    background: #ffe7d6;
    color: #a63f00;
}

.priority-critical {
    background: #ffdede;
    color: #a00000;
    font-weight: 700;
}


/* =========================================================
   Date
   ========================================================= */

.ticket-date {
    white-space: nowrap;
    direction: rtl;
    color: #56616b;
    font-size: 11px;
}


/* =========================================================
   View Button
   ========================================================= */

.ticket-view-button {
    display: inline-flex;

    align-items: center;
    justify-content: center;

    padding: 7px 12px;

    border-radius: 5px;

    background: #1f5f8b;
    color: #fff;

    text-decoration: none;

    font-size: 11px;

    white-space: nowrap;

    transition: .18s;
}

.ticket-view-button:hover {
    background: #174a6d;
}


/* =========================================================
   Empty
   ========================================================= */

.ticket-empty {
    padding: 55px 20px;

    text-align: center;

    color: #777;

    font-size: 14px;
}

.ticket-empty-icon {
    font-size: 30px;
    margin-bottom: 10px;
}

.ticket-empty-title {
    color: #444;
    font-weight: 700;
    margin-bottom: 6px;
}

.ticket-empty-text {
    color: #888;
    font-size: 12px;
}


/* =========================================================
   Pagination
   ========================================================= */

.ticket-pagination-area {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 15px;
    margin-top: 14px;
    padding: 12px 14px;
    background: #ffffff;
    border: 1px solid #dddddd;
    border-radius: 8px;
}

.ticket-pagination-info {
    color: #6b7280;
    font-size: 11px;
    white-space: nowrap;
}

.ticket-pagination {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 5px;
    flex-wrap: wrap;
}

.ticket-pagination a,
.ticket-pagination span {
    min-width: 31px;
    height: 31px;
    box-sizing: border-box;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 0 8px;
    border-radius: 5px;
    border: 1px solid #d5d9dd;
    background: #fff;
    color: #44515c;
    text-decoration: none;
    font-size: 11px;
    transition: .15s;
}

.ticket-pagination a:hover {
    border-color: #9fb4c8;
    background: #f5f8fa;
}

.ticket-pagination .ticket-pagination-current {
    border-color: #1f5f8b;
    background: #1f5f8b;
    color: #fff;
    font-weight: 700;
}

.ticket-pagination .ticket-pagination-disabled {
    color: #b5b9bd;
    background: #f7f7f7;
    cursor: default;
}

.ticket-pagination .ticket-pagination-dots {
    min-width: auto;
    padding: 0 4px;
    border-color: transparent;
    background: transparent;
}

/* =========================================================
   Responsive
   ========================================================= */

@media (max-width: 1250px) {

    .ticket-queues {
        grid-template-columns: repeat(3, 1fr);
    }

    .ticket-filters form {
        grid-template-columns: repeat(2, 1fr);
    }

}


@media (max-width: 700px) {

    .ticket-queues {
        grid-template-columns: 1fr;
    }

    .ticket-filters form {
        grid-template-columns: 1fr;
    }

    .ticket-current-queue {
        align-items: flex-start;
        flex-direction: column;
    }

}

</style>


<div class="ticket-page">


    <!-- =====================================================
         Page Header
         ===================================================== -->

    <div class="ticket-page-title">

        <h1>
            🎫 مدیریت درخواست‌های پشتیبانی
        </h1>

        <p>
            صف درخواست‌ها، پیگیری وضعیت و مدیریت درخواست‌های کاربران
        </p>

    </div>


    <!-- =====================================================
         Queue Navigation
         ===================================================== -->

    <div class="ticket-queues">


        <a
            href="tickets.php?queue=new"
            class="ticket-queue <?php echo ($queue === 'new') ? 'active' : ''; ?>"
        >

            <div class="ticket-queue-title">

                <strong>
                    🆕 درخواست‌های جدید
                </strong>

                <span>
                    درخواست‌های تازه ثبت‌شده
                </span>

            </div>

            <span class="ticket-queue-count">
                <?php echo persianNumber($queueStats['new']); ?>
            </span>

        </a>


        <a
            href="tickets.php?queue=active"
            class="ticket-queue <?php echo ($queue === 'active') ? 'active' : ''; ?>"
        >

            <div class="ticket-queue-title">

                <strong>
                    🔧 درخواست‌های در حال اجرا
                </strong>

                <span>
                    تخصیص داده شده و در حال بررسی
                </span>

            </div>

            <span class="ticket-queue-count">
                <?php echo persianNumber($queueStats['active']); ?>
            </span>

        </a>


        <a
            href="tickets.php?queue=waiting"
            class="ticket-queue <?php echo ($queue === 'waiting') ? 'active' : ''; ?>"
        >

            <div class="ticket-queue-title">

                <strong>
                    ⏳ منتظر پاسخ کاربر
                </strong>

                <span>
                    منتظر اطلاعات یا پاسخ کاربر
                </span>

            </div>

            <span class="ticket-queue-count">
                <?php echo persianNumber($queueStats['waiting']); ?>
            </span>

        </a>


        <a
            href="tickets.php?queue=attention"
            class="ticket-queue attention <?php echo ($queue === 'attention') ? 'active' : ''; ?>"
        >

            <div class="ticket-queue-title">

                <strong>
                    🔔 نیازمند توجه من
                </strong>

                <span>
                    پیام‌های خوانده‌نشده من
                </span>

            </div>

            <span class="ticket-queue-count">
                <?php echo persianNumber($queueStats['attention']); ?>
            </span>

        </a>


        <a
            href="tickets.php?queue=closed"
            class="ticket-queue <?php echo ($queue === 'closed') ? 'active' : ''; ?>"
        >

            <div class="ticket-queue-title">

                <strong>
                    🟢 درخواست‌های بسته‌شده
                </strong>

                <span>
                    درخواست‌های خاتمه‌یافته
                </span>

            </div>

            <span class="ticket-queue-count">
                <?php echo persianNumber($queueStats['closed']); ?>
            </span>

        </a>


        <a
            href="tickets.php?queue=all"
            class="ticket-queue <?php echo ($queue === 'all') ? 'active' : ''; ?>"
        >

            <div class="ticket-queue-title">

                <strong>
                    📋 درخواست‌های جاری
                </strong>

                <span>
                    درخواست‌های جدید و در حال پیگیری
                </span>

            </div>

            <span class="ticket-queue-count">
                <?php echo persianNumber($queueStats['all']); ?>
            </span>

        </a>


    </div>


    <!-- =====================================================
         Filters
         ===================================================== -->

    <div class="ticket-filters">

        <form method="get" action="tickets.php">


            <input
                type="hidden"
                name="queue"
                value="<?php echo htmlspecialchars($queue); ?>"
            >


            <div class="ticket-filter-group">

                <label>
                    جستجو
                </label>

                <input
                    type="text"
                    name="search"
                    value="<?php echo htmlspecialchars($search); ?>"
                    placeholder="شماره پیگیری، موضوع، نام یا نام کاربری"
                >

            </div>


            <div class="ticket-filter-group">

                <label>
                    وضعیت
                </label>

                <select name="status">

                    <option value="">
                        همه وضعیت‌ها
                    </option>

                    <?php foreach ($statusLabels as $key => $label): ?>

                        <option
                            value="<?php echo htmlspecialchars($key); ?>"
                            <?php echo ($status === $key) ? 'selected' : ''; ?>
                        >
                            <?php echo htmlspecialchars($label); ?>
                        </option>

                    <?php endforeach; ?>

                </select>

            </div>


            <div class="ticket-filter-group">

                <label>
                    اولویت
                </label>

                <select name="priority">

                    <option value="">
                        همه اولویت‌ها
                    </option>

                    <?php foreach ($priorityLabels as $key => $label): ?>

                        <option
                            value="<?php echo htmlspecialchars($key); ?>"
                            <?php echo ($priority === $key) ? 'selected' : ''; ?>
                        >
                            <?php echo htmlspecialchars($label); ?>
                        </option>

                    <?php endforeach; ?>

                </select>

            </div>


            <div class="ticket-filter-group">

                <label>
                    واحد
                </label>

                <select name="department">

                    <option value="">
                        همه واحدها
                    </option>

                    <?php foreach ($departmentLabels as $key => $label): ?>

                        <option
                            value="<?php echo htmlspecialchars($key); ?>"
                            <?php echo ($department === $key) ? 'selected' : ''; ?>
                        >
                            <?php echo htmlspecialchars($label); ?>
                        </option>

                    <?php endforeach; ?>

                </select>

            </div>


            <button
                type="submit"
                class="ticket-filter-button"
            >
                🔎 جستجو
            </button>


            <a
                href="tickets.php?queue=<?php echo urlencode($queue); ?>"
                class="ticket-filter-reset"
            >
                پاک کردن
            </a>


        </form>

    </div>


    <!-- =====================================================
         Current Queue Header
         ===================================================== -->

    <?php

    $queueTitles = array(
        'new'       => 'درخواست‌های جدید',
        'active'    => 'درخواست‌های در حال اجرا',
        'waiting'   => 'منتظر پاسخ کاربر',
        'attention' => 'درخواست‌های نیازمند توجه من',
        'closed'    => 'درخواست‌های بسته‌شده',
        'all'       => 'درخواست‌های جاری'
    );

    $currentQueueTitle = $queueTitles[$queue];

    ?>

    <div class="ticket-current-queue">

        <div class="ticket-current-queue-title">

            <strong>
                <?php echo htmlspecialchars($currentQueueTitle); ?>
            </strong>

            <span>
                <?php echo persianNumber($total_tickets); ?> درخواست
            </span>

        </div>

    </div>


    <!-- =====================================================
         Ticket Table
         ===================================================== -->

    <div class="ticket-table-box">


        <?php if (empty($tickets)): ?>


            <div class="ticket-empty">

                <div class="ticket-empty-icon">
                    🎫
                </div>

                <div class="ticket-empty-title">
                    درخواستی در این بخش وجود ندارد
                </div>

                <div class="ticket-empty-text">
                    با فیلترهای فعلی هیچ درخواستی برای نمایش پیدا نشد.
                </div>

            </div>


        <?php else: ?>


            <table class="ticket-table">

                <thead>

                    <tr>

                        <th>
                            شماره پیگیری
                        </th>

                        <th>
                            درخواست‌کننده
                        </th>

                        <th>
                            موضوع
                        </th>

                        <th>
                            نوع درخواست
                        </th>

                        <th>
                            واحد
                        </th>

                        <th>
                            اولویت
                        </th>

                        <th>
                            وضعیت
                        </th>

                        <th>
                            کارشناس
                        </th>

                        <th>
                            آخرین فعالیت
                        </th>

                        <th>
                            تاریخ ثبت
                        </th>

                        <th>
                            عملیات
                        </th>

                    </tr>

                </thead>


                <tbody>


                <?php foreach ($tickets as $ticket): ?>


                    <?php

                    $unreadCount = (int)$ticket['unread_count'];

                    $rowClass = $unreadCount > 0
                        ? 'ticket-unread'
                        : '';

                    $priorityClass =
                        'priority-' . $ticket['priority'];

                    $priorityText =
                        isset($priorityLabels[$ticket['priority']])
                            ? $priorityLabels[$ticket['priority']]
                            : $ticket['priority'];

                    $statusClass =
                        'status-' . $ticket['status'];

                    $statusText =
                        isset($statusLabels[$ticket['status']])
                            ? $statusLabels[$ticket['status']]
                            : $ticket['status'];

                    $departmentText =
                        isset($departmentLabels[$ticket['department']])
                            ? $departmentLabels[$ticket['department']]
                            : $ticket['department'];

                    $lastActivity =
                        !empty($ticket['last_message_at'])
                            ? $ticket['last_message_at']
                            : $ticket['updated_at'];

                    ?>


                    <tr class="<?php echo $rowClass; ?>">


                        <!-- Tracking -->

                        <td>

                            <div class="ticket-number">

                                <?php
                                echo htmlspecialchars(
                                    $ticket['tracking_number']
                                );
                                ?>

                            </div>


                            <?php if ($unreadCount > 0): ?>

                                <div class="ticket-unread-indicator">

                                    <span class="ticket-unread-dot"></span>

                                    <?php
                                    echo persianNumber($unreadCount);
                                    ?>

                                    پیام خوانده‌نشده

                                </div>

                            <?php endif; ?>

                        </td>


                        <!-- Requester -->

                        <td>

                            <div class="ticket-requester">

                                <strong>

                                    <?php
                                    echo htmlspecialchars(
                                        $ticket['fullname']
                                    );
                                    ?>

                                </strong>

                                <small>

                                    <?php
                                    echo htmlspecialchars(
                                        $ticket['requester_username']
                                    );
                                    ?>

                                </small>

                            </div>

                        </td>


                        <!-- Subject -->

                        <td>

                            <div class="ticket-subject">

                                <?php
                                echo htmlspecialchars(
                                    $ticket['subject']
                                );
                                ?>

                            </div>

                        </td>


                        <!-- Request Type -->

                        <td>

                            <?php
                            echo !empty($ticket['request_types'])
                                ? htmlspecialchars(
                                    $ticket['request_types']
                                )
                                : '-';
                            ?>

                        </td>


                        <!-- Department -->

                        <td>

                            <?php
                            echo htmlspecialchars(
                                $departmentText
                            );
                            ?>

                        </td>


                        <!-- Priority -->

                        <td>

                            <span
                                class="ticket-badge <?php echo $priorityClass; ?>"
                            >

                                <?php
                                echo htmlspecialchars(
                                    $priorityText
                                );
                                ?>

                            </span>

                        </td>


                        <!-- Status -->

                        <td>

                            <span
                                class="ticket-badge <?php echo $statusClass; ?>"
                            >

                                <?php
                                echo htmlspecialchars(
                                    $statusText
                                );
                                ?>

                            </span>

                        </td>


                        <!-- Assignee -->

                        <td>

                            <?php if (!empty($ticket['assigned_username'])): ?>

                                <div class="ticket-assignee assigned">

                                    👤
                                    <?php
                                    echo htmlspecialchars(
                                        $ticket['assigned_username']
                                    );
                                    ?>

                                </div>

                            <?php else: ?>

                                <div class="ticket-assignee unassigned">

                                    بدون کارشناس

                                </div>

                            <?php endif; ?>

                        </td>


                        <!-- Last Activity -->

                        <td>

                            <div class="ticket-last-activity">

                                <div class="ticket-last-activity-label">
                                    آخرین فعالیت
                                </div>

                                <?php
                                echo htmlspecialchars(
                                    jalaliDate($lastActivity)
                                );
                                ?>

                            </div>

                        </td>


                        <!-- Created -->

                        <td>

                            <div class="ticket-date">

                                <?php
                                echo htmlspecialchars(
                                    jalaliDate(
                                        $ticket['created_at']
                                    )
                                );
                                ?>

                            </div>

                        </td>


                        <!-- Action -->

                        <td>

                            <a
                                href="ticket-manage.php?id=<?php echo (int)$ticket['id']; ?>"
                                class="ticket-view-button"
                            >
                                مدیریت
                            </a>

                        </td>


                    </tr>


                <?php endforeach; ?>


                </tbody>

            </table>


        <?php endif; ?>


    </div>


    <?php if ($total_pages > 1): ?>

        <div class="ticket-pagination-area">

            <div class="ticket-pagination-info">
                نمایش
                <?php echo persianNumber($pagination_start); ?>
                تا
                <?php echo persianNumber($pagination_end); ?>
                از
                <?php echo persianNumber($total_tickets); ?>
                درخواست
            </div>

            <div class="ticket-pagination">

                <?php if ($current_page > 1): ?>
                    <a href="<?php echo htmlspecialchars($buildPageUrl($current_page - 1)); ?>" aria-label="صفحه قبل">‹</a>
                <?php else: ?>
                    <span class="ticket-pagination-disabled">‹</span>
                <?php endif; ?>

                <?php
                $paginationPages = array();

                if ($total_pages <= 7) {
                    for ($i = 1; $i <= $total_pages; $i++) {
                        $paginationPages[] = $i;
                    }
                } else {
                    $paginationPages[] = 1;

                    if ($current_page > 4) {
                        $paginationPages[] = 'dots-left';
                    }

                    $startPage = max(2, $current_page - 2);
                    $endPage = min($total_pages - 1, $current_page + 2);

                    for ($i = $startPage; $i <= $endPage; $i++) {
                        $paginationPages[] = $i;
                    }

                    if ($current_page < $total_pages - 3) {
                        $paginationPages[] = 'dots-right';
                    }

                    $paginationPages[] = $total_pages;
                }
                ?>

                <?php foreach ($paginationPages as $pageItem): ?>

                    <?php if (is_string($pageItem)): ?>

                        <span class="ticket-pagination-dots">…</span>

                    <?php elseif ($pageItem === $current_page): ?>

                        <span class="ticket-pagination-current">
                            <?php echo persianNumber($pageItem); ?>
                        </span>

                    <?php else: ?>

                        <a href="<?php echo htmlspecialchars($buildPageUrl($pageItem)); ?>">
                            <?php echo persianNumber($pageItem); ?>
                        </a>

                    <?php endif; ?>

                <?php endforeach; ?>

                <?php if ($current_page < $total_pages): ?>
                    <a href="<?php echo htmlspecialchars($buildPageUrl($current_page + 1)); ?>" aria-label="صفحه بعد">›</a>
                <?php else: ?>
                    <span class="ticket-pagination-disabled">›</span>
                <?php endif; ?>

            </div>

        </div>

    <?php endif; ?>


</div>


<?php require_once __DIR__ . '/includes/footer.php'; ?>

