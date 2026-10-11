<?php

$portal_mode = true;

require_once "auth/auth.php";
require_once "config/config.php";

check_user_login();
$page_title = "جزئیات درخواست";

$db = new PDO(
    "sqlite:" . __DIR__ . "/data/neal.db"
);

$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$current_username = isset($_SESSION['user_username'])
    ? (string)$_SESSION['user_username']
    : '';

/*
 * شناسه درخواست
 */

$request_id = isset($_GET['id'])
    ? (int)$_GET['id']
    : 0;

if ($request_id <= 0) {

    header("Location: my-tickets.php");
    exit;

}


/*
 * تبدیل تاریخ میلادی به شمسی
 */

function jalaliDate($datetime)
{
    if (empty($datetime)) {
        return '---';
    }

    $timestamp = strtotime($datetime);

    if ($timestamp === false) {
        return '---';
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

    $gy2 = ($gm > 2)
        ? ($gy + 1)
        : $gy;

    $days =
        (365 * $gy)
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


/*
 * تبدیل اعداد به فارسی
 */

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



/*
 * ------------------------------------------------------------
 * Rich Text Message Helpers
 * ------------------------------------------------------------
 */

function ticket_view_escape($value)
{
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        'UTF-8'
    );
}


function sanitize_ticket_view_message_html($html)
{
    $html = (string)$html;

    /*
     * حذف تگ‌های خطرناک
     */
    $html = preg_replace(
        '#<(script|style|iframe|object|embed|form|input|button|textarea|select|option|meta|link)[^>]*>.*?</\1>#is',
        '',
        $html
    );

    $html = preg_replace(
        '#<(script|style|iframe|object|embed|form|input|button|textarea|select|option|meta|link)[^>]*/?>#is',
        '',
        $html
    );

    /*
     * حذف comment
     */
    $html = preg_replace(
        '#<!--.*?-->#s',
        '',
        $html
    );

    /*
     * حذف event handlerها
     */
    $html = preg_replace(
        '/\s+on[a-z0-9_-]+\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^\s>]+)/i',
        '',
        $html
    );

    /*
     * فقط تگ‌های مورد نیاز Rich Text
     */
    $allowed =
        '<strong><b><em><i><u><a><ul><ol><li><br><div><p><span>';

    $html = strip_tags(
        $html,
        $allowed
    );

    /*
     * حذف URLهای خطرناک
     */
    $html = preg_replace_callback(
        '/\s+href\s*=\s*(["\'])(.*?)\1/is',
        function ($match) {

            $url = trim($match[2]);

            if (
                !preg_match(
                    '#^(https?|mailto|tel):#i',
                    $url
                )
            ) {
                return '';
            }

            return
                ' href="' .
                ticket_view_escape($url) .
                '"';

        },
        $html
    );

    /*
     * پاکسازی attributeهای تگ a
     */
    $html = preg_replace_callback(
        '/<a\b([^>]*)>/i',
        function ($match) {

            $attrs = $match[1];

            $href = '';

            if (
                preg_match(
                    '/\bhref\s*=\s*(["\'])(.*?)\1/i',
                    $attrs,
                    $m
                )
            ) {
                $href = trim($m[2]);
            }

            if ($href === '') {
                return '<a>';
            }

            return
                '<a href="' .
                ticket_view_escape($href) .
                '" target="_blank" rel="noopener noreferrer">';

        },
        $html
    );

    /*
     * فقط text-align مجاز باشد.
     */
    $html = preg_replace_callback(
        '/<(span|div|p)\b([^>]*)>/i',
        function ($match) {

            $tag = strtolower($match[1]);
            $attrs = $match[2];

            $style = '';

            if (
                preg_match(
                    '/\bstyle\s*=\s*(["\'])(.*?)\1/i',
                    $attrs,
                    $m
                )
            ) {

                if (
                    preg_match(
                        '/text-align\s*:\s*(left|right|center|justify)/i',
                        $m[2],
                        $align
                    )
                ) {

                    $style =
                        ' style="text-align:' .
                        strtolower($align[1]) .
                        ';"';

                }

            }

            return '<' . $tag . $style . '>';

        },
        $html
    );

    /*
     * حذف تمام attributeهای سایر تگ‌ها
     */
    $html = preg_replace_callback(
        '/<(strong|b|em|i|u|ul|ol|li|br)\b[^>]*>/i',
        function ($match) {

            return '<' .
                strtolower($match[1]) .
                '>';

        },
        $html
    );

    return trim($html);
}


function ticket_view_message_html($message)
{
    $message = (string)$message;

    /*
     * پیام‌های جدید Rich Text
     */
    if (
        preg_match(
            '/<\s*(strong|b|em|i|u|a|ul|ol|li|br|div|p|span)\b/i',
            $message
        )
    ) {

        return sanitize_ticket_view_message_html(
            $message
        );

    }

    /*
     * پیام‌های قدیمی Plain Text
     */
    return nl2br(
        ticket_view_escape($message),
        false
    );
}



/*
 * وضعیت‌ها
 */

$statusLabels = array(

    'new' => 'جدید',

    'assigned' => 'تخصیص داده شده',

    'in_progress' => 'در حال بررسی',

    'waiting_user' => 'منتظر پاسخ شما',

    'resolved' => 'حل شده',

    'closed' => 'بسته شده',

    'cancelled' => 'لغو شده'

);


/*
 * اولویت‌ها
 */

$priorityLabels = array(

    'normal' => 'عادی',

    'important' => 'مهم',

    'urgent' => 'فوری',

    'critical' => 'بحرانی'

);


/*
 * نام فارسی واحدها
 */

$departmentLabels = array(

    'management' => 'واحد مدیریت',

    'regulatory' => 'واحد مسئول فنی و رگولاتوری',

    'administrative' => 'واحد اداری',

    'engineering' => 'واحد فنی مهندسی',

    'finance' => 'واحد مالی',

    'planning' => 'واحد برنامه ریزی و انبار',

    'quality' => 'واحد آزمایشگاه کنترل کیفیت',

    'commercial' => 'واحد بازرگانی',

    'production' => 'واحد تولید',

    'hse' => 'واحد ایمنی و بهداشت'

);


/*
 * دریافت درخواست
 *
 * فقط درخواست متعلق به همین کاربر
 */

$stmt = $db->prepare("
    SELECT
        sr.*,
        GROUP_CONCAT(rt.name, '، ') AS request_types

    FROM service_requests sr

    LEFT JOIN service_request_types srt
        ON srt.request_id = sr.id

    LEFT JOIN request_types rt
        ON rt.id = srt.request_type_id

    WHERE sr.id = ?
      AND sr.requester_username = ?
    GROUP BY sr.id

    LIMIT 1
");

$stmt->execute(
    array(
        $request_id,
	$current_username        
    )
);

$ticket = $stmt->fetch(PDO::FETCH_ASSOC);


/*
 * اگر درخواست وجود نداشت
 */

if (!$ticket) {

    header("Location: my-tickets.php");
    exit;

}


/*
 * ثبت پیام جدید
 *
 * باید قبل از header.php انجام شود
 * تا redirect بدون مشکل انجام شود.
 */

$message_error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $message = isset($_POST['message'])
        ? trim($_POST['message'])
        : '';

    /*
     * Sanitize Rich Text message before database storage.
     */
    $message = sanitize_ticket_view_message_html(
        $message
    );

    /*
     * Plain text version used only to validate
     * that the message actually contains content.
     */
    $plainMessage = trim(
        html_entity_decode(
            strip_tags($message),
            ENT_QUOTES,
            'UTF-8'
        )
    );

    if ($message === '' || $plainMessage === '') {

        $message_error = 'لطفاً متن پیام را وارد کنید.';

    } else {

        /*
         * فقط صاحب همین تیکت اجازه ارسال پیام دارد.
         */

        $checkStmt = $db->prepare("
            SELECT id
            FROM service_requests
            WHERE id = ?
	    AND requester_username = ?              
            LIMIT 1
        ");

        $checkStmt->execute(
            array(
                $request_id,
                $current_username
            )
        );

        $allowedTicket = $checkStmt->fetch(PDO::FETCH_ASSOC);

        if (!$allowedTicket) {

            header("Location: my-tickets.php");
            exit;

        }


        /*
         * نام کاربری فعلی
         */

                        $senderUsername = $current_username;

        /*
         * ذخیره پیام
         */

        $insertStmt = $db->prepare("
            INSERT INTO ticket_messages
            (
                request_id,
                sender_admin_id,
                sender_username,
                message,
                is_internal
            )
            VALUES
            (
                ?,
                ?,
                ?,
                ?,
                0
            )
        ");

        $insertStmt->execute(
    array(
        $request_id,
        null,
        $senderUsername,
        $message
    )
);        

        /*
         * بروزرسانی زمان تیکت
         */

        $updateStmt = $db->prepare("
            UPDATE service_requests
            SET updated_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ");

        $updateStmt->execute(
            array($request_id)
        );


        /*
         * بازگشت به همان صفحه
         * برای جلوگیری از ارسال دوباره فرم با Refresh
         */

        header(
            "Location: ticket-view.php?id=" .
            $request_id .
            "#conversation"
        );

        exit;

    }

}


/*
 * دریافت پیام‌های گفتگو
 *
 * پیام‌های داخلی IT برای کاربر نمایش داده نمی‌شوند.
 */

$messageStmt = $db->prepare("
    SELECT
        tm.*
    FROM ticket_messages tm
    WHERE tm.request_id = ?
      AND tm.is_internal = 0
    ORDER BY tm.created_at ASC, tm.id ASC
");

$messageStmt->execute(
    array($request_id)
);

$messages = $messageStmt->fetchAll(PDO::FETCH_ASSOC);
/*
 * دریافت پیوست‌های درخواست
 */

$attachmentStmt = $db->prepare("
    SELECT
        id,
        original_name,
        file_size,
        mime_type,
        created_at
    FROM ticket_attachments
    WHERE request_id = ?
    ORDER BY created_at ASC, id ASC
");

$attachmentStmt->execute(
    array($request_id)
);

$attachments = $attachmentStmt->fetchAll(PDO::FETCH_ASSOC);


$status = $ticket['status'];

$priority = $ticket['priority'];

$statusText =
    isset($statusLabels[$status])
    ? $statusLabels[$status]
    : $status;

$priorityText =
    isset($priorityLabels[$priority])
    ? $priorityLabels[$priority]
    : $priority;

$departmentText =
    isset($departmentLabels[$ticket['department']])
    ? $departmentLabels[$ticket['department']]
    : $ticket['department'];

?>

<?php include "includes/header.php"; ?>


<div class="ticket-view-page">


    <!-- Header -->

    <div class="ticket-view-header">


        <div>

            <div class="ticket-back">

                <a href="my-tickets.php">
                    ← بازگشت به درخواست‌های من
                </a>

            </div>


            <h1>
                جزئیات درخواست
            </h1>

            <p>
                مشاهده اطلاعات کامل درخواست ثبت‌شده
            </p>

        </div>


        <div class="ticket-view-number">

            <span>
                شماره پیگیری
            </span>

            <strong>
                <?php
                echo htmlspecialchars(
                    $ticket['tracking_number']
                );
                ?>
            </strong>

        </div>


    </div>



    <!-- Status / Priority -->

    <div class="ticket-view-summary">


        <div class="summary-item">

            <span>
                وضعیت
            </span>

            <strong
                class="summary-badge ticket-status-<?php echo htmlspecialchars($status); ?>"
            >
                <?php
                echo htmlspecialchars($statusText);
                ?>
            </strong>

        </div>


        <div class="summary-item">

            <span>
                اولویت
            </span>

            <strong
                class="summary-badge ticket-priority-<?php echo htmlspecialchars($priority); ?>"
            >
                <?php
                echo htmlspecialchars($priorityText);
                ?>
            </strong>

        </div>


        <div class="summary-item">

            <span>
                تاریخ ثبت
            </span>

            <strong dir="ltr">

                <?php
                echo persianNumber(
                    jalaliDate(
                        $ticket['created_at']
                    )
                );
                ?>

            </strong>

        </div>


        <div class="summary-item">

            <span>
                شماره داخلی
            </span>

            <strong>

                #<?php
                echo persianNumber(
                    $ticket['id']
                );
                ?>

            </strong>

        </div>


    </div>



    <!-- Subject -->

    <div class="ticket-view-card">


        <div class="ticket-card-title">

            <span class="ticket-card-icon">
                📝
            </span>

            <div>

                <h2>
                    موضوع درخواست
                </h2>

                <p>
                    عنوان ثبت‌شده برای درخواست
                </p>

            </div>

        </div>


        <div class="ticket-subject-box">

            <?php
            echo htmlspecialchars(
                $ticket['subject']
            );
            ?>

        </div>


    </div>



    <!-- Request Information -->

    <div class="ticket-view-card">


        <div class="ticket-card-title">

            <span class="ticket-card-icon">
                📋
            </span>

            <div>

                <h2>
                    اطلاعات درخواست
                </h2>

                <p>
                    مشخصات ثبت‌شده هنگام ایجاد درخواست
                </p>

            </div>

        </div>


        <div class="ticket-detail-grid">


            <div class="detail-box">

                <span>
                    نام و نام خانوادگی
                </span>

                <strong>
                    <?php
                    echo htmlspecialchars(
                        $ticket['fullname']
                    );
                    ?>
                </strong>

            </div>


            <div class="detail-box">

                <span>
                    کد پرسنلی
                </span>

                <strong>
                    <?php
                    echo htmlspecialchars(
                        $ticket['personnel_code']
                    );
                    ?>
                </strong>

            </div>


            <div class="detail-box">

                <span>
                    واحد / دپارتمان
                </span>

                <strong>
                    <?php
                    echo htmlspecialchars(
                        $departmentText
                    );
                    ?>
                </strong>

            </div>


            <div class="detail-box">

                <span>
                    تلفن
                </span>

                <strong>
                    <?php
                    echo htmlspecialchars(
                        $ticket['phone']
                        ?: '---'
                    );
                    ?>
                </strong>

            </div>


            <div class="detail-box detail-box-wide">

                <span>
                    نوع درخواست
                </span>

                <strong>
                    <?php
                    echo htmlspecialchars(
                        $ticket['request_types']
                        ?: '---'
                    );
                    ?>
                </strong>

            </div>


            <div class="detail-box">

                <span>
                    سیستم مرتبط
                </span>

                <strong>
                    <?php
                    echo htmlspecialchars(
                        $ticket['related_system']
                        ?: '---'
                    );
                    ?>
                </strong>

            </div>


            <div class="detail-box">

                <span>
                    نام کامپیوتر
                </span>

                <strong dir="ltr">

                    <?php
                    echo htmlspecialchars(
                        $ticket['hostname']
                        ?: '---'
                    );
                    ?>

                </strong>

            </div>


            <div class="detail-box">

                <span>
                    آدرس IP
                </span>

                <strong dir="ltr">

                    <?php
                    echo htmlspecialchars(
                        $ticket['ip_address']
                        ?: '---'
                    );
                    ?>

                </strong>

            </div>


            <div class="detail-box">

                <span>
                    محل
                </span>

                <strong>
                    <?php
                    echo htmlspecialchars(
                        $ticket['location']
                        ?: '---'
                    );
                    ?>
                </strong>

            </div>


            <div class="detail-box">

                <span>
                    دارایی / تجهیزات
                </span>

                <strong>
                    <?php
                    echo htmlspecialchars(
                        $ticket['asset']
                        ?: '---'
                    );
                    ?>
                </strong>

            </div>


        </div>


    </div>



    <!-- Description -->

    <div class="ticket-view-card">


        <div class="ticket-card-title">

            <span class="ticket-card-icon">
                📄
            </span>

            <div>

                <h2>
                    شرح کامل درخواست
                </h2>

                <p>
                    توضیحات ثبت‌شده توسط درخواست‌کننده
                </p>

            </div>

        </div>


        <div class="ticket-description">

            <?php
            echo nl2br(
                htmlspecialchars(
                    $ticket['description']
                )
            );
            ?>

        </div>


    </div>
	    <!-- Attachments -->

    <div class="ticket-view-card">


        <div class="ticket-card-title">

            <span class="ticket-card-icon">
                📎
            </span>

            <div>

                <h2>
                    فایل‌های پیوست
                </h2>

                <p>
                    فایل‌های ضمیمه‌شده به این درخواست
                </p>

            </div>

        </div>


        <?php if (!empty($attachments)): ?>


            <div class="ticket-attachments-list">


                <?php foreach ($attachments as $attachment): ?>


                    <?php

                    $fileSize = (int)$attachment['file_size'];

                    if ($fileSize >= 1048576) {

                        $fileSizeText =
                            number_format(
                                $fileSize / 1048576,
                                2
                            ) . ' MB';

                    } elseif ($fileSize >= 1024) {

                        $fileSizeText =
                            number_format(
                                $fileSize / 1024,
                                2
                            ) . ' KB';

                    } else {

                        $fileSizeText =
                            $fileSize . ' B';

                    }


                    $originalName =
                        (string)$attachment['original_name'];

                    $extension =
                        strtoupper(
                            pathinfo(
                                $originalName,
                                PATHINFO_EXTENSION
                            )
                        );

                    ?>


                    <div class="ticket-attachment-item">


                        <div class="ticket-attachment-info">


                            <div class="ticket-attachment-icon">
                                📎
                            </div>


                            <div class="ticket-attachment-details">


                                <a
                                    href="download-attachment.php?id=<?php echo (int)$attachment['id']; ?>"
                                    class="ticket-attachment-name"
                                >
                                    <?php
                                    echo htmlspecialchars(
                                        $originalName
                                    );
                                    ?>
                                </a>


                                <div class="ticket-attachment-meta">


                                    <span>
                                        فرمت:
                                        <?php
                                        echo htmlspecialchars(
                                            $extension ?: '---'
                                        );
                                        ?>
                                    </span>


                                    <span>
                                        حجم:
                                        <?php
                                        echo htmlspecialchars(
                                            $fileSizeText
                                        );
                                        ?>
                                    </span>


                                    <span>
                                        تاریخ:
                                        <?php
                                        echo htmlspecialchars(
                                            jalaliDate(
                                                $attachment['created_at']
                                            )
                                        );
                                        ?>
                                    </span>


                                </div>


                            </div>


                        </div>


                        <a
                            href="download-attachment.php?id=<?php echo (int)$attachment['id']; ?>"
                            class="ticket-attachment-download"
                        >
                            ⬇ دانلود
                        </a>


                    </div>


                <?php endforeach; ?>


            </div>


        <?php else: ?>


            <div class="ticket-no-attachments">

                <span>
                    📎
                </span>

                <span>
                    برای این درخواست فایلی ضمیمه نشده است.
                </span>

            </div>


        <?php endif; ?>


    </div>



    <!-- Dates -->


    <!-- Dates -->

    <div class="ticket-view-card">


        <div class="ticket-card-title">

            <span class="ticket-card-icon">
                🕐
            </span>

            <div>

                <h2>
                    زمان‌بندی درخواست
                </h2>

                <p>
                    تاریخ‌های ثبت‌شده در چرخه درخواست
                </p>

            </div>

        </div>


        <div class="ticket-detail-grid">


            <div class="detail-box">

                <span>
                    تاریخ ثبت
                </span>

                <strong dir="ltr">

                    <?php
                    echo persianNumber(
                        jalaliDate(
                            $ticket['created_at']
                        )
                    );
                    ?>

                </strong>

            </div>


            <div class="detail-box">

                <span>
                    تاریخ تخصیص
                </span>

                <strong dir="ltr">

                    <?php
                    echo persianNumber(
                        jalaliDate(
                            $ticket['assigned_at']
                        )
                    );
                    ?>

                </strong>

            </div>


            <div class="detail-box">

                <span>
                    تاریخ شروع بررسی
                </span>

                <strong dir="ltr">

                    <?php
                    echo persianNumber(
                        jalaliDate(
                            $ticket['started_at']
                        )
                    );
                    ?>

                </strong>

            </div>


            <div class="detail-box">

                <span>
                    تاریخ حل شدن
                </span>

                <strong dir="ltr">

                    <?php
                    echo persianNumber(
                        jalaliDate(
                            $ticket['resolved_at']
                        )
                    );
                    ?>

                </strong>

            </div>


            <div class="detail-box">

                <span>
                    تاریخ بسته شدن
                </span>

                <strong dir="ltr">

                    <?php
                    echo persianNumber(
                        jalaliDate(
                            $ticket['closed_at']
                        )
                    );
                    ?>

                </strong>

            </div>


            <div class="detail-box">

                <span>
                    آخرین بروزرسانی
                </span>

                <strong dir="ltr">

                    <?php
                    echo persianNumber(
                        jalaliDate(
                            $ticket['updated_at']
                        )
                    );
                    ?>

                </strong>

            </div>


        </div>


    </div>



    <!-- Conversation -->

    <div
        class="ticket-view-card ticket-conversation-card"
        id="conversation"
    >


        <div class="ticket-card-title">

            <span class="ticket-card-icon">
                💬
            </span>

            <div>

                <h2>
                    گفتگو
                </h2>

                <p>
                    پیام‌های مربوط به این درخواست
                </p>

            </div>

        </div>



        <?php if (count($messages) === 0): ?>


            <div class="conversation-empty">

                <div class="conversation-empty-icon">
                    💬
                </div>

                <strong>
                    هنوز پیامی در این گفتگو ثبت نشده است.
                </strong>

                <span>
                    در صورت نیاز می‌توانید پیام خود را برای واحد فناوری اطلاعات ارسال کنید.
                </span>

            </div>


        <?php else: ?>


            <div class="conversation-list">


                <?php foreach ($messages as $msg): ?>


                    <?php

                        $isMine =
                        ((string)$msg['sender_username'] === $current_username);

                    ?>


                    <div
                        class="conversation-message <?php echo $isMine ? 'message-mine' : 'message-other'; ?>"
                    >


                        <div class="message-header">


                            <strong>

                                <?php if ($isMine): ?>

                                    شما

                                <?php else: ?>

                                    <?php
                                    echo htmlspecialchars(
                                        $msg['sender_username']
                                    );
                                    ?>

                                <?php endif; ?>

                            </strong>


                            <span dir="ltr">

                                <?php
                                echo persianNumber(
                                    jalaliDate(
                                        $msg['created_at']
                                    )
                                );
                                ?>

                            </span>


                        </div>


                        <div class="message-body">

                            <?php
                            echo ticket_view_message_html(
                                $msg['message']
                            );
                            ?>

                        </div>


                    </div>


                <?php endforeach; ?>


            </div>


        <?php endif; ?>



        <!-- New Message -->

        <div class="conversation-reply">


            <div class="reply-title">

                ارسال پیام جدید

            </div>


            <?php if ($message_error !== ''): ?>

                <div class="message-error">

                    <?php
                    echo htmlspecialchars(
                        $message_error
                    );
                    ?>

                </div>

            <?php endif; ?>


            <form
                method="post"
                action="ticket-view.php?id=<?php echo (int)$request_id; ?>#conversation"
            >


                <div class="ticket-rich-editor">

                    <div class="ticket-rich-toolbar">

                        <button
                            type="button"
                            class="ticket-rich-btn"
                            data-command="bold"
                            title="پررنگ"
                        >
                            <strong>B</strong>
                        </button>

                        <button
                            type="button"
                            class="ticket-rich-btn"
                            data-command="italic"
                            title="کج"
                        >
                            <em>I</em>
                        </button>

                        <button
                            type="button"
                            class="ticket-rich-btn"
                            data-command="underline"
                            title="زیرخط"
                        >
                            <u>U</u>
                        </button>

                        <span class="ticket-rich-separator"></span>

                        <button
                            type="button"
                            class="ticket-rich-btn"
                            data-command="insertUnorderedList"
                            title="فهرست"
                        >
                            ☷
                        </button>

                        <button
                            type="button"
                            class="ticket-rich-btn"
                            data-command="insertOrderedList"
                            title="فهرست شماره‌دار"
                        >
                            ≣
                        </button>

                        <span class="ticket-rich-separator"></span>

                        <button
                            type="button"
                            class="ticket-rich-btn"
                            data-command="justifyRight"
                            title="راست‌چین"
                        >
                            ⇥
                        </button>

                        <button
                            type="button"
                            class="ticket-rich-btn"
                            data-command="justifyCenter"
                            title="وسط‌چین"
                        >
                            ↔
                        </button>

                        <button
                            type="button"
                            class="ticket-rich-btn"
                            data-command="justifyLeft"
                            title="چپ‌چین"
                        >
                            ⇤
                        </button>

                        <span class="ticket-rich-separator"></span>

                        <button
                            type="button"
                            class="ticket-rich-btn"
                            data-action="link"
                            title="افزودن لینک"
                        >
                            🔗
                        </button>

                        <button
                            type="button"
                            class="ticket-rich-btn"
                            data-action="emoji"
                            title="ایموجی"
                        >
                            😊
                        </button>

                        <span class="ticket-rich-separator"></span>

                        <button
                            type="button"
                            class="ticket-rich-btn"
                            data-command="undo"
                            title="بازگشت"
                        >
                            ↶
                        </button>

                        <button
                            type="button"
                            class="ticket-rich-btn"
                            data-command="redo"
                            title="جلو"
                        >
                            ↷
                        </button>

                        <button
                            type="button"
                            class="ticket-rich-btn"
                            data-command="removeFormat"
                            title="حذف قالب‌بندی"
                        >
                            Tx
                        </button>

                    </div>


                    <div
                        class="ticket-rich-emoji-panel"
                        id="ticketRichEmojiPanel"
                    >

                        <button type="button">😀</button>
                        <button type="button">😂</button>
                        <button type="button">😊</button>
                        <button type="button">😍</button>
                        <button type="button">😉</button>
                        <button type="button">👍</button>
                        <button type="button">👏</button>
                        <button type="button">🙏</button>
                        <button type="button">❤️</button>
                        <button type="button">✅</button>
                        <button type="button">❌</button>
                        <button type="button">⚠️</button>
                        <button type="button">💡</button>
                        <button type="button">📌</button>
                        <button type="button">🔧</button>
                        <button type="button">💻</button>

                    </div>


                    <div
                        id="ticketRichEditor"
                        class="ticket-rich-content"
                        contenteditable="true"
                        dir="rtl"
                        data-placeholder="پیام خود را برای واحد فناوری اطلاعات بنویسید..."
                    ></div>


                    <textarea
                        name="message"
                        id="ticketRichMessage"
                        class="ticket-rich-hidden"
                        tabindex="-1"
                        aria-hidden="true"
                    ></textarea>

                </div>


                <div class="reply-footer">


                    <span>
                        پیام شما برای پیگیری این درخواست ثبت خواهد شد.
                    </span>


                    <button
                        type="submit"
                        class="conversation-send-btn"
                    >
                        ارسال پیام
                    </button>


                </div>


            </form>


        </div>


    </div>


</div>



<style>


/* =========================================================
   Page
   ========================================================= */

.ticket-view-page {

    max-width: 1400px;

    margin: 0 auto;

}


/* =========================================================
   Header
   ========================================================= */

.ticket-view-header {

    display: flex;

    justify-content: space-between;

    align-items: flex-end;

    gap: 25px;

    margin-bottom: 22px;

}


.ticket-back {

    margin-bottom: 12px;

}


.ticket-back a {

    color: #64748b;

    text-decoration: none;

    font-size: 13px;

}


.ticket-back a:hover {

    color: #0b1f3a;

}


.ticket-view-header h1 {

    margin: 0 0 7px 0;

}


.ticket-view-header p {

    margin: 0;

    color: #718096;

    font-size: 14px;

}


.ticket-view-number {

    background: #ffffff;

    border: 1px solid #e4eaf1;

    border-radius: 12px;

    padding: 13px 18px;

    min-width: 190px;

    text-align: center;

    box-shadow: 0 3px 12px rgba(0,0,0,.035);

}


.ticket-view-number span {

    display: block;

    color: #718096;

    font-size: 11px;

    margin-bottom: 5px;

}


.ticket-view-number strong {

    display: block;

    color: #0b1f3a;

    font-size: 18px;

}


/* =========================================================
   Summary
   ========================================================= */

.ticket-view-summary {

    display: grid;

    grid-template-columns: repeat(4, 1fr);

    gap: 14px;

    margin-bottom: 20px;

}


.summary-item {

    background: #ffffff;

    border: 1px solid #e4eaf1;

    border-radius: 11px;

    padding: 15px;

    box-shadow: 0 3px 12px rgba(0,0,0,.03);

}


.summary-item > span {

    display: block;

    color: #718096;

    font-size: 11px;

    margin-bottom: 8px;

}


.summary-item > strong {

    color: #334155;

    font-size: 13px;

}


.summary-badge {

    display: inline-flex;

    padding: 5px 10px;

    border-radius: 18px;

}


/* =========================================================
   Main Cards
   ========================================================= */

.ticket-view-card {

    background: #ffffff;

    border: 1px solid #e4eaf1;

    border-radius: 14px;

    padding: 23px;

    margin-bottom: 18px;

    box-shadow: 0 3px 12px rgba(0,0,0,.035);

}


.ticket-card-title {

    display: flex;

    align-items: center;

    gap: 12px;

    padding-bottom: 17px;

    margin-bottom: 18px;

    border-bottom: 1px solid #edf1f5;

}


.ticket-card-icon {

    width: 40px;

    height: 40px;

    border-radius: 9px;

    background: #f4f7fb;

    display: flex;

    align-items: center;

    justify-content: center;

    font-size: 19px;

}


.ticket-card-title h2 {

    margin: 0 0 4px 0;

    color: #0b1f3a;

    font-size: 16px;

}


.ticket-card-title p {

    margin: 0;

    color: #94a3b8;

    font-size: 11px;

}


/* =========================================================
   Subject
   ========================================================= */

.ticket-subject-box {

    background: #f8fafc;

    border: 1px solid #edf1f5;

    border-radius: 9px;

    padding: 16px;

    color: #0b1f3a;

    font-size: 17px;

    font-weight: 700;

}


/* =========================================================
   Detail Grid
   ========================================================= */

.ticket-detail-grid {

    display: grid;

    grid-template-columns: repeat(3, 1fr);

    gap: 12px;

}


.detail-box {

    background: #f8fafc;

    border: 1px solid #edf1f5;

    border-radius: 9px;

    padding: 13px 14px;

    min-height: 66px;

}


.detail-box-wide {

    grid-column: span 2;

}


.detail-box span {

    display: block;

    color: #718096;

    font-size: 11px;

    margin-bottom: 7px;

}


.detail-box strong {

    display: block;

    color: #334155;

    font-size: 13px;

    line-height: 1.7;

}


/* =========================================================
   Description
   ========================================================= */
/* ============================================================
   Attachments
   ============================================================ */

.ticket-attachments-list {
    display: flex;
    flex-direction: column;
    gap: 12px;
    margin-top: 20px;
}

.ticket-attachment-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 20px;
    padding: 16px 18px;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
}

.ticket-attachment-info {
    display: flex;
    align-items: center;
    gap: 14px;
    min-width: 0;
    flex: 1;
}

.ticket-attachment-icon {
    width: 44px;
    height: 44px;
    min-width: 44px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #eef2ff;
    border-radius: 10px;
    font-size: 22px;
}

.ticket-attachment-details {
    min-width: 0;
    flex: 1;
}

.ticket-attachment-name {
    display: inline-block;
    max-width: 100%;
    color: #1d4ed8;
    font-size: 15px;
    font-weight: 700;
    text-decoration: none;
    word-break: break-word;
}

.ticket-attachment-name:hover {
    text-decoration: underline;
}

.ticket-attachment-meta {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 8px 18px;
    margin-top: 7px;
    color: #64748b;
    font-size: 12px;
}

.ticket-attachment-meta span {
    white-space: nowrap;
}

.ticket-attachment-download {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    padding: 9px 15px;
    background: #2563eb;
    color: #ffffff;
    border-radius: 8px;
    font-size: 13px;
    font-weight: 600;
    text-decoration: none;
    transition: opacity 0.2s ease;
}

.ticket-attachment-download:hover {
    opacity: 0.88;
    color: #ffffff;
}

.ticket-no-attachments {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-top: 18px;
    padding: 14px 16px;
    background: #f8fafc;
    border: 1px dashed #cbd5e1;
    border-radius: 10px;
    color: #64748b;
    font-size: 13px;
}


/* Mobile */

@media (max-width: 700px) {

    .ticket-attachment-item {
        flex-direction: column;
        align-items: stretch;
    }

    .ticket-attachment-download {
        width: 100%;
    }

    .ticket-attachment-meta {
        gap: 6px 12px;
    }

}


.ticket-description {

    background: #f8fafc;

    border: 1px solid #edf1f5;

    border-radius: 9px;

    padding: 18px;

    color: #334155;

    font-size: 14px;

    line-height: 2.1;

    min-height: 100px;

}


/* =========================================================
   Conversation
   ========================================================= */

.ticket-conversation-card {

    scroll-margin-top: 30px;

}


.conversation-empty {

    background: #f8fafc;

    border: 1px dashed #d7e0ea;

    border-radius: 10px;

    padding: 28px;

    text-align: center;

    margin-bottom: 22px;

}


.conversation-empty-icon {

    font-size: 30px;

    margin-bottom: 10px;

}


.conversation-empty strong {

    display: block;

    color: #334155;

    font-size: 14px;

    margin-bottom: 8px;

}


.conversation-empty span {

    display: block;

    color: #718096;

    font-size: 12px;

}


.conversation-list {

    display: flex;

    flex-direction: column;

    gap: 14px;

    margin-bottom: 24px;

}


.conversation-message {

    max-width: 78%;

    border-radius: 12px;

    padding: 13px 15px;

    border: 1px solid #e5eaf0;

}


.message-mine {

    align-self: flex-start;

    background: #eef6ff;

    border-color: #d7e8fb;

}


.message-other {

    align-self: flex-end;

    background: #f8fafc;

}


.message-header {

    display: flex;

    justify-content: space-between;

    align-items: center;

    gap: 20px;

    margin-bottom: 8px;

}


.message-header strong {

    color: #0b1f3a;

    font-size: 12px;

}


.message-header span {

    color: #94a3b8;

    font-size: 10px;

}


.message-body {

    color: #334155;

    font-size: 13px;

    line-height: 2;

    white-space: normal;

}


/* =========================================================
   Reply
   ========================================================= */

.conversation-reply {

    border-top: 1px solid #edf1f5;

    padding-top: 20px;

}


.reply-title {

    color: #0b1f3a;

    font-size: 14px;

    font-weight: 700;

    margin-bottom: 10px;

}


.message-error {

    background: #fff1f1;

    border: 1px solid #e6b8b8;

    color: #c00000;

    border-radius: 8px;

    padding: 10px 13px;

    font-size: 12px;

    margin-bottom: 10px;

}


.conversation-textarea {

    display: block;

    width: 100%;

    min-height: 120px;

    box-sizing: border-box;

    resize: vertical;

    border: 1px solid #d9e1ea;

    border-radius: 9px;

    background: #ffffff;

    padding: 13px 14px;

    font-family: inherit;

    font-size: 13px;

    line-height: 1.9;

    color: #334155;

    outline: none;

}


.conversation-textarea:focus {

    border-color: #8ba9c7;

    box-shadow: 0 0 0 3px rgba(59,130,246,.08);

}


.reply-footer {

    display: flex;

    justify-content: space-between;

    align-items: center;

    gap: 15px;

    margin-top: 10px;

}


.reply-footer span {

    color: #94a3b8;

    font-size: 11px;

}


.conversation-send-btn {

    border: 0;

    border-radius: 8px;

    padding: 10px 22px;

    background: #0b1f3a;

    color: #ffffff;

    font-family: inherit;

    font-size: 13px;

    cursor: pointer;

}


.conversation-send-btn:hover {

    background: #16365f;

}


/* =========================================================
   Status / Priority
   ========================================================= */

.ticket-status-new {

    background: #eaf2ff;

    color: #1d4ed8;

}


.ticket-status-assigned {

    background: #eef2ff;

    color: #4338ca;

}


.ticket-status-in_progress {

    background: #fff7db;

    color: #9a6700;

}


.ticket-status-waiting_user {

    background: #e8f5ff;

    color: #0369a1;

}


.ticket-status-resolved {

    background: #eaf8ef;

    color: #15803d;

}


.ticket-status-closed {

    background: #e8f5ed;

    color: #166534;

}


.ticket-status-cancelled {

    background: #f3f4f6;

    color: #6b7280;

}


.ticket-priority-normal {

    background: #f3f4f6;

    color: #4b5563;

}


.ticket-priority-important {

    background: #fff7db;

    color: #92400e;

}


.ticket-priority-urgent {

    background: #fff0e6;

    color: #c2410c;

}


.ticket-priority-critical {

    background: #ffe8e8;

    color: #b91c1c;

}


/* =========================================================
   Responsive
   ========================================================= */

@media (max-width: 900px) {

    .ticket-view-summary {

        grid-template-columns: repeat(2, 1fr);

    }

    .ticket-detail-grid {

        grid-template-columns: repeat(2, 1fr);

    }

    .conversation-message {

        max-width: 90%;

    }

}


@media (max-width: 650px) {

    .ticket-view-header {

        flex-direction: column;

        align-items: stretch;

    }

    .ticket-view-number {

        width: auto;

    }

    .ticket-view-summary {

        grid-template-columns: 1fr;

    }

    .ticket-detail-grid {

        grid-template-columns: 1fr;

    }

    .detail-box-wide {

        grid-column: span 1;

    }

    .ticket-view-card {

        padding: 17px;

    }

    .conversation-message {

        max-width: 100%;

    }

    .reply-footer {

        flex-direction: column;

        align-items: stretch;

    }

    .conversation-send-btn {

        width: 100%;

    }

}



/*
 * ============================================================
 * Ticket Rich Text Editor
 * ============================================================
 */

.ticket-rich-editor {
    position: relative;
    width: 100%;
    box-sizing: border-box;
}

.ticket-rich-toolbar {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 5px;
    padding: 8px;
    border: 1px solid #d9e1ea;
    border-bottom: 0;
    border-radius: 9px 9px 0 0;
    background: #f8fafc;
}

.ticket-rich-btn {
    min-width: 34px;
    height: 32px;
    padding: 0 8px;
    border: 1px solid #d9e1ea;
    border-radius: 6px;
    background: #ffffff;
    color: #334155;
    cursor: pointer;
    font-family: inherit;
    font-size: 13px;
    line-height: 30px;
}

.ticket-rich-btn:hover {
    background: #eef4f9;
    border-color: #b8c8d8;
}

.ticket-rich-btn:active {
    background: #e5edf5;
}

.ticket-rich-separator {
    width: 1px;
    height: 24px;
    background: #d7e0e8;
    margin: 0 2px;
}

.ticket-rich-content {
    display: block;
    width: 100%;
    min-height: 145px;
    box-sizing: border-box;
    padding: 13px 14px;
    border: 1px solid #d9e1ea;
    border-radius: 0 0 9px 9px;
    background: #ffffff;
    color: #334155;
    font-family: inherit;
    font-size: 13px;
    line-height: 1.9;
    outline: none;
    overflow-y: auto;
}

.ticket-rich-content:focus {
    border-color: #8ba9c7;
    box-shadow: 0 0 0 3px rgba(59,130,246,.08);
}

.ticket-rich-content:empty:before {
    content: attr(data-placeholder);
    color: #94a3b8;
    pointer-events: none;
}

.ticket-rich-content p {
    margin: 0 0 8px;
}

.ticket-rich-content ul,
.ticket-rich-content ol {
    margin-top: 6px;
    margin-bottom: 8px;
    padding-right: 24px;
}

.ticket-rich-content a {
    color: #2563eb;
    text-decoration: underline;
}

.ticket-rich-hidden {
    display: none !important;
}

.ticket-rich-emoji-panel {
    display: none;
    position: absolute;
    z-index: 50;
    top: 50px;
    right: 8px;
    width: 245px;
    padding: 9px;
    border: 1px solid #d9e1ea;
    border-radius: 8px;
    background: #ffffff;
    box-shadow: 0 8px 25px rgba(15,23,42,.14);
}

.ticket-rich-emoji-panel.active {
    display: grid;
    grid-template-columns: repeat(8, 1fr);
    gap: 4px;
}

.ticket-rich-emoji-panel button {
    width: 27px;
    height: 27px;
    padding: 0;
    border: 0;
    border-radius: 5px;
    background: transparent;
    cursor: pointer;
    font-size: 17px;
}

.ticket-rich-emoji-panel button:hover {
    background: #eef4f9;
}

.message-body p {
    margin-top: 0;
    margin-bottom: 8px;
}

.message-body ul,
.message-body ol {
    margin-top: 6px;
    margin-bottom: 8px;
    padding-right: 24px;
}

.message-body a {
    color: #2563eb;
    text-decoration: underline;
}


</style>




<script>
(function () {

    var editor = document.getElementById(
        'ticketRichEditor'
    );

    var hidden = document.getElementById(
        'ticketRichMessage'
    );

    var emojiPanel = document.getElementById(
        'ticketRichEmojiPanel'
    );

    if (!editor || !hidden) {
        return;
    }


    function syncMessage() {

        hidden.value = editor.innerHTML;

    }


    function focusEditor() {

        editor.focus();

    }


    /*
     * Formatting buttons
     */
    var commandButtons =
        document.querySelectorAll(
            '.ticket-rich-btn[data-command]'
        );

    commandButtons.forEach(function (button) {

        button.addEventListener(
            'mousedown',
            function (event) {

                event.preventDefault();

            }
        );

        button.addEventListener(
            'click',
            function () {

                var command =
                    button.getAttribute(
                        'data-command'
                    );

                focusEditor();

                try {

                    document.execCommand(
                        command,
                        false,
                        null
                    );

                } catch (error) {

                    console.log(error);

                }

                syncMessage();

            }
        );

    });


    /*
     * Link / Emoji buttons
     */
    var actionButtons =
        document.querySelectorAll(
            '.ticket-rich-btn[data-action]'
        );

    actionButtons.forEach(function (button) {

        button.addEventListener(
            'mousedown',
            function (event) {

                event.preventDefault();

            }
        );

        button.addEventListener(
            'click',
            function () {

                var action =
                    button.getAttribute(
                        'data-action'
                    );


                /*
                 * Link
                 */
                if (action === 'link') {

                    focusEditor();

                    var url =
                        window.prompt(
                            'آدرس لینک را وارد کنید:',
                            'https://'
                        );

                    if (
                        url &&
                        url.trim() !== ''
                    ) {

                        var cleanUrl =
                            url.trim();

                        if (
                            !/^https?:\/\//i.test(
                                cleanUrl
                            ) &&
                            !/^mailto:/i.test(
                                cleanUrl
                            ) &&
                            !/^tel:/i.test(
                                cleanUrl
                            )
                        ) {

                            cleanUrl =
                                'https://' +
                                cleanUrl;

                        }

                        document.execCommand(
                            'createLink',
                            false,
                            cleanUrl
                        );

                        syncMessage();

                    }

                }


                /*
                 * Emoji
                 */
                if (action === 'emoji') {

                    if (!emojiPanel) {
                        return;
                    }

                    emojiPanel.classList.toggle(
                        'active'
                    );

                }

            }
        );

    });


    /*
     * Emoji buttons
     */
    if (emojiPanel) {

        var emojiButtons =
            emojiPanel.querySelectorAll(
                'button'
            );

        emojiButtons.forEach(function (button) {

            button.addEventListener(
                'mousedown',
                function (event) {

                    event.preventDefault();

                }
            );

            button.addEventListener(
                'click',
                function () {

                    focusEditor();

                    document.execCommand(
                        'insertText',
                        false,
                        button.textContent
                    );

                    syncMessage();

                    emojiPanel.classList.remove(
                        'active'
                    );

                }
            );

        });

    }


    /*
     * Sync editor with hidden textarea
     */
    editor.addEventListener(
        'input',
        syncMessage
    );

    editor.addEventListener(
        'blur',
        syncMessage
    );


    /*
     * Validate form before submit
     */
    var form = editor.closest('form');

    if (form) {

        form.addEventListener(
            'submit',
            function (event) {

                syncMessage();

                var plain =
                    editor.innerText
                    .replace(/\u00a0/g, ' ')
                    .trim();

                if (plain === '') {

                    event.preventDefault();

                    alert(
                        'لطفاً متن پیام را وارد کنید.'
                    );

                    focusEditor();

                    return false;

                }

            }
        );

    }


    /*
     * Close emoji panel when clicking outside
     */
    document.addEventListener(
        'click',
        function (event) {

            if (!emojiPanel) {
                return;
            }

            if (
                !emojiPanel.contains(event.target) &&
                !event.target.closest(
                    '[data-action="emoji"]'
                )
            ) {

                emojiPanel.classList.remove(
                    'active'
                );

            }

        }
    );


})();
</script>


<?php

require_once "includes/footer.php";

?>
