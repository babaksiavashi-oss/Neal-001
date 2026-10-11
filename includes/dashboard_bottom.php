<?php

/* =========================================================
   Dashboard Bottom
   Top Sites + Top Users + Recent SARG Reports
   ========================================================= */


/* =========================================================
   TOP SITES
   ========================================================= */

$dashboard_top_sites = array();

$topsites_file = $current . "/topsites.html";

if (file_exists($topsites_file)) {

    $html = file_get_contents($topsites_file);

    if (preg_match_all(
        '/<tr>\s*<td[^>]*class="data"[^>]*>\s*(\d+)\s*<\/td>\s*<td[^>]*class="data2"[^>]*>(.*?)<\/td>\s*<td[^>]*class="data"[^>]*>(.*?)<\/td>\s*<td[^>]*class="data"[^>]*>(.*?)<\/td>/is',
        $html,
        $rows,
        PREG_SET_ORDER
    )) {

        foreach ($rows as $row) {

            $rank = intval($row[1]);

            $site = trim(
                strip_tags($row[2])
            );

            $connect = trim(
                strip_tags($row[3])
            );

            $bytes = trim(
                strip_tags($row[4])
            );

            if ($site !== "") {

                $dashboard_top_sites[] = array(
                    "rank"    => $rank,
                    "site"    => $site,
                    "connect" => $connect,
                    "bytes"   => $bytes
                );

            }

            if (count($dashboard_top_sites) >= 10) {
                break;
            }
        }
    }
}


/* =========================================================
   TOP USERS
   ========================================================= */

$dashboard_top_users = array();

$sarg_index = $current . "/index.html";

if (file_exists($sarg_index)) {

    $html = file_get_contents($sarg_index);

    if (preg_match_all(
        '/<tr>\s*<td[^>]*class="data"[^>]*>\s*(\d+)\s*<\/td>\s*<td[^>]*class="data2"[^>]*>.*?<\/td>\s*<td[^>]*class="data2"[^>]*>\s*<a href="([^"]+)">([^<]+)<\/a>\s*<\/td>\s*<td[^>]*class="data"[^>]*>([^<]+)<\/td>\s*<td[^>]*class="data"[^>]*>([^<]+)<\/td>\s*<td[^>]*class="data"[^>]*>([^<]+)<\/td>\s*<td[^>]*class="data"[^>]*>([^<]+)<\/td>\s*<td[^>]*class="data"[^>]*>([^<]+)<\/td>/is',
        $html,
        $rows,
        PREG_SET_ORDER
    )) {

        foreach ($rows as $row) {

            $rank = intval($row[1]);

            $user_link = trim($row[2]);

            $username = trim($row[3]);

            $connect = trim($row[4]);

            $bytes = trim($row[5]);

            $percent_bytes = trim($row[6]);

            $cache_in = trim($row[7]);

            $cache_out = trim($row[8]);

            $elapsed = trim($row[9]);

            if ($username !== "") {

                $dashboard_top_users[] = array(
                    "rank"          => $rank,
                    "username"      => $username,
                    "link"          => $user_link,
                    "connect"       => $connect,
                    "bytes"         => $bytes,
                    "percent_bytes" => $percent_bytes,
                    "cache_in"      => $cache_in,
                    "cache_out"     => $cache_out,
                    "elapsed"       => $elapsed
                );

            }

            if (count($dashboard_top_users) >= 10) {
                break;
            }
        }
    }
}

?>

<style>

/* =========================================================
   DASHBOARD BOTTOM
   ========================================================= */

.dashboard-bottom-grid {

    display: grid;

    grid-template-columns:
        repeat(2, minmax(0, 1fr));

    gap: 22px;

    margin-top: 22px;
}


.dashboard-bottom-card {

    background: #ffffff;

    border: 1px solid #e8edf3;

    border-radius: 18px;

    padding: 22px;

    box-shadow:
        0 8px 24px rgba(15, 23, 42, 0.06);

    transition:
        transform 0.2s ease,
        box-shadow 0.2s ease;
}


.dashboard-bottom-card:hover {

    transform: translateY(-2px);

    box-shadow:
        0 12px 30px rgba(15, 23, 42, 0.09);
}


.dashboard-bottom-header {

    display: flex;

    align-items: center;

    justify-content: space-between;

    gap: 15px;

    margin-bottom: 18px;
}


.dashboard-bottom-title {

    display: flex;

    align-items: center;

    gap: 10px;
}


.dashboard-bottom-title-icon {

    width: 42px;

    height: 42px;

    border-radius: 12px;

    display: flex;

    align-items: center;

    justify-content: center;

    font-size: 21px;

    background: #f1f5f9;
}


.dashboard-bottom-title h2 {

    margin: 0;

    font-size: 19px;

    color: #0b1f3a;
}


.dashboard-bottom-subtitle {

    display: block;

    margin-top: 3px;

    font-size: 12px;

    color: #8a94a6;
}


.dashboard-bottom-badge {

    display: inline-flex;

    align-items: center;

    padding: 7px 11px;

    border-radius: 999px;

    background: #f5f7fa;

    color: #667085;

    font-size: 12px;

    font-weight: 700;

    white-space: nowrap;
}


/* =========================================================
   TOP LIST
   ========================================================= */

.dashboard-top-list {

    display: flex;

    flex-direction: column;

    gap: 8px;
}


.dashboard-top-row {

    display: grid;

    grid-template-columns:
        38px
        minmax(0, 1fr)
        auto;

    align-items: center;

    gap: 12px;

    min-height: 55px;

    padding: 8px 10px;

    border-radius: 12px;

    transition:
        background 0.2s ease,
        transform 0.2s ease;
}


.dashboard-top-row:hover {

    background: #f8fafc;

    transform: translateX(-2px);
}


.dashboard-rank {

    width: 32px;

    height: 32px;

    border-radius: 10px;

    display: flex;

    align-items: center;

    justify-content: center;

    background: #f1f5f9;

    color: #64748b;

    font-size: 13px;

    font-weight: 800;
}


.dashboard-top-row:nth-child(1) .dashboard-rank {

    background: #fff7ed;

    color: #ea580c;
}


.dashboard-top-row:nth-child(2) .dashboard-rank {

    background: #f8fafc;

    color: #475569;
}


.dashboard-top-row:nth-child(3) .dashboard-rank {

    background: #fefce8;

    color: #a16207;
}


.dashboard-top-main {

    min-width: 0;
}


.dashboard-top-name {

    display: block;

    overflow: hidden;

    text-overflow: ellipsis;

    white-space: nowrap;

    color: #172b4d;

    font-size: 14px;

    font-weight: 700;
}


.dashboard-top-meta {

    display: block;

    margin-top: 3px;

    color: #98a2b3;

    font-size: 11px;
}


.dashboard-top-value {

    text-align: left;

    white-space: nowrap;
}


.dashboard-top-bytes {

    display: block;

    color: #0b1f3a;

    font-size: 14px;

    font-weight: 800;
}


.dashboard-top-connect {

    display: block;

    margin-top: 3px;

    color: #98a2b3;

    font-size: 10px;
}


/* =========================================================
   REPORTS
   ========================================================= */

.dashboard-reports-card {

    margin-top: 22px;
}


.dashboard-report-list {

    display: flex;

    flex-direction: column;

    gap: 8px;
}


.dashboard-report-row {

    display: flex;

    align-items: center;

    justify-content: space-between;

    gap: 15px;

    padding: 13px 14px;

    border-radius: 12px;

    background: #f8fafc;

    border: 1px solid #eef2f6;

    transition:
        background 0.2s ease,
        transform 0.2s ease;
}


.dashboard-report-row:hover {

    background: #f1f5f9;

    transform: translateX(-2px);
}


.dashboard-report-info {

    display: flex;

    align-items: center;

    gap: 12px;

    min-width: 0;
}


.dashboard-report-icon {

    width: 38px;

    height: 38px;

    border-radius: 10px;

    display: flex;

    align-items: center;

    justify-content: center;

    background: #eef6ff;

    font-size: 18px;
}


.dashboard-report-date {

    color: #172b4d;

    font-size: 14px;

    font-weight: 700;
}


.dashboard-report-label {

    display: block;

    margin-top: 3px;

    color: #98a2b3;

    font-size: 11px;
}


.dashboard-report-link {

    display: inline-flex;

    align-items: center;

    gap: 6px;

    padding: 8px 12px;

    border-radius: 9px;

    background: #eef6ff;

    color: #2563eb;

    text-decoration: none;

    font-size: 12px;

    font-weight: 700;

    white-space: nowrap;
}


.dashboard-report-link:hover {

    background: #dbeafe;

}


/* =========================================================
   EMPTY STATE
   ========================================================= */

.dashboard-bottom-empty {

    padding: 35px 15px;

    text-align: center;

    color: #98a2b3;

    font-size: 13px;
}


/* =========================================================
   RESPONSIVE
   ========================================================= */

@media (max-width: 900px) {

    .dashboard-bottom-grid {

        grid-template-columns: 1fr;

    }

}


@media (max-width: 600px) {

    .dashboard-bottom-card {

        padding: 16px;

    }

    .dashboard-report-row {

        align-items: flex-start;

        flex-direction: column;

    }

}

</style>


<!-- =========================================================
     TOP SITES + TOP USERS
     ========================================================= -->

<div class="dashboard-bottom-grid">


    <!-- =========================
         TOP SITES
         ========================= -->

    <div class="dashboard-bottom-card">

        <div class="dashboard-bottom-header">

            <div class="dashboard-bottom-title">

                <div class="dashboard-bottom-title-icon">
                    🔥
                </div>

                <div>

                    <h2>
                        Top Sites
                    </h2>

                    <span class="dashboard-bottom-subtitle">
                        پرترافیک‌ترین سایت‌های گزارش‌شده
                    </span>

                </div>

            </div>

            <span class="dashboard-bottom-badge">
                ۱۰ سایت برتر
            </span>

        </div>


        <?php if (count($dashboard_top_sites) > 0): ?>

        <div class="dashboard-top-list">

            <?php foreach ($dashboard_top_sites as $site): ?>

            <div class="dashboard-top-row">

                <div class="dashboard-rank">
                    <?php echo $site["rank"]; ?>
                </div>


                <div class="dashboard-top-main">

                    <span
                        class="dashboard-top-name"
                        title="<?php echo htmlspecialchars($site["site"]); ?>"
                    >
                        <?php echo htmlspecialchars($site["site"]); ?>
                    </span>

                    <span class="dashboard-top-meta">
                        تعداد اتصال: <?php echo htmlspecialchars($site["connect"]); ?>
                    </span>

                </div>


                <div class="dashboard-top-value">

                    <span class="dashboard-top-bytes">
                        <?php echo htmlspecialchars($site["bytes"]); ?>
                    </span>

                    <span class="dashboard-top-connect">
                        مصرف
                    </span>

                </div>

            </div>

            <?php endforeach; ?>

        </div>

        <?php else: ?>

        <div class="dashboard-bottom-empty">
            اطلاعات Top Sites موجود نیست.
        </div>

        <?php endif; ?>

    </div>



    <!-- =========================
         TOP USERS
         ========================= -->

    <div class="dashboard-bottom-card">

        <div class="dashboard-bottom-header">

            <div class="dashboard-bottom-title">

                <div class="dashboard-bottom-title-icon">
                    👤
                </div>

                <div>

                    <h2>
                        Top Users
                    </h2>

                    <span class="dashboard-bottom-subtitle">
                        پرمصرف‌ترین کاربران گزارش‌شده
                    </span>

                </div>

            </div>

            <span class="dashboard-bottom-badge">
                ۱۰ کاربر برتر
            </span>

        </div>


        <?php if (count($dashboard_top_users) > 0): ?>

        <div class="dashboard-top-list">

            <?php foreach ($dashboard_top_users as $user): ?>

            <div class="dashboard-top-row">

                <div class="dashboard-rank">
                    <?php echo $user["rank"]; ?>
                </div>


                <div class="dashboard-top-main">

                    <a
                        href="/sarg/<?php echo $year; ?>/<?php echo $month; ?>/<?php echo htmlspecialchars($latest); ?>/<?php echo htmlspecialchars($user["link"]); ?>"
                        target="_blank"
                        class="dashboard-top-name"
                        style="text-decoration:none;"
                    >
                        <?php echo htmlspecialchars($user["username"]); ?>
                    </a>

                    <span class="dashboard-top-meta">
                        <?php echo htmlspecialchars($user["connect"]); ?> اتصال
                        ·
                        <?php echo htmlspecialchars($user["elapsed"]); ?>
                    </span>

                </div>


                <div class="dashboard-top-value">

                    <span class="dashboard-top-bytes">
                        <?php echo htmlspecialchars($user["bytes"]); ?>
                    </span>

                    <span class="dashboard-top-connect">
                        مصرف
                    </span>

                </div>

            </div>

            <?php endforeach; ?>

        </div>

        <?php else: ?>

        <div class="dashboard-bottom-empty">
            اطلاعات Top Users موجود نیست.
        </div>

        <?php endif; ?>

    </div>

</div>



<!-- =========================================================
     RECENT SARG REPORTS
     ========================================================= -->

<div class="dashboard-bottom-card dashboard-reports-card">

    <div class="dashboard-bottom-header">

        <div class="dashboard-bottom-title">

            <div class="dashboard-bottom-title-icon">
                📅
            </div>

            <div>

                <h2>
                    آخرین گزارش‌های SARG
                </h2>

                <span class="dashboard-bottom-subtitle">
                    گزارش‌های تولیدشده برای بررسی مصرف اینترنت
                </span>

            </div>

        </div>


        <?php if (count($reports) > 5): ?>

        <a
            href="/neal/reports.php"
            class="dashboard-bottom-badge"
            style="text-decoration:none;"
        >
            مشاهده همه گزارش‌ها
        </a>

        <?php else: ?>

        <span class="dashboard-bottom-badge">
            <?php echo count($recent_reports); ?> گزارش اخیر
        </span>

        <?php endif; ?>

    </div>


    <?php if (count($recent_reports) > 0): ?>

    <div class="dashboard-report-list">

        <?php foreach ($recent_reports as $report): ?>

        <div class="dashboard-report-row">

            <div class="dashboard-report-info">

                <div class="dashboard-report-icon">
                    📊
                </div>

                <div>

                    <div class="dashboard-report-date">
                        <?php echo htmlspecialchars($report); ?>
                    </div>

                    <span class="dashboard-report-label">
                        Squid Analysis Report
                    </span>

                </div>

            </div>


            <a
                href="/sarg/<?php echo $year; ?>/<?php echo $month; ?>/<?php echo urlencode($report); ?>/"
                target="_blank"
                class="dashboard-report-link"
            >
                👁️ مشاهده گزارش
            </a>

        </div>

        <?php endforeach; ?>

    </div>

    <?php else: ?>

    <div class="dashboard-bottom-empty">
        گزارش SARG برای ماه جاری موجود نیست.
    </div>

    <?php endif; ?>

</div>
