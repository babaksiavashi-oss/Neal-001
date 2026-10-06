<?php

require_once __DIR__ . "/../config/config.php";


// Check Squid Status

function squid_status()
{

    $status = shell_exec("service squid status 2>&1");

    if (strpos($status, "is running") !== false) {

        return "Running";

    }

    return "Stopped";

}



// Count Blocked Sites

function blocked_sites_count()
{

    if (file_exists(BLOCK_LIST)) {

        $lines = file(BLOCK_LIST, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        return count($lines);

    }

    return 0;

}



// Check SARG

function sarg_status()
{

    if (is_dir(SARG_PATH)) {

        return "Active";

    }

    return "Inactive";

}



// Last SARG Report

function last_sarg_report()
{

    $path = SARG_PATH . "/2026";

    if (!is_dir($path)) {

        return "-";

    }


    $files = scandir($path, SCANDIR_SORT_DESCENDING);


    foreach ($files as $file) {

        if ($file != "." && $file != "..") {

            return $file;

        }

    }


    return "-";

}

// Check Squid Proxy Port

function squid_proxy_port_status()
{

    $output = shell_exec(
        "sockstat -4 -l 2>/dev/null | grep ':3128'"
    );

    if (!empty(trim($output))) {

        return "Active";

    }

    return "Inactive";

}



// Check Squid Access Log

function squid_access_log_status()
{

    $log = "/var/log/squid/access.log";

    if (!file_exists($log)) {

        return "Inactive";

    }

    if (filesize($log) <= 0) {

        return "Empty";

    }

    return "Active";

}



// Get Squid Cache Hit Rate

function squid_cache_hit_rate()
{

    $log = "/var/log/squid/access.log";

    if (!file_exists($log)) {

        return 0;

    }


    $hit = 0;
    $miss = 0;


    $handle = @fopen($log, "r");

    if (!$handle) {

        return 0;

    }


    while (($line = fgets($handle)) !== false) {

        $parts = preg_split('/\s+/', trim($line));

        if (!isset($parts[3])) {

            continue;

        }


        $status = $parts[3];


        if (
            preg_match(
                '/^TCP_(HIT|MEM_HIT|REFRESH_UNMODIFIED)/',
                $status
            )
        ) {

            $hit++;

        }


        elseif (
            preg_match(
                '/^TCP_(MISS|REFRESH_MODIFIED)/',
                $status
            )
        ) {

            $miss++;

        }

    }


    fclose($handle);


    $total = $hit + $miss;


    if ($total == 0) {

        return 0;

    }


    return round(
        ($hit / $total) * 100,
        2
    );

}

// Proxy Activity Statistics

function proxy_activity_stats()
{
    $log_file = "/var/log/squid/access.log";

    $stats = array(
        "total" => 0,
        "auth_required" => 0,
        "denied" => 0,
        "https_tunnel" => 0
    );

    if (!file_exists($log_file)) {
        return $stats;
    }

    $handle = fopen($log_file, "r");

    if (!$handle) {
        return $stats;
    }

    $cutoff = time() - (24 * 60 * 60);

    while (($line = fgets($handle)) !== false) {

        $parts = preg_split('/\s+/', trim($line));

        if (!isset($parts[0], $parts[3])) {
            continue;
        }

        $timestamp = (float) $parts[0];

        if ($timestamp < $cutoff) {
            continue;
        }

        $status = $parts[3];

        if (strpos($status, "TCP_") !== 0) {
            continue;
        }

        $stats["total"]++;

        if ($status === "TCP_DENIED/407") {
            $stats["auth_required"]++;
        }

        if ($status === "TCP_DENIED/403") {
            $stats["denied"]++;
        }

        if ($status === "TCP_TUNNEL/200") {
            $stats["https_tunnel"]++;
        }
    }

    fclose($handle);

    return $stats;
}

/* Proxy Activity - Hourly Statistics */

function proxy_activity_hourly()
{
    $log_file = "/var/log/squid/access.log";

    $hours = array();

    for ($i = 23; $i >= 0; $i--) {
        $hour = date("H", time() - ($i * 3600));
        $hours[$hour] = 0;
    }

    if (!file_exists($log_file)) {
        return $hours;
    }

    $handle = fopen($log_file, "r");

    if (!$handle) {
        return $hours;
    }

    $cutoff = time() - (24 * 60 * 60);

    while (($line = fgets($handle)) !== false) {

        $parts = preg_split('/\s+/', trim($line));

        if (!isset($parts[0], $parts[3])) {
            continue;
        }

        $timestamp = (float) $parts[0];

        if ($timestamp < $cutoff) {
            continue;
        }

        $status = $parts[3];

        if (strpos($status, "TCP_") !== 0) {
            continue;
        }

        $hour = date("H", (int) $timestamp);

        if (isset($hours[$hour])) {
            $hours[$hour]++;
        }
    }

    fclose($handle);

    return $hours;
}

/* Proxy Activity - Hourly Detailed Statistics */

function proxy_activity_hourly_detail()
{
    $log_file = "/var/log/squid/access.log";

    $hours = array();

    for ($i = 23; $i >= 0; $i--) {

        $hour = date("H", time() - ($i * 3600));

        $hours[$hour] = array(
            "total" => 0,
            "denied" => 0,
            "https" => 0
        );
    }

    if (!file_exists($log_file)) {
        return $hours;
    }

    $handle = fopen($log_file, "r");

    if (!$handle) {
        return $hours;
    }

    $cutoff = time() - (24 * 60 * 60);

    while (($line = fgets($handle)) !== false) {

        $parts = preg_split('/\s+/', trim($line));

        if (!isset($parts[0], $parts[3])) {
            continue;
        }

        $timestamp = (float) $parts[0];

        if ($timestamp < $cutoff) {
            continue;
        }

        $status = $parts[3];

        if (strpos($status, "TCP_") !== 0) {
            continue;
        }

        $hour = date("H", (int) $timestamp);

        if (!isset($hours[$hour])) {
            continue;
        }

        $hours[$hour]["total"]++;

        if ($status === "TCP_DENIED/403") {
            $hours[$hour]["denied"]++;
        }

        if ($status === "TCP_TUNNEL/200") {
            $hours[$hour]["https"]++;
        }
    }

    fclose($handle);

    return $hours;
}



/* =========================
   Get Latest SARG Report
   ========================= */

function latest_sarg_report()
{
    $base = "/usr/local/www/sarg";

    $files = array();

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($base)
    );

    foreach ($iterator as $file) {

        if ($file->getFilename() == "topsites.html") {

            $files[] = $file->getPathname();

        }

    }

    if (empty($files)) {
        return false;
    }

    usort($files, function($a,$b){
        return filemtime($b) - filemtime($a);
    });

    return dirname($files[0]);
}

/* =========================
   Top SARG Users by Bytes
   ========================= */

function top_sarg_users($limit = 10)
{
    $latest_report = latest_sarg_report();

    if ($latest_report === false || !is_dir($latest_report)) {
        return array();
    }

    $users = array();

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(
            $latest_report,
            FilesystemIterator::SKIP_DOTS
        )
    );

    foreach ($iterator as $file) {

        if (!$file->isFile()) {
            continue;
        }

        $filename = $file->getFilename();

        if (preg_match("/^192_168_/", $filename)) {
            continue;
        }

        if ($filename === "tt.html" ||
            $filename === "graph.html" ||
            preg_match("/^d/", $filename)) {
            continue;
        }

        if (substr($filename, -5) !== ".html") {
            continue;
        }

        $html = @file_get_contents($file->getPathname());

        if ($html === false) {
            continue;
        }

        if (!preg_match(
            "/User:&nbsp;(.*?)<\\/td>/i",
            $html,
            $user_match
        )) {
            continue;
        }

        $username = trim(
            html_entity_decode(strip_tags($user_match[1]))
        );

        if ($username === "") {
            continue;
        }

        if (!preg_match(
            "/<tfoot>\\s*<tr>(.*?)<\\/tr>\\s*<\\/tfoot>/is",
            $html,
            $total_match
        )) {
            continue;
        }

        preg_match_all(
            "/<(?:th|td)[^>]*>(.*?)<\\/(?:th|td)>/is",
            $total_match[1],
            $cells
        );

        if (!isset($cells[1][2]) || !isset($cells[1][3])) {
            continue;
        }

        $requests = trim(strip_tags($cells[1][2]));
        $bytes_display = trim(strip_tags($cells[1][3]));
        $bytes = sarg_bytes_to_number($bytes_display);

        $site_count = preg_match_all(
            "/<td[^>]*class=[\"\x27]data2[\"\x27][^>]*>\\s*<a\\s+href=/i",
            $html,
            $site_matches
        );

        if ($site_count === false) {
            $site_count = 0;
        }

        if (!isset($users[$username])) {
            $users[$username] = array(
                "user" => $username,
                "bytes" => 0,
                "bytes_text" => "",
                "sites" => 0,
                "requests" => 0
            );
        }

        $users[$username]["bytes"] += $bytes;
        $users[$username]["sites"] += $site_count;
        $users[$username]["requests"] += sarg_number_to_number($requests);
        $users[$username]["bytes_text"] = $bytes_display;
    }

    $reports = array_values($users);

    usort($reports, function($a, $b) {
        return ($a["bytes"] < $b["bytes"]) ? 1 : -1;
    });

    return array_slice($reports, 0, $limit);
}
/* =========================
/* =========================
/* =========================
   Convert SARG Bytes
   ========================= */

/* =========================
   Convert SARG Number
   ========================= */

function sarg_number_to_number($value)
{
    $value = trim(strtoupper($value));

    if ($value === "") {
        return 0;
    }

    if (!preg_match("/^([0-9]+(?:\\.[0-9]+)?)\\s*([KMGT]?)$/", $value, $match)) {
        return 0;
    }

    $number = floatval($match[1]);
    $unit = isset($match[2]) ? $match[2] : "";

    switch ($unit) {
        case "K":
            return $number * 1000;
        case "M":
            return $number * 1000000;
        case "G":
            return $number * 1000000000;
        case "T":
            return $number * 1000000000000;
        default:
            return $number;
    }
}

function sarg_bytes_to_number($value)
{
    $value = trim(
        strtoupper($value)
    );

    if ($value === "") {
        return 0;
    }

    if (!preg_match(
        '/^([0-9]+(?:\.[0-9]+)?)\s*([KMGT]?)B?$/',
        $value,
        $match
    )) {
        return 0;
    }

    $number = floatval($match[1]);

    $unit = isset($match[2])
        ? $match[2]
        : "";

    switch ($unit) {

        case "K":
            $number *= 1024;
            break;

        case "M":
            $number *= 1024 * 1024;
            break;

        case "G":
            $number *= 1024 * 1024 * 1024;
            break;

        case "T":
            $number *= 1024 * 1024 * 1024 * 1024;
            break;
    }

    return $number;
}

?>
