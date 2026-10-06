<?php

require_once __DIR__ . "/config/config.php";
require_once __DIR__ . "/auth/auth.php";


/*
 * =========================================================
 * Database
 * =========================================================
 */

$db = new PDO("sqlite:" . __DIR__ . "/data/neal.db");

$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);


/*
 * =========================================================
 * Already Logged In
 * =========================================================
 */

if (isset($_SESSION['admin_id'])) {

    header("Location: /neal/");

    exit;
}


if (isset($_SESSION['user_id'])) {

    header("Location: /neal/portal.php");

    exit;
}


/*
 * =========================================================
 * Login
 * =========================================================
 */

$error = "";

$timeout = isset($_GET['timeout']) && $_GET['timeout'] == "1";


if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $login_type = $_POST['login_type'] ?? "";
    $username   = trim($_POST['username'] ?? "");
    $password   = $_POST['password'] ?? "";


    if ($username === "" || $password === "") {

        $error = "نام کاربری و رمز عبور را وارد کنید.";

    }


    /*
     * =====================================================
     * Admin / Expert Login
     * =====================================================
     */

    elseif ($login_type === "admin") {

        $stmt = $db->prepare("
            SELECT *
            FROM admins
            WHERE username = ?
            LIMIT 1
        ");

        $stmt->execute([$username]);

        $admin = $stmt->fetch();


        if ($admin && password_verify($password, $admin['password'])) {

            session_regenerate_id(true);


            /*
             * پاک کردن Session کاربر عادی
             */

            unset(
                $_SESSION['user_id'],
                $_SESSION['user_username'],
                $_SESSION['user_fullname'],
                $_SESSION['user_personnel_code'],
                $_SESSION['user_department'],
                $_SESSION['user_department_name'],
                $_SESSION['user_phone'],
                $_SESSION['user_last_activity']
            );


            /*
             * ایجاد Session مدیر / کارشناس
             */

            $_SESSION['admin_id'] = $admin['id'];

            $_SESSION['username'] = $admin['username'];

            $_SESSION['role'] = $admin['role'] ?? "admin";

            $_SESSION['last_activity'] = time();


            header("Location: /neal/");

            exit;

        }


        $error = "نام کاربری یا رمز عبور مدیریت / کارشناس صحیح نیست.";

    }


    /*
     * =====================================================
     * Normal User Login
     * =====================================================
     */

    elseif ($login_type === "user") {

        $stmt = $db->prepare("
            SELECT
                u.*,
                d.name AS department_name
            FROM users u
            LEFT JOIN departments d
                ON d.code = u.department
            WHERE u.username = ?
              AND u.is_active = 1
            LIMIT 1
        ");

        $stmt->execute([$username]);

        $user = $stmt->fetch();


        if ($user && password_verify($password, $user['password'])) {

            session_regenerate_id(true);


            /*
             * پاک کردن Session مدیر / کارشناس
             */

            unset(
                $_SESSION['admin_id'],
                $_SESSION['username'],
                $_SESSION['role'],
                $_SESSION['last_activity']
            );


            /*
             * ایجاد Session کاربر عادی
             */

            $_SESSION['user_id'] = $user['id'];

            $_SESSION['user_username'] = $user['username'];

            $_SESSION['user_fullname'] = $user['fullname'];

            $_SESSION['user_personnel_code'] = $user['personnel_code'];

            $_SESSION['user_department'] = $user['department'];

            $_SESSION['user_department_name'] = $user['department_name'];

            $_SESSION['user_phone'] = $user['phone'];

            $_SESSION['user_last_activity'] = time();


            header("Location: /neal/portal.php");

            exit;

        }


        $error = "نام کاربری یا رمز عبور کاربر صحیح نیست.";

    }


    else {

        $error = "نوع ورود نامعتبر است.";

    }

}

?>
<!DOCTYPE html>

<html lang="fa" dir="rtl">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>ورود به سامانه NEAL</title>


    <style>

        * {
            box-sizing: border-box;
        }


        body {

            margin: 0;

            padding: 0;

            font-family: Tahoma, Arial, sans-serif;

            background:
                linear-gradient(
                    135deg,
                    #eef2f7,
                    #dfe7f1
                );

            min-height: 100vh;

            display: flex;

            align-items: center;

            justify-content: center;

            color: #1f2937;

        }


        .login-wrapper {

            width: 100%;

            max-width: 1050px;

            padding: 30px;

        }


        /*
         * =====================================================
         * NEAL Pharmed Logo
         * =====================================================
         */

        .brand-logo {

            text-align: center;

            margin-bottom: 18px;

        }


        .brand-logo img {

            display: inline-block;

            width: auto;

            height: 105px;

            max-width: 220px;

            object-fit: contain;

        }


        .header {

            text-align: center;

            margin-bottom: 30px;

        }


        .header h1 {

            margin: 0 0 10px 0;

            font-size: 30px;

            color: #17365d;

        }


        .header p {

            margin: 0;

            color: #64748b;

            font-size: 14px;

        }


        .login-cards {

            display: grid;

            grid-template-columns: repeat(2, 1fr);

            gap: 25px;

        }


        .login-card {

            background: #ffffff;

            border-radius: 14px;

            padding: 30px;

            box-shadow:
                0 10px 30px rgba(0,0,0,0.08);

            border: 1px solid #e2e8f0;

        }


        .card-title {

            display: flex;

            align-items: center;

            gap: 12px;

            margin-bottom: 10px;

        }


        .card-icon {

            width: 48px;

            height: 48px;

            min-width: 48px;

            border-radius: 12px;

            display: flex;

            align-items: center;

            justify-content: center;

            background: #eef4ff;

            color: #17365d;

        }


        .card-icon svg {

            width: 28px;

            height: 28px;

            display: block;

        }


        .login-card h2 {

            margin: 0;

            font-size: 20px;

            color: #17365d;

        }


        .login-card .description {

            color: #64748b;

            font-size: 13px;

            margin-bottom: 25px;

            line-height: 1.8;

        }


        .form-group {

            margin-bottom: 18px;

        }


        .form-group label {

            display: block;

            margin-bottom: 7px;

            font-size: 13px;

            font-weight: bold;

            color: #334155;

        }


        .form-group input {

            width: 100%;

            padding: 12px 13px;

            border: 1px solid #cbd5e1;

            border-radius: 8px;

            font-family: Tahoma, Arial, sans-serif;

            font-size: 14px;

            outline: none;

            transition: 0.2s;

        }


        .form-group input:focus {

            border-color: #2563eb;

            box-shadow:
                0 0 0 3px rgba(37,99,235,0.10);

        }


        .login-button {

            width: 100%;

            border: none;

            border-radius: 8px;

            padding: 12px;

            font-family: Tahoma, Arial, sans-serif;

            font-size: 14px;

            font-weight: bold;

            cursor: pointer;

            color: #ffffff;

            background: #17365d;

            transition: 0.2s;

        }


        .login-button:hover {

            background: #0f2948;

        }


        .user-card .card-icon {

            background: #eff6ff;

            color: #2563eb;

        }


        .user-card .login-button {

            background: #2563eb;

        }


        .user-card .login-button:hover {

            background: #1d4ed8;

        }


        .error {

            background: #fee2e2;

            color: #991b1b;

            border: 1px solid #fecaca;

            padding: 12px 14px;

            border-radius: 8px;

            margin-bottom: 20px;

            font-size: 13px;

            line-height: 1.7;

        }


        .timeout {

            background: #fff7ed;

            color: #9a3412;

            border: 1px solid #fed7aa;

            padding: 12px 14px;

            border-radius: 8px;

            margin-bottom: 20px;

            font-size: 13px;

            text-align: center;

        }


        .footer {

            text-align: center;

            margin-top: 25px;

            color: #64748b;

            font-size: 12px;

        }


        @media (max-width: 800px) {

            .login-wrapper {

                padding: 20px;

            }


            .brand-logo img {

                height: 90px;

                max-width: 190px;

            }


            .login-cards {

                grid-template-columns: 1fr;

            }

        }


        @media (max-width: 480px) {

            .login-wrapper {

                padding: 15px;

            }


            .brand-logo img {

                height: 80px;

                max-width: 170px;

            }


            .login-card {

                padding: 22px;

            }


            .header h1 {

                font-size: 25px;

            }

        }

    
/* =========================================================
   NEAL Proxy Manager - Vazirmatn Login Font
   ========================================================= */

@font-face {
    font-family: 'Vazirmatn';
    src: url('/neal/fonts/Vazirmatn-Regular.woff2') format('woff2');
    font-weight: 400;
    font-style: normal;
    font-display: swap;
}

@font-face {
    font-family: 'Vazirmatn';
    src: url('/neal/fonts/Vazirmatn-Medium.woff2') format('woff2');
    font-weight: 500;
    font-style: normal;
    font-display: swap;
}

@font-face {
    font-family: 'Vazirmatn';
    src: url('/neal/fonts/Vazirmatn-SemiBold.woff2') format('woff2');
    font-weight: 600;
    font-style: normal;
    font-display: swap;
}

@font-face {
    font-family: 'Vazirmatn';
    src: url('/neal/fonts/Vazirmatn-Bold.woff2') format('woff2');
    font-weight: 700;
    font-style: normal;
    font-display: swap;
}

body,
body *:not(i):not(.fa):not(.fas):not(.far):not(.fab):not([class*="icon"]) {
    font-family: 'Vazirmatn', Tahoma, Arial, sans-serif !important;
}

input,
textarea,
select,
button {
    font-family: 'Vazirmatn', Tahoma, Arial, sans-serif !important;
}

</style>

</head>


<body>


<div class="login-wrapper">


    <!-- =====================================================
         NEAL Pharmed Logo
         ===================================================== -->

    <div class="brand-logo">

        <img
            src="/neal/images/logo.png"
            alt="NEAL Pharmed"
        >

    </div>


    <div class="header">

        <h1>سامانه NEAL</h1>

        <p>
            سامانه مدیریت خدمات و درخواست‌های فناوری اطلاعات
        </p>

    </div>


    <?php if ($timeout): ?>

        <div class="timeout">

            به دلیل عدم فعالیت، نشست شما منقضی شده است.
            لطفاً مجدداً وارد شوید.

        </div>

    <?php endif; ?>


    <?php if ($error !== ""): ?>

        <div class="error">

            <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>

        </div>

    <?php endif; ?>


    <div class="login-cards">


        <!-- =================================================
             Admin / Expert
             ================================================= -->

        <div class="login-card">


            <div class="card-title">

                <div class="card-icon">

                    <!-- Administrator / Shield Icon -->

                    <svg
                        viewBox="0 0 24 24"
                        fill="none"
                        xmlns="http://www.w3.org/2000/svg"
                        aria-hidden="true"
                    >

                        <path
                            d="M12 3L20 6V11.5C20 16.5 16.8 20.3 12 22C7.2 20.3 4 16.5 4 11.5V6L12 3Z"
                            stroke="currentColor"
                            stroke-width="1.8"
                            stroke-linejoin="round"
                        />

                        <circle
                            cx="12"
                            cy="9"
                            r="2.2"
                            stroke="currentColor"
                            stroke-width="1.8"
                        />

                        <path
                            d="M8.2 16C8.7 13.8 10.1 12.6 12 12.6C13.9 12.6 15.3 13.8 15.8 16"
                            stroke="currentColor"
                            stroke-width="1.8"
                            stroke-linecap="round"
                        />

                    </svg>

                </div>

                <h2>
                    مدیریت / کارشناسان
                </h2>

            </div>


            <div class="description">

                ورود مدیران و کارشناسان فناوری اطلاعات
                به بخش مدیریت سامانه و رسیدگی به درخواست‌ها.

            </div>


            <form method="post">

                <input
                    type="hidden"
                    name="login_type"
                    value="admin"
                >


                <div class="form-group">

                    <label>
                        نام کاربری
                    </label>

                    <input
                        type="text"
                        name="username"
                        autocomplete="username"
                        required
                    >

                </div>


                <div class="form-group">

                    <label>
                        رمز عبور
                    </label>

                    <input
                        type="password"
                        name="password"
                        autocomplete="current-password"
                        required
                    >

                </div>


                <button
                    type="submit"
                    class="login-button"
                >
                    ورود به بخش مدیریت
                </button>

            </form>

        </div>



        <!-- =================================================
             Normal User
             ================================================= -->

        <div class="login-card user-card">


            <div class="card-title">

                <div class="card-icon">

                    <!-- Normal User Icon -->

                    <svg
                        viewBox="0 0 24 24"
                        fill="none"
                        xmlns="http://www.w3.org/2000/svg"
                        aria-hidden="true"
                    >

                        <circle
                            cx="12"
                            cy="8"
                            r="3.2"
                            stroke="currentColor"
                            stroke-width="1.8"
                        />

                        <path
                            d="M5.5 20C6.1 15.9 8.2 13.8 12 13.8C15.8 13.8 17.9 15.9 18.5 20"
                            stroke="currentColor"
                            stroke-width="1.8"
                            stroke-linecap="round"
                        />

                    </svg>

                </div>

                <h2>
                    کاربران
                </h2>

            </div>


            <div class="description">

                ورود کارکنان برای ثبت درخواست،
                مشاهده درخواست‌های خود و پیگیری پاسخ کارشناسان.

            </div>


            <form method="post">

                <input
                    type="hidden"
                    name="login_type"
                    value="user"
                >


                <div class="form-group">

                    <label>
                        نام کاربری
                    </label>

                    <input
                        type="text"
                        name="username"
                        autocomplete="username"
                        required
                    >

                </div>


                <div class="form-group">

                    <label>
                        رمز عبور
                    </label>

                    <input
                        type="password"
                        name="password"
                        autocomplete="current-password"
                        required
                    >

                </div>


                <button
                    type="submit"
                    class="login-button"
                >
                    ورود به پنل کاربری
                </button>

            </form>

        </div>


    </div>


    <div class="footer">

        NEAL Pharmed Pharmaceutical Company

    </div>


</div>


</body>

</html>
