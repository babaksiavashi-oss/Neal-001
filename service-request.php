<?php

$portal_mode = true;

require_once "auth/auth.php";

check_user_login();

$page_title = "درخواست خدمات IT";




/*
 * =========================================================
 * تنظیمات
 * =========================================================
 */

date_default_timezone_set('Asia/Tehran');

$db_file = __DIR__ . "/data/neal.db";

$errors = [];

$form = [
    'fullname'       => '',
    'personnel_code' => '',
    'department'     => '',
    'phone'          => '',
    'priority'       => '',
    'subject'        => '',
    'description'    => '',
    'asset'          => '',
    'hostname'       => '',
    'ip_address'     => '',
    'location'       => '',
    'related_system' => ''
];

$selected_types = [];


/*
 * =========================================================
 * تبدیل تاریخ میلادی به شمسی
 * =========================================================
 */

function gregorian_to_jalali($gy, $gm, $gd)
{
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

    $gy2 = ($gm > 2) ? ($gy + 1) : $gy;

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

    return [
        $jy,
        $jm,
        $jd
    ];
}


function current_jalali_year()
{
    $result = gregorian_to_jalali(
        (int)date('Y'),
        (int)date('n'),
        (int)date('j')
    );

    return $result[0];
}


/*
 * =========================================================
 * اطلاعات مرجع
 * =========================================================
 */

$db = new PDO(
    "sqlite:" . $db_file,
    null,
    null,
    [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]
);

/*
 * =========================================================
 * کاربر جاری
 * =========================================================
 */

$current_user_id = (int)($_SESSION['user_id'] ?? 0);

$user_stmt = $db->prepare(
    "SELECT
        id,
        username,
        fullname,
        personnel_code,
        department,
        phone
     FROM users
     WHERE id = ?
       AND is_active = 1
     LIMIT 1"
);
$user_stmt->execute([$current_user_id]);
$current_user = $user_stmt->fetch();

if (!$current_user) {
    logout();
    header("Location: /neal/login.php");
    exit;
}

$form['fullname'] = $current_user['fullname'];
$form['personnel_code'] = $current_user['personnel_code'];
$form['department'] = $current_user['department'];
$form['phone'] = $current_user['phone'] ?? '';

$departments = $db
    ->query(
        "SELECT code, name
         FROM departments
         WHERE is_active = 1
         ORDER BY id"
    )
    ->fetchAll();

$request_types = $db
    ->query(
        "SELECT code, name
         FROM request_types
         WHERE is_active = 1
         ORDER BY id"
    )
    ->fetchAll();


/*
 * =========================================================
 * ثبت درخواست
 * =========================================================
 */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    foreach ([
        'priority',
        'subject',
        'description',
        'asset',
        'hostname',
        'ip_address',
        'location',
        'related_system'
    ] as $key) {

        if (isset($_POST[$key])) {

            $form[$key] = trim((string)$_POST[$key]);

        }

    }

    /* اطلاعات هویتی همیشه از حساب کاربر خوانده می‌شود. */
    $form['fullname'] = $current_user['fullname'];
    $form['personnel_code'] = $current_user['personnel_code'];
    $form['department'] = $current_user['department'];
    $form['phone'] = $current_user['phone'] ?? '';


    if (isset($_POST['request_type']) && is_array($_POST['request_type'])) {

        $selected_types = array_values(
            array_unique(
                array_map(
                    'trim',
                    $_POST['request_type']
                )
            )
        );

    }


    /*
     * اعتبارسنجی
     */

    if ($form['fullname'] === '') {

        $errors[] = 'نام و نام خانوادگی را وارد کنید.';

    }

    if ($form['personnel_code'] === '') {

        $errors[] = 'کد پرسنلی را وارد کنید.';

    }

    $valid_departments = array_column(
        $departments,
        'code'
    );

    if (
        $form['department'] === ''
        ||
        !in_array(
            $form['department'],
            $valid_departments,
            true
        )
    ) {

        $errors[] = 'واحد / دپارتمان را انتخاب کنید.';

    }


    $valid_type_codes = array_column(
        $request_types,
        'code'
    );

    $selected_types = array_values(
        array_intersect(
            $selected_types,
            $valid_type_codes
        )
    );

    if (count($selected_types) === 0) {

        $errors[] = 'حداقل یک نوع درخواست را انتخاب کنید.';

    }


    $valid_priorities = [
        'normal',
        'important',
        'urgent',
        'critical'
    ];

    if (
        !in_array(
            $form['priority'],
            $valid_priorities,
            true
        )
    ) {

        $errors[] = 'اولویت درخواست را انتخاب کنید.';

    }

    if ($form['subject'] === '') {

        $errors[] = 'موضوع درخواست را وارد کنید.';

    }

    if ($form['description'] === '') {

        $errors[] = 'شرح کامل درخواست را وارد کنید.';

    }


    /*
     * بررسی فایل پیوست
     */

    $attachment = null;

    if (
        isset($_FILES['attachment'])
        &&
        $_FILES['attachment']['error'] !== UPLOAD_ERR_NO_FILE
    ) {

        if (
            $_FILES['attachment']['error']
            !== UPLOAD_ERR_OK
        ) {

            $errors[] = 'در دریافت فایل پیوست خطایی رخ داد.';

        } else {

            $max_size = 10 * 1024 * 1024;

            if ($_FILES['attachment']['size'] > $max_size) {

                $errors[] = 'حجم فایل پیوست نباید بیشتر از 10 مگابایت باشد.';

            }

            $allowed_extensions = [
                'pdf',
                'doc',
                'docx',
                'txt',
                'zip'
            ];

            $original_name =
                basename(
                    $_FILES['attachment']['name']
                );

            $extension =
                strtolower(
                    pathinfo(
                        $original_name,
                        PATHINFO_EXTENSION
                    )
                );

            if (
                $extension === ''
                ||
                !in_array(
                    $extension,
                    $allowed_extensions,
                    true
                )
            ) {

                $errors[] =
                    'فرمت فایل پیوست مجاز نیست.';

            }

            if (count($errors) === 0) {

                $attachment = [
                    'original_name' => $original_name,
                    'tmp_name'      => $_FILES['attachment']['tmp_name'],
                    'mime_type'     => $_FILES['attachment']['type'],
                    'file_size'     => (int)$_FILES['attachment']['size'],
                    'extension'     => $extension
                ];

            }

        }

    }


    /*
     * اگر خطایی نداریم، ثبت واقعی درخواست
     */

    if (count($errors) === 0) {

        $transaction_started = false;
        $stored_file = null;

        try {

            /*
             * سال شمسی جاری
             */

            $jalali_year = current_jalali_year();


            /*
             * جلوگیری از تولید شماره تکراری
             *
             * BEGIN IMMEDIATE باعث می‌شود دو درخواست
             * همزمان نتوانند یک شماره دریافت کنند.
             */

            $db->exec('BEGIN IMMEDIATE');

            $transaction_started = true;


            /*
             * شمارنده سال
             */

            $stmt = $db->prepare(
                "SELECT last_number
                 FROM ticket_counters
                 WHERE jalali_year = ?"
            );

            $stmt->execute([
                $jalali_year
            ]);

            $counter = $stmt->fetch();


            if ($counter) {

                $yearly_number =
                    ((int)$counter['last_number']) + 1;

                $stmt = $db->prepare(
                    "UPDATE ticket_counters
                     SET last_number = ?
                     WHERE jalali_year = ?"
                );

                $stmt->execute([
                    $yearly_number,
                    $jalali_year
                ]);

            } else {

                $yearly_number = 1;

                $stmt = $db->prepare(
                    "INSERT INTO ticket_counters
                     (jalali_year, last_number)
                     VALUES (?, ?)"
                );

                $stmt->execute([
                    $jalali_year,
                    $yearly_number
                ]);

            }


            /*
             * شماره پیگیری
             *
             * مثال:
             * 1405-000001
             */

            $tracking_number =
                $jalali_year
                . '-'
                . str_pad(
                    (string)$yearly_number,
                    6,
                    '0',
                    STR_PAD_LEFT
                );


            /*
             * ثبت درخواست اصلی
             */

            $stmt = $db->prepare(
                "INSERT INTO service_requests (
                    tracking_number,
                    jalali_year,
                    yearly_number,
                    requester_admin_id,
                    requester_username,
                    fullname,
                    personnel_code,
                    department,
                    phone,
                    priority,
                    subject,
                    description,
                    asset,
                    hostname,
                    ip_address,
                    location,
                    related_system,
                    status
                )
                VALUES (
                    ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
                )"
            );

            $stmt->execute([

                $tracking_number,

                $jalali_year,

                $yearly_number,

                null,

                $current_user['username'],

                $form['fullname'],

                $form['personnel_code'],

                $form['department'],

                $form['phone'],

                $form['priority'],

                $form['subject'],

                $form['description'],

                $form['asset'],

                $form['hostname'],

                $form['ip_address'],

                $form['location'],

                $form['related_system'],

                'new'

            ]);


            $request_id =
                (int)$db->lastInsertId();


            /*
             * ثبت نوع / انواع درخواست
             */

            $type_stmt = $db->prepare(
                "SELECT id
                 FROM request_types
                 WHERE code = ?"
            );

            $link_stmt = $db->prepare(
                "INSERT INTO service_request_types
                 (request_id, request_type_id)
                 VALUES (?, ?)"
            );


            foreach ($selected_types as $type_code) {

                $type_stmt->execute([
                    $type_code
                ]);

                $type = $type_stmt->fetch();

                if (!$type) {

                    throw new RuntimeException(
                        'نوع درخواست معتبر نیست.'
                    );

                }

                $link_stmt->execute([
                    $request_id,
                    $type['id']
                ]);

            }


            /*
             * ثبت اولین وضعیت
             */

            $stmt = $db->prepare(
                "INSERT INTO ticket_status_history (
                    request_id,
                    old_status,
                    new_status,
                    changed_by,
                    note
                )
                VALUES (?, ?, ?, ?, ?)"
            );

            $stmt->execute([

                $request_id,

                null,

                'new',

                null,

                'درخواست توسط کاربر ثبت شد.'

            ]);


            /*
             * ذخیره فایل پیوست
             */

            if ($attachment !== null) {

                $storage_dir =
                    __DIR__
                    . "/data/ticket_attachments";

                if (!is_dir($storage_dir)) {

                    if (
                        !mkdir(
                            $storage_dir,
                            0770,
                            true
                        )
                    ) {

                        throw new RuntimeException(
                            'امکان ایجاد پوشه پیوست وجود ندارد.'
                        );

                    }

                }


                $stored_name =
                    $request_id
                    . '_'
                    . bin2hex(
                        random_bytes(8)
                    )
                    . '.'
                    . $attachment['extension'];


                $destination =
                    $storage_dir
                    . '/'
                    . $stored_name;


                if (
                    !move_uploaded_file(
                        $attachment['tmp_name'],
                        $destination
                    )
                ) {

                    throw new RuntimeException(
                        'ذخیره فایل پیوست انجام نشد.'
                    );

                }


                $stored_file = $destination;


                $relative_path =
                    "data/ticket_attachments/"
                    . $stored_name;


                $stmt = $db->prepare(
                    "INSERT INTO ticket_attachments (
                        request_id,
                        original_name,
                        stored_name,
                        file_path,
                        mime_type,
                        file_size,
                        uploaded_by
                    )
                    VALUES (?, ?, ?, ?, ?, ?, ?)"
                );

                $stmt->execute([

                    $request_id,

                    $attachment['original_name'],

                    $stored_name,

                    $relative_path,

                    $attachment['mime_type'],

                    $attachment['file_size'],

                    null

                ]);

            }


            /*
             * پایان تراکنش
             */

            $db->commit();

            $transaction_started = false;


            /*
             * انتقال به صفحه موفقیت
             */

            header(
                "Location: /neal/ticket-success.php?id="
                . $request_id
            );

            exit;


        } catch (Throwable $e) {

            if ($stored_file !== null && is_file($stored_file)) {

                @unlink($stored_file);

            }

            if ($transaction_started) {

                $db->rollBack();

            }

            error_log(
                "NEAL Ticket Error: "
                . $e->getMessage()
            );

            $errors[] =
                'ثبت درخواست انجام نشد. لطفاً دوباره تلاش کنید.';

        }

    }

}

require_once "includes/header.php";

?>

<div class="card service-request-page">

    <div class="service-request-header">

        <div>

            <h1>🎫 درخواست خدمات فناوری اطلاعات</h1>

            <p>
                برای ثبت درخواست پشتیبانی، اطلاعات زیر را تکمیل نمایید.
            </p>

        </div>

    </div>

        <div class="required-fields-notice">
    <span>⚠</span>
    پر کردن فیلدهای ستاره‌دار الزامی است.
</div>


    <?php if (count($errors) > 0): ?>

        <div class="ticket-errors">

            <strong>لطفاً موارد زیر را بررسی کنید:</strong>

            <ul>

                <?php foreach ($errors as $error): ?>

                    <li>
                        <?php echo htmlspecialchars($error); ?>
                    </li>

                <?php endforeach; ?>

            </ul>

        </div>

    <?php endif; ?>


    <form
        class="service-request-form"
        method="post"
        action=""
        enctype="multipart/form-data"
    >


        <!-- =====================================================
             اطلاعات درخواست کننده
        ====================================================== -->

        <div class="form-section">

            <h2>۱. اطلاعات درخواست‌کننده</h2>

            <div class="form-grid">

                <div class="form-group">

    <label>
        نام و نام خانوادگی
        <span class="required-star">*</span>
    </label>

    <input
        type="text"
        name="fullname"
        required
        readonly
        value="<?php echo htmlspecialchars($form['fullname']); ?>"
        placeholder="نام و نام خانوادگی"
    >

</div>

                <div class="form-group">

                    <label>
                        کد پرسنلی

                <span class="required-star">*</span>
                 </label>

                    <input
                        type="text"
                        required
                        name="personnel_code"
                        readonly
                        value="<?php echo htmlspecialchars($form['personnel_code']); ?>"
                        placeholder="کد پرسنلی"
                    >

                </div>


                <div class="form-group">

                    <label>
                        واحد / دپارتمان
<span class="required-star">*</span>
 </label>

                    <select
                        name="department"
                        required
                        disabled
                        class="form-select"
                    >

                        <option
                            value=""
                            disabled
                            <?php echo $form['department'] === '' ? 'selected' : ''; ?>
                        >
                            دپارتمان خود را انتخاب کنید
                        </option>

                        <?php foreach ($departments as $department): ?>

                            <option
                                value="<?php echo htmlspecialchars($department['code']); ?>"
                                <?php echo $form['department'] === $department['code'] ? 'selected' : ''; ?>
                            >
                                <?php echo htmlspecialchars($department['name']); ?>
                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>


                <div class="form-group">

                    <label>
                        شماره تماس
                    </label>

                    <input
                        type="text"
                        name="phone"
                        readonly
                        value="<?php echo htmlspecialchars($form['phone']); ?>"
                        placeholder="شماره داخلی / موبایل"
                    >

                </div>

            </div>

        </div>


        <!-- =====================================================
             نوع درخواست
        ====================================================== -->

        <div class="form-section">

            <h2>۲. نوع درخواست</h2>

            <div class="request-type-grid">

                <?php

                $icons = [
                    'hardware'  => '🖥️',
                    'software'  => '💿',
                    'install'   => '⚙️',
                    'access'    => '🔐',
                    'network'   => '🌐',
                    'equipment' => '🔧',
                    'security'  => '🛡️',
                    'backup'    => '💾',
                    'other'     => '📌'
                ];

                ?>

                <?php foreach ($request_types as $type): ?>

                    <label class="request-type">

                        <input
                            type="checkbox"
                            name="request_type[]"
                            value="<?php echo htmlspecialchars($type['code']); ?>"
                            <?php echo in_array($type['code'], $selected_types, true) ? 'checked' : ''; ?>
                        >

                        <span>
                            <?php echo $icons[$type['code']] ?? '📌'; ?>
                        </span>

                        <strong>
                            <?php echo htmlspecialchars($type['name']); ?>
                        </strong>

                    </label>

                <?php endforeach; ?>

            </div>

        </div>


        <!-- =====================================================
             اولویت
        ====================================================== -->

        <div class="form-section">

            <h2>۳. اولویت درخواست</h2>

            <div class="priority-grid">

                <label class="priority-option priority-normal">

                    <input
                        type="radio"
                        name="priority"
                        value="normal"
                        <?php echo $form['priority'] === 'normal' ? 'checked' : ''; ?>
                    >

                    <span class="priority-box">

                        <span class="priority-icon">
                            🟢
                        </span>

                        <span class="priority-content">

                            <strong>
                                عادی
                            </strong>

                            <small>
                                بدون اختلال جدی
                            </small>

                        </span>

                    </span>

                </label>


                <label class="priority-option priority-important">

                    <input
                        type="radio"
                        name="priority"
                        value="important"
                        <?php echo $form['priority'] === 'important' ? 'checked' : ''; ?>
                    >

                    <span class="priority-box">

                        <span class="priority-icon">
                            🟠
                        </span>

                        <span class="priority-content">

                            <strong>
                                مهم
                            </strong>

                            <small>
                                نیازمند رسیدگی
                            </small>

                        </span>

                    </span>

                </label>


                <label class="priority-option priority-urgent">

                    <input
                        type="radio"
                        name="priority"
                        value="urgent"
                        <?php echo $form['priority'] === 'urgent' ? 'checked' : ''; ?>
                    >

                    <span class="priority-box">

                        <span class="priority-icon">
                            🔴
                        </span>

                        <span class="priority-content">

                            <strong>
                                فوری
                            </strong>

                            <small>
                                اختلال در کار
                            </small>

                        </span>

                    </span>

                </label>


                <label class="priority-option priority-critical">

                    <input
                        type="radio"
                        name="priority"
                        value="critical"
                        <?php echo $form['priority'] === 'critical' ? 'checked' : ''; ?>
                    >

                    <span class="priority-box">

                        <span class="priority-icon">
                            🚨
                        </span>

                        <span class="priority-content">

                            <strong>
                                بحرانی
                            </strong>

                            <small>
                                توقف فعالیت
                            </small>

                        </span>

                    </span>

                </label>

            </div>

        </div>


        <!-- =====================================================
             شرح درخواست
        ====================================================== -->

        <div class="form-section">

            <h2>۴. شرح درخواست</h2>

            <div class="form-group">

    <label>
        موضوع درخواست
        <span class="required-star">*</span>
    </label>

    <input
        type="text"
        name="subject"
        value="<?php echo htmlspecialchars($form['subject']); ?>"
        required
        placeholder="موضوع درخواست را وارد کنید"
    >

</div>

            </div>


            <div class="form-group">

    <label>
        شرح کامل درخواست
        <span class="required-star">*</span>
    </label>

    <textarea
        name="description"
        rows="7"
        required
        placeholder="لطفاً مشکل یا درخواست خود را با جزئیات توضیح دهید..."
    ><?php echo htmlspecialchars($form['description']); ?></textarea>

</div>

        </div>


        <!-- =====================================================
             اطلاعات فنی
        ====================================================== -->

        <div class="form-section">

            <h2>۵. اطلاعات فنی</h2>

            <div class="form-grid">

                <div class="form-group">

                    <label>
                        نام دستگاه / کد اموال
                    </label>

                    <input
                        type="text"
                        name="asset"
                        value="<?php echo htmlspecialchars($form['asset']); ?>"
                        placeholder="در صورت وجود"
                    >

                </div>


                <div class="form-group">

                    <label>
                        Hostname
                    </label>

                    <input
                        type="text"
                        name="hostname"
                        value="<?php echo htmlspecialchars($form['hostname']); ?>"
                        placeholder="نام سیستم"
                    >

                </div>


                <div class="form-group">

                    <label>
                        شماره IP
                    </label>

                    <input
                        type="text"
                        name="ip_address"
                        value="<?php echo htmlspecialchars($form['ip_address']); ?>"
                        placeholder="مثلاً 192.168.7.25"
                    >

                </div>


                <div class="form-group">

                    <label>
                        محل استقرار
                    </label>

                    <input
                        type="text"
                        name="location"
                        value="<?php echo htmlspecialchars($form['location']); ?>"
                        placeholder="مثلاً واحد مالی / اتاق ۲"
                    >

                </div>


                <div class="form-group form-group-full">

                    <label>
                        نرم‌افزار / سامانه مرتبط
                    </label>

                    <input
                        type="text"
                        name="related_system"
                        value="<?php echo htmlspecialchars($form['related_system']); ?>"
                        placeholder="در صورت وجود"
                    >

                </div>

            </div>

        </div>


        <!-- =====================================================
             پیوست
        ====================================================== -->

        <div class="form-section">

            <h2>۶. پیوست</h2>

            <div class="attachment-box">

                <label for="attachment">
                    📎 انتخاب فایل
                </label>

                <input
                    id="attachment"
                    type="file"
                    name="attachment"
                >

                <small>
                    حداکثر حجم فایل: ۱۰ مگابایت
                    |
                    فرمت‌های مجاز:
                    PDF، DOC، DOCX، TXT و ZIP
                </small>

            </div>

        </div>


        <!-- =====================================================
             دکمه ها
        ====================================================== -->

        <div class="form-actions">

            <button
                type="button"
                class="btn-cancel"
                onclick="window.history.back();"
            >
                انصراف
            </button>

            <button
                type="submit"
                class="btn-submit"
            >
                🎫 ثبت درخواست
            </button>

        </div>


        <div class="form-notice">

            پس از ثبت درخواست، شماره پیگیری اختصاصی برای شما ایجاد خواهد شد.

        </div>

    </form>

</div>


<style>

/* =========================================================
   Service Request
   ========================================================= */

.service-request-page {
    max-width: 1100px;
    margin: 0 auto;
}


.service-request-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 24px 26px;
    margin-bottom: 22px;
    background: linear-gradient(
        135deg,
        #f8fafc,
        #eef4fa
    );
    border: 1px solid #e4e7ec;
    border-radius: 12px;
}


.service-request-header h1 {
    margin: 0;
    color: #0b1f3a;
    font-size: 23px;
}


.service-request-header p {
    margin: 8px 0 0;
    color: #667085;
    font-size: 13px;
}


/* =========================================================
   Errors
   ========================================================= */

.ticket-errors {
    margin-bottom: 20px;
    padding: 16px 20px;
    background: #fff4f4;
    border: 1px solid #f3b7b7;
    border-radius: 10px;
    color: #b42318;
    font-size: 13px;
}


.ticket-errors ul {
    margin: 8px 0 0;
    padding-right: 20px;
}


/* =========================================================
   Sections
   ========================================================= */

.form-section {
    margin-bottom: 22px;
    padding: 24px;
    background: #ffffff;
    border: 1px solid #eaecf0;
    border-radius: 12px;
}


.form-section h2 {
    margin: 0 0 20px;
    padding-bottom: 12px;
    border-bottom: 1px solid #eaecf0;
    color: #0b1f3a;
    font-size: 16px;
}


/* =========================================================
   Form Grid
   ========================================================= */

.form-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 18px;
}


.form-group-full {
    grid-column: 1 / -1;
}


.form-group label {
    display: block;
    margin-bottom: 7px;
    color: #344054;
    font-size: 12px;
    font-weight: 600;
}


.form-group input,
.form-group textarea,
.form-select {
    width: 100%;
    padding: 12px 13px;
    border: 1px solid #d0d5dd;
    border-radius: 8px;
    background: #ffffff;
    color: #101828;
    font-family: Tahoma, Arial, sans-serif;
    font-size: 13px;
    outline: none;
    transition:
        border-color .15s ease,
        box-shadow .15s ease;
}


.form-group textarea {
    resize: vertical;
    min-height: 150px;
}


.form-group input:focus,
.form-group textarea:focus,
.form-select:focus {
    border-color: #0b1f3a;
    box-shadow:
        0 0 0 3px rgba(11, 31, 58, 0.08);
}


.form-group input[readonly],
.form-select:disabled {
    background: #f2f4f7;
    color: #475467;
    cursor: not-allowed;
}

.form-group input[readonly]:focus,
.form-select:disabled:focus {
    border-color: #d0d5dd;
    box-shadow: none;
}


/* =========================================================
   Request Types
   ========================================================= */

.request-type-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 12px;
}


.request-type {
    position: relative;
    display: flex;
    align-items: center;
    gap: 10px;
    min-height: 64px;
    padding: 12px 14px;
    border: 1px solid #d0d5dd;
    border-radius: 10px;
    background: #ffffff;
    cursor: pointer;
    transition:
        border-color .15s ease,
        background .15s ease,
        transform .15s ease;
}


.request-type:hover {
    border-color: #98a2b3;
    transform: translateY(-1px);
}


.request-type input {
    position: absolute;
    opacity: 0;
}


.request-type > span {
    font-size: 22px;
}


.request-type strong {
    color: #344054;
    font-size: 12px;
}


.request-type:has(input:checked) {
    border-color: #0b1f3a;
    background: #f5f8fc;
    box-shadow:
        0 0 0 2px rgba(11, 31, 58, 0.06);
}


/* =========================================================
   Priority
   ========================================================= */

.priority-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 12px;
}


.priority-option {
    position: relative;
    cursor: pointer;
}


.priority-option input {
    position: absolute;
    opacity: 0;
}


.priority-box {
    display: flex;
    align-items: center;
    gap: 10px;
    min-height: 76px;
    padding: 12px;
    border: 1px solid #d0d5dd;
    border-radius: 10px;
    background: #ffffff;
    transition: all .15s ease;
}


.priority-icon {
    font-size: 23px;
}


.priority-content {
    display: flex;
    flex-direction: column;
    gap: 5px;
}


.priority-content strong {
    color: #344054;
    font-size: 13px;
}


.priority-content small {
    color: #667085;
    font-size: 11px;
}


.priority-option:has(input:checked) .priority-box {
    box-shadow:
        0 0 0 2px rgba(11, 31, 58, 0.08);
}


.priority-normal:has(input:checked) .priority-box {
    border-color: #12b76a;
    background: #f0fdf4;
}


.priority-important:has(input:checked) .priority-box {
    border-color: #f79009;
    background: #fffaeb;
}


.priority-urgent:has(input:checked) .priority-box {
    border-color: #f04438;
    background: #fff5f4;
}


.priority-critical:has(input:checked) .priority-box {
    border-color: #d92d20;
    background: #fff0ee;
}


/* =========================================================
   Attachment
   ========================================================= */

.attachment-box {
    padding: 20px;
    text-align: center;
    border: 1px dashed #98a2b3;
    border-radius: 10px;
    background: #f8fafc;
}


.attachment-box label {
    display: inline-block;
    margin-bottom: 12px;
    padding: 10px 18px;
    border-radius: 8px;
    background: #0b1f3a;
    color: #ffffff;
    font-size: 12px;
    cursor: pointer;
}


.attachment-box input[type="file"] {
    display: block;
    width: 100%;
    margin-bottom: 10px;
}


.attachment-box small {
    display: block;
    color: #667085;
    font-size: 11px;
}


/* =========================================================
   Actions
   ========================================================= */

.form-actions {
    display: flex;
    justify-content: flex-start;
    gap: 10px;
    margin-top: 24px;
}


.form-actions button {
    border: 0;
    border-radius: 8px;
    padding: 12px 24px;
    font-family: Tahoma, Arial, sans-serif;
    font-size: 13px;
    cursor: pointer;
}


.btn-submit {
    background: #0b1f3a;
    color: #ffffff;
}


.btn-submit:hover {
    background: #173b63;
}


.btn-cancel {
    background: #f2f4f7;
    color: #344054;
}


.btn-cancel:hover {
    background: #e4e7ec;
}


.form-notice {
    margin-top: 14px;
    text-align: center;
    color: #667085;
    font-size: 11px;
}


/* =========================================================
   Responsive
   ========================================================= */

@media (max-width: 900px) {

    .request-type-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .priority-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

}


@media (max-width: 650px) {

    .form-grid,
    .request-type-grid,
    .priority-grid {
        grid-template-columns: 1fr;
    }

    .form-section {
        padding: 18px;
    }

    .service-request-header {
        padding: 18px;
    }

}

.required-fields-notice { color: #c00000; }
.required-fields-notice span { color: #f2c94c; }
</style>


<?php

require_once "includes/footer.php";

?>
