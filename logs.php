<?php

require_once "auth/auth.php";

check_login();

$page_title = "Logs";


/* =========================
   Squid Log Configuration
   ========================= */

$squid_conf = "/usr/local/etc/squid/squid.conf";

$access_log = "/var/log/squid/access.log";
$cache_log  = "/var/log/squid/cache.log";


if (file_exists($squid_conf)) {

    $config = file(
        $squid_conf,
        FILE_IGNORE_NEW_LINES
    );

    foreach ($config as $line) {

        $line = trim($line);

        if (
            $line === "" ||
            strpos($line, "#") === 0
        ) {
            continue;
        }


        if (
            preg_match(
                '/^access_log\s+(.+)$/i',
                $line,
                $m
            )
        ) {

            $access_log = trim($m[1]);

        }


        if (
            preg_match(
                '/^cache_log\s+(.+)$/i',
                $line,
                $m
            )
        ) {

            $cache_log = trim($m[1]);

        }

    }

}


/* =========================
   Helper Functions
   ========================= */

function log_size($file)
{

    if (!file_exists($file)) {
        return "یافت نشد";
    }


    $size = filesize($file);


    if ($size < 1024) {
        return $size . " B";
    }


    if ($size < 1024 * 1024) {
        return round($size / 1024, 1) . " KB";
    }


    return round(
        $size / (1024 * 1024),
        1
    ) . " MB";

}


function log_tail($file, $lines = 30)
{

    if (!is_readable($file)) {
        return array(
            "خطا: فایل قابل خواندن نیست."
        );
    }


    $output = array();


    exec(
        "tail -n " .
        intval($lines) .
        " " .
        escapeshellarg($file) .
        " 2>/dev/null",
        $output
    );


    return $output;

}


$access_lines = log_tail(
    $access_log,
    30
);


$cache_lines = log_tail(
    $cache_log,
    30
);


/* =========================
   Cache Log Error Detection
   ========================= */

$cache_errors = 0;


foreach ($cache_lines as $line) {

    if (
        stripos($line, "ERROR") !== false ||
        stripos($line, "WARNING") !== false
    ) {

        $cache_errors++;

    }

}


include "includes/header.php";

?>


<h1>لاگ‌های سیستم</h1>

<p>
مشاهده آخرین رویدادهای Squid و بررسی وضعیت لاگ‌ها
</p>


<!-- =========================
     Log Status
     ========================= -->

<div class="dashboard-box logs-status-box">


<div class="stat dashboard-stat">

<div class="stat-icon">
🌐
</div>

<div class="stat-content">

<span class="stat-label">
Access Log
</span>

<strong>
<?php
echo htmlspecialchars(
    log_size($access_log)
);
?>
</strong>

</div>

</div>


<div class="stat dashboard-stat">

<div class="stat-icon">
🦑
</div>

<div class="stat-content">

<span class="stat-label">
Cache Log
</span>

<strong>
<?php
echo htmlspecialchars(
    log_size($cache_log)
);
?>
</strong>

</div>

</div>


<div class="stat dashboard-stat">

<div class="stat-icon">
📄
</div>

<div class="stat-content">

<span class="stat-label">
Access Entries
</span>

<strong>
30 خط آخر
</strong>

</div>

</div>


<div class="stat dashboard-stat">

<div class="stat-icon">
<?php
echo $cache_errors > 0
    ? "⚠️"
    : "✅";
?>
</div>

<div class="stat-content">

<span class="stat-label">
وضعیت Cache Log
</span>

<strong>

<?php

if ($cache_errors > 0) {

    echo "نیازمند بررسی";

} else {

    echo "بدون خطای اخیر";

}

?>

</strong>

</div>

</div>


</div>


<!-- =========================
     Refresh
     ========================= -->

<div class="card log-toolbar">

<div>

<strong>
آخرین ۳۰ خط لاگ
</strong>

<p>
برای مشاهده جدیدترین رویدادها صفحه را Refresh کنید.
</p>

</div>


<a
href="logs.php"
class="btn-primary"
>
🔄 بروزرسانی
</a>


</div>


<!-- =========================
     Access Log
     ========================= -->

<div class="card">


<div class="section-header">

<h2>
🌐 Access Log
</h2>

<span class="section-badge">
<?php
echo htmlspecialchars(
    $access_log
);
?>
</span>

</div>


<div class="log-viewer">

<?php foreach ($access_lines as $line): ?>

<?php

$log_class = "log-normal";

if (
    strpos($line, "TCP_DENIED") !== false ||
    strpos($line, "/407") !== false
) {

    $log_class = "log-error";

} elseif (
    strpos($line, "TCP_TUNNEL/200") !== false
) {

    $log_class = "log-success";

} elseif (
    strpos($line, "TCP_MISS") !== false
) {

    $log_class = "log-info";

}

?>

<div class="log-line <?php echo $log_class; ?>">

<?php
echo htmlspecialchars(
    $line
);
?>

</div>

<?php endforeach; ?>


</div>


</div>


<!-- =========================
     Cache Log
     ========================= -->

<div class="card">


<div class="section-header">

<h2>
🦑 Cache Log
</h2>

<span class="section-badge">
<?php
echo htmlspecialchars(
    $cache_log
);
?>
</span>

</div>


<div class="log-viewer">

<?php foreach ($cache_lines as $line): ?>

<?php

$log_class = "log-normal";

if (
    stripos($line, "ERROR") !== false
) {

    $log_class = "log-error";

} elseif (
    stripos($line, "WARNING") !== false
) {

    $log_class = "log-warning";

}

?>

<div class="log-line <?php echo $log_class; ?>">

<?php
echo htmlspecialchars(
    $line
);
?>

</div>

<?php endforeach; ?>

</div>


</div>


<?php include "includes/footer.php"; ?>
