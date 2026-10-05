<?php

/*
 * =========================================================
 * NEAL Proxy Manager
 * Authentication / Session Management
 * =========================================================
 */

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}


/*
 * =========================================================
 * Session Timeout
 * =========================================================
 */

$session_timeout = 3600;


/*
 * =========================================================
 * Detect Current Session Type
 * =========================================================
 */

$is_admin_session = isset($_SESSION['admin_id']);

$is_user_session = isset($_SESSION['user_id']);


/*
 * =========================================================
 * Session Idle Timeout
 * =========================================================
 */

if ($is_admin_session) {

    if (
        isset($_SESSION['last_activity']) &&
        (time() - $_SESSION['last_activity']) >= $session_timeout
    ) {

        session_unset();
        session_destroy();

        header("Location: /neal/login.php?timeout=1");

        exit;
    }

    $_SESSION['last_activity'] = time();

}


elseif ($is_user_session) {

    if (
        isset($_SESSION['user_last_activity']) &&
        (time() - $_SESSION['user_last_activity']) >= $session_timeout
    ) {

        session_unset();
        session_destroy();

        header("Location: /neal/login.php?timeout=1");

        exit;
    }

    $_SESSION['user_last_activity'] = time();

}


/*
 * =========================================================
 * Check Admin Login
 * =========================================================
 */

function check_login()
{

    if (!isset($_SESSION['admin_id'])) {

        /*
         * اگر کاربر عادی بخواهد مستقیماً
         * وارد بخش مدیریتی شود،
         * اجازه ورود ندارد.
         */

        if (isset($_SESSION['user_id'])) {

            header("Location: /neal/portal.php");

            exit;

        }


        header("Location: /neal/login.php");

        exit;

    }

}


/*
 * =========================================================
 * Check User Login
 * =========================================================
 */

function check_user_login()
{

    if (!isset($_SESSION['user_id'])) {

        /*
         * اگر مدیر/کارشناس وارد صفحه‌ای از
         * بخش کاربران شود، او را به داشبورد برگردان.
         */

        if (isset($_SESSION['admin_id'])) {

            header("Location: /neal/");

            exit;

        }


        header("Location: /neal/login.php");

        exit;

    }

}


/*
 * =========================================================
 * Check Any Login
 * =========================================================
 */

function is_logged_in()
{

    return (
        isset($_SESSION['admin_id']) ||
        isset($_SESSION['user_id'])
    );

}


/*
 * =========================================================
 * Is Admin
 * =========================================================
 */

function is_admin_logged_in()
{

    return isset($_SESSION['admin_id']);

}


/*
 * =========================================================
 * Is Normal User
 * =========================================================
 */

function is_user_logged_in()
{

    return isset($_SESSION['user_id']);

}


/*
 * =========================================================
 * Logout
 * =========================================================
 */

function logout()
{

    $_SESSION = array();


    if (ini_get("session.use_cookies")) {

        $params = session_get_cookie_params();

        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params["path"],
            $params["domain"],
            $params["secure"],
            $params["httponly"]
        );

    }


    session_destroy();

}

?>
