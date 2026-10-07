<?php

require_once "auth/auth.php";

check_login();

$page_title = "Dashboards";

require_once "includes/functions.php";


/* =========================
   Live Proxy Status
   ========================= */

$proxy_port_status = squid_proxy_port_status();

$access_log_status = squid_access_log_status();

$cache_hit_rate = squid_cache_hit_rate();




/* =========================
   Proxy Activity Statistics
   ========================= */

$proxy_activity = proxy_activity_stats();

$proxy_hourly = proxy_activity_hourly();

$proxy_hourly_detail = proxy_activity_hourly_detail();

/* =========================
   Proxy Chart Summary
   ========================= */

$chart_total = array_sum($proxy_hourly);

$chart_max = max($proxy_hourly);
$chart_max_hour = array_search($chart_max, $proxy_hourly);

$chart_min = min($proxy_hourly);
$chart_min_hour = array_search($chart_min, $proxy_hourly);




/* =========================
   SARG Reports
   ========================= */

$year = date("Y");
$month = date("m");

$report_path = SARG_PATH . "/" . $year . "/" . $month;

$reports = array();


if (is_dir($report_path)) {

    foreach (scandir($report_path) as $item) {

        if ($item == "." || $item == "..") {
            continue;
        }

        if (is_dir($report_path . "/" . $item)) {
            $reports[] = $item;
        }

    }

}


/* Sort newest reports first */

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


/* Latest five reports */

$recent_reports = array_slice($reports, 0, 5);


/* =========================
   Latest SARG Report
   ========================= */

$latest = "";

if (count($reports) > 0) {
    $latest = $reports[0];
}

$current = $report_path . "/" . $latest;


/* =========================
   SARG Reported Users
   ========================= */

$reported_users = 0;

if (file_exists($current . "/sarg-users")) {

    $reported_users = intval(
        trim(
            file_get_contents(
                $current . "/sarg-users"
            )
        )
    );

}


/* =========================
   Panel Users
   ========================= */

$panel_users = 0;

$db_file = __DIR__ . "/data/neal.db";

if (file_exists($db_file)) {

    try {

        $db = new PDO(
            "sqlite:" . $db_file
        );

        $db->setAttribute(
            PDO::ATTR_ERRMODE,
            PDO::ERRMODE_EXCEPTION
        );

        $panel_users = intval(
            $db->query(
                "SELECT COUNT(*) FROM admins"
            )->fetchColumn()
        );

    } catch (PDOException $e) {

        $panel_users = 0;

    }

}


/* =========================
   Top Sites
   ========================= */

$top_sites = array();


if (file_exists($current . "/topsites.html")) {

    $html = file_get_contents(
        $current . "/topsites.html"
    );


    preg_match_all(
        '/<td[^>]*>(.*?)<\/td>/i',
        $html,
        $matches
    );


    if (isset($matches[1])) {

        foreach ($matches[1] as $site) {

            $site = trim(
                strip_tags($site)
            );


            if ($site != "") {

                $top_sites[] = $site;

            }


            if (count($top_sites) >= 10) {
                break;
            }

        }

    }

}


/* =========================
   Dynamic Dashboard
   ========================= */

$dashboard_data = [
    "users" => 0,
    "active_users" => 0,
    "pending_messages" => 0,
    "active_announcements" => 0,
    "active_occasions" => 0,
    "open_requests" => 0,
    "new_requests" => 0,
    "recent_requests" => []
];

try {
    $dashboard_db = new PDO("sqlite:" . __DIR__ . "/data/neal.db");
    $dashboard_db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $dashboard_db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    $table_exists = function($table) use ($dashboard_db) {
        $stmt = $dashboard_db->prepare("SELECT COUNT(*) FROM sqlite_master WHERE type='table' AND name=?");
        $stmt->execute([$table]);
        return (int)$stmt->fetchColumn() > 0;
    };

    if ($table_exists("users")) {
        $dashboard_data["users"] = (int)$dashboard_db->query("SELECT COUNT(*) FROM users")->fetchColumn();
        $dashboard_data["active_users"] = (int)$dashboard_db->query("SELECT COUNT(*) FROM users WHERE is_active=1")->fetchColumn();
        $dashboard_data["pending_messages"] = (int)$dashboard_db->query("SELECT COUNT(*) FROM users WHERE TRIM(COALESCE(login_message,''))<>'' AND COALESCE(login_message_status,'pending')='pending'")->fetchColumn();
    }

    if ($table_exists("announcements")) {
        $dashboard_data["active_announcements"] = (int)$dashboard_db->query("
            SELECT COUNT(*) FROM announcements
            WHERE is_active=1
              AND (published_at IS NULL OR published_at<=CURRENT_TIMESTAMP)
              AND (expires_at IS NULL OR expires_at='' OR expires_at>CURRENT_TIMESTAMP)
        ")->fetchColumn();
    }

    if ($table_exists("occasions")) {
        $dashboard_data["active_occasions"] = (int)$dashboard_db->query("SELECT COUNT(*) FROM occasions WHERE is_active=1")->fetchColumn();
    }

    if ($table_exists("service_requests")) {
        $dashboard_data["open_requests"] = (int)$dashboard_db->query("
            SELECT COUNT(*) FROM service_requests
            WHERE status IN ('new','open','in_progress','pending')
        ")->fetchColumn();

        $dashboard_data["new_requests"] = (int)$dashboard_db->query("
            SELECT COUNT(*) FROM service_requests WHERE status='new'
        ")->fetchColumn();

        $dashboard_data["recent_requests"] = $dashboard_db->query("
            SELECT tracking_number, fullname, subject, priority, status
            FROM service_requests
            ORDER BY id DESC
            LIMIT 5
        ")->fetchAll();
    }
} catch (Throwable $e) {
    // Dashboard must remain available even if an optional table is missing.
}

include "includes/header.php";

?>


<h1>داشبورد مدیریتی</h1>

<p>
وضعیت Squid Proxy و گزارش‌های SARG
</p>


<!-- =========================
     Status Cards
     ========================= -->



<div class="dashboard-box">


<div class="stat dashboard-stat squid-stat">

<div class="stat-icon">
🦑
</div>

<div class="stat-content">

<span class="stat-label">
وضعیت Squid
</span>

<strong>
<?php echo squid_status(); ?>
</strong>

</div>

</div>


<div class="stat dashboard-stat blocked-stat">

<div class="stat-icon">
🚫
</div>

<div class="stat-content">

<span class="stat-label">
سایت‌های مسدود
</span>

<strong>
<?php echo blocked_sites_count(); ?>
</strong>

</div>

</div>


<div class="stat dashboard-stat sarg-stat">

<div class="stat-icon">
📊
</div>

<div class="stat-content">

<span class="stat-label">
وضعیت SARG
</span>

<strong>
<?php echo sarg_status(); ?>
</strong>

</div>

</div>


<div class="stat dashboard-stat reported-stat">

<div class="stat-icon">
👥
</div>

<div class="stat-content">

<span class="stat-label">
کاربران گزارش‌شده
</span>

<strong>
<?php echo $reported_users; ?>
</strong>

</div>

</div>


<div class="stat dashboard-stat panel-stat">

<div class="stat-icon">
🔐
</div>

<div class="stat-content">

<span class="stat-label">
کاربران پنل
</span>

<strong>
<?php echo $panel_users; ?>
</strong>

</div>

</div>


</div>


<!-- =========================
     Dynamic Dashboard
     ========================= -->

<div class="card dashboard-dynamic-card">
    <div class="section-header">
        <div>
            <h2>📌 وضعیت جاری پنل</h2>
            <span style="display:block;margin-top:6px;color:#667085;font-size:12px;">
                اطلاعات زنده کاربران، اعلان‌ها و درخواست‌های خدمات
            </span>
        </div>
        <span class="section-badge">Dynamic</span>
    </div>

    <div class="dashboard-dynamic-grid">
        <a class="dynamic-stat" href="users.php">
            <span class="dynamic-stat-icon">👥</span>
            <span><small>کاربران فعال</small><strong><?php echo number_format($dashboard_data["active_users"]); ?></strong></span>
        </a>
        <a class="dynamic-stat" href="login-messages.php">
            <span class="dynamic-stat-icon">💬</span>
            <span><small>پیام‌های در انتظار بررسی</small><strong><?php echo number_format($dashboard_data["pending_messages"]); ?></strong></span>
        </a>
        <a class="dynamic-stat" href="announcements.php">
            <span class="dynamic-stat-icon">📢</span>
            <span><small>اطلاعیه‌های فعال</small><strong><?php echo number_format($dashboard_data["active_announcements"]); ?></strong></span>
        </a>
        <a class="dynamic-stat" href="occasions.php">
            <span class="dynamic-stat-icon">📅</span>
            <span><small>مناسبت‌های فعال</small><strong><?php echo number_format($dashboard_data["active_occasions"]); ?></strong></span>
        </a>
        <a class="dynamic-stat" href="service-requests.php">
            <span class="dynamic-stat-icon">🎫</span>
            <span><small>درخواست‌های باز</small><strong><?php echo number_format($dashboard_data["open_requests"]); ?></strong></span>
        </a>
        <a class="dynamic-stat" href="service-requests.php">
            <span class="dynamic-stat-icon">🆕</span>
            <span><small>درخواست جدید</small><strong><?php echo number_format($dashboard_data["new_requests"]); ?></strong></span>
        </a>
    </div>

    <?php if (!empty($dashboard_data["recent_requests"])): ?>
    <div class="dashboard-recent-requests">
        <div class="dashboard-subtitle">آخرین درخواست‌های خدمات</div>
        <?php foreach ($dashboard_data["recent_requests"] as $request): ?>
        <div class="dashboard-request-row">
            <div>
                <strong><?php echo htmlspecialchars($request["tracking_number"] ?? "—", ENT_QUOTES, "UTF-8"); ?></strong>
                <span><?php echo htmlspecialchars($request["fullname"] ?? "—", ENT_QUOTES, "UTF-8"); ?> · <?php echo htmlspecialchars($request["subject"] ?? "—", ENT_QUOTES, "UTF-8"); ?></span>
            </div>
            <span class="dashboard-request-status status-<?php echo htmlspecialchars($request["status"] ?? "new", ENT_QUOTES, "UTF-8"); ?>">
                <?php echo htmlspecialchars($request["status"] ?? "—", ENT_QUOTES, "UTF-8"); ?>
            </span>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<!-- =========================
     Live Proxy Status
     ========================= -->



<div class="card dashboard-live-card">

    <div class="section-header">

        <h2>
            ⚡ وضعیت لحظه‌ای Proxy
        </h2>

        <span class="section-badge">
            Live
        </span>

    </div>


    <div class="dashboard-live-grid">


        <div class="dashboard-live-item">

            <div class="live-item-icon">
                🦑
            </div>

            <div class="live-item-content">

                <span class="live-item-label">
                    Proxy Port
                </span>

                <strong>
                    <?php echo htmlspecialchars($proxy_port_status); ?>
                </strong>

            </div>

        </div>


        <div class="dashboard-live-item">

            <div class="live-item-icon">
                📄
            </div>

            <div class="live-item-content">

                <span class="live-item-label">
                    Access Log
                </span>

                <strong>
                    <?php echo htmlspecialchars($access_log_status); ?>
                </strong>

            </div>

        </div>


        <div class="dashboard-live-item">

            <div class="live-item-icon">
                💾
            </div>

            <div class="live-item-content">

                <span class="live-item-label">
                    Cache Hit Rate
                </span>

                <strong>
                    <?php echo number_format($cache_hit_rate, 2); ?>%
                </strong>

            </div>

        </div>


    </div>

</div>


<!-- =========================
     Proxy Activity Statistics
     ========================= -->



<div class="card dashboard-activity-card">

    <div class="section-header">

        <h2>
            📡 فعالیت Proxy
        </h2>

        <span class="section-badge">
            Live Statistics
        </span>

    </div>


    <div class="dashboard-activity-grid">


        <div class="dashboard-activity-item activity-total">

            <div class="activity-icon">
                📊
            </div>

            <div class="activity-content">

                <span class="activity-label">
                    کل درخواست‌ها
                </span>

                <strong>
                    <?php echo number_format($proxy_activity["total"]); ?>
                </strong>

                <small>
                    در ۲۴ ساعت اخیر
                </small>

            </div>

        </div>


        <div class="dashboard-activity-item activity-auth">

            <div class="activity-icon">
                🔐
            </div>

            <div class="activity-content">

                <span class="activity-label">
                    احراز هویت
                </span>

                <strong>
                    <?php echo number_format($proxy_activity["auth_required"]); ?>
                </strong>

                <small>
                    HTTP 407
                </small>

            </div>

        </div>


        <div class="dashboard-activity-item activity-denied">

            <div class="activity-icon">
                🚫
            </div>

            <div class="activity-content">

                <span class="activity-label">
                    درخواست‌های ردشده
                </span>

                <strong>
                    <?php echo number_format($proxy_activity["denied"]); ?>
                </strong>

                <small>
                    HTTP 403
                </small>

            </div>

        </div>


        <div class="dashboard-activity-item activity-https">

            <div class="activity-icon">
                🔒
            </div>

            <div class="activity-content">

                <span class="activity-label">
                    HTTPS موفق
                </span>

                <strong>
                    <?php echo number_format($proxy_activity["https_tunnel"]); ?>
                </strong>

                <small>
                    TCP Tunnel 200
                </small>

            </div>

        </div>


    </div>

</div>


<!-- =========================
     Proxy Activity Chart
     ========================= -->



<div class="card dashboard-chart-card">

    <div class="section-header">

        <h2>
            📈 فعالیت Proxy در ۲۴ ساعت اخیر
        </h2>

        <span class="section-badge">
            24 Hours
        </span>

    </div>

    <div class="chart-summary-grid">

        <div class="chart-summary-item summary-total">

            <div class="summary-icon">
                📊
            </div>

            <div class="summary-content">

                <span>
                    مجموع درخواست‌ها
                </span>

                <strong>
                    <?php echo number_format($chart_total); ?>
                </strong>

            </div>

        </div>


        <div class="chart-summary-item summary-peak">

            <div class="summary-icon">
                🔥
            </div>

            <div class="summary-content">

                <span>
                    شلوغ‌ترین ساعت
                </span>

                <strong>
                    <?php echo htmlspecialchars($chart_max_hour); ?>:00
                </strong>

                <small>
                    <?php echo number_format($chart_max); ?> درخواست
                </small>

            </div>

        </div>


        <div class="chart-summary-item summary-low">

            <div class="summary-icon">
                📉
            </div>

            <div class="summary-content">

                <span>
                    کم‌مصرف‌ترین ساعت
                </span>

                <strong>
                    <?php echo htmlspecialchars($chart_min_hour); ?>:00
                </strong>

                <small>
                    <?php echo number_format($chart_min); ?> درخواست
                </small>

            </div>

        </div>

    </div>
    <div class="proxy-chart">

        <?php

        $chart_max = max($proxy_hourly);

        if ($chart_max < 1) {
            $chart_max = 1;
        }

        foreach ($proxy_hourly as $hour => $count):

            $height = ($count / $chart_max) * 100;

        ?>

            <div class="chart-column">

                <div class="chart-value">
                    <?php echo number_format($count); ?>
                </div>

                <div class="chart-bar-area">

                    <div
                        class="chart-bar"
                        style="height: <?php echo $height; ?>%;"
                        title="<?php echo htmlspecialchars($hour); ?>:00 - <?php echo number_format($count); ?> requests"
                    ></div>

                </div>

                <div class="chart-hour">
                    <?php echo htmlspecialchars($hour); ?>
                </div>

            </div>

        <?php endforeach; ?>

    </div>

</div>

<!-- =========================================================
     Proxy Activity Multi Chart
     ========================================================= -->

<?php include "includes/proxy_multi_chart.php"; ?>


<!-- =========================================================
     Dashboard Analytics
     ========================================================= -->

<?php

/* =========================================================
   TOP USERS
   ========================================================= */

$top_users = array();

if ($latest != "" && file_exists($current . "/index.html")) {

    $user_html = file_get_contents($current . "/index.html");

    /*
     * SARG index.html
     *
     * Structure:
     *
     * Rank
     * Report links
     * Username
     * Connect
     * Bytes
     * %Bytes
     * Cache In
     * Cache Out
     * Elapsed Time
     * Milliseconds
     * %Time
     */

    preg_match_all(
        '/<tr>\s*
        <td[^>]*>\s*(\d+)\s*<\/td>
        \s*
        <td[^>]*>.*?<\/td>
        \s*
        <td[^>]*>\s*
            <a[^>]*>\s*(.*?)\s*<\/a>
        \s*<\/td>
        \s*
        <td[^>]*>\s*(.*?)\s*<\/td>
        \s*
        <td[^>]*>\s*(.*?)\s*<\/td>
        \s*
        <td[^>]*>\s*(.*?)\s*<\/td>
        \s*
        <td[^>]*>\s*(.*?)\s*<\/td>
        \s*
        <td[^>]*>\s*(.*?)\s*<\/td>
        \s*
        <td[^>]*>\s*(.*?)\s*<\/td>
        \s*
        <td[^>]*>\s*(.*?)\s*<\/td>
        \s*
        <td[^>]*>\s*(.*?)\s*<\/td>
        \s*
        <\/tr>/isx',
        $user_html,
        $user_matches,
        PREG_SET_ORDER
    );


    if (!empty($user_matches)) {

        foreach ($user_matches as $row) {

            $username = trim(
                html_entity_decode(
                    strip_tags($row[2]),
                    ENT_QUOTES | ENT_HTML5,
                    'UTF-8'
                )
            );


            /*
             * Ignore empty names
             */

            if ($username === "") {
                continue;
            }


            /*
             * Ignore IP-only entries.
             * These are clients that are not authenticated users.
             */

            if (preg_match(
                '/^(?:\d{1,3}\.){3}\d{1,3}$/',
                $username
            )) {
                continue;
            }


            $top_users[] = array(

                "rank" => intval($row[1]),

                "username" => $username,

                "connect" => trim(
                    strip_tags($row[3])
                ),

                "bytes" => trim(
                    strip_tags($row[4])
                ),

                "cache_in" => trim(
                    strip_tags($row[5])
                ),

                "cache_out" => trim(
                    strip_tags($row[6])
                ),

                "time" => trim(
                    strip_tags($row[7])
                ),

                "milliseconds" => trim(
                    strip_tags($row[8])
                ),

                "time_percent" => trim(
                    strip_tags($row[9])
                )

            );


            /*
             * Top 10 authenticated users
             */

            if (count($top_users) >= 10) {
                break;
            }

        }

    }

}

/* =========================================================
   TOP SITES
   ========================================================= */

$top_sites = array();

if ($latest != "" && file_exists($current . "/topsites.html")) {

    $sites_html = file_get_contents(
        $current . "/topsites.html"
    );

    preg_match_all(
        '/<tr>\s*<td[^>]*>(\d+)<\/td>\s*<td[^>]*>\s*<a[^>]*>(.*?)<\/a>\s*<\/td>\s*<td[^>]*>(.*?)<\/td>\s*<td[^>]*>(.*?)<\/td>\s*<td[^>]*>(.*?)<\/td>\s*<td[^>]*>(.*?)<\/td>\s*<\/tr>/is',
        $sites_html,
        $site_matches,
        PREG_SET_ORDER
    );

    if (!empty($site_matches)) {

        foreach ($site_matches as $row) {

            $site = trim(
                strip_tags($row[2])
            );

            if ($site == "") {
                continue;
            }

            $top_sites[] = array(
                "rank"    => intval($row[1]),
                "site"    => $site,
                "connect" => trim(strip_tags($row[3])),
                "bytes"   => trim(strip_tags($row[4])),
                "time"    => trim(strip_tags($row[5])),
                "users"   => trim(strip_tags($row[6]))
            );

            if (count($top_sites) >= 10) {
                break;
            }
        }
    }
}

?>

<!-- =========================================================
     Proxy Consumption Analysis
     ========================================================= -->

<div class="card dashboard-consumption-card">

    <div class="section-header">

        <div>

            <h2>
                📊 تحلیل مصرف Proxy
            </h2>

            <span style="
                display:block;
                margin-top:6px;
                color:#667085;
                font-size:12px;
            ">
                برترین کاربران و سایت‌های پرترافیک در آخرین گزارش SARG
            </span>

        </div>

        <span class="section-badge">
            SARG Analytics
        </span>

    </div>


    <div class="dashboard-consumption-grid">


        <!-- =================================================
             TOP USERS
             ================================================= -->

        <div class="consumption-panel">

            <div class="consumption-panel-header">

                <div class="consumption-title">

                    <div class="consumption-icon user-icon">
                        👥
                    </div>

                    <div>

                        <strong>
                            Top Users
                        </strong>

                        <span>
                            پرمصرف‌ترین کاربران
                        </span>

                    </div>

                </div>

                <span class="consumption-count">
                    ۱۰ کاربر
                </span>

            </div>


            <?php if (!empty($top_users)): ?>

                <div class="consumption-list">

                    <?php
                    $user_rank = 1;
                    foreach ($top_users as $user):
                    ?>

                    <div class="consumption-row">

                        <div class="consumption-rank">

                            <?php if ($user_rank == 1): ?>

                                <span class="rank-gold">
                                    1
                                </span>

                            <?php elseif ($user_rank == 2): ?>

                                <span class="rank-silver">
                                    2
                                </span>

                            <?php elseif ($user_rank == 3): ?>

                                <span class="rank-bronze">
                                    3
                                </span>

                            <?php else: ?>

                                <span class="rank-normal">
                                    <?php echo $user_rank; ?>
                                </span>

                            <?php endif; ?>

                        </div>


                        <div class="consumption-main">

                            <strong>
                                <?php
                                echo htmlspecialchars(
                                    $user["username"]
                                );
                                ?>
                            </strong>

                            <span>
                                <?php echo htmlspecialchars($user["connect"]); ?>
                                اتصال
                            </span>

                        </div>


                        <div class="consumption-value">

                            <strong>
                                <?php
                                echo htmlspecialchars(
                                    $user["bytes"]
                                );
                                ?>
                            </strong>

                            <span>
                                مصرف
                            </span>

                        </div>

                    </div>

                    <?php
                        $user_rank++;
                    endforeach;
                    ?>

                </div>

            <?php else: ?>

                <div class="consumption-empty">

                    <div>
                        📭
                    </div>

                    <span>
                        اطلاعات کاربران در گزارش موجود نیست
                    </span>

                </div>

            <?php endif; ?>

        </div>


        <!-- =================================================
             TOP SITES
             ================================================= -->

        <div class="consumption-panel">

            <div class="consumption-panel-header">

                <div class="consumption-title">

                    <div class="consumption-icon site-icon">
                        🌐
                    </div>

                    <div>

                        <strong>
                            Top Sites
                        </strong>

                        <span>
                            پرترافیک‌ترین سایت‌ها
                        </span>

                    </div>

                </div>

                <span class="consumption-count">
                    ۱۰ سایت
                </span>

            </div>


            <?php if (!empty($top_sites)): ?>

                <div class="consumption-list">

                    <?php
                    $site_rank = 1;

                    foreach ($top_sites as $site):
                    ?>

                    <div class="consumption-row">

                        <div class="consumption-rank">

                            <?php if ($site_rank == 1): ?>

                                <span class="rank-gold">
                                    1
                                </span>

                            <?php elseif ($site_rank == 2): ?>

                                <span class="rank-silver">
                                    2
                                </span>

                            <?php elseif ($site_rank == 3): ?>

                                <span class="rank-bronze">
                                    3
                                </span>

                            <?php else: ?>

                                <span class="rank-normal">
                                    <?php echo $site_rank; ?>
                                </span>

                            <?php endif; ?>

                        </div>


                        <div class="consumption-main">

                            <strong
                                title="<?php echo htmlspecialchars($site["site"]); ?>"
                            >
                                <?php
                                echo htmlspecialchars(
                                    $site["site"]
                                );
                                ?>
                            </strong>

                            <span>
                                <?php echo htmlspecialchars($site["connect"]); ?>
                                اتصال
                                ·
                                <?php echo htmlspecialchars($site["users"]); ?>
                                کاربر
                            </span>

                        </div>


                        <div class="consumption-value">

                            <strong>
                                <?php
                                echo htmlspecialchars(
                                    $site["bytes"]
                                );
                                ?>
                            </strong>

                            <span>
                                مصرف
                            </span>

                        </div>

                    </div>

                    <?php
                        $site_rank++;
                    endforeach;
                    ?>

                </div>

            <?php else: ?>

                <div class="consumption-empty">

                    <div>
                        🌐
                    </div>

                    <span>
                        اطلاعات سایت‌ها در گزارش موجود نیست
                    </span>

                </div>

            <?php endif; ?>

        </div>


    </div>

</div>



<!-- =========================================================
     SARG REPORT ARCHIVE
     ========================================================= -->

<div class="card dashboard-sarg-card">

    <div class="section-header">

        <div>

            <h2>
                📋 گزارش‌های SARG
            </h2>

            <span style="
                display:block;
                margin-top:6px;
                color:#667085;
                font-size:12px;
            ">
                دسترسی سریع به گزارش‌های تولیدشده Proxy
            </span>

        </div>


        <?php if (count($reports) > 5): ?>

            <a
                href="/neal/reports.php"
                class="btn btn-primary"
            >
                📚 مشاهده همه گزارش‌ها
            </a>

        <?php endif; ?>

    </div>


    <?php if (count($recent_reports) > 0): ?>


        <div class="sarg-report-list">

            <?php
            $counter = 1;

            foreach ($recent_reports as $report):
            ?>

            <div class="sarg-report-item">


                <div class="sarg-report-date">

                    <div class="sarg-report-number">
                        <?php echo $counter++; ?>
                    </div>

                    <div>

                        <strong>
                            <?php
                            echo htmlspecialchars(
                                $report
                            );
                            ?>
                        </strong>

                        <span>
                            گزارش دوره‌ای SARG
                        </span>

                    </div>

                </div>


                <div class="sarg-report-meta">

                    <span>
                        📊
                        گزارش کامل
                    </span>

                </div>


                <a
                    href="/sarg/<?php echo $year; ?>/<?php echo $month; ?>/<?php echo urlencode($report); ?>/"
                    target="_blank"
                    class="sarg-report-button"
                >
                    مشاهده گزارش
                    <span>←</span>
                </a>


            </div>

            <?php endforeach; ?>

        </div>


    <?php else: ?>


        <div class="consumption-empty">

            <div>
                📭
            </div>

            <span>
                گزارش SARG برای ماه جاری موجود نیست.
            </span>

        </div>


    <?php endif; ?>

</div>



<!-- =========================================================
     Dashboard Redesign Styles
     ========================================================= -->

<style>

.dashboard-consumption-card,
.dashboard-sarg-card {
    margin-top: 24px;
}


/* =========================================================
   Consumption Grid
   ========================================================= */

.dashboard-consumption-grid {

    display:grid;

    grid-template-columns:
        repeat(2, minmax(0, 1fr));

    gap:20px;

    margin-top:20px;
}


.consumption-panel {

    background:
        linear-gradient(
            145deg,
            #ffffff 0%,
            #f8fafc 100%
        );

    border:1px solid #e4e7ec;

    border-radius:16px;

    padding:20px;

    box-shadow:
        0 4px 12px rgba(16,24,40,.04);

    transition:
        transform .2s ease,
        box-shadow .2s ease;
}


.consumption-panel:hover {

    transform:translateY(-2px);

    box-shadow:
        0 10px 24px rgba(16,24,40,.08);
}


/* =========================================================
   Panel Header
   ========================================================= */

.consumption-panel-header {

    display:flex;

    align-items:center;

    justify-content:space-between;

    gap:15px;

    padding-bottom:16px;

    margin-bottom:8px;

    border-bottom:1px solid #eaecf0;
}


.consumption-title {

    display:flex;

    align-items:center;

    gap:12px;
}


.consumption-icon {

    width:42px;

    height:42px;

    display:flex;

    align-items:center;

    justify-content:center;

    border-radius:12px;

    font-size:21px;
}


.user-icon {

    background:#eef4ff;
}


.site-icon {

    background:#ecfdf3;
}


.consumption-title strong {

    display:block;

    color:#101828;

    font-size:16px;
}


.consumption-title span {

    display:block;

    color:#667085;

    font-size:11px;

    margin-top:4px;
}


.consumption-count {

    padding:5px 9px;

    border-radius:20px;

    background:#f2f4f7;

    color:#475467;

    font-size:10px;

    white-space:nowrap;
}


/* =========================================================
   Rows
   ========================================================= */

.consumption-list {

    display:flex;

    flex-direction:column;
}


.consumption-row {

    display:grid;

    grid-template-columns:
        38px
        minmax(0,1fr)
        auto;

    align-items:center;

    gap:10px;

    min-height:58px;

    border-bottom:1px solid #f2f4f7;

    transition:
        background .15s ease,
        padding .15s ease;
}


.consumption-row:last-child {

    border-bottom:none;
}


.consumption-row:hover {

    background:#f8fafc;

    padding-left:5px;

    padding-right:5px;

    border-radius:8px;
}


.consumption-rank {

    display:flex;

    justify-content:center;
}


.consumption-rank span {

    width:27px;

    height:27px;

    display:flex;

    align-items:center;

    justify-content:center;

    border-radius:50%;

    font-size:11px;

    font-weight:700;
}


.rank-gold {

    background:#fff4cc;

    color:#9a6700;
}


.rank-silver {

    background:#eef2f6;

    color:#475467;
}


.rank-bronze {

    background:#fbe6d5;

    color:#9a4d12;
}


.rank-normal {

    background:#f2f4f7;

    color:#667085;
}


.consumption-main {

    min-width:0;
}


.consumption-main strong {

    display:block;

    color:#1d2939;

    font-size:12px;

    white-space:nowrap;

    overflow:hidden;

    text-overflow:ellipsis;

    max-width:100%;
}


.consumption-main span {

    display:block;

    color:#98a2b3;

    font-size:10px;

    margin-top:4px;
}


.consumption-value {

    text-align:right;

    min-width:65px;
}


.consumption-value strong {

    display:block;

    color:#0b1f3a;

    font-size:13px;

    font-weight:700;
}


.consumption-value span {

    display:block;

    color:#98a2b3;

    font-size:9px;

    margin-top:3px;
}


/* =========================================================
   Empty
   ========================================================= */

.consumption-empty {

    min-height:180px;

    display:flex;

    flex-direction:column;

    align-items:center;

    justify-content:center;

    gap:10px;

    color:#98a2b3;

    font-size:12px;

    text-align:center;
}


.consumption-empty div {

    font-size:30px;
}


/* =========================================================
   SARG Reports
   ========================================================= */

.sarg-report-list {

    margin-top:20px;

    display:flex;

    flex-direction:column;

    gap:10px;
}


.sarg-report-item {

    display:grid;

    grid-template-columns:
        minmax(220px,1fr)
        auto
        auto;

    align-items:center;

    gap:20px;

    padding:15px 18px;

    border:1px solid #eaecf0;

    border-radius:13px;

    background:#ffffff;

    transition:
        transform .18s ease,
        box-shadow .18s ease,
        border-color .18s ease;
}


.sarg-report-item:hover {

    transform:translateY(-1px);

    border-color:#d0d5dd;

    box-shadow:
        0 6px 18px rgba(16,24,40,.06);
}


.sarg-report-date {

    display:flex;

    align-items:center;

    gap:12px;

    min-width:0;
}


.sarg-report-number {

    width:34px;

    height:34px;

    display:flex;

    align-items:center;

    justify-content:center;

    border-radius:10px;

    background:#eef4ff;

    color:#3159a6;

    font-size:12px;

    font-weight:700;
}


.sarg-report-date strong {

    display:block;

    color:#101828;

    font-size:13px;
}


.sarg-report-date span {

    display:block;

    color:#98a2b3;

    font-size:10px;

    margin-top:4px;
}


.sarg-report-meta {

    color:#667085;

    font-size:11px;

    white-space:nowrap;
}


.sarg-report-button {

    display:inline-flex;

    align-items:center;

    gap:8px;

    padding:8px 13px;

    border-radius:8px;

    background:#f2f4f7;

    color:#344054;

    text-decoration:none;

    font-size:11px;

    font-weight:600;

    transition:
        background .15s ease,
        color .15s ease;
}


.sarg-report-button:hover {

    background:#e4e7ec;

    color:#101828;

}


.sarg-report-button span {

    font-size:13px;
}


/* =========================================================
   Responsive
   ========================================================= */

@media (max-width: 900px) {

    .dashboard-consumption-grid {

        grid-template-columns:1fr;

    }

}


@media (max-width: 700px) {

    .sarg-report-item {

        grid-template-columns:1fr;

        gap:12px;

    }


    .sarg-report-meta {

        display:none;

    }


    .sarg-report-button {

        justify-content:center;

    }

}


.dashboard-dynamic-card{margin-top:24px}.dashboard-dynamic-grid{display:grid;grid-template-columns:repeat(6,minmax(0,1fr));gap:10px;margin-top:18px}.dynamic-stat{display:flex;align-items:center;gap:10px;padding:13px;border:1px solid #eaecf0;border-radius:12px;background:#fff;text-decoration:none;color:inherit;transition:.18s}.dynamic-stat:hover{transform:translateY(-2px);box-shadow:0 7px 18px rgba(16,24,40,.07);border-color:#d0d5dd}.dynamic-stat-icon{width:36px;height:36px;display:flex;align-items:center;justify-content:center;border-radius:10px;background:#f2f4f7;font-size:18px}.dynamic-stat small{display:block;color:#667085;font-size:9px;line-height:1.5}.dynamic-stat strong{display:block;color:#101828;font-size:17px;margin-top:3px}.dashboard-recent-requests{margin-top:18px;border-top:1px solid #eaecf0;padding-top:15px}.dashboard-subtitle{font-size:12px;font-weight:700;color:#344054;margin-bottom:8px}.dashboard-request-row{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:10px 4px;border-bottom:1px solid #f2f4f7}.dashboard-request-row:last-child{border-bottom:0}.dashboard-request-row strong{display:block;font-size:11px;color:#101828}.dashboard-request-row div span{display:block;font-size:10px;color:#98a2b3;margin-top:3px}.dashboard-request-status{padding:4px 8px;border-radius:20px;background:#f2f4f7;color:#475467;font-size:9px;white-space:nowrap}.status-new{background:#eef4ff;color:#3159a6}.status-in_progress{background:#fff4cc;color:#9a6700}.status-open{background:#ecfdf3;color:#027a48}.status-pending{background:#fff1f3;color:#c01048}@media(max-width:1100px){.dashboard-dynamic-grid{grid-template-columns:repeat(3,minmax(0,1fr))}}@media(max-width:650px){.dashboard-dynamic-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.dashboard-request-row{align-items:flex-start;flex-direction:column}}
</style>


<?php include "includes/footer.php"; ?>


