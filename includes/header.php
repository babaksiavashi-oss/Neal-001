<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo isset($page_title) ? "NEAL Proxy Manager - " . $page_title : "NEAL Proxy Manager"; ?></title>
<link rel="stylesheet" href="css/style.css">
<style>
.portal-body{display:block!important;width:100%;min-height:calc(100vh - 125px);}
.content{width:100%;box-sizing:border-box;max-width:100%;padding-top:24px;}
.neal-header-bar{width:100%;min-height:76px;display:flex;align-items:center;padding:10px 28px;background:#0b1f3a;color:#fff;gap:18px}
.neal-header-brand{display:flex;align-items:center;gap:14px;min-width:0}
.neal-header-brand img{width:110px;height:54px;object-fit:contain}
.neal-header-title{font-size:18px;font-weight:700;white-space:nowrap}
.neal-header-title span{display:block;margin-top:2px;font-size:10px;font-weight:400;opacity:.72}
.neal-header-user{margin-right:auto;display:flex;align-items:center;gap:12px;font-size:12px;white-space:nowrap}
.neal-header-user a{color:#fff;text-decoration:none;padding:7px 11px;border-radius:8px;background:rgba(255,255,255,.08)}
.neal-header-user a:hover{background:rgba(255,255,255,.15)}
@media(max-width:700px){.neal-header-bar{padding:9px 14px}.neal-header-brand img{width:78px;height:44px}.neal-header-title{font-size:15px}.neal-header-title span{display:none}.neal-header-user{font-size:10px}}
</style>
</head>
<body>
<header class="neal-header-bar">
<div class="neal-header-brand">
<img src="images/logo.png" class="logo" alt="NEAL Pharmed">
<div class="neal-header-title">NEAL Proxy Manager<span>Squid Proxy Management System</span></div>
</div>
<div class="neal-header-user">
<?php if(isset($_SESSION['username'])): ?>
<span>👤 <?php echo htmlspecialchars($_SESSION['username'], ENT_QUOTES, 'UTF-8'); ?></span>
<a href="/neal/logout.php">↪ خروج</a>
<?php endif; ?>
</div>
</header>
<div class="portal-body">
<?php
if (isset($portal_mode) && $portal_mode === true) {
    include "user-menu.php";
} else {
    include "menu.php";
}
?>
<div class="content">