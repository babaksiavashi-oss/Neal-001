<style>
.neal-topnav{direction:rtl;display:flex;align-items:center;gap:6px;padding:10px 18px;background:#fff;border-bottom:1px solid #e8ebf0;position:relative;z-index:1000;font-family:Vazirmatn,Tahoma,sans-serif}
.neal-topnav-brand{margin-left:10px;font-weight:700;color:#172033;text-decoration:none;padding:10px 12px}
.neal-topnav>a,.neal-nav-group>button{border:0;background:transparent;text-decoration:none;color:#344054;font:inherit;font-size:14px;padding:10px 13px;border-radius:10px;cursor:pointer;white-space:nowrap}
.neal-topnav>a:hover,.neal-nav-group:hover>button{background:#f4f6f8;color:#111827}
.neal-nav-group{position:relative}
.neal-nav-dropdown{position:absolute;right:0;top:calc(100% + 7px);min-width:245px;background:#fff;border:1px solid #e7e9ee;border-radius:14px;box-shadow:0 14px 35px rgba(16,24,40,.12);padding:8px;opacity:0;visibility:hidden;transform:translateY(-5px);transition:.16s ease}
.neal-nav-group:hover .neal-nav-dropdown,.neal-nav-group:focus-within .neal-nav-dropdown{opacity:1;visibility:visible;transform:translateY(0)}
.neal-nav-dropdown a{display:flex;align-items:center;gap:10px;padding:11px 12px;border-radius:9px;color:#344054;text-decoration:none;font-size:13px}
.neal-nav-dropdown a:hover{background:#f5f7fa;color:#111827}
.neal-nav-divider{height:1px;background:#eef0f3;margin:6px 4px}
@media(max-width:800px){.neal-topnav{overflow-x:auto;justify-content:flex-start}.neal-nav-dropdown{position:fixed;right:12px;top:62px}}
</style>
<nav class="neal-topnav">
<a class="neal-topnav-brand" href="index.php">NEAL</a>
<a href="index.php">خانه</a>
<div class="neal-nav-group">
<button type="button">مدیریت اعلان‌ها⌄</button>
<div class="neal-nav-dropdown">
<a href="login-messages.php">💬 مدیریت پیام همکاران</a>
<a href="announcements.php">📢 مدیریت اطلاعیه‌های شرکت</a>
<a href="occasions.php">📅 مدیریت مناسبت‌ها</a>
<div class="neal-nav-divider"></div>
<a href="announcements-list.php">👁 مشاهده اطلاعیه‌ها</a>
</div>
</div>
<div class="neal-nav-group">
<button type="button">مدیریت سیستم⌄</button>
<div class="neal-nav-dropdown">
<a href="users.php">👥 کاربران</a>
<a href="settings.php">⚙️ تنظیمات</a>
<a href="cache.php">⚡ کش</a>
<a href="backup.php">💾 پشتیبان‌گیری</a>
<a href="logs.php">📄 لاگ‌ها</a>
</div>
</div>
<div class="neal-nav-group">
<button type="button">گزارش و درخواست‌ها⌄</button>
<div class="neal-nav-dropdown">
<a href="tickets.php">🎫 درخواست‌ها</a>
<a href="reports.php">📊 گزارش‌ها</a>
<a href="blocked.php">🚫 لیست مسدود شده‌ها</a>
</div>
</div>
</nav>