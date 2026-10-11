<?php

require_once "auth/auth.php";

check_login();

$page_title = "Cache";

include "includes/header.php";


/*
 * Cache Status
 */

$cache_path = "/var/squid/cache";

$cache_exists = is_dir($cache_path);


/*
 * Cache Directory Size
 */

if ($cache_exists) {

    $cache_size = trim(
        (string) shell_exec(
            "du -sh " . escapeshellarg($cache_path) .
            " 2>/dev/null | awk '{print \$1}'"
        )
    );

    if ($cache_size === "") {
        $cache_size = "N/A";
    }

} else {

    $cache_size = "N/A";

}


/*
 * Number of Files
 */

if ($cache_exists) {

    $file_count = (int) trim(
        (string) shell_exec(
            "find " . escapeshellarg($cache_path) .
            " -type f 2>/dev/null | wc -l"
        )
    );

} else {

    $file_count = 0;

}


/*
 * Check cache_dir
 */

$cache_dir = trim(
    (string) shell_exec(
        "grep -E '^[[:space:]]*cache_dir' " .
        "/usr/local/etc/squid/squid.conf 2>/dev/null"
    )
);

$cache_enabled = ($cache_dir !== "");


/*
 * Squid Status
 */

$squid_status = trim(
    (string) shell_exec(
        "service squid status 2>&1"
    )
);

$squid_running = (
    strpos($squid_status, "is running") !== false
);

/*
 * Cache Hit / Miss Statistics
 */

$cache_hit = (int) trim(
    (string) shell_exec(
        "awk '\$4 ~ /^TCP_(HIT|MEM_HIT|REFRESH_UNMODIFIED)/ { hit++ } END { print hit+0 }' " .
        "/var/log/squid/access.log 2>/dev/null"
    )
);


$cache_miss = (int) trim(
    (string) shell_exec(
        "awk '\$4 ~ /^TCP_(MISS|REFRESH_MODIFIED)/ { miss++ } END { print miss+0 }' " .
        "/var/log/squid/access.log 2>/dev/null"
    )
);


$cache_total = $cache_hit + $cache_miss;


if ($cache_total > 0) {

    $cache_hit_rate = round(
        ($cache_hit / $cache_total) * 100,
        2
    );

} else {

    $cache_hit_rate = 0;

}


/*
 * Log Files
 */

$access_log = "/var/log/squid/access.log";
$cache_log  = "/var/log/squid/cache.log";


/*
 * Access Log Size
 */

if (file_exists($access_log)) {

    $access_log_size = trim(
        (string) shell_exec(
            "du -sh " . escapeshellarg($access_log) .
            " 2>/dev/null | awk '{print \$1}'"
        )
    );

    if ($access_log_size === "") {
        $access_log_size = "N/A";
    }

} else {

    $access_log_size = "N/A";

}


/*
 * Cache Log Size
 */

if (file_exists($cache_log)) {

    $cache_log_size = trim(
        (string) shell_exec(
            "du -sh " . escapeshellarg($cache_log) .
            " 2>/dev/null | awk '{print \$1}'"
        )
    );

    if ($cache_log_size === "") {
        $cache_log_size = "N/A";
    }

} else {

    $cache_log_size = "N/A";

}


/*
 * Refresh Patterns
 */

$refresh_patterns = trim(
    (string) shell_exec(
        "grep -E '^[[:space:]]*refresh_pattern' " .
        "/usr/local/etc/squid/squid.conf 2>/dev/null"
    )
);

if ($refresh_patterns === "") {
    $refresh_patterns = "هیچ Refresh Patternای تعریف نشده است.";
}

?>

<h1>وضعیت Cache Squid</h1>

<div class="card">

    <div class="section-header">

        <h2>آمار Cache</h2>

        <span class="section-badge">
            Live Statistics
        </span>

    </div>


<div class="cache-stats-grid">

    <div class="cache-stat-card cache-stat-hit">

        <span class="cache-stat-title">
            Cache Hit
        </span>

        <span class="cache-stat-value">
            <?= number_format($cache_hit) ?>
        </span>

        <span class="cache-stat-unit">
            درخواست موفق از Cache
        </span>

    </div>


    <div class="cache-stat-card cache-stat-miss">

        <span class="cache-stat-title">
            Cache Miss
        </span>

        <span class="cache-stat-value">
            <?= number_format($cache_miss) ?>
        </span>

        <span class="cache-stat-unit">
            درخواست دریافت‌شده از Internet
        </span>

    </div>


    <div class="cache-stat-card cache-stat-total">

        <span class="cache-stat-title">
            قابل ارزیابی
        </span>

        <span class="cache-stat-value">
            <?= number_format($cache_total) ?>
        </span>

        <span class="cache-stat-unit">
            Hit + Miss
        </span>

    </div>


    <div class="cache-stat-card cache-stat-rate">

        <span class="cache-stat-title">
            Hit Rate
        </span>

        <span class="cache-stat-value">
            <?= number_format($cache_hit_rate, 2) ?>%
        </span>

        <span class="cache-stat-unit">
            نسبت استفاده از Cache
        </span>

    </div>

</div>
       


       

</div>

<div class="card">

    <div class="section-header">

        <div>
            <h2>وضعیت فعلی Cache</h2>
        </div>

        <span class="section-badge">
            <?= $squid_running ? "Squid Running" : "Squid Stopped" ?>
        </span>

    </div>


    <div class="logs-status-box">


        <div class="stat">

            <strong>وضعیت Squid</strong>

            <span>
                <?= $squid_running ? "فعال" : "متوقف" ?>
            </span>

        </div>


        <div class="stat">

            <strong>Disk Cache</strong>

            <span>
                <?= $cache_enabled ? "فعال" : "غیرفعال" ?>
            </span>

        </div>


        <div class="stat">

            <strong>حجم مسیر Cache</strong>

            <span>
                <?= htmlspecialchars($cache_size) ?>
            </span>

        </div>


        <div class="stat">

            <strong>تعداد فایل‌ها</strong>

            <span>
                <?= number_format($file_count) ?>
            </span>

        </div>


        <div class="stat">

            <strong>Access Log</strong>

            <span>
                <?= htmlspecialchars($access_log_size) ?>
            </span>

        </div>


        <div class="stat">

            <strong>Cache Log</strong>

            <span>
                <?= htmlspecialchars($cache_log_size) ?>
            </span>

        </div>


    </div>

</div>



<div class="card">

    <div class="section-header">

        <h2>مسیر Cache</h2>

    </div>


    <p class="log-line log-info">

        <?= htmlspecialchars($cache_path) ?>

    </p>

</div>



<div class="card">

    <div class="section-header">

        <h2>وضعیت Disk Cache</h2>

    </div>


    <?php if ($cache_enabled): ?>


        <p class="log-line log-success">

            Disk Cache در Squid فعال است.

        </p>


        <p>
            تنظیم فعلی:
        </p>


        <p class="log-line">

            <?= htmlspecialchars($cache_dir) ?>

        </p>


    <?php else: ?>


        <p class="log-line log-warning">

            Disk Cache در تنظیمات Squid فعال نیست.

        </p>


        <p>

            در حال حاضر مسیر

            <strong>/var/squid/cache</strong>

            برای ذخیره Objectهای Cache به عنوان

            <code>cache_dir</code>

            تعریف نشده است.

        </p>


    <?php endif; ?>


</div>



<div class="card">

    <div class="section-header">

        <h2>قوانین Cache</h2>

        <span class="section-badge">
            Refresh Pattern
        </span>

    </div>


    <div class="logs-status-box">


        <div class="stat">

            <strong>FTP</strong>

            <span>
                ۱ تا ۷ روز
            </span>

        </div>


        <div class="stat">

            <strong>CGI / Query</strong>

            <span>
                بدون Cache
            </span>

        </div>


        <div class="stat">

            <strong>سایر محتوا</strong>

            <span>
                تا ۳ روز
            </span>

        </div>


    </div>


    <div style="margin-top: 18px;">

        <p>
            تنظیمات واقعی Squid:
        </p>

        <pre class="log-line"><?= htmlspecialchars($refresh_patterns) ?></pre>

    </div>

</div>


<?php include "includes/footer.php"; ?>
