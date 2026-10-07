<!DOCTYPE html>
<html lang="fa" dir="rtl">

<head>

<meta charset="UTF-8">

<title>
<?php
echo isset($page_title)
? "NEAL Proxy Manager - " . $page_title
: "NEAL Proxy Manager";
?>
</title>

<link rel="stylesheet" href="css/style.css">\n<style>\n.portal-body{display:block!important;width:100%;}\n.content{width:100%;box-sizing:border-box;}\n.sidebar{width:100%!important;box-sizing:border-box;}\n</style>

</head>


<body>


<div class="topbar">


    <img src="images/logo.png" class="logo">


    <div class="title">

        NEAL Proxy Manager

        <span>Squid Proxy Management System</span>

    </div>


    <div class="user-menu">

        <?php

        if(isset($_SESSION['username'])) {

            echo "👤 " . htmlspecialchars($_SESSION['username']);

            echo ' | <a href="/neal/logout.php">↪ خروج</a>';

        }

        ?>

    </div>


</div>


<div class="portal-body">

<?php if (isset($portal_mode) && $portal_mode === true) { include "user-menu.php"; } else { include "menu.php"; } ?>


<div class="content">
