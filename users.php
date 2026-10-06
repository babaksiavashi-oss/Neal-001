<?php

require_once "auth/auth.php";

check_login();

$page_title = "مدیریت کاربران";

$message = "";
$message_type = "";

$edit_user = null;
$edit_admin = null;

/* admin_edit_mode_v1 */


/*
|--------------------------------------------------------------------------
| Database
|--------------------------------------------------------------------------
*/

$db = new PDO(
    "sqlite:" . __DIR__ . "/data/neal.db",
    null,
    null,
    [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]
);


/*
|--------------------------------------------------------------------------
| Helper Functions
|--------------------------------------------------------------------------
*/

function h($value)
{
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        'UTF-8'
    );
}


function admin_role_label($role)
{
    $roles = [
        'admin'   => 'مدیر سیستم',
        'manager' => 'مدیر',
        'expert'  => 'کارشناس'
    ];

    return $roles[$role] ?? $role;
}


function user_status_label($is_active)
{
    return ((int)$is_active === 1)
        ? 'فعال'
        : 'غیرفعال';
}


function persianNumber($number)
{
    return strtr(
        (string)$number,
        [
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
        ]
    );
}


/*
|--------------------------------------------------------------------------
| Gregorian -> Jalali
|--------------------------------------------------------------------------
|
| این تابع همان الگوریتم تبدیل تاریخ مورد استفاده در بخش‌های
| دیگر پروژه است.
|
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
|--------------------------------------------------------------------------
| Departments
|--------------------------------------------------------------------------
*/

$departments = [];

$stmt = $db->query(
    "SELECT id, code, name
     FROM departments
     WHERE is_active = 1
     ORDER BY id"
);

$departments = $stmt->fetchAll();


/*
|--------------------------------------------------------------------------
| POST Actions
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $action = $_POST['action'] ?? '';


    /*
    |--------------------------------------------------------------------------
    | Add Admin
    |--------------------------------------------------------------------------
    */

    if ($action === 'add_admin') {

        $username = trim(
            $_POST['admin_username'] ?? $_POST['username'] ?? ''
        );

        $password = $_POST['admin_password'] ?? $_POST['password'] ?? '';

        $confirm_password = $_POST['admin_confirm_password'] ?? $_POST['confirm_password'] ?? '';

        $role = $_POST['admin_role'] ?? $_POST['role'] ?? 'expert';


        if (
            $username === '' ||
            $password === '' ||
            $confirm_password === ''
        ) {

            $message = 'لطفاً تمام فیلدهای الزامی را تکمیل کنید.';

            $message_type = 'error';

        } elseif ($password !== $confirm_password) {

            $message = 'رمز عبور و تکرار آن یکسان نیستند.';

            $message_type = 'error';

        } elseif (strlen($password) < 6) {

            $message = 'رمز عبور باید حداقل ۶ کاراکتر باشد.';

            $message_type = 'error';

        } else {

            $check = $db->prepare(
                "SELECT id
                 FROM admins
                 WHERE username = ?"
            );

            $check->execute([
                $username
            ]);

            if ($check->fetch()) {

                $message = 'این نام کاربری قبلاً ثبت شده است.';

                $message_type = 'error';

            } else {

                $hashed_password = password_hash(
                    $password,
                    PASSWORD_DEFAULT
                );

                $insert = $db->prepare(
                    "INSERT INTO admins
                    (
                        username,
                        password,
                        role
                    )
                    VALUES
                    (
                        ?,
                        ?,
                        ?
                    )"
                );

                $insert->execute([
                    $username,
                    $hashed_password,
                    $role
                ]);

                $message = 'مدیر جدید با موفقیت ایجاد شد.';

                $message_type = 'success';
            }
        }
    }



    /*
    |--------------------------------------------------------------------------
    | Edit Admin
    |--------------------------------------------------------------------------
    */

    elseif ($action === 'edit_admin') {

        $admin_id = (int)(
            $_POST['admin_id'] ?? 0
        );

        $username = trim(
            $_POST['admin_username'] ?? ''
        );

        $password = $_POST['admin_password'] ?? '';

        $confirm_password = $_POST['admin_confirm_password'] ?? '';

        $role = $_POST['admin_role'] ?? 'expert';

        $allowed_roles = [
            'admin',
            'manager',
            'expert'
        ];

        if ($admin_id <= 0) {

            $message = 'مدیر موردنظر معتبر نیست.';

            $message_type = 'error';

        } elseif (
            $username === '' ||
            !in_array($role, $allowed_roles, true)
        ) {

            $message = 'لطفاً اطلاعات مدیر را کامل و صحیح وارد کنید.';

            $message_type = 'error';

        } elseif (
            $password !== '' &&
            $password !== $confirm_password
        ) {

            $message = 'رمز عبور و تکرار آن یکسان نیستند.';

            $message_type = 'error';

        } elseif (
            $password !== '' &&
            strlen($password) < 6
        ) {

            $message = 'رمز عبور باید حداقل ۶ کاراکتر باشد.';

            $message_type = 'error';

        } else {

            $check = $db->prepare(
                "SELECT id
                 FROM admins
                 WHERE username = ?
                 AND id != ?"
            );

            $check->execute([
                $username,
                $admin_id
            ]);

            if ($check->fetch()) {

                $message = 'این نام کاربری قبلاً توسط مدیر دیگری استفاده شده است.';

                $message_type = 'error';

            } else {

                if ($password !== '') {

                    $hashed_password = password_hash(
                        $password,
                        PASSWORD_DEFAULT
                    );

                    $update = $db->prepare(
                        "UPDATE admins
                         SET
                            username = ?,
                            password = ?,
                            role = ?
                         WHERE id = ?"
                    );

                    $update->execute([
                        $username,
                        $hashed_password,
                        $role,
                        $admin_id
                    ]);

                } else {

                    $update = $db->prepare(
                        "UPDATE admins
                         SET
                            username = ?,
                            role = ?
                         WHERE id = ?"
                    );

                    $update->execute([
                        $username,
                        $role,
                        $admin_id
                    ]);
                }

                $message = 'اطلاعات مدیر با موفقیت ویرایش شد.';

                $message_type = 'success';

                $edit_admin = null;
            }
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Toggle Admin
    |--------------------------------------------------------------------------
    */

    elseif ($action === 'toggle_admin') {

        $admin_id = (int)(
            $_POST['admin_id'] ?? 0
        );

        if ($admin_id > 0) {

            $columns = $db->query(
                "PRAGMA table_info(admins)"
            )->fetchAll();

            $has_is_active = false;

            foreach ($columns as $column) {

                if ($column['name'] === 'is_active') {

                    $has_is_active = true;

                    break;
                }
            }

            if (!$has_is_active) {

                $db->exec(
                    "ALTER TABLE admins
                     ADD COLUMN is_active INTEGER NOT NULL DEFAULT 1"
                );
            }

            $update = $db->prepare(
                "UPDATE admins
                 SET is_active =
                     CASE
                         WHEN is_active = 1 THEN 0
                         ELSE 1
                     END
                 WHERE id = ?"
            );

            $update->execute([
                $admin_id
            ]);

            $message = 'وضعیت مدیر با موفقیت تغییر کرد.';

            $message_type = 'success';
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Delete Admin
    |--------------------------------------------------------------------------
    */

    elseif ($action === 'delete_admin') {

        $admin_id = (int)(
            $_POST['admin_id'] ?? 0
        );

        if ($admin_id > 0) {

            $delete = $db->prepare(
                "DELETE FROM admins
                 WHERE id = ?"
            );

            $delete->execute([
                $admin_id
            ]);

            $message = 'حساب مدیر برای همیشه حذف شد.';

            $message_type = 'success';
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Add User
    |--------------------------------------------------------------------------
    */

    elseif ($action === 'add_user') {

        $username = trim(
            $_POST['username'] ?? ''
        );

        $password = $_POST['password'] ?? '';

        $confirm_password = $_POST['confirm_password'] ?? '';

        $fullname = trim(
            $_POST['fullname'] ?? ''
        );

        $personnel_code = trim(
            $_POST['personnel_code'] ?? ''
        );

        $department = trim(
            $_POST['department'] ?? ''
        );

        $phone = trim(
            $_POST['phone'] ?? ''
        );


        if (
            $username === '' ||
            $password === '' ||
            $confirm_password === '' ||
            $fullname === '' ||
            $department === ''
        ) {

            $message = 'لطفاً تمام فیلدهای الزامی را تکمیل کنید.';

            $message_type = 'error';

        } elseif ($password !== $confirm_password) {

            $message = 'رمز عبور و تکرار آن یکسان نیستند.';

            $message_type = 'error';

        } elseif (strlen($password) < 6) {

            $message = 'رمز عبور باید حداقل ۶ کاراکتر باشد.';

            $message_type = 'error';

        } else {

            $check = $db->prepare(
                "SELECT id
                 FROM users
                 WHERE username = ?"
            );

            $check->execute([
                $username
            ]);

            if ($check->fetch()) {

                $message = 'این نام کاربری قبلاً ثبت شده است.';

                $message_type = 'error';

            } else {

                $checkPersonnel = null;

                if ($personnel_code !== '') {

                    $checkPersonnel = $db->prepare(
                        "SELECT id
                         FROM users
                         WHERE personnel_code = ?"
                    );

                    $checkPersonnel->execute([
                        $personnel_code
                    ]);
                }

                if (
                    $checkPersonnel &&
                    $checkPersonnel->fetch()
                ) {

                    $message = 'این کد پرسنلی قبلاً ثبت شده است.';

                    $message_type = 'error';

                } else {

                    $hashed_password = password_hash(
                        $password,
                        PASSWORD_DEFAULT
                    );

                    $insert = $db->prepare(
                        "INSERT INTO users
                        (
                            username,
                            password,
                            fullname,
                            personnel_code,
                            department,
                            phone,
                            is_active
                        )
                        VALUES
                        (
                            ?,
                            ?,
                            ?,
                            ?,
                            ?,
                            ?,
                            1
                        )"
                    );

                    $insert->execute([
                        $username,
                        $hashed_password,
                        $fullname,
                        $personnel_code !== ''
                            ? $personnel_code
                            : null,
                        $department,
                        $phone !== ''
                            ? $phone
                            : null
                    ]);

                    $message = 'کاربر جدید با موفقیت ایجاد شد.';

                    $message_type = 'success';
                }
            }
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Edit User
    |--------------------------------------------------------------------------
    */

    elseif ($action === 'edit_user') {

        $user_id = (int)(
            $_POST['user_id'] ?? 0
        );

        $username = trim(
            $_POST['username'] ?? ''
        );

        $password = $_POST['password'] ?? '';

        $confirm_password = $_POST['confirm_password'] ?? '';

        $fullname = trim(
            $_POST['fullname'] ?? ''
        );

        $personnel_code = trim(
            $_POST['personnel_code'] ?? ''
        );

        $department = trim(
            $_POST['department'] ?? ''
        );

        $phone = trim(
            $_POST['phone'] ?? ''
        );


        if ($user_id <= 0) {

            $message = 'کاربر موردنظر معتبر نیست.';

            $message_type = 'error';

        } elseif (
            $username === '' ||
            $fullname === '' ||
            $department === ''
        ) {

            $message = 'لطفاً فیلدهای الزامی را تکمیل کنید.';

            $message_type = 'error';

        } elseif (
            $password !== '' &&
            $password !== $confirm_password
        ) {

            $message = 'رمز عبور و تکرار آن یکسان نیستند.';

            $message_type = 'error';

        } elseif (
            $password !== '' &&
            strlen($password) < 6
        ) {

            $message = 'رمز عبور باید حداقل ۶ کاراکتر باشد.';

            $message_type = 'error';

        } else {

            $check = $db->prepare(
                "SELECT id
                 FROM users
                 WHERE username = ?
                 AND id != ?"
            );

            $check->execute([
                $username,
                $user_id
            ]);

            if ($check->fetch()) {

                $message = 'این نام کاربری قبلاً توسط کاربر دیگری استفاده شده است.';

                $message_type = 'error';

            } else {

                $checkPersonnel = null;

                if ($personnel_code !== '') {

                    $checkPersonnel = $db->prepare(
                        "SELECT id
                         FROM users
                         WHERE personnel_code = ?
                         AND id != ?"
                    );

                    $checkPersonnel->execute([
                        $personnel_code,
                        $user_id
                    ]);
                }

                if (
                    $checkPersonnel &&
                    $checkPersonnel->fetch()
                ) {

                    $message = 'این کد پرسنلی قبلاً توسط کاربر دیگری استفاده شده است.';

                    $message_type = 'error';

                } else {

                    if ($password !== '') {

                        $hashed_password = password_hash(
                            $password,
                            PASSWORD_DEFAULT
                        );

                        $update = $db->prepare(
                            "UPDATE users
                             SET
                                username = ?,
                                password = ?,
                                fullname = ?,
                                personnel_code = ?,
                                department = ?,
                                phone = ?
                             WHERE id = ?"
                        );

                        $update->execute([
                            $username,
                            $hashed_password,
                            $fullname,
                            $personnel_code !== ''
                                ? $personnel_code
                                : null,
                            $department,
                            $phone !== ''
                                ? $phone
                                : null,
                            $user_id
                        ]);

                    } else {

                        $update = $db->prepare(
                            "UPDATE users
                             SET
                                username = ?,
                                fullname = ?,
                                personnel_code = ?,
                                department = ?,
                                phone = ?
                             WHERE id = ?"
                        );

                        $update->execute([
                            $username,
                            $fullname,
                            $personnel_code !== ''
                                ? $personnel_code
                                : null,
                            $department,
                            $phone !== ''
                                ? $phone
                                : null,
                            $user_id
                        ]);
                    }

                    $message = 'اطلاعات کاربر با موفقیت ویرایش شد.';

                    $message_type = 'success';

                    $edit_user = null;
                }
            }
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Toggle User
    |--------------------------------------------------------------------------
    */

    elseif ($action === 'toggle_user') {

        $user_id = (int)(
            $_POST['user_id'] ?? 0
        );

        if ($user_id > 0) {

            $update = $db->prepare(
                "UPDATE users
                 SET is_active =
                     CASE
                         WHEN is_active = 1 THEN 0
                         ELSE 1
                     END
                 WHERE id = ?"
            );

            $update->execute([
                $user_id
            ]);

            $message = 'وضعیت کاربر با موفقیت تغییر کرد.';

            $message_type = 'success';
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Delete User
    |--------------------------------------------------------------------------
    */

    elseif ($action === 'delete_user') {

        $user_id = (int)(
            $_POST['user_id'] ?? 0
        );

        if ($user_id > 0) {

            $delete = $db->prepare(
                "DELETE FROM users
                 WHERE id = ?"
            );

            $delete->execute([
                $user_id
            ]);

            $message = 'کاربر برای همیشه حذف شد.';

            $message_type = 'success';
        }
    }
}


/*
|--------------------------------------------------------------------------
| Edit User - GET
|--------------------------------------------------------------------------
*/

if (
    isset($_GET['edit_user']) &&
    (int)$_GET['edit_user'] > 0
) {

    $edit_id = (int)$_GET['edit_user'];

    $stmt = $db->prepare(
        "SELECT
            id,
            username,
            fullname,
            personnel_code,
            department,
            phone,
            is_active,
            created_at
         FROM users
         WHERE id = ?"
    );

    $stmt->execute([
        $edit_id
    ]);

    $edit_user = $stmt->fetch();

    if (!$edit_user) {

        $message = 'کاربر موردنظر پیدا نشد.';

        $message_type = 'error';

        $edit_user = null;
    }
}



/*
|--------------------------------------------------------------------------
| Edit Admin - GET
|--------------------------------------------------------------------------
*/

if (
    isset($_GET['edit_admin']) &&
    (int)$_GET['edit_admin'] > 0
) {

    $edit_admin_id = (int)$_GET['edit_admin'];

    $columns = $db->query(
        "PRAGMA table_info(admins)"
    )->fetchAll();

    $has_is_active = false;

    foreach ($columns as $column) {

        if ($column['name'] === 'is_active') {

            $has_is_active = true;

            break;
        }
    }

    if (!$has_is_active) {

        $db->exec(
            "ALTER TABLE admins
             ADD COLUMN is_active INTEGER NOT NULL DEFAULT 1"
        );
    }

    $stmt = $db->prepare(
        "SELECT
            id,
            username,
            role,
            is_active,
            created_at
         FROM admins
         WHERE id = ?"
    );

    $stmt->execute([
        $edit_admin_id
    ]);

    $edit_admin = $stmt->fetch();

    if (!$edit_admin) {

        $message = 'مدیر موردنظر پیدا نشد.';

        $message_type = 'error';

        $edit_admin = null;
    }
}


/*
|--------------------------------------------------------------------------
| Admins
|--------------------------------------------------------------------------
*/

$admin_columns = $db->query(
    "PRAGMA table_info(admins)"
)->fetchAll();

$admin_has_is_active = false;

foreach ($admin_columns as $column) {

    if ($column['name'] === 'is_active') {

        $admin_has_is_active = true;

        break;
    }
}

if (!$admin_has_is_active) {

    $db->exec(
        "ALTER TABLE admins
         ADD COLUMN is_active INTEGER NOT NULL DEFAULT 1"
    );

    $admin_has_is_active = true;
}

$stmt = $db->query(
    "SELECT
        id,
        username,
        role,
        is_active,
        created_at
     FROM admins
     ORDER BY id DESC"
);

$admins = $stmt->fetchAll();


/*
|--------------------------------------------------------------------------
| Normal Users
|--------------------------------------------------------------------------
*/

$users_per_page = 10;

$search = trim(
    $_GET['search'] ?? ''
);

$current_page = isset($_GET['page'])
    ? (int)$_GET['page']
    : 1;

if ($current_page < 1) {
    $current_page = 1;
}

$where_sql = '';

$search_value = '';

if ($search !== '') {

    $where_sql = "
        WHERE
            u.username LIKE :search
            OR u.personnel_code LIKE :search
            OR u.fullname LIKE :search
    ";

    $search_value = '%' . $search . '%';
}


$count_sql = "
    SELECT COUNT(*)
    FROM users u
    {$where_sql}
";

$count_stmt = $db->prepare($count_sql);

if ($search !== '') {

    $count_stmt->bindValue(
        ':search',
        $search_value,
        PDO::PARAM_STR
    );
}

$count_stmt->execute();

$total_users = (int)$count_stmt->fetchColumn();

$total_pages = max(
    1,
    (int)ceil($total_users / $users_per_page)
);

if ($current_page > $total_pages) {
    $current_page = $total_pages;
}

$offset = (
    $current_page - 1
) * $users_per_page;

$stmt = $db->prepare(
    "SELECT
        u.id,
        u.username,
        u.fullname,
        u.personnel_code,
        u.department,
        u.phone,
        u.is_active,
        u.created_at,
        d.name AS department_name
     FROM users u
     LEFT JOIN departments d
        ON d.code = u.department
     {$where_sql}
     ORDER BY u.id DESC
     LIMIT :limit OFFSET :offset"
);

if ($search !== '') {

    $stmt->bindValue(
        ':search',
        $search_value,
        PDO::PARAM_STR
    );
}

$stmt->bindValue(
    ':limit',
    $users_per_page,
    PDO::PARAM_INT
);

$stmt->bindValue(
    ':offset',
    $offset,
    PDO::PARAM_INT
);

$stmt->execute();

$users = $stmt->fetchAll();

$pagination_start = $total_users > 0
    ? $offset + 1
    : 0;

$pagination_end = min(
    $offset + $users_per_page,
    $total_users
);


/*
|--------------------------------------------------------------------------
| Header
|--------------------------------------------------------------------------
*/

require_once "includes/header.php";

?>

<div class="users-page">

    <div class="page-header">

        <div>
            <h1>مدیریت کاربران</h1>

            <p>
                مدیریت مدیران سیستم و کاربران عادی
            </p>
        </div>

    </div>


    <?php if ($message !== ''): ?>

        <div
            class="message
            <?php echo $message_type === 'success'
                ? 'message-success'
                : 'message-error'; ?>"
        >

            <?php if ($message_type === 'success'): ?>

                <span class="message-icon">✓</span>

            <?php else: ?>

                <span class="message-icon">!</span>

            <?php endif; ?>

            <span>
                <?php echo h($message); ?>
            </span>

        </div>

    <?php endif; ?>


    <!-- =====================================================
         افزودن / ویرایش مدیر
    ====================================================== -->

    <div class="card">

        <div class="section-title">

            <div>

                <h2>
                    <?php echo $edit_admin ? 'ویرایش مدیر' : 'ایجاد مدیر جدید'; ?>
                </h2>

                <p>
                    <?php echo $edit_admin
                        ? 'اطلاعات حساب مدیریتی را ویرایش کنید.'
                        : 'حساب کاربری برای مدیران و کارشناسان سیستم'; ?>
                </p>

            </div>

        </div>


        <form
            method="post"
            class="user-form"
        >

            <?php if ($edit_admin): ?>

                <input
                    type="hidden"
                    name="action"
                    value="edit_admin"
                >

                <input
                    type="hidden"
                    name="admin_id"
                    value="<?php echo h($edit_admin['id']); ?>"
                >

            <?php else: ?>

                <input
                    type="hidden"
                    name="action"
                    value="add_admin"
                >

            <?php endif; ?>


            <div class="form-grid">

                <div class="form-group">

                    <label for="admin_username">
                        نام کاربری
                    </label>

                    <input
                        type="text"
                        id="admin_username"
                        name="admin_username"
                        required
                        autocomplete="off"
                        value="<?php
                            echo $edit_admin
                                ? h($edit_admin['username'])
                                : '';
                        ?>"
                    >

                </div>


                <div class="form-group">

                    <label for="admin_role">
                        سطح دسترسی
                    </label>

                    <select
                        id="admin_role"
                        name="admin_role"
                        required
                    >

                        <?php
                        $selected_admin_role = $edit_admin
                            ? $edit_admin['role']
                            : 'expert';
                        ?>

                        <option
                            value="expert"
                            <?php echo $selected_admin_role === 'expert'
                                ? 'selected'
                                : ''; ?>
                        >
                            کارشناس
                        </option>

                        <option
                            value="manager"
                            <?php echo $selected_admin_role === 'manager'
                                ? 'selected'
                                : ''; ?>
                        >
                            مدیر
                        </option>

                        <option
                            value="admin"
                            <?php echo $selected_admin_role === 'admin'
                                ? 'selected'
                                : ''; ?>
                        >
                            مدیر سیستم
                        </option>

                    </select>

                </div>


                <div class="form-group">

                    <label for="admin_password">

                        <?php if ($edit_admin): ?>

                            رمز عبور جدید

                            <small>
                                در صورت عدم تغییر خالی بگذارید
                            </small>

                        <?php else: ?>

                            رمز عبور

                        <?php endif; ?>

                    </label>

                    <input
                        type="password"
                        id="admin_password"
                        name="admin_password"
                        minlength="6"
                        autocomplete="new-password"
                        <?php echo $edit_admin ? '' : 'required'; ?>
                    >

                </div>


                <div class="form-group">

                    <label for="admin_confirm_password">

                        <?php if ($edit_admin): ?>

                            تکرار رمز عبور جدید

                        <?php else: ?>

                            تکرار رمز عبور

                        <?php endif; ?>

                    </label>

                    <input
                        type="password"
                        id="admin_confirm_password"
                        name="admin_confirm_password"
                        minlength="6"
                        autocomplete="new-password"
                        <?php echo $edit_admin ? '' : 'required'; ?>
                    >

                </div>

            </div>


            <div class="form-actions">

                <?php if ($edit_admin): ?>

                    <button
                        type="submit"
                        class="btn-primary"
                    >
                        ذخیره تغییرات
                    </button>

                    <a
                        href="/neal/users.php"
                        class="btn-secondary"
                    >
                        انصراف
                    </a>

                <?php else: ?>

                    <button
                        type="submit"
                        class="btn-primary"
                    >
                        ایجاد مدیر
                    </button>

                <?php endif; ?>

            </div>

        </form>

    </div>


    <!-- =====================================================
         لیست مدیران
    ====================================================== -->

    <div class="card">

        <div class="section-title">

            <div>

                <h2>
                    مدیران سیستم
                </h2>

                <p>
                    فهرست حساب‌های مدیریتی سیستم
                </p>

            </div>

        </div>


        <div class="table-wrapper">

            <table class="users-table">

                <thead>

                    <tr>

                        <th>
                            #
                        </th>

                        <th>
                            نام کاربری
                        </th>

                        <th>
                            سطح دسترسی
                        </th>

                        <th>
                            تاریخ ایجاد
                        </th>

                        <th class="actions-header">
                            عملیات
                        </th>

                    </tr>

                </thead>


                <tbody>

                <?php if (empty($admins)): ?>

                    <tr>

                        <td
                            colspan="5"
                            class="empty-cell"
                        >
                            هنوز مدیر یا کارشناس مدیریتی ثبت نشده است.
                        </td>

                    </tr>

                <?php else: ?>

                    <?php foreach ($admins as $admin): ?>

                        <tr>

                            <td>
                                <?php echo h($admin["id"]); ?>
                            </td>

                            <td>
                                <strong>
                                    <?php echo h($admin["username"]); ?>
                                </strong>
                            </td>

                            <td>

                                <span class="role-badge">

                                    <?php
                                    echo h(
                                        admin_role_label(
                                            $admin["role"]
                                        )
                                    );
                                    ?>

                                </span>

                            </td>

                            <td>

                                <?php
                                echo h(
                                    jalali_date(
                                        $admin["created_at"]
                                    )
                                );
                                ?>

                            </td>


                            <td class="actions-cell">

                                <div class="action-buttons">


                                    <a
                                        href="/neal/users.php?edit_admin=<?php echo h($admin["id"]); ?>"
                                        class="icon-action icon-edit"
                                        title="ویرایش مدیر"
                                        aria-label="ویرایش مدیر"
                                    >

                                        <svg
                                            viewBox="0 0 24 24"
                                            aria-hidden="true"
                                        >

                                            <path d="M12 20h9"></path>

                                            <path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4Z"></path>

                                        </svg>

                                    </a>


                                    <form
                                        method="post"
                                        class="inline-action-form"
                                    >

                                        <input
                                            type="hidden"
                                            name="action"
                                            value="toggle_admin"
                                        >

                                        <input
                                            type="hidden"
                                            name="admin_id"
                                            value="<?php echo h($admin["id"]); ?>"
                                        >

                                        <?php if ((int)$admin["is_active"] === 1): ?>

                                            <button
                                                type="submit"
                                                class="icon-action icon-disable"
                                                title="غیرفعال کردن مدیر"
                                                aria-label="غیرفعال کردن مدیر"
                                                onclick="return confirm('آیا مطمئن هستید که می‌خواهید این مدیر را غیرفعال کنید؟');"
                                            >

                                                <svg
                                                    viewBox="0 0 24 24"
                                                    aria-hidden="true"
                                                >

                                                    <path d="M18 8a6 6 0 0 0-12 0v4a6 6 0 0 0 12 0Z"></path>

                                                    <path d="M8 21h8"></path>

                                                    <path d="M12 2v6"></path>

                                                </svg>

                                            </button>

                                        <?php else: ?>

                                            <button
                                                type="submit"
                                                class="icon-action icon-enable"
                                                title="فعال کردن مدیر"
                                                aria-label="فعال کردن مدیر"
                                                onclick="return confirm('آیا می‌خواهید این مدیر دوباره فعال شود؟');"
                                            >

                                                <svg
                                                    viewBox="0 0 24 24"
                                                    aria-hidden="true"
                                                >

                                                    <path d="M12 2v10"></path>

                                                    <path d="M18.36 6.64a9 9 0 1 1-12.73 0"></path>

                                                </svg>

                                            </button>

                                        <?php endif; ?>

                                    </form>


                                    <form
                                        method="post"
                                        class="inline-action-form"
                                        onsubmit="return confirm('⚠️ آیا مطمئن هستید که می‌خواهید این مدیر را برای همیشه حذف کنید؟');"
                                    >

                                        <input
                                            type="hidden"
                                            name="action"
                                            value="delete_admin"
                                        >

                                        <input
                                            type="hidden"
                                            name="admin_id"
                                            value="<?php echo h($admin["id"]); ?>"
                                        >

                                        <button
                                            type="submit"
                                            class="icon-action icon-delete"
                                            title="حذف دائمی مدیر"
                                            aria-label="حذف دائمی مدیر"
                                        >

                                            <svg
                                                viewBox="0 0 24 24"
                                                aria-hidden="true"
                                            >

                                                <path d="M3 6h18"></path>

                                                <path d="M8 6V4h8v2"></path>

                                                <path d="M19 6l-1 14H6L5 6"></path>

                                                <path d="M10 11v5"></path>

                                                <path d="M14 11v5"></path>

                                            </svg>

                                        </button>

                                    </form>

                                </div>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                <?php endif; ?>

                </tbody>

            </table>

        </div>

    </div>


    <!-- =====================================================
         افزودن / ویرایش کاربر
    ====================================================== -->

    <div class="card">

        <div class="section-title">

            <div>

                <h2>

                    <?php if ($edit_user): ?>

                        ویرایش کاربر

                    <?php else: ?>

                        ایجاد کاربر جدید

                    <?php endif; ?>

                </h2>

                <p>

                    <?php if ($edit_user): ?>

                        اطلاعات کاربر را ویرایش کنید.

                    <?php else: ?>

                        ایجاد حساب کاربری برای کارکنان شرکت.

                    <?php endif; ?>

                </p>

            </div>

        </div>


        <form
            method="post"
            class="user-form"
        >

            <?php if ($edit_user): ?>

                <input
                    type="hidden"
                    name="action"
                    value="edit_user"
                >

                <input
                    type="hidden"
                    name="user_id"
                    value="<?php echo h($edit_user["id"]); ?>"
                >

            <?php else: ?>

                <input
                    type="hidden"
                    name="action"
                    value="add_user"
                >

            <?php endif; ?>


            <div class="form-grid">


                <div class="form-group">

                    <label for="user_username">
                        نام کاربری
                    </label>

                    <input
                        type="text"
                        id="user_username"
                        name="username"
                        required
                        autocomplete="off"
                        value="<?php
                            echo $edit_user
                                ? h($edit_user["username"])
                                : '';
                        ?>"
                    >

                </div>


                <div class="form-group">

                    <label for="user_fullname">
                        نام و نام خانوادگی
                    </label>

                    <input
                        type="text"
                        id="user_fullname"
                        name="fullname"
                        required
                        value="<?php
                            echo $edit_user
                                ? h($edit_user["fullname"])
                                : '';
                        ?>"
                    >

                </div>


                <div class="form-group">

                    <label for="personnel_code">
                        کد پرسنلی
                    </label>

                    <input
                        type="text"
                        id="personnel_code"
                        name="personnel_code"
                        value="<?php
                            echo $edit_user
                                ? h($edit_user["personnel_code"])
                                : '';
                        ?>"
                    >

                </div>


                <div class="form-group">

                    <label for="department">
                        واحد / دپارتمان
                    </label>

                    <select
                        id="department"
                        name="department"
                        required
                    >

                        <option value="">
                            انتخاب واحد
                        </option>

                        <?php foreach ($departments as $department): ?>

                            <option
                                value="<?php echo h($department["code"]); ?>"
                                <?php
                                echo (
                                    $edit_user &&
                                    $edit_user["department"] ===
                                    $department["code"]
                                )
                                    ? 'selected'
                                    : '';
                                ?>
                            >

                                <?php echo h($department["name"]); ?>

                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>


                <div class="form-group">

                    <label for="phone">
                        شماره تماس
                    </label>

                    <input
                        type="text"
                        id="phone"
                        name="phone"
                        value="<?php
                            echo $edit_user
                                ? h($edit_user["phone"])
                                : '';
                        ?>"
                    >

                </div>


                <div class="form-group">

                    <label for="user_password">

                        <?php if ($edit_user): ?>

                            رمز عبور جدید
                            <small>
                                در صورت عدم تغییر خالی بگذارید
                            </small>

                        <?php else: ?>

                            رمز عبور

                        <?php endif; ?>

                    </label>

                    <input
                        type="password"
                        id="user_password"
                        name="password"
                        minlength="6"
                        autocomplete="new-password"
                        <?php echo $edit_user ? '' : 'required'; ?>
                    >

                </div>


                <div class="form-group">

                    <label for="user_confirm_password">

                        <?php if ($edit_user): ?>

                            تکرار رمز عبور جدید

                        <?php else: ?>

                            تکرار رمز عبور

                        <?php endif; ?>

                    </label>

                    <input
                        type="password"
                        id="user_confirm_password"
                        name="confirm_password"
                        minlength="6"
                        autocomplete="new-password"
                        <?php echo $edit_user ? '' : 'required'; ?>
                    >

                </div>

            </div>


            <div class="form-actions">

                <?php if ($edit_user): ?>

                    <button
                        type="submit"
                        class="btn-primary"
                    >
                        ذخیره تغییرات
                    </button>

                    <a
                        href="/neal/users.php"
                        class="btn-secondary"
                    >
                        انصراف
                    </a>

                <?php else: ?>

                    <button
                        type="submit"
                        class="btn-primary"
                    >
                        ایجاد کاربر
                    </button>

                <?php endif; ?>

            </div>

        </form>

    </div>


    <!-- =====================================================
         لیست کاربران
    ====================================================== -->

    <div class="card users-list-card">

        <div class="section-title">

            <div>

                <h2>
                    کاربران عادی
                </h2>

                <p>
                    مدیریت حساب کاربران پرتال
                </p>

            </div>

        </div>


        <form
            method="get"
            class="users-search-form"
        >

            <div class="users-search-box">

                <svg
                    viewBox="0 0 24 24"
                    aria-hidden="true"
                >
                    <circle cx="11" cy="11" r="7"></circle>
                    <path d="m20 20-4-4"></path>
                </svg>

                <input
                    type="search"
                    name="search"
                    value="<?php echo h($search); ?>"
                    placeholder="جستجو بر اساس نام کاربری، کد پرسنلی یا نام و نام خانوادگی..."
                    aria-label="جستجوی کاربران"
                >

                <?php if ($search !== ''): ?>

                    <a
                        href="/neal/users.php"
                        class="users-search-clear"
                        title="پاک کردن جستجو"
                        aria-label="پاک کردن جستجو"
                    >
                        ×
                    </a>

                <?php endif; ?>

                <button
                    type="submit"
                    class="users-search-button"
                >
                    جستجو
                </button>

            </div>

        </form>


        <?php if ($search !== ''): ?>

            <div class="users-search-result">
                نتیجه جستجو برای:
                <strong><?php echo h($search); ?></strong>
                —
                <?php echo persianNumber($total_users); ?>
                کاربر
            </div>

        <?php endif; ?>


        <div class="users-table-wrapper">

            <table class="users-table">

                <thead>

                    <tr>

                        <th>
                            #
                        </th>

                        <th>
                            نام کاربری
                        </th>

                        <th>
                            نام و نام خانوادگی
                        </th>

                        <th>
                            کد پرسنلی
                        </th>

                        <th>
                            واحد
                        </th>

                        <th>
                            شماره تماس
                        </th>

                        <th>
                            وضعیت
                        </th>

                        <th>
                            تاریخ ایجاد
                        </th>

                        <th class="actions-header">
                            عملیات
                        </th>

                    </tr>

                </thead>


                <tbody>

                <?php if (empty($users)): ?>

                    <tr>

                        <td
                            colspan="9"
                            class="empty-cell"
                        >

                            <?php if ($search !== ''): ?>

                                کاربری با این عبارت جستجو پیدا نشد.

                            <?php else: ?>

                                هنوز کاربری ثبت نشده است.

                            <?php endif; ?>

                        </td>

                    </tr>

                <?php else: ?>

                    <?php foreach ($users as $user): ?>

                        <tr
                            class="<?php
                                echo ((int)$user["is_active"] === 0)
                                    ? 'inactive-row'
                                    : '';
                            ?>"
                        >

                            <td>

                                <?php
                                echo h($user["id"]);
                                ?>

                            </td>


                            <td>

                                <strong>
                                    <?php
                                    echo h(
                                        $user["username"]
                                    );
                                    ?>
                                </strong>

                            </td>


                            <td>

                                <?php
                                echo h(
                                    $user["fullname"]
                                );
                                ?>

                            </td>


                            <td>

                                <?php
                                echo $user["personnel_code"] !== null &&
                                     $user["personnel_code"] !== ''
                                    ? h($user["personnel_code"])
                                    : '---';
                                ?>

                            </td>


                            <td>

                                <?php
                                echo h(
                                    $user["department_name"]
                                    ?: $user["department"]
                                );
                                ?>

                            </td>


                            <td>

                                <?php
                                echo $user["phone"] !== null &&
                                     $user["phone"] !== ''
                                    ? h($user["phone"])
                                    : '---';
                                ?>

                            </td>


                            <td>

                                <?php if ((int)$user["is_active"] === 1): ?>

                                    <span class="status-badge status-active">

                                        <span class="status-dot"></span>

                                        فعال

                                    </span>

                                <?php else: ?>

                                    <span class="status-badge status-inactive">

                                        <span class="status-dot"></span>

                                        غیرفعال

                                    </span>

                                <?php endif; ?>

                            </td>


                            <td>

                                <?php
                                echo h(
                                    jalali_date(
                                        $user["created_at"]
                                    )
                                );
                                ?>

                            </td>


                            <!-- =============================
                                 عملیات
                            ============================== -->

                            <td class="actions-cell">

                                <div class="action-buttons">


                                    <!-- ویرایش -->

                                    <a
                                        href="/neal/users.php?edit_user=<?php echo h($user["id"]); ?>"
                                        class="icon-action icon-edit"
                                        title="ویرایش کاربر"
                                        aria-label="ویرایش کاربر"
                                    >

                                        <svg
                                            viewBox="0 0 24 24"
                                            aria-hidden="true"
                                        >

                                            <path
                                                d="M12 20h9"
                                            />

                                            <path
                                                d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4Z"
                                            />

                                        </svg>

                                    </a>


                                    <!-- فعال / غیرفعال -->

                                    <form
                                        method="post"
                                        class="inline-action-form"
                                    >

                                        <input
                                            type="hidden"
                                            name="action"
                                            value="toggle_user"
                                        >

                                        <input
                                            type="hidden"
                                            name="user_id"
                                            value="<?php echo h($user["id"]); ?>"
                                        >


                                        <?php if ((int)$user["is_active"] === 1): ?>

                                            <button
                                                type="submit"
                                                class="icon-action icon-disable"
                                                title="غیرفعال کردن کاربر"
                                                aria-label="غیرفعال کردن کاربر"
                                                onclick="return confirm('آیا مطمئن هستید که می‌خواهید این کاربر را غیرفعال کنید؟');"
                                            >

                                                <svg
                                                    viewBox="0 0 24 24"
                                                    aria-hidden="true"
                                                >

                                                    <path
                                                        d="M18 8a6 6 0 0 0-12 0v4a6 6 0 0 0 12 0Z"
                                                    />

                                                    <path
                                                        d="M8 21h8"
                                                    />

                                                    <path
                                                        d="M12 2v6"
                                                    />

                                                </svg>

                                            </button>

                                        <?php else: ?>

                                            <button
                                                type="submit"
                                                class="icon-action icon-enable"
                                                title="فعال کردن کاربر"
                                                aria-label="فعال کردن کاربر"
                                                onclick="return confirm('آیا می‌خواهید این کاربر دوباره فعال شود؟');"
                                            >

                                                <svg
                                                    viewBox="0 0 24 24"
                                                    aria-hidden="true"
                                                >

                                                    <path
                                                        d="M12 2v10"
                                                    />

                                                    <path
                                                        d="M18.36 6.64a9 9 0 1 1-12.73 0"
                                                    />

                                                </svg>

                                            </button>

                                        <?php endif; ?>

                                    </form>


                                    <!-- حذف -->

                                    <form
                                        method="post"
                                        class="inline-action-form"
                                        onsubmit="return confirm('⚠️ آیا مطمئن هستید که می‌خواهید این کاربر را برای همیشه حذف کنید؟\n\nاطلاعات درخواست‌های ثبت‌شده حذف نخواهد شد.');"
                                    >

                                        <input
                                            type="hidden"
                                            name="action"
                                            value="delete_user"
                                        >

                                        <input
                                            type="hidden"
                                            name="user_id"
                                            value="<?php echo h($user["id"]); ?>"
                                        >

                                        <button
                                            type="submit"
                                            class="icon-action icon-delete"
                                            title="حذف دائمی کاربر"
                                            aria-label="حذف دائمی کاربر"
                                        >

                                            <svg
                                                viewBox="0 0 24 24"
                                                aria-hidden="true"
                                            >

                                                <path
                                                    d="M3 6h18"
                                                />

                                                <path
                                                    d="M8 6V4h8v2"
                                                />

                                                <path
                                                    d="M19 6l-1 14H6L5 6"
                                                />

                                                <path
                                                    d="M10 11v5"
                                                />

                                                <path
                                                    d="M14 11v5"
                                                />

                                            </svg>

                                        </button>

                                    </form>

                                </div>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                <?php endif; ?>

                </tbody>

            </table>

        </div>


        <?php if ($total_pages > 1): ?>

            <div class="pagination-area">

                <div class="pagination-info">
                    نمایش
                    <?php echo persianNumber($pagination_start); ?>
                    تا
                    <?php echo persianNumber($pagination_end); ?>
                    از
                    <?php echo persianNumber($total_users); ?>
                    کاربر
                </div>

                <div class="pagination">

                    <?php
                    $pagination_query = $_GET;
                    unset($pagination_query['page']);

                    $buildPaginationUrl = function ($page) use ($pagination_query) {
                        $query = $pagination_query;
                        $query['page'] = $page;

                        return '/neal/users.php?' .
                            http_build_query($query);
                    };
                    ?>

                    <?php if ($current_page > 1): ?>

                        <a
                            href="<?php echo h($buildPaginationUrl($current_page - 1)); ?>"
                            class="pagination-btn"
                            aria-label="صفحه قبل"
                            title="صفحه قبل"
                        >
                            <svg viewBox="0 0 24 24" aria-hidden="true">
                                <path d="m15 18-6-6 6-6"></path>
                            </svg>
                        </a>

                    <?php else: ?>

                        <span
                            class="pagination-btn pagination-disabled"
                            aria-hidden="true"
                        >
                            <svg viewBox="0 0 24 24" aria-hidden="true">
                                <path d="m15 18-6-6 6-6"></path>
                            </svg>
                        </span>

                    <?php endif; ?>


                    <div class="pagination-numbers">

                        <?php
                        $pages = [];

                        if ($total_pages <= 7) {

                            for ($i = 1; $i <= $total_pages; $i++) {
                                $pages[] = $i;
                            }

                        } else {

                            $pages[] = 1;

                            if ($current_page > 4) {
                                $pages[] = '...';
                            }

                            $start_page = max(2, $current_page - 1);
                            $end_page = min(
                                $total_pages - 1,
                                $current_page + 1
                            );

                            for (
                                $i = $start_page;
                                $i <= $end_page;
                                $i++
                            ) {
                                $pages[] = $i;
                            }

                            if ($current_page < $total_pages - 3) {
                                $pages[] = '...';
                            }

                            $pages[] = $total_pages;
                        }
                        ?>

                        <?php foreach ($pages as $page_item): ?>

                            <?php if ($page_item === '...'): ?>

                                <span class="pagination-dots">...</span>

                            <?php elseif ((int)$page_item === $current_page): ?>

                                <span class="pagination-number pagination-current">
                                    <?php echo persianNumber($page_item); ?>
                                </span>

                            <?php else: ?>

                                <a
                                    href="<?php echo h($buildPaginationUrl($page_item)); ?>"
                                    class="pagination-number"
                                >
                                    <?php echo persianNumber($page_item); ?>
                                </a>

                            <?php endif; ?>

                        <?php endforeach; ?>

                    </div>


                    <?php if ($current_page < $total_pages): ?>

                        <a
                            href="<?php echo h($buildPaginationUrl($current_page + 1)); ?>"
                            class="pagination-btn"
                            aria-label="صفحه بعد"
                            title="صفحه بعد"
                        >
                            <svg viewBox="0 0 24 24" aria-hidden="true">
                                <path d="m9 18 6-6-6 6"></path>
                            </svg>
                        </a>

                    <?php else: ?>

                        <span
                            class="pagination-btn pagination-disabled"
                            aria-hidden="true"
                        >
                            <svg viewBox="0 0 24 24" aria-hidden="true">
                                <path d="m9 18 6-6-6-6"></path>
                            </svg>
                        </span>

                    <?php endif; ?>

                </div>

            </div>

        <?php endif; ?>

    </div>

</div>


<style>

/* =========================================================
   Page
========================================================= */

.users-page {

    width: 100%;

    max-width: 1500px;

    margin: 0 auto;

}


.page-header {

    margin-bottom: 25px;

}

.page-header h1 {

    margin: 0 0 7px 0;

    font-size: 28px;

    color: #0b1f3a;

}

.page-header p {

    margin: 0;

    color: #777;

    font-size: 14px;

}


/* =========================================================
   Card
========================================================= */

.users-page .card {

    background: #fff;

    border: 1px solid #e5e7eb;

    border-radius: 12px;

    padding: 22px;

    margin-bottom: 25px;

    box-shadow: 0 2px 8px rgba(0,0,0,.04);

}


.section-title {

    display: flex;

    justify-content: space-between;

    align-items: center;

    margin-bottom: 22px;

    padding-bottom: 15px;

    border-bottom: 1px solid #edf0f2;

}

.section-title h2 {

    margin: 0 0 6px 0;

    color: #0b1f3a;

    font-size: 19px;

}

.section-title p {

    margin: 0;

    color: #777;

    font-size: 13px;

}


/* =========================================================
   Messages
========================================================= */

.message {

    display: flex;

    align-items: center;

    gap: 10px;

    padding: 13px 16px;

    border-radius: 9px;

    margin-bottom: 20px;

    font-size: 14px;

    font-weight: 600;

}

.message-success {

    background: #ecfdf5;

    color: #047857;

    border: 1px solid #a7f3d0;

}

.message-error {

    background: #fef2f2;

    color: #b91c1c;

    border: 1px solid #fecaca;

}

.message-icon {

    width: 24px;

    height: 24px;

    border-radius: 50%;

    display: inline-flex;

    align-items: center;

    justify-content: center;

    background: rgba(255,255,255,.65);

}


/* =========================================================
   Forms
========================================================= */

.user-form {

    width: 100%;

}

.form-grid {

    display: grid;

    grid-template-columns: repeat(3, minmax(0, 1fr));

    gap: 18px;

}

.form-group {

    display: flex;

    flex-direction: column;

    gap: 7px;

}

.form-group label {

    font-size: 13px;

    font-weight: 700;

    color: #374151;

}

.form-group label small {

    color: #888;

    font-weight: 400;

    margin-right: 5px;

}

.form-group input,

.form-group select {

    width: 100%;

    box-sizing: border-box;

    height: 43px;

    padding: 0 12px;

    border: 1px solid #d9dee5;

    border-radius: 8px;

    background: #fff;

    color: #222;

    font-family: inherit;

    font-size: 13px;

    outline: none;

    transition: border-color .2s, box-shadow .2s;

}

.form-group input:focus,

.form-group select:focus {

    border-color: #1769aa;

    box-shadow: 0 0 0 3px rgba(23,105,170,.10);

}

.form-actions {

    display: flex;

    align-items: center;

    gap: 10px;

    margin-top: 20px;

}

.btn-primary,

.btn-secondary {

    display: inline-flex;

    align-items: center;

    justify-content: center;

    min-height: 42px;

    padding: 0 20px;

    border-radius: 8px;

    border: none;

    text-decoration: none;

    font-family: inherit;

    font-size: 13px;

    font-weight: 700;

    cursor: pointer;

}

.btn-primary {

    background: #1769aa;

    color: #fff;

}

.btn-primary:hover {

    background: #12588e;

}

.btn-secondary {

    background: #f1f5f9;

    color: #475569;

    border: 1px solid #e2e8f0;

}

.btn-secondary:hover {

    background: #e2e8f0;

}


/* =========================================================
   User Search
========================================================= */

.users-search-form {

    width: 100%;

    margin: -5px 0 16px 0;

}

.users-search-box {

    display: flex;

    align-items: center;

    gap: 10px;

    width: 100%;

    min-height: 46px;

    box-sizing: border-box;

    padding: 4px 6px 4px 14px;

    background: #f8fafc;

    border: 1px solid #e1e7ee;

    border-radius: 10px;

    transition:
        border-color .18s ease,
        box-shadow .18s ease,
        background .18s ease;

}

.users-search-box:focus-within {

    background: #fff;

    border-color: #1769aa;

    box-shadow: 0 0 0 3px rgba(23,105,170,.08);

}

.users-search-box > svg {

    width: 19px;

    height: 19px;

    flex: 0 0 19px;

    fill: none;

    stroke: #64748b;

    stroke-width: 1.8;

    stroke-linecap: round;

    stroke-linejoin: round;

}

.users-search-box input {

    flex: 1;

    min-width: 0;

    height: 36px;

    padding: 0;

    border: none;

    outline: none;

    background: transparent;

    color: #1f2937;

    font-family: inherit;

    font-size: 13px;

}

.users-search-box input::placeholder {

    color: #9ca3af;

}

.users-search-button {

    min-width: 82px;

    height: 36px;

    padding: 0 16px;

    border: none;

    border-radius: 7px;

    background: #1769aa;

    color: #fff;

    font-family: inherit;

    font-size: 13px;

    font-weight: 700;

    cursor: pointer;

    transition: background .18s ease;

}

.users-search-button:hover {

    background: #12588e;

}

.users-search-clear {

    width: 28px;

    height: 28px;

    flex: 0 0 28px;

    display: inline-flex;

    align-items: center;

    justify-content: center;

    border-radius: 50%;

    color: #64748b;

    text-decoration: none;

    font-size: 22px;

    line-height: 1;

}

.users-search-clear:hover {

    background: #e5e7eb;

    color: #111827;

}

.users-search-result {

    margin: -4px 0 14px 0;

    color: #6b7280;

    font-size: 12px;

}

.users-search-result strong {

    color: #1769aa;

    font-weight: 700;

}


/* =========================================================
   Tables
========================================================= */

.table-wrapper,

.users-table-wrapper {

    width: 100%;

    max-width: 100%;

    overflow-x: auto;

    overflow-y: hidden;

    -webkit-overflow-scrolling: touch;

    border: 1px solid #eeeeee;

    border-radius: 8px;

}

.users-table {

    width: 100%;

    min-width: 1250px;

    border-collapse: collapse;

    table-layout: auto;

}

.users-table th {

    background: #f8fafc;

    color: #555;

    font-size: 13px;

    font-weight: 700;

    padding: 13px 12px;

    text-align: right;

    border-bottom: 1px solid #e5e7eb;

    white-space: nowrap;

}

.users-table td {

    padding: 13px 12px;

    border-bottom: 1px solid #edf0f2;

    vertical-align: middle;

    font-size: 13px;

    white-space: nowrap;

}

.users-table tbody tr:last-child td {

    border-bottom: none;

}

.users-table tbody tr:hover {

    background: #fafcff;

}

.inactive-row {

    background: #fafafa;

}

.inactive-row:hover {

    background: #f7f7f7 !important;

}


/* =========================================================
   Role Badge
========================================================= */

.role-badge {

    display: inline-flex;

    align-items: center;

    padding: 5px 10px;

    border-radius: 20px;

    background: #eef6ff;

    color: #1769aa;

    font-size: 12px;

    font-weight: 700;

}


/* =========================================================
   Status
========================================================= */

.status-badge {

    display: inline-flex;

    align-items: center;

    gap: 7px;

    padding: 5px 10px;

    border-radius: 20px;

    font-size: 12px;

    font-weight: 700;

}

.status-active {

    background: #ecfdf5;

    color: #047857;

}

.status-inactive {

    background: #f3f4f6;

    color: #6b7280;

}

.status-dot {

    width: 7px;

    height: 7px;

    border-radius: 50%;

    background: currentColor;

}


/* =========================================================
   Actions
========================================================= */

.actions-header {

    min-width: 150px;

}

.actions-cell {

    min-width: 150px;

    width: 150px;

    white-space: nowrap !important;

}

.action-buttons {

    display: flex;

    align-items: center;

    justify-content: flex-start;

    gap: 7px;

    flex-wrap: nowrap;

}

.inline-action-form {

    display: inline-flex;

    margin: 0;

    padding: 0;

}


/*
|--------------------------------------------------------------------------
| Icon buttons
|--------------------------------------------------------------------------
*/

.icon-action {

    position: relative;

    width: 36px;

    height: 36px;

    flex: 0 0 36px;

    display: inline-flex;

    align-items: center;

    justify-content: center;

    border-radius: 8px;

    border: 1px solid transparent;

    background: #fff;

    cursor: pointer;

    text-decoration: none;

    transition:
        background .18s ease,
        border-color .18s ease,
        transform .18s ease;

}

.icon-action:hover {

    transform: translateY(-1px);

}

.icon-action svg {

    width: 18px;

    height: 18px;

    fill: none;

    stroke: currentColor;

    stroke-width: 1.9;

    stroke-linecap: round;

    stroke-linejoin: round;

}


/* Edit */

.icon-edit {

    color: #1769aa;

    background: #eef6ff;

    border-color: #d7eaff;

}

.icon-edit:hover {

    background: #dcecff;

    border-color: #c3e0ff;

}


/* Disable */

.icon-disable {

    color: #b45309;

    background: #fff7df;

    border-color: #fde7a9;

}

.icon-disable:hover {

    background: #ffefbd;

    border-color: #f8d978;

}


/* Enable */

.icon-enable {

    color: #047857;

    background: #ecfdf5;

    border-color: #b7efd7;

}

.icon-enable:hover {

    background: #d8f8e9;

    border-color: #91e3c3;

}


/* Delete */

.icon-delete {

    color: #dc2626;

    background: #fef2f2;

    border-color: #fecaca;

}

.icon-delete:hover {

    background: #fee2e2;

    border-color: #fca5a5;

}


/* =========================================================
   Empty
========================================================= */

.empty-cell {

    text-align: center !important;

    color: #888;

    padding: 35px !important;

}


/* =========================================================
   Pagination
========================================================= */

.pagination-area {

    display: flex;

    align-items: center;

    justify-content: space-between;

    gap: 20px;

    margin-top: 18px;

    padding-top: 18px;

    border-top: 1px solid #edf0f2;

}

.pagination-info {

    color: #6b7280;

    font-size: 13px;

}

.pagination {

    display: flex;

    align-items: center;

    gap: 6px;

}

.pagination-numbers {

    display: flex;

    align-items: center;

    gap: 5px;

}

.pagination-btn,
.pagination-number {

    width: 36px;

    height: 36px;

    display: inline-flex;

    align-items: center;

    justify-content: center;

    border: 1px solid #e1e5ea;

    border-radius: 8px;

    background: #fff;

    color: #374151;

    text-decoration: none;

    font-size: 13px;

    transition:
        background .15s ease,
        border-color .15s ease,
        color .15s ease,
        box-shadow .15s ease;

}

.pagination-btn svg {

    width: 17px;

    height: 17px;

    fill: none;

    stroke: currentColor;

    stroke-width: 2;

    stroke-linecap: round;

    stroke-linejoin: round;

}

.pagination-btn:hover,
.pagination-number:hover {

    background: #f5f7fa;

    border-color: #cbd5e1;

    color: #0b1f3a;

}

.pagination-number.pagination-current {

    background: #0b1f3a;

    border-color: #0b1f3a;

    color: #fff;

    font-weight: 700;

}

.pagination-disabled {

    opacity: .45;

    cursor: default;

    pointer-events: none;

}

.pagination-dots {

    width: 28px;

    text-align: center;

    color: #9ca3af;

    font-size: 14px;

}


/* =========================================================
   Responsive
========================================================= */

@media (max-width: 1100px) {

    .form-grid {

        grid-template-columns: repeat(2, minmax(0, 1fr));

    }

}

@media (max-width: 700px) {

    .users-search-box {

        padding-left: 10px;

    }

    .users-search-button {

        min-width: 68px;

        padding: 0 11px;

    }

    .users-page .card {

        padding: 16px;

    }

    .form-grid {

        grid-template-columns: 1fr;

    }

    .page-header h1 {

        font-size: 23px;

    }

    .pagination-area {

        flex-direction: column;

        align-items: stretch;

    }

    .pagination {

        justify-content: center;

    }

}

</style>


<?php

require_once "includes/footer.php";

?>

