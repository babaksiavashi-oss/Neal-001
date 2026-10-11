<?php

require_once "auth/auth.php";

check_login();

$page_title = "Reports";


$sarg_base = "/usr/local/www/sarg";

$year = date("Y");
$month = date("m");

$report_path = $sarg_base . "/" . $year . "/" . $month;

$reports = array();


if (is_dir($report_path)) {

    foreach (scandir($report_path) as $dir) {

        if ($dir == "." || $dir == "..") {
            continue;
        }

        if (is_dir($report_path . "/" . $dir)) {
            $reports[] = $dir;
        }

    }

}


usort($reports, function($a, $b) {

    preg_match('/(\d+)-?(\d*)/', $a, $ma);
    preg_match('/(\d+)-?(\d*)/', $b, $mb);

    $da = isset($ma[2]) && $ma[2] != ""
        ? intval($ma[2])
        : intval($ma[1]);

    $db = isset($mb[2]) && $mb[2] != ""
        ? intval($mb[2])
        : intval($mb[1]);

    return $db - $da;

});


include "includes/header.php";

?>


<h1>گزارش مصرف اینترنت</h1>


<!-- Summary -->

<div class="blacklist-summary">

    <span class="summary-icon">📊</span>

    <div class="summary-text">

        <span class="summary-label">
            تعداد گزارش‌ها
        </span>

        <strong>
            <?php echo count($reports); ?>
        </strong>

    </div>

</div>


<!-- Latest Report -->

<div class="card">

    <h2>📅 آخرین گزارش</h2>

    <?php if (count($reports) > 0): ?>

        <div style="display:flex;align-items:center;justify-content:space-between;gap:20px;flex-wrap:wrap;">

            <div>

                <div style="color:#667085;font-size:13px;margin-bottom:6px;">
                    جدیدترین گزارش موجود
                </div>

                <strong style="font-size:20px;color:#0b1f3a;">
                    <?php echo htmlspecialchars($reports[0]); ?>
                </strong>

            </div>


            <a
                class="btn btn-primary"
                target="_blank"
                href="/sarg/<?php echo $year; ?>/<?php echo $month; ?>/<?php echo urlencode($reports[0]); ?>/index.html"
            >
                👁️ مشاهده آخرین گزارش
            </a>

        </div>

    <?php else: ?>

        <div class="alert alert-error">
            گزارشی برای ماه جاری موجود نیست.
        </div>

    <?php endif; ?>

</div>


<!-- Reports List -->

<div class="card">

    <h2>📋 گزارش‌های SARG</h2>


    <?php if (count($reports) > 0): ?>

    <table class="data-table">

        <thead>

            <tr>

                <th>ردیف</th>

                <th>تاریخ گزارش</th>

                <th>وضعیت</th>

                <th>مشاهده</th>

            </tr>

        </thead>


        <tbody>

        <?php $counter = 1; ?>

        <?php foreach ($reports as $report): ?>

            <tr>

                <td>
                    <?php echo $counter++; ?>
                </td>


                <td>

                    <strong>
                        <?php echo htmlspecialchars($report); ?>
                    </strong>

                </td>


                <td>

                    <span class="badge badge-admin">
                        موجود
                    </span>

                </td>


                <td>

                    <a
                        class="btn btn-primary"
                        target="_blank"
                        href="/sarg/<?php echo $year; ?>/<?php echo $month; ?>/<?php echo urlencode($report); ?>/index.html"
                    >
                        👁️ مشاهده
                    </a>

                </td>

            </tr>

        <?php endforeach; ?>

        </tbody>

    </table>

    <?php else: ?>

        <div class="alert alert-error">
            هیچ گزارش SARG برای ماه جاری پیدا نشد.
        </div>

    <?php endif; ?>


</div>


<?php include "includes/footer.php"; ?>
