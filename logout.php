<?php

require_once __DIR__ . "/auth/auth.php";

logout();

header("Location: /neal/login.php");

exit;

?>
