<?php

$portal_mode = true;

require_once "auth/auth.php";

check_user_login();

$page_title = "پرتال خدمات فناوری اطلاعات";


/*
 * =========================================================
 * Database
 * =========================================================
 */

$db = new PDO(
    "sqlite:" . __DIR__ . "/data/neal.db"
);

$db->setAttribute(
    PDO::ATTR_ERRMODE,
    PDO::ERRMODE_EXCEPTION
);

$db->setAttribute(
    PDO::ATTR_DEFAULT_FETCH_MODE,
    PDO::FETCH_ASSOC
);


/*
 * =========================================================
 * Current User
 * =========================================================
 */

$current_username = $_SESSION['user_username'] ?? "";


/*
 * =========================================================
 * Ticket Statistics
 * =========================================================
 */

$stats = [
    'total'   => 0,
    'active'  => 0,
    'waiting' => 0,
    'closed'  => 0
];


if ($current_username !== "") {

    /*
     * کل درخواست‌ها
     */

    $stmt = $db->prepare("
        SELECT COUNT(*)
        FROM service_requests
        WHERE requester_username = ?
    ");

    $stmt->execute([
        $current_username
    ]);

    $stats['total'] = (int)$stmt->fetchColumn();


    /*
     * درخواست‌های در حال بررسی
     *
     * assigned
     * in_progress
     */

    $stmt = $db->prepare("
        SELECT COUNT(*)
        FROM service_requests
        WHERE requester_username = ?
          AND status IN ('assigned', 'in_progress')
    ");

    $stmt->execute([
        $current_username
    ]);

    $stats['active'] = (int)$stmt->fetchColumn();


    /*
     * منتظر پاسخ کاربر
     */

    $stmt = $db->prepare("
        SELECT COUNT(*)
        FROM service_requests
        WHERE requester_username = ?
          AND status = 'waiting_user'
    ");

    $stmt->execute([
        $current_username
    ]);

    $stats['waiting'] = (int)$stmt->fetchColumn();


    /*
     * درخواست‌های بسته‌شده
     */

    $stmt = $db->prepare("
        SELECT COUNT(*)
        FROM service_requests
        WHERE requester_username = ?
          AND status = 'closed'
    ");

    $stmt->execute([
        $current_username
    ]);

    $stats['closed'] = (int)$stmt->fetchColumn();

}

?>

<?php include "includes/header.php"; ?>


<div class="user-portal">


    <div class="portal-welcome">

        <div>

            <h1>
                پرتال خدمات فناوری اطلاعات
            </h1>

            <p>
                به سامانه خدمات فناوری اطلاعات خوش آمدید.
                از این بخش می‌توانید درخواست خود را ثبت و پیگیری کنید.
            </p>

        </div>


        <div class="portal-user">

            👤

            <?php
            echo htmlspecialchars(
                $_SESSION['user_fullname']
                    ?? $_SESSION['user_username']
                    ?? '',
                ENT_QUOTES,
                'UTF-8'
            );
            ?>

        </div>

    </div>



    <div class="portal-actions">


        <a
            href="service-request.php"
            class="portal-action primary"
        >

            <div class="portal-action-icon">
                📝
            </div>

            <div>

                <strong>
                    درخواست جدید
                </strong>

                <span>
                    ثبت درخواست یا اعلام مشکل جدید
                </span>

            </div>

        </a>



        <a
            href="my-tickets.php"
            class="portal-action"
        >

            <div class="portal-action-icon">
                🎫
            </div>

            <div>

                <strong>
                    درخواست‌های من
                </strong>

                <span>
                    مشاهده و پیگیری درخواست‌های ثبت‌شده
                </span>

            </div>

        </a>


    </div>



    <div class="portal-section">


        <div class="portal-section-title">

            <div>

                <h2>
                    وضعیت درخواست‌ها
                </h2>

                <span>
                    خلاصه درخواست‌های شما
                </span>

            </div>

        </div>



        <div class="portal-status-grid">


            <div class="portal-status">

                <span>
                    کل درخواست‌ها
                </span>

                <strong>
                    <?= $stats['total'] ?>
                </strong>

            </div>



            <div class="portal-status">

                <span>
                    در حال بررسی
                </span>

                <strong>
                    <?= $stats['active'] ?>
                </strong>

            </div>



            <div class="portal-status">

                <span>
                    منتظر پاسخ شما
                </span>

                <strong>
                    <?= $stats['waiting'] ?>
                </strong>

            </div>



            <div class="portal-status">

                <span>
                    بسته‌شده
                </span>

                <strong>
                    <?= $stats['closed'] ?>
                </strong>

            </div>


        </div>

    </div>



    <div class="portal-section">


        <div class="portal-section-title">

            <div>

                <h2>
                    دسترسی سریع
                </h2>

                <span>
                    خدمات پرکاربرد فناوری اطلاعات
                </span>

            </div>

        </div>



        <div class="portal-services">


            <a href="service-request.php">

                💻

                <span>
                    مشکل کامپیوتر
                </span>

            </a>



            <a href="service-request.php">

                🌐

                <span>
                    مشکل شبکه و اینترنت
                </span>

            </a>



            <a href="service-request.php">

                🖨️

                <span>
                    مشکل چاپگر
                </span>

            </a>



            <a href="service-request.php">

                🔐

                <span>
                    دسترسی و حساب کاربری
                </span>

            </a>



            <a href="service-request.php">

                📧

                <span>
                    ایمیل و نرم‌افزار
                </span>

            </a>



            <a href="service-request.php">

                ❓

                <span>
                    سایر درخواست‌ها
                </span>

            </a>


        </div>

    </div>



    <div class="portal-notice">


        <div class="portal-notice-icon">
            ℹ️
        </div>


        <div>

            <strong>
                نکته
            </strong>

            <p>

                در صورت بروز مشکل، ابتدا درخواست خود را ثبت کنید.
                کارشناسان فناوری اطلاعات پس از بررسی، پاسخ و وضعیت درخواست
                را در همین سامانه اعلام خواهند کرد.

            </p>

        </div>


    </div>


</div>



<style>

.user-portal {
    max-width: 1400px;
    margin: 0 auto;
}


.portal-welcome {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 25px;
    margin-bottom: 28px;
}


.portal-welcome h1 {
    margin: 0 0 10px 0;
    color: #0b1f3a;
    font-size: 26px;
}


.portal-welcome p {
    margin: 0;
    color: #718096;
    font-size: 14px;
}


.portal-user {
    background: white;
    border: 1px solid #e9eef5;
    border-radius: 10px;
    padding: 11px 18px;
    color: #334e68;
    font-size: 14px;
    white-space: nowrap;
}


.portal-actions {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 20px;
    margin-bottom: 25px;
}


.portal-action {
    display: flex;
    align-items: center;
    gap: 18px;
    background: white;
    border: 1px solid #e9eef5;
    border-radius: 14px;
    padding: 24px;
    text-decoration: none;
    color: #0b1f3a;
    box-shadow: 0 3px 12px rgba(0,0,0,.04);
    transition: .2s;
}


.portal-action:hover {
    transform: translateY(-2px);
    box-shadow: 0 7px 20px rgba(0,0,0,.08);
}


.portal-action.primary {
    background: #0b1f3a;
    color: white;
}


.portal-action-icon {
    width: 55px;
    height: 55px;
    border-radius: 12px;
    background: rgba(255,255,255,.12);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 26px;
    flex-shrink: 0;
}


.portal-action:not(.primary) .portal-action-icon {
    background: #f4f7fb;
}


.portal-action strong {
    display: block;
    font-size: 17px;
    margin-bottom: 7px;
}


.portal-action span {
    display: block;
    font-size: 13px;
    opacity: .75;
}


.portal-section {
    background: white;
    border: 1px solid #e9eef5;
    border-radius: 14px;
    padding: 24px;
    margin-bottom: 25px;
    box-shadow: 0 3px 12px rgba(0,0,0,.04);
}


.portal-section-title {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding-bottom: 18px;
    border-bottom: 1px solid #edf1f5;
    margin-bottom: 20px;
}


.portal-section-title h2 {
    margin: 0 0 6px 0;
    color: #0b1f3a;
    font-size: 18px;
}


.portal-section-title span {
    color: #718096;
    font-size: 13px;
}


.portal-status-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 15px;
}


.portal-status {
    background: #f8fafc;
    border-radius: 10px;
    padding: 18px;
    text-align: center;
}


.portal-status span {
    display: block;
    color: #718096;
    font-size: 13px;
    margin-bottom: 8px;
}


.portal-status strong {
    color: #0b1f3a;
    font-size: 24px;
}


.portal-services {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 12px;
}


.portal-services a {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 16px;
    background: #f8fafc;
    border-radius: 9px;
    color: #334e68;
    text-decoration: none;
    font-size: 14px;
    transition: .2s;
}


.portal-services a:hover {
    background: #edf2f7;
    transform: translateY(-1px);
}


.portal-services a:first-letter {
    font-size: 20px;
}


.portal-notice {
    display: flex;
    align-items: flex-start;
    gap: 15px;
    background: #eef6ff;
    border-right: 4px solid #3182ce;
    border-radius: 10px;
    padding: 18px 20px;
    margin-bottom: 30px;
}


.portal-notice-icon {
    font-size: 20px;
}


.portal-notice strong {
    display: block;
    color: #1a365d;
    margin-bottom: 5px;
}


.portal-notice p {
    margin: 0;
    color: #4a5568;
    font-size: 13px;
    line-height: 1.8;
}


@media (max-width: 900px) {

    .portal-actions {
        grid-template-columns: 1fr;
    }

    .portal-status-grid {
        grid-template-columns: repeat(2, 1fr);
    }

    .portal-services {
        grid-template-columns: repeat(2, 1fr);
    }

}


@media (max-width: 600px) {

    .portal-welcome {
        flex-direction: column;
        align-items: stretch;
    }

    .portal-status-grid,
    .portal-services {
        grid-template-columns: 1fr;
    }

}

</style>



<?php include "includes/footer.php"; ?>
