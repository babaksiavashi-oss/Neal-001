<?php

require_once "auth/auth.php";

check_login();

$page_title = "BlackList";

$file = "/usr/local/etc/squid/blocked_sites.txt";

$message = "";


/* Add new domain */

if(isset($_POST['add'])) {

    $domain = trim($_POST['domain']);

    if($domain != "") {

        if($domain[0] != ".") {
            $domain = "." . $domain;
        }

        $sites = file(
            $file,
            FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES
        );

        if(!in_array($domain, $sites)) {

            file_put_contents(
                $file,
                $domain . PHP_EOL,
                FILE_APPEND
            );

            header("Location: blocked.php");
            exit;

        }

    }

}


/* Delete domain */

if(isset($_GET['delete'])) {

    $delete = $_GET['delete'];

    $lines = file(
        $file,
        FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES
    );

    $new = array();

    foreach($lines as $line) {

        if(trim($line) != trim($delete)) {

            $new[] = $line;

        }

    }

    file_put_contents(
        $file,
        implode(PHP_EOL,$new) . PHP_EOL
    );

    header("Location: blocked.php");
    exit;

}


/* Reload Squid */

if(isset($_POST['reload'])) {

    shell_exec("service squid reload");

    header("Location: blocked.php");
    exit;

}


$sites = file(
    $file,
    FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES
);


include "includes/header.php";

?>


<h1>مدیریت سایت‌های مسدود شده</h1>

<div class="blacklist-summary">

    <span class="summary-icon">🚫</span>

    <div class="summary-text">
        <span class="summary-label">سایت‌های مسدود</span>
        <strong><?php echo count($sites); ?></strong>
    </div>

</div>


<div class="card">

    <h2>➕ افزودن سایت به BlackList</h2>

    <form method="post">

        <div class="form-group">

            <label for="domain">
                نام دامنه
            </label>

            <input
                id="domain"
                class="form-control"
                type="text"
                name="domain"
                placeholder=".example.com"
                autocomplete="off"
                required
            >

        </div>

        <button
            type="submit"
            name="add"
            class="btn btn-primary"
        >
            ➕ افزودن سایت
        </button>

    </form>

</div>


<div class="card">

    <h2>⚙️ اعمال تغییرات در Squid</h2>

    <p style="color:#667085;">
        پس از افزودن یا حذف سایت‌ها، برای اعمال تغییرات روی Squid
        دکمه زیر را انتخاب کنید.
    </p>

    <form method="post">

        <button
            type="submit"
            name="reload"
            class="btn btn-success"
        >
            🔄 Reload Squid
        </button>

    </form>

</div>


<div class="card">

    <h2>🚫 لیست سایت‌های مسدود شده</h2>

    <?php if(count($sites) > 0): ?>

    <table class="data-table">

        <thead>

            <tr>

                <th>ردیف</th>

                <th>دامنه</th>

                <th>عملیات</th>

            </tr>

        </thead>

        <tbody>

        <?php $counter = 1; ?>

        <?php foreach($sites as $site): ?>

            <tr>

                <td>
                    <?php echo $counter++; ?>
                </td>

                <td>
                    <strong>
                        <?php echo htmlspecialchars($site); ?>
                    </strong>
                </td>

                <td>

                    <a
                        class="btn-danger"
                        href="?delete=<?php echo urlencode($site); ?>"
                        onclick="return confirm('آیا از حذف این سایت مطمئن هستید؟');"
                    >
                        حذف
                    </a>

                </td>

            </tr>

        <?php endforeach; ?>

        </tbody>

    </table>

    <?php else: ?>

        <div class="alert alert-success">
            در حال حاضر هیچ سایتی در BlackList وجود ندارد.
        </div>

    <?php endif; ?>

</div>


<?php include "includes/footer.php"; ?>
