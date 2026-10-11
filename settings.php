<?php

require_once "auth/auth.php";

check_login();

$page_title = "Settings";


/* =========================
   Server Information
   ========================= */

$hostname = gethostname();

$server_ip = "نامشخص";

$ifconfig = shell_exec(
    "ifconfig vmx0 2>/dev/null"
);

if (
    $ifconfig &&
    preg_match(
        '/inet\s+([0-9.]+)/',
        $ifconfig,
        $match
    )
) {
    $server_ip = $match[1];
}


/* =========================
   Squid Configuration
   ========================= */

$squid_conf = "/usr/local/etc/squid/squid.conf";

$visible_hostname = "تعریف نشده";
$access_log = "تعریف نشده";
$cache_log = "تعریف نشده";
$coredump_dir = "تعریف نشده";


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
                '/^visible_hostname\s+(.+)$/i',
                $line,
                $m
            )
        ) {
            $visible_hostname = trim($m[1]);
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


        if (
            preg_match(
                '/^coredump_dir\s+(.+)$/i',
                $line,
                $m
            )
        ) {
            $coredump_dir = trim($m[1]);
        }

    }

}


/* =========================
   Service Status
   ========================= */

$squid_running = false;
$apache_running = false;


exec(
    "service squid status 2>&1",
    $squid_output,
    $squid_code
);

if ($squid_code === 0) {
    $squid_running = true;
}


exec(
    "service apache24 status 2>&1",
    $apache_output,
    $apache_code
);

if ($apache_code === 0) {
    $apache_running = true;
}


/* =========================
   SARG Reports
   ========================= */

$sarg_path = "/usr/local/www/sarg";

$sarg_available = is_dir($sarg_path);

$latest_report = "گزارشی موجود نیست";

$latest_timestamp = 0;


if ($sarg_available) {

    $year = date("Y");
    $month = date("m");

    $month_path =
        $sarg_path .
        "/" .
        $year .
        "/" .
        $month;


    if (is_dir($month_path)) {

        foreach (
            scandir($month_path)
            as $item
        ) {

            if (
                $item === "." ||
                $item === ".."
            ) {
                continue;
            }


            $full_path =
                $month_path .
                "/" .
                $item;


            if (
                is_dir($full_path) &&
                file_exists(
                    $full_path .
                    "/index.html"
                )
            ) {

                $time =
                    filemtime($full_path);


                if (
                    $time >
                    $latest_timestamp
                ) {

                    $latest_timestamp = $time;

                    $latest_report = $item;

                }

            }

        }

    }

}


/* =========================
   SARG Status Text
   ========================= */

if ($latest_timestamp > 0) {

    $sarg_status =
        "آخرین گزارش: " .
        $latest_report;

} elseif ($sarg_available) {

    $sarg_status =
        "مسیر گزارش موجود است";

} else {

    $sarg_status =
        "مسیر گزارش یافت نشد";

}


include "includes/header.php";

?>


<h1>تنظیمات سیستم</h1>

<p>
اطلاعات و وضعیت سرویس‌های NEAL Proxy Manager
</p>


<!-- =========================
     Server
     ========================= -->

<div class="card">

<div class="section-header">

<h2>
🖥️ اطلاعات سرور
</h2>

<span class="section-badge">
Server
</span>

</div>


<table class="data-table">

<tr>

<th>
Hostname
</th>

<td>
<?php echo htmlspecialchars($hostname); ?>
</td>

</tr>


<tr>

<th>
IP Address
</th>

<td>
<?php echo htmlspecialchars($server_ip); ?>
</td>

</tr>


<tr>

<th>
سیستم‌عامل
</th>

<td>
FreeBSD
</td>

</tr>

</table>

</div>


<!-- =========================
     Services
     ========================= -->

<div class="card">

<div class="section-header">

<h2>
⚙️ وضعیت سرویس‌ها
</h2>

<span class="section-badge">
Services
</span>

</div>


<div class="dashboard-box settings-status-box">


<div class="stat dashboard-stat squid-stat">

<div class="stat-icon">
🦑
</div>

<div class="stat-content">

<span class="stat-label">
Squid
</span>

<strong>
<?php
echo $squid_running
    ? "فعال"
    : "متوقف";
?>
</strong>

</div>

</div>


<div class="stat dashboard-stat apache-stat">

<div class="stat-icon">
🌐
</div>

<div class="stat-content">

<span class="stat-label">
Apache
</span>

<strong>
<?php
echo $apache_running
    ? "فعال"
    : "متوقف";
?>
</strong>

</div>

</div>


<div class="stat dashboard-stat sarg-stat">

<div class="stat-icon">
📊
</div>

<div class="stat-content">

<span class="stat-label">
SARG
</span>

<strong>
<?php
echo $sarg_available
    ? "موجود"
    : "یافت نشد";
?>
</strong>

</div>

</div>


</div>

</div>


<!-- =========================
     Squid
     ========================= -->

<div class="card">

<div class="section-header">

<h2>
🦑 تنظیمات Squid
</h2>

<span class="section-badge">
Squid
</span>

</div>


<table class="data-table">


<tr>

<th>
Visible Hostname
</th>

<td>
<?php
echo htmlspecialchars(
    $visible_hostname
);
?>
</td>

</tr>


<tr>

<th>
Access Log
</th>

<td>
<?php
echo htmlspecialchars(
    $access_log
);
?>
</td>

</tr>


<tr>

<th>
Cache Log
</th>

<td>
<?php
echo htmlspecialchars(
    $cache_log
);
?>
</td>

</tr>


<tr>

<th>
Coredump Directory
</th>

<td>
<?php
echo htmlspecialchars(
    $coredump_dir
);
?>
</td>

</tr>


</table>

</div>


<!-- =========================
     SARG
     ========================= -->

<div class="card">

<div class="section-header">

<h2>
📊 گزارش‌های SARG
</h2>

<span class="section-badge">
SARG
</span>

</div>


<table class="data-table">


<tr>

<th>
مسیر گزارش‌ها
</th>

<td>
<?php
echo htmlspecialchars(
    $sarg_path
);
?>
</td>

</tr>


<tr>

<th>
آخرین وضعیت
</th>

<td>
<?php
echo htmlspecialchars(
    $sarg_status
);
?>
</td>

</tr>


</table>

</div>


<?php include "includes/footer.php"; ?>
EOF
