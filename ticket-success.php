<?php

$portal_mode = true;

require_once "auth/auth.php";

check_user_login();

$page_title = "درخواست با موفقیت ثبت شد";

$request_id = isset($_GET['id'])
    ? (int)$_GET['id']
    : 0;

$ticket = null;

if ($request_id > 0) {

    $db = new PDO(
        "sqlite:" . __DIR__ . "/data/neal.db"
    );

    $db->setAttribute(
        PDO::ATTR_ERRMODE,
        PDO::ERRMODE_EXCEPTION
    );

    /*
     * کاربر فقط می‌تواند درخواست متعلق به
     * username خودش را مشاهده کند.
     */
    $stmt = $db->prepare(
        "SELECT
            id,
            tracking_number,
            subject,
            status,
            created_at
         FROM service_requests
         WHERE id = ?
           AND requester_username = ?
         LIMIT 1"
    );

    $stmt->execute([
        $request_id,
        $_SESSION['user_username']
    ]);

    $ticket = $stmt->fetch(PDO::FETCH_ASSOC);
}

require_once "includes/header.php";

?>

<div class="card ticket-success-page">

    <?php if ($ticket): ?>

        <div class="success-icon">
            ✓
        </div>

        <h1>
            درخواست شما با موفقیت ثبت شد
        </h1>

        <p class="success-text">
            درخواست شما در سامانه خدمات فناوری اطلاعات ثبت گردید.
        </p>

        <div class="tracking-box">

            <span>
                شماره پیگیری
            </span>

            <strong>
                <?php
                echo htmlspecialchars(
                    $ticket['tracking_number'],
                    ENT_QUOTES,
                    'UTF-8'
                );
                ?>
            </strong>

        </div>

        <div class="ticket-summary">

            <div>

                <span>
                    موضوع
                </span>

                <strong>
                    <?php
                    echo htmlspecialchars(
                        $ticket['subject'],
                        ENT_QUOTES,
                        'UTF-8'
                    );
                    ?>
                </strong>

            </div>

            <div>

                <span>
                    وضعیت
                </span>

                <strong>
                    جدید
                </strong>

            </div>

        </div>

        <div class="success-actions">

            <a
                href="/neal/my-tickets.php"
                class="success-btn primary"
            >
                📋 مشاهده درخواست‌های من
            </a>

            <a
                href="/neal/service-request.php"
                class="success-btn"
            >
                ➕ ثبت درخواست جدید
            </a>

            <a
                href="/neal/portal.php"
                class="success-btn"
            >
                🏠 بازگشت به پرتال
            </a>

        </div>

        <div class="success-note">
            شماره پیگیری را برای پیگیری‌های بعدی نزد خود نگه دارید.
        </div>

    <?php else: ?>

        <div class="success-icon error-icon">
            !
        </div>

        <h1>
            درخواست مورد نظر پیدا نشد
        </h1>

        <p class="success-text">
            این درخواست وجود ندارد یا متعلق به حساب کاربری شما نیست.
        </p>

        <div class="success-actions">

            <a
                href="/neal/my-tickets.php"
                class="success-btn primary"
            >
                📋 درخواست‌های من
            </a>

            <a
                href="/neal/portal.php"
                class="success-btn"
            >
                🏠 بازگشت به پرتال
            </a>

        </div>

    <?php endif; ?>

</div>

<style>

.ticket-success-page {
    max-width: 760px;
    margin: 40px auto;
    padding: 45px 35px;
    text-align: center;
}

.success-icon {
    width: 70px;
    height: 70px;
    margin: 0 auto 20px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    background: #ecfdf3;
    color: #039855;
    font-size: 38px;
    font-weight: bold;
}

.error-icon {
    background: #fff4f4;
    color: #d92d20;
}

.ticket-success-page h1 {
    margin: 0;
    color: #0b1f3a;
    font-size: 23px;
}

.success-text {
    margin: 12px 0 25px;
    color: #667085;
    font-size: 13px;
}

.tracking-box {
    padding: 22px;
    border: 1px solid #d0d5dd;
    border-radius: 12px;
    background: #f8fafc;
}

.tracking-box span {
    display: block;
    margin-bottom: 8px;
    color: #667085;
    font-size: 12px;
}

.tracking-box strong {
    direction: ltr;
    display: block;
    color: #0b1f3a;
    font-family: Arial, sans-serif;
    font-size: 30px;
    letter-spacing: 1px;
}

.ticket-summary {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px;
    margin-top: 18px;
}

.ticket-summary > div {
    padding: 15px;
    text-align: right;
    border: 1px solid #eaecf0;
    border-radius: 10px;
}

.ticket-summary span {
    display: block;
    margin-bottom: 6px;
    color: #667085;
    font-size: 11px;
}

.ticket-summary strong {
    color: #344054;
    font-size: 12px;
}

.success-actions {
    display: flex;
    justify-content: center;
    flex-wrap: wrap;
    gap: 10px;
    margin-top: 28px;
}

.success-btn {
    display: inline-block;
    padding: 11px 18px;
    border-radius: 8px;
    background: #f2f4f7;
    color: #344054;
    text-decoration: none;
    font-size: 12px;
}

.success-btn.primary {
    background: #0b1f3a;
    color: #ffffff;
}

.success-note {
    margin-top: 22px;
    color: #667085;
    font-size: 11px;
}

@media (max-width: 600px) {

    .ticket-summary {
        grid-template-columns: 1fr;
    }

    .ticket-success-page {
        padding: 30px 20px;
    }

}

</style>

<?php

require_once "includes/footer.php";

?>
