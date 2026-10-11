<?php

// NEAL Proxy Manager Configuration


// Squid

define("SQUID_SERVICE", "squid");


// Squid Block List

define(
    "BLOCK_LIST",
    "/usr/local/etc/squid/blocked_sites.txt"
);


// SARG Reports

define(
    "SARG_PATH",
    "/usr/local/www/sarg"
);


// Squid Logs

define(
    "SQUID_LOG",
    "/var/log/squid/access.log"
);


// System Name

define(
    "SYSTEM_NAME",
    "NEAL Proxy Manager"
);

?>
