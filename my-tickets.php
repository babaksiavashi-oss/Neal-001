<?php

$portal_mode = true;

require_once "auth/auth.php";

check_user_login();

$page_title = "درخواست‌های من";

$db = new PDO(
    "sqlite:" . __DIR__ . "/data/neal.db",
    null,
    null,
    [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]
);

$current_username = $_SESSION['user_username'] ?? '';


/*
 * =========================================================
 * وضعیت‌ها
 * =========================================================
 */

$status_labels = [
    'new'          => 'جدید',
    'assigned'     => 'ارجاع شده',
    'in_progress'  => 'در حال بررسی',
    'waiting_user' => 'منتظر پاسخ شما',
    'resolved'     => 'حل شده',
    'closed'       => 'بسته شده',
    'cancelled'    => 'لغو شده'
];


$priority_labels = [
    'normal'    => 'عادی',
    'important' => 'مهم',
    'urgent'    => 'فوری',
    'critical'  => 'بحرانی'
];


/*
 * =========================================================
 * آمار درخواست‌های همین کاربر
 * =========================================================
 */

$stats = [
    'all'          => 0,
    'active'       => 0,
    'waiting_user' => 0,
    'closed'       => 0
];

$stmt = $db->prepare(
    "SELECT
        COUNT(*) AS all_count,

        SUM(
            CASE
                WHEN status IN ('new', 'assigned', 'in_progress')
                THEN 1
                ELSE 0
            END
        ) AS active_count,

        SUM(
            CASE
                WHEN status = 'waiting_user'
                THEN 1
                ELSE 0
            END
        ) AS waiting_count,

        SUM(
            CASE
                WHEN status = 'closed'
                THEN 1
                ELSE 0
            END
        ) AS closed_count

     FROM service_requests

     WHERE requester_username = ?"
);

$stmt->execute([
    $current_username
]);

$row = $stmt->fetch();

if ($row) {

    $stats['all'] =
        (int)$row['all_count'];

    $stats['active'] =
        (int)$row['active_count'];

    $stats['waiting_user'] =
        (int)$row['waiting_count'];

    $stats['closed'] =
        (int)$row['closed_count'];
}


/*
 * =========================================================
 * لیست درخواست‌های همین کاربر
 * =========================================================
 */

$stmt = $db->prepare(
    "SELECT
        sr.id,
        sr.tracking_number,
        sr.subject,
        sr.department,
        sr.priority,
        sr.status,
        sr.created_at,
        sr.updated_at,
        sr.assigned_to,

        a.username AS assigned_username,

        GROUP_CONCAT(
            DISTINCT rt.name
        ) AS request_types,

        (
            SELECT COUNT(*)
            FROM ticket_messages tm
            WHERE tm.request_id = sr.id
              AND tm.is_internal = 0
              AND tm.sender_username <> ?
        ) AS message_count

     FROM service_requests sr

     LEFT JOIN admins a
        ON a.id = sr.assigned_to

     LEFT JOIN service_request_types srt
        ON srt.request_id = sr.id

     LEFT JOIN request_types rt
        ON rt.id = srt.request_type_id

     WHERE sr.requester_username = ?

     GROUP BY sr.id

     ORDER BY
        CASE sr.status
            WHEN 'waiting_user' THEN 1
            WHEN 'new' THEN 2
            WHEN 'assigned' THEN 3
            WHEN 'in_progress' THEN 4
            WHEN 'resolved' THEN 5
            WHEN 'closed' THEN 6
            WHEN 'cancelled' THEN 7
            ELSE 8
        END,

        sr.updated_at DESC,
        sr.id DESC"
);

$stmt->execute([
    $current_username,
    $current_username
]);

$tickets = $stmt->fetchAll();


/*
 * =========================================================
 * دریافت پیوست‌های درخواست‌های همین کاربر
 *
 * هر درخواست می‌تواند چند فایل پیوست داشته باشد.
 * =========================================================
 */

$attachments_by_ticket = [];

$attachmentStmt = $db->prepare(
    "SELECT
        ta.id,
        ta.request_id,
        ta.original_name,
        ta.file_size,
        ta.mime_type,
        ta.created_at

     FROM ticket_attachments ta

     INNER JOIN service_requests sr
        ON sr.id = ta.request_id

     WHERE sr.requester_username = ?

     ORDER BY
        ta.created_at ASC,
        ta.id ASC"
);

$attachmentStmt->execute([
    $current_username
]);

$all_attachments = $attachmentStmt->fetchAll();

foreach ($all_attachments as $attachment) {

    $ticket_id = (int)$attachment['request_id'];

    if (!isset($attachments_by_ticket[$ticket_id])) {
        $attachments_by_ticket[$ticket_id] = [];
    }

    $attachments_by_ticket[$ticket_id][] = $attachment;
}


/*
 * =========================================================
 * تبدیل تاریخ
 * =========================================================
 */

function jalali_date($datetime)
{
    $timestamp = strtotime($datetime);

    if (!$timestamp) {
        return $datetime;
    }

    $gy = (int)date('Y', $timestamp);
    $gm = (int)date('n', $timestamp);
    $gd = (int)date('j', $timestamp);

    $g_d_m = [
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
    ];

    if ($gy > 1600) {

        $jy = 979;
        $gy -= 1600;

    } else {

        $jy = 0;
        $gy -= 621;
    }

    $gy2 = ($gm > 2)
        ? ($gy + 1)
        : $gy;

    $days =
        (365 * $gy)
        + floor(($gy2 + 3) / 4)
        - floor(($gy2 + 99) / 100)
        + floor(($gy2 + 399) / 400)
        - 80
        + $gd
        + $g_d_m[$gm - 1];

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


/*
 * =========================================================
 * کلاس‌های وضعیت
 * =========================================================
 */

function ticket_status_class($status)
{
    switch ($status) {

        case 'new':
            return 'status-new';

        case 'assigned':
            return 'status-assigned';

        case 'in_progress':
            return 'status-progress';

        case 'waiting_user':
            return 'status-waiting';

        case 'resolved':
            return 'status-resolved';

        case 'closed':
            return 'status-closed';

        case 'cancelled':
            return 'status-cancelled';

        default:
            return '';
    }
}


function ticket_priority_class($priority)
{
    switch ($priority) {

        case 'critical':
            return 'priority-critical';

        case 'urgent':
            return 'priority-urgent';

        case 'important':
            return 'priority-important';

        default:
            return 'priority-normal';
    }
}


require_once "includes/header.php";

?>

<div class="my-tickets-page">


    <div class="my-tickets-header">

        <div>

            <h1>
                درخواست‌های من
            </h1>

            <p>
                مشاهده و پیگیری درخواست‌های ثبت‌شده توسط شما
            </p>

        </div>


        <a
            href="/neal/service-request.php"
            class="new-ticket-button"
        >
            ＋ درخواست جدید
        </a>

    </div>



    <!-- =====================================================
         آمار
    ====================================================== -->

    <div class="ticket-stats">


        <div class="ticket-stat-card">

            <div class="ticket-stat-icon">
                📥
            </div>

            <div>

                <span>
                    کل درخواست‌ها
                </span>

                <strong>
                    <?php echo $stats['all']; ?>
                </strong>

            </div>

        </div>



        <div class="ticket-stat-card">

            <div class="ticket-stat-icon">
                🔵
            </div>

            <div>

                <span>
                    در حال بررسی
                </span>

                <strong>
                    <?php echo $stats['active']; ?>
                </strong>

            </div>

        </div>



        <div class="ticket-stat-card">

            <div class="ticket-stat-icon">
                🟡
            </div>

            <div>

                <span>
                    منتظر پاسخ شما
                </span>

                <strong>
                    <?php echo $stats['waiting_user']; ?>
                </strong>

            </div>

        </div>



        <div class="ticket-stat-card">

            <div class="ticket-stat-icon">
                🟢
            </div>

            <div>

                <span>
                    بسته‌شده
                </span>

                <strong>
                    <?php echo $stats['closed']; ?>
                </strong>

            </div>

        </div>


    </div>



    <!-- =====================================================
         لیست درخواست‌ها
    ====================================================== -->

    <div class="card ticket-list-card">


        <div class="ticket-list-header">

            <div>

                <h2>
                    لیست درخواست‌ها
                </h2>

                <span>
                    درخواست‌های ثبت‌شده توسط شما
                </span>

            </div>

        </div>



        <?php if (empty($tickets)): ?>


            <div class="ticket-empty">

                <div class="ticket-empty-icon">
                    🎫
                </div>

                <h3>
                    هنوز درخواستی ثبت نشده است
                </h3>

                <p>
                    اگر مشکلی دارید یا به خدمات واحد فناوری اطلاعات نیاز دارید،
                    می‌توانید یک درخواست جدید ثبت کنید.
                </p>

                <a
                    href="/neal/service-request.php"
                    class="new-ticket-button"
                >
                    ＋ ثبت درخواست جدید
                </a>

            </div>


        <?php else: ?>


            <div class="ticket-table-wrapper">

                <table class="ticket-table">


                    <thead>

                        <tr>

                            <th>
                                شماره پیگیری
                            </th>


                            <th>
                                موضوع
                            </th>


                            <th>
                                نوع درخواست
                            </th>


                            <th>
                                پیوست
                            </th>


                            <th>
                                اولویت
                            </th>


                            <th>
                                وضعیت
                            </th>


                            <th>
                                آخرین بروزرسانی
                            </th>


                            <th>
                                عملیات
                            </th>

                        </tr>

                    </thead>



                    <tbody>


                    <?php foreach ($tickets as $ticket): ?>


                        <?php

                        $ticket_id =
                            (int)$ticket['id'];

                        $ticket_attachments =
                            $attachments_by_ticket[$ticket_id]
                            ?? [];

                        ?>


                        <tr>


                            <td>

                                <strong class="tracking-number">

                                    <?php

                                    echo htmlspecialchars(
                                        $ticket['tracking_number']
                                    );

                                    ?>

                                </strong>

                            </td>



                            <td>

                                <div class="ticket-subject">

                                    <?php

                                    echo htmlspecialchars(
                                        $ticket['subject']
                                    );

                                    ?>

                                </div>


                                <?php if (!empty($ticket['department'])): ?>

                                    <small>

                                        <?php

                                        echo htmlspecialchars(
                                            $ticket['department']
                                        );

                                        ?>

                                    </small>

                                <?php endif; ?>


                            </td>



                            <td>

                                <?php

                                echo htmlspecialchars(
                                    $ticket['request_types']
                                    ?: '---'
                                );

                                ?>

                            </td>



                            <!-- Attachment -->

                            <td>

                                <?php if (!empty($ticket_attachments)): ?>


                                    <div class="ticket-list-attachments">


                                        <?php foreach ($ticket_attachments as $attachment): ?>


                                            <?php

                                            $attachment_id =
                                                (int)$attachment['id'];

                                            $attachment_name =
                                                (string)$attachment['original_name'];

                                            $attachment_extension =
                                                strtoupper(
                                                    pathinfo(
                                                        $attachment_name,
                                                        PATHINFO_EXTENSION
                                                    )
                                                );

                                            $attachment_size =
                                                (int)$attachment['file_size'];


                                            if ($attachment_size >= 1048576) {

                                                $attachment_size_text =
                                                    number_format(
                                                        $attachment_size / 1048576,
                                                        2
                                                    ) . ' MB';

                                            } elseif ($attachment_size >= 1024) {

                                                $attachment_size_text =
                                                    number_format(
                                                        $attachment_size / 1024,
                                                        2
                                                    ) . ' KB';

                                            } else {

                                                $attachment_size_text =
                                                    $attachment_size . ' B';
                                            }

                                            ?>


                                            <a
                                                href="/neal/download-attachment.php?id=<?php echo $attachment_id; ?>"
                                                class="ticket-list-attachment"
                                                title="<?php echo htmlspecialchars($attachment_name); ?>"
                                            >

                                                <span class="ticket-list-attachment-icon">
                                                    📎
                                                </span>


                                                <span class="ticket-list-attachment-content">


                                                    <strong>

                                                        <?php

                                                        echo htmlspecialchars(
                                                            $attachment_name
                                                        );

                                                        ?>

                                                    </strong>


                                                    <small>

                                                        <?php

                                                        echo htmlspecialchars(
                                                            $attachment_extension
                                                            ?: '---'
                                                        );

                                                        ?>

                                                        ·

                                                        <?php

                                                        echo htmlspecialchars(
                                                            $attachment_size_text
                                                        );

                                                        ?>

                                                    </small>


                                                </span>


                                            </a>


                                        <?php endforeach; ?>


                                    </div>


                                <?php else: ?>


                                    <span class="no-ticket-attachment">
                                        —
                                    </span>


                                <?php endif; ?>


                            </td>



                            <td>

                                <span
                                    class="ticket-badge
                                    <?php

                                    echo ticket_priority_class(
                                        $ticket['priority']
                                    );

                                    ?>"
                                >

                                    <?php

                                    echo htmlspecialchars(
                                        $priority_labels[
                                            $ticket['priority']
                                        ]
                                        ?? $ticket['priority']
                                    );

                                    ?>

                                </span>

                            </td>



                            <td>

                                <span
                                    class="ticket-badge
                                    <?php

                                    echo ticket_status_class(
                                        $ticket['status']
                                    );

                                    ?>"
                                >

                                    <?php

                                    echo htmlspecialchars(
                                        $status_labels[
                                            $ticket['status']
                                        ]
                                        ?? $ticket['status']
                                    );

                                    ?>

                                </span>

                            </td>



                            <td>

                                <?php

                                echo htmlspecialchars(
                                    jalali_date(
                                        $ticket['updated_at']
                                    )
                                );

                                ?>

                            </td>



                            <td>

                                <a
                                    href="/neal/ticket-view.php?id=<?php echo $ticket_id; ?>"
                                    class="view-ticket-button"
                                >
                                    👁 مشاهده
                                </a>

                            </td>


                        </tr>


                    <?php endforeach; ?>


                    </tbody>


                </table>

            </div>


        <?php endif; ?>


    </div>


</div>



<style>


.my-tickets-page {
    width: 100%;
    max-width: 1400px;
    margin: 0 auto;
}


.my-tickets-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 20px;
    margin-bottom: 25px;
}


.my-tickets-header h1 {
    margin: 0 0 8px 0;
    font-size: 28px;
}


.my-tickets-header p {
    margin: 0;
    color: #777;
    font-size: 14px;
}


.new-ticket-button {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 11px 18px;
    border-radius: 9px;
    background: #1769aa;
    color: #fff !important;
    text-decoration: none;
    font-weight: 700;
    white-space: nowrap;
}


.new-ticket-button:hover {
    opacity: .9;
}


.ticket-stats {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 15px;
    margin-bottom: 25px;
}


.ticket-stat-card {
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 12px;
    padding: 18px;
    display: flex;
    align-items: center;
    gap: 15px;
    box-shadow: 0 2px 8px rgba(0,0,0,.04);
}


.ticket-stat-icon {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #f1f5f9;
    font-size: 24px;
}


.ticket-stat-card span {
    display: block;
    color: #777;
    font-size: 13px;
    margin-bottom: 5px;
}


.ticket-stat-card strong {
    display: block;
    font-size: 24px;
    color: #222;
}


.ticket-list-card {
    overflow: hidden;
}


.ticket-list-header {
    padding: 22px;
    border-bottom: 1px solid #e5e7eb;
}


.ticket-list-header h2 {
    margin: 0 0 6px 0;
}


.ticket-list-header span {
    color: #777;
    font-size: 13px;
}


.ticket-table-wrapper {
    width: 100%;
    overflow-x: auto;
}


.ticket-table {
    width: 100%;
    border-collapse: collapse;
    min-width: 1150px;
}


.ticket-table th {
    background: #f8fafc;
    color: #555;
    font-size: 13px;
    font-weight: 700;
    padding: 14px;
    text-align: right;
    border-bottom: 1px solid #e5e7eb;
}


.ticket-table td {
    padding: 15px 14px;
    border-bottom: 1px solid #edf0f2;
    vertical-align: middle;
    font-size: 13px;
}


.ticket-table tbody tr:hover {
    background: #fafcff;
}


.tracking-number {
    color: #1769aa;
    direction: ltr;
    display: inline-block;
}


.ticket-subject {
    font-weight: 700;
    margin-bottom: 5px;
}


.ticket-table small {
    color: #888;
}


/* =========================================================
   Attachment
   ========================================================= */


.ticket-list-attachments {
    display: flex;
    flex-direction: column;
    gap: 8px;
    min-width: 190px;
    max-width: 280px;
}


.ticket-list-attachment {
    display: flex;
    align-items: center;
    gap: 8px;
    width: 100%;
    padding: 8px 10px;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    color: #344054 !important;
    text-decoration: none;
    box-sizing: border-box;
}


.ticket-list-attachment:hover {
    background: #eef6ff;
    border-color: #b9d7f5;
}


.ticket-list-attachment-icon {
    flex-shrink: 0;
    font-size: 17px;
}


.ticket-list-attachment-content {
    display: flex;
    flex-direction: column;
    min-width: 0;
    gap: 3px;
}


.ticket-list-attachment-content strong {
    display: block;
    color: #1769aa;
    font-size: 12px;
    font-weight: 700;
    line-height: 1.5;
    overflow-wrap: anywhere;
    word-break: break-word;
}


.ticket-list-attachment-content small {
    color: #667085;
    font-size: 10px;
    line-height: 1.4;
}


.no-ticket-attachment {
    color: #98a2b3;
    font-size: 16px;
}


.ticket-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 5px 10px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 700;
    white-space: nowrap;
}


.status-new {
    background: #e8f1ff;
    color: #1769aa;
}


.status-assigned {
    background: #eef2ff;
    color: #4f46e5;
}


.status-progress {
    background: #e8f7ff;
    color: #087ea4;
}


.status-waiting {
    background: #fff7df;
    color: #a16207;
}


.status-resolved {
    background: #ecfdf5;
    color: #047857;
}


.status-closed {
    background: #eef0f2;
    color: #555;
}


.status-cancelled {
    background: #fef2f2;
    color: #b91c1c;
}


.priority-normal {
    background: #f1f5f9;
    color: #475569;
}


.priority-important {
    background: #fff7df;
    color: #a16207;
}


.priority-urgent {
    background: #fff0e6;
    color: #c2410c;
}


.priority-critical {
    background: #fee2e2;
    color: #b91c1c;
}


.view-ticket-button {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 7px 12px;
    border-radius: 7px;
    background: #eef6ff;
    color: #1769aa !important;
    text-decoration: none;
    font-weight: 700;
    white-space: nowrap;
}


.view-ticket-button:hover {
    background: #dcecff;
}


.ticket-empty {
    text-align: center;
    padding: 60px 25px;
}


.ticket-empty-icon {
    font-size: 48px;
    margin-bottom: 15px;
}


.ticket-empty h3 {
    margin: 0 0 10px 0;
}


.ticket-empty p {
    max-width: 550px;
    margin: 0 auto 22px auto;
    color: #777;
    line-height: 1.9;
}


@media (max-width: 900px) {

    .ticket-stats {
        grid-template-columns: repeat(2, 1fr);
    }


    .my-tickets-header {
        align-items: flex-start;
        flex-direction: column;
    }

}


@media (max-width: 600px) {

    .ticket-stats {
        grid-template-columns: 1fr;
    }

}


</style>



<?php

require_once "includes/footer.php";

?>

