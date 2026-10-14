<?php
require_once __DIR__ . '/auth/auth.php';
require_once __DIR__ . '/config/config.php';
check_login();

$db = new PDO('sqlite:' . __DIR__ . '/data/neal.db', null, null, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
]);

function itr_h($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function itr_duration($seconds) {
    if ($seconds === null || $seconds === '' || !is_numeric($seconds) || (float)$seconds < 0) return '—';
    $s = (int)$seconds;
    $days = intdiv($s, 86400); $s %= 86400;
    $hours = intdiv($s, 3600); $s %= 3600;
    $minutes = intdiv($s, 60);
    if ($days) return $days . ' روز و ' . $hours . ' ساعت';
    if ($hours) return $hours . ' ساعت و ' . $minutes . ' دقیقه';
    return $minutes . ' دقیقه';
}
function itr_jdate($value) {
    if (!$value || !($ts = strtotime($value))) return '—';
    $gy=(int)date('Y',$ts); $gm=(int)date('n',$ts); $gd=(int)date('j',$ts);
    $gdm=[0,31,59,90,120,151,181,212,243,273,304,334];
    if($gy>1600){$jy=979;$gy-=1600;}else{$jy=0;$gy-=621;}
    $gy2=$gm>2?$gy+1:$gy;
    $days=365*$gy+(int)(($gy2+3)/4)-(int)(($gy2+99)/100)+(int)(($gy2+399)/400)-80+$gd+$gdm[$gm-1];
    $jy+=33*(int)($days/12053);$days%=12053;$jy+=4*(int)($days/1461);$days%=1461;
    if($days>365){$jy+=(int)(($days-1)/365);$days=($days-1)%365;}
    if($days<186){$jm=1+(int)($days/31);$jd=1+$days%31;}else{$jm=7+(int)(($days-186)/30);$jd=1+(($days-186)%30);}
    return sprintf('%04d/%02d/%02d %s',$jy,$jm,$jd,date('H:i',$ts));
}
function itr_table_exists(PDO $db, string $table): bool {
    $s=$db->prepare("SELECT 1 FROM sqlite_master WHERE type='table' AND name=?");
    $s->execute([$table]); return (bool)$s->fetchColumn();
}

$from = trim((string)($_GET['from'] ?? date('Y-m-01')));
$to = trim((string)($_GET['to'] ?? date('Y-m-d')));
$typeId = (int)($_GET['type_id'] ?? 0);
$department = trim((string)($_GET['department'] ?? ''));
$assigneeId = (int)($_GET['assignee'] ?? 0);
$priority = trim((string)($_GET['priority'] ?? ''));
$validDate = static function($v) { return preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) === 1; };
if (!$validDate($from)) $from = date('Y-m-01');
if (!$validDate($to)) $to = date('Y-m-d');
if ($from > $to) { $swap=$from; $from=$to; $to=$swap; }

$types = $db->query("SELECT id, name FROM request_types WHERE is_active=1 ORDER BY name")->fetchAll();
$admins = $db->query("SELECT id, username FROM admins WHERE COALESCE(is_active,1)=1 ORDER BY username")->fetchAll();
$departments = $db->query("SELECT DISTINCT department FROM service_requests WHERE department IS NOT NULL AND department<>'' ORDER BY department")->fetchAll();
$departmentLabels = [
'management'=>'واحد مدیریت','regulatory'=>'واحد مسئول فنی و رگولاتوری','administrative'=>'واحد اداری',
'engineering'=>'واحد فنی مهندسی','finance'=>'واحد مالی','planning'=>'واحد برنامه ریزی و انبار',
'quality'=>'واحد آزمایشگاه کنترل کیفیت','commercial'=>'واحد بازرگانی','production'=>'واحد تولید','hse'=>'واحد ایمنی و بهداشت'
];
$priorityLabels=['normal'=>'عادی','important'=>'مهم','urgent'=>'فوری','critical'=>'بحرانی'];
$statusLabels=['new'=>'جدید','assigned'=>'ارجاع‌شده','in_progress'=>'در حال بررسی','waiting_user'=>'منتظر پاسخ کاربر','resolved'=>'حل‌شده','closed'=>'بسته‌شده','cancelled'=>'لغوشده'];

$where = ["date(sr.created_at) BETWEEN date(?) AND date(?)"];
$params = [$from,$to];
if ($typeId > 0) {
    $where[] = "EXISTS (SELECT 1 FROM service_request_types sx WHERE sx.request_id=sr.id AND sx.request_type_id=?)";
    $params[] = $typeId;
}
if ($department !== '') { $where[]="sr.department=?"; $params[]=$department; }
if ($assigneeId > 0) { $where[]="sr.assigned_to=?"; $params[]=$assigneeId; }
if (isset($priorityLabels[$priority])) { $where[]="sr.priority=?"; $params[]=$priority; }
$whereSql=implode(' AND ',$where);
$closedExpr = "COALESCE(sr.closed_at, sr.resolved_at, (SELECT MAX(h.created_at) FROM ticket_status_history h WHERE h.request_id=sr.id AND h.new_status IN ('closed','resolved')))";
$baseSql = "SELECT sr.*, a.username AS assigned_username, $closedExpr AS report_closed_at,
 (SELECT GROUP_CONCAT(DISTINCT rt.name) FROM service_request_types st JOIN request_types rt ON rt.id=st.request_type_id WHERE st.request_id=sr.id) AS request_types
 FROM service_requests sr LEFT JOIN admins a ON a.id=sr.assigned_to WHERE $whereSql";
$stmt=$db->prepare($baseSql . " ORDER BY sr.created_at DESC, sr.id DESC"); $stmt->execute($params); $tickets=$stmt->fetchAll();

$total=count($tickets); $open=0; $closed=0; $durations=[]; $longest=null; $shortest=null;
foreach($tickets as &$t) {
    $isClosed=in_array($t['status'],['closed','resolved'],true);
    if($isClosed) {
        $closed++;
        $start=strtotime((string)$t['created_at']); $end=strtotime((string)$t['report_closed_at']);
        if($start && $end && $end >= $start) {
            $t['_duration']=$end-$start; $durations[]=$t['_duration'];
            if($longest===null || $t['_duration']>$longest['_duration']) $longest=$t;
            if($shortest===null || $t['_duration']<$shortest['_duration']) $shortest=$t;
        } else $t['_duration']=null;
    } else { $open++; $t['_duration']=null; }
}
unset($t);
$avgDuration=count($durations)?array_sum($durations)/count($durations):null;
$stale=0; foreach($tickets as $t) if(!in_array($t['status'],['closed','resolved','cancelled'],true) && strtotime((string)$t['created_at']) < strtotime('-7 days')) $stale++;

$byTech=[];
foreach($admins as $a) $byTech[(int)$a['id']]=['id'=>(int)$a['id'],'name'=>$a['username'],'total'=>0,'closed'=>0,'open'=>0,'durations'=>[],'urgent'=>0];
$byTech[0]=['id'=>0,'name'=>'بدون کارشناس تخصیص‌یافته','total'=>0,'closed'=>0,'open'=>0,'durations'=>[],'urgent'=>0];
$byType=[]; $byDept=[]; $byPriority=[]; $byMonth=[];
foreach($tickets as $t) {
    $aid=(int)($t['assigned_to'] ?? 0);
    if(!isset($byTech[$aid])) $byTech[$aid]=['id'=>$aid,'name'=>$t['assigned_username'] ?: 'کارشناس حذف‌شده','total'=>0,'closed'=>0,'open'=>0,'durations'=>[],'urgent'=>0];
    $byTech[$aid]['total']++;
    if(in_array($t['status'],['closed','resolved'],true)) {$byTech[$aid]['closed']++; if($t['_duration']!==null)$byTech[$aid]['durations'][]=$t['_duration'];}
    elseif($t['status']!=='cancelled')$byTech[$aid]['open']++;
    if(in_array($t['priority'],['urgent','critical'],true))$byTech[$aid]['urgent']++;
    $dept=$t['department'] ?: 'نامشخص'; $byDept[$dept]=($byDept[$dept]??0)+1;
    $pr=$t['priority'] ?: 'normal'; $byPriority[$pr]=($byPriority[$pr]??0)+1;
    $month=substr((string)$t['created_at'],0,7); if($month!=='')$byMonth[$month]=($byMonth[$month]??0)+1;
    $names=trim((string)$t['request_types'])!==''?explode(',',(string)$t['request_types']):['بدون نوع'];
    foreach($names as $name){$name=trim($name);$byType[$name]=($byType[$name]??0)+1;}
}
foreach($byTech as &$tech){$tech['avg']=count($tech['durations'])?array_sum($tech['durations'])/count($tech['durations']):null;} unset($tech);
uasort($byTech,static function($a,$b){return $b['total']<=>$a['total'];});
arsort($byType); arsort($byDept); ksort($byMonth);
$page_title='گزارش کارکرد واحد انفورماتیک';

// صفحه‌بندی فقط روی جدول جزئیات اعمال می‌شود؛ آمار بالا بر اساس همه نتایج فیلترشده است.
$perPage = 10;
$totalTickets = count($tickets);
$totalPages = max(1, (int)ceil($totalTickets / $perPage));
$page = max(1, min($totalPages, (int)($_GET['page'] ?? 1)));
$tickets = array_slice($tickets, ($page - 1) * $perPage, $perPage);

require_once __DIR__ . '/includes/header.php';
?>
<style>
.it-report{direction:rtl;color:#243247;font-family:Vazirmatn,Tahoma,sans-serif}.it-head{display:flex;justify-content:space-between;align-items:flex-start;gap:15px;margin:10px 0 22px}.it-head h1{font-size:25px;margin:0 0 6px}.it-muted{color:#778397;font-size:12px}.it-actions{display:flex;gap:8px;flex-wrap:wrap}.it-btn{border:1px solid #dbe2ea;background:#fff;color:#344054;border-radius:8px;padding:9px 13px;text-decoration:none;font:inherit;font-size:12px;cursor:pointer}.it-btn.primary{background:#183b5b;color:#fff;border-color:#183b5b}.it-filters,.it-card{background:#fff;border:1px solid #e4e9ef;border-radius:13px;padding:17px;margin-bottom:16px;box-shadow:0 3px 12px rgba(16,24,40,.025)}.it-filters form{display:grid;grid-template-columns:repeat(6,minmax(120px,1fr));gap:12px;align-items:end}.it-field{display:flex;flex-direction:column;gap:6px}.it-field label{font-size:12px;color:#667085}.it-field input,.it-field select{width:100%;box-sizing:border-box;min-height:39px;border:1px solid #d0d7e2;border-radius:8px;padding:8px;font:inherit;font-size:12px;background:#fff}.it-kpis{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:12px;margin-bottom:16px}.it-kpi{background:#fff;border:1px solid #e4e9ef;border-radius:12px;padding:16px;min-height:95px}.it-kpi span{display:block;font-size:12px;color:#778397;margin-bottom:10px}.it-kpi strong{font-size:22px;color:#183b5b}.it-grid{display:grid;grid-template-columns:1.15fr 1fr;gap:16px}.it-card h2{font-size:15px;margin:0 0 14px}.it-table-wrap{overflow:auto}.it-table{width:100%;border-collapse:collapse;min-width:690px}.it-table th,.it-table td{text-align:right;border-bottom:1px solid #edf0f4;padding:10px 9px;font-size:12px;white-space:nowrap}.it-table th{background:#f7f9fb;color:#5d6878;font-weight:700}.it-table tr:last-child td{border-bottom:0}.it-bar-row{display:grid;grid-template-columns:minmax(100px,1.1fr) 2fr 38px;align-items:center;gap:9px;margin:12px 0;font-size:12px}.it-bar{height:9px;background:#eef1f5;border-radius:20px;overflow:hidden}.it-bar i{display:block;height:100%;background:linear-gradient(90deg,#6da8d6,#183b5b);border-radius:20px}.it-pill{display:inline-block;padding:4px 7px;border-radius:6px;background:#f0f5fa;color:#34536e;font-size:11px}.it-note{background:#f8fafc;border:1px dashed #d6dee8;border-radius:9px;padding:11px 13px;color:#667085;font-size:11px;line-height:1.9}.it-link{color:#1b6090;text-decoration:none;font-weight:700}.it-link:hover{text-decoration:underline}.it-detail{max-width:100%;overflow:auto}.it-detail table{min-width:1050px}.it-pagination{display:flex;align-items:center;justify-content:center;gap:7px;flex-wrap:wrap;margin-top:16px}.it-pagination .it-btn{display:inline-flex;align-items:center;justify-content:center;min-width:36px;box-sizing:border-box}.it-pagination .it-page-label{font-size:12px;color:#667085}.it-empty{text-align:center!important;color:#98a2b3;padding:24px!important}@media(max-width:1100px){.it-kpis{grid-template-columns:repeat(3,1fr)}.it-filters form{grid-template-columns:repeat(3,minmax(0,1fr))}.it-grid{grid-template-columns:1fr}}@media(max-width:650px){.it-kpis{grid-template-columns:repeat(2,1fr)}.it-filters form{grid-template-columns:repeat(2,minmax(0,1fr))}.it-head{flex-direction:column}.it-head h1{font-size:21px}}@media print{.neal-topnav,.it-filters,.it-actions,.it-no-print{display:none!important}.it-card,.it-kpi{break-inside:avoid;box-shadow:none}.it-report{font-size:10px}}
</style>
<div class="it-report">
 <div class="it-head"><div><h1>📈 گزارش کارکرد واحد انفورماتیک</h1><div class="it-muted">تحلیل درخواست‌ها، زمان رسیدگی و مقایسه عملکرد کارشناسان در بازه انتخابی</div></div><div class="it-actions"><button class="it-btn" onclick="window.print()">🖨 چاپ گزارش</button></div></div>
 <section class="it-filters"><form method="get">
 <div class="it-field"><label for="from">از تاریخ ثبت</label><input type="date" id="from" name="from" value="<?php echo itr_h($from); ?>"></div>
 <div class="it-field"><label for="to">تا تاریخ ثبت</label><input type="date" id="to" name="to" value="<?php echo itr_h($to); ?>"></div>
 <div class="it-field"><label for="type_id">نوع درخواست</label><select id="type_id" name="type_id"><option value="0">همه انواع درخواست</option><?php foreach($types as $type): ?><option value="<?php echo (int)$type['id']; ?>" <?php echo $typeId===(int)$type['id']?'selected':''; ?>><?php echo itr_h($type['name']); ?></option><?php endforeach; ?></select></div>
 <div class="it-field"><label for="department">واحد درخواست‌کننده</label><select id="department" name="department"><option value="">همه واحدها</option><?php foreach($departments as $d): $code=$d['department']; ?><option value="<?php echo itr_h($code); ?>" <?php echo $department===$code?'selected':''; ?>><?php echo itr_h($departmentLabels[$code]??$code); ?></option><?php endforeach; ?></select></div>
 <div class="it-field"><label for="assignee">کارشناس فعلی</label><select id="assignee" name="assignee"><option value="0">همه کارشناسان</option><?php foreach($admins as $a): ?><option value="<?php echo (int)$a['id']; ?>" <?php echo $assigneeId===(int)$a['id']?'selected':''; ?>><?php echo itr_h($a['username']); ?></option><?php endforeach; ?></select></div>
 <div class="it-field"><label for="priority">اولویت</label><select id="priority" name="priority"><option value="">همه اولویت‌ها</option><?php foreach($priorityLabels as $key=>$label): ?><option value="<?php echo itr_h($key); ?>" <?php echo $priority===$key?'selected':''; ?>><?php echo itr_h($label); ?></option><?php endforeach; ?></select></div>
 <div class="it-actions"><button class="it-btn primary" type="submit">اعمال فیلتر</button><a class="it-btn" href="it-performance-report.php">پاک‌کردن</a></div>
 </form></section>
 <div class="it-kpis">
 <div class="it-kpi"><span>کل درخواست‌های ثبت‌شده</span><strong><?php echo number_format($total); ?></strong></div>
 <div class="it-kpi"><span>بسته / حل‌شده</span><strong><?php echo number_format($closed); ?></strong></div>
 <div class="it-kpi"><span>باز و در جریان</span><strong><?php echo number_format($open); ?></strong></div>
 <div class="it-kpi"><span>میانگین زمان حل</span><strong style="font-size:16px"><?php echo itr_h(itr_duration($avgDuration)); ?></strong></div>
 <div class="it-kpi"><span>بازِ قدیمی‌تر از ۷ روز</span><strong><?php echo number_format($stale); ?></strong></div>
 </div>
 <div class="it-grid">
 <section class="it-card"><h2>👨‍💻 مقایسه عملکرد کارشناسان</h2><div class="it-note">مبنای انتساب هر درخواست، کارشناس فعلیِ ثبت‌شده روی درخواست است. زمان حل، از ثبت درخواست تا بسته‌شدن محاسبه می‌شود؛ این عدد زمان کار خالص هر کارشناس نیست.</div><div class="it-table-wrap"><table class="it-table"><thead><tr><th>کارشناس</th><th>ارجاع‌شده</th><th>بسته‌شده</th><th>باز</th><th>میانگین زمان حل</th><th>فوری/بحرانی</th></tr></thead><tbody><?php foreach($byTech as $tech): ?><tr><td><?php echo itr_h($tech['name']); ?></td><td><?php echo number_format($tech['total']); ?></td><td><?php echo number_format($tech['closed']); ?></td><td><?php echo number_format($tech['open']); ?></td><td><?php echo itr_h(itr_duration($tech['avg'])); ?></td><td><?php echo number_format($tech['urgent']); ?></td></tr><?php endforeach; ?></tbody></table></div></section>
 <section class="it-card"><h2>🏢 بیشترین درخواست به تفکیک واحد</h2><?php if(!$byDept): ?><div class="it-empty">داده‌ای در این بازه وجود ندارد.</div><?php else: $max=max($byDept); foreach(array_slice($byDept,0,8,true) as $name=>$count): ?><div class="it-bar-row"><span><?php echo itr_h($departmentLabels[$name]??$name); ?></span><div class="it-bar"><i style="width:<?php echo max(2,($count/$max)*100); ?>%"></i></div><strong><?php echo number_format($count); ?></strong></div><?php endforeach; endif; ?></section>
 <section class="it-card"><h2>🧩 نوع درخواست</h2><?php if(!$byType): ?><div class="it-empty">داده‌ای در این بازه وجود ندارد.</div><?php else: $max=max($byType); foreach(array_slice($byType,0,10,true) as $name=>$count): ?><div class="it-bar-row"><span><?php echo itr_h($name); ?></span><div class="it-bar"><i style="width:<?php echo max(2,($count/$max)*100); ?>%"></i></div><strong><?php echo number_format($count); ?></strong></div><?php endforeach; endif; ?></section>
 <section class="it-card"><h2>🚦 تفکیک بر اساس اولویت</h2><?php if(!$byPriority): ?><div class="it-empty">داده‌ای در این بازه وجود ندارد.</div><?php else: $max=max($byPriority); foreach($priorityLabels as $key=>$label): if(empty($byPriority[$key]))continue; ?><div class="it-bar-row"><span><?php echo itr_h($label); ?></span><div class="it-bar"><i style="width:<?php echo max(2,($byPriority[$key]/$max)*100); ?>%"></i></div><strong><?php echo number_format($byPriority[$key]); ?></strong></div><?php endforeach; endif; ?></section>
 <section class="it-card"><h2>📆 روند ماهانه ثبت درخواست</h2><?php if(!$byMonth): ?><div class="it-empty">داده‌ای در این بازه وجود ندارد.</div><?php else: $max=max($byMonth); foreach($byMonth as $month=>$count): ?><div class="it-bar-row"><span><?php echo itr_h($month); ?></span><div class="it-bar"><i style="width:<?php echo max(2,($count/$max)*100); ?>%"></i></div><strong><?php echo number_format($count); ?></strong></div><?php endforeach; endif; ?></section>
 <section class="it-card"><h2>⏱ سریع‌ترین و طولانی‌ترین زمان حل</h2><?php foreach([['عنوان'=>'سریع‌ترین','ticket'=>$shortest],['عنوان'=>'طولانی‌ترین','ticket'=>$longest]] as $item): ?><div style="padding:12px 0;border-bottom:1px solid #edf0f4"><div class="it-muted"><?php echo $item['عنوان']; ?></div><?php if($item['ticket']): ?><a class="it-link" href="ticket-manage.php?id=<?php echo (int)$item['ticket']['id']; ?>"><?php echo itr_h($item['ticket']['tracking_number'].' — '.$item['ticket']['subject']); ?></a><div style="margin-top:6px;font-size:13px;font-weight:700"><?php echo itr_h(itr_duration($item['ticket']['_duration'])); ?></div><?php else: ?><div class="it-muted">در این بازه درخواست بسته‌شده با تاریخ معتبر وجود ندارد.</div><?php endif; ?></div><?php endforeach; ?></section>
 </div>
 <section class="it-card"><h2>📋 جزئیات درخواست‌ها <span class="it-muted">نمایش <?php echo $totalTickets ? number_format(($page - 1) * $perPage + 1) . ' تا ' . number_format(($page - 1) * $perPage + count($tickets)) : '۰'; ?> از <?php echo number_format($totalTickets); ?> درخواست</span> <span class="it-muted">(برای مشاهده پرونده، روی شماره پیگیری کلیک کنید)</span></h2><div class="it-table-wrap it-detail"><table class="it-table"><thead><tr><th>شماره پیگیری</th><th>موضوع</th><th>درخواست‌کننده</th><th>واحد</th><th>نوع</th><th>اولویت</th><th>وضعیت</th><th>کارشناس فعلی</th><th>ثبت</th><th>بسته‌شدن</th><th>مدت حل</th></tr></thead><tbody><?php if(!$tickets): ?><tr><td class="it-empty" colspan="11">برای فیلترهای انتخاب‌شده درخواستی پیدا نشد.</td></tr><?php else: foreach($tickets as $t): ?><tr><td><a class="it-link" href="ticket-manage.php?id=<?php echo (int)$t['id']; ?>"><?php echo itr_h($t['tracking_number']); ?></a></td><td><?php echo itr_h($t['subject']); ?></td><td><?php echo itr_h($t['fullname']); ?></td><td><?php echo itr_h($departmentLabels[$t['department']]??$t['department']); ?></td><td><?php echo itr_h($t['request_types']??'—'); ?></td><td><span class="it-pill"><?php echo itr_h($priorityLabels[$t['priority']]??$t['priority']); ?></span></td><td><?php echo itr_h($statusLabels[$t['status']]??$t['status']); ?></td><td><?php echo itr_h($t['assigned_username']??'—'); ?></td><td><?php echo itr_h(itr_jdate($t['created_at'])); ?></td><td><?php echo itr_h(itr_jdate($t['report_closed_at'])); ?></td><td><?php echo itr_h(itr_duration($t['_duration'])); ?></td></tr><?php endforeach; endif; ?></tbody></table></div>
 <?php if ($totalPages > 1): $pageQuery = $_GET; unset($pageQuery['page'], $pageQuery['export']); ?>
 <nav class="it-pagination it-no-print" aria-label="صفحه‌بندی جزئیات درخواست‌ها">
 <?php if ($page > 1): $pageQuery['page'] = $page - 1; ?><a class="it-btn" href="?<?php echo itr_h(http_build_query($pageQuery)); ?>">← قبلی</a><?php endif; ?>
 <span class="it-page-label">صفحه <?php echo number_format($page); ?> از <?php echo number_format($totalPages); ?></span>
 <?php for ($p = max(1, $page - 2); $p <= min($totalPages, $page + 2); $p++): $pageQuery['page'] = $p; if ($p === $page): ?><span class="it-btn primary"><?php echo number_format($p); ?></span><?php else: ?><a class="it-btn" href="?<?php echo itr_h(http_build_query($pageQuery)); ?>"><?php echo number_format($p); ?></a><?php endif; endfor; ?>
 <?php if ($page < $totalPages): $pageQuery['page'] = $page + 1; ?><a class="it-btn" href="?<?php echo itr_h(http_build_query($pageQuery)); ?>">بعدی →</a><?php endif; ?>
 </nav><?php endif; ?>
 </section>
 <div class="it-note">بازه فیلتر بر اساس تاریخ ثبت درخواست است. درخواست‌های لغوشده در آمار «بسته/حل‌شده» و میانگین زمان حل شمرده نمی‌شوند. «باز قدیمی‌تر از ۷ روز» درخواست‌های هنوز باز در همین بازه را نشان می‌دهد.</div>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
